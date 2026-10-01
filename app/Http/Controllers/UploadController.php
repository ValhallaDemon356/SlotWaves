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
use App\Models\FdrUploadSession;
use App\Models\FdrProcessingJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
     * Create or retrieve a persistent upload session for large FDR files.
     * Guaranteed persistent state across serverless Lambda invocations.
     */
    public function createUploadSession(Request $request)
    {
        try {
            $tokenInput  = $request->input('upload_token');
            $filename    = $request->input('original_filename') ?: $request->input('filename', 'dataset.xls');
            $fileSize    = (int) $request->input('file_size', 0);
            $chunkSize   = (int) $request->input('chunk_size', 3 * 1024 * 1024);
            $totalChunks = (int) $request->input('total_chunks', ($chunkSize > 0 && $fileSize > 0) ? (int) ceil($fileSize / $chunkSize) : 1);
            $fileHash    = $request->input('file_hash');
            $mimeType    = $request->input('mime_type', 'application/vnd.ms-excel');

            // 1. Direct lookup by active upload token if provided
            if (!empty($tokenInput)) {
                $session = FdrUploadSession::where('upload_token', $tokenInput)->first();
                if ($session) {
                    if ($session->status === FdrUploadSession::STATUS_PAUSED) {
                        $session->status = FdrUploadSession::STATUS_UPLOADING;
                        $session->save();
                    }
                    $uploaded = $session->uploaded_chunks ?? [];
                    $missing  = $session->getMissingChunks();
                    return response()->json([
                        'success'               => true,
                        'is_resume'             => true,
                        'upload_token'          => $session->upload_token,
                        'session_id'            => $session->id,
                        'filename'              => $session->original_filename,
                        'original_filename'     => $session->original_filename,
                        'file_size'             => $session->file_size,
                        'total_chunks'          => $session->total_chunks,
                        'uploaded_chunks'       => $uploaded,
                        'uploaded_chunks_count' => count($uploaded),
                        'missing_chunks'        => $missing,
                        'uploaded_bytes'        => $session->uploaded_bytes,
                        'last_confirmed_chunk'  => $session->last_confirmed_chunk,
                        'status'                => $session->status,
                        'already_complete'      => empty($missing) && $session->total_chunks > 0,
                    ]);
                }
            }

            // 2. Duplicate detection (§11 & §55): If exact same file has already been ingested and ready
            if (!empty($fileHash)) {
                $existingJob = FdrProcessingJob::where('file_hash', $fileHash)
                    ->where('status', 'READY')
                    ->latest()
                    ->first();
                if ($existingJob) {
                    return response()->json([
                        'success'      => true,
                        'is_duplicate' => true,
                        'upload_token' => $existingJob->upload_token,
                        'session_id'   => $existingJob->id,
                        'upload_id'    => $existingJob->upload_id,
                        'status'       => 'READY',
                        'result_url'   => $existingJob->result_url,
                        'message'      => 'Identical file already parsed and ready.',
                    ]);
                }

                // Check for existing unfinished session with same hash
                $existingSession = FdrUploadSession::where('file_hash', $fileHash)
                    ->whereIn('status', [FdrUploadSession::STATUS_CREATED, FdrUploadSession::STATUS_UPLOADING, FdrUploadSession::STATUS_PAUSED])
                    ->latest()
                    ->first();
                if ($existingSession) {
                    if ($existingSession->status === FdrUploadSession::STATUS_PAUSED) {
                        $existingSession->status = FdrUploadSession::STATUS_UPLOADING;
                        $existingSession->save();
                    }
                    $uploaded = $existingSession->uploaded_chunks ?? [];
                    $missing  = $existingSession->getMissingChunks();
                    return response()->json([
                        'success'               => true,
                        'is_resume'             => true,
                        'upload_token'          => $existingSession->upload_token,
                        'session_id'            => $existingSession->id,
                        'filename'              => $existingSession->original_filename,
                        'original_filename'     => $existingSession->original_filename,
                        'file_size'             => $existingSession->file_size,
                        'total_chunks'          => $existingSession->total_chunks,
                        'uploaded_chunks'       => $uploaded,
                        'uploaded_chunks_count' => count($uploaded),
                        'missing_chunks'        => $missing,
                        'uploaded_bytes'        => $existingSession->uploaded_bytes,
                        'last_confirmed_chunk'  => $existingSession->last_confirmed_chunk,
                        'status'                => $existingSession->status,
                        'already_complete'      => empty($missing) && $existingSession->total_chunks > 0,
                    ]);
                }
            }

            $token = $tokenInput ?: ('upl_fdr_' . time() . '_' . \Illuminate\Support\Str::random(8));

            $session = FdrUploadSession::firstOrCreate(
                ['upload_token' => $token],
                [
                    'original_filename'    => $filename,
                    'file_size'            => $fileSize,
                    'mime_type'            => $mimeType,
                    'file_hash'            => $fileHash,
                    'chunk_size'           => $chunkSize,
                    'total_chunks'         => $totalChunks,
                    'uploaded_bytes'       => 0,
                    'uploaded_chunks'      => [],
                    'status'               => FdrUploadSession::STATUS_CREATED,
                    'last_confirmed_chunk' => -1,
                ]
            );

            $uploaded = $session->uploaded_chunks ?? [];
            $missing  = $session->getMissingChunks();

            return response()->json([
                'success'               => true,
                'upload_token'          => $session->upload_token,
                'session_id'            => $session->id,
                'filename'              => $session->original_filename,
                'original_filename'     => $session->original_filename,
                'file_size'             => $session->file_size,
                'total_chunks'          => $session->total_chunks,
                'uploaded_chunks'       => $uploaded,
                'uploaded_chunks_count' => count($uploaded),
                'missing_chunks'        => $missing,
                'uploaded_bytes'        => $session->uploaded_bytes,
                'last_confirmed_chunk'  => $session->last_confirmed_chunk,
                'status'                => $session->status,
                'already_complete'      => empty($missing) && $session->total_chunks > 0,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'SESSION_CREATE_ERROR', 'message' => $e->getMessage(), 'retryable' => true],
            ], 500);
        }
    }

    /**
     * Get upload session state for browser refresh / resume support (§10).
     */
    public function getUploadSession($token)
    {
        try {
            if (!$token || !preg_match('/^[a-zA-Z0-9_\-]+$/', $token)) {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'INVALID_UPLOAD_TOKEN', 'message' => 'Invalid upload token.', 'retryable' => false],
                ], 422);
            }

            $session = FdrUploadSession::where('upload_token', $token)->first();
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'SESSION_NOT_FOUND', 'message' => 'Upload session not found.', 'retryable' => false],
                ], 404);
            }

            $job = FdrProcessingJob::where('upload_token', $token)->first();

            $uploadedChunks = $session->uploaded_chunks ?? [];
            $missingChunks  = $session->getMissingChunks();
            $uploadedCount  = count($uploadedChunks);

            $payload = [
                'upload_token'          => $session->upload_token,
                'session_id'            => $session->id,
                'filename'              => $session->original_filename,
                'original_filename'     => $session->original_filename,
                'file_size'             => $session->file_size,
                'uploaded_bytes'        => $session->uploaded_bytes,
                'uploaded_chunks'       => $uploadedChunks,
                'uploaded_chunks_count' => $uploadedCount,
                'missing_chunks'        => $missingChunks,
                'total_chunks'          => $session->total_chunks,
                'last_confirmed_chunk'  => $session->last_confirmed_chunk,
                'failed_chunk'          => $session->failed_chunk,
                'status'                => $session->status,
                'progress'              => $session->progress,
                'job_id'                => $job?->id,
                'poll_url'              => $job ? route('fdr.jobs.status', $job->id) : null,
                'process_url'           => $job ? route('fdr.jobs.process', $job->id) : null,
                'result_url'            => $job?->result_url,
            ];

            return response()->json(array_merge([
                'success' => true,
                'session' => $payload,
            ], $payload));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'SESSION_FETCH_ERROR', 'message' => $e->getMessage(), 'retryable' => true],
            ], 500);
        }
    }

    /**
     * Pause an active upload session (§16 & §19). Keeps all uploaded chunks.
     */
    public function pauseUploadSession(Request $request, $token)
    {
        try {
            $session = FdrUploadSession::where('upload_token', $token)->first();
            if ($session) {
                $session->status = FdrUploadSession::STATUS_PAUSED;
                if ($request->has('failed_chunk')) {
                    $session->failed_chunk = (int)$request->input('failed_chunk');
                }
                $session->save();
            }
            return response()->json([
                'success'      => true,
                'status'       => FdrUploadSession::STATUS_PAUSED,
                'failed_chunk' => $session?->failed_chunk,
                'message'      => 'Upload paused. Chunks are preserved.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'PAUSE_ERROR', 'message' => $e->getMessage(), 'retryable' => true],
            ], 500);
        }
    }

    /**
     * Cancel an upload session (§19). Purges session and uploaded chunks.
     */
    public function cancelUploadSession($token)
    {
        try {
            DB::table('upload_chunks')->where('upload_token', $token)->delete();
            Storage::disk('local')->deleteDirectory("fdr_chunks/{$token}");
            $tmpAssembled = sys_get_temp_dir() . '/fdr_asm_' . $token . '.bin';
            if (file_exists($tmpAssembled)) {
                @unlink($tmpAssembled);
            }
            $session = FdrUploadSession::where('upload_token', $token)->first();
            if ($session) {
                if ($session->storage_path && Storage::disk('local')->exists($session->storage_path)) {
                    Storage::disk('local')->delete($session->storage_path);
                }
                $session->delete();
            }
            return response()->json([
                'success' => true,
                'message' => 'Upload session and files cancelled and purged.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'CANCEL_ERROR', 'message' => $e->getMessage(), 'retryable' => false],
            ], 500);
        }
    }

    /**
     * Resumable chunk status inquiry.
     * Uses persistent database session as authoritative source of truth (§2 & §8).
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

            // 1. Authoritative check: persistent database session
            $session = FdrUploadSession::where('upload_token', $uploadToken)->first();
            if ($session) {
                $uploaded = $session->uploaded_chunks ?? [];
                $missing  = $session->getMissingChunks();

                return response()->json([
                    'success'              => true,
                    'upload_token'         => $uploadToken,
                    'total_chunks'         => $session->total_chunks ?: $totalChunks,
                    'uploaded_chunks'      => $uploaded,
                    'missing_chunks'       => $missing,
                    'completed'            => empty($missing),
                    'assembled_bytes'      => $session->uploaded_bytes,
                    'uploaded_bytes'       => $session->uploaded_bytes,
                    'last_confirmed_chunk' => $session->last_confirmed_chunk,
                    'status'               => $session->status,
                ]);
            }

            // 2. Check upload_chunks table in database
            $chunksInDb = DB::table('upload_chunks')
                ->where('upload_token', $uploadToken)
                ->pluck('chunk_index')
                ->toArray();

            if (!empty($chunksInDb)) {
                sort($chunksInDb);
                $present = array_flip($chunksInDb);
                $missing = [];
                for ($i = 0; $i < $totalChunks; $i++) {
                    if (!isset($present[$i])) {
                        $missing[] = $i;
                    }
                }

                return response()->json([
                    'success'         => true,
                    'upload_token'    => $uploadToken,
                    'total_chunks'    => $totalChunks,
                    'uploaded_chunks' => $chunksInDb,
                    'missing_chunks'  => $missing,
                    'completed'       => empty($missing),
                    'assembled_bytes' => count($chunksInDb) * $chunkSize,
                ]);
            }

            // 3. Fallback: check /tmp assembled file if any exists
            $tmpAssembled = sys_get_temp_dir() . '/fdr_asm_' . $uploadToken . '.bin';
            $currentSize = file_exists($tmpAssembled) ? filesize($tmpAssembled) : 0;
            $chunksWritten = ($chunkSize > 0) ? (int) ceil($currentSize / $chunkSize) : 0;

            $uploaded = [];
            $missing  = [];
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
                'completed'       => empty($missing) && $chunksWritten > 0,
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
     * Chunked upload endpoint — fully persistent, resilient to Vercel Lambda container swaps.
     *
     * Key Architecture:
     * - FDR_CHUNK_SIZE constant = 3 MiB (safely under 4.5 MB Vercel gateway)
     * - Database upload_chunks table + persistent storage are the source of truth (§2)
     * - Each chunk is recorded idempotently with its exact chunk index (§7)
     * - Out-of-order chunks supported via array index tracking (§9)
     * - Chunk retry NEVER restarts from chunk 0 (§4 & §18)
     * - Final chunk only validates and queues job — NEVER performs heavy parsing synchronously (§21)
     * - Entire method returns JSON, never HTML (§275)
     */
    public function uploadChunk(Request $request)
    {
        if (!defined('FDR_CHUNK_SIZE_BYTES')) {
            define('FDR_CHUNK_SIZE_BYTES', 3 * 1024 * 1024); // 3 MiB
        }

        try {
            $reportType  = $request->input('report_type', 'DAU1');
            $uploadToken = $request->input('upload_token');
            $chunkIndex  = (int) $request->input('chunk_index', 0);
            $totalChunks = (int) $request->input('total_chunks', 1);
            $filename    = $request->input('filename', 'dataset.xls');
            $fileSize    = (int) $request->input('file_size', 0);
            $fileHash    = $request->input('file_hash');

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

            $isFdr = strcasecmp($reportType, 'fdr') === 0;

            // ── 1. Persistent session initialization (FDR) ───────────────────
            $session = null;
            if ($isFdr) {
                $session = FdrUploadSession::firstOrCreate(
                    ['upload_token' => $uploadToken],
                    [
                        'original_filename'    => $filename,
                        'file_size'            => $fileSize,
                        'mime_type'            => $chunkFile->getClientMimeType() ?: 'application/vnd.ms-excel',
                        'file_hash'            => $fileHash,
                        'chunk_size'           => FDR_CHUNK_SIZE_BYTES,
                        'total_chunks'         => $totalChunks,
                        'uploaded_bytes'       => 0,
                        'uploaded_chunks'      => [],
                        'status'               => FdrUploadSession::STATUS_UPLOADING,
                        'last_confirmed_chunk' => -1,
                    ]
                );

                if ($session->total_chunks <= 0 || $session->total_chunks !== $totalChunks) {
                    $session->total_chunks = $totalChunks;
                    $session->save();
                }
            }

            // ── 2. Idempotency: Check if chunk already confirmed ─────────────
            if ($session && $session->hasChunk($chunkIndex)) {
                Log::info("Chunk {$chunkIndex} for session {$uploadToken} already confirmed. Idempotent skip.");
                if (!$session->isFullyUploaded()) {
                    return response()->json([
                        'success'              => true,
                        'completed'            => false,
                        'chunk_index'          => $chunkIndex,
                        'total_chunks'         => $session->total_chunks,
                        'uploaded_chunks'      => $session->uploaded_chunks,
                        'missing_chunks'       => $session->getMissingChunks(),
                        'uploaded_bytes'       => $session->uploaded_bytes,
                        'last_confirmed_chunk' => $session->last_confirmed_chunk,
                        'status'               => $session->status,
                        'message'              => "Chunk {$chunkIndex} already confirmed.",
                    ]);
                }
            }

            // Read chunk data
            $chunkData  = file_get_contents($chunkFile->getRealPath());
            $chunkBytes = strlen($chunkData);

            // ── 3. Store chunk persistently (Database BLOB + Storage Disk Part) ───
            // Persistent database storage (GUARANTEED across all Vercel Lambda invocations):
            DB::table('upload_chunks')->updateOrInsert(
                ['upload_token' => $uploadToken, 'chunk_index' => $chunkIndex],
                [
                    'total_chunks' => $totalChunks,
                    'chunk_size'   => $chunkBytes,
                    'chunk_data'   => $chunkData,
                    'created_at'   => now(),
                ]
            );

            // Also write to storage disk as cached part
            try {
                Storage::disk('local')->put("fdr_chunks/{$uploadToken}/chunk_{$chunkIndex}.part", $chunkData);
            } catch (\Throwable $e) {
                // Non-fatal, DB is primary persistent source
            }

            // Maintain /tmp assembled file for fast single-container execution
            $tmpDir = rtrim(sys_get_temp_dir(), '/\\');
            $tmpAssembled = "{$tmpDir}/fdr_asm_{$uploadToken}.bin";
            $expectedOffset = $chunkIndex * FDR_CHUNK_SIZE_BYTES;
            $currentTmpSize = file_exists($tmpAssembled) ? filesize($tmpAssembled) : 0;
            if ($currentTmpSize <= $expectedOffset) {
                $fh = @fopen($tmpAssembled, 'ab');
                if ($fh) {
                    fwrite($fh, $chunkData);
                    fclose($fh);
                }
            }

            if ($session) {
                $session->recordChunk($chunkIndex, $chunkBytes);
            }

            unset($chunkData); // free memory immediately

            // ── 4. NOT FULLY UPLOADED: Return immediately ────────────────────
            $isCompleted = $session ? $session->isFullyUploaded() : ($chunkIndex >= $totalChunks - 1);
            if (!$isCompleted) {
                return response()->json([
                    'success'              => true,
                    'completed'            => false,
                    'chunk_index'          => $chunkIndex,
                    'total_chunks'         => $totalChunks,
                    'uploaded_chunks'      => $session ? $session->uploaded_chunks : range(0, $chunkIndex),
                    'missing_chunks'       => $session ? $session->getMissingChunks() : range($chunkIndex + 1, $totalChunks - 1),
                    'assembled_bytes'      => $session ? $session->uploaded_bytes : (file_exists($tmpAssembled) ? filesize($tmpAssembled) : 0),
                    'uploaded_bytes'       => $session ? $session->uploaded_bytes : 0,
                    'last_confirmed_chunk' => $session ? $session->last_confirmed_chunk : $chunkIndex,
                    'status'               => $session ? $session->status : 'UPLOADING',
                    'message'              => "Chunk {$chunkIndex} of {$totalChunks} received.",
                ]);
            }

            // ── 5. ALL CHUNKS RECEIVED: Assemble canonical file ───────────────
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'xls';
            $canonicalRelativePath = "uploads/fdr/{$uploadToken}.{$ext}";

            // Open assembly target in /tmp
            $asmHandle = fopen($tmpAssembled, 'wb');
            if (!$asmHandle) {
                throw new \RuntimeException("Failed to initialize assembled file in {$tmpAssembled}");
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $partData = null;
                $partPath = "fdr_chunks/{$uploadToken}/chunk_{$i}.part";

                if (Storage::disk('local')->exists($partPath)) {
                    $partData = Storage::disk('local')->get($partPath);
                }
                if ($partData === null || strlen($partData) === 0) {
                    $row = DB::table('upload_chunks')
                        ->where('upload_token', $uploadToken)
                        ->where('chunk_index', $i)
                        ->first();
                    $partData = $row ? $row->chunk_data : null;
                }

                if ($partData === null) {
                    fclose($asmHandle);
                    return response()->json([
                        'success'        => false,
                        'category'       => 'PROCESSING_FAILED',
                        'category_title' => 'ASSEMBLY INCOMPLETE',
                        'error'          => [
                            'code'      => 'CHUNK_MISSING',
                            'message'   => "Chunk {$i} is missing from persistent storage during assembly.",
                            'retryable' => true,
                        ],
                    ], 422);
                }

                fwrite($asmHandle, $partData);
                unset($partData);
            }
            fclose($asmHandle);

            $assembledSize = file_exists($tmpAssembled) ? filesize($tmpAssembled) : 0;
            if ($assembledSize < 10) {
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

            // Persist assembled file to Storage disk
            $storedViaStorage = false;
            try {
                Storage::disk('local')->put($canonicalRelativePath, fopen($tmpAssembled, 'r'));
                $assembledAbsolutePath = Storage::disk('local')->path($canonicalRelativePath);
                $storedViaStorage = true;
            } catch (\Throwable $e) {
                $assembledAbsolutePath = $tmpAssembled;
                $canonicalRelativePath = $tmpAssembled;
                Log::warning("Storage disk write failed, using assembled path directly: " . $e->getMessage());
            }

            // ── 6. FDR Validation & Job Queue (NO HEAVY PARSING HERE! §21) ────
            if ($isFdr) {
                $head = @file_get_contents($assembledAbsolutePath, false, null, 0, 65536) ?: '';
                $lowerHead = strtolower($head);
                $isFdrHtml = str_contains($lowerHead, '<table') || str_contains($lowerHead, '<html')
                    || str_contains($lowerHead, '<tr') || str_contains($lowerHead, 'aeronautical')
                    || str_contains($lowerHead, 'oasys') || str_contains($lowerHead, 'flight daily');

                if (!$isFdrHtml) {
                    @unlink($tmpAssembled);
                    if ($storedViaStorage) {
                        Storage::disk('local')->delete($canonicalRelativePath);
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

                $airportCode = 'CGK';
                if (preg_match('/name=[\'"]BRANCH_CODE[\'"][^>]*value=[\'"]([A-Z]{3,4})[\'"]/i', $head, $bcm)) {
                    $airportCode = strtoupper($bcm[1]);
                } elseif (preg_match('/value=[\'"]([A-Z]{3,4})[\'"][^>]*name=[\'"]BRANCH_CODE[\'"]/i', $head, $bcm2)) {
                    $airportCode = strtoupper($bcm2[1]);
                }
                $airport = \App\Models\Airport::findByIata($airportCode) ?? \App\Models\Airport::findByIata('CGK');
                $fileHash = @hash_file('sha256', $assembledAbsolutePath) ?: ($fileHash ?: null);

                if ($session) {
                    $session->update([
                        'file_hash'            => $fileHash,
                        'storage_path'         => $canonicalRelativePath,
                        'status'               => FdrUploadSession::STATUS_UPLOADED,
                        'uploaded_bytes'       => $assembledSize,
                        'last_confirmed_chunk' => $totalChunks - 1,
                    ]);
                }

                $upload = Upload::create([
                    'original_filename'  => $filename,
                    'stored_path'        => $canonicalRelativePath,
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

                $job = FdrProcessingJob::create([
                    'upload_id'      => $upload->id,
                    'upload_token'   => $uploadToken,
                    'filename'       => $filename,
                    'stored_path'    => $canonicalRelativePath,
                    'file_size'      => $assembledSize,
                    'file_hash'      => $fileHash,
                    'report_type'    => 'fdr',
                    'status'         => 'QUEUED',
                    'stage'          => 'QUEUED',
                    'stage_label'    => 'Queued for processing',
                    'progress'       => 0,
                    'processed_rows' => 0,
                    'total_rows'     => 0,
                    'meta'           => ['airport' => $airportCode, 'tmp_path' => $tmpAssembled],
                    'result_url'     => route('fdr.dashboard', ['upload' => $upload->id, 'date_scope' => 'ALL_PERIOD']),
                ]);

                if ($session) {
                    $session->update(['status' => FdrUploadSession::STATUS_PROCESSING]);
                }

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
                    Storage::disk('local')->delete($canonicalRelativePath);
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
                'stored_path'       => $canonicalRelativePath,
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
                'exception'   => $e,
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
