<?php

namespace App\Services\Dau\Parsers;

class DAU10AParser extends BaseDauParser
{
    public function parse(string $filePath): array
    {
        $rawRows = $this->extractRawTable($filePath);
        $meta = $this->extractMetadata($filePath, $rawRows);

        $header0 = $rawRows[0] ?? [];
        $header1 = $rawRows[1] ?? [];

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
            }
            $colIdx += 10;
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

        for ($i = 2; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
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
                'period'           => $period,
                'total_flights'    => $rowTotFlights,
                'total_passengers' => $rowTotPax,
                'terminals'        => $termBreakdown,
            ];
        }

        return [
            'report_type'      => 'DAU10A',
            'report_title'     => 'Jam Puncak Menurut Terminal Matrix (DAU-10A)',
            'report_code'      => 'DAU-10A',
            'meta'             => $meta,
            'summary'          => $summary,
            'matrix_terminals' => $terminals,
            'records_count'    => count($records),
            'records'          => $records,
            'normalized_pairs' => $normalizedPairs,
            'columns'          => ['No', 'Periode Jam', 'Total Pesawat', 'Total Penumpang', 'Terminal Breakdown'],
        ];
    }
}
