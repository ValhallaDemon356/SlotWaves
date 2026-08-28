<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SlotWaves — Daily Flight Movement Report</title>
    <style>
        /* ── Reset ──────────────────────────────────────────────────────── */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #1a1a2e;
            background: #fff;
            line-height: 1.35;
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

        /* ── Header ─────────────────────────────────────────────────────── */
        .report-header {
            margin-bottom: 5mm;
        }
        .header-brand {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }
        .brand-logo {
            font-size: 14pt;
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
            margin: 2px 0;
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

        /* ── Tables ──────────────────────────────────────────────────────── */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 4px;
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
            padding: 3.5px 6px;
            border: 1px solid #d1d5db;
            vertical-align: middle;
        }
        .row-even { background: #fff; }
        .row-odd  { background: #f8fafc; }
        tr { page-break-inside: avoid; }

        /* Column widths in landscape A4 */
        .col-no      { width: 28px; text-align: center; color: #6b7280; font-size: 7.5pt; }
        .col-flight  { width: 75px; font-weight: bold; }
        .col-airline { width: 140px; }
        .col-ac      { width: 75px; }
        .col-dir     { width: 45px; text-align: center; }
        .col-time    { width: 55px; text-align: center; }
        .col-origin  { width: 130px; }
        .col-dest    { width: 130px; }
        .col-type    { width: 45px; text-align: center; }
        .col-days    { width: 70px; text-align: center; }

        .mono { font-family: 'Courier New', monospace; font-weight: 700; }
        .fw-bold { font-weight: 700; }
        .text-center { text-align: center; }

        /* Badges */
        .badge-type {
            display: inline-block;
            font-size: 6.5pt;
            font-weight: 700;
            padding: 1px 4px;
            border-radius: 2px;
        }
        .badge-dom { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-int { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .badge-arr { background: #ffedd5; color: #9a3412; border: 1px solid #fdba74; font-weight: 800; }
        .badge-dep { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-weight: 800; }

        /* Totals */
        .totals-row {
            background: #1e3a5f;
            color: #fff;
            font-weight: 700;
        }
        .totals-label { text-align: right; font-size: 8pt; color: #fff; padding-right: 8px; font-weight: 700; }
        .totals-value { text-align: center; font-weight: 900; color: #fbbf24; font-size: 9.5pt; }

        .empty-row { text-align: center; color: #9ca3af; font-style: italic; padding: 12px; }
    </style>
</head>
<body>

@php
    $airportCode = $airport ? strtoupper($airport->iata_code) : 'BDO';
    $airportName = $airport ? strtoupper($airport->name) : 'HUSEIN SASTRANEGARA';
    $sortedFlights = $flights->sortBy('scheduled_time')->values();

    $arrCount = $sortedFlights->filter(fn($f) => str_contains($f->flight_type, 'arrival'))->count();
    $depCount = $sortedFlights->filter(fn($f) => str_contains($f->flight_type, 'departure'))->count();
    $totalCount = $sortedFlights->count();

    $formatDos = function(?string $days): string {
        if (!$days) return '—';
        if ($days === '1234567') return 'Daily';
        return implode(',', str_split($days));
    };
@endphp

{{-- ── Fixed footer on every page ──────────────────────────────────────── --}}
<div class="report-footer">
    <div class="footer-left">Generated by SlotWaves &mdash; Daily Flight Movement Report &mdash; {{ \Carbon\Carbon::now()->format('d F Y, H:i') }} WIB</div>
    <div class="footer-right">
        Total: {{ $totalCount }} Flights (Arrivals: {{ $arrCount }}, Departures: {{ $depCount }})
    </div>
</div>

{{-- ── Header ─────────────────────────────────────────────────────────── --}}
<div class="report-header">
    <div class="header-brand">
        <span class="brand-name">SLOTWAVES</span>
    </div>
    <div class="header-report-title">DAILY FLIGHT MOVEMENT REPORT</div>
    <div class="header-airport">
        {{ $airportName }} ({{ $airportCode }}) &mdash; CHRONOLOGICAL FLIGHT MOVEMENTS
    </div>
    <div class="header-meta">
        <div><strong>Schedule Source:</strong> {{ $upload->original_filename }}</div>
        <div><strong>Printed:</strong> {{ \Carbon\Carbon::now()->format('d F Y, H:i') }} WIB</div>
        <div><strong>Total Movements:</strong> {{ $totalCount }} (ARR: {{ $arrCount }} | DEP: {{ $depCount }})</div>
    </div>
    <div class="header-divider"></div>
</div>

{{-- ── Flight Movement Table ───────────────────────────────────────────── --}}
<table class="report-table">
    <thead>
        <tr>
            <th class="col-no">No.</th>
            <th class="col-flight">Flight No.</th>
            <th class="col-airline">Airline</th>
            <th class="col-ac">Type A/C</th>
            <th class="col-dir">Dir</th>
            <th class="col-time">STA/STD</th>
            <th class="col-origin">Origin</th>
            <th class="col-dest">Destination</th>
            <th class="col-type">Type</th>
            <th class="col-days">DOS</th>
        </tr>
    </thead>
    <tbody>
        @forelse($sortedFlights as $i => $flight)
            @php
                $isArr = str_contains($flight->flight_type, 'arrival');
                $airlineName = $flight->airline?->airline_name ?: ($flight->airline_code ?: substr($flight->flight_number, 0, 2));
                $originCode = $flight->origin ?: ($isArr ? '—' : $airportCode);
                $originName = $flight->originAirport?->name ?: ($originCode === $airportCode ? $airportName : $originCode);
                $destCode   = $flight->destination ?: ($isArr ? $airportCode : '—');
                $destName   = $flight->destinationAirport?->name ?: ($destCode === $airportCode ? $airportName : $destCode);
            @endphp
            <tr class="{{ $i % 2 === 0 ? 'row-even' : 'row-odd' }}">
                <td class="col-no">{{ $i + 1 }}</td>
                <td class="col-flight font-mono fw-bold" style="color: #1d4ed8;">{{ $flight->flight_number }}</td>
                <td class="col-airline">{{ $airlineName }}</td>
                <td class="col-ac font-mono">{{ $flight->aircraft_type ?? '—' }}</td>
                <td class="col-dir text-center">
                    <span class="badge-type {{ $isArr ? 'badge-arr' : 'badge-dep' }}">
                        {{ $isArr ? 'ARR' : 'DEP' }}
                    </span>
                </td>
                <td class="col-time mono">{{ substr($flight->scheduled_time, 0, 5) }}</td>
                <td class="col-origin">
                    <span class="font-mono fw-bold">{{ $originCode }}</span>
                    <span style="font-size: 7pt; color: #64748b;">({{ \Illuminate\Support\Str::limit($originName, 14) }})</span>
                </td>
                <td class="col-dest">
                    <span class="font-mono fw-bold">{{ $destCode }}</span>
                    <span style="font-size: 7pt; color: #64748b;">({{ \Illuminate\Support\Str::limit($destName, 14) }})</span>
                </td>
                <td class="col-type text-center">
                    <span class="badge-type {{ str_contains($flight->flight_type, 'domestic') ? 'badge-dom' : 'badge-int' }}">
                        {{ str_contains($flight->flight_type, 'domestic') ? 'DOM' : 'INT' }}
                    </span>
                </td>
                <td class="col-days font-mono">{{ $formatDos($flight->operating_days) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="empty-row">No flight movement records found for the selected query.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="totals-row">
            <td colspan="9" class="totals-label">TOTAL DAILY FLIGHT MOVEMENTS</td>
            <td class="totals-value">{{ $totalCount }}</td>
        </tr>
    </tfoot>
</table>

</body>
</html>
