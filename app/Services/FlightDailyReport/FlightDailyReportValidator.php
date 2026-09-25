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
     */
    public function validate(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'valid'          => false,
                'category'       => 'FILE_NOT_FOUND',
                'category_title' => 'FILE NOT FOUND',
                'errors'         => ['The selected source file could not be found or read.'],
            ];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $validExtensions = ['xls', 'xlsx', 'csv', 'html'];
        if (!in_array($extension, $validExtensions)) {
            return [
                'valid'          => false,
                'category'       => 'INVALID_EXTENSION',
                'category_title' => 'UNSUPPORTED FILE TYPE',
                'errors'         => ["Unsupported file extension .{$extension}. Please upload an OASYS FDR (.xls, .xlsx, or .csv) file."],
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
