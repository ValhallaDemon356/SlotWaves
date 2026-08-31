<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Flight;
use App\Models\TimelinePosition;
use App\Models\Upload;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedesignedViewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
    }

    public function test_home_page_renders_redesigned_ui_when_no_uploads(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('SlotWaves');
        $response->assertSee('Flight Intelligence Pipeline');
        $response->assertSee('Import Flight Schedule');
    }

    public function test_import_page_renders_redesigned_ui(): void
    {
        $response = $this->get('/import');
        $response->assertOk();
        $response->assertSee('SlotWaves');
        $response->assertSee('Flight Intelligence Pipeline');
        $response->assertSee('Import Flight Schedule');
    }

    public function test_home_page_redirects_to_dashboard_when_upload_exists(): void
    {
        $airport = Airport::findByIata('BDO');
        $upload = Upload::create([
            'original_filename' => 'TEST_SCHEDULE.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'airport_id' => $airport ? $airport->id : null,
            'status' => 'completed',
            'season' => 'summer',
            'total_rows' => 1,
            'valid_rows' => 1,
        ]);

        Flight::create([
            'upload_id' => $upload->id,
            'flight_number' => 'GA101',
            'airline_code' => 'GA',
            'scheduled_time' => '10:00:00',
            'flight_type' => 'arrival_domestic',
            'operating_days' => '1234567',
        ]);

        $response = $this->get('/');
        $response->assertRedirect(route('schedule.dashboard', $upload->id));

        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertRedirect(route('schedule.dashboard', $upload->id));
    }

    public function test_master_data_renders_redesigned_ui(): void
    {
        $response = $this->get('/master-data');
        $response->assertOk();
        $response->assertSee('Master Reference Database');
        $response->assertSee('Airports Registry');
        $response->assertSee('Airlines & Operators');
        $response->assertSee('PT. Angkasa Pura Indonesia');
    }

    public function test_schedule_dashboard_renders_redesigned_ui(): void
    {
        $airport = Airport::findByIata('BDO');

        $upload = Upload::create([
            'original_filename' => 'SUMMER2026_BDO_SLOT.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'airport_id' => $airport ? $airport->id : null,
            'season' => 'summer',
            'total_rows' => 2,
            'valid_rows' => 2,
            'invalid_rows' => 0,
            'status' => 'completed',
        ]);

        Flight::create([
            'upload_id' => $upload->id,
            'airline_code' => 'JT',
            'flight_number' => 'JT755',
            'aircraft_type' => 'B738',
            'origin' => 'JOG',
            'destination' => 'BDO',
            'scheduled_time' => '19:00:00',
            'direction' => 'arrival',
            'traffic_type' => 'domestic',
            'flight_type' => 'arrival_domestic',
            'operating_days' => '1234567',
            'validation_status' => 'valid',
            'parse_status' => 'valid',
        ]);

        Flight::create([
            'upload_id' => $upload->id,
            'airline_code' => 'JT',
            'flight_number' => 'JT952',
            'aircraft_type' => 'B738',
            'origin' => 'BDO',
            'destination' => 'DPS',
            'scheduled_time' => '19:45:00',
            'direction' => 'departure',
            'traffic_type' => 'domestic',
            'flight_type' => 'departure_domestic',
            'operating_days' => '1234567',
            'validation_status' => 'valid',
            'parse_status' => 'valid',
        ]);

        $response = $this->get(route('schedule.dashboard', $upload->id));
        $response->assertOk();
        $response->assertSee('AIRPORT OPERATIONS CONTROL');
        $response->assertSee('BANDAR UDARA');
        $response->assertSee("Flight Activity");
        $response->assertSee('Operational Capacity');
        $response->assertSee('LIST PERGERAKAN HARI INI');
        $response->assertSee('JT755');
        $response->assertSee('JT952');
        $response->assertDontSee('Stand A0');
    }

    public function test_timeline_view_renders_redesigned_ui(): void
    {
        $airport = Airport::findByIata('BDO');

        $upload = Upload::create([
            'original_filename' => 'SUMMER2026_BDO_SLOT.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'airport_id' => $airport ? $airport->id : null,
            'season' => 'summer',
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
            'status' => 'completed',
        ]);

        $flight = Flight::create([
            'upload_id' => $upload->id,
            'airline_code' => 'JT',
            'flight_number' => 'JT755',
            'aircraft_type' => 'B738',
            'origin' => 'JOG',
            'destination' => 'BDO',
            'scheduled_time' => '19:00:00',
            'direction' => 'arrival',
            'traffic_type' => 'domestic',
            'flight_type' => 'arrival_domestic',
            'operating_days' => '1234567',
            'validation_status' => 'valid',
            'parse_status' => 'valid',
        ]);

        TimelinePosition::create([
            'upload_id' => $upload->id,
            'flight_id' => $flight->id,
            'section' => 'arrival',
            'hour' => 19,
            'offset_minutes' => 0,
            'row' => 0,
            'color_hex' => '#975432',
        ]);

        $response = $this->get(route('timeline.show', $upload->id));
        $response->assertOk();
        $response->assertSee('24-Hour Timeline');
        $response->assertSee('Airport Operational Slot Schedule');
        $response->assertSee('Departure');
        $response->assertSee('Arrival');
        $response->assertSee('TOT');
        $response->assertSee('JT755');
    }

    public function test_preview_reports_render_properly(): void
    {
        $upload = Upload::create([
            'original_filename' => 'SUMMER2026_BDO_SLOT.pdf',
            'stored_path' => 'uploads/test.pdf',
            'airport_iata' => 'BDO',
            'season' => 'summer',
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
            'status' => 'completed',
        ]);

        Flight::create([
            'upload_id' => $upload->id,
            'airline_code' => 'JT',
            'flight_number' => 'JT755',
            'aircraft_type' => 'B738',
            'origin' => 'JOG',
            'destination' => 'BDO',
            'scheduled_time' => '19:00:00',
            'direction' => 'arrival',
            'traffic_type' => 'domestic',
            'flight_type' => 'arrival_domestic',
            'operating_days' => '1234567',
            'validation_status' => 'valid',
            'parse_status' => 'valid',
        ]);

        $timeResponse = $this->get(route('schedule.preview.time', $upload->id));
        $timeResponse->assertOk();
        $timeResponse->assertSee('TIME FLIGHT SCHEDULE');

        $dosResponse = $this->get(route('schedule.preview.dos', $upload->id));
        $dosResponse->assertOk();
        $dosResponse->assertSee('DAILY OPERATING SERVICE');
    }
}
