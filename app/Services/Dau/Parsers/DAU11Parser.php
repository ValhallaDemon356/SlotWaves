<?php

namespace App\Services\Dau\Parsers;

class DAU11Parser extends BaseDauParser
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

            $acIntArr = $this->toInt($row[2] ?? 0);
            $acIntDep = $this->toInt($row[3] ?? 0);
            $acDomArr = $this->toInt($row[4] ?? 0);
            $acDomDep = $this->toInt($row[5] ?? 0);
            $totAc    = $this->toInt($row[6] ?? ($acIntArr + $acIntDep + $acDomArr + $acDomDep));

            $pIntArr  = $this->toInt($row[7] ?? 0);
            $pIntDep  = $this->toInt($row[8] ?? 0);
            $pIntTrn  = $this->toInt($row[9] ?? 0);
            $pIntTrf  = $this->toInt($row[10] ?? 0);
            $pIntCrw  = $this->toInt($row[11] ?? 0);
            $pIntExCr = $this->toInt($row[12] ?? 0);

            $pDomArr  = $this->toInt($row[13] ?? 0);
            $pDomDep  = $this->toInt($row[14] ?? 0);
            $pDomTrn  = $this->toInt($row[15] ?? 0);
            $pDomTrf  = $this->toInt($row[16] ?? 0);
            $pDomCrw  = $this->toInt($row[17] ?? 0);
            $pDomExCr = $this->toInt($row[18] ?? 0);

            $totPax   = $this->toInt($row[19] ?? ($pIntArr + $pIntDep + $pIntTrn + $pIntTrf + $pDomArr + $pDomDep + $pDomTrn + $pDomTrf));

            $rec = [
                'no'                     => $this->toInt($first),
                'date'                   => $date,
                'aircraft_int_arrival'   => $acIntArr,
                'aircraft_int_departure' => $acIntDep,
                'aircraft_dom_arrival'   => $acDomArr,
                'aircraft_dom_departure' => $acDomDep,
                'aircraft_total'         => $totAc,
                'passenger_int_arrival'  => $pIntArr,
                'passenger_int_departure'=> $pIntDep,
                'passenger_int_transit'  => $pIntTrn,
                'passenger_int_transfer' => $pIntTrf,
                'passenger_int_crew'     => $pIntCrw + $pIntExCr,
                'passenger_dom_arrival'  => $pDomArr,
                'passenger_dom_departure'=> $pDomDep,
                'passenger_dom_transit'  => $pDomTrn,
                'passenger_dom_transfer' => $pDomTrf,
                'passenger_dom_crew'     => $pDomCrw + $pDomExCr,
                'passenger_total'        => $totPax,
            ];
            $records[] = $rec;

            $summary['total_movements']    += $totAc;
            $summary['aircraft_arrival']   += ($acIntArr + $acDomArr);
            $summary['aircraft_departure'] += ($acIntDep + $acDomDep);
            $summary['passenger_arrival']  += ($pIntArr + $pDomArr);
            $summary['passenger_departure']+= ($pIntDep + $pDomDep);
            $summary['passenger_transit']  += ($pIntTrn + $pDomTrn);
            $summary['passenger_transfer'] += ($pIntTrf + $pDomTrf);
            $summary['passenger_total']    += $totPax;
            $summary['crew_total']         += ($pIntCrw + $pIntExCr + $pDomCrw + $pDomExCr);
        }

        return [
            'report_type'      => 'DAU11',
            'report_title'     => 'Data Statistik Angkutan Udara 1 (DAU-11)',
            'report_code'      => 'DAU-11',
            'meta'             => $meta,
            'summary'          => $summary,
            'records_count'    => count($records),
            'records'          => $records,
            'columns'          => [
                'No', 'Tanggal', 'Pesawat INT (DTG/BKT)', 'Pesawat DOM (DTG/BKT)', 'Total Pesawat',
                'Penumpang INT (DTG/BKT/TRN/TRF/CRW)', 'Penumpang DOM (DTG/BKT/TRN/TRF/CRW)', 'Total Penumpang'
            ],
        ];
    }
}
