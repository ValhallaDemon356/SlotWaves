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
     * Indonesian major domestic airports for traffic type detection.
     */
    protected const DOMESTIC_AIRPORTS = [
        'CGK', 'HLP', 'BDO', 'SUB', 'DPS', 'KNO', 'UPG', 'JOG', 'SRG', 'YIA',
        'BPN', 'BDJ', 'MDC', 'LOP', 'PLM', 'BTJ', 'PKU', 'PDG', 'DJB', 'TKG',
        'PNK', 'TRK', 'KOE', 'AMQ', 'DJJ', 'TIM', 'BIK', 'MKQ', 'SOQ', 'MKW',
        'GTO', 'KDI', 'TTE', 'TJQ', 'PGK', 'TNJ', 'BTH', 'DTB', 'BWX', 'MLG'
    ];

    /**
     * Detect actual markup and container format of FDR source file (Prompt Item 12 & 13).
     * Possible: HTML_XLS, XLS, XLSX, XML, CSV
     */
    public function detectActualFormat(string $content, string $fileName = ''): string
    {
        $head = substr($content, 0, 32768);
        $lower = strtolower($head);

        // 1. Native XLS binary OLE2 container
        if (strncmp($content, "\xD0\xCF\x11\xE0", 4) === 0) {
            return 'XLS';
        }

        // 2. OpenXML ZIP archive (.xlsx)
        if (strncmp($content, "PK\x03\x04", 4) === 0) {
            return 'XLSX';
        }

        // 3. XML Spreadsheet 2003 / XML
        if ($this->isXmlSpreadsheet($content) || (str_starts_with(trim($lower), '<?xml') && !str_contains($lower, '<html'))) {
            return 'XML';
        }

        // 4. OASYS HTML XLS (disguised HTML table in .xls container)
        if ($this->isHtmlTable($content) || str_contains($lower, '<html') || str_contains($lower, '<table') || str_contains($lower, '<!doctype')) {
            return 'HTML_XLS';
        }

        // 5. CSV format
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'csv' || $this->isCsvContent($head)) {
            return 'CSV';
        }

        return 'HTML_XLS';
    }

    /**
     * Detect exact format of FDR source file.
     */
    public function detectFormat(string $filePath, ?string $content = null): string
    {
        if ($content === null && file_exists($filePath)) {
            $fp = @fopen($filePath, 'rb');
            $content = $fp ? fread($fp, 32768) : '';
            if (is_resource($fp)) fclose($fp);
        }
        $actual = $this->detectActualFormat($content ?? '', basename($filePath));
        if ($actual === 'HTML_XLS') {
            return 'OASYS HTML XLS';
        }
        if ($actual === 'XLS') {
            return 'NATIVE XLS';
        }
        return $actual;
    }

    /**
     * Check if content is XML spreadsheet or XML document.
     */
    public function isXmlSpreadsheet(string $content): bool
    {
        $head = strtolower(substr($content, 0, 4096));
        return (str_starts_with(trim($head), '<?xml') && (str_contains($head, '<workbook') || str_contains($head, '<table') || str_contains($head, 'urn:schemas-microsoft-com:office:spreadsheet')));
    }

    /**
     * Check if content resembles CSV structure.
     */
    public function isCsvContent(string $head): bool
    {
        $lines = explode("\n", substr($head, 0, 2048));
        if (count($lines) >= 2) {
            $first = str_getcsv($lines[0]);
            $second = str_getcsv($lines[1]);
            return (count($first) >= 4 && count($first) === count($second));
        }
        return false;
    }

    /**
     * Parse raw FDR workbook content or path.
     * Uses high-efficiency streaming parser for HTML-XLS files to handle 45 MB+ files with minimal memory.
     */
    public function parse(string $filePath, ?callable $onProgress = null): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        @ini_set('pcre.backtrack_limit', '10000000');
        @ini_set('memory_limit', '512M');

        // Probe first 4KB to detect format before reading whole file
        $handle = @fopen($filePath, 'rb');
        $head = $handle ? fread($handle, 4096) : '';
        if ($handle) {
            fclose($handle);
        }

        $detectedFormat = $this->detectFormat($filePath, $head);

        // For OASYS HTML XLS files (including large 45 MB+ files), use streaming parser
        if ($detectedFormat === 'OASYS HTML XLS' || $this->isHtmlTable($head)) {
            return $this->parseHtmlStream($filePath, $onProgress);
        }

        $content = file_get_contents($filePath);
        $rawRows = [];
        $metaHeaders = [];

        if ($detectedFormat === 'XML' || $this->isXmlSpreadsheet($content)) {
            [$metaHeaders, $rawRows] = $this->parseXmlTable($content);
        } elseif ($detectedFormat === 'CSV' || strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'csv') {
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

        // Compute diagnostics
        $classification = $this->classifyRows($records);
        $htmlDataRows = max(0, count($rawRows) - ($columnMap['header_row_index'] >= 0 ? $columnMap['header_row_index'] + 1 : 0));
        $meta['diagnostics'] = [
            'html_data_rows'   => $htmlDataRows ?: count($records),
            'source_rows'      => $htmlDataRows ?: count($records),
            'movement_rows'    => $classification['movement_count'],
            'summary_rows'     => $classification['summary_count'],
            'rejected_rows'    => max(0, $htmlDataRows - count($records)),
        ];

        return [
            'meta'            => $meta,
            'records'         => $records,
            'summary'         => $this->buildFastSummary($records, $meta),
            'detected_format' => $detectedFormat,
        ];
    }

    /**
     * High-performance streaming HTML parser for large OASYS FDR files (e.g. 45 MB, 33k+ rows).
     * Avoids giant preg_match_all strings and loads records row-by-row with minimal memory footprint.
     */
    public function parseHtmlStream(string $filePath, ?callable $onProgress = null): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Failed to open FDR file: {$filePath}");
        }

        $lead = fread($handle, 65536);
        $metaHeaders = [];
        if (preg_match_all('/<input[^>]+type=["\']hidden["\'][^>]*>/i', $lead, $mInputs)) {
            foreach ($mInputs[0] as $input) {
                $name = '';
                $value = '';
                if (preg_match('/name=["\']([^"\']+)["\']/i', $input, $n)) $name = $n[1];
                if (preg_match('/value=["\']([^"\']*)["\']/i', $input, $v)) $value = $v[1];
                if ($name !== '') $metaHeaders[] = strtoupper($name) . ': ' . trim($value);
            }
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $lead, $titleMatch)) {
            $metaHeaders[] = trim(strip_tags($titleMatch[1]));
        }
        if (preg_match_all('/<center[^>]*>(.*?)<\/center>/is', $lead, $centerMatches)) {
            foreach ($centerMatches[1] as $c) {
                $metaHeaders[] = trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $c)));
            }
        }

        $meta = $this->extractMetadata($metaHeaders, []);
        $meta['detected_format'] = 'OASYS HTML XLS';

        $buffer = $lead;
        $headerCols = [];
        $columnMap = [];
        $records = [];
        $htmlDataRows = 0;
        $fileSize = filesize($filePath);
        $bytesRead = strlen($lead);

        if ($onProgress) {
            $onProgress('READING', 10, 0);
        }

        while (!feof($handle) || strlen($buffer) > 0) {
            $trPos = stripos($buffer, '<tr');
            if ($trPos === false) {
                if (!feof($handle)) {
                    $chunk = fread($handle, 131072);
                    $bytesRead += strlen($chunk);
                    $buffer .= $chunk;
                    continue;
                } else {
                    break;
                }
            }

            $endTrPos = stripos($buffer, '</tr>', $trPos);
            if ($endTrPos === false) {
                if (!feof($handle)) {
                    $chunk = fread($handle, 131072);
                    $bytesRead += strlen($chunk);
                    $buffer .= $chunk;
                    continue;
                } else {
                    $endTrPos = strlen($buffer);
                }
            }

            $trLen = ($endTrPos + 5) - $trPos;
            $trHtml = substr($buffer, $trPos, $trLen);
            $buffer = substr($buffer, $endTrPos + 5);

            if (preg_match_all('/<(td|th)([^>]*)>(.*?)<\/\1>/is', $trHtml, $cMatches, PREG_SET_ORDER)) {
                $cells = [];
                foreach ($cMatches as $c) {
                    $cellClean = trim(html_entity_decode(strip_tags($c[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $cellClean = trim(preg_replace('/\s+/', ' ', $cellClean));
                    $cells[] = $cellClean;
                }

                if (empty($cells) || (count($cells) === 1 && $cells[0] === '')) continue;

                $rowStr = implode(' ', $cells);
                if (empty($headerCols) && (stripos($rowStr, 'AIR LINE') !== false || stripos($rowStr, 'FLIGHT NO') !== false || stripos($rowStr, 'SIBT') !== false)) {
                    $headerCols = $cells;
                    $colInfo = $this->identifyColumns([$headerCols]);
                    $columnMap = $colInfo['mapping'];
                    continue;
                }

                if (empty($headerCols)) {
                    continue;
                }

                $firstCell = $cells[0] ?? '';
                $isMovement = is_numeric($firstCell) && (int)$firstCell > 0;
                $isSummary = (stripos($rowStr, 'PAX ALL') !== false || preg_match('/-(?:pax\s*all|adult,\s*child,\s*infant|transit)-/i', $rowStr));

                if (!$isMovement && !$isSummary) {
                    continue;
                }

                $htmlDataRows++;
                if ($isSummary) {
                    $ext = $this->extractSourceSummaryFromRow($cells);
                    if ($ext) {
                        $meta['source_summary'] = $ext['source_summary'];
                        $meta['source_passenger_summary'] = $ext['source_passenger_summary'];
                    }
                }
                $colInfo = ['header_row_index' => 0, 'mapping' => $columnMap];
                $norm = $this->normalizeRecords([$headerCols, $cells], $colInfo, $meta);
                if (!empty($norm)) {
                    $normRecord = $norm[0];
                    $normRecord['index'] = count($records) + 1;
                    $records[] = $normRecord;
                }

                if ($onProgress && ($htmlDataRows % 2500 === 0)) {
                    $pct = min(85, (int)(15 + ($bytesRead / max(1, $fileSize)) * 70));
                    $onProgress('PARSING', $pct, count($records));
                }
            }
        }
        fclose($handle);

        if ($onProgress) {
            $onProgress('NORMALIZING', 90, count($records));
        }

        // Derive multi-day / multi-month period from actual records if not explicit
        $meta = $this->refineMetadataWithRecords($meta, $records);

        // Compute diagnostics
        $classification = $this->classifyRows($records);
        $meta['diagnostics'] = [
            'html_data_rows'   => $htmlDataRows,
            'source_rows'      => $htmlDataRows,
            'movement_rows'    => $classification['movement_count'],
            'summary_rows'     => $classification['summary_count'],
            'rejected_rows'    => max(0, $htmlDataRows - count($records)),
        ];

        if ($onProgress) {
            $onProgress('VALIDATING', 95, count($records));
        }

        return [
            'meta'            => $meta,
            'records'         => $records,
            'summary'         => $this->buildFastSummary($records, $meta),
            'detected_format' => 'OASYS HTML XLS',
        ];
    }

    /**
     * Incremental batch parser for large OASYS FDR files (Prompt Section 13 & 14).
     * Processes batches of rows and yields checkpoints (processed_rows, current_offset, current_batch, progress).
     * Resumes directly from $offset without restarting from row 1.
     */
    public function parseBatch(string $filePath, int $offset = 0, int $batchSize = 2000, ?array $savedMeta = null): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $fileSize = filesize($filePath);
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new \RuntimeException("Failed to open FDR file: {$filePath}");
        }

        $headerByteOffset = $savedMeta['_header_byte_offset'] ?? 0;
        $headerCols = $savedMeta['_header_cols'] ?? [];
        $colMap = $savedMeta['_col_map'] ?? [];
        $meta = $savedMeta ?: [];

        if (empty($headerCols) || $offset <= 0) {
            $lead = fread($handle, 65536);
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
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $lead, $tm)) {
                $metaHeaders[] = trim(strip_tags($tm[1]));
            }
            if (preg_match_all('/<center[^>]*>(.*?)<\/center>/is', $lead, $cm)) {
                foreach ($cm[1] as $c) {
                    $metaHeaders[] = trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $c)));
                }
            }

            $meta = $this->extractMetadata($metaHeaders, []);
            $meta['detected_format'] = 'OASYS HTML XLS';

            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $lead, $trs);
            foreach ($trs[1] as $tr) {
                preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $tr, $tds);
                $cells = array_map(function($c) {
                    return trim(html_entity_decode(strip_tags($c), ENT_QUOTES, 'UTF-8'));
                }, $tds[1]);
                $rowStr = implode(' ', $cells);
                if (stripos($rowStr, 'AIRLINE') !== false || stripos($rowStr, 'AIR LINE') !== false || stripos($rowStr, 'FLIGHT NO') !== false || stripos($rowStr, 'SIBT') !== false) {
                    $headerCols = $cells;
                    $headerByteOffset = strpos($lead, '</tr>', stripos($lead, 'AIR')) + 5;
                    break;
                }
            }

            $colInfo = $this->identifyColumns([$headerCols]);
            $colMap = $colInfo['mapping'];
            $meta['_header_byte_offset'] = $headerByteOffset;
            $meta['_header_cols'] = $headerCols;
            $meta['_col_map'] = $colMap;

            $offset = max(1, $headerByteOffset);
        }

        fseek($handle, $offset);
        $buffer = '';
        $batchRecords = [];
        $batchSummaries = [];
        $colInfo = ['header_row_index' => 0, 'mapping' => $colMap];

        while (!feof($handle)) {
            $chunk = fread($handle, 131072);
            $buffer .= $chunk;

            while (($trPos = stripos($buffer, '<tr')) !== false) {
                $endTr = stripos($buffer, '</tr>', $trPos);
                if ($endTr === false) break;

                $trHtml = substr($buffer, $trPos, ($endTr + 5) - $trPos);
                $buffer = substr($buffer, $endTr + 5);

                if (preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $trHtml, $cMatches)) {
                    $cells = array_map(function($c) {
                        return trim(html_entity_decode(strip_tags($c), ENT_QUOTES, 'UTF-8'));
                    }, $cMatches[1]);

                    if (empty($cells) || (count($cells) === 1 && $cells[0] === '')) continue;
                    $firstCell = $cells[0];
                    $rowStr = implode(' ', $cells);

                    if (is_numeric($firstCell) && (int)$firstCell > 0) {
                        $norm = $this->normalizeRecords([$headerCols, $cells], $colInfo, $meta);
                        if (!empty($norm)) {
                            $batchRecords[] = $norm[0];
                        }
                        if (count($batchRecords) >= $batchSize) {
                            break 2;
                        }
                    } elseif (stripos($rowStr, 'PAX ALL') !== false || preg_match('/-(?:pax\s*all|adult,\s*child,\s*infant|transit)-/i', $rowStr)) {
                        $ext = $this->extractSourceSummaryFromRow($cells);
                        if ($ext) {
                            $meta['source_summary'] = $ext['source_summary'];
                            $meta['source_passenger_summary'] = $ext['source_passenger_summary'];
                        }
                        $norm = $this->normalizeRecords([$headerCols, $cells], $colInfo, $meta);
                        if (!empty($norm)) {
                            $batchSummaries[] = $norm[0];
                        }
                    }
                }
            }
        }

        $consumedBytes = ftell($handle) - strlen($buffer);
        $isEof = (feof($handle) && stripos($buffer, '<tr') === false) || ($consumedBytes >= $fileSize);
        fclose($handle);

        $nextOffset = $isEof ? $fileSize : max($consumedBytes, $offset + 1);
        $progress = $fileSize > 0 ? min(99, (int) round(($nextOffset / $fileSize) * 85) + 10) : 100;

        return [
            'records'         => $batchRecords,
            'summary_records' => $batchSummaries,
            'next_offset'     => $nextOffset,
            'is_eof'          => $isEof,
            'progress'        => $isEof ? 100 : $progress,
            'meta'            => $meta,
        ];
    }

    /**
     * Separate raw normalized records into operational flight movements and summary rows.
     * Section 8 & 9: Exclude summary row from movementRecords.
     */
    public function classifyRows(array $records): array
    {
        $movementRecords = [];
        $summaryRecords = [];
        foreach ($records as $r) {
            if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY'
                || strcasecmp(trim($r['air_line'] ?? ''), 'PAX ALL') === 0
                || stripos(trim($r['air_line'] ?? ''), 'PAX ALL') !== false
                || stripos(trim($r['desc'] ?? ''), 'PAX ALL') !== false) {
                $summaryRecords[] = $r;
                continue;
            }
            $movementRecords[] = $r;
        }

        return [
            'movement_records' => $movementRecords,
            'summary_rows'     => $summaryRecords,
            'summary_records'  => $summaryRecords,
            'movements'        => $movementRecords,
            'summaries'        => $summaryRecords,
            'movement_count'   => count($movementRecords),
            'summary_count'    => count($summaryRecords),
            0                  => $movementRecords,
            1                  => $summaryRecords,
        ];
    }

    /**
     * Parse XML spreadsheet table format.
     */
    protected function parseXmlTable(string $xmlContent): array
    {
        $metaHeaders = [];
        $rows = [];
        try {
            $xml = @simplexml_load_string($xmlContent);
            if ($xml) {
                // Register XML namespaces
                $ns = $xml->getDocNamespaces(true);
                foreach ($ns as $prefix => $uri) {
                    $xml->registerXPathNamespace($prefix ?: 'ss', $uri);
                }
                $tableNodes = $xml->xpath('//ss:Worksheet//ss:Table | //Table');
                if (!empty($tableNodes)) {
                    foreach ($tableNodes[0]->xpath('.//ss:Row | .//Row') as $rowNode) {
                        $row = [];
                        foreach ($rowNode->xpath('.//ss:Cell | .//Cell') as $cellNode) {
                            $data = $cellNode->xpath('.//ss:Data | .//Data');
                            $val = !empty($data) ? (string)$data[0] : (string)$cellNode;
                            $row[] = trim($val);
                        }
                        if (!empty(array_filter($row))) {
                            $rows[] = $row;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        return [$metaHeaders, $rows];
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

        $head = substr($content, 0, 32768);
        $lower = strtolower($head);
        return (strpos($lower, '<html') !== false
            || strpos($lower, '<!doctype') !== false
            || strpos($lower, '<table') !== false
            || strpos($lower, '<center') !== false
            || strpos($lower, '<title') !== false
            || strpos($lower, '<form') !== false
            || strpos($lower, '<td') !== false
            || strpos($lower, '<tr') !== false
            || strpos($lower, 'transactions_datefdr') !== false
            || strpos($lower, 'aeronautical') !== false
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
                $cellClean = trim(preg_replace('/<[^>]+>/', ' ', (string)$cell));
                $cellTrimmed = trim(preg_replace('/\s+/', ' ', $cellClean));
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
    /**
     * Extract source summary metrics from a raw row (e.g. PAX ALL).
     */
    public function extractSourceSummaryFromRow(array $rRow): ?array
    {
        $rowStr = implode(' ', (array)$rRow);
        if (!preg_match('/pax\s*all/i', $rowStr) && !preg_match('/-(?:pax\s*all|adult,\s*child,\s*infant|transit)-/i', $rowStr)) {
            return null;
        }

        $pAll = null;
        $pAdl = null;
        $pTr = null;
        if (preg_match('/pax\s*all[^\d]*\(?(\d[\d,\.]*)\)?/i', $rowStr, $pm)) {
            $pAll = (int)str_replace([',', '.'], '', $pm[1]);
        }
        if (preg_match('/(?:adult,\s*child,\s*infant|adult|child|infant|dewasa)[^\d]*\(?(\d[\d,\.]*)\)?/i', $rowStr, $am)) {
            $pAdl = (int)str_replace([',', '.'], '', $am[1]);
        }
        if (preg_match('/transit[^\d]*\(?(\d[\d,\.]*)\)?/i', $rowStr, $tm)) {
            $pTr = (int)str_replace([',', '.'], '', $tm[1]);
        }

        $adultVal = 0; $childVal = 0; $infantVal = 0; $transitVal = $pTr ?: 0; $transferVal = 0;
        $divertVal = 0; $missVal = 0; $crewVal = 0; $cargoVal = 0.0; $baggageVal = 0.0; $posVal = 0.0;
        $standVal = 0; $runwayVal = 0;

        foreach ($rRow as $cell) {
            $cellClean = trim(preg_replace('/\s+/', ' ', (string)$cell));
            if (preg_match('/^(?:ADU\s*LT|ADULT)\s*(\d+)$/i', $cellClean, $m)) $adultVal = (int)$m[1];
            if (preg_match('/^(?:CHI\s*LD|CHILD)\s*(\d+)$/i', $cellClean, $m)) $childVal = (int)$m[1];
            if (preg_match('/^(?:INF\s*ANT|INFANT)\s*(\d+)$/i', $cellClean, $m)) $infantVal = (int)$m[1];
            if (preg_match('/^(?:TRAN\s*SIT|TRANSIT)\s*(\d+)$/i', $cellClean, $m)) $transitVal = (int)$m[1];
            if (preg_match('/^(?:TRAN\s*FER|TRANSFER)\s*(\d+)$/i', $cellClean, $m)) $transferVal = (int)$m[1];
            if (preg_match('/^(?:DIV\s*ERT|DIVERT)\s*(\d+)$/i', $cellClean, $m)) $divertVal = (int)$m[1];
            if (preg_match('/^MISS\s*(\d+)$/i', $cellClean, $m)) $missVal = (int)$m[1];
            if (preg_match('/^CRW\s*(\d+)$/i', $cellClean, $m)) $crewVal = (int)$m[1];
            if (preg_match('/^CAR\.?\s*\(?KG\)?\s*(\d+)$/i', $cellClean, $m)) $cargoVal = (float)$m[1];
            if (preg_match('/^BAGG?\.?\s*\(?KG\)?\s*(\d+)$/i', $cellClean, $m)) $baggageVal = (float)$m[1];
            if (preg_match('/^POS\.?\s*\(?KG\)?\s*(\d+)$/i', $cellClean, $m)) $posVal = (float)$m[1];
            if (preg_match('/^STAND\s*(?:Use)?\s*(\d+)$/i', $cellClean, $m)) $standVal = (int)$m[1];
            if (preg_match('/^(?:RUN\s*WAY|RUNWAY)\s*(\d+)$/i', $cellClean, $m)) $runwayVal = (int)$m[1];
        }

        $passengerCore = ($adultVal + $childVal + $infantVal) ?: $pAdl;

        $sourcePassengerSummary = [
            'pax_all'            => $pAll,
            'adult_child_infant' => $passengerCore,
            'adult'              => $adultVal,
            'child'              => $childVal,
            'infant'             => $infantVal,
            'transit'            => $transitVal,
            'transfer'           => $transferVal,
        ];

        $sourceSummary = [
            'pax_all'        => $pAll,
            'adult'          => $adultVal,
            'child'          => $childVal,
            'infant'         => $infantVal,
            'passenger_core' => $passengerCore,
            'transit'        => $transitVal,
            'transfer'       => $transferVal,
            'divert'         => $divertVal,
            'miss'           => $missVal,
            'crew'           => $crewVal,
            'cargo_kg'       => $cargoVal,
            'baggage_kg'     => $baggageVal,
            'pos_kg'         => $posVal,
            'stand_use'      => $standVal,
            'runway_use'     => $runwayVal,
        ];

        return [
            'source_summary'           => $sourceSummary,
            'source_passenger_summary' => $sourcePassengerSummary,
        ];
    }

    /**
     * Extract metadata from headers and raw rows.
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

        // 1. Airport Code (BRANCH_CODE is authoritative for REPORT AIRPORT, Prompt Items 1, 3, 10)
        $airportCode = null;
        if (!empty($metaMap['BRANCH_CODE'])) {
            $airportCode = strtoupper(trim($metaMap['BRANCH_CODE']));
        } elseif (preg_match('/Flight\s*Daily\s*Report\s*([A-Z]{3})\b/i', $metaText, $fdrBranchMatch)) {
            $airportCode = strtoupper(trim($fdrBranchMatch[1]));
        }

        if (empty($airportCode)) {
            if (preg_match('/(HALIM|HLP)/i', $metaText)) {
                $airportCode = 'HLP';
            } elseif (preg_match('/(TANGERANG|SOEKARNO HATTA|CGK)/i', $metaText)) {
                $airportCode = 'CGK';
            } elseif (preg_match('/(HUSEIN|BANDUNG|BDO)/i', $metaText)) {
                $airportCode = 'BDO';
            } elseif (preg_match('/(SULTAN ISKANDAR MUDA|BANDA ACEH|BTJ)/i', $metaText)) {
                $airportCode = 'BTJ';
            } elseif (preg_match('/(JUANDA|SURABAYA|SUB)/i', $metaText)) {
                $airportCode = 'SUB';
            } elseif (preg_match('/(NGURAH RAI|BALI|DENPASAR|DPS)/i', $metaText)) {
                $airportCode = 'DPS';
            } elseif (preg_match('/\b([A-Z]{3})\b/', $metaText, $m)) {
                foreach (self::DOMESTIC_AIRPORTS as $ap) {
                    if (stripos($metaText, "($ap)") !== false || stripos($metaText, " - $ap") !== false || stripos($metaText, " $ap ") !== false) {
                        $airportCode = $ap;
                        break;
                    }
                }
            }
        }

        if (empty($airportCode)) {
            $airportCode = 'CGK';
        }

        $airportName = self::resolveAirportName($airportCode);

        // 2. Operator
        $operator = 'ALL AIRLINE';
        if (!empty($metaMap['OPERATOR'])) {
            $operator = strtoupper($metaMap['OPERATOR']);
        } elseif (preg_match('/(operator|airline|maskapai)\s*[:=]\s*([^\n\r<]+)/i', $metaText, $opMatch)) {
            $operator = strtoupper(trim($opMatch[2]));
        }
        if (strcasecmp($operator, 'PAX ALL') === 0 || stripos($operator, 'PAX ALL') !== false) {
            $operator = 'ALL AIRLINE';
        }

        // Extract Source Passenger Summary from raw rows if present (e.g. PAX ALL row)
        $sourcePassengerSummary = null;
        $sourceSummary = null;
        foreach ($rows as $rRow) {
            $ext = $this->extractSourceSummaryFromRow((array)$rRow);
            if ($ext) {
                $sourceSummary = $ext['source_summary'];
                $sourcePassengerSummary = $ext['source_passenger_summary'];
                break;
            }
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

        $pStart = $startDate ?: '2026-08-01';
        $pEnd = $endDate ?: '2026-08-31';
        $sourceType = self::detectGranularity($pStart, $pEnd);

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
        $legMeta = !empty($metaMap['LEG']) ? strtoupper(trim($metaMap['LEG'])) : 'ALL';
        if ($legMeta === 'ALL') {
            $direction = 'ALL';
        } elseif (str_starts_with($legMeta, 'A')) {
            $direction = 'ARRIVAL';
        } elseif (str_starts_with($legMeta, 'D')) {
            $direction = 'DEPARTURE';
        }

        // 7. Suffix
        $suffix = !empty($metaMap['SUFFIX']) ? strtoupper($metaMap['SUFFIX']) : 'ALL';

        // 8. Data Type
        $dataType = 'OPERATIONAL DATA';
        if (preg_match('/(report\s*data|laporan\s*data)/i', $metaText)) {
            $dataType = 'REPORT DATA';
        }

        return [
            'airport'                  => $airportCode,
            'airport_code'             => $airportCode,
            'airport_name'             => $airportName,
            'report_airport'           => $airportCode,
            'report_airports'          => [$airportCode],
            'branch_code'              => $airportCode,
            'is_single_airport'        => true,
            'operator'                 => $operator,
            'date_start'               => $pStart,
            'date_end'                 => $pEnd,
            'period_start'             => $pStart,
            'period_end'               => $pEnd,
            'source_start'             => $pStart,
            'source_end'               => $pEnd,
            'period_label'             => "{$pStart} s/d {$pEnd}",
            'transactions_date_fdr'    => $metaMap['TRANSACTIONS_DATEFDR'] ?? "{$pStart} - {$pEnd}",
            'source_type'              => $sourceType,
            'source_granularity'       => str_replace(' ', '_', $sourceType),
            'direction'                => $direction,
            'leg'                      => $legMeta,
            'route_type'               => $routeType,
            'category_code'            => $metaMap['CATEGORY_CODE'] ?? 'ALL',
            'suffix'                   => $suffix,
            'realization'              => $realization,
            'real'                     => $metaMap['REAL'] ?? $realization,
            'data_type'                => $dataType,
            'source_system'            => 'OASYS',
            'report_name'              => 'FLIGHT DAILY REPORT',
            'source_passenger_summary' => $sourcePassengerSummary,
            'source_summary'           => $sourceSummary,
        ];
    }

    /**
     * Resolve descriptive airport name from 3-letter IATA code.
     */
    public static function resolveAirportName(string $code): string
    {
        $code = strtoupper(trim($code));
        $names = [
            'HLP' => 'Halim Perdanakusuma (Jakarta)',
            'CGK' => 'Soekarno-Hatta (Jakarta)',
            'BDO' => 'Husein Sastranegara (Bandung)',
            'BTJ' => 'Sultan Iskandar Muda (Banda Aceh)',
            'SUB' => 'Juanda (Surabaya)',
            'DPS' => 'I Gusti Ngurah Rai (Bali)',
            'KNO' => 'Kualanamu (Medan)',
            'BPN' => 'Sultan Aji Muhammad Sulaiman Sepinggan (Balikpapan)',
            'JOG' => 'Adisutjipto (Yogyakarta)',
            'MLG' => 'Abdul Rachman Saleh (Malang)',
            'UPG' => 'Sultan Hasanuddin (Makassar)',
            'SRG' => 'Jenderal Ahmad Yani (Semarang)',
            'SOC' => 'Adi Soemarmo (Solo)',
            'PDG' => 'Minangkabau (Padang)',
            'PKU' => 'Sultan Syarif Kasim II (Pekanbaru)',
            'PLM' => 'Sultan Mahmud Badaruddin II (Palembang)',
            'BDJ' => 'Syamsudin Noor (Banjarmasin)',
            'MDC' => 'Sam Ratulangi (Manado)',
            'AAP' => 'Aji Pangeran Tumenggung Pranoto (Samarinda)',
            'DJB' => 'Sultan Thaha (Jambi)',
            'PGK' => 'Depati Amir (Pangkal Pinang)',
            'TJQ' => 'H.A.S. Hanandjoeddin (Belitung)',
            'TNJ' => 'Raja Haji Fisabilillah (Tanjung Pinang)',
            'DTB' => 'Silangit (Tapanuli Utara)',
            'BWX' => 'Banyuwangi (Banyuwangi)',
            'KJT' => 'Kertajati (Majalengka)',
        ];

        return $names[$code] ?? "{$code} Airport";
    }

    /**
     * Automatically detect source period granularity.
     * DAILY: 1 calendar day
     * MONTHLY: full calendar month
     * YEARLY: full calendar year
     * CUSTOM RANGE: other multi-day period
     */
    public static function detectGranularity(string $startDate, string $endDate): string
    {
        $start = trim(str_replace('/', '-', $startDate));
        $end = trim(str_replace('/', '-', $endDate));

        if ($start === $end) {
            return 'DAILY';
        }

        try {
            $cStart = Carbon::parse($start);
            $cEnd = Carbon::parse($end);

            if ($cStart->isSameDay($cEnd)) {
                return 'DAILY';
            }

            if ($cStart->year === $cEnd->year && $cStart->month === 1 && $cStart->day === 1 && $cEnd->month === 12 && $cEnd->day === 31) {
                return 'YEARLY';
            }

            if ($cStart->year === $cEnd->year && $cStart->month === $cEnd->month && $cStart->day === 1 && $cEnd->day === $cEnd->copy()->endOfMonth()->day) {
                return 'MONTHLY';
            }
        } catch (\Throwable $e) {}

        return 'CUSTOM RANGE';
    }

    /**
     * Refine metadata (dates, operator, airport) from parsed rows if needed.
     */
    public function refineMetadataWithRecords(array $meta, array $records): array
    {
        if (empty($records)) {
            return $meta;
        }

        $dates = [];
        $airlines = [];
        $hasActuals = false;

        foreach ($records as $r) {
            $d = $r['operational_date'] ?? ($r['flight_date'] ?? null);
            if (!empty($d) && $d !== 'N/A') {
                $dates[$d] = true;
            }
            if (!empty($r['air_line']) && $r['air_line'] !== 'N/A') {
                $airlines[$r['air_line']] = true;
            }
            if (($r['aibt'] !== 'N/A' && !empty($r['aibt'])) || ($r['aobt'] !== 'N/A' && !empty($r['aobt']))) {
                $hasActuals = true;
            }
        }

        if (!empty($dates)) {
            $sortedDates = array_keys($dates);
            sort($sortedDates);
            $minDate = reset($sortedDates);
            $maxDate = end($sortedDates);
            $meta['available_days'] = count($sortedDates);

            // If header was empty, use record min/max date
            if (empty($meta['period_start'])) {
                $meta['date_start'] = $minDate;
                $meta['date_end'] = $maxDate;
                $meta['period_start'] = $minDate;
                $meta['period_end'] = $maxDate;
                $meta['source_start'] = $minDate;
                $meta['source_end'] = $maxDate;
                $meta['period_label'] = "{$minDate} s/d {$maxDate}";
            }
        }

        // Re-evaluate granularity after record inspection
        $meta['source_type'] = self::detectGranularity($meta['period_start'] ?? '', $meta['period_end'] ?? '');
        $meta['source_granularity'] = str_replace(' ', '_', $meta['source_type']);

        $branches = [];
        foreach ($records as $r) {
            $b = $r['report_airport'] ?? ($r['branch'] ?? null);
            if (!empty($b) && $b !== 'N/A') {
                $branches[strtoupper($b)] = true;
            }
        }
        if (!empty($branches)) {
            $meta['report_airports'] = array_keys($branches);
            $meta['is_single_airport'] = (count($branches) === 1);
        }

        if (!$hasActuals && ($meta['realization'] ?? 'YES') === 'YES') {
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
            $rowText = implode(' ', (array)$row);

            // Detect PAX ALL and other summary rows (Section 10: row_type = SUMMARY, NOT MOVEMENT, NOT airline = PAX ALL)
            $isPaxAllSummary = preg_match('/pax\s*all/i', $rowText)
                || preg_match('/-(?:pax\s*all|adult,\s*child,\s*infant|transit)-/i', $rowText)
                || (isset($map['air_line']) && preg_match('/pax\s*all/i', (string)($row[$map['air_line']] ?? '')));

            if ($isPaxAllSummary) {
                $paxAllCount = 0;
                if (preg_match('/pax\s*all[^\d]*\(?(\d[\d,\.]*)\)?/i', $rowText, $pm)) {
                    $paxAllCount = (int)str_replace([',', '.'], '', $pm[1]);
                }
                $srcSum = $meta['source_summary'] ?? [];
                $records[] = [
                    'index'                        => count($records) + 1,
                    'row_type'                     => 'SUMMARY', // NOT MOVEMENT
                    'air_line'                     => 'N/A',     // NOT PAX ALL
                    'flight_no'                    => 'N/A',
                    'flight_no_base'               => 'N/A',
                    'flight_suffix'                => '',
                    'paired_no'                    => 'N/A',
                    'desc'                         => 'PAX ALL SOURCE SUMMARY',
                    'sibt'                         => 'N/A',
                    'sobt'                         => 'N/A',
                    'aibt'                         => 'N/A',
                    'aobt'                         => 'N/A',
                    'sched_display'                => 'N/A',
                    'actual_display'               => 'N/A',
                    'scheduled_datetime'           => null,
                    'actual_datetime'              => null,
                    'scheduled_arrival_datetime'   => null,
                    'actual_arrival_datetime'      => null,
                    'scheduled_departure_datetime' => null,
                    'actual_departure_datetime'    => null,
                    'operational_date'             => $meta['period_start'] ?? date('Y-m-d'),
                    'actual_operational_date'      => null,
                    'flight_date'                  => $meta['period_start'] ?? date('Y-m-d'),
                    'scheduled_hour'               => null,
                    'actual_hour'                  => null,
                    'operational_hour'             => 0,
                    'hour'                         => 0,
                    'operational_datetime'         => ($meta['period_start'] ?? date('Y-m-d')) . ' 00:00:00',
                    'leg'                          => 'N/A',
                    'raw_leg'                      => 'N/A',
                    'direction'                    => 'N/A',
                    'sched_type'                   => 'SUMMARY',
                    'is_scheduled'                 => false,
                    'city_1'                       => 'N/A',
                    'city_2'                       => 'N/A',
                    'route'                        => 'N/A',
                    'report_airport'               => $meta['airport'] ?? 'N/A',
                    'origin_airport'               => 'N/A',
                    'destination_airport'          => 'N/A',
                    'traffic'                      => 'N/A',
                    'route_type'                   => 'N/A',
                    'mtow'                         => 'N/A',
                    'reg_no'                       => 'N/A',
                    'cap'                          => 0,
                    'load'                         => $paxAllCount ?: ($srcSum['pax_all'] ?? 0),
                    'load_factor'                  => 'N/A',
                    'adult'                        => $srcSum['adult'] ?? 0,
                    'child'                        => $srcSum['child'] ?? 0,
                    'infant'                       => $srcSum['infant'] ?? 0,
                    'transit'                      => $srcSum['transit'] ?? 0,
                    'transfer'                     => $srcSum['transfer'] ?? 0,
                    'divert'                       => $srcSum['divert'] ?? 0,
                    'miss'                         => $srcSum['miss'] ?? 0,
                    'crw'                          => $srcSum['crew'] ?? 0,
                    'ex_crw'                       => 0,
                    'cargo_kg'                     => (float)($srcSum['cargo_kg'] ?? 0.0),
                    'baggage_kg'                   => (float)($srcSum['baggage_kg'] ?? 0.0),
                    'pos_kg'                       => (float)($srcSum['pos_kg'] ?? 0.0),
                    'stand'                        => (string)($srcSum['stand_use'] ?? 'N/A'),
                    'runway'                       => (string)($srcSum['runway_use'] ?? 'N/A'),
                    'final'                        => 'N/A',
                    'final_time'                   => 'N/A',
                    'branch'                       => 'N/A',
                    'delay_minutes'                => 0,
                    'is_irregular'                 => false,
                    'is_realized'                  => false,
                ];
                continue;
            }

            if (empty(array_filter($row)) || preg_match('/^(total|grand\s*total|jumlah|subtotal|summary)/i', $firstCell) || preg_match('/^(grand\s*total|halaman)/i', $rowText)) {
                continue;
            }

            // Movement row detection: First cell must be a positive numeric row number (Section 18)
            if (!is_numeric($firstCell) || (int)$firstCell <= 0) {
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

            // Combined and separate timestamp columns
            $sibtSobtVal = $get('sibt_sobt', 'N/A');
            $aibtAobtVal = $get('aibt_aobt', 'N/A');
            $sibtVal     = $get('sibt', 'N/A');
            $sobtVal     = $get('sobt', 'N/A');
            $aibtVal     = $get('aibt', 'N/A');
            $aobtVal     = $get('aobt', 'N/A');

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
            $umroh    = $getNumeric('umroh', 0);

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

            if (str_starts_with($legUpper, 'D') || ($sobtVal !== 'N/A' && $sibtVal === 'N/A') || ($aobtVal !== 'N/A' && $aibtVal === 'N/A')) {
                $direction = 'DEPARTURE';
            } elseif (str_starts_with($legUpper, 'A') || ($sibtVal !== 'N/A' && $sobtVal === 'N/A') || ($aibtVal !== 'N/A' && $aobtVal === 'N/A')) {
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

            // ── DIRECTION-AWARE TIMESTAMP MAPPING (PART 4, 5, 6) ──
            $schedArrivalRaw = null;
            $actArrivalRaw   = null;
            $schedDepRaw     = null;
            $actDepRaw       = null;

            if ($direction === 'ARRIVAL') {
                $rawS = ($sibtVal !== 'N/A' && $sibtVal !== '') ? $sibtVal : (($sibtSobtVal !== 'N/A' && $sibtSobtVal !== '') ? $sibtSobtVal : null);
                $rawA = ($aibtVal !== 'N/A' && $aibtVal !== '') ? $aibtVal : (($aibtAobtVal !== 'N/A' && $aibtAobtVal !== '') ? $aibtAobtVal : null);

                $schedArrivalRaw = ($rawS !== '-' && $rawS !== 'NULL') ? $rawS : null;
                $actArrivalRaw   = ($rawA !== '-' && $rawA !== 'NULL') ? $rawA : null;

                $sibt = $schedArrivalRaw ?: 'N/A';
                $sobt = 'N/A';
                $aibt = $actArrivalRaw ?: 'N/A';
                $aobt = 'N/A';
            } else {
                $rawS = ($sobtVal !== 'N/A' && $sobtVal !== '') ? $sobtVal : (($sibtSobtVal !== 'N/A' && $sibtSobtVal !== '') ? $sibtSobtVal : null);
                $rawA = ($aobtVal !== 'N/A' && $aobtVal !== '') ? $aobtVal : (($aibtAobtVal !== 'N/A' && $aibtAobtVal !== '') ? $aibtAobtVal : null);

                $schedDepRaw = ($rawS !== '-' && $rawS !== 'NULL') ? $rawS : null;
                $actDepRaw   = ($rawA !== '-' && $rawA !== 'NULL') ? $rawA : null;

                $sobt = $schedDepRaw ?: 'N/A';
                $sibt = 'N/A';
                $aobt = $actDepRaw ?: 'N/A';
                $aibt = 'N/A';
            }

            // Directionally relevant raw strings
            $schedMovementRaw = ($direction === 'ARRIVAL') ? $schedArrivalRaw : $schedDepRaw;
            $actMovementRaw   = ($direction === 'ARRIVAL') ? $actArrivalRaw   : $actDepRaw;

            // Parse timestamps
            $schedParsed = $this->parseDateTimeString($schedMovementRaw, $meta['period_start'] ?? null);
            $actParsed   = $this->parseDateTimeString($actMovementRaw, $meta['period_start'] ?? null);

            $scheduledDatetime = $schedParsed['datetime'];
            $actualDatetime    = $actParsed['datetime'];

            $scheduledHour = $schedParsed['hour'];
            $actualHour    = $actParsed['hour'];

            // Operational Date based on SCHEDULED timestamp (PART 7)
            $operationalDate       = $schedParsed['date'] ?: ($actParsed['date'] ?: ($meta['period_start'] ?? date('Y-m-d')));
            $actualOperationalDate = $actParsed['date'];

            // Operational Hour: scheduled_hour prefered for planned analytics, actual_hour for realized (PART 8)
            $operationalHour = $scheduledHour !== null ? $scheduledHour : ($actualHour !== null ? $actualHour : 0);
            $operationalDatetime = $scheduledDatetime ?: ($actualDatetime ?: "{$operationalDate} 00:00:00");

            // Delay in minutes (cross-midnight aware)
            $delayMinutes = 0;
            if ($schedMovementRaw && $actMovementRaw) {
                $delayMinutes = $this->calculateTimeDifferenceMinutes($schedMovementRaw, $actMovementRaw, $scheduledDatetime, $actualDatetime);
            }

            // Irregularity Flag: Divert > 0, Miss > 0, or Unscheduled (PART 13)
            $isIrregular = ($divert > 0 || $miss > 0 || $schedType === 'UNSCHEDULED');
            $isRealized  = ($actualDatetime !== null && $actMovementRaw !== null && $actMovementRaw !== '-' && $actMovementRaw !== 'N/A');

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
                'index'                        => count($records) + 1,
                'row_type'                     => 'MOVEMENT',
                'air_line'                     => $airLine,
                'flight_no'                    => $flightNo,
                'flight_no_base'               => $flightNoBase,
                'flight_suffix'                => $flightSuffix,
                'paired_no'                    => $pairedNo,
                'desc'                         => $desc,
                'sibt'                         => $sibt,
                'sobt'                         => $sobt,
                'aibt'                         => $aibt,
                'aobt'                         => $aobt,
                'sibt_sobt'                    => $sibtSobtVal !== 'N/A' ? $sibtSobtVal : ($direction === 'ARRIVAL' ? $sibt : $sobt),
                'aibt_aobt'                    => $aibtAobtVal !== 'N/A' ? $aibtAobtVal : ($direction === 'ARRIVAL' ? $aibt : $aobt),
                'sched_display'                => ($direction === 'ARRIVAL' ? $sibt : $sobt),
                'actual_display'               => ($direction === 'ARRIVAL' ? $aibt : $aobt),
                'scheduled_datetime'           => $scheduledDatetime,
                'actual_datetime'              => $actualDatetime,
                'scheduled_arrival_datetime'   => ($direction === 'ARRIVAL' ? $scheduledDatetime : null),
                'actual_arrival_datetime'      => ($direction === 'ARRIVAL' ? $actualDatetime : null),
                'scheduled_departure_datetime' => ($direction === 'DEPARTURE' ? $scheduledDatetime : null),
                'actual_departure_datetime'    => ($direction === 'DEPARTURE' ? $actualDatetime : null),
                'operational_date'             => $operationalDate,
                'actual_operational_date'      => $actualOperationalDate,
                'flight_date'                  => $operationalDate,
                'scheduled_hour'               => $scheduledHour,
                'actual_hour'                  => $actualHour,
                'operational_hour'             => $operationalHour,
                'hour'                         => $operationalHour,
                'operational_datetime'         => $operationalDatetime,
                'leg'                          => $normalizedLeg,
                'raw_leg'                      => $rawLeg,
                'direction'                    => $direction,
                'sched_type'                   => $schedType,
                'is_scheduled'                 => ($schedType === 'SCHEDULED'),
                'city_1'                       => $city1,
                'city_2'                       => $city2,
                'route'                        => ($city1 !== 'N/A' && $city2 !== 'N/A') ? "{$city1} → {$city2}" : 'N/A',
                'traffic'                      => $traffic,
                'route_type'                   => $traffic,
                'mtow'                         => $mtow,
                'reg_no'                       => $regNo,
                'cap'                          => $cap,
                'load'                         => $load,
                'load_factor'                  => $loadFactor,
                'adult'                        => $adult,
                'child'                        => $child,
                'infant'                       => $infant,
                'transit'                      => $transit,
                'transfer'                     => $transfer,
                'umroh'                        => $umroh,
                'divert'                       => $divert,
                'miss'                         => $miss,
                'crw'                          => $crw,
                'ex_crw'                       => $exCrw,
                'cargo_kg'                     => $cargoKg,
                'baggage_kg'                   => $baggageKg,
                'pos_kg'                       => $posKg,
                'stand'                        => $stand,
                'runway'                       => $runway,
                'final'                        => $final,
                'final_time'                   => $finalTime,
                'branch'                       => $branch,
                'report_airport'               => !empty($branch) && $branch !== 'N/A' ? strtoupper($branch) : ($meta['airport'] ?? 'CGK'),
                'origin_airport'               => $city1,
                'destination_airport'          => $city2,
                'delay_minutes'        => $delayMinutes,
                'is_irregular'         => $isIrregular,
                'is_realized'          => $isRealized,
            ];
        }

        return $records;
    }

    /**
     * Parse date and hour from any supported date/time format.
     * Never defaults unparseable hour to 12.
     */
    public function parseDateTimeString(?string $str, ?string $fallbackDate = null): array
    {
        if (empty($str) || $str === 'N/A' || $str === '-' || $str === 'NULL') {
            return ['date' => null, 'hour' => null, 'datetime' => null];
        }

        $str = trim($str);
        $date = null;
        $hour = null;
        $datetime = null;

        // Check for YYYY-MM-DD or YYYY/MM/DD
        if (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $str, $m)) {
            $date = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }
        // Check for DD-MM-YYYY or DD/MM/YYYY
        elseif (preg_match('/(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/', $str, $m)) {
            $date = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        } elseif ($fallbackDate) {
            $date = $fallbackDate;
        }

        // Check for hour:minute(:second)
        if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $str, $tm)) {
            $h = (int)$tm[1];
            $min = (int)$tm[2];
            $sec = isset($tm[3]) ? (int)$tm[3] : 0;
            if ($h >= 24) $h = 23;
            $hour = $h;
            if ($date) {
                $datetime = sprintf('%s %02d:%02d:%02d', $date, $h, $min, $sec);
            } else {
                $datetime = sprintf('%02d:%02d:%02d', $h, $min, $sec);
            }
        } elseif ($date) {
            $datetime = "{$date} 00:00:00";
        }

        return ['date' => $date, 'hour' => $hour, 'datetime' => $datetime];
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
    public static function calculateTimeDifferenceMinutes(string $sched, string $act, ?string $schedDt = null, ?string $actDt = null): int
    {
        try {
            if ($schedDt && $actDt) {
                return (int) Carbon::parse($schedDt)->diffInMinutes(Carbon::parse($actDt), false);
            }
            if (preg_match('/(\d{1,2}[-\/]\d{1,2}[-\/]\d{4}\s+\d{1,2}:\d{2})/', $sched) && preg_match('/(\d{1,2}[-\/]\d{1,2}[-\/]\d{4}\s+\d{1,2}:\d{2})/', $act)) {
                return (int) Carbon::parse(str_replace('/', '-', $sched))->diffInMinutes(Carbon::parse(str_replace('/', '-', $act)), false);
            }
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
    public function buildFastSummary(array $records, array $meta): array
    {
        $movementRecords = array_filter($records, fn($r) => ($r['row_type'] ?? 'MOVEMENT') !== 'SUMMARY' && stripos($r['air_line'] ?? '', 'PAX ALL') === false);
        $totalFlights = count($movementRecords);
        $arrivals = 0;
        $departures = 0;
        $totalPax = 0;
        $totalCap = 0;
        $totalLoad = 0;
        $diverts = 0;
        $misses = 0;
        $unscheduled = 0;

        foreach ($movementRecords as $r) {
            if (($r['direction'] ?? '') === 'ARRIVAL') $arrivals++;
            else $departures++;

            $totalPax += ((int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0));
            $totalCap += (int)($r['cap'] ?? 0);
            $totalLoad += (int)($r['load'] ?? 0);

            if (($r['divert'] ?? 0) > 0) $diverts += (int)$r['divert'];
            if (($r['miss'] ?? 0) > 0) $misses += (int)$r['miss'];
            if (in_array($r['sched_type'] ?? '', ['UNSCHED', 'UNSCHEDULED'], true)) $unscheduled++;
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
            'airport'         => $meta['airport'] ?? 'CGK',
            'period'          => $meta['period_label'] ?? '',
            'source_summary'  => $meta['source_summary'] ?? null,
            'diagnostics'     => $meta['diagnostics'] ?? null,
        ];
    }


}
