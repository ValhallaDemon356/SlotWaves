<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportValidator;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;
use App\Services\FlightDailyReport\HourlyChartService;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportPdfExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;

class FlightDailyReportController extends Controller
{
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportValidator $validator;
    protected FlightDailyReportAnalytics $analytics;
    protected HourlyChartService $hourlyChartService;
    protected FlightDailyReportFilter $filterService;
    protected FlightDailyReportPdfExport $pdfExport;

    public function __construct(
        FlightDailyReportParser $parser,
        FlightDailyReportValidator $validator,
        FlightDailyReportAnalytics $analytics,
        HourlyChartService $hourlyChartService,
        FlightDailyReportFilter $filterService,
        FlightDailyReportPdfExport $pdfExport
    ) {
        $this->parser = $parser;
        $this->validator = $validator;
        $this->analytics = $analytics;
        $this->hourlyChartService = $hourlyChartService;
        $this->filterService = $filterService;
        $this->pdfExport = $pdfExport;
    }

    /**
     * Redirect to FDR config page.
     */
    public function configRedirect()
    {
        return redirect()->route('fdr.config');
    }

    /**
     * Dedicated FDR Configuration Form Page.
     * Landing Page Flow: Home → Select Type Data to Generate → [ Flight Daily Report ] → FDR Config Form → FDR Dashboard → Flight Details → Export.
     */
    public function config(Request $request, Upload $upload = null)
    {
        // If an upload ID was provided or in session
        if (!$upload || $upload->report_type !== 'fdr') {
            $activeUploadId = session('fdr_active_upload_id');
            if ($activeUploadId) {
                $upload = Upload::where('id', $activeUploadId)
                    ->where('report_type', 'fdr')
                    ->where('status', 'completed')
                    ->first();
            }
        }

        // If still no upload exists, check if we have any completed FDR upload in database
        if (!$upload) {
            $upload = Upload::where('report_type', 'fdr')
                ->where('status', 'completed')
                ->latest()
                ->first();
        }

        // If no upload exists in DB at all, auto-initialize from the standard OASYS FDR reference template
        if (!$upload) {
            $templatePath = $this->resolveReferenceTemplatePath();
            if ($templatePath && file_exists($templatePath)) {
                $parsed = $this->parser->parse($templatePath);
                $upload = Upload::create([
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
                session(['fdr_active_upload_id' => $upload->id]);
            }
        }

        $meta = $upload ? ($upload->report_data['meta'] ?? []) : [];
        $records = $upload ? ($upload->report_data['records'] ?? []) : [];

        // Extract available airlines & airports from current dataset or system
        $availableAirlines = [];
        $availableAirports = [];
        $availableFlightNos = [];

        foreach ($records as $r) {
            if (!empty($r['air_line']) && $r['air_line'] !== 'N/A') {
                $availableAirlines[$r['air_line']] = true;
            }
            if (!empty($r['city_1']) && $r['city_1'] !== 'N/A') {
                $availableAirports[$r['city_1']] = true;
            }
            if (!empty($r['city_2']) && $r['city_2'] !== 'N/A') {
                $availableAirports[$r['city_2']] = true;
            }
            if (!empty($r['flight_no']) && $r['flight_no'] !== 'N/A') {
                $availableFlightNos[$r['flight_no_base'] ?? $r['flight_no']] = true;
            }
        }

        // Merge with major Indonesian airports from DB
        try {
            $dbAirports = Airport::select('iata_code', 'name', 'city')->take(50)->get();
            foreach ($dbAirports as $ap) {
                if (!empty($ap->iata_code)) {
                    $availableAirports[$ap->iata_code] = true;
                }
            }
        } catch (\Throwable $e) {}

        ksort($availableAirlines);
        ksort($availableAirports);
        ksort($availableFlightNos);

        $airlinesList = array_keys($availableAirlines);
        $airportsList = array_keys($availableAirports);
        $flightNosList = array_slice(array_keys($availableFlightNos), 0, 100);

        return view('fdr.config', [
            'upload'         => $upload,
            'meta'           => $meta,
            'airlines'       => $airlinesList,
            'airports'       => $airportsList,
            'flightNos'      => $flightNosList,
            'recordsCount'   => count($records),
        ]);
    }

    /**
     * Ingest and validate a new FDR source file.
     */
    public function store(Request $request)
    {
        $request->validate([
            'fdr_file' => 'nullable|file|max:51200', // 50MB
        ]);

        $file = $request->file('fdr_file') ?: $request->file('file');

        if (!$file) {
            // Check if user requested reference template
            return $this->useReference($request);
        }

        $origName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx', 'csv'])) {
            $displayExt = $ext ? ".{$ext}" : '(unknown)';
            $err = "Unsupported file extension {$displayExt}. Please upload an OASYS FDR (.xls, .xlsx, or .csv) file.";
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $err], 422);
            }
            return back()->withErrors(['fdr_file' => $err])->withInput();
        }

        $storedPath = $file->store('uploads/fdr', 'local');
        $fullPath = Storage::disk('local')->path($storedPath);

        // Validate strictly
        $validation = $this->validator->validate($fullPath, $origName);
        if (!$validation['valid']) {
            Storage::disk('local')->delete($storedPath);
            return back()->withErrors(['fdr_file' => implode('; ', $validation['errors'])])->withInput();
        }

        // Parse and normalize records
        $parsed = $this->parser->parse($fullPath);

        $upload = Upload::create([
            'original_filename'  => $origName,
            'stored_path'        => $storedPath,
            'status'             => 'completed',
            'report_type'        => 'fdr',
            'total_rows'         => count($parsed['records']),
            'valid_rows'         => count($parsed['records']),
            'invalid_rows'       => 0,
            'duplicate_rows'     => 0,
            'parsing_confidence' => 1.0,
            'validation_summary' => $validation,
            'report_data'        => $parsed,
        ]);

        session(['fdr_active_upload_id' => $upload->id]);

        // If requested via AJAX
        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'upload_id'    => $upload->id,
                'redirect_url' => route('fdr.config', $upload->id),
                'meta'         => $parsed['meta'],
                'summary'      => $parsed['summary'],
            ]);
        }

        return redirect()->route('fdr.config', $upload->id)->with('success', 'FDR Workbook ingested and normalized successfully.');
    }

    /**
     * Use the authentic OASYS FDR Reference Workbook.
     */
    public function useReference(Request $request)
    {
        $templatePath = $this->resolveReferenceTemplatePath();
        if (!$templatePath || !file_exists($templatePath)) {
            abort(404, "Reference FDR template file not found.");
        }

        $parsed = $this->parser->parse($templatePath);

        $upload = Upload::create([
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

        session(['fdr_active_upload_id' => $upload->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'upload_id'    => $upload->id,
                'redirect_url' => route('fdr.config', $upload->id),
            ]);
        }

        return redirect()->route('fdr.config', $upload->id)->with('success', 'Loaded OASYS FDR Reference Dataset.');
    }

    /**
     * FDR Interactive Analytics Dashboard.
     * FDR Dashboard → Flight Details → Export
     */
    public function dashboard(Upload $upload, Request $request)
    {
        if ($upload->report_type !== 'fdr' || empty($upload->report_data)) {
            return redirect()->route('fdr.config')->with('error', 'Please select or upload a valid Flight Daily Report workbook.');
        }

        session(['fdr_active_upload_id' => $upload->id]);

        $data = $upload->report_data;
        $meta = $data['meta'] ?? [];
        $rawRecords = $data['records'] ?? [];

        // Read active filters from request query
        $filters = [
            'airport'     => strtoupper(trim($request->query('airport', $meta['airport'] ?? 'ALL'))),
            'leg'         => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'    => trim($request->query('operator', 'ALL')),
            'traffic'     => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'   => strtoupper(trim($request->query('data_type', $meta['data_type'] ?? 'OPERATIONAL DATA'))),
            'realization' => strtoupper(trim($request->query('realization', $meta['realization'] ?? 'ALL'))),
            'flight_no'   => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'      => strtoupper(trim($request->query('suffix', ''))),
            'start_date'  => trim($request->query('start_date', $meta['period_start'] ?? '')),
            'end_date'    => trim($request->query('end_date', $meta['period_end'] ?? '')),
            'report_mode' => (int)$request->query('report_mode', 1),
            'search'      => trim($request->query('search', '')),
            'v'           => $request->query('v', time()),
        ];

        // Apply filter cascade
        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);
        $filteredRecords = $filterResult['records'];

        // Compute analytical intelligence payload
        $analytics = $this->analytics->compute($filteredRecords, $meta, ['report_mode' => $filters['report_mode']]);

        // Distinct filter lists for reactive dropdowns
        $airlines = [];
        $airports = [];
        foreach ($rawRecords as $r) {
            if (!empty($r['air_line']) && $r['air_line'] !== 'N/A') $airlines[$r['air_line']] = true;
            if (!empty($r['city_1']) && $r['city_1'] !== 'N/A') $airports[$r['city_1']] = true;
            if (!empty($r['city_2']) && $r['city_2'] !== 'N/A') $airports[$r['city_2']] = true;
        }
        ksort($airlines);
        ksort($airports);

        return view('fdr.dashboard', [
            'upload'          => $upload,
            'meta'            => $meta,
            'filters'         => $filters,
            'filterResult'    => $filterResult,
            'analytics'       => $analytics,
            'records'         => array_slice($filteredRecords, 0, 50), // first 50 rows for initial view
            'totalRecords'    => count($filteredRecords),
            'airlines'        => array_keys($airlines),
            'airports'        => array_keys($airports),
            'rawRecordsCount' => count($rawRecords),
        ]);
    }

    /**
     * Reactive AJAX API endpoint for live filtering without page reloads.
     * Resolves race conditions (latest request version token wins).
     */
    public function filterApi(Upload $upload, Request $request)
    {
        if ($upload->report_type !== 'fdr' || empty($upload->report_data)) {
            return response()->json(['error' => 'Report data not found.'], 404);
        }

        $data = $upload->report_data;
        $meta = $data['meta'] ?? [];
        $rawRecords = $data['records'] ?? [];

        $filters = [
            'airport'     => strtoupper(trim($request->query('airport', 'ALL'))),
            'leg'         => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'    => trim($request->query('operator', 'ALL')),
            'traffic'     => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'   => strtoupper(trim($request->query('data_type', 'ALL'))),
            'realization' => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'   => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'      => strtoupper(trim($request->query('suffix', ''))),
            'start_date'  => trim($request->query('start_date', '')),
            'end_date'    => trim($request->query('end_date', '')),
            'report_mode' => (int)$request->query('report_mode', 1),
            'search'      => trim($request->query('search', '')),
            'v'           => $request->query('v', time()),
        ];

        $page = max(1, (int)$request->query('page', 1));
        $perPage = 50;

        // Apply filter cascade
        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);
        $filteredRecords = $filterResult['records'];

        // Compute analytics
        $analytics = $this->analytics->compute($filteredRecords, $meta, ['report_mode' => $filters['report_mode']]);

        // Pagination for detailed table
        $total = count($filteredRecords);
        $offset = ($page - 1) * $perPage;
        $pagedRecords = array_slice($filteredRecords, $offset, $perPage);

        return response()->json([
            'version'         => $filters['v'],
            'total_count'     => $filterResult['total_count'],
            'filtered_count'  => $filterResult['filtered_count'],
            'counter_text'    => $filterResult['counter_text'],
            'active_chips'    => $filterResult['active_chips'],
            'kpis'            => $analytics['kpis'],
            'hourly_charts'   => $analytics['hourly_charts'],
            'sched_vs_real'   => $analytics['schedule_vs_realization'],
            'pax_analytics'   => $analytics['passenger_analytics'],
            'airline_route'   => $analytics['airline_route'],
            'ground_ops'      => $analytics['ground_operations'],
            'mode_payload'    => $analytics['mode_payload'],
            'reconciliation_apps'   => $analytics['reconciliation_apps'],
            'reconciliation_edifly' => $analytics['reconciliation_edifly'],
            'records'         => $pagedRecords,
            'pagination'      => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total_pages'  => max(1, (int)ceil($total / $perPage)),
                'total'        => $total,
            ],
        ]);
    }

    /**
     * Get individual flight details modal payload.
     */
    public function flightDetails(Upload $upload, $flightIndex, Request $request)
    {
        $records = $upload->report_data['records'] ?? [];
        $idx = (int)$flightIndex - 1;

        if (!isset($records[$idx])) {
            // Search by index field
            foreach ($records as $r) {
                if ((int)$r['index'] === (int)$flightIndex) {
                    return response()->json(['flight' => $r]);
                }
            }
            return response()->json(['error' => 'Flight record not found.'], 404);
        }

        return response()->json(['flight' => $records[$idx]]);
    }

    /**
     * Export raw filtered dataset as formatted CSV spreadsheet with UTF-8 BOM.
     */
    public function exportCsv(Upload $upload, Request $request): StreamedResponse
    {
        if ($upload->report_type !== 'fdr' || empty($upload->report_data)) {
            abort(404, "Report data not ready for export.");
        }

        $meta = $upload->report_data['meta'] ?? [];
        $rawRecords = $upload->report_data['records'] ?? [];

        $filters = [
            'airport'     => strtoupper(trim($request->query('airport', 'ALL'))),
            'leg'         => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'    => trim($request->query('operator', 'ALL')),
            'traffic'     => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'   => strtoupper(trim($request->query('data_type', 'ALL'))),
            'realization' => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'   => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'      => strtoupper(trim($request->query('suffix', ''))),
            'start_date'  => trim($request->query('start_date', '')),
            'end_date'    => trim($request->query('end_date', '')),
            'report_mode' => (int)$request->query('report_mode', 1),
            'search'      => trim($request->query('search', '')),
        ];

        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);
        $records = $filterResult['records'];

        $filename = 'FDR_' . ($meta['airport'] ?? 'AIRPORT') . '_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($meta, $records, $filters) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            // Header metadata comments
            fputcsv($handle, ['SLOTWAVES FLIGHT DAILY REPORT (FDR) ANALYTICS']);
            fputcsv($handle, ['Airport:', $meta['airport'] ?? 'CGK', 'Name:', $meta['airport_name'] ?? 'N/A']);
            fputcsv($handle, ['Period:', $meta['period_label'] ?? 'N/A']);
            fputcsv($handle, ['Operator:', $meta['operator'] ?? 'ALL AIRLINE']);
            fputcsv($handle, ['Realization:', $meta['realization'] ?? 'YES']);
            fputcsv($handle, ['Export Timestamp:', date('Y-m-d H:i:s')]);
            fputcsv($handle, []);

            // Strict raw FDR headers
            $headers = [
                'NO', 'AIR LINE', 'FLIGHT NO', 'PAIRED NO', 'SIBT', 'SOBT', 'AIBT', 'AOBT',
                'LEG', 'DIRECTION', 'CITY 1', 'CITY 2', 'ROUTE', 'TRAFFIC', 'MTOW', 'REG. NO',
                'CAP.', 'LOAD', 'LOAD FACTOR (%)', 'ADULT', 'CHILD', 'INFANT', 'TRANSIT', 'TRANSFER',
                'DIVERT', 'MISS', 'CRW', 'EX. CRW', 'CAR. (KG)', 'BAGG. (KG)', 'POS (KG)',
                'STAND', 'RUN WAY', 'STATUS'
            ];
            fputcsv($handle, $headers);

            foreach ($records as $idx => $r) {
                $lfDisplay = ($r['load_factor'] !== 'N/A') ? $r['load_factor'] . '%' : 'N/A';
                fputcsv($handle, [
                    $idx + 1,
                    $r['air_line'],
                    $r['flight_no'],
                    $r['paired_no'],
                    $r['sibt'],
                    $r['sobt'],
                    $r['aibt'],
                    $r['aobt'],
                    $r['leg'],
                    $r['direction'],
                    $r['city_1'],
                    $r['city_2'],
                    $r['route'],
                    $r['traffic'],
                    $r['mtow'],
                    $r['reg_no'],
                    $r['cap'],
                    $r['load'],
                    $lfDisplay,
                    $r['adult'],
                    $r['child'],
                    $r['infant'],
                    $r['transit'],
                    $r['transfer'],
                    $r['divert'],
                    $r['miss'],
                    $r['crw'],
                    $r['ex_crw'],
                    $r['cargo_kg'],
                    $r['baggage_kg'],
                    $r['pos_kg'],
                    $r['stand'],
                    $r['runway'],
                    !empty($r['is_irregular']) ? 'IRREGULAR' : 'NORMAL',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export PDF with native rendering of the 3 mentor hourly charts.
     */
    public function exportPdf(Upload $upload, Request $request)
    {
        if ($upload->report_type !== 'fdr' || empty($upload->report_data)) {
            abort(404, "Report data not ready for export.");
        }

        $meta = $upload->report_data['meta'] ?? [];
        $rawRecords = $upload->report_data['records'] ?? [];

        $filters = [
            'airport'     => strtoupper(trim($request->query('airport', $meta['airport'] ?? 'ALL'))),
            'leg'         => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'    => trim($request->query('operator', 'ALL')),
            'traffic'     => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'   => strtoupper(trim($request->query('data_type', 'ALL'))),
            'realization' => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'   => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'      => strtoupper(trim($request->query('suffix', ''))),
            'start_date'  => trim($request->query('start_date', '')),
            'end_date'    => trim($request->query('end_date', '')),
            'report_mode' => (int)$request->query('report_mode', 1),
            'search'      => trim($request->query('search', '')),
        ];

        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);

        return $this->pdfExport->download($filterResult['records'], $meta, $filters);
    }

    /**
     * Resolve path to OASYS FDR reference template file.
     */
    protected function resolveReferenceTemplatePath(): ?string
    {
        $p1 = resource_path('templates/fdr/OASYS-FDR-TEMPLATE.xls');
        if (file_exists($p1)) return $p1;

        $p2 = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        if (file_exists($p2)) return $p2;

        return null;
    }
}
