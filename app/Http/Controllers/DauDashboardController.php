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

        // For DAU10A, prefer normalized_pairs so each row has hour, terminal, and all movement columns
        if ($reportType === 'DAU10A' && !empty($data['normalized_pairs'])) {
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

        // Precompute initial full analytics
        $analytics = $this->filterReportDataset($records, [
            'flight_type' => 'ALL',
            'terminal'    => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'aircraft',
            'operation'   => 'ALL',
        ], $meta, $reportType);

        $summary = $analytics['summary'];
        $peaks = $analytics['peaks'];
        $hourlyDistribution = $analytics['hourly_distribution'];
        $terminalComparison = $analytics['terminal_comparison'];
        $columns = $data['columns'] ?? [];
        $matrixRecords = $data['records'] ?? [];

        // Airport capacity, operational hours and timezone configuration
        $initialNac = (int) config('slotwaves.nac', 6);
        $airport = null;
        try {
            $airport = $upload->airport ?: \App\Models\Airport::where('iata_code', $meta['airport_code'] ?? 'CGK')->first();
            if ($airport) {
                $initialNac = (int) $airport->getEffectiveCapacity();
            }
        } catch (\Throwable $e) {}

        $opsStartTime = $airport?->ops_start_time ?? '00:00';
        $opsEndTime   = $airport?->ops_end_time ?? '24:00';
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
            'peaks',
            'hourlyDistribution',
            'terminalComparison',
            'matrixRecords',
            'initialNac',
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

        if ($reportType === 'DAU10A' && !empty($data['normalized_pairs'])) {
            $baseRecords = $data['normalized_pairs'];
        } else {
            $baseRecords = $data['records'] ?? [];
        }

        // Read active filters from request
        $filters = [
            'flight_type' => strtoupper(trim($request->query('flight_type', 'ALL'))),
            'terminal'    => trim($request->query('terminal', 'ALL')),
            'hour'        => trim($request->query('hour', 'ALL')),
            'metric'      => strtolower(trim($request->query('metric', 'aircraft'))),
            'operation'   => strtoupper(trim($request->query('operation', 'ALL'))),
            'start_date'  => trim($request->query('start_date', '')),
            'end_date'    => trim($request->query('end_date', '')),
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

        // Generate dynamic PDF filename: e.g. DAU-10_CGK_2026-08-01.pdf or DAU-10A_CGK_2026-08-01_DOM_T2F.pdf
        $code = str_replace('-', '', $conf['code'] ?? $reportType);
        // Standardize code presentation: DAU-10, DAU-10A, DAU-10B
        if (preg_match('/DAU([0-9]+)([A-Z]?)/i', $code, $cm)) {
            $codeFormat = 'DAU-' . $cm[1] . ($cm[2] ?? '');
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

        // Resolve Aircraft Capacity (NAC) for report
        $reqNac = $request->query('nac');
        if ($reqNac !== null && is_numeric($reqNac) && (int)$reqNac > 0) {
            $nac = (int) $reqNac;
        } else {
            $nac = (int) config('slotwaves.nac', 6);
            try {
                $airport = $upload->airport ?: \App\Models\Airport::where('iata_code', $meta['airport_code'] ?? 'CGK')->first();
                if ($airport) {
                    $nac = (int) $airport->getEffectiveCapacity();
                }
            } catch (\Throwable $e) {}
        }

        // Compute DAU-10A Capacity Status & Summary
        $capacitySummary = [
            'nac'                  => $nac,
            'peak_aircraft'       => $peaks['peak_aircraft'],
            'peak_hour'           => $peaks['peak_aircraft_hour'],
            'available_hours'     => 0,
            'full_hours'          => 0,
            'over_capacity_hours' => 0,
        ];

        $hourlyCapacityStatus = [];
        if ($reportType === 'DAU10A') {
            foreach ($hourlyData as $hd) {
                $arr = (int)($hd['aircraft_arrival'] ?? 0);
                $dep = (int)($hd['aircraft_departure'] ?? 0);
                $demand = $arr + $dep;
                $util = $nac > 0 ? round(($demand / $nac) * 100) : 0;

                $status = 'AVAILABLE';
                if ($demand > $nac) {
                    $status = 'OVER CAPACITY';
                    $capacitySummary['over_capacity_hours']++;
                } elseif ($demand === $nac) {
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
                    'opc'         => 'N/A',
                    'demand'      => $demand,
                    'nac'         => $nac,
                    'utilization' => $util,
                    'status'      => $status,
                ];
            }
        }

        $pdf = Pdf::loadView('dau.pdf', [
            'upload'               => $upload,
            'reportType'           => $reportType,
            'conf'                 => $conf,
            'data'                 => $data,
            'meta'                 => $meta,
            'summary'              => $summary,
            'records'              => $filteredRecords,
            'peaks'                => $peaks,
            'hourlyData'           => $hourlyData,
            'terminalData'         => $terminalData,
            'heatmapMatrix'        => $heatmapMatrix,
            'filters'              => $filters,
            'metric'               => $filters['metric'],
            'nac'                  => $nac,
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

        if ($reportType === 'DAU10A' && !empty($data['normalized_pairs'])) {
            $baseRecords = $data['normalized_pairs'];
        } else {
            $baseRecords = $data['records'] ?? [];
        }

        $filters = [
            'flight_type' => strtoupper(trim($request->query('flight_type', 'ALL'))),
            'terminal'    => trim($request->query('terminal', 'ALL')),
            'hour'        => trim($request->query('hour', 'ALL')),
            'metric'      => strtolower(trim($request->query('metric', 'aircraft'))),
            'operation'   => strtoupper(trim($request->query('operation', 'ALL'))),
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

            // Specific columns for DAU10 / DAU10A / DAU10B
            if ($reportType === 'DAU10B') {
                $cols = [
                    'Hour', 'Terminal', 'Block On Acft (DTG)', 'Block Off Acft (BRK)', 'Total Acft',
                    'Block On Pax (DTG)', 'Block Off Pax (BRK)', 'Transit Pax', 'Transfer Pax', 'Total Pax',
                    'Crew', 'Extra Crew', 'Total Crew', 'Baggage (Kg)', 'Cargo (Kg)', 'POS (Kg)'
                ];
            } else {
                $cols = [
                    'Hour', 'Terminal', 'Aircraft ARR', 'Aircraft DEP', 'Aircraft Total',
                    'Passenger ARR', 'Passenger DEP', 'Transit', 'Transfer', 'Passenger Total',
                    'Crew', 'Extra Crew', 'Total Crew', 'Baggage (Kg)', 'Cargo (Kg)', 'POS (Kg)'
                ];
            }
            fputcsv($handle, $cols);

            foreach ($records as $r) {
                fputcsv($handle, [
                    $r['hour'] ?? $r['period'] ?? '',
                    $r['terminal'] ?? '',
                    $r['aircraft_arrival'] ?? 0,
                    $r['aircraft_departure'] ?? 0,
                    $r['aircraft_total'] ?? 0,
                    $r['passenger_arrival'] ?? 0,
                    $r['passenger_departure'] ?? 0,
                    $r['passenger_transit'] ?? 0,
                    $r['passenger_transfer'] ?? 0,
                    $r['passenger_total'] ?? 0,
                    $r['crew'] ?? 0,
                    $r['extra_crew'] ?? 0,
                    $r['crew_total'] ?? 0,
                    $r['baggage'] ?? 0,
                    $r['cargo'] ?? 0,
                    $r['pos'] ?? 0,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Unified filtering & analytics engine for DAU-10, DAU-10A, and DAU-10B.
     * Ensures KPI, Charts, Detail Table, and PDF Export receive identical, mathematically consistent data.
     */
    public function filterReportDataset(array $records, array $filters, array $meta, string $reportType): array
    {
        $flightType = $filters['flight_type'] ?? 'ALL';
        $terminalFilter = $filters['terminal'] ?? 'ALL';
        $hourFilter = $filters['hour'] ?? 'ALL';
        $operationFilter = $filters['operation'] ?? 'ALL';
        $metric = strtolower($filters['metric'] ?? 'aircraft');

        // Clean hour filter for loose matching
        $cleanHourFilter = $hourFilter !== 'ALL' ? preg_replace('/[^0-9]/', '', $hourFilter) : null;

        $filtered = [];

        foreach ($records as $r) {
            $recHour = $r['hour'] ?? $r['period'] ?? '';
            $recTerminal = (string)($r['terminal'] ?? '');

            // 1. Terminal Filter
            if ($terminalFilter !== 'ALL' && strcasecmp($recTerminal, $terminalFilter) !== 0) {
                continue;
            }

            // 2. Hour Filter
            if ($hourFilter !== 'ALL') {
                $cleanRecHour = preg_replace('/[^0-9]/', '', $recHour);
                if ($cleanHourFilter && $cleanRecHour !== $cleanHourFilter && strcasecmp($recHour, $hourFilter) !== 0) {
                    continue;
                }
            }

            // 3. Flight Type Filter (DOM / INT / ALL)
            // In CGK: Terminals 1, 1B, 1C, 2D are domestic. Terminals 2E, 2F, 3U support domestic & international.
            if ($flightType === 'DOM' || $flightType === 'DOMESTIC') {
                if (stripos($meta['flight_scope'] ?? '', 'INTERNASIONAL') !== false && stripos($meta['flight_scope'] ?? '', 'DOMESTIK') === false) {
                    // Uploaded file is strictly international, no domestic records exist
                    continue;
                }
                // All terminals in CGK accommodate domestic flights
            } elseif ($flightType === 'INT' || $flightType === 'INTERNATIONAL') {
                if (stripos($meta['flight_scope'] ?? '', 'DOMESTIK') !== false && stripos($meta['flight_scope'] ?? '', 'INTERNASIONAL') === false) {
                    // Uploaded file is strictly domestic, no international records exist
                    continue;
                }
                // In CGK, only 2E, 2F, and 3U accommodate international traffic
                if (!in_array(strtoupper($recTerminal), ['2E', '2F', '3U', 'T2E', 'T2F', 'T3U', '3'])) {
                    continue;
                }
            }

            // 4. Operation Filter for DAU10B (BLOCK ON vs BLOCK OFF)
            if ($reportType === 'DAU10B' && $operationFilter !== 'ALL') {
                if ($operationFilter === 'BLOCK_ON') {
                    if (($r['aircraft_arrival'] ?? 0) === 0 && ($r['passenger_arrival'] ?? 0) === 0) {
                        // Skip records that have no block on traffic when strictly filtering by block on
                        continue;
                    }
                } elseif ($operationFilter === 'BLOCK_OFF') {
                    if (($r['aircraft_departure'] ?? 0) === 0 && ($r['passenger_departure'] ?? 0) === 0) {
                        // Skip records that have no block off traffic when strictly filtering by block off
                        continue;
                    }
                }
            }

            $filtered[] = $r;
        }

        // Compute summary totals from filtered dataset
        $summary = [
            'total_movements'    => 0,
            'aircraft_arrival'   => 0,
            'aircraft_departure' => 0,
            'passenger_arrival'  => 0,
            'passenger_departure'=> 0,
            'passenger_transit'  => 0,
            'passenger_transfer' => 0,
            'passenger_total'    => 0,
            'crew_total'         => 0,
            'baggage_total'      => 0,
            'cargo_total'        => 0,
            'pos_total'          => 0,
        ];

        $hourlyBuckets = [];
        $terminalBuckets = [];

        foreach ($filtered as $r) {
            $h = $r['hour'] ?? $r['period'] ?? 'N/A';
            $t = $r['terminal'] ?? 'ALL';

            $acArr = (int)($r['aircraft_arrival'] ?? 0);
            $acDep = (int)($r['aircraft_departure'] ?? 0);
            $acTot = (int)($r['aircraft_total'] ?? ($acArr + $acDep));

            $pxArr = (int)($r['passenger_arrival'] ?? 0);
            $pxDep = (int)($r['passenger_departure'] ?? 0);
            $pxTrn = (int)($r['passenger_transit'] ?? 0);
            $pxTrf = (int)($r['passenger_transfer'] ?? 0);
            $pxTot = (int)($r['passenger_total'] ?? ($pxArr + $pxDep + $pxTrn + $pxTrf));

            $crew  = (int)($r['crew_total'] ?? (($r['crew'] ?? 0) + ($r['extra_crew'] ?? 0)));
            $bag   = (int)($r['baggage'] ?? 0);
            $cgo   = (int)($r['cargo'] ?? 0);
            $pos   = (int)($r['pos'] ?? 0);

            $summary['total_movements']    += $acTot;
            $summary['aircraft_arrival']   += $acArr;
            $summary['aircraft_departure'] += $acDep;
            $summary['passenger_arrival']  += $pxArr;
            $summary['passenger_departure']+= $pxDep;
            $summary['passenger_transit']  += $pxTrn;
            $summary['passenger_transfer'] += $pxTrf;
            $summary['passenger_total']    += $pxTot;
            $summary['crew_total']         += $crew;
            $summary['baggage_total']      += $bag;
            $summary['cargo_total']        += $cgo;
            $summary['pos_total']          += $pos;

            // Hourly grouping
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

            // Terminal grouping
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

        // Format ordered hourly distribution array
        $hourlyDistribution = array_values($hourlyBuckets);

        // Terminal comparison array
        $terminalComparison = array_values($terminalBuckets);

        // For DAU10A: build heatmap matrix grid
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
                    // Find record matching term and hour
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

        return [
            'filtered_records'    => $filtered,
            'summary'             => $summary,
            'peaks'               => $peaks,
            'hourly_distribution' => $hourlyDistribution,
            'terminal_comparison' => $terminalComparison,
            'heatmap_matrix'      => $heatmapMatrix,
        ];
    }
}
