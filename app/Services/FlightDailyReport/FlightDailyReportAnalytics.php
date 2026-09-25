<?php

namespace App\Services\FlightDailyReport;

use Carbon\Carbon;

class FlightDailyReportAnalytics
{
    protected HourlyChartService $hourlyChartService;
    protected ReconciliationEngine $reconciliationEngine;

    public function __construct(
        HourlyChartService $hourlyChartService = null,
        ReconciliationEngine $reconciliationEngine = null
    ) {
        $this->hourlyChartService = $hourlyChartService ?: new HourlyChartService();
        $this->reconciliationEngine = $reconciliationEngine ?: new ReconciliationEngine();
    }

    /**
     * Compute comprehensive presentation-ready operational metrics from FDR records.
     */
    public function compute(array $records, array|string $meta = [], array|int $options = []): array
    {
        if (is_string($meta)) {
            $meta = ['airport' => $meta];
        }
        if (is_int($options)) {
            $options = ['report_mode' => $options];
        }

        $reportMode = (int)($options['report_mode'] ?? 1);
        $airportCode = $meta['airport'] ?? 'CGK';

        // 1. 3 Mentor Hourly Charts
        $hourlyCharts = $this->hourlyChartService->buildHourlyCharts($records, $airportCode);

        // 2. Top KPI Metric Cards (attaching peak hour)
        $kpis = $this->computeTopKpis($records);
        $kpis['peak_hour'] = $hourlyCharts['peak_hour'] ?? null;

        // 3. Schedule vs Realization Analysis
        $schedVsReal = $this->computeScheduleVsRealization($records);

        // 4. Passenger Analytics & Multi-Day Trend
        $paxAnalytics = $this->computePassengerAnalytics($records);

        // 5. Airline & Route Performance
        $airlineRoute = $this->computeAirlineRoutePerformance($records);

        // 6. Ground Operations (Stand & Runway) & Irregularities
        $groundOps = $this->computeGroundOps($records);

        // 7. Reconciliation Engine (Modes 7 & 8)
        $reconciliationApps = $this->reconciliationEngine->reconcileOasysVsApps($records);
        $reconciliationEdifly = $this->reconciliationEngine->reconcileOasysVsEdifly($records);

        // 8. Mode Specific Prioritizations
        $modePayload = $this->buildModePayload($reportMode, $records, [
            'kpis'                 => $kpis,
            'hourly'               => $hourlyCharts,
            'pax'                  => $paxAnalytics,
            'ground'               => $groundOps,
            'reconciliation_apps'  => $reconciliationApps,
            'reconciliation_edifly'=> $reconciliationEdifly,
        ]);

        return [
            'meta'                    => $meta,
            'kpis'                    => $kpis,
            'hourly_charts'           => $hourlyCharts,
            'schedule_vs_realization' => $schedVsReal,
            'sched_vs_real'           => $schedVsReal,
            'passenger_analytics'     => $paxAnalytics,
            'pax_analytics'           => $paxAnalytics,
            'airline_route'           => $airlineRoute,
            'ground_operations'       => $groundOps,
            'ground_ops'              => $groundOps,
            'reconciliation_apps'     => $reconciliationApps,
            'reconciliation_edifly'   => $reconciliationEdifly,
            'mode_payload'            => $modePayload,
            'report_mode'             => $reportMode,
        ];
    }

