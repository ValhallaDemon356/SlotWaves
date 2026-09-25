<?php

namespace App\Services\FlightDailyReport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class FlightDailyReportParser
{
    /**
     * Strict raw FDR headers mapping regex.
     */
    protected const HEADER_MAPPINGS = [
        'air_line'    => '/^(air\s*line|airline|maskapai|operator)$/i',
        'flight_no'   => '/^(flight\s*no\.?|flt\s*no\.?|flight\s*number|no\s*penerbangan)$/i',
        'paired_no'   => '/^(paired\s*no\.?|pair\s*no\.?|paired\s*flight|no\s*pasangan)$/i',
        'desc'        => '/^(desc|description|keterangan)$/i',
        'sibt'        => '/^(sibt|sched(uled)?\s*in(\s*block)?(\s*time)?|sta\b)$/i',
        'sobt'        => '/^(sobt|sched(uled)?\s*off(\s*block)?(\s*time)?|std\b)$/i',
        'sibt_sobt'   => '/^(sibt\s*[\/\-]\s*sobt|sched(uled)?\s*block(\s*time)?)$/i',
        'aibt'        => '/^(aibt|actual\s*in(\s*block)?(\s*time)?|ata\b)$/i',
        'aobt'        => '/^(aobt|actual\s*off(\s*block)?(\s*time)?|atd\b)$/i',
        'aibt_aobt'   => '/^(aibt\s*[\/\-]\s*aobt|actual\s*block(\s*time)?)$/i',
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
        'transfer'    => '/^(transfer)$/i',
        'divert'      => '/^(divert|diverted)$/i',
        'miss'        => '/^(miss|missed|batal|cancel)$/i',
        'crw'         => '/^(crw|crew|awak)$/i',
        'ex_crw'      => '/^(ex\.?\s*crw|extra\s*crew|awak\s*ekstra)$/i',
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
     * Indonesian major domestic airports for traffic type detection.
     */
    protected const DOMESTIC_AIRPORTS = [
        'CGK', 'HLP', 'BDO', 'SUB', 'DPS', 'KNO', 'UPG', 'JOG', 'SRG', 'YIA',
        'BPN', 'BDJ', 'MDC', 'LOP', 'PLM', 'BTJ', 'PKU', 'PDG', 'DJB', 'TKG',
        'PNK', 'TRK', 'KOE', 'AMQ', 'DJJ', 'TIM', 'BIK', 'MKQ', 'SOQ', 'MKW',
        'GTO', 'KDI', 'TTE', 'TJQ', 'PGK', 'TNJ', 'BTH', 'DTB', 'BWX', 'MLG'
    ];

    /**
     * Detect exact format of FDR source file.
     */
    public function detectFormat(string $filePath, string $content): string
    {
        if ($this->isHtmlTable($content)) {
            return 'OASYS HTML XLS';
        }

        if (strncmp($content, "\xD0\xCF\x11\xE0", 4) === 0) {
            return 'NATIVE XLS';
        }

        if (strncmp($content, "PK\x03\x04", 4) === 0) {
            return 'XLSX';
        }

        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'csv') {
            return 'CSV';
        }

        return 'OASYS HTML XLS';
    }

