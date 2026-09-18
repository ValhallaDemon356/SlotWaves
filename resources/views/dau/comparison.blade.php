@extends('layouts.app')

@section('title', 'DAU-02 Historical Comparison — ' . ($comparison['airport_name'] ?? 'Airport') . ' (' . ($comparison['airport_code'] ?? 'CGK') . ')')
@section('bodyClass', 'bg-slate-50 dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between transition-colors duration-150')

@section('content')
<div x-data="dauComparisonDashboard()" class="min-h-screen flex flex-col justify-between">

    {{-- ══ TOPBAR NAVIGATION ═══════════════════════════════════════════════════ --}}
    <header class="w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-navy-900/90 backdrop-blur-md sticky top-0 z-30 px-4 sm:px-8 py-3 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="w-8 h-8 rounded-lg bg-aviation-600 flex items-center justify-center shadow-sm text-white hover:bg-aviation-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-black tracking-tight text-slate-900 dark:text-white">SlotWaves</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                        DAU-02 Historical Comparison
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                    {{ $comparison['airport_name'] }} ({{ $comparison['airport_code'] }}) &bull; {{ $comparison['period_count'] }} Periods Evaluated
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            {{-- Export CSV --}}
            <a :href="'{{ route('dau.compare.export.csv') }}?reports=' + reportIdsStr + '&flight_type=' + activeFlightType + '&direction=' + activeDirection + '&baseline=' + baselinePeriodKey"
               class="text-xs font-bold text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-800 hover:bg-slate-50 dark:hover:bg-navy-700 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Export CSV</span>
            </a>

            {{-- Export PDF --}}
            <a :href="'{{ route('dau.compare.export.pdf') }}?reports=' + reportIdsStr + '&flight_type=' + activeFlightType + '&direction=' + activeDirection + '&baseline=' + baselinePeriodKey"
               class="text-xs font-bold text-white px-3 py-1.5 rounded-lg bg-aviation-600 hover:bg-aviation-700 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <span>Export PDF</span>
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

    {{-- ══ MAIN CONTENT ════════════════════════════════════════════════════════ --}}
    <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">

        {{-- ══ PERIOD COMPARISON OVERVIEW CARDS ════════════════════════════════ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-{{ min(4, count($comparison['periods'])) }} gap-3">
            @foreach ($comparison['periods'] as $pKey => $p)
                <div class="glass-card p-4 rounded-xl border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between shadow-2xs relative overflow-hidden">
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-aviation-600"></div>
                    <div class="pl-2">
                        <div class="text-[10px] font-mono uppercase tracking-wider text-aviation-600 dark:text-aviation-400 font-bold">
                            {{ $p['label'] }}
                        </div>
                        <div class="text-sm font-black text-slate-900 dark:text-white mt-0.5">
                            {{ $p['display_range'] }}
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                            {{ $p['data_days'] }} data days &bull; {{ $p['airport_code'] }}
                        </div>
                    </div>
                    <span class="w-8 h-8 rounded-lg bg-aviation-50 dark:bg-aviation-950/80 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-mono font-black text-xs flex items-center justify-center shrink-0">
                        {{ $p['letter'] }}
                    </span>
                </div>
            @endforeach
        </div>

        {{-- ══ INTERACTIVE FILTER PIPELINE & CONTROLS ═══════════════════════════ --}}
        <div class="glass-card p-4 rounded-xl border border-slate-200/80 dark:border-slate-800/80 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-2xs">
            <div class="flex flex-wrap items-center gap-3">
                {{-- Flight Scope Filter (ALL / DOM / INT) --}}
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Scope:</span>
                    <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 bg-slate-100 dark:bg-navy-800">
                        <button type="button" @click="setFlightType('ALL')"
                                :class="activeFlightType === 'ALL' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">ALL</button>
                        <button type="button" @click="setFlightType('DOM')"
                                :class="activeFlightType === 'DOM' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">DOMESTIK</button>
                        <button type="button" @click="setFlightType('INT')"
                                :class="activeFlightType === 'INT' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">INTERNASIONAL</button>
                    </div>
                </div>

                {{-- Direction Filter (ALL / ARRIVAL / DEPARTURE) --}}
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Direction:</span>
                    <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 bg-slate-100 dark:bg-navy-800">
                        <button type="button" @click="setDirection('ALL')"
                                :class="activeDirection === 'ALL' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">ALL</button>
                        <button type="button" @click="setDirection('ARRIVAL')"
                                :class="activeDirection === 'ARRIVAL' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">ARRIVAL</button>
                        <button type="button" @click="setDirection('DEPARTURE')"
                                :class="activeDirection === 'DEPARTURE' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">DEPARTURE</button>
                    </div>
                </div>
            </div>

            {{-- Baseline Period Selector for Recovery Rate --}}
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Baseline Period:</span>
                <select x-model="baselinePeriodKey" @change="applyFilterParams()"
                        class="text-xs font-bold font-mono px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-800 text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                    @foreach ($comparison['periods'] as $pKey => $p)
                        <option value="{{ $pKey }}">{{ $p['label'] }} ({{ $p['short_label'] }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- ══ KEY HIGHLIGHTS CARD ═════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {{-- Passenger Highlight --}}
            <div class="p-4 rounded-xl bg-white dark:bg-navy-900/80 border border-slate-200 dark:border-slate-800 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">
                        Passenger Movement
                    </span>
                    <div class="text-xl font-black text-slate-900 dark:text-white mt-0.5">
                        {{ $comparison['highlights']['passenger']['growth_pct'] }}
                    </div>
                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                        Change: {{ ($comparison['highlights']['passenger']['difference'] > 0 ? '+' : '') . number_format($comparison['highlights']['passenger']['difference']) }} Pax
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $comparison['highlights']['passenger']['is_positive'] ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $comparison['highlights']['passenger']['is_positive'] ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6' }}"/></svg>
                </div>
            </div>

            {{-- Aircraft Highlight --}}
            <div class="p-4 rounded-xl bg-white dark:bg-navy-900/80 border border-slate-200 dark:border-slate-800 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">
                        Aircraft Movement
                    </span>
                    <div class="text-xl font-black text-slate-900 dark:text-white mt-0.5">
                        {{ $comparison['highlights']['aircraft']['growth_pct'] }}
                    </div>
                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                        Change: {{ ($comparison['highlights']['aircraft']['difference'] > 0 ? '+' : '') . number_format($comparison['highlights']['aircraft']['difference']) }} A/C
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $comparison['highlights']['aircraft']['is_positive'] ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $comparison['highlights']['aircraft']['is_positive'] ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6' }}"/></svg>
                </div>
            </div>

            {{-- Cargo Highlight --}}
            <div class="p-4 rounded-xl bg-white dark:bg-navy-900/80 border border-slate-200 dark:border-slate-800 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">
                        Cargo Movement ({{ $comparison['cargo_unit'] }})
                    </span>
                    <div class="text-xl font-black text-slate-900 dark:text-white mt-0.5">
                        {{ $comparison['highlights']['cargo']['growth_pct'] }}
                    </div>
                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                        Change: {{ ($comparison['highlights']['cargo']['difference'] > 0 ? '+' : '') . number_format($comparison['highlights']['cargo']['difference']) }} {{ $comparison['cargo_unit'] }}
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $comparison['highlights']['cargo']['is_positive'] ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $comparison['highlights']['cargo']['is_positive'] ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6' }}"/></svg>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             SECTION 1: KINERJA OPERASIONAL BANDARA
             ═══════════════════════════════════════════════════════════════════ --}}
        <section class="space-y-4">
            <div class="border-b border-slate-200/80 dark:border-slate-800/80 pb-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400 font-mono">
                    SECTION 1 &bull; OPERATIONAL PERFORMANCE
                </div>
                <h2 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5">
                    KINERJA OPERASIONAL BANDARA
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                    {{ $comparison['airport_name'] }} ({{ $comparison['airport_code'] }}) &mdash; Comprehensive comparison across operational reporting periods
                </p>
            </div>

            {{-- 3 Major Analytical Cards with Grouped Bar Charts --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Metric 1: Pergerakan Penumpang --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 font-mono">
                                1. PERGERAKAN PENUMPANG
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold">
                                Pax
                            </span>
                        </div>

                        {{-- Period Summary Values --}}
                        <div class="space-y-2 py-1 font-mono text-xs">
                            @foreach ($comparison['analysis']['passenger']['series'] as $pKey => $row)
                                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50/80 dark:bg-navy-900/60 border border-slate-100 dark:border-slate-800/60">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $row['period_label'] }} ({{ $row['short_label'] }})</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-black text-slate-900 dark:text-white">{{ number_format($row['value']) }}</span>
                                        @if ($row['growth_pct'] !== null)
                                            <span class="text-[10px] font-bold ml-1 {{ $row['growth_pct'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                                {{ $row['growth_fmt'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Mini Grouped Bar Chart Canvas --}}
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 h-44 relative">
                            <canvas id="chart-section1-passenger"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Metric 2: Pergerakan Pesawat --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-mono">
                                2. PERGERAKAN PESAWAT
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-bold">
                                Movements
                            </span>
                        </div>

                        {{-- Period Summary Values --}}
                        <div class="space-y-2 py-1 font-mono text-xs">
                            @foreach ($comparison['analysis']['aircraft']['series'] as $pKey => $row)
                                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50/80 dark:bg-navy-900/60 border border-slate-100 dark:border-slate-800/60">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $row['period_label'] }} ({{ $row['short_label'] }})</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-black text-slate-900 dark:text-white">{{ number_format($row['value']) }}</span>
                                        @if ($row['growth_pct'] !== null)
                                            <span class="text-[10px] font-bold ml-1 {{ $row['growth_pct'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                                {{ $row['growth_fmt'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Mini Grouped Bar Chart Canvas --}}
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 h-44 relative">
                            <canvas id="chart-section1-aircraft"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Metric 3: Pergerakan Kargo --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 font-mono">
                                3. PERGERAKAN KARGO
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 font-bold">
                                {{ $comparison['cargo_unit'] }}
                            </span>
                        </div>

                        {{-- Period Summary Values --}}
                        <div class="space-y-2 py-1 font-mono text-xs">
                            @foreach ($comparison['analysis']['cargo']['series'] as $pKey => $row)
                                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50/80 dark:bg-navy-900/60 border border-slate-100 dark:border-slate-800/60">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-amber-600"></span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $row['period_label'] }} ({{ $row['short_label'] }})</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-black text-slate-900 dark:text-white">{{ number_format($row['value']) }}</span>
                                        @if ($row['growth_pct'] !== null)
                                            <span class="text-[10px] font-bold ml-1 {{ $row['growth_pct'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                                {{ $row['growth_fmt'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Mini Grouped Bar Chart Canvas --}}
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 h-44 relative">
                            <canvas id="chart-section1-cargo"></canvas>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════════
             SECTION 2: DATA PERGERAKAN HISTORIS
             ═══════════════════════════════════════════════════════════════════ --}}
        <section class="space-y-4">
            <div class="border-b border-slate-200/80 dark:border-slate-800/80 pb-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400 font-mono">
                    SECTION 2 &bull; HISTORICAL TRENDS &amp; RECOVERY ANALYSIS
                </div>
                <h2 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5">
                    DATA PERGERAKAN HISTORIS
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                    Comparison of historical operational periods &mdash; Separate scale aligned visual series
                </p>
            </div>

            {{-- 3 Separate Aligned Charts on Independent Numeric Axes --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Chart 1: Penumpang --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="text-xs font-bold text-slate-900 dark:text-white">PERGERAKAN PENUMPANG</span>
                        <span class="text-[10px] font-mono text-slate-400">Pax Scale</span>
                    </div>
                    <div class="h-60 relative">
                        <canvas id="chart-hist-passenger"></canvas>
                    </div>
                </div>

                {{-- Chart 2: Pesawat --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="text-xs font-bold text-slate-900 dark:text-white">PERGERAKAN PESAWAT</span>
                        <span class="text-[10px] font-mono text-slate-400">Movements Scale</span>
                    </div>
                    <div class="h-60 relative">
                        <canvas id="chart-hist-aircraft"></canvas>
                    </div>
                </div>

                {{-- Chart 3: Kargo --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="text-xs font-bold text-slate-900 dark:text-white">PERGERAKAN KARGO</span>
                        <span class="text-[10px] font-mono text-slate-400">{{ $comparison['cargo_unit'] }} Scale</span>
                    </div>
                    <div class="h-60 relative">
                        <canvas id="chart-hist-cargo"></canvas>
                    </div>
                </div>
            </div>

            {{-- ══ HISTORICAL COMPARISON TABLE ═════════════════════════════════ --}}
            <div class="glass-card p-5 sm:p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-mono uppercase tracking-wider text-aviation-600 dark:text-aviation-400 font-bold">Tabular Comparison</div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">HISTORICAL OPERATIONAL MOVEMENTS SUMMARY</h3>
                    </div>
                    <span class="text-xs font-mono text-slate-500">
                        Baseline: <strong class="text-slate-800 dark:text-slate-200">{{ $comparison['periods'][$comparison['baseline_period_key']]['label'] ?? 'P1' }}</strong>
                    </span>
                </div>

                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                    <table class="w-full text-xs font-sans border-collapse">
                        <thead class="bg-slate-50 dark:bg-navy-900 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3 text-left">Metrik Operasional</th>
                                <th class="px-3 py-3 text-center">Unit</th>
                                @foreach ($comparison['periods'] as $pKey => $p)
                                    <th class="px-4 py-3 text-right">
                                        <div>{{ $p['label'] }}</div>
                                        <div class="text-[9px] font-mono font-normal text-slate-400">{{ $p['short_label'] }}</div>
                                    </th>
                                @endforeach
                                <th class="px-4 py-3 text-right text-aviation-600 dark:text-aviation-400">Change (&Delta;)</th>
                                <th class="px-4 py-3 text-right text-aviation-600 dark:text-aviation-400">Growth %</th>
                                <th class="px-4 py-3 text-right text-slate-500">Recovery %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono text-xs">
                            {{-- Passenger Row --}}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-navy-800/40 transition">
                                <td class="px-4 py-3 font-bold font-sans text-slate-900 dark:text-white flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                                    <span>Pergerakan Penumpang</span>
                                </td>
                                <td class="px-3 py-3 text-center text-slate-400 font-mono text-[11px]">Pax</td>
                                @foreach ($comparison['analysis']['passenger']['series'] as $pKey => $row)
                                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-200">
                                        {{ number_format($row['value']) }}
                                    </td>
                                @endforeach
                                @php
                                    $pSeries = array_values($comparison['analysis']['passenger']['series']);
                                    $lastRow = end($pSeries);
                                @endphp
                                <td class="px-4 py-3 text-right font-black {{ $lastRow['difference'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                    {{ $lastRow['difference_fmt'] }}
                                </td>
                                <td class="px-4 py-3 text-right font-black {{ ($lastRow['growth_pct'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                    {{ $lastRow['growth_fmt'] }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-slate-600 dark:text-slate-300">
                                    {{ $lastRow['recovery_fmt'] }}
                                </td>
                            </tr>

                            {{-- Aircraft Row --}}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-navy-800/40 transition">
                                <td class="px-4 py-3 font-bold font-sans text-slate-900 dark:text-white flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                                    <span>Pergerakan Pesawat</span>
                                </td>
                                <td class="px-3 py-3 text-center text-slate-400 font-mono text-[11px]">Movements</td>
                                @foreach ($comparison['analysis']['aircraft']['series'] as $pKey => $row)
                                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-200">
                                        {{ number_format($row['value']) }}
                                    </td>
                                @endforeach
                                @php
                                    $aSeries = array_values($comparison['analysis']['aircraft']['series']);
                                    $lastRowA = end($aSeries);
                                @endphp
                                <td class="px-4 py-3 text-right font-black {{ $lastRowA['difference'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                    {{ $lastRowA['difference_fmt'] }}
                                </td>
                                <td class="px-4 py-3 text-right font-black {{ ($lastRowA['growth_pct'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                    {{ $lastRowA['growth_fmt'] }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-slate-600 dark:text-slate-300">
                                    {{ $lastRowA['recovery_fmt'] }}
                                </td>
                            </tr>

                            {{-- Cargo Row --}}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-navy-800/40 transition">
                                <td class="px-4 py-3 font-bold font-sans text-slate-900 dark:text-white flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-600"></span>
                                    <span>Pergerakan Kargo</span>
                                </td>
                                <td class="px-3 py-3 text-center text-slate-400 font-mono text-[11px]">{{ $comparison['cargo_unit'] }}</td>
                                @foreach ($comparison['analysis']['cargo']['series'] as $pKey => $row)
                                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-200">
                                        {{ number_format($row['value']) }}
                                    </td>
                                @endforeach
                                @php
                                    $cSeries = array_values($comparison['analysis']['cargo']['series']);
                                    $lastRowC = end($cSeries);
                                @endphp
                                <td class="px-4 py-3 text-right font-black {{ $lastRowC['difference'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                    {{ $lastRowC['difference_fmt'] }}
                                </td>
                                <td class="px-4 py-3 text-right font-black {{ ($lastRowC['growth_pct'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                    {{ $lastRowC['growth_fmt'] }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-slate-600 dark:text-slate-300">
                                    {{ $lastRowC['recovery_fmt'] }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>

    {{-- ══ FOOTER ═══════════════════════════════════════════════════════════════ --}}
    <footer class="w-full border-t border-slate-200/80 dark:border-slate-800/80 py-3.5 px-4 text-center">
        <div class="max-w-4xl mx-auto flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400 font-mono">
            <span>SlotWaves &bull; DAU-02 Historical Comparison Engine</span>
            <span>Airport: {{ $comparison['airport_name'] }} ({{ $comparison['airport_code'] }})</span>
            <span>All source values verified</span>
        </div>
    </footer>

</div>
@endsection

@push('scripts')
<script>
function dauComparisonDashboard() {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
        reportIdsStr: @json($reportIdsStr),
        activeFlightType: @json($filters['flight_type']),
        activeDirection: @json($filters['direction']),
        baselinePeriodKey: @json($comparison['baseline_period_key']),
        comparisonData: @json($comparison),

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
            this.renderAllCharts();
        },

        setFlightType(type) {
            this.activeFlightType = type;
            this.applyFilterParams();
        },

        setDirection(dir) {
            this.activeDirection = dir;
            this.applyFilterParams();
        },

        applyFilterParams() {
            const url = new URL(window.location.href);
            url.searchParams.set('flight_type', this.activeFlightType);
            url.searchParams.set('direction', this.activeDirection);
            url.searchParams.set('baseline', this.baselinePeriodKey);
            window.location.href = url.toString();
        },

        renderAllCharts() {
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? 'rgba(51, 65, 85, 0.3)' : 'rgba(226, 232, 240, 0.6)';

            const periods = Object.values(this.comparisonData.periods);
            const labels = periods.map(p => p.short_label || p.label);

            const colors = [
                { bar: '#2563eb', hover: '#1d4ed8' }, // Blue
                { bar: '#059669', hover: '#047857' }, // Emerald
                { bar: '#d97706', hover: '#b45309' }, // Amber
                { bar: '#7c3aed', hover: '#6d28d9' }, // Violet
                { bar: '#db2777', hover: '#be185d' }, // Pink
            ];

            // ── Section 1 Grouped Bars ──
            const createBarChart = (canvasId, seriesData, label, barColor) => {
                const ctx = document.getElementById(canvasId);
                if (!ctx) return;
                const existing = Chart.getChart(ctx);
                if (existing) existing.destroy();

                const values = periods.map(p => seriesData[p.key]?.value || 0);

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: label,
                            data: values,
                            backgroundColor: barColor,
                            borderRadius: 6,
                            maxBarThickness: 44,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: (ctxArr) => {
                                        const idx = ctxArr[0].dataIndex;
                                        return periods[idx].label + ' (' + periods[idx].display_range + ')';
                                    },
                                    label: (item) => {
                                        return label + ': ' + Number(item.raw).toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: textColor, font: { family: 'JetBrains Mono', size: 10 } }
                            },
                            y: {
                                grid: { color: gridColor },
                                ticks: {
                                    color: textColor,
                                    font: { family: 'JetBrains Mono', size: 9 },
                                    callback: (v) => v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v)
                                }
                            }
                        }
                    }
                });
            };

            // Render Section 1 Charts
            createBarChart('chart-section1-passenger', this.comparisonData.analysis.passenger.series, 'Passenger Movement', '#2563eb');
            createBarChart('chart-section1-aircraft', this.comparisonData.analysis.aircraft.series, 'Aircraft Movement', '#059669');
            createBarChart('chart-section1-cargo', this.comparisonData.analysis.cargo.series, 'Cargo (' + this.comparisonData.cargo_unit + ')', '#d97706');

            // ── Section 2 Historical Trends Charts ──
            createBarChart('chart-hist-passenger', this.comparisonData.analysis.passenger.series, 'Passenger', '#3b82f6');
            createBarChart('chart-hist-aircraft', this.comparisonData.analysis.aircraft.series, 'Aircraft', '#10b981');
            createBarChart('chart-hist-cargo', this.comparisonData.analysis.cargo.series, 'Cargo (' + this.comparisonData.cargo_unit + ')', '#f59e0b');
        },

        init() {
            this.$nextTick(() => {
                this.renderAllCharts();
            });
        }
    };
}
</script>
@endpush