    /**
     * Top Metric Cards:
     * Total Flights, Arrivals, Departures, Total Passengers (Adult/Child/Infant),
     * Avg Load Factor, Cargo (KG), Baggage (KG), Irregularities (Divert, Miss, Unscheduled).
     */
    public function computeTopKpis(array $records): array
    {
        $totalFlights = count($records);
        $arrivals = 0;
        $departures = 0;
        $adult = 0;
        $child = 0;
        $infant = 0;
        $transit = 0;
        $transfer = 0;
        $totalCap = 0;
        $totalLoad = 0;
        $cargoKg = 0.0;
        $baggageKg = 0.0;
        $posKg = 0.0;
        $diverts = 0;
        $misses = 0;
        $unscheduled = 0;

        $lfSum = 0.0;
        $lfCount = 0;

        foreach ($records as $r) {
            if ($r['direction'] === 'ARRIVAL') {
                $arrivals++;
            } else {
                $departures++;
            }

            $rAdult = (int)($r['adult'] ?? ($r['pax_adult'] ?? 0));
            $rChild = (int)($r['child'] ?? ($r['pax_child'] ?? 0));
            $rInfant = (int)($r['infant'] ?? ($r['pax_infant'] ?? 0));
            $rTransit = (int)($r['transit'] ?? ($r['pax_transit'] ?? 0));
            $rTransfer = (int)($r['transfer'] ?? ($r['pax_transfer'] ?? 0));

            $adult += $rAdult;
            $child += $rChild;
            $infant += $rInfant;
            $transit += $rTransit;
            $transfer += $rTransfer;

            $cap = (int)($r['cap'] ?? ($r['capacity'] ?? 0));
            $load = (int)($r['load'] ?? ($r['pax_total'] ?? ($rAdult + $rChild + $rInfant)));
            $totalCap += $cap;
            $totalLoad += $load;

            if ($cap > 0) {
                $lfSum += (($load / $cap) * 100.0);
                $lfCount++;
            }

            $cargoKg += (float)($r['cargo_kg'] ?? 0.0);
            $baggageKg += (float)($r['baggage_kg'] ?? 0.0);
            $posKg += (float)($r['pos_kg'] ?? 0.0);

            $div = (int)($r['divert'] ?? 0);
            $ms = (int)($r['miss'] ?? 0);
            $diverts += $div;
            $misses += $ms;

            if (in_array($r['sched_type'] ?? '', ['UNSCHED', 'UNSCHEDULED'], true)) {
                $unscheduled++;
            }
        }

        $directPax = $adult + $child + $infant;
        $totalPax = ($totalLoad > 0) ? $totalLoad : ($directPax + $transit + $transfer);

        // Load Factor Guardrail: When totalCap = 0, strictly 'N/A'
        $avgLoadFactor = ($totalCap > 0) ? round(($totalLoad / $totalCap) * 100, 1) . '%' : 'N/A';
        $avgLoadFactorNum = ($totalCap > 0) ? round(($totalLoad / $totalCap) * 100, 1) : null;

        return [
            'total_flights'        => $totalFlights,
            'arrivals'             => $arrivals,
            'departures'           => $departures,
            'total_passengers'     => $totalPax,
            'adult_passengers'     => $adult,
            'child_passengers'     => $child,
            'infant_passengers'    => $infant,
            'transit_passengers'   => $transit,
            'transfer_passengers'  => $transfer,
            'total_capacity'       => $totalCap,
            'total_load'           => $totalLoad,
            'avg_load_factor'      => $avgLoadFactor,
            'avg_load_factor_num'  => $avgLoadFactorNum,
            'cargo_kg'             => round($cargoKg, 1),
            'total_cargo_kg'       => round($cargoKg, 1),
            'cargo_ton'            => round($cargoKg / 1000, 2),
            'baggage_kg'           => round($baggageKg, 1),
            'total_baggage_kg'     => round($baggageKg, 1),
            'pos_kg'               => round($posKg, 1),
            'irregularities'       => [
                'total'        => ($diverts + $misses + $unscheduled),
                'divert'       => $diverts,
                'miss'         => $misses,
                'unscheduled'  => $unscheduled,
            ],
        ];
    }

