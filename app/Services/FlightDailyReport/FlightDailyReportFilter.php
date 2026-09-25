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
        $search       = strtolower(trim($filters['search'] ?? ''));
        $reportMode   = (int)($filters['report_mode'] ?? 1);
        $reqVersion   = $filters['v'] ?? ($filters['req_id'] ?? time());

        $activeChips = [];

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
            $activeChips[] = ['key' => 'date_range', 'label' => "Date: {$lbl}", 'value' => $lbl];
        }
        if (!empty($search)) {
            $activeChips[] = ['key' => 'search', 'label' => "Query: {$search}", 'value' => $search];
        }

        foreach ($records as $r) {
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
                $al = trim($r['air_line'] ?? '');
                if (strcasecmp($al, $operator) !== 0 && stripos($al, $operator) === false) {
                    continue;
                }
            }

            // 4. Traffic Filter (ALL / DOMESTIC / INTERNATIONAL)
            if ($traffic !== 'ALL' && !empty($traffic)) {
                $tr = strtoupper($r['traffic'] ?? 'DOMESTIC');
                if ($tr !== $traffic) {
                    continue;
                }
            }

            // 5. Realization Filter (ALL / YES / NO)
            if ($realization !== 'ALL' && !empty($realization)) {
                $isRealized = !empty($r['is_realized']);
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
                'airport'     => $airport,
                'leg'         => $leg,
                'operator'    => $operator,
                'traffic'     => $traffic,
                'realization' => $realization,
                'data_type'   => $dataType,
                'flight_no'   => $flightNo,
                'suffix'      => $suffix,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'search'      => $search,
                'report_mode' => $reportMode,
                'v'           => $reqVersion,
            ],
            'version'        => $reqVersion,
        ];
    }

    /**
     * Standardize date into Y-m-d.
     */
    protected function standardizeDate(string $rawDate): string
    {
        try {
            $rawDate = trim(str_replace('/', '-', $rawDate));
            if (empty($rawDate) || $rawDate === 'N/A') return 'N/A';
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $rawDate, $m)) {
                return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
            }
            if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})/', $rawDate, $m)) {
                return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            }
            $ts = strtotime($rawDate);
            return $ts ? date('Y-m-d', $ts) : $rawDate;
        } catch (\Throwable $e) {
            return $rawDate;
        }
    }
}
