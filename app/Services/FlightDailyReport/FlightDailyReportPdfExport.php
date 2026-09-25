<?php

namespace App\Services\FlightDailyReport;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class FlightDailyReportPdfExport
{
    protected HourlyChartService $hourlyChartService;
    protected FlightDailyReportAnalytics $analytics;

    public function __construct(
        HourlyChartService $hourlyChartService = null,
        FlightDailyReportAnalytics $analytics = null
    ) {
        $this->hourlyChartService = $hourlyChartService ?: new HourlyChartService();
        $this->analytics = $analytics ?: new FlightDailyReportAnalytics();
    }

    /**
     * Generate high-fidelity DomPDF instance for FDR analytics report.
     */
    public function generate(array $filteredRecords, array $meta, array $filters = []): \Barryvdh\DomPDF\PDF
    {
        $reportMode = (int)($filters['report_mode'] ?? 1);
        $airportCode = $meta['airport'] ?? 'CGK';

        // Compute full analytics payload
        $analyticsData = $this->analytics->compute($filteredRecords, $meta, ['report_mode' => $reportMode]);

        // Generate vector SVGs for the 3 Mentor Hourly Charts
        $hourlyData = $analyticsData['hourly_charts']['hourly_data'] ?? [];
        $svgChart1 = $this->hourlyChartService->renderChartSvg('movement', $hourlyData, 720, 160);
        $svgChart2 = $this->hourlyChartService->renderChartSvg('departure', $hourlyData, 720, 140);
        $svgChart3 = $this->hourlyChartService->renderChartSvg('arrival', $hourlyData, 720, 140);

        $analysisDate = $filters['analysis_date'] ?? '';
        $analysisDateFormatted = (!empty($analysisDate) && $analysisDate !== 'ALL')
            ? date('d F Y', strtotime($analysisDate))
            : ($meta['period_label'] ?? 'Operational Analysis');

        $pdf = Pdf::loadView('fdr.pdf', [
            'meta'                  => $meta,
            'filters'               => $filters,
            'analysisDate'          => $analysisDate,
            'analysisDateFormatted' => $analysisDateFormatted,
            'kpis'                  => $analyticsData['kpis'],
            'hourly'                => $hourlyData,
            'peakHour'              => $analyticsData['hourly_charts']['peak_hour'] ?? null,
            'svgChart1'             => $svgChart1,
            'svgChart2'             => $svgChart2,
            'svgChart3'             => $svgChart3,
            'schedVsReal'           => $analyticsData['schedule_vs_realization'],
            'paxAnalytics'          => $analyticsData['passenger_analytics'],
            'airlineRoute'          => $analyticsData['airline_route'],
            'groundOps'             => $analyticsData['ground_operations'],
            'reconciliation'        => ($reportMode === 7) ? $analyticsData['reconciliation_apps'] : $analyticsData['reconciliation_edifly'],
            'reportMode'            => $reportMode,
            'records'               => array_slice($filteredRecords, 0, 150), // Show up to 150 records in PDF
            'totalRecords'          => count($filteredRecords),
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'sans-serif',
            'dpi'                  => 150,
        ]);

        return $pdf;
    }

    /**
     * Download PDF response.
     */
    public function download(array $filteredRecords, array $meta, array $filters = []): Response
    {
        $pdf = $this->generate($filteredRecords, $meta, $filters);
        $analysisDate = $filters['analysis_date'] ?? '';
        $dateSuffix = (!empty($analysisDate) && $analysisDate !== 'ALL') ? date('Ymd', strtotime($analysisDate)) : date('Ymd_His');
        $filename = 'FDR_' . ($meta['airport'] ?? 'AIRPORT') . '_' . $dateSuffix . '.pdf';

        return $pdf->download($filename);
    }
}
