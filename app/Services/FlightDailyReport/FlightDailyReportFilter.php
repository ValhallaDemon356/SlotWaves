<?php

namespace App\Services\FlightDailyReport;

class FlightDailyReportFilter
{
    /**
     * Apply filter cascade to raw FDR records.
     * Raw Records → Filter State → Aggregated Metrics Payload → Charts & Table
     */
    /**
     * Classify records into movement records and summary rows.
     */
    public function classifyRows(array $records): array
    {
        $movementRecords = [];
        $summaryRecords = [];
        foreach ($records as $r) {
            if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY'
                || strcasecmp(trim($r['air_line'] ?? ''), 'PAX ALL') === 0
                || stripos(trim($r['air_line'] ?? ''), 'PAX ALL') !== false
                || stripos(trim($r['desc'] ?? ''), 'PAX ALL') !== false) {
                $summaryRecords[] = $r;
                continue;
            }
            $movementRecords[] = $r;
        }
        return [
            'movement_records' => array_values($movementRecords),
            'summary_rows'     => array_values($summaryRecords),
            0                  => array_values($movementRecords),
            1                  => array_values($summaryRecords),
        ];
    }

    /**
     * Apply filter cascade to raw FDR records.
     * Raw Records → Classify Rows → Movement Records → Global Filters → Date Scope → Filtered Records
     */
    public function apply(array $records, array $filters, array $meta = []): array
    {
        $filtered = [];

        // 1. Classify rows and exclude summary rows (Section 8, 9, 10 & 11)
        [$movementRecords, $summaryRecords] = $this->classifyRows($records);
        $totalCount = count($movementRecords);

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
        $rawDateScope  = strtoupper(trim($filters['date_scope'] ?? ''));
        $rawAnalysisDate = trim($filters['analysis_date'] ?? '');
        $analysisLevel = strtoupper(trim($filters['analysis_level'] ?? ''));
        $analysisMonth = trim($filters['analysis_month'] ?? '');
        $analysisYear  = trim($filters['analysis_year'] ?? '');
        $reportMode    = (int)($filters['report_mode'] ?? 1);
        $reqVersion    = $filters['v'] ?? ($filters['req_id'] ?? time());

        // Resolve explicit date scope state (Section 3, 4, 5, 6 & 23)
        if ($rawDateScope === 'ALL_PERIOD' || $rawDateScope === 'FULL_RANGE' || $rawDateScope === 'FULL' || $rawAnalysisDate === 'ALL' || $analysisLevel === 'FULL' || $analysisLevel === 'ALL_PERIOD' || ($rawDateScope === '' && ($rawAnalysisDate === '' || $rawAnalysisDate === null))) {
            $dateScope = 'ALL_PERIOD';
            $stdAnalysisDate = null;
            $analysisLevel = 'FULL';
        } elseif ($rawDateScope === 'DAY' || (!empty($rawAnalysisDate) && $rawAnalysisDate !== 'ALL' && $rawAnalysisDate !== 'N/A')) {
            $dateScope = 'DAY';
            $stdAnalysisDate = self::standardizeDate($rawAnalysisDate);
            $analysisLevel = 'DAILY';
        } elseif ($rawDateScope === 'RANGE' || (!empty($startDate) && !empty($endDate))) {
            $dateScope = 'RANGE';
            $stdAnalysisDate = null;
            $analysisLevel = 'RANGE';
        } else {
            $dateScope = 'ALL_PERIOD';
            $stdAnalysisDate = null;
            $analysisLevel = 'FULL';
        }

        $activeChips = [];

        // Active chip for date scope
        if ($dateScope === 'ALL_PERIOD') {
            $activeChips[] = ['key' => 'date_scope', 'label' => 'Period: FULL RANGE', 'text' => 'Period: FULL RANGE', 'value' => 'ALL_PERIOD'];
        } elseif ($dateScope === 'DAY') {
            if ($stdAnalysisDate !== null && $stdAnalysisDate !== 'N/A') {
                $displayDate = date('d-m-Y', strtotime($stdAnalysisDate));
                $activeChips[] = ['key' => 'analysis_date', 'label' => "Date: {$displayDate}", 'text' => "Date: {$displayDate}", 'value' => $stdAnalysisDate];
            }
        } elseif ($analysisLevel === 'MONTHLY') {
            $monthTarget = !empty($analysisMonth) ? $analysisMonth : ($stdAnalysisDate ? substr($stdAnalysisDate, 0, 7) : substr($startDate, 0, 7));
            if (!empty($monthTarget)) {
                $displayMonth = date('F Y', strtotime($monthTarget . '-01'));
                $activeChips[] = ['key' => 'analysis_level', 'label' => "Month: {$displayMonth}", 'text' => "Month: {$displayMonth}", 'value' => $monthTarget];
            }
        } elseif ($analysisLevel === 'YEARLY') {
            $yearTarget = !empty($analysisYear) ? $analysisYear : ($stdAnalysisDate ? substr($stdAnalysisDate, 0, 4) : substr($startDate, 0, 4));
            if (!empty($yearTarget)) {
                $activeChips[] = ['key' => 'analysis_level', 'label' => "Year: {$yearTarget}", 'text' => "Year: {$yearTarget}", 'value' => $yearTarget];
            }
        }

        $isSingleAirport = (bool)($meta['is_single_airport'] ?? (count($meta['report_airports'] ?? []) <= 1));
        if ($airport !== 'ALL' && !empty($airport) && !$isSingleAirport) {
            $activeChips[] = ['key' => 'airport', 'label' => "Airport: {$airport}", 'text' => "Airport: {$airport}", 'value' => $airport];
        }
        if ($leg !== 'ALL' && !empty($leg)) {
            $activeChips[] = ['key' => 'leg', 'label' => "Leg: {$leg}", 'text' => "Leg: {$leg}", 'value' => $leg];
        }
        if ($operator !== 'ALL' && !empty($operator)) {
            $activeChips[] = ['key' => 'operator', 'label' => "Operator: {$operator}", 'text' => "Operator: {$operator}", 'value' => $operator];
        }
        if ($traffic !== 'ALL' && !empty($traffic)) {
            $activeChips[] = ['key' => 'traffic', 'label' => "Traffic: {$traffic}", 'text' => "Traffic: {$traffic}", 'value' => $traffic];
        }
        if ($realization !== 'ALL' && !empty($realization)) {
            $activeChips[] = ['key' => 'realization', 'label' => "Realized: {$realization}", 'text' => "Realized: {$realization}", 'value' => $realization];
        }
        if (!empty($flightNo)) {
            $activeChips[] = ['key' => 'flight_no', 'label' => "Flight: {$flightNo}", 'text' => "Flight: {$flightNo}", 'value' => $flightNo];
        }
        if (!empty($suffix)) {
            $activeChips[] = ['key' => 'suffix', 'label' => "Suffix: {$suffix}", 'text' => "Suffix: {$suffix}", 'value' => $suffix];
        }
        if (!empty($startDate) || !empty($endDate)) {
            $lbl = trim("{$startDate} to {$endDate}");
            $activeChips[] = ['key' => 'date_range', 'label' => "Range: {$lbl}", 'text' => "Range: {$lbl}", 'value' => $lbl];
        }
        if (!empty($search)) {
            $activeChips[] = ['key' => 'search', 'label' => "Query: {$search}", 'text' => "Query: {$search}", 'value' => $search];
        }

        $excludedReasons = [];

        foreach ($movementRecords as $r) {
            $rDate = self::standardizeDate($r['operational_date'] ?? ($r['flight_date'] ?? ''));

            // 0. Date Scope Filter (Explicit Section 3, 4, 5 & 23)
            if ($dateScope === 'DAY') {
                if ($stdAnalysisDate !== null && $stdAnalysisDate !== 'N/A') {
                    if ($rDate !== $stdAnalysisDate) {
                        $excludedReasons['Outside selected analysis date (' . ($rDate ?: 'N/A') . ')'] = ($excludedReasons['Outside selected analysis date (' . ($rDate ?: 'N/A') . ')'] ?? 0) + 1;
                        continue;
                    }
                }
            } elseif ($dateScope === 'RANGE') {
                if (!empty($startDate)) {
                    $stdStart = self::standardizeDate($startDate);
                    if ($rDate !== 'N/A' && $rDate < $stdStart) {
                        $excludedReasons['Date earlier than start range'] = ($excludedReasons['Date earlier than start range'] ?? 0) + 1;
                        continue;
                    }
                }
                if (!empty($endDate)) {
                    $stdEnd = self::standardizeDate($endDate);
                    if ($rDate !== 'N/A' && $rDate > $stdEnd) {
                        $excludedReasons['Date later than end range'] = ($excludedReasons['Date later than end range'] ?? 0) + 1;
                        continue;
                    }
                }
            } elseif ($analysisLevel === 'MONTHLY') {
                $monthTarget = !empty($analysisMonth) ? $analysisMonth : ($stdAnalysisDate ? substr($stdAnalysisDate, 0, 7) : substr($startDate, 0, 7));
                if (!empty($monthTarget) && substr($rDate, 0, 7) !== $monthTarget) {
                    $excludedReasons['Outside selected month (' . substr($rDate, 0, 7) . ')'] = ($excludedReasons['Outside selected month (' . substr($rDate, 0, 7) . ')'] ?? 0) + 1;
                    continue;
                }
            } elseif ($analysisLevel === 'YEARLY') {
                $yearTarget = !empty($analysisYear) ? $analysisYear : ($stdAnalysisDate ? substr($stdAnalysisDate, 0, 4) : substr($startDate, 0, 4));
                if (!empty($yearTarget) && substr($rDate, 0, 4) !== $yearTarget) {
                    $excludedReasons['Outside selected year (' . substr($rDate, 0, 4) . ')'] = ($excludedReasons['Outside selected year (' . substr($rDate, 0, 4) . ')'] ?? 0) + 1;
                    continue;
                }
            }
            // For ALL_PERIOD: NO DATE FILTER IS APPLIED! Every valid movement record is retained.

            // 1. Airport Filter (REPORT AIRPORT Scope, Prompt Items 3, 10, 23)
            // Never implement airport == CITY 1 OR airport == CITY 2
            if ($airport !== 'ALL' && !empty($airport)) {
                $recAirport = strtoupper($r['report_airport'] ?? ($r['branch'] ?? ($meta['airport'] ?? '')));
                if ($recAirport !== $airport) {
                    $excludedReasons["Airport mismatch (expected {$airport}, got {$recAirport})"] = ($excludedReasons["Airport mismatch (expected {$airport}, got {$recAirport})"] ?? 0) + 1;
                    continue;
                }
            }

            // 2. Leg Filter (ALL / ARR / DEP / ARRIVAL / DEPARTURE)
            if ($leg !== 'ALL' && !empty($leg)) {
                $dir = strtoupper($r['direction'] ?? '');
                $rLeg = strtoupper($r['leg'] ?? '');
                $matchesLeg = false;
                if ($leg === 'ARR' || $leg === 'ARRIVAL') {
                    $matchesLeg = ($dir === 'ARRIVAL' || str_starts_with($rLeg, 'A') || str_starts_with($dir, 'A'));
                } elseif ($leg === 'DEP' || $leg === 'DEPARTURE') {
                    $matchesLeg = ($dir === 'DEPARTURE' || str_starts_with($rLeg, 'D') || str_starts_with($dir, 'D'));
                } else {
                    $matchesLeg = ($dir === $leg || str_starts_with($rLeg, $leg[0]));
                }
                if (!$matchesLeg) {
                    $excludedReasons["Leg mismatch (flight is {$dir})"] = ($excludedReasons["Leg mismatch (flight is {$dir})"] ?? 0) + 1;
                    continue;
                }
            }

            // 3. Operator Filter
            if ($operator !== 'ALL' && !empty($operator) && strcasecmp($operator, 'ALL AIRLINE') !== 0) {
                $resolved = self::resolveAirlineCode($operator);
                $al = strtoupper(trim($r['air_line'] ?? ($r['operator'] ?? '')));
                $flNo = strtoupper(trim($r['flight_no'] ?? ''));
                $matchesAl = false;

                if (!empty($resolved)) {
                    foreach ($resolved as $code) {
                        if ($al === $code || stripos($al, $code) !== false || str_starts_with($flNo, $code)) {
                            $matchesAl = true;
                            break;
                        }
                    }
                } else {
                    $matchesAl = (strcasecmp($al, $operator) === 0 || stripos($al, $operator) !== false || str_starts_with($flNo, strtoupper($operator)));
                }

                if (!$matchesAl) {
                    $excludedReasons["Operator mismatch ({$al})"] = ($excludedReasons["Operator mismatch ({$al})"] ?? 0) + 1;
                    continue;
                }
            }

            // 4. Traffic Filter (ALL / DOM / INTL / DOMESTIC / INTERNATIONAL)
            if ($traffic !== 'ALL' && !empty($traffic)) {
                $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? 'DOMESTIC')));
                $matches = ($tr === $traffic);
                if (!$matches) {
                    if (($traffic === 'DOM' || $traffic === 'DOMESTIC') && ($tr === 'DOM' || $tr === 'DOMESTIC')) $matches = true;
                    if (($traffic === 'INT' || $traffic === 'INTL' || $traffic === 'INTERNATIONAL') && ($tr === 'INT' || $tr === 'INTL' || $tr === 'INTERNATIONAL')) $matches = true;
                }
                if (!$matches) {
                    $excludedReasons["Traffic mismatch ({$tr})"] = ($excludedReasons["Traffic mismatch ({$tr})"] ?? 0) + 1;
                    continue;
                }
            }

            // 5. Realization Filter (ALL / YES / NO)
            if ($realization !== 'ALL' && !empty($realization)) {
                $isRealized = !empty($r['is_realized']) || !empty($r['realization']);
                if ($realization === 'YES' && !$isRealized) {
                    $excludedReasons['Unrealized flight (no actual AIBT/AOBT)'] = ($excludedReasons['Unrealized flight (no actual AIBT/AOBT)'] ?? 0) + 1;
                    continue;
                }
                if ($realization === 'NO' && $isRealized) {
                    $excludedReasons['Realized flight (excluded by NO filter)'] = ($excludedReasons['Realized flight (excluded by NO filter)'] ?? 0) + 1;
                    continue;
                }
            }

            // 6. Flight No & Suffix Filter
            if (!empty($flightNo)) {
                $fn = strtoupper($r['flight_no'] ?? '');
                $fnb = strtoupper($r['flight_no_base'] ?? '');
                if (stripos($fn, $flightNo) === false && stripos($fnb, $flightNo) === false) {
                    $excludedReasons["Flight No mismatch ({$fn})"] = ($excludedReasons["Flight No mismatch ({$fn})"] ?? 0) + 1;
                    continue;
                }
            }
            if (!empty($suffix)) {
                $sfx = strtoupper($r['flight_suffix'] ?? '');
                if ($sfx !== $suffix) {
                    $excludedReasons["Suffix mismatch ({$sfx})"] = ($excludedReasons["Suffix mismatch ({$sfx})"] ?? 0) + 1;
                    continue;
                }
            }

            // 7. Date Range Filter
            if (!empty($startDate)) {
                $stdStart = self::standardizeDate($startDate);
                $fd = self::standardizeDate($r['flight_date'] ?? '');
                if ($fd !== 'N/A' && $fd < $stdStart) {
                    $excludedReasons['Date earlier than start range'] = ($excludedReasons['Date earlier than start range'] ?? 0) + 1;
                    continue;
                }
            }
            if (!empty($endDate)) {
                $stdEnd = self::standardizeDate($endDate);
                $fd = self::standardizeDate($r['flight_date'] ?? '');
                if ($fd !== 'N/A' && $fd > $stdEnd) {
                    $excludedReasons['Date later than end range'] = ($excludedReasons['Date later than end range'] ?? 0) + 1;
                    continue;
                }
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
                    $excludedReasons['Search query mismatch'] = ($excludedReasons['Search query mismatch'] ?? 0) + 1;
                    continue;
                }
            }

            $filtered[] = $r;
        }

        $filteredCount = count($filtered);
        $excludedCount = $totalCount - $filteredCount;

        return [
            'records'        => array_values($filtered),
            'total_count'    => $totalCount,
            'source_count'   => $totalCount,
            'normalized_count' => $totalCount,
            'filtered_count' => $filteredCount,
            'excluded_count' => $excludedCount,
            'reconciliation' => [
                'source_count'      => $totalCount,
                'normalized_count'  => $totalCount,
                'filtered_count'    => $filteredCount,
                'excluded_count'    => $excludedCount,
                'is_reconciled'     => ($excludedCount === 0),
                'exclusion_reasons' => $excludedReasons,
            ],
            'counter_text'   => 'Showing ' . number_format($filteredCount) . ' of ' . number_format($totalCount) . ' records',
            'active_chips'   => $activeChips,
            'filters'        => [
                'date_scope'     => $dateScope,
                'analysis_level' => $analysisLevel,
                'analysis_date'  => $stdAnalysisDate ?? ($rawAnalysisDate ?: null),
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
     * Resolve airline operator string (code or full name) to potential codes and aliases.
     */
    public static function resolveAirlineCode(string $operator): array
    {
        $op = trim($operator);
        if ($op === '' || strcasecmp($op, 'ALL') === 0 || strcasecmp($op, 'ALL AIRLINE') === 0) {
            return [];
        }

        static $map = [
            'GARUDA INDONESIA'   => ['GA', 'GARUDA INDONESIA'],
            'GARUDA'             => ['GA', 'GARUDA INDONESIA'],
            'GA'                 => ['GA', 'GARUDA INDONESIA'],
            'SRIWIJAYA AIR'      => ['SJ', 'SRIWIJAYA AIR'],
            'SRIWIJAYA'          => ['SJ', 'SRIWIJAYA AIR'],
            'SJ'                 => ['SJ', 'SRIWIJAYA AIR'],
            'CITILINK'           => ['QG', 'CITILINK'],
            'CITILINK INDONESIA' => ['QG', 'CITILINK'],
            'QG'                 => ['QG', 'CITILINK'],
            'BATIK AIR'          => ['ID', 'BATIK AIR'],
            'BATIK'              => ['ID', 'BATIK AIR'],
            'ID'                 => ['ID', 'BATIK AIR'],
            'LION AIR'           => ['JT', 'LION AIR'],
            'LION'               => ['JT', 'LION AIR'],
            'JT'                 => ['JT', 'LION AIR'],
            'SUPER AIR JET'      => ['IU', 'SUPER AIR JET'],
            'IU'                 => ['IU', 'SUPER AIR JET'],
            'PELITA AIR'         => ['IP', 'PELITA AIR'],
            'PELITA'             => ['IP', 'PELITA AIR'],
            'IP'                 => ['IP', 'PELITA AIR'],
            'TRANSNUSA'          => ['8B', 'TRANSNUSA'],
            '8B'                 => ['8B', 'TRANSNUSA'],
            'INDONESIA AIRASIA'  => ['QZ', 'AIRASIA'],
            'AIRASIA'            => ['QZ', 'AIRASIA'],
            'QZ'                 => ['QZ', 'AIRASIA'],
            'WINGS AIR'          => ['IW', 'WINGS AIR'],
            'IW'                 => ['IW', 'WINGS AIR'],
            'NAM AIR'            => ['IN', 'NAM AIR'],
            'IN'                 => ['IN', 'NAM AIR'],
        ];

        $upper = strtoupper($op);
        if (isset($map[$upper])) {
            return $map[$upper];
        }

        try {
            $air = \App\Models\Airline::where('airline_code', $upper)
                ->orWhereRaw('UPPER(airline_name) = ?', [$upper])
                ->first();
            if ($air) {
                return array_unique([strtoupper($air->airline_code), strtoupper($air->airline_name)]);
            }
        } catch (\Throwable $e) {}

        return [$upper];
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
