<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02OperationalPerformanceComboChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    private function createReport(int $year, int $pax, int $ac, int $cargo): Upload
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
                        'aircraft_arrival' => $ac / 2, 'aircraft_departure' => $ac / 2, 'aircraft_total' => $ac,
                        'passenger_arrival' => $pax / 2, 'passenger_departure' => $pax / 2, 'passenger_total' => $pax,
                        'cargo_arrival' => $cargo / 2, 'cargo_departure' => $cargo / 2, 'cargo' => $cargo,
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
     * Test browser comparison dashboard loads correctly and delivers combo chart configuration.
     */
    public function test_comparison_dashboard_renders_operational_combo_charts(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);
        $u3 = $this->createReport(2022, 11000, 210, 5200);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id},{$u3->id}&baseline=P1");
        $res->assertStatus(200);

        // Section 1 headers and combined chart tags
        $res->assertSee('KINERJA OPERASIONAL BANDARA');
        $res->assertSee('SECTION 1 &bull; OPERATIONAL PERFORMANCE', false);
        $res->assertSee('Combined Operational Trend');
        $res->assertSee('Pax and Flight Trend');

        // Canvas element for combined chart
        $res->assertSee('id="chart-operational-combined"', false);
        $res->assertDontSee('id="chart-trend-passenger"', false);
        $res->assertDontSee('id="chart-trend-aircraft"', false);
        $res->assertDontSee('id="chart-trend-cargo"', false);

        // Verify view data structure
        $comparison = $res->viewData('comparison');
        $this->assertNotNull($comparison);
        $this->assertArrayHasKey('operational_trend', $comparison);

        $paxTrend = $comparison['operational_trend']['passenger'];
        $this->assertCount(3, $paxTrend);

        // P1: Bar = 10000, Line = null / N/A
        $this->assertEquals(10000, $paxTrend[0]['value']);
        $this->assertNull($paxTrend[0]['growth_pct']);
        $this->assertEquals('N/A', $paxTrend[0]['growth_fmt']);

        // P2: Bar = 12000, Line = +20%
        $this->assertEquals(12000, $paxTrend[1]['value']);
        $this->assertEquals(20.0, $paxTrend[1]['growth_pct']);
        $this->assertEquals('+20.00%', $paxTrend[1]['growth_fmt']);

        // P3: Bar = 11000, Line = -8.33%
        $this->assertEquals(11000, $paxTrend[2]['value']);
        $this->assertEquals(-8.33, $paxTrend[2]['growth_pct']);
        $this->assertEquals('-8.33%', $paxTrend[2]['growth_fmt']);
    }

    /**
     * Test all 3 metrics have correct units and scales.
     */
    public function test_metrics_have_independent_scales_and_units(): void
    {
        $u1 = $this->createReport(2023, 50000, 1000, 20000);
        $u2 = $this->createReport(2024, 60000, 1100, 22000);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $res->assertStatus(200);

        $comparison = $res->viewData('comparison');
        $trend = $comparison['operational_trend'];

        $this->assertEquals('Pax', $trend['passenger'][0]['unit']);
        $this->assertEquals('Movements', $trend['aircraft'][0]['unit']);
        $this->assertEquals('Kg', $trend['cargo'][0]['unit']);

        $this->assertEquals(50000, $trend['passenger'][0]['value']);
        $this->assertEquals(1000, $trend['aircraft'][0]['value']);
        $this->assertEquals(20000, $trend['cargo'][0]['value']);
    }
}
