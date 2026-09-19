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
             SECTION 1: KINERJA OPERASIONAL BANDARA (ONE COMBINED OPERATIONAL CHART)
             ═══════════════════════════════════════════════════════════════════ --}}
        <section class="space-y-4">
            <div class="border-b border-slate-200/80 dark:border-slate-800/80 pb-3 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400 font-mono">
                            SECTION 1 &bull; OPERATIONAL PERFORMANCE
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900">
                            Combined Operational Trend
                        </span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white mt-0.5">
                        KINERJA OPERASIONAL BANDARA
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                        {{ $comparison['airport_name'] }} ({{ $comparison['airport_code'] }}) &mdash; Passenger, Aircraft &amp; Cargo Movement Trend
                    </p>
                </div>
            </div>

            {{-- Empty state if periods is empty --}}
            <template x-if="periodsList.length === 0">
                <div class="glass-card p-12 text-center rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md">
                    <div class="text-3xl mb-2">📊</div>
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300">NO DATA AVAILABLE</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Please upload or select valid DAU-02 reports to display operational comparison.</p>
                </div>
            </template>

            {{-- ONE Large Combined Chart Card --}}
            <template x-if="periodsList.length > 0">
                <div class="glass-card p-5 sm:p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md">
                    {{-- Chart Header: Title, Subtitle, and Compact Legend Row --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3 pb-3 border-b border-slate-100 dark:border-slate-800/60">
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white font-mono tracking-tight flex items-center gap-2">
                                <span>PAX AND FLIGHT TREND</span>
                                <span class="sr-only">Pax and Flight Trend</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-md bg-slate-100 dark:bg-navy-800 text-slate-500 dark:text-slate-400 font-normal">
                                    3 Independent Dynamic Scales
                                </span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
                                Passenger &bull; Aircraft &bull; Cargo
                            </p>
                        </div>

                        {{-- Compact Horizontal Legend Row --}}
                        <div class="flex items-center gap-4 text-xs font-mono bg-slate-50/80 dark:bg-navy-900/60 px-3.5 py-1.5 rounded-xl border border-slate-200/80 dark:border-slate-800/80">
                            <span class="inline-flex items-center gap-1.5 font-bold text-blue-600 dark:text-blue-400">
                                <span class="w-3 h-3 rounded-xs bg-blue-600 inline-block shadow-2xs"></span> Passenger
                            </span>
                            <span class="inline-flex items-center gap-1.5 font-bold text-emerald-600 dark:text-emerald-400">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 inline-block shadow-2xs"></span> Aircraft
                            </span>
                            <span class="inline-flex items-center gap-1.5 font-bold text-amber-600 dark:text-amber-400">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-600 inline-block shadow-2xs"></span> Cargo
                            </span>
                        </div>
                    </div>

                    {{-- Executive Summary Metric Strip (Syncs with latest/hovered period) --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-4" x-show="activePeriodMetric">
                        {{-- Period Status Chip --}}
                        <div class="p-2.5 rounded-xl bg-slate-50/80 dark:bg-navy-900/40 border border-slate-200/70 dark:border-slate-800/70 flex flex-col justify-center">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono">
                                    <span x-text="activePeriodMetric?.isHovered ? 'HOVERED PERIOD' : 'LATEST PERIOD'"></span>
                                </span>
                                <template x-if="activePeriodMetric?.isBaseline">
                                    <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 font-bold">BASELINE</span>
                                </template>
                            </div>
                            <div class="text-sm font-black text-slate-800 dark:text-slate-100 font-mono mt-0.5" x-text="activePeriodMetric?.label"></div>
                        </div>

                        {{-- Passenger Metric Chip --}}
                        <div class="p-2.5 rounded-xl bg-blue-50/40 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/40 flex flex-col justify-center">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 font-mono">PASSENGER</span>
                            <div class="text-sm font-black text-blue-700 dark:text-blue-300 font-mono mt-0.5">
                                <span x-text="formatMetricCompact(activePeriodMetric?.passenger)"></span> <span class="text-[11px] font-semibold text-blue-500">Pax</span>
                            </div>
                        </div>

                        {{-- Aircraft Metric Chip --}}
                        <div class="p-2.5 rounded-xl bg-emerald-50/40 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 flex flex-col justify-center">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-mono">AIRCRAFT</span>
                            <div class="text-sm font-black text-emerald-700 dark:text-emerald-300 font-mono mt-0.5">
                                <span x-text="formatMetricCompact(activePeriodMetric?.aircraft)"></span> <span class="text-[11px] font-semibold text-emerald-500">A/C</span>
                            </div>
                        </div>

                        {{-- Cargo Metric Chip --}}
                        <div class="p-2.5 rounded-xl bg-amber-50/40 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/40 flex flex-col justify-center">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 font-mono">CARGO</span>
                            <div class="text-sm font-black text-amber-700 dark:text-amber-300 font-mono mt-0.5">
                                <span x-text="formatMetricCompact(activePeriodMetric?.cargo)"></span> <span class="text-[11px] font-semibold text-amber-500" x-text="comparisonData.cargo_unit || 'Kg'"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Unified Chart Canvas Container (Full Width, ~440-460px height) --}}
                    <div class="h-96 sm:h-[450px] w-full relative" @mouseleave="hoveredPeriodIndex = null">
                        <canvas id="chart-operational-combined"></canvas>
                    </div>
                </div>
            </template>
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
const _dauCharts = { combined: null, trend: {}, historical: {} };

function deepFreeze(obj) {
    if (obj && typeof obj === 'object' && !Object.isFrozen(obj)) {
        Object.freeze(obj);
        for (const key of Object.keys(obj)) {
            deepFreeze(obj[key]);
        }
    }
    return obj;
}

const _comparisonData = deepFreeze(@json($comparison));

function dauComparisonDashboard() {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
        reportIdsStr: @json($reportIdsStr),
        baselinePeriodKey: @json($comparison['baseline_period_key']),
        activeTargetKey: '',
        historicalScope: @json($filters['hist_scope'] ?? 'ALL'),
        historicalDirection: @json($filters['hist_direction'] ?? 'ALL'),
        comparisonData: _comparisonData,
        hoveredPeriodIndex: null,

        get periodsList() {
            return Object.values(this.comparisonData.periods || {});
        },

        get activePeriodMetric() {
            const periods = this.periodsList;
            if (!periods || periods.length === 0) return null;
            const idx = (this.hoveredPeriodIndex !== null && this.hoveredPeriodIndex >= 0 && this.hoveredPeriodIndex < periods.length)
                ? this.hoveredPeriodIndex
                : (periods.length - 1);
            const p = periods[idx];
            if (!p) return null;

            const paxTrend = this.comparisonData.operational_trend?.passenger || [];
            const acTrend  = this.comparisonData.operational_trend?.aircraft || [];
            const cargoTrend = this.comparisonData.operational_trend?.cargo || [];

            const pVal = paxTrend[idx]?.value !== undefined ? paxTrend[idx].value : (p.metrics?.passenger ?? 0);
            const aVal = acTrend[idx]?.value !== undefined ? acTrend[idx].value : (p.metrics?.aircraft ?? 0);
            const cVal = cargoTrend[idx]?.value !== undefined ? cargoTrend[idx].value : (p.metrics?.cargo ?? 0);

            return {
                label: p.short_label || p.label,
                fullLabel: p.label,
                isLatest: (idx === periods.length - 1),
                isHovered: (this.hoveredPeriodIndex !== null),
                isBaseline: (p.key === this.baselinePeriodKey),
                passenger: Number(pVal || 0),
                aircraft: Number(aVal || 0),
                cargo: Number(cVal || 0),
            };
        },

        formatMetricCompact(v) {
            if (v === null || v === undefined) return '0';
            if (v >= 1000000000) return (v / 1000000000).toFixed(2) + 'B';
            if (v >= 1000000) return (v / 1000000).toFixed(2) + 'M';
            if (v >= 1000) return (v / 1000).toFixed(2) + 'K';
            return Number(v).toLocaleString();
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
                    return b.int_tot;
                });
                return [
                    { label: 'International', backgroundColor: '#7c3aed', data: intVals, borderRadius: 4, maxBarThickness: 44 }
                ];
            }
        },

        renderTrendCharts() {
            const canvasId = 'chart-operational-combined';
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            const existing = Chart.getChart(canvasId) || _dauCharts.combined;
            if (existing) {
                existing.destroy();
                _dauCharts.combined = null;
            }

            const periods = this.periodsList;
            if (!periods || periods.length === 0) return;

            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? 'rgba(51, 65, 85, 0.25)' : 'rgba(241, 245, 249, 0.9)';

            const labels = periods.map(p => p.short_label || p.label);
            const baselineIdx = periods.findIndex(p => p.key === this.baselinePeriodKey);

            const paxTrend = this.comparisonData.operational_trend?.passenger || [];
            const acTrend  = this.comparisonData.operational_trend?.aircraft || [];
            const cargoTrend = this.comparisonData.operational_trend?.cargo || [];

            // Unified period metrics mapping
            const periodMetrics = periods.map((p, i) => {
                const pVal = paxTrend[i]?.value !== undefined ? paxTrend[i].value : (p.metrics?.passenger ?? null);
                const aVal = acTrend[i]?.value !== undefined ? acTrend[i].value : (p.metrics?.aircraft ?? null);
                const cVal = cargoTrend[i]?.value !== undefined ? cargoTrend[i].value : (p.metrics?.cargo ?? null);
                return {
                    period: p.short_label || p.label,
                    passenger: pVal !== null && pVal !== undefined ? Number(pVal) : null,
                    aircraft: aVal !== null && aVal !== undefined ? Number(aVal) : null,
                    cargo: cVal !== null && cVal !== undefined ? Number(cVal) : null,
                };
            });

            const paxValues = periodMetrics.map(m => m.passenger);
            const acValues = periodMetrics.map(m => m.aircraft);
            const cargoValues = periodMetrics.map(m => m.cargo);

            // Passenger Bar Styling (Primary series: solid blue; baseline marked by subtle amber border outline)
            const paxBarBg = paxValues.map(() => isDark ? 'rgba(37, 99, 235, 0.85)' : 'rgba(37, 99, 235, 0.9)');
            const paxBarBorder = paxValues.map((_, i) => i === baselineIdx ? '#f59e0b' : 'transparent');
            const paxBarBorderWidth = paxValues.map((_, i) => i === baselineIdx ? 2 : 0);

            const cargoUnit = this.comparisonData.cargo_unit || 'Kg';

            const formatShort = (v) => {
                if (v === null || v === undefined) return '0';
                if (v >= 1000000000) return (v / 1000000000).toFixed(1) + 'B';
                if (v >= 1000000) return (v / 1000000).toFixed(1) + 'M';
                if (v >= 1000) return (v / 1000).toFixed(0) + 'K';
                return Number(v).toLocaleString();
            };

            // Custom baseline vertical line plugin: subtle 1px dashed marker that never obscures data points
            const baselineMarkerPlugin = {
                id: 'baselineMarker',
                afterDraw: (chart) => {
                    if (baselineIdx === -1) return;
                    const meta = chart.getDatasetMeta(0);
                    if (!meta || !meta.data || !meta.data[baselineIdx]) return;
                    const bar = meta.data[baselineIdx];
                    const chartCtx = chart.ctx;
                    const x = bar.x;
                    const top = chart.chartArea.top;
                    const bottom = chart.chartArea.bottom;

                    chartCtx.save();
                    chartCtx.beginPath();
                    chartCtx.setLineDash([3, 3]);
                    chartCtx.strokeStyle = isDark ? 'rgba(245, 158, 11, 0.65)' : 'rgba(217, 119, 6, 0.6)';
                    chartCtx.lineWidth = 1;
                    chartCtx.moveTo(x, top);
                    chartCtx.lineTo(x, bottom);
                    chartCtx.stroke();

                    chartCtx.fillStyle = isDark ? '#fbbf24' : '#d97706';
                    chartCtx.font = 'bold 8px "JetBrains Mono", monospace';
                    chartCtx.textAlign = 'center';
                    chartCtx.fillText('BASELINE', x, top - 4);
                    chartCtx.restore();
                }
            };

            const self = this;

            _dauCharts.combined = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Passenger Movement',
                            data: paxValues,
                            yAxisID: 'yPassenger',
                            backgroundColor: paxBarBg,
                            borderColor: paxBarBorder,
                            borderWidth: paxBarBorderWidth,
                            hoverBackgroundColor: '#1d4ed8',
                            borderRadius: 4,
                            maxBarThickness: 42,
                            order: 3
                        },
                        {
                            type: 'line',
                            label: 'Aircraft Movement',
                            data: acValues,
                            yAxisID: 'yAircraft',
                            borderColor: '#059669',
                            backgroundColor: '#059669',
                            borderWidth: 2.5,
                            fill: false,
                            tension: 0.12,
                            spanGaps: true,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#059669',
                            pointBorderWidth: 2.5,
                            pointHoverBackgroundColor: '#059669',
                            pointHoverBorderColor: '#ffffff',
                            pointHoverBorderWidth: 2,
                            order: 1
                        },
                        {
                            type: 'line',
                            label: 'Cargo Movement',
                            data: cargoValues,
                            yAxisID: 'yCargo',
                            borderColor: '#ea580c',
                            backgroundColor: '#ea580c',
                            borderWidth: 2.5,
                            fill: false,
                            tension: 0.12,
                            spanGaps: true,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#ea580c',
                            pointBorderWidth: 2.5,
                            pointHoverBackgroundColor: '#ea580c',
                            pointHoverBorderColor: '#ffffff',
                            pointHoverBorderWidth: 2,
                            order: 2
                        }
                    ]
                },
                plugins: [baselineMarkerPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 16,
                            bottom: 6,
                            left: 4,
                            right: 6
                        }
                    },
                    onHover: (event, activeElements) => {
                        if (activeElements && activeElements.length > 0) {
                            const idx = activeElements[0].index;
                            if (self.hoveredPeriodIndex !== idx) {
                                self.hoveredPeriodIndex = idx;
                            }
                        } else {
                            if (self.hoveredPeriodIndex !== null) {
                                self.hoveredPeriodIndex = null;
                            }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: false // Using compact horizontal HTML legend in card header to eliminate overlap
                        },
                        tooltip: {
                            backgroundColor: isDark ? 'rgba(15, 23, 42, 0.96)' : 'rgba(15, 23, 42, 0.94)',
                            padding: { top: 8, bottom: 8, left: 12, right: 12 },
                            cornerRadius: 8,
                            borderWidth: 1,
                            borderColor: isDark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(255, 255, 255, 0.1)',
                            titleFont: { family: 'JetBrains Mono', size: 11, weight: 'bold' },
                            titleColor: '#ffffff',
                            titleSpacing: 6,
                            bodyFont: { family: 'JetBrains Mono', size: 10.5 },
                            bodyColor: '#e2e8f0',
                            bodySpacing: 4,
                            boxWidth: 8,
                            boxHeight: 8,
                            boxPadding: 4,
                            usePointStyle: true,
                            multiKeyBackground: 'transparent',
                            callbacks: {
                                title: (items) => {
                                    const idx = items[0].dataIndex;
                                    const p = periods[idx];
                                    const isBase = (p?.key === self.baselinePeriodKey);
                                    const pLabel = p ? (p.label + ' (' + (p.short_label || p.label) + ')') : labels[idx];
                                    return pLabel + (isBase ? '  [BASELINE]' : '');
                                },
                                label: (item) => {
                                    const val = item.raw;
                                    if (val === null || val === undefined) {
                                        return ' ' + item.dataset.label + ': N/A';
                                    }
                                    let unit = 'Pax';
                                    if (item.dataset.yAxisID === 'yAircraft') unit = 'A/C';
                                    else if (item.dataset.yAxisID === 'yCargo') unit = cargoUnit;
                                    return ' ' + item.dataset.label + ': ' + Number(val).toLocaleString() + ' ' + unit;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            border: { display: false },
                            grid: { display: false, drawTicks: false },
                            ticks: {
                                color: textColor,
                                font: { family: 'JetBrains Mono', size: 10, weight: '500' },
                                padding: 6
                            }
                        },
                        yPassenger: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            grace: '8%',
                            border: { display: false },
                            grid: {
                                color: gridColor,
                                drawTicks: false
                            },
                            title: {
                                display: true,
                                text: 'Passenger (Pax)',
                                color: isDark ? '#93c5fd' : '#2563eb',
                                font: { family: 'JetBrains Mono', size: 9, weight: '600' },
                                padding: { bottom: 2 }
                            },
                            ticks: {
                                color: isDark ? '#94a3b8' : '#64748b',
                                font: { family: 'JetBrains Mono', size: 9 },
                                maxTicksLimit: 5,
                                padding: 6,
                                callback: (v) => formatShort(v)
                            }
                        },
                        yAircraft: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            grace: '8%',
                            border: { display: false },
                            grid: { drawOnChartArea: false, drawTicks: false },
                            title: {
                                display: true,
                                text: 'Aircraft (A/C)',
                                color: isDark ? '#6ee7b7' : '#059669',
                                font: { family: 'JetBrains Mono', size: 9, weight: '600' },
                                padding: { bottom: 2 }
                            },
                            ticks: {
                                color: isDark ? '#6ee7b7' : '#059669',
                                font: { family: 'JetBrains Mono', size: 9 },
                                maxTicksLimit: 5,
                                padding: 6,
                                callback: (v) => formatShort(v)
                            }
                        },
                        yCargo: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            grace: '8%',
                            border: { display: false },
                            grid: { drawOnChartArea: false, drawTicks: false },
                            title: {
                                display: true,
                                text: 'Cargo (' + cargoUnit + ')',
                                color: isDark ? '#fdba74' : '#ea580c',
                                font: { family: 'JetBrains Mono', size: 9, weight: '600' },
                                padding: { bottom: 2 }
                            },
                            ticks: {
                                color: isDark ? '#fdba74' : '#ea580c',
                                font: { family: 'JetBrains Mono', size: 9 },
                                maxTicksLimit: 5,
                                padding: 6,
                                callback: (v) => formatShort(v)
                            }
                        }
                    }
                }
            });
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
                const existing = Chart.getChart(canvasId) || _dauCharts.historical[canvasId];
                if (existing) {
                    existing.destroy();
                }

                const datasets = this.getHistoricalDatasets(metricKey);

                _dauCharts.historical[canvasId] = new Chart(ctx, {
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
                const chart = Chart.getChart(c.id) || _dauCharts.historical[c.id];
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
