<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\DauComparisonService;
use App\Services\Dau\Parsers\DAU2Parser;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DauComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    /**
     * Helper to create a fake DAU-02 file content with custom airport, dates, and metric multipliers.
     */
    protected function createDau02Content(
        string $airportCode = 'CGK',
        string $airportName = 'TANGERANG BANTEN - SOEKARNO HATTA',
        string $startDate = '2025-01-01',
        string $endDate = '2025-06-30',
        float $multiplier = 1.0
    ): string {
        $acDomArr = (int)(371 * $multiplier);
        $acDomDep = (int)(364 * $multiplier);
        $acDomTot = $acDomArr + $acDomDep;

        $pxDomArr = (int)(49031 * $multiplier);
        $pxDomDep = (int)(41105 * $multiplier);
        $pxDomTra = (int)(6245 * $multiplier);
        $pxDomTrf = 0;
        $pxDomTot = $pxDomArr + $pxDomDep + $pxDomTra + $pxDomTrf;

        $cwDom = (int)(729 * $multiplier);
        $bgDomArr = (int)(434248 * $multiplier);
        $bgDomDep = (int)(457504 * $multiplier);
        $bgDomTot = $bgDomArr + $bgDomDep;

        $cgDomArr = (int)(366278 * $multiplier);
        $cgDomDep = (int)(535899 * $multiplier);
        $cgDomTot = $cgDomArr + $cgDomDep;

        $posDomArr = (int)(100 * $multiplier);
        $posDomDep = (int)(200 * $multiplier);
        $posDomTot = $posDomArr + $posDomDep;

        // International
        $acIntArr = (int)(139 * $multiplier);
        $acIntDep = (int)(145 * $multiplier);
        $acIntTot = $acIntArr + $acIntDep;

        $pxIntArr = (int)(26390 * $multiplier);
        $pxIntDep = (int)(26476 * $multiplier);
        $pxIntTra = (int)(51 * $multiplier);
        $pxIntTrf = 0;
        $pxIntTot = $pxIntArr + $pxIntDep + $pxIntTra + $pxIntTrf;

        $cwInt = (int)(301 * $multiplier);
        $bgIntArr = (int)(440750 * $multiplier);
        $bgIntDep = (int)(387419 * $multiplier);
        $bgIntTot = $bgIntArr + $bgIntDep;

        $cgIntArr = (int)(426663 * $multiplier);
        $cgIntDep = (int)(444162 * $multiplier);
        $cgIntTot = $cgIntArr + $cgIntDep;

        $posIntArr = (int)(50 * $multiplier);
        $posIntDep = (int)(60 * $multiplier);
        $posIntTot = $posIntArr + $posIntDep;

        $totAc = $acDomTot + $acIntTot;
        $totPx = $pxDomTot + $pxIntTot;
        $totCg = $cgDomTot + $cgIntTot;

        return <<<HTML
<title>&nbsp;OASYS Report-02</title>
<CENTER><B>DATA ANGKUTAN UDARA SECARA TOTAL (DAU-02)
<BR>{$airportName} ({$airportCode})<BR>TANGGAL {$startDate} s/d {$endDate}<br>PENERBANGAN DOMESTIK & INTERNASIONAL<br>ALL TERMINAL</B></CENTER>
<BR>
<table width="100%" border="1" cellspacing="1" cellpadding="3">
  <tr> 
    <td rowspan="2">NO</td><td rowspan="2">JENIS PENERBANGAN</td>
    <td colspan="3">PESAWAT</td><td colspan="5">PENUMPANG</td><td colspan="3">AWAK</td>
    <td colspan="3">BAGASI</td><td colspan="3">KARGO (Kg)</td><td colspan="3">POS (Kg)</td>
  </tr>
  <tr> 
    <td>DTG</td><td>BRK</td><td>JML (3+4)</td>
    <td>DTG</td><td>BRK</td><td>TRANSIT</td><td>TRANSFER</td><td>JML (6+7+8+9)</td>
    <td>CREW</td><td>EXTRA CREW</td><td>JML (11+12)</td>
    <td>DTG</td><td>BRK</td><td>JML (14+15)</td>
    <td>DTG</td><td>BRK</td><td>JML (17+18)</td>
    <td>DTG</td><td>BRK</td><td>JML (20+21)</td>
  </tr>
  <tr>
    <td>1</td><td>DOMESTIK</td>
    <td>{$acDomArr}</td><td>{$acDomDep}</td><td>{$acDomTot}</td>
    <td>{$pxDomArr}</td><td>{$pxDomDep}</td><td>{$pxDomTra}</td><td>{$pxDomTrf}</td><td>{$pxDomTot}</td>
    <td>{$cwDom}</td><td>0</td><td>{$cwDom}</td>
    <td>{$bgDomArr}</td><td>{$bgDomDep}</td><td>{$bgDomTot}</td>
    <td>{$cgDomArr}</td><td>{$cgDomDep}</td><td>{$cgDomTot}</td>
    <td>{$posDomArr}</td><td>{$posDomDep}</td><td>{$posDomTot}</td>
  </tr>
  <tr>
    <td>2</td><td>INTERNASIONAL</td>
    <td>{$acIntArr}</td><td>{$acIntDep}</td><td>{$acIntTot}</td>
    <td>{$pxIntArr}</td><td>{$pxIntDep}</td><td>{$pxIntTra}</td><td>{$pxIntTrf}</td><td>{$pxIntTot}</td>
    <td>{$cwInt}</td><td>0</td><td>{$cwInt}</td>
    <td>{$bgIntArr}</td><td>{$bgIntDep}</td><td>{$bgIntTot}</td>
    <td>{$cgIntArr}</td><td>{$cgIntDep}</td><td>{$cgIntTot}</td>
    <td>{$posIntArr}</td><td>{$posIntDep}</td><td>{$posIntTot}</td>
  </tr>
  <tr>
    <td></td><td>TOTAL</td>
    <td>{$totAc}</td><td>0</td><td>{$totAc}</td>
    <td>{$totPx}</td><td>0</td><td>0</td><td>0</td><td>{$totPx}</td>
    <td>0</td><td>0</td><td>0</td>
    <td>0</td><td>0</td><td>0</td>
    <td>{$totCg}</td><td>0</td><td>{$totCg}</td>
    <td>0</td><td>0</td><td>0</td>
  </tr>
</table>
HTML;
    }

    /**
     * Create an UploadedFile instance from string content.
     */
    protected function createFakeUploadedFile(string $content, string $filename = 'DAU-02.xls'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'dau_test_');
        file_put_contents($tempPath, $content);
        return new UploadedFile($tempPath, $filename, 'application/vnd.ms-excel', null, true);
    }

    /**
     * Test 1: Mode 1 - Single DAU-02 report continues to work normally.
     */
    public function test_single_dau02_mode_is_preserved_and_works_normally(): void
    {
        $content = $this->createDau02Content('CGK', 'TANGERANG BANTEN - SOEKARNO HATTA', '2025-01-01', '2025-06-30', 1.0);
        $file = $this->createFakeUploadedFile($content, 'DAU-2-Single.xls');

        // Direct standard upload via /upload
        $res = $this->postJson('/upload', [
            'report_type' => 'DAU2',
            'file'        => $file,
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true, 'report_type' => 'DAU2', 'status' => 'completed']);
        $uploadId = $res->json('upload_id');
        $this->assertNotNull($uploadId);

        // Dashboard rendering
        $dashRes = $this->get("/dau/{$uploadId}/dashboard");
        $dashRes->assertStatus(200);
        $dashRes->assertSee('Data Angkutan Udara Secara Total (DAU-02)');
        $dashRes->assertSee('COMPARATIVE BREAKDOWN');
    }

    /**
     * Test 2: Validation accepts same airport, same period length, different dates.
     */
    public function test_validation_accepts_same_airport_same_duration_different_dates(): void
    {
        $rep1 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'airport_name' => 'Soekarno Hatta',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30',
        ];

        $rep2 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'airport_name' => 'Soekarno Hatta',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-06-30',
        ];

        $res = DauComparisonService::validateComparisonReports([$rep1, $rep2]);
        $this->assertTrue($res['valid']);
        $this->assertTrue($res['checks']['same_dau_type']);
        $this->assertTrue($res['checks']['same_airport']);
        $this->assertTrue($res['checks']['different_period']);
        $this->assertTrue($res['checks']['same_period_length']);
        $this->assertEmpty($res['errors']);
    }

    /**
     * Test 3: Validation rejects different airports.
     */
    public function test_validation_rejects_different_airports(): void
    {
        $rep1 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'airport_name' => 'Soekarno Hatta',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30',
        ];

        $rep2 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'DPS',
            'airport_name' => 'Ngurah Rai',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-06-30',
        ];

        $res = DauComparisonService::validateComparisonReports([$rep1, $rep2]);
        $this->assertFalse($res['valid']);
        $this->assertFalse($res['checks']['same_airport']);
        $this->assertStringContainsString('same airport', $res['errors'][0]);
    }

    /**
     * Test 4: Validation rejects different DAU types.
     */
    public function test_validation_rejects_different_dau_types(): void
    {
        $rep1 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30',
        ];

        $rep2 = [
            'report_type'  => 'DAU1',
            'airport_code' => 'CGK',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-06-30',
        ];

        $res = DauComparisonService::validateComparisonReports([$rep1, $rep2]);
        $this->assertFalse($res['valid']);
        $this->assertFalse($res['checks']['same_dau_type']);
    }

    /**
     * Test 5: Validation rejects identical date ranges.
     */
    public function test_validation_rejects_identical_date_ranges(): void
    {
        $rep1 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30',
        ];

        $rep2 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30',
        ];

        $res = DauComparisonService::validateComparisonReports([$rep1, $rep2]);
        $this->assertFalse($res['valid']);
        $this->assertFalse($res['checks']['different_period']);
        $this->assertStringContainsString('different reporting periods', $res['errors'][0]);
    }

    /**
     * Test 6: Validation rejects different period lengths (e.g. 6 months vs 9 months).
     */
    public function test_validation_rejects_different_durations(): void
    {
        $rep1 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30', // 181 days
        ];

        $rep2 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-09-30', // 273 days
        ];

        $res = DauComparisonService::validateComparisonReports([$rep1, $rep2]);
        $this->assertFalse($res['valid']);
        $this->assertFalse($res['checks']['same_period_length']);
        $this->assertStringContainsString('same duration', $res['errors'][0]);
    }

    /**
     * Test 7: Overlapping periods generate a non-blocking warning.
     */
    public function test_validation_warns_on_overlapping_periods(): void
    {
        $rep1 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30',
        ];

        $rep2 = [
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2025-04-01',
            'end_date'     => '2025-09-30',
        ];

        $res = DauComparisonService::validateComparisonReports([$rep1, $rep2]);
        $this->assertTrue($res['valid']);
        $this->assertNotEmpty($res['warnings']);
        $this->assertStringContainsString('Warning: comparison periods overlap', $res['warnings'][0]);
    }

    /**
     * Test 8: Growth, difference, and zero-baseline calculation rules.
     */
    public function test_growth_and_difference_calculations(): void
    {
        // 2-period test: A = 1,000, B = 1,200 -> diff = +200, growth = +20%
        $repA = [
            'id'           => 1,
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30',
            'report_data'  => [
                'records' => [
                    ['category' => 'DOMESTIK', 'passenger_total' => 1000, 'aircraft_total' => 100, 'cargo' => 500]
                ]
            ]
        ];

        $repB = [
            'id'           => 2,
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-06-30',
            'report_data'  => [
                'records' => [
                    ['category' => 'DOMESTIK', 'passenger_total' => 1200, 'aircraft_total' => 110, 'cargo' => 550]
                ]
            ]
        ];

        $model = DauComparisonService::buildComparisonModel([$repA, $repB]);
        $this->assertTrue($model['valid']);

        $pSeries = array_values($model['analysis']['passenger']['series']);
        $this->assertEquals(0, $pSeries[0]['difference']);
        $this->assertEquals(200, $pSeries[1]['difference']);
        $this->assertEquals(20.0, $pSeries[1]['growth_pct']);
        $this->assertEquals('+20.00%', $pSeries[1]['growth_fmt']);

        // Zero-baseline test: A = 0, B = 100 -> growth formatted as "N/A" (no NaN or Infinity)
        $repZeroA = [
            'id'           => 1,
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30',
            'report_data'  => [
                'records' => [
                    ['category' => 'DOMESTIK', 'passenger_total' => 0, 'aircraft_total' => 0, 'cargo' => 0]
                ]
            ]
        ];

        $repZeroB = [
            'id'           => 2,
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-06-30',
            'report_data'  => [
                'records' => [
                    ['category' => 'DOMESTIK', 'passenger_total' => 100, 'aircraft_total' => 10, 'cargo' => 50]
                ]
            ]
        ];

        $modelZero = DauComparisonService::buildComparisonModel([$repZeroA, $repZeroB]);
        $zeroSeries = array_values($modelZero['analysis']['passenger']['series']);
        $this->assertNull($zeroSeries[1]['growth_pct']);
        $this->assertEquals('N/A', $zeroSeries[1]['growth_fmt']);
    }

    /**
     * Test 9: 3-period test with sequential growth A -> B -> C.
     */
    public function test_three_period_growth(): void
    {
        $repA = [
            'id'           => 1,
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2024-01-01',
            'end_date'     => '2024-06-30',
            'report_data'  => [
                'records' => [['category' => 'DOMESTIK', 'passenger_total' => 100, 'aircraft_total' => 10, 'cargo' => 50]]
            ]
        ];

        $repB = [
            'id'           => 2,
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-06-30',
            'report_data'  => [
                'records' => [['category' => 'DOMESTIK', 'passenger_total' => 120, 'aircraft_total' => 12, 'cargo' => 60]]
            ]
        ];

        $repC = [
            'id'           => 3,
            'report_type'  => 'DAU2',
            'airport_code' => 'CGK',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-06-30',
            'report_data'  => [
                'records' => [['category' => 'DOMESTIK', 'passenger_total' => 150, 'aircraft_total' => 15, 'cargo' => 75]]
            ]
        ];

        $model = DauComparisonService::buildComparisonModel([$repA, $repB, $repC]);
        $this->assertTrue($model['valid']);
        $this->assertCount(3, $model['periods']);

        $pSeries = array_values($model['analysis']['passenger']['series']);
        // A -> B: +20%
        $this->assertEquals(20.0, $pSeries[1]['growth_pct']);
        $this->assertEquals('+20.00%', $pSeries[1]['growth_fmt']);

        // B -> C: +25%
        $this->assertEquals(25.0, $pSeries[2]['growth_pct']);
        $this->assertEquals('+25.00%', $pSeries[2]['growth_fmt']);
    }

    /**
     * Test 10: Comparison dashboard route with full data and filters.
     */
    public function test_comparison_upload_and_dashboard_flow(): void
    {
        $contentA = $this->createDau02Content('CGK', 'SOEKARNO HATTA', '2025-01-01', '2025-06-30', 1.0);
        $fileA = $this->createFakeUploadedFile($contentA, 'DAU-2025.xls');

        $contentB = $this->createDau02Content('CGK', 'SOEKARNO HATTA', '2026-01-01', '2026-06-30', 1.1);
        $fileB = $this->createFakeUploadedFile($contentB, 'DAU-2026.xls');

        // Upload via /upload/compare-file
        $resA = $this->postJson('/upload/compare-file', ['file' => $fileA]);
        $resA->assertStatus(200);
        $resA->assertJson(['success' => true, 'airport_code' => 'CGK', 'dau_type' => 'DAU-02']);
        $idA = $resA->json('upload_id');

        $resB = $this->postJson('/upload/compare-file', ['file' => $fileB]);
        $resB->assertStatus(200);
        $idB = $resB->json('upload_id');

        // Validate comparison endpoint
        $valRes = $this->postJson('/dau/compare/validate', [
            'report_ids' => [$idA, $idB],
        ]);
        $valRes->assertStatus(200);
        $valRes->assertJson(['valid' => true]);

        // Load dashboard
        $dashRes = $this->get("/dau/compare?reports={$idA},{$idB}");
        $dashRes->assertStatus(200);
        $dashRes->assertSee('KINERJA OPERASIONAL BANDARA');
        $dashRes->assertSee('DATA PERGERAKAN HISTORIS');
        $dashRes->assertSee('CGK');

        // Test with DOM filter
        $domRes = $this->get("/dau/compare?reports={$idA},{$idB}&flight_type=DOM");
        $domRes->assertStatus(200);

        // Test PDF export
        $pdfRes = $this->get("/dau/compare/export/pdf?reports={$idA},{$idB}");
        $pdfRes->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $pdfRes->headers->get('content-type'));

        // Test CSV export
        $csvRes = $this->get("/dau/compare/export/csv?reports={$idA},{$idB}");
        $csvRes->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvRes->headers->get('content-type'));
    }
}
