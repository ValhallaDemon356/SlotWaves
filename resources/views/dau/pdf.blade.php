<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $conf['title'] ?? 'DAU Report' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 10mm 12mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #1e293b;
            line-height: 1.25;
        }
        .header-box {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .title {
            font-size: 14pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }
        .subtitle {
            font-size: 9pt;
            color: #475569;
            font-weight: bold;
        }
        .meta-table {
            width: 100%;
            margin-top: 6px;
            font-size: 7.5pt;
        }
        .meta-table td {
            padding: 2px 4px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .kpi-cell {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 6px;
            text-align: center;
        }
        .kpi-label {
            font-size: 6.5pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .kpi-value {
            font-size: 12pt;
            font-weight: bold;
            color: #0284c7;
            margin-top: 2px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 5px;
            border: 1px solid #334155;
            font-size: 6.5pt;
        }
        .data-table td {
            padding: 3px 5px;
            border: 1px solid #cbd5e1;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 7pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            text-align: right;
        }
    </style>
</head>
<body>

    <div class="header-box">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="title">{{ $conf['title'] ?? 'DATA ANGKUTAN UDARA' }}</div>
                    <div class="subtitle">{{ $meta['airport_name'] ?? 'Tangerang Banten - Soekarno Hatta' }} ({{ $meta['airport_code'] ?? 'CGK' }})</div>
                </td>
                <td style="text-align: right;">
                    <div style="font-size: 12pt; font-weight: bold; color: #0284c7;">{{ $conf['code'] ?? $reportType }}</div>
                    <div style="font-size: 7pt; color: #64748b;">Source: OASYS</div>
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
        </table>
    </div>

    {{-- KPI Summary --}}
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell" style="width: 16.6%;">
                <div class="kpi-label">Total Movements</div>
                <div class="kpi-value">{{ number_format($summary['total_movements'] ?? 0) }}</div>
            </td>
            <td class="kpi-cell" style="width: 16.6%;">
                <div class="kpi-label">Total Passengers</div>
                <div class="kpi-value" style="color: #059669;">{{ number_format($summary['passenger_total'] ?? 0) }}</div>
            </td>
            <td class="kpi-cell" style="width: 16.6%;">
                <div class="kpi-label">Transit &amp; Transfer</div>
                <div class="kpi-value" style="color: #2563eb;">{{ number_format(($summary['passenger_transit'] ?? 0) + ($summary['passenger_transfer'] ?? 0)) }}</div>
            </td>
            <td class="kpi-cell" style="width: 16.6%;">
                <div class="kpi-label">Baggage (Kg)</div>
                <div class="kpi-value" style="color: #d97706;">{{ number_format($summary['baggage_total'] ?? 0) }}</div>
            </td>
            <td class="kpi-cell" style="width: 16.6%;">
                <div class="kpi-label">Cargo (Kg)</div>
                <div class="kpi-value" style="color: #4f46e5;">{{ number_format($summary['cargo_total'] ?? 0) }}</div>
            </td>
            <td class="kpi-cell" style="width: 16.6%;">
                <div class="kpi-label">POS / Mail (Kg)</div>
                <div class="kpi-value" style="color: #7c3aed;">{{ number_format($summary['pos_total'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    {{-- Main Data Table --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                @if ($reportType === 'DAU1')
                    <th style="width: 18%;">Route / Airport</th>
                    <th style="width: 10%;">Flight No</th>
                    <th style="width: 12%;">Tipe Pesawat</th>
                    <th class="text-right">Kapasitas</th>
                    <th class="text-right">Acft DTG</th>
                    <th class="text-right">Acft BRK</th>
                    <th class="text-right">Total Acft</th>
                    <th class="text-right">Pass DTG</th>
                    <th class="text-right">Pass BRK</th>
                    <th class="text-right">Total Pass</th>
                    <th class="text-right">Bagasi</th>
                    <th class="text-right">Kargo</th>
                @elseif ($reportType === 'DAU2' || $reportType === 'DAU3')
                    <th>Kategori</th>
                    <th class="text-right">Pesawat DTG</th>
                    <th class="text-right">Pesawat BRK</th>
                    <th class="text-right">Total Pesawat</th>
                    <th class="text-right">Pax DTG</th>
                    <th class="text-right">Pax BRK</th>
                    <th class="text-right">Total Pax</th>
                    <th class="text-right">Awak</th>
                    <th class="text-right">Bagasi</th>
                    <th class="text-right">Kargo</th>
                @elseif ($reportType === 'DAU4' || $reportType === 'DAU4A')
                    <th>Airport</th>
                    <th>IATA</th>
                    <th>City</th>
                    @if ($reportType === 'DAU4A')
                        <th>Operator</th>
                    @endif
                    <th class="text-right">Acft Arr</th>
                    <th class="text-right">Acft Dep</th>
                    <th class="text-right">Total Acft</th>
                    <th class="text-right">Pass Arr</th>
                    <th class="text-right">Pass Dep</th>
                    <th class="text-right">Total Pass</th>
                @elseif (in_array($reportType, ['DAU5', 'DAU5A', 'DAU5B', 'DAU5C']))
                    @if ($reportType === 'DAU5B')
                        <th>Terminal</th>
                    @endif
                    <th>Airline / Operator</th>
                    <th class="text-right">Acft Arr</th>
                    <th class="text-right">Acft Dep</th>
                    <th class="text-right">Total Acft</th>
                    <th class="text-right">Pass Arr</th>
                    <th class="text-right">Pass Dep</th>
                    <th class="text-right">Total Pass</th>
                    <th class="text-right">Awak</th>
                    <th class="text-right">Kargo</th>
                @elseif ($reportType === 'DAU6')
                    <th>Tipe Pesawat</th>
                    <th class="text-right">Acft Arr</th>
                    <th class="text-right">Acft Dep</th>
                    <th class="text-right">Total Acft</th>
                    <th class="text-right">Pass Arr</th>
                    <th class="text-right">Pass Dep</th>
                    <th class="text-right">Total Pass</th>
                    <th class="text-right">Awak</th>
                    <th class="text-right">Kargo</th>
                @elseif (in_array($reportType, ['DAU10', 'DAU10B']))
                    <th>Jam</th>
                    <th>Terminal</th>
                    <th class="text-right">Acft Arr</th>
                    <th class="text-right">Acft Dep</th>
                    <th class="text-right">Total Acft</th>
                    <th class="text-right">Pass Arr</th>
                    <th class="text-right">Pass Dep</th>
                    <th class="text-right">Total Pass</th>
                @elseif ($reportType === 'DAU10A')
                    <th>Periode Jam</th>
                    <th class="text-right">Total Pesawat</th>
                    <th class="text-right">Total Penumpang</th>
                    <th>Active Terminals</th>
                @elseif (in_array($reportType, ['DAU11', 'DAU12']))
                    <th>Tanggal</th>
                    <th class="text-right">Total Pesawat</th>
                    <th class="text-right">Total Penumpang</th>
                    <th class="text-right">Arrival Pax</th>
                    <th class="text-right">Departure Pax</th>
                @else
                    <th>Item</th>
                    <th class="text-right">Total Flights</th>
                    <th class="text-right">Total Pass</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach (array_slice($records, 0, 150) as $idx => $r)
                <tr>
                    <td class="text-center">{{ $r['no'] ?? ($idx + 1) }}</td>
                    @if ($reportType === 'DAU1')
                        <td>{{ $r['airport_route'] ?? '' }}</td>
                        <td><strong>{{ $r['flight_number'] ?? '' }}</strong></td>
                        <td>{{ $r['aircraft_type'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['seat_capacity'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['aircraft_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['passenger_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>
                    @elseif ($reportType === 'DAU2' || $reportType === 'DAU3')
                        <td><strong>{{ $r['category'] ?? '' }}</strong></td>
                        <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['aircraft_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['passenger_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['crew_total'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['baggage'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>
                    @elseif ($reportType === 'DAU4' || $reportType === 'DAU4A')
                        <td><strong>{{ $r['airport'] ?? '' }}</strong></td>
                        <td>{{ $r['city_code'] ?? '' }}</td>
                        <td>{{ $r['city'] ?? '' }}</td>
                        @if ($reportType === 'DAU4A')
                            <td>{{ $r['operator_name'] ?? '' }}</td>
                        @endif
                        <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['aircraft_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['passenger_total'] ?? 0) }}</strong></td>
                    @elseif (in_array($reportType, ['DAU5', 'DAU5A', 'DAU5B', 'DAU5C']))
                        @if ($reportType === 'DAU5B')
                            <td>{{ $r['terminal'] ?? '' }}</td>
                        @endif
                        <td><strong>{{ $r['airline'] ?? '' }}</strong></td>
                        <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['aircraft_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['passenger_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['crew_total'] ?? $r['crew'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>
                    @elseif ($reportType === 'DAU6')
                        <td><strong>{{ $r['aircraft_type'] ?? '' }}</strong></td>
                        <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['aircraft_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['passenger_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['crew_total'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['cargo'] ?? 0) }}</td>
                    @elseif (in_array($reportType, ['DAU10', 'DAU10B']))
                        <td><strong>{{ $r['hour'] ?? '' }}</strong></td>
                        <td>{{ $r['terminal'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($r['aircraft_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['aircraft_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['aircraft_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['passenger_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['passenger_departure'] ?? 0) }}</td>
                        <td class="text-right"><strong>{{ number_format($r['passenger_total'] ?? 0) }}</strong></td>
                    @elseif ($reportType === 'DAU10A')
                        <td><strong>{{ $r['period'] ?? '' }}</strong></td>
                        <td class="text-right"><strong>{{ number_format($r['total_flights'] ?? 0) }}</strong></td>
                        <td class="text-right"><strong>{{ number_format($r['total_passengers'] ?? 0) }}</strong></td>
                        <td>{{ implode(', ', array_keys($r['terminals'] ?? [])) }}</td>
                    @elseif (in_array($reportType, ['DAU11', 'DAU12']))
                        <td><strong>{{ $r['date'] ?? '' }}</strong></td>
                        <td class="text-right"><strong>{{ number_format($r['aircraft_total'] ?? 0) }}</strong></td>
                        <td class="text-right"><strong>{{ number_format($r['passenger_total'] ?? 0) }}</strong></td>
                        <td class="text-right">{{ number_format($r['passenger_arrival_tot'] ?? $r['passenger_int_arrival'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r['passenger_departure_tot'] ?? $r['passenger_int_departure'] ?? 0) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        SlotWaves Report System • Page 1 • Official OASYS Flight Data Verification
    </div>

</body>
</html>
