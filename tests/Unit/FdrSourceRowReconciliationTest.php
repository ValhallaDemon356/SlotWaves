<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;

class FdrSourceRowReconciliationTest extends TestCase
{
    protected string $filePath;
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->filter = new FlightDailyReportFilter();
        $this->filePath = storage_path('app/templates/CGK FDR.xls');
    }

    public function test_source_rows_reconcile_to_normalized_records_without_silent_loss()
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        $records = $result['records'];
        $meta = $result['meta'];

        // Source rows must equal normalized valid flight row count: 190
        $this->assertCount(190, $records, "Expected exactly 190 normalized flight rows, received " . count($records));

        $arrivals = array_filter($records, fn($r) => $r['direction'] === 'ARRIVAL');
        $departures = array_filter($records, fn($r) => $r['direction'] === 'DEPARTURE');

        $this->assertCount(95, $arrivals, "Expected 95 ARRIVAL rows");
        $this->assertCount(95, $departures, "Expected 95 DEPARTURE rows");

        // Filter with default 'ALL' settings
        $filterResult = $this->filter->apply($records, [
            'analysis_level' => 'DAILY',
            'analysis_date'  => '2026-07-01',
            'airport'        => 'ALL',
            'leg'            => 'ALL',
            'operator'       => 'ALL',
            'traffic'        => 'ALL',
            'realization'    => 'ALL',
        ], $meta);

        $this->assertEquals(190, $filterResult['source_count']);
        $this->assertEquals(190, $filterResult['normalized_count']);
        $this->assertEquals(190, $filterResult['filtered_count']);
        $this->assertEquals(0, $filterResult['excluded_count']);
        $this->assertTrue($filterResult['reconciliation']['is_reconciled']);
        $this->assertEmpty($filterResult['reconciliation']['exclusion_reasons']);
    }

    public function test_reconciliation_tracks_exact_reasons_when_rows_are_filtered()
    {
        $result = $this->parser->parse($this->filePath);
        $records = $result['records'];
        $meta = $result['meta'];

        // When realization is filtered to YES, exactly 25 unrealized flights are excluded
        $filterResult = $this->filter->apply($records, [
            'analysis_level' => 'DAILY',
            'analysis_date'  => '2026-07-01',
            'airport'        => 'ALL',
            'leg'            => 'ALL',
            'operator'       => 'ALL',
            'traffic'        => 'ALL',
            'realization'    => 'YES',
        ], $meta);

        $this->assertEquals(190, $filterResult['source_count']);
        $this->assertEquals(165, $filterResult['filtered_count']);
        $this->assertEquals(25, $filterResult['excluded_count']);
        $this->assertFalse($filterResult['reconciliation']['is_reconciled']);
        $this->assertArrayHasKey('Unrealized flight (no actual AIBT/AOBT)', $filterResult['reconciliation']['exclusion_reasons']);
        $this->assertEquals(25, $filterResult['reconciliation']['exclusion_reasons']['Unrealized flight (no actual AIBT/AOBT)']);
    }
}
