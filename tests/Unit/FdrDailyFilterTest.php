<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;

class FdrDailyFilterTest extends TestCase
{
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportFilter $filter;
    protected string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->filter = new FlightDailyReportFilter();
        $this->filePath = storage_path('app/templates/CGK IP YES.xls');
        if (!file_exists($this->filePath)) {
            $this->filePath = 'C:/Users/Axioo Pongo/Downloads/CGK IP YES.xls';
        }
    }

    public function test_single_day_filter_jan_1_and_mar_15(): void
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        // Test 2026-01-01
        $jan1Result = $this->filter->apply($result['records'], [
            'date_scope'    => 'DAY',
            'analysis_date' => '2026-01-01',
        ], $result['meta']);

        $Jan1 = count($jan1Result['records']);
        $this->assertSame(46, $Jan1);
        $this->assertSame('Showing 46 of 9,224 records', $jan1Result['counter_text']);

        // Test 2026-03-15
        $mar15Result = $this->filter->apply($result['records'], [
            'date_scope'    => 'DAY',
            'analysis_date' => '2026-03-15',
        ], $result['meta']);

        $Mar15 = count($mar15Result['records']);
        $this->assertSame(68, $Mar15);
        $this->assertSame('Showing 68 of 9,224 records', $mar15Result['counter_text']);
    }
}
