<?php

namespace App\Services\Dau\Parsers;

class DAU1Parser extends BaseDauParser
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
            'baggage_total'      => 0,
            'cargo_total'        => 0,
            'pos_total'          => 0,
        ];

        // Find header row and data start
        $dataStartIndex = 4;
        foreach ($rawRows as $idx => $row) {
            if (isset($row[0]) && is_numeric($row[0]) && (int)$row[0] === 1 && !empty($row[2])) {
                $dataStartIndex = $idx;
                break;
            }
        }

        for ($i = $dataStartIndex; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if (empty($row) || count($row) < 10) continue;

            $firstCell = $this->toStr($row[0] ?? '');
            $secondCell = $this->toStr($row[1] ?? '');

            // Skip total or subtotal row
            if (stripos($firstCell, 'TOTAL') !== false || stripos($secondCell, 'TOTAL') !== false) {
                continue;
            }

            if (!is_numeric($firstCell) && empty($row[2])) {
                continue;
            }

            $arrAc = $this->toInt($row[6] ?? 0);
            $depAc = $this->toInt($row[7] ?? 0);
            $totAc = $this->toInt($row[8] ?? ($arrAc + $depAc));

            // Passenger datang (Dewasa + Anak + Bayi)
            $pArrDewasa = $this->toInt($row[9] ?? 0);
            $pArrAnak   = $this->toInt($row[10] ?? 0);
            $pArrBayi   = $this->toInt($row[11] ?? 0);
            $pArrTotal  = $pArrDewasa + $pArrAnak + $pArrBayi;

            // Passenger berangkat (Dewasa + Anak + Bayi)
            $pDepDewasa = $this->toInt($row[12] ?? 0);
            $pDepAnak   = $this->toInt($row[13] ?? 0);
            $pDepBayi   = $this->toInt($row[14] ?? 0);
            $pDepTotal  = $pDepDewasa + $pDepAnak + $pDepBayi;

            // Transit & Transfer
            $pTransitTotal  = $this->toInt($row[15] ?? 0) + $this->toInt($row[16] ?? 0) + $this->toInt($row[17] ?? 0);
            $pTransferTotal = $this->toInt($row[18] ?? 0) + $this->toInt($row[19] ?? 0) + $this->toInt($row[20] ?? 0);
            $pTotal = $this->toInt($row[21] ?? ($pArrTotal + $pDepTotal + $pTransitTotal + $pTransferTotal));

            // Bagasi, Kargo, Pos
            $baggage = $this->toInt($row[22] ?? 0) + $this->toInt($row[23] ?? 0);
            $cargo   = $this->toInt($row[24] ?? 0) + $this->toInt($row[25] ?? 0) + $this->toInt($row[26] ?? 0);
            $pos     = $this->toInt($row[27] ?? 0) + $this->toInt($row[28] ?? 0);

            // Infer Airline code from flight number (e.g. 3Y 835 -> 3Y, GA 123 -> GA)
            $flNum = $this->toStr($row[2] ?? '');
            $airCode = '';
            if (preg_match('/^([A-Z0-9]{2,3})\s*/i', $flNum, $m)) {
                $airCode = strtoupper(trim($m[1]));
            }

            $rec = [
                'no'                   => $this->toInt($firstCell),
                'airport_route'        => $secondCell,
                'origin'               => $secondCell,
                'flight_number'        => $flNum,
                'airline_code'         => $airCode,
                'schedule_type'        => $this->toStr($row[3] ?? ''),
                'aircraft_type'        => $this->toStr($row[4] ?? ''),
                'seat_capacity'        => $this->toInt($row[5] ?? 0),
                'aircraft_arrival'     => $arrAc,
                'aircraft_departure'   => $depAc,
                'aircraft_total'       => $totAc,
                'passenger_arrival'    => $pArrTotal,
                'passenger_departure'  => $pDepTotal,
                'passenger_transit'    => $pTransitTotal,
                'passenger_transfer'   => $pTransferTotal,
                'passenger_total'      => $pTotal,
                'baggage'              => $baggage,
                'cargo'                => $cargo,
                'pos'                  => $pos,
                'details'              => [
                    'arr_adult' => $pArrDewasa, 'arr_child' => $pArrAnak, 'arr_infant' => $pArrBayi,
                    'dep_adult' => $pDepDewasa, 'dep_child' => $pDepAnak, 'dep_infant' => $pDepBayi,
                ]
            ];

            $records[] = $rec;

            // Accumulate summary
            $summary['total_movements']    += $totAc;
            $summary['aircraft_arrival']   += $arrAc;
            $summary['aircraft_departure'] += $depAc;
            $summary['passenger_arrival']  += $pArrTotal;
            $summary['passenger_departure']+= $pDepTotal;
            $summary['passenger_transit']  += $pTransitTotal;
            $summary['passenger_transfer'] += $pTransferTotal;
            $summary['passenger_total']    += $pTotal;
            $summary['baggage_total']      += $baggage;
            $summary['cargo_total']        += $cargo;
            $summary['pos_total']          += $pos;
        }

        return [
            'report_type'      => 'DAU1',
            'report_title'     => 'Data Lalu Lintas Angkutan Udara (DAU-01)',
            'report_code'      => 'DAU-01',
            'meta'             => $meta,
            'summary'          => $summary,
            'records_count'    => count($records),
            'records'          => $records,
            'columns'          => [
                'No', 'Bandara Asal/Tujuan', 'Flight No', 'Status', 'Tipe Pesawat',
                'Kapasitas Kursi', 'Pesawat (DTG/BRK/TOT)', 'Penumpang (DTG/BRK/TOT)',
                'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }
}
