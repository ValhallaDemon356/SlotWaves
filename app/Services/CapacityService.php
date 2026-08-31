<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * CapacityService — Minute-Level Event-Based Aircraft Occupancy & Capacity Engine.
 *
 * Business Rules:
 * - Event-based simulation timeline across 1,440 minutes (00:00–23:59).
 * - NAC = 6 (Default maximum aircraft simultaneously occupying capacity).
 * - Remaining = max(0, NAC - Peak Occupied in hourly window).
 * - Status: AVAILABLE (Occupied < NAC), FULL (Occupied == NAC), OVER CAPACITY (Occupied > NAC).
 * - Unpaired arrivals stay PARKED for the remainder of the day and overnight.
 * - Unpaired departures are PARKED from 00:00 until STD.
 * - Source flight data is 100% immutable.
 */
class CapacityService
{
    public function __construct(
        private AircraftCategoryService $categoryService,
        private AircraftPairingService $pairingService
    ) {}

    public function calculate(Collection $flights, int|string|null $operatingDay = null, ?int $opsStartHour = null, ?int $opsEndHour = null, ?int $capacityLimit = null): array
    {
        $nac = $capacityLimit ?? (int) config('slotwaves.nac', 6);
        $startHour = $opsStartHour ?? 0;
        $endHour   = $opsEndHour ?? 24;

        // 1. Resolve pairings and detailed decision log
        $pairingResult = $this->pairingService->resolvePairings($flights, $operatingDay);
        $rotations     = $pairingResult['rotations'];
        $decisionLog   = $pairingResult['decision_log'];

        // 2. Minute-level simulation timeline: minutes 0 to 1439
        // Array of arrays: minute => array of rotation_ids occupied
        $minuteTimeline = array_fill(0, 1440, []);

        foreach ($rotations as &$rot) {
            $isCargo = $this->categoryService->isCargoFlight($rot['arrival'] ?? $rot['departure']) ||
                       $rot['category'] === AircraftCategoryService::CNB ||
                       $rot['category'] === AircraftCategoryService::CWB ||
                       ($rot['operation_type'] ?? '') === 'CARGO';

            $rot['is_cargo'] = $isCargo;
            $rot['operation_type'] = $isCargo ? 'CARGO' : 'PASSENGER';

            // Cargo aircraft have separate parking stand/capacity: 0 units on passenger timeline
            $passengerUnits = $isCargo ? 0 : $rot['capacity_units'];
            $rot['passenger_units'] = $passengerUnits;

            if ($passengerUnits <= 0) {
                continue; // Cargo excluded from passenger occupancy timeline
            }

            $start = $rot['start_minute'];
            $end   = $rot['end_minute'];

            if ($rot['rotation_status'] === 'PAIRED') {
                if ($start < $end) {
                    // Normal turnaround: occupied from start to end - 1
                    for ($m = $start; $m < $end && $m < 1440; $m++) {
                        if ($m >= 0) $minuteTimeline[$m][] = $rot;
                    }
                } elseif ($end < $start) {
                    // Overnight turnaround: occupied start..1439 AND 0..end - 1
                    for ($m = $start; $m < 1440; $m++) {
                        if ($m >= 0) $minuteTimeline[$m][] = $rot;
                    }
                    for ($m = 0; $m < $end && $m < 1440; $m++) {
                        if ($m >= 0) $minuteTimeline[$m][] = $rot;
                    }
                } else {
                    // start == end
                    if ($start >= 0 && $start < 1440) {
                        $minuteTimeline[$start][] = $rot;
                    }
                }
            } elseif ($rot['rotation_status'] === 'UNPAIRED_ARR') {
                // Arrives at STA, stays parked continuously until end of operating day (1439) & overnight
                for ($m = $start; $m < 1440; $m++) {
                    if ($m >= 0) $minuteTimeline[$m][] = $rot;
                }
            } elseif ($rot['rotation_status'] === 'UNPAIRED_DEP') {
                // Present parked from 00:00 (minute 0) until STD - 1 (leaves at STD)
                for ($m = 0; $m < $end && $m < 1440; $m++) {
                    if ($m >= 0) $minuteTimeline[$m][] = $rot;
                }
            }
        }
        unset($rot);

        // 3. Initialize 24 hourly summary buckets
        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $startLabel = sprintf('%02d:00', $h);
            $endLabel   = sprintf('%02d:59', $h);

            $hourly[$h] = [
                'hour'                      => $h,
                'label'                     => "{$startLabel}–{$endLabel}",
                'arrivals_count'            => 0,
                'departures_count'          => 0,
                'passenger_arrivals_count'  => 0,
                'passenger_departures_count'=> 0,
                'passenger_count'           => 0,
                'cargo_arrivals_count'      => 0,
                'cargo_departures_count'    => 0,
                'cargo_count'               => 0,
                'demand'                    => 0,
                'total_movements'           => 0,
                'nac'                       => $nac,
                'end_of_interval_occupancy' => 0,
                'occupied'                  => 0,
                'remaining'                 => $nac,
                'useable'                   => $nac,
                'exceeded'                  => 0,
                'status'                    => 'AVAILABLE',
                'sta_flights'               => [],
                'std_flights'               => [],
                'occupied_aircraft'         => [],
                'parked_aircraft'           => [],
                'rotations'                 => [],
            ];
        }

