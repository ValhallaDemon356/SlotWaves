<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use App\Models\Flight;
use App\Services\CapacityService;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NacAndOperationalSettingsRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
    }

    /**
     * TEST 1 (Section 10 & 28):
     * NAC = 6
     * RON = 1 (dep 13:40)
     * Arrival = 06:55 (dep 07:55)
     * Expected at 06:00: occupied = 2, remaining = 4, status = AVAILABLE.
     */
    public function test_1_regression_ron_and_arrival_occupancy_hour_0600(): void
    {
        $upload = Upload::create([
            'original_filename' => 'TEST_SCHEDULE.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'status' => 'completed',
            'season' => 'summer',
        ]);

        // RON departure: no arrival, departs 13:40
        $ronDep = Flight::create([
            'upload_id' => $upload->id,
            'flight_number' => 'GA102',
            'airline_code' => 'GA',
            'aircraft_type' => 'B738',
            'scheduled_time' => '13:40:00',
            'flight_type' => 'departure_domestic',
            'operating_days' => '1234567',
        ]);

        // Arrival 06:55 and paired Departure 07:55
        $arr = Flight::create([
            'upload_id' => $upload->id,
            'flight_number' => 'QG820',
            'airline_code' => 'QG',
            'aircraft_type' => 'A320',
            'scheduled_time' => '06:55:00',
            'flight_type' => 'arrival_domestic',
            'operating_days' => '1234567',
        ]);

        $dep = Flight::create([
            'upload_id' => $upload->id,
            'flight_number' => 'QG821',
            'airline_code' => 'QG',
            'aircraft_type' => 'A320',
            'scheduled_time' => '07:55:00',
            'flight_type' => 'departure_domestic',
            'operating_days' => '1234567',
        ]);

        $flights = Flight::where('upload_id', $upload->id)->get();
        $capacityService = app(CapacityService::class);
        $res = $capacityService->calculate($flights, null, 6, 20, 6);

        // Hour 06:00 (index 6)
        $hour06 = $res['hourly'][6];
        $this->assertEquals(2, $hour06['occupied'], 'Hour 06:00 occupied must be 2 (1 RON + 1 Arrival 06:55)');
        $this->assertEquals(6, $hour06['nac']);
        $this->assertEquals(4, $hour06['remaining'], 'Hour 06:00 remaining must be 4 (6 - 2 = 4)');
        $this->assertEquals('AVAILABLE', $hour06['status']);

        // Hour 07:00 (index 7)
        $hour07 = $res['hourly'][7];
        $this->assertEquals(2, $hour07['occupied'], 'Hour 07:00 occupied must be 2 (1 RON + 1 Turnaround remaining until 07:55)');
        $this->assertEquals(4, $hour07['remaining']);
        $this->assertEquals('AVAILABLE', $hour07['status']);
    }

    /**
     * TEST 2 (Section 13, 24 & 28):
     * Arrival 06:55 paired with Departure 07:55.
     * Expected: Hour 06:00 occupied = 1, Hour 07:00 occupied = 1.
     * Never double counted as 2 aircraft in the same hour.
     */
    public function test_2_regression_arrival_0655_dep_0755_no_double_count(): void
    {
        $upload = Upload::create([
            'original_filename' => 'TEST_SCHEDULE.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'status' => 'completed',
            'season' => 'summer',
        ]);

        Flight::create([
            'upload_id' => $upload->id,
            'flight_number' => 'QG820',
            'airline_code' => 'QG',
            'aircraft_type' => 'A320',
            'scheduled_time' => '06:55:00',
            'flight_type' => 'arrival_domestic',
            'operating_days' => '1234567',
        ]);

        Flight::create([
            'upload_id' => $upload->id,
            'flight_number' => 'QG821',
            'airline_code' => 'QG',
            'aircraft_type' => 'A320',
            'scheduled_time' => '07:55:00',
            'flight_type' => 'departure_domestic',
            'operating_days' => '1234567',
        ]);

        $flights = Flight::where('upload_id', $upload->id)->get();
        $capacityService = app(CapacityService::class);
        $res = $capacityService->calculate($flights, null, 6, 20, 6);

        $this->assertEquals(1, $res['hourly'][6]['occupied'], 'Hour 06:00 must count exactly 1 aircraft');
        $this->assertEquals(1, $res['hourly'][7]['occupied'], 'Hour 07:00 must count exactly 1 aircraft');
        $this->assertEquals(0, $res['hourly'][8]['occupied'], 'Hour 08:00 must be 0 (departed at 07:55)');
    }

    /**
     * TEST 3 (Section 14 & 28):
     * RON departure 13:40.
     * Hours 00:00 -> 13:00: RON contributes 1 occupancy.
     * Hour 14:00 onwards: 0 occupancy.
     */
    public function test_3_regression_ron_departure_1340_lifetime_boundary(): void
    {
        $upload = Upload::create([
            'original_filename' => 'TEST_SCHEDULE.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'status' => 'completed',
            'season' => 'summer',
        ]);

        Flight::create([
            'upload_id' => $upload->id,
            'flight_number' => 'GA102',
            'airline_code' => 'GA',
            'aircraft_type' => 'B738',
            'scheduled_time' => '13:40:00',
            'flight_type' => 'departure_domestic',
            'operating_days' => '1234567',
        ]);

        $flights = Flight::where('upload_id', $upload->id)->get();
        $capacityService = app(CapacityService::class);
        $res = $capacityService->calculate($flights, null, 0, 24, 6);

        for ($h = 0; $h <= 13; $h++) {
            $this->assertEquals(1, $res['hourly'][$h]['occupied'], "Hour {$h}:00 must have RON occupied = 1");
        }

        for ($h = 14; $h <= 23; $h++) {
            $this->assertEquals(0, $res['hourly'][$h]['occupied'], "Hour {$h}:00 must have occupied = 0 after 13:40 departure");
        }
    }

    /**
     * TEST 4 (Section 17 & 28):
     * Passenger = 5, Cargo = 3, NAC = 6.
     * Expected: occupied = 5, remaining = 1, status = AVAILABLE.
     */
    public function test_4_regression_passenger_5_cargo_3_nac_6(): void
    {
        $capacityService = app(CapacityService::class);
        $res = $capacityService->classifyHourlyStatus(5, 6, true);
        $this->assertEquals('AVAILABLE', $res['status']);
        $this->assertEquals(1, $res['remaining']);
        $this->assertEquals(0, $res['exceeded']);
    }

    /**
     * TEST 5 (Section 19 & 28):
     * Passenger = 6, Cargo = 3, NAC = 6.
     * Expected: occupied = 6, remaining = 0, status = FULL / MAX.
     */
    public function test_5_regression_passenger_6_cargo_3_nac_6_full_max(): void
    {
        $capacityService = app(CapacityService::class);
        $res = $capacityService->classifyHourlyStatus(6, 6, true);
        $this->assertEquals('FULL / MAX', $res['status']);
        $this->assertEquals(0, $res['remaining']);
        $this->assertEquals(0, $res['exceeded']);
    }

    /**
     * TEST 6 (Section 19 & 28):
     * Passenger = 8, Cargo = 3, NAC = 6.
     * Expected: occupied = 8, remaining = 0, exceeded = 2, status = OVER CAPACITY.
     */
    public function test_6_regression_passenger_8_cargo_3_nac_6_over_capacity(): void
    {
        $capacityService = app(CapacityService::class);
        $res = $capacityService->classifyHourlyStatus(8, 6, true);
        $this->assertEquals('OVER CAPACITY', $res['status']);
        $this->assertEquals(0, $res['remaining']);
        $this->assertEquals(2, $res['exceeded']);
    }

    /**
     * TEST 7 (Section 18 & 28):
     * NAC change 6 -> 8 persists and recalculates.
     */
    public function test_7_regression_change_nac_6_to_8_persists(): void
    {
        $airport = Airport::findByIata('BDO');
        $this->assertNotNull($airport);
        $this->assertEquals(6, $airport->getEffectiveCapacity());

        $upload = Upload::create([
            'original_filename' => 'TEST_SCHEDULE.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'airport_id' => $airport->id,
            'status' => 'completed',
            'season' => 'summer',
        ]);

        $response = $this->patchJson(route('schedule.operational-settings.save', $upload->id), [
            'aircraft_capacity' => 8,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'aircraft_capacity' => 8,
        ]);

        $airport->refresh();
        $this->assertEquals(8, $airport->getEffectiveCapacity());
    }

    /**
     * TEST 8 (Section 18 & 28):
     * Multi airport NAC: BDO = 6, DPS = 20, CGK = 30.
     */
    public function test_8_regression_multi_airport_capacities(): void
    {
        $bdo = Airport::findByIata('BDO');
        $dps = Airport::findByIata('DPS');
        $cgk = Airport::findByIata('CGK');

        $this->assertEquals(6, $bdo->getEffectiveCapacity());
        $this->assertEquals(20, $dps->getEffectiveCapacity());
        $this->assertEquals(30, $cgk->getEffectiveCapacity());
    }

    /**
     * Diagnostic report test (Section 23).
     */
    public function test_diagnostic_report(): void
    {
        $upload = Upload::create([
            'original_filename' => 'TEST_SCHEDULE.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'status' => 'completed',
            'season' => 'summer',
        ]);

        Flight::create([
            'upload_id' => $upload->id,
            'flight_number' => 'GA102',
            'airline_code' => 'GA',
            'aircraft_type' => 'B738',
            'scheduled_time' => '13:40:00',
            'flight_type' => 'departure_domestic',
            'operating_days' => '1234567',
        ]);

        $flights = Flight::where('upload_id', $upload->id)->get();
        $capacityService = app(CapacityService::class);
        $diag = $capacityService->getDiagnosticReport($flights, 6, 6, 20);

        $this->assertCount(24, $diag);
        $this->assertEquals('06:00', $diag[6]['hour']);
        $this->assertEquals(1, $diag[6]['occupied']);
        $this->assertEquals(5, $diag[6]['remaining']);
    }
}
