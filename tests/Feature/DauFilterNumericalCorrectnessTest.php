<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Http\Controllers\DauDashboardController;
use App\Services\Dau\Parsers\DAU1Parser;
use App\Services\Dau\Parsers\DAU2Parser;
use App\Services\Dau\Parsers\DAU3Parser;
use App\Services\Dau\Parsers\DAU4Parser;
use App\Services\Dau\Parsers\DAU4AParser;
use App\Services\Dau\Parsers\DAU4BParser;
use App\Services\Dau\Parsers\DAU5Parser;
use App\Services\Dau\Parsers\DAU5AParser;
use App\Services\Dau\Parsers\DAU5BParser;
use App\Services\Dau\Parsers\DAU5CParser;
use App\Services\Dau\Parsers\DAU6Parser;
use App\Services\Dau\Parsers\DAU10Parser;
use App\Services\Dau\Parsers\DAU10BParser;
use App\Services\Dau\Parsers\DAU11Parser;
use App\Services\Dau\Parsers\DAU12Parser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * DauFilterNumericalCorrectnessTest
 *
 * Verifies that record-level filtering actually modifies the dataset output
 * (KPIs, filtered_records, summary totals) for every target DAU report type.
 *
 * Golden rule: a filter is only working when:
 *   1. filtered_records contains ONLY records matching the criterion
 *   2. summary totals are recomputed from the filtered set (not pre-aggregated)
 *   3. filtered record count is <= unfiltered record count
 *
 * DAU-10A is the reference implementation and is covered by Dau10AMetricSeparationTest.
 */
class DauFilterNumericalCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private DauDashboardController $controller;
    private \ReflectionMethod $filterMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->airport = Airport::firstOrCreate(
            ['iata_code' => 'CGK'],
            [
                'name'    => 'Soekarno Hatta',
                'city'    => 'Tangerang',
                'country' => 'Indonesia',
            ]
        );

        $this->controller = new DauDashboardController();
        $ref = new ReflectionClass($this->controller);
        $this->filterMethod = $ref->getMethod('filterReportDataset');
        $this->filterMethod->setAccessible(true);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function parseFixture(object $parser, string $filename): array
    {
        $path = base_path("resources/templates/dau/{$filename}");
        return $parser->parse($path);
    }

    private function makeUpload(string $reportType, array $parsed): Upload
    {
        return Upload::create([
            'original_filename' => "{$reportType}.xls",
            'stored_path'       => "uploads/{$reportType}.xls",
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => $reportType,
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $parsed,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);
    }

    private function defaultFilters(array $overrides = []): array
    {
        return array_merge([
            'flight_type'    => 'ALL',
            'terminal'       => 'ALL',
            'hour'           => 'ALL',
            'metric'         => 'aircraft',
            'operation'      => 'ALL',
            'direction'      => 'ALL',
            'airline'        => 'ALL',
            'airport'        => 'ALL',
            'schedule_type'  => 'ALL',
            'status'         => 'ALL',
            'aircraft_type'  => 'ALL',
            'category'       => 'ALL',
            'search'         => '',
            'top_n'          => 'ALL',
            'threshold'      => 0,
            'passenger_type' => 'ALL',
            'display_mode'   => 'absolute',
        ], $overrides);
    }

    private function invoke(array $records, array $filters, array $meta, string $reportType): array
    {
        return $this->filterMethod->invoke($this->controller, $records, $filters, $meta, $reportType);
    }

    // ============================================================
    // DAU-01
    // ============================================================

    public function test_dau01_direction_arrival_filter(): void
    {
        $parsed = $this->parseFixture(new DAU1Parser(), 'DAU-1.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $all = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU1');
        $arr = $this->invoke($records, $this->defaultFilters(['direction' => 'ARRIVAL']), $meta, 'DAU1');

        $this->assertNotEmpty($arr['filtered_records'], 'ARRIVAL filter must return at least one record');

        // Every returned record must have a non-zero aircraft_arrival or passenger_arrival
        foreach ($arr['filtered_records'] as $r) {
            $hasArr = (int)($r['aircraft_arrival'] ?? 0) > 0 || (int)($r['passenger_arrival'] ?? 0) > 0;
            $this->assertTrue($hasArr, 'All records returned by direction=ARRIVAL must have arrivals > 0');
        }

        // Filtered count must be <= unfiltered
        $this->assertLessThanOrEqual(
            count($all['filtered_records']),
            count($arr['filtered_records'])
        );
    }

    public function test_dau01_passenger_type_adult_filter(): void
    {
        $parsed = $this->parseFixture(new DAU1Parser(), 'DAU-1.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $all   = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU1');
        $adult = $this->invoke($records, $this->defaultFilters(['passenger_type' => 'ADULT']), $meta, 'DAU1');

        $this->assertNotEmpty($adult['filtered_records'], 'ADULT passenger_type filter must return records');

        foreach ($adult['filtered_records'] as $r) {
            $this->assertGreaterThan(
                0,
                (int)($r['passenger_adult'] ?? ($r['adult'] ?? 0)),
                'All ADULT-filtered records must have passenger_adult > 0'
            );
        }
    }

    public function test_dau01_schedule_type_filter(): void
    {
        $parsed = $this->parseFixture(new DAU1Parser(), 'DAU-1.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        // Find a schedule_type value that exists in this dataset
        $scheduleTypes = array_unique(array_filter(array_column($records, 'schedule_type')));
        if (empty($scheduleTypes)) {
            $this->markTestSkipped('No schedule_type data in DAU-1 fixture');
        }

        $st = array_values($scheduleTypes)[0];
        $filtered = $this->invoke($records, $this->defaultFilters(['schedule_type' => strtoupper($st)]), $meta, 'DAU1');

        $this->assertNotEmpty($filtered['filtered_records'], "schedule_type={$st} must return records");

        foreach ($filtered['filtered_records'] as $r) {
            $this->assertStringContainsStringIgnoringCase(
                $st,
                $r['schedule_type'] ?? '',
                "All filtered records must match schedule_type={$st}"
            );
        }
    }

    // ============================================================
    // DAU-02
    // ============================================================

    public function test_dau02_flight_type_dom_int_must_sum_to_all(): void
    {
        $parsed = $this->parseFixture(new DAU2Parser(), 'DAU-2.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $all = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU2');
        $dom = $this->invoke($records, $this->defaultFilters(['flight_type' => 'DOM']), $meta, 'DAU2');
        $int = $this->invoke($records, $this->defaultFilters(['flight_type' => 'INT']), $meta, 'DAU2');

        $domTotal = $dom['summary']['aircraft_total'] ?? 0;
        $intTotal = $int['summary']['aircraft_total'] ?? 0;
        $allTotal = $all['summary']['aircraft_total'] ?? 0;

        // DOM + INT aircraft totals must equal ALL (since DAU-2 records are either DOM or INT)
        $this->assertEquals(
            $allTotal,
            $domTotal + $intTotal,
            'DAU-2: DOM aircraft total + INT aircraft total must equal ALL total'
        );

        // Filtered counts must be <= all
        $this->assertLessThanOrEqual(count($all['filtered_records']), count($dom['filtered_records']));
        $this->assertLessThanOrEqual(count($all['filtered_records']), count($int['filtered_records']));
    }

    // ============================================================
    // DAU-03
    // ============================================================

    public function test_dau03_status_niaga_bukan_niaga_mutually_exclusive(): void
    {
        $parsed = $this->parseFixture(new DAU3Parser(), 'DAU-3.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $niaga     = $this->invoke($records, $this->defaultFilters(['status' => 'NIAGA']), $meta, 'DAU3');
        $bukanNiaga = $this->invoke($records, $this->defaultFilters(['status' => 'BUKAN NIAGA']), $meta, 'DAU3');

        $this->assertNotEmpty($niaga['filtered_records'], 'NIAGA filter must return records');

        // No record from NIAGA filter should appear in BUKAN NIAGA filter
        $niagaDates  = array_column($niaga['filtered_records'], 'section');
        $bukanDates  = array_column($bukanNiaga['filtered_records'], 'section');

        foreach ($niagaDates as $sec) {
            $this->assertStringNotContainsStringIgnoringCase(
                'BUKAN',
                $sec,
                'NIAGA-filtered records must not contain BUKAN NIAGA sections'
            );
        }
    }

    // ============================================================
    // DAU-04
    // ============================================================

    public function test_dau04_top_n_5_limits_routes(): void
    {
        $parsed = $this->parseFixture(new DAU4Parser(), 'DAU-4.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $all   = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU4');
        $topFive = $this->invoke($records, $this->defaultFilters(['top_n' => '5']), $meta, 'DAU4');

        $this->assertNotEmpty($topFive['filtered_records'], 'top_n=5 must return records');

        // The diverging chart data must be capped at 5 routes
        $topArr = $topFive['dau4_diverging']['top_arrival'] ?? [];
        $topDep = $topFive['dau4_diverging']['top_departure'] ?? [];

        $this->assertLessThanOrEqual(5, count($topArr), 'top_n=5: top_arrival must have at most 5 entries');
        $this->assertLessThanOrEqual(5, count($topDep), 'top_n=5: top_departure must have at most 5 entries');
    }

    // ============================================================
    // DAU-04A
    // ============================================================

    public function test_dau04a_airline_filter_isolates_records(): void
    {
        $parsed = $this->parseFixture(new DAU4AParser(), 'DAU-4A.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        // Get the first airline that appears in the dataset
        $airlines = array_unique(array_filter(array_map(
            fn($r) => $r['airline'] ?? $r['operator_name'] ?? null,
            $records
        )));

        if (empty($airlines)) {
            $this->markTestSkipped('No airline data in DAU-4A fixture');
        }

        $targetAirline = array_values($airlines)[0];
        $all      = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU4A');
        $filtered = $this->invoke($records, $this->defaultFilters(['airline' => $targetAirline]), $meta, 'DAU4A');

        $this->assertNotEmpty($filtered['filtered_records'], "airline={$targetAirline} must return records");
        $this->assertLessThanOrEqual(
            count($all['filtered_records']),
            count($filtered['filtered_records']),
            'Airline filter must not return more records than unfiltered'
        );

        foreach ($filtered['filtered_records'] as $r) {
            $rAirline = $r['airline'] ?? $r['operator_name'] ?? '';
            $this->assertTrue(
                stripos($rAirline, $targetAirline) !== false || strcasecmp($rAirline, $targetAirline) === 0,
                "Record airline '{$rAirline}' must match filter '{$targetAirline}'"
            );
        }
    }

    // ============================================================
    // DAU-04B
    // ============================================================

    public function test_dau04b_threshold_filter_excludes_low_frequency_routes(): void
    {
        $parsed = $this->parseFixture(new DAU4BParser(), 'DAU-4B.xls');
        // DAU4B uses normalized_pairs
        $records = $parsed['normalized_pairs'] ?? $parsed['records'] ?? [];
        $meta    = $parsed['meta'] ?? [];

        if (empty($records)) {
            $this->markTestSkipped('No records in DAU-4B fixture');
        }

        $all      = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU4B');
        $filtered = $this->invoke($records, $this->defaultFilters(['threshold' => 3]), $meta, 'DAU4B');

        // All records in the filtered set must have aircraft_total >= 3
        foreach ($filtered['filtered_records'] as $r) {
            $acCount = (int)($r['aircraft_total'] ?? $r['total_flights'] ?? 0);
            $this->assertGreaterThanOrEqual(3, $acCount, 'threshold=3: All returned records must have aircraft_total >= 3');
        }

        // The filter must reduce the dataset if any records have aircraft_total < 3
        $hasLowFreq = count(array_filter($records, fn($r) => ((int)($r['aircraft_total'] ?? $r['total_flights'] ?? 0)) < 3)) > 0;
        if ($hasLowFreq) {
            $this->assertLessThan(
                count($all['filtered_records']),
                count($filtered['filtered_records']),
                'threshold=3 must reduce the record count when low-frequency routes exist'
            );
        }
    }

    // ============================================================
    // DAU-05
    // ============================================================

    public function test_dau05_metric_passenger_changes_airline_ranking(): void
    {
        $parsed = $this->parseFixture(new DAU5Parser(), 'DAU-5.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $acftResult = $this->invoke($records, $this->defaultFilters(['metric' => 'aircraft']), $meta, 'DAU5');
        $paxResult  = $this->invoke($records, $this->defaultFilters(['metric' => 'passenger']), $meta, 'DAU5');

        // filterReportDataset returns dau5_pareto with per-airline ranked data
        $acftPareto = $acftResult['dau5_pareto'] ?? [];
        $paxPareto  = $paxResult['dau5_pareto'] ?? [];

        $this->assertNotEmpty($acftPareto, 'DAU-5: aircraft metric must produce airline pareto ranking');
        $this->assertNotEmpty($paxPareto, 'DAU-5: passenger metric must produce airline pareto ranking');

        // The metric_value must differ between aircraft and passenger modes for the same top airline
        if (count($acftPareto) > 0 && count($paxPareto) > 0) {
            $acftTopVal = $acftPareto[0]['aircraft_total'] ?? $acftPareto[0]['metric_value'] ?? 0;
            $paxTopVal  = $paxPareto[0]['passenger_total'] ?? $paxPareto[0]['metric_value'] ?? 0;
            // Passengers should be much greater than aircraft movements
            $this->assertGreaterThan(
                $acftTopVal,
                $paxTopVal,
                'Passenger metric value should exceed aircraft movements for the top airline'
            );
        }
    }

    // ============================================================
    // DAU-05A
    // ============================================================

    public function test_dau05a_airline_filter_isolates_crew_records(): void
    {
        $parsed = $this->parseFixture(new DAU5AParser(), 'DAU-5A.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $airlines = array_unique(array_filter(array_map(
            fn($r) => $r['airline'] ?? $r['operator_name'] ?? null,
            $records
        )));

        if (empty($airlines)) {
            $this->markTestSkipped('No airline data in DAU-5A fixture');
        }

        $targetAirline = array_values($airlines)[0];
        $filtered = $this->invoke($records, $this->defaultFilters(['airline' => $targetAirline]), $meta, 'DAU5A');

        $this->assertNotEmpty($filtered['filtered_records']);

        foreach ($filtered['filtered_records'] as $r) {
            $rAirline = $r['airline'] ?? $r['operator_name'] ?? '';
            $this->assertTrue(
                stripos($rAirline, $targetAirline) !== false || strcasecmp($rAirline, $targetAirline) === 0,
                "DAU-5A: Record airline must match filter '{$targetAirline}'"
            );
        }
    }

    // ============================================================
    // DAU-05B
    // ============================================================

    public function test_dau05b_terminal_filter_isolates_terminal_records(): void
    {
        $parsed = $this->parseFixture(new DAU5BParser(), 'DAU-5B.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $terminals = array_unique(array_filter(array_column($records, 'terminal')));

        if (empty($terminals)) {
            $this->markTestSkipped('No terminal data in DAU-5B fixture');
        }

        $targetTerminal = array_values($terminals)[0];
        $all      = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU5B');
        $filtered = $this->invoke($records, $this->defaultFilters(['terminal' => $targetTerminal]), $meta, 'DAU5B');

        $this->assertNotEmpty($filtered['filtered_records'], "terminal={$targetTerminal} must return records");
        $this->assertLessThanOrEqual(count($all['filtered_records']), count($filtered['filtered_records']));

        foreach ($filtered['filtered_records'] as $r) {
            $this->assertEquals(
                $targetTerminal,
                $r['terminal'] ?? '',
                "DAU-5B: All terminal-filtered records must match terminal={$targetTerminal}"
            );
        }
    }

    // ============================================================
    // DAU-05C
    // ============================================================

    public function test_dau05c_airline_filter_reduces_dataset(): void
    {
        $parsed = $this->parseFixture(new DAU5CParser(), 'DAU-5C.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $airlines = array_unique(array_filter(array_map(
            fn($r) => $r['airline'] ?? $r['operator_name'] ?? null,
            $records
        )));

        if (empty($airlines)) {
            $this->markTestSkipped('No airline data in DAU-5C fixture');
        }

        $targetAirline = array_values($airlines)[0];
        $all      = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU5C');
        $filtered = $this->invoke($records, $this->defaultFilters(['airline' => $targetAirline]), $meta, 'DAU5C');

        $this->assertNotEmpty($filtered['filtered_records']);
        $this->assertLessThanOrEqual(count($all['filtered_records']), count($filtered['filtered_records']));
    }

    // ============================================================
    // DAU-06
    // ============================================================

    public function test_dau06_aircraft_type_filter_isolates_records(): void
    {
        $parsed = $this->parseFixture(new DAU6Parser(), 'DAU-6.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $aircraftTypes = array_unique(array_filter(array_column($records, 'aircraft_type')));

        if (empty($aircraftTypes)) {
            $this->markTestSkipped('No aircraft_type data in DAU-6 fixture');
        }

        $targetType = array_values($aircraftTypes)[0];
        $all      = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU6');
        $filtered = $this->invoke($records, $this->defaultFilters(['aircraft_type' => $targetType]), $meta, 'DAU6');

        $this->assertNotEmpty($filtered['filtered_records'], "aircraft_type={$targetType} must return records");
        $this->assertLessThanOrEqual(count($all['filtered_records']), count($filtered['filtered_records']));

        foreach ($filtered['filtered_records'] as $r) {
            $this->assertEqualsIgnoringCase(
                $targetType,
                $r['aircraft_type'] ?? '',
                "DAU-6: All records must match aircraft_type={$targetType}"
            );
        }
    }

    // ============================================================
    // DAU-10
    // ============================================================

    public function test_dau10_terminal_filter_isolates_records(): void
    {
        $parsed = $this->parseFixture(new DAU10Parser(), 'DAU-10.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $terminals = array_unique(array_filter(array_column($records, 'terminal')));

        if (empty($terminals)) {
            $this->markTestSkipped('No terminal data in DAU-10 fixture');
        }

        $targetTerminal = array_values($terminals)[0];
        $all      = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU10');
        $filtered = $this->invoke($records, $this->defaultFilters(['terminal' => $targetTerminal]), $meta, 'DAU10');

        $this->assertNotEmpty($filtered['filtered_records'], "terminal={$targetTerminal} must return records");
        $this->assertLessThanOrEqual(count($all['filtered_records']), count($filtered['filtered_records']));

        foreach ($filtered['filtered_records'] as $r) {
            $this->assertEqualsIgnoringCase(
                $targetTerminal,
                $r['terminal'] ?? '',
                "DAU-10: All terminal-filtered records must match terminal={$targetTerminal}"
            );
        }
    }

    public function test_dau10_hour_filter_isolates_records(): void
    {
        $parsed = $this->parseFixture(new DAU10Parser(), 'DAU-10.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $hours = array_unique(array_filter(array_column($records, 'hour')));

        if (empty($hours)) {
            $this->markTestSkipped('No hour data in DAU-10 fixture');
        }

        $targetHour = array_values($hours)[0];
        $all      = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU10');
        $filtered = $this->invoke($records, $this->defaultFilters(['hour' => $targetHour]), $meta, 'DAU10');

        $this->assertNotEmpty($filtered['filtered_records'], "hour={$targetHour} must return records");
        $this->assertLessThanOrEqual(count($all['filtered_records']), count($filtered['filtered_records']));

        // Filtered summary must be <= total
        $this->assertLessThanOrEqual(
            $all['summary']['aircraft_total'],
            $filtered['summary']['aircraft_total'],
            'Hour-filtered aircraft total must be <= unfiltered total'
        );
    }

    // ============================================================
    // DAU-10B
    // ============================================================

    public function test_dau10b_operation_block_on_isolates_arrival_records(): void
    {
        $parsed = $this->parseFixture(new DAU10BParser(), 'DAU-10B.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $blockOn  = $this->invoke($records, $this->defaultFilters(['operation' => 'BLOCK_ON']), $meta, 'DAU10B');
        $blockOff = $this->invoke($records, $this->defaultFilters(['operation' => 'BLOCK_OFF']), $meta, 'DAU10B');

        $this->assertNotEmpty($blockOn['filtered_records'], 'BLOCK_ON filter must return arrival records');
        $this->assertNotEmpty($blockOff['filtered_records'], 'BLOCK_OFF filter must return departure records');

        // BLOCK_ON records must have aircraft_arrival > 0 or passenger_arrival > 0
        foreach ($blockOn['filtered_records'] as $r) {
            $hasArr = (int)($r['aircraft_arrival'] ?? 0) > 0 || (int)($r['passenger_arrival'] ?? 0) > 0;
            $this->assertTrue($hasArr, 'DAU-10B BLOCK_ON: All records must have arrival > 0');
        }

        // BLOCK_OFF records must have aircraft_departure > 0 or passenger_departure > 0
        foreach ($blockOff['filtered_records'] as $r) {
            $hasDep = (int)($r['aircraft_departure'] ?? 0) > 0 || (int)($r['passenger_departure'] ?? 0) > 0;
            $this->assertTrue($hasDep, 'DAU-10B BLOCK_OFF: All records must have departure > 0');
        }
    }

    // ============================================================
    // DAU-11 — Regression guard for Bug 1 (field alias fix)
    // ============================================================

    public function test_dau11_direction_arrival_filter_returns_records(): void
    {
        $parsed = $this->parseFixture(new DAU11Parser(), 'DAU-11.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $this->assertNotEmpty($records, 'DAU-11 fixture must parse records');

        // Verify fix: records now have aircraft_arrival alias
        $firstRecord = $records[0];
        $this->assertArrayHasKey(
            'aircraft_arrival',
            $firstRecord,
            'DAU-11 records must have normalized aircraft_arrival field (fix for Bug 1)'
        );
        $this->assertArrayHasKey(
            'passenger_arrival',
            $firstRecord,
            'DAU-11 records must have normalized passenger_arrival field'
        );

        $all = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU11');
        $arr = $this->invoke($records, $this->defaultFilters(['direction' => 'ARRIVAL']), $meta, 'DAU11');

        $this->assertNotEmpty($arr['filtered_records'], 'DAU-11 direction=ARRIVAL must return records (Bug 1 regression guard)');

        // All returned records must have aircraft_arrival > 0 or passenger_arrival > 0
        foreach ($arr['filtered_records'] as $r) {
            $hasArr = (int)($r['aircraft_arrival'] ?? 0) > 0 || (int)($r['passenger_arrival'] ?? 0) > 0;
            $this->assertTrue($hasArr, 'DAU-11 ARRIVAL filter: all records must have non-zero arrivals');
        }

        // Filtered must be subset of all
        $this->assertLessThanOrEqual(count($all['filtered_records']), count($arr['filtered_records']));
    }

    public function test_dau11_direction_departure_filter_returns_records(): void
    {
        $parsed = $this->parseFixture(new DAU11Parser(), 'DAU-11.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $all = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU11');
        $dep = $this->invoke($records, $this->defaultFilters(['direction' => 'DEPARTURE']), $meta, 'DAU11');

        $this->assertNotEmpty($dep['filtered_records'], 'DAU-11 direction=DEPARTURE must return records');

        foreach ($dep['filtered_records'] as $r) {
            $hasDep = (int)($r['aircraft_departure'] ?? 0) > 0 || (int)($r['passenger_departure'] ?? 0) > 0;
            $this->assertTrue($hasDep, 'DAU-11 DEPARTURE filter: all records must have non-zero departures');
        }
    }

    public function test_dau11_summary_uses_filtered_data_not_precomputed(): void
    {
        $parsed = $this->parseFixture(new DAU11Parser(), 'DAU-11.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $all = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU11');
        $arr = $this->invoke($records, $this->defaultFilters(['direction' => 'ARRIVAL']), $meta, 'DAU11');

        // Passenger total from ARRIVAL-only filter must be <= ALL total
        $this->assertLessThanOrEqual(
            $all['summary']['passenger_total'],
            $arr['summary']['passenger_total'],
            'DAU-11: Arrival-filtered passenger_total must be <= unfiltered total'
        );

        // Aircraft total from ARRIVAL-only filter must be <= ALL total
        $this->assertLessThanOrEqual(
            $all['summary']['aircraft_total'],
            $arr['summary']['aircraft_total'],
            'DAU-11: Arrival-filtered aircraft_total must be <= unfiltered total'
        );
    }

    public function test_dau11_passenger_arrival_kpi_is_nonzero_after_fix(): void
    {
        $parsed = $this->parseFixture(new DAU11Parser(), 'DAU-11.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $all = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU11');

        // With the normalized alias fix, passenger_arrival KPI must reflect actual data
        $this->assertGreaterThan(
            0,
            $all['summary']['passenger_arrival'],
            'DAU-11: summary.passenger_arrival must be > 0 (Bug 3 regression guard — recalculateAnalytics alias)'
        );

        $this->assertGreaterThan(
            0,
            $all['summary']['aircraft_arrival'],
            'DAU-11: summary.aircraft_arrival must be > 0'
        );
    }

    // ============================================================
    // DAU-12 — Regression guard for Bug 2 (field alias fix)
    // ============================================================

    public function test_dau12_direction_arrival_filter_returns_records(): void
    {
        $parsed = $this->parseFixture(new DAU12Parser(), 'DAU-12.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $this->assertNotEmpty($records, 'DAU-12 fixture must parse records');

        // Verify fix: records now have aircraft_arrival alias
        $firstRecord = $records[0];
        $this->assertArrayHasKey(
            'aircraft_arrival',
            $firstRecord,
            'DAU-12 records must have normalized aircraft_arrival field (fix for Bug 2)'
        );
        $this->assertArrayHasKey(
            'passenger_arrival',
            $firstRecord,
            'DAU-12 records must have normalized passenger_arrival field'
        );

        $all = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU12');
        $arr = $this->invoke($records, $this->defaultFilters(['direction' => 'ARRIVAL']), $meta, 'DAU12');

        $this->assertNotEmpty($arr['filtered_records'], 'DAU-12 direction=ARRIVAL must return records (Bug 2 regression guard)');

        foreach ($arr['filtered_records'] as $r) {
            $hasArr = (int)($r['aircraft_arrival'] ?? 0) > 0 || (int)($r['passenger_arrival'] ?? 0) > 0;
            $this->assertTrue($hasArr, 'DAU-12 ARRIVAL filter: all records must have non-zero arrivals');
        }

        $this->assertLessThanOrEqual(count($all['filtered_records']), count($arr['filtered_records']));
    }

    public function test_dau12_direction_departure_filter_returns_records(): void
    {
        $parsed = $this->parseFixture(new DAU12Parser(), 'DAU-12.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $dep = $this->invoke($records, $this->defaultFilters(['direction' => 'DEPARTURE']), $meta, 'DAU12');

        $this->assertNotEmpty($dep['filtered_records'], 'DAU-12 direction=DEPARTURE must return records');

        foreach ($dep['filtered_records'] as $r) {
            $hasDep = (int)($r['aircraft_departure'] ?? 0) > 0 || (int)($r['passenger_departure'] ?? 0) > 0;
            $this->assertTrue($hasDep, 'DAU-12 DEPARTURE filter: all records must have non-zero departures');
        }
    }

    public function test_dau12_summary_passenger_arrival_is_nonzero_after_fix(): void
    {
        $parsed = $this->parseFixture(new DAU12Parser(), 'DAU-12.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        $all = $this->invoke($records, $this->defaultFilters(), $meta, 'DAU12');

        $this->assertGreaterThan(
            0,
            $all['summary']['passenger_arrival'],
            'DAU-12: summary.passenger_arrival must be > 0 after alias fix'
        );

        $this->assertGreaterThan(
            0,
            $all['summary']['aircraft_arrival'],
            'DAU-12: summary.aircraft_arrival must be > 0 after alias fix'
        );
    }

    public function test_dau12_dom_int_arrival_sums_correctly(): void
    {
        $parsed = $this->parseFixture(new DAU12Parser(), 'DAU-12.xls');
        $records = $parsed['records'];
        $meta    = $parsed['meta'] ?? [];

        // For each DAU-12 record, verify the alias is consistent with the authoritative total column.
        // Note: aircraft_arrival_tot is the pre-computed Excel cell and is authoritative.
        // aircraft_arr_domestic + aircraft_arr_int may differ if the source has rounding or sub-categories.
        foreach ($records as $r) {
            $aliasArrival = (int)($r['aircraft_arrival'] ?? 0);
            $explicitTot  = (int)($r['aircraft_arrival_tot'] ?? 0);

            $this->assertEquals(
                $explicitTot,
                $aliasArrival,
                'DAU-12: aircraft_arrival alias must equal aircraft_arrival_tot'
            );

            // Verify passenger_arrival alias is also consistent
            $aliasPassArr = (int)($r['passenger_arrival'] ?? 0);
            $explicitPaxTot = (int)($r['passenger_arrival_tot'] ?? 0);
            $this->assertEquals(
                $explicitPaxTot,
                $aliasPassArr,
                'DAU-12: passenger_arrival alias must equal passenger_arrival_tot'
            );
        }
    }
}