        // 4. Map STA and STD flight details to hourly buckets
        foreach ($rotations as $rot) {
            $isRotCargo = !empty($rot['is_cargo']);

            // Arrival flight in rotation
            if (!empty($rot['arrival'])) {
                $arr        = $rot['arrival'];
                $arrHour    = (int) substr($arr->scheduled_time, 0, 2);
                $isArrCargo = $this->categoryService->isCargoFlight($arr) || $isRotCargo;

                if ($arrHour >= 0 && $arrHour <= 23) {
                    $pairLabel = !empty($rot['departure'])
                        ? " (Pair → {$rot['departure']->flight_number} · " . substr($rot['departure']->scheduled_time, 0, 5) . ")"
                        : " (Unpaired · Parked)";

                    $item = [
                        'flight_number'     => $arr->flight_number,
                        'scheduled_time'    => substr($arr->scheduled_time, 0, 5),
                        'aircraft_type'     => $arr->aircraft_type ?? '—',
                        'aircraft_category' => $rot['category'],
                        'category_label'    => $rot['category_label'],
                        'category_badge'    => $rot['category_badge'],
                        'operation_type'    => $isArrCargo ? 'CARGO' : 'PASSENGER',
                        'is_cargo'          => $isArrCargo,
                        'capacity_units'    => $isArrCargo ? 0 : $rot['capacity_units'],
                        'is_paired'         => $rot['is_paired'],
                        'pair_text'         => $pairLabel,
                        'paired_with'       => $rot['departure'] ? $rot['departure']->flight_number : null,
                        'label'             => "{$arr->flight_number} · " . substr($arr->scheduled_time, 0, 5) . " · {$rot['category_badge']}{$pairLabel}",
                        'origin'            => $arr->origin ?? '—',
                        'destination'       => $arr->destination ?? '—',
                        'operating_days'    => $arr->operating_days ?? '—',
                        'flight_type'       => $arr->flight_type,
                    ];

                    $hourly[$arrHour]['arrivals_count']++;
                    if ($isArrCargo) {
                        $hourly[$arrHour]['cargo_arrivals_count']++;
                    } else {
                        $hourly[$arrHour]['passenger_arrivals_count']++;
                    }
                    $hourly[$arrHour]['sta_flights'][] = $item;
                }
            }

            // Departure flight in rotation
            if (!empty($rot['departure'])) {
                $dep        = $rot['departure'];
                $depHour    = (int) substr($dep->scheduled_time, 0, 2);
                $isDepCargo = $this->categoryService->isCargoFlight($dep) || $isRotCargo;

                if ($depHour >= 0 && $depHour <= 23) {
                    $pairLabel = !empty($rot['arrival'])
                        ? " (Pair ← {$rot['arrival']->flight_number} · " . substr($rot['arrival']->scheduled_time, 0, 5) . ")"
                        : " (Unpaired · Parked)";

                    $item = [
                        'flight_number'     => $dep->flight_number,
                        'scheduled_time'    => substr($dep->scheduled_time, 0, 5),
                        'aircraft_type'     => $dep->aircraft_type ?? '—',
                        'aircraft_category' => $rot['category'],
                        'category_label'    => $rot['category_label'],
                        'category_badge'    => $rot['category_badge'],
                        'operation_type'    => $isDepCargo ? 'CARGO' : 'PASSENGER',
                        'is_cargo'          => $isDepCargo,
                        'capacity_units'    => $isDepCargo ? 0 : $rot['capacity_units'],
                        'is_paired'         => $rot['is_paired'],
                        'pair_text'         => $pairLabel,
                        'paired_with'       => $rot['arrival'] ? $rot['arrival']->flight_number : null,
                        'label'             => "{$dep->flight_number} · " . substr($dep->scheduled_time, 0, 5) . " · {$rot['category_badge']}{$pairLabel}",
                        'origin'            => $dep->origin ?? '—',
                        'destination'       => $dep->destination ?? '—',
                        'operating_days'    => $dep->operating_days ?? '—',
                        'flight_type'       => $dep->flight_type,
                    ];

                    $hourly[$depHour]['departures_count']++;
                    if ($isDepCargo) {
                        $hourly[$depHour]['cargo_departures_count']++;
                    } else {
                        $hourly[$depHour]['passenger_departures_count']++;
                    }
                    $hourly[$depHour]['std_flights'][] = $item;
                }
            }
        }

