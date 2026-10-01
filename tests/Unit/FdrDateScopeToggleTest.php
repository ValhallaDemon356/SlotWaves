<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;

class FdrDateScopeToggleTest extends TestCase
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

    public function test_date_scope_toggle_sequence(): void
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        // 1. FULL RANGE -> 9,224
        $r1 = $this->filter->apply($result['records'], [
            'date_scope' => 'ALL_PERIOD',
        ], $result['meta']);
        $this->assertSame(9224, count($r1['records']));

        // 2. DAY: 01-01-2026 -> 46
        $r2 = $this->filter->apply($result['records'], [
            'date_scope'    => 'DAY',
            'analysis_date' => '2026-01-01',
        ], $result['meta']);
        $this->assertSame(46, count($r2['records']));

        // 3. DAY: 15-03-2026 -> 68
        $r3 = $this->filter->apply($result['records'], [
            'date_scope'    => 'DAY',
            'analysis_date' => '2026-03-15',
        ], $result['meta']);
        $this->assertSame(68, count($r3['records']));

        // 4. Return to FULL RANGE -> 9,224
        $r4 = $this->filter->apply($result['records'], [
            'date_scope' => 'ALL_PERIOD',
        ], $result['meta']);
        $this->assertSame(9224, count($r4['records']));
    }
}