    /**
     * Schedule vs Realization Analysis:
     * Compares scheduled timestamps (SIBT/SOBT) with actual timestamps (AIBT/AOBT).
     */
    public function computeScheduleVsRealization(array $records): array
    {
        $onTime = 0;       // delay <= 15 min
        $minorDelay = 0;   // delay 16-45 min
        $severeDelay = 0;  // delay > 45 min
        $early = 0;        // delay < 0 min
        $totalEvaluated = 0;
        $totalDelayMinutes = 0;

        $hourlyVariances = array_fill(0, 24, ['scheduled' => 0, 'actual' => 0, 'avg_variance' => 0, 'var_sum' => 0]);

        foreach ($records as $r) {
            $isRealized = !empty($r['is_realized']);
            if (!$isRealized) continue;

            $totalEvaluated++;
            $delay = (int)($r['delay_minutes'] ?? 0);
            $totalDelayMinutes += $delay;

            if ($delay < 0) {
                $early++;
                $onTime++;
            } elseif ($delay <= 15) {
                $onTime++;
            } elseif ($delay <= 45) {
                $minorDelay++;
            } else {
                $severeDelay++;
            }

            $h = (int)($r['hour'] ?? 12);
            if ($h >= 0 && $h < 24) {
                $hourlyVariances[$h]['actual']++;
                $hourlyVariances[$h]['var_sum'] += $delay;
            }
        }

        // Scheduled hours distribution
        foreach ($records as $r) {
            $schedTime = (($r['direction'] ?? '') === 'ARRIVAL')
                ? ($r['sibt'] ?? ($r['arr_sched'] ?? 'N/A'))
                : ($r['sobt'] ?? ($r['dep_sched'] ?? 'N/A'));
            if ($schedTime && $schedTime !== 'N/A' && preg_match('/(\d{1,2}):(\d{2})/', $schedTime, $tm)) {
                $sh = (int)$tm[1];
                if ($sh >= 0 && $sh < 24) {
                    $hourlyVariances[$sh]['scheduled']++;
                }
            }
        }

        for ($h = 0; $h < 24; $h++) {
            $act = $hourlyVariances[$h]['actual'];
            $hourlyVariances[$h]['avg_variance'] = ($act > 0) ? round($hourlyVariances[$h]['var_sum'] / $act, 1) : 0;
            unset($hourlyVariances[$h]['var_sum']);
        }

        $hasEvaluated = ($totalEvaluated > 0);
        $onTimePct = $hasEvaluated ? round(($onTime / $totalEvaluated) * 100, 1) : null;
        $onTimePctStr = $hasEvaluated ? "{$onTimePct}%" : 'N/A';
        $avgDelay = $hasEvaluated ? round($totalDelayMinutes / $totalEvaluated, 1) : null;
        $avgDelayStr = $hasEvaluated ? "{$avgDelay} min" : 'N/A';

        return [
            'has_evaluation'        => $hasEvaluated,
            'evaluated_flights'     => $hasEvaluated ? $totalEvaluated : 'N/A',
            'evaluated_count'       => $totalEvaluated,
            'on_time_percentage'    => $onTimePctStr,
            'on_time_pct_num'       => $onTimePct ?? 0,
            'avg_delay_minutes'     => $avgDelayStr,
            'avg_delay_num'         => $avgDelay ?? 0,
            'early_count'           => $early,
            'on_time_count'         => $onTime,
            'minor_delay_count'     => $minorDelay,
            'severe_delay_count'    => $severeDelay,
            'hourly_comparison'     => $hourlyVariances,
        ];
    }

    /**
     * Passenger Analytics & Trend:
     * - Composition: Stacked / grouped breakdown (Adult, Child, Infant, Transit, Transfer).
     * - Multi-day Trend: Combo chart (Pax Volume vs Load Factor line).
     */
    public function computePassengerAnalytics(array $records): array
    {
        $comp = [
            'adult'    => 0,
            'child'    => 0,
            'infant'   => 0,
            'transit'  => 0,
            'transfer' => 0,
        ];

        $dailyGroups = [];
        $totalDirect = 0;
        $totalAll = 0;
        $totalLoad = 0;

        foreach ($records as $r) {
            $a = (int)($r['adult'] ?? ($r['pax_adult'] ?? 0));
            $c = (int)($r['child'] ?? ($r['pax_child'] ?? 0));
            $i = (int)($r['infant'] ?? ($r['pax_infant'] ?? 0));
            $tr = (int)($r['transit'] ?? ($r['pax_transit'] ?? 0));
            $tf = (int)($r['transfer'] ?? ($r['pax_transfer'] ?? 0));
            $ld = (int)($r['load'] ?? ($r['pax_total'] ?? ($a + $c + $i)));

            $comp['adult'] += $a;
            $comp['child'] += $c;
            $comp['infant'] += $i;
            $comp['transit'] += $tr;
            $comp['transfer'] += $tf;
            $totalDirect += ($a + $c + $i);
            $totalAll += ($a + $c + $i + $tr + $tf);
            $totalLoad += $ld;

            $date = $r['flight_date'] ?? date('Y-08-01');
            if (!isset($dailyGroups[$date])) {
                $dailyGroups[$date] = [
                    'date'       => $date,
                    'flights'    => 0,
                    'passengers' => 0,
                    'capacity'   => 0,
                    'load'       => 0,
                ];
            }

            $dailyGroups[$date]['flights']++;
            $dailyGroups[$date]['passengers'] += ($a + $c + $i);
            $dailyGroups[$date]['capacity'] += (int)($r['cap'] ?? 0);
            $dailyGroups[$date]['load'] += $ld;
        }

        ksort($dailyGroups);

        $dailyTrend = [];
        foreach ($dailyGroups as $date => $dg) {
            $lf = ($dg['capacity'] > 0) ? round(($dg['load'] / $dg['capacity']) * 100, 1) : null;
            $dailyTrend[] = [
                'date'        => $date,
                'date_label'  => date('d M', strtotime($date)),
                'flights'     => $dg['flights'],
                'volume'      => $dg['passengers'],
                'capacity'    => $dg['capacity'],
                'load_factor' => $lf,
            ];
        }

        $hasBreakdown = ($totalAll > 0);

        return [
            'composition'     => $comp,
            'total_direct'    => $totalDirect,
            'total_all'       => $totalAll,
            'total_load'      => $totalLoad,
            'has_breakdown'   => $hasBreakdown,
            'daily_trend'     => $dailyTrend,
        ];
    }

