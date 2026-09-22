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

        $peakIntelligence = self::calculatePeakHours($records);

        return [
            'report_type'       => 'DAU10',
            'report_title'      => 'Data Angkutan Udara Jam Puncak Pesawat/Penumpang (DAU-10)',
            'report_code'       => 'DAU-10',
            'meta'              => $meta,
            'summary'           => $summary,
            'peak_intelligence' => $peakIntelligence,
            'peak_hours'        => $peakIntelligence,
            'records_count'     => count($records),
            'records'           => $records,
            'columns'           => [
                'No', 'Jam (Period)', 'Terminal', 'Pesawat (DTG/BRK/TOT)', 'Penumpang (DTG/BRK/Transit/Transfer/TOT)',
                'Awak (Crew/Ex Crew/TOT)', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }

    /**
     * Precompute Top 3 Peak Hours for Aircraft Movements and Passengers.
     */
    public static function calculatePeakHours(array $records): array
    {
        $hourlyMap = [];

        foreach ($records as $r) {
            $h = trim($r['hour'] ?? $r['period'] ?? '');
            if ($h === '') continue;

            if (!isset($hourlyMap[$h])) {
                $hourlyMap[$h] = [
                    'hour'                => $h,
                    'aircraft_arrival'    => 0,
                    'aircraft_departure'  => 0,
                    'aircraft_total'      => 0,
                    'passenger_arrival'   => 0,
                    'passenger_departure' => 0,
                    'passenger_total'     => 0,
                ];
            }

            $hourlyMap[$h]['aircraft_arrival']    += (int)($r['aircraft_arrival'] ?? 0);
            $hourlyMap[$h]['aircraft_departure']  += (int)($r['aircraft_departure'] ?? 0);
            $hourlyMap[$h]['aircraft_total']      += (int)($r['aircraft_total'] ?? 0);
            $hourlyMap[$h]['passenger_arrival']   += (int)($r['passenger_arrival'] ?? 0);
            $hourlyMap[$h]['passenger_departure'] += (int)($r['passenger_departure'] ?? 0);
            $hourlyMap[$h]['passenger_total']     += (int)($r['passenger_total'] ?? 0);
        }

        $allHours = array_values($hourlyMap);

        // Sort for Top 3 Aircraft Peak Hours
        $byAcft = $allHours;
        usort($byAcft, fn($a, $b) => $b['aircraft_total'] <=> $a['aircraft_total']);
        $top3Acft = array_slice($byAcft, 0, 3);

        // Sort for Top 3 Passenger Peak Hours
        $byPax = $allHours;
        usort($byPax, fn($a, $b) => $b['passenger_total'] <=> $a['passenger_total']);
        $top3Pax = array_slice($byPax, 0, 3);

        // Format Rank 1, 2, 3 Badges
        $rankLabels = ['1st Peak', '2nd Peak', '3rd Peak'];
        $acftBadges = [];
        foreach ($top3Acft as $idx => $item) {
            $acftBadges[] = [
                'rank'   => $idx + 1,
                'label'  => $rankLabels[$idx] ?? ("#" . ($idx + 1)),
                'hour'   => $item['hour'],
                'value'  => $item['aircraft_total'],
                'volume' => $item['aircraft_total'],
                'arr'    => $item['aircraft_arrival'],
                'dep'    => $item['aircraft_departure'],
            ];
        }

        $paxBadges = [];
        foreach ($top3Pax as $idx => $item) {
            $paxBadges[] = [
                'rank'   => $idx + 1,
                'label'  => $rankLabels[$idx] ?? ("#" . ($idx + 1)),
                'hour'   => $item['hour'],
                'value'  => $item['passenger_total'],
                'volume' => $item['passenger_total'],
                'arr'    => $item['passenger_arrival'],
                'dep'    => $item['passenger_departure'],
            ];
        }

        return [
            'top3_aircraft_peaks'  => $acftBadges,
            'top3_aircraft'        => $acftBadges,
            'top3_passenger_peaks' => $paxBadges,
            'top3_passengers'      => $paxBadges,
            'hourly_timeline'      => $allHours,
        ];
    }
}

