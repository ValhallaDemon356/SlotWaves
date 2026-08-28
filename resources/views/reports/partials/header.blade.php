{{-- reports/partials/header.blade.php --}}
{{-- Receives: $airport (Airport|null), $upload (Upload), $reportTitle (string) --}}
@php
    $airportName = $airport ? strtoupper($airport->name) : 'HUSEIN SASTRANEGARA';
    $airportIata = $airport ? strtoupper($airport->iata_code) : 'BDO';
    $seasonLabel = ucfirst($upload->season ?? 'Summer');
@endphp

<div class="report-header">
    <div class="header-brand">
        <div class="brand-logo">✈</div>
        <div class="brand-name">SLOTWAVES &mdash; FLIGHT SCHEDULE</div>
    </div>
    <div class="header-report-title">{{ $reportTitle }}</div>
    <div class="header-airport">
        BANDAR UDARA {{ $airportName }} - {{ $airportIata }}
    </div>
    <div class="header-meta">
        <span class="header-file">Schedule: {{ $upload->original_filename }} &nbsp;|&nbsp; Season: {{ $seasonLabel }}</span>
        <span class="header-generated">Generated: {{ \Carbon\Carbon::now()->format('d F Y H:i') }} WIB</span>
    </div>
    <div class="header-divider"></div>
</div>
