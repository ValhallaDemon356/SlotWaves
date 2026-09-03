<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Models\Airport;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReportTemplateValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
    }

    public function test_home_page_displays_select_type_data_to_generate(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Select Type Data to Generate');
        $response->assertSee('Airport Slot Schedule');
        $response->assertSee('DAU1');
        $response->assertSee('DAU4B');
        $response->assertSee('DAU10A');
        $response->assertSee('DAU12');
    }

    public function test_template_validation_endpoint_accepts_valid_dau1(): void
    {
        $samplePath = storage_path('app/templates/DAU-1.xls');
        if (!file_exists($samplePath)) {
            $samplePath = resource_path('templates/dau/DAU-1.xls');
        }

        $file = new UploadedFile($samplePath, 'DAU-1.xls', 'application/vnd.ms-excel', null, true);

        $response = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU1',
            'file'        => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'valid'            => true,
            'detectedTemplate' => 'DAU1',
            'expectedTemplate' => 'DAU-1.xls',
        ]);
        $this->assertGreaterThan(0, $response->json('records_count'));
    }

    public function test_template_validation_rejects_wrong_template_dau1_selected_with_dau5_file(): void
    {
        $samplePath = storage_path('app/templates/DAU-5.xls');
        if (!file_exists($samplePath)) {
            $samplePath = resource_path('templates/dau/DAU-5.xls');
        }

        $file = new UploadedFile($samplePath, 'DAU-5.xls', 'application/vnd.ms-excel', null, true);

        $response = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU1',
            'file'        => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid'            => false,
            'detectedTemplate' => 'DAU5',
            'expectedTemplate' => 'DAU1',
        ]);
    }

    public function test_template_validation_rejects_pdf_for_dau_report(): void
    {
        $samplePdf = storage_path('app/uploads/bdo_agustus_2026.pdf');
        $file = new UploadedFile($samplePdf, 'bdo_agustus_2026.pdf', 'application/pdf', null, true);

        $response = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU1',
            'file'        => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid'            => false,
            'detectedTemplate' => 'PDF Document',
        ]);
    }

    public function test_template_validation_rejects_excel_for_slot_schedule(): void
    {
        $samplePath = storage_path('app/templates/DAU-1.xls');
        $file = new UploadedFile($samplePath, 'DAU-1.xls', 'application/vnd.ms-excel', null, true);

        $response = $this->postJson('/upload/validate-template', [
            'report_type' => 'slot_schedule',
            'file'        => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid'            => false,
            'expectedTemplate' => 'Airport Slot Schedule PDF',
        ]);
    }

    public function test_template_download_endpoint_downloads_authentic_dau_template(): void
    {
        $response = $this->get('/templates/download/DAU1');
        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_dau_upload_process_and_dashboard_end_to_end(): void
    {
        $samplePath = storage_path('app/templates/DAU-2.xls');
        $file = new UploadedFile($samplePath, 'DAU-2.xls', 'application/vnd.ms-excel', null, true);

        // Upload and stage
        $uploadRes = $this->postJson('/upload', [
            'report_type' => 'DAU2',
            'file'        => $file,
        ]);

        $uploadRes->assertStatus(200);
        $uploadId = $uploadRes->json('upload_id');
        $this->assertNotNull($uploadId);

        // Process upload
        $procRes = $this->postJson("/upload/{$uploadId}/process");
        $procRes->assertStatus(200);
        $procRes->assertJson([
            'success' => true,
            'status'  => 'completed',
        ]);

        // Verify DB model
        $upload = Upload::find($uploadId);
        $this->assertEquals('completed', $upload->status);
        $this->assertEquals('DAU2', $upload->report_type);
        $this->assertNotEmpty($upload->report_data);

        // Verify Dashboard
        $dashRes = $this->get("/dau/{$uploadId}/dashboard");
        $dashRes->assertStatus(200);
        $dashRes->assertSee('DAU-02');
        $dashRes->assertSee('Secara Total');

        // Verify CSV export
        $csvRes = $this->get("/dau/{$uploadId}/export/excel");
        $csvRes->assertStatus(200);

        // Verify PDF export
        $pdfRes = $this->get("/dau/{$uploadId}/export/pdf");
        $pdfRes->assertStatus(200);
    }

    public function test_slot_schedule_pdf_upload_continues_to_work_unaffected(): void
    {
        $samplePdf = storage_path('app/uploads/bdo_agustus_2026.pdf');
        $file = new UploadedFile($samplePdf, 'bdo_agustus_2026.pdf', 'application/pdf', null, true);

        $uploadRes = $this->postJson('/upload', [
            'report_type'  => 'slot_schedule',
            'schedule_pdf' => $file,
        ]);

        $uploadRes->assertStatus(200);
        $uploadId = $uploadRes->json('upload_id');

        $procRes = $this->postJson("/upload/{$uploadId}/process");
        $procRes->assertStatus(200);

        $upload = Upload::find($uploadId);
        $this->assertEquals('completed', $upload->status);
        $this->assertEquals('slot_schedule', $upload->report_type);
        $this->assertGreaterThan(0, $upload->flights()->count());

        $dashRes = $this->get("/schedule/{$uploadId}/dashboard");
        $dashRes->assertStatus(200);
    }
}
