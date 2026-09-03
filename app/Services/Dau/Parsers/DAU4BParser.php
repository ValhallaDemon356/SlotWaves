<?php

namespace App\Services\Dau\Parsers;

class DAU4BParser extends BaseDauParser
{
    public function parse(string $filePath): array
    {
        $rawRows = $this->extractRawTable($filePath);
        $meta = $this->extractMetadata($filePath, $rawRows);

        // Header row 0 contains airline names (cols 3, 13, 23...)
        $header0 = $rawRows[0] ?? [];
        $header1 = $rawRows[1] ?? [];

        $airlines = [];
        $colIdx = 3;
        while ($colIdx < count($header0)) {
            $name = $this->toStr($header0[$colIdx] ?? '');
            if (!empty($name)) {
                $code = '';
                if (preg_match('/^([A-Z0-9]{2,3})\s*(.*)/i', $name, $m)) {
                    $code = strtoupper(trim($m[1]));
                    $cleanName = trim($m[2]);
                } else {
                    $cleanName = $name;
                }
                $airlines[] = [
                    'start_col' => $colIdx,
                    'code'      => $code ?: substr($name, 0, 2),
                    'name'      => $cleanName ?: $name,
                    'raw_label' => $name,
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
            if (empty($row) || count($row) < 3) continue;

            $first = $this->toStr($row[0] ?? '');
            $city = $this->toStr($row[1] ?? '');
            $iata = $this->toStr($row[2] ?? '');

            if (stripos($first, 'TOTAL') !== false || stripos($city, 'TOTAL') !== false) {
                continue;
            }

            if (!is_numeric($first)) continue;

            $rowTotFlights = 0;
            $rowTotPax = 0;
            $airlineBreakdown = [];

            foreach ($airlines as $a) {
                $c = $a['start_col'];
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

                if ($totFl > 0 || $totPx > 0 || $crew > 0) {
                    $airlineData = [
                        'airline'             => $a['name'],
                        'airline_code'        => $a['code'],
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
                    ];
                    $airlineBreakdown[$a['code']] = $airlineData;

                    $normalizedPairs[] = array_merge([
                        'city'      => $city,
                        'city_code' => $iata,
                    ], $airlineData);
                }

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
                'city'             => $city,
                'city_code'        => $iata,
                'total_flights'    => $rowTotFlights,
                'total_passengers' => $rowTotPax,
                'airlines'         => $airlineBreakdown,
            ];
        }

        return [
            'report_type'      => 'DAU4B',
            'report_title'     => 'Asal/Tujuan Menurut Airline/Operator Matrix (DAU-04B)',
            'report_code'      => 'DAU-04B',
            'meta'             => $meta,
            'summary'          => $summary,
            'matrix_airlines'  => $airlines,
            'records_count'    => count($records),
            'records'          => $records,
            'normalized_pairs' => $normalizedPairs,
            'columns'          => ['No', 'Kota Asal/Tujuan', 'Kode IATA', 'Total Pesawat', 'Total Penumpang', 'Airline Breakdown'],
        ];
    }
}
