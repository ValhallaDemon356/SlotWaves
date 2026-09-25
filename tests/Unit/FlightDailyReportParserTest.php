<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;

class FlightDailyReportParserTest extends TestCase
{
    protected FlightDailyReportParser $parser;
    protected string $templatePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->templatePath = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
    }

    public function test_it_parses_oasys_html_table_workbook(): void
    {
        $this->assertFileExists($this->templatePath);
        $result = $this->parser->parse($this->templatePath);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertGreaterThanOrEqual(100, count($result['records']));
    }

    public function test_it_extracts_metadata_strictly(): void
    {
        $result = $this->parser->parse($this->templatePath);
        $meta = $result['meta'];

        $this->assertEquals('CGK', $meta['airport']);
        $this->assertStringContainsString('Soekarno', $meta['airport_name']);
        $this->assertEquals('ALL AIRLINE', $meta['operator']);
        $this->assertEquals('2026-08-01', $meta['period_start']);
        $this->assertEquals('2026-08-31', $meta['period_end']);
        $this->assertEquals('YES', $meta['realization']);
        $this->assertEquals('OPERATIONAL DATA', $meta['data_type']);
        $this->assertEquals('OASYS', $meta['source_system']);
    }

    public function test_it_maps_all_twenty_seven_raw_fdr_fields(): void
    {
        $result = $this->parser->parse($this->templatePath);
        $first = $result['records'][0];

        $requiredKeys = [
            'air_line', 'flight_no', 'paired_no', 'sibt', 'sobt', 'aibt', 'aobt',
            'leg', 'city_1', 'city_2', 'mtow', 'reg_no', 'cap', 'load', 'adult',
            'child', 'infant', 'transit', 'transfer', 'divert', 'miss', 'crw',
            'ex_crw', 'cargo_kg', 'baggage_kg', 'pos_kg', 'stand', 'runway'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $first, "Missing normalized key: {$key}");
        }
    }

    public function test_load_factor_guardrail_returns_na_when_capacity_is_zero(): void
    {
        // Custom rows with CAP = 0
        $rows = [
            ['AIR LINE', 'FLIGHT NO', 'CAP.', 'LOAD'],
            ['Garuda Indonesia', 'GA999', '0', '0'],
            ['Lion Air', 'JT888', '180', '162'],
        ];

        $colInfo = $this->parser->identifyColumns($rows);
        $meta = ['airport' => 'CGK', 'period_start' => '2026-08-01'];
        $records = $this->parser->normalizeRecords($rows, $colInfo, $meta);

        $this->assertCount(2, $records);
        // First record with CAP = 0 must return 'N/A' (never NaN or Infinity)
        $this->assertSame('N/A', $records[0]['load_factor']);
        $this->assertNotSame(NAN, $records[0]['load_factor']);
        $this->assertNotSame(INF, $records[0]['load_factor']);

        // Second record with CAP = 180 and LOAD = 162 must be 90.0%
        $this->assertEquals(90.0, $records[1]['load_factor']);
    }

    public function test_missing_fields_default_to_na(): void
    {
        $rows = [
            ['AIR LINE', 'FLIGHT NO', 'STAND', 'RUN WAY'],
            ['Batik Air', 'ID654', '', '-'],
        ];

        $colInfo = $this->parser->identifyColumns($rows);
        $meta = ['airport' => 'CGK', 'period_start' => '2026-08-01'];
        $records = $this->parser->normalizeRecords($rows, $colInfo, $meta);

        $this->assertCount(1, $records);
        $this->assertEquals('N/A', $records[0]['stand']);
        $this->assertEquals('N/A', $records[0]['runway']);
        $this->assertEquals('N/A', $records[0]['paired_no']);
    }

    public function test_flight_suffix_extraction(): void
    {
        $rows = [
            ['AIR LINE', 'FLIGHT NO'],
            ['Garuda Indonesia', 'GA120A'],
            ['Citilink', 'QG682'],
        ];

        $colInfo = $this->parser->identifyColumns($rows);
        $meta = ['airport' => 'CGK', 'period_start' => '2026-08-01'];
        $records = $this->parser->normalizeRecords($rows, $colInfo, $meta);

        $this->assertEquals('GA120A', $records[0]['flight_no']);
        $this->assertEquals('GA120', $records[0]['flight_no_base']);
        $this->assertEquals('A', $records[0]['flight_suffix']);

        $this->assertEquals('QG682', $records[1]['flight_no']);
        $this->assertEquals('QG682', $records[1]['flight_no_base']);
        $this->assertEquals('', $records[1]['flight_suffix']);
    }
}
