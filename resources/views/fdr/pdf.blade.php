<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Flight Daily Report (FDR) — {{ $meta['airport'] ?? 'CGK' }}</title>
    <style>
        @page {
            margin: 18px 22px 25px 22px;
            size: a4 portrait;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1E293B;
            line-height: 1.35;
            background-color: #FFFFFF;
        }
        .header {
            border-bottom: 2px solid #0284C7;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header table {
            width: 100%;
        }
        .brand-title {
            font-size: 16px;
            font-weight: 900;
            color: #0369A1;
            letter-spacing: -0.5px;
        }
        .brand-sub {
            font-size: 9px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .meta-pill {
            display: inline-block;
            background-color: #F0F9FF;
            border: 1px solid #BAE6FD;
            color: #0369A1;
            padding: 3px 7px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            margin-right: 4px;
        }
        .section-title {
            font-size: 10.5px;
            font-weight: 800;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 10px 0 5px 0;
            border-left: 3px solid #0284C7;
            padding-left: 6px;
        }
        /* Top Metric Cards */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin-bottom: 10px;
        }
        .kpi-card {
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 5px;
            padding: 7px 8px;
            text-align: left;
            width: 25%;
        }
        .kpi-label {
            font-size: 7.5px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .kpi-value {
            font-size: 14px;
            font-weight: 900;
            color: #0F172A;
            margin-top: 1px;
        }
        .kpi-sub {
            font-size: 7.5px;
            color: #0284C7;
            font-weight: 600;
            margin-top: 1px;
        }
        /* Chart Containers */
        .chart-box {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 6px 8px;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .chart-header {
            font-size: 9px;
            font-weight: 800;
            color: #1E293B;
            margin-bottom: 4px;
            display: block;
        }
        .chart-legend {
            font-size: 7.5px;
            color: #64748B;
            margin-bottom: 4px;
        }
        .legend-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 2px;
            margin-right: 2px;
            vertical-align: middle;
        }
        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        .data-table th {
            background-color: #F1F5F9;
            color: #334155;
            font-weight: 800;
            text-align: left;
            padding: 4px 5px;
            border: 1px solid #CBD5E1;
            text-transform: uppercase;
            font-size: 7.5px;
        }
        .data-table td {
            padding: 3.5px 5px;
            border: 1px solid #E2E8F0;
            color: #1E293B;
        }
        .data-table tr:nth-child(even) {
            background-color: #F8FAFC;
        }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: 700;
        }
        .badge-green { background: #DCFCE7; color: #166534; }
        .badge-amber { background: #FEF3C7; color: #92400E; }
        .badge-red { background: #FEE2E2; color: #991B1B; }
        .badge-blue { background: #DBEAFE; color: #1E40AF; }
        .footer {
            margin-top: 15px;
            border-top: 1px solid #E2E8F0;
            padding-top: 6px;
            font-size: 7.5px;
            color: #94A3B8;
            text-align: right;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <table>
            <tr>
                <td style="width: 60%;">
                    <div class="brand-title">SLOTWAVES — FLIGHT DAILY REPORT (FDR)</div>
                    <div class="brand-sub">OASYS Operational Intelligence &amp; Movement Analytics &bull; <strong>PEAK DAILY ANALYSIS</strong></div>
                    <div style="margin-top: 6px;">
                        <span class="meta-pill">AIRPORT: {{ $meta['airport'] ?? 'CGK' }}</span>
                        <span class="meta-pill" style="background-color: #FEF3C7; border-color: #FDE68A; color: #92400E;">ANALYSIS DATE: {{ !empty($analysisDate) ? date('d-m-Y', strtotime($analysisDate)) : 'ALL' }}</span>
                        <span class="meta-pill">SOURCE PERIOD: {{ $meta['period_label'] ?? 'N/A' }}</span>
                        <span class="meta-pill">OPERATOR: {{ $filters['operator'] ?: ($meta['operator'] ?? 'ALL AIRLINE') }}</span>
                    </div>
                </td>
                <td style="width: 40%; text-align: right;">
                    <div style="font-size: 8px; color: #64748B;">Generated on: <strong>{{ date('d-M-Y H:i:s') }}</strong></div>
                    <div style="font-size: 8px; color: #64748B;">Source System: <strong>OASYS Operational Reporting</strong></div>
                    <div style="font-size: 8px; color: #0284C7; font-weight: bold; margin-top: 3px;">MODE {{ $reportMode }}: {{ $filters['mode_label'] ?? 'PEAK DAILY OPERATIONAL' }}</div>
                    @if(!empty($peakHour['display']) && $peakHour['display'] !== 'N/A')
                        <div style="font-size: 7.5px; color: #D97706; font-weight: bold; margin-top: 2px;">PEAK HOUR: {{ $peakHour['display'] }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- 1. TOP METRIC CARDS --}}
    <div class="section-title">Operational Key Performance Indicators ({{ strtoupper($analysisDateFormatted) }})</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-label">Analysis Day Flights</div>
                <div class="kpi-value">{{ number_format($kpis['total_flights']) }}</div>
                <div class="kpi-sub">Arr: {{ number_format($kpis['arrivals']) }} | Dep: {{ number_format($kpis['departures']) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Analysis Day Passengers</div>
                <div class="kpi-value">{{ number_format($kpis['total_passengers']) }}</div>
                <div class="kpi-sub">Adult: {{ number_format($kpis['adult_passengers']) }} | Chd: {{ number_format($kpis['child_passengers']) }} | Inf: {{ number_format($kpis['infant_passengers']) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Average Load Factor</div>
                <div class="kpi-value" style="color: #0284C7;">{{ $kpis['avg_load_factor'] }}</div>
                <div class="kpi-sub">Load: {{ number_format($kpis['total_load']) }} / Cap: {{ number_format($kpis['total_capacity']) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Peak Hour &amp; Cargo</div>
                <div class="kpi-value" style="font-size: 11px; margin-top: 3px; color: #D97706;">{{ $peakHour['time_range'] ?? 'N/A' }}</div>
                <div class="kpi-sub">{{ number_format($kpis['cargo_ton'], 1) }} Ton Cargo &bull; {{ $peakHour['movements'] ?? 0 }} Movements</div>
            </td>
        </tr>
    </table>

    {{-- 2. THE 3 MENTOR HOURLY CHARTS (NATIVE VECTOR SVGS) --}}
    <div class="section-title">PEAK DAILY ANALYSIS — {{ strtoupper($analysisDateFormatted) }} (Source: {{ $meta['period_label'] ?? 'N/A' }})</div>

    {{-- CHART 1: ARRIVAL-DEPARTURE MOVEMENT --}}
    <div class="chart-box">
        <span class="chart-header">Chart 1: ARRIVAL–DEPARTURE MOVEMENT</span>
        <div class="chart-legend">
            <span class="legend-dot" style="background: #FDBA74;"></span> Plan (PPRP)
            &nbsp;&nbsp;
            <span class="legend-dot" style="background: #D97706;"></span> Irregular Flt
            &nbsp;&nbsp;
            <span class="legend-dot" style="background: #EF4444;"></span> Runway Capacity (Continuous Variable Profile)
        </div>
        {!! $svgChart1 !!}
    </div>

    {{-- CHART 2: DEPARTURE MOVEMENT --}}
    <div class="chart-box">
        <span class="chart-header">Chart 2: DEPARTURE MOVEMENT (Blue Semantic Palette)</span>
        <div class="chart-legend">
            <span class="legend-dot" style="background: #93C5FD;"></span> Plan (PPRP)
            &nbsp;&nbsp;
            <span class="legend-dot" style="background: #1D4ED8;"></span> Irregular Flt
        </div>
        {!! $svgChart2 !!}
    </div>

    {{-- CHART 3: ARRIVAL MOVEMENT --}}
    <div class="chart-box">
        <span class="chart-header">Chart 3: ARRIVAL MOVEMENT (Salmon/Magenta Semantic Palette)</span>
        <div class="chart-legend">
            <span class="legend-dot" style="background: #FDA4AF;"></span> Plan (PPRP)
            &nbsp;&nbsp;
            <span class="legend-dot" style="background: #BE185D;"></span> Irregular Flt
        </div>
        {!! $svgChart3 !!}
    </div>

    <div class="page-break"></div>

    {{-- PAGE 2: OPERATIONAL SUMMARY & TABLES --}}
    <div class="header">
        <table>
            <tr>
                <td><div class="brand-title">SLOTWAVES — OPERATIONAL MODULES &amp; FLIGHT RECORDS</div></td>
                <td style="text-align: right;"><span class="meta-pill">Page 2</span></td>
            </tr>
        </table>
    </div>

    {{-- Schedule vs Realization & Stand / Runway Utilization --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 6px;">
                <div class="section-title">Schedule vs Realization (Punctuality)</div>
                <table class="data-table">
                    <tr>
                        <th>Metric</th>
                        <th>Value</th>
                    </tr>
                    <tr>
                        <td>Evaluated Realized Flights</td>
                        <td><strong>{{ number_format($schedVsReal['evaluated_flights'] ?? 0) }}</strong></td>
                    </tr>
                    <tr>
                        <td>On-Time Adherence (≤ 15 min)</td>
                        <td><span class="badge badge-green">{{ $schedVsReal['on_time_percentage'] ?? '100%' }}</span></td>
                    </tr>
                    <tr>
                        <td>Average Variance Delay</td>
                        <td><strong>{{ $schedVsReal['avg_delay_minutes'] ?? 0 }} mins</strong></td>
                    </tr>
                    <tr>
                        <td>Irregular Flights (Divert / Miss / Unscheduled)</td>
                        <td><span class="badge badge-amber">{{ $kpis['irregularities']['total'] }} flts</span> (Divert: {{ $kpis['irregularities']['divert'] }}, Miss: {{ $kpis['irregularities']['miss'] }})</td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 6px;">
                <div class="section-title">Ground Operations (Stands &amp; Runways)</div>
                <table class="data-table">
                    <tr>
                        <th>Top Stands</th>
                        <th>Flights</th>
                        <th>Top Runways</th>
                        <th>Flights</th>
                    </tr>
                    @for($s = 0; $s < 4; $s++)
                        @php
                            $st = $groundOps['stands'][$s] ?? null;
                            $rw = $groundOps['runways'][$s] ?? null;
                        @endphp
                        <tr>
                            <td>{{ $st ? $st['stand'] : '-' }}</td>
                            <td>{{ $st ? $st['count'] . ' (' . $st['percentage'] . '%)' : '-' }}</td>
                            <td>{{ $rw ? $rw['runway'] : '-' }}</td>
                            <td>{{ $rw ? $rw['count'] . ' (' . $rw['percentage'] . '%)' : '-' }}</td>
                        </tr>
                    @endfor
                </table>
            </td>
        </tr>
    </table>

    {{-- Top Airlines & Top Routes --}}
    <div class="section-title">Airline &amp; Route Performance (Top 5)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Airline Operator</th>
                <th>Flights</th>
                <th>Passengers</th>
                <th>Cargo (KG)</th>
                <th>Load Factor</th>
                <th>Top Route</th>
                <th>Route Flights</th>
                <th>Route LF%</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 0; $i < min(5, max(count($airlineRoute['ranked_airlines']), count($airlineRoute['top_routes']))); $i++)
                @php
                    $al = $airlineRoute['ranked_airlines'][$i] ?? null;
                    $rt = $airlineRoute['top_routes'][$i] ?? null;
                @endphp
                <tr>
                    <td><strong>{{ $al['airline'] ?? '-' }}</strong></td>
                    <td>{{ $al ? number_format($al['flights']) : '-' }}</td>
                    <td>{{ $al ? number_format($al['passengers']) : '-' }}</td>
                    <td>{{ $al ? number_format($al['cargo_kg'], 1) : '-' }}</td>
                    <td><span class="badge badge-blue">{{ $al['avg_load_factor'] ?? '-' }}</span></td>
                    <td><strong>{{ $rt['route'] ?? '-' }}</strong></td>
                    <td>{{ $rt ? number_format($rt['flights']) : '-' }}</td>
                    <td><span class="badge badge-green">{{ $rt['avg_load_factor'] ?? '-' }}</span></td>
                </tr>
            @endfor
        </tbody>
    </table>

    {{-- DETAILED FLIGHT TABLE (SAMPLE) --}}
    <div class="section-title">Flight Daily Records (Showing {{ count($records) }} of {{ $totalRecords }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Airline</th>
                <th>Flight No</th>
                <th>Leg</th>
                <th>Route</th>
                <th>SIBT / SOBT</th>
                <th>AIBT / AOBT</th>
                <th>Reg No</th>
                <th>Cap</th>
                <th>Load</th>
                <th>LF%</th>
                <th>Cargo</th>
                <th>Stand</th>
                <th>Rwy</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $r)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $r['air_line'] }}</td>
                    <td><strong>{{ $r['flight_no'] }}</strong></td>
                    <td><span class="badge {{ str_contains($r['leg'], 'A') ? 'badge-blue' : 'badge-green' }}">{{ $r['leg'] }}</span></td>
                    <td>{{ $r['route'] }}</td>
                    <td>{{ ($r['direction'] === 'ARRIVAL') ? $r['sibt'] : $r['sobt'] }}</td>
                    <td>{{ ($r['direction'] === 'ARRIVAL') ? $r['aibt'] : $r['aobt'] }}</td>
                    <td>{{ $r['reg_no'] }}</td>
                    <td>{{ $r['cap'] }}</td>
                    <td>{{ $r['load'] }}</td>
                    <td>{{ $r['load_factor'] !== 'N/A' ? $r['load_factor'] . '%' : 'N/A' }}</td>
                    <td>{{ number_format($r['cargo_kg']) }}</td>
                    <td>{{ $r['stand'] }}</td>
                    <td>{{ $r['runway'] }}</td>
                    <td>
                        @if(!empty($r['is_irregular']))
                            <span class="badge badge-red">IRREG</span>
                        @else
                            <span class="badge badge-green">NORMAL</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        SlotWaves Airport Operational Slot Management Platform • OASYS Flight Daily Report Analytics • Page 2 of 2
    </div>

</body>
</html>
