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
     * Redirect to FDR dashboard directly (Part 1 & Part 29).
     */
    public function configRedirect()
    {
        $activeUploadId = session('fdr_active_upload_id');
        if ($activeUploadId) {
            $upload = Upload::where('id', $activeUploadId)
                ->where('report_type', 'fdr')
                ->where('status', 'completed')
                ->first();
            if ($upload) {
                return redirect()->route('fdr.dashboard', $upload->id);
            }
        }

        $latest = Upload::where('report_type', 'fdr')
            ->where('status', 'completed')
            ->latest()
            ->first();
        if ($latest) {
            return redirect()->route('fdr.dashboard', $latest->id);
        }

        // Auto-initialize from the standard OASYS FDR reference template
        $templatePath = $this->resolveReferenceTemplatePath();
        if ($templatePath && file_exists($templatePath)) {
            $parsed = $this->parser->parse($templatePath);
            $classified = $this->parser->classifyRows($parsed['records']);
            $movementCount = count($classified['movement_records']);
            $upload = Upload::create([
                'original_filename'  => 'CGK FDR.xls',
                'stored_path'        => 'templates/CGK FDR.xls',
                'status'             => 'completed',
                'report_type'        => 'fdr',
                'total_rows'         => $movementCount,
                'valid_rows'         => $movementCount,
                'invalid_rows'       => 0,
                'duplicate_rows'     => 0,
                'parsing_confidence' => 1.0,
                'validation_summary' => ['valid' => true],
                'report_data'        => $parsed,
            ]);
            session(['fdr_active_upload_id' => $upload->id]);
            return redirect()->route('fdr.dashboard', $upload->id);
        }

        return redirect()->route('home');
    }

    /**
     * Dedicated FDR Configuration Form Page.
     * Landing Page Flow: Home → Select Type Data to Generate → [ Flight Daily Report ] → FDR Config Form → FDR Dashboard → Flight Details → Export.
     */
    public function config(Request $request, Upload $upload = null)
    {
        if (!$upload || $upload->report_type !== 'fdr') {
            $activeUploadId = session('fdr_active_upload_id');
            if ($activeUploadId) {
                $upload = Upload::where('id', $activeUploadId)->where('report_type', 'fdr')->first();
            }
            if (!$upload) {
                $upload = Upload::where('report_type', 'fdr')->where('status', 'completed')->latest()->first();
            }
        }

        if (!$upload) {
            return $this->configRedirect();
        }

        $meta = $upload->report_data['meta'] ?? [];
        $records = $upload->report_data['records'] ?? [];
        $recordsCount = count($records);

        $airlines = [];
        $airports = [];
        foreach ($records as $r) {
            if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY') continue;
            $al = trim($r['air_line'] ?? '');
            if ($al === '' || $al === 'N/A' || strcasecmp($al, 'PAX ALL') === 0 || stripos($al, 'PAX ALL') !== false) continue;
            $airlines[$al] = true;
            if (!empty($r['city_1']) && $r['city_1'] !== 'N/A') $airports[$r['city_1']] = true;
            if (!empty($r['city_2']) && $r['city_2'] !== 'N/A') $airports[$r['city_2']] = true;
        }
        ksort($airlines);
        ksort($airports);

        return view('fdr.config', [
            'upload'       => $upload,
            'meta'         => $meta,
            'recordsCount' => $recordsCount,
            'airlines'     => array_keys($airlines),
            'airports'     => array_keys($airports),
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
        $classified = $this->parser->classifyRows($parsed['records']);
        $movementCount = count($classified['movement_records']);

        $upload = Upload::create([
            'original_filename'  => $origName,
            'stored_path'        => $storedPath,
            'status'             => 'completed',
            'report_type'        => 'fdr',
            'total_rows'         => $movementCount,
            'valid_rows'         => $movementCount,
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
                'redirect_url' => route('fdr.dashboard', $upload->id),
                'meta'         => $parsed['meta'],
                'summary'      => $parsed['summary'],
            ]);
        }

        return redirect()->route('fdr.dashboard', $upload->id)->with('success', 'FDR Workbook ingested and normalized successfully.');
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
        $classified = $this->parser->classifyRows($parsed['records']);
        $movementCount = count($classified['movement_records']);

        $upload = Upload::create([
            'original_filename'  => 'OASYS-FDR-TEMPLATE.xls',
            'stored_path'        => 'templates/OASYS-FDR-TEMPLATE.xls',
            'status'             => 'completed',
            'report_type'        => 'fdr',
            'total_rows'         => $movementCount,
            'valid_rows'         => $movementCount,
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
                'redirect_url' => route('fdr.dashboard', $upload->id),
            ]);
        }

        return redirect()->route('fdr.dashboard', $upload->id)->with('success', 'Loaded OASYS FDR Reference Dataset.');
    }

    /**
     * FDR Interactive Analytics Dashboard.
     * FDR Dashboard → Flight Details → Export
     */
    public function dashboard(Upload $upload, Request $request)
    {
        if ($upload->report_type !== 'fdr' || empty($upload->report_data)) {
            return redirect()->route('fdr.index')->with('error', 'Please select or upload a valid Flight Daily Report workbook.');
        }

        session(['fdr_active_upload_id' => $upload->id]);

        $data = $upload->report_data;
        $meta = $data['meta'] ?? [];
        $rawRecords = $data['records'] ?? [];

        // Extract distinct available dates & source dataset summary
        $availableDates = $this->extractAvailableDates($rawRecords, $meta);
        $sourceSummary = $this->buildSourceSummary($rawRecords, $availableDates, $meta);
        $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

        // Read active filters from request query
        $filters = [
            'date_scope'     => $scope['date_scope'],
            'analysis_level' => $scope['analysis_level'],
            'analysis_date'  => $scope['analysis_date'],
            'analysis_month' => $scope['analysis_month'],
            'analysis_year'  => $scope['analysis_year'],
            'airport'        => strtoupper(trim($request->query('airport', $meta['airport'] ?? 'ALL'))),
            'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'       => trim($request->query('operator', 'ALL')),
            'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'      => strtoupper(trim($request->query('data_type', $meta['data_type'] ?? 'OPERATIONAL DATA'))),
            'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'         => strtoupper(trim($request->query('suffix', ''))),
            'start_date'     => trim($request->query('start_date', $meta['period_start'] ?? '')),
            'end_date'       => trim($request->query('end_date', $meta['period_end'] ?? '')),
            'report_mode'    => (int)$request->query('report_mode', 1),
            'time_basis'     => in_array($request->query('time_basis', 'scheduled'), ['scheduled','actual']) ? $request->query('time_basis', 'scheduled') : 'scheduled',
            'otp_tolerance'  => max(1, min(120, (int)$request->query('otp_tolerance', 15))),
            'search'         => trim($request->query('search', '')),
            'v'              => $request->query('v', time()),
        ];

        // Apply filter cascade (Analysis Date / Scope + other operational filters)
        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);
        $filteredRecords = $filterResult['records'];

        // Compute analytical intelligence payload strictly for the filtered daily/scoped dataset
        $analytics = $this->analytics->compute($filteredRecords, $meta, [
            'report_mode'    => $filters['report_mode'],
            'time_basis'     => $filters['time_basis'],
            'report_date'    => $scope['analysis_date'],
            'analysis_level' => $scope['analysis_level'],
            'date_scope'     => $scope['date_scope'],
            'otp_tolerance'  => $filters['otp_tolerance'],
        ]);

        // Distinct filter lists for reactive dropdowns
        $airlines = [];
        $airports = [];
        foreach ($rawRecords as $r) {
            if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY') continue;
            $al = trim($r['air_line'] ?? '');
            if ($al === '' || $al === 'N/A' || strcasecmp($al, 'PAX ALL') === 0 || stripos($al, 'PAX ALL') !== false) continue;
            $airlines[$al] = true;
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
            'dateScope'       => $scope['date_scope'],
            'analysisLevel'   => $scope['analysis_level'],
            'analysisDate'    => $scope['analysis_date'],
            'analysisMonth'   => $scope['analysis_month'],
            'analysisYear'    => $scope['analysis_year'],
            'sourceType'      => $scope['source_type'],
            'availableDates'  => $availableDates,
            'sourceSummary'   => $sourceSummary,
            'timeBasis'       => $filters['time_basis'],
            'otpTolerance'    => $filters['otp_tolerance'],
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

        $availableDates = $this->extractAvailableDates($rawRecords, $meta);
        $sourceSummary = $this->buildSourceSummary($rawRecords, $availableDates, $meta);
        $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

        $filters = [
            'date_scope'     => $scope['date_scope'],
            'analysis_level' => $scope['analysis_level'],
            'analysis_date'  => $scope['analysis_date'],
            'analysis_month' => $scope['analysis_month'],
            'analysis_year'  => $scope['analysis_year'],
            'airport'        => strtoupper(trim($request->query('airport', 'ALL'))),
            'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'       => trim($request->query('operator', 'ALL')),
            'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'      => strtoupper(trim($request->query('data_type', 'ALL'))),
            'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'         => strtoupper(trim($request->query('suffix', ''))),
            'start_date'     => trim($request->query('start_date', '')),
            'end_date'       => trim($request->query('end_date', '')),
            'report_mode'    => (int)$request->query('report_mode', 1),
            'time_basis'     => in_array($request->query('time_basis', 'scheduled'), ['scheduled','actual']) ? $request->query('time_basis', 'scheduled') : 'scheduled',
            'otp_tolerance'  => max(1, min(120, (int)$request->query('otp_tolerance', 15))),
            'search'         => trim($request->query('search', '')),
            'v'              => $request->query('v', time()),
        ];

        $page = max(1, (int)$request->query('page', 1));
        $perPage = 50;

        // Apply filter cascade
        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);
        $filteredRecords = $filterResult['records'];

        // Compute analytics strictly for the selected single day or analytical scope
        $analytics = $this->analytics->compute($filteredRecords, $meta, [
            'report_mode'    => $filters['report_mode'],
            'time_basis'     => $filters['time_basis'],
            'report_date'    => $scope['analysis_date'],
            'analysis_level' => $scope['analysis_level'],
            'date_scope'     => $scope['date_scope'],
            'otp_tolerance'  => $filters['otp_tolerance'],
        ]);

        // Pagination for detailed table
        $total = count($filteredRecords);
        $offset = ($page - 1) * $perPage;
        $pagedRecords = array_slice($filteredRecords, $offset, $perPage);

        $counterScope = ($scope['date_scope'] === 'DAY' && !empty($scope['analysis_date']))
            ? date('d M Y', strtotime($scope['analysis_date']))
            : (($scope['analysis_level'] === 'MONTHLY')
                ? date('F Y', strtotime($scope['analysis_month'] . '-01'))
                : (($scope['analysis_level'] === 'YEARLY') ? "Year " . $scope['analysis_year'] : 'FULL RANGE'));

        $totalMovements = $sourceSummary['total_flights'] ?? $filterResult['source_count'];

        return response()->json([
            'version'             => $filters['v'],
            'date_scope'          => $scope['date_scope'],
            'analysis_level'      => $scope['analysis_level'],
            'analysis_date'       => $scope['analysis_date'],
            'analysis_month'      => $scope['analysis_month'],
            'analysis_year'       => $scope['analysis_year'],
            'analysis_date_label' => $scope['analysis_date'] ? date('d-m-Y', strtotime($scope['analysis_date'])) : 'FULL RANGE',
            'analysis_date_title' => $scope['analysis_date'] ? strtoupper(date('d F Y', strtotime($scope['analysis_date']))) : 'FULL RANGE',
            'source_summary'      => $sourceSummary,
            'source_type'         => $scope['source_type'],
            'available_dates'     => $availableDates,
            'total_count'         => $totalMovements,
            'source_count'        => $totalMovements,
            'normalized_count'    => $totalMovements,
            'filtered_count'      => $filterResult['filtered_count'],
            'excluded_count'      => $filterResult['excluded_count'],
            'reconciliation'      => $filterResult['reconciliation'],
            'exclusion_reasons'   => $filterResult['reconciliation']['exclusion_reasons'],
            'counter_text'        => "Showing " . number_format($total) . " of " . number_format($totalMovements) . " records",
            'active_chips'        => $filterResult['active_chips'],
            'kpis'                => $analytics['kpis'],
            'hourly_charts'       => $analytics['hourly_charts'],
            'combined_trend'      => $analytics['combined_trend'] ?? null,
            'sched_vs_real'       => $analytics['schedule_vs_realization'],
            'pax_analytics'       => $analytics['passenger_analytics'],
            'airline_route'       => $analytics['airline_route'],
            'fleet_performance'   => $analytics['fleet_performance'],
            'ground_ops'          => $analytics['ground_operations'],
            'mode_payload'        => $analytics['mode_payload'],
            'reconciliation_apps' => $analytics['reconciliation_apps'],
            'reconciliation_edifly' => $analytics['reconciliation_edifly'],
            'time_basis'          => $filters['time_basis'],
            'otp_tolerance'       => $filters['otp_tolerance'],
            'records'             => $pagedRecords,
            'pagination'          => [
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

        $availableDates = $this->extractAvailableDates($rawRecords, $meta);
        $sourceSummary = $this->buildSourceSummary($rawRecords, $availableDates, $meta);
        $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

        $filters = [
            'date_scope'     => $scope['date_scope'],
            'analysis_level' => $scope['analysis_level'],
            'analysis_date'  => $scope['analysis_date'],
            'analysis_month' => $scope['analysis_month'],
            'analysis_year'  => $scope['analysis_year'],
            'airport'        => strtoupper(trim($request->query('airport', $meta['airport'] ?? 'ALL'))),
            'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'       => trim($request->query('operator', 'ALL')),
            'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'      => strtoupper(trim($request->query('data_type', 'ALL'))),
            'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'         => strtoupper(trim($request->query('suffix', ''))),
            'start_date'     => trim($request->query('start_date', '')),
            'end_date'       => trim($request->query('end_date', '')),
            'report_mode'    => (int)$request->query('report_mode', 1),
            'search'         => trim($request->query('search', '')),
        ];

        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);
        $records = $filterResult['records'];

        $dateSuffix = !empty($scope['analysis_date']) ? date('Ymd', strtotime($scope['analysis_date'])) : date('Ymd_His');
        $filename = 'FDR_' . ($meta['airport'] ?? 'AIRPORT') . '_' . $dateSuffix . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($meta, $records, $filters, $scope, $sourceSummary) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            // Header metadata comments
            fputcsv($handle, ['SLOTWAVES FLIGHT DAILY REPORT (FDR) ANALYTICS']);
            fputcsv($handle, ['Airport:', $meta['airport'] ?? 'CGK', 'Name:', $meta['airport_name'] ?? 'N/A']);
            fputcsv($handle, ['Source Period:', $sourceSummary['period_label'] ?? 'N/A', 'Source Type:', $sourceSummary['source_type']]);
            fputcsv($handle, ['Analysis Level:', $scope['analysis_level']]);
            if ($scope['date_scope'] === 'ALL_PERIOD') {
                fputcsv($handle, ['Analysis Scope:', 'FULL RANGE (' . ($sourceSummary['period_label'] ?? 'All Dates') . ')']);
            } elseif ($scope['analysis_level'] === 'DAILY') {
                fputcsv($handle, ['Peak Analysis Date:', date('d-m-Y', strtotime($scope['analysis_date'])) . ' (' . date('d F Y', strtotime($scope['analysis_date'])) . ')']);
            } elseif ($scope['analysis_level'] === 'MONTHLY') {
                fputcsv($handle, ['Analysis Month:', date('F Y', strtotime($scope['analysis_month'] . '-01'))]);
            } elseif ($scope['analysis_level'] === 'YEARLY') {
                fputcsv($handle, ['Analysis Year:', $scope['analysis_year']]);
            }
            fputcsv($handle, ['Flight Movement:', $filters['leg'] ?: 'ALL']);
            fputcsv($handle, ['Traffic Type:', $filters['traffic'] ?: 'ALL']);
            fputcsv($handle, ['Operator:', $filters['operator'] ?: ($meta['operator'] ?? 'ALL AIRLINE')]);
            fputcsv($handle, ['Realization:', $filters['realization'] ?: ($meta['realization'] ?? 'YES')]);
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

        $availableDates = $this->extractAvailableDates($rawRecords, $meta);
        $sourceSummary = $this->buildSourceSummary($rawRecords, $availableDates, $meta);
        $scope = $this->resolveAnalysisScope($request, $sourceSummary, $availableDates);

        $filters = [
            'date_scope'     => $scope['date_scope'],
            'analysis_level' => $scope['analysis_level'],
            'analysis_date'  => $scope['analysis_date'],
            'analysis_month' => $scope['analysis_month'],
            'analysis_year'  => $scope['analysis_year'],
            'airport'        => strtoupper(trim($request->query('airport', $meta['airport'] ?? 'ALL'))),
            'leg'            => strtoupper(trim($request->query('leg', 'ALL'))),
            'operator'       => trim($request->query('operator', 'ALL')),
            'traffic'        => strtoupper(trim($request->query('traffic', 'ALL'))),
            'data_type'      => strtoupper(trim($request->query('data_type', 'ALL'))),
            'realization'    => strtoupper(trim($request->query('realization', 'ALL'))),
            'flight_no'      => strtoupper(trim($request->query('flight_no', ''))),
            'suffix'         => strtoupper(trim($request->query('suffix', ''))),
            'start_date'     => trim($request->query('start_date', '')),
            'end_date'       => trim($request->query('end_date', '')),
            'report_mode'    => (int)$request->query('report_mode', 1),
            'search'         => trim($request->query('search', '')),
        ];

        $filterResult = $this->filterService->apply($rawRecords, $filters, $meta);

        return $this->pdfExport->download($filterResult['records'], $meta, $filters);
    }

    /**
     * Resolve analysis level and date/scope based on request and source dataset constraints.
     */
    protected function resolveAnalysisScope(Request $request, array $sourceSummary, array $availableDates): array
    {
        $sourceType = $sourceSummary['source_type'] ?? 'DAILY';
        $startDate  = $sourceSummary['start_date'] ?? date('Y-m-d');
        $endDate    = $sourceSummary['end_date'] ?? date('Y-m-d');

        $reqDateScope = strtoupper(trim($request->query('date_scope', '')));
        $reqAnalysisDate = trim($request->query('analysis_date', ''));

        if ($sourceType === 'DAILY') {
            $dateScope     = 'DAY';
            $analysisLevel = 'DAILY';
            $analysisDate  = $startDate;
        } else {
            // Multi-day / Multi-month source
            $isExplicitDay = ($reqDateScope === 'DAY');
            $hasDateParam = (!empty($reqAnalysisDate) && !in_array(strtoupper($reqAnalysisDate), ['ALL', 'ALL_PERIOD', 'FULL', 'FULL_RANGE'], true));

            if ($isExplicitDay || $hasDateParam) {
                $stdReqDate = FlightDailyReportFilter::standardizeDate($reqAnalysisDate);
                $isValidDate = (!empty($stdReqDate) && $stdReqDate !== 'N/A' && in_array($stdReqDate, $availableDates, true));

                if ($isValidDate) {
                    $dateScope     = 'DAY';
                    $analysisLevel = 'DAILY';
                    $analysisDate  = $stdReqDate;
                } elseif ($isExplicitDay && !empty($availableDates)) {
                    $dateScope     = 'DAY';
                    $analysisLevel = 'DAILY';
                    $analysisDate  = reset($availableDates);
                } else {
                    $dateScope     = 'ALL_PERIOD';
                    $analysisLevel = 'FULL';
                    $analysisDate  = null;
                }
            } else {
                // ALL_PERIOD / FULL RANGE
                $dateScope     = 'ALL_PERIOD';
                $analysisLevel = 'FULL';
                $analysisDate  = null;
            }
        }

        $analysisMonth = trim($request->query('analysis_month', $analysisDate ? substr($analysisDate, 0, 7) : substr($startDate, 0, 7)));
        $analysisYear  = trim($request->query('analysis_year', $analysisDate ? substr($analysisDate, 0, 4) : substr($startDate, 0, 4)));

        return [
            'date_scope'     => $dateScope,
            'analysis_level' => $analysisLevel,
            'analysis_date'  => $analysisDate,
            'analysis_month' => $analysisMonth,
            'analysis_year'  => $analysisYear,
            'source_type'    => $sourceType,
        ];
    }

    /**
     * Extract distinct, sorted available dates from FDR records.
     */
    protected function extractAvailableDates(array $rawRecords, array $meta = []): array
    {
        $dates = [];
        foreach ($rawRecords as $r) {
            $d = $r['operational_date'] ?? ($r['flight_date'] ?? null);
            if ($d && $d !== 'N/A') {
                $std = FlightDailyReportFilter::standardizeDate($d);
                if ($std !== 'N/A') {
                    $dates[$std] = true;
                }
            }
        }
        $sorted = array_keys($dates);
        sort($sorted);
        return $sorted;
    }

    /**
     * Build source dataset summary.
     */
    protected function buildSourceSummary(array $rawRecords, array $availableDates, array $meta = []): array
    {
        $startDate = !empty($meta['period_start']) ? $meta['period_start'] : (!empty($availableDates) ? reset($availableDates) : date('Y-m-01'));
        $endDate = !empty($meta['period_end']) ? $meta['period_end'] : (!empty($availableDates) ? end($availableDates) : date('Y-m-t'));
        $daysCount = count($availableDates);

        $sourceType = $meta['source_type'] ?? FlightDailyReportParser::detectGranularity($startDate, $endDate);

        $movementRecords = array_filter($rawRecords, fn($r) => ($r['row_type'] ?? 'MOVEMENT') !== 'SUMMARY' && stripos($r['air_line'] ?? '', 'PAX ALL') === false);
        $totalFlights = count($movementRecords);

        return [
            'total_flights'            => $totalFlights,
            'days_count'               => $daysCount,
            'start_date'               => $startDate,
            'end_date'                 => $endDate,
            'start_label'              => date('d-m-Y', strtotime($startDate)),
            'end_label'                => date('d-m-Y', strtotime($endDate)),
            'period_label'             => date('d-m-Y', strtotime($startDate)) . ' → ' . date('d-m-Y', strtotime($endDate)),
            'days_available'           => "{$daysCount} DAYS AVAILABLE",
            'source_type'              => $sourceType,
            'is_daily'                 => ($sourceType === 'DAILY'),
            'is_monthly'               => ($sourceType === 'MONTHLY'),
            'is_yearly'                => ($sourceType === 'YEARLY'),
            'is_custom'                => ($sourceType === 'CUSTOM RANGE'),
            'source_passenger_summary' => $meta['source_passenger_summary'] ?? null,
            'pax_summary'              => $meta['source_passenger_summary'] ?? null,
        ];
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
