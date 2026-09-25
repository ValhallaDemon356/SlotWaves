<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\HourlyChartService;

class FDRDailyPassengerCompositionTest extends TestCase
{
    protected FlightDailyReportAnalytics $analyticsService;
    protected FlightDailyReportFilter $filterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = new FlightDailyReportFilter();
        $this->analyticsService = new FlightDailyReportAnalytics(new HourlyChartService());
    }

    public function test_passenger_composition_isolates_selected_day(): void
    {
        $records = [
            // 15 Aug: Adult: 120, Child: 20, Infant: 5, Transit: 10, Transfer: 15
            [
                'flight_no'   => 'GA101',
                'flight_date' => '2026-08-15',
                'leg'         => 'ARR',
                'direction'   => 'ARRIVAL',
                'pax_adult'   => 120,
                'pax_child'   => 20,
                'pax_infant'  => 5,
                'pax_transit' => 10,
                'pax_transfer'=> 15,
                'pax_total'   => 170,
                'cap'         => 180,
                'realization' => true,
            ],
            // 16 Aug: Adult: 300, Child: 50, Infant: 10, Transit: 20, Transfer: 30
            [
                'flight_no'   => 'GA201',
                'flight_date' => '2026-08-16',
                'leg'         => 'DEP',
                'direction'   => 'DEPARTURE',
                'pax_adult'   => 300,
                'pax_child'   => 50,
                'pax_infant'  => 10,
                'pax_transit' => 20,
                'pax_transfer'=> 30,
                'pax_total'   => 410,
                'cap'         => 450,
                'realization' => true,
            ],
        ];

        // Filter for 15 Aug
        $filtered = $this->filterService->filterRecords($records, ['analysis_date' => '2026-08-15']);
        $analytics = $this->analyticsService->compute($filtered, 'CGK', 1);

        $comp = $analytics['pax_analytics']['composition'];
        $this->assertTrue($analytics['pax_analytics']['has_breakdown']);
        $this->assertEquals(120, $comp['adult']);
        $this->assertEquals(20, $comp['child']);
        $this->assertEquals(5, $comp['infant']);
        $this->assertEquals(10, $comp['transit']);
        $this->assertEquals(15, $comp['transfer']);

        // Filter for 16 Aug
        $filtered16 = $this->filterService->filterRecords($records, ['analysis_date' => '2026-08-16']);
        $analytics16 = $this->analyticsService->compute($filtered16, 'CGK', 1);

        $comp16 = $analytics16['pax_analytics']['composition'];
        $this->assertEquals(300, $comp16['adult']);
        $this->assertEquals(50, $comp16['child']);
    }

    public function test_passenger_composition_handles_records_without_breakdown(): void
    {
        $records = [
            [
                'flight_no'   => 'GA101',
                'flight_date' => '2026-08-15',
                'leg'         => 'DEP',
                'direction'   => 'DEPARTURE',
                'pax_adult'   => 0,
                'pax_child'   => 0,
                'pax_infant'  => 0,
                'pax_transit' => 0,
                'pax_transfer'=> 0,
                'pax_total'   => 150,
                'cap'         => 180,
                'realization' => true,
            ],
        ];

        $filtered = $this->filterService->filterRecords($records, ['analysis_date' => '2026-08-15']);
        $analytics = $this->analyticsService->compute($filtered, 'CGK', 1);

        $this->assertFalse($analytics['pax_analytics']['has_breakdown']);
        $this->assertEquals(150, $analytics['pax_analytics']['total_load']);
    }
}
