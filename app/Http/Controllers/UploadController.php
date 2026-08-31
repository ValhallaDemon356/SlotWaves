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
    public function index()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('uploads')) {
                $latestUpload = Upload::where('status', 'completed')
                    ->has('flights')
                    ->latest('id')
                    ->first();

                if ($latestUpload) {
                    return redirect()->route('schedule.dashboard', $latestUpload->id);
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback if database schema is not initialized yet
        }

        return view('home');
    }

    public function uploadPage()
    {
        return view('home');
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
            return redirect()->route('schedule.dashboard', $recentUpload->id);
        }

        // Store using the defined disk
        $storedPath = $file->store('uploads', 'local');
        $season = preg_match('/winter/i', $filename) ? 'winter' : 'summer';
        
        // Try to match airport code from filename (e.g. BDO, CGK, HLP, KJT)
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

        try {
            if (!Storage::disk('local')->exists($storedPath)) {
                throw new \RuntimeException("Uploaded file could not be located on storage disk.");
            }

            $absolutePath = Storage::disk('local')->path($storedPath);

            // Step 1: Parse flights from PDF using universal multi-strategy parser
            $parser       = new PdfParser();
            $parserResult = $parser->parse($absolutePath);

            // Step 2: Validate extracted flights against data integrity rules
            $validator        = new FlightScheduleValidator();
            $validationResult = $validator->validate($parserResult['flights'], $filename);

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

        } catch (\Throwable $e) {
            Log::error("Failed to parse PDF ID {$upload->id}: " . $e->getMessage(), [
                'exception'   => $e,
                'stored_path' => $storedPath
            ]);

            $upload->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->route('home')
                ->withErrors(['pdf' => $e->getMessage()]);
        }

        return redirect()->route('schedule.dashboard', $upload->id);
    }
}
