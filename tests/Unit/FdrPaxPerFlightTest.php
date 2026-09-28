<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;

class FdrPaxPerFlightTest extends TestCase
{
    protected string $filePath;
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportAnalytics $analytics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->analytics = new FlightDailyReportAnalytics();
        $this->filePath = storage_path('app/templates/CGK FDR.xls');
    }

    public function test_pax_per_flight_ratio_reconciles_to_134_point_9()
    {
        $result = $this->parser->parse($this->filePath);
        $records = $result['records'];

        $kpis = $this->analytics->computeTopKpis($records);

        // Required assertions from Part 23:
        // 25,627 / 190 = 134.8789... -> 134.9
        $this->assertEquals(134.9, $kpis['pax_per_flight']);
        $this->assertEquals('134.9 Pax / Flight', $kpis['pax_per_flight_display']);

        // Must NOT be 225 Pax / Flight
        $this->assertNotEquals(225.0, $kpis['pax_per_flight']);
        $this->assertNotEquals('225 Pax / Flight', $kpis['pax_per_flight_display']);
    }
}
