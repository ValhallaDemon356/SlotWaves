<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;

class FdrOperatorFilterReconciliationTest extends TestCase
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

    public function test_operator_filter_and_performance_in_full_range(): void
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        // Movement records classification ensures summary row is excluded
        $classification = $this->parser->classifyRows($result['records']);
        $movementRecords = $classification['movement_records'];

        // Operators extracted strictly from movementRecords
        $operators = array_unique(array_filter(array_map(fn($r) => trim($r['air_line'] ?? ''), $movementRecords)));
        $this->assertContains('IP', $operators);
        $this->assertNotContains('PAX ALL', $operators);
        $this->assertNotContains('Adult', $operators);
        $this->assertNotContains('Child', $operators);
        $this->assertNotContains('Infant', $operators);
        $this->assertNotContains('Transit', $operators);

        // Filter by operator IP in ALL_PERIOD
        $filterResult = $this->filter->apply($result['records'], [
            'date_scope' => 'ALL_PERIOD',
            'operator'   => 'IP',
        ], $result['meta']);

        $this->assertCount(9224, $filterResult['records']);

        // Analytics operator breakdown
        $computed = $this->analytics->compute($filterResult['records'], $result['meta'], [
            'date_scope' => 'ALL_PERIOD',
            'operator'   => 'IP',
        ]);

        $this->assertArrayHasKey('airline_share', $computed);
        $this->assertNotEmpty($computed['airline_share']);
        $ipShare = $computed['airline_share'][0];
        $this->assertEquals('IP', $ipShare['airline']);
        $this->assertEquals(9224, $ipShare['flights']);
    }
}
