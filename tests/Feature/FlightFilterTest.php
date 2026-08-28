<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Models\Flight;
use App\Services\AircraftCategoryService;
use App\Services\AircraftPairingService;
use App\Services\FlightFilterService;
use App\Services\FlightScheduleValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightFilterTest extends TestCase
{
    use RefreshDatabase;

    private Upload $upload;

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
            'original_filename' => 'FLIGHT SCHEDULE SUMMER 2018.pdf',
            'stored_path'       => 'uploads/test.pdf',
            'status'            => 'completed',
            'season'            => 'summer',
            'airport_id'        => $airport->id,
        ]);
    }

    /**
     * TEST 1: FlightScheduleValidator validates 102 total flight benchmark.
     */
    public function test_flight_schedule_validator_validates_102_flights(): void
    {
        $flights = [];
        for ($i = 1; $i <= 41; $i++) {
            $flights[] = ['flight_number' => "AD{$i}", 'airline_code' => 'AD', 'aircraft_type' => 'A320', 'origin' => 'SURABAYA', 'scheduled_time' => '08:00:00', 'operating_days' => '1234567', 'flight_type' => 'arrival_domestic'];
        }
        for ($i = 1; $i <= 10; $i++) {
            $flights[] = ['flight_number' => "AI{$i}", 'airline_code' => 'AI', 'aircraft_type' => 'A320', 'origin' => 'KUALALUMPUR', 'scheduled_time' => '09:00:00', 'operating_days' => '1234567', 'flight_type' => 'arrival_international'];
        }
        for ($i = 1; $i <= 41; $i++) {
            $flights[] = ['flight_number' => "DD{$i}", 'airline_code' => 'DD', 'aircraft_type' => 'A320', 'destination' => 'SURABAYA', 'scheduled_time' => '10:00:00', 'operating_days' => '1234567', 'flight_type' => 'departure_domestic'];
        }
        for ($i = 1; $i <= 10; $i++) {
            $flights[] = ['flight_number' => "DI{$i}", 'airline_code' => 'DI', 'aircraft_type' => 'A320', 'destination' => 'KUALALUMPUR', 'scheduled_time' => '11:00:00', 'operating_days' => '1234567', 'flight_type' => 'departure_international'];
        }

        $validator = new FlightScheduleValidator();
        $res = $validator->validate($flights, 'FLIGHT SCHEDULE SUMMER 2018.pdf');
        $this->assertTrue($res['is_valid']);
    }

    /**
     * TEST 2: Single-day DOS 1 filter logic (containment).
     * Must return flights operating on Day 1 (e.g. 1, 1357, 1234567, 12).
     * Must NOT return 246, 357.
     */
    public function test_single_day_dos_1_filter_logic(): void
    {
        $fA = $this->upload->flights()->create(['flight_number' => 'FLA', 'scheduled_time' => '08:00:00', 'operating_days' => '1', 'flight_type' => 'arrival_domestic']);
        $fB = $this->upload->flights()->create(['flight_number' => 'FLB', 'scheduled_time' => '09:00:00', 'operating_days' => '1357', 'flight_type' => 'arrival_domestic']);
        $fC = $this->upload->flights()->create(['flight_number' => 'FLC', 'scheduled_time' => '10:00:00', 'operating_days' => '1234567', 'flight_type' => 'arrival_domestic']);
        $fD = $this->upload->flights()->create(['flight_number' => 'FLD', 'scheduled_time' => '11:00:00', 'operating_days' => '246', 'flight_type' => 'arrival_domestic']);
        $fE = $this->upload->flights()->create(['flight_number' => 'FLE', 'scheduled_time' => '12:00:00', 'operating_days' => '357', 'flight_type' => 'arrival_domestic']);

        $filterService = app(FlightFilterService::class);
        $results = $filterService->getFilteredFlights($this->upload, ['dos' => '1']);

        $flightNumbers = $results->pluck('flight_number')->toArray();
        $this->assertContains('FLA', $flightNumbers);
        $this->assertContains('FLB', $flightNumbers);
        $this->assertContains('FLC', $flightNumbers);
        $this->assertNotContains('FLD', $flightNumbers);
        $this->assertNotContains('FLE', $flightNumbers);
    }

    /**
     * TEST 3: Multi-day DOS 2,4,6 filter logic (exact pattern match).
     * Must return ONLY flights whose normalized DOS is 246.
     * Must NOT return 1234567, 2, 24, 1246.
     */
    public function test_multi_day_dos_246_exact_match_filter_logic(): void
    {
        $fA = $this->upload->flights()->create(['flight_number' => 'FLA', 'scheduled_time' => '08:00:00', 'operating_days' => '246', 'flight_type' => 'arrival_domestic']);
        $fB = $this->upload->flights()->create(['flight_number' => 'FLB', 'scheduled_time' => '09:00:00', 'operating_days' => '1234567', 'flight_type' => 'arrival_domestic']);
        $fC = $this->upload->flights()->create(['flight_number' => 'FLC', 'scheduled_time' => '10:00:00', 'operating_days' => '2', 'flight_type' => 'arrival_domestic']);
        $fD = $this->upload->flights()->create(['flight_number' => 'FLD', 'scheduled_time' => '11:00:00', 'operating_days' => '24', 'flight_type' => 'arrival_domestic']);

        $filterService = app(FlightFilterService::class);
        $results = $filterService->getFilteredFlights($this->upload, ['dos' => '2,4,6']);

        $flightNumbers = $results->pluck('flight_number')->toArray();
        $this->assertEquals(['FLA'], $flightNumbers);
    }

    /**
     * TEST 4: Daily filter logic ("daily" or "1234567").
     * Must return ONLY flights whose normalized DOS is 1234567.
     */
    public function test_daily_dos_exact_match_filter_logic(): void
    {
        $fA = $this->upload->flights()->create(['flight_number' => 'FLA', 'scheduled_time' => '08:00:00', 'operating_days' => '1234567', 'flight_type' => 'arrival_domestic']);
        $fB = $this->upload->flights()->create(['flight_number' => 'FLB', 'scheduled_time' => '09:00:00', 'operating_days' => '1357', 'flight_type' => 'arrival_domestic']);
        $fC = $this->upload->flights()->create(['flight_number' => 'FLC', 'scheduled_time' => '10:00:00', 'operating_days' => '246', 'flight_type' => 'arrival_domestic']);

        $filterService = app(FlightFilterService::class);
        $results = $filterService->getFilteredFlights($this->upload, ['dos' => 'daily']);

        $flightNumbers = $results->pluck('flight_number')->toArray();
        $this->assertEquals(['FLA'], $flightNumbers);
    }
}
