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
        $isProbe    = filter_var($request->input('is_probe', false), FILTER_VALIDATE_BOOLEAN);
        $file       = $request->file('file') ?? $request->file('schedule_pdf') ?? $request->file('uploaded_file');

        if (!$file) {
            return response()->json([
                'valid'            => false,
                'category'         => 'CORRUPTED_FILE',
                'category_title'   => 'NO FILE RECEIVED',
                'detectedTemplate' => 'None',
                'expectedTemplate' => $reportType,
                'errors'           => ['No file provided for template validation.'],
                'error'            => 'No file provided for template validation.',
                'warnings'         => [],
            ], 422);
        }

        // File size check: if a non-probe file exceeds 25 MB, prompt to use chunked pipeline
        $fileSize = $file->getSize();
        if (!$isProbe && $fileSize > 25 * 1024 * 1024) {
            return response()->json([
                'valid'            => false,
                'category'         => 'FILE_TOO_LARGE',
                'category_title'   => 'FILE TOO LARGE FOR CURRENT UPLOAD PATH',
                'detectedTemplate' => 'Oversized File',
                'expectedTemplate' => $reportType,
                'errors'           => [
                    "File size (" . round($fileSize / 1048576, 2) . " MB) exceeds single payload limit.",
                    "Multi-month datasets are automatically uploaded via chunked transfer."
                ],
                'error'            => "File size (" . round($fileSize / 1048576, 2) . " MB) exceeds single payload limit.",
                'warnings'         => [],
            ], 413);
        }

        $validator = new TemplateValidator();
        $result = $validator->validate($reportType, $file, $isProbe);

        $status = $result['valid'] ? 200 : 422;
        return response()->json($result, $status);
    }

    /**
     * Chunked upload endpoint to support large multi-month files under Vercel's 4.5 MB payload limit.
     */
    public function uploadChunk(Request $request)
    {
        $reportType  = $request->input('report_type', 'DAU1');
        $uploadToken = $request->input('upload_token');
        $chunkIndex  = (int) $request->input('chunk_index', 0);
        $totalChunks = (int) $request->input('total_chunks', 1);
        $filename    = $request->input('filename', 'dataset.xls');

        if (!$uploadToken || !preg_match('/^[a-zA-Z0-9_\-]+$/', $uploadToken)) {
            return response()->json([
                'success'        => false,
                'category'       => 'UPLOAD_FAILED',
                'category_title' => 'UPLOAD FAILED',
                'error'          => 'Invalid or missing upload token.',
            ], 422);
        }

        $chunkFile = $request->file('chunk') ?? $request->file('file');
        if (!$chunkFile) {
            return response()->json([
                'success'        => false,
                'category'       => 'UPLOAD_FAILED',
                'category_title' => 'UPLOAD FAILED',
                'error'          => 'No chunk payload received.',
            ], 422);
        }

        $conf = ReportTemplateRegistry::find($reportType);
        if (!$conf) {
            return response()->json([
                'success'        => false,
                'category'       => 'UNSUPPORTED_DAU_TYPE',
                'category_title' => 'UNSUPPORTED REPORT TYPE',
                'error'          => "Unsupported report type: {$reportType}",
            ], 422);
        }

        // Store chunk on local storage disk
        $chunkDir = "chunks/{$uploadToken}";
        $chunkFilename = "chunk_{$chunkIndex}";
        Storage::disk('local')->putFileAs($chunkDir, $chunkFile, $chunkFilename);

        // Also store in upload_chunks table if table exists
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('upload_chunks')) {
                \Illuminate\Support\Facades\DB::table('upload_chunks')->updateOrInsert(
                    ['upload_token' => $uploadToken, 'chunk_index' => $chunkIndex],
                    [
                        'total_chunks' => $totalChunks,
                        'chunk_size'   => $chunkFile->getSize(),
                        'chunk_data'   => file_get_contents($chunkFile->getRealPath()),
                        'created_at'   => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {}

        // Check if all chunks have been received
        $allPresent = true;
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!Storage::disk('local')->exists("{$chunkDir}/chunk_{$i}")) {
                $inDb = false;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('upload_chunks')) {
                        $inDb = \Illuminate\Support\Facades\DB::table('upload_chunks')
                            ->where('upload_token', $uploadToken)
                            ->where('chunk_index', $i)
                            ->exists();
                    }
                } catch (\Throwable $e) {}

                if (!$inDb) {
                    $allPresent = false;
                    break;
                }
            }
        }

        if (!$allPresent) {
            return response()->json([
                'success'     => true,
                'completed'   => false,
                'chunk_index' => $chunkIndex,
                'total_chunks'=> $totalChunks,
                'message'     => "Chunk {$chunkIndex} of {$totalChunks} received.",
            ]);
        }

        // ── ALL CHUNKS RECEIVED: Reassemble file ───────────────────────────────
        $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'xls';
        $assembledRelativePath = "uploads/{$uploadToken}.{$ext}";
        $assembledAbsolutePath = Storage::disk('local')->path($assembledRelativePath);

        $parentDir = dirname($assembledAbsolutePath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        $outHandle = fopen($assembledAbsolutePath, 'wb');
        if (!$outHandle) {
            return response()->json([
                'success'        => false,
                'category'       => 'PROCESSING_FAILED',
                'category_title' => 'PROCESSING FAILED',
                'error'          => 'Failed to create destination file for assembled chunks.',
            ], 500);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = "{$chunkDir}/chunk_{$i}";
            if (Storage::disk('local')->exists($chunkPath)) {
                $cStream = fopen(Storage::disk('local')->path($chunkPath), 'rb');
                stream_copy_to_stream($cStream, $outHandle);
                fclose($cStream);
            } else {
                $row = null;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('upload_chunks')) {
                        $row = \Illuminate\Support\Facades\DB::table('upload_chunks')
                            ->where('upload_token', $uploadToken)
                            ->where('chunk_index', $i)
                            ->first();
                    }
                } catch (\Throwable $e) {}

                if ($row && $row->chunk_data) {
                    fwrite($outHandle, $row->chunk_data);
                }
            }
        }
        fclose($outHandle);

        // Clean up temporary chunk files and database records
        Storage::disk('local')->deleteDirectory($chunkDir);
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('upload_chunks')) {
                \Illuminate\Support\Facades\DB::table('upload_chunks')
                    ->where('upload_token', $uploadToken)
                    ->delete();
            }
        } catch (\Throwable $e) {}

        // ── Validate Assembled Template ───────────────────────────────────────
        $validator = new TemplateValidator();
        $validationResult = $validator->validate($reportType, $assembledAbsolutePath);

        if (!$validationResult['valid']) {
            Storage::disk('local')->delete($assembledRelativePath);
            return response()->json([
                'success'        => false,
                'category'       => $validationResult['category'] ?? 'INVALID_TEMPLATE',
                'category_title' => $validationResult['category_title'] ?? 'INVALID TEMPLATE',
                'error'          => $validationResult['error'] ?? implode('; ', $validationResult['errors']),
                'errors'         => $validationResult['errors'] ?? [],
                'validation'     => $validationResult,
            ], 422);
        }

        // ── Create Upload Record ──────────────────────────────────────────────
        $airportCode = $validationResult['meta']['airport_code'] ?? 'CGK';
        $airport = \App\Models\Airport::findByIata($airportCode) ?? \App\Models\Airport::findByIata('CGK');

        $upload = Upload::create([
            'original_filename' => $filename,
            'stored_path'       => $assembledRelativePath,
            'report_type'       => $reportType,
            'status'            => 'pending',
            'season'            => 'summer',
            'airport_id'        => $airport?->id,
        ]);

        // Immediate processing with optimized parser
        $this->executeDauProcessing($upload);
        session(['active_upload_id' => $upload->id]);

        return response()->json([
            'success'      => true,
            'completed'    => true,
            'upload_id'    => $upload->id,
            'report_type'  => $reportType,
            'status'       => 'completed',
            'total_rows'   => $upload->total_rows,
            'valid_rows'   => $upload->valid_rows,
            'redirect_url' => route('dau.dashboard', $upload->id),
            'message'      => "{$conf['name']} uploaded and processed successfully ({$upload->valid_rows} records).",
        ]);
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
                'success'        => false,
                'category'       => 'UNSUPPORTED_DAU_TYPE',
                'category_title' => 'UNSUPPORTED REPORT TYPE',
                'error'          => "Unsupported report type: {$reportType}",
            ], 422);
        }

        $file = $request->file('dau_file') ?? $request->file('uploaded_file') ?? $request->file('file') ?? $request->file('schedule_pdf');
        if (!$file) {
            return response()->json([
                'success'        => false,
                'category'       => 'CORRUPTED_FILE',
                'category_title' => 'NO FILE RECEIVED',
                'error'          => 'No file provided for upload.',
            ], 422);
        }

        // Check if direct file upload exceeds single-request payload capacity
        $fileSize = $file->getSize();
        if ($fileSize > 20 * 1024 * 1024) {
            return response()->json([
                'success'        => false,
                'category'       => 'FILE_TOO_LARGE',
                'category_title' => 'FILE TOO LARGE FOR CURRENT UPLOAD PATH',
                'error'          => 'FILE TOO LARGE FOR CURRENT UPLOAD PATH: File exceeds single payload limit. Please upload via the unified upload portal.',
            ], 413);
        }

        // Strict template validation by content
        $validator = new TemplateValidator();
        $validationResult = $validator->validate($reportType, $file);

        if (!$validationResult['valid']) {
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'        => false,
                    'category'       => $validationResult['category'] ?? 'INVALID_TEMPLATE',
                    'category_title' => $validationResult['category_title'] ?? 'INVALID TEMPLATE',
                    'error'          => $validationResult['error'] ?? implode('; ', $validationResult['errors']),
                    'errors'         => $validationResult['errors'] ?? [],
                    'validation'     => $validationResult,
                ], 422);
            }
            return redirect()->route('home')->withErrors([
                'dau' => $validationResult['error'] ?? implode('; ', $validationResult['errors'])
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

        // Process immediately with optimized parser
        $this->executeDauProcessing($upload);
        session(['active_upload_id' => $upload->id]);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'upload_id'    => $upload->id,
                'report_type'  => $reportType,
                'status'       => 'completed',
                'total_rows'   => $upload->total_rows,
                'valid_rows'   => $upload->valid_rows,
                'process_url'  => route('upload.process', $upload->id),
                'status_url'   => route('upload.status', $upload->id),
                'redirect_url' => route('dau.dashboard', $upload->id),
                'message'      => "{$conf['name']} template uploaded and processed successfully ({$upload->valid_rows} records).",
            ]);
        }

        return redirect()->route('dau.dashboard', $upload->id);
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
            $absolutePath = null;
            if (Storage::disk('local')->exists($storedPath)) {
                $absolutePath = Storage::disk('local')->path($storedPath);
            } elseif (file_exists(storage_path('app/private/' . $storedPath))) {
                $absolutePath = storage_path('app/private/' . $storedPath);
            } elseif (file_exists(storage_path('app/' . $storedPath))) {
                $absolutePath = storage_path('app/' . $storedPath);
            } elseif (file_exists($storedPath)) {
                $absolutePath = $storedPath;
            }

            if (!$absolutePath || !file_exists($absolutePath)) {
                throw new \RuntimeException("Uploaded report file could not be located on storage disk.");
            }
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
