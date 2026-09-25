<?php

namespace App\Services\FlightDailyReport;

class FlightDailyReportValidator
{
    protected FlightDailyReportParser $parser;

    public function __construct(FlightDailyReportParser $parser = null)
    {
        $this->parser = $parser ?: new FlightDailyReportParser();
    }

    /**
     * Validate an FDR source file strictly.
     *
     * @param string $filePath Absolute path to source or temporary file
     * @param string|null $originalFilename Client original filename (if uploaded via HTTP)
     * @return array
     */
    public function validate(string $filePath, ?string $originalFilename = null): array
    {
        if (!file_exists($filePath)) {
            return [
                'valid'          => false,
                'category'       => 'FILE_NOT_FOUND',
                'category_title' => 'FILE NOT FOUND',
                'errors'         => ['The selected source file could not be found or read.'],
            ];
        }

        // Determine extension from original filename if provided, or from filePath
        $nameToInspect = $originalFilename ?: basename($filePath);
        $extension = '';
        if (preg_match('/\.([a-zA-Z0-9]+)$/', trim($nameToInspect), $m)) {
            $extension = strtolower($m[1]);
        }

        // If extension is missing or temporary (e.g. .tmp from php upload), detect by magic bytes
        if (empty($extension) || $extension === 'tmp') {
            $handle = @fopen($filePath, 'rb');
            if ($handle) {
                $header = fread($handle, 2048);
                fclose($handle);

                if (strncmp($header, "\xD0\xCF\x11\xE0", 4) === 0) {
                    $extension = 'xls';
                } elseif (strncmp($header, "PK\x03\x04", 4) === 0) {
                    $extension = 'xlsx';
                } elseif (stripos($header, '<html') !== false || stripos($header, '<table') !== false || stripos($header, '<center') !== false || stripos($header, '<title') !== false || stripos($header, 'oasys') !== false) {
                    $extension = 'xls';
                } elseif (strpos($header, ',') !== false || strpos($header, ';') !== false) {
                    $extension = 'csv';
                }
            }
        }

        $validExtensions = ['xls', 'xlsx', 'csv', 'html'];
        if (!in_array($extension, $validExtensions)) {
            $displayExt = (!empty($extension) && $extension !== 'tmp') ? ".{$extension}" : '(unknown)';
            return [
                'valid'          => false,
                'category'       => 'UNSUPPORTED_FILE_FORMAT',
                'category_title' => 'UNSUPPORTED FILE FORMAT',
                'errors'         => ["Unsupported file extension {$displayExt}. Please upload an OASYS FDR (.xls, .xlsx, or .csv) file."],
            ];
        }

        try {
            $parsed = $this->parser->parse($filePath);
            $records = $parsed['records'] ?? [];
            $meta = $parsed['meta'] ?? [];
            $detectedFormat = $parsed['detected_format'] ?? 'OASYS HTML XLS';

            if (empty($records)) {
                return [
                    'valid'          => false,
                    'category'       => 'NO_VALID_FLIGHT_MOVEMENT_ROWS',
                    'category_title' => 'NO VALID FLIGHT MOVEMENT ROWS FOUND',
                    'errors'         => ['No valid flight movement rows found in the Flight Daily Report.'],
                ];
            }

            // Verify essential headers were found
            $sample = $records[0];
            $hasFlightNo = ($sample['flight_no'] !== 'N/A' && !empty($sample['flight_no']));
            $hasAirLine = ($sample['air_line'] !== 'N/A' && !empty($sample['air_line']));

            if (!$hasFlightNo && !$hasAirLine) {
                return [
                    'valid'          => false,
                    'category'       => 'FLIGHT_TABLE_NOT_FOUND',
                    'category_title' => 'FLIGHT TABLE NOT FOUND',
                    'errors'         => ['Flight table structure could not be identified (missing AIR LINE and FLIGHT NO columns).'],
                ];
            }

            // Check date range validity if present
            if (!empty($meta['period_start']) && !empty($meta['period_end'])) {
                if ($meta['period_start'] > $meta['period_end']) {
                    return [
                        'valid'          => false,
                        'category'       => 'INVALID_DATE_RANGE',
                        'category_title' => 'INVALID DATE RANGE',
                        'errors'         => ['Detected start date is after end date in the Flight Daily Report.'],
                    ];
                }
            }

            return [
                'valid'            => true,
                'records_count'    => count($records),
                'detected_format'  => $detectedFormat,
                'file_name'        => $fileName ?: basename($filePath),
                'meta'             => $meta,
                'airport'          => $meta['airport'] ?? 'CGK',
                'operator'         => $meta['operator'] ?? 'ALL AIRLINE',
                'period_label'     => $meta['period_label'] ?? '',
                'date_start'       => $meta['date_start'] ?? ($meta['period_start'] ?? ''),
                'date_end'         => $meta['date_end'] ?? ($meta['period_end'] ?? ''),
                'direction'        => $meta['direction'] ?? 'ALL',
                'leg'              => $meta['leg'] ?? ($meta['direction'] ?? 'ALL'),
                'route_type'       => $meta['route_type'] ?? 'ALL',
                'realization'      => $meta['realization'] ?? 'YES',
                'sample'           => array_slice($records, 0, 3),
                'summary'          => $parsed['summary'] ?? [],
                'errors'           => [],
            ];
        } catch (\Throwable $e) {
            return [
                'valid'          => false,
                'category'       => 'OASYS_FDR_NOT_DETECTED',
                'category_title' => 'OASYS FDR FORMAT NOT DETECTED',
                'errors'         => [$e->getMessage()],
            ];
        }
    }
}
