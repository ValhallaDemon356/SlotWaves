<?php

namespace App\Services\Dau\Parsers;

class DAU10AParser extends BaseDauParser
{
    public function parse(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $isHtml = $this->isHtmlTable($content);

        // Extract metadata first
        $rawRows = $this->extractRawTable($filePath);
        $meta = $this->extractMetadata($filePath, $rawRows);

        $tablesData = [];
        if ($isHtml) {
            $tablesData = $this->extractAllTablesFromHtml($content, $meta);
        }

        if (empty($tablesData)) {
            $tablesData = [
                [
                    'date' => $meta['start_date'] ?? null,
                    'rows' => $rawRows,
                ]
            ];
        }

        $records = [];
        $normalizedPairs = [];
        $summary = [
            'total_movements'    => 0,
            'aircraft_arrival'   => 0,
            'aircraft_departure' => 0,
            'passenger_arrival'  => 0,
            'passenger_departure'=> 0,
            'passenger_transit'  => 0,
            'passenger_transfer' => 0,
            'passenger_total'    => 0,
            'crew_total'         => 0,
        ];

        $globalTerminals = [];
        $uniqueDates = [];

        foreach ($tablesData as $tBlock) {
            $blockDate = $tBlock['date'] ?? ($meta['start_date'] ?? null);
            if (!empty($blockDate) && !in_array($blockDate, $uniqueDates)) {
                $uniqueDates[] = $blockDate;
            }

            $bRows = $tBlock['rows'] ?? [];
            if (empty($bRows)) continue;

            $header0 = $bRows[0] ?? [];
            $header1 = $bRows[1] ?? [];

            // Terminals are in header row 0 (e.g. 1, 2F, 3U, 1B, 2D, 2E, 1C)
            $terminals = [];
            $colIdx = 2;
            while ($colIdx < count($header0)) {
                $tName = $this->toStr($header0[$colIdx] ?? '');
                if (!empty($tName)) {
                    $terminals[] = [
                        'start_col' => $colIdx,
                        'name'      => $tName,
                    ];
                    if (!in_array($tName, array_column($globalTerminals, 'name'))) {
                        $globalTerminals[] = [
                            'start_col' => $colIdx,
                            'name'      => $tName,
                        ];
                    }
                }
                $colIdx += 10;
            }

            for ($i = 2; $i < count($bRows); $i++) {
                $row = $bRows[$i];
                if (empty($row) || count($row) < 2) continue;

                $first = $this->toStr($row[0] ?? '');
                $period = $this->toStr($row[1] ?? '');

                if (stripos($first, 'TOTAL') !== false || stripos($period, 'TOTAL') !== false) {
                    continue;
                }

                if (!is_numeric($first)) continue;

                $rowTotFlights = 0;
                $rowTotPax = 0;
                $termBreakdown = [];

                foreach ($terminals as $t) {
                    $c = $t['start_col'];
                    $flArr = $this->toInt($row[$c] ?? 0);
                    $flDep = $this->toInt($row[$c+1] ?? 0);
                    $pArr  = $this->toInt($row[$c+2] ?? 0);
                    $pDep  = $this->toInt($row[$c+3] ?? 0);
                    $pTrn  = $this->toInt($row[$c+4] ?? 0);
                    $pTrf  = $this->toInt($row[$c+5] ?? 0);
                    $crew  = $this->toInt($row[$c+6] ?? 0);
                    $exCrw = $this->toInt($row[$c+7] ?? 0);
                    $totFl = $this->toInt($row[$c+8] ?? ($flArr + $flDep));
                    $totPx = $this->toInt($row[$c+9] ?? ($pArr + $pDep + $pTrn + $pTrf));

                    $tData = [
                        'date'                => $blockDate,
                        'hour'                => $period,
                        'period'              => $period,
                        'terminal'            => $t['name'],
                        'aircraft_arrival'    => $flArr,
                        'aircraft_departure'  => $flDep,
                        'aircraft_total'      => $totFl,
                        'passenger_arrival'   => $pArr,
                        'passenger_departure' => $pDep,
                        'passenger_transit'   => $pTrn,
                        'passenger_transfer'  => $pTrf,
                        'passenger_total'     => $totPx,
                        'crew'                => $crew,
                        'extra_crew'          => $exCrw,
                        'crew_total'          => ($crew + $exCrw),
                        'baggage'             => 0,
                        'cargo'               => 0,
                        'pos'                 => 0,
                    ];
                    $termBreakdown[$t['name']] = $tData;

                    $normalizedPairs[] = $tData;

                    $rowTotFlights += $totFl;
                    $rowTotPax     += $totPx;
                    $summary['total_movements']    += $totFl;
                    $summary['aircraft_arrival']   += $flArr;
                    $summary['aircraft_departure'] += $flDep;
                    $summary['passenger_arrival']  += $pArr;
                    $summary['passenger_departure']+= $pDep;
                    $summary['passenger_transit']  += $pTrn;
                    $summary['passenger_transfer'] += $pTrf;
                    $summary['passenger_total']    += $totPx;
                    $summary['crew_total']         += ($crew + $exCrw);
                }

                $records[] = [
                    'no'               => $this->toInt($first),
                    'date'             => $blockDate,
                    'period'           => $period,
                    'total_flights'    => $rowTotFlights,
                    'total_passengers' => $rowTotPax,
                    'terminals'        => $termBreakdown,
                ];
            }
        }

        if (!empty($uniqueDates)) {
            sort($uniqueDates);
            $meta['start_date'] = $uniqueDates[0];
            $meta['end_date']   = $uniqueDates[count($uniqueDates) - 1];
            $meta['date_range'] = (count($uniqueDates) === 1)
                ? $meta['start_date']
                : "{$meta['start_date']} s/d {$meta['end_date']}";
        }

        return [
            'report_type'      => 'DAU10A',
            'report_title'     => 'Jam Puncak Menurut Terminal Matrix (DAU-10A)',
            'report_code'      => 'DAU-10A',
            'meta'             => $meta,
            'summary'          => $summary,
            'matrix_terminals' => !empty($globalTerminals) ? $globalTerminals : ($terminals ?? []),
            'records_count'    => count($records),
            'records'          => $records,
            'normalized_pairs' => $normalizedPairs,
            'available_dates'  => $uniqueDates,
            'available_days'   => max(1, count($uniqueDates)),
            'columns'          => ['No', 'Periode Jam', 'Total Pesawat', 'Total Penumpang', 'Terminal Breakdown'],
        ];
    }

