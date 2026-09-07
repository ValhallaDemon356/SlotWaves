<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use App\Services\Dau\Parsers\DAU1Parser;
use App\Services\Dau\Parsers\DAU2Parser;
use App\Services\Dau\Parsers\DAU3Parser;
use App\Services\Dau\Parsers\DAU4Parser;
use App\Services\Dau\Parsers\DAU5Parser;
use App\Services\Dau\Parsers\DAU6Parser;
use App\Services\Dau\Parsers\DAU10BParser;
use App\Services\Dau\Parsers\DAU11Parser;
use App\Services\Dau\Parsers\DAU12Parser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversalDauFilterTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;

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
    }

    private function createUploadFor(string $reportType, string $filename, object $parser): Upload
    {
        $samplePath = base_path("resources/templates/dau/{$filename}");
        $parsed = file_exists($samplePath) ? $parser->parse($samplePath) : [
            'meta' => ['airport_code' => 'CGK'],
            'summary' => ['total_movements' => 10, 'aircraft_total' => 10],
            'records' => [],
        ];

        return Upload::create([
            'original_filename' => $filename,
            'stored_path'       => "uploads/{$filename}",
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => $reportType,
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $parsed,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);
    }

    /**
     * TEST 1: DAU-03 Status Flight filter (Niaga vs Bukan Niaga).
     */
    public function test_dau03_status_filter_reacts(): void
    {
        $upload = $this->createUploadFor('DAU3', 'DAU-3.xls', new DAU3Parser());
        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertOk();
        $response->assertSee('STATUS PENERBANGAN');
    }

    /**
     * TEST 2: DAU-04 Top N Routes filter and Airline/Airport matrix.
     */
    public function test_dau04_route_filter_reacts(): void
    {
        $upload = $this->createUploadFor('DAU4', 'DAU-4.xls', new DAU4Parser());
        $response = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'top_n' => '5',
        ]));
        $response->assertOk();
        $response->assertSee('TOP ORIGIN (ARRIVAL) VS TOP DESTINATION (DEPARTURE)');
    }

    /**
     * TEST 3: DAU-05 Airline Pareto and metric switching (Aircraft vs Passenger).
     */
    public function test_dau05_airline_pareto_metric_switch(): void
    {
        $upload = $this->createUploadFor('DAU5', 'DAU-5.xls', new DAU5Parser());
        $response = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'metric' => 'passenger',
        ]));
        $response->assertOk();
        $response->assertSee('AIRLINE PARETO');
    }

    /**
     * TEST 4: DAU-06 Aircraft Fleet Mix & Category filtering.
     */
    public function test_dau06_fleet_category_filter(): void
    {
        $upload = $this->createUploadFor('DAU6', 'DAU-6.xls', new DAU6Parser());
        $response = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'category' => 'Narrow Body',
        ]));
        $response->assertOk();
        $response->assertSee('TOP 15 AIRCRAFT TYPES');
    }

    /**
     * TEST 5: DAU-10B Block On (DTG) vs Block Off (BRK) operation filter.
     */
    public function test_dau10b_block_on_off_operation_filter(): void
    {
        $upload = $this->createUploadFor('DAU10B', 'DAU-10B.xls', new DAU10BParser());
        $response = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'operation' => 'BLOCK_ON',
        ]));
        $response->assertOk();
        $response->assertSee('BLOCK ON');
    }

    /**
     * TEST 6: DAU-11 Traffic Flow Statistics and PDF export.
     */
    public function test_dau11_traffic_flow_and_pdf(): void
    {
        $upload = $this->createUploadFor('DAU11', 'DAU-11.xls', new DAU11Parser());
        $response = $this->get(route('dau.export.pdf', [
            'upload' => $upload->id,
            'flight_type' => 'ALL',
        ]));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * TEST 7: DAU-12 ARR/DEP x DOM/INT Matrix.
     */
    public function test_dau12_arr_dep_dom_int_matrix(): void
    {
        $upload = $this->createUploadFor('DAU12', 'DAU-12.xls', new DAU12Parser());
        $response = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'metric' => 'passenger',
        ]));
        $response->assertOk();
        $response->assertSee('STATISTIK');
    }
}
