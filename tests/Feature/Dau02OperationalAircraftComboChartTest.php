<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02OperationalAircraftComboChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    private function createReport(int $year, int $ac, int $pax = 1000, int $cargo = 100): Upload
    {
        return Upload::create([
            'report_type'       => 'DAU2',
            'status'            => 'completed',
            'original_filename' => "dau_{$year}.xls",
            'stored_path'       => "uploads/dau_{$year}.xls",
            'report_data'       => [
                'records' => [
                    [
                        'category' => 'DOMESTIK',
                        'aircraft_arrival' => (int)($ac / 2), 'aircraft_departure' => (int)($ac / 2), 'aircraft_total' => $ac,
                        'passenger_arrival' => (int)($pax / 2), 'passenger_departure' => (int)($pax / 2), 'passenger_total' => $pax,
                        'cargo_arrival' => (int)($cargo / 2), 'cargo_departure' => (int)($cargo / 2), 'cargo' => $cargo,
                    ]
                ],
                'meta' => [
                    'airport_code' => 'CGK', 'airport_name' => 'Soekarno Hatta',
                    'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'cargo_unit' => 'Kg',
                ]
            ]
        ]);
    }

    /**
     * Test Aircraft combo chart contains both bar actuals and trend line of the same actual values:
     * Given: 2020 = 10, 2021 = 15, 2022 = 20
     * Expected: bars: 10, 15, 20; trend: 10, 15, 20
     */
    public function test_aircraft_chart_bars_and_trend_line_match_actual_values(): void
    {
        $u1 = $this->createReport(2020, 10);
        $u2 = $this->createReport(2021, 15);
        $u3 = $this->createReport(2022, 20);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id},{$u3->id}");
        $res->assertStatus(200);

        // Section 1 header and aircraft card structure
        $res->assertSee('KINERJA OPERASIONAL BANDARA');
        $res->assertSee('2. PERGERAKAN PESAWAT');
        $res->assertSee('Aircraft Movement Trend');
        $res->assertSee('A/C');
        $res->assertSee('id="chart-trend-aircraft"', false);

        $comp = $res->viewData('comparison');
        $this->assertNotNull($comp);
        $this->assertArrayHasKey('operational_trend', $comp);

        $acTrend = $comp['operational_trend']['aircraft'];
        $this->assertCount(3, $acTrend);

        // Bar and Trend Line values must be the actual values
        $this->assertEquals(10, $acTrend[0]['value']);
        $this->assertEquals(15, $acTrend[1]['value']);
        $this->assertEquals(20, $acTrend[2]['value']);

        // Check unit
        $this->assertEquals('Movements', $acTrend[0]['unit']);
        $this->assertEquals('Movements', $acTrend[1]['unit']);
        $this->assertEquals('Movements', $acTrend[2]['unit']);
    }
}
