<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;

class FdrSummaryRowClassificationTest extends TestCase
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

    public function test_it_classifies_and_excludes_summary_row_from_movements(): void
    {
        $this->assertFileExists($this->filePath);
        $result = $this->parser->parse($this->filePath);

        $classification = $this->parser->classifyRows($result['records']);
        $movementRows = count($classification['movement_records']);
        $summaryRows = count($classification['summary_rows']);

        $this->assertSame(9224, $movementRows);
        $this->assertSame(1, $summaryRows);

        $summary = $classification['summary_rows'][0];
        $this->assertSame('SUMMARY', $summary['row_type']);
        $this->assertTrue(
            strcasecmp(trim($summary['air_line']), 'PAX ALL') === 0 ||
            stripos($summary['air_line'], 'PAX ALL') !== false ||
            stripos($summary['desc'] ?? '', 'PAX ALL') !== false
        );

        // Verify summary data stored in metadata
        $meta = $result['meta'];
        $this->assertArrayHasKey('source_summary', $meta);
        $this->assertEquals(1299163, $meta['source_summary']['pax_all'] ?? 0);
        $this->assertEquals(1180522, $meta['source_summary']['adult'] ?? 0);
        $this->assertEquals(63620, $meta['source_summary']['child'] ?? 0);
        $this->assertEquals(14784, $meta['source_summary']['infant'] ?? 0);
        $this->assertEquals(20762, $meta['source_summary']['transit'] ?? 0);
        $this->assertEquals(0, $meta['source_summary']['transfer'] ?? 0);
    }
}
