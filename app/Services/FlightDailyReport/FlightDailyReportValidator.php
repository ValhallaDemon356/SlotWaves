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
    public function validate(string $filePath, ?string $originalFilename = null, bool $isProbe = false): array
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
                'category'       => 'INVALID_EXTENSION',
                'category_title' => 'UNSUPPORTED FILE FORMAT',
                'errors'         => ["Unsupported file extension {$displayExt}. Please upload an OASYS FDR (.xls, .xlsx, or .csv) file."],
            ];
        }

        try {
            // Fast structural check for probe slices or large files (> 3 MB)
            $fileSizeBytes = file_exists($filePath) ? filesize($filePath) : 0;
            // Fast structural check for probe slices or large files (> 3 MB)
            $fileSizeBytes = file_exists($filePath) ? filesize($filePath) : 0;
            if ($isProbe || $fileSizeBytes > 3 * 1024 * 1024) {
                $handle = @fopen($filePath, 'rb');
                $lead = $handle ? fread($handle, 131072) : '';
                if ($handle) {
                    fclose($handle);
                }

                $fname = $originalFilename ?: basename($filePath);
                $detection = FlightDailyReportParser::detectOasysFdrDetailed($lead, $fname);

                $hasFdrSignatures = (
                    $detection['confidence'] >= 0.70 ||
                    stripos($lead, 'AIR LINE') !== false ||
                    stripos($lead, 'AIR<br>LINE') !== false ||
                    stripos($lead, 'Flight Daily Report') !== false ||
                    stripos($lead, 'transactions_datefdr') !== false ||
                    stripos($lead, 'aeronautical') !== false ||
                    stripos($lead, 'SIBT') !== false ||
                    stripos($lead, 'SOBT') !== false ||
                    stripos($lead, 'SEAT LOAD FACTOR') !== false ||
                    stripos($lead, 'API PAX EDIFLY') !== false ||
                    stripos($lead, 'OFF BLOCK TIME') !== false ||
                    stripos($lead, 'FLT DEP') !== false
                );

                if ($hasFdrSignatures) {
                    $metaHeaders = [];
                    if (preg_match_all('/<input[^>]+>/i', $lead, $mInputs)) {
                        foreach ($mInputs[0] as $input) {
                            $name = '';
                            $value = '';
                            if (preg_match('/name=["\']([^"\']+)["\']/i', $input, $n)) $name = $n[1];
                            if (preg_match('/value=["\']([^"\']*)["\']/i', $input, $v)) $value = $v[1];
                            if ($name !== '') $metaHeaders[] = strtoupper($name) . ': ' . trim($value);
                        }
                    }
                    $meta = $this->parser->extractMetadata($metaHeaders, []);
                    $meta['detected_template'] = $detection['template'];
                    $meta['template_type'] = $detection['template'];
                    $meta['report_type'] = $detection['template'];

                    return [
                        'valid'             => true,
                        'is_large_file'     => true,
                        'records_count'     => 'Large Dataset',
                        'detected_format'   => 'OASYS HTML XLS',
                        'detected_template' => $detection['template'],
                        'template'          => $detection['template'],
                        'template_confidence' => $detection['confidence'],
                        'template_reasons'  => $detection['reasons'],
                        'file_name'         => $fname,
                        'meta'              => $meta,
                        'airport'           => $meta['airport'] ?? 'CGK',
                        'operator'          => $meta['operator'] ?? 'ALL AIRLINE',
                        'period_label'      => $meta['period_label'] ?? '',
                        'date_start'        => $meta['date_start'] ?? ($meta['period_start'] ?? ''),
                        'date_end'          => $meta['date_end'] ?? ($meta['period_end'] ?? ''),
                        'direction'         => $meta['direction'] ?? 'ALL',
                        'leg'               => $meta['leg'] ?? ($meta['direction'] ?? 'ALL'),
                        'route_type'        => $meta['route_type'] ?? 'ALL',
                        'realization'       => $meta['realization'] ?? 'YES',
                        'summary'           => $meta['source_summary'] ?? [],
                    ];
                }
            }

            $parsed = $this->parser->parse($filePath);
            $records = $parsed['records'] ?? [];
            $meta = $parsed['meta'] ?? [];
            $detectedFormat = $parsed['detected_format'] ?? 'OASYS HTML XLS';
            $detectedTemplate = $meta['template_type'] ?? ($meta['report_type'] ?? 'NORMAL');
            $fname = $originalFilename ?: basename($filePath);

            if (empty($records)) {
                $isExHalim = (
                    $detectedTemplate === 'EX_HALIM' ||
                    stripos($fname, 'EX HALIM') !== false ||
                    stripos($fname, 'HLP') !== false ||
                    (!empty($meta['source_summary']['pax_all']) && $meta['source_summary']['pax_all'] === 0) ||
                    (!empty($meta['airport']) && $meta['airport'] === 'CGK' && ($meta['is_zero_state'] ?? false))
                );

                if ($isExHalim) {
                    return [
                        'valid'             => true,
                        'records_count'     => 0,
                        'detected_format'   => $detectedFormat,
                        'detected_template' => 'EX_HALIM',
                        'template'          => 'EX_HALIM',
                        'file_name'         => $fname,
                        'meta'              => $meta,
                        'airport'           => $meta['airport'] ?? 'CGK',
                        'operator'          => $meta['operator'] ?? 'ALL AIRLINE',
                        'period_label'      => $meta['period_label'] ?? '',
                        'date_start'        => $meta['date_start'] ?? ($meta['period_start'] ?? ''),
                        'date_end'          => $meta['date_end'] ?? ($meta['period_end'] ?? ''),
                        'direction'         => $meta['direction'] ?? 'ALL',
                        'leg'               => $meta['leg'] ?? ($meta['direction'] ?? 'ALL'),
                        'route_type'        => $meta['route_type'] ?? 'ALL',
                        'realization'       => $meta['realization'] ?? 'YES',
                        'sample'            => [],
                        'summary'           => $parsed['summary'] ?? [],
                        'errors'            => [],
                        'zero_state'        => true,
                        'zero_state_title'  => 'Zero Ex-Halim Movements Verified',
                    ];
                }

                return [
                    'valid'          => false,
                    'category'       => 'NO_RECORDS',
                    'category_title' => 'NO VALID FLIGHT MOVEMENT ROWS FOUND',
                    'errors'         => ['No valid flight movement rows found in the Flight Daily Report.'],
                ];
            }

            // Template-aware header and structure verification
            $sample = $records[0];
            if ($detectedTemplate === 'COMPARE_LOAD_FACTOR' || $detectedTemplate === 'COMPARE_LOAD_FACTOR_DAY') {
                if (!isset($sample['airport']) && !isset($sample['flight_2025']) && !isset($sample['date'])) {
                    return [
                        'valid'          => false,
                        'category'       => 'INVALID_COMPARE_SCHEMA',
                        'category_title' => 'INVALID COMPARISON TABLE FORMAT',
                        'errors'         => ['Comparison table structure could not be identified (missing Airport/Date/YoY columns).'],
                    ];
                }
            } else {
                $hasFlightNo = (!empty($sample['flight_no']) && $sample['flight_no'] !== 'N/A') || (!empty($sample['flight_number']) && $sample['flight_number'] !== 'N/A');
                $hasAirLine = (!empty($sample['air_line']) && $sample['air_line'] !== 'N/A') || (!empty($sample['airline']) && $sample['airline'] !== 'N/A') || (!empty($sample['airlines']) && $sample['airlines'] !== 'N/A');

                if (!$hasFlightNo && !$hasAirLine) {
                    return [
                        'valid'          => false,
                        'category'       => 'FLIGHT_TABLE_NOT_FOUND',
                        'category_title' => 'FLIGHT TABLE NOT FOUND',
                        'errors'         => ['Flight table structure could not be identified (missing AIR LINE and FLIGHT NO columns).'],
                    ];
                }
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
                'valid'             => true,
                'records_count'     => count($records),
                'detected_format'   => $detectedFormat,
                'detected_template' => $detectedTemplate,
                'template'          => $detectedTemplate,
                'template_confidence' => $meta['template_confidence'] ?? 1.0,
                'template_reasons'  => $meta['template_reasons'] ?? [],
                'file_name'         => $fname,
                'meta'              => $meta,
                'airport'           => $meta['airport'] ?? 'CGK',
                'operator'          => $meta['operator'] ?? 'ALL AIRLINE',
                'period_label'      => $meta['period_label'] ?? '',
                'date_start'        => $meta['date_start'] ?? ($meta['period_start'] ?? ''),
                'date_end'          => $meta['date_end'] ?? ($meta['period_end'] ?? ''),
                'direction'         => $meta['direction'] ?? 'ALL',
                'leg'               => $meta['leg'] ?? ($meta['direction'] ?? 'ALL'),
                'route_type'        => $meta['route_type'] ?? 'ALL',
                'realization'       => $meta['realization'] ?? 'YES',
                'sample'            => array_slice($records, 0, 3),
                'summary'           => $parsed['summary'] ?? [],
                'errors'            => [],
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
