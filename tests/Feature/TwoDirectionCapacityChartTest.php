<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Models\Flight;
use App\Services\Dau\Parsers\DAU10AParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoDirectionCapacityChartTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private Upload $scheduleUpload;

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

        $this->scheduleUpload = Upload::create([
            'original_filename' => 'SCHEDULE_CGK_TEST.pdf',
            'stored_path'       => 'uploads/test.pdf',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'season'            => 'summer',
            'total_rows'        => 4,
            'valid_rows'        => 4,
            'invalid_rows'      => 0,
            'status'            => 'completed',
        ]);

        Flight::create([
            'upload_id'         => $this->scheduleUpload->id,
            'airline_code'      => 'GA',
            'flight_number'     => 'GA100',
            'aircraft_type'     => 'B738',
            'origin'            => 'SUB',
            'destination'       => 'CGK',
            'scheduled_time'    => '11:15:00',
            'flight_type'       => 'arrival_domestic',
            'operating_days'    => '1234567',
            'validation_status' => 'valid',
            'parse_status'      => 'valid',
        ]);

        Flight::create([
            'upload_id'         => $this->scheduleUpload->id,
            'airline_code'      => 'GA',
            'flight_number'     => 'GA101',
            'aircraft_type'     => 'B738',
            'origin'            => 'CGK',
            'destination'       => 'DPS',
            'scheduled_time'    => '11:45:00',
            'flight_type'       => 'departure_domestic',
            'operating_days'    => '1234567',
            'validation_status' => 'valid',
            'parse_status'      => 'valid',
        ]);
    }

    /**
     * TEST 1: Schedule Dashboard renders Two-Direction Operational Capacity Envelope Chart
     */
    public function test_schedule_dashboard_renders_two_direction_capacity_chart(): void
    {
        $response = $this->get(route('schedule.dashboard', $this->scheduleUpload->id));
        $response->assertOk();

        // Check for two-direction chart container
        $response->assertSee('two-direction-capacity-chart-container');

        // Check for directional identifiers (Arrival up, Departure down)
        $response->assertSee('ARR &uarr;', false);
        $response->assertSee('DEP &darr;', false);
        $response->assertSee('OPC (RON)');

        // Check for envelope hover labels
        $response->assertSee('Batas Aircraft Capacity');
        $response->assertSee('Operating Hours Start');
        $response->assertSee('Operating Hours End');

        // Check for dynamic reactive bindings
        $response->assertSee('envelopeCoords.isVisible', false);
        $response->assertSee('gridNacOffsetPx', false);
        $response->assertSee('gridHalfNacOffsetPx', false);
    }

    /**
     * TEST 2: DAU-10A Dashboard renders Two-Direction Chart in Distribusi Per Jam mode
     * while preserving TIME × TERMINAL HEATMAP
     */
    public function test_dau10a_dashboard_renders_two_direction_chart_and_preserves_heatmap(): void
    {
        $templatePath = base_path('resources/templates/dau/DAU-10A.xls');
        $this->assertFileExists($templatePath);

        $parser = new DAU10AParser();
        $parsed = $parser->parse($templatePath);

        $upload = Upload::create([
            'original_filename' => 'DAU-10A.xls',
            'stored_path'       => 'uploads/dau10a.xls',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_type'       => 'DAU10A',
            'report_data'       => $parsed,
            'airport_id'        => $this->airport->id,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertOk();

        // 1. Heatmap Tab must remain intact
        $response->assertSee('TIME × TERMINAL HEATMAP');

        // 2. Distribusi Per Jam Tab must exist
        $response->assertSee('DISTRIBUSI PER JAM');

        // 3. Two-direction chart container & directional indicators
        $response->assertSee('two-direction-capacity-chart-container');
        $response->assertSee('ARR &uarr;', false);
        $response->assertSee('DEP &darr;', false);
        $response->assertSee('Aircraft Capacity');
    }

    /**
     * TEST 3: DAU-10A PDF Export renders Two-Direction Envelope Chart layout
     */
    public function test_dau10a_pdf_export_renders_two_direction_chart(): void
    {
        $templatePath = base_path('resources/templates/dau/DAU-10A.xls');
        $parser = new DAU10AParser();
        $parsed = $parser->parse($templatePath);

        $upload = Upload::create([
            'original_filename' => 'DAU-10A.xls',
            'stored_path'       => 'uploads/dau10a.xls',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_type'       => 'DAU10A',
            'report_data'       => $parsed,
            'airport_id'        => $this->airport->id,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);

        $response = $this->get(route('dau.export.pdf', ['upload' => $upload->id, 'custom_nac' => 8]));
        $response->assertOk();

        // Should return a streaming PDF with application/pdf header
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * TEST 4: Schedule Dashboard supports independent ARR and DEP capacities & unified modal
     */
    public function test_schedule_dashboard_supports_independent_arrival_and_departure_capacities(): void
    {
        $this->airport->update([
            'arrival_capacity'   => 7,
            'departure_capacity' => 9,
            'ops_start_time'     => '05:00',
            'ops_end_time'       => '23:00',
        ]);

        $response = $this->get(route('schedule.dashboard', $this->scheduleUpload->id));
        $response->assertOk();

        // Check unified modal presence
        $response->assertSee('EDIT AIRCRAFT CAPACITY &amp; OPERATING HOURS', false);
        $response->assertSee('ARRIVAL CAPACITY', false);
        $response->assertSee('DEPARTURE CAPACITY', false);
        $response->assertSee('modalArrCap', false);
        $response->assertSee('modalDepCap', false);

        // Check reference line hitzone tooltips
        $response->assertSee('Batas Aircraft Capacity - ARR:');
        $response->assertSee('DEP:');

        // Check that Schedule Dashboard DOES NOT have a terminal filter
        $response->assertDontSee('filterTerminal');
        $response->assertDontSee('ALL TERMINAL');
    }

    /**
     * TEST 5: Schedule operational settings persistence endpoint
     */
    public function test_schedule_save_operational_settings_endpoint_persists_capacities(): void
    {
        $response = $this->patchJson(route('schedule.operational-settings.save', $this->scheduleUpload->id), [
            'arrival_capacity'   => 12,
            'departure_capacity' => 15,
            'ops_start'          => '06:00',
            'ops_end'            => '22:00',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status'             => 'success',
            'arrival_capacity'   => 12,
            'departure_capacity' => 15,
            'ops_start'          => '06:00',
            'ops_end'            => '22:00',
        ]);

        $this->airport->refresh();
        $this->assertEquals(12, $this->airport->arrival_capacity);
        $this->assertEquals(15, $this->airport->departure_capacity);
        $this->assertEquals(15, $this->airport->aircraft_capacity);
        $this->assertEquals('06:00', $this->airport->ops_start_time);
        $this->assertEquals('22:00', $this->airport->ops_end_time);
    }

    /**
     * TEST 6: DAU Dashboard supports independent ARR/DEP capacities, unified modal, and Terminal filter
     */
    public function test_dau_dashboard_supports_independent_arrival_and_departure_capacities_and_terminal_filter(): void
    {
        $templatePath = base_path('resources/templates/dau/DAU-10A.xls');
        $parser = new DAU10AParser();
        $parsed = $parser->parse($templatePath);

        $upload = Upload::create([
            'original_filename' => 'DAU-10A.xls',
            'stored_path'       => 'uploads/dau10a.xls',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_type'       => 'DAU10A',
            'report_data'       => $parsed,
            'airport_id'        => $this->airport->id,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertOk();

        // 1. Independent capacities and unified modal
        $response->assertSee('ARR CAP:');
        $response->assertSee('DEP CAP:');
        $response->assertSee('EDIT AIRCRAFT CAPACITY &amp; OPERATING HOURS', false);
        $response->assertSee('arrivalCapacity', false);
        $response->assertSee('departureCapacity', false);
        $response->assertSee('modalArrCap', false);
        $response->assertSee('modalDepCap', false);

        // 2. Terminal filter MUST be present for DAU-10A
        $response->assertSee('ALL TERMINAL');
        $response->assertSee('filterTerminal', false);
        $response->assertSee('activeTerminalScope', false);
    }

    /**
     * TEST 7: DAU operational settings endpoint persists capacities
     */
    public function test_dau_save_operational_settings_endpoint_persists_capacities(): void
    {
        $templatePath = base_path('resources/templates/dau/DAU-10A.xls');
        $parser = new DAU10AParser();
        $parsed = $parser->parse($templatePath);

        $upload = Upload::create([
            'original_filename' => 'DAU-10A.xls',
            'stored_path'       => 'uploads/dau10a.xls',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_type'       => 'DAU10A',
            'report_data'       => $parsed,
            'airport_id'        => $this->airport->id,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);

        $response = $this->patchJson(route('dau.operational-settings.save', $upload->id), [
            'arrival_capacity'   => 8,
            'departure_capacity' => 11,
            'ops_start'          => '07:00',
            'ops_end'            => '21:00',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status'             => 'success',
            'arrival_capacity'   => 8,
            'departure_capacity' => 11,
            'ops_start'          => '07:00',
            'ops_end'            => '21:00',
        ]);

        $this->airport->refresh();
        $this->assertEquals(8, $this->airport->arrival_capacity);
        $this->assertEquals(11, $this->airport->departure_capacity);
        $this->assertEquals(11, $this->airport->aircraft_capacity);
        $this->assertEquals('07:00', $this->airport->ops_start_time);
        $this->assertEquals('21:00', $this->airport->ops_end_time);
    }

    /**
     * TEST 8: DAU-10A PDF Export with independent capacities and terminal filter
     */
    public function test_dau10a_pdf_export_with_independent_capacities_and_filtered_terminal(): void
    {
        $templatePath = base_path('resources/templates/dau/DAU-10A.xls');
        $parser = new DAU10AParser();
        $parsed = $parser->parse($templatePath);

        $upload = Upload::create([
            'original_filename' => 'DAU-10A.xls',
            'stored_path'       => 'uploads/dau10a.xls',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_type'       => 'DAU10A',
            'report_data'       => $parsed,
            'airport_id'        => $this->airport->id,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);

        $response = $this->get(route('dau.export.pdf', [
            'upload'   => $upload->id,
            'arr_nac'  => 9,
            'dep_nac'  => 12,
            'terminal' => '2F',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));

        // Check disposition header contains terminal 2F
        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('T2F', $disposition);
    }
}

