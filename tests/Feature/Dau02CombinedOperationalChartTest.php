<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02CombinedOperationalChartTest extends TestCase
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
     * TEST 1: Verify exactly one combined chart exists in Section 1 (not 3 separate combo charts).
     */
    public function test_section_1_renders_one_combined_chart_instance(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $res->assertStatus(200);

        // Section 1 headers and tags
        $res->assertSee('KINERJA OPERASIONAL BANDARA');
        $res->assertSee('SECTION 1 &bull; OPERATIONAL PERFORMANCE', false);
        $res->assertSee('Combined Operational Trend');
        $res->assertSee('Pax and Flight Trend');

        // Combined canvas exists
        $res->assertSee('id="chart-operational-combined"', false);

        // The 3 previous separate chart canvases must NOT exist
        $res->assertDontSee('id="chart-trend-passenger"', false);
        $res->assertDontSee('id="chart-trend-aircraft"', false);
        $res->assertDontSee('id="chart-trend-cargo"', false);
    }

    /**
     * TEST 2: Verify datasets configuration in view (Passenger=bar, Aircraft=line, Cargo=line).
     */
    public function test_combined_chart_datasets_configuration(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $res->assertStatus(200);

        // View JavaScript must configure Passenger as bar, Aircraft as line, Cargo as line
        $res->assertSee("label: 'Passenger Movement'", false);
        $res->assertSee("yAxisID: 'yPassenger'", false);

        $res->assertSee("label: 'Aircraft Movement'", false);
        $res->assertSee("yAxisID: 'yAircraft'", false);

        $res->assertSee("label: 'Cargo Movement'", false);
        $res->assertSee("yAxisID: 'yCargo'", false);
    }

    /**
     * TEST 3: Verify Passenger, Aircraft, Cargo datasets use actual totals.
     */
    public function test_datasets_use_actual_metric_totals(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $res->assertStatus(200);

        $comparison = $res->viewData('comparison');
        $this->assertNotNull($comparison);

        $trend = $comparison['operational_trend'];
        $this->assertEquals(10000, $trend['passenger'][0]['value']);
        $this->assertEquals(200,   $trend['aircraft'][0]['value']);
        $this->assertEquals(5000,  $trend['cargo'][0]['value']);

        $this->assertEquals(12000, $trend['passenger'][1]['value']);
        $this->assertEquals(220,   $trend['aircraft'][1]['value']);
        $this->assertEquals(5500,  $trend['cargo'][1]['value']);
    }

    /**
     * TEST 4 & 5: Verify 2-period test and 6-period test with chronological period labels.
     */
    public function test_two_period_and_six_period_data_completeness(): void
    {
        // 2 Period Test
        $u1 = $this->createReport(2020, 100, 20, 500);
        $u2 = $this->createReport(2021, 120, 25, 600);

        $res2 = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $res2->assertStatus(200);

        $comp2 = $res2->viewData('comparison');
        $this->assertCount(2, $comp2['operational_trend']['passenger']);
        $this->assertCount(2, $comp2['operational_trend']['aircraft']);
        $this->assertCount(2, $comp2['operational_trend']['cargo']);

        // 6 Period Test
        $u3 = $this->createReport(2022, 140, 28, 650);
        $u4 = $this->createReport(2023, 130, 26, 620);
        $u5 = $this->createReport(2024, 150, 30, 700);
        $u6 = $this->createReport(2025, 170, 35, 800);

        $res6 = $this->get("/dau/compare?reports={$u1->id},{$u2->id},{$u3->id},{$u4->id},{$u5->id},{$u6->id}");
        $res6->assertStatus(200);

        $comp6 = $res6->viewData('comparison');
        $this->assertCount(6, $comp6['operational_trend']['passenger']);
        $this->assertCount(6, $comp6['operational_trend']['aircraft']);
        $this->assertCount(6, $comp6['operational_trend']['cargo']);

        // Ensure chronological ordering across all 3 series
        $paxLabels = array_column($comp6['operational_trend']['passenger'], 'short_label');
        $acLabels  = array_column($comp6['operational_trend']['aircraft'], 'short_label');
        $cgLabels  = array_column($comp6['operational_trend']['cargo'], 'short_label');

        $this->assertEquals(['2020', '2021', '2022', '2023', '2024', '2025'], $paxLabels);
        $this->assertEquals($paxLabels, $acLabels);
        $this->assertEquals($paxLabels, $cgLabels);
    }

    /**
     * TEST 6: Verify changing baseline does NOT alter chart values.
     */
    public function test_baseline_change_does_not_alter_chart_values(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);
        $u3 = $this->createReport(2022, 11000, 210, 5200);

        $resBase1 = $this->get("/dau/compare?reports={$u1->id},{$u2->id},{$u3->id}&baseline=P1");
        $resBase3 = $this->get("/dau/compare?reports={$u1->id},{$u2->id},{$u3->id}&baseline=P3");

        $trend1 = $resBase1->viewData('comparison')['operational_trend'];
        $trend3 = $resBase3->viewData('comparison')['operational_trend'];

        foreach (['passenger', 'aircraft', 'cargo'] as $mKey) {
            for ($i = 0; $i < 3; $i++) {
                $this->assertEquals($trend1[$mKey][$i]['value'], $trend3[$mKey][$i]['value']);
            }
        }
    }

    /**
     * TEST 7 & 8: Verify Historical Scope and Direction filters do not alter Section 1.
     */
    public function test_historical_filters_isolation(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);

        $resAll = $this->get("/dau/compare?reports={$u1->id},{$u2->id}&hist_scope=ALL&hist_direction=ALL");
        $resDomArr = $this->get("/dau/compare?reports={$u1->id},{$u2->id}&hist_scope=DOM&hist_direction=ARRIVAL");
        $resIntDep = $this->get("/dau/compare?reports={$u1->id},{$u2->id}&hist_scope=INT&hist_direction=DEPARTURE");

        $trendAll = $resAll->viewData('comparison')['operational_trend'];
        $trendDom = $resDomArr->viewData('comparison')['operational_trend'];
        $trendInt = $resIntDep->viewData('comparison')['operational_trend'];

        $this->assertEquals($trendAll['passenger'], $trendDom['passenger']);
        $this->assertEquals($trendAll['aircraft'],  $trendInt['aircraft']);
        $this->assertEquals($trendAll['cargo'],     $trendDom['cargo']);
    }
}
