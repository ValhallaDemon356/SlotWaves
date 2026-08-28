<?php

namespace App\Services;

use App\Models\Flight;
use App\Models\FlightPairing;
use Illuminate\Support\Collection;

/**
 * AircraftPairingService — Resolves Arrival ↔ Departure Aircraft Rotations.
 *
 * IMMUTABILITY GUARANTEE:
 * Source Flight models (flight_number, scheduled_time, aircraft_type, origin, destination, operating_days)
 * are NEVER modified. Derived pairings are represented as rotation structures and optional FlightPairing records.
 *
 * Pairing Heuristics:
 * 1. Priority 1: Explicit DB relationship (paired_flight_id)
 * 2. Priority 2: Airline Code + DOS active match + Aircraft Category + Turnaround Window (15m–240m)
 * 3. Priority 3: Carrier sequence fallback (15m–360m turnaround)
 * 4. Unpaired Arrivals: Remain PARKED at airport from STA through 23:59 (and overnight)
 * 5. Unpaired Departures: Present PARKED at airport from 00:00 until STD
 */
class AircraftPairingService
{
    public function __construct(
        private AircraftCategoryService $categoryService
    ) {}

    /**
     * Pair flights into aircraft rotation objects.
     *
     * @param Collection $flights
     * @param int|string|null $operatingDay
     * @return array List of aircraft rotation arrays
     */
    public function pairFlights(Collection $flights, int|string|null $operatingDay = null): array
    {
        $res = $this->resolvePairings($flights, $operatingDay);
        return $res['rotations'];
    }

