<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FDRDailyPdfTest extends TestCase
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

    public function test_fdr_export_pdf_with_analysis_date_downloads_valid_pdf(): void
    {
        // Get available dates from report_data
        $records = $this->upload->report_data['records'] ?? [];
        $firstDate = $records[0]['flight_date'] ?? '2026-08-01';

        $response = $this->get(route('fdr.export.pdf', [
            'upload'        => $this->upload->id,
            'analysis_date' => $firstDate,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_fdr_export_csv_with_analysis_date_filters_properly(): void
    {
        $records = $this->upload->report_data['records'] ?? [];
        $firstDate = $records[0]['flight_date'] ?? '2026-08-01';

        $response = $this->get(route('fdr.export.csv', [
            'upload'        => $this->upload->id,
            'analysis_date' => $firstDate,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $response->streamedContent();
        $this->assertNotEmpty($content);
    }
}
