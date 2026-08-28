<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PdfParser;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PdfParserTest extends TestCase
{
    use RefreshDatabase;

    private PdfParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        $this->parser = new PdfParser();
    }

    /**
     * Test Case 1:
     * IW 1720 ATR 72 HALIM PERDANAKUSUMA 13:50 1 2 3 4 5 6 7
     */
    public function test_case_1(): void
    {
        $line = '1  IW 1720 ATR 72 HALIM PERDANAKUSUMA 13:50 1 2 3 4 5 6 7';
        $result = $this->parser->parseLineForTesting($line, 'arrival_domestic');

        $this->assertNotNull($result);
        $this->assertEquals('IW1720', $result['flight_number']);
        $this->assertEquals('IW', $result['airline_code']);
        $this->assertEquals('ATR72', $result['aircraft_type']);
        $this->assertEquals('HLP', $result['origin']);
        $this->assertEquals('BDO', $result['destination']);
        $this->assertEquals('13:50:00', $result['scheduled_time']);
        $this->assertEquals('1234567', $result['operating_days']);
        $this->assertEquals('arrival_domestic', $result['flight_type']);
    }

    /**
     * Test Case 2:
     * QG 820 A 320 SURABAYA 06:55 1 2 3 4 5 6 7
     */
    public function test_case_2(): void
    {
        $line = '2  QG 820 A 320 SURABAYA 06:55 1 2 3 4 5 6 7';
        $result = $this->parser->parseLineForTesting($line, 'arrival_domestic');

        $this->assertNotNull($result);
        $this->assertEquals('QG820', $result['flight_number']);
        $this->assertEquals('QG', $result['airline_code']);
        $this->assertEquals('A320', $result['aircraft_type']);
        $this->assertEquals('SUB', $result['origin']);
        $this->assertEquals('BDO', $result['destination']);
        $this->assertEquals('06:55:00', $result['scheduled_time']);
        $this->assertEquals('1234567', $result['operating_days']);
        $this->assertEquals('arrival_domestic', $result['flight_type']);
    }

    /**
     * Test Case 3:
     * XN 747 D328 TANJUNG KARANG 07:15 1 2 - 4 5 6 7
     */
    public function test_case_3(): void
    {
        $line = '3  XN 747 D328 TANJUNG KARANG 07:15 1 2 - 4 5 6 7';
        $result = $this->parser->parseLineForTesting($line, 'arrival_domestic');

        $this->assertNotNull($result);
        $this->assertEquals('XN747', $result['flight_number']);
        $this->assertEquals('XN', $result['airline_code']);
        $this->assertEquals('D328', $result['aircraft_type']);
        $this->assertEquals('TKG', $result['origin']);
        $this->assertEquals('BDO', $result['destination']);
        $this->assertEquals('07:15:00', $result['scheduled_time']);
        $this->assertEquals('124567', $result['operating_days']);
        $this->assertEquals('arrival_domestic', $result['flight_type']);
    }

    /**
     * Test Case 4:
     * JT 950 B 738 SURABAYA 16:10 1 2 3 4 5 6 7
     */
    public function test_case_4(): void
    {
        $line = '4  JT 950 B 738 SURABAYA 16:10 1 2 3 4 5 6 7';
        $result = $this->parser->parseLineForTesting($line, 'departure_domestic');

        $this->assertNotNull($result);
        $this->assertEquals('JT950', $result['flight_number']);
        $this->assertEquals('JT', $result['airline_code']);
        $this->assertEquals('B738', $result['aircraft_type']);
        $this->assertEquals('BDO', $result['origin']);
        $this->assertEquals('SUB', $result['destination']);
        $this->assertEquals('16:10:00', $result['scheduled_time']);
        $this->assertEquals('1234567', $result['operating_days']);
        $this->assertEquals('departure_domestic', $result['flight_type']);
    }
}
