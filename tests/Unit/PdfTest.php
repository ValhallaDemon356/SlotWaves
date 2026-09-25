<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportPdfExport;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use Barryvdh\DomPDF\PDF;

class PdfTest extends TestCase
{
    protected FlightDailyReportPdfExport $pdfExport;
    protected array $records;
    protected array $meta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdfExport = new FlightDailyReportPdfExport();
        $parser = new FlightDailyReportParser();
        $path = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        $parsed = $parser->parse($path);
        $this->records = $parsed['records'];
        $this->meta = $parsed['meta'];
    }

    public function test_it_generates_valid_dompdf_instance(): void
    {
        $pdf = $this->pdfExport->generate($this->records, $this->meta, ['report_mode' => 1]);

        $this->assertInstanceOf(PDF::class, $pdf);
    }

    public function test_pdf_output_contains_kpis_and_no_empty_chart_placeholders(): void
    {
        $pdf = $this->pdfExport->generate($this->records, $this->meta, ['report_mode' => 1]);
        $binary = $pdf->output();

        $this->assertNotEmpty($binary);
        // PDF header magic bytes %PDF-
        $this->assertStringStartsWith('%PDF-', $binary);

        // Render HTML view directly to inspect contents
        $view = view('fdr.pdf', [
            'meta'           => $this->meta,
            'filters'        => ['report_mode' => 1],
            'kpis'           => (new \App\Services\FlightDailyReport\FlightDailyReportAnalytics())->computeTopKpis($this->records),
            'hourly'         => (new \App\Services\FlightDailyReport\HourlyChartService())->buildHourlyCharts($this->records, 'CGK')['hourly_data'],
            'svgChart1'      => (new \App\Services\FlightDailyReport\HourlyChartService())->renderChartSvg('movement', (new \App\Services\FlightDailyReport\HourlyChartService())->buildHourlyCharts($this->records, 'CGK')['hourly_data']),
            'svgChart2'      => (new \App\Services\FlightDailyReport\HourlyChartService())->renderChartSvg('departure', (new \App\Services\FlightDailyReport\HourlyChartService())->buildHourlyCharts($this->records, 'CGK')['hourly_data']),
            'svgChart3'      => (new \App\Services\FlightDailyReport\HourlyChartService())->renderChartSvg('arrival', (new \App\Services\FlightDailyReport\HourlyChartService())->buildHourlyCharts($this->records, 'CGK')['hourly_data']),
            'schedVsReal'    => (new \App\Services\FlightDailyReport\FlightDailyReportAnalytics())->computeScheduleVsRealization($this->records),
            'paxAnalytics'   => (new \App\Services\FlightDailyReport\FlightDailyReportAnalytics())->computePassengerAnalytics($this->records),
            'airlineRoute'   => (new \App\Services\FlightDailyReport\FlightDailyReportAnalytics())->computeAirlineRoutePerformance($this->records),
            'groundOps'      => (new \App\Services\FlightDailyReport\FlightDailyReportAnalytics())->computeGroundOps($this->records),
            'reconciliation' => (new \App\Services\FlightDailyReport\ReconciliationEngine())->reconcileOasysVsApps($this->records),
            'reportMode'     => 1,
            'records'        => array_slice($this->records, 0, 20),
            'totalRecords'   => count($this->records),
        ])->render();

        // 1. Verify Top KPIs exist
        $this->assertStringContainsString('Total Flights', $view);
        $this->assertStringContainsString('Total Passengers', $view);
        $this->assertStringContainsString('Average Load Factor', $view);
        $this->assertStringContainsString('Cargo &amp; Baggage', $view);

        // 2. Verify 3 Mentor Hourly Charts exist as SVGs (NO empty placeholder boxes)
        $this->assertStringContainsString('Chart 1: ARRIVAL–DEPARTURE MOVEMENT', $view);
        $this->assertStringContainsString('Chart 2: DEPARTURE MOVEMENT', $view);
        $this->assertStringContainsString('Chart 3: ARRIVAL MOVEMENT', $view);

        // Check for native SVG tags
        $this->assertGreaterThanOrEqual(3, substr_count($view, '<svg'));
        $this->assertStringContainsString('stroke="#EF4444"', $view); // Runway Capacity red line
        $this->assertStringContainsString('#FDBA74', $view); // Plan peach bar
        $this->assertStringContainsString('#93C5FD', $view); // Plan light blue bar
        $this->assertStringContainsString('#FDA4AF', $view); // Plan light salmon bar

        // 3. Verify detailed table is present
        $this->assertStringContainsString('Flight Daily Records', $view);
        $this->assertStringContainsString($this->records[0]['flight_no'], $view);
    }

    public function test_pdf_download_returns_attachment_response(): void
    {
        $response = $this->pdfExport->download($this->records, $this->meta, ['report_mode' => 1]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
    }
}
