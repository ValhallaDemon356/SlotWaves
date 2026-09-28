<?php

namespace App\Services\FlightDailyReport;

class FlightDailyReportFilter
{
    /**
     * Apply filter cascade to raw FDR records.
     * Raw Records → Filter State → Aggregated Metrics Payload → Charts & Table
     */
    public function apply(array $records, array $filters, array $meta = []): array
    {
        $filtered = [];
        $totalCount = count($records);

        // Normalize filters with defaults
        $airport      = strtoupper(trim($filters['airport'] ?? 'ALL'));
        $leg          = strtoupper(trim($filters['leg'] ?? 'ALL'));
        $operator     = trim($filters['operator'] ?? 'ALL');
        $traffic      = strtoupper(trim($filters['traffic'] ?? ($filters['route_type'] ?? 'ALL')));
        $realization  = strtoupper(trim($filters['realization'] ?? 'ALL'));
        $dataType     = strtoupper(trim($filters['data_type'] ?? 'ALL'));
        $flightNo     = strtoupper(trim($filters['flight_no'] ?? ''));
        $suffix       = strtoupper(trim($filters['suffix'] ?? ''));
        $startDate    = trim($filters['start_date'] ?? '');
        $endDate      = trim($filters['end_date'] ?? '');
        $search        = strtolower(trim($filters['search'] ?? ''));
        $analysisDate  = trim($filters['analysis_date'] ?? '');
        $analysisLevel = strtoupper(trim($filters['analysis_level'] ?? 'DAILY'));
        $analysisMonth = trim($filters['analysis_month'] ?? '');
        $analysisYear  = trim($filters['analysis_year'] ?? '');
        $reportMode    = (int)($filters['report_mode'] ?? 1);
        $reqVersion    = $filters['v'] ?? ($filters['req_id'] ?? time());

        $activeChips = [];

        $stdAnalysisDate = (!empty($analysisDate) && $analysisDate !== 'ALL') ? self::standardizeDate($analysisDate) : null;

        // Format analysis scope chips based on analysis level
        if ($analysisLevel === 'DAILY') {
            if ($stdAnalysisDate !== null && $stdAnalysisDate !== 'N/A') {
                $displayDate = date('d-m-Y', strtotime($stdAnalysisDate));
                $activeChips[] = ['key' => 'analysis_date', 'label' => "Date: {$displayDate}", 'value' => $stdAnalysisDate];
            }
        } elseif ($analysisLevel === 'MONTHLY') {
            $monthTarget = !empty($analysisMonth) ? $analysisMonth : ($stdAnalysisDate ? substr($stdAnalysisDate, 0, 7) : substr($startDate, 0, 7));
            if (!empty($monthTarget)) {
                $displayMonth = date('F Y', strtotime($monthTarget . '-01'));
                $activeChips[] = ['key' => 'analysis_level', 'label' => "Month: {$displayMonth}", 'value' => $monthTarget];
            }
        } elseif ($analysisLevel === 'YEARLY') {
            $yearTarget = !empty($analysisYear) ? $analysisYear : ($stdAnalysisDate ? substr($stdAnalysisDate, 0, 4) : substr($startDate, 0, 4));
            if (!empty($yearTarget)) {
                $activeChips[] = ['key' => 'analysis_level', 'label' => "Year: {$yearTarget}", 'value' => $yearTarget];
            }
        }

        if ($airport !== 'ALL' && !empty($airport)) {
            $activeChips[] = ['key' => 'airport', 'label' => "Airport: {$airport}", 'value' => $airport];
        }
        if ($leg !== 'ALL' && !empty($leg)) {
            $activeChips[] = ['key' => 'leg', 'label' => "Leg: {$leg}", 'value' => $leg];
        }
        if ($operator !== 'ALL' && !empty($operator)) {
            $activeChips[] = ['key' => 'operator', 'label' => "Operator: {$operator}", 'value' => $operator];
        }
        if ($traffic !== 'ALL' && !empty($traffic)) {
            $activeChips[] = ['key' => 'traffic', 'label' => "Traffic: {$traffic}", 'value' => $traffic];
        }
        if ($realization !== 'ALL' && !empty($realization)) {
            $activeChips[] = ['key' => 'realization', 'label' => "Realized: {$realization}", 'value' => $realization];
        }
        if (!empty($flightNo)) {
            $activeChips[] = ['key' => 'flight_no', 'label' => "Flight: {$flightNo}", 'value' => $flightNo];
        }
        if (!empty($suffix)) {
            $activeChips[] = ['key' => 'suffix', 'label' => "Suffix: {$suffix}", 'value' => $suffix];
        }
        if (!empty($startDate) || !empty($endDate)) {
            $lbl = trim("{$startDate} to {$endDate}");
            $activeChips[] = ['key' => 'date_range', 'label' => "Range: {$lbl}", 'value' => $lbl];
        }
        if (!empty($search)) {
            $activeChips[] = ['key' => 'search', 'label' => "Query: {$search}", 'value' => $search];
        }

        foreach ($records as $r) {
            $rDate = self::standardizeDate($r['operational_date'] ?? ($r['flight_date'] ?? ''));

            // 0. Analysis Scope Filter (DAILY / MONTHLY / YEARLY)
            if ($analysisLevel === 'DAILY') {
                if ($stdAnalysisDate !== null && $stdAnalysisDate !== 'N/A') {
                    if ($rDate !== $stdAnalysisDate) {
                        continue;
                    }
                }
            } elseif ($analysisLevel === 'MONTHLY') {
                $monthTarget = !empty($analysisMonth) ? $analysisMonth : ($stdAnalysisDate ? substr($stdAnalysisDate, 0, 7) : substr($startDate, 0, 7));
                if (!empty($monthTarget) && substr($rDate, 0, 7) !== $monthTarget) {
                    continue;
                }
            } elseif ($analysisLevel === 'YEARLY') {
                $yearTarget = !empty($analysisYear) ? $analysisYear : ($stdAnalysisDate ? substr($stdAnalysisDate, 0, 4) : substr($startDate, 0, 4));
                if (!empty($yearTarget) && substr($rDate, 0, 4) !== $yearTarget) {
                    continue;
                }
            }

            // 1. Airport Filter
            if ($airport !== 'ALL' && !empty($airport)) {
                $c1 = strtoupper($r['city_1'] ?? '');
                $c2 = strtoupper($r['city_2'] ?? '');
                $metaAp = strtoupper($meta['airport'] ?? '');
                if ($c1 !== $airport && $c2 !== $airport && $metaAp !== $airport) {
                    continue;
                }
            }

            // 2. Leg Filter (ALL / ARRIVAL / DEPARTURE)
            if ($leg !== 'ALL' && !empty($leg)) {
                $dir = strtoupper($r['direction'] ?? '');
                if ($dir !== $leg && !str_starts_with(strtoupper($r['leg'] ?? ''), $leg[0])) {
                    continue;
                }
            }

            // 3. Operator Filter
            if ($operator !== 'ALL' && !empty($operator)) {
                $al = trim($r['air_line'] ?? ($r['operator'] ?? ''));
                if (strcasecmp($al, $operator) !== 0 && stripos($al, $operator) === false) {
                    continue;
                }
            }

            // 4. Traffic Filter (ALL / DOMESTIC / INTERNATIONAL)
            if ($traffic !== 'ALL' && !empty($traffic)) {
                $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? 'DOMESTIC')));
                $matches = ($tr === $traffic);
                if (!$matches) {
                    if (($traffic === 'DOM' || $traffic === 'DOMESTIC') && ($tr === 'DOM' || $tr === 'DOMESTIC')) $matches = true;
                    if (($traffic === 'INT' || $traffic === 'INTERNATIONAL') && ($tr === 'INT' || $tr === 'INTERNATIONAL')) $matches = true;
                }
                if (!$matches) {
                    continue;
                }
            }

            // 5. Realization Filter (ALL / YES / NO)
            if ($realization !== 'ALL' && !empty($realization)) {
                $isRealized = !empty($r['is_realized']) || !empty($r['realization']);
                if ($realization === 'YES' && !$isRealized) continue;
                if ($realization === 'NO' && $isRealized) continue;
            }

            // 6. Flight No & Suffix Filter
            if (!empty($flightNo)) {
                $fn = strtoupper($r['flight_no'] ?? '');
                $fnb = strtoupper($r['flight_no_base'] ?? '');
                if (stripos($fn, $flightNo) === false && stripos($fnb, $flightNo) === false) {
                    continue;
                }
            }
            if (!empty($suffix)) {
                $sfx = strtoupper($r['flight_suffix'] ?? '');
                if ($sfx !== $suffix) {
                    continue;
                }
            }

            // 7. Date Range Filter
            if (!empty($startDate)) {
                $stdStart = $this->standardizeDate($startDate);
                $fd = $this->standardizeDate($r['flight_date'] ?? '');
                if ($fd !== 'N/A' && $fd < $stdStart) continue;
            }
            if (!empty($endDate)) {
                $stdEnd = $this->standardizeDate($endDate);
                $fd = $this->standardizeDate($r['flight_date'] ?? '');
                if ($fd !== 'N/A' && $fd > $stdEnd) continue;
            }

            // 8. Search Filter
            // Searches: Flight No, Airline, Reg No, Route, Stand, Runway
            if (!empty($search)) {
                $haystack = strtolower(implode(' ', [
                    $r['flight_no'] ?? '',
                    $r['air_line'] ?? '',
                    $r['reg_no'] ?? '',
                    $r['route'] ?? '',
                    $r['stand'] ?? '',
                    $r['runway'] ?? '',
                    $r['city_1'] ?? '',
                    $r['city_2'] ?? '',
                ]));
                if (strpos($haystack, $search) === false) {
                    continue;
                }
            }

            $filtered[] = $r;
        }

        $filteredCount = count($filtered);

        return [
            'records'        => array_values($filtered),
            'total_count'    => $totalCount,
            'filtered_count' => $filteredCount,
            'counter_text'   => "Showing {$filteredCount} of {$totalCount} records",
            'active_chips'   => $activeChips,
            'filters'        => [
                'analysis_level' => $analysisLevel,
                'analysis_date'  => $stdAnalysisDate ?? $analysisDate,
                'analysis_month' => $analysisMonth,
                'analysis_year'  => $analysisYear,
                'airport'        => $airport,
                'leg'            => $leg,
                'operator'       => $operator,
                'traffic'        => $traffic,
                'realization'    => $realization,
                'data_type'      => $dataType,
                'flight_no'      => $flightNo,
                'suffix'         => $suffix,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'search'         => $search,
                'report_mode'    => $reportMode,
                'v'              => $reqVersion,
            ],
            'version'        => $reqVersion,
        ];
    }

    /**
     * Standardize date into Y-m-d.
     */
    public static function standardizeDate(?string $rawDate): string
    {
        try {
            if ($rawDate === null) return 'N/A';
            $rawDate = trim(str_replace('/', '-', $rawDate));
            if (empty($rawDate) || $rawDate === 'N/A') return 'N/A';
            if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $rawDate, $m)) {
                return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
            }
            if (preg_match('/(\d{1,2})-(\d{1,2})-(\d{4})/', $rawDate, $m)) {
                return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            }
            $ts = strtotime($rawDate);
            return $ts ? date('Y-m-d', $ts) : $rawDate;
        } catch (\Throwable $e) {
            return $rawDate ?? 'N/A';
        }
    }

    /**
     * Convenience method to directly filter records.
     */
    public function filterRecords(array $records, array $filters): array
    {
        return $this->apply($records, $filters)['records'];
    }

    /**
     * Convenience method to extract active filter chips.
     */
    public function getActiveFilterChips(array $filters): array
    {
        return $this->apply([], $filters)['active_chips'];
    }
}
