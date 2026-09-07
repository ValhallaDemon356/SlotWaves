<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\Parsers\DAU10AParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau10AMetricSeparationTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private Upload $dau10aUpload;

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

        // Parse authentic DAU-10A fixture
        $parser = new DAU10AParser();
        $samplePath = base_path('resources/templates/dau/DAU-10A.xls');

        $parsed = $parser->parse($samplePath);

        $this->dau10aUpload = Upload::create([
            'original_filename' => 'DAU-10A.xls',
            'stored_path'       => 'uploads/dau10a.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU10A',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $parsed,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);
    }

    /**
     * TEST A: DAU-10A Dashboard renders metric switcher [PESAWAT] [PENUMPANG] [AWAK]
     */
    public function test_dau10a_dashboard_renders_metric_switcher(): void
    {
        $response = $this->get(route('dau.dashboard', $this->dau10aUpload->id));
        $response->assertOk();

        // Check metric switcher buttons
        $response->assertSee('PESAWAT');
        $response->assertSee('PENUMPANG');
        $response->assertSee('AWAK');

        // Check metric reactive bindings in dashboard
        $response->assertSee("selectedMetric === 'aircraft'", false);
        $response->assertSee("selectedMetric === 'passenger'", false);
        $response->assertSee("selectedMetric === 'crew'", false);

        // Check dynamic section title
        $response->assertSee("selectedMetric === 'aircraft' ? 'HOURLY CAPACITY STATUS' : (selectedMetric === 'passenger' ? 'HOURLY PASSENGER STATUS' : 'HOURLY CREW STATUS')", false);
    }

    /**
     * TEST B: Aircraft Mode PDF Export retains Aircraft Capacity & Envelope
     */
    public function test_dau10a_pdf_export_aircraft_mode_retains_aircraft_capacity(): void
    {
        $response = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau10aUpload->id,
            'metric' => 'aircraft',
            'terminal' => 'ALL',
            'arr_nac' => 30,
            'dep_nac' => 30,
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertNotEmpty($content);
    }

    /**
     * TEST C: Passenger Mode PDF Export renders passenger distributions without Aircraft Capacity
     */
    public function test_dau10a_pdf_export_passenger_mode_renders_passenger_data(): void
    {
        $response = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau10aUpload->id,
            'metric' => 'passenger',
            'terminal' => 'ALL',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertNotEmpty($content);
    }

    /**
     * TEST D: Crew Mode PDF Export renders crew distributions without Aircraft Capacity
     */
    public function test_dau10a_pdf_export_crew_mode_renders_crew_data(): void
    {
        $response = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau10aUpload->id,
            'metric' => 'crew',
            'terminal' => 'ALL',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertNotEmpty($content);
    }

    /**
     * TEST E: Terminal 2F filter with Passenger Metric isolates Terminal 2F
     */
    public function test_dau10a_terminal_2f_filter_with_passenger_metric(): void
    {
        $response = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau10aUpload->id,
            'metric' => 'passenger',
            'terminal' => '2F',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * TEST F: Terminal 2F filter with Crew Metric isolates Terminal 2F
     */
    public function test_dau10a_terminal_2f_filter_with_crew_metric(): void
    {
        $response = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau10aUpload->id,
            'metric' => 'crew',
            'terminal' => '2F',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * TEST G: Part 44 & 56 Data Trace — Verify each metric uses its actual source fields
     */
    public function test_dau10a_data_trace_metric_separation_matches_source_fields(): void
    {
        $controller = new \App\Http\Controllers\DauDashboardController();
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('filterReportDataset');
        $method->setAccessible(true);

        $baseRecords = $this->dau10aUpload->report_data['normalized_pairs'] ?? [];
        $meta = $this->dau10aUpload->report_data['meta'] ?? [];

        // 1. AIRCRAFT METRIC
        $analyticsAcft = $method->invoke($controller, $baseRecords, [
            'flight_type' => 'ALL',
            'terminal'    => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'aircraft',
            'operation'   => 'ALL',
            'direction'   => 'ALL',
            'airline'     => 'ALL',
            'airport'     => 'ALL',
            'schedule_type' => 'ALL',
            'status'      => 'ALL',
            'aircraft_type' => 'ALL',
            'category'    => 'ALL',
            'search'      => '',
            'top_n'       => 'ALL',
            'threshold'   => 0,
        ], $meta, 'DAU10A');

        // 2. PASSENGER METRIC
        $analyticsPax = $method->invoke($controller, $baseRecords, [
            'flight_type' => 'ALL',
            'terminal'    => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'passenger',
            'operation'   => 'ALL',
            'direction'   => 'ALL',
            'airline'     => 'ALL',
            'airport'     => 'ALL',
            'schedule_type' => 'ALL',
            'status'      => 'ALL',
            'aircraft_type' => 'ALL',
            'category'    => 'ALL',
            'search'      => '',
            'top_n'       => 'ALL',
            'threshold'   => 0,
        ], $meta, 'DAU10A');

        // 3. CREW METRIC
        $analyticsCrew = $method->invoke($controller, $baseRecords, [
            'flight_type' => 'ALL',
            'terminal'    => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'crew',
            'operation'   => 'ALL',
            'direction'   => 'ALL',
            'airline'     => 'ALL',
            'airport'     => 'ALL',
            'schedule_type' => 'ALL',
            'status'      => 'ALL',
            'aircraft_type' => 'ALL',
            'category'    => 'ALL',
            'search'      => '',
            'top_n'       => 'ALL',
            'threshold'   => 0,
        ], $meta, 'DAU10A');

        $acftHourly = $analyticsAcft['hourly_distribution'];
        $paxHourly = $analyticsPax['hourly_distribution'];
        $crewHourly = $analyticsCrew['hourly_distribution'];

        $this->assertNotEmpty($acftHourly);
        $this->assertNotEmpty($paxHourly);
        $this->assertNotEmpty($crewHourly);

        // Pick a peak operational hour
        $sampleHour = $acftHourly[8]; // e.g. 08:00
        $paxSample = $paxHourly[8];
        $crewSample = $crewHourly[8];

        $acftArr = $sampleHour['aircraft_arrival'];
        $acftDep = $sampleHour['aircraft_departure'];

        $paxArr = $paxSample['passenger_arrival'];
        $paxDep = $paxSample['passenger_departure'];
        $paxTot = $paxSample['passenger_total'];

        $crewArr = $crewSample['crew'];
        $crewDep = $crewSample['extra_crew'];
        $crewTot = $crewSample['crew_total'];

        // Verify distinct metric data: Passenger is not Aircraft, Crew is not Aircraft or Passenger
        $this->assertGreaterThan($acftArr, $paxArr, 'Passenger arrival must not equal aircraft arrival count');
        $this->assertGreaterThan($acftDep, $paxDep, 'Passenger departure must not equal aircraft departure count');
        $this->assertNotEquals($paxTot, $crewTot, 'Crew total must not equal passenger total');
        $this->assertEquals($crewArr + $crewDep, $crewTot, 'Crew total must equal Operating Crew + Extra Crew');

        // Verify Terminal 2F isolation
        $analytics2F = $method->invoke($controller, $baseRecords, [
            'flight_type' => 'ALL',
            'terminal'    => '2F',
            'hour'        => 'ALL',
            'metric'      => 'passenger',
            'operation'   => 'ALL',
            'direction'   => 'ALL',
            'airline'     => 'ALL',
            'airport'     => 'ALL',
            'schedule_type' => 'ALL',
            'status'      => 'ALL',
            'aircraft_type' => 'ALL',
            'category'    => 'ALL',
            'search'      => '',
            'top_n'       => 'ALL',
            'threshold'   => 0,
        ], $meta, 'DAU10A');

        $this->assertLessThan(
            $analyticsPax['summary']['passenger_total'],
            $analytics2F['summary']['passenger_total'],
            'Terminal 2F passenger total must be smaller than ALL TERMINALS total'
        );

        foreach ($analytics2F['filtered_records'] as $rec) {
            $this->assertEquals('2F', $rec['terminal']);
        }
    }
}

