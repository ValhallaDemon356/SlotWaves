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

        $reportMode  = (int)($options['report_mode'] ?? 1);
        $airportCode = $meta['airport'] ?? 'CGK';
        $timeBasis   = $options['time_basis'] ?? 'scheduled';
        $reportDate  = $options['report_date'] ?? ($meta['period_start'] ?? '');

        // Section 11: Exclude PAX ALL summary rows from all analytics
        $records = array_values(array_filter($records, function ($r) {
            if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY') return false;
            $al = trim($r['air_line'] ?? '');
            if (strcasecmp($al, 'PAX ALL') === 0 || stripos($al, 'PAX ALL') !== false) return false;
            return true;
        }));

        // 1. 3 Mentor Hourly Charts (basis-aware)
        $hourlyCharts = $this->hourlyChartService->buildHourlyCharts(
            $records, $airportCode, $timeBasis, $reportDate
        );

        // 2. Top KPI Metric Cards (attaching peak hour)
        $kpis = $this->computeTopKpis($records);
        $kpis['peak_hour'] = $hourlyCharts['peak_hour'] ?? null;

        // 3. Schedule vs Realization Analysis (expanded)
        $schedVsReal = $this->computeScheduleVsRealization($records, (int)($options['otp_tolerance'] ?? 15));

        // 4. Passenger Analytics & Multi-Day Trend (expanded)
        $paxAnalytics = $this->computePassengerAnalytics($records);

        // 5. Airline & Route Performance
        $airlineRoute = $this->computeAirlineRoutePerformance($records);

        // 6. Fleet Performance (new)
        $fleetPerformance = $this->computeFleetPerformance($records);

        // 7. Ground Operations Enhanced (stand normalization + turnaround pairing)
        $groundOps = $this->computeGroundOpsEnhanced($records);

        // 8. Primary Combined Trend (Arrivals/Departures bar + Pax/Cargo line)
        $combinedTrend = $this->computeCombinedTrend($records, $options);

        // 9. Reconciliation Engine (Modes 7 & 8)
        $reconciliationApps   = $this->reconciliationEngine->reconcileOasysVsApps($records);
        $reconciliationEdifly = $this->reconciliationEngine->reconcileOasysVsEdifly($records);

        // 10. Mode Specific Prioritizations
        $modePayload = $this->buildModePayload($reportMode, $records, [
            'kpis'                  => $kpis,
            'hourly'                => $hourlyCharts,
            'pax'                   => $paxAnalytics,
            'ground'                => $groundOps,
            'combined_trend'        => $combinedTrend,
            'reconciliation_apps'   => $reconciliationApps,
            'reconciliation_edifly' => $reconciliationEdifly,
        ]);

        return [
            'meta'                    => $meta,
            'kpis'                    => $kpis,
            'kpi'                     => $kpis,
            'hourly_charts'           => $hourlyCharts,
            'combined_trend'          => $combinedTrend,
            'schedule_vs_realization' => $schedVsReal,
            'sched_vs_real'           => $schedVsReal,
            'passenger_analytics'     => $paxAnalytics,
            'pax_analytics'           => $paxAnalytics,
            'airline_route'           => $airlineRoute,
            'airline_analysis'        => $airlineRoute['ranked_airlines'] ?? [],
            'airline_share'           => $airlineRoute['ranked_airlines'] ?? [],
            'fleet_performance'       => $fleetPerformance,
            'ground_operations'       => $groundOps,
            'ground_ops'              => $groundOps,
            'reconciliation_apps'     => $reconciliationApps,
            'reconciliation_edifly'   => $reconciliationEdifly,
            'mode_payload'            => $modePayload,
            'report_mode'             => $reportMode,
            'time_basis'              => $timeBasis,
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

        $arrDom = 0;
        $arrInt = 0;
        $depDom = 0;
        $depInt = 0;

        foreach ($records as $r) {
            $isArr = ($r['direction'] ?? '') === 'ARRIVAL' || ($r['movement_type'] ?? '') === 'A' || ($r['flow'] ?? '') === 'ARR';
            $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? ($r['dom_int'] ?? 'DOMESTIC'))));
            $isDom = in_array($tr, ['DOM', 'DOMESTIC', 'D'], true);

            if ($isArr) {
                $arrivals++;
                if ($isDom) $arrDom++; else $arrInt++;
            } else {
                $departures++;
                if ($isDom) $depDom++; else $depInt++;
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
            $load = (int)($r['load'] ?? ($r['pax_total'] ?? ($r['total_passenger'] ?? ($rAdult + $rChild + $rInfant))));
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

        // Strict Passenger Movement KPI: Adult + Child + Infant (Part 21 & 22)
        // Fallback to totalLoad only if detailed pax fields are completely absent (e.g. synthetic test fixtures)
        $detailedPax = $adult + $child + $infant;
        $passengerMovement = ($detailedPax > 0) ? $detailedPax : $totalLoad;

        // Pax per Flight (Part 23): e.g. 25,627 / 190 = 134.88... -> 134.9
        $paxPerFlight = ($totalFlights > 0) ? round($passengerMovement / $totalFlights, 1) : 0.0;

        // Passenger Seat Utilization: SUM(Adult + Child + Infant) / SUM(CAP) (Part 26)
        $passengerUtilization = ($totalCap > 0) ? round(($passengerMovement / $totalCap) * 100, 1) : null;
        $passengerUtilizationStr = ($passengerUtilization !== null) ? "{$passengerUtilization}%" : 'N/A';

        // Capacity-weighted Load Factor: SUM(CAP x LOAD%) / SUM(CAP) (Part 26)
        $weightedLoadFactor = ($totalCap > 0) ? round(($totalLoad / $totalCap) * 100, 1) : null;
        $weightedLoadFactorStr = ($weightedLoadFactor !== null) ? "{$weightedLoadFactor}%" : 'N/A';

        $cargoPerFlight = ($totalFlights > 0) ? round(($cargoKg / 1000) / $totalFlights, 2) : 0.0;
        $baggagePerFlight = ($totalFlights > 0) ? (int)round($baggageKg / $totalFlights) : 0;
        $irregularTotal = $diverts + $misses + $unscheduled;
        $irregularRate = ($totalFlights > 0) ? round(($irregularTotal / $totalFlights) * 100, 1) : 0.0;

        return [
            'total_flights'            => $totalFlights,
            'arrivals'                 => $arrivals,
            'total_arrivals'           => $arrivals,
            'departures'               => $departures,
            'total_departures'         => $departures,
            'arrivals_dom'             => $arrDom,
            'arrivals_int'             => $arrInt,
            'departures_dom'           => $depDom,
            'departures_int'           => $depInt,
            'total_passengers'         => $passengerMovement,
            'passenger_movement'       => $passengerMovement,
            'direct_passengers'        => $passengerMovement,
            'adult_passengers'         => $adult,
            'child_passengers'         => $child,
            'infant_passengers'        => $infant,
            'transit_passengers'       => $transit,
            'transfer_passengers'      => $transfer,
            'pax_per_flight'           => $paxPerFlight,
            'pax_per_flight_display'   => "{$paxPerFlight} Pax / Flight",
            'total_capacity'           => $totalCap,
            'total_load'               => $totalLoad,
            'passenger_utilization'    => $passengerUtilizationStr,
            'passenger_utilization_num'=> $passengerUtilization,
            'weighted_load_factor'     => $weightedLoadFactorStr,
            'weighted_load_factor_num' => $weightedLoadFactor,
            'avg_load_factor'          => $weightedLoadFactorStr,
            'avg_load_factor_num'      => $weightedLoadFactor,
            'cargo_kg'                 => round($cargoKg, 1),
            'total_cargo_kg'           => round($cargoKg, 1),
            'cargo_ton'                => round($cargoKg / 1000, 2),
            'cargo_per_flight_t'       => $cargoPerFlight,
            'baggage_kg'               => round($baggageKg, 1),
            'total_baggage_kg'         => round($baggageKg, 1),
            'baggage_per_flight_kg'    => $baggagePerFlight,
            'pos_kg'                   => round($posKg, 1),
            'irregular_rate'           => $irregularRate,
            'irregularities'           => [
                'total'        => $irregularTotal,
                'divert'       => $diverts,
                'miss'         => $misses,
                'unscheduled'  => $unscheduled,
            ],
        ];
    }

    /**
     * Schedule vs Realization Analysis:
     * Compares SIBT/SOBT with AIBT/AOBT per flight.
     * OTP tolerance is configurable (default ±15 min per ICAO standard).
     */
    public function computeScheduleVsRealization(array $records, int $toleranceMinutes = 15): array
    {
        $delays          = [];   // individual delay_minutes for evaluated flights
        $earlyBeyond     = 0;   // early > tolerance
        $lateBeyond      = 0;   // late > tolerance
        $onTime          = 0;
        $totalEvaluated  = 0;
        $maxDelay        = null;
        $minDelay        = null;
        $top10           = [];   // largest delays (positive only)

        // Per-hour: scheduled count, actual count, avg variance (for plan vs actual chart)
        $hourlyVariances = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyVariances[$h] = ['hour' => $h, 'hour_label' => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00',
                'scheduled' => 0, 'actual' => 0, 'avg_variance' => 0, 'var_sum' => 0];
        }

        // Histogram bins (9 bins matching Schedule Variance Distribution)
        $histBins = [
            'early_gt60' => [
                'key'           => 'early_gt60',
                'label'         => '>60 MIN EARLY',
                'legacy_label'  => '<-60',
                'category'      => 'EARLY',
                'group'         => 'EARLY',
                'variance_desc' => '>60 minutes early',
                'desc'          => '>60 minutes early',
                'count'         => 0,
                'min'           => PHP_INT_MIN,
                'max'           => -61,
                'color'         => '#1E3A8A', // dark blue
            ],
            'early_31_60' => [
                'key'           => 'early_31_60',
                'label'         => '31–60 MIN EARLY',
                'legacy_label'  => '-60~-31',
                'category'      => 'EARLY',
                'group'         => 'EARLY',
                'variance_desc' => '31–60 minutes early',
                'desc'          => '31–60 minutes early',
                'count'         => 0,
                'min'           => -60,
                'max'           => -31,
                'color'         => '#2563EB', // medium blue
            ],
            'early_16_30' => [
                'key'           => 'early_16_30',
                'label'         => '16–30 MIN EARLY',
                'legacy_label'  => '-30~-16',
                'category'      => 'EARLY',
                'group'         => 'EARLY',
                'variance_desc' => '16–30 minutes early',
                'desc'          => '16–30 minutes early',
                'count'         => 0,
                'min'           => -30,
                'max'           => -16,
                'color'         => '#38BDF8', // light blue
            ],
            'early_6_15' => [
                'key'           => 'early_6_15',
                'label'         => '6–15 MIN EARLY',
                'legacy_label'  => '-15~-6',
                'category'      => 'EARLY',
                'group'         => 'EARLY',
                'variance_desc' => '6–15 minutes early',
                'desc'          => '6–15 minutes early',
                'count'         => 0,
                'min'           => -15,
                'max'           => -6,
                'color'         => '#BAE6FD', // very light blue
            ],
            'on_time' => [
                'key'           => 'on_time',
                'label'         => 'ON TIME ±5 MIN',
                'legacy_label'  => '-5~5',
                'category'      => 'ON TIME',
                'group'         => 'ON TIME',
                'variance_desc' => 'Within ±5 minutes',
                'desc'          => 'Within ±5 minutes',
                'count'         => 0,
                'min'           => -5,
                'max'           => 5,
                'color'         => '#10B981', // green
            ],
            'late_6_15' => [
                'key'           => 'late_6_15',
                'label'         => '6–15 MIN LATE',
                'legacy_label'  => '6~15',
                'category'      => 'LATE',
                'group'         => 'LATE',
                'variance_desc' => '6–15 minutes late',
                'desc'          => '6–15 minutes late',
                'count'         => 0,
                'min'           => 6,
                'max'           => 15,
                'color'         => '#FDE047', // light amber
            ],
            'late_16_30' => [
                'key'           => 'late_16_30',
                'label'         => '16–30 MIN LATE',
                'legacy_label'  => '16~30',
                'category'      => 'LATE',
                'group'         => 'LATE',
                'variance_desc' => '16–30 minutes late',
                'desc'          => '16–30 minutes late',
                'count'         => 0,
                'min'           => 16,
                'max'           => 30,
                'color'         => '#F59E0B', // amber
            ],
            'late_31_60' => [
                'key'           => 'late_31_60',
                'label'         => '31–60 MIN LATE',
                'legacy_label'  => '31~60',
                'category'      => 'LATE',
                'group'         => 'LATE',
                'variance_desc' => '31–60 minutes late',
                'desc'          => '31–60 minutes late',
                'count'         => 0,
                'min'           => 31,
                'max'           => 60,
                'color'         => '#EA580C', // orange/red
            ],
            'late_gt60' => [
                'key'           => 'late_gt60',
                'label'         => '>60 MIN LATE',
                'legacy_label'  => '>60',
                'category'      => 'LATE',
                'group'         => 'LATE',
                'variance_desc' => '>60 minutes late',
                'desc'          => '>60 minutes late',
                'count'         => 0,
                'min'           => 61,
                'max'           => PHP_INT_MAX,
                'color'         => '#DC2626', // red
            ],
        ];

        foreach ($records as $r) {
            $isRealized = !empty($r['is_realized']) || !empty($r['realization']) || (!empty($r['aibt']) && $r['aibt'] !== 'N/A') || (!empty($r['aobt']) && $r['aobt'] !== 'N/A') || isset($r['delay_minutes']);
            if (!$isRealized) continue;

            $delay = isset($r['delay_minutes']) ? (int)$r['delay_minutes'] : null;
            if ($delay === null) {
                // Try to compute from raw timestamps
                $isArr   = ($r['direction'] ?? '') === 'ARRIVAL' || ($r['movement_type'] ?? '') === 'A' || ($r['flow'] ?? '') === 'ARR';
                $schedTs = $isArr ? ($r['sibt'] ?? ($r['arr_sched'] ?? '')) : ($r['sobt'] ?? ($r['dep_sched'] ?? ''));
                $actTs   = $isArr ? ($r['aibt'] ?? ($r['arr_actual'] ?? '')) : ($r['aobt'] ?? ($r['dep_actual'] ?? ''));
                if ($schedTs && $actTs && $schedTs !== 'N/A' && $actTs !== 'N/A') {
                    if (preg_match('/(\d{1,2}):(\d{2})/', $schedTs, $sm) && preg_match('/(\d{1,2}):(\d{2})/', $actTs, $am)) {
                        $delay = ((int)$am[1] * 60 + (int)$am[2]) - ((int)$sm[1] * 60 + (int)$sm[2]);
                    }
                }
            }
            if ($delay === null) continue;

            $totalEvaluated++;
            $delays[] = $delay;
            if ($maxDelay === null || $delay > $maxDelay) $maxDelay = $delay;
            if ($minDelay === null || $delay < $minDelay) $minDelay = $delay;

            if (abs($delay) <= $toleranceMinutes) {
                $onTime++;
            } elseif ($delay < -$toleranceMinutes) {
                $earlyBeyond++;
            } else {
                $lateBeyond++;
                $top10[] = [
                    'flight_no' => $r['flight_no'] ?? 'N/A',
                    'route'     => $r['route']     ?? 'N/A',
                    'sched'     => (($r['direction'] ?? '') === 'ARRIVAL') ? ($r['sibt'] ?? 'N/A') : ($r['sobt'] ?? 'N/A'),
                    'actual'    => (($r['direction'] ?? '') === 'ARRIVAL') ? ($r['aibt'] ?? 'N/A') : ($r['aobt'] ?? 'N/A'),
                    'delay_min' => $delay,
                    'direction' => $r['direction'] ?? 'N/A',
                ];
            }

            // Hourly actual bucket (by sched hour)
            $isArr2 = ($r['direction'] ?? '') === 'ARRIVAL';
            $sTs   = $isArr2 ? ($r['sibt'] ?? '') : ($r['sobt'] ?? '');
            if ($sTs && $sTs !== 'N/A' && preg_match('/(\d{1,2}):/', $sTs, $hm)) {
                $sh = (int)$hm[1];
                if ($sh >= 0 && $sh < 24) {
                    $hourlyVariances[$sh]['scheduled']++;
                    $hourlyVariances[$sh]['actual']++;
                    $hourlyVariances[$sh]['var_sum'] += $delay;
                }
            }

            // Histogram (9 standard schedule variance bins)
            if ($delay < -60) {
                $histBins['early_gt60']['count']++;
            } elseif ($delay <= -31) {
                $histBins['early_31_60']['count']++;
            } elseif ($delay <= -16) {
                $histBins['early_16_30']['count']++;
            } elseif ($delay <= -6) {
                $histBins['early_6_15']['count']++;
            } elseif ($delay <= 5) {
                $histBins['on_time']['count']++;
            } elseif ($delay <= 15) {
                $histBins['late_6_15']['count']++;
            } elseif ($delay <= 30) {
                $histBins['late_16_30']['count']++;
            } elseif ($delay <= 60) {
                $histBins['late_31_60']['count']++;
            } else {
                $histBins['late_gt60']['count']++;
            }
        }

        for ($h = 0; $h < 24; $h++) {
            $act = $hourlyVariances[$h]['actual'];
            $hourlyVariances[$h]['avg_variance'] = ($act > 0) ? round($hourlyVariances[$h]['var_sum'] / $act, 1) : 0;
            unset($hourlyVariances[$h]['var_sum']);
        }

        // Scheduled hours distribution
        foreach ($records as $r) {
            $schedTime = (($r['direction'] ?? '') === 'ARRIVAL')
                ? ($r['sibt'] ?? ($r['arr_sched'] ?? 'N/A'))
                : ($r['sobt'] ?? ($r['dep_sched'] ?? 'N/A'));
            if ($schedTime && $schedTime !== 'N/A' && preg_match('/(\d{1,2}):(\d{2})/', $schedTime, $tm)) {
                $sh = (int)$tm[1];
                if ($sh >= 0 && $sh < 24) {
                    // only scheduled (not evaluated)
                }
            }
        }

        $hasEvaluated = ($totalEvaluated > 0);
        // Median
        $medianDelay = null;
        if ($hasEvaluated) {
            $sorted = $delays;
            sort($sorted);
            $mid = (int)floor(count($sorted) / 2);
            $medianDelay = (count($sorted) % 2 === 0)
                ? (($sorted[$mid - 1] + $sorted[$mid]) / 2.0)
                : (float)$sorted[$mid];
        }

        // Top 10 delays descending
        usort($top10, fn($a, $b) => $b['delay_min'] <=> $a['delay_min']);
        $top10 = array_slice($top10, 0, 10);

        $onTimePct    = $hasEvaluated ? round(($onTime / $totalEvaluated) * 100, 1) : null;
        $avgDelay     = $hasEvaluated ? round(array_sum($delays) / count($delays), 1) : null;

        return [
            'has_evaluation'        => $hasEvaluated,
            'evaluated_count'       => $totalEvaluated,
            'evaluated_flights'     => $hasEvaluated ? $totalEvaluated : 'N/A',
            'tolerance_minutes'     => $toleranceMinutes,
            'on_time_count'         => $onTime,
            'early_count'           => $earlyBeyond,
            'late_count'            => $lateBeyond,
            'on_time_percentage'    => $hasEvaluated ? "{$onTimePct}%" : 'N/A',
            'on_time_pct_num'       => $onTimePct ?? 0,
            'avg_delay_minutes'     => $hasEvaluated ? "{$avgDelay} min" : 'N/A',
            'avg_delay_num'         => $avgDelay ?? 0,
            'median_delay_num'      => $medianDelay,
            'median_delay'          => $hasEvaluated ? ($medianDelay >= 0 ? "+{$medianDelay}" : "{$medianDelay}") . ' min' : 'N/A',
            'max_delay_num'         => $maxDelay,
            'min_delay_num'         => $minDelay,
            'max_delay'             => $hasEvaluated ? ($maxDelay >= 0 ? "+{$maxDelay}" : "{$maxDelay}") . ' min' : 'N/A',
            'min_delay'             => $hasEvaluated ? ($minDelay >= 0 ? "+{$minDelay}" : "{$minDelay}") . ' min' : 'N/A',
            'title'                 => 'SCHEDULE VARIANCE DISTRIBUTION',
            'subtitle'              => 'How early or late actual movement occurred compared with schedule.',
            // legacy compat
            'minor_delay_count'     => $lateBeyond,
            'severe_delay_count'    => 0,
            'hourly_comparison'     => array_values($hourlyVariances),
            'histogram'             => array_values($histBins),
            'hist_bins'             => $histBins,
            'summary'               => [
                'early'   => $histBins['early_gt60']['count'] + $histBins['early_31_60']['count'] + $histBins['early_16_30']['count'] + $histBins['early_6_15']['count'],
                'on_time' => $histBins['on_time']['count'],
                'late'    => $histBins['late_6_15']['count'] + $histBins['late_16_30']['count'] + $histBins['late_31_60']['count'] + $histBins['late_gt60']['count'],
            ],
            'variance_summary'      => [
                'early_count'   => $histBins['early_gt60']['count'] + $histBins['early_31_60']['count'] + $histBins['early_16_30']['count'] + $histBins['early_6_15']['count'],
                'on_time_count' => $histBins['on_time']['count'],
                'late_count'    => $histBins['late_6_15']['count'] + $histBins['late_16_30']['count'] + $histBins['late_31_60']['count'] + $histBins['late_gt60']['count'],
            ],
            'top10_delays'          => $top10,
        ];
    }

    /**
     * Passenger Analytics & Payload Composition (expanded per B2 spec).
     * - Composition: ADU LT, CHI LD, INF ANT, TRAN SIT, CRW (from file — not fabricated).
     * - Breakdown ARR vs DEP per category.
     * - Load Factor: taken straight from the 'load' / 'cap' columns (or the LOAD column value).
     * - Payload: CAR.(KG), BAGG.(KG), POS(KG) per ARR/DEP.
     */
    public function computePassengerAnalytics(array $records): array
    {
        // Composition totals
        $comp = ['adult' => 0, 'child' => 0, 'infant' => 0, 'transit' => 0, 'transfer' => 0, 'crew' => 0];
        // Per-direction composition
        $compArr = $comp;
        $compDep = $comp;

        // Load Factor from file (as-is, do NOT recalculate)
        $lfFlights     = [];   // each element: ['lf' => float, 'dir' => 'ARR'|'DEP', 'flight_no' => ...]
        $lf0Count      = 0;
        $lfArrSum      = 0.0; $lfArrCount = 0;
        $lfDepSum      = 0.0; $lfDepCount = 0;
        $lfBuckets     = ['0pct' => 0, 'lt50' => 0, '50to69' => 0, '70to89' => 0, 'ge90' => 0];
        $lf0Flights    = [];

        // Payload per direction
        $payloadArr = ['cargo_kg' => 0.0, 'baggage_kg' => 0.0, 'pos_kg' => 0.0];
        $payloadDep = ['cargo_kg' => 0.0, 'baggage_kg' => 0.0, 'pos_kg' => 0.0];

        $dailyGroups = [];
        $totalLoad   = 0;

        foreach ($records as $r) {
            $isArr = ($r['direction'] ?? '') === 'ARRIVAL';
            $a     = (int)($r['adult']    ?? ($r['pax_adult'] ?? 0));
            $c     = (int)($r['child']    ?? ($r['pax_child'] ?? 0));
            $inf   = (int)($r['infant']   ?? ($r['pax_infant'] ?? 0));
            $tr    = (int)($r['transit']  ?? ($r['pax_transit'] ?? 0));
            $tf    = (int)($r['transfer'] ?? ($r['pax_transfer'] ?? 0));
            $crw   = (int)($r['crw']      ?? ($r['crew'] ?? 0));
            $ld    = (int)($r['load']     ?? ($r['pax_total'] ?? ($a + $c + $inf)));

            $comp['adult']    += $a;
            $comp['child']    += $c;
            $comp['infant']   += $inf;
            $comp['transit']  += $tr;
            $comp['transfer'] += $tf;
            $comp['crew']     += $crw;
            $totalLoad        += $ld;

            $target = $isArr ? 'arr' : 'dep';
            ${'comp'.ucfirst($target)}['adult']    += $a;
            ${'comp'.ucfirst($target)}['child']    += $c;
            ${'comp'.ucfirst($target)}['infant']   += $inf;
            ${'comp'.ucfirst($target)}['transit']  += $tr;
            ${'comp'.ucfirst($target)}['transfer'] += $tf;
            ${'comp'.ucfirst($target)}['crew']     += $crw;

            // Load Factor (from file — read LOAD column directly)
            $cap   = (int)($r['cap'] ?? 0);
            // The LOAD field in the parsed record IS the load_factor from the OASYS file
            $lfVal = isset($r['load_factor']) ? (float)$r['load_factor'] : ($cap > 0 ? round(($ld / $cap) * 100, 1) : 0.0);

            $lfFlights[] = ['lf' => $lfVal, 'dir' => $isArr ? 'ARR' : 'DEP', 'flight_no' => $r['flight_no'] ?? ''];
            if ($lfVal == 0) {
                $lf0Count++;
                $lf0Flights[] = $r['flight_no'] ?? 'N/A';
            } elseif ($lfVal < 50) {
                $lfBuckets['lt50']++;
            } elseif ($lfVal < 70) {
                $lfBuckets['50to69']++;
            } elseif ($lfVal < 90) {
                $lfBuckets['70to89']++;
            } else {
                $lfBuckets['ge90']++;
            }

            if ($isArr) {
                $lfArrSum += $lfVal; $lfArrCount++;
                $payloadArr['cargo_kg']   += (float)($r['cargo_kg']   ?? 0);
                $payloadArr['baggage_kg'] += (float)($r['baggage_kg'] ?? 0);
                $payloadArr['pos_kg']     += (float)($r['pos_kg']     ?? 0);
            } else {
                $lfDepSum += $lfVal; $lfDepCount++;
                $payloadDep['cargo_kg']   += (float)($r['cargo_kg']   ?? 0);
                $payloadDep['baggage_kg'] += (float)($r['baggage_kg'] ?? 0);
                $payloadDep['pos_kg']     += (float)($r['pos_kg']     ?? 0);
            }

            $date = $r['flight_date'] ?? date('Y-m-d');
            if (!isset($dailyGroups[$date])) {
                $dailyGroups[$date] = ['date' => $date, 'flights' => 0, 'passengers' => 0, 'capacity' => 0, 'load' => 0];
            }
            $dailyGroups[$date]['flights']++;
            $dailyGroups[$date]['passengers'] += ($a + $c + $inf);
            $dailyGroups[$date]['capacity']   += $cap;
            $dailyGroups[$date]['load']       += $ld;
        }

        ksort($dailyGroups);
        $dailyTrend = [];
        foreach ($dailyGroups as $date => $dg) {
            $lf = ($dg['capacity'] > 0) ? round(($dg['load'] / $dg['capacity']) * 100, 1) : null;
            $dailyTrend[] = [
                'date' => $date, 'date_label' => date('d M', strtotime($date)),
                'flights' => $dg['flights'], 'volume' => $dg['passengers'],
                'capacity' => $dg['capacity'], 'load_factor' => $lf,
            ];
        }

        $totalAll     = $comp['adult'] + $comp['child'] + $comp['infant'] + $comp['transit'] + $comp['crew'];
        $hasBreakdown = ($totalAll > 0);

        return [
            'composition'       => $comp,
            'composition_arr'   => $compArr,
            'composition_dep'   => $compDep,
            'total_direct'      => $comp['adult'] + $comp['child'] + $comp['infant'],
            'total_all'         => $totalAll,
            'total_load'        => $totalLoad,
            'has_breakdown'     => $hasBreakdown,
            'daily_trend'       => $dailyTrend,
            'lf_from_file'      => true,   // signals UI: values taken as-is from file
            'lf_avg_arr'        => $lfArrCount > 0 ? round($lfArrSum / $lfArrCount, 1) : null,
            'lf_avg_dep'        => $lfDepCount > 0 ? round($lfDepSum / $lfDepCount, 1) : null,
            'lf_zero_count'     => $lf0Count,
            'lf_zero_flights'   => $lf0Flights,
            'lf_buckets'        => [
                ['label' => '0%',        'count' => $lf0Count,           'key' => '0pct'],
                ['label' => '< 50%',     'count' => $lfBuckets['lt50'],  'key' => 'lt50'],
                ['label' => '50–69%',    'count' => $lfBuckets['50to69'],'key' => '50to69'],
                ['label' => '70–89%',    'count' => $lfBuckets['70to89'],'key' => '70to89'],
                ['label' => '≥ 90%',     'count' => $lfBuckets['ge90'],  'key' => 'ge90'],
            ],
            'payload_arr'       => ['cargo_kg' => round($payloadArr['cargo_kg']), 'baggage_kg' => round($payloadArr['baggage_kg']), 'pos_kg' => round($payloadArr['pos_kg'])],
            'payload_dep'       => ['cargo_kg' => round($payloadDep['cargo_kg']), 'baggage_kg' => round($payloadDep['baggage_kg']), 'pos_kg' => round($payloadDep['pos_kg'])],
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
     * Fleet Performance (new — B3 spec).
     * - Fleet mix per aircraft type (DESC column).
     * - Narrow vs Wide from "(N )" / "(W )" in DESC text.
     * - Top 10 registrations (REG. NO).
     */
    public function computeFleetPerformance(array $records): array
    {
        $fleetMap = [];   // keyed by desc
        $regMap   = [];   // keyed by reg_no
        $narrowCount = 0;
        $wideCount   = 0;

        foreach ($records as $r) {
            $desc  = trim($r['desc'] ?? ($r['aircraft_type'] ?? 'Unknown'));
            $regNo = trim($r['reg_no'] ?? ($r['registration'] ?? 'N/A'));
            $cap   = (int)($r['cap'] ?? 0);
            $lfVal = isset($r['load_factor']) ? (float)$r['load_factor'] : null;

            // Narrow/Wide from description text
            $isNarrow = str_contains($desc, '(N )') || str_contains($desc, '(N)');
            $isWide   = str_contains($desc, '(W )') || str_contains($desc, '(W)');
            if ($isNarrow) $narrowCount++;
            elseif ($isWide) $wideCount++;

            if (!isset($fleetMap[$desc])) {
                $fleetMap[$desc] = ['desc' => $desc, 'movements' => 0, 'cap_sum' => 0, 'cap_count' => 0, 'lf_sum' => 0.0, 'lf_count' => 0, 'narrow' => $isNarrow, 'wide' => $isWide];
            }
            $fleetMap[$desc]['movements']++;
            if ($cap > 0) { $fleetMap[$desc]['cap_sum'] += $cap; $fleetMap[$desc]['cap_count']++; }
            if ($lfVal !== null) { $fleetMap[$desc]['lf_sum'] += $lfVal; $fleetMap[$desc]['lf_count']++; }

            if ($regNo && $regNo !== 'N/A') {
                if (!isset($regMap[$regNo])) {
                    $regMap[$regNo] = ['reg_no' => $regNo, 'movements' => 0, 'desc' => $desc];
                }
                $regMap[$regNo]['movements']++;
            }
        }

        $fleetList = [];
        foreach ($fleetMap as $desc => $f) {
            $fleetList[] = [
                'desc'       => $desc,
                'movements'  => $f['movements'],
                'avg_cap'    => $f['cap_count'] > 0 ? round($f['cap_sum'] / $f['cap_count']) : null,
                'avg_lf'     => $f['lf_count']  > 0 ? round($f['lf_sum']  / $f['lf_count'], 1) : null,
                'narrow'     => $f['narrow'],
                'wide'       => $f['wide'],
            ];
        }
        usort($fleetList, fn($a, $b) => $b['movements'] <=> $a['movements']);

        $regList = array_values($regMap);
        usort($regList, fn($a, $b) => $b['movements'] <=> $a['movements']);
        $top10Regs = array_slice($regList, 0, 10);

        return [
            'fleet_mix'      => $fleetList,
            'narrow_count'   => $narrowCount,
            'wide_count'     => $wideCount,
            'total_regs'     => count($regMap),
            'regs_multi'     => count(array_filter($regMap, fn($r) => $r['movements'] > 1)),
            'top10_regs'     => $top10Regs,
        ];
    }

    /**
     * Ground Operations Enhanced (B4 spec):
     * - Normalize STAND / RUN WAY (strip airport prefix + dash).
     * - Turnaround pairing: FIFO greedy per REG_NO (ARR→DEP pairs).
     * - Ground time stats + checksum.
     */
    public function computeGroundOpsEnhanced(array $records): array
    {
        $airportPrefix = ''; // detected from first stand value
        $stands  = [];
        $runways = [];
        $standFilled   = 0;
        $runwayFilled  = 0;
        $totalFlights  = max(1, count($records));

        // Collect and normalize stand/runway
        foreach ($records as $r) {
            $rawStand  = $r['stand']  ?? '';
            $rawRunway = $r['runway'] ?? '';

            // Auto-detect prefix (e.g. "CGK-")
            if (!$airportPrefix && preg_match('/^([A-Z]{2,4})-/', (string)$rawStand, $pm)) {
                $airportPrefix = $pm[0]; // e.g. "CGK-"
            }

            $normStand  = $this->normalizeGround($rawStand,  $airportPrefix);
            $normRunway = $this->normalizeGround($rawRunway, $airportPrefix);

            if ($normStand !== '' && $normStand !== 'N/A') {
                $stands[$normStand] = ($stands[$normStand] ?? 0) + 1;
                $standFilled++;
            }
            if ($normRunway !== '' && $normRunway !== 'N/A') {
                $runways[$normRunway] = ($runways[$normRunway] ?? 0) + 1;
                $runwayFilled++;
            }
        }

        arsort($stands);
        arsort($runways);

        $standList = [];
        foreach ($stands as $stand => $cnt) {
            $standList[] = ['stand' => $stand, 'count' => $cnt, 'percentage' => round(($cnt / $totalFlights) * 100, 1)];
        }
        $runwayList = [];
        foreach ($runways as $rw => $cnt) {
            $runwayList[] = ['runway' => $rw, 'count' => $cnt, 'percentage' => round(($cnt / $totalFlights) * 100, 1)];
        }

        // ── Turnaround Pairing: FIFO greedy per REG_NO ─────────────────────
        // Sort all records by actual time (AIBT for ARR, AOBT for DEP)
        $byReg = [];
        foreach ($records as $r) {
            $regNo = trim($r['reg_no'] ?? '');
            if (!$regNo || $regNo === 'N/A') continue;
            $isArr = ($r['direction'] ?? '') === 'ARRIVAL';
            $ts    = $isArr ? ($r['aibt'] ?? '') : ($r['aobt'] ?? '');
            if (!$ts || $ts === 'N/A') continue;
            // Convert HH:MM to minutes since midnight
            if (!preg_match('/(\d{1,2}):(\d{2})/', (string)$ts, $tm)) continue;
            $tsMin = (int)$tm[1] * 60 + (int)$tm[2];

            $byReg[$regNo][] = [
                'is_arr'   => $isArr,
                'ts_min'   => $tsMin,
                'ts_str'   => $ts,
                'stand'    => $this->normalizeGround($r['stand'] ?? '', $airportPrefix),
                'raw'      => $r,
            ];
        }

        $pairs          = [];   // completed turnaround pairs
        $unpaired       = [];   // flights without a pair
        $groundTimes    = [];   // minutes
        $standChanges   = 0;

        foreach ($byReg as $reg => $events) {
            // Sort by actual timestamp
            usort($events, fn($a, $b) => $a['ts_min'] <=> $b['ts_min']);

            $arrQueue = [];  // unmatched ARR events (FIFO)
            foreach ($events as $ev) {
                if ($ev['is_arr']) {
                    $arrQueue[] = $ev;
                } else {
                    // Find earliest unmatched ARR that is before this DEP
                    $matched = null;
                    foreach ($arrQueue as $qi => $qa) {
                        if ($qa['ts_min'] <= $ev['ts_min']) {
                            $matched = $qi;
                            break;
                        }
                    }
                    if ($matched !== null) {
                        $arr = $arrQueue[$matched];
                        array_splice($arrQueue, $matched, 1);
                        $groundMin = $ev['ts_min'] - $arr['ts_min'];
                        $groundTimes[] = $groundMin;
                        if ($arr['stand'] !== $ev['stand'] && $arr['stand'] !== '' && $ev['stand'] !== '') {
                            $standChanges++;
                        }
                        $pairs[] = [
                            'reg_no'       => $reg,
                            'arr_time'     => $arr['ts_str'],
                            'dep_time'     => $ev['ts_str'],
                            'ground_min'   => $groundMin,
                            'stand_arr'    => $arr['stand'],
                            'stand_dep'    => $ev['stand'],
                            'stand_change' => ($arr['stand'] !== $ev['stand']),
                        ];
                    } else {
                        $unpaired[] = array_merge($ev, ['reg_no' => $reg, 'reason' => 'no_arr']);
                    }
                }
            }
            // Remaining ARRs without a DEP
            foreach ($arrQueue as $qa) {
                $unpaired[] = array_merge($qa, ['reg_no' => $reg, 'reason' => 'no_dep']);
            }
        }

        // Ground time statistics
        $gtCount  = count($groundTimes);
        $gtMin    = $gtCount > 0 ? min($groundTimes) : null;
        $gtMax    = $gtCount > 0 ? max($groundTimes) : null;
        $gtMean   = $gtCount > 0 ? round(array_sum($groundTimes) / $gtCount, 1) : null;
        $gtMedian = null;
        if ($gtCount > 0) {
            $sorted = $groundTimes;
            sort($sorted);
            $mid = (int)floor($gtCount / 2);
            $gtMedian = ($gtCount % 2 === 0)
                ? round(($sorted[$mid - 1] + $sorted[$mid]) / 2.0, 1)
                : (float)$sorted[$mid];
        }

        // Ground time histogram
        $gtHist = [];
        foreach ([0,30,60,90,120,180,240] as $b) {
            $next = ($b === 240) ? PHP_INT_MAX : $b + 30;
            $label = ($b === 240) ? '240+ min' : "{$b}–" . ($next - 1) . ' min';
            $gtHist[] = ['label' => $label, 'min' => $b, 'max' => $next, 'count' => 0];
        }
        foreach ($groundTimes as $gt) {
            foreach ($gtHist as &$bucket) {
                if ($gt >= $bucket['min'] && $gt < $bucket['max']) { $bucket['count']++; break; }
            }
        }
        unset($bucket);

        // Irregularity details (unchanged)
        $irregularDetails = ['divert_flights' => [], 'miss_flights' => [], 'unscheduled_flights' => []];
        foreach ($records as $r) {
            if ((int)($r['divert'] ?? 0) > 0) $irregularDetails['divert_flights'][] = $r;
            if ((int)($r['miss'] ?? 0) > 0)   $irregularDetails['miss_flights'][] = $r;
            if (in_array($r['sched_type'] ?? '', ['UNSCHED', 'UNSCHEDULED'])) $irregularDetails['unscheduled_flights'][] = $r;
        }

        return [
            'stands'             => $standList,
            'runways'            => $runwayList,
            'stand_unique'       => count($stands),
            'stand_filled'       => $standFilled,
            'runway_filled'      => $runwayFilled,
            'turnaround_pairs'   => $pairs,
            'turnaround_count'   => $gtCount,
            'unpaired_flights'   => count($unpaired),
            'stand_changes'      => $standChanges,
            'ground_time_min'    => $gtMin,
            'ground_time_median' => $gtMedian,
            'ground_time_mean'   => $gtMean,
            'ground_time_max'    => $gtMax,
            'ground_time_histogram' => $gtHist,
            'irregularities'     => $irregularDetails,
        ];
    }

    /**
     * Normalize a stand or runway string: remove airport prefix + dash.
     * E.g. "CGK-G39" -> "G39", "BTJ-4" -> "4".
     */
    protected function normalizeGround(string $raw, string $prefix): string
    {
        $v = trim($raw);
        if ($v === '' || $v === 'N/A') return $v;
        if ($prefix && str_starts_with($v, $prefix)) {
            $v = substr($v, strlen($prefix));
        }
        // Also strip leading "XXX-" pattern generically
        $v = preg_replace('/^[A-Z]{2,4}-/', '', $v);
        return trim($v);
    }

    /**
     * Ground Operations & Irregularities (legacy method — kept for backward compat).
     * Delegates to computeGroundOpsEnhanced().
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

    /**
     * Flight / Passenger / Cargo Trend (Combination Chart).
    /**
     * Flight / Passenger / Cargo Trend (Combination Analytics).
     * Synchronized series for:
     * 1. Flight Movement (Grouped Bars: Arr Dom, Arr Int, Dep Dom, Dep Int)
     * 2. Passenger Trend (Line: Arrival Pax & Departure Pax, Total Pax)
     * 3. Cargo Trend (Line/Area: Arrival Cargo & Departure Cargo, Total Cargo kg/Ton)
     * Granularity: Daily if multiple dates (Monthly/Custom), Hourly (00:00-23:00) if Daily/Single Date, Monthly if Yearly.
     */
    public function computeCombinedTrend(array $records, array $options = []): array
    {
        $timeBasis = $options['time_basis'] ?? 'scheduled';
        $analysisLevel = strtoupper($options['analysis_level'] ?? 'DAILY');

        // Check date span
        $dates = [];
        foreach ($records as $r) {
            $d = $r['operational_date'] ?? ($r['flight_date'] ?? '');
            if ($d && $d !== 'N/A') {
                $dates[$d] = true;
            }
        }
        ksort($dates);
        $dateKeys = array_keys($dates);

        $labels = [];
        $fullDates = [];
        $arrDomFlights = [];
        $arrIntFlights = [];
        $depDomFlights = [];
        $depIntFlights = [];
        $arrivals = [];
        $departures = [];
        $totalFlights = [];

        $arrPassengers = [];
        $depPassengers = [];
        $passengers = [];

        $arrCargoKg = [];
        $depCargoKg = [];
        $cargoKg = [];
        $cargoTon = [];
        $arrCargoTon = [];
        $depCargoTon = [];

        $arrBaggageKg = [];
        $depBaggageKg = [];
        $baggageKg = [];

        $dateScope = strtoupper(trim($options['date_scope'] ?? ''));
        if (empty($dateScope)) {
            if ($analysisLevel === 'FULL' || $analysisLevel === 'ALL') {
                $dateScope = 'ALL_PERIOD';
            } elseif ($analysisLevel === 'DAILY') {
                $dateScope = 'DAY';
            } else {
                $dateScope = (count($dateKeys) > 1) ? 'ALL_PERIOD' : 'DAY';
            }
        }

        $granularity = 'daily';

        if ($analysisLevel === 'YEARLY' && count($dateKeys) > 31) {
            // Yearly granularity: 12 months (Jan to Dec)
            $granularity = 'monthly';
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthMap = [];
            for ($m = 1; $m <= 12; $m++) {
                $mKey = str_pad($m, 2, '0', STR_PAD_LEFT);
                $monthMap[$mKey] = [
                    'label' => $monthNames[$m - 1],
                    'full_date' => $monthNames[$m - 1],
                    'arr_dom' => 0, 'arr_int' => 0, 'dep_dom' => 0, 'dep_int' => 0,
                    'arr_pax' => 0, 'dep_pax' => 0,
                    'arr_dom_pax' => 0, 'arr_int_pax' => 0, 'dep_dom_pax' => 0, 'dep_int_pax' => 0,
                    'arr_cargo' => 0.0, 'dep_cargo' => 0.0,
                    'arr_dom_cargo' => 0.0, 'arr_int_cargo' => 0.0, 'dep_dom_cargo' => 0.0, 'dep_int_cargo' => 0.0,
                    'arr_bagg' => 0.0, 'dep_bagg' => 0.0,
                ];
            }

            foreach ($records as $r) {
                $d = $r['operational_date'] ?? ($r['flight_date'] ?? '');
                $mKey = substr($d, 5, 2);
                if (!isset($monthMap[$mKey])) continue;

                $isArr = ($r['direction'] ?? '') === 'ARRIVAL';
                $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? 'DOMESTIC')));
                $isDom = ($tr === 'DOM' || $tr === 'DOMESTIC');

                $pax = (int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0);
                if ($pax === 0) { $pax = (int)($r['load'] ?? ($r['pax_total'] ?? 0)); }

                $cKg = (float)($r['cargo_kg'] ?? 0.0);
                $bKg = (float)($r['baggage_kg'] ?? 0.0);

                if ($isArr) {
                    if ($isDom) {
                        $monthMap[$mKey]['arr_dom']++;
                        $monthMap[$mKey]['arr_dom_pax'] += $pax;
                        $monthMap[$mKey]['arr_dom_cargo'] += $cKg;
                    } else {
                        $monthMap[$mKey]['arr_int']++;
                        $monthMap[$mKey]['arr_int_pax'] += $pax;
                        $monthMap[$mKey]['arr_int_cargo'] += $cKg;
                    }
                    $monthMap[$mKey]['arr_pax'] += $pax;
                    $monthMap[$mKey]['arr_cargo'] += $cKg;
                    $monthMap[$mKey]['arr_bagg'] += $bKg;
                } else {
                    if ($isDom) {
                        $monthMap[$mKey]['dep_dom']++;
                        $monthMap[$mKey]['dep_dom_pax'] += $pax;
                        $monthMap[$mKey]['dep_dom_cargo'] += $cKg;
                    } else {
                        $monthMap[$mKey]['dep_int']++;
                        $monthMap[$mKey]['dep_int_pax'] += $pax;
                        $monthMap[$mKey]['dep_int_cargo'] += $cKg;
                    }
                    $monthMap[$mKey]['dep_pax'] += $pax;
                    $monthMap[$mKey]['dep_cargo'] += $cKg;
                    $monthMap[$mKey]['dep_bagg'] += $bKg;
                }
            }

            $arrDomPax = [];
            $arrIntPax = [];
            $depDomPax = [];
            $depIntPax = [];
            $arrDomCargoKg = [];
            $arrIntCargoKg = [];
            $depDomCargoKg = [];
            $depIntCargoKg = [];

            foreach ($monthMap as $mKey => $val) {
                $labels[] = $val['label'];
                $fullDates[] = $val['full_date'];
                $arrDomFlights[] = $val['arr_dom'];
                $arrIntFlights[] = $val['arr_int'];
                $depDomFlights[] = $val['dep_dom'];
                $depIntFlights[] = $val['dep_int'];
                $arrTot = $val['arr_dom'] + $val['arr_int'];
                $depTot = $val['dep_dom'] + $val['dep_int'];
                $arrivals[] = $arrTot;
                $departures[] = $depTot;
                $totalFlights[] = $arrTot + $depTot;

                $arrPassengers[] = $val['arr_pax'];
                $depPassengers[] = $val['dep_pax'];
                $passengers[] = $val['arr_pax'] + $val['dep_pax'];
                $arrDomPax[] = $val['arr_dom_pax'];
                $arrIntPax[] = $val['arr_int_pax'];
                $depDomPax[] = $val['dep_dom_pax'];
                $depIntPax[] = $val['dep_int_pax'];

                $arrCargoKg[] = round($val['arr_cargo'], 1);
                $depCargoKg[] = round($val['dep_cargo'], 1);
                $totC = $val['arr_cargo'] + $val['dep_cargo'];
                $cargoKg[] = round($totC, 1);
                $cargoTon[] = round($totC / 1000, 2);
                $arrCargoTon[] = round($val['arr_cargo'] / 1000, 2);
                $depCargoTon[] = round($val['dep_cargo'] / 1000, 2);
                $arrDomCargoKg[] = round($val['arr_dom_cargo'], 1);
                $arrIntCargoKg[] = round($val['arr_int_cargo'], 1);
                $depDomCargoKg[] = round($val['dep_dom_cargo'], 1);
                $depIntCargoKg[] = round($val['dep_int_cargo'], 1);

                $arrBaggageKg[] = round($val['arr_bagg'], 1);
                $depBaggageKg[] = round($val['dep_bagg'], 1);
                $baggageKg[] = round($val['arr_bagg'] + $val['dep_bagg'], 1);
            }
        } elseif ($dateScope === 'ALL_PERIOD' || ($dateScope !== 'DAY' && count($dateKeys) > 1)) {
            // Multi-day / Full-Range granularity: Daily timeline
            $granularity = 'daily';
            $dailyMap = [];
            foreach ($dateKeys as $dk) {
                $dailyMap[$dk] = [
                    'label' => date('d M', strtotime($dk)),
                    'full_date' => date('d M Y', strtotime($dk)),
                    'arr_dom' => 0, 'arr_int' => 0, 'dep_dom' => 0, 'dep_int' => 0,
                    'arr_pax' => 0, 'dep_pax' => 0,
                    'arr_dom_pax' => 0, 'arr_int_pax' => 0, 'dep_dom_pax' => 0, 'dep_int_pax' => 0,
                    'arr_cargo' => 0.0, 'dep_cargo' => 0.0,
                    'arr_dom_cargo' => 0.0, 'arr_int_cargo' => 0.0, 'dep_dom_cargo' => 0.0, 'dep_int_cargo' => 0.0,
                    'arr_bagg' => 0.0, 'dep_bagg' => 0.0,
                ];
            }

            foreach ($records as $r) {
                $d = $r['operational_date'] ?? ($r['flight_date'] ?? '');
                $stdD = FlightDailyReportFilter::standardizeDate($d);
                if (!isset($dailyMap[$stdD])) {
                    if (isset($dailyMap[$d])) {
                        $stdD = $d;
                    } else {
                        continue;
                    }
                }

                $isArr = ($r['direction'] ?? '') === 'ARRIVAL';
                $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? 'DOMESTIC')));
                $isDom = ($tr === 'DOM' || $tr === 'DOMESTIC');

                $pax = (int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0);
                if ($pax === 0) { $pax = (int)($r['load'] ?? ($r['pax_total'] ?? 0)); }

                $cKg = (float)($r['cargo_kg'] ?? 0.0);
                $bKg = (float)($r['baggage_kg'] ?? 0.0);

                if ($isArr) {
                    if ($isDom) {
                        $dailyMap[$stdD]['arr_dom']++;
                        $dailyMap[$stdD]['arr_dom_pax'] += $pax;
                        $dailyMap[$stdD]['arr_dom_cargo'] += $cKg;
                    } else {
                        $dailyMap[$stdD]['arr_int']++;
                        $dailyMap[$stdD]['arr_int_pax'] += $pax;
                        $dailyMap[$stdD]['arr_int_cargo'] += $cKg;
                    }
                    $dailyMap[$stdD]['arr_pax'] += $pax;
                    $dailyMap[$stdD]['arr_cargo'] += $cKg;
                    $dailyMap[$stdD]['arr_bagg'] += $bKg;
                } else {
                    if ($isDom) {
                        $dailyMap[$stdD]['dep_dom']++;
                        $dailyMap[$stdD]['dep_dom_pax'] += $pax;
                        $dailyMap[$stdD]['dep_dom_cargo'] += $cKg;
                    } else {
                        $dailyMap[$stdD]['dep_int']++;
                        $dailyMap[$stdD]['dep_int_pax'] += $pax;
                        $dailyMap[$stdD]['dep_int_cargo'] += $cKg;
                    }
                    $dailyMap[$stdD]['dep_pax'] += $pax;
                    $dailyMap[$stdD]['dep_cargo'] += $cKg;
                    $dailyMap[$stdD]['dep_bagg'] += $bKg;
                }
            }

            $arrDomPax = [];
            $arrIntPax = [];
            $depDomPax = [];
            $depIntPax = [];
            $arrDomCargoKg = [];
            $arrIntCargoKg = [];
            $depDomCargoKg = [];
            $depIntCargoKg = [];

            foreach ($dailyMap as $dk => $val) {
                $labels[] = $val['label'];
                $fullDates[] = $val['full_date'];
                $arrDomFlights[] = $val['arr_dom'];
                $arrIntFlights[] = $val['arr_int'];
                $depDomFlights[] = $val['dep_dom'];
                $depIntFlights[] = $val['dep_int'];
                $arrTot = $val['arr_dom'] + $val['arr_int'];
                $depTot = $val['dep_dom'] + $val['dep_int'];
                $arrivals[] = $arrTot;
                $departures[] = $depTot;
                $totalFlights[] = $arrTot + $depTot;

                $arrPassengers[] = $val['arr_pax'];
                $depPassengers[] = $val['dep_pax'];
                $passengers[] = $val['arr_pax'] + $val['dep_pax'];
                $arrDomPax[] = $val['arr_dom_pax'];
                $arrIntPax[] = $val['arr_int_pax'];
                $depDomPax[] = $val['dep_dom_pax'];
                $depIntPax[] = $val['dep_int_pax'];

                $arrCargoKg[] = round($val['arr_cargo'], 1);
                $depCargoKg[] = round($val['dep_cargo'], 1);
                $totC = $val['arr_cargo'] + $val['dep_cargo'];
                $cargoKg[] = round($totC, 1);
                $cargoTon[] = round($totC / 1000, 2);
                $arrCargoTon[] = round($val['arr_cargo'] / 1000, 2);
                $depCargoTon[] = round($val['dep_cargo'] / 1000, 2);
                $arrDomCargoKg[] = round($val['arr_dom_cargo'], 1);
                $arrIntCargoKg[] = round($val['arr_int_cargo'], 1);
                $depDomCargoKg[] = round($val['dep_dom_cargo'], 1);
                $depIntCargoKg[] = round($val['dep_int_cargo'], 1);

                $arrBaggageKg[] = round($val['arr_bagg'], 1);
                $depBaggageKg[] = round($val['dep_bagg'], 1);
                $baggageKg[] = round($val['arr_bagg'] + $val['dep_bagg'], 1);
            }
        } else {
            // Single-day -> Hourly granularity strictly 24 hours (00:00 to 23:00)
            $granularity = 'hourly';
            $hourlyMap = [];
            for ($h = 0; $h < 24; $h++) {
                $lbl = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                $range = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00–' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':59';
                $hourlyMap[$h] = [
                    'label' => $lbl,
                    'full_date' => $range,
                    'arr_dom' => 0, 'arr_int' => 0, 'dep_dom' => 0, 'dep_int' => 0,
                    'arr_pax' => 0, 'dep_pax' => 0,
                    'arr_dom_pax' => 0, 'arr_int_pax' => 0, 'dep_dom_pax' => 0, 'dep_int_pax' => 0,
                    'arr_cargo' => 0.0, 'dep_cargo' => 0.0,
                    'arr_dom_cargo' => 0.0, 'arr_int_cargo' => 0.0, 'dep_dom_cargo' => 0.0, 'dep_int_cargo' => 0.0,
                    'arr_bagg' => 0.0, 'dep_bagg' => 0.0,
                ];
            }

            foreach ($records as $r) {
                $isArr = ($r['direction'] ?? '') === 'ARRIVAL' || ($r['movement_type'] ?? '') === 'A' || ($r['flow'] ?? '') === 'ARR';
                $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? ($r['dom_int'] ?? 'DOMESTIC'))));
                $isDom = ($tr === 'DOM' || $tr === 'DOMESTIC' || $tr === 'D');

                $ts = '';
                if ($timeBasis === 'actual') {
                    $ts = $isArr ? ($r['aibt'] ?? ($r['sibt'] ?? '')) : ($r['aobt'] ?? ($r['sobt'] ?? ''));
                } else {
                    $ts = $isArr ? ($r['sibt'] ?? ($r['aibt'] ?? '')) : ($r['sobt'] ?? ($r['aobt'] ?? ''));
                }

                $h = 0;
                if ($ts && $ts !== 'N/A' && preg_match('/(\d{1,2}):(\d{2})/', $ts, $m)) {
                    $h = (int)$m[1];
                }
                if ($h < 0 || $h > 23) $h = 0;

                $pax = (int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0);
                if ($pax === 0) {
                    $pax = (int)($r['load'] ?? ($r['pax_total'] ?? ($r['total_passenger'] ?? 0)));
                }

                $cKg = (float)($r['cargo_kg'] ?? 0.0);
                $bKg = (float)($r['baggage_kg'] ?? 0.0);

                if ($isArr) {
                    if ($isDom) {
                        $hourlyMap[$h]['arr_dom']++;
                        $hourlyMap[$h]['arr_dom_pax'] += $pax;
                        $hourlyMap[$h]['arr_dom_cargo'] += $cKg;
                    } else {
                        $hourlyMap[$h]['arr_int']++;
                        $hourlyMap[$h]['arr_int_pax'] += $pax;
                        $hourlyMap[$h]['arr_int_cargo'] += $cKg;
                    }
                    $hourlyMap[$h]['arr_pax'] += $pax;
                    $hourlyMap[$h]['arr_cargo'] += $cKg;
                    $hourlyMap[$h]['arr_bagg'] += $bKg;
                } else {
                    if ($isDom) {
                        $hourlyMap[$h]['dep_dom']++;
                        $hourlyMap[$h]['dep_dom_pax'] += $pax;
                        $hourlyMap[$h]['dep_dom_cargo'] += $cKg;
                    } else {
                        $hourlyMap[$h]['dep_int']++;
                        $hourlyMap[$h]['dep_int_pax'] += $pax;
                        $hourlyMap[$h]['dep_int_cargo'] += $cKg;
                    }
                    $hourlyMap[$h]['dep_pax'] += $pax;
                    $hourlyMap[$h]['dep_cargo'] += $cKg;
                    $hourlyMap[$h]['dep_bagg'] += $bKg;
                }
            }

            $arrDomPax = [];
            $arrIntPax = [];
            $depDomPax = [];
            $depIntPax = [];
            $arrDomCargoKg = [];
            $arrIntCargoKg = [];
            $depDomCargoKg = [];
            $depIntCargoKg = [];

            for ($h = 0; $h < 24; $h++) {
                $labels[] = $hourlyMap[$h]['label'];
                $fullDates[] = $hourlyMap[$h]['full_date'];
                $arrDomFlights[] = $hourlyMap[$h]['arr_dom'];
                $arrIntFlights[] = $hourlyMap[$h]['arr_int'];
                $depDomFlights[] = $hourlyMap[$h]['dep_dom'];
                $depIntFlights[] = $hourlyMap[$h]['dep_int'];
                $arrTot = $hourlyMap[$h]['arr_dom'] + $hourlyMap[$h]['arr_int'];
                $depTot = $hourlyMap[$h]['dep_dom'] + $hourlyMap[$h]['dep_int'];
                $arrivals[] = $arrTot;
                $departures[] = $depTot;
                $totalFlights[] = $arrTot + $depTot;

                $arrPassengers[] = $hourlyMap[$h]['arr_pax'];
                $depPassengers[] = $hourlyMap[$h]['dep_pax'];
                $passengers[] = $hourlyMap[$h]['arr_pax'] + $hourlyMap[$h]['dep_pax'];
                $arrDomPax[] = $hourlyMap[$h]['arr_dom_pax'];
                $arrIntPax[] = $hourlyMap[$h]['arr_int_pax'];
                $depDomPax[] = $hourlyMap[$h]['dep_dom_pax'];
                $depIntPax[] = $hourlyMap[$h]['dep_int_pax'];

                $arrCargoKg[] = round($hourlyMap[$h]['arr_cargo'], 1);
                $depCargoKg[] = round($hourlyMap[$h]['dep_cargo'], 1);
                $totC = $hourlyMap[$h]['arr_cargo'] + $hourlyMap[$h]['dep_cargo'];
                $cargoKg[] = round($totC, 1);
                $cargoTon[] = round($totC / 1000, 2);
                $arrCargoTon[] = round($hourlyMap[$h]['arr_cargo'] / 1000, 2);
                $depCargoTon[] = round($hourlyMap[$h]['dep_cargo'] / 1000, 2);
                $arrDomCargoKg[] = round($hourlyMap[$h]['arr_dom_cargo'], 1);
                $arrIntCargoKg[] = round($hourlyMap[$h]['arr_int_cargo'], 1);
                $depDomCargoKg[] = round($hourlyMap[$h]['dep_dom_cargo'], 1);
                $depIntCargoKg[] = round($hourlyMap[$h]['dep_int_cargo'], 1);

                $arrBaggageKg[] = round($hourlyMap[$h]['arr_bagg'], 1);
                $depBaggageKg[] = round($hourlyMap[$h]['dep_bagg'], 1);
                $baggageKg[] = round($hourlyMap[$h]['arr_bagg'] + $hourlyMap[$h]['dep_bagg'], 1);
            }
        }

        return [
            'granularity'          => $granularity,
            'labels'               => $labels,
            'full_dates'           => $fullDates,
            'time_ranges'          => $fullDates,
            'arr_dom_flights'      => $arrDomFlights,
            'arr_int_flights'      => $arrIntFlights,
            'dep_dom_flights'      => $depDomFlights,
            'dep_int_flights'      => $depIntFlights,
            'arrivals'             => $arrivals,
            'departures'           => $departures,
            'total_flights'        => $totalFlights,
            'arr_passengers'       => $arrPassengers,
            'dep_passengers'       => $depPassengers,
            'arr_dom_passengers'   => $arrDomPax,
            'arr_int_passengers'   => $arrIntPax,
            'dep_dom_passengers'   => $depDomPax,
            'dep_int_passengers'   => $depIntPax,
            'passengers'           => $passengers,
            'arr_cargo_kg'         => $arrCargoKg,
            'dep_cargo_kg'         => $depCargoKg,
            'arr_dom_cargo_kg'     => $arrDomCargoKg,
            'arr_int_cargo_kg'     => $arrIntCargoKg,
            'dep_dom_cargo_kg'     => $depDomCargoKg,
            'dep_int_cargo_kg'     => $depIntCargoKg,
            'cargo_kg'             => $cargoKg,
            'cargo_ton'            => $cargoTon,
            'arr_cargo_ton'        => $arrCargoTon,
            'dep_cargo_ton'        => $depCargoTon,
            'arr_baggage_kg'       => $arrBaggageKg,
            'dep_baggage_kg'       => $depBaggageKg,
            'baggage_kg'           => $baggageKg,
            'total_arrivals'       => array_sum($arrivals),
            'total_departures'     => array_sum($departures),
            'total_flights_count'  => array_sum($totalFlights),
            'total_passengers'     => array_sum($passengers),
            'total_arr_passengers' => array_sum($arrPassengers),
            'total_dep_passengers' => array_sum($depPassengers),
            'total_cargo_kg'       => round(array_sum($cargoKg), 1),
            'total_cargo_ton'      => round(array_sum($cargoTon), 2),
            'total_baggage_kg'     => round(array_sum($baggageKg), 1),
            'series'               => [
                'arrival'   => $arrivals,
                'departure' => $departures,
                'flights'   => $totalFlights,
                'passenger' => $passengers,
                'cargo'     => $cargoKg,
            ],
        ];
    }
}

