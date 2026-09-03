@extends('layouts.app')

@section('title', ($conf['title'] ?? 'DAU Report') . ' — SlotWaves')
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between transition-colors duration-150')

@section('content')
<div x-data="dauDashboard()" class="min-h-screen flex flex-col justify-between">

    {{-- ══ COMPACT TOPBAR NAVIGATION ══════════════════════════════════════════ --}}
    <header class="w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-navy-900/90 backdrop-blur-md sticky top-0 z-30 px-4 sm:px-8 py-3 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="w-8 h-8 rounded-lg bg-aviation-600 flex items-center justify-center shadow-sm text-white hover:bg-aviation-700 transition">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-black tracking-tight text-slate-900 dark:text-white">SlotWaves</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                        {{ $conf['code'] ?? $reportType }}
                    </span>
                    <span class="text-[10px] font-mono text-slate-400">OASYS Verified</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium truncate max-w-md">
                    {{ $conf['title'] ?? 'Data Angkutan Udara' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <a href="{{ route('home') }}"
               class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-navy-800/80 hover:bg-slate-100 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Report</span>
            </a>

            <a href="{{ route('dau.export.pdf', $upload->id) }}"
               class="text-xs font-bold text-white px-3 py-1.5 rounded-lg bg-aviation-600 hover:bg-aviation-700 transition flex items-center gap-1.5 shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <span>Export PDF</span>
            </a>

            <a href="{{ route('dau.export.excel', $upload->id) }}"
               class="text-xs font-semibold text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-800 hover:bg-slate-50 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export CSV</span>
            </a>

            <button @click="toggleTheme()" type="button"
                    class="p-2 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-navy-800 border border-slate-200 dark:border-slate-700/60 hover:bg-slate-200 dark:hover:bg-navy-700 transition cursor-pointer"
                    aria-label="Toggle theme">
                <template x-if="theme === 'dark'">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </template>
                <template x-if="theme === 'light'">
                    <svg class="w-4 h-4 text-aviation-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                </template>
            </button>
        </div>
    </header>

    {{-- ══ REPORT CONTENT BODY ════════════════════════════════════════════════ --}}
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 space-y-6">

        {{-- ══ OFFICIAL OASYS REPORT HEADER BANNER ═══════════════════════════════ --}}
        <div class="glass-card p-5 sm:p-6 shadow-md border-l-4 border-l-aviation-600 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="text-[11px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400 flex items-center gap-2">
                    <span>DATA ANGKUTAN UDARA</span>
                    <span>•</span>
                    <span class="font-mono">{{ $conf['code'] ?? $reportType }}</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ $conf['title'] ?? 'Data Angkutan Udara' }}
                </h1>
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-aviation-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    <span>{{ $meta['airport_name'] ?? 'Tangerang Banten - Soekarno Hatta' }} ({{ $meta['airport_code'] ?? 'CGK' }})</span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] font-mono shrink-0 w-full md:w-auto">
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[9.5px] font-sans text-slate-400 font-bold uppercase">Tanggal</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ $meta['date_range'] ?? 'N/A' }}</div>
                </div>
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[9.5px] font-sans text-slate-400 font-bold uppercase">Penerbangan</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ $meta['flight_scope'] ?? 'DOM & INT' }}</div>
                </div>
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[9.5px] font-sans text-slate-400 font-bold uppercase">Terminal</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ $meta['terminal_scope'] ?? 'ALL' }}</div>
                </div>
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[9.5px] font-sans text-slate-400 font-bold uppercase">Source</div>
                    <div class="font-bold text-aviation-600 dark:text-aviation-400">OASYS</div>
                </div>
            </div>
        </div>

        {{-- ══ KPI SUMMARY CARDS ═════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            {{-- Movements --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-aviation-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Movements</div>
                <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-1">
                    {{ number_format($summary['total_movements'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">
                    <span>Arr: {{ number_format($summary['aircraft_arrival'] ?? 0) }}</span> • <span>Dep: {{ number_format($summary['aircraft_departure'] ?? 0) }}</span>
                </div>
            </div>

            {{-- Passengers --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-emerald-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Passengers</div>
                <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
                    {{ number_format($summary['passenger_total'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono truncate">
                    <span>Arr: {{ number_format($summary['passenger_arrival'] ?? 0) }}</span> • <span>Dep: {{ number_format($summary['passenger_departure'] ?? 0) }}</span>
                </div>
            </div>

            {{-- Transit & Transfer --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-blue-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Transit &amp; Transfer</div>
                <div class="text-xl sm:text-2xl font-black text-blue-600 dark:text-blue-400 mt-1">
                    {{ number_format(($summary['passenger_transit'] ?? 0) + ($summary['passenger_transfer'] ?? 0)) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">
                    <span>Trn: {{ number_format($summary['passenger_transit'] ?? 0) }}</span> • <span>Trf: {{ number_format($summary['passenger_transfer'] ?? 0) }}</span>
                </div>
            </div>

            {{-- Baggage --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-amber-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Baggage (Kg)</div>
                <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">
                    {{ number_format($summary['baggage_total'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">
                    Gross Weight
                </div>
            </div>

            {{-- Cargo --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-indigo-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cargo (Kg)</div>
                <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1">
                    {{ number_format($summary['cargo_total'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">
                    Freight Cargo
                </div>
            </div>

            {{-- POS --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-purple-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">POS / Mail (Kg)</div>
                <div class="text-xl sm:text-2xl font-black text-purple-600 dark:text-purple-400 mt-1">
                    {{ number_format($summary['pos_total'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">
                    Postal Mail
                </div>
            </div>
        </div>

        {{-- ══ INTERACTIVE DATA TABLE ═════════════════════════════════════════════ --}}
        <div class="glass-card p-5 sm:p-6 shadow-lg space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Report Dataset</span>
                        <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300">
                            {{ count($records) }} records
                        </span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Filter, search, or view detailed records extracted from {{ $conf['template_filename'] ?? 'template' }}.</p>
                </div>

                <div class="w-full sm:w-64">
                    <input type="text" x-model="searchQuery" placeholder="Search table..."
                           class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 focus:outline-hidden focus:ring-2 focus:ring-aviation-500"/>
                </div>
            </div>

            <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                <table class="w-full text-left text-xs font-sans">
                    <thead class="bg-slate-50 dark:bg-navy-900/90 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-3.5 py-3">#</th>
                            @if ($reportType === 'DAU1')
                                <th class="px-3 py-3">Route / Airport</th>
                                <th class="px-3 py-3">Flight No</th>
                                <th class="px-3 py-3">Schedule</th>
                                <th class="px-3 py-3">Aircraft</th>
                                <th class="px-3 py-3 text-right">Seats</th>
                                <th class="px-3 py-3 text-right">Acft Total</th>
                                <th class="px-3 py-3 text-right">Pass Total</th>
                                <th class="px-3 py-3 text-right">Baggage</th>
                                <th class="px-3 py-3 text-right">Cargo</th>
                            @elseif ($reportType === 'DAU2' || $reportType === 'DAU3')
                                <th class="px-3 py-3">Kategori</th>
                                <th class="px-3 py-3 text-right">Pesawat DTG</th>
                                <th class="px-3 py-3 text-right">Pesawat BRK</th>
                                <th class="px-3 py-3 text-right">Total Pesawat</th>
                                <th class="px-3 py-3 text-right">Pax DTG</th>
                                <th class="px-3 py-3 text-right">Pax BRK</th>
                                <th class="px-3 py-3 text-right">Total Pax</th>
                                <th class="px-3 py-3 text-right">Awak</th>
                                <th class="px-3 py-3 text-right">Bagasi</th>
                                <th class="px-3 py-3 text-right">Kargo</th>
                            @elseif ($reportType === 'DAU4' || $reportType === 'DAU4A')
                                <th class="px-3 py-3">Airport</th>
                                <th class="px-3 py-3">IATA</th>
                                <th class="px-3 py-3">City</th>
                                @if ($reportType === 'DAU4A')
                                    <th class="px-3 py-3">Operator</th>
                                @endif
                                <th class="px-3 py-3 text-right">Acft Arr</th>
                                <th class="px-3 py-3 text-right">Acft Dep</th>
                                <th class="px-3 py-3 text-right">Total Acft</th>
                                <th class="px-3 py-3 text-right">Pass Arr</th>
                                <th class="px-3 py-3 text-right">Pass Dep</th>
                                <th class="px-3 py-3 text-right">Total Pass</th>
                            @elseif (in_array($reportType, ['DAU5', 'DAU5A', 'DAU5B', 'DAU5C']))
                                @if ($reportType === 'DAU5B')
                                    <th class="px-3 py-3">Terminal</th>
                                @endif
                                <th class="px-3 py-3">Airline / Operator</th>
                                <th class="px-3 py-3 text-right">Acft Arr</th>
                                <th class="px-3 py-3 text-right">Acft Dep</th>
                                <th class="px-3 py-3 text-right">Total Acft</th>
                                <th class="px-3 py-3 text-right">Pass Arr</th>
                                <th class="px-3 py-3 text-right">Pass Dep</th>
                                <th class="px-3 py-3 text-right">Total Pass</th>
                                <th class="px-3 py-3 text-right">Awak</th>
                                <th class="px-3 py-3 text-right">Kargo</th>
                            @elseif ($reportType === 'DAU6')
                                <th class="px-3 py-3">Tipe Pesawat</th>
                                <th class="px-3 py-3 text-right">Acft Arr</th>
                                <th class="px-3 py-3 text-right">Acft Dep</th>
                                <th class="px-3 py-3 text-right">Total Acft</th>
                                <th class="px-3 py-3 text-right">Pass Arr</th>
                                <th class="px-3 py-3 text-right">Pass Dep</th>
                                <th class="px-3 py-3 text-right">Total Pass</th>
                                <th class="px-3 py-3 text-right">Awak</th>
                                <th class="px-3 py-3 text-right">Kargo</th>
                            @elseif (in_array($reportType, ['DAU10', 'DAU10B']))
                                <th class="px-3 py-3">Jam (Period)</th>
                                <th class="px-3 py-3">Terminal</th>
                                <th class="px-3 py-3 text-right">Acft Arr</th>
                                <th class="px-3 py-3 text-right">Acft Dep</th>
                                <th class="px-3 py-3 text-right">Total Acft</th>
                                <th class="px-3 py-3 text-right">Pass Arr</th>
                                <th class="px-3 py-3 text-right">Pass Dep</th>
                                <th class="px-3 py-3 text-right">Total Pass</th>
                                <th class="px-3 py-3 text-right">Awak</th>
                            @elseif ($reportType === 'DAU10A')
                                <th class="px-3 py-3">Periode Jam</th>
                                <th class="px-3 py-3 text-right">Total Acft</th>
                                <th class="px-3 py-3 text-right">Total Pass</th>
                                <th class="px-3 py-3">Active Terminals</th>
                            @elseif (in_array($reportType, ['DAU11', 'DAU12']))
                                <th class="px-3 py-3">Tanggal</th>
                                <th class="px-3 py-3 text-right">Total Pesawat</th>
                                <th class="px-3 py-3 text-right">Total Penumpang</th>
                                <th class="px-3 py-3 text-right">Arrival Pax</th>
                                <th class="px-3 py-3 text-right">Departure Pax</th>
                            @else
                                <th class="px-3 py-3">Primary Field</th>
                                <th class="px-3 py-3 text-right">Total Flights</th>
                                <th class="px-3 py-3 text-right">Total Pass</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono text-[11px]">
                        <template x-for="(row, idx) in paginatedRecords" :key="idx">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-navy-800/50 transition">
                                <td class="px-3.5 py-2.5 font-bold text-slate-400" x-text="row.no || (startIndex + idx + 1)"></td>

                                @if ($reportType === 'DAU1')
                                    <td class="px-3 py-2.5 font-sans font-bold text-slate-900 dark:text-white" x-text="row.airport_route"></td>
                                    <td class="px-3 py-2.5 font-bold text-aviation-600 dark:text-aviation-400" x-text="row.flight_number"></td>
                                    <td class="px-3 py-2.5 font-sans text-slate-500" x-text="row.schedule_type"></td>
                                    <td class="px-3 py-2.5 font-sans text-slate-700 dark:text-slate-300" x-text="row.aircraft_type"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.seat_capacity"></td>
                                    <td class="px-3 py-2.5 text-right font-bold" x-text="row.aircraft_total"></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600" x-text="row.passenger_total"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.baggage"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.cargo"></td>
                                @elseif ($reportType === 'DAU2' || $reportType === 'DAU3')
                                    <td class="px-3 py-2.5 font-sans font-bold text-slate-900 dark:text-white" x-text="row.category"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_arrival"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold" x-text="row.aircraft_total"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_arrival"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600" x-text="row.passenger_total"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.crew_total"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.baggage"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.cargo"></td>
                                @elseif ($reportType === 'DAU4' || $reportType === 'DAU4A')
                                    <td class="px-3 py-2.5 font-sans font-bold text-slate-900 dark:text-white" x-text="row.airport"></td>
                                    <td class="px-3 py-2.5 font-bold text-aviation-600 dark:text-aviation-400" x-text="row.city_code"></td>
                                    <td class="px-3 py-2.5 font-sans text-slate-700 dark:text-slate-300" x-text="row.city"></td>
                                    @if ($reportType === 'DAU4A')
                                        <td class="px-3 py-2.5 font-sans" x-text="row.operator_name"></td>
                                    @endif
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_arrival"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold" x-text="row.aircraft_total"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_arrival"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600" x-text="row.passenger_total"></td>
                                @elseif (in_array($reportType, ['DAU5', 'DAU5A', 'DAU5B', 'DAU5C']))
                                    @if ($reportType === 'DAU5B')
                                        <td class="px-3 py-2.5 font-sans font-bold" x-text="row.terminal"></td>
                                    @endif
                                    <td class="px-3 py-2.5 font-sans font-bold text-slate-900 dark:text-white" x-text="row.airline"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_arrival"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold" x-text="row.aircraft_total"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_arrival"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600" x-text="row.passenger_total"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.crew_total || row.crew"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.cargo"></td>
                                @elseif ($reportType === 'DAU6')
                                    <td class="px-3 py-2.5 font-sans font-bold text-slate-900 dark:text-white" x-text="row.aircraft_type"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_arrival"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold" x-text="row.aircraft_total"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_arrival"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600" x-text="row.passenger_total"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.crew_total"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.cargo"></td>
                                @elseif (in_array($reportType, ['DAU10', 'DAU10B']))
                                    <td class="px-3 py-2.5 font-mono font-bold text-aviation-600" x-text="row.hour"></td>
                                    <td class="px-3 py-2.5 font-sans font-bold" x-text="row.terminal"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_arrival"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.aircraft_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold" x-text="row.aircraft_total"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_arrival"></td>
                                    <td class="px-3 py-2.5 text-right text-emerald-600" x-text="row.passenger_departure"></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600" x-text="row.passenger_total"></td>
                                    <td class="px-3 py-2.5 text-right" x-text="row.crew_total"></td>
                                @elseif ($reportType === 'DAU10A')
                                    <td class="px-3 py-2.5 font-mono font-bold text-aviation-600" x-text="row.period"></td>
                                    <td class="px-3 py-2.5 text-right font-bold" x-text="row.total_flights"></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600" x-text="row.total_passengers"></td>
                                    <td class="px-3 py-2.5 font-sans text-slate-500">
                                        <span x-text="Object.keys(row.terminals || {}).join(', ')"></span>
                                    </td>
                                @elseif (in_array($reportType, ['DAU11', 'DAU12']))
                                    <td class="px-3 py-2.5 font-bold" x-text="row.date"></td>
                                    <td class="px-3 py-2.5 text-right font-bold" x-text="row.aircraft_total"></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600" x-text="row.passenger_total"></td>
                                    <td class="px-3 py-2.5 text-right text-slate-500" x-text="row.passenger_arrival_tot || row.passenger_int_arrival || 0"></td>
                                    <td class="px-3 py-2.5 text-right text-slate-500" x-text="row.passenger_departure_tot || row.passenger_int_departure || 0"></td>
                                @else
                                    <td class="px-3 py-2.5 font-sans font-bold" x-text="row.city || row.airline || row.airport || 'Record'"></td>
                                    <td class="px-3 py-2.5 text-right font-bold" x-text="row.aircraft_total || row.total_flights || 0"></td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600" x-text="row.passenger_total || row.total_passengers || 0"></td>
                                @endif
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination controls --}}
            <div class="flex items-center justify-between pt-3 text-xs text-slate-500 font-mono">
                <div>
                    Showing <span class="font-bold text-slate-800 dark:text-slate-200" x-text="startIndex + 1"></span> to 
                    <span class="font-bold text-slate-800 dark:text-slate-200" x-text="Math.min(endIndex, filteredRecords.length)"></span> of 
                    <span class="font-bold text-slate-800 dark:text-slate-200" x-text="filteredRecords.length"></span> records
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" @click="prevPage()" :disabled="currentPage === 1"
                            class="px-2.5 py-1 rounded border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer">
                        &larr; Prev
                    </button>
                    <span class="px-2 font-bold text-slate-700 dark:text-slate-300" x-text="'Page ' + currentPage + ' / ' + totalPages"></span>
                    <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages"
                            class="px-2.5 py-1 rounded border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer">
                        Next &rarr;
                    </button>
                </div>
            </div>
        </div>

    </main>

    {{-- ══ FOOTER ═══════════════════════════════════════════════════════════════ --}}
    <footer class="w-full border-t border-slate-200/80 dark:border-slate-800/80 py-3 px-4 text-center text-xs text-slate-400 font-mono">
        SlotWaves Report System • OASYS Source Verification Active • {{ $meta['airport_name'] ?? 'CGK' }}
    </footer>

</div>
@endsection

@push('scripts')
<script>
function dauDashboard() {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
        records: @json($records),
        searchQuery: '',
        currentPage: 1,
        pageSize: 50,

        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('slotwaves-theme', this.theme);
            if (this.theme === 'light') {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            } else {
                document.documentElement.classList.remove('light');
                document.documentElement.classList.add('dark');
            }
        },

        get filteredRecords() {
            if (!this.searchQuery) return this.records;
            const q = this.searchQuery.toLowerCase();
            return this.records.filter(r => JSON.stringify(r).toLowerCase().includes(q));
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredRecords.length / this.pageSize));
        },

        get startIndex() {
            return (this.currentPage - 1) * this.pageSize;
        },

        get endIndex() {
            return this.startIndex + this.pageSize;
        },

        get paginatedRecords() {
            return this.filteredRecords.slice(this.startIndex, this.endIndex);
        },

        nextPage() {
            if (this.currentPage < this.totalPages) this.currentPage++;
        },

        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        }
    };
}
</script>
@endpush
