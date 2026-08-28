<?php

namespace Tests\Unit;

use App\Services\AirportResolverService;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirportResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private AirportResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        $this->resolver = new AirportResolverService();
    }

    /**
     * TEST 1: Resolves long station names to 3-letter IATA codes across dataset.
     */
    public function test_resolves_long_station_names_to_iata_codes(): void
    {
        $this->assertEquals('SUB', $this->resolver->getIataCode('SURABAYA'));
        $this->assertEquals('SUB', $this->resolver->getIataCode('JUANDA'));
        $this->assertEquals('CGK', $this->resolver->getIataCode('JAKARTA'));
        $this->assertEquals('HLP', $this->resolver->getIataCode('HALIM PERDANAKUSUMA'));
        $this->assertEquals('BDO', $this->resolver->getIataCode('BANDUNG'));
        $this->assertEquals('KJT', $this->resolver->getIataCode('KERTAJATI'));
        $this->assertEquals('TKG', $this->resolver->getIataCode('TANJUNG KARANG'));
        $this->assertEquals('SIN', $this->resolver->getIataCode('SINGAPURA'));
        $this->assertEquals('MLG', $this->resolver->getIataCode('MALANG'));
        $this->assertEquals('PGK', $this->resolver->getIataCode('PANGKALPINANG'));
        $this->assertEquals('SOC', $this->resolver->getIataCode('SOLO'));
        $this->assertEquals('SRG', $this->resolver->getIataCode('SEMARANG'));
        $this->assertEquals('DPS', $this->resolver->getIataCode('DENPASAR'));
        $this->assertEquals('KUL', $this->resolver->getIataCode('KUALA LUMPUR'));
    }

    /**
     * TEST 2: Existing 3-letter IATA codes remain intact.
     */
    public function test_existing_3_letter_iata_codes_remain_intact(): void
    {
        $this->assertEquals('SUB', $this->resolver->getIataCode('SUB'));
        $this->assertEquals('CGK', $this->resolver->getIataCode('CGK'));
        $this->assertEquals('HLP', $this->resolver->getIataCode('HLP'));
        $this->assertEquals('TKG', $this->resolver->getIataCode('TKG'));
    }

    /**
     * TEST 3: Full label resolution e.g., "SUB — SURABAYA" or "TKG — TANJUNG KARANG".
     */
    public function test_full_label_resolution(): void
    {
        $this->assertEquals('SUB — Juanda', $this->resolver->getFullLabel('SURABAYA'));
        $this->assertEquals('SUB — Juanda', $this->resolver->getFullLabel('SUB'));
        $this->assertEquals('TKG — Radin Inten II', $this->resolver->getFullLabel('TANJUNG KARANG'));
        $this->assertEquals('CGK — Soekarno-Hatta', $this->resolver->getFullLabel('JAKARTA'));
    }

    /**
     * TEST 4: Fallback for unknown stations returns clean original string without crashing.
     */
    public function test_fallback_for_unknown_stations(): void
    {
        $this->assertEquals('UNKNOWN AIRPORT', $this->resolver->getIataCode('UNKNOWN AIRPORT'));
        $this->assertEquals('—', $this->resolver->getIataCode(null));
        $this->assertEquals('—', $this->resolver->getIataCode(''));
    }
}
