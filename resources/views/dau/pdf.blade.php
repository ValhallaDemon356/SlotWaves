<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $conf['title'] ?? 'DAU Report' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm 12mm 10mm;
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7.2pt;
            color: #1e293b;
            line-height: 1.25;
        }
        .header-box {
            border-bottom: 2.5px solid #0284c7;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .title {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0 0 2px 0;
            letter-spacing: 0.5px;
        }
        .subtitle {
            font-size: 8.5pt;
            color: #475569;
            font-weight: bold;
        }
        .meta-table {
            width: 100%;
            margin-top: 5px;
            font-size: 7pt;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .meta-table td {
            padding: 3px 6px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .kpi-cell {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 5px 4px;
            text-align: center;
        }
        .kpi-label {
            font-size: 6pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .kpi-value {
            font-size: 11pt;
            font-weight: bold;
            color: #0284c7;
            margin-top: 1px;
        }
        .kpi-sub {
            font-size: 5.5pt;
            color: #64748b;
            margin-top: 1px;
        }

        /* Chart & Diagram Boxes */
        .chart-box {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            padding: 6px 8px;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        .chart-header {
            font-size: 7.5pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }
        .chart-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            font-size: 5.5pt;
        }
        .chart-table td {
            vertical-align: bottom;
            padding: 1px;
        }
        .bar-wrap {
            height: 48px;
            position: relative;
            background: #f1f5f9;
            border-radius: 2px;
        }
        .bar-inner {
            position: absolute;
            bottom: 0;
            width: 100%;
            border-radius: 2px 2px 0 0;
        }

        /* Heatmap Grid */
        .heatmap-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6pt;
            text-align: center;
        }
        .heatmap-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 5.5pt;
            padding: 3px 2px;
            border: 1px solid #334155;
        }
        .heatmap-table td {
            border: 1px solid #cbd5e1;
            padding: 3px 2px;
            font-weight: bold;
        }

        /* Main Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.2pt;
            margin-top: 4px;
        }
        .data-table thead {
            display: table-header-group;
        }
        .data-table tr {
            page-break-inside: avoid;
        }
        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            padding: 3px 4px;
            border: 1px solid #334155;
            font-size: 5.8pt;
        }
        .data-table td {
            padding: 2.5px 3.5px;
            border: 1px solid #cbd5e1;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }

        .badge-available {
            background-color: #d1fae5;
            color: #065f46;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
            font-size: 5.5pt;
        }
        .badge-full {
            background-color: #fef3c7;
            color: #92400e;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
            font-size: 5.5pt;
        }
        .badge-over {
            background-color: #f3e8ff;
            color: #6b21a8;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
            font-size: 5.5pt;
        }
        .badge-off {
            background-color: #f1f5f9;
            color: #475569;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
            font-size: 5.5pt;
        }

        .page-break {
            page-break-after: always;
        }

        .progress-bar-wrap {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
        }
        .progress-bar-fill {
            height: 100%;
            display: block;
        }
    </style>
</head>
<body>

    {{-- Script for dynamic page numbering --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "SlotWaves • Data Angkutan Udara • {{ $conf['code'] ?? $reportType }} • Generated: {{ date('d/m/Y H:i:s') }} • Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            $size = 6.5;
            $font = $fontMetrics->getFont("DejaVu Sans", "normal");
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $pdf->page_text(842 - $width - 28, 595 - 20, $text, $font, $size, [0.4, 0.4, 0.4]);
        }
    </script>

    {{-- ══ HEADER SECTION ════════════════════════════════════════════════════ --}}
    <div class="header-box">
        <table style="width: 100%;">
            <tr>
                <td style="width: 75%;">
                    <div class="title">
                        DATA ANGKUTAN UDARA
                        @if ($reportType === 'DAU1')
                            ARUS LALU LINTAS PESAWAT, PENUMPANG, BAGASI & KARGO (DAU-01)
                            @if (($filters['metric'] ?? 'aircraft') === 'passenger')
                                <div style="font-size: 8pt; color: #2563eb; font-weight: normal; margin-top: 2px;">METRIC: PENUMPANG (PASSENGER BREAKDOWN: DEWASA, ANAK, BAYI)</div>
                            @endif
                        @elseif ($reportType === 'DAU2')
                            SECARA TOTAL (DOMESTIK VS INTERNASIONAL) (DAU-02)
                            <div style="font-size: 8pt; color: #2563eb; font-weight: normal; margin-top: 2px;">METRIC: {{ strtoupper($filters['metric'] ?? 'AIRCRAFT') }} &bull; MODE: {{ strtoupper($filters['display_mode'] ?? 'ABSOLUTE') }}</div>
                        @elseif ($reportType === 'DAU3')
                            STATUS PENERBANGAN (NIAGA & BUKAN NIAGA) (DAU-03)
                        @elseif ($reportType === 'DAU4')
                            ASAL / TUJUAN (ORIGIN & DESTINATION) (DAU-04)
                        @elseif ($reportType === 'DAU4A')
                            ASAL / TUJUAN — OPERATOR (DAU-04A)
                        @elseif ($reportType === 'DAU4B')
                            ASAL / TUJUAN — AIRLINE / OPERATOR (DAU-04B)
                        @elseif ($reportType === 'DAU5')
                            AIRLINE OPERATOR (DAU-05)
                        @elseif ($reportType === 'DAU5A')
                            AIRLINE OPERATOR — EXTRA CREW (DAU-05A)
                        @elseif ($reportType === 'DAU5B')
                            AIRLINE OPERATOR — TERMINAL (DAU-05B)
                        @elseif ($reportType === 'DAU5C')
                            AIRLINE OPERATOR — LOAD FACTOR / PROFIL (DAU-05C)
                        @elseif ($reportType === 'DAU6')
                            TIPE PESAWAT (FLEET MIX & WTC) (DAU-06)
                        @elseif ($reportType === 'DAU10')
                            JAM PUNCAK PESAWAT/PENUMPANG (DAU-10)
                        @elseif ($reportType === 'DAU10A')
                            JAM PUNCAK MENURUT TERMINAL (DAU-10A)
                        @elseif ($reportType === 'DAU10B')
                            JAM PUNCAK PESAWAT/PENUMPANG (BLOCK ON/OFF) (DAU-10B)
                        @elseif ($reportType === 'DAU11')
                            DATA STATISTIK 1 (TRAFFIC FLOW) (DAU-11)
                        @elseif ($reportType === 'DAU12')
                            DATA STATISTIK 2 (ARR/DEP x DOM/INT) (DAU-12)
                        @else
                            {{ $conf['title'] ?? 'LAPORAN DAU' }} ({{ $conf['code'] ?? $reportType }})
                        @endif
                    </div>
                    <div class="subtitle">{{ $meta['airport_name'] ?? 'Tangerang Banten - Soekarno Hatta' }} ({{ $meta['airport_code'] ?? 'CGK' }})</div>
                </td>
                <td style="width: 25%; text-align: right; vertical-align: top;">
                    <div style="font-size: 13pt; font-weight: bold; color: #0284c7;">{{ $conf['code'] ?? $reportType }}</div>
                    <div style="font-size: 7pt; color: #64748b;">Source: OASYS Verified</div>
                </td>
            </tr>
        </table>

        <table class="meta-table">
            <tr>
                <td style="width: 25%;"><strong>TANGGAL:</strong> {{ $meta['date_range'] ?? 'N/A' }}</td>
                <td style="width: 25%;"><strong>PENERBANGAN:</strong> {{ $meta['flight_scope'] ?? 'DOM & INT' }}</td>
                <td style="width: 25%;"><strong>TERMINAL:</strong> {{ $meta['terminal_scope'] ?? 'ALL' }}</td>
                <td style="width: 25%; text-align: right;"><strong>PRINTED:</strong> {{ date('d/m/Y H:i:s') }}</td>
            </tr>
            @php
                $activeFilterList = [];
                if (!empty($filters['flight_type']) && $filters['flight_type'] !== 'ALL') $activeFilterList[] = 'Flight: ' . $filters['flight_type'];
                if (!empty($filters['direction']) && $filters['direction'] !== 'ALL') $activeFilterList[] = 'Direction: ' . $filters['direction'];
                if (!empty($filters['terminal']) && $filters['terminal'] !== 'ALL') $activeFilterList[] = 'Terminal: ' . $filters['terminal'];
                if (!empty($filters['airline']) && $filters['airline'] !== 'ALL') $activeFilterList[] = 'Airline: ' . $filters['airline'];
                if (!empty($filters['hour']) && $filters['hour'] !== 'ALL') $activeFilterList[] = 'Hour: ' . $filters['hour'];
                if (!empty($filters['metric'])) $activeFilterList[] = 'Metric: ' . strtoupper($filters['metric']);
                if (!empty($filters['passenger_type']) && $filters['passenger_type'] !== 'ALL') $activeFilterList[] = 'Pax Type: ' . $filters['passenger_type'];
                if (!empty($filters['display_mode']) && $filters['display_mode'] !== 'absolute') $activeFilterList[] = 'Mode: ' . strtoupper($filters['display_mode']);
                if (!empty($filters['search'])) $activeFilterList[] = 'Search: "' . $filters['search'] . '"';
            @endphp
            @if (count($activeFilterList) > 0)
                <tr>
                    <td colspan="4" style="color: #0284c7; font-weight: bold; border-top: 1px dashed #cbd5e1;">
                        FILTER AKTIF: {{ implode(' • ', $activeFilterList) }} &nbsp; ({{ count($records) }} records terpilih)
                    </td>
                </tr>
            @endif
        </table>
    </div>

    {{-- ══ EXECUTIVE SUMMARY / KPI CARDS ════════════════════════════════════ --}}
    <table class="kpi-table">
        <tr>
            @if ($reportType === 'DAU1')
                @if (($filters['metric'] ?? 'aircraft') === 'passenger')
                    @php
                        $pAdult = $summary['passenger_adult'] ?? ($summary['arr_adult'] ?? 0) + ($summary['dep_adult'] ?? 0);
                        $pChild = $summary['passenger_child'] ?? ($summary['arr_child'] ?? 0) + ($summary['dep_child'] ?? 0);
                        $pInfant = $summary['passenger_infant'] ?? ($summary['arr_infant'] ?? 0) + ($summary['dep_infant'] ?? 0);
                        $pTotal = $summary['passenger_total'] ?? ($pAdult + $pChild + $pInfant);
                    @endphp
                    <td class="kpi-cell" style="width: 16.6%;">
                        <div class="kpi-label">Total Passengers</div>
                        <div class="kpi-value" style="color: #059669;">{{ number_format($pTotal) }}</div>
                        <div class="kpi-sub">Seluruh Pax</div>
                    </td>
                    <td class="kpi-cell" style="width: 16.6%;">
                        <div class="kpi-label">Dewasa (Adult)</div>
                        <div class="kpi-value" style="color: #2563eb;">{{ number_format($pAdult) }}</div>
                        <div class="kpi-sub">Penumpang Dewasa</div>
                    </td>
                    <td class="kpi-cell" style="width: 16.6%;">
                        <div class="kpi-label">Anak (Child)</div>
                        <div class="kpi-value" style="color: #0284c7;">{{ number_format($pChild) }}</div>
                        <div class="kpi-sub">Penumpang Anak</div>
                    </td>
                    <td class="kpi-cell" style="width: 16.6%;">
                        <div class="kpi-label">Bayi (Infant)</div>
                        <div class="kpi-value" style="color: #a855f7;">{{ number_format($pInfant) }}</div>
                        <div class="kpi-sub">Penumpang Bayi</div>
                    </td>
                    <td class="kpi-cell" style="width: 16.6%;">
                        <div class="kpi-label">Pax Kedatangan</div>
                        <div class="kpi-value" style="color: #d97706;">{{ number_format($summary['passenger_arrival'] ?? 0) }}</div>
                        <div class="kpi-sub">Arrival (ARR)</div>
                    </td>
                    <td class="kpi-cell" style="width: 17%;">
                        <div class="kpi-label">Pax Keberangkatan</div>
                        <div class="kpi-value" style="color: #0284c7;">{{ number_format($summary['passenger_departure'] ?? 0) }}</div>
                        <div class="kpi-sub">Departure (DEP)</div>
                    </td>
                @else
                    <td class="kpi-cell" style="width: 14.2%;">
                        <div class="kpi-label">Total Aircraft</div>
                        <div class="kpi-value">{{ number_format($summary['aircraft_total'] ?? 0) }}</div>
                        <div class="kpi-sub">Movements</div>
                    </td>
                    <td class="kpi-cell" style="width: 14.2%;">
                        <div class="kpi-label">Arrival Acft</div>
                        <div class="kpi-value" style="color: #d97706;">{{ number_format($summary['aircraft_arrival'] ?? 0) }}</div>
                        <div class="kpi-sub">Inbound</div>
                    </td>
                    <td class="kpi-cell" style="width: 14.2%;">
                        <div class="kpi-label">Departure Acft</div>
                        <div class="kpi-value" style="color: #0284c7;">{{ number_format($summary['aircraft_departure'] ?? 0) }}</div>
                        <div class="kpi-sub">Outbound</div>
                    </td>
                    <td class="kpi-cell" style="width: 14.2%;">
                        <div class="kpi-label">Total Passengers</div>
                        <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                        <div class="kpi-sub">PAX Carried</div>
                    </td>
                    <td class="kpi-cell" style="width: 14.2%;">
                        <div class="kpi-label">Baggage (Kg)</div>
                        <div class="kpi-value" style="color: #d97706;">{{ number_format($summary['baggage_total'] ?? 0) }}</div>
                        <div class="kpi-sub">Luggage</div>
                    </td>
                    <td class="kpi-cell" style="width: 14.2%;">
                        <div class="kpi-label">Cargo (Kg)</div>
                        <div class="kpi-value" style="color: #4f46e5;">{{ number_format($summary['cargo_total'] ?? 0) }}</div>
                        <div class="kpi-sub">Freight</div>
                    </td>
                    <td class="kpi-cell" style="width: 14.8%;">
                        <div class="kpi-label">POS (Kg)</div>
                        <div class="kpi-value" style="color: #64748b;">{{ number_format($summary['pos_total'] ?? 0) }}</div>
                        <div class="kpi-sub">Mail &amp; Post</div>
                    </td>
                @endif

            @elseif ($reportType === 'DAU2')
                @php
                    $mKey = $filters['metric'] ?? 'aircraft';
                    $comp = $analytics['dau2_comparative'][$mKey] ?? ($analytics['dau2_comparative']['aircraft'] ?? null);
                    $dVal = $comp['domestic'] ?? ($analytics['dau2_distribution']['domestic'][$mKey] ?? 0);
                    $iVal = $comp['international'] ?? ($analytics['dau2_distribution']['international'][$mKey] ?? 0);
                    $tVal = $comp['total'] ?? ($dVal + $iVal);
                    $dShare = $comp['domestic_pct'] ?? ($tVal > 0 ? round(($dVal / $tVal) * 100, 1) : 0);
                    $iShare = $comp['international_pct'] ?? ($tVal > 0 ? round(($iVal / $tVal) * 100, 1) : 0);
                    $unit = $comp['unit'] ?? 'Units';
                    $label = $comp['metric'] ?? strtoupper($mKey);
                @endphp
                <td class="kpi-cell" style="width: 25%;">
                    <div class="kpi-label">Domestic {{ $label }}</div>
                    <div class="kpi-value" style="color: #2563eb;">{{ number_format($dVal) }}</div>
                    <div class="kpi-sub">{{ $dShare }}% share ({{ $unit }})</div>
                </td>
                <td class="kpi-cell" style="width: 25%;">
                    <div class="kpi-label">Int'l {{ $label }}</div>
                    <div class="kpi-value" style="color: #4f46e5;">{{ number_format($iVal) }}</div>
                    <div class="kpi-sub">{{ $iShare }}% share ({{ $unit }})</div>
                </td>
                <td class="kpi-cell" style="width: 25%;">
                    <div class="kpi-label">Total {{ $label }}</div>
                    <div class="kpi-value" style="color: #0f172a;">{{ number_format($tVal) }}</div>
                    <div class="kpi-sub">Domestic + Int ({{ $unit }})</div>
                </td>
                <td class="kpi-cell" style="width: 25%;">
                    <div class="kpi-label">Proporsi Domestik</div>
                    <div class="kpi-value" style="color: #059669;">{{ $dShare }}%</div>
                    <div class="kpi-sub">vs {{ $iShare }}% Int'l</div>
                </td>

            @elseif ($reportType === 'DAU3')
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Niaga (Commercial)</div>
                    <div class="kpi-value">{{ number_format($analytics['dau3_status']['niaga']['aircraft'] ?? 0) }}</div>
                    <div class="kpi-sub">Aircraft</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Bukan Niaga</div>
                    <div class="kpi-value" style="color: #7c3aed;">{{ number_format($analytics['dau3_status']['bukan_niaga']['aircraft'] ?? 0) }}</div>
                    <div class="kpi-sub">Aircraft</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Aircraft</div>
                    <div class="kpi-value">{{ number_format($summary['aircraft_total'] ?? 0) }}</div>
                    <div class="kpi-sub">All Flights</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Niaga Pax</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($analytics['dau3_status']['niaga']['passenger'] ?? 0) }}</div>
                    <div class="kpi-sub">Commercial Pax</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Bukan Niaga Pax</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($analytics['dau3_status']['bukan_niaga']['passenger'] ?? 0) }}</div>
                    <div class="kpi-sub">Non-commercial</div>
                </td>
                <td class="kpi-cell" style="width: 17%;">
                    <div class="kpi-label">Total Passengers</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                    <div class="kpi-sub">All Categories</div>
                </td>

            @elseif ($reportType === 'DAU4' || $reportType === 'DAU4A' || $reportType === 'DAU4B')
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Aircraft</div>
                    <div class="kpi-value">{{ number_format($summary['aircraft_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Movements</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Arrival Acft</div>
                    <div class="kpi-value" style="color: #d97706;">{{ number_format($summary['aircraft_arrival'] ?? 0) }}</div>
                    <div class="kpi-sub">Inbound</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Departure Acft</div>
                    <div class="kpi-value" style="color: #0284c7;">{{ number_format($summary['aircraft_departure'] ?? 0) }}</div>
                    <div class="kpi-sub">Outbound</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Passengers</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                    <div class="kpi-sub">PAX Carried</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Crew</div>
                    <div class="kpi-value" style="color: #475569;">{{ number_format($summary['crew_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Flight Crew</div>
                </td>
                <td class="kpi-cell" style="width: 17%;">
                    <div class="kpi-label">Baggage / Cargo</div>
                    <div class="kpi-value" style="font-size: 8.5pt; color: #475569;">
                        {{ number_format(round(($summary['baggage_total'] ?? 0)/1000)) }}k / {{ number_format(round(($summary['cargo_total'] ?? 0)/1000)) }}k
                    </div>
                    <div class="kpi-sub">Tonnes (Kg)</div>
                </td>

            @elseif ($reportType === 'DAU5' || $reportType === 'DAU5C')
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Airlines</div>
                    <div class="kpi-value" style="color: #0284c7;">{{ count($records) }}</div>
                    <div class="kpi-sub">Operators</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Aircraft</div>
                    <div class="kpi-value">{{ number_format($summary['aircraft_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Movements</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Passengers</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                    <div class="kpi-sub">PAX Carried</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Crew</div>
                    <div class="kpi-value" style="color: #475569;">{{ number_format($summary['crew_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Awak Pesawat</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Baggage (Kg)</div>
                    <div class="kpi-value" style="color: #d97706;">{{ number_format($summary['baggage_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Luggage</div>
                </td>
                <td class="kpi-cell" style="width: 17%;">
                    <div class="kpi-label">Cargo (Kg)</div>
                    <div class="kpi-value" style="color: #4f46e5;">{{ number_format($summary['cargo_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Freight</div>
                </td>

            @elseif ($reportType === 'DAU5A')
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Airlines</div>
                    <div class="kpi-value" style="color: #0284c7;">{{ count($records) }}</div>
                    <div class="kpi-sub">Operators</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Aircraft</div>
                    <div class="kpi-value">{{ number_format($summary['aircraft_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Movements</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Passengers</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                    <div class="kpi-sub">PAX</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Operating Crew</div>
                    <div class="kpi-value" style="color: #2563eb;">{{ number_format($summary['crew_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Tugas Operasi</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Extra Crew</div>
                    <div class="kpi-value" style="color: #9333ea;">{{ number_format($summary['extra_crew_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Ekstra Crew</div>
                </td>
                <td class="kpi-cell" style="width: 17%;">
                    <div class="kpi-label">Total Awak</div>
                    <div class="kpi-value" style="color: #475569;">{{ number_format(($summary['crew_total'] ?? 0) + ($summary['extra_crew_total'] ?? 0)) }}</div>
                    <div class="kpi-sub">Combined Crew</div>
                </td>

            @elseif ($reportType === 'DAU5B')
                @php
                    $activeTermCount = count($analytics['dau5b_terminals'] ?? []);
                @endphp
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Terminals</div>
                    <div class="kpi-value" style="color: #0284c7;">{{ $activeTermCount }}</div>
                    <div class="kpi-sub">Active Terminals</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Aircraft</div>
                    <div class="kpi-value">{{ number_format($summary['aircraft_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Movements</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Passengers</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                    <div class="kpi-sub">PAX Carried</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Arrival Movements</div>
                    <div class="kpi-value" style="color: #d97706;">{{ number_format($summary['aircraft_arrival'] ?? 0) }}</div>
                    <div class="kpi-sub">Inbound</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Departure Movements</div>
                    <div class="kpi-value" style="color: #0284c7;">{{ number_format($summary['aircraft_departure'] ?? 0) }}</div>
                    <div class="kpi-sub">Outbound</div>
                </td>
                <td class="kpi-cell" style="width: 17%;">
                    <div class="kpi-label">Baggage / Cargo</div>
                    <div class="kpi-value" style="font-size: 8.5pt; color: #475569;">
                        {{ number_format(round(($summary['baggage_total'] ?? 0)/1000)) }}k / {{ number_format(round(($summary['cargo_total'] ?? 0)/1000)) }}k
                    </div>
                    <div class="kpi-sub">Tonnes (Kg)</div>
                </td>

            @elseif ($reportType === 'DAU6')
                @php
                    $catShare = $analytics['dau6_fleet']['category_share'] ?? [];
                    $narrowCnt = $catShare['Narrow Body'] ?? 0;
                    $wideCnt   = $catShare['Wide Body'] ?? 0;
                @endphp
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Aircraft Types</div>
                    <div class="kpi-value" style="color: #0284c7;">{{ count($records) }}</div>
                    <div class="kpi-sub">Active Types</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Movements</div>
                    <div class="kpi-value">{{ number_format($summary['aircraft_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Flights</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Passengers</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                    <div class="kpi-sub">PAX Carried</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Narrow Body</div>
                    <div class="kpi-value" style="color: #2563eb;">{{ number_format($narrowCnt) }}</div>
                    <div class="kpi-sub">B737, A320, etc.</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Wide Body</div>
                    <div class="kpi-value" style="color: #7c3aed;">{{ number_format($wideCnt) }}</div>
                    <div class="kpi-sub">B777, A330, B787</div>
                </td>
                <td class="kpi-cell" style="width: 17%;">
                    <div class="kpi-label">Baggage / Cargo</div>
                    <div class="kpi-value" style="font-size: 8.5pt; color: #475569;">
                        {{ number_format(round(($summary['baggage_total'] ?? 0)/1000)) }}k / {{ number_format(round(($summary['cargo_total'] ?? 0)/1000)) }}k
                    </div>
                    <div class="kpi-sub">Tonnes (Kg)</div>
                </td>

            @elseif ($reportType === 'DAU10' || $reportType === 'DAU10A' || $reportType === 'DAU10B')
                @if ($reportType === 'DAU10B')
                    <td class="kpi-cell" style="width: 12.5%;">
                        <div class="kpi-label">Peak Block On</div>
                        <div class="kpi-value" style="color: #7c3aed;">{{ number_format($peaks['peak_block_on'] ?? 0) }}</div>
                        <div class="kpi-sub">{{ $peaks['peak_block_on_hour'] ?? '—' }}</div>
                    </td>
                    <td class="kpi-cell" style="width: 12.5%;">
                        <div class="kpi-label">Peak Block Off</div>
                        <div class="kpi-value" style="color: #d97706;">{{ number_format($peaks['peak_block_off'] ?? 0) }}</div>
                        <div class="kpi-sub">{{ $peaks['peak_block_off_hour'] ?? '—' }}</div>
                    </td>
                @else
                    <td class="kpi-cell" style="width: 12.5%;">
                        <div class="kpi-label">Peak Aircraft</div>
                        <div class="kpi-value">{{ number_format($peaks['peak_aircraft'] ?? 0) }}</div>
                        <div class="kpi-sub">{{ $peaks['peak_aircraft_hour'] ?? '—' }}</div>
                    </td>
                    <td class="kpi-cell" style="width: 12.5%;">
                        <div class="kpi-label">Peak Passenger</div>
                        <div class="kpi-value" style="color: #059669;">{{ number_format($peaks['peak_passenger'] ?? 0) }}</div>
                        <div class="kpi-sub">{{ $peaks['peak_passenger_hour'] ?? '—' }}</div>
                    </td>
                @endif

                <td class="kpi-cell" style="width: 12.5%;">
                    <div class="kpi-label">Peak Hour</div>
                    <div class="kpi-value" style="color: #2563eb; font-size: 8.5pt;">{{ $peaks['peak_hour'] ?? '—' }}</div>
                    <div class="kpi-sub">Highest Density</div>
                </td>

                @if ($reportType === 'DAU10A' || $reportType === 'DAU10B')
                    <td class="kpi-cell" style="width: 12.5%;">
                        <div class="kpi-label">Peak Terminal</div>
                        <div class="kpi-value" style="color: #9333ea;">T{{ $peaks['peak_terminal'] ?? '—' }}</div>
                        <div class="kpi-sub">{{ number_format($peaks['peak_terminal_val'] ?? 0) }} mov</div>
                    </td>
                @endif

                <td class="kpi-cell" style="width: 12.5%;">
                    <div class="kpi-label">Total Movements</div>
                    <div class="kpi-value">{{ number_format($summary['total_movements'] ?? $summary['aircraft_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Arr: {{ number_format($summary['aircraft_arrival'] ?? 0) }} | Dep: {{ number_format($summary['aircraft_departure'] ?? 0) }}</div>
                </td>

                <td class="kpi-cell" style="width: 12.5%;">
                    <div class="kpi-label">Total Passengers</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                    <div class="kpi-sub">PAX Carried</div>
                </td>

                <td class="kpi-cell" style="width: 12.5%;">
                    <div class="kpi-label">Total Crew</div>
                    <div class="kpi-value" style="color: #475569;">{{ number_format($summary['crew_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Flight Crew</div>
                </td>

                @if ($reportType !== 'DAU10A')
                    <td class="kpi-cell" style="width: 12.5%;">
                        <div class="kpi-label">Baggage (Kg)</div>
                        <div class="kpi-value" style="color: #d97706;">{{ number_format($summary['baggage_total'] ?? 0) }}</div>
                        <div class="kpi-sub">Luggage</div>
                    </td>
                    <td class="kpi-cell" style="width: 12.5%;">
                        <div class="kpi-label">Cargo (Kg)</div>
                        <div class="kpi-value" style="color: #4f46e5;">{{ number_format($summary['cargo_total'] ?? 0) }}</div>
                        <div class="kpi-sub">Freight</div>
                    </td>
                @endif

            @elseif ($reportType === 'DAU11')
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Aircraft</div>
                    <div class="kpi-value">{{ number_format($summary['aircraft_total'] ?? 0) }}</div>
                    <div class="kpi-sub">Movements</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Passengers</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                    <div class="kpi-sub">PAX Carried</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Dom Arrival Pax</div>
                    <div class="kpi-value" style="color: #2563eb;">{{ number_format($analytics['dau11_flow']['dom_arr'] ?? 0) }}</div>
                    <div class="kpi-sub">Domestic Inbound</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Dom Departure Pax</div>
                    <div class="kpi-value" style="color: #2563eb;">{{ number_format($analytics['dau11_flow']['dom_dep'] ?? 0) }}</div>
                    <div class="kpi-sub">Domestic Outbound</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Int'l Arrival Pax</div>
                    <div class="kpi-value" style="color: #7c3aed;">{{ number_format($analytics['dau11_flow']['int_arr'] ?? 0) }}</div>
                    <div class="kpi-sub">Int Inbound</div>
                </td>
                <td class="kpi-cell" style="width: 17%;">
                    <div class="kpi-label">Int'l Departure Pax</div>
                    <div class="kpi-value" style="color: #7c3aed;">{{ number_format($analytics['dau11_flow']['int_dep'] ?? 0) }}</div>
                    <div class="kpi-sub">Int Outbound</div>
                </td>

            @elseif ($reportType === 'DAU12')
                @php
                    $m = $analytics['dau12_matrix'] ?? [];
                    $arrAc = ($m['aircraft']['arr_dom'] ?? 0) + ($m['aircraft']['arr_int'] ?? 0);
                    $depAc = ($m['aircraft']['dep_dom'] ?? 0) + ($m['aircraft']['dep_int'] ?? 0);
                    $arrPax = ($m['passenger']['arr_dom'] ?? 0) + ($m['passenger']['arr_int'] ?? 0);
                    $depPax = ($m['passenger']['dep_dom'] ?? 0) + ($m['passenger']['dep_int'] ?? 0);
                @endphp
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Arrival Acft</div>
                    <div class="kpi-value" style="color: #d97706;">{{ number_format($arrAc) }}</div>
                    <div class="kpi-sub">Dom: {{ number_format($m['aircraft']['arr_dom'] ?? 0) }} | Int: {{ number_format($m['aircraft']['arr_int'] ?? 0) }}</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Departure Acft</div>
                    <div class="kpi-value" style="color: #0284c7;">{{ number_format($depAc) }}</div>
                    <div class="kpi-sub">Dom: {{ number_format($m['aircraft']['dep_dom'] ?? 0) }} | Int: {{ number_format($m['aircraft']['dep_int'] ?? 0) }}</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Total Aircraft</div>
                    <div class="kpi-value">{{ number_format($arrAc + $depAc) }}</div>
                    <div class="kpi-sub">All Movements</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Arrival Pax</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($arrPax) }}</div>
                    <div class="kpi-sub">Dom: {{ number_format($m['passenger']['arr_dom'] ?? 0) }} | Int: {{ number_format($m['passenger']['arr_int'] ?? 0) }}</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%;">
                    <div class="kpi-label">Departure Pax</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($depPax) }}</div>
                    <div class="kpi-sub">Dom: {{ number_format($m['passenger']['dep_dom'] ?? 0) }} | Int: {{ number_format($m['passenger']['dep_int'] ?? 0) }}</div>
                </td>
                <td class="kpi-cell" style="width: 17%;">
                    <div class="kpi-label">Total Passengers</div>
                    <div class="kpi-value" style="color: #059669;">{{ number_format($arrPax + $depPax) }}</div>
                    <div class="kpi-sub">All Flights</div>
                </td>
            @endif
        </tr>
    </table>

    {{-- ══ REPORT-SPECIFIC CHARTS / VISUALIZATIONS SECTION ═══════════════════ --}}
    @if ($reportType === 'DAU1')
        @if (($filters['metric'] ?? 'aircraft') === 'passenger')
            {{-- DAU-1: Passenger Mode - Top 10 Routes Passenger Breakdown --}}
            @php
                $topR = array_slice($analytics['top_routes'] ?? ($analytics['dau1_routes'] ?? []), 0, 10);
                $maxPaxVal = 1;
                foreach ($topR as $tr) {
                    $maxPaxVal = max($maxPaxVal, (int)($tr['passenger_total'] ?? 0));
                }
            @endphp
            <div class="chart-box">
                <div class="chart-header" style="display: table; width: 100%;">
                    <div style="display: table-cell; text-align: left;">
                        TOP 10 RUTE / BANDARA — Perincian Penumpang (Dewasa, Anak, Bayi)
                    </div>
                    <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #64748b;">
                        <span style="display: inline-block; width: 8px; height: 8px; background: #2563eb; vertical-align: middle;"></span> Dewasa &nbsp;&nbsp;
                        <span style="display: inline-block; width: 8px; height: 8px; background: #0284c7; vertical-align: middle;"></span> Anak &nbsp;&nbsp;
                        <span style="display: inline-block; width: 8px; height: 8px; background: #a855f7; vertical-align: middle;"></span> Bayi
                    </div>
                </div>
                <table class="chart-table" style="width: 100%;">
                    <tr>
                        @foreach ($topR as $rItem)
                            @php
                                $ad = (int)($rItem['passenger_adult'] ?? $rItem['adult'] ?? ((int)($rItem['arr_adult'] ?? 0) + (int)($rItem['dep_adult'] ?? 0)));
                                $ch = (int)($rItem['passenger_child'] ?? $rItem['child'] ?? ((int)($rItem['arr_child'] ?? 0) + (int)($rItem['dep_child'] ?? 0)));
                                $inf = (int)($rItem['passenger_infant'] ?? $rItem['infant'] ?? ((int)($rItem['arr_infant'] ?? 0) + (int)($rItem['dep_infant'] ?? 0)));
                                $totPax = (int)($rItem['passenger_total'] ?? ($ad + $ch + $inf));
                                $adH = max(2, round(($ad / $maxPaxVal) * 36));
                                $chH = $ch > 0 ? max(1, round(($ch / $maxPaxVal) * 36)) : 0;
                                $infH = $inf > 0 ? max(1, round(($inf / $maxPaxVal) * 36)) : 0;
                            @endphp
                            <td style="width: 10%; vertical-align: bottom; padding: 2px;">
                                <div style="font-size: 5pt; font-weight: bold; margin-bottom: 2px; color: #0f172a;">
                                    {{ number_format($totPax) }}
                                </div>
                                <table style="width: 80%; margin: 0 auto; border-collapse: collapse;">
                                    @if ($infH > 0)
                                        <tr><td style="padding: 0;"><div style="height: {{ $infH }}px; background-color: #a855f7; width: 100%; border-radius: 2px 2px 0 0;"></div></td></tr>
                                    @endif
                                    @if ($chH > 0)
                                        <tr><td style="padding: 0;"><div style="height: {{ $chH }}px; background-color: #0284c7; width: 100%;"></div></td></tr>
                                    @endif
                                    <tr><td style="padding: 0;"><div style="height: {{ $adH }}px; background-color: #2563eb; width: 100%; border-radius: 0 0 2px 2px;"></div></td></tr>
                                </table>
                                <div style="font-size: 5.5pt; font-weight: bold; color: #0f172a; margin-top: 3px;">
                                    {{ $rItem['airport_route'] ?? $rItem['origin'] ?? 'Route' }}
                                </div>
                                <div style="font-size: 4.5pt; color: #64748b;">
                                    D:{{ number_format($ad) }} A:{{ number_format($ch) }} B:{{ number_format($inf) }}
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </table>
            </div>
        @else
            {{-- DAU-1: Aircraft Mode - Top 10 Routes Combo & Cargo/Baggage Composition --}}
            @php
                $topR = $analytics['dau1_routes'] ?? [];
                $maxRVal = 1;
                foreach ($topR as $tr) {
                    $maxRVal = max($maxRVal, $tr['aircraft_arrival'] ?? 0, $tr['aircraft_departure'] ?? 0);
                }
            @endphp
            <div class="chart-box">
                <div class="chart-header" style="display: table; width: 100%;">
                    <div style="display: table-cell; text-align: left;">
                        TOP 10 RUTE / BANDARA — Perbandingan Pergerakan Pesawat ARR vs DEP &amp; Penumpang
                    </div>
                    <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #64748b;">
                        <span style="display: inline-block; width: 8px; height: 8px; background: #f59e0b; vertical-align: middle;"></span> ARR Acft &nbsp;&nbsp;
                        <span style="display: inline-block; width: 8px; height: 8px; background: #0284c7; vertical-align: middle;"></span> DEP Acft
                    </div>
                </div>
                <table class="chart-table" style="width: 100%;">
                    <tr>
                        @foreach ($topR as $rItem)
                            @php
                                $aH = max(2, round((($rItem['aircraft_arrival'] ?? 0) / $maxRVal) * 44));
                                $dH = max(2, round((($rItem['aircraft_departure'] ?? 0) / $maxRVal) * 44));
                            @endphp
                            <td style="width: 10%; vertical-align: bottom; padding: 2px;">
                                <div style="font-size: 5pt; font-weight: bold; margin-bottom: 2px;">
                                    <span style="color: #d97706;">{{ $rItem['aircraft_arrival'] ?? 0 }}</span> /
                                    <span style="color: #0284c7;">{{ $rItem['aircraft_departure'] ?? 0 }}</span>
                                </div>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="width: 50%; padding: 0; vertical-align: bottom;">
                                            <div style="height: {{ $aH }}px; background-color: #f59e0b; width: 80%; margin: 0 auto; border-radius: 2px 2px 0 0;"></div>
                                        </td>
                                        <td style="width: 50%; padding: 0; vertical-align: bottom;">
                                            <div style="height: {{ $dH }}px; background-color: #0284c7; width: 80%; margin: 0 auto; border-radius: 2px 2px 0 0;"></div>
                                        </td>
                                    </tr>
                                </table>
                                <div style="font-size: 5.5pt; font-weight: bold; color: #0f172a; margin-top: 3px;">
                                    {{ $rItem['airport_route'] ?? $rItem['origin'] ?? 'Route' }}
                                </div>
                                <div style="font-size: 5pt; color: #059669; font-weight: bold;">
                                    {{ number_format($rItem['passenger_total'] ?? 0) }} pax
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </table>
            </div>
        @endif

    @elseif ($reportType === 'DAU2')
        {{-- DAU-2: Comparative Breakdown Table + 100% Stacked Bar Comparison --}}
        @php
            $d2Comp = $analytics['dau2_comparative'] ?? [];
            $activeM = $filters['metric'] ?? 'aircraft';
            $compRows = [
                'aircraft' => $d2Comp['aircraft'] ?? ['metric' => 'Aircraft Movements', 'unit' => 'A/C', 'domestic' => 0, 'international' => 0, 'total' => 0, 'domestic_pct' => 0, 'international_pct' => 0],
                'passenger' => $d2Comp['passenger'] ?? ['metric' => 'Passengers (PAX)', 'unit' => 'PAX', 'domestic' => 0, 'international' => 0, 'total' => 0, 'domestic_pct' => 0, 'international_pct' => 0],
                'baggage' => $d2Comp['baggage'] ?? ['metric' => 'Baggage Volume', 'unit' => 'KG', 'domestic' => 0, 'international' => 0, 'total' => 0, 'domestic_pct' => 0, 'international_pct' => 0],
                'cargo' => $d2Comp['cargo'] ?? ['metric' => 'Cargo Volume', 'unit' => 'KG', 'domestic' => 0, 'international' => 0, 'total' => 0, 'domestic_pct' => 0, 'international_pct' => 0],
                'pos' => $d2Comp['pos'] ?? ['metric' => 'POS / Mail Volume', 'unit' => 'KG', 'domestic' => 0, 'international' => 0, 'total' => 0, 'domestic_pct' => 0, 'international_pct' => 0],
            ];
        @endphp
        <div class="chart-box">
            <div class="chart-header">
                COMPARATIVE BREAKDOWN — DOMESTIK VS INTERNASIONAL (SELURUH METRIK OPERASIONAL)
            </div>
            <table class="data-table" style="margin-top: 3px; font-size: 6.5pt;">
                <thead>
                    <tr>
                        <th style="width: 32%; text-align: left;">Metrik Operasional</th>
                        <th class="text-right" style="width: 17%; color: #93c5fd;">Domestik</th>
                        <th class="text-right" style="width: 17%; color: #c4b5fd;">Internasional</th>
                        <th class="text-right" style="width: 17%;">Total</th>
                        <th class="text-right" style="width: 17%; color: #fde68a;">Proporsi Dom / Int</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($compRows as $k => $cRow)
                        <tr @if ($activeM === $k) style="background-color: #f0f9ff; font-weight: bold;" @endif>
                            <td class="text-left font-bold">
                                @if ($activeM === $k)
                                    <span style="color: #0284c7;">&bull;</span>
                                @endif
                                {{ $cRow['metric'] ?? $cRow['label'] ?? ucfirst($k) }} <span style="font-size: 5.5pt; color: #64748b;">({{ $cRow['unit'] ?? '' }})</span>
                            </td>
                            <td class="text-right" style="color: #2563eb;">{{ number_format($cRow['domestic'] ?? 0) }} <span style="font-size: 5pt; color: #64748b;">({{ $cRow['domestic_pct'] ?? 0 }}%)</span></td>
                            <td class="text-right" style="color: #7c3aed;">{{ number_format($cRow['international'] ?? 0) }} <span style="font-size: 5pt; color: #64748b;">({{ $cRow['international_pct'] ?? 0 }}%)</span></td>
                            <td class="text-right font-bold">{{ number_format($cRow['total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ $cRow['domestic_pct'] ?? 0 }}% / {{ $cRow['international_pct'] ?? 0 }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="font-size: 6pt; font-weight: bold; color: #475569; margin: 6px 0 2px 0;">
                KOMPOSISI 100% STACKED BAR — DOMESTIK VS INTERNASIONAL
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 6pt;">
                @foreach ($compRows as $k => $cRow)
                    <tr>
                        <td style="width: 25%; font-weight: bold; padding: 2px 0;">{{ strtoupper($cRow['metric'] ?? $cRow['label'] ?? $k) }}</td>
                        <td style="width: 55%; padding: 2px 4px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    @if ($cRow['domestic_pct'] > 0)
                                        <td style="width: {{ $cRow['domestic_pct'] }}%; background: #2563eb; color: #ffffff; font-size: 4.5pt; font-weight: bold; text-align: center; padding: 1.5px 0;">
                                            {{ $cRow['domestic_pct'] }}%
                                        </td>
                                    @endif
                                    @if ($cRow['international_pct'] > 0)
                                        <td style="width: {{ $cRow['international_pct'] }}%; background: #7c3aed; color: #ffffff; font-size: 4.5pt; font-weight: bold; text-align: center; padding: 1.5px 0;">
                                            {{ $cRow['international_pct'] }}%
                                        </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                        <td style="width: 20%; text-align: right; padding: 2px 0; font-size: 5.5pt;">
                            <span style="color: #2563eb; font-weight: bold;">{{ number_format($cRow['domestic']) }}</span> /
                            <span style="color: #7c3aed; font-weight: bold;">{{ number_format($cRow['international']) }}</span>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>

    @elseif ($reportType === 'DAU3')
        {{-- DAU-3: Status Penerbangan Breakdown --}}
        @php
            $s3 = $analytics['dau3_status'] ?? [];
            $totAc3 = max(1, ($s3['niaga']['aircraft'] ?? 0) + ($s3['bukan_niaga']['aircraft'] ?? 0));
            $niagaPct = round((($s3['niaga']['aircraft'] ?? 0) / $totAc3) * 100, 1);
            $bukanPct = round(100 - $niagaPct, 1);
        @endphp
        <div class="chart-box">
            <div class="chart-header">
                KOMPOSISI STATUS USAHA PENERBANGAN (NIAGA VS BUKAN NIAGA)
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 7pt; margin-top: 2px;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding-right: 8px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 2px 0; font-weight: bold;">Penerbangan Niaga (Commercial):</td>
                                <td style="text-align: right; font-weight: bold; color: #0284c7;">{{ number_format($s3['niaga']['aircraft'] ?? 0) }} acft ({{ $niagaPct }}%)</td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding: 2px 0;">
                                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                        <div style="width: {{ $niagaPct }}%; height: 100%; background: #0284c7;"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 2px 0; font-weight: bold; margin-top: 4px;">Penerbangan Bukan Niaga (Non-Commercial):</td>
                                <td style="text-align: right; font-weight: bold; color: #7c3aed;">{{ number_format($s3['bukan_niaga']['aircraft'] ?? 0) }} acft ({{ $bukanPct }}%)</td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding: 2px 0;">
                                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                        <div style="width: {{ $bukanPct }}%; height: 100%; background: #7c3aed;"></div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 50%; vertical-align: top; border-left: 1px solid #e2e8f0; padding-left: 8px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 2px 0; font-weight: bold;">Penumpang Niaga:</td>
                                <td style="text-align: right; font-weight: bold; color: #059669;">{{ number_format($s3['niaga']['passenger'] ?? 0) }} pax</td>
                            </tr>
                            <tr>
                                <td style="padding: 2px 0; font-weight: bold;">Penumpang Bukan Niaga:</td>
                                <td style="text-align: right; font-weight: bold; color: #059669;">{{ number_format($s3['bukan_niaga']['passenger'] ?? 0) }} pax</td>
                            </tr>
                            <tr>
                                <td style="padding: 2px 0; font-weight: bold;">Total Penumpang Seluruh Kategori:</td>
                                <td style="text-align: right; font-weight: bold; color: #0f172a;">{{ number_format(($s3['niaga']['passenger'] ?? 0) + ($s3['bukan_niaga']['passenger'] ?? 0)) }} pax</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

    @elseif ($reportType === 'DAU4')
        {{-- DAU-4: Bi-Directional Diverging Origin vs Destination --}}
        @php
            $topArr = $analytics['dau4_diverging']['top_arrival'] ?? [];
            $topDep = $analytics['dau4_diverging']['top_departure'] ?? [];
            $maxDiverg = 1;
            foreach ($topArr as $aItem) $maxDiverg = max($maxDiverg, $aItem['aircraft_arrival'] ?? 0);
            foreach ($topDep as $dItem) $maxDiverg = max($maxDiverg, $dItem['aircraft_departure'] ?? 0);
            $countRows = max(count($topArr), count($topDep));
        @endphp
        <div class="chart-box">
            <div class="chart-header" style="display: table; width: 100%;">
                <div style="display: table-cell; text-align: left;">
                    BI-DIRECTIONAL ORIGIN &amp; DESTINATION ANALYSIS (TOP ROUTES)
                </div>
                <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #64748b;">
                    <span style="color: #d97706; font-weight: bold;">&larr; TOP ORIGIN (ARRIVAL)</span> &nbsp;|&nbsp;
                    <span style="color: #0284c7; font-weight: bold;">TOP DESTINATION (DEPARTURE) &rarr;</span>
                </div>
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 6pt;">
                @for ($i = 0; $i < min(8, $countRows); $i++)
                    @php
                        $aR = $topArr[$i] ?? null;
                        $dR = $topDep[$i] ?? null;
                        $aW = $aR ? min(100, round((($aR['aircraft_arrival'] ?? 0) / $maxDiverg) * 100)) : 0;
                        $dW = $dR ? min(100, round((($dR['aircraft_departure'] ?? 0) / $maxDiverg) * 100)) : 0;
                    @endphp
                    <tr>
                        {{-- Left: ARR --}}
                        <td style="width: 18%; text-align: right; font-weight: bold; padding: 1.5px 4px;">
                            {{ $aR['city'] ?? $aR['airport'] ?? '—' }} ({{ $aR['city_code'] ?? '—' }})
                        </td>
                        <td style="width: 6%; text-align: right; font-weight: bold; color: #d97706; padding: 1.5px 4px;">
                            {{ $aR['aircraft_arrival'] ?? 0 }}
                        </td>
                        <td style="width: 24%; padding: 1.5px 2px;">
                            <div style="width: 100%; background: #f1f5f9; height: 7px; border-radius: 2px;">
                                <div style="float: right; width: {{ $aW }}%; background: #f59e0b; height: 100%; border-radius: 2px;"></div>
                            </div>
                        </td>

                        {{-- Center Divider --}}
                        <td style="width: 4%; text-align: center; color: #94a3b8; font-weight: bold;">#{{ $i+1 }}</td>

                        {{-- Right: DEP --}}
                        <td style="width: 24%; padding: 1.5px 2px;">
                            <div style="width: 100%; background: #f1f5f9; height: 7px; border-radius: 2px;">
                                <div style="float: left; width: {{ $dW }}%; background: #0284c7; height: 100%; border-radius: 2px;"></div>
                            </div>
                        </td>
                        <td style="width: 6%; text-align: left; font-weight: bold; color: #0284c7; padding: 1.5px 4px;">
                            {{ $dR['aircraft_departure'] ?? 0 }}
                        </td>
                        <td style="width: 18%; text-align: left; font-weight: bold; padding: 1.5px 4px;">
                            {{ $dR['city'] ?? $dR['airport'] ?? '—' }} ({{ $dR['city_code'] ?? '—' }})
                        </td>
                    </tr>
                @endfor
            </table>
        </div>

    @elseif ($reportType === 'DAU4B')
        {{-- DAU-4B: Matrix Heatmap Sample in PDF --}}
        @php
            $m4b = $analytics['dau4b_matrix'] ?? [];
            $cList = array_slice($m4b['cities'] ?? [], 0, 10);
            $aList = array_slice($m4b['airlines'] ?? [], 0, 8);
            $grid = $m4b['grid'] ?? [];
            $maxMVal = 1;
            foreach ($cList as $c) {
                foreach ($aList as $a) {
                    $maxMVal = max($maxMVal, $grid[$c][$a] ?? 0);
                }
            }
        @endphp
        <div class="chart-box">
            <div class="chart-header">
                AIRLINE × AIRPORT FREQUENCY MATRIX HEATMAP (Top Routes &amp; Carriers)
            </div>
            <table class="heatmap-table">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 20%;">Kota / Rute</th>
                        @foreach ($aList as $alName)
                            <th style="width: {{ 80 / max(1, count($aList)) }}%;">{{ substr($alName, 0, 10) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cList as $cName)
                        <tr>
                            <td class="text-left" style="background: #f8fafc; font-weight: bold;">{{ $cName }}</td>
                            @foreach ($aList as $alName)
                                @php
                                    $val = $grid[$cName][$alName] ?? 0;
                                    $bg = '#ffffff';
                                    $txt = '#1e293b';
                                    if ($val > 0) {
                                        $ratio = $val / $maxMVal;
                                        if ($ratio < 0.25) $bg = '#dbeafe';
                                        elseif ($ratio < 0.50) $bg = '#93c5fd';
                                        elseif ($ratio < 0.75) { $bg = '#3b82f6'; $txt = '#ffffff'; }
                                        else { $bg = '#1d4ed8'; $txt = '#ffffff'; }
                                    }
                                @endphp
                                <td style="background: {{ $bg }}; color: {{ $txt }}; font-size: 5.5pt;">
                                    {{ $val > 0 ? $val : '-' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @elseif ($reportType === 'DAU5')
        {{-- DAU-5: Pareto Airlines --}}
        @php
            $pareto = array_slice($analytics['dau5_pareto'] ?? [], 0, 10);
            $maxPV = 1;
            foreach ($pareto as $pItem) $maxPV = max($maxPV, $pItem['metric_value'] ?? 0);
        @endphp
        <div class="chart-box">
            <div class="chart-header" style="display: table; width: 100%;">
                <div style="display: table-cell; text-align: left;">
                    PARETO AIRLINE TRAFFIC DISTRIBUTION (Top Carriers &amp; Cumulative Percentage)
                </div>
                <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #64748b;">
                    <span style="display: inline-block; width: 8px; height: 8px; background: #0284c7; vertical-align: middle;"></span> Volume &nbsp;&nbsp;
                    <span style="color: #7c3aed; font-weight: bold;">Line: Cumulative %</span>
                </div>
            </div>
            <table class="chart-table" style="width: 100%;">
                <tr>
                    @foreach ($pareto as $pItem)
                        @php
                            $bH = max(2, round((($pItem['metric_value'] ?? 0) / $maxPV) * 42));
                        @endphp
                        <td style="width: 10%; vertical-align: bottom; padding: 2px;">
                            <div style="font-size: 5pt; font-weight: bold; color: #7c3aed; margin-bottom: 2px;">
                                {{ $pItem['cumulative_pct'] }}%
                            </div>
                            <div class="bar-wrap">
                                <div class="bar-inner" style="height: {{ $bH }}px; background-color: #0284c7;"></div>
                            </div>
                            <div style="font-size: 5pt; font-weight: bold; color: #0284c7; margin-top: 2px;">
                                {{ number_format($pItem['metric_value'] ?? 0) }}
                            </div>
                            <div style="font-size: 5pt; color: #1e293b; font-weight: bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ substr($pItem['airline'] ?? 'Airline', 0, 11) }}
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>

    @elseif ($reportType === 'DAU5A')
        {{-- DAU-5A: Operating Crew vs Extra Crew --}}
        @php
            $crewList = array_slice($analytics['dau5a_crew'] ?? [], 0, 10);
            $maxCrew = 1;
            foreach ($crewList as $cItem) $maxCrew = max($maxCrew, $cItem['operating_crew'], $cItem['extra_crew']);
        @endphp
        <div class="chart-box">
            <div class="chart-header" style="display: table; width: 100%;">
                <div style="display: table-cell; text-align: left;">
                    PERBANDINGAN OPERATING CREW VS EXTRA CREW PER MASKAPAI
                </div>
                <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #64748b;">
                    <span style="display: inline-block; width: 8px; height: 8px; background: #2563eb; vertical-align: middle;"></span> Operating Crew &nbsp;&nbsp;
                    <span style="display: inline-block; width: 8px; height: 8px; background: #9333ea; vertical-align: middle;"></span> Extra Crew
                </div>
            </div>
            <table class="chart-table" style="width: 100%;">
                <tr>
                    @foreach ($crewList as $cItem)
                        @php
                            $opH = max(2, round((($cItem['operating_crew'] ?? 0) / $maxCrew) * 42));
                            $exH = max(2, round((($cItem['extra_crew'] ?? 0) / $maxCrew) * 42));
                        @endphp
                        <td style="width: 10%; vertical-align: bottom; padding: 2px;">
                            <div style="font-size: 5pt; font-weight: bold; margin-bottom: 2px;">
                                <span style="color: #2563eb;">{{ $cItem['operating_crew'] }}</span> /
                                <span style="color: #9333ea;">{{ $cItem['extra_crew'] }}</span>
                            </div>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 50%; padding: 0; vertical-align: bottom;">
                                        <div style="height: {{ $opH }}px; background-color: #2563eb; width: 80%; margin: 0 auto; border-radius: 2px 2px 0 0;"></div>
                                    </td>
                                    <td style="width: 50%; padding: 0; vertical-align: bottom;">
                                        <div style="height: {{ $exH }}px; background-color: #9333ea; width: 80%; margin: 0 auto; border-radius: 2px 2px 0 0;"></div>
                                    </td>
                                </tr>
                            </table>
                            <div style="font-size: 5pt; color: #1e293b; font-weight: bold; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ substr($cItem['airline'] ?? 'Airline', 0, 11) }}
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>

    @elseif ($reportType === 'DAU6')
        {{-- DAU-6: Fleet Mix ranking & Category share --}}
        @php
            $fleetList = array_slice($analytics['dau6_fleet']['types'] ?? [], 0, 10);
            $maxFleet = 1;
            foreach ($fleetList as $fItem) $maxFleet = max($maxFleet, $fItem['aircraft_total'] ?? 0);
        @endphp
        <div class="chart-box">
            <div class="chart-header">
                KOMPOSISI TIPE PESAWAT (FLEET MIX) &amp; WTC
            </div>
            <table class="chart-table" style="width: 100%;">
                <tr>
                    @foreach ($fleetList as $fItem)
                        @php
                            $fH = max(2, round((($fItem['aircraft_total'] ?? 0) / $maxFleet) * 44));
                        @endphp
                        <td style="width: 10%; vertical-align: bottom; padding: 2px;">
                            <div style="font-size: 5pt; font-weight: bold; color: #0284c7; margin-bottom: 2px;">
                                {{ number_format($fItem['aircraft_total'] ?? 0) }}
                            </div>
                            <div class="bar-wrap">
                                <div class="bar-inner" style="height: {{ $fH }}px; background-color: #0284c7;"></div>
                            </div>
                            <div style="font-size: 5.5pt; font-weight: bold; color: #0f172a; margin-top: 3px;">
                                {{ $fItem['aircraft_type'] ?? 'Type' }}
                            </div>
                            <div style="font-size: 4.8pt; color: #64748b;">
                                {{ $fItem['category'] ?? 'Narrow' }} • {{ $fItem['wtc'] ?? 'M' }}
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>

    @elseif ($reportType === 'DAU10')
        {{-- DAU-10 Hourly Movements Vector Bar Chart --}}
        @php
            $maxHourlyAc = 1;
            foreach ($hourlyData as $hd) {
                if ($hd['aircraft_total'] > $maxHourlyAc) $maxHourlyAc = $hd['aircraft_total'];
            }
        @endphp
        <div class="chart-box">
            <div class="chart-header">
                Hourly Aircraft Movement Distribution (00:00 - 24:00) — Total Pergerakan per Jam
            </div>
            <table class="chart-table">
                <tr>
                    @foreach ($hourlyData as $hd)
                        @php
                            $hRatio = min(1, $hd['aircraft_total'] / max(1, $maxHourlyAc));
                            $barHeight = max(3, round($hRatio * 44));
                            $isPeak = ($hd['hour'] === ($peaks['peak_aircraft_hour'] ?? ''));
                        @endphp
                        <td style="width: {{ 100 / max(1, count($hourlyData)) }}%;">
                            <div style="font-size: 5.5pt; font-weight: bold; margin-bottom: 1px; color: {{ $isPeak ? '#b45309' : '#1e293b' }};">
                                {{ $hd['aircraft_total'] }}
                            </div>
                            <div class="bar-wrap">
                                <div class="bar-inner" style="height: {{ $barHeight }}px; background-color: {{ $isPeak ? '#f59e0b' : '#0284c7' }};"></div>
                            </div>
                            <div style="font-size: 5pt; color: #64748b; margin-top: 2px;">
                                {{ explode(' - ', $hd['hour'])[0] ?? $hd['hour'] }}
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>

    @elseif ($reportType === 'DAU10A')
        {{-- DAU-10A DISTRIBUSI PER JAM & HOURLY STATUS (METRIC SEPARATED) --}}
        @php
            $metricMode = strtolower($metric ?? 'aircraft');
            $arrNacVal = (int)($arrNac ?? ($capacitySummary['arr_nac'] ?? 6));
            $depNacVal = (int)($depNac ?? ($capacitySummary['dep_nac'] ?? 6));
            $opsStartStr = $opsStart ?? '00:00';
            $opsEndStr = $opsEnd ?? '24:00';

            if ($metricMode === 'passenger') {
                $maxAmpPdf = 1;
                foreach ($hourlyData as $hd) {
                    $a = (int)($hd['passenger_arrival'] ?? 0);
                    $d = (int)($hd['passenger_departure'] ?? 0);
                    if ($a > $maxAmpPdf) $maxAmpPdf = $a;
                    if ($d > $maxAmpPdf) $maxAmpPdf = $d;
                }
            } elseif ($metricMode === 'crew') {
                $maxAmpPdf = 1;
                foreach ($hourlyData as $hd) {
                    $a = (int)($hd['crew'] ?? 0);
                    $d = (int)($hd['extra_crew'] ?? 0);
                    if ($a > $maxAmpPdf) $maxAmpPdf = $a;
                    if ($d > $maxAmpPdf) $maxAmpPdf = $d;
                }
            } else {
                $maxAmpPdf = max($arrNacVal, $depNacVal, 1);
                foreach ($hourlyData as $hd) {
                    $a = (int)($hd['aircraft_arrival'] ?? 0);
                    $d = (int)($hd['aircraft_departure'] ?? 0);
                    if ($a > $maxAmpPdf) $maxAmpPdf = $a;
                    if ($d > $maxAmpPdf) $maxAmpPdf = $d;
                }
            }
        @endphp

        @if ($metricMode === 'passenger')
            {{-- PASSENGER MODE: NO AIRCRAFT CAPACITY ENVELOPE --}}
            <div class="chart-box">
                <div class="chart-header" style="display: table; width: 100%;">
                    <div style="display: table-cell; text-align: left;">
                        DISTRIBUSI PER JAM — Hourly Passenger Distribution
                    </div>
                    <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #475569;">
                        <span style="display: inline-block; width: 7px; height: 7px; background: #059669; vertical-align: middle;"></span> Pax ARR &uarr; &nbsp;
                        <span style="display: inline-block; width: 7px; height: 7px; background: #0284c7; vertical-align: middle;"></span> Pax DEP &darr;
                    </div>
                </div>

                <table class="chart-table" style="border-collapse: collapse; width: 100%;">
                    {{-- UPPER SECTION: PASSENGER ARRIVALS (+Y) --}}
                    <tr>
                        @foreach ($hourlyData as $hd)
                            @php
                                $arrVal = (int)($hd['passenger_arrival'] ?? 0);
                                $arrH = $arrVal > 0 ? max(3, round(($arrVal / $maxAmpPdf) * 32)) : 0;
                            @endphp
                            <td style="width: {{ 100 / max(1, count($hourlyData)) }}%; vertical-align: bottom; text-align: center; padding: 1px 1px 0 1px;">
                                @if($arrVal > 0)
                                    <div style="font-size: 4pt; font-weight: bold; color: #059669; margin-bottom: 1px;">
                                        {{ number_format($arrVal) }}
                                    </div>
                                    <div style="height: {{ $arrH }}px; background-color: #10b981; width: 70%; margin: 0 auto; border-radius: 1px 1px 0 0;"></div>
                                @else
                                    <div style="height: 1px; background-color: #cbd5e1; width: 50%; margin: 0 auto;"></div>
                                @endif
                            </td>
                        @endforeach
                    </tr>

                    {{-- CENTER TIME AXIS --}}
                    <tr>
                        @foreach ($hourlyData as $hd)
                            <td style="text-align: center; font-size: 5pt; font-weight: bold; font-family: monospace; padding: 2px 0; background-color: #f1f5f9; border-top: 1px solid #94a3b8; border-bottom: 1px solid #94a3b8; color: #334155;">
                                {{ explode(' - ', $hd['hour'])[0] ?? $hd['hour'] }}
                            </td>
                        @endforeach
                    </tr>

                    {{-- LOWER SECTION: PASSENGER DEPARTURES (-Y) --}}
                    <tr>
                        @foreach ($hourlyData as $hd)
                            @php
                                $depVal = (int)($hd['passenger_departure'] ?? 0);
                                $depH = $depVal > 0 ? max(3, round(($depVal / $maxAmpPdf) * 32)) : 0;
                            @endphp
                            <td style="width: {{ 100 / max(1, count($hourlyData)) }}%; vertical-align: top; text-align: center; padding: 0 1px 1px 1px;">
                                @if($depVal > 0)
                                    <div style="height: {{ $depH }}px; background-color: #0284c7; width: 70%; margin: 0 auto; border-radius: 0 0 1px 1px;"></div>
                                    <div style="font-size: 4pt; font-weight: bold; color: #0284c7; margin-top: 1px;">
                                        {{ number_format($depVal) }}
                                    </div>
                                @else
                                    <div style="height: 1px; background-color: #cbd5e1; width: 50%; margin: 0 auto;"></div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            </div>

            {{-- Hourly Passenger Distribution Table --}}
            <div class="chart-box" style="margin-top: 5px;">
                <div class="chart-header">
                    HOURLY PASSENGER DISTRIBUTION
                </div>
                <table class="data-table" style="margin-top: 1px;">
                    <thead>
                        <tr>
                            <th style="width: 15%; text-align: left;">Hour</th>
                            <th class="text-right" style="width: 18%; color: #a7f3d0;">Passenger ARR</th>
                            <th class="text-right" style="width: 18%; color: #38bdf8;">Passenger DEP</th>
                            <th class="text-right" style="width: 15%;">Transit</th>
                            <th class="text-right" style="width: 15%;">Transfer</th>
                            <th class="text-right" style="width: 19%; color: #fde68a;">Total Passenger</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hourlyCapacityStatus as $hcs)
                            <tr>
                                <td class="font-bold text-left">{{ $hcs['hour'] }}</td>
                                <td class="text-right font-bold" style="color: #059669;">{{ number_format($hcs['arr']) }}</td>
                                <td class="text-right font-bold" style="color: #0284c7;">{{ number_format($hcs['dep']) }}</td>
                                <td class="text-right" style="color: #64748b;">{{ number_format($hcs['transit'] ?? 0) }}</td>
                                <td class="text-right" style="color: #64748b;">{{ number_format($hcs['transfer'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #d97706;">{{ number_format($hcs['demand']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @elseif ($metricMode === 'crew')
            {{-- CREW MODE: NO AIRCRAFT CAPACITY ENVELOPE --}}
            <div class="chart-box">
                <div class="chart-header" style="display: table; width: 100%;">
                    <div style="display: table-cell; text-align: left;">
                        DISTRIBUSI PER JAM — Hourly Crew Distribution
                    </div>
                    <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #475569;">
                        <span style="display: inline-block; width: 7px; height: 7px; background: #2563eb; vertical-align: middle;"></span> Operating Crew (ARR) &uarr; &nbsp;
                        <span style="display: inline-block; width: 7px; height: 7px; background: #7c3aed; vertical-align: middle;"></span> Extra Crew (DEP) &darr;
                    </div>
                </div>

                <table class="chart-table" style="border-collapse: collapse; width: 100%;">
                    {{-- UPPER SECTION: OPERATING CREW (+Y) --}}
                    <tr>
                        @foreach ($hourlyData as $hd)
                            @php
                                $arrVal = (int)($hd['crew'] ?? 0);
                                $arrH = $arrVal > 0 ? max(3, round(($arrVal / $maxAmpPdf) * 32)) : 0;
                            @endphp
                            <td style="width: {{ 100 / max(1, count($hourlyData)) }}%; vertical-align: bottom; text-align: center; padding: 1px 1px 0 1px;">
                                @if($arrVal > 0)
                                    <div style="font-size: 4.5pt; font-weight: bold; color: #2563eb; margin-bottom: 1px;">
                                        {{ $arrVal }}
                                    </div>
                                    <div style="height: {{ $arrH }}px; background-color: #2563eb; width: 70%; margin: 0 auto; border-radius: 1px 1px 0 0;"></div>
                                @else
                                    <div style="height: 1px; background-color: #cbd5e1; width: 50%; margin: 0 auto;"></div>
                                @endif
                            </td>
                        @endforeach
                    </tr>

                    {{-- CENTER TIME AXIS --}}
                    <tr>
                        @foreach ($hourlyData as $hd)
                            <td style="text-align: center; font-size: 5pt; font-weight: bold; font-family: monospace; padding: 2px 0; background-color: #f1f5f9; border-top: 1px solid #94a3b8; border-bottom: 1px solid #94a3b8; color: #334155;">
                                {{ explode(' - ', $hd['hour'])[0] ?? $hd['hour'] }}
                            </td>
                        @endforeach
                    </tr>

                    {{-- LOWER SECTION: EXTRA CREW (-Y) --}}
                    <tr>
                        @foreach ($hourlyData as $hd)
                            @php
                                $depVal = (int)($hd['extra_crew'] ?? 0);
                                $depH = $depVal > 0 ? max(3, round(($depVal / $maxAmpPdf) * 32)) : 0;
                            @endphp
                            <td style="width: {{ 100 / max(1, count($hourlyData)) }}%; vertical-align: top; text-align: center; padding: 0 1px 1px 1px;">
                                @if($depVal > 0)
                                    <div style="height: {{ $depH }}px; background-color: #7c3aed; width: 70%; margin: 0 auto; border-radius: 0 0 1px 1px;"></div>
                                    <div style="font-size: 4.5pt; font-weight: bold; color: #7c3aed; margin-top: 1px;">
                                        {{ $depVal }}
                                    </div>
                                @else
                                    <div style="height: 1px; background-color: #cbd5e1; width: 50%; margin: 0 auto;"></div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            </div>

            {{-- Hourly Crew Distribution Table --}}
            <div class="chart-box" style="margin-top: 5px;">
                <div class="chart-header">
                    HOURLY CREW DISTRIBUTION
                </div>
                <table class="data-table" style="margin-top: 1px;">
                    <thead>
                        <tr>
                            <th style="width: 25%; text-align: left;">Hour</th>
                            <th class="text-right" style="width: 25%; color: #93c5fd;">Operating Crew (ARR)</th>
                            <th class="text-right" style="width: 25%; color: #c4b5fd;">Extra Crew (DEP)</th>
                            <th class="text-right" style="width: 25%; color: #fde68a;">Total Crew</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hourlyCapacityStatus as $hcs)
                            <tr>
                                <td class="font-bold text-left">{{ $hcs['hour'] }}</td>
                                <td class="text-right font-bold" style="color: #2563eb;">{{ number_format($hcs['arr']) }}</td>
                                <td class="text-right font-bold" style="color: #7c3aed;">{{ number_format($hcs['dep']) }}</td>
                                <td class="text-right font-bold" style="color: #0f172a;">{{ number_format($hcs['demand']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @else
            {{-- AIRCRAFT MODE: TWO-DIRECTION CAPACITY ENVELOPE & STATUS --}}
            <div class="chart-box">
                <div class="chart-header" style="display: table; width: 100%;">
                    <div style="display: table-cell; text-align: left;">
                        DISTRIBUSI PER JAM — Two-Direction Aircraft Capacity Envelope (ARR Cap: {{ $arrNacVal }} A/C | DEP Cap: {{ $depNacVal }} A/C | Ops: {{ $opsStartStr }}-{{ $opsEndStr }})
                    </div>
                    <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #475569;">
                        <span style="display: inline-block; width: 7px; height: 7px; background: #f59e0b; vertical-align: middle;"></span> ARR &uarr; &nbsp;
                        <span style="display: inline-block; width: 7px; height: 7px; background: #0284c7; vertical-align: middle;"></span> DEP &darr; &nbsp;
                        <span style="display: inline-block; width: 8px; height: 8px; border: 1.5px dashed #059669; vertical-align: middle;"></span> Ops Hours &nbsp;
                        <span style="display: inline-block; width: 14px; border-top: 1.5px dashed #d97706; vertical-align: middle;"></span> +ARR Cap ({{ $arrNacVal }}) &nbsp;
                        <span style="display: inline-block; width: 14px; border-top: 1.5px dashed #0284c7; vertical-align: middle;"></span> -DEP Cap ({{ $depNacVal }})
                    </div>
                </div>

                <table class="chart-table" style="border-collapse: collapse; width: 100%;">
                    {{-- UPPER SECTION: ARRIVALS (+Y, Grows UPWARD) --}}
                    <tr>
                        @foreach ($hourlyData as $hd)
                            @php
                                $arrVal = (int)($hd['aircraft_arrival'] ?? 0);
                                $depVal = (int)($hd['aircraft_departure'] ?? 0);
                                $demVal = $arrVal + $depVal;
                                $arrH = $arrVal > 0 ? max(3, round(($arrVal / $maxAmpPdf) * 32)) : 0;

                                $isOffHour = false;
                                if ($opsStartStr !== '00:00' || ($opsEndStr !== '24:00' && $opsEndStr !== '23:59')) {
                                    $hNum = (int)explode(':', explode(' - ', $hd['hour'])[0] ?? $hd['hour'])[0];
                                    $sNum = (int)explode(':', $opsStartStr)[0];
                                    $eNum = (int)explode(':', $opsEndStr)[0];
                                    if ($hNum < $sNum || $hNum >= $eNum) {
                                        $isOffHour = true;
                                    }
                                }

                                if ($isOffHour) {
                                    $statusText = 'OFF';
                                    $statusCol = '#64748b';
                                } elseif ($arrVal > $arrNacVal || $depVal > $depNacVal) {
                                    $statusText = 'OVER';
                                    $statusCol = '#7c3aed';
                                } elseif ($arrVal === $arrNacVal || $depVal === $depNacVal) {
                                    $statusText = 'FULL';
                                    $statusCol = '#d97706';
                                } else {
                                    $statusText = 'AVAIL';
                                    $statusCol = '#059669';
                                }
                            @endphp
                            <td style="width: {{ 100 / max(1, count($hourlyData)) }}%; vertical-align: bottom; text-align: center; padding: 1px 1px 0 1px; background-color: {{ $isOffHour ? '#f8fafc' : '#ffffff' }};">
                                <div style="font-size: 4.5pt; font-weight: bold; color: {{ $statusCol }}; margin-bottom: 1px;">
                                    {{ $statusText }}
                                </div>
                                @if($arrVal > 0)
                                    <div style="font-size: 4.5pt; font-weight: bold; color: #d97706; margin-bottom: 1px;">
                                        {{ $arrVal }}
                                    </div>
                                    <div style="height: {{ $arrH }}px; background-color: #f59e0b; width: 70%; margin: 0 auto; border-radius: 1px 1px 0 0;"></div>
                                @else
                                    <div style="height: 1px; background-color: #cbd5e1; width: 50%; margin: 0 auto;"></div>
                                @endif
                            </td>
                        @endforeach
                    </tr>

                    {{-- CENTER TIME AXIS (Y=0 Separator) --}}
                    <tr>
                        @foreach ($hourlyData as $hd)
                            @php
                                $isOffH = false;
                                if ($opsStartStr !== '00:00' || ($opsEndStr !== '24:00' && $opsEndStr !== '23:59')) {
                                    $hNum = (int)explode(':', explode(' - ', $hd['hour'])[0] ?? $hd['hour'])[0];
                                    $sNum = (int)explode(':', $opsStartStr)[0];
                                    $eNum = (int)explode(':', $opsEndStr)[0];
                                    if ($hNum < $sNum || $hNum >= $eNum) {
                                        $isOffH = true;
                                    }
                                }
                            @endphp
                            <td style="text-align: center; font-size: 5pt; font-weight: bold; font-family: monospace; padding: 2px 0; background-color: {{ $isOffH ? '#e2e8f0' : '#dcfce7' }}; border-top: 1px solid {{ $isOffH ? '#94a3b8' : '#10b981' }}; border-bottom: 1px solid {{ $isOffH ? '#94a3b8' : '#10b981' }}; color: {{ $isOffH ? '#64748b' : '#065f46' }};">
                                {{ explode(' - ', $hd['hour'])[0] ?? $hd['hour'] }}
                            </td>
                        @endforeach
                    </tr>

                    {{-- LOWER SECTION: DEPARTURES (-Y, Grows DOWNWARD) --}}
                    <tr>
                        @foreach ($hourlyData as $hd)
                            @php
                                $depVal = (int)($hd['aircraft_departure'] ?? 0);
                                $depH = $depVal > 0 ? max(3, round(($depVal / $maxAmpPdf) * 32)) : 0;
                            @endphp
                            <td style="width: {{ 100 / max(1, count($hourlyData)) }}%; vertical-align: top; text-align: center; padding: 0 1px 1px 1px;">
                                @if($depVal > 0)
                                    <div style="height: {{ $depH }}px; background-color: #0284c7; width: 70%; margin: 0 auto; border-radius: 0 0 1px 1px;"></div>
                                    <div style="font-size: 4.5pt; font-weight: bold; color: #0284c7; margin-top: 1px;">
                                        {{ $depVal }}
                                    </div>
                                @else
                                    <div style="height: 1px; background-color: #cbd5e1; width: 50%; margin: 0 auto;"></div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            </div>

            {{-- Hourly Capacity Status Table --}}
            <div class="chart-box" style="margin-top: 5px;">
                <div class="chart-header">
                    HOURLY CAPACITY STATUS (Evaluasi ARR Cap: {{ $arrNacVal }} A/C | DEP Cap: {{ $depNacVal }} A/C | Ops: {{ $opsStartStr }}-{{ $opsEndStr }})
                </div>
                <table class="data-table" style="margin-top: 1px;">
                    <thead>
                        <tr>
                            <th style="width: 14%; text-align: left;">Hour</th>
                            <th class="text-right" style="width: 11%;">ARR</th>
                            <th class="text-center" style="width: 11%; color: #d97706;">ARR Cap</th>
                            <th class="text-right" style="width: 11%;">DEP</th>
                            <th class="text-center" style="width: 11%; color: #0284c7;">DEP Cap</th>
                            <th class="text-center" style="width: 8%;">OPC</th>
                            <th class="text-right" style="width: 18%;">Aircraft Demand</th>
                            <th class="text-center" style="width: 16%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hourlyCapacityStatus as $hcs)
                            <tr>
                                <td class="font-bold text-left">{{ $hcs['hour'] }}</td>
                                <td class="text-right" style="color: #d97706; font-weight: bold;">{{ number_format($hcs['arr']) }}</td>
                                <td class="text-center font-bold" style="color: #d97706;">{{ $hcs['arr_nac'] ?? $arrNacVal }}</td>
                                <td class="text-right" style="color: #0284c7; font-weight: bold;">{{ number_format($hcs['dep']) }}</td>
                                <td class="text-center font-bold" style="color: #0284c7;">{{ $hcs['dep_nac'] ?? $depNacVal }}</td>
                                <td class="text-center" style="color: #64748b;">{{ $hcs['opc'] }}</td>
                                <td class="text-right font-bold">{{ number_format($hcs['demand']) }}</td>
                                <td class="text-center">
                                    <span class="{{ $hcs['status'] === 'AVAILABLE' ? 'badge-available' : ($hcs['status'] === 'FULL / MAX' ? 'badge-full' : ($hcs['status'] === 'OFF HOURS' ? 'badge-off' : 'badge-over')) }}">
                                        {{ $hcs['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="page-break"></div>

        {{-- DAU-10A Time x Terminal Heatmap Matrix --}}
        <div class="chart-box">
            <div class="chart-header">
                TIME × TERMINAL HEATMAP MATRIX (Nilai: {{ ucfirst($metric ?? 'aircraft') }})
            </div>
            @php
                $pdfTerms = ['1', '2F', '3U', '1B', '2D', '2E', '1C'];
                $allHours = [];
                foreach ($hourlyData as $hd) $allHours[] = $hd['hour'];
                $calcMax = 1;
                foreach ($pdfTerms as $term) {
                    foreach ($allHours as $h) {
                        $cv = (int)($heatmapMatrix[$term][$h] ?? 0);
                        if ($cv > $calcMax) $calcMax = $cv;
                    }
                }
                $maxVal = $calcMax;
            @endphp
            <table class="heatmap-table">
                <thead>
                    <tr>
                        <th style="width: 10%; text-align: left;">Terminal</th>
                        @foreach ($allHours as $h)
                            <th style="width: {{ 90 / max(1, count($allHours)) }}%;">{{ explode(' - ', $h)[0] ?? $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pdfTerms as $term)
                        <tr>
                            <td class="text-left" style="background: #f8fafc; font-weight: bold;">T{{ $term }}</td>
                            @foreach ($allHours as $h)
                                @php
                                    $cellVal = $heatmapMatrix[$term][$h] ?? 0;
                                    $ratio = min(1, $cellVal / max(1, $maxVal));
                                    $bg = '#ffffff';
                                    if ($cellVal > 0) {
                                        if ($ratio < 0.20) $bg = '#dbeafe';
                                        elseif ($ratio < 0.45) $bg = '#93c5fd';
                                        elseif ($ratio < 0.70) $bg = '#3b82f6';
                                        else $bg = '#1d4ed8';
                                    }
                                    $textCol = ($ratio >= 0.45) ? '#ffffff' : '#0f172a';
                                @endphp
                                <td style="background-color: {{ $bg }}; color: {{ $textCol }}; font-size: 5.5pt;">
                                    {{ $cellVal > 0 ? $cellVal : '-' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @elseif ($reportType === 'DAU10B')
        {{-- DAU-10B Block On vs Block Off Comparative Distribution --}}
        @php
            $maxBlock = 1;
            foreach ($hourlyData as $hd) {
                $maxBlock = max($maxBlock, $hd['aircraft_arrival'], $hd['aircraft_departure']);
            }
        @endphp
        <div class="chart-box">
            <div class="chart-header" style="display: table; width: 100%;">
                <div style="display: table-cell; text-align: left;">
                    Block On (Inbound Gates / DTG) vs Block Off (Outbound Gates / BRK) Hourly Distribution
                </div>
                <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #64748b;">
                    <span style="display: inline-block; width: 8px; height: 8px; background: #7c3aed; vertical-align: middle;"></span> Block On (DTG) &nbsp;&nbsp;
                    <span style="display: inline-block; width: 8px; height: 8px; background: #f59e0b; vertical-align: middle;"></span> Block Off (BRK)
                </div>
            </div>
            <table class="chart-table">
                <tr>
                    @foreach ($hourlyData as $hd)
                        @php
                            $onH = max(2, round(($hd['aircraft_arrival'] / max(1, $maxBlock)) * 44));
                            $offH = max(2, round(($hd['aircraft_departure'] / max(1, $maxBlock)) * 44));
                        @endphp
                        <td style="width: {{ 100 / max(1, count($hourlyData)) }}%;">
                            <div style="font-size: 5pt; font-weight: bold; margin-bottom: 1px;">
                                <span style="color: #7c3aed;">{{ $hd['aircraft_arrival'] }}</span>/<span style="color: #d97706;">{{ $hd['aircraft_departure'] }}</span>
                            </div>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 50%; padding: 0;">
                                        <div class="bar-wrap" style="background: transparent;">
                                            <div class="bar-inner" style="height: {{ $onH }}px; background-color: #7c3aed;"></div>
                                        </div>
                                    </td>
                                    <td style="width: 50%; padding: 0;">
                                        <div class="bar-wrap" style="background: transparent;">
                                            <div class="bar-inner" style="height: {{ $offH }}px; background-color: #f59e0b;"></div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <div style="font-size: 5pt; color: #64748b; margin-top: 2px;">
                                {{ explode(' - ', $hd['hour'])[0] ?? $hd['hour'] }}
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>

    @elseif ($reportType === 'DAU11')
        {{-- DAU-11: Traffic Flow Breakdown --}}
        @php
            $f11 = $analytics['dau11_flow'] ?? [];
            $totDomPax = ($f11['dom_arr'] ?? 0) + ($f11['dom_dep'] ?? 0) + ($f11['dom_transit'] ?? 0) + ($f11['dom_transfer'] ?? 0);
            $totIntPax = ($f11['int_arr'] ?? 0) + ($f11['int_dep'] ?? 0) + ($f11['int_transit'] ?? 0) + ($f11['int_transfer'] ?? 0);
        @endphp
        <div class="chart-box">
            <div class="chart-header">
                ARUS LALU LINTAS PENUMPANG (DIRECT, TRANSIT, TRANSFER)
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 6.5pt;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding-right: 8px;">
                        <div style="font-weight: bold; color: #2563eb; border-bottom: 1px solid #bfdbfe; padding-bottom: 2px; margin-bottom: 3px;">
                            DOMESTIK FLOW (Total: {{ number_format($totDomPax) }} pax)
                        </div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td>Arrival (Kedatangan):</td><td style="text-align: right; font-weight: bold;">{{ number_format($f11['dom_arr'] ?? 0) }}</td></tr>
                            <tr><td>Departure (Keberangkatan):</td><td style="text-align: right; font-weight: bold;">{{ number_format($f11['dom_dep'] ?? 0) }}</td></tr>
                            <tr><td>Transit:</td><td style="text-align: right; font-weight: bold;">{{ number_format($f11['dom_transit'] ?? 0) }}</td></tr>
                            <tr><td>Transfer:</td><td style="text-align: right; font-weight: bold;">{{ number_format($f11['dom_transfer'] ?? 0) }}</td></tr>
                        </table>
                    </td>
                    <td style="width: 50%; vertical-align: top; padding-left: 8px; border-left: 1px solid #e2e8f0;">
                        <div style="font-weight: bold; color: #7c3aed; border-bottom: 1px solid #ddd6fe; padding-bottom: 2px; margin-bottom: 3px;">
                            INTERNASIONAL FLOW (Total: {{ number_format($totIntPax) }} pax)
                        </div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td>Arrival (Kedatangan):</td><td style="text-align: right; font-weight: bold;">{{ number_format($f11['int_arr'] ?? 0) }}</td></tr>
                            <tr><td>Departure (Keberangkatan):</td><td style="text-align: right; font-weight: bold;">{{ number_format($f11['int_dep'] ?? 0) }}</td></tr>
                            <tr><td>Transit:</td><td style="text-align: right; font-weight: bold;">{{ number_format($f11['int_transit'] ?? 0) }}</td></tr>
                            <tr><td>Transfer:</td><td style="text-align: right; font-weight: bold;">{{ number_format($f11['int_transfer'] ?? 0) }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

    @elseif ($reportType === 'DAU12')
        {{-- DAU-12: Directional Matrix (ARR/DEP x DOM/INT) --}}
        @php
            $m12 = $analytics['dau12_matrix'] ?? [];
        @endphp
        <div class="chart-box">
            <div class="chart-header">
                PERBANDINGAN KEDATANGAN &amp; KEBERANGKATAN (DOMESTIK VS INTERNASIONAL)
            </div>
            <table class="data-table" style="margin-top: 1px;">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 30%;">Kategori / Arah</th>
                        <th class="text-right" style="width: 23%; color: #93c5fd;">Domestik</th>
                        <th class="text-right" style="width: 23%; color: #c4b5fd;">Internasional</th>
                        <th class="text-right" style="width: 24%; color: #fde68a;">Total Semua</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="font-bold">Pesawat — Kedatangan (Arrival)</td>
                        <td class="text-right">{{ number_format($m12['aircraft']['arr_dom'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($m12['aircraft']['arr_int'] ?? 0) }}</td>
                        <td class="text-right font-bold">{{ number_format(($m12['aircraft']['arr_dom'] ?? 0) + ($m12['aircraft']['arr_int'] ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold">Pesawat — Keberangkatan (Departure)</td>
                        <td class="text-right">{{ number_format($m12['aircraft']['dep_dom'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($m12['aircraft']['dep_int'] ?? 0) }}</td>
                        <td class="text-right font-bold">{{ number_format(($m12['aircraft']['dep_dom'] ?? 0) + ($m12['aircraft']['dep_int'] ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="color: #059669;">Penumpang — Kedatangan (Arrival)</td>
                        <td class="text-right" style="color: #059669;">{{ number_format($m12['passenger']['arr_dom'] ?? 0) }}</td>
                        <td class="text-right" style="color: #059669;">{{ number_format($m12['passenger']['arr_int'] ?? 0) }}</td>
                        <td class="text-right font-bold" style="color: #059669;">{{ number_format(($m12['passenger']['arr_dom'] ?? 0) + ($m12['passenger']['arr_int'] ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="color: #059669;">Penumpang — Keberangkatan (Departure)</td>
                        <td class="text-right" style="color: #059669;">{{ number_format($m12['passenger']['dep_dom'] ?? 0) }}</td>
                        <td class="text-right" style="color: #059669;">{{ number_format($m12['passenger']['dep_int'] ?? 0) }}</td>
                        <td class="text-right font-bold" style="color: #059669;">{{ number_format(($m12['passenger']['dep_dom'] ?? 0) + ($m12['passenger']['dep_int'] ?? 0)) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- ══ MAIN DATA TABLE (TAILORED COLUMNS PER DAU) ════════════════════════ --}}
    <div style="margin-top: 6px;">
        <div style="font-size: 7.5pt; font-weight: bold; color: #0f172a; text-transform: uppercase; margin-bottom: 3px;">
            DAFTAR DETAIL DATA OPERASIONAL 
            @if (!empty($isTruncatedForPdf))
                (Menampilkan {{ count($records) }} dari {{ number_format($totalFilteredCount) }} baris data • Unduh Excel/CSV untuk dataset lengkap)
            @else
                ({{ number_format(count($records)) }} baris data)
            @endif
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 3%; text-align: center;">#</th>
                    @if ($reportType === 'DAU1')
                        @if (($filters['metric'] ?? 'aircraft') === 'passenger')
                            <th style="width: 16%; text-align: left;">Bandara Asal / Tujuan</th>
                            <th style="width: 8%;">Flight No</th>
                            <th style="width: 12%; text-align: left;">Airline</th>
                            <th class="text-right" style="width: 10%; color: #93c5fd;">Dewasa</th>
                            <th class="text-right" style="width: 9%; color: #38bdf8;">Anak</th>
                            <th class="text-right" style="width: 9%; color: #c084fc;">Bayi</th>
                            <th class="text-right" style="width: 11%; color: #a7f3d0;">Total Pax</th>
                            <th class="text-right" style="width: 9%;">Pax ARR</th>
                            <th class="text-right" style="width: 9%;">Pax DEP</th>
                            <th class="text-right" style="width: 7%;">Transit</th>
                        @else
                            <th style="width: 14%; text-align: left;">Bandara Asal / Tujuan</th>
                            <th style="width: 8%;">Flight No</th>
                            <th style="width: 7%;">Status</th>
                            <th style="width: 8%;">Tipe Pesawat</th>
                            <th class="text-right" style="width: 6%;">Seat Cap</th>
                            <th class="text-right" style="width: 6%;">ARR Acft</th>
                            <th class="text-right" style="width: 6%;">DEP Acft</th>
                            <th class="text-right" style="width: 7%;">Total Acft</th>
                            <th class="text-right" style="width: 9%; color: #a7f3d0;">Total Pax</th>
                            <th class="text-right" style="width: 8%;">Bagasi (Kg)</th>
                            <th class="text-right" style="width: 8%;">Kargo (Kg)</th>
                            <th class="text-right" style="width: 8%;">POS (Kg)</th>
                        @endif

                    @elseif ($reportType === 'DAU2')
                        @php
                            $d2m = $filters['metric'] ?? 'aircraft';
                        @endphp
                        @if ($d2m === 'passenger')
                            <th style="width: 35%; text-align: left;">Kategori Penerbangan</th>
                            <th class="text-right" style="width: 13%; color: #a7f3d0;">Pax ARR</th>
                            <th class="text-right" style="width: 13%; color: #38bdf8;">Pax DEP</th>
                            <th class="text-right" style="width: 15%; color: #fde68a;">Total Pax</th>
                            <th class="text-right" style="width: 12%;">Transit</th>
                            <th class="text-right" style="width: 12%;">Transfer</th>
                        @elseif ($d2m === 'baggage')
                            <th style="width: 40%; text-align: left;">Kategori Penerbangan</th>
                            <th class="text-right" style="width: 20%; color: #fde68a;">Bagasi ARR (Kg)</th>
                            <th class="text-right" style="width: 20%; color: #fde68a;">Bagasi DEP (Kg)</th>
                            <th class="text-right" style="width: 20%; color: #fde68a;">Total Bagasi (Kg)</th>
                        @elseif ($d2m === 'cargo')
                            <th style="width: 40%; text-align: left;">Kategori Penerbangan</th>
                            <th class="text-right" style="width: 20%; color: #c4b5fd;">Kargo ARR (Kg)</th>
                            <th class="text-right" style="width: 20%; color: #c4b5fd;">Kargo DEP (Kg)</th>
                            <th class="text-right" style="width: 20%; color: #c4b5fd;">Total Kargo (Kg)</th>
                        @elseif ($d2m === 'pos')
                            <th style="width: 40%; text-align: left;">Kategori Penerbangan</th>
                            <th class="text-right" style="width: 20%; color: #cbd5e1;">POS ARR (Kg)</th>
                            <th class="text-right" style="width: 20%; color: #cbd5e1;">POS DEP (Kg)</th>
                            <th class="text-right" style="width: 20%; color: #cbd5e1;">Total POS (Kg)</th>
                        @else
                            <th style="width: 25%; text-align: left;">Jenis Penerbangan (Kategori)</th>
                            <th class="text-right" style="width: 8%;">ARR Acft</th>
                            <th class="text-right" style="width: 8%;">DEP Acft</th>
                            <th class="text-right" style="width: 9%;">Total Acft</th>
                            <th class="text-right" style="width: 9%;">ARR Pax</th>
                            <th class="text-right" style="width: 9%;">DEP Pax</th>
                            <th class="text-right" style="width: 11%; color: #a7f3d0;">Total Pax</th>
                            <th class="text-right" style="width: 7%;">Awak</th>
                            <th class="text-right" style="width: 7%;">Bagasi</th>
                            <th class="text-right" style="width: 7%;">Kargo</th>
                        @endif

                    @elseif ($reportType === 'DAU3')
                        <th style="width: 20%; text-align: left;">Status Usaha</th>
                        <th style="width: 20%; text-align: left;">Jenis Penerbangan</th>
                        <th class="text-right" style="width: 12%;">Total Acft</th>
                        <th class="text-right" style="width: 16%; color: #a7f3d0;">Total Pax</th>
                        <th class="text-right" style="width: 14%;">Bagasi (Kg)</th>
                        <th class="text-right" style="width: 15%;">Kargo (Kg)</th>

                    @elseif ($reportType === 'DAU4')
                        <th style="width: 18%; text-align: left;">Nama Airport</th>
                        <th style="width: 6%;">IATA</th>
                        <th style="width: 15%; text-align: left;">Kota</th>
                        <th class="text-right" style="width: 7%;">ARR Acft</th>
                        <th class="text-right" style="width: 7%;">DEP Acft</th>
                        <th class="text-right" style="width: 8%;">Total Acft</th>
                        <th class="text-right" style="width: 10%; color: #a7f3d0;">Total Pax</th>
                        <th class="text-right" style="width: 7%;">Awak</th>
                        <th class="text-right" style="width: 9%;">Bagasi (Kg)</th>
                        <th class="text-right" style="width: 10%;">Kargo (Kg)</th>

                    @elseif ($reportType === 'DAU4A')
                        <th style="width: 18%; text-align: left;">Operator / Airline</th>
                        <th style="width: 6%;">Kode</th>
                        <th style="width: 18%; text-align: left;">Bandara Rute</th>
                        <th style="width: 6%;">IATA</th>
                        <th class="text-right" style="width: 8%;">Total Acft</th>
                        <th class="text-right" style="width: 12%; color: #a7f3d0;">Total Pax</th>
                        <th class="text-right" style="width: 7%;">Awak</th>
                        <th class="text-right" style="width: 11%;">Bagasi (Kg)</th>
                        <th class="text-right" style="width: 11%;">Kargo (Kg)</th>

                    @elseif ($reportType === 'DAU4B')
                        <th style="width: 22%; text-align: left;">Kota / Rute</th>
                        <th style="width: 7%;">IATA</th>
                        <th style="width: 25%; text-align: left;">Airline / Operator</th>
                        <th class="text-right" style="width: 10%;">ARR Acft</th>
                        <th class="text-right" style="width: 10%;">DEP Acft</th>
                        <th class="text-right" style="width: 11%;">Total Acft</th>
                        <th class="text-right" style="width: 12%; color: #a7f3d0;">Total Pax</th>

                    @elseif ($reportType === 'DAU5' || $reportType === 'DAU5C')
                        <th style="width: 22%; text-align: left;">Airline / Operator</th>
                        <th class="text-right" style="width: 7%;">ARR Acft</th>
                        <th class="text-right" style="width: 7%;">DEP Acft</th>
                        <th class="text-right" style="width: 8%;">Total Acft</th>
                        <th class="text-right" style="width: 9%;">ARR Pax</th>
                        <th class="text-right" style="width: 9%;">DEP Pax</th>
                        <th class="text-right" style="width: 11%; color: #a7f3d0;">Total Pax</th>
                        <th class="text-right" style="width: 7%;">Awak</th>
                        <th class="text-right" style="width: 9%;">Bagasi (Kg)</th>
                        <th class="text-right" style="width: 9%;">Kargo (Kg)</th>

                    @elseif ($reportType === 'DAU5A')
                        <th style="width: 24%; text-align: left;">Airline / Operator</th>
                        <th class="text-right" style="width: 8%;">Total Acft</th>
                        <th class="text-right" style="width: 11%; color: #a7f3d0;">Total Pax</th>
                        <th class="text-right" style="width: 11%; color: #93c5fd;">Operating Crew</th>
                        <th class="text-right" style="width: 11%; color: #c4b5fd;">ARR Ex Crew</th>
                        <th class="text-right" style="width: 11%; color: #c4b5fd;">DEP Ex Crew</th>
                        <th class="text-right" style="width: 11%; color: #e9d5ff;">Total Extra Crew</th>
                        <th class="text-right" style="width: 10%;">Total Awak</th>

                    @elseif ($reportType === 'DAU5B')
                        <th style="width: 10%;">Terminal</th>
                        <th style="width: 22%; text-align: left;">Airline</th>
                        <th class="text-right" style="width: 8%;">ARR Acft</th>
                        <th class="text-right" style="width: 8%;">DEP Acft</th>
                        <th class="text-right" style="width: 9%;">Total Acft</th>
                        <th class="text-right" style="width: 12%; color: #a7f3d0;">Total Pax</th>
                        <th class="text-right" style="width: 14%;">Bagasi (Kg)</th>
                        <th class="text-right" style="width: 14%;">Kargo (Kg)</th>

                    @elseif ($reportType === 'DAU6')
                        <th style="width: 18%; text-align: left;">Tipe Pesawat</th>
                        <th style="width: 12%;">Kategori</th>
                        <th style="width: 7%;">WTC</th>
                        <th class="text-right" style="width: 7%;">ARR Acft</th>
                        <th class="text-right" style="width: 7%;">DEP Acft</th>
                        <th class="text-right" style="width: 8%;">Total Acft</th>
                        <th class="text-right" style="width: 11%; color: #a7f3d0;">Total Pax</th>
                        <th class="text-right" style="width: 7%;">Awak</th>
                        <th class="text-right" style="width: 10%;">Bagasi</th>
                        <th class="text-right" style="width: 10%;">Kargo</th>

                    @elseif ($reportType === 'DAU10A')
                        @if ($metricMode === 'passenger')
                            <th style="width: 14%;">Jam / Periode</th>
                            <th style="width: 10%;">Terminal</th>
                            <th class="text-right" style="width: 18%; color: #a7f3d0;">Pax ARR</th>
                            <th class="text-right" style="width: 18%; color: #38bdf8;">Pax DEP</th>
                            <th class="text-right" style="width: 18%; color: #fde68a;">Total Pax</th>
                            <th class="text-right" style="width: 11%;">Transit</th>
                            <th class="text-right" style="width: 11%;">Transfer</th>
                        @elseif ($metricMode === 'crew')
                            <th style="width: 25%;">Jam / Periode</th>
                            <th style="width: 15%;">Terminal</th>
                            <th class="text-right" style="width: 20%; color: #93c5fd;">Operating Crew (ARR)</th>
                            <th class="text-right" style="width: 20%; color: #c4b5fd;">Extra Crew (DEP)</th>
                            <th class="text-right" style="width: 20%; color: #fde68a;">Total Crew</th>
                        @else
                            <th style="width: 11%;">Jam / Periode</th>
                            <th style="width: 7%;">Terminal</th>
                            <th class="text-right" style="width: 8%;">Acft ARR</th>
                            <th class="text-right" style="width: 8%;">Acft DEP</th>
                            <th class="text-right" style="width: 8%;">Total Acft</th>
                            <th class="text-right" style="width: 9%;">Pax ARR</th>
                            <th class="text-right" style="width: 9%;">Pax DEP</th>
                            <th class="text-right" style="width: 11%; color: #a7f3d0;">Total Pax</th>
                            <th class="text-right" style="width: 8%;">Transit</th>
                            <th class="text-right" style="width: 8%;">Transfer</th>
                            <th class="text-right" style="width: 8%;">Awak</th>
                        @endif

                    @elseif ($reportType === 'DAU10' || $reportType === 'DAU10B')
                        <th style="width: 11%;">Jam / Periode</th>
                        <th style="width: 7%;">Terminal</th>
                        <th class="text-right" style="width: 7%;">@if ($reportType === 'DAU10B') Acft On @else Acft ARR @endif</th>
                        <th class="text-right" style="width: 7%;">@if ($reportType === 'DAU10B') Acft Off @else Acft DEP @endif</th>
                        <th class="text-right" style="width: 7%;">Total Acft</th>
                        <th class="text-right" style="width: 8%;">@if ($reportType === 'DAU10B') Pax On @else Pax ARR @endif</th>
                        <th class="text-right" style="width: 8%;">@if ($reportType === 'DAU10B') Pax Off @else Pax DEP @endif</th>
                        <th class="text-right" style="width: 9%; color: #a7f3d0;">Total Pax</th>
                        <th class="text-right" style="width: 6%;">Transit</th>
                        <th class="text-right" style="width: 6%;">Transfer</th>
                        <th class="text-right" style="width: 6%;">Awak</th>
                        <th class="text-right" style="width: 8%;">Bagasi</th>
                        <th class="text-right" style="width: 8%;">Kargo</th>

                    @elseif ($reportType === 'DAU11')
                        <th style="width: 12%;">Tanggal</th>
                        <th class="text-right" style="width: 11%;">INT ARR</th>
                        <th class="text-right" style="width: 11%;">INT DEP</th>
                        <th class="text-right" style="width: 11%;">DOM ARR</th>
                        <th class="text-right" style="width: 11%;">DOM DEP</th>
                        <th class="text-right" style="width: 15%;">Total Aircraft</th>
                        <th class="text-right" style="width: 18%; color: #a7f3d0;">Total Passenger</th>

                    @elseif ($reportType === 'DAU12')
                        <th style="width: 11%;">Tanggal</th>
                        <th class="text-right" style="width: 9%;">ARR DOM</th>
                        <th class="text-right" style="width: 9%;">ARR INT</th>
                        <th class="text-right" style="width: 10%;">ARR Total</th>
                        <th class="text-right" style="width: 9%;">DEP DOM</th>
                        <th class="text-right" style="width: 9%;">DEP INT</th>
                        <th class="text-right" style="width: 10%;">DEP Total</th>
                        <th class="text-right" style="width: 12%;">Total Acft</th>
                        <th class="text-right" style="width: 18%; color: #a7f3d0;">Total Pax</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $idx => $r)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        @if ($reportType === 'DAU1')
                            @if (($filters['metric'] ?? 'aircraft') === 'passenger')
                                @php
                                    $pAdult = $r['passenger_adult'] ?? $r['adult'] ?? ((int)($r['arr_adult'] ?? 0) + (int)($r['dep_adult'] ?? 0));
                                    $pChild = $r['passenger_child'] ?? $r['child'] ?? ((int)($r['arr_child'] ?? 0) + (int)($r['dep_child'] ?? 0));
                                    $pInfant = $r['passenger_infant'] ?? $r['infant'] ?? ((int)($r['arr_infant'] ?? 0) + (int)($r['dep_infant'] ?? 0));
                                    $pTotal = $r['passenger_total'] ?? ($pAdult + $pChild + $pInfant);
                                @endphp
                                <td class="text-left font-bold">{{ $r['airport_route'] ?? $r['origin'] ?? '—' }}</td>
                                <td class="text-center font-bold" style="color: #0284c7;">{{ $r['flight_number'] ?? '—' }}</td>
                                <td class="text-left">{{ $r['airline'] ?? $r['operator_name'] ?? '—' }}</td>
                                <td class="text-right font-bold" style="color: #2563eb;">{{ number_format($pAdult) }}</td>
                                <td class="text-right font-bold" style="color: #0284c7;">{{ number_format($pChild) }}</td>
                                <td class="text-right font-bold" style="color: #a855f7;">{{ number_format($pInfant) }}</td>
                                <td class="text-right font-bold" style="color: #059669;">{{ number_format($pTotal) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_transit'] ?? 0) }}</td>
                            @else
                                <td class="text-left font-bold">{{ $r['airport_route'] ?? $r['origin'] ?? '—' }}</td>
                                <td class="text-center font-bold" style="color: #0284c7;">{{ $r['flight_number'] ?? '—' }}</td>
                                <td class="text-center">{{ $r['schedule_type'] ?? '—' }}</td>
                                <td class="text-center">{{ $r['aircraft_type'] ?? '—' }}</td>
                                <td class="text-right">{{ number_format($r['seat_capacity'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['pos'] ?? 0) }}</td>
                            @endif

                        @elseif ($reportType === 'DAU2')
                            @php
                                $d2m = $filters['metric'] ?? 'aircraft';
                            @endphp
                            <td class="text-left font-bold">{{ $r['category'] ?? '—' }}</td>
                            @if ($d2m === 'passenger')
                                <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #0284c7;">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #10b981;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_transit'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_transfer'] ?? 0) }}</td>
                            @elseif ($d2m === 'baggage')
                                <td class="text-right font-bold" style="color: #d97706;">{{ number_format($r['baggage_arrival'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #d97706;">{{ number_format($r['baggage_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #b45309;">{{ number_format($r['baggage'] ?? 0) }}</td>
                            @elseif ($d2m === 'cargo')
                                <td class="text-right font-bold" style="color: #4f46e5;">{{ number_format($r['cargo_arrival'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #4f46e5;">{{ number_format($r['cargo_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #4338ca;">{{ number_format($r['cargo'] ?? 0) }}</td>
                            @elseif ($d2m === 'pos')
                                <td class="text-right font-bold" style="color: #64748b;">{{ number_format($r['pos_arrival'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #64748b;">{{ number_format($r['pos_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #334155;">{{ number_format($r['pos'] ?? 0) }}</td>
                            @else
                                <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['crew_total'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>
                            @endif

                        @elseif ($reportType === 'DAU3')
                            <td class="text-left font-bold" style="color: #0284c7;">{{ $r['section'] ?? '—' }}</td>
                            <td class="text-left">{{ $r['category'] ?? '—' }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU4')
                            <td class="text-left font-bold">{{ $r['airport'] ?? '—' }}</td>
                            <td class="text-center font-bold" style="color: #0284c7;">{{ $r['city_code'] ?? '—' }}</td>
                            <td class="text-left">{{ $r['city'] ?? '—' }}</td>
                            <td class="text-right" style="color: #d97706;">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                            <td class="text-right" style="color: #0284c7;">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['crew_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU4A')
                            <td class="text-left font-bold">{{ $r['operator_name'] ?? $r['airline'] ?? '—' }}</td>
                            <td class="text-center font-bold" style="color: #0284c7;">{{ $r['operator_code'] ?? $r['airline_code'] ?? '—' }}</td>
                            <td class="text-left">{{ $r['airport'] ?? '—' }}</td>
                            <td class="text-center">{{ $r['city_code'] ?? '—' }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['crew_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU4B')
                            <td class="text-left font-bold">{{ $r['city'] ?? '—' }}</td>
                            <td class="text-center font-bold" style="color: #0284c7;">{{ $r['city_code'] ?? '—' }}</td>
                            <td class="text-left">{{ $r['airline'] ?? '—' }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU5' || $reportType === 'DAU5C')
                            <td class="text-left font-bold">{{ $r['airline'] ?? '—' }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['crew_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU5A')
                            <td class="text-left font-bold">{{ $r['airline'] ?? '—' }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #2563eb;">{{ number_format($r['crew'] ?? 0) }}</td>
                            <td class="text-right" style="color: #7c3aed;">{{ number_format($r['arr_extra_crew'] ?? 0) }}</td>
                            <td class="text-right" style="color: #7c3aed;">{{ number_format($r['dep_extra_crew'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #9333ea;">{{ number_format($r['extra_crew'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['crew_total'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU5B')
                            <td class="text-center font-bold" style="color: #0284c7;">T{{ $r['terminal'] ?? '—' }}</td>
                            <td class="text-left font-bold">{{ $r['airline'] ?? '—' }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU6')
                            <td class="text-left font-bold">{{ $r['aircraft_type'] ?? '—' }}</td>
                            <td class="text-center">{{ $r['category'] ?? 'Narrow Body' }}</td>
                            <td class="text-center font-bold" style="color: #0284c7;">{{ $r['wtc'] ?? 'Medium' }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['crew_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU10A')
                            @if ($metricMode === 'passenger')
                                <td class="font-bold">{{ $r['hour'] ?? $r['period'] ?? '—' }}</td>
                                <td>{{ $r['terminal'] ?? '—' }}</td>
                                <td class="text-right" style="color: #059669; font-weight: bold;">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                                <td class="text-right" style="color: #0284c7; font-weight: bold;">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_transit'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_transfer'] ?? 0) }}</td>
                            @elseif ($metricMode === 'crew')
                                <td class="font-bold">{{ $r['hour'] ?? $r['period'] ?? '—' }}</td>
                                <td>{{ $r['terminal'] ?? '—' }}</td>
                                <td class="text-right" style="color: #2563eb; font-weight: bold;">{{ number_format($r['crew'] ?? 0) }}</td>
                                <td class="text-right" style="color: #7c3aed; font-weight: bold;">{{ number_format($r['extra_crew'] ?? 0) }}</td>
                                <td class="text-right font-bold">{{ number_format($r['crew_total'] ?? (($r['crew'] ?? 0) + ($r['extra_crew'] ?? 0))) }}</td>
                            @else
                                <td class="font-bold">{{ $r['hour'] ?? $r['period'] ?? '—' }}</td>
                                <td>{{ $r['terminal'] ?? '—' }}</td>
                                <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                                <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_transit'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['passenger_transfer'] ?? 0) }}</td>
                                <td class="text-right">{{ number_format($r['crew'] ?? 0) }}</td>
                            @endif

                        @elseif ($reportType === 'DAU10' || $reportType === 'DAU10B')
                            <td class="font-bold">{{ $r['hour'] ?? $r['period'] ?? '—' }}</td>
                            <td>{{ $r['terminal'] ?? '—' }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['passenger_transit'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['passenger_transfer'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['crew'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU11')
                            <td class="text-center font-bold">{{ $r['date'] ?? '—' }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_int_arrival'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_int_departure'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_dom_arrival'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_dom_departure'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>

                        @elseif ($reportType === 'DAU12')
                            <td class="text-center font-bold">{{ $r['date'] ?? '—' }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_arr_domestic'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_arr_int'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_arrival_tot'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_dep_domestic'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['aircraft_dep_int'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_departure_tot'] ?? 0) }}</td>
                            <td class="text-right font-bold">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                            <td class="text-right font-bold" style="color: #059669;">{{ number_format($r['passenger_total'] ?? 0) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="text-center" style="padding: 12px; color: #64748b;">
                            NO DATA FOR SELECTED FILTER — Tidak ada rekod data yang sesuai dengan filter yang dipilih.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</body>
</html>
