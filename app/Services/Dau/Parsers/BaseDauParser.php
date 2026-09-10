<?php

namespace App\Services\Dau\Parsers;

use DOMDocument;
use DOMElement;

abstract class BaseDauParser
{
    /**
     * Parse the file into a normalized DAU data array.
     */
    abstract public function parse(string $filePath): array;

    /**
     * Parse HTML table or Excel file into a 2D matrix of row cells.
     */
    protected function extractRawTable(string $filePath): array
    {
        $content = file_get_contents($filePath);

        // OASYS exports are HTML tables saved as .xls
        if ($this->isHtmlTable($content)) {
            return $this->extractFromHtml($content);
        }

        // Fallback for real binary Excel .xls or .xlsx
        return $this->extractFromSpreadsheet($filePath);
    }

    /**
     * Check if file content is an HTML table.
     */
    protected function isHtmlTable(string $content): bool
    {
        $head = substr($content, 0, 1024);
        return (stripos($head, '<html') !== false
            || stripos($head, '<table') !== false
            || stripos($head, '<title') !== false
            || stripos($head, '<center') !== false);
    }

    /**
     * Extract full cell matrix from HTML taking into account colspan and rowspan.
     */
    protected function extractFromHtml(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // Ensure UTF-8 handling
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            return [];
        }

        $table = $tables->item(0);
        $rows = $table->getElementsByTagName('tr');

        $grid = [];
        $rIdx = 0;

        foreach ($rows as $tr) {
            $cIdx = 0;
            foreach ($tr->childNodes as $node) {
                if (!($node instanceof DOMElement) || ($node->nodeName !== 'td' && $node->nodeName !== 'th')) {
                    continue;
                }

                // Advance over already filled cells from previous rowspans
                while (isset($grid[$rIdx][$cIdx])) {
                    $cIdx++;
                }

                $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
                $colspan = (int) $node->getAttribute('colspan') ?: 1;
                $rowspan = (int) $node->getAttribute('rowspan') ?: 1;

                for ($r = 0; $r < $rowspan; $r++) {
                    for ($c = 0; $c < $colspan; $c++) {
                        $targetR = $rIdx + $r;
                        $targetC = $cIdx + $c;
                        // Put full text in the top-left cell, and in spans propagate or mark
                        $grid[$targetR][$targetC] = ($r === 0 && $c === 0) ? $text : $text;
                    }
                }

                $cIdx += $colspan;
            }
            $rIdx++;
        }

        // Normalize grid into sequential rows
        $matrix = [];
        $maxCols = 0;
        foreach ($grid as $r => $cols) {
            ksort($cols);
            $maxCols = max($maxCols, count($cols));
            $matrix[$r] = array_values($cols);
        }

