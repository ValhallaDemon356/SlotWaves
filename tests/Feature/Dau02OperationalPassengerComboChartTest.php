<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02OperationalPassengerComboChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    private function createReport(int $year, int $pax, int $ac = 10, int $cargo = 100): Upload
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
     * Test Passenger combo chart contains both bar actuals and trend line of the same actual values:
     * Given: 2020 = 100, 2021 = 120, 2022 = 150
     * Expected: bars: 100, 120, 150; trend: 100, 120, 150
     */
    public function test_passenger_chart_bars_and_trend_line_match_actual_values(): void
    {
        $u1 = $this->createReport(2020, 100);
        $u2 = $this->createReport(2021, 120);
        $u3 = $this->createReport(2022, 150);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id},{$u3->id}");
        $res->assertStatus(200);

        // Section 1 header and passenger card structure
        $res->assertSee('KINERJA OPERASIONAL BANDARA');
        $res->assertSee('1. PERGERAKAN PENUMPANG');
        $res->assertSee('Passenger Movement Trend');
        $res->assertSee('Pax');
        $res->assertSee('id="chart-trend-passenger"', false);

        $comp = $res->viewData('comparison');
        $this->assertNotNull($comp);
        $this->assertArrayHasKey('operational_trend', $comp);

        $paxTrend = $comp['operational_trend']['passenger'];
        $this->assertCount(3, $paxTrend);

        // Bar and Trend Line values must be the actual values
        $this->assertEquals(100, $paxTrend[0]['value']);
        $this->assertEquals(120, $paxTrend[1]['value']);
        $this->assertEquals(150, $paxTrend[2]['value']);

        // Check unit
        $this->assertEquals('Pax', $paxTrend[0]['unit']);
        $this->assertEquals('Pax', $paxTrend[1]['unit']);
        $this->assertEquals('Pax', $paxTrend[2]['unit']);
    }
}
