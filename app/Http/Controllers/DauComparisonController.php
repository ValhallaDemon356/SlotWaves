<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Services\Dau\DauComparisonService;
use App\Services\Dau\Parsers\BaseDauParser;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DauComparisonController extends Controller
{
    /**
     * AJAX endpoint to validate a candidate list of DAU reports for comparison.
     */
    public function validateComparison(Request $request)
    {
        $reportIds = $request->input('report_ids', []);
        if (is_string($reportIds)) {
            $reportIds = explode(',', $reportIds);
        }
        $reportIds = array_filter(array_map('trim', (array)$reportIds));

        if (count($reportIds) < 2) {
            return response()->json([
                'valid'    => false,
                'checks'   => [
                    'same_dau_type'       => false,
                    'same_airport'        => false,
                    'different_period'    => false,
                    'same_period_length'  => false,
                    'compatible_template' => false,
                ],
                'errors'   => ['Minimum 2 reports are required for comparison.'],
                'warnings' => [],
            ], 422);
        }

        $uploads = Upload::whereIn('id', $reportIds)->get()->keyBy('id');
        $reports = [];
        foreach ($reportIds as $id) {
            if (isset($uploads[$id])) {
                $u = $uploads[$id];
                $reports[] = [
                    'id'           => $u->id,
                    'report_type'  => $u->report_type,
                    'airport_code' => $u->report_data['meta']['airport_code'] ?? 'CGK',
                    'airport_name' => $u->report_data['meta']['airport_name'] ?? 'Soekarno Hatta',
                    'start_date'   => $u->report_data['meta']['start_date'] ?? null,
                    'end_date'     => $u->report_data['meta']['end_date'] ?? null,
                    'meta'         => $u->report_data['meta'] ?? [],
                    'report_data'  => $u->report_data ?? [],
                ];
            }
        }

        $validation = DauComparisonService::validateComparisonReports($reports);
        $status = $validation['valid'] ? 200 : 422;

        return response()->json($validation, $status);
    }

    /**
     * Display the historical comparison dashboard.
     */
    public function show(Request $request)
    {
        $rawReportsParam = $request->query('reports', '');
        $reportIds = is_array($rawReportsParam) ? $rawReportsParam : explode(',', (string)$rawReportsParam);
        $reportIds = array_filter(array_map('trim', $reportIds));

        if (count($reportIds) < 2) {
            return redirect()->route('home')->withErrors([
                'dau' => 'Historical comparison requires at least 2 valid DAU-02 reports.'
            ]);
        }

        $uploads = Upload::whereIn('id', $reportIds)
            ->where('status', 'completed')
            ->get();

        if ($uploads->count() < 2) {
            return redirect()->route('home')->withErrors([
                'dau' => 'One or more comparison reports could not be found or have not completed processing.'
            ]);
        }

        $reportsData = [];
        foreach ($uploads as $u) {
            $reportsData[] = [
                'id'           => $u->id,
                'report_type'  => $u->report_type,
                'airport_code' => $u->report_data['meta']['airport_code'] ?? 'CGK',
                'airport_name' => $u->report_data['meta']['airport_name'] ?? 'Soekarno Hatta',
                'start_date'   => $u->report_data['meta']['start_date'] ?? null,
                'end_date'     => $u->report_data['meta']['end_date'] ?? null,
                'meta'         => $u->report_data['meta'] ?? [],
                'report_data'  => $u->report_data ?? [],
            ];
        }

        $filters = [
            'flight_type' => strtoupper(trim($request->query('flight_type', 'ALL'))),
            'direction'   => strtoupper(trim($request->query('direction', 'ALL'))),
        ];

        $baselinePeriodKey = trim($request->query('baseline', ''));

        $comparison = DauComparisonService::buildComparisonModel($reportsData, $filters, $baselinePeriodKey ?: null);

        if (!$comparison['valid']) {
            $err = !empty($comparison['validation']['errors'])
                ? implode(' ', $comparison['validation']['errors'])
                : 'Invalid comparison parameters.';
            return redirect()->route('home')->withErrors(['dau' => $err]);
        }

        return view('dau.comparison', [
            'comparison'  => $comparison,
            'reportIds'   => $reportIds,
            'reportIdsStr'=> implode(',', $reportIds),
            'filters'     => $filters,
        ]);
    }

    /**
     * Export structured comparison PDF.
     */
    public function exportPdf(Request $request)
    {
        $rawReportsParam = $request->query('reports', '');
        $reportIds = is_array($rawReportsParam) ? $rawReportsParam : explode(',', (string)$rawReportsParam);
        $reportIds = array_filter(array_map('trim', $reportIds));

        if (count($reportIds) < 2) {
            abort(404, 'Comparison requires at least 2 reports.');
        }

        $uploads = Upload::whereIn('id', $reportIds)
            ->where('status', 'completed')
            ->get();

        if ($uploads->count() < 2) {
            abort(404, 'One or more reports not ready.');
        }

        $reportsData = [];
        foreach ($uploads as $u) {
            $reportsData[] = [
                'id'           => $u->id,
                'report_type'  => $u->report_type,
                'airport_code' => $u->report_data['meta']['airport_code'] ?? 'CGK',
                'airport_name' => $u->report_data['meta']['airport_name'] ?? 'Soekarno Hatta',
                'start_date'   => $u->report_data['meta']['start_date'] ?? null,
                'end_date'     => $u->report_data['meta']['end_date'] ?? null,
                'meta'         => $u->report_data['meta'] ?? [],
                'report_data'  => $u->report_data ?? [],
            ];
        }

        $filters = [
            'flight_type' => strtoupper(trim($request->query('flight_type', 'ALL'))),
            'direction'   => strtoupper(trim($request->query('direction', 'ALL'))),
        ];

        $baselinePeriodKey = trim($request->query('baseline', ''));

        $comparison = DauComparisonService::buildComparisonModel($reportsData, $filters, $baselinePeriodKey ?: null);

        $airportCode = $comparison['airport_code'] ?: 'CGK';
        $timestamp = now()->format('Ymd_His');
        $filename = "DAU02_Comparison_{$airportCode}_{$timestamp}.pdf";

        $pdf = Pdf::loadView('dau.comparison-pdf', [
            'comparison' => $comparison,
            'filters'    => $filters,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    /**
     * Export comparison tabular metrics to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $rawReportsParam = $request->query('reports', '');
        $reportIds = is_array($rawReportsParam) ? $rawReportsParam : explode(',', (string)$rawReportsParam);
        $reportIds = array_filter(array_map('trim', $reportIds));

        if (count($reportIds) < 2) {
            abort(404, 'Comparison requires at least 2 reports.');
        }

        $uploads = Upload::whereIn('id', $reportIds)
            ->where('status', 'completed')
            ->get();

        $reportsData = [];
        foreach ($uploads as $u) {
            $reportsData[] = [
                'id'           => $u->id,
                'report_type'  => $u->report_type,
                'airport_code' => $u->report_data['meta']['airport_code'] ?? 'CGK',
                'airport_name' => $u->report_data['meta']['airport_name'] ?? 'Soekarno Hatta',
                'start_date'   => $u->report_data['meta']['start_date'] ?? null,
                'end_date'     => $u->report_data['meta']['end_date'] ?? null,
                'meta'         => $u->report_data['meta'] ?? [],
                'report_data'  => $u->report_data ?? [],
            ];
        }

        $filters = [
            'flight_type' => strtoupper(trim($request->query('flight_type', 'ALL'))),
            'direction'   => strtoupper(trim($request->query('direction', 'ALL'))),
        ];

        $baselinePeriodKey = trim($request->query('baseline', ''));
        $comparison = DauComparisonService::buildComparisonModel($reportsData, $filters, $baselinePeriodKey ?: null);

        $airportCode = $comparison['airport_code'] ?: 'CGK';
        $filename = "DAU02_Comparison_{$airportCode}_" . now()->format('Ymd_His') . ".csv";

        return new StreamedResponse(function () use ($comparison, $filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['# SLOTWAVES DAU-02 HISTORICAL OPERATIONAL COMPARISON']);
            fputcsv($handle, ['Airport', $comparison['airport_name'] . ' (' . $comparison['airport_code'] . ')']);
            fputcsv($handle, ['Flight Scope', $filters['flight_type']]);
            fputcsv($handle, ['Direction', $filters['direction']]);
            fputcsv($handle, ['Generated At', now()->toDateTimeString()]);
            fputcsv($handle, []);

            // Summary Table
            fputcsv($handle, ['METRIC', 'PERIOD', 'PERIOD LABEL', 'DATE RANGE', 'DATA DAYS', 'VALUE', 'UNIT', 'DIFFERENCE', 'GROWTH %', 'RECOVERY RATE %']);

            $metrics = [
                'passenger' => ['name' => 'Pergerakan Penumpang', 'unit' => 'Pax'],
                'aircraft'  => ['name' => 'Pergerakan Pesawat',   'unit' => 'Movements'],
                'cargo'     => ['name' => 'Pergerakan Kargo',     'unit' => $comparison['cargo_unit']],
            ];

            foreach ($metrics as $mKey => $mInfo) {
                $series = $comparison['analysis'][$mKey]['series'] ?? [];
                foreach ($series as $pKey => $row) {
                    $pData = $comparison['periods'][$pKey] ?? [];
                    fputcsv($handle, [
                        $mInfo['name'],
                        $row['period_label'],
                        $row['short_label'],
                        $row['display_range'],
                        $pData['data_days'] ?? '',
                        $row['value'],
                        $mInfo['unit'],
                        $row['difference'],
                        $row['growth_fmt'],
                        $row['recovery_fmt'],
                    ]);
                }
            }

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
