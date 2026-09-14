<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\TemplateValidator;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LargeDauUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    protected function getLargeDau01Path(): ?string
    {
        $path = 'C:/Users/Axioo Pongo/OneDrive/Documents/Data ATDP OASYS/DAU/(01-01-2025) - (31-07-2025)/DAU-01.xls';
        return file_exists($path) ? $path : null;
    }

    protected function getLargeDau04BPath(): ?string
    {
        $path = 'C:/Users/Axioo Pongo/OneDrive/Documents/Data ATDP OASYS/DAU/(01-01-2025) - (31-07-2025)/DAU-4B.xls';
        return file_exists($path) ? $path : null;
    }

    public function test_small_dau01_validates_and_uploads(): void
    {
        $samplePath = resource_path('templates/dau/DAU-1.xls');
        if (!file_exists($samplePath)) {
            $samplePath = storage_path('app/templates/DAU-1.xls');
        }
        $this->assertFileExists($samplePath);

        $file = new UploadedFile($samplePath, 'DAU-1.xls', 'application/vnd.ms-excel', null, true);

        // 1. Template validation
        $valRes = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU1',
            'file'        => $file,
        ]);

        $valRes->assertStatus(200);
        $valRes->assertJson([
            'valid'            => true,
            'detectedTemplate' => 'DAU1',
        ]);
        $this->assertGreaterThan(0, $valRes->json('records_count'));

        // 2. Direct upload
        $uploadRes = $this->postJson('/upload', [
            'report_type' => 'DAU1',
            'file'        => $file,
        ]);

        $uploadRes->assertStatus(200);
        $uploadId = $uploadRes->json('upload_id');
        $this->assertNotNull($uploadId);

        $upload = Upload::find($uploadId);
        $this->assertEquals('completed', $upload->status);
        $this->assertEquals('DAU1', $upload->report_type);
        $this->assertGreaterThan(0, $upload->valid_rows);

        // 3. Dashboard rendering
        $dashRes = $this->get("/dau/{$uploadId}/dashboard");
        $dashRes->assertStatus(200);
        $dashRes->assertSee('DAU-01');
    }

    public function test_small_dau04b_validates_and_uploads(): void
    {
        $samplePath = resource_path('templates/dau/DAU-4B.xls');
        if (!file_exists($samplePath)) {
            $samplePath = storage_path('app/templates/DAU-4B.xls');
        }
        $this->assertFileExists($samplePath);

        $file = new UploadedFile($samplePath, 'DAU-4B.xls', 'application/vnd.ms-excel', null, true);

        // 1. Template validation
        $valRes = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU4B',
            'file'        => $file,
        ]);

        $valRes->assertStatus(200);
        $valRes->assertJson([
            'valid'            => true,
            'detectedTemplate' => 'DAU4B',
        ]);
        $this->assertGreaterThan(0, $valRes->json('records_count'));

        // 2. Direct upload
        $uploadRes = $this->postJson('/upload', [
            'report_type' => 'DAU4B',
            'file'        => $file,
        ]);

        $uploadRes->assertStatus(200);
        $uploadId = $uploadRes->json('upload_id');
        $this->assertNotNull($uploadId);

        $upload = Upload::find($uploadId);
        $this->assertEquals('completed', $upload->status);
        $this->assertEquals('DAU4B', $upload->report_type);
        $this->assertGreaterThan(0, $upload->valid_rows);

        // 3. Dashboard rendering
        $dashRes = $this->get("/dau/{$uploadId}/dashboard");
        $dashRes->assertStatus(200);
        $dashRes->assertSee('DAU-04B');
    }

    public function test_large_dau01_probe_validation_and_chunked_processing(): void
    {
        $filePath = $this->getLargeDau01Path();
        if (!$filePath) {
            $this->markTestSkipped('Large DAU-01 test file not found on system.');
        }

        $fileSize = filesize($filePath);
        $this->assertGreaterThan(5 * 1024 * 1024, $fileSize, 'Large DAU-01 should be > 5 MB');

        // Step 1: Probe validation with first 256 KB slice
        $handle = fopen($filePath, 'rb');
        $probeBytes = fread($handle, 256 * 1024);
        fclose($handle);

        $tempProbe = tempnam(sys_get_temp_dir(), 'dau_probe_');
        file_put_contents($tempProbe, $probeBytes);
        $probeFile = new UploadedFile($tempProbe, 'probe_slice.xls', 'application/vnd.ms-excel', null, true);

        $probeRes = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU1',
            'is_probe'    => '1',
            'file'        => $probeFile,
        ]);

        @unlink($tempProbe);

        $probeRes->assertStatus(200);
        $probeRes->assertJson([
            'valid'            => true,
            'detectedTemplate' => 'DAU1',
            'is_probe'         => true,
        ]);

        // Step 2: Chunked Upload (simulate 2.5 MB chunks)
        $chunkSize = (int) (2.5 * 1024 * 1024);
        $totalChunks = (int) ceil($fileSize / $chunkSize);
        $uploadToken = 'test_token_' . Str::random(12);

        $srcHandle = fopen($filePath, 'rb');
        $finalResponse = null;

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkData = fread($srcHandle, $chunkSize);
            $tempChunk = tempnam(sys_get_temp_dir(), 'dau_chunk_');
            file_put_contents($tempChunk, $chunkData);

            $chunkFile = new UploadedFile($tempChunk, "chunk_{$i}.part", 'application/octet-stream', null, true);

            $res = $this->postJson('/upload/chunk', [
                'report_type'  => 'DAU1',
                'upload_token' => $uploadToken,
                'chunk_index'  => $i,
                'total_chunks' => $totalChunks,
                'filename'     => 'DAU-01_Jan_Jul_2025.xls',
                'chunk'        => $chunkFile,
            ]);

            @unlink($tempChunk);

            if ($i < $totalChunks - 1) {
                $res->assertStatus(200);
                $res->assertJson([
                    'success'   => true,
                    'completed' => false,
                ]);
            } else {
                $finalResponse = $res;
            }
        }
        fclose($srcHandle);

        $this->assertNotNull($finalResponse);
        $finalResponse->assertStatus(200);
        $finalResponse->assertJson([
            'success'   => true,
            'completed' => true,
            'status'    => 'completed',
        ]);

        $uploadId = $finalResponse->json('upload_id');
        $this->assertNotNull($uploadId);

        // Verify Upload DB record
        $upload = Upload::find($uploadId);
        $this->assertNotNull($upload);
        $this->assertEquals('completed', $upload->status);
        $this->assertEquals('DAU1', $upload->report_type);
        $this->assertGreaterThan(4000, $upload->valid_rows, 'Large DAU-01 should contain ~4,952 rows');

        // Check date range covers multiple months
        $reportData = $upload->report_data;
        $this->assertNotEmpty($reportData['meta']['date_range'] ?? null);
        $this->assertStringContainsString('2025', $reportData['meta']['date_range']);

        // Verify Dashboard access
        $dashRes = $this->get("/dau/{$uploadId}/dashboard");
        $dashRes->assertStatus(200);
        $dashRes->assertSee('DAU-01');
    }

    public function test_large_dau04b_probe_validation_and_chunked_processing(): void
    {
        $filePath = $this->getLargeDau04BPath();
        if (!$filePath) {
            $this->markTestSkipped('Large DAU-04B test file not found on system.');
        }

        $fileSize = filesize($filePath);
        $this->assertGreaterThan(4 * 1024 * 1024, $fileSize, 'Large DAU-04B should be > 4 MB');

        // Step 1: Probe validation with first 256 KB slice
        $handle = fopen($filePath, 'rb');
        $probeBytes = fread($handle, 256 * 1024);
        fclose($handle);

        $tempProbe = tempnam(sys_get_temp_dir(), 'dau4b_probe_');
        file_put_contents($tempProbe, $probeBytes);
        $probeFile = new UploadedFile($tempProbe, 'probe_slice.xls', 'application/vnd.ms-excel', null, true);

        $probeRes = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU4B',
            'is_probe'    => '1',
            'file'        => $probeFile,
        ]);

        @unlink($tempProbe);

        $probeRes->assertStatus(200);
        $probeRes->assertJson([
            'valid'            => true,
            'detectedTemplate' => 'DAU4B',
            'is_probe'         => true,
        ]);

        // Step 2: Chunked Upload
        $chunkSize = (int) (2.5 * 1024 * 1024);
        $totalChunks = (int) ceil($fileSize / $chunkSize);
        $uploadToken = 'test_token_4b_' . Str::random(12);

        $srcHandle = fopen($filePath, 'rb');
        $finalResponse = null;

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkData = fread($srcHandle, $chunkSize);
            $tempChunk = tempnam(sys_get_temp_dir(), 'dau4b_chunk_');
            file_put_contents($tempChunk, $chunkData);

            $chunkFile = new UploadedFile($tempChunk, "chunk_{$i}.part", 'application/octet-stream', null, true);

            $res = $this->postJson('/upload/chunk', [
                'report_type'  => 'DAU4B',
                'upload_token' => $uploadToken,
                'chunk_index'  => $i,
                'total_chunks' => $totalChunks,
                'filename'     => 'DAU-4B_Jan_Jul_2025.xls',
                'chunk'        => $chunkFile,
            ]);

            @unlink($tempChunk);

            if ($i < $totalChunks - 1) {
                $res->assertStatus(200);
                $res->assertJson([
                    'success'   => true,
                    'completed' => false,
                ]);
            } else {
                $finalResponse = $res;
            }
        }
        fclose($srcHandle);

        $this->assertNotNull($finalResponse);
        $finalResponse->assertStatus(200);
        $finalResponse->assertJson([
            'success'   => true,
            'completed' => true,
            'status'    => 'completed',
        ]);

        $uploadId = $finalResponse->json('upload_id');
        $this->assertNotNull($uploadId);

        // Verify Upload DB record
        $upload = Upload::find($uploadId);
        $this->assertNotNull($upload);
        $this->assertEquals('completed', $upload->status);
        $this->assertEquals('DAU4B', $upload->report_type);
        $this->assertGreaterThan(100, $upload->valid_rows);

        // Verify Dashboard access
        $dashRes = $this->get("/dau/{$uploadId}/dashboard");
        $dashRes->assertStatus(200);
        $dashRes->assertSee('DAU-04B');
    }

    public function test_structural_validation_flexible_row_counts(): void
    {
        // Generate valid DAU-01 HTML templates with 10, 100, 1,000, and 10,000 records.
        // All must be accepted. Template validity must NOT depend on exact row count.
        $rowCountsToTest = [10, 100, 1000, 10000];
        $validator = new TemplateValidator();

        foreach ($rowCountsToTest as $rowCount) {
            $html = '<title>OASYS Report-01</title>';
            $html .= '<CENTER><B>DATA LALU LINTAS ANGKUTAN UDARA (DAU-01)<BR>TANGERANG BANTEN - SOEKARNO HATTA  (CGK)<BR>TANGGAL 2025-01-01 s/d  2025-07-31<br>PENERBANGAN DOMESTIK & INTERNASIONAL<br>ALL TERMINAL</B></CENTER>';
            $html .= '<table class="table table-striped2">';
            $html .= '<tr>';
            $html .= '<td>NO</td><td>BANDARA ASAL/TUJUAN</td><td>FLIGHT NO</td><td>BERJADWAL / TIDAK BERJADWAL</td><td>TYPE PESAWAT</td><td>KAPASITAS KURSI</td>';
            $html .= '<td>PESAWAT DTG</td><td>PESAWAT BRK</td><td>PESAWAT TOT</td>';
            $html .= '<td>PENUMPANG DEWASA</td><td>PENUMPANG ANAK</td><td>PENUMPANG BAYI</td>';
            $html .= '<td>BAGASI</td><td>KARGO</td><td>POS</td>';
            $html .= '</tr>';

            for ($r = 1; $r <= $rowCount; $r++) {
                $html .= "<tr><td>{$r}</td><td>DPS</td><td>GA123</td><td>BERJADWAL</td><td>B738</td><td>160</td><td>1</td><td>0</td><td>1</td><td>150</td><td>10</td><td>0</td><td>500</td><td>200</td><td>50</td></tr>";
            }
            $html .= '</table>';

            $tempFile = tempnam(sys_get_temp_dir(), 'dau_rows_');
            file_put_contents($tempFile, $html);

            $result = $validator->validate('DAU1', $tempFile);
            @unlink($tempFile);

            $this->assertTrue(
                $result['valid'],
                "DAU-01 with {$rowCount} rows must be structurally valid. Got error: " . ($result['error'] ?? '')
            );
            $this->assertEquals('DAU1', $result['detectedTemplate']);
            $this->assertEquals($rowCount, $result['records_count']);
        }
    }

    public function test_invalid_template_returns_specific_category_and_no_object_object(): void
    {
        $html = '<html><body><table><tr><td>Random Document</td><td>Not DAU</td></tr></table></body></html>';
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_');
        file_put_contents($tempFile, $html);

        $file = new UploadedFile($tempFile, 'invalid_doc.xls', 'application/vnd.ms-excel', null, true);

        $response = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU1',
            'file'        => $file,
        ]);

        @unlink($tempFile);

        $response->assertStatus(422);
        $data = $response->json();

        $this->assertFalse($data['valid']);
        $this->assertEquals('INVALID_TEMPLATE', $data['category']);
        $this->assertEquals('INVALID DAU-01 TEMPLATE', $data['category_title']);
        $this->assertIsString($data['error']);
        $this->assertNotEmpty($data['error']);
        $this->assertStringNotContainsString('[object Object]', $data['error']);
        $this->assertIsArray($data['errors']);
        $this->assertNotEmpty($data['errors']);
    }

    public function test_wrong_dau_type_returns_invalid_template_category(): void
    {
        $samplePath = resource_path('templates/dau/DAU-5.xls');
        if (!file_exists($samplePath)) {
            $samplePath = storage_path('app/templates/DAU-5.xls');
        }

        $file = new UploadedFile($samplePath, 'DAU-5.xls', 'application/vnd.ms-excel', null, true);

        $response = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU1',
            'file'        => $file,
        ]);

        $response->assertStatus(422);
        $data = $response->json();

        $this->assertFalse($data['valid']);
        $this->assertEquals('INVALID_TEMPLATE', $data['category']);
        $this->assertEquals('DAU5', $data['detectedTemplate']);
        $this->assertStringNotContainsString('[object Object]', $data['error']);
    }

    public function test_corrupted_empty_file_returns_corrupted_category(): void
    {
        $response = $this->postJson('/upload/validate-template', [
            'report_type' => 'DAU1',
        ]);

        $response->assertStatus(422);
        $data = $response->json();

        $this->assertFalse($data['valid']);
        $this->assertEquals('CORRUPTED_FILE', $data['category']);
        $this->assertStringNotContainsString('[object Object]', $data['error']);
    }
}
