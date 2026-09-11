<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\Parsers\DAU10BParser;
use App\Http\Controllers\DauDashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau10BHourlyComparisonTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private Upload $dau10bUpload;
    private array $parsedData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->airport = Airport::firstOrCreate(
            ['iata_code' => 'CGK'],
            [
                'name'    => 'Soekarno Hatta',
                'city'    => 'Tangerang',
                'country' => 'Indonesia',
            ]
        );

        $parser = new DAU10BParser();
        $samplePath = base_path('resources/templates/dau/DAU-10B.xls');
        $this->parsedData = $parser->parse($samplePath);

        $this->dau10bUpload = Upload::create([
            'original_filename' => 'DAU-10B.xls',
            'stored_path'       => 'uploads/dau10b.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU10B',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $this->parsedData,
            'source_type'       => 'excel',
            'parser_metadata'   => $this->parsedData,
        ]);
    }

    /**
     * TEST 1: Sum of hourly distribution reconciles with summary totals for both metrics
     */
    public function test_hourly_distribution_sums_reconcile_with_summary_totals(): void
    {
        $controller = new DauDashboardController();
        $records = $this->parsedData['records'];
        $meta = $this->parsedData['meta'];

        // 1. Aircraft mode reconciliation
        $resAc = $controller->filterReportDataset($records, [
            'metric'    => 'aircraft',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'operation' => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $sumHourlyAcOn = array_sum(array_column($resAc['hourly_distribution'], 'aircraft_arrival'));
        $sumHourlyAcOff = array_sum(array_column($resAc['hourly_distribution'], 'aircraft_departure'));
        $sumHourlyAcTot = array_sum(array_column($resAc['hourly_distribution'], 'aircraft_total'));

        $this->assertEquals($resAc['summary']['aircraft_arrival'], $sumHourlyAcOn, "Hourly Block On sum must match summary");
        $this->assertEquals($resAc['summary']['aircraft_departure'], $sumHourlyAcOff, "Hourly Block Off sum must match summary");
        $this->assertEquals($resAc['summary']['aircraft_total'], $sumHourlyAcTot, "Hourly Total Acft sum must match summary");

        // 2. Passenger mode reconciliation
        $resPax = $controller->filterReportDataset($records, [
            'metric'    => 'passenger',
            'terminal'  => 'ALL',
            'hour'      => 'ALL',
            'operation' => 'ALL',
            'direction' => 'ALL',
        ], $meta, 'DAU10B');

        $sumHourlyPaxOn = array_sum(array_column($resPax['hourly_distribution'], 'passenger_arrival'));
        $sumHourlyPaxOff = array_sum(array_column($resPax['hourly_distribution'], 'passenger_departure'));

        $this->assertEquals($resPax['summary']['passenger_arrival'], $sumHourlyPaxOn, "Hourly Passenger Block On sum must match summary");
        $this->assertEquals($resPax['summary']['passenger_departure'], $sumHourlyPaxOff, "Hourly Passenger Block Off sum must match summary");
    }

    /**
     * TEST 2: CSV/Excel export includes dedicated DAU-10B Block On and Block Off columns
     */
    public function test_dau10b_csv_export_has_block_on_off_headers(): void
    {
        $response = $this->get(route('dau.export.excel', [
            'upload' => $this->dau10bUpload->id,
            'metric' => 'aircraft',
        ]));

        $response->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        $streamedContent = $response->streamedContent();
        $this->assertStringContainsString('Block On Acft (DTG)', $streamedContent);
        $this->assertStringContainsString('Block Off Acft (BRK)', $streamedContent);
        $this->assertStringContainsString('Block On Pax (DTG)', $streamedContent);
        $this->assertStringContainsString('Block Off Pax (BRK)', $streamedContent);
    }

    /**
     * TEST 3: PDF export functions in both Aircraft and Passenger modes
     */
    public function test_dau10b_pdf_export_functions_in_both_modes(): void
    {
        // Aircraft Mode PDF
        $responseAc = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau10bUpload->id,
            'metric' => 'aircraft',
        ]));
        $responseAc->assertOk();
        $this->assertEquals('application/pdf', $responseAc->headers->get('Content-Type'));
        $this->assertNotEmpty($responseAc->getContent());

        // Passenger Mode PDF
        $responsePax = $this->get(route('dau.export.pdf', [
            'upload' => $this->dau10bUpload->id,
            'metric' => 'passenger',
        ]));
        $responsePax->assertOk();
        $this->assertEquals('application/pdf', $responsePax->headers->get('Content-Type'));
        $this->assertNotEmpty($responsePax->getContent());
    }
}