    /**
     * Airline & Route Performance:
     * - Horizontal ranked bar of flights, passenger volume, cargo, and load factor by airline.
     * - Top routes matrix based on CITY 1 → CITY 2.
     */
    public function computeAirlineRoutePerformance(array $records): array
    {
        $airlines = [];
        $routes = [];

        foreach ($records as $r) {
            $al = $r['air_line'] ?? 'N/A';
            if (!isset($airlines[$al])) {
                $airlines[$al] = [
                    'airline'      => $al,
                    'flights'      => 0,
                    'passengers'   => 0,
                    'capacity'     => 0,
                    'load'         => 0,
                    'cargo_kg'     => 0.0,
                ];
            }
            $airlines[$al]['flights']++;
            $airlines[$al]['passengers'] += ((int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0));
            $airlines[$al]['capacity'] += (int)($r['cap'] ?? 0);
            $airlines[$al]['load'] += (int)($r['load'] ?? 0);
            $airlines[$al]['cargo_kg'] += (float)($r['cargo_kg'] ?? 0.0);

            $rt = $r['route'] ?? 'N/A';
            if ($rt !== 'N/A') {
                if (!isset($routes[$rt])) {
                    $routes[$rt] = [
                        'route'      => $rt,
                        'origin'     => $r['city_1'] ?? '',
                        'dest'       => $r['city_2'] ?? '',
                        'flights'    => 0,
                        'passengers' => 0,
                        'capacity'   => 0,
                        'load'       => 0,
                    ];
                }
                $routes[$rt]['flights']++;
                $routes[$rt]['passengers'] += ((int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0));
                $routes[$rt]['capacity'] += (int)($r['cap'] ?? 0);
                $routes[$rt]['load'] += (int)($r['load'] ?? 0);
            }
        }

        // Compute Load Factors and sort airlines by flight volume desc
        foreach ($airlines as &$a) {
            $a['avg_load_factor'] = ($a['capacity'] > 0) ? round(($a['load'] / $a['capacity']) * 100, 1) . '%' : 'N/A';
            $a['lf_num'] = ($a['capacity'] > 0) ? round(($a['load'] / $a['capacity']) * 100, 1) : 0;
            $a['cargo_kg'] = round($a['cargo_kg'], 1);
        }
        unset($a);
        usort($airlines, fn($x, $y) => $y['flights'] <=> $x['flights']);

        // Sort routes by flights desc
        foreach ($routes as &$rt) {
            $rt['avg_load_factor'] = ($rt['capacity'] > 0) ? round(($rt['load'] / $rt['capacity']) * 100, 1) . '%' : 'N/A';
            $rt['lf_num'] = ($rt['capacity'] > 0) ? round(($rt['load'] / $rt['capacity']) * 100, 1) : 0;
        }
        unset($rt);
        usort($routes, fn($x, $y) => $y['flights'] <=> $x['flights']);

        return [
            'ranked_airlines' => array_values($airlines),
            'top_routes'      => array_slice(array_values($routes), 0, 15),
        ];
    }

