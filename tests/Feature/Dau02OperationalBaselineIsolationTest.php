<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02OperationalBaselineIsolationTest extends TestCase
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
                        'aircraft_arrival' => (int)($ac * 0.3), 'aircraft_departure' => (int)($ac * 0.3), 'aircraft_total' => (int)($ac * 0.6),
                        'passenger_arrival' => (int)($pax * 0.3), 'passenger_departure' => (int)($pax * 0.3), 'passenger_total' => (int)($pax * 0.6),
                        'cargo_arrival' => (int)($cargo * 0.3), 'cargo_departure' => (int)($cargo * 0.3), 'cargo' => (int)($cargo * 0.6),
                    ],
                    [
                        'category' => 'INTERNASIONAL',
                        'aircraft_arrival' => (int)($ac * 0.2), 'aircraft_departure' => (int)($ac * 0.2), 'aircraft_total' => (int)($ac * 0.4),
                        'passenger_arrival' => (int)($pax * 0.2), 'passenger_departure' => (int)($pax * 0.2), 'passenger_total' => (int)($pax * 0.4),
                        'cargo_arrival' => (int)($cargo * 0.2), 'cargo_departure' => (int)($cargo * 0.2), 'cargo' => (int)($cargo * 0.4),
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
     * Test Baseline Period selection does NOT alter chart values for Passenger, Aircraft, or Cargo.
     */
    public function test_baseline_selection_does_not_alter_chart_values(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);
        $u3 = $this->createReport(2022, 15000, 250, 6000);

        // Capture with Baseline = P1 (2020)
        $resBase1 = $this->get("/dau/compare?reports={$u1->id},{$u2->id},{$u3->id}&baseline=P1");
        $compBase1 = $resBase1->viewData('comparison');

        // Capture with Baseline = P3 (2022)
        $resBase3 = $this->get("/dau/compare?reports={$u1->id},{$u2->id},{$u3->id}&baseline=P3");
        $compBase3 = $resBase3->viewData('comparison');

        // Passenger bar and trend values must remain strictly identical
        $pax1 = array_column($compBase1['operational_trend']['passenger'], 'value');
        $pax3 = array_column($compBase3['operational_trend']['passenger'], 'value');
        $this->assertEquals([10000, 12000, 15000], $pax1);
        $this->assertEquals($pax1, $pax3);

        // Aircraft bar and trend values must remain strictly identical
        $ac1 = array_column($compBase1['operational_trend']['aircraft'], 'value');
        $ac3 = array_column($compBase3['operational_trend']['aircraft'], 'value');
        $this->assertEquals([200, 220, 250], $ac1);
        $this->assertEquals($ac1, $ac3);

        // Cargo bar and trend values must remain strictly identical
        $cg1 = array_column($compBase1['operational_trend']['cargo'], 'value');
        $cg3 = array_column($compBase3['operational_trend']['cargo'], 'value');
        $this->assertEquals([5000, 5500, 6000], $cg1);
        $this->assertEquals($cg1, $cg3);
    }

    /**
     * Test Historical filters (DOM/INT, ARR/DEP) do NOT alter Section 1 Kinerja Operasional Bandara.
     */
    public function test_historical_filters_do_not_alter_kinerja_operasional_charts(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);

        $resAll = $this->get("/dau/compare?reports={$u1->id},{$u2->id}&hist_scope=ALL&hist_direction=ALL");
        $compAll = $resAll->viewData('comparison');

        $resDom = $this->get("/dau/compare?reports={$u1->id},{$u2->id}&hist_scope=DOM&hist_direction=DEPARTURE");
        $compDom = $resDom->viewData('comparison');

        // Section 1 Kinerja Operasional must be identical
        $this->assertEquals(
            $compAll['operational_trend']['passenger'],
            $compDom['operational_trend']['passenger']
        );
        $this->assertEquals(
            $compAll['operational_trend']['aircraft'],
            $compDom['operational_trend']['aircraft']
        );
        $this->assertEquals(
            $compAll['operational_trend']['cargo'],
            $compDom['operational_trend']['cargo']
        );

        // But Section 2 Historical Model DOES change according to filters
        $this->assertNotEquals(
            $compAll['historical_model']['metrics']['passenger']['datasets'],
            $compDom['historical_model']['metrics']['passenger']['datasets']
        );
    }
}
