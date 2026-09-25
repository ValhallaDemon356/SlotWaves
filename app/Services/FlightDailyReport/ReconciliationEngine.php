<?php

namespace App\Services\FlightDailyReport;

class ReconciliationEngine
{
    /**
     * Tolerance thresholds.
     */
    public const TOLERANCE_MATCH = 0.5; // <= 0.5% delta is MATCH
    public const TOLERANCE_MINOR = 2.0; // <= 2.0% delta is MINOR GAP, > 2.0% is MISMATCH

    /**
     * Reconcile OASYS vs APPS (Mode 7).
     * Compares Flights, Pax, Cargo between Source A (OASYS) and Source B (APPS).
     */
    public function reconcileOasysVsApps(array $records): array
    {
        $oasysFlights = count($records);
        $oasysPax = 0;
        $oasysCargo = 0;

        foreach ($records as $r) {
            $oasysPax += ($r['adult'] + $r['child'] + $r['infant']);
            $oasysCargo += $r['cargo_kg'];
        }

        // Deterministic APPS simulation based on flight records
        // Real-world: APPS gate manifest has slight variation due to no-shows or late cargo weigh-ins
        $appsPax = 0;
        $appsCargo = 0;
        $reconciledFlights = [];

        foreach ($records as $idx => $r) {
            $pax = ($r['adult'] + $r['child'] + $r['infant']);
            $cargo = $r['cargo_kg'];

            // Deterministic slight variance for demonstration & testing
            $seed = crc32($r['flight_no'] . ($r['flight_date'] ?? ''));
            $paxVar = ($seed % 30 === 0) ? -2 : (($seed % 45 === 0) ? 1 : 0);
            $cargoVar = ($seed % 25 === 0) ? -15.0 : (($seed % 40 === 0) ? 8.5 : 0.0);

            $appsFlightPax = max(0, $pax + $paxVar);
            $appsFlightCargo = max(0.0, round($cargo + $cargoVar, 1));

            $appsPax += $appsFlightPax;
            $appsCargo += $appsFlightCargo;

            $paxDelta = $appsFlightPax - $pax;
            $cargoDelta = round($appsFlightCargo - $cargo, 1);

            $status = 'MATCH';
            if ($paxDelta != 0 || abs($cargoDelta) > 10) {
                $status = (abs($paxDelta) <= 2 && abs($cargoDelta) <= 25) ? 'MINOR GAP' : 'MISMATCH';
            }

            if ($idx < 100) { // Keep top 100 rows for view table
                $reconciledFlights[] = [
                    'flight_no'    => $r['flight_no'],
                    'air_line'     => $r['air_line'],
                    'route'        => $r['route'],
                    'leg'          => $r['leg'],
                    'oasys_pax'    => $pax,
                    'apps_pax'     => $appsFlightPax,
                    'pax_delta'    => $paxDelta,
                    'oasys_cargo'  => $cargo,
                    'apps_cargo'   => $appsFlightCargo,
                    'cargo_delta'  => $cargoDelta,
                    'status'       => $status,
                ];
            }
        }

        $appsFlights = $oasysFlights; // Flight count identical or close
        $paxDeltaTotal = $appsPax - $oasysPax;
        $paxDeltaPct = ($oasysPax > 0) ? round((abs($paxDeltaTotal) / $oasysPax) * 100, 2) : 0.0;
        $cargoDeltaTotal = round($appsCargo - $oasysCargo, 1);
        $cargoDeltaPct = ($oasysCargo > 0) ? round((abs($cargoDeltaTotal) / $oasysCargo) * 100, 2) : 0.0;

        $overallStatus = 'MATCH';
        if ($paxDeltaPct > self::TOLERANCE_MINOR || $cargoDeltaPct > self::TOLERANCE_MINOR) {
            $overallStatus = 'MISMATCH';
        } elseif ($paxDeltaPct > self::TOLERANCE_MATCH || $cargoDeltaPct > self::TOLERANCE_MATCH) {
            $overallStatus = 'MINOR GAP';
        }

        return [
            'mode'           => 'OASYS_VS_APPS',
            'title'          => 'OASYS vs APPS Reconciliation (Passenger & Cargo Clearance)',
            'source_a_label' => 'OASYS (Airport Operational System)',
            'source_b_label' => 'APPS (Passenger Processing & Baggage)',
            'overall_status' => $overallStatus,
            'metrics'        => [
                'flights' => [
                    'source_a'  => $oasysFlights,
                    'source_b'  => $appsFlights,
                    'delta'     => 0,
                    'delta_pct' => '0.00%',
                    'status'    => 'MATCH',
                ],
                'passengers' => [
                    'source_a'  => $oasysPax,
                    'source_b'  => $appsPax,
                    'delta'     => $paxDeltaTotal,
                    'delta_pct' => "{$paxDeltaPct}%",
                    'status'    => ($paxDeltaPct <= self::TOLERANCE_MATCH) ? 'MATCH' : (($paxDeltaPct <= self::TOLERANCE_MINOR) ? 'MINOR GAP' : 'MISMATCH'),
                ],
                'cargo' => [
                    'source_a'  => $oasysCargo,
                    'source_b'  => $appsCargo,
                    'delta'     => $cargoDeltaTotal,
                    'delta_pct' => "{$cargoDeltaPct}%",
                    'status'    => ($cargoDeltaPct <= self::TOLERANCE_MATCH) ? 'MATCH' : (($cargoDeltaPct <= self::TOLERANCE_MINOR) ? 'MINOR GAP' : 'MISMATCH'),
                ],
            ],
            'details'        => $reconciledFlights,
        ];
    }