    /**
     * Ground Operations & Irregularities:
     * - Stand utilization (STAND) & Runway usage (RUN WAY).
     * - Operational irregularity breakdown.
     */
    public function computeGroundOps(array $records): array
    {
        $stands = [];
        $runways = [];
        $totalFlights = max(1, count($records));

        $irregularDetails = [
            'divert_flights'      => [],
            'miss_flights'        => [],
            'unscheduled_flights' => [],
        ];

        foreach ($records as $r) {
            $st = $r['stand'] ?? 'N/A';
            if ($st !== 'N/A' && $st !== '') {
                $stands[$st] = ($stands[$st] ?? 0) + 1;
            }

            $rw = $r['runway'] ?? 'N/A';
            if ($rw !== 'N/A' && $rw !== '') {
                $runways[$rw] = ($runways[$rw] ?? 0) + 1;
            }

            if ((int)($r['divert'] ?? 0) > 0) {
                $irregularDetails['divert_flights'][] = $r;
            }
            if ((int)($r['miss'] ?? 0) > 0) {
                $irregularDetails['miss_flights'][] = $r;
            }
            if (in_array($r['sched_type'] ?? '', ['UNSCHED', 'UNSCHEDULED'], true)) {
                $irregularDetails['unscheduled_flights'][] = $r;
            }
        }

        arsort($stands);
        arsort($runways);

        $standList = [];
        foreach ($stands as $stand => $cnt) {
            $standList[] = [
                'stand'      => $stand,
                'count'      => $cnt,
                'percentage' => round(($cnt / $totalFlights) * 100, 1),
            ];
        }

        $runwayList = [];
        foreach ($runways as $rw => $cnt) {
            $runwayList[] = [
                'runway'     => $rw,
                'count'      => $cnt,
                'percentage' => round(($cnt / $totalFlights) * 100, 1),
            ];
        }

        return [
            'stands'          => $standList,
            'runways'         => $runwayList,
            'irregularities'  => $irregularDetails,
        ];
    }

    /**
     * Build Mode Specific Prioritizations for Modes 1..8.
     */
    public function buildModePayload(int $reportMode, array $records, array $context): array
    {
        switch ($reportMode) {
            case 2: // LOAD FACTOR (Capacity vs load priority)
                return $this->buildMode2LoadFactor($records);

            case 3: // COMPARE LOAD FACTOR (Period vs period comparison)
                return $this->buildMode3ComparePeriod($records);

            case 4: // COMPARE LOAD FACTOR DAY (Day-of-week comparison)
                return $this->buildMode4CompareDayOfWeek($records);

            case 5: // AIR TRAFFIC MONITORING I (Hourly operational movement priority)
                return [
                    'mode'        => 5,
                    'mode_name'   => 'AIR TRAFFIC MONITORING I',
                    'description' => 'Hourly operational flow analysis with peak index and capacity constraints.',
                    'hourly'      => $context['hourly'],
                ];

            case 6: // AIR TRAFFIC MONITORING II (Ground/stand & runway priority)
                return [
                    'mode'        => 6,
                    'mode_name'   => 'AIR TRAFFIC MONITORING II',
                    'description' => 'Ground stand distribution, runway assignment balance, and turnaround logistics.',
                    'stands'      => $context['ground']['stands'],
                    'runways'     => $context['ground']['runways'],
                ];

            case 7: // OASYS VS APPS
                return [
                    'mode'           => 7,
                    'mode_name'      => 'OASYS VS APPS',
                    'reconciliation' => $context['reconciliation_apps'],
                ];

            case 8: // OASYS VS EDIFLY
                return [
                    'mode'           => 8,
                    'mode_name'      => 'OASYS VS EDIFLY',
                    'reconciliation' => $context['reconciliation_edifly'],
                ];

            case 1:
            default: // NORMAL
                return [
                    'mode'        => 1,
                    'mode_name'   => 'NORMAL',
                    'description' => 'Standard holistic operational dashboard with full aviation telemetry.',
                ];
        }
    }

    /**
     * Mode 2: LOAD FACTOR
     */
    protected function buildMode2LoadFactor(array $records): array
    {
        $high = 0;   // >= 85%
        $normal = 0; // 70-84%
        $low = 0;    // < 70%
        $zeroCap = 0;

        foreach ($records as $r) {
            $cap = (int)($r['cap'] ?? 0);
            $load = (int)($r['load'] ?? 0);
            if ($cap <= 0) {
                $zeroCap++;
                continue;
            }
            $lf = ($load / $cap) * 100;
            if ($lf >= 85) $high++;
            elseif ($lf >= 70) $normal++;
            else $low++;
        }

        return [
            'mode'           => 2,
            'mode_name'      => 'LOAD FACTOR',
            'tiers'          => [
                'high_tier'   => ['label' => 'High Demand (≥ 85%)', 'count' => $high, 'color' => '#10B981'],
                'normal_tier' => ['label' => 'Optimal (70% - 84%)', 'count' => $normal, 'color' => '#3B82F6'],
                'low_tier'    => ['label' => 'Under-utilized (< 70%)', 'count' => $low, 'color' => '#F59E0B'],
                'zero_cap'    => ['label' => 'Unassigned Cap', 'count' => $zeroCap, 'color' => '#94A3B8'],
            ],
        ];
    }

