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

    public function getReportDataAttribute($value)
    {
        $data = is_array($value) ? $value : (json_decode($value, true) ?: []);
        if (!isset($data['records']) && !empty($data['_storage_path'])) {
            try {
                $disk = config('filesystems.default', 'local');
                if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($data['_storage_path'])) {
                    $raw = \Illuminate\Support\Facades\Storage::disk($disk)->get($data['_storage_path']);
                    $decompressed = str_ends_with($data['_storage_path'], '.gz') ? gzdecode($raw) : $raw;
                    unset($raw);
                    $records = json_decode($decompressed, true);
                    unset($decompressed);
                    $data['records'] = $records ?: [];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to load records from storage: " . $e->getMessage());
            }
        }
        return $data;
    }

    public function setReportDataAttribute($value)
    {
        if (is_array($value) && isset($value['records']) && count($value['records']) > 500) {
            $disk = config('filesystems.default', 'local');
            $fileKey = !empty($this->id) ? "id_{$this->id}" : ('key_' . md5($this->stored_path ?? uniqid()));
            $path = "reports/fdr_{$fileKey}.json.gz";

            $tmpFile = tempnam(sys_get_temp_dir(), 'fdr_gz_');
            $gz = gzopen($tmpFile, 'wb6');
            if ($gz) {
                gzwrite($gz, '[');
                $first = true;
                foreach ($value['records'] as $rec) {
                    if (!$first) {
                        gzwrite($gz, ',');
                    }
                    gzwrite($gz, json_encode($rec));
                    $first = false;
                }
                gzwrite($gz, ']');
                gzclose($gz);

                $stream = fopen($tmpFile, 'rb');
                \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $stream);
                if (is_resource($stream)) fclose($stream);
                @unlink($tmpFile);
            }

            $light = $value;
            unset($light['records']);
            $light['_storage_path'] = $path;
            $this->attributes['report_data'] = json_encode($light);
            return;
        }
        $this->attributes['report_data'] = is_array($value) ? json_encode($value) : $value;
    }

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
