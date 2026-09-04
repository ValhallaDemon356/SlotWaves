<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Services\Dau\ReportTemplateRegistry;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DauDashboardController extends Controller
{
    /**
     * Display the DAU analytical dashboard for a completed DAU upload.
     */
    public function show(Upload $upload)
    {
        if ($upload->status !== 'completed') {
            return redirect()->route('home')->withErrors([
                'dau' => 'This report has not completed processing or encountered an error.'
            ]);
        }

        // If this is actually an Airport Slot Schedule upload, redirect to slot schedule dashboard
        if ($upload->report_type === 'slot_schedule' || empty($upload->report_type)) {
            return redirect()->route('schedule.dashboard', $upload->id);
        }

        session(['active_upload_id' => $upload->id]);

        $reportType = $upload->report_type;
        $conf = ReportTemplateRegistry::find($reportType);
        $data = $upload->report_data ?? [];

        $meta = $data['meta'] ?? [
            'airport'        => 'Soekarno Hatta (CGK)',
            'airport_code'   => 'CGK',
            'airport_name'   => 'Tangerang Banten - Soekarno Hatta',
            'date_range'     => 'N/A',
            'start_date'     => date('Y-m-d'),
            'end_date'       => date('Y-m-d'),
            'flight_scope'   => 'DOMESTIK & INTERNASIONAL',
            'terminal_scope' => 'ALL TERMINAL',
            'source'         => 'OASYS',
        ];

        // For DAU10A and DAU4B, prefer normalized_pairs so each row has all standard movement columns
        if (in_array($reportType, ['DAU10A', 'DAU4B']) && !empty($data['normalized_pairs'])) {
            $records = $data['normalized_pairs'];
        } else {
            $records = $data['records'] ?? [];
        }

        // Extract terminals list
        $terminals = [];
        if ($reportType === 'DAU10A' && !empty($data['matrix_terminals'])) {
            foreach ($data['matrix_terminals'] as $mt) {
                $terminals[] = (string) $mt['name'];
            }
        } else {
            $termSet = [];
            foreach ($records as $r) {
                if (!empty($r['terminal'])) {
                    $termSet[(string)$r['terminal']] = true;
                }
            }
            $terminals = array_keys($termSet);
        }

        // Extract hours list
        $hourSet = [];
        foreach ($records as $r) {
            $h = $r['hour'] ?? $r['period'] ?? null;
            if (!empty($h)) {
                $hourSet[(string)$h] = true;
            }
        }
        $hours = array_keys($hourSet);

        // Extract airlines list
        $airlineSet = [];
        foreach ($records as $r) {
            $al = $r['airline'] ?? $r['operator_name'] ?? null;
            if (!empty($al)) {
                $airlineSet[(string)$al] = true;
            }
        }
        $airlines = array_keys($airlineSet);

        // Extract airports / routes list
        $airportSet = [];
        foreach ($records as $r) {
            $ap = $r['airport_route'] ?? $r['airport'] ?? $r['city'] ?? null;
            if (!empty($ap)) {
                $airportSet[(string)$ap] = true;
            }
        }
        $airports = array_keys($airportSet);

        // Extract aircraft types
        $actSet = [];
        foreach ($records as $r) {
            $act = $r['aircraft_type'] ?? null;
            if (!empty($act)) {
                $actSet[(string)$act] = true;
            }
        }
        $aircraftTypes = array_keys($actSet);

        // Extract categories
        $catSet = [];
        foreach ($records as $r) {
            $cat = $r['category'] ?? null;
            if (!empty($cat)) {
                $catSet[(string)$cat] = true;
            }
        }
        $categories = array_keys($catSet);

        // Precompute initial full analytics
        $analytics = $this->filterReportDataset($records, [
            'flight_type' => 'ALL',
            'terminal'    => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'aircraft',
            'operation'   => 'ALL',
            'direction'   => 'ALL',
            'airline'     => 'ALL',
            'airport'     => 'ALL',
            'schedule_type' => 'ALL',
            'status'      => 'ALL',
            'aircraft_type' => 'ALL',
            'category'    => 'ALL',
            'search'      => '',
            'top_n'       => 'ALL',
            'threshold'   => 0,
        ], $meta, $reportType);

        $summary = $analytics['summary'];
        $peaks = $analytics['peaks'];
        $hourlyDistribution = $analytics['hourly_distribution'];
        $terminalComparison = $analytics['terminal_comparison'];
        $columns = $data['columns'] ?? [];
        $matrixRecords = $data['records'] ?? [];

        // Airport capacity, operational hours and timezone configuration
        $initialNac = (int) config('slotwaves.nac', 6);
        $initialArrivalCapacity = (int) config('slotwaves.nac', 6);
        $initialDepartureCapacity = (int) config('slotwaves.nac', 6);
        $airport = null;
        try {
            $airport = $upload->airport ?: \App\Models\Airport::where('iata_code', $meta['airport_code'] ?? 'CGK')->first();
            if ($airport) {
                $initialNac = (int) $airport->getEffectiveCapacity();
                $initialArrivalCapacity = (int) $airport->getEffectiveArrivalCapacity();
                $initialDepartureCapacity = (int) $airport->getEffectiveDepartureCapacity();
            }
        } catch (\Throwable $e) {}

        $opsStartTime = $airport?->ops_start_time ?? '06:00';
        $opsEndTime   = $airport?->ops_end_time ?? '20:00';
        $tzAbbr       = $airport ? $airport->getTimezoneAbbreviation() : 'WIB';
        $tzOffset     = $airport ? (int) round($airport->getTimezoneOffsetMinutes() / 60) : 7;

        return view('dau.dashboard', compact(
            'upload',
            'reportType',
            'conf',
            'data',
            'meta',
            'summary',
            'records',
            'columns',
            'terminals',
            'hours',
            'airlines',
            'airports',
            'aircraftTypes',
            'categories',
            'peaks',
            'hourlyDistribution',
            'terminalComparison',
            'matrixRecords',
            'analytics',
            'initialNac',
            'initialArrivalCapacity',
            'initialDepartureCapacity',
            'opsStartTime',
            'opsEndTime',
            'tzAbbr',
            'tzOffset'
        ));
    }

    /**
     * Download authentic reference template for a DAU report.
     */
    public function downloadTemplate(string $reportType)
    {
        $conf = ReportTemplateRegistry::find($reportType);
        if (!$conf || ($conf['is_pdf'] ?? false)) {
            abort(404, "Template for report type {$reportType} not found.");
        }

        $path = ReportTemplateRegistry::getTemplatePath($reportType);
        if (!$path || !file_exists($path)) {
            abort(404, "Template file {$conf['template_filename']} is not currently available.");
        }

        return response()->download($path, $conf['template_filename'], [
            'Content-Type' => 'application/vnd.ms-excel',
        ]);
    }

    /**
     * Export DAU report as print-ready PDF matching current active filters.
     */
    public function exportPdf(Upload $upload, Request $request)
    {
        if ($upload->status !== 'completed' || empty($upload->report_data)) {
            abort(404, "Report data not ready for export.");
        }

        $reportType = $upload->report_type;
        $conf = ReportTemplateRegistry::find($reportType);
        $data = $upload->report_data;
        $meta = $data['meta'] ?? [];

        if (in_array($reportType, ['DAU10A', 'DAU4B']) && !empty($data['normalized_pairs'])) {
            $baseRecords = $data['normalized_pairs'];
        } else {
            $baseRecords = $data['records'] ?? [];
        }

        // Read active filters from request
        $filters = [
            'flight_type'   => strtoupper(trim($request->query('flight_type', 'ALL'))),
            'terminal'      => trim($request->query('terminal', 'ALL')),
            'hour'          => trim($request->query('hour', 'ALL')),
            'metric'        => strtolower(trim($request->query('metric', 'aircraft'))),
            'operation'     => strtoupper(trim($request->query('operation', 'ALL'))),
            'direction'     => strtoupper(trim($request->query('direction', 'ALL'))),
            'airline'       => trim($request->query('airline', 'ALL')),
            'airport'       => trim($request->query('airport', 'ALL')),
            'schedule_type' => strtoupper(trim($request->query('schedule_type', 'ALL'))),
            'status'        => strtoupper(trim($request->query('status', 'ALL'))),
            'aircraft_type' => trim($request->query('aircraft_type', 'ALL')),
            'category'      => trim($request->query('category', 'ALL')),
            'search'        => trim($request->query('search', '')),
            'top_n'         => trim($request->query('top_n', 'ALL')),
            'threshold'     => (int) $request->query('threshold', 0),
            'start_date'    => trim($request->query('start_date', '')),
            'end_date'      => trim($request->query('end_date', '')),
        ];

        // Apply filtering and analytics
        $analytics = $this->filterReportDataset($baseRecords, $filters, $meta, $reportType);

        $filteredRecords = $analytics['filtered_records'];
        $summary = $analytics['summary'];
        $peaks = $analytics['peaks'];
        $hourlyData = $analytics['hourly_distribution'];
        $terminalData = $analytics['terminal_comparison'];
        $heatmapMatrix = $analytics['heatmap_matrix'] ?? [];

        // Update meta reflecting active filters
        $activeFlightScope = 'ALL';
        if ($filters['flight_type'] === 'DOM' || $filters['flight_type'] === 'DOMESTIC') {
            $activeFlightScope = 'DOMESTIK';
        } elseif ($filters['flight_type'] === 'INT' || $filters['flight_type'] === 'INTERNATIONAL') {
            $activeFlightScope = 'INTERNASIONAL';
        } else {
            $activeFlightScope = $meta['flight_scope'] ?? 'DOMESTIK & INTERNASIONAL';
        }
        $meta['flight_scope'] = $activeFlightScope;

        $activeTerminalScope = $filters['terminal'] !== 'ALL' ? 'TERMINAL ' . $filters['terminal'] : ($meta['terminal_scope'] ?? 'ALL TERMINAL');
        $meta['terminal_scope'] = $activeTerminalScope;

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $meta['date_range'] = "{$filters['start_date']} s/d {$filters['end_date']}";
        }

        // Generate dynamic PDF filename: e.g. DAU-01_CGK_YYYY-MM-DD.pdf or DAU-10A_CGK_YYYY-MM-DD_DOM_T2F.pdf
        $code = str_replace('-', '', $conf['code'] ?? $reportType);
        if (preg_match('/DAU([0-9]+)([A-Z]?)/i', $code, $cm)) {
            $codeFormat = 'DAU-' . str_pad($cm[1], 2, '0', STR_PAD_LEFT) . ($cm[2] ?? '');
        } else {
            $codeFormat = $conf['code'] ?? $reportType;
        }

        $airportCode = $meta['airport_code'] ?? 'CGK';
        $datePart = $meta['start_date'] ?? date('Y-m-d');
        $filename = "{$codeFormat}_{$airportCode}_{$datePart}";

        if ($filters['flight_type'] === 'DOM' || $filters['flight_type'] === 'DOMESTIC') {
            $filename .= '_DOM';
        } elseif ($filters['flight_type'] === 'INT' || $filters['flight_type'] === 'INTERNATIONAL') {
            $filename .= '_INT';
        }

        if ($filters['terminal'] !== 'ALL') {
            $cleanTerm = preg_replace('/[^A-Za-z0-9]/', '', $filters['terminal']);
            $filename .= "_T{$cleanTerm}";
        }

        if ($filters['hour'] !== 'ALL') {
            $cleanHour = substr(preg_replace('/[^0-9]/', '', $filters['hour']), 0, 4);
            if ($cleanHour) $filename .= "_{$cleanHour}";
        }

        if ($reportType === 'DAU10B' && $filters['operation'] !== 'ALL') {
            $filename .= '_' . ($filters['operation'] === 'BLOCK_ON' ? 'BLOCKON' : 'BLOCKOFF');
        }

        $filename .= '.pdf';

        // Resolve Aircraft Capacity (ARR and DEP) for report
        $airport = null;
        try {
            $airport = $upload->airport ?: \App\Models\Airport::where('iata_code', $meta['airport_code'] ?? 'CGK')->first();
        } catch (\Throwable $e) {}

        $reqArrNac = $request->query('arr_nac', $request->query('nac'));
        $reqDepNac = $request->query('dep_nac', $request->query('nac'));

        $arrNac = ($reqArrNac !== null && is_numeric($reqArrNac) && (int)$reqArrNac > 0)
            ? (int) $reqArrNac
            : ($airport ? $airport->getEffectiveArrivalCapacity() : (int) config('slotwaves.nac', 6));

        $depNac = ($reqDepNac !== null && is_numeric($reqDepNac) && (int)$reqDepNac > 0)
            ? (int) $reqDepNac
            : ($airport ? $airport->getEffectiveDepartureCapacity() : (int) config('slotwaves.nac', 6));

        $nac = max($arrNac, $depNac);

        $reqOpsStart = $request->query('ops_start');
        $reqOpsEnd   = $request->query('ops_end');

        $opsStart = ($reqOpsStart !== null && trim($reqOpsStart) !== '')
            ? trim($reqOpsStart)
            : ($airport?->ops_start_time ?? '06:00');

        $opsEnd   = ($reqOpsEnd !== null && trim($reqOpsEnd) !== '')
            ? trim($reqOpsEnd)
            : ($airport?->ops_end_time ?? '20:00');

        $is24h    = ($opsStart === '00:00' && ($opsEnd === '24:00' || $opsEnd === '23:59'));
        $startNum = (int) explode(':', $opsStart)[0];
        $endNum   = (int) explode(':', $opsEnd)[0];

        // Compute DAU-10A Capacity Status & Summary
        $capacitySummary = [
            'nac'                 => $nac,
            'arr_nac'             => $arrNac,
            'dep_nac'             => $depNac,
            'ops_start'           => $opsStart,
            'ops_end'             => $opsEnd,
            'peak_aircraft'       => $peaks['peak_aircraft'],
            'peak_hour'           => $peaks['peak_aircraft_hour'],
            'available_hours'     => 0,
            'full_hours'          => 0,
            'over_capacity_hours' => 0,
            'off_hours'           => 0,
        ];

        $hourlyCapacityStatus = [];
        if ($reportType === 'DAU10A') {
            foreach ($hourlyData as $hd) {
                $arr = (int)($hd['aircraft_arrival'] ?? 0);
                $dep = (int)($hd['aircraft_departure'] ?? 0);
                $demand = $arr + $dep;
                $util = $nac > 0 ? round(($demand / $nac) * 100) : 0;

                $hNum = (int) explode(':', explode(' - ', $hd['hour'])[0])[0];
                $isOffHour = (!$is24h && ($hNum < $startNum || $hNum >= $endNum));

                $status = 'AVAILABLE';
                if ($isOffHour) {
                    $status = 'OFF HOURS';
                    $capacitySummary['off_hours']++;
                } elseif ($arr > $arrNac || $dep > $depNac) {
                    $status = 'OVER CAPACITY';
                    $capacitySummary['over_capacity_hours']++;
                } elseif ($arr === $arrNac || $dep === $depNac) {
                    $status = 'FULL / MAX';
                    $capacitySummary['full_hours']++;
                } else {
                    $status = 'AVAILABLE';
                    $capacitySummary['available_hours']++;
                }

                $hourlyCapacityStatus[] = [
                    'hour'        => $hd['hour'],
                    'arr'         => $arr,
                    'dep'         => $dep,
                    'arr_nac'     => $arrNac,
                    'dep_nac'     => $depNac,
                    'opc'         => 'N/A',
                    'demand'      => $demand,
                    'nac'         => $nac,
                    'utilization' => $util,
                    'status'      => $status,
                    'is_ops'      => !$isOffHour,
                ];
            }
        }

        $maxPdfRows = 150;
        $totalFilteredCount = count($filteredRecords);
        $isTruncatedForPdf = $totalFilteredCount > $maxPdfRows;
        $pdfRecords = $isTruncatedForPdf ? array_slice($filteredRecords, 0, $maxPdfRows) : $filteredRecords;

        $pdf = Pdf::loadView('dau.pdf', [
            'upload'               => $upload,
            'reportType'           => $reportType,
            'conf'                 => $conf,
            'data'                 => $data,
            'meta'                 => $meta,
            'summary'              => $summary,
            'records'              => $pdfRecords,
            'totalFilteredCount'   => $totalFilteredCount,
            'isTruncatedForPdf'    => $isTruncatedForPdf,
            'peaks'                => $peaks,
            'hourlyData'           => $hourlyData,
            'terminalData'         => $terminalData,
            'heatmapMatrix'        => $heatmapMatrix,
            'analytics'            => $analytics,
            'filters'              => $filters,
            'metric'               => $filters['metric'],
            'nac'                  => $nac,
            'arrNac'               => $arrNac,
            'depNac'               => $depNac,
            'opsStart'             => $opsStart,
            'opsEnd'               => $opsEnd,
            'capacitySummary'      => $capacitySummary,
            'hourlyCapacityStatus' => $hourlyCapacityStatus,
        ])
        ->setPaper('a4', 'landscape')
        ->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
            'chroot'               => public_path(),
        ]);

        return $pdf->download($filename);
    }

    /**
     * Export DAU report as CSV spreadsheet matching active filters.
     */
    public function exportExcel(Upload $upload, Request $request): StreamedResponse
    {
        if ($upload->status !== 'completed' || empty($upload->report_data)) {
            abort(404, "Report data not ready for export.");
        }

        $reportType = $upload->report_type;
        $conf = ReportTemplateRegistry::find($reportType);
        $data = $upload->report_data;
        $meta = $data['meta'] ?? [];

        if (in_array($reportType, ['DAU10A', 'DAU4B']) && !empty($data['normalized_pairs'])) {
            $baseRecords = $data['normalized_pairs'];
        } else {
            $baseRecords = $data['records'] ?? [];
        }

        $filters = [
            'flight_type'   => strtoupper(trim($request->query('flight_type', 'ALL'))),
            'terminal'      => trim($request->query('terminal', 'ALL')),
            'hour'          => trim($request->query('hour', 'ALL')),
            'metric'        => strtolower(trim($request->query('metric', 'aircraft'))),
            'operation'     => strtoupper(trim($request->query('operation', 'ALL'))),
            'direction'     => strtoupper(trim($request->query('direction', 'ALL'))),
            'airline'       => trim($request->query('airline', 'ALL')),
            'airport'       => trim($request->query('airport', 'ALL')),
            'schedule_type' => strtoupper(trim($request->query('schedule_type', 'ALL'))),
            'status'        => strtoupper(trim($request->query('status', 'ALL'))),
            'aircraft_type' => trim($request->query('aircraft_type', 'ALL')),
            'category'      => trim($request->query('category', 'ALL')),
            'search'        => trim($request->query('search', '')),
        ];

        $analytics = $this->filterReportDataset($baseRecords, $filters, $meta, $reportType);
        $records = $analytics['filtered_records'];

        $filename = ($conf ? $conf['code'] : $reportType) . '_Data_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($conf, $meta, $records, $reportType) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8

            fputcsv($handle, [$conf['title'] ?? 'DATA ANGKUTAN UDARA']);
            fputcsv($handle, ['Bandara:', $meta['airport'] ?? 'N/A']);
            fputcsv($handle, ['Tanggal:', $meta['date_range'] ?? 'N/A']);
            fputcsv($handle, ['Penerbangan:', $meta['flight_scope'] ?? 'DOMESTIK & INTERNASIONAL']);
            fputcsv($handle, ['Terminal:', $meta['terminal_scope'] ?? 'ALL TERMINAL']);
            fputcsv($handle, ['Sumber:', 'OASYS']);
            fputcsv($handle, []);

            // Dynamically select columns based on report type
            if ($reportType === 'DAU1') {
                $cols = ['No', 'Bandara Asal/Tujuan', 'Flight No', 'Status', 'Tipe Pesawat', 'Kapasitas Kursi', 'Pesawat ARR', 'Pesawat DEP', 'Pesawat Total', 'Penumpang ARR', 'Penumpang DEP', 'Penumpang Transit', 'Penumpang Transfer', 'Penumpang Total', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['no'] ?? '', $r['airport_route'] ?? $r['origin'] ?? '', $r['flight_number'] ?? '', $r['schedule_type'] ?? '', $r['aircraft_type'] ?? '', $r['seat_capacity'] ?? 0,
                        $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_transit'] ?? 0, $r['passenger_transfer'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU2') {
                $cols = ['Jenis Penerbangan', 'Pesawat ARR', 'Pesawat DEP', 'Pesawat Total', 'Penumpang ARR', 'Penumpang DEP', 'Penumpang Transit', 'Penumpang Transfer', 'Penumpang Total', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['category'] ?? '', $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_transit'] ?? 0, $r['passenger_transfer'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew_total'] ?? 0, $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU3') {
                $cols = ['Status', 'Jenis Penerbangan', 'Pesawat ARR', 'Pesawat DEP', 'Pesawat Total', 'Penumpang ARR', 'Penumpang DEP', 'Transit', 'Transfer', 'Penumpang Total', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['section'] ?? '', $r['category'] ?? '', $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_transit'] ?? 0, $r['passenger_transfer'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew_total'] ?? 0, $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU4') {
                $cols = ['No', 'Airport', 'Kode IATA', 'Kota', 'Pesawat ARR', 'Pesawat DEP', 'Pesawat Total', 'Penumpang ARR', 'Penumpang DEP', 'Transit', 'Transfer', 'Penumpang Total', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['no'] ?? '', $r['airport'] ?? '', $r['city_code'] ?? '', $r['city'] ?? '',
                        $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_transit'] ?? 0, $r['passenger_transfer'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew_total'] ?? 0, $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU4A') {
                $cols = ['No', 'Operator', 'Kode', 'Airport', 'Kode IATA', 'Kota', 'Pesawat ARR', 'Pesawat DEP', 'Pesawat Total', 'Penumpang ARR', 'Penumpang DEP', 'Transit', 'Transfer', 'Penumpang Total', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['no'] ?? '', $r['operator_name'] ?? $r['airline'] ?? '', $r['operator_code'] ?? $r['airline_code'] ?? '', $r['airport'] ?? '', $r['city_code'] ?? '', $r['city'] ?? '',
                        $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_transit'] ?? 0, $r['passenger_transfer'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew_total'] ?? 0, $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU4B') {
                $cols = ['No', 'Kota', 'Kode IATA', 'Total Pesawat', 'Total Penumpang'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['no'] ?? '', $r['city'] ?? '', $r['city_code'] ?? '', $r['aircraft_total'] ?? $r['total_flights'] ?? 0, $r['passenger_total'] ?? $r['total_passengers'] ?? 0
                    ]);
                }
            } elseif (in_array($reportType, ['DAU5', 'DAU5C'])) {
                $cols = ['No', 'Airline / Operator', 'Pesawat ARR', 'Pesawat DEP', 'Pesawat Total', 'Penumpang ARR', 'Penumpang DEP', 'Transit', 'Transfer', 'Penumpang Total', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['no'] ?? '', $r['airline'] ?? '', $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_transit'] ?? 0, $r['passenger_transfer'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew_total'] ?? 0, $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU5A') {
                $cols = ['No', 'Airline / Operator', 'Pesawat ARR', 'Pesawat DEP', 'Pesawat Total', 'Penumpang ARR', 'Penumpang DEP', 'Total Penumpang', 'Operating Crew', 'ARR Extra Crew', 'DEP Extra Crew', 'Total Extra Crew', 'Total Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['no'] ?? '', $r['airline'] ?? '', $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew'] ?? 0, $r['arr_extra_crew'] ?? 0, $r['dep_extra_crew'] ?? 0, $r['extra_crew'] ?? 0, $r['crew_total'] ?? 0,
                        $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU5B') {
                $cols = ['No', 'Terminal', 'Airline', 'Pesawat ARR', 'Pesawat DEP', 'Pesawat Total', 'Penumpang ARR', 'Penumpang DEP', 'Total Penumpang', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['no'] ?? '', $r['terminal'] ?? '', $r['airline'] ?? '', $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew_total'] ?? 0, $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU6') {
                $cols = ['No', 'Tipe Pesawat', 'Kategori', 'WTC', 'Pesawat ARR', 'Pesawat DEP', 'Pesawat Total', 'Penumpang ARR', 'Penumpang DEP', 'Total Penumpang', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['no'] ?? '', $r['aircraft_type'] ?? '', $r['category'] ?? 'Narrow Body', $r['wtc'] ?? 'Medium',
                        $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew_total'] ?? 0, $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU10B') {
                $cols = ['Hour', 'Terminal', 'Block On Acft (DTG)', 'Block Off Acft (BRK)', 'Total Acft', 'Block On Pax (DTG)', 'Block Off Pax (BRK)', 'Transit Pax', 'Transfer Pax', 'Total Pax', 'Crew', 'Extra Crew', 'Total Crew', 'Baggage (Kg)', 'Cargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['hour'] ?? $r['period'] ?? '', $r['terminal'] ?? '', $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_transit'] ?? 0, $r['passenger_transfer'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew'] ?? 0, $r['extra_crew'] ?? 0, $r['crew_total'] ?? 0, $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0,
                    ]);
                }
            } elseif ($reportType === 'DAU11') {
                $cols = ['Tanggal', 'Pesawat INT ARR', 'Pesawat INT DEP', 'Pesawat DOM ARR', 'Pesawat DOM DEP', 'Total Pesawat', 'Penumpang INT ARR', 'Penumpang INT DEP', 'Penumpang DOM ARR', 'Penumpang DOM DEP', 'Total Penumpang'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['date'] ?? '', $r['aircraft_int_arrival'] ?? 0, $r['aircraft_int_departure'] ?? 0, $r['aircraft_dom_arrival'] ?? 0, $r['aircraft_dom_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_int_arrival'] ?? 0, $r['passenger_int_departure'] ?? 0, $r['passenger_dom_arrival'] ?? 0, $r['passenger_dom_departure'] ?? 0, $r['passenger_total'] ?? 0
                    ]);
                }
            } elseif ($reportType === 'DAU12') {
                $cols = ['Tanggal', 'Pesawat ARR DOM', 'Pesawat ARR INT', 'Pesawat ARR Total', 'Pesawat DEP DOM', 'Pesawat DEP INT', 'Pesawat DEP Total', 'Total Pesawat', 'Penumpang ARR DOM', 'Penumpang ARR INT', 'Penumpang ARR Total', 'Penumpang DEP DOM', 'Penumpang DEP INT', 'Penumpang DEP Total', 'Total Penumpang'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['date'] ?? '', $r['aircraft_arr_domestic'] ?? 0, $r['aircraft_arr_int'] ?? 0, $r['aircraft_arrival_tot'] ?? 0,
                        $r['aircraft_dep_domestic'] ?? 0, $r['aircraft_dep_int'] ?? 0, $r['aircraft_departure_tot'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arr_domestic'] ?? 0, $r['passenger_arr_int'] ?? 0, $r['passenger_arrival_tot'] ?? 0,
                        $r['passenger_dep_domestic'] ?? 0, $r['passenger_dep_int'] ?? 0, $r['passenger_departure_tot'] ?? 0, $r['passenger_total'] ?? 0
                    ]);
                }
            } else {
                $cols = ['Hour', 'Terminal', 'Aircraft ARR', 'Aircraft DEP', 'Aircraft Total', 'Passenger ARR', 'Passenger DEP', 'Transit', 'Transfer', 'Passenger Total', 'Crew', 'Extra Crew', 'Total Crew', 'Baggage (Kg)', 'Cargo (Kg)', 'POS (Kg)'];
                fputcsv($handle, $cols);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['hour'] ?? $r['period'] ?? '', $r['terminal'] ?? '', $r['aircraft_arrival'] ?? 0, $r['aircraft_departure'] ?? 0, $r['aircraft_total'] ?? 0,
                        $r['passenger_arrival'] ?? 0, $r['passenger_departure'] ?? 0, $r['passenger_transit'] ?? 0, $r['passenger_transfer'] ?? 0, $r['passenger_total'] ?? 0,
                        $r['crew'] ?? 0, $r['extra_crew'] ?? 0, $r['crew_total'] ?? 0, $r['baggage'] ?? 0, $r['cargo'] ?? 0, $r['pos'] ?? 0,
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Persist airport operational settings (Arrival/Departure Capacity, Ops Hours) for DAU upload
     */
    public function saveOperationalSettings(Request $request, Upload $upload)
    {
        $validated = $request->validate([
            'arrival_capacity'   => 'nullable|integer|min:1|max:150',
            'departure_capacity' => 'nullable|integer|min:1|max:150',
            'aircraft_capacity'  => 'nullable|integer|min:1|max:150',
            'ops_start'          => 'nullable|string|max:10',
            'ops_end'            => 'nullable|string|max:10',
        ]);

        $meta = $upload->report_data['meta'] ?? [];
        $airport = $upload->airport ?: \App\Models\Airport::where('iata_code', $meta['airport_code'] ?? 'CGK')->first();

        if ($airport) {
            $updates = [];
            if (!empty($validated['arrival_capacity'])) {
                $updates['arrival_capacity'] = (int) $validated['arrival_capacity'];
            }
            if (!empty($validated['departure_capacity'])) {
                $updates['departure_capacity'] = (int) $validated['departure_capacity'];
            }
            if (!empty($validated['aircraft_capacity'])) {
                $updates['aircraft_capacity'] = (int) $validated['aircraft_capacity'];
                if (empty($updates['arrival_capacity'])) $updates['arrival_capacity'] = (int) $validated['aircraft_capacity'];
                if (empty($updates['departure_capacity'])) $updates['departure_capacity'] = (int) $validated['aircraft_capacity'];
            } elseif (!empty($updates['arrival_capacity']) || !empty($updates['departure_capacity'])) {
                $updates['aircraft_capacity'] = max(
                    $updates['arrival_capacity'] ?? $airport->arrival_capacity ?? 6,
                    $updates['departure_capacity'] ?? $airport->departure_capacity ?? 6
                );
            }
            if (!empty($validated['ops_start'])) {
                $updates['ops_start_time'] = str_contains($validated['ops_start'], ':') ? $validated['ops_start'] : sprintf('%02d:00', $validated['ops_start']);
            }
            if (!empty($validated['ops_end'])) {
                $updates['ops_end_time'] = str_contains($validated['ops_end'], ':') ? $validated['ops_end'] : sprintf('%02d:00', $validated['ops_end']);
            }
            if (!empty($updates)) {
                $airport->update($updates);
            }
        }

        return response()->json([
            'status'             => 'success',
            'success'            => true,
            'message'            => 'Airport Operational Settings updated successfully.',
            'arrival_capacity'   => $airport ? $airport->getEffectiveArrivalCapacity() : ($validated['arrival_capacity'] ?? 6),
            'departure_capacity' => $airport ? $airport->getEffectiveDepartureCapacity() : ($validated['departure_capacity'] ?? 6),
            'aircraft_capacity'  => $airport ? $airport->getEffectiveCapacity() : ($validated['aircraft_capacity'] ?? 6),
            'ops_start'          => $airport ? $airport->ops_start_time : '06:00',
            'ops_end'            => $airport ? $airport->ops_end_time : '20:00',
        ]);
    }

    /**
     * Unified filtering & analytics engine for all DAU report types (DAU-1 through DAU-12).
     * Ensures KPI, Charts, Detail Table, and PDF Export receive identical, mathematically consistent data.
     */
    public function filterReportDataset(array $records, array $filters, array $meta, string $reportType): array
    {
        $flightType = $filters['flight_type'] ?? 'ALL';
        $terminalFilter = $filters['terminal'] ?? 'ALL';
        $hourFilter = $filters['hour'] ?? 'ALL';
        $operationFilter = $filters['operation'] ?? 'ALL';
        $directionFilter = $filters['direction'] ?? 'ALL';
        $airlineFilter = $filters['airline'] ?? 'ALL';
        $airportFilter = $filters['airport'] ?? 'ALL';
        $scheduleTypeFilter = $filters['schedule_type'] ?? 'ALL';
        $statusFilter = $filters['status'] ?? 'ALL';
        $actFilter = $filters['aircraft_type'] ?? 'ALL';
        $categoryFilter = $filters['category'] ?? 'ALL';
        $searchQuery = strtolower(trim($filters['search'] ?? ''));
        $topN = $filters['top_n'] ?? 'ALL';
        $threshold = (int) ($filters['threshold'] ?? 0);
        $metric = strtolower($filters['metric'] ?? 'aircraft');

        $cleanHourFilter = $hourFilter !== 'ALL' ? preg_replace('/[^0-9]/', '', $hourFilter) : null;

        $filtered = [];

        foreach ($records as $r) {
            // 1. Terminal filter
            if ($terminalFilter !== 'ALL') {
                $recTerminal = (string)($r['terminal'] ?? '');
                if ($recTerminal !== '' && strcasecmp($recTerminal, $terminalFilter) !== 0) {
                    continue;
                }
            }

            // 2. Hour filter
            if ($hourFilter !== 'ALL') {
                $recHour = $r['hour'] ?? $r['period'] ?? '';
                if ($recHour !== '') {
                    $cleanRecHour = preg_replace('/[^0-9]/', '', $recHour);
                    if ($cleanHourFilter && $cleanRecHour !== $cleanHourFilter && strcasecmp($recHour, $hourFilter) !== 0) {
                        continue;
                    }
                }
            }

            // 3. Flight Type Filter (DOM / INT / ALL)
            if ($flightType === 'DOM' || $flightType === 'DOMESTIC') {
                if (!empty($r['category']) && stripos($r['category'], 'INT') !== false) {
                    continue;
                }
                if (in_array($reportType, ['DAU10', 'DAU10A', 'DAU10B'])) {
                    if (stripos($meta['flight_scope'] ?? '', 'INTERNASIONAL') !== false && stripos($meta['flight_scope'] ?? '', 'DOMESTIK') === false) {
                        continue;
                    }
                }
            } elseif ($flightType === 'INT' || $flightType === 'INTERNATIONAL') {
                if (!empty($r['category']) && stripos($r['category'], 'DOM') !== false) {
                    continue;
                }
                if (in_array($reportType, ['DAU10', 'DAU10A', 'DAU10B'])) {
                    if (stripos($meta['flight_scope'] ?? '', 'DOMESTIK') !== false && stripos($meta['flight_scope'] ?? '', 'INTERNASIONAL') === false) {
                        continue;
                    }
                    $recTerminal = (string)($r['terminal'] ?? '');
                    if ($recTerminal !== '' && !in_array(strtoupper($recTerminal), ['2E', '2F', '3U', 'T2E', 'T2F', 'T3U', '3'])) {
                        continue;
                    }
                }
            }

            // 4. Direction filter
            if ($directionFilter !== 'ALL') {
                if ($directionFilter === 'ARRIVAL') {
                    if (($r['aircraft_arrival'] ?? 0) === 0 && ($r['passenger_arrival'] ?? 0) === 0) {
                        continue;
                    }
                } elseif ($directionFilter === 'DEPARTURE') {
                    if (($r['aircraft_departure'] ?? 0) === 0 && ($r['passenger_departure'] ?? 0) === 0) {
                        continue;
                    }
                }
            }

            // 5. Airline filter
            if ($airlineFilter !== 'ALL') {
                $rAirline = $r['airline'] ?? $r['operator_name'] ?? $r['airline_code'] ?? '';
                if ($rAirline !== '' && strcasecmp($rAirline, $airlineFilter) !== 0 && stripos($rAirline, $airlineFilter) === false) {
                    continue;
                }
            }

            // 6. Airport / Route filter
            if ($airportFilter !== 'ALL') {
                $rAirport = $r['airport_route'] ?? $r['airport'] ?? $r['city'] ?? $r['city_code'] ?? '';
                if ($rAirport !== '' && strcasecmp($rAirport, $airportFilter) !== 0 && stripos($rAirport, $airportFilter) === false) {
                    continue;
                }
            }

            // 7. Schedule Type (Berjadwal / Tdk Berjadwal)
            if ($scheduleTypeFilter !== 'ALL' && !empty($r['schedule_type'])) {
                if (stripos($r['schedule_type'], $scheduleTypeFilter) === false) {
                    continue;
                }
            }

            // 8. Status (Niaga / Bukan Niaga)
            if ($statusFilter !== 'ALL') {
                $rStatus = $r['section'] ?? $r['status'] ?? '';
                if ($rStatus !== '' && stripos($rStatus, $statusFilter) === false) {
                    continue;
                }
            }

            // 9. Aircraft Type
            if ($actFilter !== 'ALL' && !empty($r['aircraft_type'])) {
                if (strcasecmp($r['aircraft_type'], $actFilter) !== 0) {
                    continue;
                }
            }

            // 10. Category (Narrow Body / Wide Body / Regional)
            if ($categoryFilter !== 'ALL' && !empty($r['category'])) {
                if (stripos($r['category'], $categoryFilter) === false) {
                    continue;
                }
            }

            // 11. Operation Filter for DAU10B (BLOCK ON vs BLOCK OFF)
            if ($reportType === 'DAU10B' && $operationFilter !== 'ALL') {
                if ($operationFilter === 'BLOCK_ON') {
                    if (($r['aircraft_arrival'] ?? 0) === 0 && ($r['passenger_arrival'] ?? 0) === 0) {
                        continue;
                    }
                } elseif ($operationFilter === 'BLOCK_OFF') {
                    if (($r['aircraft_departure'] ?? 0) === 0 && ($r['passenger_departure'] ?? 0) === 0) {
                        continue;
                    }
                }
            }

            // 12. Minimum Flight Threshold (for DAU4B)
            if ($threshold > 0) {
                $acCount = $r['aircraft_total'] ?? $r['total_flights'] ?? 0;
                if ($acCount < $threshold) {
                    continue;
                }
            }

            // 13. Search query
            if ($searchQuery !== '') {
                $haystack = strtolower(implode(' ', array_map(function ($v) {
                    return is_scalar($v) ? (string)$v : '';
                }, $r)));
                if (stripos($haystack, $searchQuery) === false) {
                    continue;
                }
            }

            $filtered[] = $r;
        }

        // Compute summary totals from filtered dataset
        $summary = [
            'total_movements'    => 0,
            'aircraft_total'     => 0,
            'aircraft_arrival'   => 0,
            'aircraft_departure' => 0,
            'passenger_arrival'  => 0,
            'passenger_departure'=> 0,
            'passenger_transit'  => 0,
            'passenger_transfer' => 0,
            'passenger_total'    => 0,
            'crew_total'         => 0,
            'extra_crew_total'   => 0,
            'baggage_total'      => 0,
            'cargo_total'        => 0,
            'pos_total'          => 0,
        ];

        $hourlyBuckets = [];
        $terminalBuckets = [];
        $airlineBuckets = [];
        $airportBuckets = [];

        foreach ($filtered as $r) {
            $h = $r['hour'] ?? $r['period'] ?? null;
            $t = $r['terminal'] ?? null;
            $al = $r['airline'] ?? $r['operator_name'] ?? null;
            $ap = $r['airport_route'] ?? $r['airport'] ?? $r['city'] ?? null;

            $acArr = (int)($r['aircraft_arrival'] ?? ($r['aircraft_arr_domestic'] ?? 0) + ($r['aircraft_arr_int'] ?? 0));
            $acDep = (int)($r['aircraft_departure'] ?? ($r['aircraft_dep_domestic'] ?? 0) + ($r['aircraft_dep_int'] ?? 0));
            $acTot = (int)($r['aircraft_total'] ?? ($r['total_flights'] ?? ($acArr + $acDep)));

            $pxArr = (int)($r['passenger_arrival'] ?? ($r['passenger_arr_domestic'] ?? 0) + ($r['passenger_arr_int'] ?? 0));
            $pxDep = (int)($r['passenger_departure'] ?? ($r['passenger_dep_domestic'] ?? 0) + ($r['passenger_dep_int'] ?? 0));
            $pxTrn = (int)($r['passenger_transit'] ?? 0);
            $pxTrf = (int)($r['passenger_transfer'] ?? 0);
            $pxTot = (int)($r['passenger_total'] ?? ($r['total_passengers'] ?? ($pxArr + $pxDep + $pxTrn + $pxTrf)));

            $crew  = (int)($r['crew_total'] ?? (($r['crew'] ?? 0) + ($r['extra_crew'] ?? 0)));
            $bag   = (int)($r['baggage'] ?? 0);
            $cgo   = (int)($r['cargo'] ?? 0);
            $pos   = (int)($r['pos'] ?? 0);

            $summary['total_movements']    += $acTot;
            $summary['aircraft_total']     += $acTot;
            $summary['aircraft_arrival']   += $acArr;
            $summary['aircraft_departure'] += $acDep;
            $summary['passenger_arrival']  += $pxArr;
            $summary['passenger_departure']+= $pxDep;
            $summary['passenger_transit']  += $pxTrn;
            $summary['passenger_transfer'] += $pxTrf;
            $summary['passenger_total']    += $pxTot;
            $summary['crew_total']         += $crew;
            $summary['extra_crew_total']   += (int)($r['extra_crew'] ?? 0);
            $summary['baggage_total']      += $bag;
            $summary['cargo_total']        += $cgo;
            $summary['pos_total']          += $pos;

            // Hourly grouping
            if ($h !== null) {
                if (!isset($hourlyBuckets[$h])) {
                    $hourlyBuckets[$h] = [
                        'hour'                => $h,
                        'aircraft_arrival'    => 0,
                        'aircraft_departure'  => 0,
                        'aircraft_total'      => 0,
                        'passenger_arrival'   => 0,
                        'passenger_departure' => 0,
                        'passenger_total'     => 0,
                        'crew_total'          => 0,
                        'baggage'             => 0,
                        'cargo'               => 0,
                        'pos'                 => 0,
                    ];
                }
                $hourlyBuckets[$h]['aircraft_arrival']    += $acArr;
                $hourlyBuckets[$h]['aircraft_departure']  += $acDep;
                $hourlyBuckets[$h]['aircraft_total']      += $acTot;
                $hourlyBuckets[$h]['passenger_arrival']   += $pxArr;
                $hourlyBuckets[$h]['passenger_departure'] += $pxDep;
                $hourlyBuckets[$h]['passenger_total']     += $pxTot;
                $hourlyBuckets[$h]['crew_total']          += $crew;
                $hourlyBuckets[$h]['baggage']             += $bag;
                $hourlyBuckets[$h]['cargo']               += $cgo;
                $hourlyBuckets[$h]['pos']                 += $pos;
            }

            // Terminal grouping
            if ($t !== null) {
                if (!isset($terminalBuckets[$t])) {
                    $terminalBuckets[$t] = [
                        'terminal'            => $t,
                        'aircraft_arrival'    => 0,
                        'aircraft_departure'  => 0,
                        'aircraft_total'      => 0,
                        'passenger_arrival'   => 0,
                        'passenger_departure' => 0,
                        'passenger_total'     => 0,
                        'crew_total'          => 0,
                        'baggage'             => 0,
                        'cargo'               => 0,
                        'pos'                 => 0,
                    ];
                }
                $terminalBuckets[$t]['aircraft_arrival']    += $acArr;
                $terminalBuckets[$t]['aircraft_departure']  += $acDep;
                $terminalBuckets[$t]['aircraft_total']      += $acTot;
                $terminalBuckets[$t]['passenger_arrival']   += $pxArr;
                $terminalBuckets[$t]['passenger_departure'] += $pxDep;
                $terminalBuckets[$t]['passenger_total']     += $pxTot;
                $terminalBuckets[$t]['crew_total']          += $crew;
                $terminalBuckets[$t]['baggage']             += $bag;
                $terminalBuckets[$t]['cargo']               += $cgo;
                $terminalBuckets[$t]['pos']                 += $pos;
            }

            // Airline grouping
            if ($al !== null) {
                if (!isset($airlineBuckets[$al])) {
                    $airlineBuckets[$al] = [
                        'airline'             => $al,
                        'aircraft_arrival'    => 0,
                        'aircraft_departure'  => 0,
                        'aircraft_total'      => 0,
                        'passenger_arrival'   => 0,
                        'passenger_departure' => 0,
                        'passenger_total'     => 0,
                        'crew_total'          => 0,
                        'operating_crew'      => 0,
                        'arr_extra_crew'      => 0,
                        'dep_extra_crew'      => 0,
                        'extra_crew'          => 0,
                        'baggage'             => 0,
                        'cargo'               => 0,
                        'pos'                 => 0,
                    ];
                }
                $airlineBuckets[$al]['aircraft_arrival']    += $acArr;
                $airlineBuckets[$al]['aircraft_departure']  += $acDep;
                $airlineBuckets[$al]['aircraft_total']      += $acTot;
                $airlineBuckets[$al]['passenger_arrival']   += $pxArr;
                $airlineBuckets[$al]['passenger_departure'] += $pxDep;
                $airlineBuckets[$al]['passenger_total']     += $pxTot;
                $airlineBuckets[$al]['crew_total']          += $crew;
                $airlineBuckets[$al]['operating_crew']      += (int)($r['crew'] ?? 0);
                $airlineBuckets[$al]['arr_extra_crew']      += (int)($r['arr_extra_crew'] ?? 0);
                $airlineBuckets[$al]['dep_extra_crew']      += (int)($r['dep_extra_crew'] ?? 0);
                $airlineBuckets[$al]['extra_crew']          += (int)($r['extra_crew'] ?? 0);
                $airlineBuckets[$al]['baggage']             += $bag;
                $airlineBuckets[$al]['cargo']               += $cgo;
                $airlineBuckets[$al]['pos']                 += $pos;
            }

            // Airport / Route grouping
            if ($ap !== null) {
                if (!isset($airportBuckets[$ap])) {
                    $airportBuckets[$ap] = [
                        'airport'             => $ap,
                        'city_code'           => $r['city_code'] ?? '',
                        'city'                => $r['city'] ?? $ap,
                        'aircraft_arrival'    => 0,
                        'aircraft_departure'  => 0,
                        'aircraft_total'      => 0,
                        'passenger_arrival'   => 0,
                        'passenger_departure' => 0,
                        'passenger_total'     => 0,
                    ];
                }
                $airportBuckets[$ap]['aircraft_arrival']    += $acArr;
                $airportBuckets[$ap]['aircraft_departure']  += $acDep;
                $airportBuckets[$ap]['aircraft_total']      += $acTot;
                $airportBuckets[$ap]['passenger_arrival']   += $pxArr;
                $airportBuckets[$ap]['passenger_departure'] += $pxDep;
                $airportBuckets[$ap]['passenger_total']     += $pxTot;
            }
        }

        // Peak calculations
        $peakAcHour = '—';
        $peakAcVal = 0;
        $peakPaxHour = '—';
        $peakPaxVal = 0;
        $peakBlockOnHour = '—';
        $peakBlockOnVal = 0;
        $peakBlockOffHour = '—';
        $peakBlockOffVal = 0;

        foreach ($hourlyBuckets as $h => $hb) {
            if ($hb['aircraft_total'] > $peakAcVal) {
                $peakAcVal = $hb['aircraft_total'];
                $peakAcHour = $h;
            }
            if ($hb['passenger_total'] > $peakPaxVal) {
                $peakPaxVal = $hb['passenger_total'];
                $peakPaxHour = $h;
            }
            if ($hb['aircraft_arrival'] > $peakBlockOnVal) {
                $peakBlockOnVal = $hb['aircraft_arrival'];
                $peakBlockOnHour = $h;
            }
            if ($hb['aircraft_departure'] > $peakBlockOffVal) {
                $peakBlockOffVal = $hb['aircraft_departure'];
                $peakBlockOffHour = $h;
            }
        }

        $peakTerm = '—';
        $peakTermVal = 0;
        foreach ($terminalBuckets as $t => $tb) {
            $val = $metric === 'passenger' ? $tb['passenger_total'] : $tb['aircraft_total'];
            if ($val > $peakTermVal) {
                $peakTermVal = $val;
                $peakTerm = $t;
            }
        }

        $peaks = [
            'peak_aircraft_hour' => $peakAcHour,
            'peak_aircraft'      => $peakAcVal,
            'peak_passenger_hour'=> $peakPaxHour,
            'peak_passenger'     => $peakPaxVal,
            'peak_hour'          => ($metric === 'passenger' ? $peakPaxHour : $peakAcHour),
            'peak_terminal'      => $peakTerm,
            'peak_terminal_val'  => $peakTermVal,
            'peak_block_on_hour' => $peakBlockOnHour,
            'peak_block_on'      => $peakBlockOnVal,
            'peak_block_off_hour'=> $peakBlockOffHour,
            'peak_block_off'     => $peakBlockOffVal,
        ];

        $hourlyDistribution = array_values($hourlyBuckets);
        $terminalComparison = array_values($terminalBuckets);

        // DAU1 Combo Chart: Top 10 Routes (Grouped ARR/DEP bars + Line Total Pax)
        uasort($airportBuckets, function ($a, $b) use ($metric) {
            $valA = $metric === 'passenger' ? $a['passenger_total'] : $a['aircraft_total'];
            $valB = $metric === 'passenger' ? $b['passenger_total'] : $b['aircraft_total'];
            return $valB <=> $valA;
        });
        $topRoutes = array_slice(array_values($airportBuckets), 0, 10);

        // DAU2: Dom vs Int Stacked Data
        $dau2Distribution = [
            'domestic' => [
                'aircraft'  => 0,
                'passenger' => 0,
                'baggage'   => 0,
                'cargo'     => 0,
            ],
            'international' => [
                'aircraft'  => 0,
                'passenger' => 0,
                'baggage'   => 0,
                'cargo'     => 0,
            ],
        ];
        foreach ($filtered as $r) {
            $isDom = stripos($r['category'] ?? '', 'DOM') !== false;
            $k = $isDom ? 'domestic' : 'international';
            $dau2Distribution[$k]['aircraft']  += (int)($r['aircraft_total'] ?? 0);
            $dau2Distribution[$k]['passenger'] += (int)($r['passenger_total'] ?? 0);
            $dau2Distribution[$k]['baggage']   += (int)($r['baggage'] ?? 0);
            $dau2Distribution[$k]['cargo']     += (int)($r['cargo'] ?? 0);
        }

        // DAU3: Niaga vs Bukan Niaga Breakdown
        $dau3Status = [
            'niaga' => ['aircraft' => 0, 'passenger' => 0],
            'bukan_niaga' => ['aircraft' => 0, 'passenger' => 0],
            'domestik' => ['aircraft' => 0, 'passenger' => 0],
            'internasional' => ['aircraft' => 0, 'passenger' => 0],
        ];
        foreach ($filtered as $r) {
            $sec = strtoupper($r['section'] ?? '');
            if (stripos($sec, 'BUKAN') !== false) {
                $dau3Status['bukan_niaga']['aircraft']  += (int)($r['aircraft_total'] ?? 0);
                $dau3Status['bukan_niaga']['passenger'] += (int)($r['passenger_total'] ?? 0);
            } else {
                $dau3Status['niaga']['aircraft']  += (int)($r['aircraft_total'] ?? 0);
                $dau3Status['niaga']['passenger'] += (int)($r['passenger_total'] ?? 0);
            }
            if (stripos($r['category'] ?? '', 'DOM') !== false) {
                $dau3Status['domestik']['aircraft']  += (int)($r['aircraft_total'] ?? 0);
                $dau3Status['domestik']['passenger'] += (int)($r['passenger_total'] ?? 0);
            } else {
                $dau3Status['internasional']['aircraft']  += (int)($r['aircraft_total'] ?? 0);
                $dau3Status['internasional']['passenger'] += (int)($r['passenger_total'] ?? 0);
            }
        }

        // DAU4: Diverging Origin (ARR) vs Destination (DEP)
        $limitN = is_numeric($topN) ? (int)$topN : 10;
        $arrSorted = $airportBuckets;
        uasort($arrSorted, function ($a, $b) use ($metric) {
            $valA = $metric === 'passenger' ? $a['passenger_arrival'] : $a['aircraft_arrival'];
            $valB = $metric === 'passenger' ? $b['passenger_arrival'] : $b['aircraft_arrival'];
            return $valB <=> $valA;
        });
        $depSorted = $airportBuckets;
        uasort($depSorted, function ($a, $b) use ($metric) {
            $valA = $metric === 'passenger' ? $a['passenger_departure'] : $a['aircraft_departure'];
            $valB = $metric === 'passenger' ? $b['passenger_departure'] : $b['aircraft_departure'];
            return $valB <=> $valA;
        });
        $dau4Diverging = [
            'top_arrival'   => array_slice(array_values($arrSorted), 0, $limitN),
            'top_departure' => array_slice(array_values($depSorted), 0, $limitN),
        ];

        // DAU5: Pareto Airlines
        $airlinesRanked = array_values($airlineBuckets);
        uasort($airlinesRanked, function ($a, $b) use ($metric) {
            $key = match ($metric) {
                'passenger' => 'passenger_total',
                'baggage'   => 'baggage',
                'cargo'     => 'cargo',
                'pos'       => 'pos',
                default     => 'aircraft_total',
            };
            return ($b[$key] ?? 0) <=> ($a[$key] ?? 0);
        });
        $airlinesRanked = array_values($airlinesRanked);

        $totRankVal = 0;
        $rankKey = match ($metric) {
            'passenger' => 'passenger_total',
            'baggage'   => 'baggage',
            'cargo'     => 'cargo',
            'pos'       => 'pos',
            default     => 'aircraft_total',
        };
        foreach ($airlinesRanked as $alItem) {
            $totRankVal += ($alItem[$rankKey] ?? 0);
        }

        $cum = 0;
        $dau5Pareto = [];
        foreach ($airlinesRanked as $alItem) {
            $val = $alItem[$rankKey] ?? 0;
            $cum += $val;
            $cumPct = $totRankVal > 0 ? round(($cum / $totRankVal) * 100, 1) : 0;
            $dau5Pareto[] = array_merge($alItem, [
                'metric_value'   => $val,
                'cumulative_pct' => $cumPct,
            ]);
        }

        // DAU5A: Operating Crew vs Extra Crew
        $dau5aCrew = [];
        foreach ($airlineBuckets as $alName => $alData) {
            $dau5aCrew[] = [
                'airline'        => $alName,
                'operating_crew' => $alData['operating_crew'] ?? 0,
                'arr_extra_crew' => $alData['arr_extra_crew'] ?? 0,
                'dep_extra_crew' => $alData['dep_extra_crew'] ?? 0,
                'extra_crew'     => $alData['extra_crew'] ?? 0,
                'crew_total'     => $alData['crew_total'] ?? 0,
            ];
        }
        uasort($dau5aCrew, fn($a, $b) => $b['crew_total'] <=> $a['crew_total']);
        $dau5aCrew = array_slice(array_values($dau5aCrew), 0, 15);

        // DAU5B: Terminal x Airline matrix
        $dau5bTerminals = [];
        foreach ($filtered as $r) {
            $term = $r['terminal'] ?? 'Unknown';
            $al   = $r['airline'] ?? 'Unknown';
            if (!isset($dau5bTerminals[$term])) $dau5bTerminals[$term] = [];
            if (!isset($dau5bTerminals[$term][$al])) $dau5bTerminals[$term][$al] = 0;
            $dau5bTerminals[$term][$al] += ($metric === 'passenger' ? ($r['passenger_total'] ?? 0) : ($r['aircraft_total'] ?? 0));
        }

        // DAU6: Fleet Mix ranking & Category share
        $fleetBuckets = [];
        $categoryShare = [];
        $wtcShare = [];
        foreach ($filtered as $r) {
            $type = $r['aircraft_type'] ?? 'Unknown';
            $cat  = $r['category'] ?? 'Narrow Body';
            $wtc  = $r['wtc'] ?? 'Medium';
            $val  = $metric === 'passenger' ? ($r['passenger_total'] ?? 0) : ($r['aircraft_total'] ?? 0);

            if (!isset($fleetBuckets[$type])) {
                $fleetBuckets[$type] = [
                    'aircraft_type'   => $type,
                    'category'        => $cat,
                    'wtc'             => $wtc,
                    'aircraft_total'  => 0,
                    'passenger_total' => 0,
                ];
            }
            $fleetBuckets[$type]['aircraft_total']  += (int)($r['aircraft_total'] ?? 0);
            $fleetBuckets[$type]['passenger_total'] += (int)($r['passenger_total'] ?? 0);

            $categoryShare[$cat] = ($categoryShare[$cat] ?? 0) + $val;
            $wtcShare[$wtc]      = ($wtcShare[$wtc] ?? 0) + $val;
        }
        uasort($fleetBuckets, function ($a, $b) use ($metric) {
            $key = $metric === 'passenger' ? 'passenger_total' : 'aircraft_total';
            return $b[$key] <=> $a[$key];
        });
        $dau6Fleet = [
            'types'          => array_slice(array_values($fleetBuckets), 0, 15),
            'category_share' => $categoryShare,
            'wtc_share'      => $wtcShare,
        ];

        // DAU10A Heatmap Matrix
        $heatmapMatrix = [];
        if ($reportType === 'DAU10A') {
            $allTerminals = ['1', '2F', '3U', '1B', '2D', '2E', '1C'];
            foreach ($terminalBuckets as $tKey => $_) {
                if (!in_array($tKey, $allTerminals)) $allTerminals[] = $tKey;
            }
            foreach ($allTerminals as $term) {
                $heatmapMatrix[$term] = [];
                foreach ($hourlyDistribution as $hItem) {
                    $hKey = $hItem['hour'];
                    $val = 0;
                    foreach ($filtered as $r) {
                        if (($r['terminal'] ?? '') === $term && (($r['hour'] ?? $r['period'] ?? '') === $hKey)) {
                            if ($metric === 'passenger') {
                                $val = (int)($r['passenger_total'] ?? 0);
                            } elseif ($metric === 'crew') {
                                $val = (int)($r['crew_total'] ?? 0);
                            } else {
                                $val = (int)($r['aircraft_total'] ?? 0);
                            }
                            break;
                        }
                    }
                    $heatmapMatrix[$term][$hKey] = $val;
                }
            }
        }

        // DAU4B Heatmap Matrix
        $dau4bMatrix = [];
        if ($reportType === 'DAU4B') {
            $topCities = array_slice(array_keys($airportBuckets), 0, 20);
            $topAirlines = array_slice(array_keys($airlineBuckets), 0, 15);
            $matrixGrid = [];
            foreach ($topCities as $cKey) {
                $matrixGrid[$cKey] = [];
                foreach ($topAirlines as $aKey) {
                    $mVal = 0;
                    foreach ($filtered as $r) {
                        $matchCity = (($r['city'] ?? $r['airport_route'] ?? '') === $cKey);
                        $matchAir  = (($r['airline'] ?? $r['operator_name'] ?? $r['airline_code'] ?? '') === $aKey);
                        if ($matchCity && $matchAir) {
                            $mVal += ($metric === 'passenger' ? ($r['passenger_total'] ?? 0) : ($r['aircraft_total'] ?? 0));
                        }
                    }
                    $matrixGrid[$cKey][$aKey] = $mVal;
                }
            }
            $dau4bMatrix = [
                'cities'   => $topCities,
                'airlines' => $topAirlines,
                'grid'     => $matrixGrid,
            ];
        }

        // DAU11: Traffic flow breakdown
        $dau11Flow = [
            'dom_arr' => 0, 'dom_dep' => 0, 'dom_transit' => 0, 'dom_transfer' => 0,
            'int_arr' => 0, 'int_dep' => 0, 'int_transit' => 0, 'int_transfer' => 0,
        ];
        foreach ($filtered as $r) {
            $dau11Flow['dom_arr']     += (int)($r['passenger_dom_arrival'] ?? 0);
            $dau11Flow['dom_dep']     += (int)($r['passenger_dom_departure'] ?? 0);
            $dau11Flow['dom_transit'] += (int)($r['passenger_dom_transit'] ?? 0);
            $dau11Flow['dom_transfer']+= (int)($r['passenger_dom_transfer'] ?? 0);
            $dau11Flow['int_arr']     += (int)($r['passenger_int_arrival'] ?? 0);
            $dau11Flow['int_dep']     += (int)($r['passenger_int_departure'] ?? 0);
            $dau11Flow['int_transit'] += (int)($r['passenger_int_transit'] ?? 0);
            $dau11Flow['int_transfer']+= (int)($r['passenger_int_transfer'] ?? 0);
        }

        // DAU12: Grouped columns (Arrival Dom/Int vs Departure Dom/Int)
        $dau12Matrix = [
            'aircraft' => [
                'arr_dom' => 0, 'arr_int' => 0,
                'dep_dom' => 0, 'dep_int' => 0,
            ],
            'passenger' => [
                'arr_dom' => 0, 'arr_int' => 0,
                'dep_dom' => 0, 'dep_int' => 0,
            ],
        ];
        foreach ($filtered as $r) {
            $dau12Matrix['aircraft']['arr_dom'] += (int)($r['aircraft_arr_domestic'] ?? 0);
            $dau12Matrix['aircraft']['arr_int'] += (int)($r['aircraft_arr_int'] ?? 0);
            $dau12Matrix['aircraft']['dep_dom'] += (int)($r['aircraft_dep_domestic'] ?? 0);
            $dau12Matrix['aircraft']['dep_int'] += (int)($r['aircraft_dep_int'] ?? 0);

            $dau12Matrix['passenger']['arr_dom'] += (int)($r['passenger_arr_domestic'] ?? 0);
            $dau12Matrix['passenger']['arr_int'] += (int)($r['passenger_arr_int'] ?? 0);
            $dau12Matrix['passenger']['dep_dom'] += (int)($r['passenger_dep_domestic'] ?? 0);
            $dau12Matrix['passenger']['dep_int'] += (int)($r['passenger_dep_int'] ?? 0);
        }

        return [
            'filtered_records'    => $filtered,
            'summary'             => $summary,
            'peaks'               => $peaks,
            'hourly_distribution' => $hourlyDistribution,
            'terminal_comparison' => $terminalComparison,
            'heatmap_matrix'      => $heatmapMatrix,
            'dau1_routes'         => $topRoutes,
            'dau2_distribution'   => $dau2Distribution,
            'dau3_status'         => $dau3Status,
            'dau4_diverging'      => $dau4Diverging,
            'dau4b_matrix'        => $dau4bMatrix,
            'dau5_pareto'         => $dau5Pareto,
            'dau5a_crew'          => $dau5aCrew,
            'dau5b_terminals'     => $dau5bTerminals,
            'dau6_fleet'          => $dau6Fleet,
            'dau11_flow'          => $dau11Flow,
            'dau12_matrix'        => $dau12Matrix,
        ];
    }
}
