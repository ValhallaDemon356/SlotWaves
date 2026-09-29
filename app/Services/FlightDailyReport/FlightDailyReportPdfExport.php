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

        $analysisLevel = $filters['analysis_level'] ?? 'DAILY';
        $analysisDate = $filters['analysis_date'] ?? '';
        $analysisMonth = $filters['analysis_month'] ?? '';
        $analysisYear = $filters['analysis_year'] ?? '';

        // Compute full analytics payload
        $analyticsData = $this->analytics->compute($filteredRecords, $meta, [
            'report_mode'    => $reportMode,
            'time_basis'     => $filters['time_basis'] ?? 'scheduled',
            'analysis_level' => $analysisLevel,
            'report_date'    => $analysisDate,
        ]);

        // Generate vector SVGs for the 3 Mentor Hourly Charts (legacy support)
        $hourlyData = $analyticsData['hourly_charts']['hourly_data'] ?? [];
        $svgChart1 = $this->hourlyChartService->renderChartSvg('movement', $hourlyData, 720, 160);
        $svgChart2 = $this->hourlyChartService->renderChartSvg('departure', $hourlyData, 720, 140);
        $svgChart3 = $this->hourlyChartService->renderChartSvg('arrival', $hourlyData, 720, 140);

        // Generate vector SVGs for the Combined Analytics Section
        $legFilter = strtoupper($filters['leg'] ?? 'ALL');
        $trafficFilter = strtoupper($filters['traffic'] ?? 'ALL');
        $combinedTrend = $analyticsData['combined_trend'] ?? [];

        $svgFlightMovement = $this->hourlyChartService->renderCombinedFlightMovementSvg($combinedTrend, $legFilter, $trafficFilter, 720, 150);
        $svgPaxTrend = $this->hourlyChartService->renderCombinedPaxTrendSvg($combinedTrend, $legFilter, 720, 120);
        $svgCargoTrend = $this->hourlyChartService->renderCombinedCargoTrendSvg($combinedTrend, $legFilter, 720, 120);
        $svgScheduleVariance = $this->hourlyChartService->renderScheduleVarianceDistributionSvg($analyticsData['schedule_vs_realization'], 720, 135);

        // Generate Donut SVGs (Passenger Composition & Payload Composition)
        $paxComp = $analyticsData['passenger_analytics']['composition'] ?? [];
        $adult = (int)($paxComp['adult'] ?? 0);
        $child = (int)($paxComp['child'] ?? 0);
        $infant = (int)($paxComp['infant'] ?? 0);
        $totalDirectPax = $adult + $child + $infant;

        $paxSegments = [
            ['label' => 'Adult', 'value' => $adult, 'color' => '#2563EB'],
            ['label' => 'Child', 'value' => $child, 'color' => '#38BDF8'],
            ['label' => 'Infant', 'value' => $infant, 'color' => '#A855F7'],
        ];
        $svgPaxDonut = $this->hourlyChartService->renderDonutSvg($paxSegments, 'TOTAL PAX', number_format($totalDirectPax), 130);

        $cargoKg = (float)($analyticsData['kpis']['total_cargo_kg'] ?? 0);
        $baggageKg = (float)($analyticsData['kpis']['total_baggage_kg'] ?? 0);
        $totalPayload = $cargoKg + $baggageKg;
        $payloadValFmt = ($totalPayload >= 1000) ? number_format($totalPayload / 1000, 1) . ' t' : number_format($totalPayload) . ' kg';

        $payloadSegments = [
            ['label' => 'Cargo', 'value' => $cargoKg, 'color' => '#10B981'],
            ['label' => 'Baggage', 'value' => $baggageKg, 'color' => '#F59E0B'],
        ];
        $svgPayloadDonut = $this->hourlyChartService->renderDonutSvg($payloadSegments, 'TOTAL PAYLOAD', $payloadValFmt, 130);

        if ($analysisLevel === 'DAILY') {
            $analysisDateFormatted = (!empty($analysisDate) && $analysisDate !== 'ALL')
                ? date('d F Y', strtotime($analysisDate))
                : ($meta['period_label'] ?? 'Peak Daily Analysis');
        } elseif ($analysisLevel === 'MONTHLY') {
            $m = !empty($analysisMonth) ? $analysisMonth : substr($analysisDate, 0, 7);
            $analysisDateFormatted = !empty($m) ? date('F Y', strtotime($m . '-01')) : ($meta['period_label'] ?? 'Monthly Overview');
        } elseif ($analysisLevel === 'YEARLY') {
            $y = !empty($analysisYear) ? $analysisYear : substr($analysisDate, 0, 4);
            $analysisDateFormatted = !empty($y) ? "Year {$y}" : ($meta['period_label'] ?? 'Yearly Overview');
        } else {
            $analysisDateFormatted = $meta['period_label'] ?? 'Operational Analysis';
        }

        $pdf = Pdf::loadView('fdr.pdf', [
            'meta'                  => $meta,
            'filters'               => $filters,
            'legFilter'             => $legFilter,
            'trafficFilter'         => $trafficFilter,
            'analysisLevel'         => $analysisLevel,
            'analysisDate'          => $analysisDate,
            'analysisDateFormatted' => $analysisDateFormatted,
            'kpis'                  => $analyticsData['kpis'],
            'hourly'                => $hourlyData,
            'combinedTrend'         => $combinedTrend,
            'peakHour'              => $analyticsData['hourly_charts']['peak_hour'] ?? null,
            'svgChart1'             => $svgChart1,
            'svgChart2'             => $svgChart2,
            'svgChart3'             => $svgChart3,
            'svgFlightMovement'     => $svgFlightMovement,
            'svgPaxTrend'           => $svgPaxTrend,
            'svgCargoTrend'         => $svgCargoTrend,
            'svgPaxDonut'           => $svgPaxDonut,
            'svgPayloadDonut'       => $svgPayloadDonut,
            'svgScheduleVariance'   => $svgScheduleVariance,
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
