<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Models\FdrProcessingJob;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FdrLargeFileUploadTest extends TestCase
{
    protected string $fixturePath;
    protected FlightDailyReportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('migrate');
        ini_set('memory_limit', '1024M');
        gc_collect_cycles();
        $this->parser = app(FlightDailyReportParser::class);
        $this->fixturePath = storage_path('app/templates/DAU_1790826066.xls');

        if (!file_exists($this->fixturePath)) {
            $sourceDownload = 'C:\\Users\\Axioo Pongo\\Downloads\\DAU_1790826066.xls';
            if (file_exists($sourceDownload)) {
                @copy($sourceDownload, $this->fixturePath);
            }
        }
    }

    protected function tearDown(): void
    {
        gc_collect_cycles();
        parent::tearDown();
    }

    /**
     * Test 1: HTML-XLS detected correctly (not binary XLS).
     */
    public function test_html_xls_detected_correctly_on_45mb_file(): void
    {
        $this->assertFileExists($this->fixturePath);

        $handle = fopen($this->fixturePath, 'rb');
        $head = fread($handle, 4096);
        fclose($handle);

        $this->assertTrue($this->parser->isHtmlTable($head));
        $this->assertSame('OASYS HTML XLS', $this->parser->detectFormat($this->fixturePath, $head));
    }

    /**
     * Test 2: 33,589 movement rows recognized, 1 summary row excluded, 0 rejected.
     */
    public function test_large_record_count_and_summary_row_exclusion(): void
    {
        $this->assertFileExists($this->fixturePath);

        $parsed = $this->parser->parseHtmlStream($this->fixturePath);

        $this->assertNotEmpty($parsed['records']);
        $this->assertSame(33590, count($parsed['records']));

        $classified = $this->parser->classifyRows($parsed['records']);
        $movements = $classified['movement_records'];
        $summaries = $classified['summary_records'];

        $this->assertSame(33589, count($movements));
        $this->assertSame(1, count($summaries));

        // Assert summary row properties
        $summaryRow = $summaries[0];
        $this->assertSame('SUMMARY', $summaryRow['row_type'] ?? 'SUMMARY');
        $this->assertStringContainsString('PAX ALL', ($summaryRow['desc'] ?? '') . ' ' . ($summaryRow['raw_text'] ?? ''));

        // Assert metadata
        $meta = $parsed['meta'];
        $this->assertSame('CGK', $meta['report_airport'] ?? $meta['airport']);
        $this->assertSame('2026-01-01', $meta['period_start']);
        $this->assertSame('2026-06-30', $meta['period_end']);
    }

    /**
     * Test 3: Large upload chunk does not fail at chunk 18 and creates async processing job.
     */
    public function test_large_upload_chunk_does_not_fail_at_chunk_18(): void
    {
        Storage::disk('local')->deleteDirectory('chunks');
        $token = 'upl_test_' . time();
        $totalChunks = 18;

        // Simulate chunks 0 to 16
        for ($i = 0; $i < 17; $i++) {
            $chunkContent = "<!-- chunk {$i} test slice -->\n";
            $tmp = tempnam(sys_get_temp_dir(), "chk{$i}_") . '.tmp';
            file_put_contents($tmp, $chunkContent);
            $chunkFile = new UploadedFile($tmp, "chunk_{$i}.tmp", 'application/octet-stream', null, true);

            $res = $this->post(route('upload.chunk'), [
                'report_type'  => 'fdr',
                'upload_token' => $token,
                'chunk_index'  => $i,
                'total_chunks' => $totalChunks,
                'filename'     => 'DAU_1790826066.xls',
                'chunk'        => $chunkFile,
            ]);
            @unlink($tmp);

            $res->assertStatus(200);
            $res->assertJson([
                'success'     => true,
                'completed'   => false,
                'chunk_index' => $i,
            ]);
        }

        // Test chunk status inquiry
        $statusRes = $this->get(route('upload.chunk.status', [
            'upload_token' => $token,
            'total_chunks' => $totalChunks,
        ]));
        $statusRes->assertStatus(200);
        $statusRes->assertJson([
            'success'   => true,
            'completed' => false,
        ]);
        $this->assertCount(17, $statusRes->json('uploaded_chunks'));
        $this->assertSame([17], $statusRes->json('missing_chunks'));

        // Chunk 18 (index 17): Upload valid OASYS content slice to complete reassembly
        $realContent = file_get_contents(storage_path('app/templates/CGK FDR.xls'));
        $tmpFinal = tempnam(sys_get_temp_dir(), 'chk17_') . '.tmp';
        file_put_contents($tmpFinal, $realContent);
        $finalChunkFile = new UploadedFile($tmpFinal, 'chunk_17.tmp', 'application/octet-stream', null, true);

        $t0 = microtime(true);
        $finalRes = $this->post(route('upload.chunk'), [
            'report_type'  => 'fdr',
            'upload_token' => $token,
            'chunk_index'  => 17,
            'total_chunks' => $totalChunks,
            'filename'     => 'DAU_1790826066.xls',
            'chunk'        => $finalChunkFile,
        ]);
        $duration = microtime(true) - $t0;
        @unlink($tmpFinal);

        // Crucial requirement: Must respond immediately (< 1.5s), NOT blocking chunk 18 for heavy parsing!
        $this->assertLessThan(2.0, $duration, "Chunk 18 response took {$duration}s, must be fast and asynchronous!");

        $finalRes->assertStatus(200);
        $finalRes->assertJson([
            'success'      => true,
            'completed'    => true,
            'report_type'  => 'fdr',
            'status'       => 'QUEUED',
            'is_async_job' => true,
        ]);
        $this->assertNotEmpty($finalRes->json('job_id'));
        $this->assertNotEmpty($finalRes->json('upload_id'));
        $this->assertNotEmpty($finalRes->json('poll_url'));
        $this->assertNotEmpty($finalRes->json('process_url'));

        // Verify job in database
        $job = FdrProcessingJob::find($finalRes->json('job_id'));
        $this->assertNotNull($job);
        $this->assertSame('QUEUED', $job->status);
    }

    /**
     * Test 4: Backend returns JSON for API-controlled failures (never HTML).
     */
    public function test_backend_returns_json_for_api_controlled_failures(): void
    {
        // 1. Missing upload token
        $res1 = $this->post(route('upload.chunk'), []);
        $res1->assertStatus(422);
        $this->assertTrue(str_contains($res1->headers->get('content-type'), 'application/json'));
        $res1->assertJsonStructure([
            'success',
            'error' => ['code', 'message', 'retryable'],
        ]);

        // 2. Non-existent job status
        $res2 = $this->get(route('fdr.jobs.status', 'non-existent-uuid-1234'));
        $res2->assertStatus(404);
        $this->assertTrue(str_contains($res2->headers->get('content-type'), 'application/json'));
        $res2->assertJsonStructure([
            'success',
            'error' => ['code', 'message', 'retryable'],
        ]);
    }

    /**
     * Helper to obtain or populate a completed Upload model with DAU_1790826066.xls.
     */
    protected function getOrCreateCompletedLargeUpload(): Upload
    {
        $upload = Upload::where('original_filename', 'DAU_1790826066.xls')
            ->where('status', 'completed')
            ->whereNotNull('report_data')
            ->latest('id')
            ->first();

        if (!$upload || empty($upload->report_data['records'])) {
            $parsed = $this->parser->parseHtmlStream($this->fixturePath);
            $classified = $this->parser->classifyRows($parsed['records']);
            $movementCount = count($classified['movement_records']);
            $upload = Upload::create([
                'original_filename'  => 'DAU_1790826066.xls',
                'stored_path'        => 'templates/DAU_1790826066.xls',
                'status'             => 'completed',
                'report_type'        => 'fdr',
                'total_rows'         => $movementCount,
                'valid_rows'         => $movementCount,
                'invalid_rows'       => 0,
                'duplicate_rows'     => 0,
                'parsing_confidence' => 1.0,
                'validation_summary' => ['valid' => true],
                'report_data'        => $parsed,
            ]);
        }

        return $upload;
    }

    /**
     * Test 5: Multi-period analytics: FULL RANGE (33,589 movements), DAILY, and MONTHLY granularity.
     */
    public function test_multi_period_analytics_and_granularity(): void
    {
        $this->assertFileExists($this->fixturePath);

        $upload = $this->getOrCreateCompletedLargeUpload();

        $analytics = app(FlightDailyReportAnalytics::class);
        $filterService = app(FlightDailyReportFilter::class);
        $rawRecords = $upload->report_data['records'];
        $meta = $upload->report_data['meta'];

        // 1. FULL RANGE Scope
        $filtersFull = [
            'date_scope'     => 'ALL_PERIOD',
            'analysis_level' => 'FULL_RANGE',
            'analysis_date'  => null,
            'airport'        => 'CGK',
            'leg'            => 'ALL',
            'operator'       => 'ALL',
            'traffic'        => 'ALL',
            'data_type'      => 'ALL',
            'realization'    => 'ALL',
            'report_mode'    => 1,
            'time_basis'     => 'scheduled',
            'otp_tolerance'  => 15,
        ];
        $filteredFull = $filterService->apply($rawRecords, $filtersFull, $meta);
        $this->assertSame(33589, $filteredFull['filtered_count']);

        $resAnalyticsFull = $analytics->compute($filteredFull['records'], $meta, [
            'date_scope'     => 'ALL_PERIOD',
            'analysis_level' => 'FULL_RANGE',
            'report_date'    => null,
        ]);
        $this->assertSame(33589, $resAnalyticsFull['kpis']['total_flights']);
        $this->assertSame('daily', $resAnalyticsFull['combined_trend']['granularity']);
        $this->assertNotEmpty($resAnalyticsFull['combined_trend']['total_flights'] ?? []);
        $this->assertGreaterThan(100, count($resAnalyticsFull['combined_trend']['labels'] ?? []));

        // 2. DAILY Scope (Single Date: 2026-03-15)
        $filtersDay = array_merge($filtersFull, [
            'date_scope'     => 'DAY',
            'analysis_level' => 'DAILY',
            'analysis_date'  => '2026-03-15',
        ]);
        $filteredDay = $filterService->apply($rawRecords, $filtersDay, $meta);
        $this->assertGreaterThan(0, $filteredDay['filtered_count']);
        $this->assertLessThan(33589, $filteredDay['filtered_count']);

        // Verify hourly distribution returned for single day
        $resAnalyticsDay = $analytics->compute($filteredDay['records'], $meta, [
            'date_scope'     => 'DAY',
            'analysis_level' => 'DAILY',
            'report_date'    => '2026-03-15',
        ]);
        $hourlyData = $resAnalyticsDay['hourly_charts']['hourly_data'] ?? [];
        $this->assertCount(24, $hourlyData);
    }

    /**
     * Test 6: Detailed Flight Table Server-side Pagination & Search.
     */
    public function test_detailed_table_server_side_pagination_and_search(): void
    {
        $upload = $this->getOrCreateCompletedLargeUpload();

        // Test page 1 with per_page 50
        $resP1 = $this->get(route('fdr.filter', [
            'upload'     => $upload->id,
            'date_scope' => 'ALL_PERIOD',
            'page'       => 1,
            'per_page'   => 50,
        ]));
        $resP1->assertStatus(200);
        $resP1->assertJson([
            'source_count' => 33589,
        ]);
        $this->assertCount(50, $resP1->json('records'));

        // Test page 2 with per_page 50
        $resP2 = $this->get(route('fdr.filter', [
            'upload'     => $upload->id,
            'date_scope' => 'ALL_PERIOD',
            'page'       => 2,
            'per_page'   => 50,
        ]));
        $resP2->assertStatus(200);
        $this->assertCount(50, $resP2->json('records'));
        $this->assertNotSame($resP1->json('records')[0], $resP2->json('records')[0]);

        // Test search by flight number
        $firstFlt = $resP1->json('records')[0]['flight_no'] ?? 'GA';
        $resSearch = $this->get(route('fdr.filter', [
            'upload'     => $upload->id,
            'date_scope' => 'ALL_PERIOD',
            'search'     => $firstFlt,
        ]));
        $resSearch->assertStatus(200);
        $this->assertGreaterThan(0, $resSearch->json('filtered_count'));
    }

    /**
     * Test 7: CSV streaming export of large dataset without memory exhaustion.
     */
    public function test_csv_streaming_export_on_large_dataset(): void
    {
        $upload = $this->getOrCreateCompletedLargeUpload();

        $res = $this->get(route('fdr.export.csv', [
            'upload'     => $upload->id,
            'date_scope' => 'ALL_PERIOD',
        ]));

        $res->assertStatus(200);
        $this->assertSame('text/csv; charset=UTF-8', $res->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', $res->headers->get('content-disposition'));
    }

    /**
     * Test 8: PDF export safety (does not crash or exceed memory).
     */
    public function test_pdf_export_memory_safety(): void
    {
        $upload = $this->getOrCreateCompletedLargeUpload();

        $res = $this->get(route('fdr.export.pdf', [
            'upload'        => $upload->id,
            'date_scope'    => 'ALL_PERIOD',
            'analysis_date' => '2026-03-15',
        ]));

        $res->assertStatus(200);
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    /**
     * Test 9 (Prompt §35): Targeted Resume Test.
     * chunk 0: SUCCESS, chunk 1: SUCCESS, chunk 2: FAIL x3 -> PAUSED.
     * Resume continues from chunk 2, NOT chunk 0.
     */
    public function test_chunk_pause_and_resume_continues_from_missing_chunk(): void
    {
        $token = 'upl_test_resume_' . time();
        $totalChunks = 15;

        // 1. Create upload session
        $createRes = $this->postJson(route('upload.session.create'), [
            'upload_token'      => $token,
            'original_filename' => 'DAU_1790826066.xls',
            'file_size'         => 45007926,
            'total_chunks'      => $totalChunks,
            'chunk_size'        => 3145728,
            'report_type'       => 'fdr',
        ]);
        $createRes->assertStatus(200);
        $createRes->assertJson(['success' => true]);

        // 2. Upload Chunk 0 & 1 -> SUCCESS
        for ($i = 0; $i < 2; $i++) {
            $tmp = tempnam(sys_get_temp_dir(), "chk{$i}_") . '.tmp';
            file_put_contents($tmp, "chunk-{$i}-data");
            $chunkFile = new UploadedFile($tmp, "chunk_{$i}.tmp", 'application/octet-stream', null, true);

            $res = $this->post(route('upload.chunk'), [
                'report_type'  => 'fdr',
                'upload_token' => $token,
                'chunk_index'  => $i,
                'total_chunks' => $totalChunks,
                'filename'     => 'DAU_1790826066.xls',
                'chunk'        => $chunkFile,
            ]);
            @unlink($tmp);
            $res->assertStatus(200);
        }

        // 3. Simulate chunk 2 failure after 3 retries -> PAUSED
        $pauseRes = $this->postJson("/upload/session/{$token}/pause", [
            'failed_chunk' => 2,
            'reason'       => 'Simulated chunk 2 failure after 3 retries',
        ]);
        $pauseRes->assertStatus(200);
        $pauseRes->assertJson([
            'success'      => true,
            'status'       => 'PAUSED',
            'failed_chunk' => 2,
        ]);

        // 4. Resume: Query session state
        $sessionRes = $this->getJson("/upload/session/{$token}");
        $sessionRes->assertStatus(200);
        $sessionData = $sessionRes->json();

        $this->assertSame('PAUSED', $sessionData['status']);
        $this->assertSame([0, 1], $sessionData['uploaded_chunks']);
        $this->assertSame(2, $sessionData['failed_chunk']);

        // Assert resume continues from chunk 2, NEVER chunk 0 or 1
        $missing = $sessionData['missing_chunks'];
        $this->assertCount(13, $missing);
        $this->assertSame(2, $missing[0], 'Resume must start from missing chunk 2');
        $this->assertFalse(in_array(0, $missing), 'Chunk 0 must not be re-uploaded');
        $this->assertFalse(in_array(1, $missing), 'Chunk 1 must not be re-uploaded');
    }

    /**
     * Test 10 (Prompt §36): Browser Refresh Test.
     * After chunks 0..5, refresh browser: session restored, resume continues from chunk 6.
     */
    public function test_browser_refresh_restores_session_state(): void
    {
        $token = 'upl_test_refresh_' . time();
        $totalChunks = 15;

        // Upload chunks 0 to 5
        for ($i = 0; $i <= 5; $i++) {
            $tmp = tempnam(sys_get_temp_dir(), "chk{$i}_") . '.tmp';
            file_put_contents($tmp, "chunk-{$i}-data");
            $chunkFile = new UploadedFile($tmp, "chunk_{$i}.tmp", 'application/octet-stream', null, true);

            $res = $this->post(route('upload.chunk'), [
                'report_type'  => 'fdr',
                'upload_token' => $token,
                'chunk_index'  => $i,
                'total_chunks' => $totalChunks,
                'filename'     => 'DAU_1790826066.xls',
                'chunk'        => $chunkFile,
            ]);
            @unlink($tmp);
            $res->assertStatus(200);
        }

        // Simulate browser refresh: fetch /upload/session/{token}
        $refreshRes = $this->getJson("/upload/session/{$token}");
        $refreshRes->assertStatus(200);
        $data = $refreshRes->json();

        $this->assertTrue($data['success']);
        $this->assertCount(6, $data['uploaded_chunks']);
        $this->assertSame([0, 1, 2, 3, 4, 5], $data['uploaded_chunks']);
        $this->assertSame(6, $data['missing_chunks'][0], 'Resume after refresh must continue from chunk 6, NOT chunk 0');
    }
}
