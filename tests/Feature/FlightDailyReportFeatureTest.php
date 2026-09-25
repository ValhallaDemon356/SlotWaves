<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

class FlightDailyReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Upload $upload;

    protected function setUp(): void
    {
        parent::setUp();

        $parser = new FlightDailyReportParser();
        $templatePath = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        $parsed = $parser->parse($templatePath);

        $this->upload = Upload::create([
            'original_filename'  => 'OASYS-FDR-TEMPLATE.xls',
            'stored_path'        => 'templates/OASYS-FDR-TEMPLATE.xls',
            'status'             => 'completed',
            'report_type'        => 'fdr',
            'total_rows'         => count($parsed['records']),
            'valid_rows'         => count($parsed['records']),
            'invalid_rows'       => 0,
            'duplicate_rows'     => 0,
            'parsing_confidence' => 1.0,
            'validation_summary' => ['valid' => true],
            'report_data'        => $parsed,
        ]);
    }

    public function test_fdr_index_redirects_to_config(): void
    {
        $response = $this->get('/fdr');
        $response->assertRedirect(route('fdr.config'));
    }

    public function test_fdr_config_page_renders_successfully(): void
    {
        $response = $this->get(route('fdr.config', $this->upload->id));
        $response->assertStatus(200);
        $response->assertSee('Flight Daily Report (FDR) Configuration');
        $response->assertSee('Operational Scope &amp; Flight Filters', false);
        $response->assertSee('Select Report Mode (Modes 1 to 8)', false);
        $response->assertSee('1. NORMAL');
        $response->assertSee('2. LOAD FACTOR');
        $response->assertSee('7. OASYS VS APPS');
        $response->assertSee('8. OASYS VS EDIFLY');
    }

    public function test_fdr_dashboard_renders_successfully(): void
    {
        $response = $this->get(route('fdr.dashboard', $this->upload->id));
        $response->assertStatus(200);
        $response->assertSee('FDR Intelligence');
        $response->assertSee('3 Mentor Hourly Operational Charts');
        $response->assertSee('Chart 1: ARRIVAL–DEPARTURE MOVEMENT');
        $response->assertSee('Chart 2: DEPARTURE MOVEMENT');
        $response->assertSee('Chart 3: ARRIVAL MOVEMENT');
        $response->assertSee('Detailed Flight Movement Registry');
    }

    public function test_fdr_filter_api_returns_json_with_race_condition_token(): void
    {
        $response = $this->getJson(route('fdr.filter', [
            'upload'      => $this->upload->id,
            'airport'     => 'CGK',
            'leg'         => 'ARRIVAL',
            'report_mode' => 1,
            'v'           => 987654,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'version',
            'total_count',
            'filtered_count',
            'counter_text',
            'active_chips',
            'kpis',
            'hourly_charts',
            'sched_vs_real',
            'pax_analytics',
            'airline_route',
            'ground_ops',
            'records',
            'pagination',
        ]);
        $this->assertEquals(987654, $response->json('version'));
    }

    public function test_fdr_flight_details_api(): void
    {
        $response = $this->getJson(route('fdr.flight-details', [
            'upload'      => $this->upload->id,
            'flightIndex' => 1,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'flight' => [
                'flight_no', 'air_line', 'leg', 'direction', 'sibt', 'aibt', 'cap', 'load', 'load_factor'
            ]
        ]);
    }

    public function test_fdr_export_csv_streams_valid_content(): void
    {
        $response = $this->get(route('fdr.export.csv', $this->upload->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_landing_page_renders_fdr_card_below_dau_categories(): void
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('Flight Daily Report (FDR)');
        $response->assertSee('Operational flight daily movement, load factor, and traffic analytics.');
        $response->assertDontSee('Open FDR Config'); // Hero banner was removed
    }

    public function test_upload_validate_template_for_fdr(): void
    {
        $templatePath = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        $file = new UploadedFile($templatePath, 'OASYS-FDR-TEMPLATE.xls', 'application/vnd.ms-excel', null, true);

        $response = $this->postJson(route('upload.validate-template'), [
            'report_type' => 'fdr',
            'file'        => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'valid'            => true,
            'detectedTemplate' => 'fdr',
        ]);
    }

    public function test_upload_validate_template_for_btj_cgk_bdo_fdr(): void
    {
        $files = [
            'BTJ FDR.xls' => storage_path('app/templates/BTJ FDR.xls'),
            'CGK FDR.xls' => storage_path('app/templates/CGK FDR.xls'),
            'BDO FDR.xls' => storage_path('app/templates/BDO FDR.xls'),
        ];

        foreach ($files as $name => $path) {
            if (!file_exists($path)) continue;

            // Simulate real browser upload with temp file ending in .tmp
            $tempPath = tempnam(sys_get_temp_dir(), 'php_up_') . '.tmp';
            copy($path, $tempPath);
            $file = new UploadedFile($tempPath, $name, 'application/octet-stream', null, true);

            $response = $this->postJson(route('upload.validate-template'), [
                'report_type' => 'fdr',
                'file'        => $file,
            ]);

            @unlink($tempPath);

            $response->assertStatus(200);
            $response->assertJson([
                'valid'            => true,
                'detectedTemplate' => 'fdr',
            ]);
            $this->assertGreaterThan(0, $response->json('records_count'));
        }
    }

    public function test_upload_store_for_fdr_redirects_to_config(): void
    {
        $templatePath = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        $file = new UploadedFile($templatePath, 'OASYS-FDR-TEMPLATE.xls', 'application/vnd.ms-excel', null, true);

        $response = $this->postJson(route('upload.store'), [
            'report_type' => 'fdr',
            'file'        => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'upload_id',
            'report_type',
            'redirect_url',
        ]);
        $this->assertEquals('fdr', $response->json('report_type'));
        $this->assertStringContainsString('/fdr/config/', $response->json('redirect_url'));
    }

    public function test_upload_store_for_btj_fdr_workbooks(): void
    {
        $path = storage_path('app/templates/BTJ FDR.xls');
        if (file_exists($path)) {
            $tempPath = tempnam(sys_get_temp_dir(), 'php_up_') . '.tmp';
            copy($path, $tempPath);
            $file = new UploadedFile($tempPath, 'BTJ FDR.xls', 'application/octet-stream', null, true);

            $response = $this->postJson(route('upload.store'), [
                'report_type' => 'fdr',
                'file'        => $file,
            ]);

            @unlink($tempPath);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'success',
                'upload_id',
                'report_type',
                'redirect_url',
            ]);
            $this->assertEquals('fdr', $response->json('report_type'));
        }
    }

    public function test_download_template_for_fdr(): void
    {
        $response = $this->get(route('templates.download', 'fdr'));
        $response->assertStatus(200);
        $this->assertStringContainsString('OASYS-FDR-TEMPLATE.xls', $response->headers->get('content-disposition'));
    }

    public function test_fdr_export_pdf_downloads_valid_pdf(): void
    {
        $response = $this->get(route('fdr.export.pdf', $this->upload->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_upload_chunk_for_fdr_reassembles_and_redirects_to_config(): void
    {
        $templatePath = storage_path('app/templates/BTJ FDR.xls');
        if (!file_exists($templatePath)) {
            $templatePath = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        }

        $content = file_get_contents($templatePath);
        $totalBytes = strlen($content);
        $half = (int) floor($totalBytes / 2);

        $part1 = substr($content, 0, $half);
        $part2 = substr($content, $half);

        $token = 'test_token_' . uniqid();
        $filename = 'BTJ FDR.xls';

        // Chunk 0
        $tmp1 = tempnam(sys_get_temp_dir(), 'chk0_') . '.tmp';
        file_put_contents($tmp1, $part1);
        $chunkFile0 = new UploadedFile($tmp1, 'chunk_0.tmp', 'application/octet-stream', null, true);

        $res0 = $this->post(route('upload.chunk'), [
            'report_type'  => 'fdr',
            'upload_token' => $token,
            'chunk_index'  => 0,
            'total_chunks' => 2,
            'filename'     => $filename,
            'chunk'        => $chunkFile0,
        ]);
        @unlink($tmp1);

        $res0->assertStatus(200);
        $res0->assertJson([
            'success'      => true,
            'completed'    => false,
            'chunk_index'  => 0,
            'total_chunks' => 2,
        ]);

        // Chunk 1 (final chunk triggering reassembly)
        $tmp2 = tempnam(sys_get_temp_dir(), 'chk1_') . '.tmp';
        file_put_contents($tmp2, $part2);
        $chunkFile1 = new UploadedFile($tmp2, 'chunk_1.tmp', 'application/octet-stream', null, true);

        $res1 = $this->post(route('upload.chunk'), [
            'report_type'  => 'fdr',
            'upload_token' => $token,
            'chunk_index'  => 1,
            'total_chunks' => 2,
            'filename'     => $filename,
            'chunk'        => $chunkFile1,
        ]);
        @unlink($tmp2);

        $res1->assertStatus(200);
        $res1->assertJson([
            'success'     => true,
            'completed'   => true,
            'report_type' => 'fdr',
            'status'      => 'completed',
        ]);
        $this->assertNotEmpty($res1->json('upload_id'));
        $this->assertStringContainsString('/fdr/config/', $res1->json('redirect_url'));
        $this->assertGreaterThan(0, $res1->json('valid_rows'));
    }
}
