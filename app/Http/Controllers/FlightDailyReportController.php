<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\Airport;
use App\Models\FdrFlight;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportValidator;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;
use App\Services\FlightDailyReport\HourlyChartService;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportPdfExport;
use App\Services\FlightDailyReport\FdrDatabaseService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\FdrUploadSession;
use App\Models\FdrProcessingJob;
use App\Services\Storage\SupabaseStorageService;

class FlightDailyReportController extends Controller
{
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportValidator $validator;
    protected FlightDailyReportAnalytics $analytics;
    protected HourlyChartService $hourlyChartService;
    protected FlightDailyReportFilter $filterService;
    protected FlightDailyReportPdfExport $pdfExport;
    protected FdrDatabaseService $fdrDbService;

    public function __construct(
        FlightDailyReportParser $parser,
        FlightDailyReportValidator $validator,
        FlightDailyReportAnalytics $analytics,
        HourlyChartService $hourlyChartService,
        FlightDailyReportFilter $filterService,
        FlightDailyReportPdfExport $pdfExport,
        FdrDatabaseService $fdrDbService
    ) {
        $this->parser = $parser;
        $this->validator = $validator;
        $this->analytics = $analytics;
        $this->hourlyChartService = $hourlyChartService;
        $this->filterService = $filterService;
        $this->pdfExport = $pdfExport;
        $this->fdrDbService = $fdrDbService;
    }

    /**
     * Redirect to FDR dashboard directly (Part 1 & Part 29).
     */
    public function configRedirect()
    {
        $activeUploadId = session('fdr_active_upload_id');
        if ($activeUploadId) {
            $upload = Upload::where('id', $activeUploadId)
                ->where('report_type', 'fdr')
                ->where('status', 'completed')
                ->first();
            if ($upload) {
                return redirect()->route('fdr.dashboard', $upload->id);
            }
        }

        $latest = Upload::where('report_type', 'fdr')
            ->where('status', 'completed')
            ->latest()
            ->first();
        if ($latest) {
            return redirect()->route('fdr.dashboard', $latest->id);
        }

        // Auto-initialize from the standard OASYS FDR reference template
        $templatePath = $this->resolveReferenceTemplatePath();
        if ($templatePath && file_exists($templatePath)) {
            $parsed = $this->parser->parse($templatePath);
            $classified = $this->parser->classifyRows($parsed['records']);
            $movementCount = count($classified['movement_records']);
            $upload = Upload::createWithRetry([
                'original_filename'  => 'CGK FDR.xls',
                'stored_path'        => 'templates/CGK FDR.xls',
                'status'             => 'completed',
                'report_type'        => 'fdr',
                'total_rows'         => $movementCount,
                'valid_rows'         => $movementCount,
                'invalid_rows'       => 0,
                'duplicate_rows'     => 0,
                'parsing_confidence' => 1.0,
                'validation_summary' => ['valid' => true],
                'report_data'        => [
                    'meta'            => $parsed['meta'] ?? [],
                    'summary'         => $parsed['summary'] ?? [],
                    'detected_format' => $parsed['detected_format'] ?? 'OASYS HTML XLS',
                ],
            ]);
            session(['fdr_active_upload_id' => $upload->id]);
            return redirect()->route('fdr.dashboard', $upload->id);
        }

        return redirect()->route('home');
    }

    /**
     * Dedicated FDR Configuration Form Page.
     * Landing Page Flow: Home → Select Type Data to Generate → [ Flight Daily Report ] → FDR Config Form → FDR Dashboard → Flight Details → Export.
     */
    public function config(Request $request, Upload $upload = null)
    {
        if (!$upload || $upload->report_type !== 'fdr') {
            $activeUploadId = session('fdr_active_upload_id');
            if ($activeUploadId) {
                $upload = Upload::where('id', $activeUploadId)->where('report_type', 'fdr')->first();
            }
            if (!$upload) {
                $upload = Upload::where('report_type', 'fdr')->where('status', 'completed')->latest()->first();
            }
        }

        if (!$upload) {
            return $this->configRedirect();
        }

        $meta = $upload->report_data['meta'] ?? [];
        $cached = $upload->cached_analytics ?? [];
        $recordsCount = (int)($upload->total_rows ?? 0);
        if ($recordsCount <= 0) {
            $recordsCount = count($upload->report_data['records'] ?? []);
        }

        $airlines = [];
        $reportAirports = [];
        $mainAirport = strtoupper(trim($meta['report_airport'] ?? ($meta['airport'] ?? 'CGK')));
        if (!empty($mainAirport)) {
            $reportAirports[$mainAirport] = true;
        }

        if (!empty($cached['airlines']) || !empty($cached['airports'])) {
            foreach ($cached['airlines'] ?? [] as $al) {
                $airlines[$al] = true;
            }
            foreach ($cached['airports'] ?? [] as $rap) {
                $reportAirports[$rap] = true;
            }
        } else {
            $dbAirlines = \App\Models\FdrFlight::where('upload_id', $upload->id)
                ->whereNotNull('airline_code')
                ->where('airline_code', '!=', '')
                ->where('airline_code', '!=', 'N/A')
                ->whereRaw("LOWER(airline_code) NOT LIKE '%pax all%'")
                ->distinct()
                ->orderBy('airline_code')
                ->pluck('airline_code')
                ->toArray();

            $dbAirports = \App\Models\FdrFlight::where('upload_id', $upload->id)
                ->whereNotNull('report_airport')
                ->where('report_airport', '!=', '')
                ->where('report_airport', '!=', 'N/A')
                ->distinct()
                ->orderBy('report_airport')
                ->pluck('report_airport')
                ->toArray();

            if (!empty($dbAirlines) || !empty($dbAirports)) {
                foreach ($dbAirlines as $al) {
                    $airlines[$al] = true;
                }
                foreach ($dbAirports as $rap) {
                    $reportAirports[$rap] = true;
                }
            } else {
                $records = $upload->report_data['records'] ?? ($upload->getOffloadedRecords() ?? []);
                foreach ($records as $r) {
                    if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY') continue;
                    $al = trim($r['air_line'] ?? '');
                    if ($al === '' || $al === 'N/A' || strcasecmp($al, 'PAX ALL') === 0 || stripos($al, 'PAX ALL') !== false) continue;
                    $airlines[$al] = true;
                    $rap = strtoupper(trim($r['report_airport'] ?? ($r['branch'] ?? '')));
                    if (!empty($rap) && $rap !== 'N/A') {
                        $reportAirports[$rap] = true;
                    }
                }
            }
        }
        ksort($airlines);
        $airports = array_keys($reportAirports);
        sort($airports);

        return view('fdr.config', [
            'upload'       => $upload,
            'meta'         => $meta,
            'recordsCount' => $recordsCount,
            'airlines'     => array_keys($airlines),
            'airports'     => $airports,
        ]);
    }

