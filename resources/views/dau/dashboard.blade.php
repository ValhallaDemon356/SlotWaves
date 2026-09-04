@extends('layouts.app')

@section('title', ($conf['title'] ?? 'DAU Report') . ' — SlotWaves')
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between transition-colors duration-150')

@section('content')
<div x-data="dauEnhancedDashboard()" x-init="initDashboard()" class="min-h-screen flex flex-col justify-between">

    {{-- ══ TOPBAR NAVIGATION & EXPORT BAR ══════════════════════════════════════ --}}
    <header class="w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-navy-900/90 backdrop-blur-md sticky top-0 z-40 px-4 sm:px-8 py-3 flex items-center justify-between shadow-xs">
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
                    <span class="text-[10px] font-mono text-slate-400">OASYS Analytics Active</span>
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
                <span class="hidden sm:inline">New Report</span>
            </a>

            {{-- Filtered PDF Export Button with Download Feedback --}}
            <button type="button" @click="downloadPdfReport()" :disabled="isExportingPdf"
                    class="text-xs font-bold text-white px-3.5 py-1.5 rounded-lg bg-aviation-600 hover:bg-aviation-700 disabled:opacity-75 transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                <template x-if="!isExportingPdf">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                </template>
                <template x-if="isExportingPdf">
                    <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                </template>
                <span x-text="pdfButtonText">Export PDF</span>
            </button>

            {{-- Filtered CSV Export --}}
            <a :href="exportCsvUrl"
               class="text-xs font-semibold text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-800 hover:bg-slate-50 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="hidden sm:inline">Export CSV</span>
            </a>

            {{-- Theme Toggle --}}
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

    {{-- ══ MAIN REPORT DASHBOARD BODY ══════════════════════════════════════════ --}}
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 space-y-6">

        {{-- ══ 1. OFFICIAL OASYS REPORT HEADER BANNER ═══════════════════════════ --}}
        <div class="glass-card p-5 sm:p-6 shadow-md border-l-4 border-l-aviation-600 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="text-[11px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400 flex items-center gap-2">
                    <span>DATA ANGKUTAN UDARA</span>
                    <span>•</span>
                    <span class="font-mono">{{ $conf['code'] ?? $reportType }}</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    @if ($reportType === 'DAU10')
                        JAM PUNCAK PESAWAT/PENUMPANG (DAU-10)
                    @elseif ($reportType === 'DAU10A')
                        JAM PUNCAK MENURUT TERMINAL (DAU-10A)
                    @elseif ($reportType === 'DAU10B')
                        JAM PUNCAK PESAWAT/PENUMPANG (BLOCK ON/OFF) (DAU-10B)
                    @else
                        {{ $conf['title'] ?? 'Data Angkutan Udara' }}
                    @endif
                </h1>
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-aviation-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    <span>{{ $meta['airport_name'] ?? 'Tangerang Banten - Soekarno Hatta' }} ({{ $meta['airport_code'] ?? 'CGK' }})</span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] font-mono shrink-0 w-full md:w-auto">
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[9.5px] font-sans text-slate-400 font-bold uppercase">Tanggal</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate" x-text="activeDateRange"></div>
                </div>
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[9.5px] font-sans text-slate-400 font-bold uppercase">Penerbangan</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate" x-text="activeFlightScope"></div>
                </div>
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[9.5px] font-sans text-slate-400 font-bold uppercase">Terminal</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate" x-text="activeTerminalScope"></div>
                </div>
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[9.5px] font-sans text-slate-400 font-bold uppercase">Source</div>
                    <div class="font-bold text-aviation-600 dark:text-aviation-400">OASYS</div>
                </div>
            </div>
        </div>

        {{-- ══ 2. ADVANCED FILTER BAR (POSITIONED ABOVE CHARTS) ════════════════ --}}
        <div class="glass-card p-5 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-aviation-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white">Filter &amp; Analytics Controls</span>
                </div>

                {{-- Quick Filter Pills --}}
                <div class="flex flex-wrap items-center gap-1.5 text-xs font-semibold">
                    <span class="text-[10px] uppercase font-bold text-slate-400 mr-1">Scope:</span>
                    <button type="button" @click="setFlightType('ALL')"
                            :class="filterFlightType === 'ALL' ? 'bg-aviation-600 text-white shadow-2xs' : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
                            class="px-2.5 py-1 rounded-md text-[11px] font-bold transition cursor-pointer">ALL</button>
                    <button type="button" @click="setFlightType('DOM')"
                            :class="filterFlightType === 'DOM' ? 'bg-aviation-600 text-white shadow-2xs' : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
                            class="px-2.5 py-1 rounded-md text-[11px] font-bold transition cursor-pointer">DOM</button>
                    <button type="button" @click="setFlightType('INT')"
                            :class="filterFlightType === 'INT' ? 'bg-aviation-600 text-white shadow-2xs' : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
                            class="px-2.5 py-1 rounded-md text-[11px] font-bold transition cursor-pointer">INT</button>

                    <span class="text-slate-300 dark:text-slate-700 mx-1">|</span>

                    <button type="button" @click="setTerminal('ALL')"
                            :class="filterTerminal === 'ALL' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-2xs' : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
                            class="px-2 py-1 rounded-md text-[11px] font-bold transition cursor-pointer">ALL TERMINAL</button>

                    @foreach ($terminals as $t)
                        <button type="button" @click="setTerminal('{{ $t }}')"
                                :class="filterTerminal === '{{ $t }}' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-2xs' : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200'"
                                class="px-2 py-1 rounded-md text-[11px] font-bold transition cursor-pointer">{{ $t }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Filter Controls Form --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 text-xs">
                {{-- Date Range --}}
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-slate-400">Date Range</label>
                    <div class="flex items-center gap-1">
                        <input type="date" x-model="filterStartDate" @change="applyFilters()"
                               class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-mono">
                    </div>
                </div>

                {{-- Flight Type --}}
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-slate-400">Flight Type</label>
                    <select x-model="filterFlightType" @change="applyFilters()"
                            class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-medium">
                        <option value="ALL">ALL (Semua)</option>
                        <option value="DOM">DOMESTIC</option>
                        <option value="INT">INTERNATIONAL</option>
                    </select>
                </div>

                {{-- Terminal --}}
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-slate-400">Terminal</label>
                    <select x-model="filterTerminal" @change="applyFilters()"
                            class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-medium">
                        <option value="ALL">ALL TERMINAL</option>
                        @foreach ($terminals as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Hour --}}
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-slate-400">Hour / Period</label>
                    <select x-model="filterHour" @change="applyFilters()"
                            class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-mono">
                        <option value="ALL">ALL HOURS (00 - 24)</option>
                        @foreach ($hours as $h)
                            <option value="{{ $h }}">{{ $h }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Metric Selector --}}
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-slate-400">Metric</label>
                    <select x-model="selectedMetric" @change="applyFilters()"
                            class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold text-aviation-600">
                        <option value="aircraft">Aircraft (Pesawat)</option>
                        <option value="passenger">Passenger (Penumpang)</option>
                        <option value="crew">Crew (Awak Pesawat)</option>
                        @if ($reportType !== 'DAU10A')
                            <option value="baggage">Baggage (Bagasi Kg)</option>
                            <option value="cargo">Cargo (Kargo Kg)</option>
                            <option value="pos">POS / Mail (Kg)</option>
                        @endif
                    </select>
                </div>

                {{-- DAU10B Operation Filter / Apply Buttons --}}
                <div class="space-y-1">
                    @if ($reportType === 'DAU10B')
                        <label class="text-[10px] font-bold uppercase text-slate-400">Operation</label>
                        <select x-model="filterOperation" @change="applyFilters()"
                                class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold text-purple-600">
                            <option value="ALL">ALL OPERATIONS</option>
                            <option value="BLOCK_ON">BLOCK ON</option>
                            <option value="BLOCK_OFF">BLOCK OFF</option>
                        </select>
                    @else
                        <label class="text-[10px] font-bold uppercase text-slate-400">Actions</label>
                        <div class="flex items-center gap-1.5 pt-0.5">
                            <button type="button" @click="applyFilters()"
                                    class="w-full px-3 py-1.5 text-xs font-bold text-white rounded-lg bg-aviation-600 hover:bg-aviation-700 transition cursor-pointer">
                                Apply
                            </button>
                            <button type="button" @click="resetFilters()"
                                    class="px-2.5 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer">
                                Reset
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            @if ($reportType === 'DAU10B')
                <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="applyFilters()"
                            class="px-4 py-1.5 text-xs font-bold text-white rounded-lg bg-aviation-600 hover:bg-aviation-700 transition cursor-pointer shadow-2xs">
                        Apply Filter
                    </button>
                    <button type="button" @click="resetFilters()"
                            class="px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer">
                        Reset
                    </button>
                </div>
            @endif
        </div>

        {{-- ══ 3. DYNAMIC KPI SUMMARY CARDS ════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            {{-- Peak Aircraft / Peak Block On --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-aviation-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    @if ($reportType === 'DAU10B')
                        Peak Block On (DTG)
                    @else
                        Peak Aircraft
                    @endif
                </div>
                <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-1" x-text="formatNumber(reportType === 'DAU10B' ? peaks.peak_block_on : peaks.peak_aircraft)">
                    {{ number_format($reportType === 'DAU10B' ? ($peaks['peak_block_on'] ?? 0) : ($peaks['peak_aircraft'] ?? 0)) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono truncate" x-text="reportType === 'DAU10B' ? peaks.peak_block_on_hour : peaks.peak_aircraft_hour">
                    {{ $reportType === 'DAU10B' ? ($peaks['peak_block_on_hour'] ?? '—') : ($peaks['peak_aircraft_hour'] ?? '—') }}
                </div>
            </div>

            {{-- Peak Passenger / Peak Block Off --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-emerald-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    @if ($reportType === 'DAU10B')
                        Peak Block Off (BRK)
                    @else
                        Peak Passenger
                    @endif
                </div>
                <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1" x-text="formatNumber(reportType === 'DAU10B' ? peaks.peak_block_off : peaks.peak_passenger)">
                    {{ number_format($reportType === 'DAU10B' ? ($peaks['peak_block_off'] ?? 0) : ($peaks['peak_passenger'] ?? 0)) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono truncate" x-text="reportType === 'DAU10B' ? peaks.peak_block_off_hour : peaks.peak_passenger_hour">
                    {{ $reportType === 'DAU10B' ? ($peaks['peak_block_off_hour'] ?? '—') : ($peaks['peak_passenger_hour'] ?? '—') }}
                </div>
            </div>

            {{-- Peak Hour --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-blue-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Peak Hour</div>
                <div class="text-base sm:text-lg font-black text-blue-600 dark:text-blue-400 mt-1 font-mono truncate" x-text="peaks.peak_hour">
                    {{ $peaks['peak_hour'] ?? '—' }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">
                    Highest Demand
                </div>
            </div>

            {{-- Peak Terminal / Total Movements --}}
            @if ($reportType === 'DAU10A' || $reportType === 'DAU10B')
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-purple-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Peak Terminal</div>
                    <div class="text-xl sm:text-2xl font-black text-purple-600 dark:text-purple-400 mt-1 truncate" x-text="'T' + peaks.peak_terminal">
                        T{{ $peaks['peak_terminal'] ?? '—' }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono truncate" x-text="formatNumber(peaks.peak_terminal_val) + ' movements'">
                        {{ number_format($peaks['peak_terminal_val'] ?? 0) }} movements
                    </div>
                </div>
            @endif

            {{-- Total Movements --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-amber-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Movements</div>
                <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 mt-1" x-text="formatNumber(activeSummary.total_movements)">
                    {{ number_format($summary['total_movements'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono truncate">
                    <span>Arr: <span x-text="formatNumber(activeSummary.aircraft_arrival)">{{ number_format($summary['aircraft_arrival'] ?? 0) }}</span></span> • 
                    <span>Dep: <span x-text="formatNumber(activeSummary.aircraft_departure)">{{ number_format($summary['aircraft_departure'] ?? 0) }}</span></span>
                </div>
            </div>

            {{-- Total Passengers --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-indigo-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Passengers</div>
                <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1" x-text="formatNumber(activeSummary.passenger_total)">
                    {{ number_format($summary['passenger_total'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono truncate">
                    <span>Arr: <span x-text="formatNumber(activeSummary.passenger_arrival)">{{ number_format($summary['passenger_arrival'] ?? 0) }}</span></span> • 
                    <span>Dep: <span x-text="formatNumber(activeSummary.passenger_departure)">{{ number_format($summary['passenger_departure'] ?? 0) }}</span></span>
                </div>
            </div>

            {{-- Total Crew --}}
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-slate-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Crew</div>
                <div class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-200 mt-1" x-text="formatNumber(activeSummary.crew_total)">
                    {{ number_format($summary['crew_total'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">
                    Flight Crew
                </div>
            </div>

            @if ($reportType !== 'DAU10A')
                {{-- Baggage --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-rose-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Baggage (Kg)</div>
                    <div class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400 mt-1" x-text="formatNumber(activeSummary.baggage_total)">
                        {{ number_format($summary['baggage_total'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        Gross Luggage
                    </div>
                </div>

                {{-- Cargo --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-teal-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cargo (Kg)</div>
                    <div class="text-xl sm:text-2xl font-black text-teal-600 dark:text-teal-400 mt-1" x-text="formatNumber(activeSummary.cargo_total)">
                        {{ number_format($summary['cargo_total'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        Freight Cargo
                    </div>
                </div>

                {{-- POS --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-cyan-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">POS / Mail (Kg)</div>
                    <div class="text-xl sm:text-2xl font-black text-cyan-600 dark:text-cyan-400 mt-1" x-text="formatNumber(activeSummary.pos_total)">
                        {{ number_format($summary['pos_total'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        Postal Mail
                    </div>
                </div>
            @endif
        </div>

        {{-- ══ 4. ANALYTICAL CHARTS & DIAGRAMS ═════════════════════════════════ --}}

        {{-- ──────────────── DAU-10 CHARTS ──────────────── --}}
        @if ($reportType === 'DAU10')
            <div class="space-y-6">
                {{-- Chart 1: Hourly Aircraft Movement --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Hourly Distribution</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">HOURLY AIRCRAFT MOVEMENT</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> Arrival</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> Departure</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-slate-900 dark:bg-white"></span> Total</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                Peak: <span x-text="peaks.peak_aircraft_hour + ' (' + formatNumber(peaks.peak_aircraft) + ' Acft)'"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Grouped Bar Chart Canvas --}}
                    <div class="h-64 sm:h-72 w-full flex items-end gap-1.5 sm:gap-2 pt-6 pb-2 px-1 overflow-x-auto">
                        <template x-for="(item, idx) in activeHourlyDistribution" :key="idx">
                            <div @click="setHourFilter(item.hour)"
                                 class="flex-1 min-w-[32px] sm:min-w-[40px] flex flex-col items-center h-full justify-end group relative cursor-pointer"
                                 :class="item.hour === peaks.peak_aircraft_hour ? 'bg-amber-50/60 dark:bg-amber-950/40 rounded-lg ring-1 ring-amber-400' : ''">
                                
                                {{-- Tooltip --}}
                                <div class="opacity-0 group-hover:opacity-100 transition pointer-events-none absolute bottom-full mb-2 z-30 bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-[10px] font-mono rounded-lg px-2.5 py-1.5 shadow-xl whitespace-nowrap">
                                    <div class="font-bold" x-text="'Jam: ' + item.hour"></div>
                                    <div x-text="'Arr: ' + formatNumber(item.aircraft_arrival)"></div>
                                    <div x-text="'Dep: ' + formatNumber(item.aircraft_departure)"></div>
                                    <div class="font-bold text-amber-400 dark:text-amber-600" x-text="'Total: ' + formatNumber(item.aircraft_total) + ' Acft'"></div>
                                    <div class="text-[9px] text-slate-400">Click to filter table</div>
                                </div>

                                {{-- Bars Group --}}
                                <div class="w-full flex items-end justify-center gap-0.5 sm:gap-1 px-1" style="height: 100%;">
                                    {{-- Arr Bar --}}
                                    <div class="w-1/3 bg-amber-500 hover:bg-amber-400 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(item.aircraft_arrival, maxAircraftPerHour) + '%'"></div>
                                    {{-- Dep Bar --}}
                                    <div class="w-1/3 bg-blue-600 hover:bg-blue-500 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(item.aircraft_departure, maxAircraftPerHour) + '%'"></div>
                                    {{-- Tot Indicator --}}
                                    <div class="w-1/3 bg-slate-800 dark:bg-slate-200 transition-all rounded-t-sm font-bold"
                                         :style="'height: ' + calculateBarHeight(item.aircraft_total, maxAircraftPerHour) + '%'"></div>
                                </div>

                                {{-- Hour Label --}}
                                <div class="text-[9px] font-mono text-slate-500 mt-2 truncate w-full text-center"
                                     x-text="item.hour.split(' - ')[0] || item.hour"></div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Chart 2: Hourly Passenger Movement --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Passenger Flow</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">HOURLY PASSENGER MOVEMENT</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-500"></span> Arrival</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-teal-600"></span> Departure</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-slate-900 dark:bg-white"></span> Total</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                Peak: <span x-text="peaks.peak_passenger_hour + ' (' + formatNumber(peaks.peak_passenger) + ' Pax)'"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Grouped Bar Chart Canvas --}}
                    <div class="h-64 sm:h-72 w-full flex items-end gap-1.5 sm:gap-2 pt-6 pb-2 px-1 overflow-x-auto">
                        <template x-for="(item, idx) in activeHourlyDistribution" :key="idx">
                            <div @click="setHourFilter(item.hour)"
                                 class="flex-1 min-w-[32px] sm:min-w-[40px] flex flex-col items-center h-full justify-end group relative cursor-pointer"
                                 :class="item.hour === peaks.peak_passenger_hour ? 'bg-emerald-50/60 dark:bg-emerald-950/40 rounded-lg ring-1 ring-emerald-400' : ''">
                                
                                {{-- Tooltip --}}
                                <div class="opacity-0 group-hover:opacity-100 transition pointer-events-none absolute bottom-full mb-2 z-30 bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-[10px] font-mono rounded-lg px-2.5 py-1.5 shadow-xl whitespace-nowrap">
                                    <div class="font-bold" x-text="'Jam: ' + item.hour"></div>
                                    <div x-text="'Arr: ' + formatNumber(item.passenger_arrival) + ' Pax'"></div>
                                    <div x-text="'Dep: ' + formatNumber(item.passenger_departure) + ' Pax'"></div>
                                    <div class="font-bold text-emerald-400 dark:text-emerald-600" x-text="'Total: ' + formatNumber(item.passenger_total) + ' Pax'"></div>
                                    <div class="text-[9px] text-slate-400">Click to filter table</div>
                                </div>

                                {{-- Bars Group --}}
                                <div class="w-full flex items-end justify-center gap-0.5 sm:gap-1 px-1" style="height: 100%;">
                                    <div class="w-1/3 bg-emerald-500 hover:bg-emerald-400 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(item.passenger_arrival, maxPassengerPerHour) + '%'"></div>
                                    <div class="w-1/3 bg-teal-600 hover:bg-teal-500 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(item.passenger_departure, maxPassengerPerHour) + '%'"></div>
                                    <div class="w-1/3 bg-slate-800 dark:bg-slate-200 transition-all rounded-t-sm font-bold"
                                         :style="'height: ' + calculateBarHeight(item.passenger_total, maxPassengerPerHour) + '%'"></div>
                                </div>

                                {{-- Hour Label --}}
                                <div class="text-[9px] font-mono text-slate-500 mt-2 truncate w-full text-center"
                                     x-text="item.hour.split(' - ')[0] || item.hour"></div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Chart 3: Hourly Comparison (Aircraft vs Passenger toggle) --}}
                <div class="glass-card p-5 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600">Hourly Comparative Analysis</div>
                            <h2 class="text-base font-black text-slate-900 dark:text-white">AIRCRAFT VS PASSENGER BY HOUR</h2>
                        </div>
                        <div class="flex items-center gap-1.5 p-1 rounded-lg bg-slate-100 dark:bg-navy-800">
                            <button type="button" @click="comparisonMetric = 'aircraft'"
                                    :class="comparisonMetric === 'aircraft' ? 'bg-white dark:bg-navy-900 text-aviation-600 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400'"
                                    class="px-3 py-1 rounded text-xs transition cursor-pointer">Aircraft</button>
                            <button type="button" @click="comparisonMetric = 'passenger'"
                                    :class="comparisonMetric === 'passenger' ? 'bg-white dark:bg-navy-900 text-emerald-600 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400'"
                                    class="px-3 py-1 rounded text-xs transition cursor-pointer">Passenger</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
                        <template x-for="(item, idx) in activeHourlyDistribution" :key="idx">
                            <div class="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-navy-900/50">
                                <div class="text-[9px] font-mono text-slate-400 font-bold" x-text="item.hour.split(' - ')[0]"></div>
                                <div class="text-sm font-black mt-0.5"
                                     :class="comparisonMetric === 'aircraft' ? 'text-aviation-600 dark:text-aviation-400' : 'text-emerald-600 dark:text-emerald-400'"
                                     x-text="comparisonMetric === 'aircraft' ? formatNumber(item.aircraft_total) : formatNumber(item.passenger_total)"></div>
                                <div class="text-[9px] text-slate-500 font-mono mt-0.5 truncate">
                                    <span x-text="'Arr: ' + formatNumber(comparisonMetric === 'aircraft' ? item.aircraft_arrival : item.passenger_arrival)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- ──────────────── DAU-10A CHARTS ──────────────── --}}
        @if ($reportType === 'DAU10A')
            <div class="space-y-6">
                {{-- 1. Time x Terminal Heatmap --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Terminal Operational Intensity</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">TIME × TERMINAL HEATMAP</h2>
                        </div>

                        {{-- Metric Selector for Heatmap --}}
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1.5 text-xs">
                                <span class="text-[10px] uppercase font-bold text-slate-400">Intensity:</span>
                                <div class="flex items-center gap-1 text-[10px] font-mono">
                                    <span>Low</span>
                                    <span class="w-3 h-3 rounded bg-blue-100 dark:bg-navy-800 border border-slate-200"></span>
                                    <span class="w-3 h-3 rounded bg-blue-300 dark:bg-blue-900"></span>
                                    <span class="w-3 h-3 rounded bg-blue-500 dark:bg-blue-600"></span>
                                    <span class="w-3 h-3 rounded bg-blue-700 dark:bg-blue-400"></span>
                                    <span>High</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Heatmap Matrix Table --}}
                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                        <table class="w-full text-xs font-mono text-center border-collapse">
                            <thead class="bg-slate-100 dark:bg-navy-900 text-[10px] font-bold text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="px-3 py-2 text-left bg-slate-200/70 dark:bg-navy-950 font-sans sticky left-0 z-20">Terminal</th>
                                    <template x-for="(h, hIdx) in hours" :key="hIdx">
                                        <th class="px-2 py-2 min-w-[48px] truncate" x-text="h.split(' - ')[0] || h"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                <template x-for="term in terminals" :key="term">
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-navy-900/30 transition">
                                        <td class="px-3 py-2 text-left font-bold text-slate-900 dark:text-white bg-slate-50 dark:bg-navy-900/90 sticky left-0 z-10 font-sans"
                                            x-text="'Terminal ' + term"></td>
                                        <template x-for="h in hours" :key="h">
                                            <td @click="filterByTerminalAndHour(term, h)"
                                                class="px-1 py-2 font-bold cursor-pointer transition relative group"
                                                :style="'background-color: ' + getHeatmapColor(term, h)">
                                                
                                                <span x-text="getHeatmapValue(term, h)"></span>

                                                {{-- Tooltip on Hover --}}
                                                <div class="opacity-0 group-hover:opacity-100 transition pointer-events-none absolute bottom-full mb-1 left-1/2 -translate-x-1/2 z-30 bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-[10px] rounded px-2 py-1 shadow-lg whitespace-nowrap">
                                                    <span x-text="'T' + term + ' @ ' + h + ': ' + getHeatmapValue(term, h)"></span>
                                                </div>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 2. Terminal Traffic Comparison Bar Chart --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600">Cross-Terminal Ranking</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">TERMINAL TRAFFIC COMPARISON</h2>
                        </div>
                        <div class="text-xs font-mono font-bold text-slate-500">
                            Metric: <span class="capitalize text-aviation-600" x-text="selectedMetric"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3">
                        <template x-for="tItem in activeTerminalComparison" :key="tItem.terminal">
                            <div @click="setTerminal(tItem.terminal)"
                                 class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-navy-900 flex flex-col justify-between hover:border-aviation-500 transition cursor-pointer group shadow-2xs">
                                <div>
                                    <div class="text-xs font-black uppercase text-slate-700 dark:text-slate-200 flex items-center justify-between">
                                        <span x-text="'Terminal ' + tItem.terminal"></span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-aviation-50 dark:bg-aviation-950 text-aviation-600 font-mono">Select</span>
                                    </div>
                                    <div class="text-xl font-black mt-2 text-aviation-600 dark:text-aviation-400"
                                         x-text="formatNumber(selectedMetric === 'passenger' ? tItem.passenger_total : (selectedMetric === 'crew' ? tItem.crew_total : tItem.aircraft_total))"></div>
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono mt-3 pt-2 border-t border-slate-200 dark:border-slate-800">
                                    <div>Arr: <span x-text="formatNumber(selectedMetric === 'passenger' ? tItem.passenger_arrival : tItem.aircraft_arrival)"></span></div>
                                    <div>Dep: <span x-text="formatNumber(selectedMetric === 'passenger' ? tItem.passenger_departure : tItem.aircraft_departure)"></span></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- ──────────────── DAU-10B CHARTS ──────────────── --}}
        @if ($reportType === 'DAU10B')
            <div class="space-y-6">
                {{-- Chart 1: Block On / Block Off Hourly Distribution --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Gate Operations Dynamic</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">BLOCK ON / BLOCK OFF HOURLY DISTRIBUTION</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-purple-600"></span> Block On (DTG)</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> Block Off (BRK)</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300">
                                Peak On: <span x-text="peaks.peak_block_on_hour + ' (' + formatNumber(peaks.peak_block_on) + ')'"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Grouped Bars for Block On vs Block Off --}}
                    <div class="h-64 sm:h-72 w-full flex items-end gap-1.5 sm:gap-2 pt-6 pb-2 px-1 overflow-x-auto">
                        <template x-for="(item, idx) in activeHourlyDistribution" :key="idx">
                            <div @click="setHourFilter(item.hour)"
                                 class="flex-1 min-w-[32px] sm:min-w-[40px] flex flex-col items-center h-full justify-end group relative cursor-pointer"
                                 :class="item.hour === peaks.peak_block_on_hour ? 'bg-purple-50/60 dark:bg-purple-950/40 rounded-lg ring-1 ring-purple-400' : ''">
                                
                                {{-- Tooltip --}}
                                <div class="opacity-0 group-hover:opacity-100 transition pointer-events-none absolute bottom-full mb-2 z-30 bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-[10px] font-mono rounded-lg px-2.5 py-1.5 shadow-xl whitespace-nowrap">
                                    <div class="font-bold" x-text="'Jam: ' + item.hour"></div>
                                    <div class="text-purple-400" x-text="'Block On: ' + formatNumber(selectedMetric === 'passenger' ? item.passenger_arrival : item.aircraft_arrival)"></div>
                                    <div class="text-amber-400" x-text="'Block Off: ' + formatNumber(selectedMetric === 'passenger' ? item.passenger_departure : item.aircraft_departure)"></div>
                                    <div class="font-bold text-white dark:text-slate-900" x-text="'Total: ' + formatNumber(selectedMetric === 'passenger' ? item.passenger_total : item.aircraft_total)"></div>
                                </div>

                                {{-- Bars Group --}}
                                <div class="w-full flex items-end justify-center gap-1 px-1" style="height: 100%;">
                                    <div class="w-1/2 bg-purple-600 hover:bg-purple-500 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(selectedMetric === 'passenger' ? item.passenger_arrival : item.aircraft_arrival, maxBlockActivity) + '%'"></div>
                                    <div class="w-1/2 bg-amber-500 hover:bg-amber-400 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(selectedMetric === 'passenger' ? item.passenger_departure : item.aircraft_departure, maxBlockActivity) + '%'"></div>
                                </div>

                                {{-- Hour Label --}}
                                <div class="text-[9px] font-mono text-slate-500 mt-2 truncate w-full text-center"
                                     x-text="item.hour.split(' - ')[0] || item.hour"></div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Activity Comparison Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="glass-card p-5 shadow-sm border-l-4 border-l-purple-600 space-y-2">
                        <div class="text-xs font-black uppercase tracking-wider text-purple-600">Block On Activity (Inbound Gates)</div>
                        <p class="text-xs text-slate-500">Chock-on gate arrivals peak distribution across all active CGK aprons.</p>
                        <div class="flex items-center justify-between pt-2 text-xs font-mono">
                            <span>Peak Inbound Hour:</span>
                            <span class="font-bold text-slate-900 dark:text-white" x-text="peaks.peak_block_on_hour"></span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-mono">
                            <span>Peak Inbound Volume:</span>
                            <span class="font-bold text-purple-600" x-text="formatNumber(peaks.peak_block_on)"></span>
                        </div>
                    </div>

                    <div class="glass-card p-5 shadow-sm border-l-4 border-l-amber-600 space-y-2">
                        <div class="text-xs font-black uppercase tracking-wider text-amber-600">Block Off Activity (Outbound Gates)</div>
                        <p class="text-xs text-slate-500">Chock-off pushback departures peak distribution across all active CGK aprons.</p>
                        <div class="flex items-center justify-between pt-2 text-xs font-mono">
                            <span>Peak Outbound Hour:</span>
                            <span class="font-bold text-slate-900 dark:text-white" x-text="peaks.peak_block_off_hour"></span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-mono">
                            <span>Peak Outbound Volume:</span>
                            <span class="font-bold text-amber-600" x-text="formatNumber(peaks.peak_block_off)"></span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══ 5. PEAK HOUR ANALYSIS SECTION ═════════════════════════════════════ --}}
        <div class="glass-card p-5 sm:p-6 shadow-md border-l-4 border-l-blue-600 space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">PEAK HOUR ANALYSIS</h2>
                </div>
                <div class="text-xs font-mono text-slate-500">
                    Active Filter Applied: <span class="font-bold text-slate-800 dark:text-slate-200" x-text="activeFlightScope + ' • ' + activeTerminalScope"></span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-xs">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[10px] font-bold uppercase text-slate-400">Peak Hour Period</div>
                    <div class="text-base font-black text-blue-600 dark:text-blue-400 mt-1 font-mono" x-text="peaks.peak_hour"></div>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[10px] font-bold uppercase text-slate-400">Aircraft at Peak</div>
                    <div class="text-base font-black text-aviation-600 mt-1" x-text="formatNumber(peaks.peak_aircraft) + ' Aircraft'"></div>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[10px] font-bold uppercase text-slate-400">Passenger at Peak</div>
                    <div class="text-base font-black text-emerald-600 mt-1" x-text="formatNumber(peaks.peak_passenger) + ' Passengers'"></div>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                    <div class="text-[10px] font-bold uppercase text-slate-400">Peak Terminal Focus</div>
                    <div class="text-base font-black text-purple-600 mt-1" x-text="'Terminal ' + peaks.peak_terminal"></div>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-800 text-xs space-y-1">
                <div class="font-bold text-blue-900 dark:text-blue-200 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Why this period is identified as Peak Hour:</span>
                </div>
                <p class="text-slate-600 dark:text-slate-300">
                    During period <strong class="font-mono text-slate-900 dark:text-white" x-text="peaks.peak_hour"></strong>, 
                    the airport records the highest combined traffic density with <strong class="text-aviation-600 dark:text-aviation-400" x-text="formatNumber(peaks.peak_aircraft)"></strong> aircraft movements 
                    and <strong class="text-emerald-600 dark:text-emerald-400" x-text="formatNumber(peaks.peak_passenger)"></strong> passengers handled, 
                    concentrating maximum operational demand primarily at <strong class="text-purple-600" x-text="'Terminal ' + peaks.peak_terminal"></strong>.
                </p>
            </div>
        </div>

        {{-- ══ 6. INTERACTIVE DETAIL DATA TABLE ═════════════════════════════════ --}}
        <div class="glass-card p-5 sm:p-6 shadow-lg space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Hourly Detailed Dataset</span>
                        <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300"
                              x-text="filteredRecords.length + ' records'">
                        </span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Filter, search, or sort detailed hourly movement records extracted from {{ $conf['template_filename'] ?? 'template' }}.</p>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <input type="text" x-model="searchQuery" placeholder="Search table..."
                           class="w-full sm:w-64 px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 focus:outline-hidden focus:ring-2 focus:ring-aviation-500"/>
                    <select x-model.number="pageSize"
                            class="px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-mono">
                        <option value="15">15 rows</option>
                        <option value="25">25 rows</option>
                        <option value="50">50 rows</option>
                        <option value="100">100 rows</option>
                    </select>
                </div>
            </div>

            {{-- Empty / Zero State --}}
            <template x-if="filteredRecords.length === 0">
                <div class="p-8 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-700 space-y-3">
                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-navy-800 flex items-center justify-center mx-auto text-slate-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="text-sm font-bold text-slate-700 dark:text-slate-300">NO DATA FOR SELECTED FILTER</div>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">There are no operational records matching the selected flight type, terminal, or hour combination in the source report.</p>
                    <button type="button" @click="resetFilters()"
                            class="px-4 py-1.5 text-xs font-bold text-aviation-600 bg-aviation-50 hover:bg-aviation-100 dark:bg-aviation-950 dark:hover:bg-aviation-900 rounded-lg transition cursor-pointer">
                        Reset Filter
                    </button>
                </div>
            </template>

            {{-- Table with Sticky Header --}}
            <template x-if="filteredRecords.length > 0">
                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl max-h-[550px]">
                    <table class="w-full text-left text-xs font-sans border-collapse">
                        <thead class="bg-slate-50 dark:bg-navy-900 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 sticky top-0 z-20 shadow-2xs">
                            <tr>
                                <th class="px-3 py-2.5">#</th>
                                <th @click="sortBy('hour')" class="px-3 py-2.5 cursor-pointer hover:text-slate-900 dark:hover:text-white">
                                    Hour <span x-show="sortCol === 'hour'" x-text="sortAsc ? '▲' : '▼'"></span>
                                </th>
                                <th @click="sortBy('terminal')" class="px-3 py-2.5 cursor-pointer hover:text-slate-900 dark:hover:text-white">
                                    Terminal <span x-show="sortCol === 'terminal'" x-text="sortAsc ? '▲' : '▼'"></span>
                                </th>
                                <th @click="sortBy('aircraft_arrival')" class="px-3 py-2.5 text-right cursor-pointer hover:text-slate-900">
                                    @if ($reportType === 'DAU10B') Acft On (DTG) @else Acft ARR @endif
                                </th>
                                <th @click="sortBy('aircraft_departure')" class="px-3 py-2.5 text-right cursor-pointer hover:text-slate-900">
                                    @if ($reportType === 'DAU10B') Acft Off (BRK) @else Acft DEP @endif
                                </th>
                                <th @click="sortBy('aircraft_total')" class="px-3 py-2.5 text-right cursor-pointer font-bold hover:text-slate-900">
                                    Acft Total
                                </th>
                                <th @click="sortBy('passenger_arrival')" class="px-3 py-2.5 text-right cursor-pointer hover:text-slate-900">
                                    @if ($reportType === 'DAU10B') Pax On (DTG) @else Pax ARR @endif
                                </th>
                                <th @click="sortBy('passenger_departure')" class="px-3 py-2.5 text-right cursor-pointer hover:text-slate-900">
                                    @if ($reportType === 'DAU10B') Pax Off (BRK) @else Pax DEP @endif
                                </th>
                                <th @click="sortBy('passenger_total')" class="px-3 py-2.5 text-right cursor-pointer font-bold text-emerald-600 hover:text-emerald-700">
                                    Pax Total
                                </th>
                                <th class="px-2.5 py-2.5 text-right">Transit</th>
                                <th class="px-2.5 py-2.5 text-right">Transfer</th>
                                <th class="px-2.5 py-2.5 text-right">Crew</th>
                                <th class="px-2.5 py-2.5 text-right">Extra Crew</th>
                                @if ($reportType !== 'DAU10A')
                                    <th class="px-2.5 py-2.5 text-right">Baggage</th>
                                    <th class="px-2.5 py-2.5 text-right">Cargo</th>
                                    <th class="px-2.5 py-2.5 text-right">POS</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono text-[11px]">
                            <template x-for="(row, idx) in paginatedRecords" :key="idx">
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-navy-800/50 transition">
                                    <td class="px-3 py-2 font-bold text-slate-400" x-text="startIndex + idx + 1"></td>
                                    <td class="px-3 py-2 font-bold text-aviation-600 dark:text-aviation-400" x-text="row.hour || row.period || '—'"></td>
                                    <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.terminal || '—'"></td>
                                    <td class="px-3 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                    <td class="px-3 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                    <td class="px-3 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                    <td class="px-3 py-2 text-right text-emerald-600" x-text="formatNumber(row.passenger_arrival)"></td>
                                    <td class="px-3 py-2 text-right text-emerald-600" x-text="formatNumber(row.passenger_departure)"></td>
                                    <td class="px-3 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                    <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.passenger_transit)"></td>
                                    <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.passenger_transfer)"></td>
                                    <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.crew)"></td>
                                    <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.extra_crew)"></td>
                                    @if ($reportType !== 'DAU10A')
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.pos)"></td>
                                    @endif
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            {{-- Pagination Controls --}}
            <template x-if="filteredRecords.length > 0">
                <div class="flex items-center justify-between pt-3 text-xs text-slate-500 font-mono">
                    <div>
                        Showing <span class="font-bold text-slate-800 dark:text-slate-200" x-text="startIndex + 1"></span> to 
                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="Math.min(endIndex, filteredRecords.length)"></span> of 
                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="filteredRecords.length"></span> filtered records
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
            </template>
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
function dauEnhancedDashboard() {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
        reportType: @json($reportType),
        allRecords: @json($records),
        meta: @json($meta),
        terminals: @json($terminals),
        hours: @json($hours),

        // Filter state
        filterStartDate: @json($meta['start_date'] ?? date('Y-m-d')),
        filterEndDate: @json($meta['end_date'] ?? date('Y-m-d')),
        filterFlightType: 'ALL', // 'ALL' | 'DOM' | 'INT'
        filterTerminal: 'ALL',
        filterHour: 'ALL',
        selectedMetric: 'aircraft', // 'aircraft' | 'passenger' | 'crew' | 'baggage' | 'cargo' | 'pos'
        filterOperation: 'ALL', // 'ALL' | 'BLOCK_ON' | 'BLOCK_OFF'
        comparisonMetric: 'aircraft',

        // Table interaction state
        searchQuery: '',
        currentPage: 1,
        pageSize: 25,
        sortCol: 'hour',
        sortAsc: true,

        // PDF Export download experience state
        isExportingPdf: false,
        pdfButtonText: 'Export PDF',

        // Dynamic computed dataset
        filteredRecords: [],
        activeSummary: {},
        peaks: {},
        activeHourlyDistribution: [],
        activeTerminalComparison: [],

        initDashboard() {
            this.applyFilters();
        },

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

        setFlightType(type) {
            this.filterFlightType = type;
            this.applyFilters();
        },

        setTerminal(term) {
            this.filterTerminal = term;
            this.applyFilters();
        },

        setHourFilter(hour) {
            this.filterHour = (this.filterHour === hour) ? 'ALL' : hour;
            this.applyFilters();
        },

        filterByTerminalAndHour(term, hour) {
            this.filterTerminal = term;
            this.filterHour = hour;
            this.applyFilters();
        },

        resetFilters() {
            this.filterStartDate = this.meta.start_date || '';
            this.filterEndDate = this.meta.end_date || '';
            this.filterFlightType = 'ALL';
            this.filterTerminal = 'ALL';
            this.filterHour = 'ALL';
            this.filterOperation = 'ALL';
            this.selectedMetric = 'aircraft';
            this.searchQuery = '';
            this.currentPage = 1;
            this.applyFilters();
        },

        applyFilters() {
            this.currentPage = 1;
            const ft = this.filterFlightType;
            const term = this.filterTerminal;
            const hr = this.filterHour;
            const op = this.filterOperation;
            const cleanHr = hr !== 'ALL' ? hr.replace(/[^0-9]/g, '') : null;

            // Filter records
            const matched = this.allRecords.filter(r => {
                const rTerm = String(r.terminal || '');
                const rHour = String(r.hour || r.period || '');

                // Terminal match
                if (term !== 'ALL' && rTerm.toLowerCase() !== term.toLowerCase()) {
                    return false;
                }

                // Hour match
                if (hr !== 'ALL') {
                    const cleanRHour = rHour.replace(/[^0-9]/g, '');
                    if (cleanHr && cleanRHour !== cleanHr && rHour !== hr) {
                        return false;
                    }
                }

                // Flight Scope match
                if (ft === 'DOM') {
                    // All terminals in CGK accommodate domestic flights
                } else if (ft === 'INT') {
                    // Only 2E, 2F, 3U accommodate international traffic in CGK
                    const isIntTerm = ['2E', '2F', '3U', 'T2E', 'T2F', 'T3U', '3'].includes(rTerm.toUpperCase());
                    if (!isIntTerm) return false;
                }

                // DAU10B Operation match
                if (this.reportType === 'DAU10B' && op !== 'ALL') {
                    if (op === 'BLOCK_ON') {
                        if (Number(r.aircraft_arrival || 0) === 0 && Number(r.passenger_arrival || 0) === 0) return false;
                    } else if (op === 'BLOCK_OFF') {
                        if (Number(r.aircraft_departure || 0) === 0 && Number(r.passenger_departure || 0) === 0) return false;
                    }
                }

                return true;
            });

            this.filteredRecords = matched;
            this.recalculateAnalytics();
        },

        recalculateAnalytics() {
            const summary = {
                total_movements: 0,
                aircraft_arrival: 0,
                aircraft_departure: 0,
                passenger_arrival: 0,
                passenger_departure: 0,
                passenger_transit: 0,
                passenger_transfer: 0,
                passenger_total: 0,
                crew_total: 0,
                baggage_total: 0,
                cargo_total: 0,
                pos_total: 0,
            };

            const hourlyMap = {};
            const termMap = {};

            this.filteredRecords.forEach(r => {
                const h = r.hour || r.period || 'N/A';
                const t = r.terminal || 'ALL';

                const acArr = Number(r.aircraft_arrival || 0);
                const acDep = Number(r.aircraft_departure || 0);
                const acTot = Number(r.aircraft_total || (acArr + acDep));

                const pxArr = Number(r.passenger_arrival || 0);
                const pxDep = Number(r.passenger_departure || 0);
                const pxTrn = Number(r.passenger_transit || 0);
                const pxTrf = Number(r.passenger_transfer || 0);
                const pxTot = Number(r.passenger_total || (pxArr + pxDep + pxTrn + pxTrf));

                const crew = Number(r.crew_total || (Number(r.crew || 0) + Number(r.extra_crew || 0)));
                const bag  = Number(r.baggage || 0);
                const cgo  = Number(r.cargo || 0);
                const pos  = Number(r.pos || 0);

                summary.total_movements    += acTot;
                summary.aircraft_arrival   += acArr;
                summary.aircraft_departure += acDep;
                summary.passenger_arrival  += pxArr;
                summary.passenger_departure+= pxDep;
                summary.passenger_transit  += pxTrn;
                summary.passenger_transfer += pxTrf;
                summary.passenger_total    += pxTot;
                summary.crew_total         += crew;
                summary.baggage_total      += bag;
                summary.cargo_total        += cgo;
                summary.pos_total          += pos;

                // Hourly bucket
                if (!hourlyMap[h]) {
                    hourlyMap[h] = {
                        hour: h,
                        aircraft_arrival: 0,
                        aircraft_departure: 0,
                        aircraft_total: 0,
                        passenger_arrival: 0,
                        passenger_departure: 0,
                        passenger_total: 0,
                        crew_total: 0,
                        baggage: 0,
                        cargo: 0,
                        pos: 0,
                    };
                }
                hourlyMap[h].aircraft_arrival    += acArr;
                hourlyMap[h].aircraft_departure  += acDep;
                hourlyMap[h].aircraft_total      += acTot;
                hourlyMap[h].passenger_arrival   += pxArr;
                hourlyMap[h].passenger_departure += pxDep;
                hourlyMap[h].passenger_total     += pxTot;
                hourlyMap[h].crew_total          += crew;
                hourlyMap[h].baggage             += bag;
                hourlyMap[h].cargo               += cgo;
                hourlyMap[h].pos                 += pos;

                // Terminal bucket
                if (!termMap[t]) {
                    termMap[t] = {
                        terminal: t,
                        aircraft_arrival: 0,
                        aircraft_departure: 0,
                        aircraft_total: 0,
                        passenger_arrival: 0,
                        passenger_departure: 0,
                        passenger_total: 0,
                        crew_total: 0,
                        baggage: 0,
                        cargo: 0,
                        pos: 0,
                    };
                }
                termMap[t].aircraft_arrival    += acArr;
                termMap[t].aircraft_departure  += acDep;
                termMap[t].aircraft_total      += acTot;
                termMap[t].passenger_arrival   += pxArr;
                termMap[t].passenger_departure += pxDep;
                termMap[t].passenger_total     += pxTot;
                termMap[t].crew_total          += crew;
                termMap[t].baggage             += bag;
                termMap[t].cargo               += cgo;
                termMap[t].pos                 += pos;
            });

            this.activeSummary = summary;
            this.activeHourlyDistribution = Object.values(hourlyMap);
            this.activeTerminalComparison = Object.values(termMap);

            // Compute dynamic peaks
            let peakAcHour = '—', peakAc = 0;
            let peakPaxHour = '—', peakPax = 0;
            let peakOnHour = '—', peakOn = 0;
            let peakOffHour = '—', peakOff = 0;

            this.activeHourlyDistribution.forEach(hb => {
                if (hb.aircraft_total > peakAc) {
                    peakAc = hb.aircraft_total;
                    peakAcHour = hb.hour;
                }
                if (hb.passenger_total > peakPax) {
                    peakPax = hb.passenger_total;
                    peakPaxHour = hb.hour;
                }
                if (hb.aircraft_arrival > peakOn) {
                    peakOn = hb.aircraft_arrival;
                    peakOnHour = hb.hour;
                }
                if (hb.aircraft_departure > peakOff) {
                    peakOff = hb.aircraft_departure;
                    peakOffHour = hb.hour;
                }
            });

            let peakTerm = '—', peakTermVal = 0;
            this.activeTerminalComparison.forEach(tb => {
                const v = this.selectedMetric === 'passenger' ? tb.passenger_total : tb.aircraft_total;
                if (v > peakTermVal) {
                    peakTermVal = v;
                    peakTerm = tb.terminal;
                }
            });

            this.peaks = {
                peak_aircraft_hour: peakAcHour,
                peak_aircraft: peakAc,
                peak_passenger_hour: peakPaxHour,
                peak_passenger: peakPax,
                peak_hour: this.selectedMetric === 'passenger' ? peakPaxHour : peakAcHour,
                peak_terminal: peakTerm,
                peak_terminal_val: peakTermVal,
                peak_block_on_hour: peakOnHour,
                peak_block_on: peakOn,
                peak_block_off_hour: peakOffHour,
                peak_block_off: peakOff,
            };
        },

        get activeDateRange() {
            if (this.filterStartDate && this.filterEndDate) {
                return `${this.filterStartDate} s/d ${this.filterEndDate}`;
            }
            return this.meta.date_range || 'N/A';
        },

        get activeFlightScope() {
            if (this.filterFlightType === 'DOM') return 'DOMESTIK';
            if (this.filterFlightType === 'INT') return 'INTERNASIONAL';
            return 'ALL (DOM & INT)';
        },

        get activeTerminalScope() {
            if (this.filterTerminal !== 'ALL') return 'TERMINAL ' + this.filterTerminal;
            return 'ALL TERMINAL';
        },

        get maxAircraftPerHour() {
            let max = 1;
            this.activeHourlyDistribution.forEach(h => {
                if (h.aircraft_total > max) max = h.aircraft_total;
            });
            return max;
        },

        get maxPassengerPerHour() {
            let max = 1;
            this.activeHourlyDistribution.forEach(h => {
                if (h.passenger_total > max) max = h.passenger_total;
            });
            return max;
        },

        get maxBlockActivity() {
            let max = 1;
            this.activeHourlyDistribution.forEach(h => {
                const v1 = this.selectedMetric === 'passenger' ? h.passenger_arrival : h.aircraft_arrival;
                const v2 = this.selectedMetric === 'passenger' ? h.passenger_departure : h.aircraft_departure;
                if (v1 > max) max = v1;
                if (v2 > max) max = v2;
            });
            return max;
        },

        calculateBarHeight(val, max) {
            if (!val || val <= 0 || !max) return 3;
            return Math.max(4, Math.round((val / max) * 95));
        },

        getHeatmapValue(term, hour) {
            const rec = this.allRecords.find(r => 
                String(r.terminal) === String(term) && 
                (String(r.hour) === String(hour) || String(r.period) === String(hour))
            );
            if (!rec) return 0;
            if (this.selectedMetric === 'passenger') return Number(rec.passenger_total || 0);
            if (this.selectedMetric === 'crew') return Number(rec.crew_total || 0);
            return Number(rec.aircraft_total || 0);
        },

        getHeatmapColor(term, hour) {
            const val = this.getHeatmapValue(term, hour);
            if (val === 0) return 'transparent';
            const maxVal = this.selectedMetric === 'passenger' ? 4000 : 30;
            const ratio = Math.min(1, val / maxVal);
            if (ratio < 0.15) return 'rgba(147, 197, 253, 0.25)';
            if (ratio < 0.35) return 'rgba(96, 165, 250, 0.45)';
            if (ratio < 0.65) return 'rgba(59, 130, 246, 0.70)';
            return 'rgba(29, 78, 216, 0.90)';
        },

        formatNumber(val) {
            if (val === null || val === undefined || isNaN(val)) return '0';
            return Number(val).toLocaleString('id-ID');
        },

        // Search, Sort, Pagination
        sortBy(col) {
            if (this.sortCol === col) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortCol = col;
                this.sortAsc = true;
            }
        },

        get searchedRecords() {
            if (!this.searchQuery) return this.filteredRecords;
            const q = this.searchQuery.toLowerCase();
            return this.filteredRecords.filter(r => JSON.stringify(r).toLowerCase().includes(q));
        },

        get sortedRecords() {
            const list = [...this.searchedRecords];
            const col = this.sortCol;
            const asc = this.sortAsc;
            return list.sort((a, b) => {
                let vA = a[col] ?? 0;
                let vB = b[col] ?? 0;
                if (typeof vA === 'string') {
                    return asc ? vA.localeCompare(vB) : vB.localeCompare(vA);
                }
                return asc ? (vA - vB) : (vB - vA);
            });
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.sortedRecords.length / this.pageSize));
        },

        get startIndex() {
            return (this.currentPage - 1) * this.pageSize;
        },

        get endIndex() {
            return this.startIndex + this.pageSize;
        },

        get paginatedRecords() {
            return this.sortedRecords.slice(this.startIndex, this.endIndex);
        },

        nextPage() {
            if (this.currentPage < this.totalPages) this.currentPage++;
        },

        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        },

        // URLs & Export Download Feedback
        get filterQueryParams() {
            const params = new URLSearchParams();
            params.set('flight_type', this.filterFlightType);
            params.set('terminal', this.filterTerminal);
            params.set('hour', this.filterHour);
            params.set('metric', this.selectedMetric);
            if (this.reportType === 'DAU10B') {
                params.set('operation', this.filterOperation);
            }
            if (this.filterStartDate) params.set('start_date', this.filterStartDate);
            if (this.filterEndDate) params.set('end_date', this.filterEndDate);
            return params.toString();
        },

        get exportPdfUrl() {
            return `{{ route('dau.export.pdf', $upload->id) }}?${this.filterQueryParams}`;
        },

        get exportCsvUrl() {
            return `{{ route('dau.export.excel', $upload->id) }}?${this.filterQueryParams}`;
        },

        async downloadPdfReport() {
            this.isExportingPdf = true;
            this.pdfButtonText = 'Preparing PDF Report...';

            try {
                // Short wait to ensure visual feedback
                await new Promise(r => setTimeout(r, 400));
                this.pdfButtonText = 'Generating PDF...';

                const response = await fetch(this.exportPdfUrl);
                if (!response.ok) {
                    throw new Error('PDF EXPORT FAILED: Unable to render the selected report.');
                }

                const blob = await response.blob();
                const disposition = response.headers.get('content-disposition');
                let filename = 'DAU_Report.pdf';
                if (disposition && disposition.includes('filename=')) {
                    const match = disposition.match(/filename="?([^"]+)"?/);
                    if (match && match[1]) filename = match[1];
                }

                const blobUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(blobUrl);

                this.pdfButtonText = 'Download ready';
                setTimeout(() => {
                    this.pdfButtonText = 'Export PDF';
                    this.isExportingPdf = false;
                }, 1500);

            } catch (err) {
                console.error(err);
                alert(err.message || 'PDF Export encountered an error. Please retry.');
                this.pdfButtonText = 'Export PDF';
                this.isExportingPdf = false;
            }
        }
    };
}
</script>
@endpush
