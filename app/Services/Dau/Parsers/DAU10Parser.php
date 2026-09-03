<?php

namespace App\Services\Dau\Parsers;

class DAU10Parser extends BaseDauParser
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

        $dataStartIndex = 3;
        foreach ($rawRows as $idx => $row) {
            if (isset($row[0]) && is_numeric($row[0]) && (int)$row[0] === 1 && !empty($row[2])) {
                $dataStartIndex = $idx;
                break;
            }
        }

        $currentHour = '';

        for ($i = $dataStartIndex; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if (empty($row) || count($row) < 8) continue;
            $first  = $this->toStr($row[0] ?? '');
            $hour   = $this->toStr($row[1] ?? '');
            $term   = $this->toStr($row[2] ?? '');

            if (stripos($first, 'TOTAL') !== false || stripos($hour, 'TOTAL') !== false || stripos($term, 'TOTAL') !== false) {
                continue;
            }

            if (!is_numeric($first)) continue;

            if (!empty($hour)) {
                $currentHour = $hour;
            }

            $arrAc  = $this->toInt($row[3] ?? 0);
            $depAc  = $this->toInt($row[4] ?? 0);
            $totAc  = $this->toInt($row[5] ?? ($arrAc + $depAc));
            $arrPax = $this->toInt($row[6] ?? 0);
            $depPax = $this->toInt($row[7] ?? 0);
            $transPax = $this->toInt($row[8] ?? 0);
            $trfPax   = $this->toInt($row[9] ?? 0);
            $totPax   = $this->toInt($row[10] ?? ($arrPax + $depPax + $transPax + $trfPax));
            $crew     = $this->toInt($row[11] ?? 0);
            $exCrew   = $this->toInt($row[12] ?? 0);
            $totCrew  = $this->toInt($row[13] ?? ($crew + $exCrew));
            $baggage  = $this->toInt($row[16] ?? 0);
            $cargo    = $this->toInt($row[19] ?? 0);
            $pos      = $this->toInt($row[22] ?? 0);

            $rec = [
                'no'                  => $this->toInt($first),
                'hour'                => $currentHour,
                'terminal'            => $term,
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
            'report_type'      => 'DAU10',
            'report_title'     => 'Data Angkutan Udara Jam Puncak Pesawat/Penumpang (DAU-10)',
            'report_code'      => 'DAU-10',
            'meta'             => $meta,
            'summary'          => $summary,
            'records_count'    => count($records),
            'records'          => $records,
            'columns'          => [
                'No', 'Jam (Period)', 'Terminal', 'Pesawat (DTG/BRK/TOT)', 'Penumpang (DTG/BRK/Transit/Transfer/TOT)',
                'Awak (Crew/Ex Crew/TOT)', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }
}
