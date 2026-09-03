<?php

namespace App\Services\Dau\Parsers;

class DAU3Parser extends BaseDauParser
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

        $currentSection = 'NIAGA';

        foreach ($rawRows as $row) {
            if (empty($row) || count($row) < 3) continue;
            $first = $this->toStr($row[0] ?? '');
            $second = $this->toStr($row[1] ?? '');

            // Section markers
            if (stripos($first, 'A. NIAGA') !== false || stripos($first, 'NIAGA') !== false) {
                $currentSection = 'NIAGA';
                continue;
            }
            if (stripos($first, 'B. BUKAN NIAGA') !== false || stripos($first, 'BUKAN NIAGA') !== false) {
                $currentSection = 'BUKAN NIAGA';
                continue;
            }

            $isData = (is_numeric($first) && in_array(strtoupper($second), ['DOMESTIK', 'INTERNASIONAL'])) ||
                      strtoupper($first) === 'DOMESTIK' || strtoupper($first) === 'INTERNASIONAL';

            if ($isData && count($row) >= 10) {
                $category = !empty($second) && !is_numeric($second) ? $second : $first;
                $offset = is_numeric($first) ? 2 : 1;

                $rec = [
                    'section'             => $currentSection,
                    'category'            => strtoupper($category),
                    'aircraft_arrival'    => $this->toInt($row[$offset] ?? 0),
                    'aircraft_departure'  => $this->toInt($row[$offset+1] ?? 0),
                    'aircraft_total'      => $this->toInt($row[$offset+2] ?? 0),
                    'passenger_arrival'   => $this->toInt($row[$offset+3] ?? 0),
                    'passenger_departure' => $this->toInt($row[$offset+4] ?? 0),
                    'passenger_transit'   => $this->toInt($row[$offset+5] ?? 0),
                    'passenger_transfer'  => $this->toInt($row[$offset+6] ?? 0),
                    'passenger_total'     => $this->toInt($row[$offset+7] ?? 0),
                    'crew'                => $this->toInt($row[$offset+8] ?? 0),
                    'extra_crew'          => $this->toInt($row[$offset+9] ?? 0),
                    'crew_total'          => $this->toInt($row[$offset+10] ?? 0),
                    'baggage'             => $this->toInt($row[$offset+13] ?? 0),
                    'cargo'               => $this->toInt($row[$offset+16] ?? 0),
                    'pos'                 => $this->toInt($row[$offset+19] ?? 0),
                ];
                $records[] = $rec;

                $summary['total_movements']    += $rec['aircraft_total'];
                $summary['aircraft_arrival']   += $rec['aircraft_arrival'];
                $summary['aircraft_departure'] += $rec['aircraft_departure'];
                $summary['passenger_arrival']  += $rec['passenger_arrival'];
                $summary['passenger_departure']+= $rec['passenger_departure'];
                $summary['passenger_transit']  += $rec['passenger_transit'];
                $summary['passenger_transfer'] += $rec['passenger_transfer'];
                $summary['passenger_total']    += $rec['passenger_total'];
                $summary['crew_total']         += $rec['crew_total'];
                $summary['baggage_total']      += $rec['baggage'];
                $summary['cargo_total']        += $rec['cargo'];
                $summary['pos_total']          += $rec['pos'];
            }
        }

        return [
            'report_type'      => 'DAU3',
            'report_title'     => 'Data Angkutan Udara Menurut Status Penerbangan (DAU-03)',
            'report_code'      => 'DAU-03',
            'meta'             => $meta,
            'summary'          => $summary,
            'records_count'    => count($records),
            'records'          => $records,
            'columns'          => [
                'Status (Niaga/Bukan Niaga)', 'Jenis Penerbangan', 'Pesawat (DTG/BRK/TOT)',
                'Penumpang (DTG/BRK/Transit/Transfer/TOT)', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }
}
