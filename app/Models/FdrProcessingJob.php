<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FdrProcessingJob extends Model
{
    protected $table = 'fdr_processing_jobs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($job) {
            if (empty($job->id)) {
                $job->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected $fillable = [
        'id',
        'upload_id',
        'upload_token',
        'filename',
        'stored_path',
        'file_size',
        'file_hash',
        'report_type',
        'status',
        'stage_label',
        'progress',
        'processed_rows',
        'total_rows',
        'meta',
        'diagnostics',
        'error_message',
        'result_url',
    ];

    protected $casts = [
        'meta'           => 'array',
        'diagnostics'    => 'array',
        'progress'       => 'integer',
        'processed_rows' => 'integer',
        'total_rows'     => 'integer',
        'file_size'      => 'integer',
    ];

    public function upload()
    {
        return $this->belongsTo(Upload::class, 'upload_id');
    }
}
