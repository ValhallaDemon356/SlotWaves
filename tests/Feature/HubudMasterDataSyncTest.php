<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Airline;
use App\Models\Upload;
use App\Models\Flight;
use App\Services\AirportResolverService;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubudMasterDataSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
    }

    /**
     * TEST 1: Master airport resolution by IATA and ICAO.
     */
    public function test_1_master_airport_resolution_by_iata_and_icao(): void
    {
        $bdo = Airport::findByIata('BDO');
        $this->assertNotNull($bdo);
        $this->assertEquals('WICC', $bdo->icao_code);
        $this->assertEquals('Husein Sastranegara', $bdo->name);
        $this->assertEquals('PT. Angkasa Pura Indonesia', $bdo->management_name);
        $this->assertEquals(Airport::MANAGEMENT_ANGKASA_PURA, $bdo->management_type);

        $sub = Airport::findByIata('SUB');
        $this->assertNotNull($sub);
        $this->assertEquals('WARR', $sub->icao_code);
        $this->assertEquals('Juanda', $sub->name);

        $dps = Airport::findByIata('DPS');
        $this->assertNotNull($dps);
        $this->assertEquals('WADD', $dps->icao_code);
        $this->assertEquals('I Gusti Ngurah Rai', $dps->name);

        $jog = Airport::findByIata('JOG');
        $this->assertNotNull($jog);
        $this->assertEquals('WAHH', $jog->icao_code);
        $this->assertEquals('Adisutjipto', $jog->name);
    }

    /**
     * TEST 2: Custom project reference airport CJN (Nusawiru) is strictly preserved.
     */
    public function test_2_cjn_nusawiru_preserved(): void
    {
        $cjn = Airport::findByIata('CJN');
        $this->assertNotNull($cjn);
        $this->assertEquals('Nusawiru', $cjn->name);
        $this->assertEquals('UPT Daerah/Pemda', $cjn->management_name);
        $this->assertEquals(Airport::MANAGEMENT_UPT_PEMDA, $cjn->management_type);
        $this->assertFalse($cjn->is_international);
    }

    /**
     * TEST 3: Airport IATA uniqueness and integrity.
     */
    public function test_3_airport_iata_uniqueness(): void
    {
        $totalCount   = Airport::count();
        $uniqueIatas  = Airport::whereNotNull('iata_code')->where('iata_code', '!=', '')->where('iata_code', '!=', '-')->distinct('iata_code')->count('iata_code');
        $nonNullIatas = Airport::whereNotNull('iata_code')->where('iata_code', '!=', '')->where('iata_code', '!=', '-')->count();
        $this->assertEquals($nonNullIatas, $uniqueIatas, 'All assigned IATA codes must be strictly unique!');
        $this->assertGreaterThan(50, $totalCount);
    }

    /**
     * TEST 4: Airline prefix lookup matches official names.
     */
    public function test_4_airline_code_prefix_lookup(): void
    {
        $this->assertEquals('Citilink', Airline::findByCode('QG')?->airline_name);
        $this->assertEquals('Lion Air', Airline::findByCode('JT')?->airline_name);
        $this->assertEquals('Wings Air', Airline::findByCode('IW')?->airline_name);
        $this->assertEquals('Express Air', Airline::findByCode('XN')?->airline_name);
        $this->assertEquals('TransNusa', Airline::findByCode('8B')?->airline_name);
        $this->assertEquals('Scoot', Airline::findByCode('TR')?->airline_name);
    }

    /**
     * TEST 5: Separation of flight airline_code and Hubud organization_code.
     */
    public function test_5_separation_of_airline_code_and_organization_code(): void
    {
        $ga = Airline::findByCode('GA');
        $this->assertNotNull($ga);
        $this->assertEquals('GA', $ga->airline_code);
        $this->assertEquals('AOC 121-001', $ga->organization_code);

        $jt = Airline::findByCode('JT');
        $this->assertNotNull($jt);
        $this->assertEquals('JT', $jt->airline_code);
        $this->assertEquals('AOC 121-010', $jt->organization_code);

        $qg = Airline::findByCode('QG');
        $this->assertNotNull($qg);
        $this->assertEquals('QG', $qg->airline_code);
        $this->assertEquals('AOC 121-046', $qg->organization_code);
    }

    /**
     * TEST 6: Airline code uniqueness and integrity.
     */
    public function test_6_airline_code_uniqueness(): void
    {
        $totalCount = Airline::count();
        $uniqueCodes = Airline::distinct('airline_code')->count('airline_code');
        $this->assertEquals($totalCount, $uniqueCodes);
        $this->assertGreaterThan(50, $totalCount);
    }

    /**
     * TEST 7: Idempotency of slotwaves:sync-hubud artisan command.
     */
    public function test_7_sync_command_idempotent(): void
    {
        $countAirportsBefore = Airport::count();
        $countAirlinesBefore = Airline::count();

        $this->artisan('slotwaves:sync-hubud')
            ->assertExitCode(0);

        $this->assertEquals($countAirportsBefore, Airport::count());
        $this->assertEquals($countAirlinesBefore, Airline::count());
    }

    /**
     * TEST 8: Master database synchronization does not inject artificial flights or mutate uploads.
     */
    public function test_8_master_data_isolated_from_flight_schedules(): void
    {
        $airport = Airport::findByIata('BDO');
        $upload = Upload::create([
            'original_filename' => 'TEST_ISOLATION.pdf',
            'stored_path'       => 'uploads/test.pdf',
            'status'            => 'completed',
            'season'            => 'summer',
            'airport_id'        => $airport->id,
        ]);

        $upload->flights()->create([
            'flight_number'  => 'GA100',
            'airline_code'   => 'GA',
            'aircraft_type'  => 'B738',
            'scheduled_time' => '10:00:00',
            'operating_days' => '1234567',
            'flight_type'    => 'arrival_domestic',
            'origin'         => 'SUB',
            'destination'    => 'BDO',
            'parse_status'   => 'valid',
            'validation_status' => 'valid',
        ]);

        $this->assertEquals(1, Flight::where('upload_id', $upload->id)->count());

        // Re-run Hubud sync
        $this->artisan('slotwaves:sync-hubud')->assertExitCode(0);

        // Upload flight count must remain exactly 1
        $this->assertEquals(1, Flight::where('upload_id', $upload->id)->count());
    }
}
