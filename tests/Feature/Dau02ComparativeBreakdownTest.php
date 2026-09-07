<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use App\Services\Dau\Parsers\DAU2Parser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau02ComparativeBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private Upload $dau2Upload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->airport = Airport::firstOrCreate(
            ['iata_code' => 'CGK'],
            [
                'name'    => 'Soekarno Hatta',
                'city'    => 'Tangerang',
                'country' => 'Indonesia',
            ]
        );

        // Parse authentic DAU-2.xls
        $parser = new DAU2Parser();
        $samplePath = base_path('resources/templates/dau/DAU-2.xls');
        $parsed = $parser->parse($samplePath);

        $this->dau2Upload = Upload::create([
            'original_filename' => 'DAU-2.xls',
            'stored_path'       => 'uploads/dau2.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU2',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $parsed,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);
    }

    /**
     * TEST 1: DAU-02 Dashboard renders Comparative Breakdown section and display mode toggle.
     */
    public function test_dau02_dashboard_renders_comparative_breakdown_section(): void
    {
        $response = $this->get(route('dau.dashboard', $this->dau2Upload->id));
        $response->assertOk();

        // Check prominent Comparative Breakdown header
        $response->assertSee('COMPARATIVE BREAKDOWN');
        $response->assertSee('Perbandingan Komparatif');

        // Check Display Mode buttons
        $response->assertSee('ABSOLUTE');
        $response->assertSee('PERCENTAGE');

        // Check column headers
        $response->assertSee('DOMESTIK');
        $response->assertSee('INTERNASIONAL');
        $response->assertSee('TOTAL');
        $response->assertSee('Proporsi Dom / Int');

        // Check table template binding
        $response->assertSee('dau2ComparativeList', false);
    }

    /**
     * TEST 2: DAU-02 Comparative Breakdown strictly matches ground-truth values from source data.
     */
    public function test_dau02_comparative_breakdown_matches_ground_truth_values(): void
    {
        $response = $this->get(route('dau.dashboard', $this->dau2Upload->id));
        $response->assertOk();

        $analytics = $response->viewData('analytics');
        $comp = $analytics['dau2_comparative'] ?? [];

        // 1. Aircraft Movements
        $this->assertArrayHasKey('aircraft', $comp);
        $this->assertEquals(735, $comp['aircraft']['domestic']);
        $this->assertEquals(284, $comp['aircraft']['international']);
        $this->assertEquals(1019, $comp['aircraft']['total']);
        $this->assertEquals(72.1, $comp['aircraft']['domestic_pct']);
        $this->assertEquals(27.9, $comp['aircraft']['international_pct']);

        // 2. Passengers (PAX)
        $this->assertArrayHasKey('passenger', $comp);
        $this->assertEquals(96381, $comp['passenger']['domestic']);
        $this->assertEquals(52917, $comp['passenger']['international']);
        $this->assertEquals(149298, $comp['passenger']['total']);
        $this->assertEquals(64.6, $comp['passenger']['domestic_pct']);
        $this->assertEquals(35.4, $comp['passenger']['international_pct']);

        // 3. Baggage (KG)
        $this->assertArrayHasKey('baggage', $comp);
        $this->assertEquals(891752, $comp['baggage']['domestic']);
        $this->assertEquals(828169, $comp['baggage']['international']);
        $this->assertEquals(1719921, $comp['baggage']['total']);

        // 4. Cargo (KG)
        $this->assertArrayHasKey('cargo', $comp);
        $this->assertEquals(902177, $comp['cargo']['domestic']);
        $this->assertEquals(870825, $comp['cargo']['international']);
        $this->assertEquals(1773002, $comp['cargo']['total']);

        // 5. POS / Mail (KG)
        $this->assertArrayHasKey('pos', $comp);
        $this->assertEquals(0, $comp['pos']['domestic']);
        $this->assertEquals(0, $comp['pos']['international']);
        $this->assertEquals(0, $comp['pos']['total']);
    }

    /**
     * TEST 3: Metric switch to Passenger updates filters and active metric in controller.
     */
    public function test_dau02_metric_switch_to_passenger(): void
    {
        $response = $this->get(route('dau.dashboard', [
            'upload' => $this->dau2Upload->id,
            'metric' => 'passenger',
        ]));

        $response->assertOk();
        $analytics = $response->viewData('analytics');
        $this->assertEquals('passenger', $analytics['filters']['metric']);
    }

    /**
     * TEST 4: Percentage display mode is handled in controller and query params.
     */
    public function test_dau02_display_mode_percentage(): void
    {
        $response = $this->get(route('dau.dashboard', [
            'upload' => $this->dau2Upload->id,
            'display_mode' => 'percentage',
        ]));

        $response->assertOk();
        $analytics = $response->viewData('analytics');
        $this->assertEquals('percentage', $analytics['filters']['display_mode']);
    }

    /**
     * TEST 5: PDF Export renders Comparative Breakdown table and 100% stacked chart.
     */
    public function test_dau02_pdf_export_renders_comparative_breakdown(): void
    {
        $response = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau2Upload->id,
            'metric' => 'passenger',
            'display_mode' => 'absolute',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
    }
}
