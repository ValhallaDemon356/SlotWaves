<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;

class FdrPassengerReconciliationTest extends TestCase
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

    public function test_passenger_reconciliation_in_full_range(): void
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

        // Core passengers: Adult + Child + Infant = 1,258,926
        $passengerCore = $kpis['total_passengers'];
        $this->assertSame(1258926, $passengerCore);

        // Individual passenger fields from analytics kpis or records
        $adult = $kpis['adult_passengers'] ?? 0;
        $child = $kpis['child_passengers'] ?? 0;
        $infant = $kpis['infant_passengers'] ?? 0;
        $transit = $kpis['transit_passengers'] ?? 0;
        $transfer = $kpis['transfer_passengers'] ?? 0;
        $capacity = $kpis['total_capacity'] ?? 0;

        $this->assertSame(1180522, $adult);
        $this->assertSame(63620, $child);
        $this->assertSame(14784, $infant);
        $this->assertSame(20762, $transit);
        $this->assertSame(0, $transfer);
        $this->assertSame(1664808, $capacity);
    }
}
