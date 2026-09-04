<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\Parsers\DAU10Parser;
use App\Services\Dau\Parsers\DAU10AParser;
use App\Services\Dau\Parsers\DAU10BParser;
use App\Http\Controllers\DauDashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DauDashboardEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private DauDashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->airport = Airport::firstOrCreate(
            ['iata_code' => 'CGK'],
            [
                'name'    => 'Soekarno Hatta',
                'city'    => 'Tangerang',
                'country' => 'Indonesia',
            ]
        );

        $this->controller = new DauDashboardController();
    }

    /**
     * TEST 1: Verify DAU-10 parser produces exact authentic Excel totals
     */
    public function test_dau10_parser_matches_source_totals(): void
    {
        $templatePath = base_path('resources/templates/dau/DAU-10.xls');
        $this->assertFileExists($templatePath);

        $parser = new DAU10Parser();
        $res = $parser->parse($templatePath);

        $this->assertEquals('DAU10', $res['report_type']);
        $this->assertEquals(1019, $res['summary']['total_movements']);
        $this->assertEquals(510, $res['summary']['aircraft_arrival']);
        $this->assertEquals(509, $res['summary']['aircraft_departure']);
        $this->assertEquals(149298, $res['summary']['passenger_total']);
        $this->assertEquals(1030, $res['summary']['crew_total']);
        $this->assertEquals(1719921, $res['summary']['baggage_total']);
        $this->assertEquals(1773002, $res['summary']['cargo_total']);
        $this->assertCount(145, $res['records']);
    }

    /**
     * TEST 2: Verify DAU-10A parser produces normalized pairs and matches source totals
     */
    public function test_dau10a_parser_matches_source_totals(): void
    {
        $templatePath = base_path('resources/templates/dau/DAU-10A.xls');
        $this->assertFileExists($templatePath);

        $parser = new DAU10AParser();
        $res = $parser->parse($templatePath);

        $this->assertEquals('DAU10A', $res['report_type']);
        $this->assertEquals(1008, $res['summary']['total_movements']);
        $this->assertEquals(503, $res['summary']['aircraft_arrival']);
        $this->assertEquals(505, $res['summary']['aircraft_departure']);
        $this->assertEquals(147738, $res['summary']['passenger_total']);
        $this->assertCount(24, $res['records']);
        $this->assertArrayHasKey('normalized_pairs', $res);
        $this->assertCount(168, $res['normalized_pairs']); // 24 hours x 7 terminals
    }

    /**
     * TEST 3: Verify DAU-10B parser produces Block On/Off records and matches source totals
     */
    public function test_dau10b_parser_matches_source_totals(): void
    {
        $templatePath = base_path('resources/templates/dau/DAU-10B.xls');
        $this->assertFileExists($templatePath);

        $parser = new DAU10BParser();
        $res = $parser->parse($templatePath);

        $this->assertEquals('DAU10B', $res['report_type']);
        $this->assertTrue($res['is_block_on_off']);
        $this->assertEquals(1019, $res['summary']['total_movements']);
        $this->assertEquals(149298, $res['summary']['passenger_total']);
        $this->assertCount(145, $res['records']);
        $this->assertArrayHasKey('block_on_aircraft', $res['records'][0]);
        $this->assertArrayHasKey('block_off_aircraft', $res['records'][0]);
    }

    /**
     * TEST 4: Unified Filter Engine - Terminal filter
     */
    public function test_filter_by_terminal(): void
    {
        $parser = new DAU10Parser();
        $data = $parser->parse(base_path('resources/templates/dau/DAU-10.xls'));

        $analytics = $this->controller->filterReportDataset($data['records'], [
            'flight_type' => 'ALL',
            'terminal'    => '2F',
            'hour'        => 'ALL',
            'metric'      => 'aircraft',
            'operation'   => 'ALL',
        ], $data['meta'], 'DAU10');

        $this->assertNotEmpty($analytics['filtered_records']);
        foreach ($analytics['filtered_records'] as $r) {
            $this->assertEquals('2F', $r['terminal']);
        }
        $this->assertEquals(80, $analytics['summary']['total_movements']);
        $this->assertEquals(11709, $analytics['summary']['passenger_total']);
    }

    /**
     * TEST 5: Unified Filter Engine - Hour filter
     */
    public function test_filter_by_hour(): void
    {
        $parser = new DAU10Parser();
        $data = $parser->parse(base_path('resources/templates/dau/DAU-10.xls'));

        $analytics = $this->controller->filterReportDataset($data['records'], [
            'flight_type' => 'ALL',
            'terminal'    => 'ALL',
            'hour'        => '00.01 - 01.00',
            'metric'      => 'aircraft',
            'operation'   => 'ALL',
        ], $data['meta'], 'DAU10');

        $this->assertNotEmpty($analytics['filtered_records']);
        foreach ($analytics['filtered_records'] as $r) {
            $this->assertEquals('00.01 - 01.00', $r['hour']);
        }
    }

    /**
     * TEST 6: Unified Filter Engine - Flight Type (DOM vs INT)
     */
    public function test_filter_by_flight_type(): void
    {
        $parser = new DAU10Parser();
        $data = $parser->parse(base_path('resources/templates/dau/DAU-10.xls'));

        // Filter ALL
        $allRes = $this->controller->filterReportDataset($data['records'], [
            'flight_type' => 'ALL',
            'terminal'    => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'aircraft',
            'operation'   => 'ALL',
        ], $data['meta'], 'DAU10');
        $this->assertCount(145, $allRes['filtered_records']);

        // Filter INT: only 2E, 2F, 3U match
        $intRes = $this->controller->filterReportDataset($data['records'], [
            'flight_type' => 'INT',
            'terminal'    => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'aircraft',
            'operation'   => 'ALL',
        ], $data['meta'], 'DAU10');

        $this->assertNotEmpty($intRes['filtered_records']);
        foreach ($intRes['filtered_records'] as $r) {
            $this->assertContains($r['terminal'], ['2E', '2F', '3U']);
        }
    }

    /**
     * TEST 7: Unified Filter Engine - DAU10B Operation (BLOCK ON vs BLOCK OFF)
     */
    public function test_dau10b_filter_by_operation(): void
    {
        $parser = new DAU10BParser();
        $data = $parser->parse(base_path('resources/templates/dau/DAU-10B.xls'));

        // Filter BLOCK_ON
        $onRes = $this->controller->filterReportDataset($data['records'], [
            'flight_type' => 'ALL',
            'terminal'    => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'aircraft',
            'operation'   => 'BLOCK_ON',
        ], $data['meta'], 'DAU10B');

        $this->assertNotEmpty($onRes['filtered_records']);
        foreach ($onRes['filtered_records'] as $r) {
            $this->assertTrue($r['aircraft_arrival'] > 0 || $r['passenger_arrival'] > 0);
        }

        // Filter BLOCK_OFF
        $offRes = $this->controller->filterReportDataset($data['records'], [
            'flight_type' => 'ALL',
            'terminal'    => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'aircraft',
            'operation'   => 'BLOCK_OFF',
        ], $data['meta'], 'DAU10B');

        $this->assertNotEmpty($offRes['filtered_records']);
        foreach ($offRes['filtered_records'] as $r) {
            $this->assertTrue($r['aircraft_departure'] > 0 || $r['passenger_departure'] > 0);
        }
    }

    /**
     * TEST 8: Dashboard Web View - DAU10, DAU10A, DAU10B HTTP 200
     */
    public function test_dashboard_view_renders_successfully(): void
    {
        $parser = new DAU10Parser();
        $parsed10 = $parser->parse(base_path('resources/templates/dau/DAU-10.xls'));

        $upload = Upload::create([
            'original_filename' => 'DAU-10.xls',
            'stored_path'       => 'templates/dau/DAU-10.xls',
            'status'            => 'completed',
            'report_type'       => 'DAU10',
            'report_data'       => $parsed10,
            'airport_id'        => $this->airport->id,
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);
        $response->assertSee('JAM PUNCAK PESAWAT/PENUMPANG (DAU-10)');
        $response->assertSee('HOURLY AIRCRAFT MOVEMENT');
        $response->assertSee('PEAK HOUR ANALYSIS');
        $response->assertSee('1,019'); // Total movements
        $response->assertSee('149,298'); // Total passengers
    }

    /**
     * TEST 9: Filtered PDF Export - HTTP 200 and Content Type
     */
    public function test_filtered_pdf_export_downloads_correctly(): void
    {
        $parser = new DAU10AParser();
        $parsed10A = $parser->parse(base_path('resources/templates/dau/DAU-10A.xls'));

        $upload = Upload::create([
            'original_filename' => 'DAU-10A.xls',
            'stored_path'       => 'templates/dau/DAU-10A.xls',
            'status'            => 'completed',
            'report_type'       => 'DAU10A',
            'report_data'       => $parsed10A,
            'airport_id'        => $this->airport->id,
        ]);

        // Export PDF with filters: Terminal = 2F, Flight Type = DOM
        $response = $this->get(route('dau.export.pdf', [
            'upload'      => $upload->id,
            'flight_type' => 'DOM',
            'terminal'    => '2F',
            'hour'        => 'ALL',
            'metric'      => 'passenger',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');

        $disposition = $response->headers->get('content-disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('DAU-10A', $disposition);
        $this->assertStringContainsString('DOM', $disposition);
        $this->assertStringContainsString('T2F', $disposition);
    }
}
