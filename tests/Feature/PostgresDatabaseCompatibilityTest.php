<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use App\Models\Flight;
use App\Models\TimelineSetting;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostgresDatabaseCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
    }

    public function test_pgsql_connection_configuration_is_correctly_defined(): void
    {
        $pgsqlConfig = config('database.connections.pgsql');

        $this->assertIsArray($pgsqlConfig);
        $this->assertEquals('pgsql', $pgsqlConfig['driver']);
        $this->assertArrayHasKey('url', $pgsqlConfig);
        $this->assertArrayHasKey('host', $pgsqlConfig);
        $this->assertArrayHasKey('port', $pgsqlConfig);
        $this->assertArrayHasKey('database', $pgsqlConfig);
        $this->assertArrayHasKey('username', $pgsqlConfig);
        $this->assertArrayHasKey('password', $pgsqlConfig);
        $this->assertArrayHasKey('sslmode', $pgsqlConfig);
        $this->assertArrayHasKey('search_path', $pgsqlConfig);
    }

    public function test_check_database_artisan_command_executes_successfully(): void
    {
        $this->artisan('slotwaves:check-db')
            ->expectsOutputToContain('SLOTWAVES DATABASE INTEGRITY CHECK')
            ->expectsOutputToContain('Connection Status: [SUCCESS]')
            ->assertExitCode(0);
    }

    public function test_models_json_and_boolean_casts_are_cross_database_compatible(): void
    {
        $airport = Airport::findByIata('BDO');
        $this->assertNotNull($airport);
        $this->assertIsBool($airport->is_international);
        $this->assertIsBool($airport->is_active);

        $upload = Upload::create([
            'original_filename'  => 'TEST_SCHEDULE.pdf',
            'stored_path'        => 'uploads/test.pdf',
            'airport_iata'       => 'BDO',
            'airport_id'         => $airport->id,
            'status'             => 'completed',
            'season'             => 'summer',
            'validation_summary' => ['total' => 10, 'valid' => 10, 'invalid' => 0],
        ]);

        $this->assertIsArray($upload->validation_summary);
        $this->assertEquals(10, $upload->validation_summary['total']);

        $flight = Flight::create([
            'upload_id'         => $upload->id,
            'flight_number'     => 'QG820',
            'airline_code'      => 'QG',
            'aircraft_type'     => 'A320',
            'origin'            => 'SUB',
            'scheduled_time'    => '06:55:00',
            'flight_type'       => 'arrival_domestic',
            'direction'         => 'arrival',
            'traffic_type'      => 'domestic',
            'validation_status' => 'valid',
            'validation_errors' => [],
        ]);

        $this->assertIsArray($flight->validation_errors);
        $this->assertEquals('QG820', $flight->flight_number);

        $setting = TimelineSetting::create([
            'upload_id' => $upload->id,
            'ops_start' => 6,
            'ops_end'   => 20,
        ]);

        $this->assertEquals(6, $setting->ops_start);
        $this->assertEquals(20, $setting->ops_end);
    }

    public function test_manual_nac_and_operational_settings_save_and_persist(): void
    {
        $airport = Airport::findByIata('BDO');
        $this->assertEquals(6, $airport->getEffectiveCapacity());

        $upload = Upload::create([
            'original_filename' => 'TEST_SCHEDULE.pdf',
            'stored_path'       => 'uploads/test.pdf',
            'airport_iata'      => 'BDO',
            'airport_id'        => $airport->id,
            'status'            => 'completed',
            'season'            => 'summer',
        ]);

        $response = $this->patchJson(route('schedule.operational-settings.save', $upload->id), [
            'aircraft_capacity' => 10,
            'ops_start'         => '05:00',
            'ops_end'           => '21:00',
            'timezone'          => 'Asia/Jakarta',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success'           => true,
            'aircraft_capacity' => 10,
        ]);

        $airport->refresh();
        $this->assertEquals(10, $airport->getEffectiveCapacity());
    }
}
