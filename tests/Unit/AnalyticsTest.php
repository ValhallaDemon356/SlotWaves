<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;

class AnalyticsTest extends TestCase
{
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportAnalytics $analytics;
    protected array $records;
    protected array $meta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->analytics = new FlightDailyReportAnalytics();
        $path = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        $parsed = $this->parser->parse($path);
        $this->records = $parsed['records'];
        $this->meta = $parsed['meta'];
    }

    public function test_it_computes_top_kpi_cards(): void
    {
        $kpis = $this->analytics->computeTopKpis($this->records);

        $this->assertArrayHasKey('total_flights', $kpis);
        $this->assertArrayHasKey('arrivals', $kpis);
        $this->assertArrayHasKey('departures', $kpis);
        $this->assertArrayHasKey('total_passengers', $kpis);
        $this->assertArrayHasKey('avg_load_factor', $kpis);
        $this->assertArrayHasKey('cargo_kg', $kpis);
        $this->assertArrayHasKey('baggage_kg', $kpis);
        $this->assertArrayHasKey('irregularities', $kpis);

        $this->assertEquals(count($this->records), $kpis['total_flights']);
        $this->assertEquals($kpis['total_flights'], $kpis['arrivals'] + $kpis['departures']);
        $this->assertGreaterThan(0, $kpis['total_passengers']);
        $this->assertStringContainsString('%', $kpis['avg_load_factor']);
    }

    public function test_load_factor_never_produces_nan_or_infinity(): void
    {
        // Custom dataset with zero capacity
        $emptyCapRecords = [
            [
                'direction'   => 'ARRIVAL',
                'cap'         => 0,
                'load'        => 0,
                'adult'       => 0,
                'child'       => 0,
                'infant'      => 0,
                'cargo_kg'    => 0,
                'baggage_kg'  => 0,
                'pos_kg'      => 0,
                'divert'      => 0,
                'miss'        => 0,
                'sched_type'  => 'SCHED',
            ],
        ];

        $kpis = $this->analytics->computeTopKpis($emptyCapRecords);

        $this->assertSame('N/A', $kpis['avg_load_factor']);
        $this->assertNull($kpis['avg_load_factor_num']);
    }

    public function test_it_computes_schedule_vs_realization(): void
    {
        $schedVsReal = $this->analytics->computeScheduleVsRealization($this->records);

        $this->assertArrayHasKey('evaluated_flights', $schedVsReal);
        $this->assertArrayHasKey('on_time_percentage', $schedVsReal);
        $this->assertArrayHasKey('avg_delay_minutes', $schedVsReal);
        $this->assertGreaterThan(0, $schedVsReal['evaluated_flights']);
    }

    public function test_it_computes_passenger_analytics_and_daily_trend(): void
    {
        $pax = $this->analytics->computePassengerAnalytics($this->records);

        $this->assertArrayHasKey('composition', $pax);
        $this->assertArrayHasKey('daily_trend', $pax);
        $this->assertArrayHasKey('adult', $pax['composition']);
        $this->assertArrayHasKey('child', $pax['composition']);
        $this->assertArrayHasKey('transit', $pax['composition']);
        $this->assertNotEmpty($pax['daily_trend']);
    }

    public function test_it_computes_airline_and_route_performance(): void
    {
        $perf = $this->analytics->computeAirlineRoutePerformance($this->records);

        $this->assertNotEmpty($perf['ranked_airlines']);
        $this->assertNotEmpty($perf['top_routes']);

        $firstAirline = $perf['ranked_airlines'][0];
        $this->assertArrayHasKey('airline', $firstAirline);
        $this->assertArrayHasKey('flights', $firstAirline);
        $this->assertArrayHasKey('avg_load_factor', $firstAirline);
    }

    public function test_it_computes_ground_ops(): void
    {
        $ground = $this->analytics->computeGroundOps($this->records);

        $this->assertNotEmpty($ground['stands']);
        $this->assertNotEmpty($ground['runways']);
        $this->assertArrayHasKey('irregularities', $ground);
    }

    public function test_it_computes_reconciliation_modes(): void
    {
        $res = $this->analytics->compute($this->records, $this->meta, ['report_mode' => 7]);

        $this->assertArrayHasKey('reconciliation_apps', $res);
        $apps = $res['reconciliation_apps'];
        $this->assertContains($apps['overall_status'], ['MATCH', 'MINOR GAP', 'MISMATCH']);
        $this->assertArrayHasKey('metrics', $apps);
        $this->assertArrayHasKey('flights', $apps['metrics']);
        $this->assertArrayHasKey('passengers', $apps['metrics']);
        $this->assertArrayHasKey('cargo', $apps['metrics']);

        $res8 = $this->analytics->compute($this->records, $this->meta, ['report_mode' => 8]);
        $edifly = $res8['reconciliation_edifly'];
        $this->assertContains($edifly['overall_status'], ['MATCH', 'MINOR GAP', 'MISMATCH']);
    }
}
