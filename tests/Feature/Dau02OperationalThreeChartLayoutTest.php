<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02OperationalThreeChartLayoutTest extends TestCase
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
     * Test Section 1 renders three separate chart cards and three distinct canvas elements.
     */
    public function test_three_separate_combo_charts_rendered_in_section_1(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $res->assertStatus(200);

        // Section 1 headers and 3 separate categories
        $res->assertSee('KINERJA OPERASIONAL BANDARA');
        $res->assertSee('1. PERGERAKAN PENUMPANG');
        $res->assertSee('2. PERGERAKAN PESAWAT');
        $res->assertSee('3. PERGERAKAN KARGO');

        // Subtitles
        $res->assertSee('Passenger Movement Trend');
        $res->assertSee('Aircraft Movement Trend');
        $res->assertSee('Cargo Movement Trend');

        // Three separate canvas elements exist
        $res->assertSee('id="chart-trend-passenger"', false);
        $res->assertSee('id="chart-trend-aircraft"', false);
        $res->assertSee('id="chart-trend-cargo"', false);

        // Minimal legends present on cards
        $res->assertSee('Actual Movement');
        $res->assertSee('Trend');
    }

    /**
     * Test Chart Independence: changing Passenger data does NOT alter Aircraft or Cargo metrics.
     */
    public function test_chart_independence_across_categories(): void
    {
        // Dataset A: Pax 10000, AC 200, Cargo 5000
        $u1a = $this->createReport(2020, 10000, 200, 5000);
        $u2a = $this->createReport(2021, 12000, 220, 5500);

        $resA = $this->get("/dau/compare?reports={$u1a->id},{$u2a->id}");
        $compA = $resA->viewData('comparison');

        // Dataset B: Pax changed to 99999, but AC and Cargo remain 200 & 5000
        $u1b = $this->createReport(2020, 99999, 200, 5000);
        $u2b = $this->createReport(2021, 88888, 220, 5500);

        $resB = $this->get("/dau/compare?reports={$u1b->id},{$u2b->id}");
        $compB = $resB->viewData('comparison');

        // Passenger changed
        $this->assertNotEquals($compA['operational_trend']['passenger'], $compB['operational_trend']['passenger']);

        // Aircraft and Cargo remain strictly identical
        $this->assertEquals($compA['operational_trend']['aircraft'], $compB['operational_trend']['aircraft']);
        $this->assertEquals($compA['operational_trend']['cargo'], $compB['operational_trend']['cargo']);
    }
}