    /**
     * Extract multiple tables with individual date contexts from HTML.
     */
    protected function extractAllTablesFromHtml(string $html, array $globalMeta): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            return [];
        }

        $results = [];

        // Check if multiple dates exist across the HTML text
        for ($tIdx = 0; $tIdx < $tables->length; $tIdx++) {
            $table = $tables->item($tIdx);

            $tableDate = null;
            $curr = $table->previousSibling;
            while ($curr) {
                $text = $curr->textContent ?? '';
                if (preg_match('/TANGGAL\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $text, $m)) {
                    $tableDate = $m[1];
                    break;
                }
                $curr = $curr->previousSibling;
            }

            if (!$tableDate && $tables->length === 1) {
                $tableDate = $globalMeta['start_date'] ?? null;
            }

            $rows = $table->getElementsByTagName('tr');
            $grid = [];
            $rIdx = 0;

            foreach ($rows as $tr) {
                $cIdx = 0;
                foreach ($tr->childNodes as $node) {
                    if (!($node instanceof \DOMElement) || ($node->nodeName !== 'td' && $node->nodeName !== 'th')) {
                        continue;
                    }

                    while (isset($grid[$rIdx][$cIdx])) {
                        $cIdx++;
                    }

                    $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
                    $colspan = (int) $node->getAttribute('colspan') ?: 1;
                    $rowspan = (int) $node->getAttribute('rowspan') ?: 1;

                    for ($r = 0; $r < $rowspan; $r++) {
                        for ($c = 0; $c < $colspan; $c++) {
                            $grid[$rIdx + $r][$cIdx + $c] = $text;
                        }
                    }

                    $cIdx += $colspan;
                }
                $rIdx++;
            }

            $matrix = [];
            foreach ($grid as $r => $cols) {
                ksort($cols);
                $matrix[$r] = array_values($cols);
            }

            $results[] = [
                'date' => $tableDate,
                'rows' => $matrix,
            ];
        }

        return $results;
    }
}