    /**
     * Detailed pairing calculation returning rotations and step-by-step decision log.
     *
     * @param Collection $flights
     * @param int|string|null $operatingDay
     * @return array{rotations: array, decision_log: array}
     */
    public function resolvePairings(Collection $flights, int|string|null $operatingDay = null): array
    {
        $dayFilter = is_numeric($operatingDay) ? (int)$operatingDay : null;

        $arrivals = $flights->filter(function ($f) use ($dayFilter) {
            if (!str_contains($f->flight_type, 'arrival')) return false;
            if ($dayFilter !== null && !empty($f->operating_days)) {
                return str_contains($f->operating_days, (string)$dayFilter);
            }
            return true;
        })->sortBy('scheduled_time')->values();

        $departures = $flights->filter(function ($f) use ($dayFilter) {
            if (!str_contains($f->flight_type, 'departure')) return false;
            if ($dayFilter !== null && !empty($f->operating_days)) {
                return str_contains($f->operating_days, (string)$dayFilter);
            }
            return true;
        })->sortBy('scheduled_time')->values();

        $pairedArrivalIds   = [];
        $pairedDepartureIds = [];
        $rotations          = [];
        $decisionLog        = [];

        $toMinute = function (?string $time): int {
            if (empty($time)) return 0;
            $parts = explode(':', $time);
            return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
        };

        $getId = function ($f, string $prefix): string|int {
            return $f->id ?? "{$prefix}_{$f->flight_number}_{$f->scheduled_time}";
        };

        // 1. Priority 1: Explicit DB relationship (paired_flight_id)
        foreach ($arrivals as $arr) {
            $arrId = $getId($arr, 'arr');
            if (!empty($arr->paired_flight_id) && !in_array($arrId, $pairedArrivalIds, true)) {
                $dep = $departures->firstWhere('id', $arr->paired_flight_id);
                $depId = $dep ? $getId($dep, 'dep') : null;
                if ($dep && $depId && !in_array($depId, $pairedDepartureIds, true)) {
                    $pairedArrivalIds[]   = $arrId;
                    $pairedDepartureIds[] = $depId;

                    $staMin = $toMinute($arr->scheduled_time);
                    $stdMin = $toMinute($dep->scheduled_time);
                    if ($stdMin < $staMin) $stdMin += 1440; // Overnight

                    $category = $this->categoryService->classify($arr->aircraft_type ?? $dep->aircraft_type);
                    $units    = $this->categoryService->getCapacityUnits($category);
                    $rotId    = "ROT-{$arrId}-{$depId}";

                    $rotations[] = [
                        'rotation_id'      => $rotId,
                        'arrival'          => $arr,
                        'departure'        => $dep,
                        'is_paired'        => true,
                        'rotation_status'  => 'PAIRED',
                        'sta'              => substr($arr->scheduled_time, 0, 5),
                        'std'              => substr($dep->scheduled_time, 0, 5),
                        'start_minute'     => $staMin,
                        'end_minute'       => $stdMin,
                        'aircraft_type'    => $arr->aircraft_type ?? $dep->aircraft_type,
                        'category'         => $category,
                        'category_label'   => $this->categoryService->getCategoryLabel($category),
                        'category_badge'   => $this->categoryService->getCategoryBadge($category),
                        'capacity_units'   => $units,
                        'turnaround_mins'  => max(0, $stdMin - $staMin),
                        'operating_day'    => $dayFilter ?? 1,
                    ];

                    $decisionLog[] = [
                        'arrival_flight' => $arr->flight_number,
                        'sta'            => substr($arr->scheduled_time, 0, 5),
                        'dos'            => $arr->operating_days,
                        'candidate'      => $dep->flight_number,
                        'std'            => substr($dep->scheduled_time, 0, 5),
                        'candidate_dos'  => $dep->operating_days,
                        'result'         => 'PAIRED (Explicit DB Link)',
                        'rotation'       => "{$arr->flight_number} → {$dep->flight_number}",
                        'turnaround'     => max(0, $stdMin - $staMin) . " mins",
                    ];
                }
            }
        }

        // 2. Priority 2: Airline Code + DOS active match + Category compatibility + Turnaround Window (15m–240m)
        foreach ($arrivals as $arr) {
            $arrId = $getId($arr, 'arr');
            if (in_array($arrId, $pairedArrivalIds, true)) continue;

            $arrMin = $toMinute($arr->scheduled_time);
            $arrCat = $this->categoryService->classify($arr->aircraft_type);

            $bestDep   = null;
            $bestDiff  = 9999;
            $bestScore = -99999;
            $candidatesEvaluated = [];

            foreach ($departures as $dep) {
                $depId = $getId($dep, 'dep');
                if (in_array($depId, $pairedDepartureIds, true)) continue;

                // Airline code match
                if ($arr->airline_code !== $dep->airline_code) continue;

                // DOS active check for specific day
                if ($dayFilter !== null) {
                    $arrActive = empty($arr->operating_days) || str_contains($arr->operating_days, (string)$dayFilter);
                    $depActive = empty($dep->operating_days) || str_contains($dep->operating_days, (string)$dayFilter);
                    if (!$arrActive || !$depActive) {
                        $candidatesEvaluated[] = "{$dep->flight_number} (STD {$dep->scheduled_time}, DOS {$dep->operating_days}) -> REJECTED (DOS mismatch for Day {$dayFilter})";
                        continue;
                    }
                } else {
                    // Check DOS overlap between arrival and departure
                    if (!$this->dosMatches($arr->operating_days, $dep->operating_days)) {
                        $candidatesEvaluated[] = "{$dep->flight_number} (STD {$dep->scheduled_time}, DOS {$dep->operating_days}) -> REJECTED (No DOS overlap with {$arr->operating_days})";
                        continue;
                    }
                }

                // Aircraft type category compatibility check
                $depCat = $this->categoryService->classify($dep->aircraft_type);
                if ($arrCat !== $depCat && !empty($arr->aircraft_type) && !empty($dep->aircraft_type)) {
                    $candidatesEvaluated[] = "{$dep->flight_number} ({$dep->aircraft_type}) -> REJECTED (Incompatible category vs {$arr->aircraft_type})";
                    continue;
                }

                $depMin = $toMinute($dep->scheduled_time);
                $diff   = $depMin - $arrMin;

                if ($diff >= 15 && $diff <= 240) {
                    $score = 0;
                    
                    // Exact DOS match bonus
                    if ($arr->operating_days === $dep->operating_days) {
                        $score += 1000;
                    }

                    // Flight number sequence / pair bonus (e.g. 580/581, 143/144, 420/421)
                    $arrDigits = (int) preg_replace('/\D/', '', $arr->flight_number);
                    $depDigits = (int) preg_replace('/\D/', '', $dep->flight_number);
                    if ($arrDigits > 0 && $depDigits > 0) {
                        $digitDiff = abs($arrDigits - $depDigits);
                        if ($digitDiff === 0 || $digitDiff === 1) {
                            $score += 500;
                        } elseif ($digitDiff <= 5) {
                            $score += 200;
                        }
                    }

                    // Same exact aircraft type bonus
                    if ($arr->aircraft_type === $dep->aircraft_type) {
                        $score += 200;
                    }

                    // Turnaround penalty: prefer shorter turnaround (e.g. 30–90 mins)
                    $score -= ($diff * 0.5);

                    if (!isset($bestScore) || $score > $bestScore) {
                        $bestScore = $score;
                        $bestDiff  = $diff;
                        $bestDep   = $dep;
                    }
                }
            }

            if ($bestDep) {
                $arrId = $getId($arr, 'arr');
                $depId = $getId($bestDep, 'dep');
                $pairedArrivalIds[]   = $arrId;
                $pairedDepartureIds[] = $depId;

                $depMin   = $toMinute($bestDep->scheduled_time);
                $category = $arrCat;
                $units    = $this->categoryService->getCapacityUnits($category);
                $rotId    = "ROT-{$arrId}-{$depId}";

                $rotations[] = [
                    'rotation_id'      => $rotId,
                    'arrival'          => $arr,
                    'departure'        => $bestDep,
                    'is_paired'        => true,
                    'rotation_status'  => 'PAIRED',
                    'sta'              => substr($arr->scheduled_time, 0, 5),
                    'std'              => substr($bestDep->scheduled_time, 0, 5),
                    'start_minute'     => $arrMin,
                    'end_minute'       => $depMin,
                    'aircraft_type'    => $arr->aircraft_type ?? $bestDep->aircraft_type,
                    'category'         => $category,
                    'category_label'   => $this->categoryService->getCategoryLabel($category),
                    'category_badge'   => $this->categoryService->getCategoryBadge($category),
                    'capacity_units'   => $units,
                    'turnaround_mins'  => $bestDiff,
                    'operating_day'    => $dayFilter ?? 1,
                ];

                $decisionLog[] = [
                    'arrival_flight' => $arr->flight_number,
                    'sta'            => substr($arr->scheduled_time, 0, 5),
                    'dos'            => $arr->operating_days,
                    'candidate'      => $bestDep->flight_number,
                    'std'            => substr($bestDep->scheduled_time, 0, 5),
                    'candidate_dos'  => $bestDep->operating_days,
                    'result'         => 'PAIRED',
                    'rotation'       => "{$arr->flight_number} → {$bestDep->flight_number}",
                    'turnaround'     => "{$bestDiff} mins",
                ];
            } else {
                $decisionLog[] = [
                    'arrival_flight' => $arr->flight_number,
                    'sta'            => substr($arr->scheduled_time, 0, 5),
                    'dos'            => $arr->operating_days,
                    'candidates'     => $candidatesEvaluated,
                    'result'         => 'UNPAIRED',
                ];
            }
        }

        // 3. Priority 3: Carrier sequence fallback (up to 360 mins turnaround)
        foreach ($arrivals as $arr) {
            $arrId = $getId($arr, 'arr');
            if (in_array($arrId, $pairedArrivalIds, true)) continue;

            $bestDep   = null;
            $bestDiff  = 9999;
            $bestScore = -99999;

            foreach ($departures as $dep) {
                $depId = $getId($dep, 'dep');
                if (in_array($depId, $pairedDepartureIds, true)) continue;
                if ($arr->airline_code !== $dep->airline_code) continue;

                if ($dayFilter !== null) {
                    $arrActive = empty($arr->operating_days) || str_contains($arr->operating_days, (string)$dayFilter);
                    $depActive = empty($dep->operating_days) || str_contains($dep->operating_days, (string)$dayFilter);
                    if (!$arrActive || !$depActive) continue;
                } else {
                    if (!$this->dosMatches($arr->operating_days, $dep->operating_days)) continue;
                }

                // Aircraft type category compatibility check
                $arrCat = $this->categoryService->classify($arr->aircraft_type);
                $depCat = $this->categoryService->classify($dep->aircraft_type);
                if ($arrCat !== $depCat && !empty($arr->aircraft_type) && !empty($dep->aircraft_type)) continue;

                $depMin = $toMinute($dep->scheduled_time);
                $diff   = $depMin - $arrMin;

                if ($diff >= 15 && $diff <= 360) {
                    $score = 0;
                    if ($arr->operating_days === $dep->operating_days) $score += 1000;
                    $arrDigits = (int) preg_replace('/\D/', '', $arr->flight_number);
                    $depDigits = (int) preg_replace('/\D/', '', $dep->flight_number);
                    if ($arrDigits > 0 && $depDigits > 0 && abs($arrDigits - $depDigits) <= 1) $score += 500;
                    if ($arr->aircraft_type === $dep->aircraft_type) $score += 200;
                    $score -= ($diff * 0.5);

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestDiff  = $diff;
                        $bestDep   = $dep;
                    }
                }
            }

            if ($bestDep) {
                $arrId = $getId($arr, 'arr');
                $depId = $getId($bestDep, 'dep');
                $pairedArrivalIds[]   = $arrId;
                $pairedDepartureIds[] = $depId;

                $depMin   = $toMinute($bestDep->scheduled_time);
                $category = $this->categoryService->classify($arr->aircraft_type ?? $bestDep->aircraft_type);
                $units    = $this->categoryService->getCapacityUnits($category);
                $rotId    = "ROT-{$arrId}-{$depId}";

                $rotations[] = [
                    'rotation_id'      => $rotId,
                    'arrival'          => $arr,
                    'departure'        => $bestDep,
                    'is_paired'        => true,
                    'rotation_status'  => 'PAIRED',
                    'sta'              => substr($arr->scheduled_time, 0, 5),
                    'std'              => substr($bestDep->scheduled_time, 0, 5),
                    'start_minute'     => $arrMin,
                    'end_minute'       => $depMin,
                    'aircraft_type'    => $arr->aircraft_type ?? $bestDep->aircraft_type,
                    'category'         => $category,
                    'category_label'   => $this->categoryService->getCategoryLabel($category),
                    'category_badge'   => $this->categoryService->getCategoryBadge($category),
                    'capacity_units'   => $units,
                    'turnaround_mins'  => $bestDiff,
                    'operating_day'    => $dayFilter ?? 1,
                ];

                $decisionLog[] = [
                    'arrival_flight' => $arr->flight_number,
                    'sta'            => substr($arr->scheduled_time, 0, 5),
                    'dos'            => $arr->operating_days,
                    'candidate'      => $bestDep->flight_number,
                    'std'            => substr($bestDep->scheduled_time, 0, 5),
                    'candidate_dos'  => $bestDep->operating_days,
                    'result'         => 'PAIRED (Carrier sequence fallback)',
                    'rotation'       => "{$arr->flight_number} → {$bestDep->flight_number}",
                    'turnaround'     => "{$bestDiff} mins",
                ];
            }
        }

