<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Services\Dau\DauComparisonChartRenderer;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02CombinedOperationalChartPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    /**
     * Test combined chart renderer produces valid 2x retina PNG data URI with 3 independent scales.
     */
    public function test_combined_chart_renderer_produces_valid_png_data_uri(): void
    {
        $periods = [
            ['key' => 'P1', 'short_label' => '2020', 'label' => 'PERIOD 2020'],
            ['key' => 'P2', 'short_label' => '2021', 'label' => 'PERIOD 2021'],
            ['key' => 'P3', 'short_label' => '2022', 'label' => 'PERIOD 2022'],
        ];

        $operationalTrend = [
            'passenger' => [
                ['key' => 'P1', 'short_label' => '2020', 'value' => 54953746.0, 'unit' => 'Pax', 'is_baseline' => true],
                ['key' => 'P2', 'short_label' => '2021', 'value' => 45000000.0, 'unit' => 'Pax', 'is_baseline' => false],
                ['key' => 'P3', 'short_label' => '2022', 'value' => 62000000.0, 'unit' => 'Pax', 'is_baseline' => false],
            ],
            'aircraft' => [
                ['key' => 'P1', 'short_label' => '2020', 'value' => 368269.0, 'unit' => 'Movements', 'is_baseline' => true],
                ['key' => 'P2', 'short_label' => '2021', 'value' => 310000.0, 'unit' => 'Movements', 'is_baseline' => false],
                ['key' => 'P3', 'short_label' => '2022', 'value' => 395000.0, 'unit' => 'Movements', 'is_baseline' => false],
            ],
            'cargo' => [
                ['key' => 'P1', 'short_label' => '2020', 'value' => 682008176.0, 'unit' => 'Kg', 'is_baseline' => true],
                ['key' => 'P2', 'short_label' => '2021', 'value' => 590000000.0, 'unit' => 'Kg', 'is_baseline' => false],
                ['key' => 'P3', 'short_label' => '2022', 'value' => 710000000.0, 'unit' => 'Kg', 'is_baseline' => false],
            ],
        ];

        $dataUri = DauComparisonChartRenderer::renderCombinedOperationalChartPng(
            $periods,
            $operationalTrend,
            'Kg',
            'P1',
            'Pax and Flight Trend',
            700,
            250
        );

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $binary = base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
        $this->assertNotEmpty($binary);

        $size = getimagesizefromstring($binary);
        $this->assertNotFalse($size);
        $this->assertEquals(1400, $size[0]); // 700 * 2
        $this->assertEquals(500,  $size[1]); // 250 * 2
        $this->assertEquals('image/png', $size['mime']);
    }

    /**
     * Test empty periods fallback returns valid placeholder image without error.
     */
    public function test_combined_chart_renderer_handles_empty_periods(): void
    {
        $dataUri = DauComparisonChartRenderer::renderCombinedOperationalChartPng(
            [],
            ['passenger' => [], 'aircraft' => [], 'cargo' => []],
            'Kg',
            null,
            'Pax and Flight Trend',
            700,
            250
        );

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    /**
     * Test PDF export contains the combined operational chart and valid binary stream.
     */
    public function test_pdf_export_contains_combined_operational_chart(): void
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
