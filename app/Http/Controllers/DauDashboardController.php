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
            'flight_scope'   => 'DOMESTIK & INTERNASIONAL',
            'terminal_scope' => 'ALL TERMINAL',
            'source'         => 'OASYS',
        ];

        $summary = $data['summary'] ?? [];
        $records = $data['records'] ?? [];
        $columns = $data['columns'] ?? [];

        return view('dau.dashboard', compact('upload', 'reportType', 'conf', 'data', 'meta', 'summary', 'records', 'columns'));
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
     * Export DAU report as print-ready PDF.
     */
    public function exportPdf(Upload $upload)
    {
        if ($upload->status !== 'completed' || empty($upload->report_data)) {
            abort(404, "Report data not ready for export.");
        }

        $reportType = $upload->report_type;
        $conf = ReportTemplateRegistry::find($reportType);
        $data = $upload->report_data;
        $meta = $data['meta'] ?? [];
        $summary = $data['summary'] ?? [];
        $records = $data['records'] ?? [];

        $filename = ($conf ? $conf['code'] : $reportType) . '_Report_' . date('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('dau.pdf', compact('upload', 'reportType', 'conf', 'data', 'meta', 'summary', 'records'))
            ->setPaper('a4', 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->download($filename);
    }

    /**
     * Export DAU report as CSV spreadsheet.
     */
    public function exportExcel(Upload $upload): StreamedResponse
    {
        if ($upload->status !== 'completed' || empty($upload->report_data)) {
            abort(404, "Report data not ready for export.");
        }

        $reportType = $upload->report_type;
        $conf = ReportTemplateRegistry::find($reportType);
        $data = $upload->report_data;
        $meta = $data['meta'] ?? [];
        $records = $data['records'] ?? [];
        $columns = $data['columns'] ?? [];

        $filename = ($conf ? $conf['code'] : $reportType) . '_Data_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($conf, $meta, $columns, $records) {
            $handle = fopen('php://output', 'w');
            // Write BOM for UTF-8 Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Metadata block
            fputcsv($handle, [$conf['title'] ?? 'DATA ANGKUTAN UDARA']);
            fputcsv($handle, ['Bandara:', $meta['airport'] ?? 'N/A']);
            fputcsv($handle, ['Tanggal:', $meta['date_range'] ?? 'N/A']);
            fputcsv($handle, ['Penerbangan:', $meta['flight_scope'] ?? 'DOMESTIK & INTERNASIONAL']);
            fputcsv($handle, ['Terminal:', $meta['terminal_scope'] ?? 'ALL TERMINAL']);
            fputcsv($handle, ['Sumber:', 'OASYS']);
            fputcsv($handle, []); // empty row separator

            // Table headers
            if (!empty($columns)) {
                fputcsv($handle, $columns);
            }

            // Table data rows
            foreach ($records as $r) {
                // Flatten array for CSV export
                $rowVals = [];
                foreach ($r as $key => $val) {
                    if ($key === 'details' || $key === 'airlines' || $key === 'terminals') continue;
                    $rowVals[] = is_array($val) ? json_encode($val) : $val;
                }
                fputcsv($handle, $rowVals);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
