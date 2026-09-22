<?php

namespace App\Services\Dau\Parsers;

class DAU5BParser extends BaseDauParser
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

        for ($i = $dataStartIndex; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if (empty($row) || count($row) < 10) continue;
            $first = $this->toStr($row[0] ?? '');
            $term  = $this->toStr($row[1] ?? '');
            $airl  = $this->toStr($row[2] ?? '');

            if (stripos($first, 'TOTAL') !== false || stripos($term, 'TOTAL') !== false || stripos($airl, 'TOTAL') !== false) {
                continue;
            }

            if (!is_numeric($first)) continue;

            $arrAc  = $this->toInt($row[3] ?? 0);
            $depAc  = $this->toInt($row[4] ?? 0);
            $totAc  = $this->toInt($row[5] ?? ($arrAc + $depAc));
            $arrPax = $this->toInt($row[6] ?? 0);
            $depPax = $this->toInt($row[7] ?? 0);
            $transPax = $this->toInt($row[8] ?? 0);
            $trfPax   = $this->toInt($row[9] ?? 0);
            $totPax   = $this->toInt($row[10] ?? ($arrPax + $depPax + $transPax + $trfPax));

            $crew      = $this->toInt($row[11] ?? 0);
            $arrExCrew = $this->toInt($row[12] ?? 0);
            $depExCrew = $this->toInt($row[13] ?? 0);
            $totExCrew = $this->toInt($row[14] ?? ($arrExCrew + $depExCrew));
            $totCrew   = $this->toInt($row[15] ?? ($crew + $totExCrew));

            $baggage  = $this->toInt($row[18] ?? 0);
            $cargo    = $this->toInt($row[21] ?? 0);
            $pos      = $this->toInt($row[24] ?? 0);

            $rec = [
                'no'                  => $this->toInt($first),
                'terminal'            => $term,
                'airline'             => $airl,
                'aircraft_arrival'    => $arrAc,
                'aircraft_departure'  => $depAc,
                'aircraft_total'      => $totAc,
                'passenger_arrival'   => $arrPax,
                'passenger_departure' => $depPax,
                'passenger_transit'   => $transPax,
                'passenger_transfer'  => $trfPax,
                'passenger_total'     => $totPax,
                'crew'                => $crew,
                'arr_extra_crew'      => $arrExCrew,
                'dep_extra_crew'      => $depExCrew,
                'extra_crew'          => $totExCrew,
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

        $terminalAllocation = self::calculateTerminalAllocation($records);

        return [
            'report_type'         => 'DAU5B',
            'report_title'        => 'Data Angkutan Udara Menurut Terminal & Airline (DAU-05B)',
            'report_code'         => 'DAU-05B',
            'meta'                => $meta,
            'summary'             => $summary,
            'terminal_allocation' => $terminalAllocation,
            'records_count'       => count($records),
            'records'             => $records,
            'columns'             => [
                'No', 'Terminal', 'Airline / Operator', 'Pesawat (DTG/BRK/TOT)', 'Penumpang (DTG/BRK/Transit/Transfer/TOT)',
                'Awak (Crew/Arr E.Crew/Dep E.Crew/Total)', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }

    /**
     * Precompute Terminal Workload Allocation across Airlines (100% Stacked Bar structure).
     */
    public static function calculateTerminalAllocation(array $records): array
    {
        $terminals = [];
        $airlineTotals = [];

        foreach ($records as $r) {
            $term = trim($r['terminal'] ?? 'Unknown');
            $al = trim($r['airline'] ?? 'Unknown');
            $mv = (int)($r['aircraft_total'] ?? 0);
            $px = (int)($r['passenger_total'] ?? 0);

            if (!isset($terminals[$term])) {
                $terminals[$term] = [
                    'terminal'        => $term,
                    'total_movements' => 0,
                    'total_pax'       => 0,
                    'airlines'        => [],
                ];
            }

            $terminals[$term]['total_movements'] += $mv;
            $terminals[$term]['total_pax']       += $px;

            if (!isset($terminals[$term]['airlines'][$al])) {
                $terminals[$term]['airlines'][$al] = [
                    'airline'   => $al,
                    'movements' => 0,
                    'pax'       => 0,
                    'share_pct' => 0.0,
                ];
            }
            $terminals[$term]['airlines'][$al]['movements'] += $mv;
            $terminals[$term]['airlines'][$al]['pax']       += $px;

            $airlineTotals[$al] = ($airlineTotals[$al] ?? 0) + $mv;
        }

        // Calculate 100% stacked proportions
        foreach ($terminals as &$tData) {
            $tot = $tData['total_movements'];
            $alList = array_values($tData['airlines']);
            usort($alList, fn($a, $b) => $b['movements'] <=> $a['movements']);

            foreach ($alList as &$alItem) {
                $alItem['share_pct'] = $tot > 0 ? round(($alItem['movements'] / $tot) * 100, 1) : 0.0;
            }
            unset($alItem);

            $tData['airlines'] = $alList;
        }
        unset($tData);

        // Sort terminals alphabetically or naturally
        ksort($terminals);

        // Top airlines overall
        arsort($airlineTotals);
        $topAirlines = array_slice(array_keys($airlineTotals), 0, 10);

        return [
            'terminals'    => $terminals,
            'top_airlines' => $topAirlines,
        ];
    }
}

