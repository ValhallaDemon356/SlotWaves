<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Upload extends Model
{
    protected $fillable = [
        'original_filename',
        'stored_path',
        'status',
        'error_message',
        'season',
        'airport_id',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'duplicate_rows',
        'parsing_confidence',
        'validation_summary',
        'report_type',
        'report_data',
    ];

    protected $casts = [
        'validation_summary' => 'array',
        'report_data'        => 'array',
        'parsing_confidence' => 'float',
    ];

    public function airport()
    {
        return $this->belongsTo(Airport::class);
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }

    public function timelinePositions(): HasMany
    {
        return $this->hasMany(TimelinePosition::class);
    }
}
