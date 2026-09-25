<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\HourlyChartService;

class FDRHourlyAggregationByDateTest extends TestCase
{
    protected FlightDailyReportFilter $filterService;
    protected HourlyChartService $hourlyChartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = new FlightDailyReportFilter();
        $this->hourlyChartService = new HourlyChartService();
    }

    public function test_hourly_aggregation_does_not_stack_monthly_dates(): void
    {
        // Monthly records across 3 days at 12:00
        $records = [];
        
        // 01 Aug 12:00 = 10 flights
        for ($i = 1; $i <= 10; $i++) {
            $records[] = [
                'flight_no'   => "GA10$i",
                'flight_date' => '2026-08-01',
                'leg'         => 'DEP',
                'direction'   => 'DEPARTURE',
                'sibt'        => null,
                'aibt'        => null,
                'sobt'        => '12:15',
                'aobt'        => '12:20',
                'dep_sched'   => '12:15',
                'dep_actual'  => '12:20',
                'arr_sched'   => null,
                'arr_actual'  => null,
                'irregular'   => false,
            ];
        }

        // 02 Aug 12:00 = 20 flights
        for ($i = 1; $i <= 20; $i++) {
            $records[] = [
                'flight_no'   => "GA20$i",
                'flight_date' => '2026-08-02',
                'leg'         => 'DEP',
                'direction'   => 'DEPARTURE',
                'sibt'        => null,
                'aibt'        => null,
                'sobt'        => '12:30',
                'aobt'        => '12:35',
                'dep_sched'   => '12:30',
                'dep_actual'  => '12:35',
                'arr_sched'   => null,
                'arr_actual'  => null,
                'irregular'   => false,
            ];
        }

        // 03 Aug 12:00 = 30 flights
        for ($i = 1; $i <= 30; $i++) {
            $records[] = [
                'flight_no'   => "GA30$i",
                'flight_date' => '2026-08-03',
                'leg'         => 'DEP',
                'direction'   => 'DEPARTURE',
                'sibt'        => null,
                'aibt'        => null,
                'sobt'        => '12:45',
                'aobt'        => '12:50',
                'dep_sched'   => '12:45',
                'dep_actual'  => '12:50',
                'arr_sched'   => null,
                'arr_actual'  => null,
                'irregular'   => false,
            ];
        }

        // Total raw records = 60
        $this->assertCount(60, $records);

        // Select: 03 Aug
        $filteredDay3 = $this->filterService->filterRecords($records, [
            'analysis_date' => '2026-08-03'
        ]);

        $this->assertCount(30, $filteredDay3);

        $charts = $this->hourlyChartService->buildHourlyCharts($filteredDay3, 'CGK');
        $hour12 = $charts['hourly_data'][12];

        // Expected: 12:00 = 30, NOT 60!
        $this->assertEquals(30, $hour12['total_realized']);
        $this->assertNotEquals(60, $hour12['total_realized']);
    }

    public function test_peak_hour_calculation_per_selected_day(): void
    {
        $records = [];

        // 15 Aug: 08:00 = 4, 09:00 = 8, 10:00 = 12
        $addDay15 = function ($hour, $count) use (&$records) {
            for ($i = 1; $i <= $count; $i++) {
                $records[] = [
                    'flight_no'   => "GA15_{$hour}_$i",
                    'flight_date' => '2026-08-15',
                    'leg'         => 'ARR',
                    'direction'   => 'ARRIVAL',
                    'sibt'        => sprintf('%02d:10', $hour),
                    'aibt'        => sprintf('%02d:15', $hour),
                    'arr_sched'   => sprintf('%02d:10', $hour),
                    'arr_actual'  => sprintf('%02d:15', $hour),
                    'irregular'   => false,
                ];
            }
        };
        $addDay15(8, 4);
        $addDay15(9, 8);
        $addDay15(10, 12);

        // 16 Aug: 08:00 = 20, 09:00 = 25, 10:00 = 30
        $addDay16 = function ($hour, $count) use (&$records) {
            for ($i = 1; $i <= $count; $i++) {
                $records[] = [
                    'flight_no'   => "GA16_{$hour}_$i",
                    'flight_date' => '2026-08-16',
                    'leg'         => 'ARR',
                    'direction'   => 'ARRIVAL',
                    'sibt'        => sprintf('%02d:10', $hour),
                    'aibt'        => sprintf('%02d:15', $hour),
                    'arr_sched'   => sprintf('%02d:10', $hour),
                    'arr_actual'  => sprintf('%02d:15', $hour),
                    'irregular'   => false,
                ];
            }
        };
        $addDay16(8, 20);
        $addDay16(9, 25);
        $addDay16(10, 30);

        // Select: 15 Aug
        $day15 = $this->filterService->filterRecords($records, ['analysis_date' => '2026-08-15']);
        $charts15 = $this->hourlyChartService->buildHourlyCharts($day15, 'CGK');

        $this->assertEquals(10, $charts15['peak_hour']['hour']);
        $this->assertEquals(12, $charts15['peak_hour']['movements']);
        $this->assertStringContainsString('10:00–10:59', $charts15['peak_hour']['display']);
        $this->assertStringContainsString('12 movements', $charts15['peak_hour']['display']);

        // Select: 16 Aug
        $day16 = $this->filterService->filterRecords($records, ['analysis_date' => '2026-08-16']);
        $charts16 = $this->hourlyChartService->buildHourlyCharts($day16, 'CGK');

        $this->assertEquals(10, $charts16['peak_hour']['hour']);
        $this->assertEquals(30, $charts16['peak_hour']['movements']);
        $this->assertStringContainsString('10:00–10:59', $charts16['peak_hour']['display']);
        $this->assertStringContainsString('30 movements', $charts16['peak_hour']['display']);
    }

    public function test_hourly_chart_always_has_all_24_hours_even_if_empty(): void
    {
        $charts = $this->hourlyChartService->buildHourlyCharts([], 'CGK');
        $this->assertCount(24, $charts['hours']);
        $this->assertCount(24, $charts['hourly_data']);
        for ($h = 0; $h < 24; $h++) {
            $this->assertEquals(0, $charts['hourly_data'][$h]['total_realized']);
        }
    }
}
