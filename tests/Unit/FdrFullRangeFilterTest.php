<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;

class FdrFullRangeFilterTest extends TestCase
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

    public function test_full_range_returns_all_9224_movements_without_single_day_restriction(): void
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        $filterResult = $this->filter->apply($result['records'], [
            'date_scope' => 'ALL_PERIOD',
            'leg'        => 'ALL',
            'operator'   => 'ALL',
        ], $result['meta']);

        $fullRange = count($filterResult['records']);
        $this->assertSame(9224, $fullRange);

        $arrivals = 0;
        $departures = 0;
        foreach ($filterResult['records'] as $r) {
            if ($r['direction'] === 'ARRIVAL') {
                $arrivals++;
            } elseif ($r['direction'] === 'DEPARTURE') {
                $departures++;
            }
        }

        $this->assertSame(4612, $arrivals);
        $this->assertSame(4612, $departures);
        $this->assertSame('Showing 9,224 of 9,224 records', $filterResult['counter_text']);

        // Verify active chips contains Period: FULL RANGE and not a single day chip
        $chipTexts = array_column($filterResult['active_chips'], 'text');
        $this->assertContains('Period: FULL RANGE', $chipTexts);
        $this->assertNotContains('Date: 01-01-2026', $chipTexts);
    }
}
