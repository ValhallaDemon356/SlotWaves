<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Airline;
use App\Services\AirportResolverService;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InJourneyAirportMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
    }

    /**
     * TEST 1: BDO
     * IATA = BDO, ICAO = WICC, Management = PT. Angkasa Pura Indonesia, Region = Region 1
     */
    public function test_1_bdo_master_injourney_record(): void
    {
        $bdo = Airport::findByIata('BDO');
        $this->assertNotNull($bdo);
        $this->assertEquals('BDO', $bdo->iata_code);
        $this->assertEquals('WICC', $bdo->icao_code);
        $this->assertEquals('PT. Angkasa Pura Indonesia', $bdo->management_name);
        $this->assertEquals(Airport::MANAGEMENT_INJOURNEY, $bdo->management_type);
        $this->assertContains($bdo->region, ['Region 1', 'Region I']);
        $this->assertEquals(Airport::SOURCE_INJOURNEY, $bdo->data_source);
        $this->assertTrue($bdo->isAngkasaPura());
    }

    /**
     * TEST 2: CJN
     * IATA = CJN, ICAO = WICN, Management = UPT Daerah/Pemda, Region = NULL
     */
    public function test_2_cjn_nusawiru_master_record(): void
    {
        $cjn = Airport::findByIata('CJN');
        $this->assertNotNull($cjn);
        $this->assertEquals('CJN', $cjn->iata_code);
        $this->assertEquals('WICN', $cjn->icao_code);
        $this->assertEquals('UPT Daerah/Pemda', $cjn->management_name);
        $this->assertEquals(Airport::MANAGEMENT_UPTD_PEMDA, $cjn->management_type);
        $this->assertNull($cjn->region, 'Region MUST BE NULL for UPT Daerah/Pemda');
        $this->assertTrue($cjn->isUptPemda());
        $this->assertFalse($cjn->isAngkasaPura());
    }

    /**
     * TEST 3: DPS
     * IATA = DPS, ICAO = WADD, Management = PT. Angkasa Pura Indonesia, Region = Region 2
     */
    public function test_3_dps_master_injourney_record(): void
    {
        $dps = Airport::findByIata('DPS');
        $this->assertNotNull($dps);
        $this->assertEquals('DPS', $dps->iata_code);
        $this->assertEquals('WADD', $dps->icao_code);
        $this->assertEquals('PT. Angkasa Pura Indonesia', $dps->management_name);
        $this->assertContains($dps->region, ['Region 2', 'Region II']);
        $this->assertTrue($dps->is_international);
    }

    /**
     * TEST 4: JOG
     * IATA = JOG, ICAO = WAHH, Resolver maps JOGYAKARTA / JOGJAKARTA / YOGYAKARTA to JOG
     */
    public function test_4_jog_master_and_resolution_aliases(): void
    {
        $jog = Airport::findByIata('JOG');
        $this->assertNotNull($jog);
        $this->assertEquals('JOG', $jog->iata_code);
        $this->assertEquals('WAHH', $jog->icao_code);
        $this->assertContains($jog->region, ['Region 4', 'Region IV']);

        $resolver = app(AirportResolverService::class);
        $this->assertEquals('JOG', $resolver->getIataCode('JOGYAKARTA'));
        $this->assertEquals('JOG', $resolver->getIataCode('JOGJAKARTA'));
        $this->assertEquals('JOG', $resolver->getIataCode('YOGYAKARTA'));
        $this->assertEquals('JOG', $resolver->getIataCode('ADISUTJIPTO'));
    }

    /**
     * TEST 5: API Filter Management: PT. Angkasa Pura Indonesia + Region
     */
    public function test_5_api_filter_ap_with_region(): void
    {
        $response = $this->getJson('/api/airports?management_type=INJOURNEY&region=Region%202');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertCount(4, $data); // DPS, BWX, LOP, KOE
        foreach ($data as $item) {
            $this->assertEquals(Airport::MANAGEMENT_INJOURNEY, $item['management_type']);
            $this->assertContains($item['region'], ['Region 2', 'Region II']);
        }
    }

    /**
     * TEST 6: API Filter Management: UPT Daerah/Pemda (Region filter ignored)
     */
    public function test_6_api_filter_upt_pemda_ignores_region(): void
    {
        $response = $this->getJson('/api/airports?management_type=UPTD_PEMDA&region=Region%20IV');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals(Airport::MANAGEMENT_UPTD_PEMDA, $item['management_type']);
            $this->assertNull($item['region']);
        }
    }

    /**
     * TEST 7: API Filter Management: UPT Ditjen Hubud
     */
    public function test_7_api_filter_upt_ditjen_hubud(): void
    {
        $response = $this->getJson('/api/airports?management_type=UPBU_HUBUD');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals(Airport::MANAGEMENT_UPBU_HUBUD, $item['management_type']);
            $this->assertNull($item['region']);
        }
    }

    /**
     * TEST 8: Dedicated ICAO Search (WICC -> BDO)
     */
    public function test_8_icao_search_wicc(): void
    {
        $response = $this->getJson('/api/airports?icao=WICC');
        $response->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.iata_code', 'BDO')
            ->assertJsonPath('data.0.name', 'Husein Sastranegara');
    }

    /**
     * TEST 9: Dedicated IATA Search (CJN -> Nusawiru)
     */
    public function test_9_iata_search_cjn(): void
    {
        $response = $this->getJson('/api/airports?iata=CJN');
        $response->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.iata_code', 'CJN')
            ->assertJsonPath('data.0.name', 'Nusawiru')
            ->assertJsonPath('data.0.management_type', Airport::MANAGEMENT_UPT_PEMDA);
    }

    /**
     * TEST 10: Audit command returns zero anomalies
     */
    public function test_10_audit_airports_command_zero_anomalies(): void
    {
        $this->artisan('slotwaves:audit-airports')
            ->assertExitCode(0);
    }
}
