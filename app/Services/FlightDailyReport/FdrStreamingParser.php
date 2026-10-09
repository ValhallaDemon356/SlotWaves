<?php

namespace App\Services\FlightDailyReport;

use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use InvalidArgumentException;

class FdrStreamingParser
{
    /**
     * FDR Header mapping patterns.
     */
    protected const HEADER_MAPPINGS = [
        'air_line'    => '/^(air\s*line|airline|maskapai|operator)$/i',
        'flight_no'   => '/^(flight\s*no\.?|flt\s*no\.?|flight\s*number|no\s*penerbangan)$/i',
        'paired_no'   => '/^(paired\s*no\.?|pair\s*no\.?|paired\s*flight|no\s*pasangan)$/i',
        'desc'        => '/^(desc|description|keterangan)$/i',
        'sibt'        => '/^(sibt|sched(uled)?\s*in(\s*block)?(\s*time)?|sta\b)$/i',
        'sobt'        => '/^(sobt|sched(uled)?\s*off(\s*block)?(\s*time)?|std\b)$/i',
        'sibt_sobt'   => '/^(sibt[\s\/\-_]+sobt|sibt\s*sobt|sched(uled)?\s*block(\s*time)?)$/i',
        'aibt'        => '/^(aibt|actual\s*in(\s*block)?(\s*time)?|ata\b)$/i',
        'aobt'        => '/^(aobt|actual\s*off(\s*block)?(\s*time)?|atd\b)$/i',
        'aibt_aobt'   => '/^(aibt[\s\/\-_]+aobt|aibt\s*aobt|actual\s*block(\s*time)?)$/i',
        'leg'         => '/^(leg|leg\s*type|status\s*leg|a\/d\s*sched)$/i',
        'city_1'      => '/^(city\s*1|origin|asal|dari|bandara\s*asal|dep\s*apt)$/i',
        'city_2'      => '/^(city\s*2|destination|dest|tujuan|ke|bandara\s*tujuan|arr\s*apt)$/i',
        'mtow'        => '/^(mt\s*ow|mtow|max\s*take\s*off\s*weight)$/i',
        'reg_no'      => '/^(reg\.?\s*no\.?|registration|tail\s*no\.?|no\s*registrasi)$/i',
        'cap'         => '/^(cap\.?|capacity|seat\s*capacity|kapasitas(\s*kursi)?)$/i',
        'load'        => '/^(load|total\s*load|pax\s*load|muatan|penumpang\s*total)$/i',
        'adult'       => '/^(adult|dewasa)$/i',
        'child'       => '/^(child|anak)$/i',
        'infant'      => '/^(infant|bayi)$/i',
        'transit'     => '/^(transit)$/i',
        'transfer'    => '/^(transfer|tranfer)$/i',
        'divert'      => '/^(divert|diverted)$/i',
        'miss'        => '/^(miss|missed|batal|cancel)$/i',
        'crw'         => '/^(crw|crew|awak)$/i',
        'ex_crw'      => '/^(ex\.?\s*crw|extra\s*crew|awak\s*ekstra)$/i',
        'umroh'       => '/^(umroh|umrah)$/i',
        'cargo_kg'    => '/^(car\.?\s*\(?kg\)?|cargo|kargo)$/i',
        'baggage_kg'  => '/^(bagg?\.?\s*\(?kg\)?|baggage|bagasi)$/i',
        'pos_kg'      => '/^(pos\.?\s*\(?kg\)?|post|mail)$/i',
        'stand'       => '/^(stand|gate|parking\s*stand|apron\s*stand)$/i',
        'runway'      => '/^(run\s*way|runway|rwy)$/i',
        'final'       => '/^(final|status\s*final)$/i',
        'final_time'  => '/^(final\s*time|waktu\s*final)$/i',
        'branch'      => '/^(branch|cabang)$/i',
    ];

