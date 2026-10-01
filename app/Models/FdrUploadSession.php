<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FdrUploadSession extends Model
{
    protected $table = 'fdr_upload_sessions';
    public $incrementing = false;
    protected $keyType = 'string';

    const STATUS_CREATED    = 'CREATED';
    const STATUS_UPLOADING  = 'UPLOADING';
    const STATUS_PAUSED     = 'PAUSED';
    const STATUS_UPLOADED   = 'UPLOADED';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_READY      = 'READY';
    const STATUS_FAILED     = 'FAILED';
    const STATUS_EXPIRED    = 'EXPIRED';

    protected static function booted()
    {
        static::creating(function ($session) {
            if (empty($session->id)) {
                $session->id = (string) Str::uuid();
            }
            if (empty($session->status)) {
                $session->status = self::STATUS_CREATED;
            }
            if ($session->uploaded_chunks === null) {
                $session->uploaded_chunks = [];
            }
        });
    }

    protected $fillable = [
        'id',
        'user_id',
        'upload_token',
        'original_filename',
        'file_size',
        'mime_type',
        'file_hash',
        'chunk_size',
        'total_chunks',
        'uploaded_bytes',
        'uploaded_chunks',
        'storage_bucket',
        'storage_path',
        'status',
        'progress',
        'last_confirmed_chunk',
        'failed_chunk',
        'expires_at',
    ];

    protected $casts = [
        'file_size'            => 'integer',
        'chunk_size'           => 'integer',
        'total_chunks'         => 'integer',
        'uploaded_bytes'       => 'integer',
        'progress'             => 'integer',
        'last_confirmed_chunk' => 'integer',
        'failed_chunk'         => 'integer',
        'uploaded_chunks'      => 'array',
        'expires_at'           => 'datetime',
    ];

    /**
     * Record a successfully received chunk idempotently.
     */
    public function recordChunk(int $chunkIndex, int $chunkBytes): bool
    {
        $chunks = $this->uploaded_chunks ?? [];

        if (!in_array($chunkIndex, $chunks, true)) {
            $chunks[] = $chunkIndex;
            sort($chunks);
            $this->uploaded_chunks = $chunks;
            $this->uploaded_bytes = min($this->file_size > 0 ? $this->file_size : PHP_INT_MAX, $this->uploaded_bytes + $chunkBytes);
            $this->last_confirmed_chunk = max($this->last_confirmed_chunk ?? -1, $chunkIndex);
            $this->progress = $this->total_chunks > 0 ? (int) round((count($chunks) / $this->total_chunks) * 100) : 0;

            if (count($chunks) >= $this->total_chunks && $this->total_chunks > 0) {
                $this->status = self::STATUS_UPLOADED;
            } else {
                $this->status = self::STATUS_UPLOADING;
            }

            $this->save();
            return true;
        }

        return false; // Already present, idempotent
    }

    /**
     * Check if a specific chunk has already been recorded.
     */
    public function hasChunk(int $chunkIndex): bool
    {
        $chunks = $this->uploaded_chunks ?? [];
        return in_array($chunkIndex, $chunks, true);
    }

    /**
     * Get missing chunk indices.
     */
    public function getMissingChunks(): array
    {
        $total = $this->total_chunks;
        $present = array_flip($this->uploaded_chunks ?? []);
        $missing = [];

        for ($i = 0; $i < $total; $i++) {
            if (!isset($present[$i])) {
                $missing[] = $i;
            }
        }

        return $missing;
    }

    /**
     * Check if all chunks have been received.
     */
    public function isFullyUploaded(): bool
    {
        if ($this->total_chunks <= 0) {
            return false;
        }
        $present = $this->uploaded_chunks ?? [];
        return count($present) >= $this->total_chunks;
    }
}