        // 5. Calculate hourly peak simultaneous occupancy & remaining
        $activeHoursCount = max(0, $endHour - $startHour);
        $peakHour    = '—';
        $peakDemand  = 0;

        foreach ($hourly as $h => &$data) {
            usort($data['sta_flights'], fn($a, $b) => strcmp($a['scheduled_time'], $b['scheduled_time']));
            usort($data['std_flights'], fn($a, $b) => strcmp($a['scheduled_time'], $b['scheduled_time']));

            $data['passenger_count'] = $data['passenger_arrivals_count'] + $data['passenger_departures_count'];
            $data['cargo_count']     = $data['cargo_arrivals_count'] + $data['cargo_departures_count'];
            $data['total_movements'] = $data['arrivals_count'] + $data['departures_count'];
            $data['demand']          = $data['passenger_count']; // Passenger movements for aircraft capacity calculation

            // Evaluate passenger aircraft occupancy for hour $h from rotation time intervals [s, e)
            $hStart = $h * 60;
            $hNext  = ($h + 1) * 60;

            $occupiedRotations = [];
            $hourlyOccupancy   = 0;
            $arrivalContrib    = 0;
            $ronContrib        = 0;

            foreach ($rotations as $rot) {
                if (!empty($rot['is_cargo']) || ($rot['passenger_units'] ?? 1) <= 0) {
                    continue; // Cargo excluded from passenger occupancy timeline
                }

                $s      = $rot['start_minute'];
                $e      = $rot['end_minute'];
                $status = $rot['rotation_status'];
                $intersects = false;

                if ($status === 'PAIRED' && $e < $s) {
                    // Overnight turnaround crossing midnight [s..1440) U [0..e)
                    if ($s < $hNext || $e > $hStart) {
                        $intersects = true;
                    }
                } else {
                    // Normal interval [s, e)
                    // Aircraft occupies airport if arrival < hNext AND departure > hStart
                    if ($s < $hNext && $e > $hStart) {
                        $intersects = true;
                    }
                }

                if ($intersects) {
                    $occupiedRotations[] = $rot;
                    $units = (int) ($rot['passenger_units'] ?? 1);
                    $hourlyOccupancy += $units;

                    if ($status === 'UNPAIRED_DEP') {
                        $ronContrib += $units;
                    } elseif ($status === 'UNPAIRED_ARR') {
                        $arrivalContrib += $units;
                    } else {
                        // PAIRED
                        if ($s >= $hStart && $s < $hNext) {
                            $arrivalContrib += $units;
                        } else {
                            $ronContrib += $units; // Arrived in earlier hour and remains parked
                        }
                    }
                }
            }

            // Build occupied and parked lists for tooltips/modal/debug
            $occupiedList = [];
            $parkedList   = [];

            foreach ($occupiedRotations as $r) {
                $displayFlight = $r['arrival']
                    ? $r['arrival']->flight_number
                    : ($r['departure'] ? $r['departure']->flight_number : $r['rotation_id']);

                $statusText = $r['rotation_status'] === 'PAIRED'
                    ? "Turnaround ({$r['sta']} → {$r['std']})"
                    : "Parked ({$r['rotation_status']})";

                $itemInfo = [
                    'flight_number'   => $displayFlight,
                    'aircraft_type'   => $r['aircraft_type'] ?? '—',
                    'rotation_status' => $r['rotation_status'],
                    'status_text'     => $statusText,
                    'sta'             => $r['sta'],
                    'std'             => $r['std'],
                    'pair_text'       => $r['arrival'] && $r['departure']
                        ? "{$r['arrival']->flight_number} → {$r['departure']->flight_number}"
                        : ($r['arrival'] ? "Unpaired Arr ({$r['arrival']->flight_number})" : "Unpaired Dep ({$r['departure']->flight_number})"),
                ];

                $occupiedList[] = $itemInfo;
                if ($r['rotation_status'] !== 'PAIRED' || empty($r['departure'])) {
                    $parkedList[] = $itemInfo;
                }
            }

            $isInOpsWindow = ($h >= $startHour && $h < $endHour);
            $remaining     = max(0, $nac - $hourlyOccupancy);
            $exceeded      = max(0, $hourlyOccupancy - $nac);

            // Capacity comparisons: PASSENGER ONLY simultaneous occupancy vs Capacity Limit
            if (!$isInOpsWindow && ($opsStartHour !== null || $opsEndHour !== null)) {
                $status = 'OFF HOURS';
            } else {
                if ($hourlyOccupancy < $nac) {
                    $status = 'AVAILABLE';
                } elseif ($hourlyOccupancy === $nac) {
                    $status = 'FULL / MAX';
                } else {
                    $status = 'OVER CAPACITY';
                }
            }

            $data['end_of_interval_occupancy'] = $hourlyOccupancy;
            $data['occupied']                  = $hourlyOccupancy;
            $data['ron_contribution']          = $ronContrib;
            $data['arrival_contribution']      = $arrivalContrib;
            $data['remaining']                 = $remaining;
            $data['useable']                   = $remaining;
            $data['exceeded']                  = $exceeded;
            $data['status']                    = $status;
            $data['occupied_aircraft']         = $occupiedList;
            $data['parked_aircraft']           = $parkedList;

            // Only consider hours within the active operational window for peak window & peak demand
            if ($isInOpsWindow && $hourlyOccupancy > $peakDemand) {
                $peakDemand = $hourlyOccupancy;
                $peakHour   = $data['label'];
            }
        }
        unset($data);

