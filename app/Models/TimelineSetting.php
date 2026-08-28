<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimelineSetting extends Model
{
    protected $fillable = ['upload_id', 'ops_start', 'ops_end'];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
