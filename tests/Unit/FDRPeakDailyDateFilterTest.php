<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportFilter;

class FDRPeakDailyDateFilterTest extends TestCase
{
    protected FlightDailyReportFilter $filterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = new FlightDailyReportFilter();
    }

    public function test_filter_isolates_single_day_from_multi_day_dataset(): void
    {
        $records = [
            ['flight_no' => 'GA101', 'flight_date' => '2026-08-01', 'dep_sched' => '12:00', 'operator' => 'GA', 'leg' => 'DEP', 'traffic' => 'DOM'],
            ['flight_no' => 'GA102', 'flight_date' => '2026-08-02', 'dep_sched' => '12:00', 'operator' => 'GA', 'leg' => 'DEP', 'traffic' => 'DOM'],
            ['flight_no' => 'GA103', 'flight_date' => '2026-08-03', 'dep_sched' => '12:00', 'operator' => 'GA', 'leg' => 'DEP', 'traffic' => 'DOM'],
            ['flight_no' => 'GA104', 'flight_date' => '2026-08-03', 'dep_sched' => '14:00', 'operator' => 'GA', 'leg' => 'ARR', 'traffic' => 'DOM'],
        ];

        $filtered = $this->filterService->filterRecords($records, [
            'analysis_date' => '2026-08-03'
        ]);

        $this->assertCount(2, $filtered);
        foreach ($filtered as $rec) {
            $this->assertEquals('2026-08-03', FlightDailyReportFilter::standardizeDate($rec['flight_date']));
        }
    }

    public function test_filter_supports_different_date_formats(): void
    {
        $records = [
            ['flight_no' => 'GA101', 'flight_date' => '2026-08-15', 'dep_sched' => '10:00'],
            ['flight_no' => 'GA102', 'flight_date' => '15/08/2026', 'dep_sched' => '11:00'],
            ['flight_no' => 'GA103', 'flight_date' => '16-08-2026', 'dep_sched' => '12:00'],
        ];

        // Query with DD-MM-YYYY format
        $filtered = $this->filterService->filterRecords($records, [
            'analysis_date' => '15-08-2026'
        ]);

        $this->assertCount(2, $filtered);
        $this->assertEquals('GA101', $filtered[0]['flight_no']);
        $this->assertEquals('GA102', $filtered[1]['flight_no']);
    }

    public function test_filter_combination_with_leg_operator_traffic_realization(): void
    {
        $records = [
            // GA Domestic Arrival 15-08-2026
            ['flight_no' => 'GA201', 'flight_date' => '2026-08-15', 'operator' => 'GA', 'leg' => 'ARR', 'direction' => 'ARRIVAL', 'traffic' => 'DOM', 'route_type' => 'DOMESTIC', 'realization' => true],
            // GA Domestic Departure 15-08-2026 (wrong leg)
            ['flight_no' => 'GA202', 'flight_date' => '2026-08-15', 'operator' => 'GA', 'leg' => 'DEP', 'direction' => 'DEPARTURE', 'traffic' => 'DOM', 'route_type' => 'DOMESTIC', 'realization' => true],
            // QG Domestic Arrival 15-08-2026 (wrong operator)
            ['flight_no' => 'QG203', 'flight_date' => '2026-08-15', 'operator' => 'QG', 'leg' => 'ARR', 'direction' => 'ARRIVAL', 'traffic' => 'DOM', 'route_type' => 'DOMESTIC', 'realization' => true],
            // GA International Arrival 15-08-2026 (wrong route)
            ['flight_no' => 'GA204', 'flight_date' => '2026-08-15', 'operator' => 'GA', 'leg' => 'ARR', 'direction' => 'ARRIVAL', 'traffic' => 'INT', 'route_type' => 'INTERNATIONAL', 'realization' => true],
            // GA Domestic Arrival 16-08-2026 (wrong date)
            ['flight_no' => 'GA205', 'flight_date' => '2026-08-16', 'operator' => 'GA', 'leg' => 'ARR', 'direction' => 'ARRIVAL', 'traffic' => 'DOM', 'route_type' => 'DOMESTIC', 'realization' => true],
        ];

        $filtered = $this->filterService->filterRecords($records, [
            'analysis_date' => '15-08-2026',
            'leg'           => 'ARRIVAL',
            'operator'      => 'GA',
            'traffic'       => 'DOM',
            'realization'   => 'YES',
        ]);

        $this->assertCount(1, $filtered);
        $this->assertEquals('GA201', $filtered[0]['flight_no']);
        $this->assertEquals('2026-08-15', FlightDailyReportFilter::standardizeDate($filtered[0]['flight_date']));
        $this->assertEquals('ARRIVAL', $filtered[0]['direction']);
        $this->assertEquals('GA', $filtered[0]['operator']);
        $this->assertEquals('DOM', $filtered[0]['traffic']);
    }

    public function test_active_chips_includes_analysis_date(): void
    {
        $chips = $this->filterService->getActiveFilterChips([
            'analysis_date' => '2026-08-15',
            'leg'           => 'ARRIVAL',
            'operator'      => 'GA'
        ]);

        $keys = array_column($chips, 'key');
        $this->assertContains('analysis_date', $keys);
        
        $dateChip = null;
        foreach ($chips as $c) {
            if ($c['key'] === 'analysis_date') {
                $dateChip = $c;
                break;
            }
        }
        $this->assertNotNull($dateChip);
        $this->assertStringContainsString('Date: 15-08-2026', $dateChip['label']);
    }
}
