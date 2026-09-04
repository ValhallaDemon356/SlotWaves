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
            font-size: 7.5pt;
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

        /* Peak Analysis Box */
        .peak-analysis-box {
            border: 1px solid #93c5fd;
            background: #eff6ff;
            padding: 5px 8px;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        .peak-title {
            font-size: 7.5pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .peak-desc {
            font-size: 6.5pt;
            color: #1e40af;
            line-height: 1.3;
        }

        /* Chart Diagrams (CSS/HTML Vector Bars) */
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
            font-size: 6.5pt;
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
            padding: 4px 4px;
            border: 1px solid #334155;
            font-size: 6pt;
        }
        .data-table td {
            padding: 3px 4px;
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
                        @if ($reportType === 'DAU10')
                            JAM PUNCAK PESAWAT/PENUMPANG (DAU-10)
                        @elseif ($reportType === 'DAU10A')
                            JAM PUNCAK MENURUT TERMINAL (DAU-10A)
                        @elseif ($reportType === 'DAU10B')
                            JAM PUNCAK PESAWAT/PENUMPANG (BLOCK ON/OFF) (DAU-10B)
                        @else
                            {{ $conf['title'] ?? 'LAPORAN DAU' }}
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
            @if (($filters['hour'] ?? 'ALL') !== 'ALL')
                <tr>
                    <td colspan="4" style="color: #0284c7; font-weight: bold; border-top: 1px dashed #cbd5e1;">
                        FILTER AKTIF PERIODE JAM: {{ $filters['hour'] }}
                    </td>
                </tr>
            @endif
        </table>
    </div>

    {{-- ══ EXECUTIVE SUMMARY / KPI CARDS ════════════════════════════════════ --}}
    <table class="kpi-table">
        <tr>
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
                <div class="kpi-value">{{ number_format($summary['total_movements'] ?? 0) }}</div>
                <div class="kpi-sub">Arr: {{ number_format($summary['aircraft_arrival'] ?? 0) }} | Dep: {{ number_format($summary['aircraft_departure'] ?? 0) }}</div>
            </td>

            <td class="kpi-cell" style="width: 12.5%;">
                <div class="kpi-label">Total Passengers</div>
                <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
                <div class="kpi-sub">Arr: {{ number_format($summary['passenger_arrival'] ?? 0) }} | Dep: {{ number_format($summary['passenger_departure'] ?? 0) }}</div>
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
        </tr>
    </table>

    {{-- ══ PEAK HOUR ANALYSIS CALLOUT ═════════════════════════════════════════ --}}
    <div class="peak-analysis-box">
        <table style="width: 100%;">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    <div class="peak-title">Peak Hour Analysis Summary</div>
                    <div class="peak-desc">
                        Jam puncak operasional teridentifikasi pada periode <strong>{{ $peaks['peak_hour'] ?? '—' }}</strong> 
                        dengan total <strong>{{ number_format($peaks['peak_aircraft'] ?? 0) }}</strong> pergerakan pesawat 
                        dan <strong>{{ number_format($peaks['peak_passenger'] ?? 0) }}</strong> penumpang. 
                        @if (!empty($peaks['peak_terminal']) && $peaks['peak_terminal'] !== '—')
                            Konsentrasi aktivitas tertinggi tercatat pada <strong>Terminal {{ $peaks['peak_terminal'] }}</strong>.
                        @endif
                    </div>
                </td>
                <td style="width: 40%; text-align: right; vertical-align: top; font-size: 6.5pt;">
                    <div><strong>Filter Aktif:</strong> {{ $meta['flight_scope'] ?? 'DOM & INT' }} • {{ $meta['terminal_scope'] ?? 'ALL' }}</div>
                    <div style="color: #64748b; margin-top: 2px;">Jumlah Rekod Terpilih: <strong>{{ count($records) }}</strong> data rows</div>
                </td>
            </tr>
        </table>
    </div>

    @if ($reportType === 'DAU10A' && !empty($capacitySummary))
        {{-- ══ DAU-10A CAPACITY SUMMARY ════════════════════════════════════════ --}}
        <table class="kpi-table" style="margin-bottom: 6px; border: 1.5px solid #0284c7; background: #f0f9ff;">
            <tr>
                <td class="kpi-cell" style="width: 16.6%; background: #ffffff;">
                    <div class="kpi-label">Aircraft Capacity</div>
                    <div class="kpi-value" style="color: #0f172a;">{{ $capacitySummary['nac'] ?? 6 }} A/C</div>
                    <div class="kpi-sub">Batas Maksimum NAC</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%; background: #ffffff;">
                    <div class="kpi-label">Peak Aircraft</div>
                    <div class="kpi-value" style="color: #d97706;">{{ number_format($capacitySummary['peak_aircraft'] ?? 0) }} A/C</div>
                    <div class="kpi-sub">{{ $capacitySummary['peak_hour'] ?? '—' }}</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%; background: #ffffff;">
                    <div class="kpi-label">Peak Hour</div>
                    <div class="kpi-value" style="color: #2563eb; font-size: 8pt;">{{ $capacitySummary['peak_hour'] ?? '—' }}</div>
                    <div class="kpi-sub">Demand Tertinggi</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%; background: #ffffff;">
                    <div class="kpi-label">Available Hours</div>
                    <div class="kpi-value" style="color: #059669;">{{ $capacitySummary['available_hours'] ?? 0 }} Jam</div>
                    <div class="kpi-sub">Demand &lt; NAC</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%; background: #ffffff;">
                    <div class="kpi-label">Full / Max Hours</div>
                    <div class="kpi-value" style="color: #d97706;">{{ $capacitySummary['full_hours'] ?? 0 }} Jam</div>
                    <div class="kpi-sub">Demand == NAC</div>
                </td>
                <td class="kpi-cell" style="width: 16.6%; background: #ffffff;">
                    <div class="kpi-label">Over Capacity</div>
                    <div class="kpi-value" style="color: #7c3aed;">{{ $capacitySummary['over_capacity_hours'] ?? 0 }} Jam</div>
                    <div class="kpi-sub">Demand &gt; NAC</div>
                </td>
            </tr>
        </table>
    @endif

    {{-- ══ ANALYTICAL CHARTS / DIAGRAMS SECTION ══════════════════════════════ --}}
    @if ($reportType === 'DAU10')
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
        {{-- DAU-10A DISTRIBUSI PER JAM & HOURLY CAPACITY STATUS --}}
        @php
            $maxDemandPdf = max((int)($capacitySummary['nac'] ?? 6), 1);
            foreach ($hourlyData as $hd) {
                $dem = ($hd['aircraft_arrival'] ?? 0) + ($hd['aircraft_departure'] ?? 0);
                if ($dem > $maxDemandPdf) $maxDemandPdf = $dem;
            }
        @endphp

        <div class="chart-box">
            <div class="chart-header" style="display: table; width: 100%;">
                <div style="display: table-cell; text-align: left;">
                    DISTRIBUSI PER JAM — Aircraft Movement & Operational Capacity Analysis (Batas NAC: {{ $capacitySummary['nac'] ?? 6 }} A/C)
                </div>
                <div style="display: table-cell; text-align: right; font-size: 5.5pt; color: #475569;">
                    <span style="display: inline-block; width: 7px; height: 7px; background: #f59e0b; vertical-align: middle;"></span> Arrival &nbsp;
                    <span style="display: inline-block; width: 7px; height: 7px; background: #0284c7; vertical-align: middle;"></span> Departure &nbsp;
                    <span style="display: inline-block; width: 7px; height: 7px; background: #9333ea; opacity: 0.5; vertical-align: middle;"></span> OPC (N/A) &nbsp;
                    <span style="display: inline-block; width: 12px; border-top: 1.5px dashed #475569; vertical-align: middle;"></span> NAC ({{ $capacitySummary['nac'] ?? 6 }} A/C)
                </div>
            </div>

            <table class="chart-table">
                <tr>
                    @foreach ($hourlyData as $hd)
                        @php
                            $arrVal = (int)($hd['aircraft_arrival'] ?? 0);
                            $depVal = (int)($hd['aircraft_departure'] ?? 0);
                            $demVal = $arrVal + $depVal;
                            $arrH = max(2, round(($arrVal / max(1, $maxDemandPdf)) * 44));
                            $depH = max(2, round(($depVal / max(1, $maxDemandPdf)) * 44));
                            $nacVal = (int)($capacitySummary['nac'] ?? 6);
                            $statusText = $demVal > $nacVal ? 'OVER' : ($demVal === $nacVal ? 'FULL' : 'AVAIL');
                            $statusCol  = $demVal > $nacVal ? '#7c3aed' : ($demVal === $nacVal ? '#d97706' : '#059669');
                        @endphp
                        <td style="width: {{ 100 / max(1, count($hourlyData)) }}%;">
                            <div style="font-size: 4.5pt; font-weight: bold; margin-bottom: 1px; color: {{ $statusCol }};">
                                {{ $statusText }}
                            </div>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 50%; padding: 0;">
                                        <div class="bar-wrap" style="background: transparent;">
                                            <div class="bar-inner" style="height: {{ $arrH }}px; background-color: #f59e0b;"></div>
                                        </div>
                                    </td>
                                    <td style="width: 50%; padding: 0;">
                                        <div class="bar-wrap" style="background: transparent;">
                                            <div class="bar-inner" style="height: {{ $depH }}px; background-color: #0284c7;"></div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <div style="font-size: 4.5pt; color: #64748b; margin-top: 2px;">
                                {{ explode(' - ', $hd['hour'])[0] ?? $hd['hour'] }}
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>

        {{-- Hourly Capacity Status Table --}}
        <div class="chart-box" style="margin-top: 5px;">
            <div class="chart-header">
                HOURLY CAPACITY STATUS (Evaluasi Demand vs NAC: {{ $capacitySummary['nac'] ?? 6 }} A/C)
            </div>
            <table class="data-table" style="margin-top: 1px;">
                <thead>
                    <tr>
                        <th style="width: 14%; text-align: left;">Hour</th>
                        <th class="text-right" style="width: 12%;">ARR</th>
                        <th class="text-right" style="width: 12%;">DEP</th>
                        <th class="text-center" style="width: 10%;">OPC</th>
                        <th class="text-right" style="width: 16%;">Aircraft Demand</th>
                        <th class="text-center" style="width: 10%;">NAC</th>
                        <th class="text-right" style="width: 12%;">Utilization</th>
                        <th class="text-center" style="width: 14%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hourlyCapacityStatus as $hcs)
                        <tr>
                            <td class="font-bold text-left">{{ $hcs['hour'] }}</td>
                            <td class="text-right" style="color: #d97706; font-weight: bold;">{{ number_format($hcs['arr']) }}</td>
                            <td class="text-right" style="color: #0284c7; font-weight: bold;">{{ number_format($hcs['dep']) }}</td>
                            <td class="text-center" style="color: #64748b;">{{ $hcs['opc'] }}</td>
                            <td class="text-right font-bold">{{ number_format($hcs['demand']) }}</td>
                            <td class="text-center">{{ $hcs['nac'] }}</td>
                            <td class="text-right font-bold" style="color: {{ $hcs['utilization'] > 100 ? '#7c3aed' : ($hcs['utilization'] == 100 ? '#d97706' : '#059669') }};">
                                {{ $hcs['utilization'] }}%
                            </td>
                            <td class="text-center">
                                <span class="{{ $hcs['status'] === 'AVAILABLE' ? 'badge-available' : ($hcs['status'] === 'FULL / MAX' ? 'badge-full' : 'badge-over') }}">
                                    {{ $hcs['status'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

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
                $maxVal = ($metric === 'passenger') ? 3500 : 30;
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
            <div class="chart-header">
                Block On (Inbound Gates / DTG) vs Block Off (Outbound Gates / BRK) Hourly Distribution
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
            <div style="font-size: 5.5pt; color: #64748b; margin-top: 3px; text-align: right;">
                <span style="display: inline-block; width: 8px; height: 8px; background: #7c3aed; vertical-align: middle;"></span> Block On (DTG) &nbsp;&nbsp;
                <span style="display: inline-block; width: 8px; height: 8px; background: #f59e0b; vertical-align: middle;"></span> Block Off (BRK)
            </div>
        </div>
    @endif

    {{-- ══ MAIN DATA TABLE ════════════════════════════════════════════════════ --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%; text-align: center;">#</th>
                @if ($reportType === 'DAU10' || $reportType === 'DAU10A' || $reportType === 'DAU10B')
                    <th style="width: 11%;">Jam / Periode</th>
                    <th style="width: 8%;">Terminal</th>
                    <th class="text-right" style="width: 7%;">
                        @if ($reportType === 'DAU10B') Acft On (DTG) @else Acft ARR @endif
                    </th>
                    <th class="text-right" style="width: 7%;">
                        @if ($reportType === 'DAU10B') Acft Off (BRK) @else Acft DEP @endif
                    </th>
                    <th class="text-right" style="width: 7%;">Total Acft</th>
                    <th class="text-right" style="width: 8%;">
                        @if ($reportType === 'DAU10B') Pax On (DTG) @else Pax ARR @endif
                    </th>
                    <th class="text-right" style="width: 8%;">
                        @if ($reportType === 'DAU10B') Pax Off (BRK) @else Pax DEP @endif
                    </th>
                    <th class="text-right" style="width: 8%;">Total Pax</th>
                    <th class="text-right" style="width: 6%;">Transit</th>
                    <th class="text-right" style="width: 6%;">Transfer</th>
                    <th class="text-right" style="width: 6%;">Awak (Crew)</th>
                    <th class="text-right" style="width: 6%;">Ex Crew</th>
                    @if ($reportType !== 'DAU10A')
                        <th class="text-right" style="width: 8%;">Bagasi (Kg)</th>
                        <th class="text-right" style="width: 8%;">Kargo (Kg)</th>
                    @endif
                @else
                    {{-- Generic fallback for other DAU reports --}}
                    <th>Category / Route</th>
                    <th class="text-right">Total Aircraft</th>
                    <th class="text-right">Total Passenger</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $idx => $r)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    @if ($reportType === 'DAU10' || $reportType === 'DAU10A' || $reportType === 'DAU10B')
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
                        <td class="text-right">{{ number_format($r['extra_crew'] ?? 0) }}</td>
                        @if ($reportType !== 'DAU10A')
                            <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>
                        @endif
                    @else
                        <td>{{ $r['category'] ?? $r['airport'] ?? 'Row ' . ($idx+1) }}</td>
                        <td class="text-right">{{ number_format($r['aircraft_total'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['passenger_total'] ?? 0) }}</td>
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

</body>
</html>