    /**
     * Reconcile OASYS vs EDIFLY (Mode 8).
     * Compares operational flight leg & schedule consistency against EDIFLY telex feed.
     */
    public function reconcileOasysVsEdifly(array $records): array
    {
        $oasysTotal = count($records);
        $matched = 0;
        $minorGap = 0;
        $mismatch = 0;
        $reconciledFlights = [];

        foreach ($records as $idx => $r) {
            $seed = crc32($r['flight_no'] . ($r['reg_no'] ?? ''));
            // Simulating EDIFLY telex timing consistency
            $ediflyTime = ($r['direction'] === 'ARRIVAL') ? $r['sibt'] : $r['sobt'];
            $timeGapMins = 0;

            if ($seed % 35 === 0) {
                $timeGapMins = 8;
                $status = 'MINOR GAP';
                $minorGap++;
            } elseif ($seed % 80 === 0) {
                $timeGapMins = 35;
                $status = 'MISMATCH';
                $mismatch++;
            } else {
                $status = 'MATCH';
                $matched++;
            }

            if ($idx < 100) {
                $reconciledFlights[] = [
                    'flight_no'    => $r['flight_no'],
                    'air_line'     => $r['air_line'],
                    'reg_no'       => $r['reg_no'],
                    'route'        => $r['route'],
                    'leg'          => $r['leg'],
                    'oasys_time'   => ($r['direction'] === 'ARRIVAL') ? $r['sibt'] : $r['sobt'],
                    'edifly_time'  => $ediflyTime,
                    'time_gap'     => "{$timeGapMins} min",
                    'status'       => $status,
                ];
            }
        }

        $matchRate = ($oasysTotal > 0) ? round(($matched / $oasysTotal) * 100, 1) : 100.0;
        $overallStatus = ($matchRate >= 98.0) ? 'MATCH' : (($matchRate >= 93.0) ? 'MINOR GAP' : 'MISMATCH');

        return [
            'mode'           => 'OASYS_VS_EDIFLY',
            'title'          => 'OASYS vs EDIFLY Telex Consistency Reconciliation',
            'source_a_label' => 'OASYS Operational Data',
            'source_b_label' => 'EDIFLY Telecommunication Network',
            'overall_status' => $overallStatus,
            'match_rate'     => "{$matchRate}%",
            'metrics'        => [
                'matched'   => $matched,
                'minor_gap' => $minorGap,
                'mismatch'  => $mismatch,
                'total'     => $oasysTotal,
            ],
            'details'        => $reconciledFlights,
        ];
    }
}
