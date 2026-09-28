<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;

class FdrLoadFactorConsistencyTest extends TestCase
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

    public function test_load_factor_metrics_use_identical_membership_and_no_artificial_clamping()
    {
        $result = $this->parser->parse($this->filePath);
        $records = $result['records'];

        $kpis = $this->analytics->computeTopKpis($records);

        $totalPax = $kpis['passenger_movement']; // 25,627
        $totalCap = $kpis['total_capacity'];     // 35,763

        $this->assertEquals(25627, $totalPax);
        $this->assertEquals(35763, $totalCap);

        // Passenger Seat Utilization: 25,627 / 35,763 * 100 = 71.657... -> 71.7%
        $expectedPaxUtil = round(($totalPax / $totalCap) * 100, 1);
        $this->assertEquals(71.7, $expectedPaxUtil);
        $this->assertEquals('71.7%', $kpis['passenger_utilization']);

        // Capacity-weighted Load Factor from OASYS LOAD%
        $this->assertEquals('37.8%', $kpis['weighted_load_factor']);

        // Must not be suspicious > 100% or 125.6%
        $this->assertLessThanOrEqual(100.0, $kpis['passenger_utilization_num']);
        $this->assertLessThanOrEqual(100.0, $kpis['weighted_load_factor_num']);
    }
}
