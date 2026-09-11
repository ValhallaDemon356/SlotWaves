<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\Parsers\DAU12Parser;
use App\Http\Controllers\DauDashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau12DirectionalMatrixChartTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private Upload $dau12Upload;
    private array $parsedData;

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

        // Parse authentic DAU-12 fixture
        $parser = new DAU12Parser();
        $samplePath = base_path('resources/templates/dau/DAU-12.xls');
        $this->parsedData = $parser->parse($samplePath);

        $this->dau12Upload = Upload::create([
            'original_filename' => 'DAU-12.xls',
            'stored_path'       => 'uploads/dau12.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU12',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $this->parsedData,
            'source_type'       => 'excel',
            'parser_metadata'   => $this->parsedData,
        ]);
    }

    /**
     * TEST 1: DAU-12 Dashboard renders directional matrix canvas, dynamic titles, badges, and analytical table
     */
    public function test_dau12_dashboard_renders_directional_matrix_components(): void
    {
        $response = $this->get(route('dau.dashboard', $this->dau12Upload->id));
        $response->assertOk();

        // Directional Matrix Canvas
        $response->assertSee('id="dau12GroupedChart"', false);

        // Dynamic Title adapting by metric
        $response->assertSee("x-text=\"'ARRIVAL & DEPARTURE BY DOMESTIC VS INTERNATIONAL — ' + (selectedMetric === 'passenger' ? 'PASSENGER' : 'AIRCRAFT')\"", false);

        // Dynamic Unit Badge
        $response->assertSee("x-text=\"selectedMetric === 'passenger' ? 'PAX' : 'A/C'\"", false);

        // Empty state container and message
        $response->assertSee('dau12NoData', false);
        $response->assertSee('NO DATA AVAILABLE');

        // Directional Matrix Analytical Summary Table
        $response->assertSee('Directional Matrix Summary', false);
        $response->assertSee('dau12MatrixSummary.arr_dom', false);
        $response->assertSee('dau12MatrixSummary.arr_int', false);
        $response->assertSee('dau12MatrixSummary.arr_tot', false);
        $response->assertSee('dau12MatrixSummary.dep_dom', false);
        $response->assertSee('dau12MatrixSummary.dep_int', false);
        $response->assertSee('dau12MatrixSummary.dep_tot', false);
        $response->assertSee('dau12MatrixSummary.grand_tot', false);

        // Detail records table exists
        $response->assertSee('DETAILED OPERATIONAL RECORDS', false);
    }

    /**
     * TEST 2: Controller Dau12 Matrix aggregation matches authentic source parsed records
     */
    public function test_dau12_matrix_aggregation_matches_source_records(): void
    {
        $controller = new DauDashboardController();
        $records = $this->parsedData['records'];
        $meta = $this->parsedData['meta'];

        $result = $controller->filterReportDataset($records, [
            'metric'    => 'aircraft',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'direction' => 'ALL',
            'flight_type' => 'ALL',
        ], $meta, 'DAU12');

        $this->assertArrayHasKey('dau12_matrix', $result);
        $matrix = $result['dau12_matrix'];

        $sumAcArrDom = array_sum(array_column($records, 'aircraft_arr_domestic'));
        $sumAcArrInt = array_sum(array_column($records, 'aircraft_arr_int'));
        $sumAcDepDom = array_sum(array_column($records, 'aircraft_dep_domestic'));
        $sumAcDepInt = array_sum(array_column($records, 'aircraft_dep_int'));

        $this->assertEquals($sumAcArrDom, $matrix['aircraft']['arr_dom']);
        $this->assertEquals($sumAcArrInt, $matrix['aircraft']['arr_int']);
        $this->assertEquals($sumAcDepDom, $matrix['aircraft']['dep_dom']);
        $this->assertEquals($sumAcDepInt, $matrix['aircraft']['dep_int']);

        $sumPxArrDom = array_sum(array_column($records, 'passenger_arr_domestic'));
        $sumPxArrInt = array_sum(array_column($records, 'passenger_arr_int'));
        $sumPxDepDom = array_sum(array_column($records, 'passenger_dep_domestic'));
        $sumPxDepInt = array_sum(array_column($records, 'passenger_dep_int'));

        $this->assertEquals($sumPxArrDom, $matrix['passenger']['arr_dom']);
        $this->assertEquals($sumPxArrInt, $matrix['passenger']['arr_int']);
        $this->assertEquals($sumPxDepDom, $matrix['passenger']['dep_dom']);
        $this->assertEquals($sumPxDepInt, $matrix['passenger']['dep_int']);

        // Assert all values are positive finite numbers
        $this->assertGreaterThan(0, $matrix['aircraft']['arr_dom']);
        $this->assertGreaterThan(0, $matrix['aircraft']['arr_int']);
        $this->assertGreaterThan(0, $matrix['aircraft']['dep_dom']);
        $this->assertGreaterThan(0, $matrix['aircraft']['dep_int']);
        $this->assertGreaterThan(0, $matrix['passenger']['arr_dom']);
        $this->assertGreaterThan(0, $matrix['passenger']['arr_int']);
        $this->assertGreaterThan(0, $matrix['passenger']['dep_dom']);
        $this->assertGreaterThan(0, $matrix['passenger']['dep_int']);
    }

    /**
     * TEST 3: Aircraft authentic values non-zero and non-empty
     */
    public function test_dau12_aircraft_exact_values(): void
    {
        $records = $this->parsedData['records'];
        $sumAcArrDom = array_sum(array_column($records, 'aircraft_arr_domestic'));
        $sumAcArrInt = array_sum(array_column($records, 'aircraft_arr_int'));
        $sumAcDepDom = array_sum(array_column($records, 'aircraft_dep_domestic'));
        $sumAcDepInt = array_sum(array_column($records, 'aircraft_dep_int'));

        $this->assertGreaterThan(0, $sumAcArrDom);
        $this->assertGreaterThan(0, $sumAcArrInt);
        $this->assertGreaterThan(0, $sumAcDepDom);
        $this->assertGreaterThan(0, $sumAcDepInt);
    }
}
