<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportFilter;

class FDRDailyDetailTableTest extends TestCase
{
    protected FlightDailyReportFilter $filterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = new FlightDailyReportFilter();
    }

    public function test_detail_table_only_displays_records_for_selected_analysis_date(): void
    {
        $records = [];
        // Day 15: 25 flights
        for ($i = 1; $i <= 25; $i++) {
            $records[] = [
                'flight_no'   => "GA15-$i",
                'flight_date' => '2026-08-15',
                'leg'         => 'DEP',
                'direction'   => 'DEPARTURE',
                'operator'    => 'GA',
            ];
        }
        // Day 16: 40 flights
        for ($i = 1; $i <= 40; $i++) {
            $records[] = [
                'flight_no'   => "GA16-$i",
                'flight_date' => '2026-08-16',
                'leg'         => 'ARR',
                'direction'   => 'ARRIVAL',
                'operator'    => 'GA',
            ];
        }

        // Total raw records = 65
        $this->assertCount(65, $records);

        // Filter to 15 Aug
        $filteredDay15 = $this->filterService->filterRecords($records, [
            'analysis_date' => '2026-08-15'
        ]);

        $this->assertCount(25, $filteredDay15);
        foreach ($filteredDay15 as $r) {
            $this->assertEquals('2026-08-15', FlightDailyReportFilter::standardizeDate($r['flight_date']));
            $this->assertStringStartsWith('GA15-', $r['flight_no']);
        }

        // Filter to 16 Aug
        $filteredDay16 = $this->filterService->filterRecords($records, [
            'analysis_date' => '2026-08-16'
        ]);

        $this->assertCount(40, $filteredDay16);
        foreach ($filteredDay16 as $r) {
            $this->assertEquals('2026-08-16', FlightDailyReportFilter::standardizeDate($r['flight_date']));
            $this->assertStringStartsWith('GA16-', $r['flight_no']);
        }
    }

    public function test_pagination_operates_after_date_filtering(): void
    {
        $records = [];
        for ($i = 1; $i <= 25; $i++) {
            $records[] = [
                'flight_no'   => "GA15-$i",
                'flight_date' => '2026-08-15',
                'leg'         => 'DEP',
            ];
        }
        for ($i = 1; $i <= 100; $i++) {
            $records[] = [
                'flight_no'   => "GA16-$i",
                'flight_date' => '2026-08-16',
                'leg'         => 'DEP',
            ];
        }

        // 1. Filter first
        $filtered = $this->filterService->filterRecords($records, ['analysis_date' => '2026-08-15']);
        $this->assertCount(25, $filtered);

        // 2. Paginate filtered dataset
        $perPage = 10;
        $totalRows = count($filtered);
        $totalPages = (int) ceil($totalRows / $perPage);
        $this->assertEquals(3, $totalPages);

        // Page 1
        $page1 = array_slice($filtered, 0, $perPage);
        $this->assertCount(10, $page1);
        $this->assertEquals('GA15-1', $page1[0]['flight_no']);

        // Page 3 (last page)
        $page3 = array_slice($filtered, 20, $perPage);
        $this->assertCount(5, $page3);
        $this->assertEquals('GA15-25', $page3[4]['flight_no']);

        // Ensure no Day 16 flight is in any page of Day 15
        foreach (array_merge($page1, $page3) as $row) {
            $this->assertEquals('2026-08-15', $row['flight_date']);
        }
    }
}
