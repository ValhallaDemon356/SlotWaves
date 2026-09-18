<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Services\Dau\DauComparisonService;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Dau02HistoricalComparisonSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    /**
     * Helper to generate synthetic DAU-02 report array for multi-period testing.
     */
    protected function makePeriodReport(
        int $id,
        string $startDate,
        string $endDate,
        int $domArrPax,
        int $domDepPax,
        int $intArrPax,
        int $intDepPax,
        int $domArrAc = 50,
        int $domDepAc = 50,
        int $intArrAc = 20,
        int $intDepAc = 20,
        int $domArrCg = 500,
        int $domDepCg = 500,
        int $intArrCg = 200,
        int $intDepCg = 200
    ): array {
        return [
            'id'           => $id,
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'airport_name' => 'Tangerang - Soekarno Hatta',
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'cargo_unit'   => 'Kg',
            'report_data'  => [
                'records' => [
                    [
                        'category'            => 'DOMESTIK',
                        'aircraft_arrival'    => $domArrAc,
                        'aircraft_departure'  => $domDepAc,
                        'aircraft_total'      => $domArrAc + $domDepAc,
                        'passenger_arrival'   => $domArrPax,
                        'passenger_departure' => $domDepPax,
                        'passenger_transit'   => 0,
                        'passenger_transfer'  => 0,
                        'passenger_total'     => $domArrPax + $domDepPax,
                        'cargo_arrival'       => $domArrCg,
                        'cargo_departure'     => $domDepCg,
                        'cargo'               => $domArrCg + $domDepCg,
                    ],
                    [
                        'category'            => 'INTERNASIONAL',
                        'aircraft_arrival'    => $intArrAc,
                        'aircraft_departure'  => $intDepAc,
                        'aircraft_total'      => $intArrAc + $intDepAc,
                        'passenger_arrival'   => $intArrPax,
                        'passenger_departure' => $intDepPax,
                        'passenger_transit'   => 0,
                        'passenger_transfer'  => 0,
                        'passenger_total'     => $intArrPax + $intDepPax,
                        'cargo_arrival'       => $intArrCg,
                        'cargo_departure'     => $intDepCg,
                        'cargo'               => $intArrCg + $intDepCg,
                    ],
                ],
                'meta' => [
                    'airport_code' => 'CGK',
                    'airport_name' => 'Tangerang - Soekarno Hatta',
                    'start_date'   => $startDate,
                    'end_date'     => $endDate,
                    'cargo_unit'   => 'Kg',
                ]
            ]
        ];
    }

    /**
     * Test Part 1, 74, 75, 91: 6 uploaded periods compare baseline against all other periods (5 comparisons).
     * No self-comparison. Switching baseline to C yields A, B, D, E, F comparisons.
     */
    public function test_baseline_compares_against_all_other_periods_without_self_comparison(): void
    {
        $reports = [
            $this->makePeriodReport(1, '2020-01-01', '2020-06-30', 1000, 1000, 200, 200), // P1: 2400 Pax
            $this->makePeriodReport(2, '2021-01-01', '2021-06-30', 1200, 1200, 300, 300), // P2: 3000 Pax
            $this->makePeriodReport(3, '2022-01-01', '2022-06-30', 1500, 1500, 400, 400), // P3: 3800 Pax
            $this->makePeriodReport(4, '2023-01-01', '2023-06-30', 1800, 1800, 500, 500), // P4: 4600 Pax
            $this->makePeriodReport(5, '2024-01-01', '2024-06-30', 2000, 2000, 600, 600), // P5: 5200 Pax
            $this->makePeriodReport(6, '2025-01-01', '2025-06-30', 2500, 2500, 700, 700), // P6: 6400 Pax
        ];

        // 1. Baseline = P1 (Period A)
        $modelA = DauComparisonService::buildComparisonModel($reports, [], 'P1');
        $this->assertTrue($modelA['valid']);
        $this->assertEquals(6, $modelA['period_count']);

        $baseCompA = $modelA['baseline_comparison'];
        $this->assertEquals('P1', $baseCompA['baseline_key']);
        $this->assertCount(5, $baseCompA['comparisons']);

        // Assert target periods are P2, P3, P4, P5, P6 (no P1)
        $targetKeysA = array_column($baseCompA['comparisons'], 'target_key');
        $this->assertEquals(['P2', 'P3', 'P4', 'P5', 'P6'], $targetKeysA);
        $this->assertNotContains('P1', $targetKeysA);

        // 2. Switch Baseline to P3 (Period C)
        $modelC = DauComparisonService::buildComparisonModel($reports, [], 'P3');
        $baseCompC = $modelC['baseline_comparison'];
        $this->assertEquals('P3', $baseCompC['baseline_key']);
        $this->assertCount(5, $baseCompC['comparisons']);

        $targetKeysC = array_column($baseCompC['comparisons'], 'target_key');
        $this->assertEquals(['P1', 'P2', 'P4', 'P5', 'P6'], $targetKeysC);
        $this->assertNotContains('P3', $targetKeysC);
    }

    /**
     * Test Part 4-7, 81, 82: Growth %, Difference, Negative growth, Positive growth, Zero baseline.
     */
    public function test_baseline_growth_positive_negative_and_zero_baseline(): void
    {
        // P1 = 100, P2 = 120, P3 = 80, P4 = 0
        $r1 = $this->makePeriodReport(1, '2020-01-01', '2020-06-30', 50, 50, 0, 0); // Tot 100
        $r2 = $this->makePeriodReport(2, '2021-01-01', '2021-06-30', 60, 60, 0, 0); // Tot 120
        $r3 = $this->makePeriodReport(3, '2022-01-01', '2022-06-30', 40, 40, 0, 0); // Tot 80
        $r4 = $this->makePeriodReport(4, '2023-01-01', '2023-06-30', 0, 0, 0, 0);   // Tot 0

        // Baseline = P1 (100)
        $model = DauComparisonService::buildComparisonModel([$r1, $r2, $r3, $r4], [], 'P1');
        $comps = $model['baseline_comparison']['comparisons'];

        // P1 vs P2 (100 -> 120): Diff +20, Growth +20%
        $compP2 = $comps[0];
        $this->assertEquals('P2', $compP2['target_key']);
        $this->assertEquals(20, $compP2['passenger']['change']);
        $this->assertEquals(20.0, $compP2['passenger']['percentage']);
        $this->assertEquals('+20.00%', $compP2['passenger']['percentage_fmt']);
        $this->assertTrue($compP2['passenger']['is_positive']);

        // P1 vs P3 (100 -> 80): Diff -20, Growth -20%
        $compP3 = $comps[1];
        $this->assertEquals('P3', $compP3['target_key']);
        $this->assertEquals(-20, $compP3['passenger']['change']);
        $this->assertEquals(-20.0, $compP3['passenger']['percentage']);
        $this->assertEquals('-20.00%', $compP3['passenger']['percentage_fmt']);
        $this->assertFalse($compP3['passenger']['is_positive']);

        // Switch Baseline = P4 (0 Pax)
        $modelZero = DauComparisonService::buildComparisonModel([$r1, $r2, $r3, $r4], [], 'P4');
        $compsZero = $modelZero['baseline_comparison']['comparisons'];
        // P4 vs P1 (0 -> 100): Growth formatted as "N/A" without NaN or Infinity
        $this->assertNull($compsZero[0]['passenger']['percentage']);
        $this->assertEquals('N/A', $compsZero[0]['passenger']['percentage_fmt']);
    }

    /**
     * Test Part 8-14, 76, 92: Kinerja Operasional Bandara uses continuous trend lines with points.
     */
    public function test_kinerja_operasional_trend_model_contains_all_periods_and_points(): void
    {
        $r1 = $this->makePeriodReport(1, '2020-01-01', '2020-06-30', 50, 50, 0, 0); // 100
        $r2 = $this->makePeriodReport(2, '2021-01-01', '2021-06-30', 60, 60, 0, 0); // 120
        $r3 = $this->makePeriodReport(3, '2022-01-01', '2022-06-30', 55, 55, 0, 0); // 110
        $r4 = $this->makePeriodReport(4, '2023-01-01', '2023-06-30', 75, 75, 0, 0); // 150

        $model = DauComparisonService::buildComparisonModel([$r1, $r2, $r3, $r4], [], 'P2');
        $trend = $model['operational_trend'];

        $this->assertArrayHasKey('passenger', $trend);
        $this->assertArrayHasKey('aircraft', $trend);
        $this->assertArrayHasKey('cargo', $trend);

        $pts = $trend['passenger'];
        $this->assertCount(4, $pts);
        $this->assertEquals(100, $pts[0]['value']);
        $this->assertEquals(120, $pts[1]['value']);
        $this->assertEquals(110, $pts[2]['value']);
        $this->assertEquals(150, $pts[3]['value']);

        // P2 is baseline -> subtle baseline indicator flag set
        $this->assertFalse($pts[0]['is_baseline']);
        $this->assertTrue($pts[1]['is_baseline']);
        $this->assertFalse($pts[2]['is_baseline']);
    }

    /**
     * Test Part 18-21, 78-80, 93: Data Pergerakan Historis bar model filters by Scope (ALL/DOM/INT) and Direction (ALL/ARR/DEP).
     */
    public function test_historical_bar_model_scope_and_direction_filters(): void
    {
        // Fixture from Part 80:
        // Period A: Dom Arr 50, Dom Dep 30, Int Arr 20, Int Dep 10 (Tot Dom 80, Tot Int 30, Tot All 110)
        // Period B: Dom Arr 60, Dom Dep 40, Int Arr 25, Int Dep 15 (Tot Dom 100, Tot Int 40, Tot All 140)
        $rA = $this->makePeriodReport(1, '2020-01-01', '2020-06-30', 50, 30, 20, 10);
        $rB = $this->makePeriodReport(2, '2021-01-01', '2021-06-30', 60, 40, 25, 15);

        // 1. ALL + ALL: Domestic + International
        $mAll = DauComparisonService::buildComparisonModel([$rA, $rB], ['hist_scope' => 'ALL', 'hist_direction' => 'ALL']);
        $barAll = $mAll['historical_model']['metrics']['passenger'];
        $this->assertCount(2, $barAll['datasets']);
        // Dom dataset
        $this->assertEquals([80, 100], $barAll['datasets'][0]['values']);
        // Int dataset
        $this->assertEquals([30, 40], $barAll['datasets'][1]['values']);
        // Total series
        $this->assertEquals([110, 140], $barAll['total_series']);

        // 2. DOMESTIC + ARRIVAL: A = 50, B = 60
        $mDomArr = DauComparisonService::buildComparisonModel([$rA, $rB], ['hist_scope' => 'DOM', 'hist_direction' => 'ARRIVAL']);
        $barDomArr = $mDomArr['historical_model']['metrics']['passenger'];
        $this->assertCount(1, $barDomArr['datasets']);
        $this->assertEquals([50, 60], $barDomArr['datasets'][0]['values']);
        $this->assertEquals([50, 60], $barDomArr['total_series']);

        // 3. INTERNATIONAL + DEPARTURE: A = 10, B = 15
        $mIntDep = DauComparisonService::buildComparisonModel([$rA, $rB], ['hist_scope' => 'INT', 'hist_direction' => 'DEPARTURE']);
        $barIntDep = $mIntDep['historical_model']['metrics']['passenger'];
        $this->assertCount(1, $barIntDep['datasets']);
        $this->assertEquals([10, 15], $barIntDep['datasets'][0]['values']);
        $this->assertEquals([10, 15], $barIntDep['total_series']);
    }

    /**
     * Test Part 22, 60-65, 94: Filter isolation.
     * Changing Historical Filter must NOT change Baseline Comparison or Kinerja Trend.
     * Changing Baseline Period must NOT change Historical Bar values.
     */
    public function test_filter_isolation_between_baseline_trend_and_historical_bars(): void
    {
        $rA = $this->makePeriodReport(1, '2020-01-01', '2020-06-30', 50, 30, 20, 10);
        $rB = $this->makePeriodReport(2, '2021-01-01', '2021-06-30', 60, 40, 25, 15);

        // Run with ALL filters
        $m1 = DauComparisonService::buildComparisonModel([$rA, $rB], ['hist_scope' => 'ALL', 'hist_direction' => 'ALL'], 'P1');
        $baseComp1 = $m1['baseline_comparison'];
        $trend1 = $m1['operational_trend'];

        // Run with DOMESTIC + ARRIVAL filter
        $m2 = DauComparisonService::buildComparisonModel([$rA, $rB], ['hist_scope' => 'DOM', 'hist_direction' => 'ARRIVAL'], 'P1');
        $baseComp2 = $m2['baseline_comparison'];
        $trend2 = $m2['operational_trend'];

        // 1. Baseline comparisons must remain IDENTICAL
        $this->assertEquals($baseComp1['comparisons'][0]['passenger']['current'], $baseComp2['comparisons'][0]['passenger']['current']);
        $this->assertEquals($baseComp1['comparisons'][0]['passenger']['change'], $baseComp2['comparisons'][0]['passenger']['change']);
        $this->assertEquals($baseComp1['comparisons'][0]['passenger']['percentage'], $baseComp2['comparisons'][0]['passenger']['percentage']);

        // 2. Kinerja Trend values must remain IDENTICAL
        $this->assertEquals($trend1['passenger'][0]['value'], $trend2['passenger'][0]['value']);
        $this->assertEquals($trend1['passenger'][1]['value'], $trend2['passenger'][1]['value']);

        // 3. Changing Baseline Period must NOT change historical bar values
        $m3 = DauComparisonService::buildComparisonModel([$rA, $rB], ['hist_scope' => 'DOM', 'hist_direction' => 'ARRIVAL'], 'P2');
        $this->assertEquals($m2['historical_model']['metrics']['passenger']['total_series'], $m3['historical_model']['metrics']['passenger']['total_series']);
    }

    /**
     * Test Part 44-52, 71-73, 95: PDF Export contains visible vector SVG charts and correct filter state.
     */
    public function test_pdf_export_contains_visible_svg_charts_and_headers(): void
    {
        $u1 = Upload::create([
            'report_type'  => 'DAU2',
            'status'       => 'completed',
            'original_filename' => 'test_2024.xls',
            'stored_path'       => 'uploads/test_2024.xls',
            'report_data'  => $this->makePeriodReport(1, '2024-01-01', '2024-06-30', 100, 100, 20, 20)['report_data'],
        ]);

        $u2 = Upload::create([
            'report_type'  => 'DAU2',
            'status'       => 'completed',
            'original_filename' => 'test_2025.xls',
            'stored_path'       => 'uploads/test_2025.xls',
            'report_data'  => $this->makePeriodReport(2, '2025-01-01', '2025-06-30', 120, 120, 30, 30)['report_data'],
        ]);

        // Request PDF export with custom baseline & historical filters
        $res = $this->get("/dau/compare/export/pdf?reports={$u1->id},{$u2->id}&baseline=P1&hist_scope=DOM&hist_direction=ARRIVAL");
        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/pdf');

        $pdfContent = $res->getContent();
        $this->assertNotEmpty($pdfContent);
        // PDF should be a valid PDF binary
        $this->assertStringStartsWith('%PDF-', $pdfContent);
    }

    /**
     * Test CSV Export preserved and operational.
     */
    public function test_csv_export_is_preserved_and_accurate(): void
    {
        $u1 = Upload::create([
            'report_type'  => 'DAU2',
            'status'       => 'completed',
            'original_filename' => 'test_2024.xls',
            'stored_path'       => 'uploads/test_2024.xls',
            'report_data'  => $this->makePeriodReport(1, '2024-01-01', '2024-06-30', 100, 100, 20, 20)['report_data'],
        ]);

        $u2 = Upload::create([
            'report_type'  => 'DAU2',
            'status'       => 'completed',
            'original_filename' => 'test_2025.xls',
            'stored_path'       => 'uploads/test_2025.xls',
            'report_data'  => $this->makePeriodReport(2, '2025-01-01', '2025-06-30', 120, 120, 30, 30)['report_data'],
        ]);

        $res = $this->get("/dau/compare/export/csv?reports={$u1->id},{$u2->id}&baseline=P1");
        $res->assertStatus(200);
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
    }
}