    /**
     * Parse large FDR source file with streaming low-memory architecture.
     *
     * @param string $filePath Local absolute path (e.g. /tmp/fdr_....xlsx)
     * @param string|null $originalFilename Client original filename for context
     * @param string|null $airlineHint Airline hint detected from folder path / client
     * @return array
     */
    public function parseStreaming(string $filePath, ?string $originalFilename = null, ?string $airlineHint = null): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found on disk: {$filePath}");
        }

        $format = $this->detectFormat($filePath);
        $startMem = memory_get_usage(true);

        Log::info("FdrStreamingParser: Starting parsing", [
            'format'     => $format,
            'file_size'  => filesize($filePath),
            'filename'   => $originalFilename ?: basename($filePath),
            'start_mem'  => round($startMem / 1048576, 2) . ' MB',
        ]);

        $metaHeaders = [];
        $records = [];

        if ($format === 'XLSX') {
            [$metaHeaders, $records] = $this->parseXlsxStreaming($filePath);
        } elseif ($format === 'CSV') {
            [$metaHeaders, $records] = $this->parseCsvStreaming($filePath);
        } else {
            // OASYS HTML XLS or XML table
            [$metaHeaders, $records] = $this->parseHtmlXlsStreaming($filePath);
        }

        // Build metadata
        $meta = $this->extractMetadata($metaHeaders, $records, $originalFilename, $airlineHint);
        $meta['detected_format'] = $format;

        // Classify records into operational movements and summary rows
        $classified = $this->classifyRows($records);
        $movementRecords = $classified['movement_records'];
        $summaryRecords = $classified['summary_rows'];

        // Compute summary metrics
        $summary = $this->buildFastSummary($movementRecords, $meta);

        $endMem = memory_get_usage(true);
        Log::info("FdrStreamingParser: Parsing completed", [
            'total_rows'     => count($records),
            'movements'      => count($movementRecords),
            'summaries'      => count($summaryRecords),
            'peak_mem_mb'    => round(memory_get_peak_usage(true) / 1048576, 2),
            'mem_used_mb'    => round(($endMem - $startMem) / 1048576, 2),
        ]);

        return [
            'meta'            => $meta,
            'records'         => $records,
            'movements'       => $movementRecords,
            'summary_rows'    => $summaryRecords,
            'summary'         => $summary,
            'total_rows'      => count($records),
            'valid_rows'      => count($movementRecords),
            'invalid_rows'    => 0,
            'detected_format' => $format,
        ];
    }

    /**
     * Stream parse true .xlsx using OpenSpout without building in-memory DOM.
     */
    protected function parseXlsxStreaming(string $filePath): array
    {
        $reader = new XlsxReader();
        $reader->open($filePath);

        $metaHeaders = [];
        $records = [];
        $headerColMap = null;
        $rowIdx = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();
                $cleanCells = array_map(function ($val) {
                    if ($val instanceof \DateTimeInterface) {
                        return $val->format('Y-m-d H:i:s');
                    }
                    return $val !== null ? trim((string)$val) : '';
                }, $cells);

                // Skip completely empty rows
                if (empty(array_filter($cleanCells))) {
                    continue;
                }

                // If header hasn't been found, inspect row
                if ($headerColMap === null) {
                    $possibleMap = $this->mapHeaderRow($cleanCells);
                    if ($possibleMap !== null) {
                        $headerColMap = $possibleMap;
                        continue;
                    }

                    // Collect pre-header cells as metadata
                    $nonEmpty = array_values(array_filter($cleanCells));
                    if (!empty($nonEmpty) && count($nonEmpty) <= 3) {
                        $metaHeaders[] = implode(': ', $nonEmpty);
                    }
                    continue;
                }

                // Header found: parse movement row
                $record = $this->normalizeRow($cleanCells, $headerColMap, $rowIdx++);
                if ($record !== null) {
                    $records[] = $record;
                }
            }
            // Typically FDR has only one active sheet
            break;
        }

        $reader->close();
        return [$metaHeaders, $records];
    }

    /**
     * Stream parse CSV using OpenSpout or fallback fgetcsv.
     */
    protected function parseCsvStreaming(string $filePath): array
    {
        $metaHeaders = [];
        $records = [];
        $headerColMap = null;
        $rowIdx = 0;

        if (class_exists(CsvReader::class)) {
            $reader = new CsvReader();
            $reader->open($filePath);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map('trim', $row->toArray());
                    if (empty(array_filter($cells))) continue;

                    if ($headerColMap === null) {
                        $possibleMap = $this->mapHeaderRow($cells);
                        if ($possibleMap !== null) {
                            $headerColMap = $possibleMap;
                            continue;
                        }
                        $metaHeaders[] = implode(' ', $cells);
                        continue;
                    }

                    $rec = $this->normalizeRow($cells, $headerColMap, $rowIdx++);
                    if ($rec !== null) {
                        $records[] = $rec;
                    }
                }
                break;
            }
            $reader->close();
        } else {
            $handle = fopen($filePath, 'r');
            while (($cells = fgetcsv($handle, 4096, ',')) !== false) {
                $cells = array_map('trim', $cells);
                if (empty(array_filter($cells))) continue;

                if ($headerColMap === null) {
                    $possibleMap = $this->mapHeaderRow($cells);
                    if ($possibleMap !== null) {
                        $headerColMap = $possibleMap;
                        continue;
                    }
                    $metaHeaders[] = implode(' ', $cells);
                    continue;
                }

                $rec = $this->normalizeRow($cells, $headerColMap, $rowIdx++);
                if ($rec !== null) {
                    $records[] = $rec;
                }
            }
            fclose($handle);
        }

        return [$metaHeaders, $records];
    }

    /**
     * High-speed, streaming parser for OASYS HTML XLS files.
     * Uses small buffer window to parse <table> rows without large in-memory strings.
     */
    protected function parseHtmlXlsStreaming(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException("Could not open file: {$filePath}");
        }

        $metaHeaders = [];
        $records = [];
        $headerColMap = null;
        $rowIdx = 0;

        // Read initial header block for OASYS input/title metadata
        $lead = fread($handle, 65536);
        if (preg_match_all('/<input[^>]+type=["\']hidden["\'][^>]*>/i', $lead, $mInputs)) {
            foreach ($mInputs[0] as $input) {
                $name = '';
                $value = '';
                if (preg_match('/name=["\']([^"\']+)["\']/i', $input, $n)) $name = $n[1];
                if (preg_match('/value=["\']([^"\']*)["\']/i', $input, $v)) $value = $v[1];
                if ($name !== '') $metaHeaders[] = strtoupper($name) . ': ' . trim($value);
            }
        }

        $buffer = $lead;
        while (!feof($handle) || strlen($buffer) > 0) {
            $trPos = stripos($buffer, '<tr');
            if ($trPos === false) {
                if (feof($handle)) break;
                $buffer .= fread($handle, 65536);
                continue;
            }

            $endTrPos = stripos($buffer, '</tr>', $trPos);
            if ($endTrPos === false) {
                if (feof($handle)) break;
                $buffer .= fread($handle, 65536);
                continue;
            }

            $trHtml = substr($buffer, $trPos, ($endTrPos + 5) - $trPos);
            $buffer = substr($buffer, $endTrPos + 5);

            // Extract cells
            preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $trHtml, $mCells);
            if (empty($mCells[1])) continue;

            $cells = array_map(function ($c) {
                return trim(html_entity_decode(strip_tags($c), ENT_QUOTES, 'UTF-8'));
            }, $mCells[1]);

            if (empty(array_filter($cells))) continue;

            if ($headerColMap === null) {
                $possibleMap = $this->mapHeaderRow($cells);
                if ($possibleMap !== null) {
                    $headerColMap = $possibleMap;
                    continue;
                }
                continue;
            }

            $rec = $this->normalizeRow($cells, $headerColMap, $rowIdx++);
            if ($rec !== null) {
                $records[] = $rec;
            }
        }

        fclose($handle);
        return [$metaHeaders, $records];
    }

    /**
     * Check if row matches the FDR table header and returns column position map.
     */
    protected function mapHeaderRow(array $cells): ?array
    {
        $map = [];
        $matchedCount = 0;

        foreach ($cells as $idx => $cell) {
            $cleaned = trim((string)$cell);
            if ($cleaned === '') continue;

            foreach (self::HEADER_MAPPINGS as $field => $pattern) {
                if (!isset($map[$field]) && preg_match($pattern, $cleaned)) {
                    $map[$field] = $idx;
                    $matchedCount++;
                    break;
                }
            }
        }

        // Must match at least AIR LINE or FLIGHT NO plus one timestamp or leg
        $hasKeyColumns = (isset($map['air_line']) || isset($map['flight_no'])) &&
                         (isset($map['sibt']) || isset($map['sobt']) || isset($map['sibt_sobt']) || isset($map['leg']) || isset($map['paired_no']));

        return ($matchedCount >= 3 && $hasKeyColumns) ? $map : null;
    }

    /**
     * Normalize a raw data row into standard FDR schema.
     */
    protected function normalizeRow(array $cells, array $map, int $rowIndex): ?array
    {
        $getVal = function ($field) use ($cells, $map) {
            if (!isset($map[$field])) return null;
            $idx = $map[$field];
            return isset($cells[$idx]) ? trim((string)$cells[$idx]) : null;
        };

        $flightNo = $getVal('flight_no') ?: '';
        $airLine  = $getVal('air_line') ?: '';

        // If row is completely missing both flight_no and air_line, skip
        if (empty($flightNo) && empty($airLine)) {
            return null;
        }

        // Detect summary row
        $isSummary = (
            strcasecmp($airLine, 'PAX ALL') === 0 ||
            stripos($airLine, 'PAX ALL') !== false ||
            stripos($flightNo, 'TOTAL') !== false ||
            stripos($flightNo, 'GRAND') !== false ||
            stripos($getVal('desc') ?: '', 'PAX ALL') !== false
        );

        $rowType = $isSummary ? 'SUMMARY' : 'MOVEMENT';

        // Extract timestamps
        $sibt = $getVal('sibt');
        $sobt = $getVal('sobt');
        $sibtSobt = $getVal('sibt_sobt');
        if (empty($sibt) && empty($sobt) && !empty($sibtSobt)) {
            $parts = preg_split('/[\s\/\-_]+/', $sibtSobt);
            $sibt = $parts[0] ?? null;
            $sobt = $parts[1] ?? null;
        }

        $aibt = $getVal('aibt');
        $aobt = $getVal('aobt');
        $aibtAobt = $getVal('aibt_aobt');
        if (empty($aibt) && empty($aobt) && !empty($aibtAobt)) {
            $parts = preg_split('/[\s\/\-_]+/', $aibtAobt);
            $aibt = $parts[0] ?? null;
            $aobt = $parts[1] ?? null;
        }

        $leg = strtoupper($getVal('leg') ?: '');
        if ($leg === 'A' || str_starts_with($leg, 'ARR')) $leg = 'ARRIVAL';
        elseif ($leg === 'D' || str_starts_with($leg, 'DEP')) $leg = 'DEPARTURE';

        $cap   = (int) filter_var($getVal('cap'), FILTER_SANITIZE_NUMBER_INT);
        $load  = (int) filter_var($getVal('load'), FILTER_SANITIZE_NUMBER_INT);
        $adult = (int) filter_var($getVal('adult'), FILTER_SANITIZE_NUMBER_INT);
        $child = (int) filter_var($getVal('child'), FILTER_SANITIZE_NUMBER_INT);
        $inf   = (int) filter_var($getVal('infant'), FILTER_SANITIZE_NUMBER_INT);

        // Calculate load factor
        $loadFactor = ($cap > 0) ? round(($load / $cap) * 100, 2) : 0.0;

        return [
            'row_index'    => $rowIndex,
            'row_type'     => $rowType,
            'air_line'     => $airLine ?: 'N/A',
            'flight_no'    => $flightNo ?: 'N/A',
            'paired_no'    => $getVal('paired_no') ?: '',
            'desc'         => $getVal('desc') ?: '',
            'sibt'         => $sibt,
            'sobt'         => $sobt,
            'aibt'         => $aibt,
            'aobt'         => $aobt,
            'leg'          => $leg ?: 'ALL',
            'city_1'       => strtoupper($getVal('city_1') ?: ''),
            'city_2'       => strtoupper($getVal('city_2') ?: ''),
            'reg_no'       => strtoupper($getVal('reg_no') ?: ''),
            'cap'          => $cap,
            'load'         => $load,
            'adult'        => $adult,
            'child'        => $child,
            'infant'       => $inf,
            'transit'      => (int) filter_var($getVal('transit'), FILTER_SANITIZE_NUMBER_INT),
            'transfer'     => (int) filter_var($getVal('transfer'), FILTER_SANITIZE_NUMBER_INT),
            'cargo_kg'     => (float) filter_var($getVal('cargo_kg'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'baggage_kg'   => (float) filter_var($getVal('baggage_kg'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'pos_kg'       => (float) filter_var($getVal('pos_kg'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'stand'        => $getVal('stand') ?: '',
            'runway'       => $getVal('runway') ?: '',
            'branch'       => $getVal('branch') ?: '',
            'load_factor'  => $loadFactor,
        ];
    }

    /**
     * Separate records into flight movements and summary rows.
     */
    public function classifyRows(array $records): array
    {
        $movements = [];
        $summaries = [];

        foreach ($records as $r) {
            if ($r['row_type'] === 'SUMMARY') {
                $summaries[] = $r;
            } else {
                $movements[] = $r;
            }
        }

        return [
            'movement_records' => $movements,
            'summary_rows'     => $summaries,
        ];
    }

    /**
     * Build lightweight summary metrics.
     */
    protected function buildFastSummary(array $movements, array $meta): array
    {
        $totalMovements = count($movements);
        $totalLoad = 0;
        $totalCap = 0;
        $arrCount = 0;
        $depCount = 0;
        $airlines = [];

        foreach ($movements as $m) {
            $totalLoad += $m['load'] ?? 0;
            $totalCap  += $m['cap'] ?? 0;

            if (($m['leg'] ?? '') === 'ARRIVAL') {
                $arrCount++;
            } elseif (($m['leg'] ?? '') === 'DEPARTURE') {
                $depCount++;
            }

            $al = $m['air_line'] ?? '';
            if ($al && $al !== 'N/A') {
                $airlines[$al] = ($airlines[$al] ?? 0) + 1;
            }
        }

        arsort($airlines);
        $topAirline = !empty($airlines) ? array_key_first($airlines) : ($meta['operator'] ?? 'ALL AIRLINE');

        return [
            'total_movements'    => $totalMovements,
            'total_passengers'   => $totalLoad,
            'total_seats'        => $totalCap,
            'arrival_count'      => $arrCount,
            'departure_count'    => $depCount,
            'average_load_factor'=> $totalCap > 0 ? round(($totalLoad / $totalCap) * 100, 2) : 0,
            'top_airline'        => $topAirline,
            'unique_airlines'    => count($airlines),
        ];
    }

    /**
     * Extract analytical metadata from header lines and movement rows.
     */
    protected function extractMetadata(array $headers, array $records, ?string $filename = null, ?string $hint = null): array
    {
        $meta = [
            'airport'       => 'CGK',
            'airport_name'  => 'Soekarno-Hatta International Airport',
            'operator'      => $hint ?: 'ALL AIRLINE',
            'period_label'  => '',
            'date_start'    => '',
            'date_end'      => '',
            'direction'     => 'ALL',
            'realization'   => 'YES',
            'filename'      => $filename ?: 'FDR_REPORT',
        ];

        // Parse key-value headers
        foreach ($headers as $h) {
            if (stripos($h, 'BRANCH_CODE:') !== false || stripos($h, 'BRANCH:') !== false) {
                if (preg_match('/(CGK|HLP|SUB|DPS|KNO|UPG|BDO|JOG|SRG|YIA|BPN|BDJ|MDC)/i', $h, $m)) {
                    $meta['airport'] = strtoupper($m[1]);
                }
            }
            if (stripos($h, 'TRANSACTIONS_DATEFDR:') !== false || stripos($h, 'DATE:') !== false) {
                if (preg_match('/(\d{1,2}[-\/]\d{1,2}[-\/]\d{4})\s*(?:s\/?d|to|-)\s*(\d{1,2}[-\/]\d{1,2}[-\/]\d{4})/i', $h, $m)) {
                    $meta['date_start'] = $m[1];
                    $meta['date_end']   = $m[2];
                    $meta['period_label'] = "{$m[1]} s/d {$m[2]}";
                }
            }
        }

        // Infer from filename if folder hierarchy pattern is present:
        // Example: "Flight Daily Report/(01-01-2026) - (30-06-2026)/Realization/CGK GA FDR .xlsx"
        if (!empty($filename)) {
            if (preg_match('/\((\d{2}-\d{2}-\d{4})\)\s*-\s*\((\d{2}-\d{2}-\d{4})\)/', $filename, $mDates)) {
                $meta['date_start'] = $mDates[1];
                $meta['date_end']   = $mDates[2];
                $meta['period_label'] = "{$mDates[1]} s/d {$mDates[2]}";
            }
            if (preg_match('/\b(GA|GARUDA)\b/i', $filename)) {
                $meta['operator'] = 'Garuda Indonesia';
            } elseif (preg_match('/\b(LION|JT)\b/i', $filename)) {
                $meta['operator'] = 'Lion Air';
            } elseif (preg_match('/\b(CITILINK|QG|CTV)\b/i', $filename)) {
                $meta['operator'] = 'Citilink';
            }
            if (preg_match('/\b(CGK|SUB|DPS|KNO|UPG|BDO)\b/i', $filename, $mAir)) {
                $meta['airport'] = strtoupper($mAir[1]);
            }
        }

        return $meta;
    }

    /**
     * Detect format of the file.
     */
    public function detectFormat(string $filePath): string
    {
        $handle = @fopen($filePath, 'rb');
        if (!$handle) return 'XLSX';

        $header = fread($handle, 2048);
        fclose($handle);

        if (strncmp($header, "PK\x03\x04", 4) === 0) {
            return 'XLSX';
        }
        if (stripos($header, '<html') !== false || stripos($header, '<table') !== false || stripos($header, 'oasys') !== false) {
            return 'HTML_XLS';
        }
        if (strncmp($header, "\xD0\xCF\x11\xE0", 4) === 0) {
            return 'XLS';
        }
        if (str_contains($header, ',') || str_contains($header, ';')) {
            return 'CSV';
        }

        return 'XLSX';
    }
}
