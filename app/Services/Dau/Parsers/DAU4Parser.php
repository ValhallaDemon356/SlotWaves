<?php

namespace App\Services\Dau\Parsers;

class DAU4Parser extends BaseDauParser
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
            'passenger_transit'  => 0,
            'passenger_transfer' => 0,
            'passenger_total'    => 0,
            'crew_total'         => 0,
            'baggage_total'      => 0,
            'cargo_total'        => 0,
            'pos_total'          => 0,
        ];

        // Find data start (skip column numbering guide rows)
        $dataStartIndex = 3;
        foreach ($rawRows as $idx => $row) {
            if (isset($row[0]) && is_numeric($row[0]) && (int)$row[0] === 1 && !empty($row[1]) && !is_numeric($row[1])) {
                $dataStartIndex = $idx;
                break;
            }
        }

        for ($i = $dataStartIndex; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if (empty($row) || count($row) < 10) continue;
            $first = $this->toStr($row[0] ?? '');
            $second = $this->toStr($row[1] ?? '');

            if (stripos($first, 'TOTAL') !== false || stripos($second, 'TOTAL') !== false) {
                continue;
            }

            if (!is_numeric($first) || is_numeric($second)) continue;

            $arrAc  = $this->toInt($row[4] ?? 0);
            $depAc  = $this->toInt($row[5] ?? 0);
            $totAc  = $this->toInt($row[6] ?? ($arrAc + $depAc));
            $arrPax = $this->toInt($row[7] ?? 0);
            $depPax = $this->toInt($row[8] ?? 0);
            $transPax = $this->toInt($row[9] ?? 0);
            $trfPax   = $this->toInt($row[10] ?? 0);
            $totPax   = $this->toInt($row[11] ?? ($arrPax + $depPax + $transPax + $trfPax));
            $crew     = $this->toInt($row[12] ?? 0);
            $exCrew   = $this->toInt($row[13] ?? 0);
            $totCrew  = $this->toInt($row[14] ?? ($crew + $exCrew));
            $baggage  = $this->toInt($row[17] ?? 0);
            $cargo    = $this->toInt($row[20] ?? 0);
            $pos      = $this->toInt($row[23] ?? 0);

            $rec = [
                'no'                  => $this->toInt($first),
                'airport'             => $second,
                'city_code'           => $this->toStr($row[2] ?? ''),
                'city'                => $this->toStr($row[3] ?? ''),
                'aircraft_arrival'    => $arrAc,
                'aircraft_departure'  => $depAc,
                'aircraft_total'      => $totAc,
                'passenger_arrival'   => $arrPax,
                'passenger_departure' => $depPax,
                'passenger_transit'   => $transPax,
                'passenger_transfer'  => $trfPax,
                'passenger_total'     => $totPax,
                'crew'                => $crew,
                'extra_crew'          => $exCrew,
                'crew_total'          => $totCrew,
                'baggage'             => $baggage,
                'cargo'               => $cargo,
                'pos'                 => $pos,
            ];
            $records[] = $rec;

            $summary['total_movements']    += $totAc;
            $summary['aircraft_arrival']   += $arrAc;
            $summary['aircraft_departure'] += $depAc;
            $summary['passenger_arrival']  += $arrPax;
            $summary['passenger_departure']+= $depPax;
            $summary['passenger_transit']  += $transPax;
            $summary['passenger_transfer'] += $trfPax;
            $summary['passenger_total']    += $totPax;
            $summary['crew_total']         += $totCrew;
            $summary['baggage_total']      += $baggage;
            $summary['cargo_total']        += $cargo;
            $summary['pos_total']          += $pos;
        }

        return [
            'report_type'      => 'DAU4',
            'report_title'     => 'Data Angkutan Udara Menurut Asal/Tujuan (DAU-04)',
            'report_code'      => 'DAU-04',
            'meta'             => $meta,
            'summary'          => $summary,
            'records_count'    => count($records),
            'records'          => $records,
            'columns'          => [
                'No', 'Airport', 'Kode IATA', 'Kota', 'Pesawat (DTG/BRK/TOT)',
                'Penumpang (DTG/BRK/Transit/Transfer/TOT)', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }
}
