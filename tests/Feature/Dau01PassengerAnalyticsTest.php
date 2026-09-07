<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use App\Services\Dau\Parsers\DAU1Parser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau01PassengerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private Upload $dau1Upload;

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

        // Parse authentic DAU-1.xls
        $parser = new DAU1Parser();
        $samplePath = base_path('resources/templates/dau/DAU-1.xls');
        $parsed = $parser->parse($samplePath);

        $this->dau1Upload = Upload::create([
            'original_filename' => 'DAU-1.xls',
            'stored_path'       => 'uploads/dau1.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU1',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $parsed,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);
    }

    /**
     * TEST 1: DAU-01 Dashboard renders PESAWAT and PENUMPANG metrics and Passenger Type filter.
     */
    public function test_dau01_dashboard_renders_pesawat_and_penumpang_metrics(): void
    {
        $response = $this->get(route('dau.dashboard', $this->dau1Upload->id));
        $response->assertOk();

        // Metric buttons
        $response->assertSee('PESAWAT');
        $response->assertSee('PENUMPANG');

        // Passenger Type filter options
        $response->assertSee('PASSENGER TYPE');
        $response->assertSee('Dewasa (Adult)');
        $response->assertSee('Anak (Child)');
        $response->assertSee('Bayi (Infant)');

        // Reactive bindings
        $response->assertSee("selectedMetric === 'passenger'", false);
        $response->assertSee("filterPassengerType", false);
    }

    /**
     * TEST 2: DAU-01 parser and summary strictly match authentic Adult, Child, Infant totals.
     */
    public function test_dau01_summary_matches_authentic_source_fields(): void
    {
        $summary = $this->dau1Upload->report_data['summary'] ?? [];

        // Exact numerical assertions from authentic DAU-1.xls
        $this->assertEquals(149298, $summary['passenger_total'] ?? 0);
        $this->assertEquals(138690, $summary['passenger_adult'] ?? 0);
        $this->assertEquals(3525, $summary['passenger_child'] ?? 0);
        $this->assertEquals(787, $summary['passenger_infant'] ?? 0);
        $this->assertEquals(73424, $summary['arr_adult'] ?? 0);
        $this->assertEquals(65266, $summary['dep_adult'] ?? 0);
        $this->assertEquals(1617, $summary['arr_child'] ?? 0);
        $this->assertEquals(1908, $summary['dep_child'] ?? 0);
        $this->assertEquals(380, $summary['arr_infant'] ?? 0);
        $this->assertEquals(407, $summary['dep_infant'] ?? 0);

        // Aircraft movements
        $this->assertEquals(1019, $summary['total_movements'] ?? 0);
        $this->assertEquals(510, $summary['aircraft_arrival'] ?? 0);
        $this->assertEquals(509, $summary['aircraft_departure'] ?? 0);
    }

    /**
     * TEST 3: Controller filterReportDataset with passenger_type = ADULT.
     */
    public function test_dau01_passenger_type_adult_filter(): void
    {
        $response = $this->get(route('dau.dashboard', [
            'upload' => $this->dau1Upload->id,
            'metric' => 'passenger',
            'passenger_type' => 'ADULT',
        ]));

        $response->assertOk();
        $analytics = $response->viewData('analytics');
        $summary = $analytics['summary'] ?? [];

        $this->assertGreaterThan(0, $summary['passenger_adult'] ?? 0);
        $this->assertEquals(138690, $summary['passenger_adult']);
    }

    /**
     * TEST 4: Controller filterReportDataset with passenger_type = CHILD.
     */
    public function test_dau01_passenger_type_child_filter(): void
    {
        $response = $this->get(route('dau.dashboard', [
            'upload' => $this->dau1Upload->id,
            'metric' => 'passenger',
            'passenger_type' => 'CHILD',
        ]));

        $response->assertOk();
        $analytics = $response->viewData('analytics');
        $summary = $analytics['summary'] ?? [];

        $this->assertEquals(3525, $summary['passenger_child'] ?? 0);
    }

    /**
     * TEST 5: Controller filterReportDataset with passenger_type = INFANT.
     */
    public function test_dau01_passenger_type_infant_filter(): void
    {
        $response = $this->get(route('dau.dashboard', [
            'upload' => $this->dau1Upload->id,
            'metric' => 'passenger',
            'passenger_type' => 'INFANT',
        ]));

        $response->assertOk();
        $analytics = $response->viewData('analytics');
        $summary = $analytics['summary'] ?? [];

        $this->assertEquals(787, $summary['passenger_infant'] ?? 0);
    }

    /**
     * TEST 6: Direction filter ARRIVAL vs DEPARTURE.
     */
    public function test_dau01_direction_filter(): void
    {
        $responseArr = $this->get(route('dau.dashboard', [
            'upload' => $this->dau1Upload->id,
            'direction' => 'ARRIVAL',
        ]));
        $responseArr->assertOk();
        $summaryArr = $responseArr->viewData('analytics')['summary'] ?? [];
        $this->assertEquals(510, $summaryArr['aircraft_arrival'] ?? 0);
        $this->assertEquals(0, $summaryArr['aircraft_departure'] ?? 0);

        $responseDep = $this->get(route('dau.dashboard', [
            'upload' => $this->dau1Upload->id,
            'direction' => 'DEPARTURE',
        ]));
        $responseDep->assertOk();
        $summaryDep = $responseDep->viewData('analytics')['summary'] ?? [];
        $this->assertEquals(0, $summaryDep['aircraft_arrival'] ?? 0);
        $this->assertEquals(509, $summaryDep['aircraft_departure'] ?? 0);
    }

    /**
     * TEST 7: PDF Export in PENUMPANG metric mode renders valid PDF with Passenger Breakdown.
     */
    public function test_dau01_pdf_export_in_passenger_mode(): void
    {
        $response = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau1Upload->id,
            'metric' => 'passenger',
            'passenger_type' => 'ALL',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
    }

    /**
     * TEST 8: PDF Export in PESAWAT metric mode renders valid PDF.
     */
    public function test_dau01_pdf_export_in_aircraft_mode(): void
    {
        $response = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau1Upload->id,
            'metric' => 'aircraft',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
    }
}
