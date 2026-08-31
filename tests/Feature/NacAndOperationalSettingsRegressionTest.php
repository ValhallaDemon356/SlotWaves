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

    public function test_1_nac_6_passenger_5_available(): void
    {
        $capacityService = app(CapacityService::class);
        $res = $capacityService->classifyHourlyStatus(5, 6, true);
        $this->assertEquals('AVAILABLE', $res['status']);
        $this->assertEquals(1, $res['remaining']);
        $this->assertEquals(0, $res['exceeded']);
    }

    public function test_2_nac_6_passenger_6_full_max(): void
    {
        $capacityService = app(CapacityService::class);
        $res = $capacityService->classifyHourlyStatus(6, 6, true);
        $this->assertEquals('FULL / MAX', $res['status']);
        $this->assertEquals(0, $res['remaining']);
        $this->assertEquals(0, $res['exceeded']);
    }

    public function test_3_nac_6_passenger_8_over_capacity(): void
    {
        $capacityService = app(CapacityService::class);
        $res = $capacityService->classifyHourlyStatus(8, 6, true);
        $this->assertEquals('OVER CAPACITY', $res['status']);
        $this->assertEquals(0, $res['remaining']);
        $this->assertEquals(2, $res['exceeded']);
    }

    public function test_4_nac_6_passenger_5_cargo_3_available_cargo_excluded(): void
    {
        $capacityService = app(CapacityService::class);
        // Only passenger flights count towards apron limit
        $res = $capacityService->classifyHourlyStatus(5, 6, true);
        $this->assertEquals('AVAILABLE', $res['status']);
        $this->assertEquals(1, $res['remaining']);
    }

    public function test_5_change_nac_6_to_8_persists_to_airport(): void
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

    public function test_6_multi_airport_capacities_bdo_dps_cgk(): void
    {
        $bdo = Airport::findByIata('BDO');
        $dps = Airport::findByIata('DPS');
        $cgk = Airport::findByIata('CGK');

        $this->assertEquals(6, $bdo->getEffectiveCapacity());
        $this->assertEquals(20, $dps->getEffectiveCapacity());
        $this->assertEquals(30, $cgk->getEffectiveCapacity());
    }

    public function test_7_flight_outside_ops_hours_is_off_hours(): void
    {
        $capacityService = app(CapacityService::class);
        $res = $capacityService->classifyHourlyStatus(2, 6, false); // hour 22 is outside 06:00-20:00
        $this->assertEquals('OFF HOURS', $res['status']);
    }

    public function test_8_timezone_conversion_bdo_local_wib_and_utc(): void
    {
        $airport = Airport::findByIata('BDO');
        $this->assertEquals('Asia/Jakarta', $airport->getTimezone());

        $upload = Upload::create([
            'original_filename' => 'TEST_SCHEDULE.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'airport_id' => $airport->id,
            'status' => 'completed',
            'season' => 'summer',
        ]);

        $flight = Flight::create([
            'upload_id' => $upload->id,
            'flight_number' => 'GA101',
            'airline_code' => 'GA',
            'scheduled_time' => '06:55:00',
            'flight_type' => 'arrival_domestic',
            'operating_days' => '1234567',
        ]);

        $this->assertNotNull($flight->scheduled_time);
    }
}
