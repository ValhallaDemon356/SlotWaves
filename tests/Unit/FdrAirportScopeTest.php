<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;

class FdrAirportScopeTest extends TestCase
{
    protected string $fixturePath;
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportFilter $filter;
    protected FlightDailyReportAnalytics $analytics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(FlightDailyReportParser::class);
        $this->filter = app(FlightDailyReportFilter::class);
        $this->analytics = app(FlightDailyReportAnalytics::class);

        $f1 = storage_path('app/templates/DAU_1790819792.xls');
        $f2 = storage_path('app/templates/DAU_1790823756.xls');
        $this->fixturePath = file_exists($f1) ? $f1 : $f2;
    }

    /**
     * Test 1: Authoritative Report Airport is derived strictly from BRANCH_CODE.
     */
    public function test_authoritative_report_airport_derived_from_branch_code(): void
    {
        $parsed = $this->parser->parse($this->fixturePath);
        $meta = $parsed['meta'];

        $this->assertSame('HLP', $meta['report_airport']);
        $this->assertSame('HLP', $meta['airport']);
        $this->assertSame('QG', $meta['operator']);
        $this->assertTrue($meta['is_single_airport']);
        $this->assertSame(['HLP'], $meta['report_airports']);
        $this->assertStringContainsString('Halim Perdanakusuma', $meta['airport_name']);
    }

    /**
     * Test 2: Route endpoints (CITY 1 / CITY 2) do NOT leak into Report Airport options.
     */
    public function test_city1_and_city2_do_not_leak_into_airport_options(): void
    {
        $parsed = $this->parser->parse($this->fixturePath);
        $classified = $this->parser->classifyRows($parsed['records']);
        $movements = $classified['movement_records'];

        $reportAirports = [];
        $routeAirports = [];

        foreach ($movements as $m) {
            $rap = $m['report_airport'] ?? '';
            if ($rap) $reportAirports[$rap] = true;
            if (!empty($m['city_1'])) $routeAirports[$m['city_1']] = true;
            if (!empty($m['city_2'])) $routeAirports[$m['city_2']] = true;
        }

        // Report airports must ONLY be HLP
        $this->assertSame(['HLP'], array_keys($reportAirports));

        // Route airports contain many destinations / origins (DPS, JOG, MLG, SUB, CGK, etc.)
        $this->assertGreaterThan(5, count($routeAirports));
        $this->assertArrayHasKey('DPS', $routeAirports);
        $this->assertArrayHasKey('JOG', $routeAirports);
        $this->assertArrayHasKey('MLG', $routeAirports);
        $this->assertArrayHasKey('SUB', $routeAirports);

        // Crucial: Route destinations must NEVER be considered Report Airports
        $this->assertArrayNotHasKey('DPS', $reportAirports);
        $this->assertArrayNotHasKey('JOG', $reportAirports);
        $this->assertArrayNotHasKey('MLG', $reportAirports);
        $this->assertArrayNotHasKey('SUB', $reportAirports);
    }

    /**
     * Test 3: Flight routes remain intact and complete (CITY 1 → CITY 2).
     */
    public function test_flight_routes_remain_intact_and_complete(): void
    {
        $parsed = $this->parser->parse($this->fixturePath);
        $classified = $this->parser->classifyRows($parsed['records']);
        $movements = $classified['movement_records'];

        $routesFound = [];
        foreach ($movements as $m) {
            $routesFound[$m['route']] = ($routesFound[$m['route']] ?? 0) + 1;
            // Every record must have separate origin and destination
            $this->assertNotEmpty($m['origin_airport']);
            $this->assertNotEmpty($m['destination_airport']);
            $this->assertSame('HLP', $m['report_airport']);
            $this->assertStringContainsString('→', $m['route']);
        }

        // Ensure key expected routes from the prompt exist
        $this->assertArrayHasKey('HLP → DPS', $routesFound);
        $this->assertArrayHasKey('DPS → HLP', $routesFound);
        $this->assertArrayHasKey('HLP → JOG', $routesFound);
        $this->assertArrayHasKey('JOG → HLP', $routesFound);
        $this->assertArrayHasKey('HLP → MLG', $routesFound);
        $this->assertArrayHasKey('MLG → HLP', $routesFound);
        $this->assertArrayHasKey('HLP → SUB', $routesFound);
        $this->assertArrayHasKey('SUB → HLP', $routesFound);
    }

    /**
     * Test 4: Filter semantics with Airport = HLP and LEG = ALL / ARR / DEP.
     */
    public function test_filter_semantics_leg_all_arr_dep(): void
    {
        $parsed = $this->parser->parse($this->fixturePath);
        $classified = $this->parser->classifyRows($parsed['records']);
        $movements = $classified['movement_records'];
        $meta = $parsed['meta'];

        // ALL movements for report_airport HLP
        $resAll = $this->filter->apply($movements, [
            'date_scope' => 'ALL_PERIOD',
            'airport'    => 'HLP',
            'leg'        => 'ALL',
        ], $meta);
        $this->assertCount(2857, $resAll['records']);

        // ARR movements for report_airport HLP
        $resArr = $this->filter->apply($movements, [
            'date_scope' => 'ALL_PERIOD',
            'airport'    => 'HLP',
            'leg'        => 'ARR',
        ], $meta);
        $this->assertCount(1429, $resArr['records']);
        foreach ($resArr['records'] as $r) {
            $this->assertSame('ARRIVAL', $r['direction']);
            $this->assertSame('HLP', $r['report_airport']);
            $this->assertSame('HLP', $r['destination_airport']);
        }

        // DEP movements for report_airport HLP
        $resDep = $this->filter->apply($movements, [
            'date_scope' => 'ALL_PERIOD',
            'airport'    => 'HLP',
            'leg'        => 'DEP',
        ], $meta);
        $this->assertCount(1428, $resDep['records']);
        foreach ($resDep['records'] as $r) {
            $this->assertSame('DEPARTURE', $r['direction']);
            $this->assertSame('HLP', $r['report_airport']);
            $this->assertSame('HLP', $r['origin_airport']);
        }

        // Reconcile: ARR + DEP must equal ALL
        $this->assertSame(count($resAll['records']), count($resArr['records']) + count($resDep['records']));
    }

    /**
     * Test 5: Summary row is classified as SUMMARY, PAX ALL is excluded from operators.
     */
    public function test_summary_row_and_operator_classification(): void
    {
        $parsed = $this->parser->parse($this->fixturePath);
        $this->assertCount(2858, $parsed['records']);

        $classified = $this->parser->classifyRows($parsed['records']);
        $this->assertCount(2857, $classified['movement_records']);
        $this->assertCount(1, $classified['summary_records']);

        $summaryRow = $classified['summary_records'][0];
        $this->assertSame('SUMMARY', $summaryRow['row_type']);
        $this->assertNotSame('PAX ALL', $summaryRow['air_line']);

        // Check operators from movement records
        $operators = [];
        foreach ($classified['movement_records'] as $m) {
            $operators[$m['air_line']] = true;
        }
        $this->assertArrayNotHasKey('PAX ALL', $operators);
    }
}
