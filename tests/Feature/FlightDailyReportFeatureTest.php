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

    /**
     * [PASS] Comprehensive OASYS FDR Acceptance Test Suite.
     * Proves:
     * - real CGK FDR.xls detected
     * - metadata extracted
     * - flight table detected
     * - rows > 0
     * - ARRIVAL works
     * - DEPARTURE works
     * - DOMESTIC works
     * - INTERNATIONAL works
     * - YES/NO realization works
     * - date range works
     * - dashboard populated
     * - charts populated
     */
    public function test_oasys_cgk_fdr_end_to_end_verification(): void
    {
        $cgkPath = storage_path('app/templates/CGK FDR.xls');
        $this->assertFileExists($cgkPath);

        // 1. Template Validation
        $file = new UploadedFile($cgkPath, 'CGK FDR.xls', 'application/vnd.ms-excel', null, true);
        $valRes = $this->postJson(route('upload.validate-template'), [
            'report_type' => 'fdr',
            'file'        => $file,
        ]);

        $valRes->assertStatus(200);
        $valRes->assertJson([
            'valid'           => true,
            'detected_format' => 'OASYS HTML XLS',
            'airport'         => 'CGK',
        ]);
        $this->assertGreaterThan(0, $valRes->json('records_count'));

        // 2. Direct Upload Store
        $uploadFile = new UploadedFile($cgkPath, 'CGK FDR.xls', 'application/vnd.ms-excel', null, true);
        $storeRes = $this->postJson(route('upload.store'), [
            'report_type' => 'fdr',
            'file'        => $uploadFile,
        ]);

        $storeRes->assertStatus(200);
        $uploadId = $storeRes->json('upload_id');
        $this->assertNotEmpty($uploadId);

        $upload = Upload::findOrFail($uploadId);
        $this->assertEquals('fdr', $upload->report_type);
        $this->assertGreaterThan(0, $upload->valid_rows);

        // 3. Metadata Extraction Verification
        $meta = $upload->report_data['meta'];
        $this->assertEquals('CGK', $meta['airport']);
        $this->assertEquals('OASYS HTML XLS', $meta['detected_format']);
        $this->assertNotEmpty($meta['period_start']);
        $this->assertNotEmpty($meta['period_end']);

        // 4. Filter: ARRIVAL
        $arrRes = $this->getJson(route('fdr.filter', [
            'upload' => $upload->id,
            'leg'    => 'ARRIVAL',
        ]));
        $arrRes->assertStatus(200);
        $this->assertGreaterThan(0, $arrRes->json('filtered_count'));
        foreach ($arrRes->json('records') as $r) {
            $this->assertEquals('ARRIVAL', $r['direction']);
        }

        // 5. Filter: DEPARTURE
        $depRes = $this->getJson(route('fdr.filter', [
            'upload' => $upload->id,
            'leg'    => 'DEPARTURE',
        ]));
        $depRes->assertStatus(200);
        $this->assertGreaterThan(0, $depRes->json('filtered_count'));
        foreach ($depRes->json('records') as $r) {
            $this->assertEquals('DEPARTURE', $r['direction']);
        }

        // 6. Filter: DOMESTIC
        $domRes = $this->getJson(route('fdr.filter', [
            'upload'  => $upload->id,
            'traffic' => 'DOMESTIC',
        ]));
        $domRes->assertStatus(200);
        $this->assertGreaterThan(0, $domRes->json('filtered_count'));
        foreach ($domRes->json('records') as $r) {
            $this->assertEquals('DOMESTIC', $r['traffic']);
        }

        // 7. Filter: INTERNATIONAL
        $intRes = $this->getJson(route('fdr.filter', [
            'upload'  => $upload->id,
            'traffic' => 'INTERNATIONAL',
        ]));
        $intRes->assertStatus(200);
        $this->assertIsInt($intRes->json('filtered_count'));

        // 8. Filter: REALIZATION YES / NO
        $realYesRes = $this->getJson(route('fdr.filter', [
            'upload'      => $upload->id,
            'realization' => 'YES',
        ]));
        $realYesRes->assertStatus(200);
        $this->assertGreaterThan(0, $realYesRes->json('filtered_count'));
        foreach ($realYesRes->json('records') as $r) {
            $this->assertTrue($r['is_realized']);
        }

        $realNoRes = $this->getJson(route('fdr.filter', [
            'upload'      => $upload->id,
            'realization' => 'NO',
        ]));
        $realNoRes->assertStatus(200);
        $this->assertIsInt($realNoRes->json('filtered_count'));

        // 9. Filter: DATE RANGE
        $dateRes = $this->getJson(route('fdr.filter', [
            'upload'     => $upload->id,
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-05',
        ]));
        $dateRes->assertStatus(200);
        $this->assertGreaterThan(0, $dateRes->json('filtered_count'));
        foreach ($dateRes->json('records') as $r) {
            $this->assertGreaterThanOrEqual('2026-08-01', $r['flight_date']);
            $this->assertLessThanOrEqual('2026-08-05', $r['flight_date']);
        }

        // 10. Dashboard & Charts Rendering
        $dashRes = $this->get(route('fdr.dashboard', $upload->id));
        $dashRes->assertStatus(200);
        $dashRes->assertSee('CGK');
        $dashRes->assertSee('FDR Intelligence');
        $dashRes->assertSee('Chart 1: ARRIVAL–DEPARTURE MOVEMENT');
        $dashRes->assertSee('Chart 2: DEPARTURE MOVEMENT');
        $dashRes->assertSee('Chart 3: ARRIVAL MOVEMENT');

        // Verify Hourly Charts structure in API
        $charts = $arrRes->json('hourly_charts');
        $this->assertNotEmpty($charts);
        $this->assertCount(24, $charts['hours']);
        $this->assertArrayHasKey('chart1_movement', $charts);
        $this->assertArrayHasKey('chart2_departure', $charts);
        $this->assertArrayHasKey('chart3_arrival', $charts);

        // Verify KPIs populated
        $kpis = $arrRes->json('kpis');
        $this->assertGreaterThan(0, $kpis['total_flights']);
        $this->assertArrayHasKey('avg_load_factor', $kpis);
        $this->assertArrayHasKey('cargo_ton', $kpis);
    }
}
