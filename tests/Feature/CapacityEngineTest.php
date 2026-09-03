<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Models\Flight;
use App\Services\CapacityService;
use App\Services\AircraftPairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapacityEngineTest extends TestCase
{
    use RefreshDatabase;

    private Upload $upload;
    private CapacityService $capacityService;
    private AircraftPairingService $pairingService;

    protected function setUp(): void
    {
        parent::setUp();

        $airport = Airport::firstOrCreate(
            ['iata_code' => 'BDO'],
            [
                'name'    => 'Husein Sastranegara',
                'city'    => 'Bandung',
                'country' => 'Indonesia',
            ]
        );

        $this->upload = Upload::create([
            'original_filename' => 'FLIGHT SCHEDULE TEST.pdf',
            'stored_path'       => 'uploads/test.pdf',
            'status'            => 'completed',
            'season'            => 'summer',
            'airport_id'        => $airport->id,
        ]);

        $this->capacityService = app(CapacityService::class);
        $this->pairingService  = app(AircraftPairingService::class);
    }

    /**
     * TEST 1: Arrival before departure -> aircraft remains occupied until STD
     */
    public function test_1_arrival_before_departure_remains_occupied_until_std(): void
    {
        $arr = $this->upload->flights()->create([
            'flight_number'  => 'QG820',
            'airline_code'   => 'QG',
            'aircraft_type'  => 'A320',
            'origin'         => 'SURABAYA',
            'scheduled_time' => '06:55:00',
            'operating_days' => '1234567',
            'flight_type'    => 'arrival_domestic',
        ]);
        $dep = $this->upload->flights()->create([
            'flight_number'  => 'QG821',
            'airline_code'   => 'QG',
            'aircraft_type'  => 'A320',
            'destination'    => 'SURABAYA',
            'scheduled_time' => '07:25:00',
            'operating_days' => '1234567',
            'flight_type'    => 'departure_domestic',
        ]);

        $res = $this->capacityService->calculate($this->upload->flights);

        // Hour 6 (06:55): 1 occupied (QG820 arrived at 06:55)
        $this->assertEquals(1, $res['hourly'][6]['occupied']);
        $this->assertEquals(5, $res['hourly'][6]['remaining']);

        // Hour 7 (07:00-07:25): occupied until 07:25 departure
        $this->assertEquals(1, $res['hourly'][7]['occupied']);
        $this->assertEquals(5, $res['hourly'][7]['remaining']);
    }

    /**
     * TEST 2: Arrival and departure in same hour -> occupancy uses actual timestamps
     */
    public function test_2_arrival_and_departure_same_hour_uses_actual_timestamps(): void
    {
        $this->upload->flights()->create([
            'flight_number'  => 'GA100',
            'airline_code'   => 'GA',
            'aircraft_type'  => 'B738',
            'scheduled_time' => '10:10:00',
            'operating_days' => '1234567',
            'flight_type'    => 'arrival_domestic',
        ]);
        $this->upload->flights()->create([
            'flight_number'  => 'GA101',
            'airline_code'   => 'GA',
            'aircraft_type'  => 'B738',
            'scheduled_time' => '10:40:00',
            'operating_days' => '1234567',
            'flight_type'    => 'departure_domestic',
        ]);

        $res = $this->capacityService->calculate($this->upload->flights()->get());

        // Under Arrivals + Departures + OPC standard: 1 Arr + 1 Dep = 2 movements in hour 10
        $this->assertEquals(2, $res['hourly'][10]['occupied']);
        $this->assertEquals(4, $res['hourly'][10]['remaining']);
        // 11:00 should be 0 occupied
        $this->assertEquals(0, $res['hourly'][11]['occupied']);
        $this->assertEquals(6, $res['hourly'][11]['remaining']);
    }

    /**
     * TEST 3: Arrival with no same-day departure -> aircraft remains parked
     */
    public function test_3_arrival_with_no_departure_remains_parked(): void
    {
        $this->upload->flights()->create([
            'flight_number'  => 'XN999',
            'airline_code'   => 'XN',
            'aircraft_type'  => 'B738',
            'scheduled_time' => '18:00:00',
            'operating_days' => '1234567',
            'flight_type'    => 'arrival_domestic',
        ]);

        $res = $this->capacityService->calculate($this->upload->flights);

        // At 18:00, 19:00, 20:00, 21:00, 22:00, 23:00 aircraft remains PARKED and occupied
        for ($h = 18; $h <= 23; $h++) {
            $this->assertEquals(1, $res['hourly'][$h]['occupied'], "Hour {$h} must be occupied");
            $this->assertEquals(5, $res['hourly'][$h]['remaining'], "Hour {$h} remaining must be 5");
        }
    }

    /**
     * TEST 4: Arrival with next-day departure -> overnight occupancy
     */
    public function test_4_overnight_occupancy(): void
    {
        // Unpaired departure at 06:00 is occupied from 00:00 until 06:00
        $this->upload->flights()->create([
            'flight_number'  => 'JT100',
            'airline_code'   => 'JT',
            'aircraft_type'  => 'B738',
            'scheduled_time' => '06:00:00',
            'operating_days' => '1234567',
            'flight_type'    => 'departure_domestic',
        ]);

        $res = $this->capacityService->calculate($this->upload->flights);

        for ($h = 0; $h < 6; $h++) {
            $this->assertEquals(1, $res['hourly'][$h]['occupied'], "Overnight hour {$h} must be occupied");
        }
    }

    /**
     * TEST 5: DOS-specific pairing -> Monday selects DOS 1357, not 246
     */
    public function test_5_dos_specific_pairing_monday(): void
    {
        $arr  = $this->upload->flights()->create(['flight_number' => 'XN741', 'airline_code' => 'XN', 'aircraft_type' => 'B738', 'scheduled_time' => '12:50:00', 'operating_days' => '1234567', 'flight_type' => 'arrival_domestic']);
        $dep1 = $this->upload->flights()->create(['flight_number' => 'XN739', 'airline_code' => 'XN', 'aircraft_type' => 'B738', 'scheduled_time' => '13:40:00', 'operating_days' => '1357', 'flight_type' => 'departure_domestic']);
        $dep2 = $this->upload->flights()->create(['flight_number' => 'XN861', 'airline_code' => 'XN', 'aircraft_type' => 'B738', 'scheduled_time' => '13:40:00', 'operating_days' => '246', 'flight_type' => 'departure_domestic']);

        // Day 1 = Monday
        $rotationsDay1 = $this->pairingService->pairFlights($this->upload->flights, 1);

        $this->assertNotEmpty($rotationsDay1);
        $pairedRot = collect($rotationsDay1)->firstWhere('arrival.flight_number', 'XN741');
        $this->assertNotNull($pairedRot);
        $this->assertTrue($pairedRot['is_paired']);
        $this->assertEquals('XN739', $pairedRot['departure']->flight_number);
    }

    /**
     * TEST 6: Different flight numbers -> can still be paired
     */
    public function test_6_different_flight_numbers_can_be_paired(): void
    {
        $arr = $this->upload->flights()->create(['flight_number' => 'JT755', 'airline_code' => 'JT', 'aircraft_type' => 'B738', 'origin' => 'YOGYAKARTA', 'scheduled_time' => '19:15:00', 'operating_days' => '1234567', 'flight_type' => 'arrival_domestic']);
        $dep = $this->upload->flights()->create(['flight_number' => 'JT952', 'airline_code' => 'JT', 'aircraft_type' => 'B738', 'destination' => 'LOMBOK', 'scheduled_time' => '20:00:00', 'operating_days' => '1234567', 'flight_type' => 'departure_domestic']);

        $rotations = $this->pairingService->pairFlights($this->upload->flights);
        $rot = collect($rotations)->firstWhere('arrival.flight_number', 'JT755');

        $this->assertNotNull($rot);
        $this->assertTrue($rot['is_paired']);
        $this->assertEquals('JT952', $rot['departure']->flight_number);
        // Original source fields remain immutable!
        $this->assertEquals('JT755', $rot['arrival']->flight_number);
        $this->assertEquals('YOGYAKARTA', $rot['arrival']->origin);
        $this->assertEquals('JT952', $rot['departure']->flight_number);
        $this->assertEquals('LOMBOK', $rot['departure']->destination);
    }

    /**
     * TEST 7: Same airline but wrong DOS -> must NOT be paired
     */
    public function test_7_same_airline_wrong_dos_must_not_pair(): void
    {
        $arr = $this->upload->flights()->create(['flight_number' => 'IW100', 'airline_code' => 'IW', 'aircraft_type' => 'ATR72', 'scheduled_time' => '09:00:00', 'operating_days' => '135', 'flight_type' => 'arrival_domestic']);
        $dep = $this->upload->flights()->create(['flight_number' => 'IW101', 'airline_code' => 'IW', 'aircraft_type' => 'ATR72', 'scheduled_time' => '09:45:00', 'operating_days' => '246', 'flight_type' => 'departure_domestic']);

        // Check on Day 1 (Monday)
        $rotations = $this->pairingService->pairFlights($this->upload->flights, 1);
        $rot = collect($rotations)->firstWhere('arrival.flight_number', 'IW100');

        $this->assertNotNull($rot);
        $this->assertFalse($rot['is_paired']);
    }

    /**
     * TEST 8: Same airline but incompatible aircraft -> must NOT automatically pair
     */
    public function test_8_incompatible_aircraft_must_not_pair(): void
    {
        $arr = $this->upload->flights()->create(['flight_number' => 'GA200', 'airline_code' => 'GA', 'aircraft_type' => 'ATR72', 'scheduled_time' => '11:00:00', 'operating_days' => '1234567', 'flight_type' => 'arrival_domestic']);
        $dep = $this->upload->flights()->create(['flight_number' => 'GA201', 'airline_code' => 'GA', 'aircraft_type' => 'B773', 'scheduled_time' => '11:45:00', 'operating_days' => '1234567', 'flight_type' => 'departure_domestic']);

        $rotations = $this->pairingService->pairFlights($this->upload->flights);
        $rot = collect($rotations)->firstWhere('arrival.flight_number', 'GA200');

        $this->assertNotNull($rot);
        $this->assertFalse($rot['is_paired']);
    }

    /**
     * TEST 9: 12:00 case -> occupied 2, remaining 4
     */
    public function test_9_1200_case_occupied_two_remaining_four(): void
    {
        $this->upload->flights()->create(['flight_number' => 'JT960', 'airline_code' => 'JT', 'aircraft_type' => 'B738', 'scheduled_time' => '12:25:00', 'operating_days' => '1234567', 'flight_type' => 'departure_domestic']);
        $this->upload->flights()->create(['flight_number' => 'XN741', 'airline_code' => 'XN', 'aircraft_type' => 'B738', 'scheduled_time' => '12:50:00', 'operating_days' => '1234567', 'flight_type' => 'arrival_domestic']);
        $this->upload->flights()->create(['flight_number' => 'XN739', 'airline_code' => 'XN', 'aircraft_type' => 'B738', 'scheduled_time' => '13:40:00', 'operating_days' => '1234567', 'flight_type' => 'departure_domestic']);

        $res = $this->capacityService->calculate($this->upload->flights);
        $h12 = $res['hourly'][12];

        $this->assertEquals(2, $h12['occupied']);
        $this->assertEquals(4, $h12['remaining']);
        $this->assertEquals('AVAILABLE', $h12['status']);
    }

    /**
     * TEST 10: 16:00 case -> occupied 3, remaining 3
     */
    public function test_10_1600_case_occupied_three_remaining_three(): void
    {
        $this->upload->flights()->create(['flight_number' => 'MI196', 'airline_code' => 'MI', 'aircraft_type' => 'A320', 'scheduled_time' => '16:10:00', 'operating_days' => '1234567', 'flight_type' => 'departure_international']);
        $this->upload->flights()->create(['flight_number' => 'JT961', 'airline_code' => 'JT', 'aircraft_type' => 'B738', 'scheduled_time' => '16:30:00', 'operating_days' => '1234567', 'flight_type' => 'departure_domestic']);
        $this->upload->flights()->create(['flight_number' => 'QG816', 'airline_code' => 'QG', 'aircraft_type' => 'A320', 'scheduled_time' => '16:35:00', 'operating_days' => '1234567', 'flight_type' => 'departure_domestic']);

        $res = $this->capacityService->calculate($this->upload->flights);
        $h16 = $res['hourly'][16];

        $this->assertEquals(3, $h16['occupied']);
        $this->assertEquals(3, $h16['remaining']);
        $this->assertEquals('AVAILABLE', $h16['status']);
    }

    /**
     * TEST 11: 19:00 case -> occupied 6, remaining 0 (Status FULL / MAX)
     */
    public function test_11_1900_case_occupied_six_remaining_zero_status_full(): void
    {
        // 6 parked / occupied aircraft in 19:00 window
        for ($i = 1; $i <= 6; $i++) {
            $this->upload->flights()->create([
                'flight_number'  => "ARR{$i}",
                'airline_code'   => "A{$i}",
                'aircraft_type'  => 'A320',
                'scheduled_time' => "18:30:00",
                'operating_days' => '1234567',
                'flight_type'    => 'arrival_domestic',
            ]);
        }

        $res = $this->capacityService->calculate($this->upload->flights);
        $h19 = $res['hourly'][19];

        $this->assertEquals(6, $h19['occupied']);
        $this->assertEquals(0, $h19['remaining']);
        $this->assertEquals('FULL / MAX', $h19['status']);
    }

    /**
     * TEST 12: JT755 -> JT952 valid rotation if all pairing criteria satisfied
     */
    public function test_12_jt755_to_jt952_valid_rotation(): void
    {
        $arr = $this->upload->flights()->create([
            'flight_number'  => 'JT755',
            'airline_code'   => 'JT',
            'aircraft_type'  => 'B738',
            'origin'         => 'YOGYAKARTA',
            'scheduled_time' => '19:15:00',
            'operating_days' => '1234567',
            'flight_type'    => 'arrival_domestic',
        ]);
        $dep = $this->upload->flights()->create([
            'flight_number'  => 'JT952',
            'airline_code'   => 'JT',
            'aircraft_type'  => 'B738',
            'destination'    => 'LOMBOK',
            'scheduled_time' => '20:00:00',
            'operating_days' => '1234567',
            'flight_type'    => 'departure_domestic',
        ]);

        $rotations = $this->pairingService->pairFlights($this->upload->flights);
        $rot = collect($rotations)->firstWhere('arrival.flight_number', 'JT755');

        $this->assertNotNull($rot);
        $this->assertTrue($rot['is_paired']);
        $this->assertEquals('JT755', $rot['arrival']->flight_number);
        $this->assertEquals('JT952', $rot['departure']->flight_number);
        $this->assertEquals(45, $rot['turnaround_mins']);
    }
}
