<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SlotWaves — Flight Schedule Report</title>
    <style>
        /* ── Reset ──────────────────────────────────────────────────────── */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #1a1a2e;
            background: #fff;
            line-height: 1.4;
        }

        /* ── Page Layout ────────────────────────────────────────────────── */
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 15mm 12mm;
        }

        .report-footer {
            position: fixed;
            bottom: -10mm;
            left: 0; right: 0;
            font-size: 7.5pt;
            color: #6b7280;
            border-top: 1px solid #d1d5db;
            padding-top: 3mm;
            display: flex;
            justify-content: space-between;
        }

        .page-break { page-break-after: always; break-after: page; }

        /* ── Header ─────────────────────────────────────────────────────── */
        .report-header {
            margin-bottom: 6mm;
        }
        .header-brand {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 3px;
        }
        .brand-logo {
            font-size: 15pt;
            color: #1d4ed8;
        }
        .brand-name {
            font-size: 13pt;
            font-weight: 900;
            color: #1e3a5f;
            letter-spacing: 0.05em;
        }
        .header-report-title {
            font-size: 11pt;
            font-weight: 800;
            color: #1d4ed8;
            margin: 2px 0 2px 0;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .header-airport {
            font-size: 10pt;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 2px;
        }
        .header-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 2px;
            font-size: 7.5pt;
            color: #6b7280;
        }
        .header-divider {
            height: 2px;
            background: linear-gradient(to right, #1d4ed8, #60a5fa, #e5e7eb);
            margin-top: 4px;
        }

        /* ── Section Heading ─────────────────────────────────────────────── */
        .section-heading {
            font-size: 9pt;
            font-weight: 700;
            color: #1e3a5f;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 6px 0 4px 0;
            padding: 3px 6px;
            background: #eff6ff;
            border-left: 3px solid #1d4ed8;
        }
        .dos-group-header {
            font-size: 9pt;
            font-weight: 700;
            color: #fff;
            background: #1e3a5f;
            padding: 4px 8px;
            margin: 6px 0 2px 0;
        }
        .dos-group-label { font-size: 9.5pt; }
        .dos-group-days  { font-size: 7.5pt; color: #93c5fd; }
        .dos-sub-label {
            font-size: 7.5pt;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 3px 0 2px 4px;
        }

        /* ── Tables ──────────────────────────────────────────────────────── */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            page-break-inside: auto;
        }
        .report-table thead {
            display: table-header-group;
        }
        .report-table th {
            background: #1e3a5f;
            color: #fff;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 4px 6px;
            border: 1px solid #1e3a5f;
            text-align: left;
            white-space: nowrap;
        }
        .report-table td {
            font-size: 8pt;
            padding: 3px 6px;
            border: 1px solid #d1d5db;
            vertical-align: middle;
        }
        .row-even { background: #fff; }
        .row-odd  { background: #f8fafc; }
        tr { page-break-inside: avoid; }

        /* Column widths in landscape A4 */
        .col-no     { width: 32px;  text-align: center; color: #6b7280; font-size: 7.5pt; }
        .col-flight { width: 85px;  }
        .col-ac     { width: 95px;  }
        .col-time   { width: 55px;  text-align: center; }
        .col-origin { width: 160px; }
        .col-dest   { width: 160px; }
        .col-type   { width: 45px;  text-align: center; }
        .col-days   { width: 110px; }

        /* Monospace for times */
        .mono { font-family: 'Courier New', monospace; font-weight: 600; font-size: 8pt; }
        .fw-bold { font-weight: 700; }

        /* Badges */
        .badge {
            font-size: 7pt;
            color: #1d4ed8;
            font-weight: 600;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 1px 5px;
            border-radius: 3px;
        }
        .badge-type {
            display: inline-block;
            font-size: 6.5pt;
            font-weight: 700;
            padding: 1px 4px;
            border-radius: 2px;
        }
        .badge-dom { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-int { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }

        /* Totals */
        .totals-row {
            background: #eff6ff;
            font-weight: 700;
        }
        .totals-label { text-align: right; font-size: 8pt; color: #1e3a5f; padding-right: 8px; }
        .totals-value { text-align: center; font-weight: 700; color: #1d4ed8; font-size: 9pt; }

        .totals-table { margin-top: 4px; }
        .grand-totals-row { background: #1e3a5f; }
        .grand-label { color: #fff; font-weight: 700; font-size: 8pt; text-align: right; padding-right: 8px; }
        .grand-value { color: #fbbf24; font-weight: 900; font-size: 10pt; text-align: center; width: 60px; }

        /* Layout helpers */
        .section-gap   { height: 6mm; }
        .dos-group-gap { margin-top: 4mm; }
        .dos-divider   { border-top: 1px dashed #d1d5db; margin: 4mm 0; }
        .empty-row     { text-align: center; color: #9ca3af; font-style: italic; padding: 8px; }
    </style>
</head>
<body>

{{-- ── Fixed footer on every page ──────────────────────────────────────── --}}
<div class="report-footer">
    <div class="footer-left">Generated by SlotWaves &mdash; {{ \Carbon\Carbon::now()->format('d F Y, H:i') }} WIB</div>
    <div class="footer-right">
        Page <span class="pagenum"></span>
    </div>
</div>

@if($section === 'time' || $section === 'combined')
    {{-- ── SECTION 1: TIME FLIGHT SCHEDULE ─────────────────────────────── --}}
    @include('reports.sections.time', [
        'flights' => $flights,
        'airport' => $airport,
        'upload'  => $upload,
    ])
@endif

@if($section === 'combined')
    {{-- ── Page break between TIME and DOS ─────────────────────────────── --}}
    <div class="page-break"></div>
@endif

@if($section === 'dos' || $section === 'combined')
    {{-- ── SECTION 2: DAILY OPERATING SERVICE (DOS) ─────────────────────── --}}
    @include('reports.sections.dos', [
        'dosGroups' => $dosGroups,
        'airport'   => $airport,
        'upload'    => $upload,
    ])
@endif

</body>
</html>
