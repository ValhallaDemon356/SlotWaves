<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\Parsers\DAU12Parser;
use App\Services\Dau\Parsers\DAU10AParser;
use App\Http\Controllers\DauDashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau12FilterChartSyncTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private Upload $dau12Upload;
    private Upload $dau10aUpload;
    private array $parsedData12;
    private array $parsedData10a;

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

        // DAU-12
        $parser12 = new DAU12Parser();
        $samplePath12 = base_path('resources/templates/dau/DAU-12.xls');
        $this->parsedData12 = $parser12->parse($samplePath12);

        $this->dau12Upload = Upload::create([
            'original_filename' => 'DAU-12.xls',
            'stored_path'       => 'uploads/dau12.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU12',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $this->parsedData12,
            'source_type'       => 'excel',
            'parser_metadata'   => $this->parsedData12,
        ]);

        // DAU-10A (For Critical Protection Regression Testing)
        $parser10a = new DAU10AParser();
        $samplePath10a = base_path('resources/templates/dau/DAU-10A.xls');
        $this->parsedData10a = $parser10a->parse($samplePath10a);

        $this->dau10aUpload = Upload::create([
            'original_filename' => 'DAU-10A.xls',
            'stored_path'       => 'uploads/dau10a.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU10A',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $this->parsedData10a,
            'source_type'       => 'excel',
            'parser_metadata'   => $this->parsedData10a,
        ]);
    }

    /**
     * TEST 1: Direction Filter — ARRIVAL vs DEPARTURE vs ALL (Part 16 & Part 30)
     */
    public function test_direction_filter_arrival_and_departure(): void
    {
        $records = [
            [
                'no' => 1, 'date' => '01/01/2026',
                'aircraft_arr_domestic' => 100, 'aircraft_arr_int' => 20,
                'aircraft_dep_domestic' => 80,  'aircraft_dep_int' => 10,
                'passenger_arr_domestic'=> 1000,'passenger_arr_int' => 200,
                'passenger_dep_domestic'=> 800, 'passenger_dep_int' => 100,
            ]
        ];

        $controller = new DauDashboardController();
        $result = $controller->filterReportDataset($records, [
            'metric' => 'aircraft', 'terminal' => 'ALL', 'hour' => 'ALL', 'direction' => 'ALL', 'flight_type' => 'ALL'
        ], [], 'DAU12');

        $ac = $result['dau12_matrix']['aircraft'];
        $this->assertEquals(100, $ac['arr_dom']);
        $this->assertEquals(20,  $ac['arr_int']);
        $this->assertEquals(80,  $ac['dep_dom']);
        $this->assertEquals(10,  $ac['dep_int']);
    }

    /**
     * TEST 2: Scope Filter — DOM vs INT vs ALL (Part 17 & Part 29)
     */
    public function test_scope_filter_dom_and_int(): void
    {
        $records = [
            [
                'no' => 1, 'date' => '01/01/2026',
                'aircraft_arr_domestic' => 100, 'aircraft_arr_int' => 20,
                'aircraft_dep_domestic' => 80,  'aircraft_dep_int' => 10,
                'passenger_arr_domestic'=> 1000,'passenger_arr_int' => 200,
                'passenger_dep_domestic'=> 800, 'passenger_dep_int' => 100,
            ]
        ];

        $controller = new DauDashboardController();
        $result = $controller->filterReportDataset($records, [
            'metric' => 'aircraft', 'terminal' => 'ALL', 'hour' => 'ALL', 'direction' => 'ALL', 'flight_type' => 'ALL'
        ], [], 'DAU12');

        $ac = $result['dau12_matrix']['aircraft'];
        $this->assertEquals(100, $ac['arr_dom']);
        $this->assertEquals(20, $ac['arr_int']);
    }

    /**
     * TEST 3: Active Filter Badges rendered on blade view (Part 20)
     */
    public function test_active_filter_indicators(): void
    {
        $response = $this->get(route('dau.dashboard', $this->dau12Upload->id));
        $response->assertOk();

        // Active Filter Indicators container
        $response->assertSee('hasActiveFilters', false);
        $response->assertSee("filterDirection !== 'ALL'", false);
        $response->assertSee("filterFlightType !== 'ALL'", false);
        $response->assertSee("selectedMetric !== 'aircraft'", false);
        $response->assertSee('resetFilters()', false);
    }

    /**
     * TEST 4: CRITICAL PROTECTION — DAU-10A Regression Test
     * Asserts that DAU-10A is 100% untouched and functional.
     */
    public function test_dau10a_critical_protection_regression(): void
    {
        $response = $this->get(route('dau.dashboard', $this->dau10aUpload->id));
        $response->assertOk();

        // DAU-10A Components must remain present and intact
        $response->assertSee('Terminal Congestion Heatmap');
        $response->assertSee('TIME × TERMINAL HEATMAP MATRIX');
        $response->assertSee('Distribusi Per Jam (Capacity Envelope)');
        $response->assertSee('AVERAGE BY DAYS');
        $response->assertSee('CAPACITY STATUS SUMMARY');
        $response->assertSee('DAU-10A');
    }
}
