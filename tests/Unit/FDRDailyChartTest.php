<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\HourlyChartService;

class FDRDailyChartTest extends TestCase
{
    protected FlightDailyReportFilter $filterService;
    protected HourlyChartService $hourlyChartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = new FlightDailyReportFilter();
        $this->hourlyChartService = new HourlyChartService();
    }

    public function test_daily_charts_contain_three_core_movement_charts_for_one_date(): void
    {
        $records = [
            // 15 Aug: 1 Arrival at 08:15 (Plan), 1 Departure at 08:45 (Irregular)
            [
                'flight_no'   => 'GA101',
                'flight_date' => '2026-08-15',
                'leg'         => 'ARR',
                'direction'   => 'ARRIVAL',
                'sibt'        => '08:15',
                'aibt'        => '08:15',
                'arr_sched'   => '08:15',
                'arr_actual'  => '08:15',
                'irregular'   => false,
            ],
            [
                'flight_no'   => 'GA102',
                'flight_date' => '2026-08-15',
                'leg'         => 'DEP',
                'direction'   => 'DEPARTURE',
                'sobt'        => '08:45',
                'aobt'        => '08:45',
                'dep_sched'   => '08:45',
                'dep_actual'  => '08:45',
                'irregular'   => true,
            ],
            // 16 Aug: 50 flights (should NOT appear in 15 Aug chart)
            [
                'flight_no'   => 'GA201',
                'flight_date' => '2026-08-16',
                'leg'         => 'ARR',
                'direction'   => 'ARRIVAL',
                'sibt'        => '08:15',
                'aibt'        => '08:15',
                'arr_sched'   => '08:15',
                'arr_actual'  => '08:15',
                'irregular'   => false,
            ],
        ];

        $filtered = $this->filterService->filterRecords($records, [
            'analysis_date' => '2026-08-15'
        ]);

        $this->assertCount(2, $filtered);

        $charts = $this->hourlyChartService->buildHourlyCharts($filtered, 'CGK');

        // Check 24 hours
        $this->assertCount(24, $charts['hours']);
        $this->assertEquals('00:00', $charts['hours'][0]);
        $this->assertEquals('23:00', $charts['hours'][23]);

        // Check Chart 1: Arrival-Departure
        $c1 = $charts['chart1_movement'];
        $this->assertEquals('ARRIVAL–DEPARTURE MOVEMENT (24 HOURS)', $c1['title']);
        $this->assertCount(3, $c1['datasets']); // Capacity, Plan, Irregular
        // At hour 08: 1 Plan, 1 Irregular
        $this->assertEquals(1, $c1['datasets'][1]['data'][8]); // Plan
        $this->assertEquals(1, $c1['datasets'][2]['data'][8]); // Irregular

        // Check Chart 2: Departure
        $c2 = $charts['chart2_departure'];
        $this->assertEquals('DEPARTURE MOVEMENT (24 HOURS)', $c2['title']);
        $this->assertCount(2, $c2['datasets']);
        $this->assertEquals(0, $c2['datasets'][0]['data'][8]); // Plan Dep
        $this->assertEquals(1, $c2['datasets'][1]['data'][8]); // Irregular Dep

        // Check Chart 3: Arrival
        $c3 = $charts['chart3_arrival'];
        $this->assertEquals('ARRIVAL MOVEMENT (24 HOURS)', $c3['title']);
        $this->assertCount(2, $c3['datasets']);
        $this->assertEquals(1, $c3['datasets'][0]['data'][8]); // Plan Arr
        $this->assertEquals(0, $c3['datasets'][1]['data'][8]); // Irregular Arr

        // Ensure hour 08 movement does NOT include the 16 Aug flight
        $this->assertEquals(2, $charts['hourly_data'][8]['total_realized']);
    }
}
