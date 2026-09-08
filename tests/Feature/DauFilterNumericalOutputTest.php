<?php

namespace Tests\Feature;

use App\Http\Controllers\DauDashboardController;
use App\Models\Airport;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DauFilterNumericalOutputTest extends TestCase
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
     * Helper to invoke filterReportDataset via reflection
     */
    private function filterDataset(string $reportType, array $records, Request $request, array $meta = []): array
    {
        return $this->controller->filterReportDataset($records, $request->all(), $meta, $reportType);
    }

    /**
     * TEST 1: DAU-01 Direction and Passenger Type filtering.
     * Numerical isolation: ADULT vs CHILD vs INFANT, and ARRIVAL vs DEPARTURE.
     */
    public function test_dau01_direction_and_passenger_type_numerical_isolation(): void
    {
        $records = [
            [
                'airline'            => 'Garuda Indonesia',
                'airport_route'      => 'DPS',
                'aircraft_arrival'   => 1,
                'aircraft_departure' => 0,
                'aircraft_total'     => 1,
                'passenger_arrival'  => 150,
                'passenger_departure'=> 0,
                'passenger_total'    => 150,
                'passenger_adult'    => 120,
                'passenger_child'    => 20,
                'passenger_infant'   => 10,
                'arr_adult'          => 120,
                'arr_child'          => 20,
                'arr_infant'         => 10,
                'dep_adult'          => 0,
                'dep_child'          => 0,
                'dep_infant'         => 0,
            ],
            [
                'airline'            => 'Garuda Indonesia',
                'airport_route'      => 'SUB',
                'aircraft_arrival'   => 0,
                'aircraft_departure' => 1,
                'aircraft_total'     => 1,
                'passenger_arrival'  => 0,
                'passenger_departure'=> 100,
                'passenger_total'    => 100,
                'passenger_adult'    => 80,
                'passenger_child'    => 15,
                'passenger_infant'   => 5,
                'arr_adult'          => 0,
                'arr_child'          => 0,
                'arr_infant'         => 0,
                'dep_adult'          => 80,
                'dep_child'          => 15,
                'dep_infant'         => 5,
            ],
            [
                'airline'            => 'Lion Air',
                'airport_route'      => 'KNO',
                'aircraft_arrival'   => 1,
                'aircraft_departure' => 1,
                'aircraft_total'     => 2,
                'passenger_arrival'  => 180,
                'passenger_departure'=> 170,
                'passenger_total'    => 350,
                'passenger_adult'    => 300,
                'passenger_child'    => 40,
                'passenger_infant'   => 10,
                'arr_adult'          => 150,
                'arr_child'          => 20,
                'arr_infant'         => 10,
                'dep_adult'          => 150,
                'dep_child'          => 20,
                'dep_infant'         => 0,
            ],
        ];

        // 1. Unfiltered baseline
        $reqAll = new Request(['direction' => 'ALL', 'metric' => 'aircraft']);
        $resAll = $this->filterDataset('DAU1', $records, $reqAll);
        $this->assertCount(3, $resAll['filtered_records']);
        $this->assertEquals(4, $resAll['summary']['total_movements']);
        $this->assertEquals(600, $resAll['summary']['passenger_total']);

        // 2. Filter Direction = ARRIVAL
        $reqArr = new Request(['direction' => 'ARRIVAL', 'metric' => 'aircraft']);
        $resArr = $this->filterDataset('DAU1', $records, $reqArr);
        $this->assertCount(2, $resArr['filtered_records']); // Garuda DPS and Lion Air KNO
        $this->assertEquals(2, $resArr['summary']['total_movements']); // only arrivals: 1 + 1 = 2
        $this->assertEquals(330, $resArr['summary']['passenger_total']); // 150 + 180 = 330

        // 3. Filter Direction = DEPARTURE
        $reqDep = new Request(['direction' => 'DEPARTURE', 'metric' => 'aircraft']);
        $resDep = $this->filterDataset('DAU1', $records, $reqDep);
        $this->assertCount(2, $resDep['filtered_records']); // Garuda SUB and Lion Air KNO
        $this->assertEquals(2, $resDep['summary']['total_movements']); // only departures: 1 + 1 = 2
        $this->assertEquals(270, $resDep['summary']['passenger_total']); // 100 + 170 = 270

        // 4. Passenger Type = ADULT only
        $reqAdult = new Request(['direction' => 'ALL', 'metric' => 'passenger', 'passenger_type' => 'ADULT']);
        $resAdult = $this->filterDataset('DAU1', $records, $reqAdult);
        $this->assertEquals(500, $resAdult['summary']['passenger_total']); // 120 + 80 + 300 = 500
        $this->assertEquals(500, $resAdult['summary']['passenger_adult']);
        $this->assertEquals(0, $resAdult['summary']['passenger_child']);
        $this->assertEquals(0, $resAdult['summary']['passenger_infant']);

        // 5. Passenger Type = CHILD only
        $reqChild = new Request(['direction' => 'ALL', 'metric' => 'passenger', 'passenger_type' => 'CHILD']);
        $resChild = $this->filterDataset('DAU1', $records, $reqChild);
        $this->assertEquals(75, $resChild['summary']['passenger_total']); // 20 + 15 + 40 = 75
        $this->assertEquals(0, $resChild['summary']['passenger_adult']);
        $this->assertEquals(75, $resChild['summary']['passenger_child']);

        // 6. Airline Filter = Garuda Indonesia (intersection)
        $reqGA = new Request(['airline' => 'Garuda Indonesia', 'direction' => 'ARRIVAL']);
        $resGA = $this->filterDataset('DAU1', $records, $reqGA);
        $this->assertCount(1, $resGA['filtered_records']); // only Garuda DPS
        $this->assertEquals(1, $resGA['summary']['total_movements']);
        $this->assertEquals(150, $resGA['summary']['passenger_total']);
    }

    /**
     * TEST 2: DAU-02 Comparative Breakdown responds to metric switch.
     */
    public function test_dau02_comparative_breakdown_numerical_correctness(): void
    {
        $records = [
            [
                'category'       => 'DOMESTIK',
                'aircraft_total' => 10,
                'passenger_total'=> 1000,
                'baggage'        => 5000,
                'cargo'          => 2000,
                'pos'            => 300,
            ],
            [
                'category'       => 'INTERNASIONAL',
                'aircraft_total' => 5,
                'passenger_total'=> 800,
                'baggage'        => 4000,
                'cargo'          => 3000,
                'pos'            => 200,
            ],
        ];

        $req = new Request(['metric' => 'aircraft']);
        $res = $this->filterDataset('DAU2', $records, $req);

        $comp = $res['dau2_comparative'];
        $this->assertEquals(10, $comp['aircraft']['domestic']);
        $this->assertEquals(5, $comp['aircraft']['international']);
        $this->assertEquals(15, $comp['aircraft']['total']);

        $this->assertEquals(1000, $comp['passenger']['domestic']);
        $this->assertEquals(800, $comp['passenger']['international']);
        $this->assertEquals(1800, $comp['passenger']['total']);

        $this->assertEquals(5000, $comp['baggage']['domestic']);
        $this->assertEquals(4000, $comp['baggage']['international']);
        $this->assertEquals(9000, $comp['baggage']['total']);
    }

    /**
     * TEST 3: DAU-03 Status Usaha and Direction filtering.
     */
    public function test_dau03_status_and_direction_filtering(): void
    {
        $records = [
            [
                'section'          => 'NIAGA BERJADWAL',
                'category'         => 'DOMESTIK',
                'aircraft_arrival' => 2,
                'aircraft_departure'=> 0,
                'aircraft_total'   => 2,
                'passenger_total'  => 300,
            ],
            [
                'section'          => 'BUKAN NIAGA',
                'category'         => 'DOMESTIK',
                'aircraft_arrival' => 0,
                'aircraft_departure'=> 1,
                'aircraft_total'   => 1,
                'passenger_total'  => 10,
            ],
        ];

        // Filter status = BUKAN NIAGA
        $reqBukan = new Request(['status' => 'BUKAN NIAGA']);
        $resBukan = $this->filterDataset('DAU3', $records, $reqBukan);
        $this->assertCount(1, $resBukan['filtered_records']);
        $this->assertEquals(1, $resBukan['summary']['total_movements']);
        $this->assertEquals(10, $resBukan['summary']['passenger_total']);
    }

    /**
     * TEST 4: DAU-05 Airline Pareto sorting and multi-metric.
     */
    public function test_dau05_airline_ranking_and_metric(): void
    {
        $records = [
            [
                'airline'        => 'Garuda Indonesia',
                'aircraft_total' => 5,
                'passenger_total'=> 500,
                'cargo'          => 20000,
            ],
            [
                'airline'        => 'Lion Air',
                'aircraft_total' => 20,
                'passenger_total'=> 3000,
                'cargo'          => 1000,
            ],
        ];

        // Aircraft metric: Lion Air ranks #1
        $reqAc = new Request(['metric' => 'aircraft']);
        $resAc = $this->filterDataset('DAU5', $records, $reqAc);
        $this->assertEquals('Lion Air', $resAc['dau5_pareto'][0]['airline']);

        // Cargo metric: Garuda ranks #1
        $reqCgo = new Request(['metric' => 'cargo']);
        $resCgo = $this->filterDataset('DAU5', $records, $reqCgo);
        $this->assertEquals('Garuda Indonesia', $resCgo['dau5_pareto'][0]['airline']);
    }

    /**
     * TEST 5: DAU-05B Terminal and Airline AND logic.
     */
    public function test_dau05b_terminal_and_airline_combined_filter(): void
    {
        $records = [
            ['terminal' => '1A', 'airline' => 'Super Air Jet', 'aircraft_total' => 10],
            ['terminal' => '2F', 'airline' => 'Garuda Indonesia', 'aircraft_total' => 25],
            ['terminal' => '2F', 'airline' => 'Batik Air', 'aircraft_total' => 15],
            ['terminal' => '3',  'airline' => 'Garuda Indonesia', 'aircraft_total' => 30],
        ];

        // Filter Terminal 2F AND Airline Garuda Indonesia
        $req = new Request(['terminal' => '2F', 'airline' => 'Garuda Indonesia']);
        $res = $this->filterDataset('DAU5B', $records, $req);

        $this->assertCount(1, $res['filtered_records']);
        $this->assertEquals('2F', $res['filtered_records'][0]['terminal']);
        $this->assertEquals('Garuda Indonesia', $res['filtered_records'][0]['airline']);
        $this->assertEquals(25, $res['summary']['total_movements']);
    }

    /**
     * TEST 6: Zero-result state test.
     */
    public function test_zero_matching_records_returns_empty_and_zero_summary(): void
    {
        $records = [
            ['terminal' => '1A', 'airline' => 'Super Air Jet', 'aircraft_total' => 10],
        ];

        $req = new Request(['terminal' => '3', 'airline' => 'NonExistentAir']);
        $res = $this->filterDataset('DAU5B', $records, $req);

        $this->assertCount(0, $res['filtered_records']);
        $this->assertEquals(0, $res['summary']['total_movements']);
        $this->assertEquals(0, $res['summary']['passenger_total']);
    }

    /**
     * TEST 7: DAU-04 Top N and Origin/Destination diverging.
     */
    public function test_dau04_top_n_and_diverging(): void
    {
        $records = [
            ['airport_route' => 'DPS', 'aircraft_arrival' => 50, 'aircraft_departure' => 45],
            ['airport_route' => 'SUB', 'aircraft_arrival' => 30, 'aircraft_departure' => 35],
            ['airport_route' => 'KNO', 'aircraft_arrival' => 20, 'aircraft_departure' => 25],
            ['airport_route' => 'UPG', 'aircraft_arrival' => 15, 'aircraft_departure' => 10],
            ['airport_route' => 'YIA', 'aircraft_arrival' => 10, 'aircraft_departure' => 12],
            ['airport_route' => 'SRG', 'aircraft_arrival' => 8,  'aircraft_departure' => 9],
        ];

        $reqTop5 = new Request(['top_n' => 5, 'metric' => 'aircraft']);
        $resTop5 = $this->filterDataset('DAU4', $records, $reqTop5);

        $this->assertCount(5, $resTop5['dau4_diverging']['top_arrival']);
        $this->assertCount(5, $resTop5['dau4_diverging']['top_departure']);
        $this->assertEquals('DPS', $resTop5['dau4_diverging']['top_arrival'][0]['airport_route']);
        $this->assertEquals('DPS', $resTop5['dau4_diverging']['top_departure'][0]['airport_route']);
    }

    /**
     * TEST 8: DAU-04A Operator and Airport intersection.
     */
    public function test_dau04a_operator_and_airport_intersection(): void
    {
        $records = [
            ['operator_name' => 'Garuda Indonesia', 'airport' => 'DPS', 'aircraft_total' => 20, 'passenger_total' => 3000],
            ['operator_name' => 'Garuda Indonesia', 'airport' => 'SUB', 'aircraft_total' => 15, 'passenger_total' => 2200],
            ['operator_name' => 'Lion Air',         'airport' => 'DPS', 'aircraft_total' => 25, 'passenger_total' => 4000],
        ];

        $req = new Request(['airline' => 'Garuda Indonesia', 'airport' => 'DPS']);
        $res = $this->filterDataset('DAU4A', $records, $req);

        $this->assertCount(1, $res['filtered_records']);
        $this->assertEquals('Garuda Indonesia', $res['filtered_records'][0]['operator_name']);
        $this->assertEquals('DPS', $res['filtered_records'][0]['airport']);
        $this->assertEquals(20, $res['summary']['total_movements']);
        $this->assertEquals(3000, $res['summary']['passenger_total']);
    }

    /**
     * TEST 9: DAU-04B Minimum Flight Threshold.
     */
    public function test_dau04b_threshold_filtering(): void
    {
        $records = [
            ['city' => 'Denpasar', 'airline' => 'Garuda Indonesia', 'aircraft_total' => 10],
            ['city' => 'Surabaya', 'airline' => 'Lion Air',         'aircraft_total' => 4],
            ['city' => 'Medan',    'airline' => 'Citilink',         'aircraft_total' => 2],
        ];

        $req = new Request(['threshold' => 5]);
        $res = $this->filterDataset('DAU4B', $records, $req);

        $this->assertCount(1, $res['filtered_records']);
        $this->assertEquals('Denpasar', $res['filtered_records'][0]['city']);
        $this->assertEquals(10, $res['summary']['total_movements']);
    }

    /**
     * TEST 10: DAU-05A Operating Crew and Extra Crew isolation.
     */
    public function test_dau05a_crew_breakdown(): void
    {
        $records = [
            [
                'airline'        => 'Garuda Indonesia',
                'crew'           => 120,
                'extra_crew'     => 15,
                'crew_total'     => 135,
                'aircraft_total' => 10,
            ],
            [
                'airline'        => 'Lion Air',
                'crew'           => 200,
                'extra_crew'     => 10,
                'crew_total'     => 210,
                'aircraft_total' => 25,
            ],
        ];

        $req = new Request(['airline' => 'Garuda Indonesia']);
        $res = $this->filterDataset('DAU5A', $records, $req);

        $this->assertCount(1, $res['filtered_records']);
        $this->assertEquals(135, $res['summary']['crew_total']);
        $this->assertEquals(15, $res['summary']['extra_crew_total']);
    }

    /**
     * TEST 11: DAU-05C Fleet and Aircraft Type filtering.
     */
    public function test_dau05c_fleet_filtering(): void
    {
        $records = [
            ['airline' => 'Garuda Indonesia', 'aircraft_type' => 'B777-300ER', 'category' => 'Wide Body', 'aircraft_total' => 4],
            ['airline' => 'Garuda Indonesia', 'aircraft_type' => 'B737-800',   'category' => 'Narrow Body', 'aircraft_total' => 12],
            ['airline' => 'Lion Air',         'aircraft_type' => 'B737-900ER', 'category' => 'Narrow Body', 'aircraft_total' => 20],
        ];

        $req = new Request(['aircraft_type' => 'B777-300ER']);
        $res = $this->filterDataset('DAU5C', $records, $req);

        $this->assertCount(1, $res['filtered_records']);
        $this->assertEquals('B777-300ER', $res['filtered_records'][0]['aircraft_type']);
        $this->assertEquals(4, $res['summary']['total_movements']);
    }

    /**
     * TEST 12: DAU-06 Fleet Mix and Metric Switching.
     */
    public function test_dau06_fleet_mix_metric_switch(): void
    {
        $records = [
            ['aircraft_type' => 'A320-200', 'category' => 'Narrow Body', 'aircraft_total' => 50, 'passenger_total' => 8000],
            ['aircraft_type' => 'A330-300', 'category' => 'Wide Body',   'aircraft_total' => 10, 'passenger_total' => 3500],
        ];

        $reqAc = new Request(['metric' => 'aircraft']);
        $resAc = $this->filterDataset('DAU6', $records, $reqAc);
        $this->assertEquals(60, $resAc['summary']['total_movements']);

        $reqPax = new Request(['metric' => 'passenger']);
        $resPax = $this->filterDataset('DAU6', $records, $reqPax);
        $this->assertEquals(11500, $resPax['summary']['passenger_total']);
    }

    /**
     * TEST 13: DAU-10 Hour and Metric filtering.
     */
    public function test_dau10_hour_filtering(): void
    {
        $records = [
            ['hour' => '06:00', 'aircraft_arrival' => 10, 'aircraft_departure' => 5,  'aircraft_total' => 15, 'passenger_total' => 2000],
            ['hour' => '07:00', 'aircraft_arrival' => 12, 'aircraft_departure' => 14, 'aircraft_total' => 26, 'passenger_total' => 3500],
        ];

        $req = new Request(['hour' => '06:00']);
        $res = $this->filterDataset('DAU10', $records, $req);

        $this->assertCount(1, $res['filtered_records']);
        $this->assertEquals(15, $res['summary']['total_movements']);
        $this->assertEquals(2000, $res['summary']['passenger_total']);
    }

    /**
     * TEST 14: DAU-10B Block On vs Block Off operation filter.
     */
    public function test_dau10b_operation_filter(): void
    {
        $records = [
            ['hour' => '08:00', 'terminal' => '1A', 'aircraft_arrival' => 5, 'aircraft_departure' => 0, 'aircraft_total' => 5],
            ['hour' => '08:00', 'terminal' => '1A', 'aircraft_arrival' => 0, 'aircraft_departure' => 7, 'aircraft_total' => 7],
        ];

        $reqOn = new Request(['operation' => 'BLOCK_ON']);
        $resOn = $this->filterDataset('DAU10B', $records, $reqOn);

        $this->assertCount(1, $resOn['filtered_records']);
        $this->assertEquals(5, $resOn['summary']['aircraft_arrival']);

        $reqOff = new Request(['operation' => 'BLOCK_OFF']);
        $resOff = $this->filterDataset('DAU10B', $records, $reqOff);

        $this->assertCount(1, $resOff['filtered_records']);
        $this->assertEquals(7, $resOff['summary']['aircraft_departure']);
    }

    /**
     * TEST 15: DAU-11 Traffic Flow and Direction filtering.
     */
    public function test_dau11_traffic_flow_and_direction(): void
    {
        $records = [
            [
                'passenger_dom_arrival'   => 500,
                'passenger_dom_departure' => 400,
                'passenger_dom_transit'   => 50,
                'passenger_dom_transfer'  => 30,
                'passenger_int_arrival'   => 200,
                'passenger_int_departure' => 150,
                'passenger_int_transit'   => 20,
                'passenger_int_transfer'  => 10,
                'passenger_total'         => 1360,
                'aircraft_arrival'        => 5,
                'aircraft_departure'      => 4,
                'aircraft_total'          => 9,
            ],
        ];

        $req = new Request(['direction' => 'ALL']);
        $res = $this->filterDataset('DAU11', $records, $req);

        $flow = $res['dau11_flow'];
        $this->assertEquals(500, $flow['dom_arr']);
        $this->assertEquals(400, $flow['dom_dep']);
        $this->assertEquals(200, $flow['int_arr']);
        $this->assertEquals(150, $flow['int_dep']);
        $this->assertEquals(1360, $res['summary']['passenger_total']);
    }

    /**
     * TEST 16: DAU-12 ARR/DEP and DOM/INT Matrix.
     */
    public function test_dau12_arr_dep_dom_int_matrix(): void
    {
        $records = [
            [
                'aircraft_arr_domestic'  => 100,
                'aircraft_arr_int'       => 25,
                'aircraft_dep_domestic'  => 95,
                'aircraft_dep_int'       => 20,
                'passenger_arr_domestic' => 15000,
                'passenger_arr_int'      => 4000,
                'passenger_dep_domestic' => 14000,
                'passenger_dep_int'      => 3500,
            ],
        ];

        $req = new Request();
        $res = $this->filterDataset('DAU12', $records, $req);

        $mat = $res['dau12_matrix'];
        $this->assertEquals(100, $mat['aircraft']['arr_dom']);
        $this->assertEquals(25, $mat['aircraft']['arr_int']);
        $this->assertEquals(95, $mat['aircraft']['dep_dom']);
        $this->assertEquals(20, $mat['aircraft']['dep_int']);

        $this->assertEquals(15000, $mat['passenger']['arr_dom']);
        $this->assertEquals(4000, $mat['passenger']['arr_int']);
        $this->assertEquals(14000, $mat['passenger']['dep_dom']);
        $this->assertEquals(3500, $mat['passenger']['dep_int']);
    }
}
