<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Services\PdfParser;
use App\Services\FlightScheduleValidator;
use App\Services\TimelineEngine;
use App\Services\Dau\TemplateValidator;
use App\Services\Dau\ReportTemplateRegistry;
use App\Services\Dau\Parsers\BaseDauParser;
use App\Services\Dau\DauComparisonService;
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
                if ($upload->report_type === 'fdr') {
                    return redirect()->route('fdr.dashboard', $upload->id);
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

        // File size check: if a non-probe file exceeds 50 MB, prompt to use chunked pipeline
        $fileSize = $file->getSize();
        if (!$isProbe && $fileSize > 50 * 1024 * 1024) {
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
     * Upload and parse a single DAU-02 file for the historical comparison pipeline.
     */
    public function uploadCompareFile(Request $request)
    {
        $file = $request->file('file') ?? $request->file('dau_file');
        if (!$file) {
            return response()->json([
                'success'        => false,
                'category'       => 'CORRUPTED_FILE',
                'category_title' => 'NO FILE RECEIVED',
                'error'          => 'No file provided for comparison upload.',
                'errors'         => ['No file provided for comparison upload.'],
            ], 422);
        }

        $validator = new TemplateValidator();
        $validationResult = $validator->validate('DAU2', $file);

        if (!$validationResult['valid']) {
            return response()->json([
                'success'        => false,
                'category'       => $validationResult['category'] ?? 'INVALID_TEMPLATE',
                'category_title' => $validationResult['category_title'] ?? 'INVALID TEMPLATE',
                'error'          => $validationResult['error'] ?? implode('; ', $validationResult['errors']),
                'errors'         => $validationResult['errors'] ?? [],
                'validation'     => $validationResult,
            ], 422);
        }

        $filename = $file->getClientOriginalName();
        $storedPath = $file->store('uploads', 'local');

        $airportCode = $validationResult['meta']['airport_code'] ?? 'CGK';
        $airport = \App\Models\Airport::findByIata($airportCode) ?? \App\Models\Airport::findByIata('CGK');

        $upload = Upload::create([
            'original_filename' => $filename,
            'stored_path'       => $storedPath,
            'report_type'       => 'DAU2',
            'status'            => 'pending',
            'season'            => 'summer',
            'airport_id'        => $airport?->id,
        ]);

        $this->executeDauProcessing($upload);

        $startDate = BaseDauParser::normalizeOperationalDate($upload->report_data['meta']['start_date'] ?? null);
        $endDate   = BaseDauParser::normalizeOperationalDate($upload->report_data['meta']['end_date'] ?? null);
        $dataDays  = DauComparisonService::calculatePeriodDurationDays($startDate, $endDate);

        $displayRange = ($startDate && $endDate)
            ? (BaseDauParser::formatDisplayDate($startDate) . ' - ' . BaseDauParser::formatDisplayDate($endDate))
            : ($upload->report_data['meta']['date_range'] ?? 'Unknown');

        return response()->json([
            'success'            => true,
            'upload_id'          => $upload->id,
            'filename'           => $filename,
            'airport'            => $upload->report_data['meta']['airport'] ?? $airportCode,
            'airport_code'       => $airportCode,
            'airport_name'       => $upload->report_data['meta']['airport_name'] ?? 'Soekarno Hatta',
            'start_date'         => $startDate,
            'end_date'           => $endDate,
            'date_range'         => $upload->report_data['meta']['date_range'] ?? null,
            'display_date_range' => $displayRange,
            'data_days'          => $dataDays,
            'records_count'      => $upload->valid_rows,
            'cargo_unit'         => $upload->report_data['cargo_unit'] ?? 'Kg',
            'dau_type'           => 'DAU-02',
            'report_type'        => 'DAU2',
            'status'             => 'valid',
        ]);
    }

    /**
     * Resumable chunk status inquiry.
     * Checks /tmp-based assembled file size to determine which chunks are present.
     */
    public function chunkStatus(Request $request)
    {
        try {
            $uploadToken = $request->query('upload_token');
            $totalChunks = (int)$request->query('total_chunks', 1);
            $chunkSize   = (int)$request->query('chunk_size', 3 * 1024 * 1024);

            if (!$uploadToken || !preg_match('/^[a-zA-Z0-9_\-]+$/', $uploadToken)) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'      => 'INVALID_UPLOAD_TOKEN',
                        'message'   => 'Invalid or missing upload token.',
                        'retryable' => false,
                    ],
                ], 422);
            }

            // Assembled file lives in /tmp — always writable on Vercel
            $tmpAssembled = sys_get_temp_dir() . '/fdr_asm_' . $uploadToken . '.bin';
            $uploaded = [];
            $missing  = [];

            $currentSize = file_exists($tmpAssembled) ? filesize($tmpAssembled) : 0;
            // Derive which chunks are already written based on assembled size
            $chunksWritten = ($chunkSize > 0) ? (int) ceil($currentSize / $chunkSize) : 0;

            for ($i = 0; $i < $totalChunks; $i++) {
                if ($i < $chunksWritten) {
                    $uploaded[] = $i;
                } else {
                    $missing[] = $i;
                }
            }

            return response()->json([
                'success'         => true,
                'upload_token'    => $uploadToken,
                'total_chunks'    => $totalChunks,
                'uploaded_chunks' => $uploaded,
                'missing_chunks'  => $missing,
                'completed'       => empty($missing),
                'assembled_bytes' => $currentSize,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'      => 'STATUS_ERROR',
                    'message'   => $e->getMessage(),
                    'retryable' => true,
                ],
            ], 500);
        }
    }

    /**
     * Chunked upload endpoint — safe for Vercel's hard 4.5 MB request body limit.
     *
     * Strategy:
     * - FDR_CHUNK_SIZE constant = 3 MiB (safely under 4.5 MB with multipart overhead)
     * - Each chunk is APPENDED to a single assembled file in /tmp (writable on Vercel)
     * - Per-request work is O(1): only fopen('ab') + fwrite + fclose, no re-reads
     * - Idempotency: check expected offset before writing (retry-safe)
     * - Validation runs ONLY on the final (last) chunk, after full file is assembled
     * - Entire method is wrapped in try/catch → always returns JSON, never HTML
     */
    public function uploadChunk(Request $request)
    {
        // ── Chunk size constant (must match JS FDR_CHUNK_SIZE = 3 MiB) ─────────
        // Maximum chunk size: 3 MiB (safely under Vercel's 4.5 MB payload gate)
        // With multipart boundary + headers, total request stays well under 4 MB.
        // DO NOT raise above 4.5 MB under any circumstance.
        if (!defined('FDR_CHUNK_SIZE_BYTES')) {
            define('FDR_CHUNK_SIZE_BYTES', 3 * 1024 * 1024); // 3 MiB
        }

        try {
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
                    'error'          => [
                        'code'      => 'INVALID_UPLOAD_TOKEN',
                        'message'   => 'Invalid or missing upload token.',
                        'retryable' => false,
                    ],
                ], 422);
            }

            $chunkFile = $request->file('chunk') ?? $request->file('file');
            if (!$chunkFile) {
                return response()->json([
                    'success'        => false,
                    'category'       => 'UPLOAD_FAILED',
                    'category_title' => 'UPLOAD FAILED',
                    'error'          => [
                        'code'      => 'MISSING_CHUNK_PAYLOAD',
                        'message'   => 'No chunk payload received.',
                        'retryable' => true,
                    ],
                ], 422);
            }

            // For FDR we bypass ReportTemplateRegistry since it may not include 'fdr'
            $isFdr = strcasecmp($reportType, 'fdr') === 0;
            if (!$isFdr) {
                $conf = ReportTemplateRegistry::find($reportType);
                if (!$conf) {
                    return response()->json([
                        'success'        => false,
                        'category'       => 'UNSUPPORTED_DAU_TYPE',
                        'category_title' => 'UNSUPPORTED REPORT TYPE',
                        'error'          => [
                            'code'      => 'UNSUPPORTED_REPORT_TYPE',
                            'message'   => "Unsupported report type: {$reportType}",
                            'retryable' => false,
                        ],
                    ], 422);
                }
            }

            // ── /tmp assembled file path (ALWAYS writable on Vercel) ──────────
            // Each upload session gets a unique file; concurrent sessions don't collide.
            $tmpDir      = rtrim(sys_get_temp_dir(), '/\\');
            $tmpAssembled = "{$tmpDir}/fdr_asm_{$uploadToken}.bin";

            // ── IDEMPOTENCY: Detect if this chunk was already written ─────────
            // We determine expected byte offset for this chunk.
            $chunkData    = file_get_contents($chunkFile->getRealPath());
            $chunkBytes   = strlen($chunkData);
            $expectedOffset = $chunkIndex * FDR_CHUNK_SIZE_BYTES;
            $currentSize    = file_exists($tmpAssembled) ? filesize($tmpAssembled) : 0;

            if ($currentSize > $expectedOffset) {
                // Chunk already written (client retry). Skip to avoid corruption.
                Log::info("FDR chunk {$chunkIndex} already present (assembled={$currentSize} > offset={$expectedOffset}), skipping write");
            } else {
                // Append this chunk to the assembled file
                $fh = @fopen($tmpAssembled, 'ab');
                if (!$fh) {
                    return response()->json([
                        'success'        => false,
                        'category'       => 'PROCESSING_FAILED',
                        'category_title' => 'STORAGE ERROR',
                        'error'          => [
                            'code'      => 'TMP_WRITE_FAILED',
                            'message'   => 'Failed to write chunk to /tmp. Disk may be full.',
                            'retryable' => true,
                        ],
                    ], 500);
                }
                fwrite($fh, $chunkData);
                fclose($fh);
            }
            unset($chunkData); // free memory immediately

            // ── NOT LAST CHUNK: Return immediately, O(1) work done ────────────
            if ($chunkIndex < $totalChunks - 1) {
                return response()->json([
                    'success'      => true,
                    'completed'    => false,
                    'chunk_index'  => $chunkIndex,
                    'total_chunks' => $totalChunks,
                    'assembled_bytes' => file_exists($tmpAssembled) ? filesize($tmpAssembled) : 0,
                    'message'      => "Chunk {$chunkIndex} of {$totalChunks} received.",
                ]);
            }

            // ── LAST CHUNK: Full file assembled — now validate & create records ──
            $assembledSize = file_exists($tmpAssembled) ? filesize($tmpAssembled) : 0;
            if ($assembledSize < 1024) {
                @unlink($tmpAssembled);
                return response()->json([
                    'success'        => false,
                    'category'       => 'PROCESSING_FAILED',
                    'category_title' => 'ASSEMBLY INCOMPLETE',
                    'error'          => [
                        'code'      => 'ASSEMBLE_INCOMPLETE',
                        'message'   => "Assembled file is too small ({$assembledSize} bytes). Some chunks may be missing.",
                        'retryable' => true,
                    ],
                ], 422);
            }

            // ── Copy assembled file to Storage disk for persistence ───────────
            // Storage::disk('local') root = storage_path('app/private').
            // On Vercel this path IS writable during the request (Lambda /var/task is overlayfs
            // with a writable layer for the request lifetime). If write fails we fall back
            // to keeping the assembled file in /tmp and referencing it directly.
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'xls';
            $assembledRelativePath = "uploads/{$uploadToken}.{$ext}";

            // Try Storage disk first (preferred — survives between requests if using attached storage)
            $storedViaStorage = false;
            try {
                Storage::disk('local')->put(
                    $assembledRelativePath,
                    file_get_contents($tmpAssembled)
                );
                $assembledAbsolutePath = Storage::disk('local')->path($assembledRelativePath);
                $storedViaStorage = true;
            } catch (\Throwable $storageEx) {
                // Fallback: use /tmp path directly (valid within this request)
                $assembledAbsolutePath = $tmpAssembled;
                $assembledRelativePath = $tmpAssembled; // store absolute path as relative
                Log::warning("FDR chunk assembly: Storage::disk write failed, using /tmp directly: " . $storageEx->getMessage());
            }

            // Validate assembled file structure (OASYS HTML FDR check)
            if ($isFdr) {
                // FDR-specific validation: check for HTML table signature
                $head = @file_get_contents($assembledAbsolutePath, false, null, 0, 65536) ?: '';
                $lowerHead = strtolower($head);
                $isFdrHtml = str_contains($lowerHead, '<table') || str_contains($lowerHead, '<html')
                    || str_contains($lowerHead, '<tr') || str_contains($lowerHead, 'aeronautical')
                    || str_contains($lowerHead, 'oasys') || str_contains($lowerHead, 'flight daily');

                if (!$isFdrHtml) {
                    @unlink($tmpAssembled);
                    if ($storedViaStorage) {
                        Storage::disk('local')->delete($assembledRelativePath);
                    }
                    return response()->json([
                        'success'        => false,
                        'category'       => 'INVALID_TEMPLATE',
                        'category_title' => 'INVALID FDR FILE',
                        'error'          => [
                            'code'      => 'INVALID_FDR_FORMAT',
                            'message'   => 'Assembled file does not appear to be an OASYS FDR HTML workbook.',
                            'retryable' => false,
                        ],
                        'errors' => ['Not a recognized OASYS FDR HTML table format.'],
                    ], 422);
                }

                // ── Create Upload + FdrProcessingJob records ──────────────────
                $airportCode = 'CGK';
                // Try to extract BRANCH_CODE from head
                if (preg_match('/name=[\'"]BRANCH_CODE[\'"][^>]*value=[\'"]([A-Z]{3,4})[\'"]/i', $head, $bcm)) {
                    $airportCode = strtoupper($bcm[1]);
                } elseif (preg_match('/value=[\'"]([A-Z]{3,4})[\'"][^>]*name=[\'"]BRANCH_CODE[\'"]/i', $head, $bcm2)) {
                    $airportCode = strtoupper($bcm2[1]);
                }
                $airport = \App\Models\Airport::findByIata($airportCode) ?? \App\Models\Airport::findByIata('CGK');
                $fileHash = @hash_file('sha256', $assembledAbsolutePath) ?: null;

                $upload = Upload::create([
                    'original_filename'  => $filename,
                    'stored_path'        => $assembledRelativePath,
                    'status'             => 'processing',
                    'report_type'        => 'fdr',
                    'total_rows'         => 0,
                    'valid_rows'         => 0,
                    'invalid_rows'       => 0,
                    'duplicate_rows'     => 0,
                    'parsing_confidence' => 1.0,
                    'validation_summary' => ['valid' => true, 'meta' => ['airport_code' => $airportCode]],
                    'report_data'        => ['meta' => ['airport' => $airportCode, 'airport_code' => $airportCode]],
                    'airport_id'         => $airport?->id,
                ]);

                $job = \App\Models\FdrProcessingJob::create([
                    'upload_id'      => $upload->id,
                    'upload_token'   => $uploadToken,
                    'filename'       => $filename,
                    'stored_path'    => $assembledRelativePath,
                    'file_size'      => $assembledSize,
                    'file_hash'      => $fileHash,
                    'report_type'    => 'fdr',
                    'status'         => 'QUEUED',
                    'stage_label'    => 'Queued for processing',
                    'progress'       => 0,
                    'processed_rows' => 0,
                    'total_rows'     => 0,
                    'meta'           => ['airport' => $airportCode, 'tmp_path' => $tmpAssembled],
                    'result_url'     => route('fdr.dashboard', ['upload' => $upload->id, 'date_scope' => 'ALL_PERIOD']),
                ]);

                // Clean up /tmp assembled file ONLY if it was successfully copied to storage
                if ($storedViaStorage) {
                    @unlink($tmpAssembled);
                }
                // else: leave /tmp file for processJob to use directly

                session(['fdr_active_upload_id' => $upload->id]);
                session(['active_upload_id' => $upload->id]);

                return response()->json([
                    'success'      => true,
                    'completed'    => true,
                    'is_async_job' => true,
                    'job_id'       => $job->id,
                    'upload_id'    => $upload->id,
                    'report_type'  => 'fdr',
                    'status'       => 'QUEUED',
                    'poll_url'     => route('fdr.jobs.status', $job->id),
                    'process_url'  => route('fdr.jobs.process', $job->id),
                    'redirect_url' => route('fdr.dashboard', ['upload' => $upload->id, 'date_scope' => 'ALL_PERIOD']),
                    'message'      => "Flight Daily Report uploaded successfully ({$assembledSize} bytes). Processing job queued.",
                ]);
            }

            // ── NON-FDR: Validate assembled template ──────────────────────────
            $conf = ReportTemplateRegistry::find($reportType);
            if (!$conf) {
                @unlink($tmpAssembled);
                return response()->json([
                    'success'        => false,
                    'category'       => 'UNSUPPORTED_DAU_TYPE',
                    'category_title' => 'UNSUPPORTED REPORT TYPE',
                    'error'          => [
                        'code'      => 'UNSUPPORTED_REPORT_TYPE',
                        'message'   => "Unsupported report type: {$reportType}",
                        'retryable' => false,
                    ],
                ], 422);
            }

            $validator = new TemplateValidator();
            $validationResult = $validator->validate($reportType, $assembledAbsolutePath, false, $filename);

            if (!$validationResult['valid']) {
                @unlink($tmpAssembled);
                if ($storedViaStorage) {
                    Storage::disk('local')->delete($assembledRelativePath);
                }
                return response()->json([
                    'success'        => false,
                    'category'       => $validationResult['category'] ?? 'INVALID_TEMPLATE',
                    'category_title' => $validationResult['category_title'] ?? 'INVALID TEMPLATE',
                    'error'          => [
                        'code'      => 'INVALID_TEMPLATE',
                        'message'   => $validationResult['error'] ?? implode('; ', $validationResult['errors']),
                        'retryable' => false,
                    ],
                    'errors'         => $validationResult['errors'] ?? [],
                    'validation'     => $validationResult,
                ], 422);
            }

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

            $this->executeDauProcessing($upload);
            session(['active_upload_id' => $upload->id]);

            @unlink($tmpAssembled);

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

        } catch (\Throwable $e) {
            Log::error('uploadChunk fatal error: ' . $e->getMessage(), [
                'exception' => $e,
                'report_type' => $request->input('report_type'),
                'chunk_index' => $request->input('chunk_index'),
                'upload_token' => $request->input('upload_token'),
            ]);
            return response()->json([
                'success'        => false,
                'category'       => 'SERVER_ERROR',
                'category_title' => 'SERVER ERROR',
                'error'          => [
                    'code'      => 'INTERNAL_SERVER_ERROR',
                    'message'   => 'An unexpected server error occurred: ' . $e->getMessage(),
                    'retryable' => true,
                ],
            ], 500);
        }
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
        // PIPELINE 2: FLIGHT DAILY REPORT (FDR) OPERATIONAL MODULE
        // ═════════════════════════════════════════════════════════════════════
        if (strcasecmp($reportType, 'fdr') === 0) {
            $file = $request->file('fdr_file') ?? $request->file('file') ?? $request->file('dau_file') ?? $request->file('uploaded_file');
            if (!$file) {
                return response()->json([
                    'success'        => false,
                    'category'       => 'CORRUPTED_FILE',
                    'category_title' => 'NO FILE RECEIVED',
                    'error'          => 'No file provided for Flight Daily Report upload.',
                ], 422);
            }

            $origName = $file->getClientOriginalName();
            $clientExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($clientExt, ['xls', 'xlsx', 'csv'])) {
                $displayExt = $clientExt ? ".{$clientExt}" : '(unknown)';
                $err = "Unsupported file extension {$displayExt}. Please upload an OASYS FDR (.xls, .xlsx, or .csv) file.";
                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success'        => false,
                        'category'       => 'INVALID_EXTENSION',
                        'category_title' => 'UNSUPPORTED FILE TYPE',
                        'error'          => $err,
                        'errors'         => [$err],
                    ], 422);
                }
                return redirect()->route('home')->withErrors(['fdr' => $err]);
            }

            $storedPath = $file->store('uploads/fdr', 'local');
            $fullPath = Storage::disk('local')->path($storedPath);

            $fdrValidator = new \App\Services\FlightDailyReport\FlightDailyReportValidator();
            $validation = $fdrValidator->validate($fullPath, $origName);

            if (!$validation['valid']) {
                Storage::disk('local')->delete($storedPath);
                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success'        => false,
                        'category'       => $validation['category'] ?? 'INVALID_TEMPLATE',
                        'category_title' => $validation['category_title'] ?? 'INVALID FDR TEMPLATE',
                        'error'          => implode('; ', $validation['errors'] ?? []),
                        'errors'         => $validation['errors'] ?? [],
                    ], 422);
                }
                return redirect()->route('home')->withErrors([
                    'fdr' => implode('; ', $validation['errors'] ?? [])
                ]);
            }

            $fdrParser = new \App\Services\FlightDailyReport\FlightDailyReportParser();
            $parsed = $fdrParser->parse($fullPath);

            $airportCode = $parsed['meta']['airport'] ?? 'CGK';
            $airport = \App\Models\Airport::findByIata($airportCode) ?? \App\Models\Airport::findByIata('CGK');

            $upload = Upload::create([
                'original_filename'  => $origName,
                'stored_path'        => $storedPath,
                'status'             => 'completed',
                'report_type'        => 'fdr',
                'total_rows'         => count($parsed['records']),
                'valid_rows'         => count($parsed['records']),
                'invalid_rows'       => 0,
                'duplicate_rows'     => 0,
                'parsing_confidence' => 1.0,
                'validation_summary' => $validation,
                'report_data'        => $parsed,
                'airport_id'         => $airport?->id,
            ]);

            session(['fdr_active_upload_id' => $upload->id]);
            session(['active_upload_id' => $upload->id]);

            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'      => true,
                    'upload_id'    => $upload->id,
                    'report_type'  => 'fdr',
                    'status'       => 'completed',
                    'total_rows'   => $upload->total_rows,
                    'valid_rows'   => $upload->valid_rows,
                    'redirect_url' => route('fdr.dashboard', $upload->id),
                    'message'      => "Flight Daily Report uploaded and validated successfully ({$upload->valid_rows} movements).",
                ]);
            }

            return redirect()->route('fdr.dashboard', $upload->id);
        }

        // ═════════════════════════════════════════════════════════════════════
        // PIPELINE 3: DAU TYPE-SPECIFIC REPORT INGESTION
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

        if ($upload->report_type === 'fdr') {
            return redirect()->route('fdr.dashboard', $upload->id);
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
