<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Airline;
use App\Models\Upload;
use App\Models\Flight;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VercelProductionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
    }

    /**
     * TEST 1: GET /master-data returns 200 OK and renders full UI with statistics.
     */
    public function test_1_master_data_returns_200_and_renders_ui(): void
    {
        $response = $this->get('/master-data');
        $response->assertStatus(200);
        $response->assertSee('Master Reference Database');
        $response->assertSee('PT. Angkasa Pura Indonesia');
        $response->assertSee('Total Airports');
        $response->assertSee('602');
        $response->assertSee('62');
    }

    /**
     * TEST 2: GET /master-data with filters returns 200 OK.
     */
    public function test_2_master_data_with_filters(): void
    {
        $response = $this->get('/master-data?management=angkasa_pura&region=2');
        $response->assertStatus(200);
        $response->assertSee('DPS');
        $response->assertSee('I Gusti Ngurah Rai');
    }

    /**
     * TEST 3: Trusted proxies and HTTPS protocol handling.
     */
    public function test_3_https_forwarded_headers_trusted(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host'  => 'slot-waves.vercel.app',
            'X-Forwarded-Port'  => '443',
        ])->get('/');

        $response->assertStatus(200);
        $response->assertSee('SlotWaves');
    }

    /**
     * TEST 4: CSRF token generation and session persistence for upload page.
     */
    public function test_4_upload_page_provides_csrf_token_and_session(): void
    {
        $response = $this->get('/upload');
        $response->assertStatus(200);
        $this->assertNotEmpty(session()->token());
    }

    /**
     * TEST 5: POST /upload with valid session token processes successfully without 419.
     */
    public function test_5_upload_pdf_processes_without_csrf_419(): void
    {
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n4 0 obj\n<< /Length 120 >>\nstream\nBT\n/F1 12 Tf\n100 700 Td\n(FLIGHT SCHEDULE BDO SUMMER 2026) Tj\n(GA123 0800 0900 CGK BDO 1234567 B738) Tj\nET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000214 00000 n \ntrailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n386\n%%EOF";
        $file = UploadedFile::fake()->createWithContent('FLIGHT_SCHEDULE_BDO_SUMMER_2026.pdf', $pdfContent);

        $response = $this->withSession(['_token' => 'test-csrf-token'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host'  => 'slot-waves.vercel.app',
            ])
            ->post('/upload', [
                '_token'       => 'test-csrf-token',
                'schedule_pdf' => $file,
            ]);

        // Should NOT be 419 Page Expired
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    /**
     * TEST 6: Static Vite manifest file is valid JSON and maps app.css and app.js.
     */
    public function test_6_vite_manifest_exists_and_maps_assets(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('resources/css/app.css', $manifest);
        $this->assertArrayHasKey('resources/js/app.js', $manifest);

        $cssFile = public_path('build/' . $manifest['resources/css/app.css']['file']);
        $jsFile  = public_path('build/' . $manifest['resources/js/app.js']['file']);

        $this->assertFileExists($cssFile);
        $this->assertFileExists($jsFile);
    }
}