        return $matrix;
    }

    /**
     * Fallback for PhpSpreadsheet binary files.
     */
    protected function extractFromSpreadsheet(string $filePath): array
    {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            return $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Extract report header metadata (Airport, Date, Scope, Terminal) from HTML or top rows.
     */
    protected function extractMetadata(string $filePath, array $rawRows): array
    {
        $content = file_get_contents($filePath);
        $normalizedContent = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $fullText = strip_tags($normalizedContent);

        // Default meta
        $meta = [
            'airport'         => 'Soekarno Hatta (CGK)',
            'airport_code'    => 'CGK',
            'airport_name'    => 'Tangerang Banten - Soekarno Hatta',
            'start_date'      => null,
            'end_date'        => null,
            'date_range'      => null,
            'flight_scope'    => 'DOMESTIK & INTERNASIONAL',
            'terminal_scope'  => 'ALL TERMINAL',
            'source'          => 'OASYS',
            'generated_at'    => now()->toDateTimeString(),
        ];

        // 1. Airport match
        if (preg_match('/(?:BANDARA|AIRPORT|TANGERANG|JAKARTA|[A-Z\s]+)\s*-\s*([A-Za-z\s]+)\s*\(([A-Z]{3})\)/i', $fullText, $m)) {
            $meta['airport_name'] = trim($m[1]);
            $meta['airport_code'] = strtoupper(trim($m[2]));
            $meta['airport']      = "{$meta['airport_name']} ({$meta['airport_code']})";
        } elseif (preg_match('/\(([A-Z]{3})\)/', $fullText, $m)) {
            $meta['airport_code'] = strtoupper($m[1]);
            $meta['airport']      = $meta['airport_code'];
        }

        // 2. Date match (Supports DD-MM-YYYY, DD/MM/YYYY, YYYY-MM-DD, DD Mon YYYY, and ranges)
        $datePat = '(?:\d{1,2}[-\/]\d{1,2}[-\/]\d{4}|\d{4}[-\/]\d{1,2}[-\/]\d{1,2}|\d{1,2}\s+[A-Za-z]+\s+\d{4})';
        $foundDate = false;

        // Header text only needs first 4096 bytes
        $headerSection = substr($fullText, 0, 4096);

        // Range: TANGGAL <start> s/d <end>
        if (preg_match('/(?:TANGGAL|DATE)[\s:]*(' . $datePat . ')\s*(?:s\/d|s\.d|to|\-|sampai)\s*(' . $datePat . ')/i', $headerSection, $m)) {
            $s = static::normalizeOperationalDate($m[1]);
            $e = static::normalizeOperationalDate($m[2]);
            if ($s && $e) {
                $meta['start_date'] = $s;
                $meta['end_date']   = $e;
                $meta['date_range'] = ($s === $e) ? $s : "{$s} s/d {$e}";
                $foundDate = true;
            } elseif ($s) {
                $meta['start_date'] = $s;
                $meta['end_date']   = $s;
                $meta['date_range'] = $s;
                $foundDate = true;
            }
        }

        // Single date with TANGGAL keyword
        if (!$foundDate && preg_match('/(?:TANGGAL|DATE)[\s:]*(' . $datePat . ')/i', $headerSection, $m)) {
            $s = static::normalizeOperationalDate($m[1]);
            if ($s) {
                $meta['start_date'] = $s;
                $meta['end_date']   = $s;
                $meta['date_range'] = $s;
                $foundDate = true;
            }
        }

        // Fallback standalone date range
        if (!$foundDate && preg_match('/(' . $datePat . ')\s*(?:s\/d|s\.d|to|\-)\s*(' . $datePat . ')/i', $headerSection, $m)) {
            $s = static::normalizeOperationalDate($m[1]);
            $e = static::normalizeOperationalDate($m[2]);
            if ($s && $e) {
                $meta['start_date'] = $s;
                $meta['end_date']   = $e;
                $meta['date_range'] = ($s === $e) ? $s : "{$s} s/d {$e}";
                $foundDate = true;
            }
        }

        // Fallback single date
        if (!$foundDate && preg_match('/(' . $datePat . ')/i', $headerSection, $m)) {
            $s = static::normalizeOperationalDate($m[1]);
            if ($s) {
                $meta['start_date'] = $s;
                $meta['end_date']   = $s;
                $meta['date_range'] = $s;
            }
        }

        // 3. Flight scope
        if (preg_match('/PENERBANGAN\s+([A-Z\s&]+)/i', $fullText, $m)) {
            $extractedScope = trim(explode("\n", $m[1])[0]);
            if (stripos($extractedScope, 'DOMESTIK & INTERNASIONAL') !== false) {
                $meta['flight_scope'] = 'DOMESTIK & INTERNASIONAL';
            } elseif (stripos($extractedScope, 'INTERNASIONAL') !== false) {
                $meta['flight_scope'] = 'INTERNASIONAL';
            } elseif (stripos($extractedScope, 'DOMESTIK') !== false) {
                $meta['flight_scope'] = 'DOMESTIK';
            } else {
                $meta['flight_scope'] = $extractedScope;
            }
        }

        // 4. Terminal scope
        if (preg_match('/(ALL TERMINAL|TERMINAL\s+[A-Z0-9]+)/i', $fullText, $m)) {
            $meta['terminal_scope'] = trim($m[1]);
        }

        return $meta;
    }

    /**
     * Clean and convert cell value to integer.
     */
    protected function toInt(mixed $val): int
    {
        if ($val === null || $val === '') return 0;
        if (is_numeric($val)) return (int) $val;
        // remove thousand separators and non-digits
        $clean = preg_replace('/[^0-9\-]/', '', (string) $val);
        return (int) $clean;
    }

    /**
     * Clean and convert cell value to float.
     */
    protected function toFloat(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float) $val;
        $clean = preg_replace('/[^0-9\.\-]/', '', (string) $val);
        return (float) $clean;
    }

    /**
     * Clean string text.
     */
    protected function toStr(mixed $val): string
    {
        if ($val === null) return '';
        return trim(preg_replace('/\s+/', ' ', (string) $val));
    }

    /**
     * Canonical operational date normalization.
     * 
     * Supported formats:
     *  - DD-MM-YYYY (e.g. 01-08-2026 -> 2026-08-01, 31-08-2026 -> 2026-08-31)
     *  - DD/MM/YYYY (e.g. 01/08/2026 -> 2026-08-01, 31/01/2027 -> 2027-01-31)
     *  - YYYY-MM-DD (e.g. 2026-08-01 -> 2026-08-01)
     *  - DD Mon YYYY (e.g. 01 Aug 2026, 31 Jan 2027, 01 Agustus 2026)
     * 
     * Returns normalized YYYY-MM-DD string, or null if invalid/unparseable.
     * NEVER returns a silent fallback like 2026-01-01.
     */
    public static function normalizeOperationalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // Clean surrounding punctuation or brackets if any
        $str = trim($str, " \t\n\r\0\x0B()[]{}.,;:'\"");

        // 1. ISO format: YYYY-MM-DD or YYYY/MM/DD
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $str, $m)) {
            $year  = (int) $m[1];
            $month = (int) $m[2];
            $day   = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
            \Illuminate\Support\Facades\Log::warning("DAU Date Normalizer: Invalid ISO date components", ['raw' => $str]);
            return null;
        }

        // 2. Standard Indonesian/UK format: DD-MM-YYYY or DD/MM/YYYY
        // Critical: 01-08-2026 is day 1, month 8, year 2026 (2026-08-01), NOT 2026-01-08
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $str, $m)) {
            $day   = (int) $m[1];
            $month = (int) $m[2];
            $year  = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
            \Illuminate\Support\Facades\Log::warning("DAU Date Normalizer: Invalid DD-MM-YYYY date components", ['raw' => $str]);
            return null;
        }

        // 3. Textual month format: DD Mon YYYY (e.g. 01 Aug 2026, 31 Jan 2027, 01 Agustus 2026)
        if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/', $str, $m)) {
            $day    = (int) $m[1];
            $monStr = strtolower($m[2]);
            $year   = (int) $m[3];

            $monthMap = [
                'jan' => 1, 'januari' => 1, 'january' => 1,
                'feb' => 2, 'februari' => 2, 'february' => 2,
                'mar' => 3, 'maret' => 3, 'march' => 3,
                'apr' => 4, 'april' => 4,
                'mei' => 5, 'may' => 5,
                'jun' => 6, 'juni' => 6, 'june' => 6,
                'jul' => 7, 'juli' => 7, 'july' => 7,
                'agu' => 8, 'aug' => 8, 'agustus' => 8, 'august' => 8,
                'sep' => 9, 'september' => 9,
                'okt' => 10, 'oct' => 10, 'oktober' => 10, 'october' => 10,
                'nov' => 11, 'november' => 11,
                'des' => 12, 'dec' => 12, 'desember' => 12, 'december' => 12,
            ];

            if (isset($monthMap[$monStr])) {
                $month = $monthMap[$monStr];
                if (checkdate($month, $day, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
            \Illuminate\Support\Facades\Log::warning("DAU Date Normalizer: Invalid DD Mon YYYY date components", ['raw' => $str]);
            return null;
        }

        // Failed to match any known operational date format
        if (strlen($str) > 0 && preg_match('/\d/', $str)) {
            \Illuminate\Support\Facades\Log::warning("DAU Date Normalizer: Failed to parse date string", ['raw' => $str]);
        }
        return null;
    }

    /**
     * Convert normalized ISO date (YYYY-MM-DD) to display format (DD-MM-YYYY).
     */
    public static function formatDisplayDate(?string $isoDate): string
    {
        if (empty($isoDate)) {
            return '';
        }
        $clean = trim($isoDate);
        $parts = explode('-', $clean);
        if (count($parts) === 3 && strlen($parts[0]) === 4) {
            return sprintf('%02d-%02d-%04d', (int) $parts[2], (int) $parts[1], (int) $parts[0]);
        }
        return $clean;
    }
}

