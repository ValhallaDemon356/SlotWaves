<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;

class FdrCargoReconciliationTest extends TestCase
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

    public function test_cargo_and_baggage_reconciliation_in_full_range(): void
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        $filterResult = $this->filter->apply($result['records'], [
            'date_scope' => 'ALL_PERIOD',
        ], $result['meta']);

        $computed = $this->analytics->compute($filterResult['records'], $result['meta'], [
            'date_scope' => 'ALL_PERIOD',
        ]);

        $kpis = $computed['kpis'];

        $cargo = $kpis['total_cargo_kg'];
        $baggage = $kpis['total_baggage_kg'];

        $this->assertSame(9999919, (int)round($cargo));
        $this->assertSame(12536485, (int)round($baggage));
    }
}