    /**
     * Parse raw FDR workbook content or path.
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        @ini_set('pcre.backtrack_limit', '10000000');
        @ini_set('memory_limit', '512M');

        $content = file_get_contents($filePath);
        $rawRows = [];
        $metaHeaders = [];
        $detectedFormat = $this->detectFormat($filePath, $content);

        if ($this->isHtmlTable($content)) {
            [$metaHeaders, $rawRows] = $this->parseHtmlTable($content);
        } elseif (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'csv') {
            [$metaHeaders, $rawRows] = $this->parseCsv($filePath);
        } else {
            [$metaHeaders, $rawRows] = $this->parseSpreadsheet($filePath);
        }

        // Extract metadata from headers and raw rows
        $meta = $this->extractMetadata($metaHeaders, $rawRows);
        $meta['detected_format'] = $detectedFormat;

        // Find header row and map columns
        $columnMap = $this->identifyColumns($rawRows);
        $records = $this->normalizeRecords($rawRows, $columnMap, $meta);

        // Derive multi-day / multi-month period from actual records if not explicit
        $meta = $this->refineMetadataWithRecords($meta, $records);

        return [
            'meta'            => $meta,
            'records'         => $records,
            'summary'         => $this->buildFastSummary($records, $meta),
            'detected_format' => $detectedFormat,
        ];
    }

    /**
     * Detect if file is HTML table format (typical OASYS .xls export).
     */
    public function isHtmlTable(string $content): bool
    {
        // OLE2 Binary (.xls) and OpenXML (.xlsx) containers are never HTML tables
        if (strncmp($content, "\xD0\xCF\x11\xE0", 4) === 0 || strncmp($content, "PK\x03\x04", 4) === 0) {
            return false;
        }

        $head = substr($content, 0, 16384);
        $lower = strtolower($head);
        return (strpos($lower, '<html') !== false
            || strpos($lower, '<table') !== false
            || strpos($lower, '<center') !== false
            || strpos($lower, '<title') !== false
            || strpos($lower, '<td') !== false
            || strpos($lower, '<tr') !== false
            || strpos($lower, 'oasys') !== false);
    }

