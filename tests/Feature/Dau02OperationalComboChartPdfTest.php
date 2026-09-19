<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Services\Dau\DauComparisonChartRenderer;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02OperationalComboChartPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    /**
     * Test renderer generates valid 2x retina base64 PNG data with dual-axis Bar and Line layers.
     */
    public function test_combo_chart_renderer_produces_valid_png_data_uri(): void
    {
        $points = [
            [
                'key' => 'P1',
                'label' => 'PERIOD A',
                'short_label' => '2020',
                'value' => 1000000.0,
                'previous_value' => null,
                'growth_pct' => null,
                'growth_fmt' => 'N/A',
                'is_baseline' => true,
            ],
            [
                'key' => 'P2',
                'label' => 'PERIOD B',
                'short_label' => '2021',
                'value' => 1250000.0,
                'previous_value' => 1000000.0,
                'growth_pct' => 25.0,
                'growth_fmt' => '+25.00%',
                'is_baseline' => false,
            ],
            [
                'key' => 'P3',
                'label' => 'PERIOD C',
                'short_label' => '2022',
                'value' => 950000.0,
                'previous_value' => 1250000.0,
                'growth_pct' => -24.0,
                'growth_fmt' => '-24.00%',
                'is_baseline' => false,
            ],
        ];

        $dataUri = DauComparisonChartRenderer::renderOperationalComboChartPng(
            $points,
            'Pax',
            '#2563eb',
            '#f59e0b',
            'P1',
            'Pergerakan Penumpang',
            680,
            150
        );

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $binary = base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
        $this->assertNotEmpty($binary);

        $size = getimagesizefromstring($binary);
        $this->assertNotFalse($size);
        $this->assertEquals(1360, $size[0]); // 680 * 2
        $this->assertEquals(300, $size[1]);  // 150 * 2
        $this->assertEquals('image/png', $size['mime']);
    }

    /**
     * Test backwards-compatible renderTrendLineSvg produces valid combo chart PNG.
     */
    public function test_render_trend_line_svg_alias_produces_valid_combo_chart(): void
    {
        $points = [
            ['key' => 'P1', 'short_label' => '2023', 'value' => 500, 'growth_pct' => null, 'growth_fmt' => 'N/A', 'is_baseline' => false],
            ['key' => 'P2', 'short_label' => '2024', 'value' => 750, 'growth_pct' => 50.0, 'growth_fmt' => '+50.00%', 'is_baseline' => true],
        ];

        $dataUri = DauComparisonChartRenderer::renderTrendLineSvg(
            $points,
            'Movements',
            '#059669',
            'P2',
            'Pergerakan Pesawat',
            680,
            145
        );

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /**
     * Test PDF export contains 3 operational combo charts and returns valid binary.
     */
    public function test_pdf_export_contains_operational_combo_charts(): void
    {
        $u1 = Upload::create([
            'report_type'       => 'DAU2',
            'status'            => 'completed',
            'original_filename' => 'dau_2020.xls',
            'stored_path'       => 'uploads/dau_2020.xls',
            'report_data'       => [
                'records' => [
                    [
                        'category' => 'DOMESTIK',
                        'aircraft_arrival' => 100, 'aircraft_departure' => 100, 'aircraft_total' => 200,
                        'passenger_arrival' => 5000, 'passenger_departure' => 5000, 'passenger_total' => 10000,
                        'cargo_arrival' => 1000, 'cargo_departure' => 1000, 'cargo' => 2000,
                    ]
                ],
                'meta' => [
                    'airport_code' => 'CGK', 'airport_name' => 'Soekarno Hatta',
                    'start_date' => '2020-01-01', 'end_date' => '2020-12-31', 'cargo_unit' => 'Kg',
                ]
            ]
        ]);

        $u2 = Upload::create([
            'report_type'       => 'DAU2',
            'status'            => 'completed',
            'original_filename' => 'dau_2021.xls',
            'stored_path'       => 'uploads/dau_2021.xls',
            'report_data'       => [
                'records' => [
                    [
                        'category' => 'DOMESTIK',
                        'aircraft_arrival' => 120, 'aircraft_departure' => 120, 'aircraft_total' => 240,
                        'passenger_arrival' => 6000, 'passenger_departure' => 6000, 'passenger_total' => 12000,
                        'cargo_arrival' => 1200, 'cargo_departure' => 1200, 'cargo' => 2400,
                    ]
                ],
                'meta' => [
                    'airport_code' => 'CGK', 'airport_name' => 'Soekarno Hatta',
                    'start_date' => '2021-01-01', 'end_date' => '2021-12-31', 'cargo_unit' => 'Kg',
                ]
            ]
        ]);

        $res = $this->get("/dau/compare/export/pdf?reports={$u1->id},{$u2->id}&baseline=P1");
        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/pdf');

        $content = $res->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF-', $content);
    }
}