        // 4. Unpaired Arrivals (Aircraft ENTERS at STA and STAYS PARKED continuously through 23:59/overnight!)
        foreach ($arrivals as $arr) {
            $arrId = $getId($arr, 'arr');
            if (in_array($arrId, $pairedArrivalIds, true)) continue;

            $arrMin   = $toMinute($arr->scheduled_time);
            $category = $this->categoryService->classify($arr->aircraft_type);
            $units    = $this->categoryService->getCapacityUnits($category);
            $rotId    = "ROT-UNPAIRED-ARR-{$arrId}";

            $rotations[] = [
                'rotation_id'      => $rotId,
                'arrival'          => $arr,
                'departure'        => null,
                'is_paired'        => false,
                'rotation_status'  => 'UNPAIRED_ARR',
                'sta'              => substr($arr->scheduled_time, 0, 5),
                'std'              => null,
                'start_minute'     => $arrMin,
                'end_minute'       => 1439, // Stays parked until end of operating day (23:59) / overnight
                'aircraft_type'    => $arr->aircraft_type,
                'category'         => $category,
                'category_label'   => $this->categoryService->getCategoryLabel($category),
                'category_badge'   => $this->categoryService->getCategoryBadge($category),
                'capacity_units'   => $units,
                'turnaround_mins'  => null,
                'operating_day'    => $dayFilter ?? 1,
            ];
        }

