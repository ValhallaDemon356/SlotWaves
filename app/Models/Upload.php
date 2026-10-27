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
        'cached_analytics',
    ];

    protected $casts = [
        'validation_summary' => 'array',
        'report_data'        => 'array',
        'cached_analytics'   => 'array',
        'parsing_confidence' => 'float',
    ];

    public function getCachedAnalyticsAttribute($value)
    {
        $data = is_array($value) ? $value : (json_decode($value, true) ?: []);
        return $data;
    }

    public function getReportDataAttribute($value)
    {
        $data = is_array($value) ? $value : (json_decode($value, true) ?: []);
        if (empty($data['records'])) {
            $offloaded = $this->getOffloadedRecords();
            if (!empty($offloaded)) {
                $data['records'] = $offloaded;
            }
        }
        return $data;
    }

    /**
     * Explicitly load records from offloaded compressed storage if needed.
     */
    public function getOffloadedRecords(): array
    {
        $data = is_array($this->attributes['report_data'] ?? null)
            ? $this->attributes['report_data']
            : (json_decode($this->attributes['report_data'] ?? '[]', true) ?: []);

        if (!empty($data['records'])) {
            return $data['records'];
        }

        if (!empty($data['_storage_path'])) {
            try {
                $disk = config('filesystems.default', 'local');
                if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($data['_storage_path'])) {
                    $raw = \Illuminate\Support\Facades\Storage::disk($disk)->get($data['_storage_path']);
                    $decompressed = str_ends_with($data['_storage_path'], '.gz') ? gzdecode($raw) : $raw;
                    unset($raw);
                    $records = json_decode($decompressed, true);
                    unset($decompressed);
                    return $records ?: [];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to load offloaded records: " . $e->getMessage());
            }
        }

        // For FDR reports, fetch from the fdr_flights database table
        if (($this->report_type ?? '') === 'fdr' && !empty($this->id)) {
            try {
                return \App\Models\FdrFlight::where('upload_id', $this->id)
                    ->orderBy('id')
                    ->get()
                    ->map->toFdrArray()
                    ->toArray();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to fetch fdr_flights for upload #{$this->id}: " . $e->getMessage());
            }
        }

        return [];
    }

    /**
     * Resilient factory method that executes Upload::create inside executeWithRetry.
     */
    public static function createWithRetry(array $attributes, int $maxRetries = 3): static
    {
        return \App\Services\FlightDailyReport\FdrDatabaseService::executeWithRetry(function () use ($attributes) {
            return static::create($attributes);
        }, $maxRetries);
    }

    /**
     * Resilient instance updater that executes update inside executeWithRetry.
     */
    public function updateWithRetry(array $attributes = [], array $options = [], int $maxRetries = 3): bool
    {
        return \App\Services\FlightDailyReport\FdrDatabaseService::executeWithRetry(function () use ($attributes, $options) {
            return $this->update($attributes, $options);
        }, $maxRetries);
    }

    public function setReportDataAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            // Strip out massive raw records (> 50) or raw HTML strings to prevent DB row bloat
            if (isset($value['records']) && is_array($value['records']) && count($value['records']) > 50) {
                // If larger than 500 records, optionally offload to disk/storage
                if (count($value['records']) > 500) {
                    try {
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
                            $value['_storage_path'] = $path;
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Could not offload records to gz: " . $e->getMessage());
                    }
                }
                unset($value['records']);
            }
            if (isset($value['comparison_rows']) && is_array($value['comparison_rows']) && count($value['comparison_rows']) > 50) {
                unset($value['comparison_rows']);
            }
            if (isset($value['rows']) && is_array($value['rows']) && count($value['rows']) > 50) {
                unset($value['rows']);
            }
            if (isset($value['data']) && is_array($value['data']) && count($value['data']) > 50) {
                unset($value['data']);
            }

            // Clean internal parser buffers from meta
            if (isset($value['meta']) && is_array($value['meta'])) {
                unset(
                    $value['meta']['_summaries'],
                    $value['meta']['_col_map'],
                    $value['meta']['_header_cols'],
                    $value['meta']['_header_byte_offset']
                );
            }

            unset($value['raw_html'], $value['html'], $value['raw_content'], $value['content'], $value['_raw']);

            // Recursively strip giant strings (> 10KB) to prevent accidental raw HTML insertion
            foreach ($value as $k => $v) {
                if (is_string($v) && strlen($v) > 10240) {
                    unset($value[$k]);
                }
            }
        }
        $this->attributes['report_data'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setValidationSummaryAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            // Keep strictly lightweight metadata
            $trimmed = [
                'valid'             => (bool)($value['valid'] ?? true),
                'status'            => $value['status'] ?? ($value['stage'] ?? 'COMPLETED'),
                'detected_template' => $value['detected_template'] ?? ($value['template'] ?? 'NORMAL'),
                'records_count'     => $value['records_count'] ?? ($value['total_rows'] ?? 0),
                'category'          => $value['category'] ?? null,
                'category_title'    => $value['category_title'] ?? null,
            ];

            if (!empty($value['errors']) && is_array($value['errors'])) {
                $trimmed['errors'] = array_slice($value['errors'], 0, 5);
            }
            if (!empty($value['zero_state'])) {
                $trimmed['zero_state'] = true;
            }
            if (!empty($value['meta']['airport_code'])) {
                $trimmed['meta'] = ['airport_code' => $value['meta']['airport_code']];
            }

            $value = array_filter($trimmed, fn($v) => $v !== null);
        }
        $this->attributes['validation_summary'] = is_array($value) ? json_encode($value) : $value;
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

    public function fdrFlights(): HasMany
    {
        return $this->hasMany(FdrFlight::class);
    }
}
