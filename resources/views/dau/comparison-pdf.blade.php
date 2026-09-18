<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DAU-02 Comparison Report — {{ $comparison['airport_name'] }} ({{ $comparison['airport_code'] }})</title>
    <style>
        @page {
            margin: 15mm 12mm 15mm 12mm;
            size: a4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .brand-sub {
            font-size: 10px;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        .badge {
            background-color: #f1f5f9;
            color: #0284c7;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
        }
        .period-card-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 14px;
        }
        .period-card-cell {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0284c7;
            padding: 8px;
            border-radius: 4px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-top: 14px;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 12px;
        }
        .table-data th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: right;
        }
        .table-data th:first-child {
            text-align: left;
        }
        .table-data td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            text-align: right;
            font-size: 10px;
        }
        .table-data td:first-child {
            text-align: left;
            font-weight: bold;
        }
        .text-green { color: #059669; font-weight: bold; }
        .text-red { color: #dc2626; font-weight: bold; }
        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="vertical-align: middle;">
                <h1 class="brand-title">KINERJA OPERASIONAL BANDARA</h1>
                <p class="brand-sub">{{ $comparison['airport_name'] }} ({{ $comparison['airport_code'] }}) &bull; SlotWaves DAU-02 Historical Comparison</p>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <span class="badge">DAU-02 Comparison Report</span>
                <div style="font-size: 9px; color: #64748b; margin-top: 4px;">Generated: {{ now()->format('d-m-Y H:i') }} WIB</div>
            </td>
        </tr>
    </table>

    {{-- Filter state banner --}}
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 10px; border-radius: 4px; margin-bottom: 12px; font-size: 10px;">
        <strong>Active Filters:</strong> Scope: <strong>{{ $filters['flight_type'] }}</strong> &bull; Direction: <strong>{{ $filters['direction'] }}</strong> &bull; Baseline: <strong>{{ $comparison['periods'][$comparison['baseline_period_key']]['label'] ?? 'P1' }}</strong>
    </div>

    {{-- Period Overview Cards --}}
    <table class="period-card-table">
        <tr>
            @foreach ($comparison['periods'] as $pKey => $p)
                <td class="period-card-cell" style="width: {{ round(100 / count($comparison['periods'])) }}%;">
                    <div style="font-size: 9px; font-weight: bold; color: #0284c7; text-transform: uppercase;">{{ $p['label'] }}</div>
                    <div style="font-size: 11px; font-weight: bold; color: #0f172a; margin: 2px 0;">{{ $p['display_range'] }}</div>
                    <div style="font-size: 9px; color: #64748b;">{{ $p['data_days'] }} Data Days &bull; {{ $p['airport_code'] }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    {{-- Section 1: Three Primary Metrics --}}
    <div class="section-title">1. Operational Performance Highlights</div>
    <table class="table-data">
        <thead>
            <tr>
                <th>Metrik Operasional</th>
                <th>Unit</th>
                @foreach ($comparison['periods'] as $pKey => $p)
                    <th>{{ $p['label'] }}<br><span style="font-weight: normal; font-size: 8px;">{{ $p['short_label'] }}</span></th>
                @endforeach
                <th>Difference (&Delta;)</th>
                <th>Growth %</th>
                <th>Recovery %</th>
            </tr>
        </thead>
        <tbody>
            {{-- Passenger --}}
            <tr>
                <td>Pergerakan Penumpang</td>
                <td style="text-align: center; color: #64748b;">Pax</td>
                @foreach ($comparison['analysis']['passenger']['series'] as $pKey => $row)
                    <td>{{ number_format($row['value']) }}</td>
                @endforeach
                @php
                    $pSeries = array_values($comparison['analysis']['passenger']['series']);
                    $lastRowP = end($pSeries);
                @endphp
                <td class="{{ $lastRowP['difference'] >= 0 ? 'text-green' : 'text-red' }}">{{ $lastRowP['difference_fmt'] }}</td>
                <td class="{{ ($lastRowP['growth_pct'] ?? 0) >= 0 ? 'text-green' : 'text-red' }}">{{ $lastRowP['growth_fmt'] }}</td>
                <td>{{ $lastRowP['recovery_fmt'] }}</td>
            </tr>

            {{-- Aircraft --}}
            <tr>
                <td>Pergerakan Pesawat</td>
                <td style="text-align: center; color: #64748b;">Movements</td>
                @foreach ($comparison['analysis']['aircraft']['series'] as $pKey => $row)
                    <td>{{ number_format($row['value']) }}</td>
                @endforeach
                @php
                    $aSeries = array_values($comparison['analysis']['aircraft']['series']);
                    $lastRowA = end($aSeries);
                @endphp
                <td class="{{ $lastRowA['difference'] >= 0 ? 'text-green' : 'text-red' }}">{{ $lastRowA['difference_fmt'] }}</td>
                <td class="{{ ($lastRowA['growth_pct'] ?? 0) >= 0 ? 'text-green' : 'text-red' }}">{{ $lastRowA['growth_fmt'] }}</td>
                <td>{{ $lastRowA['recovery_fmt'] }}</td>
            </tr>

            {{-- Cargo --}}
            <tr>
                <td>Pergerakan Kargo</td>
                <td style="text-align: center; color: #64748b;">{{ $comparison['cargo_unit'] }}</td>
                @foreach ($comparison['analysis']['cargo']['series'] as $pKey => $row)
                    <td>{{ number_format($row['value']) }}</td>
                @endforeach
                @php
                    $cSeries = array_values($comparison['analysis']['cargo']['series']);
                    $lastRowC = end($cSeries);
                @endphp
                <td class="{{ $lastRowC['difference'] >= 0 ? 'text-green' : 'text-red' }}">{{ $lastRowC['difference_fmt'] }}</td>
                <td class="{{ ($lastRowC['growth_pct'] ?? 0) >= 0 ? 'text-green' : 'text-red' }}">{{ $lastRowC['growth_fmt'] }}</td>
                <td>{{ $lastRowC['recovery_fmt'] }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Section 2: Sequential Breakdown Table --}}
    <div class="section-title">2. Sequential Period Growth Analysis</div>
    <table class="table-data">
        <thead>
            <tr>
                <th>Period Transition</th>
                <th>Passenger Growth</th>
                <th>Aircraft Growth</th>
                <th>Cargo Growth</th>
            </tr>
        </thead>
        <tbody>
            @php
                $periodsArr = array_values($comparison['periods']);
                $n = count($periodsArr);
            @endphp
            @for ($i = 1; $i < $n; $i++)
                @php
                    $prev = $periodsArr[$i - 1];
                    $curr = $periodsArr[$i];
                    $p1 = $prev['metrics']['passenger']; $p2 = $curr['metrics']['passenger'];
                    $pGrowth = $p1 > 0 ? round((($p2 - $p1) / $p1) * 100, 2) . '%' : 'N/A';

                    $a1 = $prev['metrics']['aircraft']; $a2 = $curr['metrics']['aircraft'];
                    $aGrowth = $a1 > 0 ? round((($a2 - $a1) / $a1) * 100, 2) . '%' : 'N/A';

                    $c1 = $prev['metrics']['cargo']; $c2 = $curr['metrics']['cargo'];
                    $cGrowth = $c1 > 0 ? round((($c2 - $c1) / $c1) * 100, 2) . '%' : 'N/A';
                @endphp
                <tr>
                    <td>{{ $prev['label'] }} ({{ $prev['short_label'] }}) &rarr; {{ $curr['label'] }} ({{ $curr['short_label'] }})</td>
                    <td class="{{ str_starts_with($pGrowth, '-') ? 'text-red' : 'text-green' }}">{{ $pGrowth }}</td>
                    <td class="{{ str_starts_with($aGrowth, '-') ? 'text-red' : 'text-green' }}">{{ $aGrowth }}</td>
                    <td class="{{ str_starts_with($cGrowth, '-') ? 'text-red' : 'text-green' }}">{{ $cGrowth }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="footer">
        SlotWaves Airport Operational Slot &amp; Flight Intelligence &bull; DAU-02 Comparative Analytics Engine &bull; Official Report
    </div>

</body>
</html>
