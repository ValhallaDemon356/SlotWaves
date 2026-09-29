<?php

namespace App\Services\FlightDailyReport;

use App\Models\Airport;

class HourlyChartService
{
    /**
     * Build the 3 Mentor Hourly Charts data payload strictly covering 24 hours (00 to 23).
     *
     * @param  array  $records      Normalized FDR records
     * @param  string $airportCode  IATA code used for capacity profile
     * @param  string $timeBasis    'scheduled' (SIBT/SOBT) | 'actual' (AIBT/AOBT)
     * @param  string $reportDate   Y-m-d of the analysis day (for cross-day detection)
     */
    public function buildHourlyCharts(
        array  $records,
        string $airportCode = 'CGK',
        string $timeBasis   = 'scheduled',
        string $reportDate  = ''
    ): array {
        $hourlyData   = [];
        $prevDayCount = 0;   // flights whose sched time falls on the *previous* day

        // Resolve baseline airport capacity
        $capacityProfile = $this->resolveRunwayCapacityProfile($airportCode);

        // Initialize all 24 hours (00 to 23)
        for ($h = 0; $h < 24; $h++) {
            $hStr = str_pad((string)$h, 2, '0', STR_PAD_LEFT);
            $cap  = $capacityProfile[$h] ?? 40;

            $hourlyData[$h] = [
                'hour'            => $h,
                'hour_label'      => "{$hStr}:00",
                'time_range'      => "{$hStr}:00–{$hStr}:59",
                'runway_capacity' => $cap,

                // ── Scheduled-basis aggregates (Chart 2 = Dep, Chart 3 = Arr) ─
                'total_plan'    => 0,
                'total_irregular' => 0,
                'dep_plan'      => 0,
                'dep_dom_plan'  => 0,   // DEP Domestic  (dark blue  #1D4ED8)
                'dep_int_plan'  => 0,   // DEP Intl      (light blue #60A5FA)
                'dep_irregular' => 0,
                'arr_plan'      => 0,
                'arr_dom_plan'  => 0,   // ARR Domestic  (dark amber #CA8A04)
                'arr_int_plan'  => 0,   // ARR Intl      (light yel. #FDE047)
                'arr_irregular' => 0,

                // ── Actual-basis aggregates ─────────────────────────────────────
                'total_realized'    => 0,
                'dep_realized'      => 0,
                'dep_dom_realized'  => 0,
                'dep_int_realized'  => 0,
                'arr_realized'      => 0,
                'arr_dom_realized'  => 0,
                'arr_int_realized'  => 0,
            ];
        }

        // ── Aggregate records into hourly bins ───────────────────────────────────
        foreach ($records as $r) {
            if (($r['row_type'] ?? '') === 'SUMMARY' || strcasecmp($r['air_line'] ?? '', 'PAX ALL') === 0 || strcasecmp($r['operator'] ?? '', 'PAX ALL') === 0) {
                continue;
            }
            $isArr  = (($r['direction'] ?? '') === 'ARRIVAL');
            $isIrreg = !empty($r['is_irregular']) || !empty($r['irregular']);
            $isRealized = array_key_exists('is_realized', $r)
                ? (!empty($r['is_realized']) || !empty($r['realization']))
                : (array_key_exists('realization', $r) ? !empty($r['realization']) : true);

            // ── Traffic type (Domestic vs International) ─────────────────────
            $traffic = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? 'DOMESTIC')));
            $isDom   = ($traffic === 'DOMESTIC' || $traffic === 'DOM');

            // ── 1. SCHEDULED series (SIBT for ARR, SOBT for DEP) ────────────
            $schedHour = $r['scheduled_hour'] ?? null;
            if ($schedHour === null) {
                $schedStr = $isArr
                    ? ($r['sibt'] ?? ($r['arr_sched'] ?? null))
                    : ($r['sobt'] ?? ($r['dep_sched'] ?? null));
                if (!empty($schedStr) && $schedStr !== 'N/A'
                        && preg_match('/(\d{1,2}):(\d{2})/', (string)$schedStr, $tm)) {
                    $schedHour = (int)$tm[1];
                    // Cross-day detection: if the full datetime contains a prior date
                    if ($reportDate && !$isIrreg) {
                        $schedDateStr = preg_match('/(\d{4}-\d{2}-\d{2})/', (string)$schedStr, $dm)
                            ? $dm[1]
                            : ($r['flight_date'] ?? '');
                        if ($schedDateStr && $schedDateStr < $reportDate) {
                            $prevDayCount++;
                        }
                    }
                } elseif (isset($r['hour']) && is_numeric($r['hour'])) {
                    $schedHour = (int)$r['hour'];
                }
            }

            if ($schedHour !== null && $schedHour >= 0 && $schedHour < 24 && !$isIrreg) {
                if ($isArr) {
                    $hourlyData[$schedHour]['arr_plan']++;
                    if ($isDom) { $hourlyData[$schedHour]['arr_dom_plan']++; }
                    else        { $hourlyData[$schedHour]['arr_int_plan']++; }
                } else {
                    $hourlyData[$schedHour]['dep_plan']++;
                    if ($isDom) { $hourlyData[$schedHour]['dep_dom_plan']++; }
                    else        { $hourlyData[$schedHour]['dep_int_plan']++; }
                }
                $hourlyData[$schedHour]['total_plan'] =
                    $hourlyData[$schedHour]['arr_plan'] + $hourlyData[$schedHour]['dep_plan'];
            }

            // Irregulars: use actual hour if realized, else scheduled hour
            if ($isIrreg) {
                $irrH = $schedHour;
                // Try actual timestamp first
                $actStr2 = $isArr
                    ? ($r['aibt'] ?? ($r['arr_actual'] ?? null))
                    : ($r['aobt'] ?? ($r['dep_actual'] ?? null));
                if (!empty($actStr2) && $actStr2 !== 'N/A'
                        && preg_match('/(\d{1,2}):(\d{2})/', (string)$actStr2, $tm2)) {
                    $irrH = (int)$tm2[1];
                }
                if ($irrH !== null && $irrH >= 0 && $irrH < 24) {
                    if ($isArr) { $hourlyData[$irrH]['arr_irregular']++; }
                    else        { $hourlyData[$irrH]['dep_irregular']++; }
                    $hourlyData[$irrH]['total_irregular'] =
                        $hourlyData[$irrH]['arr_irregular'] + $hourlyData[$irrH]['dep_irregular'];
                }
            }

            // ── 2. ACTUAL series (AIBT for ARR, AOBT for DEP) ───────────────
            if ($isRealized) {
                $actHour = $r['actual_hour'] ?? null;
                if ($actHour === null) {
                    $actStr = $isArr
                        ? ($r['aibt'] ?? ($r['arr_actual'] ?? null))
                        : ($r['aobt'] ?? ($r['dep_actual'] ?? null));
                    if (!empty($actStr) && $actStr !== 'N/A'
                            && preg_match('/(\d{1,2}):(\d{2})/', (string)$actStr, $tm)) {
                        $actHour = (int)$tm[1];
                    } elseif (isset($r['hour']) && is_numeric($r['hour'])) {
                        $actHour = (int)$r['hour'];
                    }
                }

                if ($actHour !== null && $actHour >= 0 && $actHour < 24) {
                    if ($isArr) {
                        $hourlyData[$actHour]['arr_realized']++;
                        if ($isDom) { $hourlyData[$actHour]['arr_dom_realized']++; }
                        else        { $hourlyData[$actHour]['arr_int_realized']++; }
                    } else {
                        $hourlyData[$actHour]['dep_realized']++;
                        if ($isDom) { $hourlyData[$actHour]['dep_dom_realized']++; }
                        else        { $hourlyData[$actHour]['dep_int_realized']++; }
                    }
                    $hourlyData[$actHour]['total_realized'] =
                        $hourlyData[$actHour]['arr_realized'] + $hourlyData[$actHour]['dep_realized'];
                }
            }
        }

        // ── Compute tooltips and capacity status per hour ─────────────────────
        for ($h = 0; $h < 24; $h++) {
            $row  = &$hourlyData[$h];
            $plan = $row['total_plan'];
            $act  = $row['total_realized'];
            $irreg = $row['total_irregular'];
            $cap  = $row['runway_capacity'];

            $diff   = $cap - $plan;
            $status = 'AVAILABLE';
            if ($plan > $cap)                  { $status = 'OVER'; }
            elseif ($plan === $cap && $cap > 0) { $status = 'FULL'; }

            $row['difference'] = $diff;
            $row['delta']      = $diff;
            $row['status']     = $status;
            $row['tooltip']    = "{$row['time_range']} | Plan: {$plan} | Actual: {$act} | Irreg: {$irreg} | Cap: {$cap} | {$status}";

            // Expose which column is "active" for the chosen basis so JS can easily pick
            // one set of values without branching.
            $row['active_dep_dom']  = ($timeBasis === 'actual') ? $row['dep_dom_realized'] : $row['dep_dom_plan'];
            $row['active_dep_int']  = ($timeBasis === 'actual') ? $row['dep_int_realized'] : $row['dep_int_plan'];
            $row['active_arr_dom']  = ($timeBasis === 'actual') ? $row['arr_dom_realized'] : $row['arr_dom_plan'];
            $row['active_arr_int']  = ($timeBasis === 'actual') ? $row['arr_int_realized'] : $row['arr_int_plan'];
            $row['active_total']    = ($timeBasis === 'actual') ? $row['total_realized']   : $row['total_plan'];
        }
        unset($row);

        // ── Peak Hour: return ALL hours that share the maximum value ─────────
        $activeColumn = ($timeBasis === 'actual') ? 'total_realized' : 'total_plan';
        $maxMovements = max(0, ...array_column($hourlyData, $activeColumn));

        $peakHours = [];
        for ($h = 0; $h < 24; $h++) {
            if ($hourlyData[$h][$activeColumn] === $maxMovements && $maxMovements > 0) {
                $peakHours[] = [
                    'hour'       => $h,
                    'hour_label' => $hourlyData[$h]['hour_label'],
                    'time_range' => $hourlyData[$h]['time_range'],
                    'movements'  => $maxMovements,
                ];
            }
        }

        // Build display string (handles ties like "13:00–13:59 & 15:00–15:59 (16 movements)")
        if (empty($peakHours)) {
            $peakDisplay = 'N/A';
        } elseif (count($peakHours) === 1) {
            $peakDisplay = "{$peakHours[0]['time_range']} ({$maxMovements} movements)";
        } else {
            $ranges = implode(' & ', array_column($peakHours, 'time_range'));
            $peakDisplay = "{$ranges} ({$maxMovements} movements)";
        }

        // Backward-compat: single peak hour object (use first peak hour)
        $primaryPeak = $peakHours[0] ?? ['hour' => 0, 'hour_label' => 'N/A', 'time_range' => 'N/A', 'movements' => 0];
        $peakRow     = $hourlyData[$primaryPeak['hour']];
        $peakHour    = [
            'hour'        => $primaryPeak['hour'],
            'hour_label'  => $primaryPeak['hour_label'],
            'time_range'  => $primaryPeak['time_range'],
            'movements'   => $maxMovements,
            'plan'        => $peakRow['total_plan'],
            'actual'      => $peakRow['total_realized'],
            'arrivals'    => $peakRow['arr_plan'],
            'departures'  => $peakRow['dep_plan'],
            'display'     => $peakDisplay,
            'all_peaks'   => $peakHours,   // ALL tied peaks for UI
            'basis'       => $timeBasis,
        ];

        // Construct chart payloads
        return [
            'hours'               => array_column($hourlyData, 'hour_label'),
            'hourly_data'         => array_values($hourlyData),
            'chart1_movement'     => $this->buildChart1Payload($hourlyData),
            'chart2_departure'    => $this->buildChart2Payload($hourlyData),
            'chart3_arrival'      => $this->buildChart3Payload($hourlyData),
            'peak_hour'           => $peakHour,
            'peak_hours'          => $peakHours,
            'max_hourly_movement' => $maxMovements,
            'time_basis'          => $timeBasis,
            'prev_day_count'      => $prevDayCount,
        ];
    }

    /**
     * Resolve variable runway capacity profile across 24 hours.
     * Never flat - varies realistically based on airport limits and peak/off-peak operations.
     */
    public function resolveRunwayCapacityProfile(string $airportCode): array
    {
        $baseCap = 54; // Default standard base
        try {
            $ap = Airport::where('iata_code', $airportCode)->first();
            if ($ap && !empty($ap->aircraft_capacity) && $ap->aircraft_capacity > 0) {
                $baseCap = (int)$ap->aircraft_capacity;
            } elseif ($airportCode === 'CGK') {
                $baseCap = 72;
            } elseif ($airportCode === 'BDO') {
                $baseCap = 24;
            } elseif ($airportCode === 'BTJ') {
                $baseCap = 16;
            } elseif ($airportCode === 'SUB' || $airportCode === 'DPS') {
                $baseCap = 42;
            }
        } catch (\Throwable $e) {}

        // Hourly capacity variation coefficients based on aviation operational profiles
        // (Curfew / night maintenance / peak flow / midday wave)
        $hourlyFactors = [
            0  => 0.40,  // 00:00 - Night low
            1  => 0.35,  // 01:00
            2  => 0.35,  // 02:00
            3  => 0.40,  // 03:00
            4  => 0.50,  // 04:00 - Pre-dawn prep
            5  => 0.70,  // 05:00 - Early departures wave
            6  => 0.95,  // 06:00 - Morning peak start
            7  => 1.00,  // 07:00 - Morning peak max
            8  => 1.00,  // 08:00 - Morning peak
            9  => 0.92,  // 09:00
            10 => 0.85,  // 10:00 - Midday lull
            11 => 0.88,  // 11:00
            12 => 0.90,  // 12:00 - Midday peak wave
            13 => 0.92,  // 13:00
            14 => 0.88,  // 14:00
            15 => 0.95,  // 15:00 - Afternoon build-up
            16 => 1.00,  // 16:00 - Evening peak
            17 => 1.00,  // 17:00 - Evening peak max
            18 => 0.95,  // 18:00 - Sunset wave
            19 => 0.90,  // 19:00
            20 => 0.80,  // 20:00 - Late bank
            21 => 0.70,  // 21:00
            22 => 0.55,  // 22:00 - Night draw down
            23 => 0.45,  // 23:00 - Night
        ];

        $profile = [];
        foreach ($hourlyFactors as $hour => $factor) {
            $profile[$hour] = max(4, (int)round($baseCap * $factor));
        }

        return $profile;
    }

    /**
     * Chart 1: ARRIVAL–DEPARTURE MOVEMENT
     * - Grouped/layered bars: Plan (PPRP) (light peach bar) vs Realized movement with Irregular Flt (darker gold/amber segment).
     * - Line overlay: Runway Capacity (continuous red line showing hourly capacity variations).
     */
    protected function buildChart1Payload(array $hourlyData): array
    {
        return [
            'id'          => 'chart1_movement',
            'title'       => 'ARRIVAL–DEPARTURE MOVEMENT (24 HOURS)',
            'labels'      => array_column($hourlyData, 'hour_label'),
            'datasets'    => [
                [
                    'type'            => 'line',
                    'label'           => 'Runway Capacity',
                    'data'            => array_column($hourlyData, 'runway_capacity'),
                    'borderColor'     => '#EF4444', // Red line
                    'backgroundColor' => 'transparent',
                    'borderWidth'     => 2.5,
                    'pointRadius'     => 3,
                    'pointHoverRadius'=> 5,
                    'pointBackgroundColor' => '#EF4444',
                    'tension'         => 0.25,
                    'order'           => 1,
                ],
                [
                    'type'            => 'bar',
                    'label'           => 'Plan (PPRP)',
                    'data'            => array_column($hourlyData, 'total_plan'),
                    'backgroundColor' => '#FDBA74', // Light peach
                    'borderColor'     => '#FB923C',
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                    'order'           => 2,
                ],
                [
                    'type'            => 'bar',
                    'label'           => 'Irregular Flt',
                    'data'            => array_column($hourlyData, 'total_irregular'),
                    'backgroundColor' => '#D97706', // Darker gold/amber
                    'borderColor'     => '#B45309',
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                    'order'           => 3,
                ],
            ],
            'tooltips'    => array_column($hourlyData, 'tooltip'),
        ];
    }

    /**
     * Chart 2: DEPARTURE MOVEMENT
     * - Blue semantic palette: Plan (PPRP) (light blue bar) vs Irregular Flt (darker blue overlay/grouped bar).
     */
    protected function buildChart2Payload(array $hourlyData): array
    {
        return [
            'id'          => 'chart2_departure',
            'title'       => 'DEPARTURE MOVEMENT (24 HOURS)',
            'labels'      => array_column($hourlyData, 'hour_label'),
            'datasets'    => [
                [
                    'type'            => 'bar',
                    'label'           => 'Plan (PPRP)',
                    'data'            => array_column($hourlyData, 'dep_plan'),
                    'backgroundColor' => '#93C5FD', // Light blue
                    'borderColor'     => '#60A5FA',
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                ],
                [
                    'type'            => 'bar',
                    'label'           => 'Irregular Flt',
                    'data'            => array_column($hourlyData, 'dep_irregular'),
                    'backgroundColor' => '#1D4ED8', // Darker blue
                    'borderColor'     => '#1E40AF',
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                ],
            ],
            'tooltips'    => array_map(function ($row) {
                return "{$row['time_range']} | Dep Plan: {$row['dep_plan']} A/C | Dep Irregular: {$row['dep_irregular']} A/C";
            }, $hourlyData),
        ];
    }

    /**
     * Chart 3: ARRIVAL MOVEMENT
     * - Salmon/magenta semantic palette: Plan (PPRP) (light salmon bar) vs Irregular Flt (dark magenta overlay/grouped bar).
     */
    protected function buildChart3Payload(array $hourlyData): array
    {
        return [
            'id'          => 'chart3_arrival',
            'title'       => 'ARRIVAL MOVEMENT (24 HOURS)',
            'labels'      => array_column($hourlyData, 'hour_label'),
            'datasets'    => [
                [
                    'type'            => 'bar',
                    'label'           => 'Plan (PPRP)',
                    'data'            => array_column($hourlyData, 'arr_plan'),
                    'backgroundColor' => '#FDA4AF', // Light salmon
                    'borderColor'     => '#FB7185',
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                ],
                [
                    'type'            => 'bar',
                    'label'           => 'Irregular Flt',
                    'data'            => array_column($hourlyData, 'arr_irregular'),
                    'backgroundColor' => '#BE185D', // Dark magenta
                    'borderColor'     => '#9D174D',
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                ],
            ],
            'tooltips'    => array_map(function ($row) {
                return "{$row['time_range']} | Arr Plan: {$row['arr_plan']} A/C | Arr Irregular: {$row['arr_irregular']} A/C";
            }, $hourlyData),
        ];
    }

    /**
     * Render inline SVG for PDF exports without relying on client-side canvas.
     */
    public function renderChartSvg(string $chartType, array $hourlyData, int $width = 750, int $height = 180): string
    {
        $padding = ['top' => 20, 'right' => 20, 'bottom' => 30, 'left' => 35];
        $plotW = $width - $padding['left'] - $padding['right'];
        $plotH = $height - $padding['top'] - $padding['bottom'];

        // Determine max Y value
        $maxY = 10;
        foreach ($hourlyData as $row) {
            $cap = $row['runway_capacity'] ?? 0;
            $p = $row['total_plan'] ?? 0;
            $i = $row['total_irregular'] ?? 0;
            $dp = $row['dep_plan'] ?? 0;
            $di = $row['dep_irregular'] ?? 0;
            $ap = $row['arr_plan'] ?? 0;
            $ai = $row['arr_irregular'] ?? 0;

            if ($chartType === 'movement') {
                $maxY = max($maxY, $cap, $p, $i, ($p + $i));
            } elseif ($chartType === 'departure') {
                $maxY = max($maxY, $dp, $di, ($dp + $di));
            } else {
                $maxY = max($maxY, $ap, $ai, ($ap + $ai));
            }
        }
        $maxY = ceil($maxY * 1.15); // Add headroom

        $colWidth = $plotW / 24;
        $barWidth = max(4, $colWidth * 0.38);

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\" style=\"background-color:#ffffff; font-family:sans-serif;\">\n";

        // Grid lines (4 horizontal ticks)
        for ($t = 0; $t <= 4; $t++) {
            $val = round(($maxY / 4) * $t);
            $y = $padding['top'] + $plotH - ($plotH * ($val / $maxY));
            $svg .= "<line x1=\"{$padding['left']}\" y1=\"{$y}\" x2=\"" . ($width - $padding['right']) . "\" y2=\"{$y}\" stroke=\"#E2E8F0\" stroke-width=\"1\" stroke-dasharray=\"2,2\" />\n";
            $svg .= "<text x=\"" . ($padding['left'] - 6) . "\" y=\"" . ($y + 3) . "\" fill=\"#64748B\" font-size=\"9\" text-anchor=\"end\">{$val}</text>\n";
        }

        // Draw Bars
        $pointsCapacity = [];
        for ($h = 0; $h < 24; $h++) {
            $row = $hourlyData[$h];
            $xCenter = $padding['left'] + ($h * $colWidth) + ($colWidth / 2);

            if ($chartType === 'movement') {
                $valPlan = $row['total_plan'];
                $valIrreg = $row['total_irregular'];
                $colorPlan = '#FDBA74';
                $colorIrreg = '#D97706';
            } elseif ($chartType === 'departure') {
                $valPlan = $row['dep_plan'];
                $valIrreg = $row['dep_irregular'];
                $colorPlan = '#93C5FD';
                $colorIrreg = '#1D4ED8';
            } else {
                $valPlan = $row['arr_plan'];
                $valIrreg = $row['arr_irregular'];
                $colorPlan = '#FDA4AF';
                $colorIrreg = '#BE185D';
            }

            // Plan Bar
            $hPlan = ($valPlan / $maxY) * $plotH;
            $yPlan = $padding['top'] + $plotH - $hPlan;
            $xPlan = $xCenter - $barWidth - 1;
            if ($hPlan > 0) {
                $svg .= "<rect x=\"{$xPlan}\" y=\"{$yPlan}\" width=\"{$barWidth}\" height=\"{$hPlan}\" fill=\"{$colorPlan}\" rx=\"2\" />\n";
            }

            // Irregular Bar
            $hIrreg = ($valIrreg / $maxY) * $plotH;
            $yIrreg = $padding['top'] + $plotH - $hIrreg;
            $xIrreg = $xCenter + 1;
            if ($hIrreg > 0) {
                $svg .= "<rect x=\"{$xIrreg}\" y=\"{$yIrreg}\" width=\"{$barWidth}\" height=\"{$hIrreg}\" fill=\"{$colorIrreg}\" rx=\"2\" />\n";
            }

            // Capacity Line Point (Chart 1 only)
            if ($chartType === 'movement') {
                $capVal = $row['runway_capacity'];
                $yCap = $padding['top'] + $plotH - (($capVal / $maxY) * $plotH);
                $pointsCapacity[] = "{$xCenter},{$yCap}";
            }

            // X-axis label (Every 2 hours to avoid overlap)
            if ($h % 2 === 0) {
                $label = str_pad((string)$h, 2, '0', STR_PAD_LEFT);
                $svg .= "<text x=\"{$xCenter}\" y=\"" . ($height - 10) . "\" fill=\"#64748B\" font-size=\"9\" text-anchor=\"middle\">{$label}</text>\n";
            }
        }

        // Draw Capacity Line for Chart 1
        if ($chartType === 'movement' && !empty($pointsCapacity)) {
            $polylinePoints = implode(' ', $pointsCapacity);
            $svg .= "<polyline points=\"{$polylinePoints}\" fill=\"none\" stroke=\"#EF4444\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\" />\n";
            foreach ($pointsCapacity as $pt) {
                [$px, $py] = explode(',', $pt);
                $svg .= "<circle cx=\"{$px}\" cy=\"{$py}\" r=\"2.5\" fill=\"#EF4444\" />\n";
            }
        }

        $svg .= "</svg>\n";
        return $svg;
    }

    /**
     * Render SVG for Flight Movement grouped bars in PDF export.
     */
    public function renderCombinedFlightMovementSvg(array $trend, string $legFilter = 'ALL', string $trafficFilter = 'ALL', int $width = 750, int $height = 180): string
    {
        $padding = ['top' => 20, 'right' => 20, 'bottom' => 30, 'left' => 35];
        $plotW = $width - $padding['left'] - $padding['right'];
        $plotH = $height - $padding['top'] - $padding['bottom'];

        $labels = $trend['labels'] ?? [];
        $n = count($labels);
        if ($n === 0) return "<svg width=\"{$width}\" height=\"{$height}\"></svg>";

        $arrDom = $trend['arr_dom_flights'] ?? [];
        $arrInt = $trend['arr_int_flights'] ?? [];
        $depDom = $trend['dep_dom_flights'] ?? [];
        $depInt = $trend['dep_int_flights'] ?? [];

        $showArr = ($legFilter === 'ALL' || $legFilter === 'ARR' || $legFilter === 'ARRIVAL');
        $showDep = ($legFilter === 'ALL' || $legFilter === 'DEP' || $legFilter === 'DEPARTURE');
        $showDom = ($trafficFilter === 'ALL' || $trafficFilter === 'DOM' || $trafficFilter === 'DOMESTIC');
        $showInt = ($trafficFilter === 'ALL' || $trafficFilter === 'INT' || $trafficFilter === 'INTL' || $trafficFilter === 'INTERNATIONAL');

        $activeSeries = [];
        if ($showArr && $showDom) $activeSeries[] = ['name' => 'Arr Dom', 'data' => $arrDom, 'color' => '#FBBF24'];
        if ($showArr && $showInt) $activeSeries[] = ['name' => 'Arr Int', 'data' => $arrInt, 'color' => '#B45309'];
        if ($showDep && $showDom) $activeSeries[] = ['name' => 'Dep Dom', 'data' => $depDom, 'color' => '#60A5FA'];
        if ($showDep && $showInt) $activeSeries[] = ['name' => 'Dep Int', 'data' => $depInt, 'color' => '#1D4ED8'];

        $numSeries = max(1, count($activeSeries));

        $maxY = 5;
        foreach ($activeSeries as $s) {
            foreach ($s['data'] as $v) {
                if ($v > $maxY) $maxY = $v;
            }
        }
        $maxY = ceil($maxY * 1.15);

        $colWidth = $plotW / $n;
        $barWidth = max(2, min(14, ($colWidth * 0.8) / $numSeries));

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\" style=\"background-color:#ffffff; font-family:sans-serif;\">\n";

        for ($t = 0; $t <= 4; $t++) {
            $val = round(($maxY / 4) * $t);
            $y = $padding['top'] + $plotH - ($plotH * ($val / $maxY));
            $svg .= "<line x1=\"{$padding['left']}\" y1=\"{$y}\" x2=\"" . ($width - $padding['right']) . "\" y2=\"{$y}\" stroke=\"#E2E8F0\" stroke-width=\"1\" stroke-dasharray=\"2,2\" />\n";
            $svg .= "<text x=\"" . ($padding['left'] - 6) . "\" y=\"" . ($y + 3) . "\" fill=\"#64748B\" font-size=\"8.5\" text-anchor=\"end\">{$val}</text>\n";
        }

        for ($i = 0; $i < $n; $i++) {
            $xCenter = $padding['left'] + ($i * $colWidth) + ($colWidth / 2);
            $groupWidth = $numSeries * $barWidth;
            $xStart = $xCenter - ($groupWidth / 2);

            foreach ($activeSeries as $sIdx => $s) {
                $val = $s['data'][$i] ?? 0;
                $hBar = ($maxY > 0) ? ($val / $maxY) * $plotH : 0;
                $yBar = $padding['top'] + $plotH - $hBar;
                $xBar = $xStart + ($sIdx * $barWidth);

                if ($hBar > 0) {
                    $svg .= "<rect x=\"{$xBar}\" y=\"{$yBar}\" width=\"" . max(1, $barWidth - 1) . "\" height=\"{$hBar}\" fill=\"{$s['color']}\" rx=\"1.5\" />\n";
                }
            }

            $showLabel = ($n <= 12) || ($n <= 24 && $i % 2 === 0) || ($i % 3 === 0);
            if ($showLabel && isset($labels[$i])) {
                $svg .= "<text x=\"{$xCenter}\" y=\"" . ($height - 10) . "\" fill=\"#64748B\" font-size=\"8.5\" text-anchor=\"middle\">{$labels[$i]}</text>\n";
            }
        }

        $svg .= "</svg>\n";
        return $svg;
    }

    /**
     * Render SVG for Passenger Trend Area + Line in PDF export.
     */
    public function renderCombinedPaxTrendSvg(array $trend, string $legFilter = 'ALL', int $width = 750, int $height = 150): string
    {
        $padding = ['top' => 20, 'right' => 20, 'bottom' => 25, 'left' => 45];
        $plotW = $width - $padding['left'] - $padding['right'];
        $plotH = $height - $padding['top'] - $padding['bottom'];

        $labels = $trend['labels'] ?? [];
        $n = count($labels);
        if ($n === 0) return "<svg width=\"{$width}\" height=\"{$height}\"></svg>";

        $arrPax = $trend['arr_passengers'] ?? [];
        $depPax = $trend['dep_passengers'] ?? [];

        $showArr = ($legFilter === 'ALL' || $legFilter === 'ARR' || $legFilter === 'ARRIVAL');
        $showDep = ($legFilter === 'ALL' || $legFilter === 'DEP' || $legFilter === 'DEPARTURE');

        $lines = [];
        if ($legFilter === 'ALL') {
            $lines[] = ['name' => 'Arrival Pax', 'data' => $arrPax, 'color' => '#F59E0B', 'fill' => '#FEF3C7'];
            $lines[] = ['name' => 'Departure Pax', 'data' => $depPax, 'color' => '#2563EB', 'fill' => '#DBEAFE'];
        } elseif ($showArr) {
            $lines[] = ['name' => 'Arrival Pax', 'data' => $arrPax, 'color' => '#F59E0B', 'fill' => '#FEF3C7'];
        } else {
            $lines[] = ['name' => 'Departure Pax', 'data' => $depPax, 'color' => '#2563EB', 'fill' => '#DBEAFE'];
        }

        $maxY = 100;
        foreach ($lines as $ln) {
            foreach ($ln['data'] as $v) {
                if ($v > $maxY) $maxY = $v;
            }
        }
        $maxY = ceil($maxY * 1.15);

        $colWidth = $plotW / max(1, $n - 1);

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\" style=\"background-color:#ffffff; font-family:sans-serif;\">\n";

        for ($t = 0; $t <= 3; $t++) {
            $val = round(($maxY / 3) * $t);
            $y = $padding['top'] + $plotH - ($plotH * ($val / $maxY));
            $svg .= "<line x1=\"{$padding['left']}\" y1=\"{$y}\" x2=\"" . ($width - $padding['right']) . "\" y2=\"{$y}\" stroke=\"#E2E8F0\" stroke-width=\"1\" stroke-dasharray=\"2,2\" />\n";
            $valFmt = ($val >= 1000) ? round($val / 1000, 1) . 'k' : $val;
            $svg .= "<text x=\"" . ($padding['left'] - 6) . "\" y=\"" . ($y + 3) . "\" fill=\"#64748B\" font-size=\"8.5\" text-anchor=\"end\">{$valFmt}</text>\n";
        }

        foreach ($lines as $ln) {
            $pts = [];
            for ($i = 0; $i < $n; $i++) {
                $val = $ln['data'][$i] ?? 0;
                $x = $padding['left'] + ($i * $colWidth);
                $y = $padding['top'] + $plotH - (($val / $maxY) * $plotH);
                $pts[] = "{$x},{$y}";
            }
            if (!empty($pts)) {
                $firstX = $padding['left'];
                $lastX = $padding['left'] + (($n - 1) * $colWidth);
                $bottomY = $padding['top'] + $plotH;
                $areaPoints = "{$firstX},{$bottomY} " . implode(' ', $pts) . " {$lastX},{$bottomY}";
                $svg .= "<polygon points=\"{$areaPoints}\" fill=\"{$ln['fill']}\" opacity=\"0.55\" />\n";

                $svg .= "<polyline points=\"" . implode(' ', $pts) . "\" fill=\"none\" stroke=\"{$ln['color']}\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" />\n";
                foreach ($pts as $pt) {
                    [$px, $py] = explode(',', $pt);
                    $svg .= "<circle cx=\"{$px}\" cy=\"{$py}\" r=\"2.5\" fill=\"#FFFFFF\" stroke=\"{$ln['color']}\" stroke-width=\"1.5\" />\n";
                }
            }
        }

        for ($i = 0; $i < $n; $i++) {
            $showLabel = ($n <= 12) || ($n <= 24 && $i % 2 === 0) || ($i % 3 === 0);
            if ($showLabel && isset($labels[$i])) {
                $x = $padding['left'] + ($i * $colWidth);
                $svg .= "<text x=\"{$x}\" y=\"" . ($height - 8) . "\" fill=\"#64748B\" font-size=\"8.5\" text-anchor=\"middle\">{$labels[$i]}</text>\n";
            }
        }

        $svg .= "</svg>\n";
        return $svg;
    }

    /**
     * Render SVG for Cargo Trend Area + Line in PDF export.
     */
    public function renderCombinedCargoTrendSvg(array $trend, string $legFilter = 'ALL', int $width = 750, int $height = 150): string
    {
        $padding = ['top' => 20, 'right' => 20, 'bottom' => 25, 'left' => 45];
        $plotW = $width - $padding['left'] - $padding['right'];
        $plotH = $height - $padding['top'] - $padding['bottom'];

        $labels = $trend['labels'] ?? [];
        $n = count($labels);
        if ($n === 0) return "<svg width=\"{$width}\" height=\"{$height}\"></svg>";

        $arrCargo = $trend['arr_cargo_ton'] ?? [];
        $depCargo = $trend['dep_cargo_ton'] ?? [];

        $showArr = ($legFilter === 'ALL' || $legFilter === 'ARR' || $legFilter === 'ARRIVAL');
        $showDep = ($legFilter === 'ALL' || $legFilter === 'DEP' || $legFilter === 'DEPARTURE');

        $lines = [];
        if ($legFilter === 'ALL') {
            $lines[] = ['name' => 'Arrival Cargo', 'data' => $arrCargo, 'color' => '#14B8A6', 'fill' => '#CCFBF1'];
            $lines[] = ['name' => 'Departure Cargo', 'data' => $depCargo, 'color' => '#059669', 'fill' => '#D1FAE5'];
        } elseif ($showArr) {
            $lines[] = ['name' => 'Arrival Cargo', 'data' => $arrCargo, 'color' => '#14B8A6', 'fill' => '#CCFBF1'];
        } else {
            $lines[] = ['name' => 'Departure Cargo', 'data' => $depCargo, 'color' => '#059669', 'fill' => '#D1FAE5'];
        }

        $maxY = 5;
        foreach ($lines as $ln) {
            foreach ($ln['data'] as $v) {
                if ($v > $maxY) $maxY = $v;
            }
        }
        $maxY = ceil($maxY * 1.15);

        $colWidth = $plotW / max(1, $n - 1);

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\" style=\"background-color:#ffffff; font-family:sans-serif;\">\n";

        for ($t = 0; $t <= 3; $t++) {
            $val = round(($maxY / 3) * $t, 1);
            $y = $padding['top'] + $plotH - ($plotH * ($val / $maxY));
            $svg .= "<line x1=\"{$padding['left']}\" y1=\"{$y}\" x2=\"" . ($width - $padding['right']) . "\" y2=\"{$y}\" stroke=\"#E2E8F0\" stroke-width=\"1\" stroke-dasharray=\"2,2\" />\n";
            $svg .= "<text x=\"" . ($padding['left'] - 6) . "\" y=\"" . ($y + 3) . "\" fill=\"#64748B\" font-size=\"8.5\" text-anchor=\"end\">{$val} t</text>\n";
        }

        foreach ($lines as $ln) {
            $pts = [];
            for ($i = 0; $i < $n; $i++) {
                $val = $ln['data'][$i] ?? 0;
                $x = $padding['left'] + ($i * $colWidth);
                $y = $padding['top'] + $plotH - (($val / $maxY) * $plotH);
                $pts[] = "{$x},{$y}";
            }

            if (!empty($pts)) {
                $firstX = $padding['left'];
                $lastX = $padding['left'] + (($n - 1) * $colWidth);
                $bottomY = $padding['top'] + $plotH;
                $areaPoints = "{$firstX},{$bottomY} " . implode(' ', $pts) . " {$lastX},{$bottomY}";
                $svg .= "<polygon points=\"{$areaPoints}\" fill=\"{$ln['fill']}\" opacity=\"0.55\" />\n";

                $svg .= "<polyline points=\"" . implode(' ', $pts) . "\" fill=\"none\" stroke=\"{$ln['color']}\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" />\n";
                foreach ($pts as $pt) {
                    [$px, $py] = explode(',', $pt);
                    $svg .= "<circle cx=\"{$px}\" cy=\"{$py}\" r=\"2.5\" fill=\"#FFFFFF\" stroke=\"{$ln['color']}\" stroke-width=\"1.5\" />\n";
                }
            }
        }

        for ($i = 0; $i < $n; $i++) {
            $showLabel = ($n <= 12) || ($n <= 24 && $i % 2 === 0) || ($i % 3 === 0);
            if ($showLabel && isset($labels[$i])) {
                $x = $padding['left'] + ($i * $colWidth);
                $svg .= "<text x=\"{$x}\" y=\"" . ($height - 8) . "\" fill=\"#64748B\" font-size=\"8.5\" text-anchor=\"middle\">{$labels[$i]}</text>\n";
            }
        }

        $svg .= "</svg>\n";
        return $svg;
    }

    /**
     * Render SVG for Schedule Variance Distribution (9 semantic buckets) in PDF export.
     */
    public function renderScheduleVarianceDistributionSvg(array $schedVsReal, int $width = 720, int $height = 145): string
    {
        $rawBins = $schedVsReal['hist_bins'] ?? ($schedVsReal['histogram'] ?? []);
        $bins = array_values($rawBins);
        if (empty($bins) || count($bins) !== 9) {
            // Fallback default 9 bins if structure not present
            $bins = [
                ['label' => '>60 MIN EARLY', 'group' => 'EARLY', 'count' => 0, 'color' => '#1E3A8A'],
                ['label' => '31–60 MIN EARLY', 'group' => 'EARLY', 'count' => 0, 'color' => '#2563EB'],
                ['label' => '16–30 MIN EARLY', 'group' => 'EARLY', 'count' => 0, 'color' => '#60A5FA'],
                ['label' => '6–15 MIN EARLY', 'group' => 'EARLY', 'count' => 0, 'color' => '#93C5FD'],
                ['label' => 'ON TIME ±5 MIN', 'group' => 'ON TIME', 'count' => 0, 'color' => '#10B981'],
                ['label' => '6–15 MIN LATE', 'group' => 'LATE', 'count' => 0, 'color' => '#FCD34D'],
                ['label' => '16–30 MIN LATE', 'group' => 'LATE', 'count' => 0, 'color' => '#F59E0B'],
                ['label' => '31–60 MIN LATE', 'group' => 'LATE', 'count' => 0, 'color' => '#EA580C'],
                ['label' => '>60 MIN LATE', 'group' => 'LATE', 'count' => 0, 'color' => '#DC2626'],
            ];
        }

        $padding = ['top' => 30, 'right' => 15, 'bottom' => 30, 'left' => 30];
        $plotW = $width - $padding['left'] - $padding['right'];
        $plotH = $height - $padding['top'] - $padding['bottom'];

        $colWidth = $plotW / 9;
        $barWidth = max(8, min(42, $colWidth * 0.65));

        $maxVal = 5;
        foreach ($bins as $b) {
            $cnt = (int)($b['count'] ?? 0);
            if ($cnt > $maxVal) $maxVal = $cnt;
        }
        $maxY = ceil($maxVal * 1.15);

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\" style=\"background-color:#ffffff; font-family:sans-serif;\">\n";

        // Group Header Badges (EARLY: bins 0-3, ON TIME: bin 4, LATE: bins 5-8)
        $earlyW = 4 * $colWidth;
        $onTimeW = 1 * $colWidth;
        $lateW = 4 * $colWidth;

        // Early Group Header
        $earlyX = $padding['left'];
        $svg .= "<rect x=\"{$earlyX}\" y=\"6\" width=\"" . ($earlyW - 4) . "\" height=\"18\" rx=\"4\" fill=\"#EFF6FF\" stroke=\"#BFDBFE\" stroke-width=\"1\" />\n";
        $svg .= "<text x=\"" . ($earlyX + ($earlyW / 2) - 2) . "\" y=\"18\" fill=\"#1E40AF\" font-size=\"8.5\" font-weight=\"bold\" text-anchor=\"middle\">EARLY (4 BUCKETS)</text>\n";

        // On-Time Group Header
        $onTimeX = $earlyX + $earlyW;
        $svg .= "<rect x=\"{$onTimeX}\" y=\"6\" width=\"" . ($onTimeW - 4) . "\" height=\"18\" rx=\"4\" fill=\"#ECFDF5\" stroke=\"#A7F3D0\" stroke-width=\"1\" />\n";
        $svg .= "<text x=\"" . ($onTimeX + ($onTimeW / 2) - 2) . "\" y=\"18\" fill=\"#065F46\" font-size=\"8.5\" font-weight=\"bold\" text-anchor=\"middle\">ON TIME (±5 MIN)</text>\n";

        // Late Group Header
        $lateX = $onTimeX + $onTimeW;
        $svg .= "<rect x=\"{$lateX}\" y=\"6\" width=\"" . ($lateW - 4) . "\" height=\"18\" rx=\"4\" fill=\"#FEF2F2\" stroke=\"#FECACA\" stroke-width=\"1\" />\n";
        $svg .= "<text x=\"" . ($lateX + ($lateW / 2) - 2) . "\" y=\"18\" fill=\"#991B1B\" font-size=\"8.5\" font-weight=\"bold\" text-anchor=\"middle\">LATE (4 BUCKETS)</text>\n";

        // Vertical Section Separators
        $sep1X = $earlyX + $earlyW - 2;
        $sep2X = $onTimeX + $onTimeW - 2;
        $yBottom = $padding['top'] + $plotH;
        $svg .= "<line x1=\"{$sep1X}\" y1=\"{$padding['top']}\" x2=\"{$sep1X}\" y2=\"{$yBottom}\" stroke=\"#CBD5E1\" stroke-width=\"1\" stroke-dasharray=\"3,3\" />\n";
        $svg .= "<line x1=\"{$sep2X}\" y1=\"{$padding['top']}\" x2=\"{$sep2X}\" y2=\"{$yBottom}\" stroke=\"#CBD5E1\" stroke-width=\"1\" stroke-dasharray=\"3,3\" />\n";

        // Horizontal Grid Lines
        for ($t = 0; $t <= 3; $t++) {
            $val = round(($maxY / 3) * $t);
            $y = $padding['top'] + $plotH - ($plotH * ($val / $maxY));
            $svg .= "<line x1=\"{$padding['left']}\" y1=\"{$y}\" x2=\"" . ($width - $padding['right']) . "\" y2=\"{$y}\" stroke=\"#F1F5F9\" stroke-width=\"1\" />\n";
            $svg .= "<text x=\"" . ($padding['left'] - 6) . "\" y=\"" . ($y + 3) . "\" fill=\"#64748B\" font-size=\"8\" text-anchor=\"end\">{$val}</text>\n";
        }

        // Section 3 exact user-friendly labels for PDF bottom axis
        $axisLabels = [
            '>60 MIN EARLY',
            '31–60 MIN EARLY',
            '16–30 MIN EARLY',
            '6–15 MIN EARLY',
            'ON TIME ±5 MIN',
            '6–15 MIN LATE',
            '16–30 MIN LATE',
            '31–60 MIN LATE',
            '>60 MIN LATE'
        ];

        // Draw Bars
        for ($i = 0; $i < 9; $i++) {
            $b = $bins[$i];
            $cnt = (int)($b['count'] ?? 0);
            $color = $b['color'] ?? '#64748B';

            $xCenter = $padding['left'] + ($i * $colWidth) + ($colWidth / 2);
            $xBar = $xCenter - ($barWidth / 2);

            $hBar = ($maxY > 0) ? ($cnt / $maxY) * $plotH : 0;
            $yBar = $padding['top'] + $plotH - $hBar;

            if ($hBar > 0) {
                $svg .= "<rect x=\"{$xBar}\" y=\"{$yBar}\" width=\"{$barWidth}\" height=\"{$hBar}\" fill=\"{$color}\" rx=\"2.5\" />\n";
                $svg .= "<text x=\"{$xCenter}\" y=\"" . max(28, $yBar - 3) . "\" fill=\"#334155\" font-size=\"8\" font-weight=\"bold\" text-anchor=\"middle\">{$cnt}</text>\n";
            }

            $label = $axisLabels[$i] ?? ($b['label'] ?? '');
            $svg .= "<text x=\"{$xCenter}\" y=\"" . ($height - 10) . "\" fill=\"#64748B\" font-size=\"6.8\" font-weight=\"bold\" text-anchor=\"middle\">{$label}</text>\n";
        }

        $svg .= "</svg>\n";
        return $svg;
    }

    /**
     * Render SVG Donut Chart for Passenger / Payload Composition in PDF export.
     */
    public function renderDonutSvg(array $segments, string $centerTitle, string $centerValue, int $size = 150): string
    {
        $cx = $size / 2;
        $cy = $size / 2;
        $radius = ($size / 2) - 18;
        $strokeWidth = 16;
        $circ = 2 * M_PI * $radius;

        $total = 0;
        foreach ($segments as $s) {
            $total += (float)($s['value'] ?? 0);
        }

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 {$size} {$size}\" style=\"background-color:#ffffff; font-family:sans-serif;\">\n";
        $svg .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$radius}\" fill=\"transparent\" stroke=\"#F1F5F9\" stroke-width=\"{$strokeWidth}\" />\n";

        if ($total > 0) {
            $accum = 0;
            foreach ($segments as $s) {
                $val = (float)($s['value'] ?? 0);
                if ($val <= 0) continue;
                $pct = $val / $total;
                $dash = round($pct * $circ, 2);
                $gap = round($circ - $dash, 2);
                $offset = round(-$accum, 2);

                $svg .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$radius}\" fill=\"transparent\" stroke=\"{$s['color']}\" stroke-width=\"{$strokeWidth}\" stroke-dasharray=\"{$dash} {$gap}\" stroke-dashoffset=\"{$offset}\" transform=\"rotate(-90 {$cx} {$cy})\" />\n";
                $accum += $dash;
            }
        }

        $svg .= "<text x=\"{$cx}\" y=\"" . ($cy - 5) . "\" text-anchor=\"middle\" fill=\"#64748B\" font-size=\"8\" font-weight=\"bold\" letter-spacing=\"0.5\">" . htmlspecialchars($centerTitle) . "</text>\n";
        $svg .= "<text x=\"{$cx}\" y=\"" . ($cy + 11) . "\" text-anchor=\"middle\" fill=\"#0F172A\" font-size=\"12\" font-weight=\"900\">" . htmlspecialchars($centerValue) . "</text>\n";

        $svg .= "</svg>\n";
        return $svg;
    }
}
