<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DAU-02 Comparison Report — {{ $comparison['airport_name'] }} ({{ $comparison['airport_code'] }})</title>
    <style>
        @page {
            margin: 12mm 12mm 12mm 12mm;
            size: a4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.35;
        }
        .page-break {
            page-break-before: always;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .brand-title {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .brand-sub {
            font-size: 9.5px;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        .badge {
            background-color: #f1f5f9;
            color: #0284c7;
            padding: 2.5px 7px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
        }
        .filter-banner {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #0284c7;
            padding: 5px 8px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 9.5px;
        }
        .period-card-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin-bottom: 12px;
        }
        .period-card-cell {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #0284c7;
            padding: 6px;
            border-radius: 4px;
            vertical-align: top;
        }
        .section-header {
            margin-top: 12px;
            margin-bottom: 8px;
            border-bottom: 1.5px solid #0284c7;
            padding-bottom: 4px;
        }
        .section-tag {
            font-size: 8.5px;
            font-weight: bold;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-title {
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            margin: 1px 0 2px 0;
        }
        .section-desc {
            font-size: 9px;
            color: #64748b;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        .table-data th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: right;
        }
        .table-data th:first-child {
            text-align: left;
        }
        .table-data td {
            border: 1px solid #e2e8f0;
            padding: 5px 6px;
            text-align: right;
            font-size: 9px;
        }
        .table-data td:first-child {
            text-align: left;
            font-weight: bold;
        }
        .chart-container {
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px;
            background: #ffffff;
        }
        .chart-title-bar {
            font-size: 9px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 4px;
            padding-bottom: 3px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
        }
        .text-green { color: #059669; font-weight: bold; }
        .text-red { color: #dc2626; font-weight: bold; }
        .footer {
            margin-top: 15px;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ═══════════════════════════════════════════════════════════════════════
         PAGE 1: OVERVIEW & BASELINE COMPARISON
         ═══════════════════════════════════════════════════════════════════════ --}}

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="vertical-align: middle;">
                <h1 class="brand-title">SLOTWAVES &bull; DAU-02 HISTORICAL COMPARISON</h1>
                <p class="brand-sub">{{ $comparison['airport_name'] }} ({{ $comparison['airport_code'] }}) &bull; {{ $comparison['period_count'] }} Reporting Periods Evaluated</p>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <span class="badge">Official Comparison Report</span>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 3px;">Generated: {{ now()->format('d-m-Y H:i') }} WIB</div>
            </td>
        </tr>
    </table>

    {{-- Filter state banner --}}
    <div class="filter-banner">
        <strong>Baseline Period:</strong> <span style="color:#0284c7; font-weight:bold;">{{ $comparison['periods'][$comparison['baseline_period_key']]['label'] ?? 'P1' }} ({{ $comparison['periods'][$comparison['baseline_period_key']]['short_label'] ?? '' }})</span> &bull;
        <strong>Historical Scope:</strong> <strong>{{ $filters['hist_scope'] ?? 'ALL' }}</strong> &bull;
        <strong>Historical Direction:</strong> <strong>{{ $filters['hist_direction'] ?? 'ALL' }}</strong>
    </div>

    {{-- Period Overview Cards --}}
    <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; color: #475569; margin-bottom: 4px;">Evaluated Operational Periods</div>
    <table class="period-card-table">
        <tr>
            @foreach ($comparison['periods'] as $pKey => $p)
                <td class="period-card-cell" style="width: {{ round(100 / count($comparison['periods'])) }}%;">
                    <div style="font-size: 8.5px; font-weight: bold; color: #0284c7; text-transform: uppercase;">
                        {{ $p['label'] }} ({{ $p['short_label'] }})
                    </div>
                    <div style="font-size: 10px; font-weight: bold; color: #0f172a; margin: 2px 0;">
                        {{ $p['display_range'] }}
                    </div>
                    <div style="font-size: 8px; color: #64748b;">
                        {{ $p['data_days'] }} Data Days &bull; {{ $p['airport_code'] }}
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    {{-- Baseline Comparison Section --}}
    <div class="section-header">
        <div class="section-tag">BASELINE ANALYSIS</div>
        <div class="section-title">BASELINE OPERATIONAL COMPARISON</div>
        <div class="section-desc">
            Baseline: <strong>{{ $comparison['periods'][$comparison['baseline_period_key']]['label'] ?? 'P1' }} ({{ $comparison['periods'][$comparison['baseline_period_key']]['short_label'] ?? '' }})</strong> compared independently against all {{ count($comparison['baseline_comparison']['comparisons']) }} other periods
        </div>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 22%;">Target Period</th>
                <th style="width: 26%;">Pergerakan Penumpang (Pax)</th>
                <th style="width: 26%;">Pergerakan Pesawat (A/C)</th>
                <th style="width: 26%;">Pergerakan Kargo ({{ $comparison['cargo_unit'] }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comparison['baseline_comparison']['comparisons'] as $comp)
                @php
                    $t = $comp['target_period'];
                    $pax = $comp['passenger'];
                    $ac  = $comp['aircraft'];
                    $cg  = $comp['cargo'];
                @endphp
                <tr>
                    <td>
                        <div style="font-weight: bold; color: #0f172a;">{{ $t['label'] }} ({{ $t['short_label'] }})</div>
                        <div style="font-size: 8px; color: #64748b; font-weight: normal;">{{ $t['display_range'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight: bold;">{{ number_format($pax['current']) }} Pax</div>
                        <div style="font-size: 8px;">
                            <span class="{{ $pax['is_positive'] ? 'text-green' : 'text-red' }}">{{ $pax['percentage_fmt'] }}</span>
                            <span style="color: #64748b;">({{ $pax['change_fmt'] }})</span>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: bold;">{{ number_format($ac['current']) }} Movements</div>
                        <div style="font-size: 8px;">
                            <span class="{{ $ac['is_positive'] ? 'text-green' : 'text-red' }}">{{ $ac['percentage_fmt'] }}</span>
                            <span style="color: #64748b;">({{ $ac['change_fmt'] }})</span>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: bold;">{{ number_format($cg['current']) }} {{ $comparison['cargo_unit'] }}</div>
                        <div style="font-size: 8px;">
                            <span class="{{ $cg['is_positive'] ? 'text-green' : 'text-red' }}">{{ $cg['percentage_fmt'] }}</span>
                            <span style="color: #64748b;">({{ $cg['change_fmt'] }})</span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="font-size: 8.5px; color: #64748b; margin-top: 4px; font-style: italic;">
        * Baseline values: Passenger: {{ number_format($comparison['baseline_comparison']['baseline_period']['raw_totals']['passenger'] ?? 0) }} Pax &bull;
        Aircraft: {{ number_format($comparison['baseline_comparison']['baseline_period']['raw_totals']['aircraft'] ?? 0) }} Movements &bull;
        Cargo: {{ number_format($comparison['baseline_comparison']['baseline_period']['raw_totals']['cargo'] ?? 0) }} {{ $comparison['cargo_unit'] }}.
    </div>

    <div class="footer">
        Page 1 of 3 &bull; SlotWaves DAU-02 Comparative Analytics Engine &bull; Official Report
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         PAGE 2: SECTION 1 — KINERJA OPERASIONAL BANDARA (TREND LINES + POINTS)
         ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="page-break"></div>

    <table class="header-table">
        <tr>
            <td style="vertical-align: middle;">
                <div class="section-tag">SECTION 1 &bull; OPERATIONAL PERFORMANCE</div>
                <h2 class="brand-title">KINERJA OPERASIONAL BANDARA — TREND ANALYSIS</h2>
                <p class="brand-sub">Continuous operational trend across uploaded periods (Line + Data Points &bull; Independent Scales)</p>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <span class="badge">Trend Analysis</span>
            </td>
        </tr>
    </table>

    {{-- Chart 1: Passenger Trend --}}
    <div class="chart-container">
        <div class="chart-title-bar">
            <span>1. Pergerakan Penumpang — Trend</span>
            <span style="color: #2563eb;">Unit: Pax</span>
        </div>
        <div>
            <img src="{{ $charts['trend_passenger'] }}" style="width: 100%; height: auto; display: block;" />
        </div>
    </div>

    {{-- Chart 2: Aircraft Trend --}}
    <div class="chart-container">
        <div class="chart-title-bar">
            <span>2. Pergerakan Pesawat — Trend</span>
            <span style="color: #059669;">Unit: Movements / A/C</span>
        </div>
        <div>
            <img src="{{ $charts['trend_aircraft'] }}" style="width: 100%; height: auto; display: block;" />
        </div>
    </div>

    {{-- Chart 3: Cargo Trend --}}
    <div class="chart-container">
        <div class="chart-title-bar">
            <span>3. Pergerakan Kargo — Trend</span>
            <span style="color: #d97706;">Unit: {{ $comparison['cargo_unit'] }}</span>
        </div>
        <div>
            <img src="{{ $charts['trend_cargo'] }}" style="width: 100%; height: auto; display: block;" />
        </div>
    </div>

    {{-- Operational Trend Values Table --}}
    <table class="table-data" style="margin-top: 6px;">
        <thead>
            <tr>
                <th>Metrik Operasional</th>
                <th>Unit</th>
                @foreach ($comparison['periods'] as $p)
                    <th>{{ $p['label'] }}<br><span style="font-weight: normal; font-size: 7.5px;">{{ $p['short_label'] }}</span></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Pergerakan Penumpang</td>
                <td style="text-align: center; color: #64748b;">Pax</td>
                @foreach ($comparison['operational_trend']['passenger'] as $pt)
                    <td>{{ number_format($pt['value']) }}</td>
                @endforeach
            </tr>
            <tr>
                <td>Pergerakan Pesawat</td>
                <td style="text-align: center; color: #64748b;">Movements</td>
                @foreach ($comparison['operational_trend']['aircraft'] as $pt)
                    <td>{{ number_format($pt['value']) }}</td>
                @endforeach
            </tr>
            <tr>
                <td>Pergerakan Kargo</td>
                <td style="text-align: center; color: #64748b;">{{ $comparison['cargo_unit'] }}</td>
                @foreach ($comparison['operational_trend']['cargo'] as $pt)
                    <td>{{ number_format($pt['value']) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Page 2 of 3 &bull; SlotWaves DAU-02 Comparative Analytics Engine &bull; Official Report
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         PAGE 3: SECTION 2 — DATA PERGERAKAN HISTORIS (BAR CHARTS)
         ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="page-break"></div>

    <table class="header-table">
        <tr>
            <td style="vertical-align: middle;">
                <div class="section-tag">SECTION 2 &bull; HISTORICAL TRAFFIC DISTRIBUTION</div>
                <h2 class="brand-title">DATA PERGERAKAN HISTORIS — PERIOD DISTRIBUTION</h2>
                <p class="brand-sub">Period-by-period bar comparison &bull; Filter: <strong>{{ $comparison['historical_model']['subtitle'] }}</strong> (Scope: {{ $filters['hist_scope'] ?? 'ALL' }}, Direction: {{ $filters['hist_direction'] ?? 'ALL' }})</p>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <span class="badge">Period Distribution</span>
            </td>
        </tr>
    </table>

    {{-- Historical Bar Chart 1: Passenger --}}
    <div class="chart-container">
        <div class="chart-title-bar">
            <span>1. Pergerakan Penumpang — Bar Chart</span>
            <span style="color: #2563eb;">Unit: Pax</span>
        </div>
        <div>
            <img src="{{ $charts['bar_passenger'] }}" style="width: 100%; height: auto; display: block;" />
        </div>
    </div>

    {{-- Historical Bar Chart 2: Aircraft --}}
    <div class="chart-container">
        <div class="chart-title-bar">
            <span>2. Pergerakan Pesawat — Bar Chart</span>
            <span style="color: #059669;">Unit: Movements / A/C</span>
        </div>
        <div>
            <img src="{{ $charts['bar_aircraft'] }}" style="width: 100%; height: auto; display: block;" />
        </div>
    </div>

    {{-- Historical Bar Chart 3: Cargo --}}
    <div class="chart-container">
        <div class="chart-title-bar">
            <span>3. Pergerakan Kargo — Bar Chart</span>
            <span style="color: #d97706;">Unit: {{ $comparison['cargo_unit'] }}</span>
        </div>
        <div>
            <img src="{{ $charts['bar_cargo'] }}" style="width: 100%; height: auto; display: block;" />
        </div>
    </div>

    {{-- Historical Breakdown Table --}}
    <table class="table-data" style="margin-top: 6px;">
        <thead>
            <tr>
                <th>Metrik Operasional (Filtered)</th>
                <th>Unit</th>
                @foreach ($comparison['periods'] as $p)
                    <th>{{ $p['label'] }}<br><span style="font-weight: normal; font-size: 7.5px;">{{ $p['short_label'] }}</span></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Pergerakan Penumpang</td>
                <td style="text-align: center; color: #64748b;">Pax</td>
                @foreach ($comparison['historical_model']['metrics']['passenger']['total_series'] as $v)
                    <td>{{ number_format($v) }}</td>
                @endforeach
            </tr>
            <tr>
                <td>Pergerakan Pesawat</td>
                <td style="text-align: center; color: #64748b;">Movements</td>
                @foreach ($comparison['historical_model']['metrics']['aircraft']['total_series'] as $v)
                    <td>{{ number_format($v) }}</td>
                @endforeach
            </tr>
            <tr>
                <td>Pergerakan Kargo</td>
                <td style="text-align: center; color: #64748b;">{{ $comparison['cargo_unit'] }}</td>
                @foreach ($comparison['historical_model']['metrics']['cargo']['total_series'] as $v)
                    <td>{{ number_format($v) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Page 3 of 3 &bull; SlotWaves DAU-02 Comparative Analytics Engine &bull; Official Report
    </div>

</body>
</html>
