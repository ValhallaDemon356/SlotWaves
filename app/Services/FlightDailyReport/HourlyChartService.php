<?php

namespace App\Services\FlightDailyReport;

use App\Models\Airport;

class HourlyChartService
{
    /**
     * Build the 3 Mentor Hourly Charts data payload strictly covering 24 hours (00 to 23).
     */
    public function buildHourlyCharts(array $records, string $airportCode = 'CGK'): array
    {
        $hourlyData = [];

        // Resolve baseline airport capacity
        $capacityProfile = $this->resolveRunwayCapacityProfile($airportCode);

        // Initialize all 24 hours (00 to 23)
        for ($h = 0; $h < 24; $h++) {
            $hStr = str_pad((string)$h, 2, '0', STR_PAD_LEFT);
            $cap = $capacityProfile[$h] ?? 40;

            $hourlyData[$h] = [
                'hour'               => $h,
                'hour_label'         => "{$hStr}:00",
                'time_range'         => "{$hStr}:00–{$hStr}:59",
                'runway_capacity'    => $cap,
                // ARRIVAL-DEPARTURE (Chart 1)
                'total_plan'         => 0,
                'total_realized'     => 0,
                'total_irregular'    => 0,
                // DEPARTURE (Chart 2)
                'dep_plan'           => 0,
                'dep_irregular'      => 0,
                'dep_realized'       => 0,
                // ARRIVAL (Chart 3)
                'arr_plan'           => 0,
                'arr_irregular'      => 0,
                'arr_realized'       => 0,
            ];
        }

        // Aggregate records into hourly bins
        foreach ($records as $r) {
            if (isset($r['hour'])) {
                $h = (int)$r['hour'];
            } else {
                $timeStr = $r['sibt'] ?? ($r['sobt'] ?? ($r['arr_actual'] ?? ($r['dep_actual'] ?? ($r['arr_sched'] ?? ($r['dep_sched'] ?? '')))));
                if (preg_match('/(\d{1,2}):(\d{2})/', (string)$timeStr, $tm)) {
                    $h = (int)$tm[1];
                } else {
                    $h = 12;
                }
            }
            if ($h < 0 || $h > 23) $h = 12;

            $isArr = (($r['direction'] ?? '') === 'ARRIVAL' || strtoupper(substr($r['leg'] ?? '', 0, 1)) === 'A');
            $isIrreg = !empty($r['is_irregular']) || !empty($r['irregular']);
            $isRealized = !empty($r['is_realized']) || !empty($r['realization']);

            // Plan counts (all scheduled movements)
            $schedType = $r['sched_type'] ?? '';
            $isSched = !empty($r['is_scheduled']) || ($schedType === 'SCHED') || ($schedType === 'SCHEDULED') || (!$isIrreg && empty($schedType));
            if ($isSched) {
                $hourlyData[$h]['total_plan']++;
                if ($isArr) {
                    $hourlyData[$h]['arr_plan']++;
                } else {
                    $hourlyData[$h]['dep_plan']++;
                }
            }

            // Realized & Irregular counts
            $hourlyData[$h]['total_realized']++;
            if ($isArr) {
                $hourlyData[$h]['arr_realized']++;
            } else {
                $hourlyData[$h]['dep_realized']++;
            }

            if ($isIrreg) {
                $hourlyData[$h]['total_irregular']++;
                if ($isArr) {
                    $hourlyData[$h]['arr_irregular']++;
                } else {
                    $hourlyData[$h]['dep_irregular']++;
                }
            }
        }

        // Compute tooltips and status per hour
        for ($h = 0; $h < 24; $h++) {
            $row = &$hourlyData[$h];
            $plan    = $row['total_plan'];
            $irreg   = $row['total_irregular'];
            $cap     = $row['runway_capacity'];
            $totalMv = $row['total_realized'];

            $diff = $cap - $totalMv;
            $status = 'AVAILABLE';
            if ($totalMv > $cap) {
                $status = 'OVER';
            } elseif ($totalMv === $cap && $cap > 0) {
                $status = 'FULL';
            }

            $row['difference'] = $diff;
            $row['delta'] = $diff;
            $row['status'] = $status;
            $row['tooltip'] = "{$row['time_range']} | Plan: {$plan} | Irregular: {$irreg} | Capacity: {$cap} | Delta: {$diff} | Status: {$status}";
        }

        // Compute Peak Hour strictly for the filtered operational records
        $peakHourIndex = 0;
        $maxMovements = -1;
        for ($h = 0; $h < 24; $h++) {
            $totalMovements = $hourlyData[$h]['arr_realized'] + $hourlyData[$h]['dep_realized'];
            if ($totalMovements > $maxMovements) {
                $maxMovements = $totalMovements;
                $peakHourIndex = $h;
            }
        }

        $peakHourRow = $hourlyData[$peakHourIndex];
        $peakHour = [
            'hour'        => $peakHourIndex,
            'hour_label'  => $peakHourRow['hour_label'],
            'time_range'  => $peakHourRow['time_range'],
            'movements'   => $maxMovements > 0 ? $maxMovements : 0,
            'arrivals'    => $peakHourRow['arr_realized'],
            'departures'  => $peakHourRow['dep_realized'],
            'plan'        => $peakHourRow['total_plan'],
            'display'     => $maxMovements > 0
                ? "{$peakHourRow['time_range']} ({$maxMovements} movements)"
                : "N/A",
        ];

        // Construct 3 distinct chart payloads
        return [
            'hours'                   => array_column($hourlyData, 'hour_label'),
            'hourly_data'             => array_values($hourlyData),
            'chart1_movement'         => $this->buildChart1Payload($hourlyData),
            'chart2_departure'        => $this->buildChart2Payload($hourlyData),
            'chart3_arrival'          => $this->buildChart3Payload($hourlyData),
            'peak_hour'               => $peakHour,
            'max_hourly_movement'     => max(0, $maxMovements),
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
}