    /**
     * Ingest and validate a new FDR source file.
     */
    public function store(Request $request)
    {
        $request->validate([
            'fdr_file' => 'nullable|file|max:51200', // 50MB
        ]);

        $file = $request->file('fdr_file') ?: $request->file('file');

        if (!$file) {
            // Check if user requested reference template
            return $this->useReference($request);
        }

        $origName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx', 'csv'])) {
            $displayExt = $ext ? ".{$ext}" : '(unknown)';
            $err = "Unsupported file extension {$displayExt}. Please upload an OASYS FDR (.xls, .xlsx, or .csv) file.";
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $err], 422);
            }
            return back()->withErrors(['fdr_file' => $err])->withInput();
        }

        $storedPath = $file->store('uploads/fdr', 'local');
        $fullPath = Storage::disk('local')->path($storedPath);

        // Validate strictly
        $validation = $this->validator->validate($fullPath, $origName);
        if (!$validation['valid']) {
            Storage::disk('local')->delete($storedPath);
            return back()->withErrors(['fdr_file' => implode('; ', $validation['errors'])])->withInput();
        }

        // Parse and normalize records
        $parsed = $this->parser->parse($fullPath);
        $classified = $this->parser->classifyRows($parsed['records']);
        $movementCount = count($classified['movement_records']);

        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '300');

        $upload = Upload::createWithRetry([
            'original_filename'  => $origName,
            'stored_path'        => $storedPath,
            'status'             => 'completed',
            'report_type'        => 'fdr',
            'total_rows'         => $movementCount,
            'valid_rows'         => $movementCount,
            'invalid_rows'       => 0,
            'duplicate_rows'     => 0,
            'parsing_confidence' => 1.0,
            'validation_summary' => [
                'valid'             => true,
                'detected_template' => $validation['detected_template'] ?? 'NORMAL',
            ],
            'report_data'        => [
                'meta'            => $parsed['meta'] ?? [],
                'summary'         => $parsed['summary'] ?? [],
                'detected_format' => $parsed['detected_format'] ?? 'OASYS HTML XLS',
            ],
        ]);

        $this->fdrDbService->syncUploadToDatabase($upload, $classified['movement_records'], $parsed['meta'] ?? []);

        session(['fdr_active_upload_id' => $upload->id]);

        // If requested via AJAX
        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'upload_id'    => $upload->id,
                'redirect_url' => route('fdr.dashboard', $upload->id),
                'meta'         => $parsed['meta'],
                'summary'      => $parsed['summary'],
            ]);
        }

        return redirect()->route('fdr.dashboard', $upload->id)->with('success', 'FDR Workbook ingested and normalized successfully.');
    }

    /**
     * Generate an authenticated signed upload URL for Supabase Storage.
     * Allows direct client upload of large datasets (up to 100MB) without RLS friction.
     */
    public function getSignedUploadUrl(Request $request, SupabaseStorageService $storageService)
    {
        $validated = $request->validate([
            'filename'     => 'required|string',
            'file_size'    => 'nullable|numeric|max:104857600', // 100MB
            'content_type' => 'nullable|string',
            'bucket_name'  => 'nullable|string',
        ]);

        $bucket = !empty($validated['bucket_name'])
            ? $validated['bucket_name']
            : config('services.supabase.bucket', env('NEXT_PUBLIC_SUPABASE_STORAGE_BUCKET', 'flight-reports'));

        $filename = $validated['filename'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx', 'csv'])) {
            return response()->json([
                'success' => false,
                'error'   => 'FDR_INVALID_TEMPLATE: Hanya file .xls, .xlsx, atau .csv yang didukung.',
            ], 422);
        }

        $date = now();
        $year = $date->format('Y');
        $month = $date->format('m');
        $timestamp = (int)(microtime(true) * 1000);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $storagePath = "raw_fdr/{$year}/{$month}/{$timestamp}_{$safeName}";

        try {
            $signedData = $storageService->createSignedUploadUrl($storagePath, $bucket, 3600);
            return response()->json([
                'success'            => true,
                'signed_upload_url'  => $signedData['signed_upload_url'],
                'storage_path'       => $signedData['storage_path'],
                'bucket'             => $signedData['bucket'],
                'token'              => $signedData['token'],
            ]);
        } catch (\Throwable $e) {
            Log::error('FDR getSignedUploadUrl error: ' . $e->getMessage(), [
                'exception'    => $e,
                'storage_path' => $storagePath,
                'bucket'       => $bucket,
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'FDR_SIGNED_UPLOAD_ERROR: ' . $e->getMessage(),
                'bucket'  => $bucket,
                'path'    => $storagePath,
            ], 500);
        }
    }

    /**
     * Handle client notification after file has been successfully uploaded to Supabase Storage.
     * Instantly creates Upload and FdrProcessingJob records and returns async job runner URLs.
     */
    public function handleUploaded(Request $request, SupabaseStorageService $storageService)
    {
        $validated = $request->validate([
            'file_path'         => 'required|string',
            'bucket_name'       => 'nullable|string',
            'original_filename' => 'required|string',
            'file_size'         => 'nullable|numeric',
            'airline_type'      => 'nullable|string',
        ]);

        $bucket = !empty($validated['bucket_name'])
            ? $validated['bucket_name']
            : config('services.supabase.bucket', env('NEXT_PUBLIC_SUPABASE_STORAGE_BUCKET', 'flight-reports'));

        $storagePath = $validated['file_path'];
        $originalFilename = $validated['original_filename'];
        $airlineHint = $validated['airline_type'] ?? null;
        $fileSize = (int)($validated['file_size'] ?? 0);

        try {
            $info = $storageService->getFileInfo($storagePath, $bucket);
            if ($info && !empty($info['size'])) {
                $fileSize = (int)$info['size'];
            }

            $uploadToken = (string) Str::uuid();

            // Detect airport from filename
            $airportCode = 'CGK';
            if (preg_match('/\b(CGK|HLP|SUB|DPS|KNO|UPG|BDO|JOG|SRG|YIA|BPN|BDJ|MDC)\b/i', $originalFilename, $mAir)) {
                $airportCode = strtoupper($mAir[1]);
            }
            $airport = \App\Models\Airport::findByIata($airportCode) ?? \App\Models\Airport::findByIata('CGK');

            $persisted = FdrDatabaseService::executeWithRetry(function () use (
                $originalFilename,
                $bucket,
                $storagePath,
                $airportCode,
                $airlineHint,
                $airport,
                $fileSize
            ) {
                return DB::transaction(function () use (
                    $originalFilename,
                    $bucket,
                    $storagePath,
                    $airportCode,
                    $airlineHint,
                    $airport,
                    $fileSize
                ) {
                    $sourceUri = "supabase://{$bucket}/{$storagePath}";
                    $upload = Upload::where('stored_path', $sourceUri)->lockForUpdate()->first();

                    if (!$upload) {
                        $uploadToken = (string) Str::uuid();

                        $upload = Upload::create([
                            'original_filename'  => $originalFilename,
                            'stored_path'        => $sourceUri,
                            'status'             => 'processing',
                            'report_type'        => 'fdr',
                            'total_rows'         => 0,
                            'valid_rows'         => 0,
                            'invalid_rows'       => 0,
                            'duplicate_rows'     => 0,
                            'parsing_confidence' => 0.0,
                            'season'             => 'summer',
                            'airport_id'         => $airport?->id,
                            'validation_summary' => ['status' => 'QUEUED'],
                            'report_data'        => [
                                'meta' => [
                                    'airport'        => $airportCode,
                                    'report_airport' => $airportCode,
                                    'filename'       => $originalFilename,
                                    'operator'       => $airlineHint ?: 'ALL AIRLINE',
                                ],
                            ],
                        ]);

                        $job = FdrProcessingJob::create([
                            'upload_id'      => $upload->id,
                            'upload_token'   => $uploadToken,
                            'status'         => 'QUEUED',
                            'stage'          => 'QUEUED',
                            'stage_label'    => 'Queued for streaming extraction...',
                            'progress'       => 0,
                            'processed_rows' => 0,
                            'total_rows'     => 0,
                            'current_offset' => 0,
                            'current_batch'  => 0,
                            'stored_path'    => $sourceUri,
                            'file_size'      => $fileSize,
                            'result_url'     => route('fdr.dashboard', $upload->id),
                            'meta'           => [
                                'airport'        => $airportCode,
                                'report_airport' => $airportCode,
                                'operator'       => $airlineHint ?: 'ALL AIRLINE',
                                'bucket'         => $bucket,
                                'storage_path'   => $storagePath,
                            ],
                        ]);

                        return ['upload' => $upload, 'job' => $job, 'reconciled' => false];
                    }

                    $job = FdrProcessingJob::where('upload_id', $upload->id)
                        ->orWhere('stored_path', $sourceUri)
                        ->first();

                    if (!$job) {
                        $uploadToken = (string) Str::uuid();
                        $job = FdrProcessingJob::create([
                            'upload_id'      => $upload->id,
                            'upload_token'   => $uploadToken,
                            'status'         => 'QUEUED',
                            'stage'          => 'QUEUED',
                            'stage_label'    => 'Queued for streaming extraction...',
                            'progress'       => 0,
                            'processed_rows' => 0,
                            'total_rows'     => 0,
                            'current_offset' => 0,
                            'current_batch'  => 0,
                            'stored_path'    => $sourceUri,
                            'file_size'      => $fileSize,
                            'result_url'     => route('fdr.dashboard', $upload->id),
                            'meta'           => [
                                'airport'        => $airportCode,
                                'report_airport' => $airportCode,
                                'operator'       => $airlineHint ?: 'ALL AIRLINE',
                                'bucket'         => $bucket,
                                'storage_path'   => $storagePath,
                            ],
                        ]);
                    }

                    return ['upload' => $upload, 'job' => $job, 'reconciled' => true];
                });
            }, 3);

            $upload = $persisted['upload'];
            $job = $persisted['job'];

            session(['active_upload_id' => $upload->id]);
            session(['fdr_active_upload_id' => $upload->id]);

            return response()->json([
                'success'      => true,
                'async'        => true,
                'upload_id'    => $upload->id,
                'job_id'       => $job->id,
                'status'       => 'QUEUED',
                'progress'     => 0,
                'process_url'  => route('fdr.jobs.process', $job->id),
                'status_url'   => route('fdr.jobs.status', $job->id),
                'result_url'   => route('fdr.dashboard', $upload->id),
                'message'      => 'File successfully uploaded to Supabase Storage. Streaming background extraction started.',
            ]);

        } catch (\Throwable $e) {
            Log::error('FDR handleUploaded failed: ' . $e->getMessage(), [
                'exception'   => $e,
                'file_path'   => $storagePath,
                'bucket'      => $bucket,
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'FDR_UPLOAD_CALLBACK_ERROR: ' . $e->getMessage(),
                'bucket'  => $bucket,
                'path'    => $storagePath,
            ], 500);
        }
    }

    /**
     * Use the authentic OASYS FDR Reference Workbook.
     */
    public function useReference(Request $request)
    {
        $templatePath = $this->resolveReferenceTemplatePath();
        if (!$templatePath || !file_exists($templatePath)) {
            abort(404, "Reference FDR template file not found.");
        }

        $parsed = $this->parser->parse($templatePath);
        $classified = $this->parser->classifyRows($parsed['records']);
        $movementCount = count($classified['movement_records']);

        $upload = Upload::createWithRetry([
            'original_filename'  => 'OASYS-FDR-TEMPLATE.xls',
            'stored_path'        => 'templates/OASYS-FDR-TEMPLATE.xls',
            'status'             => 'completed',
            'report_type'        => 'fdr',
            'total_rows'         => $movementCount,
            'valid_rows'         => $movementCount,
            'invalid_rows'       => 0,
            'duplicate_rows'     => 0,
            'parsing_confidence' => 1.0,
            'validation_summary' => ['valid' => true],
            'report_data'        => [
                'meta'            => $parsed['meta'] ?? [],
                'summary'         => $parsed['summary'] ?? [],
                'detected_format' => $parsed['detected_format'] ?? 'OASYS HTML XLS',
            ],
        ]);

        $this->fdrDbService->syncUploadToDatabase($upload, $classified['movement_records'], $parsed['meta'] ?? []);

        session(['fdr_active_upload_id' => $upload->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'upload_id'    => $upload->id,
                'redirect_url' => route('fdr.dashboard', $upload->id),
            ]);
        }

        return redirect()->route('fdr.dashboard', $upload->id)->with('success', 'Loaded OASYS FDR Reference Dataset.');
    }

    /**
     * Get real-time status of an FDR background processing job.
     */
    public function jobStatus($jobId)
    {
        $job = FdrProcessingJob::find($jobId);
        if (!$job) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'      => 'JOB_NOT_FOUND',
                    'message'   => 'Processing job not found.',
                    'retryable' => false,
                ],
            ], 404);
        }

        return response()->json([
            'success'        => true,
            'job_id'         => $job->id,
            'upload_id'      => $job->upload_id,
            'status'         => $job->status,
            'stage'          => $job->stage ?? $job->status,
            'stage_label'    => $job->stage_label,
            'progress'       => (float) $job->progress,
            'processed_rows' => (int) $job->processed_rows,
            'total_rows'     => (int) $job->total_rows,
            'current_offset' => (int) ($job->current_offset ?? 0),
            'current_batch'  => (int) ($job->current_batch ?? 0),
            'failed_rows'    => (int) ($job->failed_rows ?? 0),
            'diagnostics'    => $job->diagnostics ?? [],
            'error_message'  => $job->error_message,
            'error_code'     => $job->error_code,
            'error'          => $job->error_message ? [
                'code'      => $job->error_code ?: 'PROCESSING_ERROR',
                'message'   => $job->error_message,
                'retryable' => in_array($job->status, ['PAUSED']),
            ] : null,
            'can_resume'     => in_array($job->status, ['PAUSED', 'FAILED']),
            'result_url'     => $job->result_url,
        ]);
    }

    /**
     * Execute high-performance streaming parsing and normalization for FDR job.
     * Prevents duplicate execution via status locking and guarantees idempotency.
     * Resolves file from Storage disk, /tmp, or seamlessly reconstructs from DB upload_chunks.
     */
    public function processJob(Request $request, $jobId)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        try {
            $job = FdrProcessingJob::find($jobId);
            if (!$job) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'      => 'JOB_NOT_FOUND',
                        'message'   => 'Processing job not found.',
                        'retryable' => false,
                    ],
                ], 404);
            }

            // Idempotency: If already completed, verify database rows exist before returning READY
            if ($job->status === 'READY') {
                $isZeroStateOrAggregate = (
                    ($job->report_type ?? '') === 'EX_HALIM' ||
                    in_array(($job->report_type ?? ''), ['COMPARE_LOAD_FACTOR', 'COMPARE_LOAD_FACTOR_DAY']) ||
                    !empty($job->diagnostics['zero_state']) ||
                    !empty($job->diagnostics['aggregate_rows'])
                );

                $totalInDb = FdrFlight::where('upload_id', $job->upload_id)->count();
                if ($totalInDb === 0 && !$isZeroStateOrAggregate) {
                    $job->update([
                        'status'        => 'FAILED',
                        'stage'         => 'FAILED',
                        'stage_label'   => 'FDR ingestion produced 0 database movement rows.',
                        'error_code'    => 'FDR_DATABASE_INGESTION_ERROR',
                        'error_message' => 'No normalized flight records found in database (database_rows = 0).',
                    ]);

                    return response()->json([
                        'success'       => false,
                        'status'        => 'FAILED',
                        'stage'         => 'FAILED',
                        'error_code'    => 'FDR_DATABASE_INGESTION_ERROR',
                        'error_message' => 'FDR ingestion produced 0 database movement rows.',
                        'error'         => [
                            'code'    => 'FDR_DATABASE_INGESTION_ERROR',
                            'message' => 'No normalized flight records found in database (database_rows = 0).',
                        ],
                    ], 422);
                }

                return response()->json([
                    'success'        => true,
                    'status'         => 'READY',
                    'stage'          => 'READY',
                    'progress'       => 100,
                    'stage_label'    => 'Ready',
                    'movement_rows'  => $totalInDb,
                    'processed_rows' => $totalInDb,
                    'summary_rows'   => $job->diagnostics['summary_rows'] ?? 1,
                    'diagnostics'    => $job->diagnostics ?? [],
                    'result_url'     => $job->result_url,
                ]);
            }

            // Update session status
            FdrUploadSession::where('upload_token', $job->upload_token)
                ->update(['status' => FdrUploadSession::STATUS_PROCESSING]);

            $disk = config('filesystems.default', 'local');
            $storedPath = $job->stored_path ?: "uploads/fdr/{$job->upload_token}.xls";
            $localTmpPath = sys_get_temp_dir() . '/fdr_src_' . $job->id . '.bin';
            $fullPath = null;

            // Direct-upload FDR sources are persisted in private Supabase Storage.
            if (str_starts_with($storedPath, 'supabase://')) {
                if (file_exists($localTmpPath) && filesize($localTmpPath) > 10) {
                    $fullPath = $localTmpPath;
                } else {
                    $withoutScheme = substr($storedPath, strlen('supabase://'));
                    $slash = strpos($withoutScheme, '/');
                    if ($slash === false) {
                        throw new \RuntimeException('FDR_STORAGE_ERROR: Invalid Supabase storage source path.');
                    }

                    $bucket = substr($withoutScheme, 0, $slash);
                    $storageObject = substr($withoutScheme, $slash + 1);

                    try {
                        /** @var \App\Services\Storage\SupabaseStorageService $supabaseStorage */
                        $supabaseStorage = app(\App\Services\Storage\SupabaseStorageService::class);
                        $downloaded = $supabaseStorage->downloadToTemp($storageObject, $bucket);
                        if ($downloaded && file_exists($downloaded) && filesize($downloaded) > 10) {
                            @copy($downloaded, $localTmpPath);
                            @unlink($downloaded);
                            $fullPath = $localTmpPath;
                        } else {
                            $fullPath = $downloaded;
                        }

                        $actualSize = @filesize($fullPath) ?: 0;
                        $job->update([
                            'file_size' => $actualSize,
                            'stored_path' => $storedPath,
                        ]);
                    } catch (\Throwable $e) {
                        throw new \RuntimeException('FDR_SOURCE_DOWNLOAD_ERROR: ' . $e->getMessage(), 0, $e);
                    }
                }
            } elseif (file_exists($localTmpPath) && filesize($localTmpPath) > 10) {
                $fullPath = $localTmpPath;
            } elseif (Storage::disk($disk)->exists($storedPath)) {
                $fullPath = Storage::disk($disk)->path($storedPath);
            } elseif (Storage::disk('local')->exists($storedPath)) {
                $fullPath = Storage::disk('local')->path($storedPath);
            } elseif (file_exists($storedPath)) {
                $fullPath = $storedPath;
            } elseif (file_exists(storage_path('app/' . $storedPath))) {
                $fullPath = storage_path('app/' . $storedPath);
            } elseif (file_exists(storage_path('app/templates/' . basename($storedPath)))) {
                $fullPath = storage_path('app/templates/' . basename($storedPath));
            }

            // Fallback: Assemble from DB upload_chunks if needed
            if (!$fullPath || !file_exists($fullPath) || filesize($fullPath) < 10) {
                $session = FdrUploadSession::where('upload_token', $job->upload_token)->first();
                $totalChunks = $session ? $session->total_chunks : 0;
                if ($totalChunks > 0) {
                    $asmHandle = @fopen($localTmpPath, 'wb');
                    if ($asmHandle) {
                        for ($i = 0; $i < $totalChunks; $i++) {
                            $partRow = DB::table('upload_chunks')
                                ->where('upload_token', $job->upload_token)
                                ->where('chunk_index', $i)
                                ->first();
                            if ($partRow && $partRow->chunk_data) {
                                $cData = is_resource($partRow->chunk_data) ? stream_get_contents($partRow->chunk_data) : $partRow->chunk_data;
                                fwrite($asmHandle, $cData);
                                unset($cData);
                            }
                        }
                        fclose($asmHandle);
                        if (file_exists($localTmpPath) && filesize($localTmpPath) > 10) {
                            $fullPath = $localTmpPath;
                        }
                    }
                }
            }

            if (!$fullPath || !file_exists($fullPath) || filesize($fullPath) < 10) {
                throw new \RuntimeException("FDR_SOURCE_NOT_FOUND: The uploaded FDR source file is no longer available on persistent storage (Path: {$storedPath}).");
            }

            $actualSize = filesize($fullPath);

            // Validate the actual OASYS source in bounded/probe mode. This reads
            // only the header for large files, so the request remains lightweight.
            if (($job->current_batch ?? 0) === 0 && ($job->processed_rows ?? 0) === 0) {
                $validation = $this->validator->validate(
                    $fullPath,
                    $job->filename,
                    true
                );

                if (!($validation['valid'] ?? false)) {
                    $job->update([
                        'status' => 'FAILED',
                        'stage' => 'VALIDATING',
                        'stage_label' => $validation['category_title'] ?? 'FDR validation failed',
                        'error_code' => $validation['category'] ?? 'FDR_VALIDATION_FAILED',
                        'error_message' => implode('; ', $validation['errors'] ?? [$validation['error'] ?? 'FDR source validation failed.']),
                    ]);

                    return response()->json([
                        'success' => false,
                        'status' => 'FAILED',
                        'stage' => 'VALIDATING',
                        'progress' => 0,
                        'error' => [
                            'code' => $job->error_code,
                            'message' => $job->error_message,
                            'retryable' => false,
                        ],
                    ], 422);
                }

                $template = $validation['detected_template'] ?? ($validation['template'] ?? 'NORMAL');
                $job->update([
                    'report_type' => $template,
                    'status' => 'PARSING',
                    'stage' => 'PARSING',
                    'stage_label' => 'Reading OASYS Flight Daily Report...',
                    'meta' => array_merge(
                        $job->meta ?? [],
                        $validation['meta'] ?? [],
                        [
                            'detected_format' => $validation['detected_format'] ?? 'OASYS HTML XLS',
                            'source_file_size' => $actualSize,
                            'source_summary' => $validation['summary'] ?? [],
                            'report_type' => $template,
                            'template_type' => $template,
                        ]
                    ),
                    'file_size' => $actualSize,
                ]);

                // Dedicated handling for non-movement templates:
                if ($template === 'EX_HALIM') {
                    $parsed = $this->parser->parse($fullPath);
                    $meta = array_merge($job->meta ?? [], $parsed['meta'] ?? [], [
                        'zero_state' => true,
                        'is_valid_zero_data' => true,
                        'zero_state_title' => '0 Ex-Halim movements for selected period',
                        'report_type' => 'EX_HALIM',
                        'template_type' => 'EX_HALIM',
                    ]);
                    $upload = Upload::find($job->upload_id);
                    if ($upload) {
                        $upload->updateWithRetry([
                            'status'             => 'completed',
                            'report_type'        => 'fdr',
                            'total_rows'         => 0,
                            'valid_rows'         => 0,
                            'invalid_rows'       => 0,
                            'duplicate_rows'     => 0,
                            'parsing_confidence' => 1.0,
                            'validation_summary' => ['valid' => true, 'zero_state' => true],
                            'report_data'        => [
                                'meta'            => $meta,
                                'records'         => [],
                                'summary'         => $parsed['summary'] ?? [],
                                'detected_format' => 'OASYS HTML XLS',
                                'report_type'     => 'EX_HALIM',
                                'template_type'   => 'EX_HALIM',
                            ],
                        ]);
                        session(['fdr_active_upload_id' => $upload->id]);
                    }
                    if (isset($localTmpPath) && file_exists($localTmpPath)) {
                        @unlink($localTmpPath);
                    }
                    $resultUrl = route('fdr.dashboard', ['upload' => $job->upload_id]);
                    $job->update([
                        'status'         => 'READY',
                        'stage'          => 'READY',
                        'stage_label'    => 'Ready (Valid Zero Movements)',
                        'progress'       => 100,
                        'processed_rows' => 0,
                        'total_rows'     => 0,
                        'report_type'    => 'EX_HALIM',
                        'diagnostics'    => [
                            'source_exists' => true,
                            'movement_rows' => 0,
                            'database_rows' => 0,
                            'summary_rows'  => 1,
                            'zero_state'    => true,
                            'template'      => 'EX_HALIM',
                        ],
                        'result_url'     => $resultUrl,
                        'completed_at'   => now(),
                    ]);
                    return response()->json([
                        'success'        => true,
                        'status'         => 'READY',
                        'stage'          => 'READY',
                        'progress'       => 100,
                        'stage_label'    => 'Ready (Valid Zero Movements)',
                        'movement_rows'  => 0,
                        'processed_rows' => 0,
                        'summary_rows'   => 1,
                        'result_url'     => $resultUrl,
                    ]);
                }

                if ($template === 'COMPARE_LOAD_FACTOR' || $template === 'COMPARE_LOAD_FACTOR_DAY') {
                    $parsed = $this->parser->parse($fullPath);
                    $compRows = $parsed['comparison_rows'] ?? ($parsed['records'] ?? []);
                    $meta = array_merge($job->meta ?? [], $parsed['meta'] ?? [], [
                        'report_type' => $template,
                        'template_type' => $template,
                    ]);
                    $upload = Upload::find($job->upload_id);
                    if ($upload) {
                        $upload->update([
                            'status'             => 'completed',
                            'report_type'        => 'fdr',
                            'total_rows'         => count($compRows),
                            'valid_rows'         => count($compRows),
                            'invalid_rows'       => 0,
                            'duplicate_rows'     => 0,
                            'parsing_confidence' => 1.0,
                            'validation_summary' => ['valid' => true],
                            'report_data'        => [
                                'meta'            => $meta,
                                'summary'         => $parsed['summary'] ?? [],
                                'detected_format' => 'OASYS HTML XLS',
                                'report_type'     => $template,
                                'template_type'   => $template,
                            ],
                        ]);
                        session(['fdr_active_upload_id' => $upload->id]);
                    }
                    if (isset($localTmpPath) && file_exists($localTmpPath)) {
                        @unlink($localTmpPath);
                    }
                    $resultUrl = route('fdr.dashboard', ['upload' => $job->upload_id]);
                    $job->update([
                        'status'         => 'READY',
                        'stage'          => 'READY',
                        'stage_label'    => 'Ready',
                        'progress'       => 100,
                        'processed_rows' => count($compRows),
                        'total_rows'     => count($compRows),
                        'report_type'    => $template,
                        'diagnostics'    => [
                            'source_exists'  => true,
                            'aggregate_rows' => count($compRows),
                            'template'       => $template,
                        ],
                        'result_url'     => $resultUrl,
                        'completed_at'   => now(),
                    ]);
                    return response()->json([
                        'success'        => true,
                        'status'         => 'READY',
                        'stage'          => 'READY',
                        'progress'       => 100,
                        'stage_label'    => 'Ready',
                        'movement_rows'  => count($compRows),
                        'processed_rows' => count($compRows),
                        'result_url'     => $resultUrl,
                    ]);
                }

                if ($template === 'LOAD_FACTOR' || $template === 'DEPARTURE_VS_APPS' || $template === 'OASYS_VS_EDIFLY') {
                    $parsed = $this->parser->parse($fullPath);
                    $records = $parsed['records'] ?? [];
                    $meta = array_merge($job->meta ?? [], $parsed['meta'] ?? [], [
                        'report_type' => $template,
                        'template_type' => $template,
                    ]);
                    $upload = Upload::find($job->upload_id);
                    $totalInserted = 0;
                    if ($upload) {
                        $totalInserted = $this->fdrDbService->syncUploadToDatabase($upload, $records, $meta);
                        $upload->updateWithRetry([
                            'status'             => 'completed',
                            'report_type'        => 'fdr',
                            'total_rows'         => $totalInserted,
                            'valid_rows'         => $totalInserted,
                            'invalid_rows'       => 0,
                            'duplicate_rows'     => 0,
                            'parsing_confidence' => 1.0,
                            'validation_summary' => ['valid' => true],
                            'report_data'        => [
                                'meta'                  => $meta,
                                'reconciliationMatrix'  => $parsed['reconciliationMatrix'] ?? null,
                                'commentTotals'         => $parsed['commentTotals'] ?? null,
                                'summary'               => $parsed['summary'] ?? [],
                                'detected_format'       => 'OASYS HTML XLS',
                                'report_type'           => $template,
                                'template_type'         => $template,
                            ],
                        ]);
                        try {
                            $this->fdrDbService->precomputeAndCacheAnalytics($upload, $meta);
                        } catch (\Throwable $e) {
                            Log::warning("Could not precompute analytics for template upload #{$upload->id}: " . $e->getMessage());
                        }
                        session(['fdr_active_upload_id' => $upload->id]);
                    }
                    if (isset($localTmpPath) && file_exists($localTmpPath)) {
                        @unlink($localTmpPath);
                    }
                    $resultUrl = route('fdr.dashboard', ['upload' => $job->upload_id, 'date_scope' => 'ALL_PERIOD']);
                    $job->update([
                        'status'         => 'READY',
                        'stage'          => 'READY',
                        'stage_label'    => 'Ready',
                        'progress'       => 100,
                        'processed_rows' => $totalInserted,
                        'total_rows'     => $totalInserted,
                        'report_type'    => $template,
                        'diagnostics'    => [
                            'source_exists'          => true,
                            'movement_rows'          => $totalInserted,
                            'database_rows'          => $totalInserted,
                            'template'               => $template,
                        ],
                        'result_url'     => $resultUrl,
                        'completed_at'   => now(),
                    ]);
                    return response()->json([
                        'success'        => true,
                        'status'         => 'READY',
                        'stage'          => 'READY',
                        'progress'       => 100,
                        'stage_label'    => 'Ready',
                        'movement_rows'  => $totalInserted,
                        'processed_rows' => $totalInserted,
                        'result_url'     => $resultUrl,
                    ]);
                }
            }

            // ── 2. Incremental Batch Processing Loop (Prompt Section 13 & 14) ──
            $offset = (int) ($job->current_offset ?? 0);
            $processedRows = (int) ($job->processed_rows ?? 0);
            $currentBatch = (int) ($job->current_batch ?? 0);
            $meta = $job->meta ?? [];
            $defaultAirport = strtoupper(trim($meta['report_airport'] ?? ($meta['airport'] ?? 'CGK')));

            $startTime = microtime(true);
            $isEof = false;

            // Idempotency: If starting fresh at offset 0, clear any previous partial rows for this upload
            if ($offset === 0 && $currentBatch === 0) {
                FdrDatabaseService::executeWithRetry(function () use ($job) {
                    FdrFlight::where('upload_id', $job->upload_id)->delete();
                });
                $processedRows = 0;
            }

            while (!$isEof) {
                $batchResult = $this->parser->parseBatch($fullPath, $offset, 2000, $meta);
                $offset = $batchResult['next_offset'];
                $isEof = $batchResult['is_eof'];
                $meta = array_merge($meta, $batchResult['meta'] ?? []);

                $batchRecords = $batchResult['records'] ?? [];
                if (!empty($batchRecords)) {
                    $now = now();
                    $dbRows = [];
                    foreach ($batchRecords as $r) {
                        $row = $this->fdrDbService->transformRecordToDbRow($r, $job->upload_id, $defaultAirport, $meta, $now);
                        if ($row !== null) {
                            $dbRows[] = $row;
                        }
                    }

                    if (!empty($dbRows)) {
                        foreach (array_chunk($dbRows, 500) as $chunk) {
                            FdrDatabaseService::executeWithRetry(function () use ($chunk) {
                                DB::transaction(function () use ($chunk) {
                                    DB::table('fdr_flights')->insert($chunk);
                                });
                            });
                        }
                        $processedRows += count($dbRows);
                    }
                    unset($dbRows);
                    unset($batchRecords);
                }

                if (!empty($batchResult['summary_records'])) {
                    $meta['_summaries'] = array_merge($meta['_summaries'] ?? [], $batchResult['summary_records']);
                }

                $currentBatch++;
                $progress = $isEof ? 99 : min(98, (int)$batchResult['progress']);

                // Live checkpoint update
                $job->update([
                    'status'         => 'PARSING',
                    'stage'          => 'PARSING',
                    'stage_label'    => "Streaming flight movements into database ({$processedRows} rows inserted)...",
                    'progress'       => $progress,
                    'processed_rows' => $processedRows,
                    'total_rows'     => $job->total_rows ?: $processedRows,
                    'current_offset' => $offset,
                    'current_batch'  => $currentBatch,
                    'meta'           => $meta,
                ]);

                // Checkpoint and yield every 3.5s to maintain responsive browser UX and prevent gateway timeout
                if (!$isEof && (microtime(true) - $startTime) > 3.5) {
                    return response()->json([
                        'success'        => true,
                        'status'         => 'PARSING',
                        'stage'          => 'PARSING',
                        'stage_label'    => "Streaming flight movements into database ({$processedRows} rows inserted)...",
                        'progress'       => $progress,
                        'processed_rows' => $processedRows,
                        'total_rows'     => $job->total_rows ?: $processedRows,
                        'current_offset' => $offset,
                        'current_batch'  => $currentBatch,
                        'result_url'     => $job->result_url,
                    ]);
                }
            }

            // ── 3. Finalization (Stage: READY) ──
            $job->update([
                'status'      => 'VALIDATING',
                'stage'       => 'VALIDATING',
                'stage_label' => 'Validating operational movements...',
                'progress'    => 99,
            ]);

            $totalInDb = FdrFlight::where('upload_id', $job->upload_id)->count();

            // Absolute Rule §2, §31, §32: If normalized flight movements in DB are 0, job MUST NOT become READY
            if ($totalInDb === 0) {
                if (isset($localTmpPath) && file_exists($localTmpPath)) {
                    @unlink($localTmpPath);
                }

                $summaryCount = count($meta['_summaries'] ?? []);
                $diagnostics = [
                    'source_exists'          => true,
                    'parser_movement_rows'   => 0,
                    'movement_rows'          => 0,
                    'database_movement_rows' => 0,
                    'database_rows'          => 0,
                    'summary_rows'           => $summaryCount,
                    'rejected_rows'          => 0,
                    'metadata_exists'        => false,
                    'storage_path'           => $storedPath,
                    'detected_format'        => $meta['detected_format'] ?? 'OASYS HTML XLS',
                ];

                $job->update([
                    'status'        => 'FAILED',
                    'stage'         => 'FAILED',
                    'stage_label'   => 'FDR ingestion produced 0 database movement rows.',
                    'error_code'    => 'FDR_DATABASE_INGESTION_ERROR',
                    'error_message' => 'No normalized flight records found in database (database_rows = 0).',
                    'diagnostics'   => $diagnostics,
                ]);

                return response()->json([
                    'success'       => false,
                    'status'        => 'FAILED',
                    'stage'         => 'FAILED',
                    'error_code'    => 'FDR_DATABASE_INGESTION_ERROR',
                    'error_message' => 'FDR ingestion produced 0 database movement rows.',
                    'error'         => [
                        'code'    => 'FDR_DATABASE_INGESTION_ERROR',
                        'message' => 'No normalized flight records found in database (database_rows = 0).',
                    ],
                    'diagnostics'   => $diagnostics,
                ], 422);
            }

            // Refine metadata with dates and airports from database
            $distinctStats = FdrFlight::where('upload_id', $job->upload_id)->selectRaw("
                MIN(flight_date) as min_date,
                MAX(flight_date) as max_date,
                COUNT(DISTINCT flight_date) as days_available
            ")->first();

            if ($distinctStats && $distinctStats->min_date) {
                $minD = is_string($distinctStats->min_date) ? substr($distinctStats->min_date, 0, 10) : $distinctStats->min_date->format('Y-m-d');
                $maxD = is_string($distinctStats->max_date) ? substr($distinctStats->max_date, 0, 10) : $distinctStats->max_date->format('Y-m-d');
                $meta['date_start'] = $minD;
                $meta['date_end'] = $maxD;
                $meta['period_start'] = $minD;
                $meta['period_end'] = $maxD;
                $meta['source_start'] = $minD;
                $meta['source_end'] = $maxD;
                $meta['period_label'] = "{$minD} s/d {$maxD}";
                $meta['available_days'] = (int)($distinctStats->days_available ?? 1);
                $meta['source_type'] = FlightDailyReportParser::detectGranularity($minD, $maxD);
                $meta['source_granularity'] = str_replace(' ', '_', $meta['source_type']);
            }

            // Clean up temporary local downloaded file
            if (isset($localTmpPath) && file_exists($localTmpPath)) {
                @unlink($localTmpPath);
            }

            // Update Upload record to completed
            $upload = Upload::find($job->upload_id);
            if ($upload) {
                $upload->updateWithRetry([
                    'status'             => 'completed',
                    'total_rows'         => $totalInDb,
                    'valid_rows'         => $totalInDb,
                    'invalid_rows'       => 0,
                    'duplicate_rows'     => 0,
                    'parsing_confidence' => 1.0,
                    'validation_summary' => ['valid' => true],
                    'report_data'        => [
                        'meta'            => $meta,
                        'detected_format' => $meta['detected_format'] ?? 'OASYS HTML XLS',
                    ],
                ]);

                try {
                    $this->fdrDbService->precomputeAndCacheAnalytics($upload, $meta);
                } catch (\Throwable $e) {
                    Log::warning("Could not precompute analytics in processJob for upload #{$upload->id}: " . $e->getMessage());
                }

                session(['fdr_active_upload_id' => $upload->id]);
                session(['active_upload_id' => $upload->id]);
            }

            $summaryCount = count($meta['_summaries'] ?? []);
            $diagnostics = [
                'source_exists'          => true,
                'parser_movement_rows'   => $totalInDb,
                'movement_rows'          => $totalInDb,
                'database_movement_rows' => $totalInDb,
                'database_rows'          => $totalInDb,
                'summary_rows'           => $summaryCount,
                'rejected_rows'          => 0,
                'metadata_exists'        => !empty($meta['period_start']),
                'storage_path'           => $storedPath,
                'detected_format'        => $meta['detected_format'] ?? 'OASYS HTML XLS',
            ];

            $resultUrl = route('fdr.dashboard', ['upload' => $job->upload_id, 'date_scope' => 'ALL_PERIOD']);

            $job->update([
                'status'         => 'READY',
                'stage'          => 'READY',
                'stage_label'    => 'Ready',
                'progress'       => 100,
                'processed_rows' => $totalInDb,
                'total_rows'     => $totalInDb,
                'current_offset' => $offset,
                'diagnostics'    => $diagnostics,
                'result_url'     => $resultUrl,
                'completed_at'   => now(),
            ]);

            FdrUploadSession::where('upload_token', $job->upload_token)
                ->update(['status' => FdrUploadSession::STATUS_READY]);

            Log::info('FDR Large File Ingestion Completed', [
                'upload_id'       => $job->upload_id,
                'file_size'       => $actualSize,
                'storage_path'    => $storedPath,
                'source_period'   => ($meta['period_start'] ?? '') . ' to ' . ($meta['period_end'] ?? ''),
                'detected_format' => 'OASYS HTML XLS',
                'movement_rows'   => $totalInDb,
                'summary_rows'    => $summaryCount,
                'database_rows'   => $totalInDb,
                'processing_time' => round(microtime(true) - $startTime, 3),
            ]);

            return response()->json([
                'success'       => true,
                'status'        => 'READY',
                'stage'         => 'READY',
                'progress'      => 100,
                'stage_label'   => 'Ready',
                'movement_rows' => $totalInDb,
                'summary_rows'  => $summaryCount,
                'diagnostics'   => $diagnostics,
                'result_url'    => $resultUrl,
            ]);

        } catch (\Throwable $e) {
            $job = isset($job) ? $job : FdrProcessingJob::find($jobId);
            $msg = $e->getMessage();
            $errCode = 'FDR_PARSER_ERROR';

            if (str_contains($msg, 'FDR_SOURCE_NOT_FOUND') || str_contains(strtolower($msg), 'not found') || str_contains(strtolower($msg), 'no longer available')) {
                $errCode = 'FDR_SOURCE_NOT_FOUND';
            } elseif ($e instanceof \Illuminate\Database\QueryException) {
                $errCode = 'FDR_DATABASE_ERROR';
            } elseif (str_contains($msg, 'FDR_STORAGE_ERROR') || str_contains(strtolower($msg), 'storage') || str_contains(strtolower($msg), 'disk')) {
                $errCode = 'FDR_STORAGE_ERROR';
            } elseif (str_contains(strtolower($msg), 'timeout') || str_contains(strtolower($msg), 'maximum execution time')) {
                $errCode = 'FDR_PROCESSING_TIMEOUT';
            }

            if ($job) {
                $job->update([
                    'status'        => 'PAUSED',
                    'stage'         => 'PAUSED',
                    'stage_label'   => 'Processing Paused',
                    'error_code'    => $errCode,
                    'error_message' => $msg,
                ]);
                FdrUploadSession::where('upload_token', $job->upload_token)
                    ->update(['status' => FdrUploadSession::STATUS_PAUSED]);
            }

            Log::error("FDR Job {$jobId} Failed", [
                'error_code'    => $errCode,
                'error_message' => $msg,
            ]);

            return response()->json([
                'success'       => false,
                'status'        => 'PAUSED',
                'stage'         => 'PAUSED',
                'stage_label'   => 'Processing Paused',
                'error'         => [
                    'code'      => $errCode,
                    'message'   => $msg,
                    'retryable' => true,
                ],
                'error_code'    => $errCode,
                'error_message' => $msg,
            ], 200);
        }
    }

    /**
     * Show job status page or redirect to FDR dashboard if ready.
     */
    public function showJob($jobId)
    {
        $job = \App\Models\FdrProcessingJob::find($jobId);
        if (!$job) {
            abort(404, 'Processing job not found.');
        }

        if ($job->status === 'READY' && $job->result_url) {
            return redirect($job->result_url);
        }

        return response()->json([
            'job' => $job,
        ]);
    }

    /**
     * Idempotent recovery / reprocessing path for FDR Upload (Section 7, 40).
     * Re-ingests source from persistent Supabase Storage without requiring a new upload.
     */
    public function reprocess(Upload $upload, Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        if ($upload->report_type !== 'fdr') {
            return redirect()->route('fdr.index')->with('error', 'Please select a valid Flight Daily Report.');
        }

        if (empty($upload->stored_path)) {
            return redirect()->route('fdr.config', ['upload' => $upload->id])
                ->with('error', 'FDR source file path is missing from storage.');
        }

        // 1. Clear any existing fdr_flights rows for this upload
        FdrFlight::where('upload_id', $upload->id)->delete();

        // 2. Locate or create FdrProcessingJob
        $job = FdrProcessingJob::where('upload_id', $upload->id)->latest()->first();
        $token = $job ? $job->upload_token : (string) Str::uuid();

        if (!$job) {
            $job = FdrProcessingJob::create([
                'id'             => (string) Str::uuid(),
                'upload_id'      => $upload->id,
                'upload_token'   => $token,
                'status'         => 'QUEUED',
                'stage'          => 'QUEUED',
                'stage_label'    => 'Queued for reprocessing...',
                'progress'       => 0,
                'current_offset' => 0,
                'current_batch'  => 0,
                'processed_rows' => 0,
                'total_rows'     => $upload->total_rows ?: 0,
                'filename'       => $upload->original_filename ?: 'CGK GA FDR.xls',
                'stored_path'    => $upload->stored_path,
                'file_size'      => 0,
                'result_url'     => route('fdr.dashboard', ['upload' => $upload->id, 'date_scope' => 'ALL_PERIOD']),
            ]);
        } else {
            $job->update([
                'status'         => 'QUEUED',
                'stage'          => 'QUEUED',
                'stage_label'    => 'Queued for reprocessing...',
                'progress'       => 0,
                'current_offset' => 0,
                'current_batch'  => 0,
                'processed_rows' => 0,
                'error_code'     => null,
                'error_message'  => null,
                'stored_path'    => $upload->stored_path,
                'result_url'     => route('fdr.dashboard', ['upload' => $upload->id, 'date_scope' => 'ALL_PERIOD']),
            ]);
        }

        $upload->update(['status' => 'processing']);

        if ($request->wantsJson()) {
            return response()->json([
                'success'     => true,
                'message'     => 'Reprocessing initialized.',
                'job_id'      => $job->id,
                'process_url' => route('fdr.jobs.process', $job->id),
                'status_url'  => route('fdr.jobs.status', $job->id),
                'result_url'  => route('fdr.dashboard', ['upload' => $upload->id, 'date_scope' => 'ALL_PERIOD']),
            ]);
        }

        return redirect()->route('fdr.config', ['upload' => $upload->id, 'auto_start' => 1])
            ->with('info', 'FDR reprocessing started. Normalized flight records will be streamed into database.');
    }

    /**
     * FDR Interactive Analytics Dashboard.
     * FDR Dashboard → Flight Details → Export
     */
    public function dashboard(Upload $upload, Request $request)
    {
        ini_set('memory_limit', '1024M');

        if ($upload->report_type !== 'fdr') {
            return redirect()->route('fdr.index')->with('error', 'Please select or upload a valid Flight Daily Report workbook.');
        }

        session(['fdr_active_upload_id' => $upload->id]);

        if ($upload->status !== 'completed') {
            $job = FdrProcessingJob::where('upload_id', $upload->id)->latest()->first();
            if ($job && $job->status !== 'READY') {
                return redirect()->route('fdr.config', ['upload' => $upload->id])
                    ->with('info', "Processing job is currently {$job->status} ({$job->progress}%). Dashboard will be available once ready.");
            }
        }

        $data = $upload->report_data ?: [];
        $meta = $data['meta'] ?? [];
        $defaultAirport = strtoupper(trim($meta['report_airport'] ?? ($meta['airport'] ?? 'CGK')));
        $reportType = $request->query('report_type', $data['report_type'] ?? ($meta['report_type'] ?? ($data['template_type'] ?? 'NORMAL')));

        // Dedicated polymorphic dashboard for EX_HALIM (Valid zero data)
        if ($reportType === 'EX_HALIM') {
            $sourceSummary = [
                'source_type'    => 'DAILY',
                'period_start'   => $meta['period_start'] ?? date('Y-m-d'),
                'period_end'     => $meta['period_end'] ?? date('Y-m-d'),
                'period_label'   => $meta['period_label'] ?? 'Selected Period',
                'days_available' => '1 Day',
                'total_flights'  => 0,
            ];
            $scope = $this->resolveAnalysisScope($request, $sourceSummary, []);
            $filters = [
                'date_scope'     => 'ALL_PERIOD',
                'analysis_level' => 'ALL_PERIOD',
                'analysis_date'  => null,
                'analysis_month' => null,
                'analysis_year'  => null,
                'airport'        => $defaultAirport ?: 'CGK',
                'leg'            => 'ALL',
                'operator'       => 'ALL',
                'traffic'        => 'ALL',
                'data_type'      => 'OPERATIONAL DATA',
                'realization'    => 'ALL',
                'flight_no'      => '',
                'suffix'         => '',
                'start_date'     => '',
                'end_date'       => '',
                'report_mode'    => 1,
                'time_basis'     => 'actual',
                'otp_tolerance'  => 15,
                'search'         => '',
                'v'              => time(),
            ];
            $filterResult = [
                'source_count'   => 0,
                'filtered_count' => 0,
                'excluded_count' => 0,
                'active_chips'   => [],
                'reconciliation' => [
                    'source_count'      => 0,
                    'filtered_count'    => 0,
                    'excluded_count'    => 0,
                    'exclusion_reasons' => [],
                ],
            ];
            $analytics = [
                'kpis' => [
                    'total_flights'            => 0,
                    'scheduled_flights'        => 0,
                    'actual_flights'           => 0,
                    'arrivals'                 => 0,
                    'total_arrivals'           => 0,
                    'departures'               => 0,
                    'total_departures'         => 0,
                    'total_passengers'         => 0,
                    'passenger_movement'       => 0,
                    'direct_passengers'        => 0,
                    'total_capacity'           => 0,
                    'total_load'               => 0,
                    'avg_load_factor'          => '0.0%',
                    'load_factor_avg'          => 0,
                    'avg_load_factor_num'      => 0,
                    'weighted_load_factor_num' => 0,
                    'passenger_utilization_num'=> 0,
                    'total_cargo_kg'           => 0,
                    'cargo_kg'                 => 0,
                    'total_baggage_kg'         => 0,
                    'baggage_kg'               => 0,
                    'operating_crew'           => 0,
                    'crew'                     => 0,
                    'extra_crew'               => 0,
                    'delayed_flights'          => 0,
                    'irregular_flights'        => 0,
                    'otp_percent'              => 0,
                ],
                'schedule_vs_realization' => [
                    'histogram' => [],
                    'hist_bins' => [],
                ],
                'passenger_analytics' => [],
                'airline_route' => [
                    'top_routes'          => [],
                    'most_delayed_routes' => [],
                    'highest_slf_routes'  => [],
                    'suffix_distribution'=> [],
                    'leg_subtypes'        => ['SCHED' => 0, 'UNSCHED' => 0, 'CARGO' => 0],
                ],
                'fleet_performance' => [],
                'ground_operations' => [],
            ];
            $clientParsedData = [
                'records'    => [],
                'zero_state' => true,
                'metadata'   => $meta,
            ];

            return view('fdr.dashboard', [
                'upload'           => $upload,
                'meta'             => $meta,
                'reportType'       => 'EX_HALIM',
                'filters'          => $filters,
                'filterResult'     => $filterResult,
                'analytics'        => $analytics,
                'records'          => [],
                'totalRecords'     => 0,
                'airlines'         => [$meta['operator'] ?? 'GA'],
                'airports'         => [$defaultAirport ?: 'CGK'],
                'rawRecordsCount'  => 0,
                'dateScope'        => 'ALL_PERIOD',
                'analysisLevel'    => 'ALL_PERIOD',
                'analysisDate'     => null,
                'analysisMonth'    => null,
                'analysisYear'     => null,
                'sourceType'       => 'DAILY',
                'availableDates'   => [],
                'sourceSummary'    => $sourceSummary,
                'timeBasis'        => 'actual',
                'otpTolerance'     => 15,
                'clientParsedData' => $clientParsedData,
            ]);
        }

        // Dedicated polymorphic dashboard for COMPARE_LOAD_FACTOR & COMPARE_LOAD_FACTOR_DAY
        if ($reportType === 'COMPARE_LOAD_FACTOR' || $reportType === 'COMPARE_LOAD_FACTOR_DAY') {
            $compRows = $data['comparison_rows'] ?? ($data['records'] ?? []);
            $sourceSummary = [
                'source_type'    => 'AGGREGATE_COMPARISON',
                'period_start'   => $meta['period_start'] ?? date('Y-m-d'),
                'period_end'     => $meta['period_end'] ?? date('Y-m-d'),
                'period_label'   => $meta['period_label'] ?? 'YoY Comparison',
                'days_available' => count($compRows) . ' Rows',
                'total_flights'  => count($compRows),
            ];
            $scope = $this->resolveAnalysisScope($request, $sourceSummary, []);
            $filters = [
                'date_scope'     => 'ALL_PERIOD',
                'analysis_level' => 'ALL_PERIOD',
                'analysis_date'  => null,
                'analysis_month' => null,
                'analysis_year'  => null,
                'airport'        => $defaultAirport ?: 'CGK',
                'leg'            => 'ALL',
                'operator'       => 'ALL',
                'traffic'        => 'ALL',
                'data_type'      => 'OPERATIONAL DATA',
                'realization'    => 'ALL',
                'flight_no'      => '',
                'suffix'         => '',
                'start_date'     => '',
                'end_date'       => '',
                'report_mode'    => 1,
                'time_basis'     => 'actual',
                'otp_tolerance'  => 15,
                'search'         => '',
                'v'              => time(),
            ];
            $filterResult = [
                'source_count'   => count($compRows),
                'filtered_count' => count($compRows),
                'excluded_count' => 0,
                'active_chips'   => [],
                'reconciliation' => [
                    'source_count'      => count($compRows),
                    'filtered_count'    => count($compRows),
                    'excluded_count'    => 0,
                    'exclusion_reasons' => [],
                ],
            ];
            $clientParsedData = [
                'records'         => $compRows,
                'comparison_rows' => $compRows,
                'metadata'        => $meta,
            ];

            return view('fdr.dashboard', [
                'upload'           => $upload,
                'meta'             => $meta,
                'reportType'       => $reportType,
                'filters'          => $filters,
                'filterResult'     => $filterResult,
                'analytics'        => ['kpis' => ['total_flights' => count($compRows)]],
                'records'          => $compRows,
                'totalRecords'     => count($compRows),
                'airlines'         => [$meta['operator'] ?? 'GA'],
                'airports'         => [$defaultAirport ?: 'CGK'],
                'rawRecordsCount'  => count($compRows),
                'dateScope'        => 'ALL_PERIOD',
                'analysisLevel'    => 'ALL_PERIOD',
                'analysisDate'     => null,
                'analysisMonth'    => null,
                'analysisYear'     => null,
                'sourceType'       => 'AGGREGATE_COMPARISON',
                'availableDates'   => [],
                'sourceSummary'    => $sourceSummary,
                'timeBasis'        => 'actual',
                'otpTolerance'     => 15,
                'clientParsedData' => $clientParsedData,
            ]);
        }

        $hasDb = $this->fdrDbService->hasDatabaseRecords($upload);

        // Auto-heal / sync if database records don't exist yet but source is available
        if (!$hasDb && $upload->status === 'completed') {
            $offloaded = $upload->getOffloadedRecords();
            if (!empty($offloaded)) {
                $classified = $this->parser->classifyRows($offloaded);
                $this->fdrDbService->syncUploadToDatabase($upload, $classified['movement_records'], $meta);
                $hasDb = true;
            } elseif (!empty($upload->stored_path)) {
                if (str_starts_with($upload->stored_path, 'supabase://')) {
                    try {
                        $clean = substr($upload->stored_path, 11);
                        $parts = explode('/', $clean, 2);
                        $bucket = $parts[0] ?: config('services.supabase.bucket', env('NEXT_PUBLIC_SUPABASE_STORAGE_BUCKET', 'flight-reports'));
                        $storagePath = $parts[1] ?? '';
                        $storageService = app(\App\Services\Storage\SupabaseStorageService::class);
                        $tempPath = $storageService->downloadToTemp($storagePath, $bucket);
                        $this->fdrDbService->syncUploadFromFile($upload, $tempPath, $this->parser);
                        $hasDb = true;
                        @unlink($tempPath);
                    } catch (\Throwable $e) {
                        Log::warning("Auto-heal from Supabase Storage failed for upload #{$upload->id}: " . $e->getMessage());
                    }
                } else {
                    $disk = config('filesystems.default', 'local');
                    $localPath = Storage::disk($disk)->path($upload->stored_path);
                    if (file_exists($localPath)) {
                        $this->fdrDbService->syncUploadFromFile($upload, $localPath, $this->parser);
                        $hasDb = true;
                    }
                }
            }
        }

        if ($hasDb) {
            $isDefaultScope = (
                (!$request->has('leg') || $request->query('leg') === 'ALL') &&
                (!$request->has('operator') || $request->query('operator') === 'ALL') &&
                (!$request->has('traffic') || $request->query('traffic') === 'ALL') &&
                (!$request->has('realization') || $request->query('realization') === 'ALL') &&
                (!$request->has('flight_no') || empty($request->query('flight_no'))) &&
                (!$request->has('suffix') || empty($request->query('suffix'))) &&
                (!$request->has('search') || empty($request->query('search'))) &&
                (!$request->has('start_date') || empty($request->query('start_date'))) &&
                (!$request->has('end_date') || empty($request->query('end_date'))) &&
                (!$request->has('analysis_date') || empty($request->query('analysis_date'))) &&
                (!$request->has('airport') || $request->query('airport') === 'ALL' || empty($request->query('airport'))) &&
                (!in_array($request->query('date_scope'), ['DAY', 'RANGE', 'MONTH', 'YEAR']))
            );

            // Fast-path: Retrieve pre-computed JSON summary from cache column (< 50ms)
            $cached = $isDefaultScope ? $this->fdrDbService->getCachedAnalytics($upload) : null;
            if ($isDefaultScope && empty($cached)) {
                try {
                    $cached = $this->fdrDbService->precomputeAndCacheAnalytics($upload, $meta);
                } catch (\Throwable $e) {
                    Log::warning("Could not precompute analytics on-demand: " . $e->getMessage());
                }
            }

            if ($isDefaultScope && !empty($cached) && !empty($cached['analytics'])) {
                $analytics = $cached['analytics'];
                $availableDates = $cached['available_dates'] ?? [];
                $sourceSummary = $cached['source_summary'] ?? [];
                $airports = $cached['airports'] ?? [$defaultAirport ?: 'CGK'];
                $airlines = $cached['airlines'] ?? [];
                $totalRecords = (int)($cached['total_flights'] ?? ($analytics['kpis']['total_flights'] ?? 0));
                $records = !empty($cached['initial_records']) ? $cached['initial_records'] : FdrFlight::where('upload_id', $upload->id)->orderBy('id')->take(50)->get()->map->toFdrArray()->toArray();
                $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

                $filters = [
                    'date_scope'     => 'ALL_PERIOD',
                    'analysis_level' => 'ALL_PERIOD',
                    'analysis_date'  => null,
                    'analysis_month' => null,
                    'analysis_year'  => null,
                    'airport'        => 'ALL',
                    'leg'            => 'ALL',
                    'operator'       => 'ALL',
                    'traffic'        => 'ALL',
                    'data_type'      => strtoupper(trim($meta['data_type'] ?? 'OPERATIONAL DATA')),
                    'realization'    => 'ALL',
                    'flight_no'      => '',
                    'suffix'         => '',
                    'start_date'     => '',
                    'end_date'       => '',
                    'report_mode'    => 1,
                    'time_basis'     => 'actual',
                    'otp_tolerance'  => 15,
                    'search'         => '',
                    'v'              => time(),
                ];

                $filterResult = [
                    'source_count'   => $totalRecords,
                    'filtered_count' => $totalRecords,
                    'excluded_count' => 0,
                    'active_chips'   => [],
                    'reconciliation' => [
                        'source_count'      => $totalRecords,
                        'filtered_count'    => $totalRecords,
                        'excluded_count'    => 0,
                        'exclusion_reasons' => [],
                    ],
                ];

                $reportType = $request->query('report_type', $meta['report_type'] ?? ($upload->report_data['meta']['report_type'] ?? 'NORMAL'));

                $clientParsedData = [
                    'records'              => $records,
                    'top_delayed_routes'   => $analytics['airline_route']['most_delayed_routes'] ?? [],
                    'most_delayed_routes'  => $analytics['airline_route']['most_delayed_routes'] ?? [],
                    'top_slf_routes'       => $analytics['airline_route']['highest_slf_routes'] ?? [],
                    'highest_slf_routes'   => $analytics['airline_route']['highest_slf_routes'] ?? [],
                    'suffix_distribution' => $analytics['airline_route']['suffix_distribution'] ?? [],
                    'leg_subtypes'         => $analytics['airline_route']['leg_subtypes'] ?? [],
                    'network_routes'       => $analytics['airline_route']['network_routes'] ?? [],
                    'network_airports'     => $analytics['airline_route']['network_airports'] ?? [],
                    'network_summary'      => $analytics['airline_route']['network_summary'] ?? null,
                    'reconciliationMatrix' => $data['reconciliationMatrix'] ?? ($upload->report_data['reconciliationMatrix'] ?? null),
                    'commentTotals'        => $data['commentTotals'] ?? ($upload->report_data['commentTotals'] ?? null),
                    'metadata'             => array_merge($meta, [
                        'totalSeatCapacity' => $analytics['kpis']['total_capacity'] ?? 0,
                        'totalPax'          => $analytics['kpis']['total_passengers'] ?? 0,
                        'avgSlf'            => $analytics['kpis']['load_factor_avg'] ?? 0,
                    ]),
                ];

                return view('fdr.dashboard', [
                    'upload'           => $upload,
                    'meta'             => $meta,
                    'reportType'       => $reportType,
                    'filters'          => $filters,
                    'filterResult'     => $filterResult,
                    'analytics'        => $analytics,
                    'records'          => $records,
                    'totalRecords'     => $totalRecords,
                    'airlines'         => $airlines,
                    'airports'         => $airports,
                    'rawRecordsCount'  => $totalRecords,
                    'dateScope'        => 'ALL_PERIOD',
                    'analysisLevel'    => 'ALL_PERIOD',
                    'analysisDate'     => null,
                    'analysisMonth'    => null,
                    'analysisYear'     => null,
                    'sourceType'       => $sourceSummary['source_type'] ?? 'DAILY',
                    'availableDates'   => $availableDates,
                    'sourceSummary'    => $sourceSummary,
                    'timeBasis'        => 'actual',
                    'otpTolerance'     => 15,
                    'clientParsedData' => $clientParsedData,
                ]);
            }

            $availableDates = FdrFlight::where('upload_id', $upload->id)
                ->selectRaw('DISTINCT flight_date')
                ->orderBy('flight_date')
                ->pluck('flight_date')
                ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : $d->format('Y-m-d'))
                ->toArray();

            $minMax = FdrFlight::where('upload_id', $upload->id)->selectRaw("
                MIN(flight_date) as min_date,
                MAX(flight_date) as max_date,
                COUNT(*) as total_flights,
                COUNT(DISTINCT flight_date) as days_available
            ")->first();

            $pStart = $minMax->min_date ? (is_string($minMax->min_date) ? substr($minMax->min_date, 0, 10) : $minMax->min_date->format('Y-m-d')) : ($meta['period_start'] ?? date('Y-m-d'));
            $pEnd = $minMax->max_date ? (is_string($minMax->max_date) ? substr($minMax->max_date, 0, 10) : $minMax->max_date->format('Y-m-d')) : ($meta['period_end'] ?? date('Y-m-d'));
            $daysCount = (int)($minMax->days_available ?? 1);

            $totalDbRows = (int)$minMax->total_flights;
            $sourceRows = (int)($meta['source_rows'] ?? ($meta['raw_rows'] ?? $totalDbRows));
            $movementRows = (int)($meta['movement_rows'] ?? $totalDbRows);
            $summaryRows = (int)($meta['summary_rows'] ?? ($sourceRows - $movementRows > 0 ? $sourceRows - $movementRows : 0));

            $sourceSummary = [
                'source_type'    => $daysCount > 30 ? 'MONTHLY' : ($daysCount > 1 ? 'MULTI-DAY' : 'DAILY'),
                'period_start'   => $pStart,
                'period_end'     => $pEnd,
                'period_label'   => date('d-m-Y', strtotime($pStart)) . ' → ' . date('d-m-Y', strtotime($pEnd)),
                'days_available' => $daysCount . ' ' . ($daysCount === 1 ? 'Day' : 'Days'),
                'total_flights'  => $totalDbRows,
                'source_rows'    => $sourceRows,
                'movement_rows'  => $movementRows,
                'summary_rows'   => $summaryRows,
                'db_rows'        => $totalDbRows,
            ];

            $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

            $reqAirport = $request->query('airport');
            $activeAirport = (!empty($reqAirport) && strtoupper(trim($reqAirport)) !== 'ALL') ? strtoupper(trim($reqAirport)) : 'ALL';

            $filters = [
                'date_scope'     => $scope['date_scope'],
                'analysis_level' => $scope['analysis_level'],
                'analysis_date'  => $scope['date_scope'] === 'ALL_PERIOD' ? null : $scope['analysis_date'],
                'analysis_month' => $scope['date_scope'] === 'ALL_PERIOD' ? null : $scope['analysis_month'],
                'analysis_year'  => $scope['date_scope'] === 'ALL_PERIOD' ? null : $scope['analysis_year'],
                'airport'        => $activeAirport,
                'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
                'operator'       => trim($request->query('operator', 'ALL')),
                'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
                'data_type'      => strtoupper(trim($request->query('data_type', $meta['data_type'] ?? 'OPERATIONAL DATA'))),
                'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
                'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
                'suffix'         => strtoupper(trim($request->query('suffix', ''))),
                'start_date'     => $scope['date_scope'] === 'ALL_PERIOD' ? '' : trim($request->query('start_date', '')),
                'end_date'       => $scope['date_scope'] === 'ALL_PERIOD' ? '' : trim($request->query('end_date', '')),
                'report_mode'    => (int)$request->query('report_mode', 1),
                'time_basis'     => in_array($request->query('time_basis'), ['scheduled', 'actual'], true) ? $request->query('time_basis') : 'actual',
                'otp_tolerance'  => max(1, min(120, (int)$request->query('otp_tolerance', 15))),
                'search'         => trim($request->query('search', '')),
                'v'              => $request->query('v', time()),
            ];

            $analytics = $this->fdrDbService->computeAnalyticsFromDb($upload, $filters, $meta);

            $filteredQuery = $this->fdrDbService->buildFdrFilteredQuery($upload->id, $filters);
            $totalRecords = (clone $filteredQuery)->count();
            $records = (clone $filteredQuery)->orderBy('id')->take(50)->get()->map->toFdrArray()->toArray();

            $airports = FdrFlight::where('upload_id', $upload->id)
                ->whereNotNull('report_airport')
                ->where('report_airport', '!=', '')
                ->where('report_airport', '!=', 'N/A')
                ->distinct()
                ->orderBy('report_airport')
                ->pluck('report_airport')
                ->toArray();
            if (empty($airports)) {
                $airports = [$defaultAirport ?: 'CGK'];
            }

            $airlines = FdrFlight::where('upload_id', $upload->id)
                ->whereNotNull('airline_code')
                ->where('airline_code', '!=', '')
                ->where('airline_code', '!=', 'N/A')
                ->whereRaw("LOWER(airline_code) NOT LIKE '%pax all%'")
                ->distinct()
                ->orderBy('airline_code')
                ->pluck('airline_code')
                ->toArray();

            $filterResult = [
                'source_count'   => (int)$minMax->total_flights,
                'filtered_count' => $totalRecords,
                'excluded_count' => max(0, (int)$minMax->total_flights - $totalRecords),
                'active_chips'   => $this->buildActiveChips($filters, $scope, count($airports) <= 1),
                'reconciliation' => [
                    'source_count'      => (int)$minMax->total_flights,
                    'filtered_count'    => $totalRecords,
                    'excluded_count'    => max(0, (int)$minMax->total_flights - $totalRecords),
                    'exclusion_reasons' => [],
                ],
            ];

            $reportType = $request->query('report_type', $meta['report_type'] ?? ($upload->report_data['meta']['report_type'] ?? 'NORMAL'));

            $clientParsedData = [
                'records'              => $records,
                'top_delayed_routes'   => $analytics['airline_route']['most_delayed_routes'] ?? [],
                'most_delayed_routes'  => $analytics['airline_route']['most_delayed_routes'] ?? [],
                'top_slf_routes'       => $analytics['airline_route']['highest_slf_routes'] ?? [],
                'highest_slf_routes'   => $analytics['airline_route']['highest_slf_routes'] ?? [],
                'suffix_distribution' => $analytics['airline_route']['suffix_distribution'] ?? [],
                'leg_subtypes'         => $analytics['airline_route']['leg_subtypes'] ?? [],
                'reconciliationMatrix' => $data['reconciliationMatrix'] ?? ($upload->report_data['reconciliationMatrix'] ?? null),
                'commentTotals'        => $data['commentTotals'] ?? ($upload->report_data['commentTotals'] ?? null),
                'metadata'             => array_merge($meta, [
                    'totalSeatCapacity' => $analytics['kpis']['total_capacity'] ?? 0,
                    'totalPax'          => $analytics['kpis']['total_passengers'] ?? 0,
                    'avgSlf'            => $analytics['kpis']['load_factor_avg'] ?? 0,
                ]),
            ];

            return view('fdr.dashboard', [
                'upload'           => $upload,
                'meta'             => $meta,
                'reportType'       => $reportType,
                'filters'          => $filters,
                'filterResult'     => $filterResult,
                'analytics'        => $analytics,
                'records'          => $records,
                'totalRecords'     => $totalRecords,
                'airlines'         => $airlines,
                'airports'         => $airports,
                'rawRecordsCount'  => (int)$minMax->total_flights,
                'dateScope'        => $scope['date_scope'],
                'analysisLevel'    => $scope['analysis_level'],
                'analysisDate'     => $scope['analysis_date'],
                'analysisMonth'    => $scope['analysis_month'],
                'analysisYear'     => $scope['analysis_year'],
                'sourceType'       => $scope['source_type'],
                'availableDates'   => $availableDates,
                'sourceSummary'    => $sourceSummary,
                'timeBasis'        => $filters['time_basis'],
                'otpTolerance'     => $filters['otp_tolerance'],
                'clientParsedData' => $clientParsedData,
            ]);
        }

        // ── Legacy In-Memory Datasets or FDR DATA NOT READY (§6, §39, §41) ──
        $rawRecords = $upload->getOffloadedRecords();
        if (empty($rawRecords)) {
            $job = FdrProcessingJob::where('upload_id', $upload->id)->latest()->first();
            return response()->view('fdr.not_ready', [
                'upload'       => $upload,
                'job'          => $job,
                'sourceFile'   => $upload->original_filename ?: 'CGK GA FDR.xls',
                'storedPath'   => $upload->stored_path,
                'reprocessUrl' => route('fdr.reprocess', $upload->id),
            ], 422);
        }
        $availableDates = $this->extractAvailableDates($rawRecords, $meta);
        $sourceSummary = $this->buildSourceSummary($rawRecords, $availableDates, $meta);
        $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

        $filters = [
            'date_scope'     => $scope['date_scope'],
            'analysis_level' => $scope['analysis_level'],
            'analysis_date'  => $scope['analysis_date'],
            'analysis_month' => $scope['analysis_month'],
            'analysis_year'  => $scope['analysis_year'],
            'airport'        => strtoupper(trim($request->query('airport', 'ALL'))),
            'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'       => trim($request->query('operator', 'ALL')),
            'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'      => strtoupper(trim($request->query('data_type', $meta['data_type'] ?? 'OPERATIONAL DATA'))),
            'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'         => strtoupper(trim($request->query('suffix', ''))),
            'start_date'     => trim($request->query('start_date', '')),
            'end_date'       => trim($request->query('end_date', '')),
            'report_mode'    => (int)$request->query('report_mode', 1),
            'time_basis'     => (function() use ($request, $meta, $rawRecords) {
                $requested = $request->query('time_basis');
                if (in_array($requested, ['scheduled', 'actual'], true)) return $requested;
                return 'actual';
            })(),
            'otp_tolerance'  => max(1, min(120, (int)$request->query('otp_tolerance', 15))),
            'search'         => trim($request->query('search', '')),
            'v'              => $request->query('v', time()),
        ];

        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);
        $filteredRecords = $filterResult['records'];

        $analytics = $this->analytics->compute($filteredRecords, $meta, [
            'report_mode'    => $filters['report_mode'],
            'time_basis'     => $filters['time_basis'],
            'report_date'    => $scope['analysis_date'],
            'analysis_level' => $scope['analysis_level'],
            'date_scope'     => $scope['date_scope'],
            'otp_tolerance'  => $filters['otp_tolerance'],
        ]);

        $airlines = [];
        $reportAirports = [];
        if (!empty($defaultAirport)) {
            $reportAirports[$defaultAirport] = true;
        }

        foreach ($rawRecords as $r) {
            if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY') continue;
            $al = trim($r['air_line'] ?? '');
            if ($al === '' || $al === 'N/A' || strcasecmp($al, 'PAX ALL') === 0 || stripos($al, 'PAX ALL') !== false) continue;
            $airlines[$al] = true;
            $rap = strtoupper(trim($r['report_airport'] ?? ($r['branch'] ?? '')));
            if (!empty($rap) && $rap !== 'N/A') {
                $reportAirports[$rap] = true;
            }
        }
        ksort($airlines);
        $airports = array_keys($reportAirports);
        sort($airports);

        $reportType = $request->query('report_type', $meta['report_type'] ?? ($upload->report_data['meta']['report_type'] ?? 'NORMAL'));

        $clientParsedData = [
            'records'              => array_slice($filteredRecords, 0, 50),
            'kpis'                 => $analytics['kpis'] ?? [],
            'totalRecords'         => count($filteredRecords),
            'hourlyDistribution'   => $analytics['hourly_distribution'] ?? null,
            'combinedTrend'        => $analytics['combined_trend'] ?? null,
            'airlineRoute'         => $analytics['airline_route'] ?? null,
            'airline_route'        => $analytics['airline_route'] ?? null,
            'top_busiest_routes'   => $analytics['airline_route']['top_routes'] ?? ($analytics['airline_route']['top_busiest_routes'] ?? []),
            'top_delayed_routes'   => $analytics['airline_route']['most_delayed_routes'] ?? [],
            'most_delayed_routes'  => $analytics['airline_route']['most_delayed_routes'] ?? [],
            'top_slf_routes'       => $analytics['airline_route']['highest_slf_routes'] ?? [],
            'highest_slf_routes'   => $analytics['airline_route']['highest_slf_routes'] ?? [],
            'suffix_distribution' => $analytics['airline_route']['suffix_distribution'] ?? [],
            'leg_subtypes'         => $analytics['airline_route']['leg_subtypes'] ?? [],
            'metadata'             => $meta,
        ];

        return view('fdr.dashboard', [
            'upload'           => $upload,
            'meta'             => $meta,
            'reportType'       => $reportType,
            'filters'          => $filters,
            'filterResult'     => $filterResult,
            'analytics'        => $analytics,
            'records'          => array_slice($filteredRecords, 0, 50),
            'totalRecords'     => count($filteredRecords),
            'airlines'         => array_keys($airlines),
            'airports'         => $airports,
            'rawRecordsCount'  => count($rawRecords),
            'dateScope'        => $scope['date_scope'],
            'analysisLevel'    => $scope['analysis_level'],
            'analysisDate'     => $scope['analysis_date'],
            'analysisMonth'    => $scope['analysis_month'],
            'analysisYear'     => $scope['analysis_year'],
            'sourceType'       => $scope['source_type'],
            'availableDates'   => $availableDates,
            'sourceSummary'    => $sourceSummary,
            'timeBasis'        => $filters['time_basis'],
            'otpTolerance'     => $filters['otp_tolerance'],
            'clientParsedData' => $clientParsedData,
        ]);
    }

    /**
     * Reactive AJAX API endpoint for live filtering without page reloads.
     * Resolves race conditions (latest request version token wins).
     */
    public function filterApi(Upload $upload, Request $request)
    {
        ini_set('memory_limit', '1024M');

        if ($upload->report_type !== 'fdr') {
            return response()->json(['error' => 'Report data not found.'], 404);
        }

        $meta = $upload->report_data['meta'] ?? [];
        $hasDb = $this->fdrDbService->hasDatabaseRecords($upload);

        if ($hasDb) {
            $availableDates = FdrFlight::where('upload_id', $upload->id)
                ->selectRaw('DISTINCT flight_date')
                ->orderBy('flight_date')
                ->pluck('flight_date')
                ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : $d->format('Y-m-d'))
                ->toArray();

            $minMax = FdrFlight::where('upload_id', $upload->id)->selectRaw("
                MIN(flight_date) as min_date,
                MAX(flight_date) as max_date,
                COUNT(*) as total_flights,
                COUNT(DISTINCT flight_date) as days_available
            ")->first();

            $pStart = $minMax->min_date ? (is_string($minMax->min_date) ? substr($minMax->min_date, 0, 10) : $minMax->min_date->format('Y-m-d')) : ($meta['period_start'] ?? date('Y-m-d'));
            $pEnd = $minMax->max_date ? (is_string($minMax->max_date) ? substr($minMax->max_date, 0, 10) : $minMax->max_date->format('Y-m-d')) : ($meta['period_end'] ?? date('Y-m-d'));
            $daysCount = (int)($minMax->days_available ?? 1);

            $totalDbRows = (int)$minMax->total_flights;
            $sourceRows = (int)($meta['source_rows'] ?? ($meta['raw_rows'] ?? $totalDbRows));
            $movementRows = (int)($meta['movement_rows'] ?? $totalDbRows);
            $summaryRows = (int)($meta['summary_rows'] ?? ($sourceRows - $movementRows > 0 ? $sourceRows - $movementRows : 0));

            $sourceSummary = [
                'source_type'    => $daysCount > 30 ? 'MONTHLY' : ($daysCount > 1 ? 'MULTI-DAY' : 'DAILY'),
                'period_start'   => $pStart,
                'period_end'     => $pEnd,
                'period_label'   => date('d-m-Y', strtotime($pStart)) . ' → ' . date('d-m-Y', strtotime($pEnd)),
                'days_available' => $daysCount . ' ' . ($daysCount === 1 ? 'Day' : 'Days'),
                'total_flights'  => $totalDbRows,
                'source_rows'    => $sourceRows,
                'movement_rows'  => $movementRows,
                'summary_rows'   => $summaryRows,
                'db_rows'        => $totalDbRows,
            ];

            $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

            $reqAirport = $request->query('airport');
            $activeAirport = (!empty($reqAirport) && strtoupper(trim($reqAirport)) !== 'ALL') ? strtoupper(trim($reqAirport)) : 'ALL';

            $filters = [
                'date_scope'     => $scope['date_scope'],
                'analysis_level' => $scope['analysis_level'],
                'analysis_date'  => $scope['date_scope'] === 'ALL_PERIOD' ? null : $scope['analysis_date'],
                'analysis_month' => $scope['date_scope'] === 'ALL_PERIOD' ? null : $scope['analysis_month'],
                'analysis_year'  => $scope['date_scope'] === 'ALL_PERIOD' ? null : $scope['analysis_year'],
                'airport'        => $activeAirport,
                'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
                'operator'       => trim($request->query('operator', 'ALL')),
                'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
                'data_type'      => strtoupper(trim($request->query('data_type', 'ALL'))),
                'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
                'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
                'suffix'         => strtoupper(trim($request->query('suffix', ''))),
                'start_date'     => $scope['date_scope'] === 'ALL_PERIOD' ? '' : trim($request->query('start_date', '')),
                'end_date'       => $scope['date_scope'] === 'ALL_PERIOD' ? '' : trim($request->query('end_date', '')),
                'report_mode'    => (int)$request->query('report_mode', 1),
                'time_basis'     => in_array($request->query('time_basis'), ['scheduled', 'actual'], true) ? $request->query('time_basis') : 'actual',
                'otp_tolerance'  => max(1, min(120, (int)$request->query('otp_tolerance', 15))),
                'search'         => trim($request->query('search', '')),
                'v'              => $request->query('v', time()),
            ];

            $page = max(1, (int)$request->query('page', 1));
            $perPage = max(1, min(100, (int)$request->query('per_page', 50)));

            $isDefaultScope = (
                $page === 1 &&
                (!$request->has('leg') || $request->query('leg') === 'ALL') &&
                (!$request->has('operator') || $request->query('operator') === 'ALL') &&
                (!$request->has('traffic') || $request->query('traffic') === 'ALL') &&
                (!$request->has('realization') || $request->query('realization') === 'ALL') &&
                (!$request->has('flight_no') || empty($request->query('flight_no'))) &&
                (!$request->has('suffix') || empty($request->query('suffix'))) &&
                (!$request->has('search') || empty($request->query('search'))) &&
                (!$request->has('start_date') || empty($request->query('start_date'))) &&
                (!$request->has('end_date') || empty($request->query('end_date'))) &&
                (!$request->has('analysis_date') || empty($request->query('analysis_date'))) &&
                (!$request->has('airport') || $request->query('airport') === 'ALL' || empty($request->query('airport'))) &&
                (!in_array($request->query('date_scope'), ['DAY', 'RANGE', 'MONTH', 'YEAR']))
            );

            $cached = $isDefaultScope ? $this->fdrDbService->getCachedAnalytics($upload) : null;
            if ($isDefaultScope && empty($cached)) {
                try {
                    $cached = $this->fdrDbService->precomputeAndCacheAnalytics($upload, $meta);
                } catch (\Throwable $e) {}
            }

            if ($isDefaultScope && !empty($cached) && !empty($cached['analytics'])) {
                $analytics = $cached['analytics'];
                $total = (int)($cached['total_flights'] ?? ($analytics['kpis']['total_flights'] ?? 0));
                $pagedRecords = !empty($cached['initial_records'])
                    ? array_slice($cached['initial_records'], 0, $perPage)
                    : FdrFlight::where('upload_id', $upload->id)->orderBy('id')->take($perPage)->get()->map->toFdrArray()->toArray();
            } else {
                $analytics = $this->fdrDbService->computeAnalyticsFromDb($upload, $filters, $meta);
                $baseQuery = $this->fdrDbService->buildFdrFilteredQuery($upload->id, $filters);
                $total = (clone $baseQuery)->count();
                $offset = ($page - 1) * $perPage;
                $pagedFlights = (clone $baseQuery)->orderBy('id')->skip($offset)->take($perPage)->get();
                $pagedRecords = $pagedFlights->map->toFdrArray()->toArray();
            }

            $totalMovements = (int)$minMax->total_flights;
            $activeChips = $this->buildActiveChips($filters, $scope, false);

            return response()->json([
                'version'             => $filters['v'],
                'date_scope'          => $scope['date_scope'],
                'analysis_level'      => $scope['analysis_level'],
                'analysis_date'       => $scope['analysis_date'],
                'analysis_month'      => $scope['analysis_month'],
                'analysis_year'       => $scope['analysis_year'],
                'analysis_date_label' => $scope['analysis_date'] ? date('d-m-Y', strtotime($scope['analysis_date'])) : 'FULL RANGE',
                'analysis_date_title' => $scope['analysis_date'] ? strtoupper(date('d F Y', strtotime($scope['analysis_date']))) : 'FULL RANGE',
                'source_summary'      => $sourceSummary,
                'source_type'         => $scope['source_type'],
                'available_dates'     => $availableDates,
                'total_count'         => $totalMovements,
                'source_count'        => $totalMovements,
                'normalized_count'    => $totalMovements,
                'filtered_count'      => $total,
                'excluded_count'      => max(0, $totalMovements - $total),
                'reconciliation'      => [
                    'source_count'      => $totalMovements,
                    'filtered_count'    => $total,
                    'excluded_count'    => max(0, $totalMovements - $total),
                    'exclusion_reasons' => [],
                ],
                'exclusion_reasons'   => [],
                'counter_text'        => "Showing " . number_format($total) . " of " . number_format($totalMovements) . " records",
                'active_chips'        => $activeChips,
                'kpis'                => $analytics['kpis'],
                'hourly_charts'       => $analytics['hourly_charts'],
                'hourly_distribution' => $analytics['hourly_distribution'] ?? null,
                'combined_trend'      => $analytics['combined_trend'] ?? null,
                'sched_vs_real'       => $analytics['schedule_vs_realization'],
                'pax_analytics'       => $analytics['passenger_analytics'],
                'airline_route'       => $analytics['airline_route'],
                'fleet_performance'   => $analytics['fleet_performance'],
                'ground_ops'          => $analytics['ground_operations'],
                'mode_payload'        => $analytics['mode_payload'],
                'reconciliation_apps' => $analytics['reconciliation_apps'],
                'reconciliation_edifly' => $analytics['reconciliation_edifly'],
                'time_basis'          => $filters['time_basis'],
                'otp_tolerance'       => $filters['otp_tolerance'],
                'records'             => $pagedRecords,
                'pagination'          => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total_pages'  => max(1, (int)ceil($total / $perPage)),
                    'total'        => $total,
                ],
            ]);
        }

        // ── Fallback for Legacy In-Memory Datasets ──
        $rawRecords = $upload->getOffloadedRecords();
        if (empty($rawRecords)) {
            return response()->json([
                'error'         => 'FDR_NOT_READY',
                'message'       => 'Normalized flight records have not been loaded into the database.',
                'upload_id'     => $upload->id,
                'reprocess_url' => route('fdr.reprocess', $upload->id),
            ], 422);
        }
        $availableDates = $this->extractAvailableDates($rawRecords, $meta);
        $sourceSummary = $this->buildSourceSummary($rawRecords, $availableDates, $meta);
        $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

        $filters = [
            'date_scope'     => $scope['date_scope'],
            'analysis_level' => $scope['analysis_level'],
            'analysis_date'  => $scope['analysis_date'],
            'analysis_month' => $scope['analysis_month'],
            'analysis_year'  => $scope['analysis_year'],
            'airport'        => strtoupper(trim($request->query('airport', 'ALL'))),
            'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'       => trim($request->query('operator', 'ALL')),
            'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'      => strtoupper(trim($request->query('data_type', 'ALL'))),
            'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'         => strtoupper(trim($request->query('suffix', ''))),
            'start_date'     => trim($request->query('start_date', '')),
            'end_date'       => trim($request->query('end_date', '')),
            'report_mode'    => (int)$request->query('report_mode', 1),
            'time_basis'     => in_array($request->query('time_basis'), ['scheduled', 'actual'], true) ? $request->query('time_basis') : 'actual',
            'otp_tolerance'  => max(1, min(120, (int)$request->query('otp_tolerance', 15))),
            'search'         => trim($request->query('search', '')),
            'v'              => $request->query('v', time()),
        ];

        $page = max(1, (int)$request->query('page', 1));
        $perPage = max(1, min(100, (int)$request->query('per_page', 50)));

        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);
        $filteredRecords = $filterResult['records'];

        $analytics = $this->analytics->compute($filteredRecords, $meta, [
            'report_mode'    => $filters['report_mode'],
            'time_basis'     => $filters['time_basis'],
            'report_date'    => $scope['analysis_date'],
            'analysis_level' => $scope['analysis_level'],
            'date_scope'     => $scope['date_scope'],
            'otp_tolerance'  => $filters['otp_tolerance'],
        ]);

        $total = count($filteredRecords);
        $offset = ($page - 1) * $perPage;
        $pagedRecords = array_slice($filteredRecords, $offset, $perPage);
        $totalMovements = $sourceSummary['total_flights'] ?? $filterResult['source_count'];

        return response()->json([
            'version'             => $filters['v'],
            'date_scope'          => $scope['date_scope'],
            'analysis_level'      => $scope['analysis_level'],
            'analysis_date'       => $scope['analysis_date'],
            'analysis_month'      => $scope['analysis_month'],
            'analysis_year'       => $scope['analysis_year'],
            'analysis_date_label' => $scope['analysis_date'] ? date('d-m-Y', strtotime($scope['analysis_date'])) : 'FULL RANGE',
            'analysis_date_title' => $scope['analysis_date'] ? strtoupper(date('d F Y', strtotime($scope['analysis_date']))) : 'FULL RANGE',
            'source_summary'      => $sourceSummary,
            'source_type'         => $scope['source_type'],
            'available_dates'     => $availableDates,
            'total_count'         => $totalMovements,
            'source_count'        => $totalMovements,
            'normalized_count'    => $totalMovements,
            'filtered_count'      => $filterResult['filtered_count'],
            'excluded_count'      => $filterResult['excluded_count'],
            'reconciliation'      => $filterResult['reconciliation'],
            'exclusion_reasons'   => $filterResult['reconciliation']['exclusion_reasons'],
            'counter_text'        => "Showing " . number_format($total) . " of " . number_format($totalMovements) . " records",
            'active_chips'        => $filterResult['active_chips'],
            'kpis'                => $analytics['kpis'],
            'hourly_charts'       => $analytics['hourly_charts'],
            'hourly_distribution' => $analytics['hourly_distribution'] ?? null,
            'combined_trend'      => $analytics['combined_trend'] ?? null,
            'sched_vs_real'       => $analytics['schedule_vs_realization'],
            'pax_analytics'       => $analytics['passenger_analytics'],
            'airline_route'       => $analytics['airline_route'],
            'fleet_performance'   => $analytics['fleet_performance'],
            'ground_ops'          => $analytics['ground_operations'],
            'mode_payload'        => $analytics['mode_payload'],
            'reconciliation_apps' => $analytics['reconciliation_apps'],
            'reconciliation_edifly' => $analytics['reconciliation_edifly'],
            'time_basis'          => $filters['time_basis'],
            'otp_tolerance'       => $filters['otp_tolerance'],
            'records'             => $pagedRecords,
            'pagination'          => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total_pages'  => max(1, (int)ceil($total / $perPage)),
                'total'        => $total,
            ],
        ]);
    }

    /**
     * Get individual flight details modal payload.
     */
    public function flightDetails(Upload $upload, $flightIndex, Request $request)
    {
        if ($this->fdrDbService->hasDatabaseRecords($upload)) {
            $flight = FdrFlight::where('upload_id', $upload->id)
                ->where(function($q) use ($flightIndex) {
                    $q->where('id', (int)$flightIndex)
                      ->orWhereRaw("(raw_data->>'index')::int = ?", [(int)$flightIndex]);
                })
                ->first();

            if ($flight) {
                return response()->json(['flight' => $flight->toFdrArray()]);
            }
        }

        $records = $upload->getOffloadedRecords();
        $idx = (int)$flightIndex - 1;

        if (!isset($records[$idx])) {
            foreach ($records as $r) {
                if ((int)($r['index'] ?? 0) === (int)$flightIndex) {
                    return response()->json(['flight' => $r]);
                }
            }
            return response()->json(['error' => 'Flight record not found.'], 404);
        }

        return response()->json(['flight' => $records[$idx]]);
    }

    /**
     * Export raw filtered dataset as formatted CSV spreadsheet with UTF-8 BOM.
     */
    public function exportCsv(Upload $upload, Request $request): StreamedResponse
    {
        ini_set('memory_limit', '1024M');

        if ($upload->report_type !== 'fdr') {
            abort(404, "Report data not ready for export.");
        }

        $meta = $upload->report_data['meta'] ?? [];
        $hasDb = $this->fdrDbService->hasDatabaseRecords($upload);

        $availableDates = $hasDb
            ? FdrFlight::where('upload_id', $upload->id)->selectRaw('DISTINCT flight_date')->orderBy('flight_date')->pluck('flight_date')->map(fn($d) => is_string($d) ? substr($d, 0, 10) : $d->format('Y-m-d'))->toArray()
            : $this->extractAvailableDates($upload->getOffloadedRecords(), $meta);

        $sourceSummary = $hasDb
            ? (function() use ($upload, $meta) {
                $minMax = FdrFlight::where('upload_id', $upload->id)->selectRaw("MIN(flight_date) as min_date, MAX(flight_date) as max_date, COUNT(*) as total_flights, COUNT(DISTINCT flight_date) as days_available")->first();
                $pStart = $minMax->min_date ? (is_string($minMax->min_date) ? substr($minMax->min_date, 0, 10) : $minMax->min_date->format('Y-m-d')) : ($meta['period_start'] ?? date('Y-m-d'));
                $pEnd = $minMax->max_date ? (is_string($minMax->max_date) ? substr($minMax->max_date, 0, 10) : $minMax->max_date->format('Y-m-d')) : ($meta['period_end'] ?? date('Y-m-d'));
                $daysCount = (int)($minMax->days_available ?? 1);
                return [
                    'source_type'    => $daysCount > 30 ? 'MONTHLY' : ($daysCount > 1 ? 'MULTI-DAY' : 'DAILY'),
                    'period_start'   => $pStart,
                    'period_end'     => $pEnd,
                    'period_label'   => date('d-m-Y', strtotime($pStart)) . ' → ' . date('d-m-Y', strtotime($pEnd)),
                    'days_available' => $daysCount . ' ' . ($daysCount === 1 ? 'Day' : 'Days'),
                    'total_flights'  => (int)$minMax->total_flights,
                ];
            })()
            : $this->buildSourceSummary($upload->getOffloadedRecords(), $availableDates, $meta);

        $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

        $reqAirport = $request->query('airport');
        $activeAirport = (!empty($reqAirport) && strtoupper(trim($reqAirport)) !== 'ALL') ? strtoupper(trim($reqAirport)) : 'ALL';

        $filters = [
            'date_scope'     => $scope['date_scope'],
            'analysis_level' => $scope['analysis_level'],
            'analysis_date'  => $scope['analysis_date'],
            'analysis_month' => $scope['analysis_month'],
            'analysis_year'  => $scope['analysis_year'],
            'airport'        => $activeAirport,
            'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'       => trim($request->query('operator', 'ALL')),
            'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'      => strtoupper(trim($request->query('data_type', 'ALL'))),
            'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'         => strtoupper(trim($request->query('suffix', ''))),
            'start_date'     => trim($request->query('start_date', '')),
            'end_date'       => trim($request->query('end_date', '')),
            'report_mode'    => (int)$request->query('report_mode', 1),
            'search'         => trim($request->query('search', '')),
        ];

        $dateSuffix = !empty($scope['analysis_date']) ? date('Ymd', strtotime($scope['analysis_date'])) : date('Ymd_His');
        $filename = 'FDR_' . ($meta['airport'] ?? 'AIRPORT') . '_' . $dateSuffix . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($upload, $hasDb, $meta, $filters, $scope, $sourceSummary) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            // Header metadata comments
            fputcsv($handle, ['SLOTWAVES FLIGHT DAILY REPORT (FDR) ANALYTICS']);
            fputcsv($handle, ['Airport:', $meta['airport'] ?? 'CGK', 'Name:', $meta['airport_name'] ?? 'N/A']);
            fputcsv($handle, ['Source Period:', $sourceSummary['period_label'] ?? 'N/A', 'Source Type:', $sourceSummary['source_type']]);
            fputcsv($handle, ['Analysis Level:', $scope['analysis_level']]);
            if ($scope['date_scope'] === 'ALL_PERIOD') {
                fputcsv($handle, ['Analysis Scope:', 'FULL RANGE (' . ($sourceSummary['period_label'] ?? 'All Dates') . ')']);
            } elseif ($scope['analysis_level'] === 'DAILY') {
                fputcsv($handle, ['Peak Analysis Date:', date('d-m-Y', strtotime($scope['analysis_date'])) . ' (' . date('d F Y', strtotime($scope['analysis_date'])) . ')']);
            } elseif ($scope['analysis_level'] === 'MONTHLY') {
                fputcsv($handle, ['Analysis Month:', date('F Y', strtotime($scope['analysis_month'] . '-01'))]);
            } elseif ($scope['analysis_level'] === 'YEARLY') {
                fputcsv($handle, ['Analysis Year:', $scope['analysis_year']]);
            }
            fputcsv($handle, ['Flight Movement:', $filters['leg'] ?: 'ALL']);
            fputcsv($handle, ['Traffic Type:', $filters['traffic'] ?: 'ALL']);
            fputcsv($handle, ['Operator:', $filters['operator'] ?: ($meta['operator'] ?? 'ALL AIRLINE')]);
            fputcsv($handle, ['Realization:', $filters['realization'] ?: ($meta['realization'] ?? 'YES')]);
            fputcsv($handle, ['Export Timestamp:', date('Y-m-d H:i:s')]);
            fputcsv($handle, []);

            $reportType = strtoupper($meta['report_type'] ?? ($upload->report_data['report_type'] ?? 'NORMAL'));

            if ($reportType === 'LOAD_FACTOR') {
                $csvHeaders = [
                    'NO', 'AIRLINES', 'FLIGHT NUMBER', 'SCHEDULED TIME', 'TYPE OF AIRCRAFT', 'AIRCRAFT REG NO',
                    'LEG', 'ARRIVAL ROUTE', 'DEPARTURE ROUTE', 'SEAT CAPACITY', 'PAX', 'SLF (%)', 'AIRPORT'
                ];
                fputcsv($handle, $csvHeaders);

                $records = $hasDb ? $this->fdrDbService->buildFdrFilteredQuery($upload->id, $filters)->orderBy('id')->get()->map(fn($f) => $f->toFdrArray())->toArray() : ($upload->getOffloadedRecords() ?? []);
                $idx = 0;
                foreach ($records as $r) {
                    $idx++;
                    fputcsv($handle, [
                        $idx,
                        $r['air_line'] ?? ($r['airline'] ?? ''),
                        $r['flight_no'] ?? ($r['flight_number'] ?? ''),
                        $r['sibt'] ?? ($r['sobt'] ?? ($r['scheduled_time'] ?? '')),
                        $r['aircraft_type'] ?? ($r['type_of_aircraft'] ?? ($r['desc'] ?? '')),
                        $r['reg_no'] ?? ($r['aircraft_reg_no'] ?? ''),
                        $r['leg'] ?? '',
                        $r['arrival_route'] ?? ($r['city_1'] ?? ''),
                        $r['departure_route'] ?? ($r['city_2'] ?? ''),
                        $r['cap'] ?? ($r['seat_capacity'] ?? 0),
                        $r['load'] ?? ($r['pax'] ?? 0),
                        $r['load_factor'] ?? ($r['slf'] ?? '0.0%'),
                        $r['airport'] ?? ($meta['airport'] ?? 'CGK'),
                    ]);
                }
            } elseif (in_array($reportType, ['COMPARE_LOAD_FACTOR', 'COMPARE_LOAD_FACTOR_DAY'])) {
                $csvHeaders = [
                    'NO', 'AIRPORT', 'FLIGHT PREV YEAR', 'FLIGHT CURR YEAR', 'FLIGHT RATIO (%)',
                    'PAX PREV YEAR', 'PAX CURR YEAR', 'PAX RATIO (%)',
                    'CARGO PREV YEAR', 'CARGO CURR YEAR', 'CARGO RATIO (%)',
                    'SEAT CAP PREV YEAR', 'SEAT CAP CURR YEAR', 'SEAT CAP RATIO (%)',
                    'SLF PREV YEAR SEAT CAP', 'SLF PREV YEAR PAX', 'SLF PREV YEAR (%)',
                    'SLF CURR YEAR SEAT CAP', 'SLF CURR YEAR PAX', 'SLF CURR YEAR (%)'
                ];
                fputcsv($handle, $csvHeaders);

                $records = $upload->report_data['yoY']['records'] ?? ($upload->getOffloadedRecords() ?? []);
                $idx = 0;
                foreach ($records as $r) {
                    $idx++;
                    fputcsv($handle, [
                        $idx,
                        $r['airport'] ?? ($r['station'] ?? ''),
                        $r['flight_prev'] ?? 0,
                        $r['flight_curr'] ?? 0,
                        $r['flight_ratio'] ?? '0.0%',
                        $r['pax_prev'] ?? 0,
                        $r['pax_curr'] ?? 0,
                        $r['pax_ratio'] ?? '0.0%',
                        $r['cargo_prev'] ?? 0,
                        $r['cargo_curr'] ?? 0,
                        $r['cargo_ratio'] ?? '0.0%',
                        $r['seatcap_prev'] ?? 0,
                        $r['seatcap_curr'] ?? 0,
                        $r['seatcap_ratio'] ?? '0.0%',
                        $r['slf_prev_seatcap'] ?? 0,
                        $r['slf_prev_pax'] ?? 0,
                        $r['slf_prev_ratio'] ?? '0.0%',
                        $r['slf_curr_seatcap'] ?? 0,
                        $r['slf_curr_pax'] ?? 0,
                        $r['slf_curr_ratio'] ?? '0.0%',
                    ]);
                }
            } elseif ($reportType === 'OASYS_VS_APPS') {
                $csvHeaders = [
                    'NO', 'AIRLINE', 'STAD', 'OFF BLOCK TIME', 'LEG', 'D/I', 'CITY',
                    'ADULT', 'CHILD', 'INFANT', 'TRANSIT', 'TRANSFER', 'DIVERT', 'MISSED', 'CREW', 'EX. CREW'
                ];
                fputcsv($handle, $csvHeaders);

                $records = $upload->getOffloadedRecords() ?? [];
                $idx = 0;
                foreach ($records as $r) {
                    $idx++;
                    fputcsv($handle, [
                        $idx,
                        $r['air_line'] ?? ($r['airline'] ?? ''),
                        $r['sobt'] ?? ($r['stad'] ?? ''),
                        $r['aobt'] ?? ($r['off_block_time'] ?? ''),
                        $r['leg'] ?? 'D',
                        $r['traffic'] ?? ($r['di'] ?? 'D'),
                        $r['city_1'] ?? ($r['city'] ?? ''),
                        $r['adult'] ?? 0,
                        $r['child'] ?? 0,
                        $r['infant'] ?? 0,
                        $r['transit'] ?? 0,
                        $r['transfer'] ?? 0,
                        $r['divert'] ?? 0,
                        $r['miss'] ?? ($r['missed'] ?? 0),
                        $r['crw'] ?? ($r['crew'] ?? 0),
                        $r['ex_crw'] ?? 0,
                    ]);
                }
            } elseif ($reportType === 'OASYS_VS_EDIFLY') {
                $csvHeaders = [
                    'NO', 'AIRLINE', 'FLIGHT NO', 'PAIRED NO', 'DESC', 'SOBT/SIBT', 'AOBT/AIBT',
                    'LEG', 'STATION 1', 'STATION 2', 'MTOW', 'REG. NO', 'API PAX EDIFLY', 'API CARGO EDIFLY'
                ];
                fputcsv($handle, $csvHeaders);

                $records = $upload->getOffloadedRecords() ?? [];
                $idx = 0;
                foreach ($records as $r) {
                    $idx++;
                    fputcsv($handle, [
                        $idx,
                        $r['air_line'] ?? ($r['airline'] ?? ''),
                        $r['flight_no'] ?? '',
                        $r['paired_no'] ?? '',
                        $r['desc'] ?? '',
                        $r['sobt'] ?? ($r['sibt'] ?? ''),
                        $r['aobt'] ?? ($r['aibt'] ?? ''),
                        $r['leg'] ?? '',
                        $r['city_1'] ?? ($r['station_1'] ?? ''),
                        $r['city_2'] ?? ($r['station_2'] ?? ''),
                        $r['mtow'] ?? ($r['mtow_ton'] ?? 0),
                        $r['reg_no'] ?? '',
                        $r['api_pax_edifly'] ?? ($r['pax_integration'] ?? 'NO INTEGRATION'),
                        $r['api_cargo_edifly'] ?? ($r['cargo_integration'] ?? 'NO INTEGRATION'),
                    ]);
                }
            } else {
                // Strict raw FDR headers for NORMAL / EX_HALIM
                $csvHeaders = [
                    'NO', 'AIR LINE', 'FLIGHT NO', 'PAIRED NO', 'SUFFIX', 'SUFFIX_LABEL', 'SIBT', 'SOBT', 'AIBT', 'AOBT',
                    'DELAY_MINUTES', 'LEG', 'LEG_SUBTYPE', 'DIRECTION', 'CITY 1', 'CITY 2', 'ROUTE', 'TRAFFIC', 'MTOW (TON)', 'REG. NO',
                    'CAP.', 'LOAD', 'LOAD FACTOR (%)', 'ADULT', 'CHILD', 'INFANT', 'UMROH', 'TRANSIT', 'TRANSFER',
                    'DIVERT', 'MISS', 'CRW', 'EX. CRW', 'OPERATING_CREW', 'CAR. (KG)', 'BAGG. (KG)', 'POS (KG)',
                    'STAND', 'RUN WAY', 'FINAL', 'STATUS'
                ];
                fputcsv($handle, $csvHeaders);

                $idx = 0;
                if ($hasDb) {
                    $cursor = $this->fdrDbService->buildFdrFilteredQuery($upload->id, $filters)->orderBy('id')->cursor();
                    foreach ($cursor as $flight) {
                        $idx++;
                        $r = $flight->toFdrArray();
                        $lfDisplay = ($r['load_factor'] !== 'N/A') ? $r['load_factor'] . '%' : 'N/A';
                        fputcsv($handle, [
                            $idx,
                            $r['air_line'],
                            $r['flight_no'],
                            $r['paired_no'],
                            $r['flight_suffix'] ?? '',
                            $r['suffix_label'] ?? '',
                            $r['sibt'],
                            $r['sobt'],
                            $r['aibt'],
                            $r['aobt'],
                            $r['delay_minutes'] ?? 0,
                            $r['leg'],
                            $r['leg_subtype'] ?? '',
                            $r['direction'],
                            $r['city_1'],
                            $r['city_2'],
                            $r['route'],
                            $r['traffic'],
                            $r['mtow'],
                            $r['reg_no'],
                            $r['cap'],
                            $r['load'],
                            $lfDisplay,
                            $r['adult'],
                            $r['child'],
                            $r['infant'],
                            $r['umroh'] ?? 0,
                            $r['transit'],
                            $r['transfer'],
                            $r['divert'] ?? 0,
                            $r['miss'] ?? 0,
                            $r['crw'] ?? 0,
                            $r['ex_crw'] ?? 0,
                            $r['operating_crew'] ?? (($r['crw'] ?? 0) + ($r['ex_crw'] ?? 0)),
                            $r['cargo_kg'],
                            $r['baggage_kg'],
                            $r['pos_kg'],
                            $r['stand'],
                            $r['runway'],
                            $r['final'] ?? 'YES',
                            !empty($r['is_hold_or_cancelled']) ? 'HOLD_OR_CANCELLED' : (!empty($r['is_irregular']) ? 'IRREGULAR' : 'NORMAL'),
                        ]);
                    }
                } else {
                    $rawRecords = $upload->getOffloadedRecords();
                    $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);
                    foreach ($filterResult['records'] as $r) {
                        $idx++;
                        $lfDisplay = ($r['load_factor'] !== 'N/A') ? $r['load_factor'] . '%' : 'N/A';
                        fputcsv($handle, [
                            $idx,
                            $r['air_line'],
                            $r['flight_no'],
                            $r['paired_no'],
                            $r['flight_suffix'] ?? '',
                            $r['suffix_label'] ?? '',
                            $r['sibt'],
                            $r['sobt'],
                            $r['aibt'],
                            $r['aobt'],
                            $r['delay_minutes'] ?? 0,
                            $r['leg'],
                            $r['leg_subtype'] ?? '',
                            $r['direction'],
                            $r['city_1'],
                            $r['city_2'],
                            $r['route'],
                            $r['traffic'],
                            $r['mtow'],
                            $r['reg_no'],
                            $r['cap'],
                            $r['load'],
                            $lfDisplay,
                            $r['adult'],
                            $r['child'],
                            $r['infant'],
                            $r['umroh'] ?? 0,
                            $r['transit'],
                            $r['transfer'],
                            $r['divert'] ?? 0,
                            $r['miss'] ?? 0,
                            $r['crw'] ?? 0,
                            $r['ex_crw'] ?? 0,
                            $r['operating_crew'] ?? (($r['crw'] ?? 0) + ($r['ex_crw'] ?? 0)),
                            $r['cargo_kg'],
                            $r['baggage_kg'],
                            $r['pos_kg'],
                            $r['stand'],
                            $r['runway'],
                            $r['final'] ?? 'YES',
                            !empty($r['is_hold_or_cancelled']) ? 'HOLD_OR_CANCELLED' : (!empty($r['is_irregular']) ? 'IRREGULAR' : 'NORMAL'),
                        ]);
                    }
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Helper to build active chips for dashboard filter bar.
     */
    protected function buildActiveChips(array $filters, array $scope, bool $isSingleAirport): array
    {
        $chips = [];
        if ($scope['date_scope'] === 'ALL_PERIOD') {
            $chips[] = ['key' => 'date_scope', 'label' => 'Period: FULL RANGE', 'text' => 'Period: FULL RANGE', 'value' => 'ALL_PERIOD'];
        } elseif ($scope['date_scope'] === 'DAY' && !empty($scope['analysis_date'])) {
            $displayDate = date('d-m-Y', strtotime($scope['analysis_date']));
            $chips[] = ['key' => 'analysis_date', 'label' => "Date: {$displayDate}", 'text' => "Date: {$displayDate}", 'value' => $scope['analysis_date']];
        }

        if (!empty($filters['airport']) && $filters['airport'] !== 'ALL' && !$isSingleAirport) {
            $chips[] = ['key' => 'airport', 'label' => "Airport: {$filters['airport']}", 'text' => "Airport: {$filters['airport']}", 'value' => $filters['airport']];
        }
        if (!empty($filters['leg']) && $filters['leg'] !== 'ALL') {
            $chips[] = ['key' => 'leg', 'label' => "Leg: {$filters['leg']}", 'text' => "Leg: {$filters['leg']}", 'value' => $filters['leg']];
        }
        if (!empty($filters['operator']) && $filters['operator'] !== 'ALL' && strcasecmp($filters['operator'], 'ALL AIRLINE') !== 0) {
            $chips[] = ['key' => 'operator', 'label' => "Operator: {$filters['operator']}", 'text' => "Operator: {$filters['operator']}", 'value' => $filters['operator']];
        }
        if (!empty($filters['traffic']) && $filters['traffic'] !== 'ALL') {
            $chips[] = ['key' => 'traffic', 'label' => "Traffic: {$filters['traffic']}", 'text' => "Traffic: {$filters['traffic']}", 'value' => $filters['traffic']];
        }
        if (!empty($filters['realization']) && $filters['realization'] !== 'ALL') {
            $chips[] = ['key' => 'realization', 'label' => "Realized: {$filters['realization']}", 'text' => "Realized: {$filters['realization']}", 'value' => $filters['realization']];
        }
        if (!empty($filters['search'])) {
            $chips[] = ['key' => 'search', 'label' => "Query: {$filters['search']}", 'text' => "Query: {$filters['search']}", 'value' => $filters['search']];
        }
        return $chips;
    }

    /**
     * Export PDF with native rendering of the 3 mentor hourly charts.
     */
    public function exportPdf(Upload $upload, Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        if ($upload->report_type !== 'fdr' || empty($upload->report_data)) {
            abort(404, "Report data not ready for export.");
        }

        $meta = $upload->report_data['meta'] ?? [];
        $rawRecords = $upload->report_data['records'] ?? ($upload->getOffloadedRecords() ?? []);

        $availableDates = $this->extractAvailableDates($rawRecords, $meta);
        $sourceSummary = $this->buildSourceSummary($rawRecords, $availableDates, $meta);
        $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

        $filters = [
            'date_scope'     => $scope['date_scope'],
            'analysis_level' => $scope['analysis_level'],
            'analysis_date'  => $scope['analysis_date'],
            'analysis_month' => $scope['analysis_month'],
            'analysis_year'  => $scope['analysis_year'],
            'airport'        => strtoupper(trim($request->query('airport', $meta['airport'] ?? 'ALL'))),
            'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'       => trim($request->query('operator', 'ALL')),
            'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'      => strtoupper(trim($request->query('data_type', 'ALL'))),
            'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'         => strtoupper(trim($request->query('suffix', ''))),
            'start_date'     => trim($request->query('start_date', '')),
            'end_date'       => trim($request->query('end_date', '')),
            'report_mode'    => (int)$request->query('report_mode', 1),
            'search'         => trim($request->query('search', '')),
        ];

        $hasDb = $this->fdrDbService->hasDatabaseRecords($upload);
        if ($hasDb) {
            $records = $this->fdrDbService->buildFdrFilteredQuery($upload->id, $filters)
                ->orderBy('id')
                ->get()
                ->map->toFdrArray()
                ->toArray();
            return $this->pdfExport->download($records, $meta, $filters);
        }

        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);

        return $this->pdfExport->download($filterResult['records'], $meta, $filters);
    }

    /**
     * Resolve analysis level and date/scope based on request and source dataset constraints.
     */
    protected function resolveAnalysisScope(Request $request, array $sourceSummary, array $availableDates): array
    {
        $sourceType = $sourceSummary['source_type'] ?? 'DAILY';
        $startDate  = $sourceSummary['period_start'] ?? ($sourceSummary['start_date'] ?? date('Y-m-d'));
        $endDate    = $sourceSummary['period_end'] ?? ($sourceSummary['end_date'] ?? date('Y-m-d'));

        $reqDateScope = strtoupper(trim($request->query('date_scope', '')));
        $reqAnalysisDate = trim($request->query('analysis_date', ''));

        if ($reqDateScope === 'ALL_PERIOD' || $reqDateScope === 'ALL' || $reqDateScope === 'FULL_RANGE') {
            $dateScope     = 'ALL_PERIOD';
            $analysisLevel = 'FULL';
            $analysisDate  = null;
        } elseif ($sourceType === 'DAILY') {
            $dateScope     = 'DAY';
            $analysisLevel = 'DAILY';
            $analysisDate  = $startDate;
        } else {
            // Multi-day / Multi-month source
            $isExplicitDay = ($reqDateScope === 'DAY');
            $hasDateParam = (!empty($reqAnalysisDate) && !in_array(strtoupper($reqAnalysisDate), ['ALL', 'ALL_PERIOD', 'FULL', 'FULL_RANGE'], true));

            if ($isExplicitDay || $hasDateParam) {
                $stdReqDate = FlightDailyReportFilter::standardizeDate($reqAnalysisDate);
                $isValidDate = (!empty($stdReqDate) && $stdReqDate !== 'N/A' && in_array($stdReqDate, $availableDates, true));

                if ($isValidDate) {
                    $dateScope     = 'DAY';
                    $analysisLevel = 'DAILY';
                    $analysisDate  = $stdReqDate;
                } elseif ($isExplicitDay && !empty($availableDates)) {
                    $dateScope     = 'DAY';
                    $analysisLevel = 'DAILY';
                    $analysisDate  = reset($availableDates);
                } else {
                    $dateScope     = 'ALL_PERIOD';
                    $analysisLevel = 'FULL';
                    $analysisDate  = null;
                }
            } else {
                // ALL_PERIOD / FULL RANGE
                $dateScope     = 'ALL_PERIOD';
                $analysisLevel = 'FULL';
                $analysisDate  = null;
            }
        }

        $reqMonth = $request->query('analysis_month');
        $analysisMonth = !empty($reqMonth) ? trim($reqMonth) : ($dateScope === 'ALL_PERIOD' ? null : ($analysisDate ? substr($analysisDate, 0, 7) : substr($startDate, 0, 7)));

        $reqYear = $request->query('analysis_year');
        $analysisYear = !empty($reqYear) ? trim($reqYear) : ($dateScope === 'ALL_PERIOD' ? null : ($analysisDate ? substr($analysisDate, 0, 4) : substr($startDate, 0, 4)));

        return [
            'date_scope'     => $dateScope,
            'analysis_level' => $analysisLevel,
            'analysis_date'  => $analysisDate,
            'analysis_month' => $analysisMonth,
            'analysis_year'  => $analysisYear,
            'source_type'    => $sourceType,
        ];
    }

    /**
     * Extract distinct, sorted available dates from FDR records.
     */
    protected function extractAvailableDates(array $rawRecords, array $meta = []): array
    {
        $dates = [];
        foreach ($rawRecords as $r) {
            $d = $r['operational_date'] ?? ($r['flight_date'] ?? null);
            if ($d && $d !== 'N/A') {
                $std = FlightDailyReportFilter::standardizeDate($d);
                if ($std !== 'N/A') {
                    $dates[$std] = true;
                }
            }
        }
        $sorted = array_keys($dates);
        sort($sorted);
        return $sorted;
    }

    /**
     * Build source dataset summary.
     */
    protected function buildSourceSummary(array $rawRecords, array $availableDates, array $meta = []): array
    {
        $startDate = !empty($meta['period_start']) ? $meta['period_start'] : (!empty($availableDates) ? reset($availableDates) : date('Y-m-01'));
        $endDate = !empty($meta['period_end']) ? $meta['period_end'] : (!empty($availableDates) ? end($availableDates) : date('Y-m-t'));
        $daysCount = count($availableDates);

        $sourceType = $meta['source_type'] ?? FlightDailyReportParser::detectGranularity($startDate, $endDate);

        $movementRecords = array_filter($rawRecords, fn($r) => ($r['row_type'] ?? 'MOVEMENT') !== 'SUMMARY' && stripos($r['air_line'] ?? '', 'PAX ALL') === false);
        $totalFlights = count($movementRecords);

        return [
            'total_flights'            => $totalFlights,
            'days_count'               => $daysCount,
            'start_date'               => $startDate,
            'end_date'                 => $endDate,
            'start_label'              => date('d-m-Y', strtotime($startDate)),
            'end_label'                => date('d-m-Y', strtotime($endDate)),
            'period_label'             => date('d-m-Y', strtotime($startDate)) . ' → ' . date('d-m-Y', strtotime($endDate)),
            'days_available'           => "{$daysCount} DAYS AVAILABLE",
            'source_type'              => $sourceType,
            'is_daily'                 => ($sourceType === 'DAILY'),
            'is_monthly'               => ($sourceType === 'MONTHLY'),
            'is_yearly'                => ($sourceType === 'YEARLY'),
            'is_custom'                => ($sourceType === 'CUSTOM RANGE'),
            'source_passenger_summary' => $meta['source_passenger_summary'] ?? null,
            'pax_summary'              => $meta['source_passenger_summary'] ?? null,
        ];
    }

    /**
     * Resolve path to OASYS FDR reference template file.
     */
    protected function resolveReferenceTemplatePath(): ?string
    {
        $p1 = resource_path('templates/fdr/OASYS-FDR-TEMPLATE.xls');
        if (file_exists($p1)) return $p1;

        $p2 = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        if (file_exists($p2)) return $p2;

        return null;
    }
}
