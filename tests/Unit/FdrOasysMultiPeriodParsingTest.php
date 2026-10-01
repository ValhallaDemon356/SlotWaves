<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;

class FdrOasysMultiPeriodParsingTest extends TestCase
{
    protected FlightDailyReportParser $parser;
    protected string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->filePath = storage_path('app/templates/CGK IP YES.xls');
        if (!file_exists($this->filePath)) {
            $this->filePath = 'C:/Users/Axioo Pongo/Downloads/CGK IP YES.xls';
        }
    }

    public function test_it_parses_authoritative_multi_month_source_period_and_diagnostics(): void
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        $this->assertIsArray($result);
        $meta = $result['meta'];

        $this->assertEquals('2026-01-01', $meta['source_start']);
        $this->assertEquals('2026-06-30', $meta['source_end']);
        $this->assertEquals(181, $meta['available_days']);
        $this->assertEquals('CUSTOM_RANGE', $meta['source_granularity']);

        $diagnostics = $meta['diagnostics'];
        $this->assertEquals(9225, $diagnostics['html_data_rows']);
        $this->assertEquals(9224, $diagnostics['movement_rows']);
        $this->assertEquals(1, $diagnostics['summary_rows']);
        $this->assertEquals(0, $diagnostics['rejected_rows']);
    }
}
