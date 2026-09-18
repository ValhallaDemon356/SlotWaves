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
            <a :href="'{{ route('dau.compare.export.csv') }}?reports=' + reportIdsStr + '&hist_scope=' + historicalScope + '&hist_direction=' + historicalDirection + '&baseline=' + baselinePeriodKey"
               class="text-xs font-bold text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-800 hover:bg-slate-50 dark:hover:bg-navy-700 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Export CSV</span>
            </a>

            {{-- Export PDF --}}
            <a :href="'{{ route('dau.compare.export.pdf') }}?reports=' + reportIdsStr + '&hist_scope=' + historicalScope + '&hist_direction=' + historicalDirection + '&baseline=' + baselinePeriodKey"
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
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-{{ min(6, count($comparison['periods'])) }} gap-3">
            @foreach ($comparison['periods'] as $pKey => $p)
                <div class="glass-card p-4 rounded-xl border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between shadow-2xs relative overflow-hidden">
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 {{ $pKey === $comparison['baseline_period_key'] ? 'bg-amber-500' : 'bg-aviation-600' }}"></div>
                    <div class="pl-2">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-mono uppercase tracking-wider font-bold {{ $pKey === $comparison['baseline_period_key'] ? 'text-amber-600 dark:text-amber-400' : 'text-aviation-600 dark:text-aviation-400' }}">
                                {{ $p['label'] }}
                            </span>
                            @if ($pKey === $comparison['baseline_period_key'])
                                <span class="text-[8px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">BASELINE</span>
                            @endif
                        </div>
                        <div class="text-sm font-black text-slate-900 dark:text-white mt-0.5">
                            {{ $p['display_range'] }}
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                            {{ $p['data_days'] }} days &bull; {{ $p['short_label'] }}
                        </div>
                    </div>
                    <span class="w-8 h-8 rounded-lg font-mono font-black text-xs flex items-center justify-center shrink-0 {{ $pKey === $comparison['baseline_period_key'] ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-200 dark:border-amber-800' : 'bg-aviation-50 text-aviation-700 dark:bg-aviation-950/80 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800' }}">
                        {{ $p['letter'] }}
                    </span>
                </div>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             BASELINE COMPARISON CONTROL & CARDS (PART 1 — 7)
             ═══════════════════════════════════════════════════════════════════ --}}
        <section class="glass-card p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800/80 pb-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/80 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800">
                            BASELINE COMPARISON
                        </span>
                        <span class="text-xs text-slate-500 font-medium">Selected baseline compared against all other uploaded periods</span>
                    </div>
                    <h3 class="text-lg font-black text-slate-900 dark:text-white mt-1">
                        BASELINE: <span class="text-aviation-600 dark:text-aviation-400" x-text="baselinePeriodLabel"></span>
                    </h3>
                </div>

                {{-- Baseline Selector Dropdown --}}
                <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Change Baseline:</label>
                    <select x-model="baselinePeriodKey" @change="changeBaseline(baselinePeriodKey)"
                            class="text-xs font-bold font-mono px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-800 text-slate-800 dark:text-slate-200 focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                        @foreach ($comparison['periods'] as $pKey => $p)
                            <option value="{{ $pKey }}">{{ $p['label'] }} ({{ $p['short_label'] }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Target Period Selector Pills --}}
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Inspect Target Period:</span>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <template x-for="target in targetPeriods" :key="target.key">
                        <button type="button" @click="activeTargetKey = target.key"
                                :class="activeTargetKey === target.key
                                    ? 'bg-aviation-600 text-white shadow-xs font-black'
                                    : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-navy-700 font-bold'"
                                class="px-3 py-1 rounded-lg text-xs font-mono transition cursor-pointer flex items-center gap-1">
                            <span x-text="target.label"></span>
                            <span class="opacity-75" x-text="'(' + target.short_label + ')'"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Semantic Comparison KPI Cards for the Selected Target Period --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" x-show="currentTargetComparison">
                {{-- Passenger Card --}}
                <div class="p-4 rounded-xl bg-white dark:bg-navy-900/90 border border-slate-200 dark:border-slate-800 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Passenger Movement
                        </span>
                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 font-bold">
                            Pax
                        </span>
                    </div>
                    <div class="text-2xl font-black mt-1"
                         :class="currentTargetComparison?.passenger?.is_positive ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400'"
                         x-text="currentTargetComparison?.passenger?.percentage_fmt || '—'">
                    </div>
                    <div class="text-xs font-mono font-bold mt-1 text-slate-700 dark:text-slate-300"
                         x-text="'Change: ' + (currentTargetComparison?.passenger?.change_fmt || '—')">
                    </div>
                    <div class="text-[10px] font-mono text-slate-400 mt-1">
                        Baseline (<span x-text="baselineShortLabel"></span>): <span x-text="formatNumber(currentTargetComparison?.passenger?.baseline)"></span> &rarr;
                        Target (<span x-text="currentTargetPeriod?.short_label"></span>): <span x-text="formatNumber(currentTargetComparison?.passenger?.current)"></span>
                    </div>
                </div>

                {{-- Aircraft Card --}}
                <div class="p-4 rounded-xl bg-white dark:bg-navy-900/90 border border-slate-200 dark:border-slate-800 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Aircraft Movement
                        </span>
                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 font-bold">
                            Movements
                        </span>
                    </div>
                    <div class="text-2xl font-black mt-1"
                         :class="currentTargetComparison?.aircraft?.is_positive ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400'"
                         x-text="currentTargetComparison?.aircraft?.percentage_fmt || '—'">
                    </div>
                    <div class="text-xs font-mono font-bold mt-1 text-slate-700 dark:text-slate-300"
                         x-text="'Change: ' + (currentTargetComparison?.aircraft?.change_fmt || '—')">
                    </div>
                    <div class="text-[10px] font-mono text-slate-400 mt-1">
                        Baseline (<span x-text="baselineShortLabel"></span>): <span x-text="formatNumber(currentTargetComparison?.aircraft?.baseline)"></span> &rarr;
                        Target (<span x-text="currentTargetPeriod?.short_label"></span>): <span x-text="formatNumber(currentTargetComparison?.aircraft?.current)"></span>
                    </div>
                </div>

                {{-- Cargo Card --}}
                <div class="p-4 rounded-xl bg-white dark:bg-navy-900/90 border border-slate-200 dark:border-slate-800 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                              x-text="'Cargo Movement (' + comparisonData.cargo_unit + ')'">
                        </span>
                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 font-bold"
                              x-text="comparisonData.cargo_unit">
                        </span>
                    </div>
                    <div class="text-2xl font-black mt-1"
                         :class="currentTargetComparison?.cargo?.is_positive ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400'"
                         x-text="currentTargetComparison?.cargo?.percentage_fmt || '—'">
                    </div>
                    <div class="text-xs font-mono font-bold mt-1 text-slate-700 dark:text-slate-300"
                         x-text="'Change: ' + (currentTargetComparison?.cargo?.change_fmt || '—')">
                    </div>
                    <div class="text-[10px] font-mono text-slate-400 mt-1">
                        Baseline (<span x-text="baselineShortLabel"></span>): <span x-text="formatNumber(currentTargetComparison?.cargo?.baseline)"></span> &rarr;
                        Target (<span x-text="currentTargetPeriod?.short_label"></span>): <span x-text="formatNumber(currentTargetComparison?.cargo?.current)"></span>
                    </div>
                </div>
            </div>

            {{-- Multi-Period Baseline Comparison Table --}}
            <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden mt-3">
                <div class="p-3 bg-slate-50 dark:bg-navy-900 text-xs font-bold text-slate-700 dark:text-slate-300 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <span>MULTI-PERIOD BASELINE BREAKDOWN TABLE (<span x-text="targetPeriods.length"></span> Comparisons vs Baseline)</span>
                    <span class="text-[11px] font-normal text-slate-500">Baseline values remain independent from historical filters</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs font-sans border-collapse">
                        <thead class="bg-slate-100/70 dark:bg-navy-950 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Target Period</th>
                                <th class="px-4 py-2.5 text-right">Passenger Movement (Pax)</th>
                                <th class="px-4 py-2.5 text-right">Passenger Growth</th>
                                <th class="px-4 py-2.5 text-right">Aircraft Movements</th>
                                <th class="px-4 py-2.5 text-right">Aircraft Growth</th>
                                <th class="px-4 py-2.5 text-right" x-text="'Cargo (' + comparisonData.cargo_unit + ')'"></th>
                                <th class="px-4 py-2.5 text-right">Cargo Growth</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono text-xs">
                            <template x-for="comp in comparisonData.baseline_comparison.comparisons" :key="comp.target_key">
                                <tr :class="activeTargetKey === comp.target_key ? 'bg-aviation-50/50 dark:bg-navy-800/50' : 'hover:bg-slate-50/40 dark:hover:bg-navy-800/20'"
                                    @click="activeTargetKey = comp.target_key"
                                    class="cursor-pointer transition">
                                    <td class="px-4 py-2.5 font-bold text-slate-900 dark:text-white">
                                        <div x-text="comp.target_period.label + ' (' + comp.target_period.short_label + ')'"></div>
                                        <div class="text-[10px] text-slate-400 font-normal" x-text="comp.target_period.display_range"></div>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-bold text-slate-800 dark:text-slate-200" x-text="formatNumber(comp.passenger.current)"></td>
                                    <td class="px-4 py-2.5 text-right font-black" :class="comp.passenger.is_positive ? 'text-emerald-600' : 'text-red-500'" x-text="comp.passenger.percentage_fmt"></td>
                                    <td class="px-4 py-2.5 text-right font-bold text-slate-800 dark:text-slate-200" x-text="formatNumber(comp.aircraft.current)"></td>
                                    <td class="px-4 py-2.5 text-right font-black" :class="comp.aircraft.is_positive ? 'text-emerald-600' : 'text-red-500'" x-text="comp.aircraft.percentage_fmt"></td>
                                    <td class="px-4 py-2.5 text-right font-bold text-slate-800 dark:text-slate-200" x-text="formatNumber(comp.cargo.current)"></td>
                                    <td class="px-4 py-2.5 text-right font-black" :class="comp.cargo.is_positive ? 'text-emerald-600' : 'text-red-500'" x-text="comp.cargo.percentage_fmt"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════════
             SECTION 1: KINERJA OPERASIONAL BANDARA (TREND LINES + POINTS)
             ═══════════════════════════════════════════════════════════════════ --}}
        <section class="space-y-4">
            <div class="border-b border-slate-200/80 dark:border-slate-800/80 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400 font-mono">
                        SECTION 1 &bull; OPERATIONAL PERFORMANCE
                    </span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900">
                        Trend Analysis (Lines + Points)
                    </span>
                </div>
                <h2 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5">
                    KINERJA OPERASIONAL BANDARA
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                    {{ $comparison['airport_name'] }} ({{ $comparison['airport_code'] }}) &mdash; Progression across uploaded periods on independent scales.
                    <span class="text-amber-600 dark:text-amber-400 font-bold">&bull; Subtle marker denotes Baseline period.</span>
                </p>
            </div>

            {{-- 3 Major Trend Line Cards --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Trend Metric 1: Pergerakan Penumpang --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 font-mono flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                                <span>1. PERGERAKAN PENUMPANG</span>
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold">
                                Pax Scale
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400 font-medium mb-3">
                            Trend line connected across all uploaded periods
                        </div>

                        {{-- Line Chart Canvas --}}
                        <div class="h-48 relative">
                            <canvas id="chart-trend-passenger"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Trend Metric 2: Pergerakan Pesawat --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-mono flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                                <span>2. PERGERAKAN PESAWAT</span>
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-bold">
                                Movements Scale
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400 font-medium mb-3">
                            Trend line connected across all uploaded periods
                        </div>

                        {{-- Line Chart Canvas --}}
                        <div class="h-48 relative">
                            <canvas id="chart-trend-aircraft"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Trend Metric 3: Pergerakan Kargo --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 font-mono flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-600"></span>
                                <span>3. PERGERAKAN KARGO</span>
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 font-bold"
                                  x-text="comparisonData.cargo_unit + ' Scale'">
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400 font-medium mb-3">
                            Trend line connected across all uploaded periods
                        </div>

                        {{-- Line Chart Canvas --}}
                        <div class="h-48 relative">
                            <canvas id="chart-trend-cargo"></canvas>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════════
             SECTION 2: DATA PERGERAKAN HISTORIS (BAR CHARTS & INDEPENDENT FILTERS)
             ═══════════════════════════════════════════════════════════════════ --}}
        <section class="space-y-4">
            <div class="border-b border-slate-200/80 dark:border-slate-800/80 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400 font-mono">
                        SECTION 2 &bull; HISTORICAL TRAFFIC DISTRIBUTION
                    </span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-900">
                        Period Distribution (Bar Charts)
                    </span>
                </div>
                <h2 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5">
                    DATA PERGERAKAN HISTORIS
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                    Filtered Period-by-Period Comparison &mdash; <span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="historicalSubtitle"></span>
                </p>
            </div>

            {{-- ══ INDEPENDENT HISTORICAL FILTER BAR ═══════════════════════════ --}}
            <div class="glass-card p-4 rounded-xl border border-slate-200/80 dark:border-slate-800/80 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-2xs">
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Scope Filter (ALL / DOM / INT) --}}
                    <div class="flex items-center gap-1.5">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Scope:</span>
                        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 bg-slate-100 dark:bg-navy-800">
                            <button type="button" @click="setHistoricalScope('ALL')"
                                    :class="historicalScope === 'ALL' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                    class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">ALL</button>
                            <button type="button" @click="setHistoricalScope('DOM')"
                                    :class="historicalScope === 'DOM' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                    class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">DOMESTIK</button>
                            <button type="button" @click="setHistoricalScope('INT')"
                                    :class="historicalScope === 'INT' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                    class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">INTERNASIONAL</button>
                        </div>
                    </div>

                    {{-- Direction Filter (ALL / ARRIVAL / DEPARTURE) --}}
                    <div class="flex items-center gap-1.5">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Direction:</span>
                        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 bg-slate-100 dark:bg-navy-800">
                            <button type="button" @click="setHistoricalDirection('ALL')"
                                    :class="historicalDirection === 'ALL' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                    class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">ALL</button>
                            <button type="button" @click="setHistoricalDirection('ARRIVAL')"
                                    :class="historicalDirection === 'ARRIVAL' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                    class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">ARRIVAL</button>
                            <button type="button" @click="setHistoricalDirection('DEPARTURE')"
                                    :class="historicalDirection === 'DEPARTURE' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-bold'"
                                    class="px-2.5 py-1 text-xs rounded-md transition cursor-pointer">DEPARTURE</button>
                        </div>
                    </div>
                </div>

                {{-- Reset Button --}}
                <div class="flex items-center gap-2">
                    <button type="button" @click="resetHistoricalFilters()"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 border border-slate-200 dark:border-slate-700 transition cursor-pointer flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset Historical Filters</span>
                    </button>
                </div>
            </div>

            {{-- Active Filter Badges Display --}}
            <div class="flex items-center gap-2 text-xs font-mono">
                <span class="text-slate-400 font-bold uppercase text-[10px]">ACTIVE HISTORICAL FILTERS:</span>
                <template x-if="historicalScope === 'ALL' && historicalDirection === 'ALL'">
                    <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300 font-bold">
                        ALL DATA
                    </span>
                </template>
                <template x-if="historicalScope !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-md bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 flex items-center gap-1 font-bold">
                        <span x-text="historicalScope === 'DOM' ? 'Domestic' : 'International'"></span>
                        <button type="button" @click="setHistoricalScope('ALL')" class="hover:text-red-500">&times;</button>
                    </span>
                </template>
                <template x-if="historicalDirection !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-md bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 flex items-center gap-1 font-bold">
                        <span x-text="historicalDirection === 'ARRIVAL' ? 'Arrival' : 'Departure'"></span>
                        <button type="button" @click="setHistoricalDirection('ALL')" class="hover:text-red-500">&times;</button>
                    </span>
                </template>
            </div>

            {{-- 3 Historical Bar Charts --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Bar Chart 1: Passenger --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded bg-blue-600"></span>
                            <span>PERGERAKAN PENUMPANG</span>
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">Pax (Bars)</span>
                    </div>
                    <div class="h-60 relative">
                        <canvas id="chart-hist-passenger"></canvas>
                    </div>
                </div>

                {{-- Bar Chart 2: Aircraft --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded bg-emerald-600"></span>
                            <span>PERGERAKAN PESAWAT</span>
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">Movements (Bars)</span>
                    </div>
                    <div class="h-60 relative">
                        <canvas id="chart-hist-aircraft"></canvas>
                    </div>
                </div>

                {{-- Bar Chart 3: Cargo --}}
                <div class="glass-card p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded bg-amber-600"></span>
                            <span>PERGERAKAN KARGO</span>
                        </span>
                        <span class="text-[10px] font-mono text-slate-400" x-text="comparisonData.cargo_unit + ' (Bars)'"></span>
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
                        <div class="text-[10px] font-mono uppercase tracking-wider text-aviation-600 dark:text-aviation-400 font-bold">Tabular Movements</div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">HISTORICAL OPERATIONAL MOVEMENTS SUMMARY</h3>
                    </div>
                    <span class="text-xs font-mono text-slate-500">
                        Active Filter: <strong class="text-slate-800 dark:text-slate-200" x-text="historicalSubtitle"></strong>
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
                                <template x-for="p in periodsList" :key="p.key">
                                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-200"
                                        x-text="formatNumber(getHistoricalValue('passenger', p.key))">
                                    </td>
                                </template>
                            </tr>

                            {{-- Aircraft Row --}}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-navy-800/40 transition">
                                <td class="px-4 py-3 font-bold font-sans text-slate-900 dark:text-white flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                                    <span>Pergerakan Pesawat</span>
                                </td>
                                <td class="px-3 py-3 text-center text-slate-400 font-mono text-[11px]">Movements</td>
                                <template x-for="p in periodsList" :key="p.key">
                                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-200"
                                        x-text="formatNumber(getHistoricalValue('aircraft', p.key))">
                                    </td>
                                </template>
                            </tr>

                            {{-- Cargo Row --}}
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-navy-800/40 transition">
                                <td class="px-4 py-3 font-bold font-sans text-slate-900 dark:text-white flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-600"></span>
                                    <span>Pergerakan Kargo</span>
                                </td>
                                <td class="px-3 py-3 text-center text-slate-400 font-mono text-[11px]" x-text="comparisonData.cargo_unit"></td>
                                <template x-for="p in periodsList" :key="p.key">
                                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-200"
                                        x-text="formatNumber(getHistoricalValue('cargo', p.key))">
                                    </td>
                                </template>
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
        baselinePeriodKey: @json($comparison['baseline_period_key']),
        activeTargetKey: '',
        historicalScope: @json($filters['hist_scope'] ?? 'ALL'),
        historicalDirection: @json($filters['hist_direction'] ?? 'ALL'),
        comparisonData: @json($comparison),

        trendCharts: {},
        historicalCharts: {},

        get periodsList() {
            return Object.values(this.comparisonData.periods || {});
        },

        get baselinePeriod() {
            return this.comparisonData.periods[this.baselinePeriodKey] || this.periodsList[0];
        },

        get baselinePeriodLabel() {
            return this.baselinePeriod ? (this.baselinePeriod.label + ' (' + this.baselinePeriod.short_label + ')') : 'P1';
        },

        get baselineShortLabel() {
            return this.baselinePeriod ? this.baselinePeriod.short_label : '';
        },

        get targetPeriods() {
            return this.periodsList.filter(p => p.key !== this.baselinePeriodKey);
        },

        get currentTargetPeriod() {
            return this.comparisonData.periods[this.activeTargetKey] || this.targetPeriods[0];
        },

        get currentTargetComparison() {
            const comps = this.comparisonData.baseline_comparison?.comparisons || [];
            return comps.find(c => c.target_key === this.activeTargetKey) || comps[0] || null;
        },

        get historicalSubtitle() {
            if (this.historicalScope === 'DOM') {
                if (this.historicalDirection === 'ARRIVAL') return 'Domestic arrival movements';
                if (this.historicalDirection === 'DEPARTURE') return 'Domestic departure movements';
                return 'Domestic movements (Arrival & Departure)';
            } else if (this.historicalScope === 'INT') {
                if (this.historicalDirection === 'ARRIVAL') return 'International arrival movements';
                if (this.historicalDirection === 'DEPARTURE') return 'International departure movements';
                return 'International movements (Arrival & Departure)';
            } else {
                if (this.historicalDirection === 'ARRIVAL') return 'All traffic arrival movements';
                if (this.historicalDirection === 'DEPARTURE') return 'All traffic departure movements';
                return 'All traffic movements';
            }
        },

        formatNumber(v) {
            if (v === null || v === undefined) return '0';
            return Number(v).toLocaleString();
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
            this.renderAllCharts();
        },

        changeBaseline(newKey) {
            this.baselinePeriodKey = newKey;
            const url = new URL(window.location.href);
            url.searchParams.set('baseline', newKey);
            url.searchParams.set('hist_scope', this.historicalScope);
            url.searchParams.set('hist_direction', this.historicalDirection);
            window.location.href = url.toString();
        },

        setHistoricalScope(scope) {
            this.historicalScope = scope;
            this.updateHistoricalCharts();
        },

        setHistoricalDirection(dir) {
            this.historicalDirection = dir;
            this.updateHistoricalCharts();
        },

        resetHistoricalFilters() {
            this.historicalScope = 'ALL';
            this.historicalDirection = 'ALL';
            this.updateHistoricalCharts();
        },

        getHistoricalValue(metricKey, periodKey) {
            const p = this.comparisonData.periods[periodKey];
            if (!p || !p.breakdown || !p.breakdown[metricKey]) return 0;
            const b = p.breakdown[metricKey];
            const scope = this.historicalScope;
            const dir = this.historicalDirection;

            if (scope === 'DOM') {
                if (dir === 'ARRIVAL') return b.dom_arr;
                if (dir === 'DEPARTURE') return b.dom_dep;
                return b.dom_tot;
            } else if (scope === 'INT') {
                if (dir === 'ARRIVAL') return b.int_arr;
                if (dir === 'DEPARTURE') return b.int_dep;
                return b.int_tot;
            } else {
                if (dir === 'ARRIVAL') return b.tot_arr;
                if (dir === 'DEPARTURE') return b.tot_dep;
                return b.total;
            }
        },

        getHistoricalDatasets(metricKey) {
            const periods = this.periodsList;
            const scope = this.historicalScope;
            const dir = this.historicalDirection;

            if (scope === 'ALL') {
                const domVals = periods.map(p => {
                    const b = p.breakdown?.[metricKey] || {};
                    if (dir === 'ARRIVAL') return b.dom_arr || 0;
                    if (dir === 'DEPARTURE') return b.dom_dep || 0;
                    return b.dom_tot || 0;
                });
                const intVals = periods.map(p => {
                    const b = p.breakdown?.[metricKey] || {};
                    if (dir === 'ARRIVAL') return b.int_arr || 0;
                    if (dir === 'DEPARTURE') return b.int_dep || 0;
                    return b.int_tot || 0;
                });
                return [
                    { label: 'Domestic', backgroundColor: '#2563eb', data: domVals, borderRadius: 4, maxBarThickness: 32 },
                    { label: 'International', backgroundColor: '#7c3aed', data: intVals, borderRadius: 4, maxBarThickness: 32 }
                ];
            } else if (scope === 'DOM') {
                const domVals = periods.map(p => {
                    const b = p.breakdown?.[metricKey] || {};
                    if (dir === 'ARRIVAL') return b.dom_arr || 0;
                    if (dir === 'DEPARTURE') return b.dom_dep || 0;
                    return b.dom_tot || 0;
                });
                return [
                    { label: 'Domestic', backgroundColor: '#2563eb', data: domVals, borderRadius: 4, maxBarThickness: 44 }
                ];
            } else {
                const intVals = periods.map(p => {
                    const b = p.breakdown?.[metricKey] || {};
                    if (dir === 'ARRIVAL') return b.int_arr || 0;
                    if (dir === 'DEPARTURE') return b.int_dep || 0;
                    return b.int_tot || 0;
                });
                return [
                    { label: 'International', backgroundColor: '#7c3aed', data: intVals, borderRadius: 4, maxBarThickness: 44 }
                ];
            }
        },

        renderTrendCharts() {
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? 'rgba(51, 65, 85, 0.3)' : 'rgba(226, 232, 240, 0.6)';

            const periods = this.periodsList;
            const labels = periods.map(p => p.short_label || p.label);

            const buildLineChart = (canvasId, metricKey, label, strokeColor, unit) => {
                const ctx = document.getElementById(canvasId);
                if (!ctx) return;
                if (this.trendCharts[canvasId]) {
                    this.trendCharts[canvasId].destroy();
                }

                const trendPts = this.comparisonData.operational_trend[metricKey] || [];
                const values = trendPts.map(pt => pt.value);
                const baselineIdx = periods.findIndex(p => p.key === this.baselinePeriodKey);

                const pointRadii = values.map((_, i) => (i === baselineIdx ? 8 : 5));
                const pointBorderColors = values.map((_, i) => (i === baselineIdx ? '#f59e0b' : strokeColor));
                const pointBgColors = values.map((_, i) => (i === baselineIdx ? '#fef3c7' : '#ffffff'));
                const pointBorderWidths = values.map((_, i) => (i === baselineIdx ? 3 : 2));

                this.trendCharts[canvasId] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: label,
                            data: values,
                            borderColor: strokeColor,
                            backgroundColor: strokeColor,
                            borderWidth: 2.5,
                            fill: false,
                            tension: 0.2,
                            pointRadius: pointRadii,
                            pointHoverRadius: 9,
                            pointBackgroundColor: pointBgColors,
                            pointBorderColor: pointBorderColors,
                            pointBorderWidth: pointBorderWidths,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: (items) => {
                                        const idx = items[0].dataIndex;
                                        return periods[idx].label + ' (' + periods[idx].short_label + ') — ' + periods[idx].display_range;
                                    },
                                    label: (item) => {
                                        const isBase = periods[item.dataIndex].key === this.baselinePeriodKey;
                                        return label + ': ' + Number(item.raw).toLocaleString() + ' ' + unit + (isBase ? ' [BASELINE]' : '');
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

            buildLineChart('chart-trend-passenger', 'passenger', 'Passenger Movement', '#2563eb', 'Pax');
            buildLineChart('chart-trend-aircraft', 'aircraft', 'Aircraft Movement', '#059669', 'Movements');
            buildLineChart('chart-trend-cargo', 'cargo', 'Cargo Movement', '#d97706', this.comparisonData.cargo_unit);
        },

        renderHistoricalCharts() {
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? 'rgba(51, 65, 85, 0.3)' : 'rgba(226, 232, 240, 0.6)';

            const periods = this.periodsList;
            const labels = periods.map(p => p.short_label || p.label);

            const buildBarChart = (canvasId, metricKey, unit) => {
                const ctx = document.getElementById(canvasId);
                if (!ctx) return;
                if (this.historicalCharts[canvasId]) {
                    this.historicalCharts[canvasId].destroy();
                }

                const datasets = this.getHistoricalDatasets(metricKey);

                this.historicalCharts[canvasId] = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: datasets.length > 1,
                                position: 'top',
                                labels: {
                                    color: textColor,
                                    font: { family: 'JetBrains Mono', size: 9 },
                                    boxWidth: 10
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    title: (items) => {
                                        const idx = items[0].dataIndex;
                                        return periods[idx].label + ' (' + periods[idx].short_label + ')';
                                    },
                                    label: (item) => {
                                        return item.dataset.label + ': ' + Number(item.raw).toLocaleString() + ' ' + unit;
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

            buildBarChart('chart-hist-passenger', 'passenger', 'Pax');
            buildBarChart('chart-hist-aircraft', 'aircraft', 'Movements');
            buildBarChart('chart-hist-cargo', 'cargo', this.comparisonData.cargo_unit);
        },

        updateHistoricalCharts() {
            const configs = [
                { id: 'chart-hist-passenger', key: 'passenger', unit: 'Pax' },
                { id: 'chart-hist-aircraft',  key: 'aircraft',  unit: 'Movements' },
                { id: 'chart-hist-cargo',     key: 'cargo',     unit: this.comparisonData.cargo_unit },
            ];

            configs.forEach(c => {
                const chart = this.historicalCharts[c.id];
                if (chart) {
                    const newDatasets = this.getHistoricalDatasets(c.key);
                    chart.data.datasets = newDatasets;
                    chart.options.plugins.legend.display = newDatasets.length > 1;
                    chart.update();
                }
            });
        },

        renderAllCharts() {
            this.renderTrendCharts();
            this.renderHistoricalCharts();
        },

        init() {
            if (this.targetPeriods.length > 0) {
                this.activeTargetKey = this.targetPeriods[0].key;
            }
            this.$nextTick(() => {
                this.renderAllCharts();
            });
        }
    };
}
</script>
@endpush