        return [
            'hourly'             => $hourly,
            'nac'                => $nac,
            'rotations'          => $rotations,
            'decision_log'       => $decisionLog,
            'runway_regular'     => config('slotwaves.runway_regular_capacity', 8),
            'runway_irregular'   => config('slotwaves.runway_irregular_capacity', 2),
            'apron_capacity'     => $nac,
            'active_hours_count' => $activeHoursCount,
            'ops_start_hour'     => $startHour,
            'ops_end_hour'       => $endHour,
            'peak_hour'          => $peakHour,
            'peak_demand'        => $peakDemand,
        ];
    }

    /**
     * Classify status based on passenger occupancy vs NAC limit and operational window.
     */
    public function classifyHourlyStatus(int $occupied, int $nac = 6, bool $isInOpsWindow = true): array
    {
        if (!$isInOpsWindow) {
            return [
                'status'    => 'OFF HOURS',
                'remaining' => 0,
                'exceeded'  => 0,
            ];
        }

        if ($occupied < $nac) {
            return [
                'status'    => 'AVAILABLE',
                'remaining' => max(0, $nac - $occupied),
                'exceeded'  => 0,
            ];
        }

        if ($occupied === $nac) {
            return [
                'status'    => 'FULL / MAX',
                'remaining' => 0,
                'exceeded'  => 0,
            ];
        }

        return [
            'status'    => 'OVER CAPACITY',
            'remaining' => 0,
            'exceeded'  => $occupied - $nac,
        ];
    }

    /**
     * Diagnostic report for hourly occupancy breakdown (Section 23).
     */
    public function getDiagnosticReport(Collection $flights, ?int $nac = 6, ?int $opsStart = 6, ?int $opsEnd = 20): array
    {
        $res = $this->calculate($flights, null, $opsStart, $opsEnd, $nac);
        $report = [];

        foreach ($res['hourly'] as $h => $d) {
            $report[] = [
                'hour'                 => sprintf('%02d:00', $h),
                'occupied'             => $d['occupied'],
                'nac'                  => $d['nac'],
                'ron_contribution'     => $d['ron_contribution'] ?? 0,
                'arrival_contribution' => $d['arrival_contribution'] ?? 0,
                'cargo_count'          => $d['cargo_count'] ?? 0,
                'remaining'            => $d['remaining'],
                'capacity'             => $d['nac'],
                'status'               => $d['status'],
            ];
        }

        return $report;
    }
}
