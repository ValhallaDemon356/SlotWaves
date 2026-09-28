<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\HourlyChartService;

class FdrDirectionalHourlyTest extends TestCase
{
    protected string $filePath;
    protected FlightDailyReportParser $parser;
    protected HourlyChartService $hourlyChartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->hourlyChartService = new HourlyChartService();
        $this->filePath = storage_path('app/templates/CGK FDR.xls');
    }

    public function test_hourly_sums_reconcile_without_double_counting()
    {
        $result = $this->parser->parse($this->filePath);
        $records = $result['records'];

        $charts = $this->hourlyChartService->buildHourlyCharts($records, 'CGK');
        $hourlyData = $charts['hourly_data'];

        $sumArrPlan = 0;
        $sumDepPlan = 0;
        $sumTotalPlan = 0;
        $sumArrReal = 0;
        $sumDepReal = 0;
        $sumTotalReal = 0;

        foreach ($hourlyData as $row) {
            $sumArrPlan += $row['arr_plan'];
            $sumDepPlan += $row['dep_plan'];
            $sumTotalPlan += $row['total_plan'];

            $sumArrReal += $row['arr_realized'];
            $sumDepReal += $row['dep_realized'];
            $sumTotalReal += $row['total_realized'];

            // For each hour, total_plan must strictly equal arr_plan + dep_plan (never plan + actual!)
            $this->assertEquals($row['arr_plan'] + $row['dep_plan'], $row['total_plan']);
            $this->assertEquals($row['arr_realized'] + $row['dep_realized'], $row['total_realized']);
        }

        // Part 33 assertions:
        $this->assertEquals(95, $sumArrPlan, "SUM(hourly_arrivals) must equal 95");
        $this->assertEquals(95, $sumDepPlan, "SUM(hourly_departures) must equal 95");
        $this->assertEquals(190, $sumTotalPlan, "SUM(hourly_total) must equal 190");

        // Part 34 assertions (165 realized flights):
        $this->assertEquals(82, $sumArrReal);
        $this->assertEquals(83, $sumDepReal);
        $this->assertEquals(165, $sumTotalReal, "SUM(hourly_realized) must equal 165 for realized flights");
    }

    public function test_chart_payloads_contain_clean_non_double_counted_series()
    {
        $result = $this->parser->parse($this->filePath);
        $charts = $this->hourlyChartService->buildHourlyCharts($result['records'], 'CGK');

        // Chart 1: Arrival-Departure Movement
        $c1 = $charts['chart1_movement'];
        $this->assertCount(3, $c1['datasets']); // Runway Capacity (line), Plan (bar), Irregular (bar)

        // Chart 2: Departure Movement
        $c2 = $charts['chart2_departure'];
        $depPlanSum = array_sum($c2['datasets'][0]['data']);
        $this->assertEquals(95, $depPlanSum, "Chart 2 departure plan series must sum to 95");

        // Chart 3: Arrival Movement
        $c3 = $charts['chart3_arrival'];
        $arrPlanSum = array_sum($c3['datasets'][0]['data']);
        $this->assertEquals(95, $arrPlanSum, "Chart 3 arrival plan series must sum to 95");
    }
}
