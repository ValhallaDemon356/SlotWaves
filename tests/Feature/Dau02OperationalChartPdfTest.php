<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Services\Dau\DauComparisonChartRenderer;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02OperationalChartPdfTest extends TestCase
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
     * Test renderer generates valid 2x retina PNG with Bar + Trend line using actual values.
     */
    public function test_operational_combo_chart_renderer_produces_valid_png(): void
    {
        $points = [
            ['key' => 'P1', 'short_label' => '2020', 'value' => 100, 'unit' => 'Pax', 'is_baseline' => true],
            ['key' => 'P2', 'short_label' => '2021', 'value' => 120, 'unit' => 'Pax', 'is_baseline' => false],
            ['key' => 'P3', 'short_label' => '2022', 'value' => 150, 'unit' => 'Pax', 'is_baseline' => false],
        ];

        $dataUri = DauComparisonChartRenderer::renderOperationalComboChartPng(
            $points,
            'Pax',
            '#2563eb',
            '#1d4ed8',
            'P1',
            'Pergerakan Penumpang',
            680,
            160
        );

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
        $binary = base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
        $this->assertNotEmpty($binary);

        $size = getimagesizefromstring($binary);
        $this->assertNotFalse($size);
        $this->assertEquals(1360, $size[0]); // 680 * 2
        $this->assertEquals(320,  $size[1]); // 160 * 2
        $this->assertEquals('image/png', $size['mime']);
    }

    /**
     * Test PDF export contains all three separate combo charts and matches browser values.
     */
    public function test_pdf_export_renders_all_three_combo_charts(): void
    {
        $u1 = $this->createReport(2020, 10000, 200, 5000);
        $u2 = $this->createReport(2021, 12000, 220, 5500);

        // Get browser data
        $dashRes = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $dashRes->assertStatus(200);
        $comp = $dashRes->viewData('comparison');

        // Export PDF
        $pdfRes = $this->get("/dau/compare/export/pdf?reports={$u1->id},{$u2->id}");
        $pdfRes->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $pdfRes->headers->get('content-type'));

        $pdfContent = $pdfRes->getContent();
        $this->assertNotEmpty($pdfContent);
        $this->assertGreaterThan(1000, strlen($pdfContent));

        // Verify that data payload matches
        $this->assertEquals(10000, $comp['operational_trend']['passenger'][0]['value']);
        $this->assertEquals(200,   $comp['operational_trend']['aircraft'][0]['value']);
        $this->assertEquals(5000,  $comp['operational_trend']['cargo'][0]['value']);
    }
}
