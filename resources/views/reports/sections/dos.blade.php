{{-- reports/sections/dos.blade.php --}}
{{-- Receives: $dosGroups (Collection of ['days','label','flights']), $airport (Airport|null), $upload (Upload) --}}

@php
    $totalFlights = $dosGroups->sum(fn($g) => $g['flights']->count());
    $airportCode  = $airport ? strtoupper($airport->iata_code) : 'BDO';

    $formatDosGroupLabel = function(string $days): string {
        if ($days === '1234567') return 'DAILY (1234567)';
        return 'DOS ' . implode(',', str_split($days)) . " ({$days})";
    };
@endphp

<div class="section-dos">

    @include('reports.partials.header', ['reportTitle' => 'DAILY OPERATING SERVICE (DOS)'])

    @forelse($dosGroups as $groupIndex => $group)

        <div class="dos-group {{ $groupIndex > 0 ? 'dos-group-gap' : '' }}">

            {{-- Group Header --}}
            <div class="dos-group-header">
                <span class="dos-group-label">{{ $formatDosGroupLabel($group['days']) }}</span>
            </div>

            {{-- Arrivals in group --}}
            @php
                $groupArrivals   = $group['flights']->filter(fn($f) => str_contains($f->flight_type, 'arrival'))->sortBy('scheduled_time')->values();
                $groupDepartures = $group['flights']->filter(fn($f) => str_contains($f->flight_type, 'departure'))->sortBy('scheduled_time')->values();
            @endphp

            @if($groupArrivals->isNotEmpty())
            <div class="dos-sub-label">ARRIVALS</div>
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
                        <th class="col-days">DOS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groupArrivals as $i => $flight)
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
                        <td class="col-days font-mono">{{ $flight->operating_days === '1234567' ? 'Daily' : implode(',', str_split($flight->operating_days)) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            @if($groupDepartures->isNotEmpty())
            <div class="dos-sub-label">DEPARTURES</div>
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
                        <th class="col-days">DOS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groupDepartures as $i => $flight)
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
                        <td class="col-days font-mono">{{ $flight->operating_days === '1234567' ? 'Daily' : implode(',', str_split($flight->operating_days)) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

        </div>

        @if(!$loop->last)
            <div class="dos-divider"></div>
        @endif

    @empty
        <p class="empty-row">No operating day data available.</p>
    @endforelse

    <div class="section-gap"></div>
    <table class="report-table totals-table">
        <tbody>
            <tr class="grand-totals-row">
                <td class="grand-label">TOTAL FLIGHTS (ALL GROUPS)</td>
                <td class="grand-value">{{ $totalFlights }}</td>
            </tr>
        </tbody>
    </table>

</div>
