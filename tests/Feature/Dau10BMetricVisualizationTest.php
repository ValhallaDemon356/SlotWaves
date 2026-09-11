<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\Parsers\DAU10BParser;
use App\Http\Controllers\DauDashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau10BMetricVisualizationTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private Upload $dau10bUpload;
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

        // Parse authentic DAU-10B fixture
        $parser = new DAU10BParser();
        $samplePath = base_path('resources/templates/dau/DAU-10B.xls');
        $this->parsedData = $parser->parse($samplePath);

        $this->dau10bUpload = Upload::create([
            'original_filename' => 'DAU-10B.xls',
            'stored_path'       => 'uploads/dau10b.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU10B',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $this->parsedData,
            'source_type'       => 'excel',
            'parser_metadata'   => $this->parsedData,
        ]);
    }

    /**
     * TEST 1: DAU-10B Dashboard renders correctly with Block On/Off canvas, title, and legend bindings
     */
    public function test_dau10b_dashboard_renders_block_on_off_components(): void
    {
        $response = $this->get(route('dau.dashboard', $this->dau10bUpload->id));
        $response->assertOk();

        // Canvas container
        $response->assertSee('id="dau10bBlockChart"', false);

        // Dynamic title adapting by metric
        $response->assertSee("selectedMetric === 'passenger' ? 'BLOCK ON (DTG) VS BLOCK OFF (BRK) HOURLY PASSENGER COMPARISON' : 'BLOCK ON (DTG) VS BLOCK OFF (BRK) HOURLY AIRCRAFT COMPARISON'", false);

        // Dynamic legend adapting by metric
        $response->assertSee("selectedMetric === 'passenger' ? 'Block On (DTG) — Passenger' : 'Block On (DTG)'", false);
        $response->assertSee("selectedMetric === 'passenger' ? 'Block Off (BRK) — Passenger' : 'Block Off (BRK)'", false);

        // Hourly summary table exists
        $response->assertSee('Hourly Passenger Summary (DTG vs BRK)', false);
        $response->assertSee('Hourly Aircraft Summary (DTG vs BRK)', false);

        // Empty state guard
        $response->assertSee('dau10bNoData', false);
        $response->assertSee('NO DATA AVAILABLE');
    }

    /**
     * TEST 2: Aircraft Mode vs Passenger Mode produces distinct data in controller filterReportDataset
     */
    public function test_aircraft_and_passenger_data_differ_substantially(): void
    {
        $controller = new DauDashboardController();
        $records = $this->parsedData['records'];
        $meta = $this->parsedData['meta'];

        // 1. Aircraft mode
        $filtersAc = [
            'metric'    => 'aircraft',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'operation' => 'ALL',
            'direction' => 'ALL',
        ];
        $analyticsAc = $controller->filterReportDataset($records, $filtersAc, $meta, 'DAU10B');

        // 2. Passenger mode
        $filtersPax = [
            'metric'    => 'passenger',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'operation' => 'ALL',
            'direction' => 'ALL',
        ];
        $analyticsPax = $controller->filterReportDataset($records, $filtersPax, $meta, 'DAU10B');

        // Total movements in Aircraft vs Passenger
        $this->assertGreaterThan(0, $analyticsAc['summary']['aircraft_arrival']);
        $this->assertGreaterThan(0, $analyticsPax['summary']['passenger_arrival']);

        // Passenger totals must be orders of magnitude higher than aircraft movements
        $this->assertGreaterThan(
            $analyticsAc['summary']['aircraft_arrival'] * 10,
            $analyticsPax['summary']['passenger_arrival'],
            "Passenger arrival must be much greater than aircraft arrival in authentic data"
        );

        // Hourly distribution values must differ
        $hour0Ac = $analyticsAc['hourly_distribution'][0];
        $hour0Pax = $analyticsPax['hourly_distribution'][0];

        $this->assertEquals($hour0Ac['hour'], $hour0Pax['hour']);
        $this->assertNotEquals(
            $hour0Ac['aircraft_arrival'],
            $hour0Pax['passenger_arrival'],
            "Hourly aircraft Block On must not equal passenger Block On"
        );

        // Peaks must reflect metric
        $this->assertGreaterThan(
            $analyticsAc['peaks']['peak_block_on'],
            $analyticsPax['peaks']['peak_block_on'],
            "Passenger peak Block On must exceed aircraft peak Block On"
        );
    }

    /**
     * TEST 3: Deterministic fixture test (Part 59 requirement)
     */
    public function test_deterministic_fixture_proves_metric_separation(): void
    {
        $controller = new DauDashboardController();
        $meta = ['flight_scope' => 'DOMESTIK & INTERNASIONAL', 'terminal_scope' => 'ALL'];

        // Two deterministic records
        $mockRecords = [
            [
                'no'                  => 1,
                'hour'                => '08.01 - 09.00',
                'terminal'            => '1A',
                'aircraft_arrival'    => 1,
                'aircraft_departure'  => 0,
                'aircraft_total'      => 1,
                'passenger_arrival'   => 100,
                'passenger_departure' => 0,
                'passenger_total'     => 100,
            ],
            [
                'no'                  => 2,
                'hour'                => '08.01 - 09.00',
                'terminal'            => '1B',
                'aircraft_arrival'    => 1,
                'aircraft_departure'  => 2,
                'aircraft_total'      => 3,
                'passenger_arrival'   => 250,
                'passenger_departure' => 300,
                'passenger_total'     => 550,
            ],
        ];

        // Aircraft mode aggregation
        $resAc = $controller->filterReportDataset($mockRecords, [
            'metric'    => 'aircraft',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'operation' => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $hAc = $resAc['hourly_distribution'][0];
        $this->assertEquals(2, $hAc['aircraft_arrival'], "DTG 08 aircraft count should be 1 + 1 = 2");
        $this->assertEquals(2, $hAc['aircraft_departure'], "BRK 08 aircraft count should be 0 + 2 = 2");

        // Passenger mode aggregation
        $resPax = $controller->filterReportDataset($mockRecords, [
            'metric'    => 'passenger',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'operation' => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $hPax = $resPax['hourly_distribution'][0];
        $this->assertEquals(350, $hPax['passenger_arrival'], "DTG 08 passenger count should be 100 + 250 = 350");
        $this->assertEquals(300, $hPax['passenger_departure'], "BRK 08 passenger count should be 0 + 300 = 300");

        // Confirms peak block on
        $this->assertEquals(2, $resAc['peaks']['peak_block_on']);
        $this->assertEquals(350, $resPax['peaks']['peak_block_on']);
    }
}
