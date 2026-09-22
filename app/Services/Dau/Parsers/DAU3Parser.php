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

            // Section markers - check BUKAN NIAGA first before NIAGA substring match
            if (stripos($first, 'B. BUKAN NIAGA') !== false || stripos($first, 'BUKAN NIAGA') !== false) {
                $currentSection = 'BUKAN NIAGA';
                continue;
            }
            if (stripos($first, 'A. NIAGA') !== false || stripos($first, 'NIAGA') !== false) {
                $currentSection = 'NIAGA';
                continue;
            }

            $isData = (is_numeric($first) && in_array(strtoupper($second), ['DOMESTIK', 'INTERNASIONAL'])) ||
                      strtoupper($first) === 'DOMESTIK' || strtoupper($first) === 'INTERNASIONAL';

            if ($isData && count($row) >= 10) {
                $category = !empty($second) && !is_numeric($second) ? $second : $first;
                $offset = is_numeric($first) ? 2 : 1;

                $isScheduled = (stripos($currentSection, 'BUKAN') === false);

                $rec = [
                    'section'             => $currentSection,
                    'category'            => strtoupper($category),
                    'is_scheduled'        => $isScheduled,
                    'flight_status'       => $isScheduled ? 'SCHEDULED_COMMERCIAL' : 'NON_SCHEDULED',
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

        $regularity = self::calculateRegularity($records, $summary);

        return [
            'report_type'      => 'DAU3',
            'report_title'     => 'Data Angkutan Udara Menurut Status Penerbangan (DAU-03)',
            'report_code'      => 'DAU-03',
            'meta'             => $meta,
            'summary'          => $summary,
            'regularity'       => $regularity,
            'records_count'    => count($records),
            'records'          => $records,
            'columns'          => [
                'Status (Niaga/Bukan Niaga)', 'Jenis Penerbangan', 'Pesawat (DTG/BRK/TOT)',
                'Penumpang (DTG/BRK/Transit/Transfer/TOT)', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }

    /**
     * Compute regularity rate, non-scheduled apron impact, and cancellation metrics.
     */
    public static function calculateRegularity(array $records, array $summary): array
    {
        $schedAcft = 0;
        $nonSchedAcft = 0;
        $schedPax = 0;
        $nonSchedPax = 0;

        foreach ($records as $r) {
            $isSched = !empty($r['is_scheduled']) || (stripos($r['section'] ?? '', 'BUKAN') === false);
            $ac = (int)($r['aircraft_total'] ?? 0);
            $pax = (int)($r['passenger_total'] ?? 0);

            if ($isSched) {
                $schedAcft += $ac;
                $schedPax  += $pax;
            } else {
                $nonSchedAcft += $ac;
                $nonSchedPax  += $pax;
            }
        }

        $totAcft = $schedAcft + $nonSchedAcft;
        $totPax  = $schedPax + $nonSchedPax;

        $regularityRate = $totAcft > 0 ? round(($schedAcft / $totAcft) * 100, 1) : 100.0;
        $extraFlightPct = $totAcft > 0 ? round(($nonSchedAcft / $totAcft) * 100, 1) : 0.0;
        $extraFlightImpact = $extraFlightPct > 5.0;

        return [
            'scheduled_acft'      => $schedAcft,
            'scheduledAcft'        => $schedAcft,
            'non_scheduled_acft'  => $nonSchedAcft,
            'nonScheduledAcft'    => $nonSchedAcft,
            'scheduled_pax'       => $schedPax,
            'scheduledPax'        => $schedPax,
            'non_scheduled_pax'   => $nonSchedPax,
            'nonScheduledPax'     => $nonSchedPax,
            'total_acft'          => $totAcft,
            'totalAcft'           => $totAcft,
            'regularity_rate'     => $regularityRate,
            'regularityRate'      => $regularityRate,
            'scheduled_pct'       => $regularityRate,
            'scheduledPct'        => $regularityRate,
            'extra_flight_pct'    => $extraFlightPct,
            'extraFlightPct'      => $extraFlightPct,
            'non_scheduled_pct'   => $extraFlightPct,
            'nonScheduledPct'     => $extraFlightPct,
            'extra_flight_impact' => $extraFlightImpact,
            'extraFlightImpact'   => $extraFlightImpact,
            'stand_stress_alert'  => $extraFlightImpact,
            'cancellation_rate'   => 'N/A',
            'cancellationRate'    => 'N/A',
            'has_cancellation'    => false,
            'hasCancellation'     => false,
            'cancellation_tracked'=> false,
            'cancellationTracked' => false,
            'cancellation_reason' => 'Data pembatalan tidak tercatat dalam arsip DAU-03',
        ];
    }
}
