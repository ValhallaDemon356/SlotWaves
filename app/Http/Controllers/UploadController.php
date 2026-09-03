<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Services\PdfParser;
use App\Services\FlightScheduleValidator;
use App\Services\TimelineEngine;
use App\Services\Dau\TemplateValidator;
use App\Services\Dau\ReportTemplateRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class UploadController extends Controller
{
    /**
     * Initial landing page — ALWAYS renders the Unified Upload Portal.
     */
    public function index()
    {
        $activeUpload = null;
        try {
            $activeUploadId = session('active_upload_id');
            if ($activeUploadId) {
                $activeUpload = Upload::where('id', $activeUploadId)
                    ->where('status', 'completed')
                    ->first();
            }
        } catch (\Throwable $e) {
            // Graceful fallback
        }

        $reportTypesGrouped = ReportTemplateRegistry::grouped();
        $allReportTypes     = ReportTemplateRegistry::all();

        return view('home', compact('activeUpload', 'reportTypesGrouped', 'allReportTypes'));
    }

    /**
     * Dedicated Upload Portal entry point.
     */
    public function uploadPage()
    {
        return $this->index();
    }

    /**
     * /dashboard shortcut route — redirects to active session schedule or falls back to Upload Portal.
     */
    public function dashboardRedirect()
    {
        $activeUploadId = session('active_upload_id');
        if ($activeUploadId) {
            $upload = Upload::where('id', $activeUploadId)
                ->where('status', 'completed')
                ->first();

            if ($upload) {
                if ($upload->report_type === 'slot_schedule' || empty($upload->report_type)) {
                    return redirect()->route('schedule.dashboard', $upload->id);
                }
                return redirect()->route('dau.dashboard', $upload->id);
            }
        }

        return redirect()->route('home');
    }

    /**
     * Reset / New Import — clears active session and returns to Upload Portal.
     */
    public function resetSession()
    {
        session()->forget('active_upload_id');
        return redirect()->route('home');
    }

    /**
     * Interactive template pre-validation endpoint.
     */
    public function validateTemplate(Request $request)
    {
        $reportType = $request->input('report_type', 'slot_schedule');
        $file = $request->file('file') ?? $request->file('schedule_pdf') ?? $request->file('uploaded_file');

        if (!$file) {
            return response()->json([
                'valid'            => false,
                'detectedTemplate' => 'None',
                'expectedTemplate' => $reportType,
                'errors'           => ['No file provided for template validation.'],
                'warnings'         => [],
            ], 422);
        }

        $validator = new TemplateValidator();
        $result = $validator->validate($reportType, $file);

        $status = $result['valid'] ? 200 : 422;
        return response()->json($result, $status);
    }

    /**
     * Store and stage uploaded file according to selected report type.
     */
    public function store(Request $request)
    {
        $reportType = $request->input('report_type', 'slot_schedule');

        // ═════════════════════════════════════════════════════════════════════
        // PIPELINE 1: AIRPORT SLOT SCHEDULE (100% PRESERVED EXISTING WORKFLOW)
        // ═════════════════════════════════════════════════════════════════════
        if ($reportType === 'slot_schedule') {
            $request->validate([
                'schedule_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            ]);

            $file = $request->file('schedule_pdf');
            $filename = $file->getClientOriginalName();

            // Idempotency check: if identical filename was completed within last 2 minutes, reuse existing upload
            $recentUpload = Upload::where('original_filename', $filename)
                ->where('status', 'completed')
                ->where('report_type', 'slot_schedule')
                ->where('created_at', '>=', now()->subMinutes(2))
                ->latest('id')
                ->first();

            if ($recentUpload) {
                session(['active_upload_id' => $recentUpload->id]);
                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success'      => true,
                        'upload_id'    => $recentUpload->id,
                        'report_type'  => 'slot_schedule',
                        'status'       => 'completed',
                        'redirect_url' => route('schedule.dashboard', $recentUpload->id),
                        'message'      => 'Reusing recently completed schedule.',
                    ]);
                }
                return redirect()->route('schedule.dashboard', $recentUpload->id);
            }

            // Store using the defined disk
            $storedPath = $file->store('uploads', 'local');
            $season = preg_match('/winter/i', $filename) ? 'winter' : 'summer';
            
            // Match airport code from filename (e.g. BDO, CGK, HLP, KJT)
            $airportId = null;
            if (preg_match('/\b([A-Z]{3,4})\b/i', $filename, $m)) {
                $airport = \App\Models\Airport::findByIata(strtoupper($m[1]));
                if ($airport) {
                    $airportId = $airport->id;
                }
            }
            if (!$airportId) {
                $bdo = \App\Models\Airport::findByIata('BDO');
                $airportId = $bdo?->id;
            }

            $upload = Upload::create([
                'original_filename' => $filename,
                'stored_path'       => $storedPath,
                'report_type'       => 'slot_schedule',
                'status'            => 'pending',
                'season'            => $season,
                'airport_id'        => $airportId,
            ]);

            // If AJAX / JSON upload (from interactive staged frontend), return immediately
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'      => true,
                    'upload_id'    => $upload->id,
                    'report_type'  => 'slot_schedule',
                    'status'       => 'pending',
                    'process_url'  => route('upload.process', $upload->id),
                    'status_url'   => route('upload.status', $upload->id),
                    'redirect_url' => route('schedule.dashboard', $upload->id),
                    'message'      => 'Schedule PDF uploaded and staged. Ready for processing.',
                ]);
            }

            // Traditional synchronous fallback for standard non-JS form post
            return $this->executeProcessing($upload);
        }

        // ═════════════════════════════════════════════════════════════════════
        // PIPELINE 2: DAU TYPE-SPECIFIC REPORT INGESTION
        // ═════════════════════════════════════════════════════════════════════
        $conf = ReportTemplateRegistry::find($reportType);
        if (!$conf) {
            return response()->json([
                'success' => false,
                'error'   => "Unsupported report type: {$reportType}",
            ], 422);
        }

        $file = $request->file('dau_file') ?? $request->file('uploaded_file') ?? $request->file('file') ?? $request->file('schedule_pdf');
        if (!$file) {
            return response()->json([
                'success' => false,
                'error'   => 'No file provided for upload.',
            ], 422);
        }

        // Strict template validation by content
        $validator = new TemplateValidator();
        $validationResult = $validator->validate($reportType, $file);

        if (!$validationResult['valid']) {
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'    => false,
                    'error'      => implode('; ', $validationResult['errors']),
                    'validation' => $validationResult,
                ], 422);
            }
            return redirect()->route('home')->withErrors([
                'dau' => implode('; ', $validationResult['errors'])
            ]);
        }

        $filename = $file->getClientOriginalName();
        $storedPath = $file->store('uploads', 'local');

        // Resolve airport from meta or default CGK
        $airportCode = $validationResult['meta']['airport_code'] ?? 'CGK';
        $airport = \App\Models\Airport::findByIata($airportCode) ?? \App\Models\Airport::findByIata('CGK');

        $upload = Upload::create([
            'original_filename' => $filename,
            'stored_path'       => $storedPath,
            'report_type'       => $reportType,
            'status'            => 'pending',
            'season'            => 'summer',
            'airport_id'        => $airport?->id,
        ]);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'upload_id'    => $upload->id,
                'report_type'  => $reportType,
                'status'       => 'pending',
                'process_url'  => route('upload.process', $upload->id),
                'status_url'   => route('upload.status', $upload->id),
                'redirect_url' => route('dau.dashboard', $upload->id),
                'message'      => "{$conf['name']} template uploaded and validated. Ready for generation.",
            ]);
        }

        return $this->executeDauProcessing($upload);
    }

    /**
     * Staged execution endpoint for PDF or DAU processing.
     */
    public function process(Upload $upload)
    {
        if ($upload->report_type === 'slot_schedule' || empty($upload->report_type)) {
            return $this->executeProcessing($upload);
        }

        return $this->executeDauProcessing($upload);
    }

    /**
     * Polling endpoint to check upload processing status.
     */
    public function status(Upload $upload)
    {
        $redirectUrl = ($upload->report_type === 'slot_schedule' || empty($upload->report_type))
            ? route('schedule.dashboard', $upload->id)
            : route('dau.dashboard', $upload->id);

        return response()->json([
            'id'                 => $upload->id,
            'status'             => $upload->status,
            'report_type'        => $upload->report_type,
            'total_rows'         => $upload->total_rows ?? 0,
            'valid_rows'         => $upload->valid_rows ?? 0,
            'invalid_rows'       => $upload->invalid_rows ?? 0,
            'duplicate_rows'     => $upload->duplicate_rows ?? 0,
            'parsing_confidence' => $upload->parsing_confidence ?? 100,
            'error_message'      => $upload->error_message,
            'redirect_url'       => $redirectUrl,
        ]);
    }

    /**
     * Execution logic for Airport Slot Schedule PDF processing (PRESERVED).
     */
    private function executeProcessing(Upload $upload)
    {
        $upload->update(['status' => 'processing']);
        $storedPath = $upload->stored_path;

        try {
            if (!Storage::disk('local')->exists($storedPath)) {
                throw new \RuntimeException("Uploaded schedule file could not be located on storage disk.");
            }

            $absolutePath = Storage::disk('local')->path($storedPath);

            // Step 1: Parse flights from PDF using universal multi-strategy parser
            $parser       = new PdfParser();
            $parserResult = $parser->parse($absolutePath);

            // Step 2: Validate extracted flights against data integrity rules
            $validator        = new FlightScheduleValidator();
            $validationResult = $validator->validate($parserResult['flights'], $upload->original_filename);

            \Illuminate\Support\Facades\DB::transaction(function () use ($upload, $validationResult, $parserResult) {
                // Step 3: Clear any prior flights/positions belonging exclusively to this upload ID
                $upload->flights()->delete();
                $upload->timelinePositions()->delete();

                // Step 4: Persist exact validated normalized records with raw source metadata (Bulk insert)
                $now = now();
                $flightRecords = [];
                foreach ($validationResult['valid_flights'] as $data) {
                    $flightRecords[] = [
                        'upload_id'         => $upload->id,
                        'flight_number'     => $data['flight_number'] ?? null,
                        'airline_code'      => $data['airline_code'] ?? null,
                        'aircraft_type'     => $data['aircraft_type'] ?? null,
                        'origin'            => $data['origin'] ?? null,
                        'destination'       => $data['destination'] ?? null,
                        'scheduled_time'    => $data['scheduled_time'] ?? null,
                        'operating_days'    => $data['operating_days'] ?? null,
                        'flight_type'       => $data['flight_type'] ?? null,
                        'direction'         => $data['direction'] ?? null,
                        'traffic_type'      => $data['traffic_type'] ?? null,
                        'slot_status'       => $data['slot_status'] ?? 'available',
                        'parse_status'      => $data['parse_status'] ?? 'valid',
                        'validation_status' => $data['validation_status'] ?? 'valid',
                        'validation_errors' => isset($data['validation_errors']) ? (is_array($data['validation_errors']) ? json_encode($data['validation_errors']) : $data['validation_errors']) : null,
                        'paired_flight_id'  => $data['paired_flight_id'] ?? null,
                        'remarks'           => $data['remarks'] ?? null,
                        'raw_data'          => isset($data['raw_data']) ? (is_array($data['raw_data']) ? json_encode($data['raw_data']) : $data['raw_data']) : null,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }

                if (!empty($flightRecords)) {
                    foreach (array_chunk($flightRecords, 100) as $chunk) {
                        \App\Models\Flight::insert($chunk);
                    }
                }

                // Step 5: Build timeline positions strictly from validated flights
                $engine = new TimelineEngine();
                $engine->build($upload);

                $upload->update([
                    'status'             => 'completed',
                    'total_rows'         => $parserResult['total_rows'],
                    'valid_rows'         => $validationResult['valid_count'],
                    'invalid_rows'       => $validationResult['invalid_count'],
                    'duplicate_rows'     => $parserResult['duplicate_rows'],
                    'parsing_confidence' => $parserResult['parsing_confidence'],
                    'validation_summary' => [
                        'section_counts' => $validationResult['section_counts'],
                        'warnings'       => $validationResult['warnings'],
                        'errors'         => $validationResult['errors'],
                    ],
                ]);
            });

            // Store active upload ID in session for session-based restoration
            session(['active_upload_id' => $upload->id]);

        } catch (\Throwable $e) {
            Log::error("Failed to parse PDF ID {$upload->id}: " . $e->getMessage(), [
                'exception'   => $e,
                'stored_path' => $storedPath
            ]);

            $upload->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status'  => 'failed',
                    'error'   => $e->getMessage(),
                ], 422);
            }

            return redirect()->route('home')
                ->withErrors(['pdf' => $e->getMessage()]);
        }

        if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success'      => true,
                'status'       => 'completed',
                'total_rows'   => $upload->total_rows,
                'valid_rows'   => $upload->valid_rows,
                'redirect_url' => route('schedule.dashboard', $upload->id),
            ]);
        }

        return redirect()->route('schedule.dashboard', $upload->id);
    }

    /**
     * Execution logic for DAU report processing.
     */
    private function executeDauProcessing(Upload $upload)
    {
        $upload->update(['status' => 'processing']);
        $storedPath = $upload->stored_path;

        try {
            if (!Storage::disk('local')->exists($storedPath)) {
                throw new \RuntimeException("Uploaded report file could not be located on storage disk.");
            }

            $absolutePath = Storage::disk('local')->path($storedPath);
            $conf = ReportTemplateRegistry::find($upload->report_type);
            if (!$conf) {
                throw new \RuntimeException("Unknown report type: {$upload->report_type}");
            }

            $parserClass = $conf['parser_class'];
            /** @var \App\Services\Dau\Parsers\BaseDauParser $parser */
            $parser = new $parserClass();
            $parsedData = $parser->parse($absolutePath);

            $upload->update([
                'status'             => 'completed',
                'report_data'        => $parsedData,
                'total_rows'         => $parsedData['records_count'] ?? 0,
                'valid_rows'         => $parsedData['records_count'] ?? 0,
                'invalid_rows'       => 0,
                'duplicate_rows'     => 0,
                'parsing_confidence' => 100.0,
            ]);

            session(['active_upload_id' => $upload->id]);

        } catch (\Throwable $e) {
            Log::error("Failed to parse DAU ID {$upload->id} ({$upload->report_type}): " . $e->getMessage(), [
                'exception'   => $e,
                'stored_path' => $storedPath
            ]);

            $upload->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status'  => 'failed',
                    'error'   => $e->getMessage(),
                ], 422);
            }

            return redirect()->route('home')->withErrors(['dau' => $e->getMessage()]);
        }

        if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success'      => true,
                'status'       => 'completed',
                'total_rows'   => $upload->total_rows,
                'valid_rows'   => $upload->valid_rows,
                'redirect_url' => route('dau.dashboard', $upload->id),
            ]);
        }

        return redirect()->route('dau.dashboard', $upload->id);
    }
}
