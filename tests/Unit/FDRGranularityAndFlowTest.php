<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;
use App\Services\FlightDailyReport\HourlyChartService;

class FDRGranularityAndFlowTest extends TestCase
{
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportFilter $filter;
    protected FlightDailyReportAnalytics $analytics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->filter = new FlightDailyReportFilter();
        $this->analytics = new FlightDailyReportAnalytics();
    }

    public function test_detect_granularity_rules(): void
    {
        // 1. DAILY: Same calendar day
        $this->assertEquals('DAILY', FlightDailyReportParser::detectGranularity('2026-07-01', '2026-07-01'));
        $this->assertEquals('DAILY', FlightDailyReportParser::detectGranularity('01-07-2026', '01-07-2026'));
        $this->assertEquals('DAILY', FlightDailyReportParser::detectGranularity('01/07/2026', '01/07/2026'));

        // 2. MONTHLY: Complete calendar month
        $this->assertEquals('MONTHLY', FlightDailyReportParser::detectGranularity('2026-08-01', '2026-08-31'));
        $this->assertEquals('MONTHLY', FlightDailyReportParser::detectGranularity('01-08-2026', '31-08-2026'));
        $this->assertEquals('MONTHLY', FlightDailyReportParser::detectGranularity('2026-02-01', '2026-02-28'));

        // 3. YEARLY: Complete calendar year
        $this->assertEquals('YEARLY', FlightDailyReportParser::detectGranularity('2026-01-01', '2026-12-31'));
        $this->assertEquals('YEARLY', FlightDailyReportParser::detectGranularity('01-01-2026', '31-12-2026'));

        // 4. CUSTOM RANGE: Any other multi-day interval
        $this->assertEquals('CUSTOM RANGE', FlightDailyReportParser::detectGranularity('2026-08-01', '2026-08-15'));
        $this->assertEquals('CUSTOM RANGE', FlightDailyReportParser::detectGranularity('2026-08-10', '2026-08-31'));
        $this->assertEquals('CUSTOM RANGE', FlightDailyReportParser::detectGranularity('2026-01-15', '2026-03-20'));
    }

    public function test_direction_aware_normalized_movement_date_and_hour(): void
    {
        $meta = [
            'airport'      => 'CGK',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-31',
            'realization'  => 'YES',
        ];

        // Column mapping
        $columnInfo = [
            'header_row_index' => 0,
            'mapping' => [
                'flight_no' => 0,
                'air_line'  => 1,
                'leg'       => 2,
                'sibt'      => 3,
                'sobt'      => 4,
                'aibt'      => 5,
                'aobt'      => 6,
                'city_1'    => 7,
                'city_2'    => 8,
            ],
        ];

        $rows = [
            ['FlightNo', 'Airline', 'Leg', 'SIBT', 'SOBT', 'AIBT', 'AOBT', 'City1', 'City2'],
            // Arrival flight on 01-08-2026 at 08:45
            ['GA101', 'Garuda Indonesia', 'A SCHED', '01-08-2026 08:30:00', 'N/A', '01-08-2026 08:45:00', 'N/A', 'SUB', 'CGK'],
            // Departure flight on 15-08-2026 at 14:15
            ['GA102', 'Garuda Indonesia', 'D SCHED', 'N/A', '15-08-2026 14:00:00', 'N/A', '15-08-2026 14:15:00', 'CGK', 'DPS'],
            // Arrival flight on 31-08-2026 at 23:10
            ['JT200', 'Lion Air', 'A SCHED', '31-08-2026 23:00:00', 'N/A', '31-08-2026 23:10:00', 'N/A', 'DPS', 'CGK'],
        ];

        $records = $this->parser->normalizeRecords($rows, $columnInfo, $meta);

        $this->assertCount(3, $records);

        // Record 1: Arrival on Aug 1
        $this->assertEquals('2026-08-01', $records[0]['operational_date']);
        $this->assertEquals(8, $records[0]['operational_hour']);
        $this->assertEquals('ARRIVAL', $records[0]['direction']);

        // Record 2: Departure on Aug 15
        $this->assertEquals('2026-08-15', $records[1]['operational_date']);
        $this->assertEquals(14, $records[1]['operational_hour']);
        $this->assertEquals('DEPARTURE', $records[1]['direction']);

        // Record 3: Arrival on Aug 31
        $this->assertEquals('2026-08-31', $records[2]['operational_date']);
        $this->assertEquals(23, $records[2]['operational_hour']);
        $this->assertEquals('ARRIVAL', $records[2]['direction']);
    }

    public function test_monthly_source_filtering_by_actual_record_date(): void
    {
        // 5 records spanning different dates in August 2026
        $records = [
            ['index' => 1, 'flight_no' => 'GA101', 'operational_date' => '2026-08-01', 'hour' => 8, 'direction' => 'ARRIVAL', 'cap' => 180, 'load' => 150, 'cargo_kg' => 1200, 'is_scheduled' => true, 'is_irregular' => false],
            ['index' => 2, 'flight_no' => 'GA102', 'operational_date' => '2026-08-01', 'hour' => 10, 'direction' => 'DEPARTURE', 'cap' => 180, 'load' => 160, 'cargo_kg' => 900, 'is_scheduled' => true, 'is_irregular' => false],
            ['index' => 3, 'flight_no' => 'GA201', 'operational_date' => '2026-08-02', 'hour' => 12, 'direction' => 'ARRIVAL', 'cap' => 150, 'load' => 140, 'cargo_kg' => 500, 'is_scheduled' => true, 'is_irregular' => false],
            ['index' => 4, 'flight_no' => 'GA301', 'operational_date' => '2026-08-15', 'hour' => 14, 'direction' => 'DEPARTURE', 'cap' => 200, 'load' => 185, 'cargo_kg' => 1500, 'is_scheduled' => true, 'is_irregular' => false],
            ['index' => 5, 'flight_no' => 'GA302', 'operational_date' => '2026-08-15', 'hour' => 14, 'direction' => 'ARRIVAL', 'cap' => 200, 'load' => 190, 'cargo_kg' => 1400, 'is_scheduled' => true, 'is_irregular' => false],
        ];

        $meta = [
            'airport'      => 'CGK',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-31',
            'source_type'  => 'MONTHLY',
        ];

        // 1. Filter for 2026-08-01: ONLY 2 records
        $res01 = $this->filter->apply($records, ['analysis_level' => 'DAILY', 'analysis_date' => '2026-08-01'], $meta);
        $this->assertCount(2, $res01['records']);
        $analytics01 = $this->analytics->compute($res01['records'], $meta);
        $this->assertEquals(2, $analytics01['kpis']['total_flights']);
        $this->assertEquals(1, $analytics01['kpis']['arrivals']);
        $this->assertEquals(1, $analytics01['kpis']['departures']);
        $this->assertEquals(310, $analytics01['kpis']['total_passengers']);

        // 2. Filter for 2026-08-15: ONLY 2 records
        $res15 = $this->filter->apply($records, ['analysis_level' => 'DAILY', 'analysis_date' => '2026-08-15'], $meta);
        $this->assertCount(2, $res15['records']);
        $analytics15 = $this->analytics->compute($res15['records'], $meta);
        $this->assertEquals(2, $analytics15['kpis']['total_flights']);
        $this->assertEquals(375, $analytics15['kpis']['total_passengers']);

        // 3. Filter for entire month (analysis_level = MONTHLY): ALL 5 records
        $resMonth = $this->filter->apply($records, ['analysis_level' => 'MONTHLY', 'analysis_month' => '2026-08'], $meta);
        $this->assertCount(5, $resMonth['records']);
        $analyticsMonth = $this->analytics->compute($resMonth['records'], $meta);
        $this->assertEquals(5, $analyticsMonth['kpis']['total_flights']);
    }

    public function test_empty_date_in_source_range_returns_zero_records(): void
    {
        $records = [
            ['index' => 1, 'flight_no' => 'GA101', 'operational_date' => '2026-08-01', 'hour' => 8, 'direction' => 'ARRIVAL', 'cap' => 180, 'load' => 150, 'cargo_kg' => 1200, 'is_scheduled' => true, 'is_irregular' => false],
        ];

        $meta = [
            'airport'      => 'CGK',
            'period_start' => '2026-08-01',
            'period_end'   => '2026-08-31',
            'source_type'  => 'MONTHLY',
        ];

        // Select a date inside August that has no flights (e.g. 2026-08-10)
        $res = $this->filter->apply($records, ['analysis_level' => 'DAILY', 'analysis_date' => '2026-08-10'], $meta);
        $this->assertCount(0, $res['records']);

        $analytics = $this->analytics->compute($res['records'], $meta);
        $this->assertEquals(0, $analytics['kpis']['total_flights']);
        $this->assertEquals('N/A', $analytics['kpis']['avg_load_factor']);
    }

    public function test_hourly_chart_totals_reconcile_with_daily_kpis(): void
    {
        $records = [
            ['index' => 1, 'flight_no' => 'GA101', 'operational_date' => '2026-08-01', 'hour' => 8, 'direction' => 'ARRIVAL', 'cap' => 180, 'load' => 150, 'cargo_kg' => 1200, 'is_scheduled' => true, 'is_irregular' => false],
            ['index' => 2, 'flight_no' => 'GA102', 'operational_date' => '2026-08-01', 'hour' => 8, 'direction' => 'DEPARTURE', 'cap' => 180, 'load' => 160, 'cargo_kg' => 900, 'is_scheduled' => true, 'is_irregular' => false],
            ['index' => 3, 'flight_no' => 'GA103', 'operational_date' => '2026-08-01', 'hour' => 12, 'direction' => 'ARRIVAL', 'cap' => 150, 'load' => 140, 'cargo_kg' => 500, 'is_scheduled' => true, 'is_irregular' => false],
        ];

        $chartService = new HourlyChartService();
        $chartData = $chartService->buildHourlyCharts($records, 'CGK');

        $hourlyArrivals = array_sum(array_column($chartData['hourly_data'], 'arr_realized'));
        $hourlyDepartures = array_sum(array_column($chartData['hourly_data'], 'dep_realized'));
        $hourlyTotal = array_sum(array_column($chartData['hourly_data'], 'total_realized'));

        $this->assertEquals(2, $hourlyArrivals);
        $this->assertEquals(1, $hourlyDepartures);
        $this->assertEquals(3, $hourlyTotal);

        // Peak hour at 08:00 has 2 movements
        $this->assertEquals(8, $chartData['peak_hour']['hour']);
        $this->assertEquals(2, $chartData['peak_hour']['movements']);
    }
}
