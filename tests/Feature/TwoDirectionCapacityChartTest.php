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
}
