<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;

class FdrMultiPeriodChartAggregationTest extends TestCase
{
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportFilter $filter;
    protected FlightDailyReportAnalytics $analytics;
    protected string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->filter = new FlightDailyReportFilter();
        $this->analytics = new FlightDailyReportAnalytics();
        $this->filePath = storage_path('app/templates/CGK IP YES.xls');
        if (!file_exists($this->filePath)) {
            $this->filePath = 'C:/Users/Axioo Pongo/Downloads/CGK IP YES.xls';
        }
    }

    public function test_full_range_chart_aggregates_into_daily_timeline(): void
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        $filterResult = $this->filter->apply($result['records'], [
            'date_scope' => 'ALL_PERIOD',
        ], $result['meta']);

        $computed = $this->analytics->compute($filterResult['records'], $result['meta'], [
            'date_scope' => 'ALL_PERIOD',
        ]);

        $trend = $computed['combined_trend'];
        $this->assertEquals('daily', $trend['granularity']);
        $this->assertCount(181, $trend['labels']);

        $totalArr = array_sum($trend['series']['arrival']);
        $totalDep = array_sum($trend['series']['departure']);
        $this->assertEquals(4612, $totalArr);
        $this->assertEquals(4612, $totalDep);
        $this->assertEquals(9224, $totalArr + $totalDep);
    }

    public function test_single_day_chart_aggregates_into_twenty_four_hourly_buckets(): void
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        $filterResult = $this->filter->apply($result['records'], [
            'date_scope'    => 'DAY',
            'analysis_date' => '2026-01-01',
        ], $result['meta']);

        $computed = $this->analytics->compute($filterResult['records'], $result['meta'], [
            'date_scope'    => 'DAY',
            'analysis_date' => '2026-01-01',
        ]);

        $trend = $computed['combined_trend'];
        $this->assertEquals('hourly', $trend['granularity']);
        $this->assertCount(24, $trend['labels']);

        $totalFlights = array_sum($trend['series']['flights']);
        $this->assertEquals(46, $totalFlights);
    }
}