    /**
     * Parse HTML Table format.
     */
    protected function parseHtmlTable(string $html): array
    {
        $metaHeaders = [];
        $grid = [];

        // Extract hidden inputs completely (name and value with quotes or unquoted)
        if (preg_match_all('/<input[^>]+>/i', $html, $inputMatches)) {
            foreach ($inputMatches[0] as $input) {
                $name = '';
                $value = '';
                if (preg_match('/name=["\']([^"\']*)["\']/i', $input, $n)) {
                    $name = $n[1];
                } elseif (preg_match('/name=([^"\'>\s]+)/i', $input, $n)) {
                    $name = $n[1];
                }
                if (preg_match('/value=["\']([^"\']*)["\']/i', $input, $v)) {
                    $value = $v[1];
                } elseif (preg_match('/value=([^"\'>\s]+)/i', $input, $v)) {
                    $value = $v[1];
                }
                if ($name !== '') {
                    $metaHeaders[] = strtoupper($name) . ': ' . trim($value);
                }
            }
        }

        // Extract any metadata from <center>, <title>, <head>
        if (preg_match_all('/<center[^>]*>(.*?)<\/center>/is', $html, $centerMatches)) {
            foreach ($centerMatches[1] as $c) {
                $metaHeaders[] = trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $c)));
            }
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatch)) {
            $metaHeaders[] = trim(strip_tags($titleMatch[1]));
        }

        // Select the best table or parse all <tr> tags
        $tableHtml = $html;
        if (preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $html, $tableMatches)) {
            $bestTable = '';
            $bestScore = 0;
            foreach ($tableMatches[1] as $tbl) {
                $score = 0;
                if (stripos($tbl, 'AIR LINE') !== false || stripos($tbl, 'FLIGHT NO') !== false) {
                    $score += 500;
                }
                $score += substr_count(strtolower($tbl), '<tr');
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestTable = $tbl;
                }
            }
            if ($bestScore > 0) {
                $tableHtml = $bestTable;
            }
        }

        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableHtml, $trMatches);
        if (empty($trMatches[1])) {
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $trMatches);
            if (empty($trMatches[1])) {
                return [$metaHeaders, []];
            }
        }

        $rIdx = 0;
        foreach ($trMatches[1] as $trHtml) {
            preg_match_all('/<(td|th)([^>]*)>(.*?)<\/\1>/is', $trHtml, $cellMatches, PREG_SET_ORDER);
            if (empty($cellMatches)) {
                continue;
            }

            $cIdx = 0;
            foreach ($cellMatches as $cell) {
                while (isset($grid[$rIdx][$cIdx])) {
                    $cIdx++;
                }

                $attrs = $cell[2];
                $inner = $cell[3];
                $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $text = preg_replace('/\s+/', ' ', $text);

                $colspan = 1;
                if (preg_match('/colspan\s*=\s*["\']?(\d+)/i', $attrs, $m)) {
                    $colspan = max(1, (int)$m[1]);
                }

                $rowspan = 1;
                if (preg_match('/rowspan\s*=\s*["\']?(\d+)/i', $attrs, $m)) {
                    $rowspan = max(1, (int)$m[1]);
                }

                for ($r = 0; $r < $rowspan; $r++) {
                    for ($c = 0; $c < $colspan; $c++) {
                        $grid[$rIdx + $r][$cIdx + $c] = $text;
                    }
                }
                $cIdx += $colspan;
            }
            $rIdx++;
        }

        // Normalize grid into indexed rows
        ksort($grid);
        $rows = [];
        foreach ($grid as $row) {
            ksort($row);
            $rows[] = array_values($row);
        }

        return [$metaHeaders, $rows];
    }

    /**
     * Parse CSV format.
     */
    protected function parseCsv(string $filePath): array
    {
        $metaHeaders = [];
        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [[], []];
        }

        while (($data = fgetcsv($handle, 10000, ',')) !== false) {
            // Check for single column metadata lines
            if (count($data) === 1 && !empty(trim($data[0]))) {
                $metaHeaders[] = trim($data[0]);
                continue;
            }
            // Strip BOM or whitespace
            $cleanRow = array_map(function ($val) {
                return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$val));
            }, $data);

            if (!empty(array_filter($cleanRow))) {
                $rows[] = $cleanRow;
            }
        }
        fclose($handle);

        return [$metaHeaders, $rows];
    }

    /**
     * Parse real Excel (.xlsx / binary .xls) via PhpSpreadsheet.
     */
    protected function parseSpreadsheet(string $filePath): array
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);

            $metaHeaders = [];
            $rows = [];
            foreach ($data as $r) {
                $cleanRow = array_map(function ($val) {
                    return $val !== null ? trim((string)$val) : '';
                }, $r);

                if (!empty(array_filter($cleanRow))) {
                    $rows[] = array_values($cleanRow);
                }
            }

            return [$metaHeaders, $rows];
        } catch (\Throwable $e) {
            // Fallback: try parsing as CSV/HTML
            $content = file_get_contents($filePath);
            if ($this->isHtmlTable($content)) {
                return $this->parseHtmlTable($content);
            }
            throw new \RuntimeException("Unable to read workbook: " . $e->getMessage());
        }
    }

    /**
     * Identify column index positions for raw FDR fields.
     */
    public function identifyColumns(array $rows): array
    {
        $bestMap = [];
        $maxMatched = 0;
        $headerRowIdx = -1;

        // Scan first 25 rows (or total rows) for the table header row
        $limit = min(25, count($rows));
        for ($i = 0; $i < $limit; $i++) {
            $row = $rows[$i];
            // Skip rows that are mostly empty or have few data columns
            $nonEmpty = array_filter($row, fn($c) => trim((string)$c) !== '');
            if (count($nonEmpty) < 2) {
                continue;
            }

            $currentMap = [];
            $matchedCount = 0;
            foreach ($row as $colIdx => $cell) {
                $cellTrimmed = trim((string)$cell);
                if ($cellTrimmed === '') continue;
                foreach (self::HEADER_MAPPINGS as $field => $regex) {
                    if (preg_match($regex, $cellTrimmed)) {
                        $currentMap[$field] = $colIdx;
                        $matchedCount++;
                        break;
                    }
                }
            }

            // Require at least flight_no or air_line or leg
            if ($matchedCount > $maxMatched && (isset($currentMap['flight_no']) || isset($currentMap['air_line']) || isset($currentMap['leg']))) {
                $maxMatched = $matchedCount;
                $bestMap = $currentMap;
                $headerRowIdx = $i;
            }
        }

        return [
            'header_row_index' => $headerRowIdx,
            'mapping'          => $bestMap,
        ];
    }

    /**
     * Extract metadata from text headers and workbook rows.
     */
    public function extractMetadata(array $metaHeaders, array $rows): array
    {
        $metaMap = [];
        foreach ($metaHeaders as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $metaMap[strtoupper(trim($k))] = trim($v);
            }
        }

        $metaText = implode("\n", $metaHeaders);
        for ($i = 0; $i < min(5, count($rows)); $i++) {
            $metaText .= "\n" . implode(' ', $rows[$i]);
        }

        // 1. Airport Code (BRANCH_CODE or header inspection)
        $airportCode = 'CGK';
        $airportName = 'Soekarno-Hatta (Jakarta)';
        if (!empty($metaMap['BRANCH_CODE'])) {
            $airportCode = strtoupper($metaMap['BRANCH_CODE']);
        } elseif (preg_match('/\b([A-Z]{3})\b/', $metaText, $m)) {
            foreach (self::DOMESTIC_AIRPORTS as $ap) {
                if (stripos($metaText, "($ap)") !== false || stripos($metaText, " - $ap") !== false || stripos($metaText, " $ap ") !== false) {
                    $airportCode = $ap;
                    break;
                }
            }
        }
        if (preg_match('/(TANGERANG|SOEKARNO HATTA|CGK)/i', $metaText)) {
            $airportCode = 'CGK';
            $airportName = 'Soekarno-Hatta (Jakarta)';
        } elseif (preg_match('/(HUSEIN|BANDUNG|BDO)/i', $metaText)) {
            $airportCode = 'BDO';
            $airportName = 'Husein Sastranegara (Bandung)';
        } elseif (preg_match('/(SULTAN ISKANDAR MUDA|BANDA ACEH|BTJ)/i', $metaText)) {
            $airportCode = 'BTJ';
            $airportName = 'Sultan Iskandar Muda (Banda Aceh)';
        } elseif (preg_match('/(JUANDA|SURABAYA|SUB)/i', $metaText)) {
            $airportCode = 'SUB';
            $airportName = 'Juanda (Surabaya)';
        } elseif (preg_match('/(NGURAH RAI|BALI|DENPASAR|DPS)/i', $metaText)) {
            $airportCode = 'DPS';
            $airportName = 'I Gusti Ngurah Rai (Bali)';
        }

        // 2. Operator
        $operator = 'ALL AIRLINE';
        if (!empty($metaMap['OPERATOR'])) {
            $operator = strtoupper($metaMap['OPERATOR']);
        } elseif (preg_match('/(operator|airline|maskapai)\s*[:=]\s*([^\n\r<]+)/i', $metaText, $opMatch)) {
            $operator = strtoupper(trim($opMatch[2]));
        }

        // 3. Period / Dates (transactions_dateFDR or header)
        $startDate = null;
        $endDate = null;
        $dateSource = !empty($metaMap['TRANSACTIONS_DATEFDR']) ? $metaMap['TRANSACTIONS_DATEFDR'] : $metaText;
        if (preg_match('/(?:tanggal|period|periode)?\s*[:=]?\s*(\d{4}[-\/]\d{2}[-\/]\d{2}|\d{2}[-\/]\d{2}[-\/]\d{4})\s*(?:s\/?d|to|-)\s*(\d{4}[-\/]\d{2}[-\/]\d{2}|\d{2}[-\/]\d{2}[-\/]\d{4})/i', $dateSource, $pMatches)) {
            $startDate = $this->standardizeDate($pMatches[1]);
            $endDate = $this->standardizeDate($pMatches[2]);
        } elseif (preg_match('/(\d{4}[-\/]\d{2}[-\/]\d{2})/i', $dateSource, $singleDate)) {
            $startDate = $this->standardizeDate($singleDate[1]);
            $endDate = $startDate;
        }

        // 4. Realization Status
        $realization = 'YES';
        if (!empty($metaMap['REAL'])) {
            $rVal = strtoupper($metaMap['REAL']);
            $realization = in_array($rVal, ['NO', 'TIDAK', 'FALSE', '0'], true) ? 'NO' : 'YES';
        } elseif (preg_match('/(realisasi|realization)\s*[:=]\s*(NO|TIDAK|FALSE)/i', $metaText)) {
            $realization = 'NO';
        }

        // 5. Route Type (CATEGORY_CODE or default ALL)
        $routeType = 'ALL';
        if (!empty($metaMap['CATEGORY_CODE'])) {
            $cat = strtoupper($metaMap['CATEGORY_CODE']);
            if (str_contains($cat, 'DOM')) $routeType = 'DOMESTIC';
            elseif (str_contains($cat, 'INT')) $routeType = 'INTERNATIONAL';
        }

        // 6. Direction / Leg (Leg input or default ALL)
        $direction = 'ALL';
        if (!empty($metaMap['LEG'])) {
            $lg = strtoupper($metaMap['LEG']);
            if (str_starts_with($lg, 'A')) $direction = 'ARRIVAL';
            elseif (str_starts_with($lg, 'D')) $direction = 'DEPARTURE';
        }

        // 7. Suffix
        $suffix = !empty($metaMap['SUFFIX']) ? strtoupper($metaMap['SUFFIX']) : '';

        // 8. Data Type
        $dataType = 'OPERATIONAL DATA';
        if (preg_match('/(report\s*data|laporan\s*data)/i', $metaText)) {
            $dataType = 'REPORT DATA';
        }

        return [
            'airport'       => $airportCode,
            'airport_code'  => $airportCode,
            'airport_name'  => $airportName,
            'operator'      => $operator,
            'date_start'    => $startDate ?: '2026-08-01',
            'date_end'      => $endDate ?: '2026-08-31',
            'period_start'  => $startDate ?: '2026-08-01',
            'period_end'    => $endDate ?: '2026-08-31',
            'period_label'  => ($startDate && $endDate) ? "{$startDate} s/d {$endDate}" : '01-08-2026 s/d 31-08-2026',
            'direction'     => $direction,
            'leg'           => $direction,
            'route_type'    => $routeType,
            'suffix'        => $suffix,
            'realization'   => $realization,
            'data_type'     => $dataType,
            'source_system' => 'OASYS',
            'report_name'   => 'FLIGHT DAILY REPORT',
        ];
    }

    /**
     * Refine metadata (dates, operator, airport) from parsed rows if needed.
     */
    protected function refineMetadataWithRecords(array $meta, array $records): array
    {
        if (empty($records)) {
            return $meta;
        }

        $dates = [];
        $airlines = [];
        $hasActuals = false;

        foreach ($records as $r) {
            if (!empty($r['flight_date']) && $r['flight_date'] !== 'N/A') {
                $dates[] = $r['flight_date'];
            }
            if (!empty($r['air_line']) && $r['air_line'] !== 'N/A') {
                $airlines[$r['air_line']] = true;
            }
            if (($r['aibt'] !== 'N/A' && !empty($r['aibt'])) || ($r['aobt'] !== 'N/A' && !empty($r['aobt']))) {
                $hasActuals = true;
            }
        }

        if (!empty($dates)) {
            sort($dates);
            $minDate = reset($dates);
            $maxDate = end($dates);
            $meta['date_start'] = $minDate;
            $meta['date_end'] = $maxDate;
            $meta['period_start'] = $minDate;
            $meta['period_end'] = $maxDate;
            $meta['period_label'] = "{$minDate} s/d {$maxDate}";
        }

        if (!$hasActuals && $meta['realization'] === 'YES') {
            $meta['realization'] = 'NO';
        }

        return $meta;
    }

    /**
     * Normalize raw rows into structured FDR records with strict guardrails.
     */
    public function normalizeRecords(array $rows, array $columnInfo, array $meta): array
    {
        $headerIdx = $columnInfo['header_row_index'];
        $map = $columnInfo['mapping'];
        $records = [];

        if ($headerIdx < 0 || empty($map)) {
            return [];
        }

        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Skip empty rows or summary/total footer rows
            $firstCell = trim((string)($row[0] ?? ''));
            $rowText = implode(' ', $row);
            if (empty(array_filter($row)) || preg_match('/^(total|grand\s*total|jumlah|subtotal|summary)/i', $firstCell) || preg_match('/^(grand\s*total|halaman)/i', $rowText)) {
                continue;
            }

            // Extract raw fields using map
            $get = function (string $key, $default = 'N/A') use ($row, $map) {
                if (isset($map[$key]) && isset($row[$map[$key]])) {
                    $val = trim((string)$row[$map[$key]]);
                    return ($val !== '' && $val !== '-' && $val !== 'NULL') ? $val : $default;
                }
                return $default;
            };

            $getNumeric = function (string $key, int $default = 0) use ($row, $map) {
                if (isset($map[$key]) && isset($row[$map[$key]])) {
                    $val = trim((string)$row[$map[$key]]);
                    $val = preg_replace('/[^0-9\.\-]/', '', $val);
                    return is_numeric($val) ? (int)$val : $default;
                }
                return $default;
            };

            $getFloat = function (string $key, float $default = 0.0) use ($row, $map) {
                if (isset($map[$key]) && isset($row[$map[$key]])) {
                    $val = trim((string)$row[$map[$key]]);
                    $val = preg_replace('/[^0-9\.\-]/', '', $val);
                    return is_numeric($val) ? (float)$val : $default;
                }
                return $default;
            };

            $airLine   = $get('air_line', 'N/A');
            $flightNo  = $get('flight_no', 'N/A');
            $pairedNo  = $get('paired_no', 'N/A');
            $desc      = $get('desc', 'N/A');
            $sibt      = $get('sibt', 'N/A');
            $sobt      = $get('sobt', 'N/A');
            $aibt      = $get('aibt', 'N/A');
            $aobt      = $get('aobt', 'N/A');
            $rawLeg    = $get('leg', 'N/A');
            $legUpper  = strtoupper(trim($rawLeg));
            $city1     = strtoupper($get('city_1', 'N/A'));
            $city2     = strtoupper($get('city_2', 'N/A'));
            $mtow      = $get('mtow', 'N/A');
            $regNo     = strtoupper($get('reg_no', 'N/A'));
            $stand     = strtoupper($get('stand', 'N/A'));
            $runway    = strtoupper($get('runway', 'N/A'));
            $final     = $get('final', 'N/A');
            $finalTime = $get('final_time', 'N/A');
            $branch    = $get('branch', 'N/A');

            // Handle combined SIBT/SOBT column if separate columns were missing
            if ($sibt === 'N/A' && $sobt === 'N/A' && isset($map['sibt_sobt'])) {
                $blockVal = $get('sibt_sobt', 'N/A');
                if (str_starts_with($legUpper, 'D')) {
                    $sobt = $blockVal;
                } else {
                    $sibt = $blockVal;
                }
            }

            // Handle combined AIBT/AOBT column if separate columns were missing
            if ($aibt === 'N/A' && $aobt === 'N/A' && isset($map['aibt_aobt'])) {
                $blockVal = $get('aibt_aobt', 'N/A');
                if (str_starts_with($legUpper, 'D')) {
                    $aobt = $blockVal;
                } else {
                    $aibt = $blockVal;
                }
            }

            // If flight_no and air_line are both N/A, skip row
            if ($flightNo === 'N/A' && $airLine === 'N/A') {
                continue;
            }

            // Extract Flight Suffix if present (e.g. GA120A => base GA120, suffix A)
            $flightNoBase = $flightNo;
            $flightSuffix = '';
            if ($flightNo !== 'N/A' && preg_match('/^([A-Z0-9]+?)([A-Z])$/i', $flightNo, $suffixMatch)) {
                if (preg_match('/\d/', $suffixMatch[1])) {
                    $flightNoBase = $suffixMatch[1];
                    $flightSuffix = strtoupper($suffixMatch[2]);
                }
            }

            // Capacity & Load Guardrails
            $cap  = $getNumeric('cap', 0);
            $load = $getNumeric('load', 0);

            // Passengers
            $adult    = $getNumeric('adult', 0);
            $child    = $getNumeric('child', 0);
            $infant   = $getNumeric('infant', 0);
            $transit  = $getNumeric('transit', 0);
            $transfer = $getNumeric('transfer', 0);

            // If load is 0 but adult+child is present, calculate load
            if ($load === 0 && ($adult > 0 || $child > 0)) {
                $load = $adult + $child;
            }

            // Load Factor Guardrail: Missing or CAP. = 0 strictly returns 'N/A'
            $loadFactor = 'N/A';
            if ($cap > 0) {
                $calcLf = ($load / $cap) * 100.0;
                $loadFactor = round($calcLf, 1);
            }

            // Irregularities
            $divert = $getNumeric('divert', 0);
            $miss   = $getNumeric('miss', 0);

            // Crew
            $crw   = $getNumeric('crw', 0);
            $exCrw = $getNumeric('ex_crw', 0);

            // Cargo, Baggage, Pos
            $cargoKg   = $getFloat('cargo_kg', 0.0);
            $baggageKg = $getFloat('baggage_kg', 0.0);
            $posKg     = $getFloat('pos_kg', 0.0);

            // Determine Leg Direction & Sched Type
            $direction = 'ARRIVAL';
            $schedType = 'SCHEDULED';

            if (str_starts_with($legUpper, 'D') || ($sobt !== 'N/A' && $sibt === 'N/A') || ($aobt !== 'N/A' && $aibt === 'N/A')) {
                $direction = 'DEPARTURE';
            } elseif (str_starts_with($legUpper, 'A') || ($sibt !== 'N/A' && $sobt === 'N/A') || ($aibt !== 'N/A' && $aobt === 'N/A')) {
                $direction = 'ARRIVAL';
            } else {
                if ($city1 === $meta['airport'] && $city2 !== $meta['airport']) {
                    $direction = 'DEPARTURE';
                }
            }

            if (str_contains($legUpper, 'UNSCHED') || str_contains($legUpper, 'TIDAK')) {
                $schedType = 'UNSCHEDULED';
            } else {
                $schedType = 'SCHEDULED';
            }

            // Standardize LEG representation: A SCHED / D SCHED / A UNSCHED / D UNSCHED
            $normalizedLeg = ($direction === 'ARRIVAL' ? 'A ' : 'D ') . ($schedType === 'SCHEDULED' ? 'SCHED' : 'UNSCHED');

            // Extract Flight Date & Hour (0-23)
            $flightDate = $meta['period_start'];
            $timeString = ($direction === 'ARRIVAL')
                ? ($aibt !== 'N/A' ? $aibt : $sibt)
                : ($aobt !== 'N/A' ? $aobt : $sobt);

            $hour = 12; // default midday fallback
            if ($timeString !== 'N/A') {
                if (preg_match('/(\d{4}[-\/]\d{2}[-\/]\d{2})/', $timeString, $dm)) {
                    $flightDate = $this->standardizeDate($dm[1]);
                }
                if (preg_match('/(\d{1,2}):(\d{2})/', $timeString, $tm)) {
                    $hour = (int)$tm[1];
                    if ($hour >= 24) $hour = 23;
                }
            }

            // Delay in minutes
            $delayMinutes = 0;
            $schedTime = ($direction === 'ARRIVAL') ? $sibt : $sobt;
            $actTime   = ($direction === 'ARRIVAL') ? $aibt : $aobt;
            if ($schedTime !== 'N/A' && $actTime !== 'N/A') {
                $delayMinutes = $this->calculateTimeDifferenceMinutes($schedTime, $actTime);
            }

            // Irregularity Flag: Divert > 0, Miss > 0, or Unscheduled
            $isIrregular = ($divert > 0 || $miss > 0 || $schedType === 'UNSCHEDULED');

            // Traffic: DOMESTIC vs INTERNATIONAL
            $traffic = 'DOMESTIC';
            $otherCity = ($direction === 'ARRIVAL') ? $city1 : $city2;
            if ($otherCity !== 'N/A' && !in_array($otherCity, self::DOMESTIC_AIRPORTS)) {
                $traffic = 'INTERNATIONAL';
            }

            // Stand and Runway fallback cleanups
            if ($stand === '-' || $stand === '') $stand = 'N/A';
            if ($runway === '-' || $runway === '') $runway = 'N/A';

            $records[] = [
                'index'            => count($records) + 1,
                'air_line'         => $airLine,
                'flight_no'        => $flightNo,
                'flight_no_base'   => $flightNoBase,
                'flight_suffix'    => $flightSuffix,
                'paired_no'        => $pairedNo,
                'desc'             => $desc,
                'sibt'             => $sibt,
                'sobt'             => $sobt,
                'aibt'             => $aibt,
                'aobt'             => $aobt,
                'leg'              => $normalizedLeg,
                'raw_leg'          => $rawLeg,
                'direction'        => $direction,
                'sched_type'       => $schedType,
                'is_scheduled'     => ($schedType === 'SCHEDULED'),
                'city_1'           => $city1,
                'city_2'           => $city2,
                'route'            => ($city1 !== 'N/A' && $city2 !== 'N/A') ? "{$city1} → {$city2}" : 'N/A',
                'traffic'          => $traffic,
                'route_type'       => $traffic,
                'mtow'             => $mtow,
                'reg_no'           => $regNo,
                'cap'              => $cap,
                'load'             => $load,
                'load_factor'      => $loadFactor,
                'adult'            => $adult,
                'child'            => $child,
                'infant'           => $infant,
                'transit'          => $transit,
                'transfer'         => $transfer,
                'divert'           => $divert,
                'miss'             => $miss,
                'crw'              => $crw,
                'ex_crw'           => $exCrw,
                'cargo_kg'         => $cargoKg,
                'baggage_kg'       => $baggageKg,
                'pos_kg'           => $posKg,
                'stand'            => $stand,
                'runway'           => $runway,
                'final'            => $final,
                'final_time'       => $finalTime,
                'branch'           => $branch,
                'flight_date'      => $flightDate,
                'hour'             => $hour,
                'delay_minutes'    => $delayMinutes,
                'is_irregular'     => $isIrregular,
                'is_realized'      => ($actTime !== 'N/A'),
            ];
        }

        return $records;
    }

    /**
     * Standardize date into Y-m-d.
     */
    protected function standardizeDate(string $rawDate): string
    {
        try {
            $rawDate = trim(str_replace('/', '-', $rawDate));
            $carbon = Carbon::parse($rawDate);
            return $carbon->format('Y-m-d');
        } catch (\Throwable $e) {
            return date('Y-08-01');
        }
    }

    /**
     * Calculate difference in minutes between scheduled and actual time.
     */
    protected function calculateTimeDifferenceMinutes(string $sched, string $act): int
    {
        try {
            preg_match('/(\d{1,2}):(\d{2})/', $sched, $sm);
            preg_match('/(\d{1,2}):(\d{2})/', $act, $am);
            if (!empty($sm) && !empty($am)) {
                $schedMins = ((int)$sm[1] * 60) + (int)$sm[2];
                $actMins   = ((int)$am[1] * 60) + (int)$am[2];
                return $actMins - $schedMins;
            }
        } catch (\Throwable $e) {}
        return 0;
    }

    /**
     * Build fast summary for rapid verification.
     */
    protected function buildFastSummary(array $records, array $meta): array
    {
        $totalFlights = count($records);
        $arrivals = 0;
        $departures = 0;
        $totalPax = 0;
        $totalCap = 0;
        $totalLoad = 0;
        $diverts = 0;
        $misses = 0;
        $unscheduled = 0;

        foreach ($records as $r) {
            if ($r['direction'] === 'ARRIVAL') $arrivals++;
            else $departures++;

            $totalPax += ($r['adult'] + $r['child'] + $r['infant']);
            $totalCap += $r['cap'];
            $totalLoad += $r['load'];

            if ($r['divert'] > 0) $diverts += $r['divert'];
            if ($r['miss'] > 0) $misses += $r['miss'];
            if (in_array($r['sched_type'], ['UNSCHED', 'UNSCHEDULED'], true)) $unscheduled++;
        }

        $avgLf = ($totalCap > 0) ? round(($totalLoad / $totalCap) * 100, 1) . '%' : 'N/A';

        return [
            'total_flights'   => $totalFlights,
            'arrivals'        => $arrivals,
            'departures'      => $departures,
            'total_pax'       => $totalPax,
            'avg_load_factor' => $avgLf,
            'diverts'         => $diverts,
            'misses'          => $misses,
            'unscheduled'     => $unscheduled,
            'airport'         => $meta['airport'],
            'period'          => $meta['period_label'],
        ];
    }
}
