<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Services\PdfParser;
use App\Services\FlightScheduleValidator;
use App\Services\TimelineEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class UploadController extends Controller
{
    /**
     * Initial landing page — ALWAYS renders the Upload Portal.
     */
    public function index()
    {
        $activeUpload = null;
        try {
            $activeUploadId = session('active_upload_id');
            if ($activeUploadId) {
                $activeUpload = Upload::where('id', $activeUploadId)
                    ->where('status', 'completed')
                    ->has('flights')
                    ->first();
            }
        } catch (\Throwable $e) {
            // Graceful fallback
        }

        return view('home', compact('activeUpload'));
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
                ->has('flights')
                ->first();

            if ($upload) {
                return redirect()->route('schedule.dashboard', $upload->id);
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

    public function store(Request $request)
    {
        $request->validate([
            'schedule_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $file = $request->file('schedule_pdf');
        $filename = $file->getClientOriginalName();

        // Idempotency check: if identical filename was completed within last 2 minutes, reuse existing upload
        $recentUpload = Upload::where('original_filename', $filename)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->latest('id')
            ->first();

        if ($recentUpload) {
            session(['active_upload_id' => $recentUpload->id]);
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'      => true,
                    'upload_id'    => $recentUpload->id,
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
            'status'            => 'pending',
            'season'            => $season,
            'airport_id'        => $airportId,
        ]);

        // If AJAX / JSON upload (from interactive staged frontend), return immediately
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'upload_id'    => $upload->id,
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

    /**
     * Staged execution endpoint for PDF parsing, matching, and generation.
     */
    public function process(Upload $upload)
    {
        return $this->executeProcessing($upload);
    }

    /**
     * Polling endpoint to check upload processing status.
     */
    public function status(Upload $upload)
    {
        return response()->json([
            'id'                 => $upload->id,
            'status'             => $upload->status,
            'total_rows'         => $upload->total_rows ?? 0,
            'valid_rows'         => $upload->valid_rows ?? 0,
            'invalid_rows'       => $upload->invalid_rows ?? 0,
            'duplicate_rows'     => $upload->duplicate_rows ?? 0,
            'parsing_confidence' => $upload->parsing_confidence ?? 100,
            'error_message'      => $upload->error_message,
            'redirect_url'       => route('schedule.dashboard', $upload->id),
        ]);
    }

    /**
     * Centralized execution logic for processing an upload.
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
                    $record = $data;
                    $record['upload_id']  = $upload->id;
                    $record['created_at'] = $now;
                    $record['updated_at'] = $now;
                    if (isset($record['validation_errors']) && is_array($record['validation_errors'])) {
                        $record['validation_errors'] = json_encode($record['validation_errors']);
                    }
                    if (isset($record['raw_data']) && is_array($record['raw_data'])) {
                        $record['raw_data'] = json_encode($record['raw_data']);
                    }
                    $flightRecords[] = $record;
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
}
