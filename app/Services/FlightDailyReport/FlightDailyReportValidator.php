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
                'category'       => 'INVALID_EXTENSION',
                'category_title' => 'UNSUPPORTED FILE TYPE',
                'errors'         => ["Unsupported file extension {$displayExt}. Please upload an OASYS FDR (.xls, .xlsx, or .csv) file."],
            ];
        }

        try {
            $parsed = $this->parser->parse($filePath);
            $records = $parsed['records'] ?? [];
            $meta = $parsed['meta'] ?? [];

            if (empty($records)) {
                return [
                    'valid'          => false,
                    'category'       => 'NO_RECORDS',
                    'category_title' => 'NO FLIGHT RECORDS FOUND',
                    'errors'         => ['The workbook does not contain valid Flight Daily Report flight movement rows.'],
                ];
            }

            // Verify essential headers were found
            $sample = $records[0];
            $hasFlightNo = ($sample['flight_no'] !== 'N/A' || !empty($sample['flight_no']));
            $hasAirLine = ($sample['air_line'] !== 'N/A' || !empty($sample['air_line']));

            if (!$hasFlightNo && !$hasAirLine) {
                return [
                    'valid'          => false,
                    'category'       => 'MISSING_CRITICAL_COLUMNS',
                    'category_title' => 'MISSING REQUIRED HEADERS',
                    'errors'         => ['Workbook is missing essential FDR columns (AIR LINE, FLIGHT NO, LEG).'],
                ];
            }

            return [
                'valid'          => true,
                'records_count'  => count($records),
                'meta'           => $meta,
                'sample'         => array_slice($records, 0, 3),
                'summary'        => $parsed['summary'] ?? [],
                'errors'         => [],
            ];
        } catch (\Throwable $e) {
            return [
                'valid'          => false,
                'category'       => 'PARSING_EXCEPTION',
                'category_title' => 'PARSER FAILURE',
                'errors'         => [$e->getMessage()],
            ];
        }
    }
}
