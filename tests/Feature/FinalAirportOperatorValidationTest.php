<?php

namespace Tests\Feature;

use App\Models\Airport;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalAirportOperatorValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
    }

    /**
     * TEST 1: DPS.region = Region 2 (INTL)
     */
    public function test_1_dps_region_2(): void
    {
        $dps = Airport::findByIata('DPS');
        $this->assertNotNull($dps);
        $this->assertContains($dps->region, ['Region 2', 'Region II']);
        $this->assertTrue($dps->is_international);
    }

    /**
     * TEST 2: BWX.region = Region 2 (INTL)
     */
    public function test_2_bwx_region_2(): void
    {
        $bwx = Airport::findByIata('BWX');
        $this->assertNotNull($bwx);
        $this->assertContains($bwx->region, ['Region 2', 'Region II']);
        $this->assertTrue($bwx->is_international);
    }

    /**
     * TEST 3: LOP.region = Region 2 (DOM)
     */
    public function test_3_lop_region_2(): void
    {
        $lop = Airport::findByIata('LOP');
        $this->assertNotNull($lop);
        $this->assertContains($lop->region, ['Region 2', 'Region II']);
        $this->assertFalse($lop->is_international);
    }

    /**
     * TEST 4: KOE.region = Region 2 (DOM)
     */
    public function test_4_koe_region_2(): void
    {
        $koe = Airport::findByIata('KOE');
        $this->assertNotNull($koe);
        $this->assertContains($koe->region, ['Region 2', 'Region II']);
        $this->assertFalse($koe->is_international);
    }

    /**
     * TEST 5: CGK.region = Region 1 (INTL)
     */
    public function test_5_cgk_region_1(): void
    {
        $cgk = Airport::findByIata('CGK');
        $this->assertNotNull($cgk);
        $this->assertContains($cgk->region, ['Region 1', 'Region I']);
        $this->assertTrue($cgk->is_international);
    }

    /**
     * TEST 6: BTH.region = Region 3 (INTL)
     */
    public function test_6_bth_region_3(): void
    {
        $bth = Airport::findByIata('BTH');
        $this->assertNotNull($bth);
        $this->assertContains($bth->region, ['Region 3', 'Region III']);
        $this->assertTrue($bth->is_international);
    }

    /**
     * TEST 7: SUB.region = Region 4 (INTL)
     */
    public function test_7_sub_region_4(): void
    {
        $sub = Airport::findByIata('SUB');
        $this->assertNotNull($sub);
        $this->assertContains($sub->region, ['Region 4', 'Region IV']);
        $this->assertTrue($sub->is_international);
    }

    /**
     * TEST 8: UPG.region = Region 5 (INTL)
     */
    public function test_8_upg_region_5(): void
    {
        $upg = Airport::findByIata('UPG');
        $this->assertNotNull($upg);
        $this->assertContains($upg->region, ['Region 5', 'Region V']);
        $this->assertTrue($upg->is_international);
    }

    /**
     * TEST 9: PNK.region = Region 6 (INTL)
     */
    public function test_9_pnk_region_6(): void
    {
        $pnk = Airport::findByIata('PNK');
        $this->assertNotNull($pnk);
        $this->assertContains($pnk->region, ['Region 6', 'Region VI']);
        $this->assertTrue($pnk->is_international);
    }

    /**
     * TEST 10: Total PT. Angkasa Pura Indonesia count must be EXACTLY 37
     */
    public function test_10_pt_api_count_strictly_equals_37(): void
    {
        $count = Airport::where('is_active', true)
            ->where('management_type', Airport::MANAGEMENT_INJOURNEY)
            ->count();

        $this->assertEquals(37, $count, "PT. Angkasa Pura Indonesia count must equal exactly 37!");
    }

    /**
     * TEST 11: Total INTL = 27, Total DOM = 10 for PT API
     */
    public function test_11_pt_api_dom_intl_counts(): void
    {
        $intlCount = Airport::where('is_active', true)
            ->where('management_type', Airport::MANAGEMENT_INJOURNEY)
            ->where('is_international', true)
            ->count();

        $domCount = Airport::where('is_active', true)
            ->where('management_type', Airport::MANAGEMENT_INJOURNEY)
            ->where('is_international', false)
            ->count();

        $this->assertEquals(27, $intlCount, "PT. Angkasa Pura Indonesia INTL count must be 27!");
        $this->assertEquals(10, $domCount, "PT. Angkasa Pura Indonesia DOM count must be 10!");
    }

    /**
     * TEST 12: Duplicate IATA check
     */
    public function test_12_no_duplicate_iata(): void
    {
        $dup = \Illuminate\Support\Facades\DB::table('airports')
            ->select('iata_code', \Illuminate\Support\Facades\DB::raw('COUNT(*) as c'))
            ->whereNotNull('iata_code')
            ->groupBy('iata_code')
            ->having('c', '>', 1)
            ->count();

        $this->assertEquals(0, $dup);
    }

    /**
     * TEST 13: Validate all 6 Regions
     */
    public function test_13_all_six_regions_exact_counts(): void
    {
        $this->assertEquals(4, Airport::where('is_active', true)->byRegion('Region 1')->count());
        $this->assertEquals(4, Airport::where('is_active', true)->byRegion('Region 2')->count());
        $this->assertEquals(13, Airport::where('is_active', true)->byRegion('Region 3')->count());
        $this->assertEquals(7, Airport::where('is_active', true)->byRegion('Region 4')->count());
        $this->assertEquals(5, Airport::where('is_active', true)->byRegion('Region 5')->count());
        $this->assertEquals(4, Airport::where('is_active', true)->byRegion('Region 6')->count());
    }

    /**
     * TEST 14: 10 Explicitly verified Non-PT API airports (Region MUST BE NULL)
     */
    public function test_14_non_pt_api_airports_have_null_region(): void
    {
        $nonPtApi = ['LBJ', 'TRK', 'AAP', 'GTO', 'KDI', 'PLW', 'MKQ', 'MKW', 'SOQ', 'TTE'];
        foreach ($nonPtApi as $code) {
            $ap = Airport::findByIata($code);
            $this->assertNotNull($ap, "Airport {$code} must exist");
            $this->assertNotEquals(Airport::MANAGEMENT_INJOURNEY, $ap->management_type, "{$code} must NOT be PT API");
            $this->assertNull($ap->region, "{$code} region must be strictly NULL");
        }
    }

    /**
     * TEST 15: Section 10 specific DOM/INTL 12 airport checks
     */
    public function test_15_section_10_twelve_airport_checks(): void
    {
        $checks = [
            'CGK' => true,
            'BDO' => false,
            'DPS' => true,
            'LOP' => false,
            'BTH' => true,
            'PGK' => false,
            'YIA' => true,
            'JOG' => false,
            'UPG' => true,
            'PKY' => false,
            'PNK' => true,
            'BPN' => true,
        ];

        foreach ($checks as $iata => $isIntl) {
            $ap = Airport::findByIata($iata);
            $this->assertNotNull($ap, "Airport {$iata} must exist");
            $this->assertEquals($isIntl, (bool)$ap->is_international, "Airport {$iata} international status mismatch");
        }
    }

    /**
     * TEST 16: Validate Full Hubud Operator Breakdown & Total 602
     */
    public function test_16_full_hubud_operator_breakdown_and_total(): void
    {
        $this->assertEquals(602, Airport::count(), "Total airports in DB must be 602 (597 Hubud + 5 Reference Hubs)");
        $this->assertEquals(37, Airport::where('management_type', Airport::MANAGEMENT_INJOURNEY)->count());
        $this->assertEquals(197, Airport::where('management_type', Airport::MANAGEMENT_UPBU_HUBUD)->count());
        $this->assertEquals(107, Airport::where('management_type', Airport::MANAGEMENT_UPTD_PEMDA)->count());
        $this->assertEquals(6, Airport::where('management_type', Airport::MANAGEMENT_TNI)->count());
        $this->assertEquals(188, Airport::where('management_type', Airport::MANAGEMENT_MISSIONARIS)->count());
        $this->assertEquals(9, Airport::where('management_type', Airport::MANAGEMENT_BUMN)->count());
        $this->assertEquals(52, Airport::where('management_type', Airport::MANAGEMENT_SWASTA)->count());
        $this->assertEquals(1, Airport::where('management_type', Airport::MANAGEMENT_MASYARAKAT)->count());
        $this->assertEquals(5, Airport::where('management_type', Airport::MANAGEMENT_OTHER)->count());

        // Verify the 597th airport (Bandara Wari, Masyarakat)
        $wari = Airport::where('name', 'LIKE', '%Wari%')->first();
        $this->assertNotNull($wari, "Bandara Wari must exist");
        $this->assertEquals(Airport::MANAGEMENT_MASYARAKAT, $wari->management_type);
        $this->assertNull($wari->region);
    }

    /**
     * TEST 17: Validate Airports Artisan Command
     */
    public function test_17_validate_airports_artisan_command(): void
    {
        $this->artisan('slotwaves:validate-airports')
            ->assertExitCode(0);
    }
}
