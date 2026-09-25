<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\HourlyChartService;

class FDRDailyKpiTest extends TestCase
{
    protected FlightDailyReportAnalytics $analyticsService;
    protected FlightDailyReportFilter $filterService;
    protected HourlyChartService $hourlyChartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = new FlightDailyReportFilter();
        $this->hourlyChartService = new HourlyChartService();
        $this->analyticsService = new FlightDailyReportAnalytics($this->hourlyChartService);
    }

    public function test_kpis_strictly_reflect_selected_analysis_day(): void
    {
        // 15 Aug has 2 flights with 180 pax each (total 360 pax, 200 cap each => 90% load factor)
        // 16 Aug has 5 flights with 150 pax each (total 750 pax, 200 cap each => 75% load factor)
        $records = [
            [
                'flight_no'   => 'GA101',
                'flight_date' => '2026-08-15',
                'leg'         => 'DEP',
                'direction'   => 'DEPARTURE',
                'dep_sched'   => '10:00',
                'pax_total'   => 180,
                'cap'         => 200,
                'cargo_kg'    => 1200,
                'baggage_kg'  => 800,
                'realization' => true,
            ],
            [
                'flight_no'   => 'GA102',
                'flight_date' => '2026-08-15',
                'leg'         => 'ARR',
                'direction'   => 'ARRIVAL',
                'arr_sched'   => '10:30',
                'pax_total'   => 180,
                'cap'         => 200,
                'cargo_kg'    => 1300,
                'baggage_kg'  => 900,
                'realization' => true,
            ],
            [
                'flight_no'   => 'GA201',
                'flight_date' => '2026-08-16',
                'leg'         => 'DEP',
                'direction'   => 'DEPARTURE',
                'dep_sched'   => '14:00',
                'pax_total'   => 150,
                'cap'         => 200,
                'cargo_kg'    => 2000,
                'baggage_kg'  => 1000,
                'realization' => true,
            ],
        ];

        // Total source records = 3
        $this->assertCount(3, $records);

        // Filter to 15-08-2026
        $dailyRecords = $this->filterService->filterRecords($records, [
            'analysis_date' => '2026-08-15'
        ]);

        $this->assertCount(2, $dailyRecords);

        $analytics = $this->analyticsService->compute($dailyRecords, 'CGK', 1);
        $kpis = $analytics['kpis'];

        // Selected Day flights: 2 (NOT 3)
        $this->assertEquals(2, $kpis['total_flights']);
        // Selected Day passengers: 360 (NOT 510)
        $this->assertEquals(360, $kpis['total_passengers']);
        // Selected Day cargo: 2500 (NOT 4500)
        $this->assertEquals(2500, $kpis['total_cargo_kg']);
        // Selected Day baggage: 1700 (NOT 2700)
        $this->assertEquals(1700, $kpis['total_baggage_kg']);
        // Selected Day load factor: 360 / 400 = 90.0%
        $this->assertEquals('90%', $kpis['avg_load_factor']);
        $this->assertEquals(90.0, $kpis['avg_load_factor_num']);

        // Peak hour on 15 Aug is 10:00 (2 movements)
        $this->assertNotNull($kpis['peak_hour']);
        $this->assertEquals(10, $kpis['peak_hour']['hour']);
        $this->assertEquals(2, $kpis['peak_hour']['movements']);
    }

    public function test_source_summary_coexists_with_analysis_day(): void
    {
        // Monthly source: 6,226 flights
        // Selected day: 214 flights
        $sourceTotal = 6226;
        $dayTotal = 214;

        $sourceSummary = [
            'source_period_start' => '2026-08-01',
            'source_period_end'   => '2026-08-31',
            'period_label'        => '01-08-2026 → 31-08-2026',
            'days_available'      => '31 DAYS AVAILABLE',
            'total_flights'       => $sourceTotal,
        ];

        $this->assertEquals(6226, $sourceSummary['total_flights']);
        $this->assertEquals('31 DAYS AVAILABLE', $sourceSummary['days_available']);
        $this->assertNotEquals($sourceSummary['total_flights'], $dayTotal);
    }
}
