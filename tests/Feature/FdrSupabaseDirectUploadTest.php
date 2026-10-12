<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Services\Storage\SupabaseStorageService;
use App\Services\FlightDailyReport\FdrStreamingParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class FdrSupabaseDirectUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_uploaded_fdr_validates_required_fields(): void
    {
        $response = $this->postJson(route('fdr.handle-uploaded'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file_path', 'original_filename']);
    }

    public function test_handle_uploaded_fdr_processes_dataset_and_returns_redirect(): void
    {
        // Use standard OASYS FDR template from storage/app/templates/CGK FDR.xls
        $sourcePath = storage_path('app/templates/CGK FDR.xls');
        $tempFile = tempnam(sys_get_temp_dir(), 'fdr_test_') . '.xls';
        copy($sourcePath, $tempFile);

        // Mock SupabaseStorageService
        $storageMock = Mockery::mock(SupabaseStorageService::class);
        $storageMock->shouldReceive('downloadToTemp')
            ->once()
            ->with('raw_fdr/2026/08/CGK FDR.xls', 'fdr-datasets')
            ->andReturn($tempFile);

        $this->app->instance(SupabaseStorageService::class, $storageMock);

        $response = $this->postJson(route('fdr.handle-uploaded'), [
            'file_path'         => 'raw_fdr/2026/08/CGK FDR.xls',
            'bucket_name'       => 'fdr-datasets',
            'original_filename' => 'CGK FDR.xls',
            'airline_type'      => 'Garuda Indonesia',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'     => true,
            'completed'   => true,
            'report_type' => 'fdr',
            'status'      => 'completed',
        ]);

        $json = $response->json();
        $this->assertArrayHasKey('upload_id', $json);
        $this->assertArrayHasKey('redirect_url', $json);
        $this->assertStringContainsString('/fdr/', $json['redirect_url']);

        // Verify Upload model record in database
        $upload = Upload::find($json['upload_id']);
        $this->assertNotNull($upload);
        $this->assertEquals('fdr', $upload->report_type);
        $this->assertEquals('completed', $upload->status);
        $this->assertEquals('CGK FDR.xls', $upload->original_filename);
        $this->assertStringContainsString('supabase://fdr-datasets/', $upload->stored_path);

        // Verify /tmp file was cleaned up by finally block
        $this->assertFileDoesNotExist($tempFile);

        // Cleanup created upload
        $upload->delete();
    }

    public function test_handle_uploaded_fdr_uses_flight_reports_bucket_fallback_and_populates_fdr_flights_for_dashboard(): void
    {
        $sourcePath = storage_path('app/templates/CGK FDR.xls');
        $tempFile = tempnam(sys_get_temp_dir(), 'fdr_test_') . '.xls';
        copy($sourcePath, $tempFile);

        $storageMock = Mockery::mock(SupabaseStorageService::class);
        $storageMock->shouldReceive('downloadToTemp')
            ->once()
            ->with('raw_fdr/2026/08/CGK_FDR_direct.xls', 'flight-reports')
            ->andReturn($tempFile);

        $this->app->instance(SupabaseStorageService::class, $storageMock);

        // Omit bucket_name so it defaults to flight-reports
        $response = $this->postJson(route('fdr.handle-uploaded'), [
            'file_path'         => 'raw_fdr/2026/08/CGK_FDR_direct.xls',
            'original_filename' => 'CGK GA FDR .xls',
            'airline_type'      => 'Garuda Indonesia',
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);

        $upload = Upload::find($json['upload_id']);
        $this->assertNotNull($upload);
        $this->assertStringContainsString('supabase://flight-reports/', $upload->stored_path);

        // Verify fdr_flights database records were synced immediately
        $dbFlightCount = \App\Models\FdrFlight::where('upload_id', $upload->id)->count();
        $this->assertGreaterThan(0, $dbFlightCount);

        // Verify dashboard loads successfully with populated metrics
        $dashResponse = $this->get(route('fdr.dashboard', $upload->id));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('FLIGHT MOVEMENT — HOURLY DISTRIBUTION');
        $dashResponse->assertSee('Garuda Indonesia');

        // Cleanup
        \App\Models\FdrFlight::where('upload_id', $upload->id)->delete();
        $upload->delete();
    }

    public function test_dau_alias_endpoint_works(): void
    {
        $response = $this->postJson(route('dau.handle-uploaded-fdr'), []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file_path', 'original_filename']);
    }
}
