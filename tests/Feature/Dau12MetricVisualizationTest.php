<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\Parsers\DAU12Parser;
use App\Http\Controllers\DauDashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau12MetricVisualizationTest extends TestCase
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
     * TEST 1: Aircraft and Passenger metrics produce substantially different values
     */
    public function test_aircraft_and_passenger_metrics_produce_distinct_values(): void
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

        $acMatrix = $result['dau12_matrix']['aircraft'];
        $pxMatrix = $result['dau12_matrix']['passenger'];

        $acTotal = $acMatrix['arr_dom'] + $acMatrix['arr_int'] + $acMatrix['dep_dom'] + $acMatrix['dep_int'];
        $pxTotal = $pxMatrix['arr_dom'] + $pxMatrix['arr_int'] + $pxMatrix['dep_dom'] + $pxMatrix['dep_int'];

        $this->assertGreaterThan(0, $acTotal);
        $this->assertGreaterThan(0, $pxTotal);
        $this->assertNotEquals($acTotal, $pxTotal, 'Passenger total must differ substantially from Aircraft total');
        $this->assertGreaterThan($acTotal * 20, $pxTotal, 'Passenger values must be orders of magnitude larger than Aircraft movements');
    }

    /**
     * TEST 2: Deterministic Aircraft Test (Part 26)
     * Record A: ARR DOM=10, ARR INT=2, DEP DOM=8, DEP INT=1
     * Record B: ARR DOM=20, ARR INT=3, DEP DOM=7, DEP INT=2
     * Expected: ARR DOM=30, ARR INT=5, DEP DOM=15, DEP INT=3
     */
    public function test_deterministic_aircraft_fixture(): void
    {
        $records = [
            [
                'no' => 1, 'date' => '01/01/2026',
                'aircraft_arr_domestic' => 10, 'aircraft_arr_int' => 2,
                'aircraft_dep_domestic' => 8,  'aircraft_dep_int' => 1,
                'passenger_arr_domestic'=> 100, 'passenger_arr_int' => 20,
                'passenger_dep_domestic'=> 80,  'passenger_dep_int' => 10,
            ],
            [
                'no' => 2, 'date' => '02/01/2026',
                'aircraft_arr_domestic' => 20, 'aircraft_arr_int' => 3,
                'aircraft_dep_domestic' => 7,  'aircraft_dep_int' => 2,
                'passenger_arr_domestic'=> 200, 'passenger_arr_int' => 30,
                'passenger_dep_domestic'=> 70,  'passenger_dep_int' => 20,
            ]
        ];

        $controller = new DauDashboardController();
        $result = $controller->filterReportDataset($records, [
            'metric' => 'aircraft', 'terminal' => 'ALL', 'hour' => 'ALL', 'direction' => 'ALL', 'flight_type' => 'ALL'
        ], [], 'DAU12');

        $ac = $result['dau12_matrix']['aircraft'];
        $this->assertEquals(30, $ac['arr_dom'], 'Expected ARR DOM = 30');
        $this->assertEquals(5,  $ac['arr_int'], 'Expected ARR INT = 5');
        $this->assertEquals(15, $ac['dep_dom'], 'Expected DEP DOM = 15');
        $this->assertEquals(3,  $ac['dep_int'], 'Expected DEP INT = 3');
    }

    /**
     * TEST 3: Deterministic Passenger Test (Part 27)
     * Record A: ARR DOM=1000, ARR INT=200, DEP DOM=800, DEP INT=100
     * Record B: ARR DOM=2000, ARR INT=300, DEP DOM=700, DEP INT=200
     * Expected: ARR DOM=3000, ARR INT=500, DEP DOM=1500, DEP INT=300
     */
    public function test_deterministic_passenger_fixture(): void
    {
        $records = [
            [
                'no' => 1, 'date' => '01/01/2026',
                'aircraft_arr_domestic' => 1, 'aircraft_arr_int' => 1,
                'aircraft_dep_domestic' => 1, 'aircraft_dep_int' => 1,
                'passenger_arr_domestic' => 1000, 'passenger_arr_int' => 200,
                'passenger_dep_domestic' => 800,  'passenger_dep_int' => 100,
            ],
            [
                'no' => 2, 'date' => '02/01/2026',
                'aircraft_arr_domestic' => 2, 'aircraft_arr_int' => 2,
                'aircraft_dep_domestic' => 2, 'aircraft_dep_int' => 2,
                'passenger_arr_domestic' => 2000, 'passenger_arr_int' => 300,
                'passenger_dep_domestic' => 700,  'passenger_dep_int' => 200,
            ]
        ];

        $controller = new DauDashboardController();
        $result = $controller->filterReportDataset($records, [
            'metric' => 'passenger', 'terminal' => 'ALL', 'hour' => 'ALL', 'direction' => 'ALL', 'flight_type' => 'ALL'
        ], [], 'DAU12');

        $px = $result['dau12_matrix']['passenger'];
        $this->assertEquals(3000, $px['arr_dom'], 'Expected ARR DOM = 3000');
        $this->assertEquals(500,  $px['arr_int'], 'Expected ARR INT = 500');
        $this->assertEquals(1500, $px['dep_dom'], 'Expected DEP DOM = 1500');
        $this->assertEquals(300,  $px['dep_int'], 'Expected DEP INT = 300');
    }

    /**
     * TEST 4: Metric Separation Test (Part 28)
     * Given Aircraft: 100, Passenger: 5000.
     * Aircraft mode = 100, Passenger mode = 5000, never cross-pollinated.
     */
    public function test_metric_separation_part_28(): void
    {
        $records = [
            [
                'no' => 1, 'date' => '01/01/2026',
                'aircraft_arr_domestic' => 100, 'aircraft_arr_int' => 0,
                'aircraft_dep_domestic' => 0,   'aircraft_dep_int' => 0,
                'passenger_arr_domestic'=> 5000,'passenger_arr_int' => 0,
                'passenger_dep_domestic'=> 0,   'passenger_dep_int' => 0,
            ]
        ];

        $controller = new DauDashboardController();
        $result = $controller->filterReportDataset($records, [
            'metric' => 'aircraft', 'terminal' => 'ALL', 'hour' => 'ALL', 'direction' => 'ALL', 'flight_type' => 'ALL'
        ], [], 'DAU12');

        $this->assertEquals(100, $result['dau12_matrix']['aircraft']['arr_dom']);
        $this->assertEquals(5000, $result['dau12_matrix']['passenger']['arr_dom']);
        $this->assertNotEquals(5000, $result['dau12_matrix']['aircraft']['arr_dom']);
        $this->assertNotEquals(100, $result['dau12_matrix']['passenger']['arr_dom']);
    }
}
