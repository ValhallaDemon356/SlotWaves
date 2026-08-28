{{-- reports/sections/time.blade.php --}}
{{-- Receives: $flights (Collection), $airport (Airport|null), $upload (Upload) --}}

@php
    $arrivals   = $flights->filter(fn($f) => str_contains($f->flight_type, 'arrival'));
    $departures = $flights->filter(fn($f) => str_contains($f->flight_type, 'departure'));

    $totalArrivals   = $arrivals->count();
    $totalDepartures = $departures->count();
    $totalFlights    = $flights->count();

    $airportCode = $airport ? strtoupper($airport->iata_code) : 'BDO';

    $formatDos = function(?string $days): string {
        if (!$days) return '—';
        if ($days === '1234567') return 'Daily';
        return implode(',', str_split($days));
    };
@endphp

<div class="section-time">

    @include('reports.partials.header', ['reportTitle' => 'TIME FLIGHT SCHEDULE'])

    {{-- ARRIVALS TABLE --}}
    <div class="section-heading">ARRIVALS &nbsp;<span class="badge">{{ $totalArrivals }} Flights</span></div>

    <table class="report-table">
        <thead>
            <tr>
                <th class="col-no">No.</th>
                <th class="col-flight">Flight No.</th>
                <th class="col-ac">Type A/C</th>
                <th class="col-time">STA</th>
                <th class="col-origin">Origin</th>
                <th class="col-dest">Dest</th>
                <th class="col-type">Type</th>
                <th class="col-days">TIME</th>
            </tr>
        </thead>
        <tbody>
            @forelse($arrivals->sortBy('scheduled_time')->values() as $i => $flight)
            <tr class="{{ $i % 2 === 0 ? 'row-even' : 'row-odd' }}">
                <td class="col-no">{{ $i + 1 }}</td>
                <td class="col-flight fw-bold">{{ $flight->flight_number }}</td>
                <td class="col-ac">{{ $flight->aircraft_type ?? '—' }}</td>
                <td class="col-time mono">{{ substr($flight->scheduled_time, 0, 5) }}</td>
                <td class="col-origin">{{ $flight->origin ?? '—' }}</td>
                <td class="col-dest fw-bold text-center">{{ $flight->destination ?? $airportCode }}</td>
                <td class="col-type text-center">
                    <span class="badge-type {{ str_contains($flight->flight_type, 'domestic') ? 'badge-dom' : 'badge-int' }}">
                        {{ str_contains($flight->flight_type, 'domestic') ? 'DOM' : 'INT' }}
                    </span>
                </td>
                <td class="col-days font-mono text-center">{{ substr($flight->scheduled_time, 0, 5) }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="empty-row">No arrival data.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td colspan="7" class="totals-label">TOTAL ARRIVALS</td>
                <td class="totals-value">{{ $totalArrivals }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-gap"></div>

    {{-- DEPARTURES TABLE --}}
    <div class="section-heading">DEPARTURES &nbsp;<span class="badge">{{ $totalDepartures }} Flights</span></div>

    <table class="report-table">
        <thead>
            <tr>
                <th class="col-no">No.</th>
                <th class="col-flight">Flight No.</th>
                <th class="col-ac">Type A/C</th>
                <th class="col-time">STD</th>
                <th class="col-origin">Origin</th>
                <th class="col-dest">Dest</th>
                <th class="col-type">Type</th>
                <th class="col-days">TIME</th>
            </tr>
        </thead>
        <tbody>
            @forelse($departures->sortBy('scheduled_time')->values() as $i => $flight)
            <tr class="{{ $i % 2 === 0 ? 'row-even' : 'row-odd' }}">
                <td class="col-no">{{ $i + 1 }}</td>
                <td class="col-flight fw-bold">{{ $flight->flight_number }}</td>
                <td class="col-ac">{{ $flight->aircraft_type ?? '—' }}</td>
                <td class="col-time mono">{{ substr($flight->scheduled_time, 0, 5) }}</td>
                <td class="col-origin fw-bold text-center">{{ $flight->origin ?? $airportCode }}</td>
                <td class="col-dest">{{ $flight->destination ?? '—' }}</td>
                <td class="col-type text-center">
                    <span class="badge-type {{ str_contains($flight->flight_type, 'domestic') ? 'badge-dom' : 'badge-int' }}">
                        {{ str_contains($flight->flight_type, 'domestic') ? 'DOM' : 'INT' }}
                    </span>
                </td>
                <td class="col-days font-mono text-center">{{ substr($flight->scheduled_time, 0, 5) }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="empty-row">No departure data.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td colspan="7" class="totals-label">TOTAL DEPARTURES</td>
                <td class="totals-value">{{ $totalDepartures }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-gap"></div>

    {{-- GRAND TOTAL --}}
    <table class="report-table totals-table">
        <tbody>
            <tr class="grand-totals-row">
                <td class="grand-label">GRAND TOTAL FLIGHTS</td>
                <td class="grand-value">{{ $totalFlights }}</td>
            </tr>
        </tbody>
    </table>

</div>
