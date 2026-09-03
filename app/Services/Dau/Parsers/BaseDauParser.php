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
        $fullText = strip_tags($content);

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

        // 2. Date match (TANGGAL 2026-08-01 s/d 2026-08-01)
        if (preg_match('/TANGGAL\s*([0-9]{4}-[0-9]{2}-[0-9]{2})\s*s\/d\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $fullText, $m)) {
            $meta['start_date'] = $m[1];
            $meta['end_date']   = $m[2];
            $meta['date_range'] = "{$m[1]} s/d {$m[2]}";
        } elseif (preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $fullText, $m)) {
            $meta['start_date'] = $m[1];
            $meta['end_date']   = $m[1];
            $meta['date_range'] = $m[1];
        }

        // 3. Flight scope
        if (preg_match('/PENERBANGAN\s+([A-Z\s&]+)/i', $fullText, $m)) {
            $meta['flight_scope'] = trim(explode("\n", $m[1])[0]);
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
}
