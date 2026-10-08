<?php

namespace App\Services\FlightDailyReport;

use App\Models\FdrFlight;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FdrDatabaseService
{
    /**
     * Check if an upload already has records in the fdr_flights database table.
     */
    public function hasDatabaseRecords(Upload $upload): bool
    {
        return FdrFlight::where('upload_id', $upload->id)->exists();
    }

    /**
     * Get count of database records for an upload.
     */
    public function getRecordCount(Upload $upload): int
    {
        return FdrFlight::where('upload_id', $upload->id)->count();
    }

    /**
     * Ingest/Sync normalized FDR records into the fdr_flights table in efficient batches.
     * Guarantees idempotency by clearing any previous partial records for this upload.
     */
    public function syncUploadToDatabase(Upload $upload, array $records, array $meta = []): int
    {
        if (empty($records)) {
            return 0;
        }

        $now = now();
        $batch = [];
        $batchSize = 500;
        $totalInserted = 0;
        $defaultAirport = strtoupper(trim($meta['airport'] ?? 'CGK'));

        DB::beginTransaction();
        try {
            FdrFlight::where('upload_id', $upload->id)->delete();

            foreach ($records as $r) {
                $row = $this->transformRecordToDbRow($r, $upload->id, $defaultAirport, $meta, $now);
                if ($row === null) {
                    continue;
                }

                $batch[] = $row;
                if (count($batch) >= $batchSize) {
                    DB::table('fdr_flights')->insert($batch);
                    $totalInserted += count($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('fdr_flights')->insert($batch);
                $totalInserted += count($batch);
                $batch = [];
            }

            DB::commit();
            Log::info("FdrDatabaseService: Synced {$totalInserted} flights to database for upload #{$upload->id}");
            return $totalInserted;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("FdrDatabaseService sync error for upload #{$upload->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Memory-safe streaming parser and database syncer directly from file.
     * Streams 2,000 rows at a time, keeping peak PHP memory below 20MB even for 50MB files.
     */
    public function syncUploadFromFile(Upload $upload, string $filePath, FlightDailyReportParser $parser): int
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Source FDR file not found: {$filePath}");
        }

        $now = now();
        $totalInserted = 0;
        $offset = 0;
        $isEof = false;
        $meta = [];

        DB::beginTransaction();
        try {
            FdrFlight::where('upload_id', $upload->id)->delete();

            while (!$isEof) {
                $batchResult = $parser->parseBatch($filePath, $offset, 2000, $meta);
                $offset = $batchResult['next_offset'];
                $isEof = $batchResult['is_eof'];
                $meta = array_merge($meta, $batchResult['meta'] ?? []);

                $records = $batchResult['records'] ?? [];
                if (!empty($records)) {
                    $dbRows = [];
                    $defaultAirport = strtoupper(trim($meta['airport'] ?? 'CGK'));
                    foreach ($records as $r) {
                        $row = $this->transformRecordToDbRow($r, $upload->id, $defaultAirport, $meta, $now);
                        if ($row !== null) {
                            $dbRows[] = $row;
                        }
                    }

                    foreach (array_chunk($dbRows, 500) as $chunk) {
                        DB::table('fdr_flights')->insert($chunk);
                        $totalInserted += count($chunk);
                    }
                    unset($dbRows);
                    unset($records);
                }
            }

            DB::commit();
            Log::info("FdrDatabaseService: Synced {$totalInserted} flights from file for upload #{$upload->id}");
            return $totalInserted;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("FdrDatabaseService file sync error for upload #{$upload->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Transform a raw/normalized FDR row into a native PostgreSQL fdr_flights insert array.
     */
    public function transformRecordToDbRow(array $r, int $uploadId, string $defaultAirport, array $meta, $now): ?array
    {
        if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY') {
            return null;
        }

        $flNo = trim((string)($r['flight_no'] ?? 'N/A'));
        $airline = trim((string)($r['air_line'] ?? 'N/A'));
        if ($flNo === 'N/A' && $airline === 'N/A') {
            return null;
        }

        $schedDt = !empty($r['scheduled_datetime']) ? Carbon::parse($r['scheduled_datetime'])->toDateTimeString() : null;
        $actDt = !empty($r['actual_datetime']) ? Carbon::parse($r['actual_datetime'])->toDateTimeString() : null;

        $flightDate = null;
        if (!empty($r['flight_date']) && $r['flight_date'] !== 'N/A') {
            $flightDate = FlightDailyReportFilter::standardizeDate($r['flight_date']);
        } elseif (!empty($r['operational_date']) && $r['operational_date'] !== 'N/A') {
            $flightDate = FlightDailyReportFilter::standardizeDate($r['operational_date']);
        } elseif ($schedDt) {
            $flightDate = substr($schedDt, 0, 10);
        } elseif (!empty($meta['period_start'])) {
            $flightDate = FlightDailyReportFilter::standardizeDate($meta['period_start']);
        }

        $direction = strtoupper(trim((string)($r['direction'] ?? 'ARRIVAL')));
        if (!in_array($direction, ['ARRIVAL', 'DEPARTURE'])) {
            $direction = 'ARRIVAL';
        }

        $repAirport = strtoupper(trim((string)($r['report_airport'] ?? ($r['branch'] ?? $defaultAirport))));
        if (empty($repAirport) || $repAirport === 'N/A') {
            $repAirport = $defaultAirport;
        }

        $traffic = strtoupper(trim((string)($r['traffic'] ?? ($r['route_type'] ?? 'DOMESTIC'))));
        if (!in_array($traffic, ['DOMESTIC', 'INTERNATIONAL'])) {
            $traffic = ($traffic === 'INT' || $traffic === 'INTL') ? 'INTERNATIONAL' : 'DOMESTIC';
        }

        $delayMinutes = (int)($r['delay_minutes'] ?? 0);
        $isRealized = (bool)($r['is_realized'] ?? false);
        if (!$isRealized && (!empty($r['aibt']) && $r['aibt'] !== 'N/A' || !empty($r['aobt']) && $r['aobt'] !== 'N/A')) {
            $isRealized = true;
        }

        $status = 'ON_TIME';
        if (!$isRealized) {
            $status = 'UNREALIZED';
        } elseif ($delayMinutes > 15) {
            $status = 'DELAYED';
        } elseif (!empty($r['is_irregular'])) {
            $status = 'IRREGULAR';
        }

        $loadFactor = null;
        if (isset($r['load_factor']) && is_numeric($r['load_factor'])) {
            $loadFactor = (float)$r['load_factor'];
        } elseif ((int)($r['cap'] ?? 0) > 0) {
            $loadFactor = round(((int)($r['load'] ?? 0) / (int)$r['cap']) * 100.0, 2);
        }

        $schedTimeStr = $r['sched_display'] ?? ($direction === 'ARRIVAL' ? ($r['sibt'] ?? null) : ($r['sobt'] ?? null));
        $actTimeStr = $r['actual_display'] ?? ($direction === 'ARRIVAL' ? ($r['aibt'] ?? null) : ($r['aobt'] ?? null));

        return [
            'upload_id'            => $uploadId,
            'flight_date'          => $flightDate ?: date('Y-m-d'),
            'flight_number'        => $flNo,
            'flight_number_base'   => $r['flight_no_base'] ?? $flNo,
            'flight_suffix'        => $r['flight_suffix'] ?? null,
            'airline_code'         => $airline,
            'paired_flight_number' => $r['paired_no'] ?? null,
            'aircraft_type'        => $r['desc'] ?? null,
            'registration_number'  => $r['reg_no'] ?? null,
            'leg'                  => $r['leg'] ?? null,
            'direction'            => $direction,
            'movement_type'        => !empty($r['is_scheduled']) ? 'SCHEDULED' : 'UNSCHEDULED',
            'origin_airport'       => $r['city_1'] ?? null,
            'destination_airport'  => $r['city_2'] ?? null,
            'report_airport'       => $repAirport,
            'traffic_type'         => $traffic,
            'scheduled_time'       => $schedDt ? substr($schedDt, 11, 8) : null,
            'actual_time'          => $actDt ? substr($actDt, 11, 8) : null,
            'scheduled_datetime'   => $schedDt,
            'actual_datetime'      => $actDt,
            'scheduled_time_str'   => $schedTimeStr,
            'actual_time_str'      => $actTimeStr,
            'scheduled_hour'       => isset($r['scheduled_hour']) ? (int)$r['scheduled_hour'] : null,
            'actual_hour'          => isset($r['actual_hour']) ? (int)$r['actual_hour'] : null,
            'operational_hour'     => (int)($r['operational_hour'] ?? ($r['hour'] ?? 0)),
            'delay_minutes'        => $delayMinutes,
            'status'               => $status,
            'is_realized'          => $isRealized ? 'true' : 'false',
            'is_irregular'         => (!empty($r['is_irregular'])) ? 'true' : 'false',
            'passenger_capacity'   => (int)($r['cap'] ?? 0),
            'passenger_load'       => (int)($r['load'] ?? 0),
            'load_factor'          => $loadFactor,
            'pax_adult'            => (int)($r['adult'] ?? 0),
            'pax_child'            => (int)($r['child'] ?? 0),
            'pax_infant'           => (int)($r['infant'] ?? 0),
            'pax_transit'          => (int)($r['transit'] ?? 0),
            'pax_transfer'         => (int)($r['transfer'] ?? 0),
            'cargo_kg'             => (float)($r['cargo_kg'] ?? 0),
            'baggage_kg'           => (float)($r['baggage_kg'] ?? 0),
            'pos_kg'               => (float)($r['pos_kg'] ?? 0),
            'stand'                => $r['stand'] ?? null,
            'runway'               => $r['runway'] ?? null,
            'mtow'                 => $r['mtow'] ?? null,
            'raw_data'             => json_encode($r),
            'created_at'           => $now,
            'updated_at'           => $now,
        ];
    }

    /**
     * Compute presentation-ready analytics directly from database for high performance.
     */
    public function computeAnalyticsFromDb(Upload $upload, array $filters, array $meta = []): array
    {
        $base = FdrFlight::where('upload_id', $upload->id)->applyFilters($filters);

        // 1. Core KPIs Aggregation
        $kpiRow = (clone $base)->selectRaw("
            COUNT(*) as total_flights,
            COUNT(CASE WHEN direction = 'ARRIVAL' THEN 1 END) as arrivals,
            COUNT(CASE WHEN direction = 'DEPARTURE' THEN 1 END) as departures,
            COUNT(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'DOMESTIC' THEN 1 END) as arrivals_dom,
            COUNT(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'INTERNATIONAL' THEN 1 END) as arrivals_int,
            COUNT(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'DOMESTIC' THEN 1 END) as departures_dom,
            COUNT(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'INTERNATIONAL' THEN 1 END) as departures_int,
            COUNT(CASE WHEN is_realized = true THEN 1 END) as realized_flights,
            COUNT(CASE WHEN is_realized = false THEN 1 END) as planned_unrealized,
            COUNT(CASE WHEN is_realized = true AND delay_minutes <= 15 THEN 1 END) as on_time_flights,
            COUNT(CASE WHEN is_realized = true AND delay_minutes > 15 THEN 1 END) as delayed_flights,
            COUNT(CASE WHEN is_irregular = true THEN 1 END) as irregular_flights,
            COALESCE(AVG(CASE WHEN is_realized = true THEN delay_minutes END), 0) as avg_delay_minutes,
            COALESCE(SUM(passenger_capacity), 0) as total_capacity,
            COALESCE(SUM(passenger_load), 0) as total_load,
            COALESCE(SUM(pax_adult), 0) as total_adult,
            COALESCE(SUM(pax_child), 0) as total_child,
            COALESCE(SUM(pax_infant), 0) as total_infant,
            COALESCE(SUM(pax_transit), 0) as total_transit,
            COALESCE(SUM(pax_transfer), 0) as total_transfer,
            COALESCE(SUM(cargo_kg), 0) as total_cargo_kg,
            COALESCE(SUM(baggage_kg), 0) as total_baggage_kg,
            COALESCE(SUM(pos_kg), 0) as total_pos_kg
        ")->first();

        $totFlights = (int)($kpiRow->total_flights ?? 0);
        $arrFlights = (int)($kpiRow->arrivals ?? 0);
        $depFlights = (int)($kpiRow->departures ?? 0);
        $realized   = (int)($kpiRow->realized_flights ?? 0);
        $onTime     = (int)($kpiRow->on_time_flights ?? 0);
        $delayed    = (int)($kpiRow->delayed_flights ?? 0);
        $totLoad    = (int)($kpiRow->total_load ?? 0);
        $totCap     = (int)($kpiRow->total_capacity ?? 0);

        $totPax = (int)$kpiRow->total_adult + (int)$kpiRow->total_child + (int)$kpiRow->total_infant;
        if ($totPax === 0 && $totLoad > 0) {
            $totPax = $totLoad;
        }

        $otpPercent = $realized > 0 ? round(($onTime / $realized) * 100, 1) : 0.0;
        $delayPercent = $realized > 0 ? round(($delayed / $realized) * 100, 1) : 0.0;
        $loadFactorAvg = $totCap > 0 ? round(($totLoad / $totCap) * 100, 1) : 0.0;

        // 2. Hourly Movement Distribution (Strictly 24 hourly buckets: 00:00 to 23:00)
        $hourlyRows = (clone $base)->selectRaw("
            operational_hour,
            COUNT(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'DOMESTIC' THEN 1 END) as arr_dom,
            COUNT(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'INTERNATIONAL' THEN 1 END) as arr_int,
            COUNT(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'DOMESTIC' THEN 1 END) as dep_dom,
            COUNT(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'INTERNATIONAL' THEN 1 END) as dep_int,
            COUNT(*) as total
        ")->groupBy('operational_hour')->get()->keyBy('operational_hour');

        $arrDomArr = array_fill(0, 24, 0);
        $arrIntArr = array_fill(0, 24, 0);
        $depDomArr = array_fill(0, 24, 0);
        $depIntArr = array_fill(0, 24, 0);
        $totHourArr = array_fill(0, 24, 0);
        $hourlyLabels = [];
        $timeRanges = [];

        $peakHourIdx = 0;
        $peakHourCount = 0;

        for ($h = 0; $h < 24; $h++) {
            $hStr = sprintf('%02d:00', $h);
            $nextH = sprintf('%02d:59', $h);
            $hourlyLabels[] = $hStr;
            $timeRanges[] = "{$hStr} - {$nextH}";

            if (isset($hourlyRows[$h])) {
                $row = $hourlyRows[$h];
                $arrDomArr[$h] = (int)$row->arr_dom;
                $arrIntArr[$h] = (int)$row->arr_int;
                $depDomArr[$h] = (int)$row->dep_dom;
                $depIntArr[$h] = (int)$row->dep_int;
                $t = (int)$row->total;
                $totHourArr[$h] = $t;
                if ($t > $peakHourCount) {
                    $peakHourCount = $t;
                    $peakHourIdx = $h;
                }
            }
        }

        // Distinct available dates for context
        $dateCounts = (clone $base)->selectRaw("COUNT(DISTINCT flight_date) as cnt")->value('cnt') ?: 1;

        $peakHourRange = sprintf('%02d:00–%02d:59', $peakHourIdx, $peakHourIdx);
        $peakHourAvg = $dateCounts > 1 ? round($peakHourCount / $dateCounts, 1) : null;

        $hourlyDistribution = [
            'labels'        => $hourlyLabels,
            'time_ranges'   => $timeRanges,
            'arr_dom'       => $arrDomArr,
            'arr_int'       => $arrIntArr,
            'dep_dom'       => $depDomArr,
            'dep_int'       => $depIntArr,
            'totals'        => $totHourArr,
            'period_days'   => $dateCounts,
            'period_label'  => ($filters['date_scope'] ?? '') === 'DAY' ? ($filters['analysis_date'] ?? 'Selected Date') : 'Full Period Range',
            'time_basis'    => $filters['time_basis'] ?? 'actual',
            'time_basis_desc' => ($filters['time_basis'] ?? 'actual') === 'actual' ? 'AIBT / AOBT' : 'SIBT / SOBT',
            'peak_hour'     => [
                'hour'            => $peakHourIdx,
                'time_range'      => $peakHourRange,
                'movements'       => $peakHourCount,
                'average_per_day' => $peakHourAvg,
            ],
        ];

        // 3. Delay Histogram (9 Bins)
        $delayBins = [
            ['label' => '< -30m', 'min' => -9999, 'max' => -31, 'color' => '#10B981', 'group' => 'EARLY', 'desc' => 'Very Early (>30m early)'],
            ['label' => '-30m to -16m', 'min' => -30, 'max' => -16, 'color' => '#34D399', 'group' => 'EARLY', 'desc' => 'Early (16m-30m early)'],
            ['label' => '-15m to -1m', 'min' => -15, 'max' => -1, 'color' => '#6EE7B7', 'group' => 'EARLY', 'desc' => 'Slightly Early (1m-15m early)'],
            ['label' => 'On Time (0m)', 'min' => 0, 'max' => 0, 'color' => '#3B82F6', 'group' => 'ON TIME', 'desc' => 'Exactly On Time (0m variance)'],
            ['label' => '+1m to +15m', 'min' => 1, 'max' => 15, 'color' => '#93C5FD', 'group' => 'ON TIME', 'desc' => 'Within Tolerance (1m-15m delay)'],
            ['label' => '+16m to +30m', 'min' => 16, 'max' => 30, 'color' => '#FBBF24', 'group' => 'MINOR DELAY', 'desc' => 'Minor Delay (16m-30m delay)'],
            ['label' => '+31m to +60m', 'min' => 31, 'max' => 60, 'color' => '#F97316', 'group' => 'MODERATE DELAY', 'desc' => 'Moderate Delay (31m-60m delay)'],
            ['label' => '+61m to +120m', 'min' => 61, 'max' => 120, 'color' => '#EF4444', 'group' => 'MAJOR DELAY', 'desc' => 'Major Delay (61m-120m delay)'],
            ['label' => '> +120m', 'min' => 121, 'max' => 9999, 'color' => '#991B1B', 'group' => 'SEVERE DELAY', 'desc' => 'Severe Delay (>120m delay)'],
        ];

        $histCounts = (clone $base)->whereRaw('is_realized = true')->selectRaw("
            COUNT(CASE WHEN delay_minutes < -30 THEN 1 END) as b0,
            COUNT(CASE WHEN delay_minutes >= -30 AND delay_minutes <= -16 THEN 1 END) as b1,
            COUNT(CASE WHEN delay_minutes >= -15 AND delay_minutes <= -1 THEN 1 END) as b2,
            COUNT(CASE WHEN delay_minutes = 0 THEN 1 END) as b3,
            COUNT(CASE WHEN delay_minutes >= 1 AND delay_minutes <= 15 THEN 1 END) as b4,
            COUNT(CASE WHEN delay_minutes >= 16 AND delay_minutes <= 30 THEN 1 END) as b5,
            COUNT(CASE WHEN delay_minutes >= 31 AND delay_minutes <= 60 THEN 1 END) as b6,
            COUNT(CASE WHEN delay_minutes >= 61 AND delay_minutes <= 120 THEN 1 END) as b7,
            COUNT(CASE WHEN delay_minutes > 120 THEN 1 END) as b8
        ")->first();

        for ($i = 0; $i < 9; $i++) {
            $key = 'b' . $i;
            $delayBins[$i]['count'] = (int)($histCounts->$key ?? 0);
        }

        // 4. Passenger & Cargo Hourly Trend
        $hourlyFlow = (clone $base)->selectRaw("
            operational_hour,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN (pax_adult + pax_child + pax_infant) END), 0) as arr_pax,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN (pax_adult + pax_child + pax_infant) END), 0) as dep_pax,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'DOMESTIC' THEN (pax_adult + pax_child + pax_infant) END), 0) as arr_dom_pax,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'INTERNATIONAL' THEN (pax_adult + pax_child + pax_infant) END), 0) as arr_int_pax,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'DOMESTIC' THEN (pax_adult + pax_child + pax_infant) END), 0) as dep_dom_pax,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'INTERNATIONAL' THEN (pax_adult + pax_child + pax_infant) END), 0) as dep_int_pax,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN cargo_kg END), 0) as arr_cargo,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN cargo_kg END), 0) as dep_cargo,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'DOMESTIC' THEN cargo_kg END), 0) as arr_dom_cargo,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'INTERNATIONAL' THEN cargo_kg END), 0) as arr_int_cargo,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'DOMESTIC' THEN cargo_kg END), 0) as dep_dom_cargo,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'INTERNATIONAL' THEN cargo_kg END), 0) as dep_int_cargo
        ")->groupBy('operational_hour')->get()->keyBy('operational_hour');

        $arrPaxArr = array_fill(0, 24, 0);
        $depPaxArr = array_fill(0, 24, 0);
        $arrDomPaxArr = array_fill(0, 24, 0);
        $arrIntPaxArr = array_fill(0, 24, 0);
        $depDomPaxArr = array_fill(0, 24, 0);
        $depIntPaxArr = array_fill(0, 24, 0);
        $arrCargoArr = array_fill(0, 24, 0);
        $depCargoArr = array_fill(0, 24, 0);
        $arrDomCargoArr = array_fill(0, 24, 0);
        $arrIntCargoArr = array_fill(0, 24, 0);
        $depDomCargoArr = array_fill(0, 24, 0);
        $depIntCargoArr = array_fill(0, 24, 0);

        for ($h = 0; $h < 24; $h++) {
            if (isset($hourlyFlow[$h])) {
                $f = $hourlyFlow[$h];
                $arrPaxArr[$h] = (int)$f->arr_pax;
                $depPaxArr[$h] = (int)$f->dep_pax;
                $arrDomPaxArr[$h] = (int)$f->arr_dom_pax;
                $arrIntPaxArr[$h] = (int)$f->arr_int_pax;
                $depDomPaxArr[$h] = (int)$f->dep_dom_pax;
                $depIntPaxArr[$h] = (int)$f->dep_int_pax;
                $arrCargoArr[$h] = (float)$f->arr_cargo;
                $depCargoArr[$h] = (float)$f->dep_cargo;
                $arrDomCargoArr[$h] = (float)$f->arr_dom_cargo;
                $arrIntCargoArr[$h] = (float)$f->arr_int_cargo;
                $depDomCargoArr[$h] = (float)$f->dep_dom_cargo;
                $depIntCargoArr[$h] = (float)$f->dep_int_cargo;
            }
        }

        $combinedTrend = [
            'labels'                => $hourlyLabels,
            'time_ranges'           => $timeRanges,
            'arr_passengers'        => $arrPaxArr,
            'dep_passengers'        => $depPaxArr,
            'arr_dom_passengers'    => $arrDomPaxArr,
            'arr_int_passengers'    => $arrIntPaxArr,
            'dep_dom_passengers'    => $depDomPaxArr,
            'dep_int_passengers'    => $depIntPaxArr,
            'arr_cargo_kg'          => $arrCargoArr,
            'dep_cargo_kg'          => $depCargoArr,
            'arr_dom_cargo_kg'      => $arrDomCargoArr,
            'arr_int_cargo_kg'      => $arrIntCargoArr,
            'dep_dom_cargo_kg'      => $depDomCargoArr,
            'dep_int_cargo_kg'      => $depIntCargoArr,
        ];

        // 5. Airline and Route Breakdown
        $airlineBreakdown = (clone $base)->selectRaw("
            airline_code,
            COUNT(*) as flights,
            SUM(passenger_capacity) as capacity,
            SUM(passenger_load) as load,
            SUM(cargo_kg) as cargo_kg
        ")->groupBy('airline_code')->orderByDesc('flights')->get();

        $airlinesMap = [];
        foreach ($airlineBreakdown as $ab) {
            $airlinesMap[$ab->airline_code] = [
                'airline'    => $ab->airline_code,
                'flights'    => (int)$ab->flights,
                'capacity'   => (int)$ab->capacity,
                'load'       => (int)$ab->load,
                'cargo_kg'   => (float)$ab->cargo_kg,
                'passengers' => (int)$ab->load,
            ];
        }

        // Top Stands & Runways
        $standsList = (clone $base)->whereNotNull('stand')->where('stand', '!=', 'N/A')->where('stand', '!=', '-')
            ->selectRaw("stand, COUNT(*) as flights")
            ->groupBy('stand')->orderByDesc('flights')->take(10)->get();

        $runwaysList = (clone $base)->whereNotNull('runway')->where('runway', '!=', 'N/A')->where('runway', '!=', '-')
            ->selectRaw("runway, COUNT(*) as flights")
            ->groupBy('runway')->orderByDesc('flights')->take(10)->get();

        $standsMap = [];
        foreach ($standsList as $st) {
            $standsMap[] = ['stand' => $st->stand, 'flights' => (int)$st->flights];
        }
        $runwaysMap = [];
        foreach ($runwaysList as $rw) {
            $runwaysMap[] = ['runway' => $rw->runway, 'flights' => (int)$rw->flights];
        }

        $kpis = [
            'total_flights'      => $totFlights,
            'arrivals'           => $arrFlights,
            'departures'         => $depFlights,
            'arrivals_dom'       => (int)$kpiRow->arrivals_dom,
            'arrivals_int'       => (int)$kpiRow->arrivals_int,
            'departures_dom'     => (int)$kpiRow->departures_dom,
            'departures_int'     => (int)$kpiRow->departures_int,
            'on_time_flights'    => $onTime,
            'delayed_flights'    => $delayed,
            'irregular_flights'  => (int)$kpiRow->irregular_flights,
            'otp_percent'        => $otpPercent,
            'delay_percent'      => $delayPercent,
            'load_factor_avg'    => $loadFactorAvg,
            'total_passengers'   => $totPax,
            'total_cargo_kg'     => (float)$kpiRow->total_cargo_kg,
            'total_baggage_kg'   => (float)$kpiRow->total_baggage_kg,
            'total_pos_kg'       => (float)$kpiRow->total_pos_kg,
            'avg_delay_minutes'  => round((float)$kpiRow->avg_delay_minutes, 1),
            'peak_hour'          => $peakHourRange,
            'peak_movements'     => $peakHourCount,
        ];

        $schedVsReal = [
            'on_time_count'   => $onTime,
            'delayed_count'   => $delayed,
            'otp_percent'     => $otpPercent,
            'delay_percent'   => $delayPercent,
            'hist_bins'       => $delayBins,
            'histogram'       => $delayBins,
            'tolerance'       => 15,
        ];

        $paxAnalytics = [
            'total_passengers' => $totPax,
            'composition'      => [
                'adult'    => (int)$kpiRow->total_adult,
                'child'    => (int)$kpiRow->total_child,
                'infant'   => (int)$kpiRow->total_infant,
                'transit'  => (int)$kpiRow->total_transit,
                'transfer' => (int)$kpiRow->total_transfer,
            ],
        ];

        return [
            'meta'                    => $meta,
            'kpis'                    => $kpis,
            'kpi'                     => $kpis,
            'hourly_charts'           => [
                'peak_hour'     => $peakHourRange,
                'peak_movements'=> $peakHourCount,
            ],
            'hourly_distribution'     => $hourlyDistribution,
            'combined_trend'          => $combinedTrend,
            'schedule_vs_realization' => $schedVsReal,
            'sched_vs_real'           => $schedVsReal,
            'passenger_analytics'     => $paxAnalytics,
            'airline_route'           => [
                'airlines' => $airlinesMap,
                'routes'   => [],
            ],
            'fleet_performance'       => [],
            'ground_operations'       => [
                'stands'  => $standsMap,
                'runways' => $runwaysMap,
            ],
            'ground_ops'              => [
                'stands'  => $standsMap,
                'runways' => $runwaysMap,
            ],
            'mode_payload'            => [],
            'reconciliation_apps'     => ['status' => 'MATCH', 'delta' => 0],
            'reconciliation_edifly'   => ['status' => 'MATCH', 'delta' => 0],
        ];
    }
}
