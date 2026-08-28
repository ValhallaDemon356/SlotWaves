<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="UTF-8">
    <title>SlotWaves Operational Slot Schedule — {{ $upload->original_filename }}</title>
    <style>
        @page {
            size: A2 landscape;
            margin: 8mm;
        }
        * {
            box-sizing: border-box;
            margin: 0; padding: 0;
        }
        html, body {
            width: 100%;
            font-family: 'DejaVu Sans', sans-serif;
            background-color: #020617;
            color: #f8fafc;
            padding: 4px;
            font-size: 11px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 3px;
            color: #ffffff;
            text-transform: uppercase;
        }
        .header p {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
            font-family: 'DejaVu Sans', monospace;
        }
        .legend {
            text-align: center;
            margin-bottom: 12px;
        }
        .legend-item {
            display: inline-block;
            margin: 0 12px;
            font-size: 10.5px;
            color: #cbd5e1;
            font-weight: bold;
        }
        .legend-box {
            display: inline-block;
            width: 14px;
            height: 10px;
            border-radius: 2px;
            vertical-align: middle;
            margin-right: 5px;
            border: 1px solid rgba(255,255,255,0.4);
        }
        .canvas-wrap {
            position: relative;
            background: #090e1a;
            border: 2px solid #1e293b;
            border-radius: 8px;
            overflow: hidden;
            width: {{ $layout['canvasWidth'] }}px;
            margin: 0 auto;
        }
        .tl-grid-table {
            width: {{ $layout['canvasWidth'] }}px;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .tl-grid-table th, .tl-grid-table td {
            border: 1px solid #1e293b;
            padding: 0;
            margin: 0;
            overflow: hidden;
            box-sizing: border-box;
        }
        .section-band {
            position: relative;
            border-bottom: 2px solid #1e293b;
            width: {{ $layout['canvasWidth'] }}px;
            overflow: hidden;
        }

        /* Flight card styling: 3-Point Vector Layout for PDF */
        .card {
            position: absolute;
            border-radius: 4px;
            border: 1.5px solid rgba(255,255,255,0.8);
            overflow: hidden;
            box-sizing: border-box;
            box-shadow: 0 2px 4px rgba(0,0,0,0.5);
            z-index: 5;
        }
        .card-inner-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            border: none;
            margin: 0;
            padding: 0;
        }
        .card-inner-table td {
            border: none !important;
            padding: 0;
            margin: 0;
        }
        .card-fn-cell {
            padding: 2px 0 0 4px !important;
            vertical-align: top;
            text-align: left;
            font-family: monospace;
            font-size: 11px;
            font-weight: 900;
            color: #ffffff;
            line-height: 1.1;
            width: 65%;
        }
        .card-off-cell {
            padding: 2px 4px 0 0 !important;
            vertical-align: top;
            text-align: right;
            width: 35%;
        }
        .card-off-badge {
            font-family: monospace;
            font-size: 8px;
            font-weight: 900;
            color: #ffffff;
            background: #dc2626;
            padding: 0 3px;
            border-radius: 2px;
            display: inline-block;
            line-height: 1.1;
        }
        .card-ac-cell {
            padding: 0 0 3px 4px !important;
            vertical-align: bottom;
            text-align: left;
            font-family: sans-serif;
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.95);
            line-height: 1;
            width: 55%;
        }
        .card-airport-cell {
            padding: 0 4px 3px 0 !important;
            vertical-align: bottom;
            text-align: right;
            font-family: monospace;
            font-size: 9.5px;
            font-weight: 900;
            color: #ffffff;
            line-height: 1;
            width: 45%;
        }

        .ops-boundary-start {
            position: absolute;
            top: 0;
            height: 100%;
            border-left: 2.5px dashed #38bdf8;
            z-index: 3;
        }
        .ops-boundary-end {
            position: absolute;
            top: 0;
            height: 100%;
            border-left: 2.5px dashed #38bdf8;
            z-index: 3;
        }

        /* Summary table with strict fixed column layout */
        .summary-table {
            width: {{ $layout['canvasWidth'] }}px;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 10px auto 0 auto;
            font-size: 10.5px;
            font-family: monospace;
        }
        .summary-table th, .summary-table td {
            border: 1px solid #1e293b;
            text-align: center;
            padding: 4px 2px;
            overflow: hidden;
            box-sizing: border-box;
        }
        .summary-table th { background: #020617; color: #94a3b8; font-weight: bold; }
        .summary-table td.label { text-align: left; font-weight: bold; padding-left: 8px; }

        /* Page Break for Section 2 Details */
        .page-break {
            page-break-before: always;
        }

        /* Section 2: Detailed Flight Schedule Table */
        .details-section {
            padding-top: 14px;
        }
        .details-header {
            border-bottom: 2px solid #334155;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .details-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .details-header p {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 3px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        .details-table th, .details-table td {
            border: 1px solid #334155;
            padding: 4px 5px;
            text-align: left;
        }
        .details-table th {
            background: #0f172a;
            color: #94a3b8;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.5px;
        }
        .details-table tr:nth-child(even) {
            background: rgba(15, 23, 42, 0.4);
        }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
        }
        .badge-dep { background: #1e3a8a; color: #93c5fd; }
        .badge-arr { background: #78350f; color: #fde68a; }
        .badge-dom { background: #064e3b; color: #6ee7b7; }
        .badge-int { background: #4c1d95; color: #c4b5fd; }
    </style>
</head>
<body>

    {{-- ══ PAGE 1: 24-HOUR OPERATIONAL SLOT TIMELINE ══════════════════════════════ --}}
    <div class="header">
        <h1>Airport Operational Slot Schedule</h1>
        <p>Bandara Husein Sastranegara (BDO) &nbsp;|&nbsp; Schedule Date &bull; {{ now()->format('d M Y') }} &nbsp;|&nbsp; 24-Hour Timeline View</p>
    </div>

    <div class="legend">
        <div class="legend-item"><span class="legend-box" style="background:#1e40af"></span>Departure Domestic (Navy)</div>
        <div class="legend-item"><span class="legend-box" style="background:#3b82f6"></span>Departure International (Light Blue)</div>
        <div class="legend-item"><span class="legend-box" style="background:#b45309"></span>Arrival Domestic (Dark Orange)</div>
        <div class="legend-item"><span class="legend-box" style="background:#f59e0b"></span>Arrival International (Light Orange)</div>
        <div class="legend-item"><span class="legend-box" style="background:#020617; border:1px solid #334155"></span>Off Hour Shading</div>
    </div>

    @php
        $colW = $layout['colW'];
        $rowH = $layout['rowH'];
        $blockW = $layout['blockW'];
        $labelOffset = $layout['labelOffset'];
        $totWidth = $layout['totWidth'] ?? $colW;
        $opsStart = $layout['opsStart'];
        $opsEnd = $layout['opsEnd'];
    @endphp

    <div class="canvas-wrap">
        {{-- Hour Header Table --}}
        <table class="tl-grid-table">
            <colgroup>
                <col style="width: {{ $labelOffset }}px;">
                @for ($h = 0; $h < 24; $h++)
                    <col style="width: {{ $colW }}px;">
                @endfor
                <col style="width: {{ $totWidth }}px;">
            </colgroup>
            <thead>
                <tr style="height: 32px; background: #020617;">
                    <th style="background: #020617; border-right: 1px solid #1e293b;"></th>
                    @for ($h = 0; $h < 24; $h++)
                        <th style="text-align: center; font-size: 11.5px; font-weight: 900; color: #60a5fa; font-family: monospace; border-right: 1px solid #1e293b;">
                            {{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00
                        </th>
                    @endfor
                    <th style="text-align: center; font-size: 11.5px; font-weight: 900; color: #f59e0b; font-family: monospace;">TOT</th>
                </tr>
            </thead>
        </table>

        {{-- DEPARTURE SECTION --}}
        <div class="section-band" style="height: {{ $layout['depBandH'] }}px;">
            {{-- Background Grid Table --}}
            <table class="tl-grid-table" style="position: absolute; top: 0; left: 0; height: {{ $layout['depBandH'] }}px; z-index: 1;">
                <colgroup>
                    <col style="width: {{ $labelOffset }}px;">
                    @for ($h = 0; $h < 24; $h++)
                        <col style="width: {{ $colW }}px;">
                    @endfor
                    <col style="width: {{ $totWidth }}px;">
                </colgroup>
                <tbody>
                    <tr style="height: {{ $layout['depBandH'] }}px;">
                        <td style="background: #020617; border-right: 1px solid #1e293b; text-align: center; font-size: 12px; font-weight: 900; letter-spacing: 1.5px; text-transform: uppercase; color: #60a5fa; vertical-align: middle;">
                            DEP
                        </td>
                        @for ($h = 0; $h < 24; $h++)
                            <td style="border-right: 1px solid #1e293b; background: {{ ($h < $opsStart || $h >= $opsEnd) ? 'rgba(2,6,23,0.85)' : 'transparent' }};"></td>
                        @endfor
                        <td style="background: {{ (24 < $opsStart || 24 >= $opsEnd) ? 'rgba(2,6,23,0.85)' : 'transparent' }};"></td>
                    </tr>
                </tbody>
            </table>

            {{-- Operational Hours Blue Dashed Boundary Lines --}}
            <div class="ops-boundary-start" style="left: {{ $labelOffset + ($opsStart * $colW) }}px;"></div>
            <div class="ops-boundary-end" style="left: {{ $labelOffset + ($opsEnd * $colW) }}px;"></div>

            {{-- 3-Point Flight Cards for Departure (Top-Left: Flight No, Top-Right: OFF, Bottom-Left: A/C, Bottom-Right: Airport) --}}
            @foreach ($departureBlocks as $p)
                @php
                    $left = $labelOffset + (int) round(($p['startMinutes'] / 60) * $colW);
                    $top  = ($p['row'] * $rowH) + 4;
                @endphp
                <div class="card" style="left: {{ $left }}px; top: {{ $top }}px; width: {{ $blockW }}px; height: {{ $rowH - 8 }}px; background: {{ $p['color_hex'] }};">
                    <table class="card-inner-table">
                        <tr>
                            <td class="card-fn-cell">{{ $p['flight']['flight_number'] }}</td>
                            <td class="card-off-cell">
                                @if (!empty($p['is_off_hour']))
                                    <span class="card-off-badge">OFF</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="card-ac-cell">{{ $p['flight']['aircraft_type'] ?: 'N/A' }}</td>
                            <td class="card-airport-cell">{{ $p['flight']['origin_iata'] }}</td>
                        </tr>
                    </table>
                </div>
            @endforeach
        </div>

        {{-- ARRIVAL SECTION --}}
        <div class="section-band" style="height: {{ $layout['arrBandH'] }}px;">
            {{-- Background Grid Table --}}
            <table class="tl-grid-table" style="position: absolute; top: 0; left: 0; height: {{ $layout['arrBandH'] }}px; z-index: 1;">
                <colgroup>
                    <col style="width: {{ $labelOffset }}px;">
                    @for ($h = 0; $h < 24; $h++)
                        <col style="width: {{ $colW }}px;">
                    @endfor
                    <col style="width: {{ $totWidth }}px;">
                </colgroup>
                <tbody>
                    <tr style="height: {{ $layout['arrBandH'] }}px;">
                        <td style="background: #020617; border-right: 1px solid #1e293b; text-align: center; font-size: 12px; font-weight: 900; letter-spacing: 1.5px; text-transform: uppercase; color: #f59e0b; vertical-align: middle;">
                            ARR
                        </td>
                        @for ($h = 0; $h < 24; $h++)
                            <td style="border-right: 1px solid #1e293b; background: {{ ($h < $opsStart || $h >= $opsEnd) ? 'rgba(2,6,23,0.85)' : 'transparent' }};"></td>
                        @endfor
                        <td style="background: {{ (24 < $opsStart || 24 >= $opsEnd) ? 'rgba(2,6,23,0.85)' : 'transparent' }};"></td>
                    </tr>
                </tbody>
            </table>

            {{-- Operational Hours Blue Dashed Boundary Lines --}}
            <div class="ops-boundary-start" style="left: {{ $labelOffset + ($opsStart * $colW) }}px;"></div>
            <div class="ops-boundary-end" style="left: {{ $labelOffset + ($opsEnd * $colW) }}px;"></div>

            {{-- 3-Point Flight Cards for Arrival (Top-Left: Flight No, Top-Right: OFF, Bottom-Left: A/C, Bottom-Right: Airport) --}}
            @foreach ($arrivalBlocks as $p)
                @php
                    $left = $labelOffset + (int) round(($p['startMinutes'] / 60) * $colW);
                    $top  = ($p['row'] * $rowH) + 4;
                @endphp
                <div class="card" style="left: {{ $left }}px; top: {{ $top }}px; width: {{ $blockW }}px; height: {{ $rowH - 8 }}px; background: {{ $p['color_hex'] }};">
                    <table class="card-inner-table">
                        <tr>
                            <td class="card-fn-cell">{{ $p['flight']['flight_number'] }}</td>
                            <td class="card-off-cell">
                                @if (!empty($p['is_off_hour']))
                                    <span class="card-off-badge">OFF</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="card-ac-cell">{{ $p['flight']['aircraft_type'] ?: 'N/A' }}</td>
                            <td class="card-airport-cell">{{ $p['flight']['origin_iata'] }}</td>
                        </tr>
                    </table>
                </div>
            @endforeach
        </div>
    </div>

    {{-- SUMMARY TABLE --}}
    <table class="summary-table">
        <colgroup>
            <col style="width: {{ $labelOffset }}px;">
            @for ($h = 0; $h < 24; $h++)
                <col style="width: {{ $colW }}px;">
            @endfor
            <col style="width: {{ $totWidth }}px;">
        </colgroup>
        <thead>
            <tr>
                <th style="text-align: left; padding-left: 8px;">TYPE</th>
                @for ($h = 0; $h < 24; $h++)
                    <th>{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00</th>
                @endfor
                <th style="color: #f59e0b;">TOT</th>
            </tr>
        </thead>
        <tbody>
            @php
                $rows = [
                    'dep_dom' => ['label' => 'Dep Dom', 'color' => '#60a5fa'],
                    'dep_int' => ['label' => 'Dep Int', 'color' => '#93c5fd'],
                    'arr_dom' => ['label' => 'Arr Dom', 'color' => '#fbbf24'],
                    'arr_int' => ['label' => 'Arr Int', 'color' => '#fde047'],
                ];
            @endphp
            @foreach ($rows as $key => $r)
                <tr>
                    <td class="label" style="color: {{ $r['label'] === 'Dep Dom' || $r['label'] === 'Dep Int' ? '#60a5fa' : '#fbbf24' }};">{{ $r['label'] }}</td>
                    @php $rowTot = 0; @endphp
                    @for ($h = 0; $h < 24; $h++)
                        @php
                            $val = $summary[$h][$key] ?? 0;
                            $rowTot += $val;
                        @endphp
                        <td style="{{ $val > 0 ? 'color:#fff; font-weight:bold;' : 'color:#475569;' }}">{{ $val ?: '·' }}</td>
                    @endfor
                    <td style="color: {{ $r['label'] === 'Dep Dom' || $r['label'] === 'Dep Int' ? '#60a5fa' : '#fbbf24' }}; font-weight:bold;">{{ $rowTot }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ══ PAGE 2+: DETAILED FLIGHT SCHEDULE REPORT ═══════════════════════════════ --}}
    <div class="page-break"></div>

    <div class="details-section">
        <div class="details-header">
            <h2>Flight Schedule Details</h2>
            <p>Bandara {{ (isset($airport) && $airport) ? $airport->name . ' (' . $airport->iata_code . ')' : 'Husein Sastranegara (BDO)' }} &nbsp;|&nbsp; Schedule Date &bull; {{ now()->format('d M Y') }} &nbsp;|&nbsp; Total Flights: {{ count($detailedFlights) }}</p>
        </div>

        <table class="details-table">
            <thead>
                <tr>
                    <th style="width: 25px; text-align: center;">No</th>
                    <th style="width: 140px;">Airline</th>
                    <th style="width: 70px;">Flight No</th>
                    <th style="width: 60px;">Type A/C</th>
                    <th style="width: 45px; text-align: center;">Dir</th>
                    <th style="width: 55px; text-align: center;">STA/STD</th>
                    <th style="width: 80px;">Route</th>
                    <th>Airport (Asal / Tujuan)</th>
                    <th style="width: 75px;">Region</th>
                    <th style="width: 150px;">Management</th>
                    <th style="width: 80px;">Category</th>
                    <th style="width: 65px; text-align: center;">DOS</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detailedFlights as $df)
                    <tr>
                        <td style="text-align: center; color: #94a3b8; font-family: monospace;">{{ $df['no'] }}</td>
                        <td style="font-weight: bold; color: #ffffff;">{{ $df['airline'] }}</td>
                        <td style="font-weight: 900; font-family: monospace; color: #38bdf8;">{{ $df['flight_number'] }}</td>
                        <td style="font-weight: bold; color: #fbbf24;">{{ $df['aircraft_type'] }}</td>
                        <td style="text-align: center;">
                            @if ($df['direction'] === 'ARR')
                                <span class="badge-arr">ARR</span>
                            @else
                                <span class="badge-dep">DEP</span>
                            @endif
                        </td>
                        <td style="text-align: center; font-weight: 900; font-family: monospace; color: #ffffff;">{{ $df['scheduled_time'] }}</td>
                        <td style="font-weight: 900; font-family: 'DejaVu Sans', sans-serif; color: #e2e8f0; font-size: 9.5px; white-space: nowrap;">{{ $df['route'] }}</td>
                        <td style="color: #f1f5f9;">{{ $df['airport'] }}</td>
                        <td style="font-family: monospace; color: #38bdf8;">{{ $df['region'] }}</td>
                        <td style="color: #cbd5e1;">{{ $df['management'] }}</td>
                        <td style="color: #94a3b8;">{{ $df['category'] }}</td>
                        <td style="text-align: center; font-weight: bold; font-family: monospace; color: #a5f3fc;">{{ $df['operating_days'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Inline Page Numbering Script for Dompdf --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Generated by SlotWaves  |  Page {PAGE_NUM} of {PAGE_COUNT}";
            $size = 9;
            $font = $fontMetrics->getFont("Helvetica", "normal");
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 25;
            $pdf->page_text($x, $y, $text, $font, $size, [0.58, 0.64, 0.72]);
        }
    </script>

</body>
</html>
