<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\Parsers\DAU10BParser;
use App\Http\Controllers\DauDashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau10BFilterChartSyncTest extends TestCase
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
     * TEST 1: Terminal filter isolates specified terminal
     */
    public function test_terminal_filter_isolates_specified_terminal(): void
    {
        $controller = new DauDashboardController();
        $records = $this->parsedData['records'];
        $meta = $this->parsedData['meta'];

        $resAll = $controller->filterReportDataset($records, [
            'terminal'  => 'ALL',
            'metric'    => 'aircraft',
            'hour'      => 'ALL',
            'operation' => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $res1B = $controller->filterReportDataset($records, [
            'terminal'  => '1B',
            'metric'    => 'aircraft',
            'hour'      => 'ALL',
            'operation' => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $this->assertLessThan(
            count($resAll['filtered_records']),
            count($res1B['filtered_records']),
            "Filtered records for Terminal 1B must be fewer than ALL terminals"
        );

        foreach ($res1B['filtered_records'] as $r) {
            $this->assertEquals('1B', $r['terminal'], "All filtered records must belong to Terminal 1B");
        }
    }

    /**
     * TEST 2: Operation filter (BLOCK_ON vs BLOCK_OFF vs ALL)
     */
    public function test_operation_filter_restricts_datasets_correctly(): void
    {
        $controller = new DauDashboardController();
        $records = $this->parsedData['records'];
        $meta = $this->parsedData['meta'];

        // 1. BLOCK_ON in Aircraft Mode
        $resOnAc = $controller->filterReportDataset($records, [
            'operation' => 'BLOCK_ON',
            'metric'    => 'aircraft',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $this->assertGreaterThan(0, $resOnAc['summary']['aircraft_arrival']);
        $this->assertEquals(0, $resOnAc['summary']['aircraft_departure'], "Block Off departure count must be 0 when operation=BLOCK_ON");
        $this->assertEquals($resOnAc['summary']['aircraft_arrival'], $resOnAc['summary']['total_movements']);

        // 2. BLOCK_OFF in Aircraft Mode
        $resOffAc = $controller->filterReportDataset($records, [
            'operation' => 'BLOCK_OFF',
            'metric'    => 'aircraft',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $this->assertGreaterThan(0, $resOffAc['summary']['aircraft_departure']);
        $this->assertEquals(0, $resOffAc['summary']['aircraft_arrival'], "Block On arrival count must be 0 when operation=BLOCK_OFF");
        $this->assertEquals($resOffAc['summary']['aircraft_departure'], $resOffAc['summary']['total_movements']);

        // 3. BLOCK_ON in Passenger Mode
        $resOnPax = $controller->filterReportDataset($records, [
            'operation' => 'BLOCK_ON',
            'metric'    => 'passenger',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $this->assertGreaterThan(0, $resOnPax['summary']['passenger_arrival']);
        $this->assertEquals(0, $resOnPax['summary']['passenger_departure'], "Block Off passenger departure count must be 0 when operation=BLOCK_ON");

        // 4. BLOCK_OFF in Passenger Mode
        $resOffPax = $controller->filterReportDataset($records, [
            'operation' => 'BLOCK_OFF',
            'metric'    => 'passenger',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $this->assertGreaterThan(0, $resOffPax['summary']['passenger_departure']);
        $this->assertEquals(0, $resOffPax['summary']['passenger_arrival'], "Block On passenger arrival count must be 0 when operation=BLOCK_OFF");
    }

    /**
     * TEST 3: Direction filter (ARRIVAL vs DEPARTURE)
     */
    public function test_direction_filter_restricts_arrival_and_departure(): void
    {
        $controller = new DauDashboardController();
        $records = $this->parsedData['records'];
        $meta = $this->parsedData['meta'];

        $resArr = $controller->filterReportDataset($records, [
            'direction' => 'ARRIVAL',
            'metric'    => 'aircraft',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'operation' => 'ALL',
        ], $meta, 'DAU10B');

        $this->assertGreaterThan(0, $resArr['summary']['aircraft_arrival']);
        $this->assertEquals(0, $resArr['summary']['aircraft_departure']);

        $resDep = $controller->filterReportDataset($records, [
            'direction' => 'DEPARTURE',
            'metric'    => 'aircraft',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'operation' => 'ALL',
        ], $meta, 'DAU10B');

        $this->assertGreaterThan(0, $resDep['summary']['aircraft_departure']);
        $this->assertEquals(0, $resDep['summary']['aircraft_arrival']);
    }

    /**
     * TEST 4: Hour filter restricts records to single hour
     */
    public function test_hour_filter_isolates_selected_hour(): void
    {
        $controller = new DauDashboardController();
        $records = $this->parsedData['records'];
        $meta = $this->parsedData['meta'];

        $targetHour = '00.01 - 01.00';
        $resHour = $controller->filterReportDataset($records, [
            'hour'      => $targetHour,
            'metric'    => 'aircraft',
            'terminal'  => 'ALL',
            'operation' => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $this->assertGreaterThan(0, count($resHour['filtered_records']));
        foreach ($resHour['filtered_records'] as $r) {
            $this->assertEquals($targetHour, $r['hour']);
        }
        $this->assertCount(1, $resHour['hourly_distribution']);
        $this->assertEquals($targetHour, $resHour['hourly_distribution'][0]['hour']);
    }

    /**
     * TEST 5: Filter combination (Terminal + Operation + Passenger Metric)
     */
    public function test_filter_combination_terminal_operation_passenger(): void
    {
        $controller = new DauDashboardController();
        $records = $this->parsedData['records'];
        $meta = $this->parsedData['meta'];

        $resComb = $controller->filterReportDataset($records, [
            'terminal'  => '1B',
            'operation' => 'BLOCK_ON',
            'metric'    => 'passenger',
            'hour'      => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        foreach ($resComb['filtered_records'] as $r) {
            $this->assertEquals('1B', $r['terminal']);
            $this->assertGreaterThan(0, (int)($r['passenger_arrival'] ?? 0));
        }
        $this->assertEquals(0, $resComb['summary']['passenger_departure']);
    }
}
