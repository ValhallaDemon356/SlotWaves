<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02OperationalCargoComboChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    private function createReport(int $year, ?int $cargo, int $pax = 1000, int $ac = 10): Upload
    {
        $records = [];
        if ($cargo !== null) {
            $records[] = [
                'category' => 'DOMESTIK',
                'aircraft_arrival' => (int)($ac / 2), 'aircraft_departure' => (int)($ac / 2), 'aircraft_total' => $ac,
                'passenger_arrival' => (int)($pax / 2), 'passenger_departure' => (int)($pax / 2), 'passenger_total' => $pax,
                'cargo_arrival' => (int)($cargo / 2), 'cargo_departure' => (int)($cargo / 2), 'cargo' => $cargo,
            ];
        } else {
            $records[] = [
                'category' => 'DOMESTIK',
                'aircraft_arrival' => (int)($ac / 2), 'aircraft_departure' => (int)($ac / 2), 'aircraft_total' => $ac,
                'passenger_arrival' => (int)($pax / 2), 'passenger_departure' => (int)($pax / 2), 'passenger_total' => $pax,
                'cargo_arrival' => null, 'cargo_departure' => null, 'cargo' => null,
            ];
        }

        return Upload::create([
            'report_type'       => 'DAU2',
            'status'            => 'completed',
            'original_filename' => "dau_{$year}.xls",
            'stored_path'       => "uploads/dau_{$year}.xls",
            'report_data'       => [
                'records' => $records,
                'meta' => [
                    'airport_code' => 'CGK', 'airport_name' => 'Soekarno Hatta',
                    'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'cargo_unit' => 'Kg',
                ]
            ]
        ]);
    }

    /**
     * Test Cargo combo chart contains both bar actuals and trend line of the same actual values:
     * Given: 2020 = 1000, 2021 = 2000, 2022 = 1500
     * Expected: bars: 1000, 2000, 1500; trend: 1000, 2000, 1500
     */
    public function test_cargo_chart_bars_and_trend_line_match_actual_values(): void
    {
        $u1 = $this->createReport(2020, 1000);
        $u2 = $this->createReport(2021, 2000);
        $u3 = $this->createReport(2022, 1500);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id},{$u3->id}");
        $res->assertStatus(200);

        // Section 1 header and cargo card structure
        $res->assertSee('KINERJA OPERASIONAL BANDARA');
        $res->assertSee('3. PERGERAKAN KARGO');
        $res->assertSee('Cargo Movement Trend');
        $res->assertSee('id="chart-trend-cargo"', false);

        $comp = $res->viewData('comparison');
        $this->assertNotNull($comp);
        $this->assertArrayHasKey('operational_trend', $comp);

        $cargoTrend = $comp['operational_trend']['cargo'];
        $this->assertCount(3, $cargoTrend);

        // Bar and Trend Line values must be the actual values
        $this->assertEquals(1000, $cargoTrend[0]['value']);
        $this->assertEquals(2000, $cargoTrend[1]['value']);
        $this->assertEquals(1500, $cargoTrend[2]['value']);

        // Check unit
        $this->assertEquals('Kg', $cargoTrend[0]['unit']);
        $this->assertEquals('Kg', $cargoTrend[1]['unit']);
        $this->assertEquals('Kg', $cargoTrend[2]['unit']);
    }

    /**
     * Partial data test: if Cargo is genuinely unavailable,
     * Passenger & Aircraft charts remain visible and Cargo shows explicit unavailable state.
     */
    public function test_partial_data_shows_explicit_unavailable_state(): void
    {
        $u1 = $this->createReport(2020, null, 1000, 10);
        $u2 = $this->createReport(2021, null, 1200, 12);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $res->assertStatus(200);

        // Passenger & Aircraft visible
        $res->assertSee('1. PERGERAKAN PENUMPANG');
        $res->assertSee('2. PERGERAKAN PESAWAT');
        $res->assertSee('3. PERGERAKAN KARGO');
        $res->assertSee('id="chart-trend-passenger"', false);
        $res->assertSee('id="chart-trend-aircraft"', false);

        // Cargo section contains the fallback container
        $res->assertSee('NO DATA AVAILABLE');
    }
}
