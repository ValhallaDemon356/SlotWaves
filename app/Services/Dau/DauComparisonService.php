<?php

namespace App\Services\Dau;

use App\Services\Dau\Parsers\BaseDauParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DauComparisonService
{
    /**
     * Determine if two operational date ranges represent the same duration/period length.
     *
     * Period Length Calculation Method:
     * 1. Extract source dates normalized to ISO YYYY-MM-DD.
     * 2. Compute exact day counts ($days = diffInDays + 1).
     * 3. If exact day counts match ($days1 === $days2), they are equal.
     * 4. If day counts differ slightly:
     *    a. Leap Year Tolerance: If difference is <= 1 day (e.g. 365 vs 366 for full year, or 181 vs 182 for Jan-Jun),
     *       they represent the same historical period length.
     *    b. Calendar Month Semantics: If both periods start on day 1 of a month and end on the last day of a month
     *       (e.g. 01-01 to 30-06 = 6 full calendar months), we compare the calendar month span ($months1 === $months2).
     *       Differences of 1-3 days resulting strictly from Gregorian month lengths (28, 29, 30, 31) are accepted.
     *    c. Matching Day-of-Month Span across different years (e.g. 01-03 to 30-06 vs 01-03 to 30-06).
     * 5. If month count and day count differ substantially (e.g. 6 months vs 9 months, or 181 days vs 273 days),
     *    the durations are strictly rejected.
     */
    public static function arePeriodLengthsEqual(?string $start1, ?string $end1, ?string $start2, ?string $end2): bool
    {
        if (!$start1 || !$end1 || !$start2 || !$end2) {
            return false;
        }

        $normS1 = BaseDauParser::normalizeOperationalDate($start1);
        $normE1 = BaseDauParser::normalizeOperationalDate($end1);
        $normS2 = BaseDauParser::normalizeOperationalDate($start2);
        $normE2 = BaseDauParser::normalizeOperationalDate($end2);

        if (!$normS1 || !$normE1 || !$normS2 || !$normE2) {
            return false;
        }

        try {
            $cS1 = Carbon::parse($normS1);
            $cE1 = Carbon::parse($normE1);
            $cS2 = Carbon::parse($normS2);
            $cE2 = Carbon::parse($normE2);

            if ($cS1->gt($cE1) || $cS2->gt($cE2)) {
                return false;
            }

            $days1 = $cS1->diffInDays($cE1) + 1;
            $days2 = $cS2->diffInDays($cE2) + 1;

            // 1. Exact day count match
            if ($days1 === $days2) {
                return true;
            }

            // 2. Leap year adjustment (e.g. 365 vs 366 days, or 181 vs 182 days)
            if (abs($days1 - $days2) <= 1) {
                return true;
            }

            // 3. Calendar month boundaries (both full months)
            $isStart1MonthStart = ($cS1->day === 1);
            $isEnd1MonthEnd     = ($cE1->day === $cE1->copy()->endOfMonth()->day);
            $isStart2MonthStart = ($cS2->day === 1);
            $isEnd2MonthEnd     = ($cE2->day === $cE2->copy()->endOfMonth()->day);

            if ($isStart1MonthStart && $isEnd1MonthEnd && $isStart2MonthStart && $isEnd2MonthEnd) {
                $months1 = ($cE1->year - $cS1->year) * 12 + ($cE1->month - $cS1->month) + 1;
                $months2 = ($cE2->year - $cS2->year) * 12 + ($cE2->month - $cS2->month) + 1;
                if ($months1 === $months2 && abs($days1 - $days2) <= 3) {
                    return true;
                }
            }

            // 4. Matching day-of-month across years (e.g. 15th to 15th)
            if ($cS1->day === $cS2->day && $cE1->day === $cE2->day) {
                $months1 = ($cE1->year - $cS1->year) * 12 + ($cE1->month - $cS1->month);
                $months2 = ($cE2->year - $cS2->year) * 12 + ($cE2->month - $cS2->month);
                if ($months1 === $months2 && abs($days1 - $days2) <= 2) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning("Period length calculation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Compute data days count for a date range.
     */
    public static function calculatePeriodDurationDays(?string $start, ?string $end): int
    {
        $normS = BaseDauParser::normalizeOperationalDate($start);
        $normE = BaseDauParser::normalizeOperationalDate($end);
        if (!$normS || !$normE) return 0;
        try {
            $s = Carbon::parse($normS);
            $e = Carbon::parse($normE);
            return $s->lte($e) ? ($s->diffInDays($e) + 1) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Validate a collection of DAU reports for comparison.
     *
     * Rules:
     * - Minimum 2 reports
     * - All files must be DAU-02
     * - All files must refer to the same airport
     * - Files must have different reporting periods (no exact duplicate dates)
     * - All files must cover the same duration/period length
     * - Overlapping periods generate a warning
     */
    public static function validateComparisonReports(array $reports): array
    {
        $checks = [
            'same_dau_type'       => true,
            'same_airport'        => true,
            'different_period'    => true,
            'same_period_length'  => true,
            'compatible_template' => true,
        ];
        $errors = [];
        $warnings = [];

        if (count($reports) < 2) {
            $checks['compatible_template'] = false;
            $errors[] = "Minimum 2 DAU-02 reports are required for historical comparison.";
            return [
                'valid'        => false,
                'checks'       => $checks,
                'errors'       => $errors,
                'warnings'     => $warnings,
                'airport_name' => null,
                'airport_code' => null,
            ];
        }

        $baseAirportCode = null;
        $baseAirportName = null;
        $baseReportType  = null;
        $baseStartDate   = null;
        $baseEndDate     = null;
        $baseDays        = null;

        $seenDateRanges = [];

        foreach ($reports as $index => $rep) {
            $num = $index + 1;
            $repType = $rep['report_type'] ?? $rep['dau_type'] ?? '';
            $airportCode = strtoupper(trim($rep['airport_code'] ?? ($rep['meta']['airport_code'] ?? '')));
            $airportName = trim($rep['airport_name'] ?? ($rep['meta']['airport_name'] ?? ''));
            $startDate   = BaseDauParser::normalizeOperationalDate($rep['start_date'] ?? ($rep['meta']['start_date'] ?? ''));
            $endDate     = BaseDauParser::normalizeOperationalDate($rep['end_date'] ?? ($rep['meta']['end_date'] ?? ''));
            $dataDays    = self::calculatePeriodDurationDays($startDate, $endDate);

            // 1. DAU Type Check
            $isDau02 = in_array(strtoupper($repType), ['DAU2', 'DAU-02', 'DAU_02']);
            if (!$isDau02) {
                $checks['same_dau_type'] = false;
                $errors[] = "Report #{$num} is not DAU-02. Comparison requires all files to be DAU-02.";
            }

            // 2. Airport Check
            if ($baseAirportCode === null) {
                $baseAirportCode = $airportCode;
                $baseAirportName = $airportName ?: ($rep['meta']['airport'] ?? $airportCode);
                $baseReportType  = $repType;
                $baseStartDate   = $startDate;
                $baseEndDate     = $endDate;
                $baseDays        = $dataDays;
            } else {
                if ($airportCode !== $baseAirportCode) {
                    $checks['same_airport'] = false;
                    $errors[] = "Comparison reports must use the same airport. (Report #1: {$baseAirportCode}, Report #{$num}: {$airportCode})";
                }
            }

            // 3. Date validity & duplicate checks
            if (!$startDate || !$endDate) {
                $checks['compatible_template'] = false;
                $errors[] = "Report #{$num} does not have valid operational reporting dates.";
                continue;
            }

            $dateKey = "{$startDate}_{$endDate}";
            if (isset($seenDateRanges[$dateKey])) {
                $checks['different_period'] = false;
                $errors[] = "Comparison files must have different reporting periods. Duplicate comparison period detected: " .
                    BaseDauParser::formatDisplayDate($startDate) . " - " . BaseDauParser::formatDisplayDate($endDate);
            }
            $seenDateRanges[$dateKey] = true;

            // 4. Duration Check vs Base
            if ($baseStartDate && $baseEndDate && ($index > 0)) {
                $durationMatch = self::arePeriodLengthsEqual($baseStartDate, $baseEndDate, $startDate, $endDate);
                if (!$durationMatch) {
                    $checks['same_period_length'] = false;
                    $baseDisplay = BaseDauParser::formatDisplayDate($baseStartDate) . ' - ' . BaseDauParser::formatDisplayDate($baseEndDate);
                    $currDisplay = BaseDauParser::formatDisplayDate($startDate) . ' - ' . BaseDauParser::formatDisplayDate($endDate);
                    $errors[] = "Comparison reports must cover the same duration. (Report #1: {$baseDays} days [{$baseDisplay}], Report #{$num}: {$dataDays} days [{$currDisplay}])";
                }
            }
        }

        // 5. Check for overlapping periods (Warning, non-blocking)
        $n = count($reports);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $s1 = BaseDauParser::normalizeOperationalDate($reports[$i]['start_date'] ?? ($reports[$i]['meta']['start_date'] ?? ''));
                $e1 = BaseDauParser::normalizeOperationalDate($reports[$i]['end_date'] ?? ($reports[$i]['meta']['end_date'] ?? ''));
                $s2 = BaseDauParser::normalizeOperationalDate($reports[$j]['start_date'] ?? ($reports[$j]['meta']['start_date'] ?? ''));
                $e2 = BaseDauParser::normalizeOperationalDate($reports[$j]['end_date'] ?? ($reports[$j]['meta']['end_date'] ?? ''));

                if ($s1 && $e1 && $s2 && $e2) {
                    // Check overlap: start1 <= end2 and end1 >= start2
                    if ($s1 <= $e2 && $e1 >= $s2 && ($s1 !== $s2 || $e1 !== $e2)) {
                        $warnings[] = "Warning: comparison periods overlap between Report #" . ($i + 1) . " and Report #" . ($j + 1) . ".";
                    }
                }
            }
        }

        $isValid = empty($errors) && $checks['same_dau_type'] && $checks['same_airport']
            && $checks['different_period'] && $checks['same_period_length'] && $checks['compatible_template'];

        return [
            'valid'        => $isValid,
            'checks'       => $checks,
            'errors'       => array_values(array_unique($errors)),
            'warnings'     => array_values(array_unique($warnings)),
            'airport_name' => $baseAirportName ?: 'Soekarno Hatta',
            'airport_code' => $baseAirportCode ?: 'CGK',
        ];
    }

    /**
     * Normalize and aggregate data for a single DAU-02 report based on active filters.
     * Each period is filtered and aggregated completely independently.
     *
     * @param array $reportData Full report array from upload (contains 'records', 'meta', etc.)
     * @param array $filters ['flight_type' => 'ALL'|'DOM'|'INT', 'direction' => 'ALL'|'ARRIVAL'|'DEPARTURE']
     * @return array
     */
    public static function aggregatePeriod(array $reportData, array $filters = []): array
    {
        $records = $reportData['records'] ?? [];
        $meta    = $reportData['meta'] ?? [];

        $flightType = strtoupper(trim($filters['flight_type'] ?? 'ALL'));
        $direction  = strtoupper(trim($filters['direction'] ?? 'ALL'));

        $cargoUnit = $reportData['cargo_unit'] ?? ($meta['cargo_unit'] ?? 'Kg');

        $agg = [
            'aircraft'             => 0,
            'aircraft_arrival'     => 0,
            'aircraft_departure'   => 0,
            'aircraft_total'       => 0,

            'passenger'            => 0,
            'passenger_arrival'    => 0,
            'passenger_departure'  => 0,
            'passenger_transit'    => 0,
            'passenger_transfer'   => 0,
            'passenger_total'      => 0,

            'cargo'                => 0,
            'cargo_arrival'        => 0,
            'cargo_departure'      => 0,
            'cargo_total'          => 0,

            'baggage'              => 0,
            'pos'                  => 0,
            'crew'                 => 0,
            'cargo_unit'           => $cargoUnit,
        ];

        foreach ($records as $r) {
            $cat = strtoupper(trim($r['category'] ?? ''));

            // Filter by flight category: DOMESTIK vs INTERNASIONAL
            if ($flightType === 'DOM' || $flightType === 'DOMESTIK') {
                if (stripos($cat, 'DOM') === false) continue;
            } elseif ($flightType === 'INT' || $flightType === 'INTERNASIONAL') {
                if (stripos($cat, 'INT') === false) continue;
            }

            $acArr = (int)($r['aircraft_arrival'] ?? 0);
            $acDep = (int)($r['aircraft_departure'] ?? 0);
            $acTot = (int)($r['aircraft_total'] ?? ($acArr + $acDep));

            $pxArr = (int)($r['passenger_arrival'] ?? 0);
            $pxDep = (int)($r['passenger_departure'] ?? 0);
            $pxTra = (int)($r['passenger_transit'] ?? 0);
            $pxTrf = (int)($r['passenger_transfer'] ?? 0);
            $pxTot = (int)($r['passenger_total'] ?? ($pxArr + $pxDep + $pxTra + $pxTrf));

            $cgArr = (int)($r['cargo_arrival'] ?? 0);
            $cgDep = (int)($r['cargo_departure'] ?? 0);
            $cgTot = (int)($r['cargo'] ?? ($cgArr + $cgDep));

            $bgTot = (int)($r['baggage'] ?? 0);
            $posTot= (int)($r['pos'] ?? 0);
            $cwTot = (int)($r['crew_total'] ?? ($r['crew'] ?? 0));

            // Accumulate base counts
            $agg['aircraft_arrival']   += $acArr;
            $agg['aircraft_departure'] += $acDep;
            $agg['aircraft_total']     += $acTot;

            $agg['passenger_arrival']  += $pxArr;
            $agg['passenger_departure']+= $pxDep;
            $agg['passenger_transit']  += $pxTra;
            $agg['passenger_transfer'] += $pxTrf;
            $agg['passenger_total']    += $pxTot;

            $agg['cargo_arrival']      += $cgArr;
            $agg['cargo_departure']    += $cgDep;
            $agg['cargo_total']        += $cgTot;

            $agg['baggage']            += $bgTot;
            $agg['pos']                += $posTot;
            $agg['crew']               += $cwTot;
        }

        // Apply direction filter to headline values
        if ($direction === 'ARRIVAL') {
            $agg['aircraft']  = $agg['aircraft_arrival'];
            $agg['passenger'] = $agg['passenger_arrival'];
            $agg['cargo']     = $agg['cargo_arrival'] > 0 ? $agg['cargo_arrival'] : $agg['cargo_total'];
        } elseif ($direction === 'DEPARTURE') {
            $agg['aircraft']  = $agg['aircraft_departure'];
            $agg['passenger'] = $agg['passenger_departure'];
            $agg['cargo']     = $agg['cargo_departure'] > 0 ? $agg['cargo_departure'] : $agg['cargo_total'];
        } else {
            $agg['aircraft']  = $agg['aircraft_total'];
            $agg['passenger'] = $agg['passenger_total'];
            $agg['cargo']     = $agg['cargo_total'];
        }

        return $agg;
    }

    /**
     * Compute full comparative historical model for a set of uploaded reports.
     *
     * @param array $rawReports Array of report entries (from Upload models)
     * @param array $filters Active filters (flight_type, direction)
     * @param string|null $baselinePeriodKey Period key for Recovery Rate
     * @return array
     */
    public static function buildComparisonModel(array $rawReports, array $filters = [], ?string $baselinePeriodKey = null): array
    {
        // 1. Sort reports chronologically by startDate
        usort($rawReports, function ($a, $b) {
            $sA = BaseDauParser::normalizeOperationalDate($a['start_date'] ?? ($a['meta']['start_date'] ?? ''));
            $sB = BaseDauParser::normalizeOperationalDate($b['start_date'] ?? ($b['meta']['start_date'] ?? ''));
            return strcmp($sA ?: '', $sB ?: '');
        });

        // 2. Validate reports
        $validation = self::validateComparisonReports($rawReports);

        $periods = [];
        $letters = range('A', 'Z');

        foreach ($rawReports as $idx => $rep) {
            $id        = $rep['id'] ?? ($idx + 1);
            $startDate = BaseDauParser::normalizeOperationalDate($rep['start_date'] ?? ($rep['meta']['start_date'] ?? ''));
            $endDate   = BaseDauParser::normalizeOperationalDate($rep['end_date'] ?? ($rep['meta']['end_date'] ?? ''));
            $dataDays  = self::calculatePeriodDurationDays($startDate, $endDate);

            $letterLabel = "PERIOD " . ($letters[$idx] ?? ($idx + 1));
            $sYear = $startDate ? Carbon::parse($startDate)->year : '';
            $eYear = $endDate ? Carbon::parse($endDate)->year : '';
            $yearLabel   = ($sYear && $sYear === $eYear) ? (string)$sYear : ($startDate ? BaseDauParser::formatDisplayDate($startDate) : $letterLabel);

            $displayRange = ($startDate && $endDate)
                ? (BaseDauParser::formatDisplayDate($startDate) . ' - ' . BaseDauParser::formatDisplayDate($endDate))
                : 'Unknown Period';

            $reportData = $rep['report_data'] ?? $rep;
            $aggregated = self::aggregatePeriod($reportData, $filters);

            $periodKey = "P" . ($idx + 1);

            $periods[$periodKey] = [
                'id'            => $id,
                'key'           => $periodKey,
                'letter'        => $letters[$idx] ?? ($idx + 1),
                'label'         => $letterLabel,
                'short_label'   => $yearLabel,
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'display_range' => $displayRange,
                'data_days'     => $dataDays,
                'metrics'       => $aggregated,
                'airport_name'  => $rep['airport_name'] ?? ($rep['meta']['airport_name'] ?? 'Soekarno Hatta'),
                'airport_code'  => strtoupper($rep['airport_code'] ?? ($rep['meta']['airport_code'] ?? 'CGK')),
            ];
        }

        // 3. Resolve baseline period for Recovery Rate (default: first period)
        $periodKeys = array_keys($periods);
        if (!$baselinePeriodKey || !isset($periods[$baselinePeriodKey])) {
            $baselinePeriodKey = $periodKeys[0] ?? null;
        }

        // 4. Calculate Sequential Growth and Baseline Recovery Rate for each metric
        $metricKeys = ['passenger', 'aircraft', 'cargo'];
        $analysis = [];

        foreach ($metricKeys as $mKey) {
            $series = [];
            $prevVal = null;
            $baselineVal = $baselinePeriodKey ? ($periods[$baselinePeriodKey]['metrics'][$mKey] ?? 0) : 0;

            foreach ($periods as $pKey => $pData) {
                $currVal = $pData['metrics'][$mKey] ?? 0;

                // Difference: Current - Previous
                $diff = ($prevVal !== null) ? ($currVal - $prevVal) : 0;

                // Sequential Growth %: ((Current - Previous) / Previous) * 100
                $growthPct = null;
                $growthFormatted = '—';
                if ($prevVal !== null) {
                    if ($prevVal > 0) {
                        $growthPct = round((($currVal - $prevVal) / $prevVal) * 100, 2);
                        $growthFormatted = ($growthPct > 0 ? '+' : '') . number_format($growthPct, 2) . '%';
                    } elseif ($currVal > 0) {
                        $growthPct = null;
                        $growthFormatted = 'N/A'; // Handled safely without NaN or Infinity
                    } else {
                        $growthPct = 0.0;
                        $growthFormatted = '0.00%';
                    }
                }

                // Recovery Rate vs Baseline: (Current / Baseline) * 100
                $recoveryRate = null;
                $recoveryFormatted = '—';
                if ($baselineVal > 0) {
                    $recoveryRate = round(($currVal / $baselineVal) * 100, 2);
                    $recoveryFormatted = number_format($recoveryRate, 2) . '%';
                }

                $series[$pKey] = [
                    'period_key'        => $pKey,
                    'period_label'      => $pData['label'],
                    'short_label'       => $pData['short_label'],
                    'display_range'     => $pData['display_range'],
                    'value'             => $currVal,
                    'previous_value'    => $prevVal,
                    'difference'        => $diff,
                    'difference_fmt'    => ($diff > 0 ? '+' : '') . number_format($diff),
                    'growth_pct'        => $growthPct,
                    'growth_fmt'        => $growthFormatted,
                    'recovery_rate'     => $recoveryRate,
                    'recovery_fmt'      => $recoveryFormatted,
                ];

                $prevVal = $currVal;
            }

            $analysis[$mKey] = [
                'metric'  => $mKey,
                'series'  => $series,
            ];
        }

        // 5. Extract Key Highlights (Growth from P1 to last period, or consecutive)
        $firstKey = reset($periodKeys);
        $lastKey  = end($periodKeys);

        $highlights = [];
        foreach ($metricKeys as $mKey) {
            $v1 = $periods[$firstKey]['metrics'][$mKey] ?? 0;
            $v2 = $periods[$lastKey]['metrics'][$mKey] ?? 0;
            $diff = $v2 - $v1;
            if ($v1 > 0) {
                $pct = round((($v2 - $v1) / $v1) * 100, 2);
                $pctStr = ($pct > 0 ? '+' : '') . number_format($pct, 2) . '%';
            } elseif ($v2 > 0) {
                $pctStr = 'N/A';
            } else {
                $pctStr = '0.00%';
            }

            $highlights[$mKey] = [
                'start_value' => $v1,
                'end_value'   => $v2,
                'difference'  => $diff,
                'growth_pct'  => $pctStr,
                'is_positive' => $diff >= 0,
            ];
        }

        $airportName = $validation['airport_name'];
        $airportCode = $validation['airport_code'];

        return [
            'valid'               => $validation['valid'],
            'validation'          => $validation,
            'airport_name'        => $airportName,
            'airport_code'        => $airportCode,
            'periods'             => $periods,
            'period_count'        => count($periods),
            'baseline_period_key' => $baselinePeriodKey,
            'analysis'            => $analysis,
            'highlights'          => $highlights,
            'cargo_unit'          => $periods[$firstKey]['metrics']['cargo_unit'] ?? 'Kg',
            'filters'             => $filters,
        ];
    }
}