        // 5. Unpaired Departures (Aircraft WAS PARKED since 00:00 until STD!)
        foreach ($departures as $dep) {
            $depId = $getId($dep, 'dep');
            if (in_array($depId, $pairedDepartureIds, true)) continue;

            $depMin   = $toMinute($dep->scheduled_time);
            $category = $this->categoryService->classify($dep->aircraft_type);
            $units    = $this->categoryService->getCapacityUnits($category);
            $rotId    = "ROT-UNPAIRED-DEP-{$depId}";

            $rotations[] = [
                'rotation_id'      => $rotId,
                'arrival'          => null,
                'departure'        => $dep,
                'is_paired'        => false,
                'rotation_status'  => 'UNPAIRED_DEP',
                'sta'              => null,
                'std'              => substr($dep->scheduled_time, 0, 5),
                'start_minute'     => 0, // Parked from 00:00 (overnight / prior arrival) until STD
                'end_minute'       => $depMin,
                'aircraft_type'    => $dep->aircraft_type,
                'category'         => $category,
                'category_label'   => $this->categoryService->getCategoryLabel($category),
                'category_badge'   => $this->categoryService->getCategoryBadge($category),
                'capacity_units'   => $units,
                'turnaround_mins'  => null,
                'operating_day'    => $dayFilter ?? 1,
            ];
        }

        return [
            'rotations'    => $rotations,
            'decision_log' => $decisionLog,
        ];
    }

    private function dosMatches(?string $dos1, ?string $dos2): bool
    {
        if (empty($dos1) || empty($dos2)) return true;
        $set1 = str_split($dos1);
        $set2 = str_split($dos2);
        return !empty(array_intersect($set1, $set2));
    }
}