    /**
     * Mode 3: COMPARE LOAD FACTOR (Period vs period comparison)
     */
    protected function buildMode3ComparePeriod(array $records): array
    {
        $dates = [];
        foreach ($records as $r) {
            if (!empty($r['flight_date']) && $r['flight_date'] !== 'N/A') {
                $dates[] = $r['flight_date'];
            }
        }
        $dates = array_unique($dates);
        sort($dates);

        $mid = max(1, (int)floor(count($dates) / 2));
        $period1Dates = array_slice($dates, 0, $mid);
        $period2Dates = array_slice($dates, $mid);

        $p1Cap = 0; $p1Load = 0; $p1Flights = 0;
        $p2Cap = 0; $p2Load = 0; $p2Flights = 0;

        foreach ($records as $r) {
            $d = $r['flight_date'] ?? '';
            $cap = (int)($r['cap'] ?? 0);
            $load = (int)($r['load'] ?? 0);

            if (in_array($d, $period1Dates)) {
                $p1Flights++;
                $p1Cap += $cap;
                $p1Load += $load;
            } else {
                $p2Flights++;
                $p2Cap += $cap;
                $p2Load += $load;
            }
        }

        $p1Lf = ($p1Cap > 0) ? round(($p1Load / $p1Cap) * 100, 1) : null;
        $p2Lf = ($p2Cap > 0) ? round(($p2Load / $p2Cap) * 100, 1) : null;
        $deltaLf = ($p1Lf !== null && $p2Lf !== null) ? round($p2Lf - $p1Lf, 1) : 0.0;

        return [
            'mode'           => 3,
            'mode_name'      => 'COMPARE LOAD FACTOR',
            'period_1'       => [
                'label'       => (!empty($period1Dates)) ? (reset($period1Dates) . ' → ' . end($period1Dates)) : 'Period 1',
                'flights'     => $p1Flights,
                'capacity'    => $p1Cap,
                'load'        => $p1Load,
                'load_factor' => ($p1Lf !== null) ? "{$p1Lf}%" : 'N/A',
                'lf_num'      => $p1Lf,
            ],
            'period_2'       => [
                'label'       => (!empty($period2Dates)) ? (reset($period2Dates) . ' → ' . end($period2Dates)) : 'Period 2',
                'flights'     => $p2Flights,
                'capacity'    => $p2Cap,
                'load'        => $p2Load,
                'load_factor' => ($p2Lf !== null) ? "{$p2Lf}%" : 'N/A',
                'lf_num'      => $p2Lf,
            ],
            'delta_load_factor' => "{$deltaLf}%",
            'trend'             => ($deltaLf >= 0) ? 'UP' : 'DOWN',
        ];
    }

    /**
     * Mode 4: COMPARE LOAD FACTOR DAY (Day-of-week comparison)
     */
    protected function buildMode4CompareDayOfWeek(array $records): array
    {
        $days = [
            1 => ['name' => 'Monday', 'short' => 'Mon', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            2 => ['name' => 'Tuesday', 'short' => 'Tue', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            3 => ['name' => 'Wednesday', 'short' => 'Wed', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            4 => ['name' => 'Thursday', 'short' => 'Thu', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            5 => ['name' => 'Friday', 'short' => 'Fri', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            6 => ['name' => 'Saturday', 'short' => 'Sat', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            7 => ['name' => 'Sunday', 'short' => 'Sun', 'flights' => 0, 'capacity' => 0, 'load' => 0],
        ];

        foreach ($records as $r) {
            $dStr = $r['flight_date'] ?? null;
            if ($dStr && $dStr !== 'N/A') {
                $dow = (int)date('N', strtotime($dStr));
                if (isset($days[$dow])) {
                    $days[$dow]['flights']++;
                    $days[$dow]['capacity'] += (int)($r['cap'] ?? 0);
                    $days[$dow]['load'] += (int)($r['load'] ?? 0);
                }
            }
        }

        foreach ($days as &$d) {
            $cap = $d['capacity'];
            $load = $d['load'];
            $d['load_factor'] = ($cap > 0) ? round(($load / $cap) * 100, 1) . '%' : 'N/A';
            $d['lf_num'] = ($cap > 0) ? round(($load / $cap) * 100, 1) : 0;
        }
        unset($d);

        return [
            'mode'      => 4,
            'mode_name' => 'COMPARE LOAD FACTOR DAY',
            'days'      => array_values($days),
        ];
    }
}
