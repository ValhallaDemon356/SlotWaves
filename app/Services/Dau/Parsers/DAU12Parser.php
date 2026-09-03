<?php

namespace App\Services\Dau\Parsers;

class DAU12Parser extends BaseDauParser
{
    public function parse(string $filePath): array
    {
        $rawRows = $this->extractRawTable($filePath);
        $meta = $this->extractMetadata($filePath, $rawRows);

        $records = [];
        $summary = [
            'total_movements'    => 0,
            'aircraft_arrival'   => 0,
            'aircraft_departure' => 0,
            'passenger_arrival'  => 0,
            'passenger_departure'=> 0,
            'passenger_total'    => 0,
        ];

        $dataStartIndex = 3;
        foreach ($rawRows as $idx => $row) {
            if (isset($row[0]) && is_numeric($row[0]) && (int)$row[0] === 1 && !empty($row[1])) {
                $dataStartIndex = $idx;
                break;
            }
        }

        for ($i = $dataStartIndex; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if (empty($row) || count($row) < 7) continue;
            $first = $this->toStr($row[0] ?? '');
            $date  = $this->toStr($row[1] ?? '');

            if (stripos($first, 'TOTAL') !== false || stripos($date, 'TOTAL') !== false) {
                continue;
            }

            if (!is_numeric($first)) continue;

            $acArrDom = $this->toInt($row[2] ?? 0);
            $acArrInt = $this->toInt($row[3] ?? 0);
            $acArrTot = $this->toInt($row[4] ?? ($acArrDom + $acArrInt));

            $acDepDom = $this->toInt($row[5] ?? 0);
            $acDepInt = $this->toInt($row[6] ?? 0);
            $acDepTot = $this->toInt($row[7] ?? ($acDepDom + $acDepInt));

            $acTot    = $this->toInt($row[8] ?? ($acArrTot + $acDepTot));

            $pArrDom  = $this->toInt($row[9] ?? 0);
            $pArrInt  = $this->toInt($row[10] ?? 0);
            $pArrTot  = $this->toInt($row[11] ?? ($pArrDom + $pArrInt));

            $pDepDom  = $this->toInt($row[12] ?? 0);
            $pDepInt  = $this->toInt($row[13] ?? 0);
            $pDepTot  = $this->toInt($row[14] ?? ($pDepDom + $pDepInt));

            $pTot     = $this->toInt($row[15] ?? ($pArrTot + $pDepTot));

            $rec = [
                'no'                    => $this->toInt($first),
                'date'                  => $date,
                'aircraft_arr_domestic' => $acArrDom,
                'aircraft_arr_int'      => $acArrInt,
                'aircraft_arrival_tot'  => $acArrTot,
                'aircraft_dep_domestic' => $acDepDom,
                'aircraft_dep_int'      => $acDepInt,
                'aircraft_departure_tot'=> $acDepTot,
                'aircraft_total'        => $acTot,
                'passenger_arr_domestic'=> $pArrDom,
                'passenger_arr_int'     => $pArrInt,
                'passenger_arrival_tot' => $pArrTot,
                'passenger_dep_domestic'=> $pDepDom,
                'passenger_dep_int'     => $pDepInt,
                'passenger_departure_tot'=> $pDepTot,
                'passenger_total'       => $pTot,
            ];
            $records[] = $rec;

            $summary['total_movements']    += $acTot;
            $summary['aircraft_arrival']   += $acArrTot;
            $summary['aircraft_departure'] += $acDepTot;
            $summary['passenger_arrival']  += $pArrTot;
            $summary['passenger_departure']+= $pDepTot;
            $summary['passenger_total']    += $pTot;
        }

        return [
            'report_type'      => 'DAU12',
            'report_title'     => 'Data Statistik Angkutan Udara 2 (DAU-12)',
            'report_code'      => 'DAU-12',
            'meta'             => $meta,
            'summary'          => $summary,
            'records_count'    => count($records),
            'records'          => $records,
            'columns'          => [
                'No', 'Tanggal', 'Pesawat Arrival (DOM/INT/TOT)', 'Pesawat Departure (DOM/INT/TOT)',
                'Total Pesawat', 'Penumpang Arrival (DOM/INT/TOT)', 'Penumpang Departure (DOM/INT/TOT)', 'Total Penumpang'
            ],
        ];
    }
}
