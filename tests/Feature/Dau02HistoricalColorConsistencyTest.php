<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Dau\DauComparisonService;
use App\Services\Dau\DauComparisonChartRenderer;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02HistoricalColorConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    protected function makePeriodReport(
        int $id,
        string $startDate,
        string $endDate,
        int $domArrPax = 100,
        int $domDepPax = 80,
        int $intArrPax = 40,
        int $intDepPax = 30,
        int $domArrAc = 50,
        int $domDepAc = 45,
        int $intArrAc = 20,
        int $intDepAc = 15,
        int $domArrCg = 5000,
        int $domDepCg = 4500,
        int $intArrCg = 2000,
        int $intDepCg = 1500
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
                        'passenger_total'     => $domArrPax + $domDepPax,
                        'cargo_arrival'       => $domArrCg,
                        'cargo_departure'     => $domDepCg,
                        'cargo_total'         => $domArrCg + $domDepCg,
                    ],
                    [
                        'category'            => 'INTERNASIONAL',
                        'aircraft_arrival'    => $intArrAc,
                        'aircraft_departure'  => $intDepAc,
                        'aircraft_total'      => $intArrAc + $intDepAc,
                        'passenger_arrival'   => $intArrPax,
                        'passenger_departure' => $intDepPax,
                        'passenger_total'     => $intArrPax + $intDepPax,
                        'cargo_arrival'       => $intArrCg,
                        'cargo_departure'     => $intDepCg,
                        'cargo_total'         => $intArrCg + $intDepCg,
                    ],
                ],
            ],
            'meta' => [
                'airport_name' => 'Tangerang - Soekarno Hatta',
                'airport_code' => 'CGK',
                'cargo_unit'   => 'Kg',
                'start_date'   => $startDate,
                'end_date'     => $endDate,
            ]
        ];
    }

    /**
     * Test 1: When SCOPE = ALL, all three metrics use their respective color families:
     * - Passenger: Blue family (Domestic = #2563eb, International = #93c5fd)
     * - Aircraft: Green family (Domestic = #059669, International = #6ee7b7)
     * - Cargo: Orange family (Domestic = #d97706, International = #fcd34d)
     */
    public function test_historical_color_mapping_across_metrics_in_all_scope(): void
    {
        $r1 = $this->makePeriodReport(1, '2020-01-01', '2020-12-31');
        $r2 = $this->makePeriodReport(2, '2021-01-01', '2021-12-31');

        $model = DauComparisonService::buildComparisonModel([$r1, $r2], [
            'hist_scope'     => 'ALL',
            'hist_direction' => 'ALL'
        ]);

        $histMetrics = $model['historical_model']['metrics'];

        // 1. Passenger: Blue Family
        $paxDatasets = $histMetrics['passenger']['datasets'];
        $this->assertCount(2, $paxDatasets);
        $this->assertEquals('Domestic', $paxDatasets[0]['label']);
        $this->assertEquals('#2563eb', $paxDatasets[0]['color']); // Strong Blue
        $this->assertEquals('International', $paxDatasets[1]['label']);
        $this->assertEquals('#93c5fd', $paxDatasets[1]['color']); // Light Blue

        // 2. Aircraft: Green Family
        $acDatasets = $histMetrics['aircraft']['datasets'];
        $this->assertCount(2, $acDatasets);
        $this->assertEquals('Domestic', $acDatasets[0]['label']);
        $this->assertEquals('#059669', $acDatasets[0]['color']); // Strong Green
        $this->assertEquals('International', $acDatasets[1]['label']);
        $this->assertEquals('#6ee7b7', $acDatasets[1]['color']); // Light Green

        // 3. Cargo: Orange Family
        $cgDatasets = $histMetrics['cargo']['datasets'];
        $this->assertCount(2, $cgDatasets);
        $this->assertEquals('Domestic', $cgDatasets[0]['label']);
        $this->assertEquals('#d97706', $cgDatasets[0]['color']); // Strong Orange
        $this->assertEquals('International', $cgDatasets[1]['label']);
        $this->assertEquals('#fcd34d', $cgDatasets[1]['color']); // Light Orange
    }

    /**
     * Test 2: When SCOPE = DOMESTIC, only Domestic series is returned with its primary shade.
     * International series is hidden entirely (not zero bars).
     */
    public function test_historical_domestic_scope_hides_international_and_retains_primary_shade(): void
    {
        $r1 = $this->makePeriodReport(1, '2020-01-01', '2020-12-31');
        $r2 = $this->makePeriodReport(2, '2021-01-01', '2021-12-31');

        $model = DauComparisonService::buildComparisonModel([$r1, $r2], [
            'hist_scope'     => 'DOM',
            'hist_direction' => 'ALL'
        ]);

        $histMetrics = $model['historical_model']['metrics'];

        // Passenger
        $this->assertCount(1, $histMetrics['passenger']['datasets']);
        $this->assertEquals('Domestic', $histMetrics['passenger']['datasets'][0]['label']);
        $this->assertEquals('#2563eb', $histMetrics['passenger']['datasets'][0]['color']);

        // Aircraft
        $this->assertCount(1, $histMetrics['aircraft']['datasets']);
        $this->assertEquals('Domestic', $histMetrics['aircraft']['datasets'][0]['label']);
        $this->assertEquals('#059669', $histMetrics['aircraft']['datasets'][0]['color']);

        // Cargo
        $this->assertCount(1, $histMetrics['cargo']['datasets']);
        $this->assertEquals('Domestic', $histMetrics['cargo']['datasets'][0]['label']);
        $this->assertEquals('#d97706', $histMetrics['cargo']['datasets'][0]['color']);
    }

    /**
     * Test 3: When SCOPE = INTERNATIONAL, only International series is returned with its secondary shade.
     * Domestic series is hidden entirely.
     */
    public function test_historical_international_scope_hides_domestic_and_retains_secondary_shade(): void
    {
        $r1 = $this->makePeriodReport(1, '2020-01-01', '2020-12-31');
        $r2 = $this->makePeriodReport(2, '2021-01-01', '2021-12-31');

        $model = DauComparisonService::buildComparisonModel([$r1, $r2], [
            'hist_scope'     => 'INT',
            'hist_direction' => 'ALL'
        ]);

        $histMetrics = $model['historical_model']['metrics'];

        // Passenger
        $this->assertCount(1, $histMetrics['passenger']['datasets']);
        $this->assertEquals('International', $histMetrics['passenger']['datasets'][0]['label']);
        $this->assertEquals('#93c5fd', $histMetrics['passenger']['datasets'][0]['color']);

        // Aircraft
        $this->assertCount(1, $histMetrics['aircraft']['datasets']);
        $this->assertEquals('International', $histMetrics['aircraft']['datasets'][0]['label']);
        $this->assertEquals('#6ee7b7', $histMetrics['aircraft']['datasets'][0]['color']);

        // Cargo
        $this->assertCount(1, $histMetrics['cargo']['datasets']);
        $this->assertEquals('International', $histMetrics['cargo']['datasets'][0]['label']);
        $this->assertEquals('#fcd34d', $histMetrics['cargo']['datasets'][0]['color']);
    }

    /**
     * Test 4: Direction filters (ARRIVAL, DEPARTURE) preserve metric color families.
     */
    public function test_direction_filters_preserve_metric_color_family(): void
    {
        $r1 = $this->makePeriodReport(1, '2020-01-01', '2020-12-31');
        $r2 = $this->makePeriodReport(2, '2021-01-01', '2021-12-31');

        foreach (['ARRIVAL', 'DEPARTURE'] as $dir) {
            $model = DauComparisonService::buildComparisonModel([$r1, $r2], [
                'hist_scope'     => 'ALL',
                'hist_direction' => $dir
            ]);

            $histMetrics = $model['historical_model']['metrics'];

            $this->assertEquals('#2563eb', $histMetrics['passenger']['datasets'][0]['color']);
            $this->assertEquals('#93c5fd', $histMetrics['passenger']['datasets'][1]['color']);

            $this->assertEquals('#059669', $histMetrics['aircraft']['datasets'][0]['color']);
            $this->assertEquals('#6ee7b7', $histMetrics['aircraft']['datasets'][1]['color']);

            $this->assertEquals('#d97706', $histMetrics['cargo']['datasets'][0]['color']);
            $this->assertEquals('#fcd34d', $histMetrics['cargo']['datasets'][1]['color']);
        }
    }

    /**
     * Test 5: PDF historical bar chart renderer generates valid PNG image with metric colors.
     */
    public function test_pdf_historical_bar_renderer_uses_metric_palette(): void
    {
        $r1 = $this->makePeriodReport(1, '2020-01-01', '2020-12-31');
        $r2 = $this->makePeriodReport(2, '2021-01-01', '2021-12-31');

        $model = DauComparisonService::buildComparisonModel([$r1, $r2], [
            'hist_scope'     => 'ALL',
            'hist_direction' => 'ALL'
        ]);

        $periods = array_values($model['periods']);

        foreach (['passenger', 'aircraft', 'cargo'] as $mKey) {
            $datasets = $model['historical_model']['metrics'][$mKey]['datasets'];
            $unit = $model['historical_model']['metrics'][$mKey]['unit'];

            $chartPng = DauComparisonChartRenderer::renderHistoricalBarSvg(
                $periods,
                $datasets,
                $unit,
                ucfirst($mKey),
                680,
                140
            );

            $this->assertStringStartsWith('data:image/png;base64,', $chartPng);
            $this->assertGreaterThan(500, strlen($chartPng));
        }
    }

    /**
     * Test 6: Baseline changes do NOT alter historical series values or colors.
     */
    public function test_baseline_isolation_from_historical_colors_and_values(): void
    {
        $r1 = $this->makePeriodReport(1, '2020-01-01', '2020-12-31');
        $r2 = $this->makePeriodReport(2, '2021-01-01', '2021-12-31');

        $model1 = DauComparisonService::buildComparisonModel([$r1, $r2], [
            'baseline' => 1,
            'hist_scope' => 'ALL',
        ]);

        $model2 = DauComparisonService::buildComparisonModel([$r1, $r2], [
            'baseline' => 2,
            'hist_scope' => 'ALL',
        ]);

        $this->assertEquals(
            $model1['historical_model']['metrics']['passenger']['datasets'],
            $model2['historical_model']['metrics']['passenger']['datasets']
        );
        $this->assertEquals(
            $model1['historical_model']['metrics']['aircraft']['datasets'],
            $model2['historical_model']['metrics']['aircraft']['datasets']
        );
        $this->assertEquals(
            $model1['historical_model']['metrics']['cargo']['datasets'],
            $model2['historical_model']['metrics']['cargo']['datasets']
        );
    }
}
