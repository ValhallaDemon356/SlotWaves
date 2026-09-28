@extends('layouts.app')

@section('title', 'SlotWaves — Flight Daily Report (FDR) Dashboard')
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between transition-colors duration-150')

@section('content')
<div class="min-h-screen flex flex-col justify-between" x-data="fdrDashboardController()">

    {{-- ══ TOPBAR NAVIGATION ══════════════════════════════════════════════════ --}}
    <header class="w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-navy-900/90 backdrop-blur-md sticky top-0 z-30 px-4 sm:px-8 py-3 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-aviation-600 flex items-center justify-center shadow-sm text-white">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('home') }}" class="text-sm font-black tracking-tight text-slate-900 dark:text-white hover:text-aviation-600 transition">SlotWaves</a>
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">FDR Intelligence</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">AOCC Airport Operational Flight Daily Report Dashboard</p>
            </div>
        </div>

        {{-- Breadcrumb Flow: Home → Flight Daily Report → FDR Dashboard --}}
        <div class="hidden lg:flex items-center gap-2 text-xs font-medium text-slate-400">
            <a href="{{ route('home') }}" class="hover:text-aviation-600 transition">Home</a>
            <span>&rarr;</span>
            <span class="text-slate-500">Flight Daily Report</span>
            <span>&rarr;</span>
            <span class="text-aviation-700 dark:text-aviation-300 font-bold px-2 py-0.5 rounded bg-aviation-50 dark:bg-aviation-950 border border-aviation-200 dark:border-aviation-800">
                FDR Dashboard
            </span>
        </div>

        {{-- Action Buttons (Exports) --}}
        <div class="flex items-center gap-2">

            {{-- Export CSV --}}
            <a :href="getExportUrl('csv')"
               class="text-xs font-semibold text-slate-700 dark:text-slate-200 hover:text-aviation-600 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-navy-800 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export CSV</span>
            </a>

            {{-- Export PDF --}}
            <a :href="getExportUrl('pdf')"
               class="text-xs font-bold text-white bg-aviation-600 hover:bg-aviation-700 px-3.5 py-1.5 rounded-lg transition flex items-center gap-1.5 shadow-xs shadow-aviation-600/30">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Export PDF</span>
            </a>
        </div>
    </header>

    {{-- ══ MAIN DASHBOARD BODY ═════════════════════════════════════════════════ --}}
    <main class="flex-1 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        {{-- ══ SOURCE DATASET & ANALYSIS LEVEL HEADER (PART 31) ═════════════════ --}}
        <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
            
            {{-- Top Row: Report Title & Source Dataset Info --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-aviation-600 text-white tracking-wider uppercase shadow-2xs">FLIGHT DAILY REPORT</span>
                        <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black uppercase tracking-wider"
                              :class="sourceSummary.source_type === 'DAILY' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300 border border-sky-300' : (sourceSummary.source_type === 'MONTHLY' ? 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300')"
                              x-text="'SOURCE TYPE: ' + sourceSummary.source_type">SOURCE TYPE: {{ $sourceType }}</span>
                        <template x-if="sourceSummary.source_type === 'DAILY'">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">🔒 SOURCE DATE LOCKED</span>
                        </template>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mt-2">
                        <span>Source Period: <strong class="text-slate-800 dark:text-slate-200 font-mono" x-text="sourceSummary.period_label">{{ $sourceSummary['period_label'] }}</strong></span>
                        <span>&bull;</span>
                        <span>Source Flights: <strong class="text-slate-800 dark:text-slate-200 font-mono" x-text="sourceSummary.total_flights.toLocaleString()">{{ number_format($sourceSummary['total_flights']) }}</strong></span>
                        <span>&bull;</span>
                        <span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="sourceSummary.days_available">{{ $sourceSummary['days_available'] }}</span>
                    </div>
                </div>

                {{-- Analysis Level Switcher (Strictly Permitted Levels Only) --}}
                <div class="flex flex-wrap items-center gap-2 bg-slate-50 dark:bg-navy-800/80 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 px-2">Analysis Level:</span>
                    
                    {{-- DAILY SOURCE: Locked to DAILY --}}
                    <template x-if="sourceSummary.source_type === 'DAILY'">
                        <span class="px-3 py-1 rounded-lg text-xs font-bold bg-white dark:bg-navy-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 shadow-2xs">
                            DAILY 🔒
                        </span>
                    </template>

                    {{-- MONTHLY SOURCE: [ DAILY ] [ MONTHLY ] --}}
                    <template x-if="sourceSummary.source_type === 'MONTHLY'">
                        <div class="flex items-center gap-1">
                            <button type="button" @click="setAnalysisLevel('DAILY')"
                                    :class="filters.analysis_level === 'DAILY' ? 'bg-aviation-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-navy-700'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                                DAILY
                            </button>
                            <button type="button" @click="setAnalysisLevel('MONTHLY')"
                                    :class="filters.analysis_level === 'MONTHLY' ? 'bg-aviation-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-navy-700'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                                MONTHLY
                            </button>
                        </div>
                    </template>

                    {{-- YEARLY SOURCE: [ DAY ] [ MONTH ] [ YEAR ] --}}
                    <template x-if="sourceSummary.source_type === 'YEARLY'">
                        <div class="flex items-center gap-1">
                            <button type="button" @click="setAnalysisLevel('DAILY')"
                                    :class="filters.analysis_level === 'DAILY' ? 'bg-aviation-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-navy-700'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                                DAY
                            </button>
                            <button type="button" @click="setAnalysisLevel('MONTHLY')"
                                    :class="filters.analysis_level === 'MONTHLY' ? 'bg-aviation-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-navy-700'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                                MONTH
                            </button>
                            <button type="button" @click="setAnalysisLevel('YEARLY')"
                                    :class="filters.analysis_level === 'YEARLY' ? 'bg-aviation-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-navy-700'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                                YEAR
                            </button>
                        </div>
                    </template>

                    {{-- CUSTOM RANGE SOURCE: [ DAILY ] [ FULL RANGE ] --}}
                    <template x-if="sourceSummary.source_type === 'CUSTOM RANGE'">
                        <div class="flex items-center gap-1">
                            <button type="button" @click="setAnalysisLevel('DAILY')"
                                    :class="filters.analysis_level === 'DAILY' ? 'bg-aviation-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-navy-700'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                                DAILY
                            </button>
                            <button type="button" @click="setAnalysisLevel('FULL')"
                                    :class="filters.analysis_level === 'FULL' ? 'bg-aviation-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-navy-700'"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                                FULL RANGE
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Bottom Row: Analysis Date Stepper / Granular Selectors --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                
                {{-- Left: Analysis Scope Label --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Analysis Scope:</span>
                    
                    {{-- DAILY Mode --}}
                    <template x-if="filters.analysis_level === 'DAILY'">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-slate-900 dark:text-white" x-text="formatDateHeader(filters.analysis_date)">{{ date('d F Y', strtotime($analysisDate)) }}</span>
                            <template x-if="sourceSummary.source_type === 'DAILY'">
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    [ <span x-text="formatDateOption(filters.analysis_date)"></span> 🔒 ]
                                </span>
                            </template>
                        </div>
                    </template>

                    {{-- MONTHLY Mode --}}
                    <template x-if="filters.analysis_level === 'MONTHLY'">
                        <span class="text-sm font-black text-slate-900 dark:text-white" x-text="formatMonthHeader(filters.analysis_month)"></span>
                    </template>

                    {{-- YEARLY Mode --}}
                    <template x-if="filters.analysis_level === 'YEARLY'">
                        <span class="text-sm font-black text-slate-900 dark:text-white" x-text="'Calendar Year ' + filters.analysis_year"></span>
                    </template>

                    {{-- FULL RANGE Mode --}}
                    <template x-if="filters.analysis_level === 'FULL'">
                        <span class="text-sm font-black text-slate-900 dark:text-white" x-text="'Full Period: ' + sourceSummary.period_label"></span>
                    </template>
                </div>

                {{-- Right: Stepper and Available Day Picker (Locked if DAILY source) --}}
                <div>
                    {{-- DAILY Mode: Day picker & Stepper --}}
                    <template x-if="filters.analysis_level === 'DAILY' && sourceSummary.source_type !== 'DAILY'">
                        <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-navy-800/80 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
                            <button type="button" @click="stepAnalysisDate(-1)" class="w-7 h-7 rounded-lg bg-white dark:bg-navy-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-navy-600 font-bold text-xs cursor-pointer shadow-2xs transition" title="Previous Available Day">
                                &larr;
                            </button>
                            <select x-model="filters.analysis_date" @change="triggerFilter()" class="rounded-lg border-0 bg-transparent text-xs font-black text-aviation-700 dark:text-aviation-300 py-1 px-2 focus:ring-0 cursor-pointer">
                                <template x-for="d in availableDates" :key="d">
                                    <option :value="d" x-text="formatDateOption(d)"></option>
                                </template>
                            </select>
                            <button type="button" @click="stepAnalysisDate(1)" class="w-7 h-7 rounded-lg bg-white dark:bg-navy-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-navy-600 font-bold text-xs cursor-pointer shadow-2xs transition" title="Next Available Day">
                                &rarr;
                            </button>
                        </div>
                    </template>

                    {{-- Locked Daily View Indicator --}}
                    <template x-if="filters.analysis_level === 'DAILY' && sourceSummary.source_type === 'DAILY'">
                        <span class="text-xs font-mono font-bold text-slate-500 bg-slate-100 dark:bg-navy-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">
                            🔒 <span x-text="formatDateOption(filters.analysis_date)"></span> LOCKED
                        </span>
                    </template>
                </div>

            </div>

        </div>

        {{-- ══ DASHBOARD TABS (PART 1, PART 38: MOVE MODES INSIDE DASHBOARD) ══ --}}
        <div class="border-b border-slate-200 dark:border-slate-800 flex items-center gap-1 sm:gap-2 overflow-x-auto pb-px text-xs font-bold">
            <button type="button" @click="setTab('overview')"
                    :class="activeTab === 'overview' ? 'text-aviation-600 border-aviation-600 border-b-2 bg-aviation-50/50 dark:bg-aviation-950/40' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 border-transparent border-b-2'"
                    class="px-4 py-2.5 rounded-t-xl transition cursor-pointer flex items-center gap-2 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>OVERVIEW</span>
            </button>

            <button type="button" @click="setTab('hourly')"
                    :class="activeTab === 'hourly' ? 'text-aviation-600 border-aviation-600 border-b-2 bg-aviation-50/50 dark:bg-aviation-950/40' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 border-transparent border-b-2'"
                    class="px-4 py-2.5 rounded-t-xl transition cursor-pointer flex items-center gap-2 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>HOURLY OPERATIONS</span>
            </button>

            <button type="button" @click="setTab('load_factor')"
                    :class="activeTab === 'load_factor' ? 'text-aviation-600 border-aviation-600 border-b-2 bg-aviation-50/50 dark:bg-aviation-950/40' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 border-transparent border-b-2'"
                    class="px-4 py-2.5 rounded-t-xl transition cursor-pointer flex items-center gap-2 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>LOAD FACTOR</span>
            </button>

            <button type="button" @click="setTab('comparison')"
                    :class="activeTab === 'comparison' ? 'text-aviation-600 border-aviation-600 border-b-2 bg-aviation-50/50 dark:bg-aviation-950/40' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 border-transparent border-b-2'"
                    class="px-4 py-2.5 rounded-t-xl transition cursor-pointer flex items-center gap-2 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>COMPARISON</span>
            </button>

            <button type="button" @click="setTab('reconciliation')"
                    :class="activeTab === 'reconciliation' ? 'text-aviation-600 border-aviation-600 border-b-2 bg-aviation-50/50 dark:bg-aviation-950/40' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 border-transparent border-b-2'"
                    class="px-4 py-2.5 rounded-t-xl transition cursor-pointer flex items-center gap-2 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <span>RECONCILIATION</span>
            </button>
        </div>

        {{-- ══ SECTION 1: FILTER CASCADE & ACTIVE CHIPS BAR ═══════════════════ --}}
        <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 sm:p-5 shadow-sm space-y-4">
            
            {{-- ══ ROW COUNT RECONCILIATION DIAGNOSTIC (PART 3 & PART 28) ══ --}}
            <div class="rounded-xl border p-3 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs"
                 :class="(reconciliation && reconciliation.excluded_count > 0) 
                    ? 'bg-amber-50/80 dark:bg-amber-950/40 border-amber-300 dark:border-amber-800 text-amber-900 dark:text-amber-200' 
                    : 'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200'">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-bold uppercase tracking-wider text-[11px] px-2 py-0.5 rounded font-mono"
                          :class="(reconciliation && reconciliation.excluded_count > 0) ? 'bg-amber-200 dark:bg-amber-900 text-amber-900 dark:text-amber-100' : 'bg-emerald-200 dark:bg-emerald-900 text-emerald-900 dark:text-emerald-100'">
                        RECONCILIATION
                    </span>
                    <span>Source Rows: <strong class="font-mono text-sm" x-text="reconciliation ? reconciliation.source_count : sourceSummary.total_flights">{{ $filterResult['source_count'] }}</strong></span>
                    <span>&bull;</span>
                    <span>Normalized: <strong class="font-mono text-sm" x-text="reconciliation ? reconciliation.normalized_count : sourceSummary.total_flights">{{ $filterResult['normalized_count'] }}</strong></span>
                    <span>&bull;</span>
                    <span>Filtered: <strong class="font-mono text-sm" x-text="reconciliation ? reconciliation.filtered_count : {{ $filterResult['filtered_count'] }}">{{ $filterResult['filtered_count'] }}</strong></span>
                    <span>&bull;</span>
                    <span>Excluded: <strong class="font-mono text-sm" x-text="reconciliation ? reconciliation.excluded_count : {{ $filterResult['excluded_count'] }}">{{ $filterResult['excluded_count'] }}</strong></span>
                </div>

                <div>
                    <template x-if="!reconciliation || reconciliation.excluded_count === 0">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-bold text-[10px] tracking-wider uppercase shadow-2xs">
                            ✓ 100% RECONCILED (0 DROPPED)
                        </span>
                    </template>
                    <template x-if="reconciliation && reconciliation.excluded_count > 0">
                        <div class="text-[11px] font-mono flex flex-wrap items-center gap-1">
                            <span class="font-bold text-amber-800 dark:text-amber-300">EXCLUSION REASONS:</span>
                            <template x-for="(count, reason) in reconciliation.exclusion_reasons" :key="reason">
                                <span class="px-2 py-0.5 rounded bg-white dark:bg-navy-800 border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200">
                                    <span x-text="reason"></span>: <strong x-text="count"></strong>
                                </span>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Quick Filter Controls --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 text-xs">
                
                {{-- Airport --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Airport</label>
                    <select x-model="filters.airport" @change="triggerFilter()" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold py-1.5 px-2.5">
                        <option value="ALL">ALL AIRPORTS</option>
                        @foreach($airports as $ap)
                            <option value="{{ $ap }}">{{ $ap }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Leg --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Leg</label>
                    <select x-model="filters.leg" @change="triggerFilter()" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold py-1.5 px-2.5">
                        <option value="ALL">ALL LEGS</option>
                        <option value="ARRIVAL">ARRIVAL</option>
                        <option value="DEPARTURE">DEPARTURE</option>
                    </select>
                </div>

                {{-- Operator --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Operator</label>
                    <select x-model="filters.operator" @change="triggerFilter()" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold py-1.5 px-2.5">
                        <option value="ALL">ALL AIRLINE</option>
                        @foreach($airlines as $al)
                            <option value="{{ $al }}">{{ $al }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Traffic --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Traffic</label>
                    <select x-model="filters.traffic" @change="triggerFilter()" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold py-1.5 px-2.5">
                        <option value="ALL">ALL TRAFFIC</option>
                        <option value="DOMESTIC">DOMESTIC</option>
                        <option value="INTERNATIONAL">INTERNATIONAL</option>
                    </select>
                </div>

                {{-- Realization (ALL / YES / NO) --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Realization</label>
                    <select x-model="filters.realization" @change="triggerFilter()" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold py-1.5 px-2.5">
                        <option value="ALL">ALL (REAL &amp; PLAN)</option>
                        <option value="YES">REALIZED ONLY (AIBT/AOBT)</option>
                        <option value="NO">UNREALIZED ONLY (PLAN)</option>
                    </select>
                </div>

                {{-- Search Box --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Search</label>
                    <input type="text" x-model.debounce.350ms="filters.search" @input="triggerFilter()" placeholder="Flt, Airline, Reg..."
                           class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs py-1.5 px-2.5">
                </div>

            </div>

            {{-- Filter Chips & Dynamic Counter --}}
            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                
                {{-- Active Chips --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Active:</span>

                    <template x-for="chip in activeChips" :key="chip.key">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-aviation-50 text-aviation-700 dark:bg-aviation-950/80 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-semibold text-[11px]">
                            <span x-text="chip.label"></span>
                            <button type="button" @click="removeFilter(chip.key)" class="hover:text-aviation-900 dark:hover:text-white text-sm font-bold leading-none cursor-pointer">&times;</button>
                        </span>
                    </template>

                    <template x-if="activeChips.length === 0">
                        <span class="text-slate-400 text-xs italic">No active filters (Showing baseline dataset)</span>
                    </template>

                    <template x-if="activeChips.length > 0">
                        <button type="button" @click="clearAllFilters()" class="text-[11px] font-bold text-red-600 dark:text-red-400 hover:underline ml-2 cursor-pointer">
                            [ CLEAR ALL ]
                        </button>
                    </template>
                </div>

                {{-- Dynamic Counter: Showing X of Y records --}}
                <div class="flex items-center gap-2 font-mono text-xs">
                    <span class="w-2 h-2 rounded-full" :class="isFiltering ? 'bg-amber-400 animate-ping' : 'bg-emerald-500'"></span>
                    <span class="font-bold text-slate-900 dark:text-white" x-text="counterText">
                        Showing {{ $filterResult['filtered_count'] }} of {{ $filterResult['total_count'] }} records
                    </span>
                </div>

            </div>

        </div>

        {{-- ══ EMPTY DATE NOTIFICATION STATE (PART 36) ═════════════════════════ --}}
        <template x-if="kpis.total_flights === 0">
            <div class="bg-amber-50/90 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl p-8 text-center space-y-3 shadow-xs">
                <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-wider">
                    NO FLIGHT DATA FOR: <span x-text="formatDateHeader(filters.analysis_date)"></span>
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                    No flight movements were registered in this uploaded dataset for the selected analysis date. Please select an available date from the selector above or reset active filters.
                </p>
                <div class="flex items-center justify-center gap-2 pt-2">
                    <template x-if="availableDates.length > 0">
                        <button type="button" @click="filters.analysis_date = availableDates[0]; triggerFilter();" class="px-4 py-2 rounded-xl bg-aviation-600 text-white font-bold text-xs hover:bg-aviation-700 transition">
                            Go to First Available Date (<span x-text="formatDateOption(availableDates[0])"></span>)
                        </button>
                    </template>
                    <button type="button" @click="clearAllFilters()" class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-navy-700 text-slate-700 dark:text-slate-200 font-bold text-xs hover:bg-slate-300 transition">
                        Reset Filters
                    </button>
                </div>
            </div>
        </template>

        {{-- ══ DASHBOARD CONTENT (RENDERED WHEN DATA IS PRESENT) ═════════════════ --}}
        <div x-show="kpis.total_flights > 0" class="space-y-6">

            {{-- ══ SECTION 2: TOP METRIC CARDS (PART 15, PART 16) ═══════════════════ --}}
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">

                {{-- 1. Analysis Flights --}}
                <div class="bg-white dark:bg-navy-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400"
                                  x-text="filters.analysis_level === 'DAILY' ? 'Analysis Day Flights' : (filters.analysis_level === 'MONTHLY' ? 'Monthly Flights' : 'Analysis Scope Flights')">
                                Analysis Day Flights
                            </span>
                            <div class="text-[10px] font-bold text-aviation-600 dark:text-aviation-400 font-mono mt-0.5" x-text="'Source Total: ' + sourceSummary.total_flights.toLocaleString()">
                                Source Total: {{ number_format($sourceSummary['total_flights']) }}
                            </div>
                        </div>
                        <span class="p-1.5 rounded-lg bg-aviation-50 dark:bg-aviation-950 text-aviation-600 dark:text-aviation-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </span>
                    </div>
                    <div class="text-2xl font-black text-slate-900 dark:text-white mt-1" x-text="kpis.total_flights.toLocaleString()">
                        {{ number_format($analytics['kpis']['total_flights']) }}
                    </div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mt-1">
                        <span class="text-emerald-600 dark:text-emerald-400">Arr: <strong x-text="kpis.arrivals.toLocaleString()">{{ number_format($analytics['kpis']['arrivals']) }}</strong></span>
                        <span>•</span>
                        <span class="text-aviation-600 dark:text-aviation-400">Dep: <strong x-text="kpis.departures.toLocaleString()">{{ number_format($analytics['kpis']['departures']) }}</strong></span>
                    </div>
                </div>

                {{-- 2. Analysis Passengers (Part 20 to Part 24) --}}
                <div class="bg-white dark:bg-navy-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                            Passenger Movement
                        </span>
                        <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </span>
                    </div>
                    <div class="text-2xl font-black text-slate-900 dark:text-white mt-1" x-text="kpis.total_passengers.toLocaleString()">
                        {{ number_format($analytics['kpis']['total_passengers']) }}
                    </div>
                    <div class="flex items-center justify-between text-xs font-bold text-aviation-600 dark:text-aviation-400 mt-1">
                        <span x-text="kpis.pax_per_flight_display">{{ $analytics['kpis']['pax_per_flight_display'] ?? '134.9 Pax / Flight' }}</span>
                        <span class="text-[10px] font-normal text-slate-400">Trn: <strong class="font-bold text-slate-700 dark:text-slate-200" x-text="kpis.transit_passengers"></strong> | Trf: <strong class="font-bold text-slate-700 dark:text-slate-200" x-text="kpis.transfer_passengers"></strong></span>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-1 truncate">
                        Adl: <strong x-text="kpis.adult_passengers.toLocaleString()">{{ number_format($analytics['kpis']['adult_passengers']) }}</strong> | Chd: <strong x-text="kpis.child_passengers.toLocaleString()">{{ number_format($analytics['kpis']['child_passengers']) }}</strong> | Inf: <strong x-text="kpis.infant_passengers.toLocaleString()">{{ number_format($analytics['kpis']['infant_passengers']) }}</strong>
                    </div>
                </div>

                {{-- 3. Average Load Factor / Seat Utilization (Part 25 & 26) --}}
                <div class="bg-white dark:bg-navy-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                            Seat Util. / Load Factor
                        </span>
                        <span class="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </span>
                    </div>
                    <div class="flex items-baseline gap-2 mt-1">
                        <div class="text-2xl font-black text-aviation-600 dark:text-aviation-400" x-text="kpis.passenger_utilization">
                            {{ $analytics['kpis']['passenger_utilization'] ?? '71.7%' }}
                        </div>
                        <span class="text-xs font-semibold text-slate-400">Pax Util.</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 mt-1 font-mono">
                        <span>W-LF: <strong x-text="kpis.weighted_load_factor">{{ $analytics['kpis']['weighted_load_factor'] ?? '37.8%' }}</strong></span>
                        <span>Cap: <strong x-text="kpis.total_capacity.toLocaleString()">{{ number_format($analytics['kpis']['total_capacity']) }}</strong></span>
                    </div>
                </div>

                {{-- 4. Peak Hour & Cargo (PART 18) --}}
                <div class="bg-white dark:bg-navy-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">Peak Hour &amp; Cargo</span>
                        <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                    </div>
                    <div class="text-xl font-black text-amber-600 dark:text-amber-400 mt-1 truncate" x-text="kpis.peak_hour ? kpis.peak_hour.time_range : 'N/A'">
                        {{ $analytics['kpis']['peak_hour']['time_range'] ?? 'N/A' }}
                    </div>
                    <div class="flex items-center justify-between text-xs mt-1">
                        <span class="font-bold text-slate-700 dark:text-slate-300" x-text="(kpis.peak_hour ? kpis.peak_hour.movements : 0) + ' Mvts'">{{ ($analytics['kpis']['peak_hour']['movements'] ?? 0) }} Mvts</span>
                        <span class="text-slate-500"><strong x-text="kpis.cargo_ton">{{ $analytics['kpis']['cargo_ton'] }}</strong> t Cargo</span>
                        <span class="font-bold text-amber-600 dark:text-amber-400" x-text="kpis.irregularities.total + ' Irreg'">{{ $analytics['kpis']['irregularities']['total'] }} Irreg</span>
                    </div>
                </div>

            </div>

            {{-- ══ TAB VIEW 1 & 2: THE 3 MENTOR HOURLY CHARTS (OVERVIEW & HOURLY OPERATIONS) ══ --}}
            <div x-show="activeTab === 'overview' || activeTab === 'hourly'" class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 sm:p-6 shadow-sm space-y-6">
                
                {{-- Chart Header (PART 22: Visible Header & Subtitle) --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2.5 py-0.5 rounded-md font-black text-[10px] bg-aviation-600 text-white uppercase tracking-wider">
                                <span x-text="filters.analysis_level === 'DAILY' ? 'PEAK DAILY ANALYSIS' : 'HOURLY ANALYSIS'">PEAK DAILY ANALYSIS</span>
                            </span>
                            <span class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight"
                                  x-text="filters.analysis_level === 'DAILY' ? formatDateHeader(filters.analysis_date) : formatMonthHeader(filters.analysis_month)">
                                {{ date('d F Y', strtotime($analysisDate)) }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-400">
                            Source Period: <strong class="text-slate-600 dark:text-slate-300 font-mono" x-text="sourceSummary.period_label">{{ $sourceSummary['period_label'] }}</strong> &bull;
                            3 Mentor Hourly Operational Charts &bull; 24-Hour Continuous Timeline (00:00 to 23:59)
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/80 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 font-bold text-xs font-mono">
                            Peak Hour: <strong x-text="kpis.peak_hour ? kpis.peak_hour.display : 'N/A'">{{ $analytics['kpis']['peak_hour']['display'] ?? 'N/A' }}</strong>
                        </span>
                    </div>
                </div>

                {{-- ── CHART 1: ARRIVAL–DEPARTURE MOVEMENT (no Runway Capacity) ─────── --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">
                                Chart 1: ARRIVAL–DEPARTURE MOVEMENT
                            </h3>
                            <span class="text-[11px] font-semibold text-slate-400 font-mono" x-text="'(' + formatDateOption(filters.analysis_date) + ')'"></span>
                        </div>
                        <div class="flex items-center gap-3 text-[11px] text-slate-500">
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#FDBA74]"></span> Plan (PPRP)</span>
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#D97706]"></span> Irregular Flt</span>
                        </div>
                    </div>
                    <div class="h-56 w-full relative">
                        <canvas id="mentorChart1Movement" style="display:block;"></canvas>
                    </div>
                </div>

                {{-- ── CHART 2: DEPARTURE MOVEMENT ───────────────────────────────── --}}
                <div class="space-y-2 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">
                                Chart 2: DEPARTURE MOVEMENT
                            </h3>
                            <span class="text-[11px] font-semibold text-slate-400 font-mono" x-text="'(' + formatDateOption(filters.analysis_date) + ')'"></span>
                        </div>
                        <div class="flex items-center gap-3 text-[11px] text-slate-500">
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#93C5FD]"></span> Dom Dep</span>
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#60A5FA]"></span> Intl Dep</span>
                        </div>
                    </div>
                    <div class="h-44 w-full relative">
                        <canvas id="mentorChart2Departure" style="display:block;"></canvas>
                    </div>
                </div>

                {{-- ── CHART 3: ARRIVAL MOVEMENT ─────────────────────────────────── --}}
                <div class="space-y-2 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">
                                Chart 3: ARRIVAL MOVEMENT
                            </h3>
                            <span class="text-[11px] font-semibold text-slate-400 font-mono" x-text="'(' + formatDateOption(filters.analysis_date) + ')'"></span>
                        </div>
                        <div class="flex items-center gap-3 text-[11px] text-slate-500">
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#CA8A04]"></span> Dom Arr</span>
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#FDE047]"></span> Intl Arr</span>
                        </div>
                    </div>
                    <div class="h-44 w-full relative">
                        <canvas id="mentorChart3Arrival" style="display:block;"></canvas>
                    </div>
                </div>

            </div>

            {{-- ══ TAB VIEW: OPERATIONAL PRESENTATION MODULES (OVERVIEW / LOAD FACTOR) ══ --}}
            <div x-show="activeTab === 'overview' || activeTab === 'load_factor'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- MODULE 1: Schedule vs Realization (PART 25: Punctuality) --}}
                <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                                1. Schedule vs Realization (SIBT/SOBT vs AIBT/AOBT)
                            </h3>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold"
                              :class="schedVsReal.has_evaluation ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-400'"
                              x-text="schedVsReal.has_evaluation ? (schedVsReal.on_time_percentage + ' ON-TIME') : 'N/A'"></span>
                    </div>

                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800/60 border border-slate-100 dark:border-slate-800">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Evaluated</div>
                            <div class="text-base font-black text-slate-900 dark:text-white mt-0.5" x-text="schedVsReal.evaluated_flights"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-800">
                            <div class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400">On-Time</div>
                            <div class="text-base font-black text-emerald-700 dark:text-emerald-300 mt-0.5" x-text="schedVsReal.has_evaluation ? schedVsReal.on_time_count : 'N/A'"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-100 dark:border-amber-800">
                            <div class="text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400">16-45m Delay</div>
                            <div class="text-base font-black text-amber-700 dark:text-amber-300 mt-0.5" x-text="schedVsReal.has_evaluation ? schedVsReal.minor_delay_count : 'N/A'"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-rose-50/70 dark:bg-rose-950/40 border border-rose-100 dark:border-rose-800">
                            <div class="text-[10px] font-bold uppercase text-rose-600 dark:text-rose-400">&gt;45m Delay</div>
                            <div class="text-base font-black text-rose-700 dark:text-rose-300 mt-0.5" x-text="schedVsReal.has_evaluation ? schedVsReal.severe_delay_count : 'N/A'"></div>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-800/40 border border-slate-100 dark:border-slate-800 text-xs flex items-center justify-between">
                        <span class="text-slate-500">Average Punctuality Variance:</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white" x-text="schedVsReal.avg_delay_minutes"></span>
                    </div>
                </div>

                {{-- MODULE 2: Passenger Composition (PART 23: Analysis Day Passengers) --}}
                <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                                2. Passenger Composition
                            </h3>
                        </div>
                        <span class="text-xs text-slate-400 font-mono" x-text="'Date: ' + formatDateOption(filters.analysis_date)"></span>
                    </div>

                    <div class="h-32 w-full relative">
                        <canvas id="passengerTrendChart"></canvas>
                    </div>

                    <div class="grid grid-cols-5 gap-1.5 text-center text-xs font-mono pt-1">
                        <div class="p-1.5 rounded-lg bg-slate-50 dark:bg-navy-800 border border-slate-100 dark:border-slate-800">
                            <div class="text-[9px] uppercase font-bold text-slate-400 font-sans">Adult</div>
                            <div class="text-xs font-black text-slate-800 dark:text-slate-200" x-text="paxAnalytics.composition.adult.toLocaleString()"></div>
                        </div>
                        <div class="p-1.5 rounded-lg bg-slate-50 dark:bg-navy-800 border border-slate-100 dark:border-slate-800">
                            <div class="text-[9px] uppercase font-bold text-slate-400 font-sans">Child</div>
                            <div class="text-xs font-black text-slate-800 dark:text-slate-200" x-text="paxAnalytics.composition.child.toLocaleString()"></div>
                        </div>
                        <div class="p-1.5 rounded-lg bg-slate-50 dark:bg-navy-800 border border-slate-100 dark:border-slate-800">
                            <div class="text-[9px] uppercase font-bold text-slate-400 font-sans">Infant</div>
                            <div class="text-xs font-black text-slate-800 dark:text-slate-200" x-text="paxAnalytics.composition.infant.toLocaleString()"></div>
                        </div>
                        <div class="p-1.5 rounded-lg bg-slate-50 dark:bg-navy-800 border border-slate-100 dark:border-slate-800">
                            <div class="text-[9px] uppercase font-bold text-slate-400 font-sans">Transit</div>
                            <div class="text-xs font-black text-slate-800 dark:text-slate-200" x-text="paxAnalytics.composition.transit.toLocaleString()"></div>
                        </div>
                        <div class="p-1.5 rounded-lg bg-slate-50 dark:bg-navy-800 border border-slate-100 dark:border-slate-800">
                            <div class="text-[9px] uppercase font-bold text-slate-400 font-sans">Transfer</div>
                            <div class="text-xs font-black text-slate-800 dark:text-slate-200" x-text="paxAnalytics.composition.transfer.toLocaleString()"></div>
                        </div>
                    </div>
                </div>

                {{-- MODULE 3: Airline & Route Performance --}}
                <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                                3. Airline Operator Performance
                            </h3>
                        </div>
                        <span class="text-[11px] text-slate-400 font-mono">Ranked by Volume</span>
                    </div>

                    <div class="space-y-2.5 max-h-56 overflow-y-auto pr-1 text-xs">
                        <template x-for="al in airlineRoute.ranked_airlines.slice(0, 6)" :key="al.airline">
                            <div class="p-2.5 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-navy-800/40 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white" x-text="al.airline"></div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        <span x-text="al.flights + ' flts'"></span> &bull;
                                        <span x-text="al.passengers.toLocaleString() + ' pax'"></span> &bull;
                                        <span x-text="(al.cargo_kg / 1000).toFixed(1) + ' t cargo'"></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300" x-text="'LF: ' + al.avg_load_factor"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- MODULE 4: Ground Operations & Irregularities --}}
                <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                                4. Ground Operations &amp; Stand Utilization
                            </h3>
                        </div>
                        <span class="text-xs text-slate-400 font-mono">Stands &amp; Runways</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400 mb-1.5">Top Stands</div>
                            <div class="space-y-1.5 max-h-44 overflow-y-auto">
                                <template x-for="st in groundOps.stands.slice(0, 5)" :key="st.stand">
                                    <div class="flex items-center justify-between p-1.5 rounded bg-slate-50 dark:bg-navy-800 font-mono text-[11px]">
                                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="st.stand"></span>
                                        <span class="text-slate-500" x-text="st.count + ' (' + st.percentage + '%)'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400 mb-1.5">Runway Distribution</div>
                            <div class="space-y-1.5 max-h-44 overflow-y-auto">
                                <template x-for="rw in groundOps.runways.slice(0, 4)" :key="rw.runway">
                                    <div class="flex items-center justify-between p-1.5 rounded bg-slate-50 dark:bg-navy-800 font-mono text-[11px]">
                                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="rw.runway"></span>
                                        <span class="text-slate-500" x-text="rw.count + ' (' + rw.percentage + '%)'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ══ TAB VIEW 4: COMPARISON VIEW ═════════════════════════════════════ --}}
            <div x-show="activeTab === 'comparison'" class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                            Operational Comparison &amp; Leg Breakdown
                        </h3>
                    </div>
                    <span class="text-xs font-mono text-slate-400">Current Scope vs Period Averages</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-800 border border-slate-200 dark:border-slate-700 space-y-2">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Arrivals vs Departures</span>
                        <div class="text-lg font-black text-slate-900 dark:text-white">
                            <span class="text-sky-600" x-text="kpis.arrivals"></span> Arr / <span class="text-emerald-600" x-text="kpis.departures"></span> Dep
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono">
                            Arrival Ratio: <strong x-text="((kpis.arrivals / Math.max(1, kpis.total_flights)) * 100).toFixed(1) + '%'"></strong>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-800 border border-slate-200 dark:border-slate-700 space-y-2">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Passenger Volume Density</span>
                        <div class="text-lg font-black text-slate-900 dark:text-white">
                            <span x-text="Math.round(kpis.total_passengers / Math.max(1, kpis.total_flights))"></span> Pax / Flight
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono">
                            Total Pax: <strong x-text="kpis.total_passengers.toLocaleString()"></strong>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-800 border border-slate-200 dark:border-slate-700 space-y-2">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Cargo Payload Density</span>
                        <div class="text-lg font-black text-slate-900 dark:text-white">
                            <span x-text="Math.round(kpis.cargo_kg / Math.max(1, kpis.total_flights))"></span> kg / Flight
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono">
                            Total Cargo: <strong x-text="kpis.cargo_ton + ' Ton'"></strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══ TAB VIEW 5: RECONCILIATION ENGINE (PART 39: NOT FABRICATED) ══════ --}}
            <div x-show="activeTab === 'reconciliation'" class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                            Cross-System Reconciliation Engine
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="reconciliationSubMode = 'apps'; triggerFilter();"
                                :class="reconciliationSubMode === 'apps' ? 'bg-aviation-600 text-white shadow-2xs' : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300'"
                                class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                            OASYS vs APPS
                        </button>
                        <button type="button" @click="reconciliationSubMode = 'edifly'; triggerFilter();"
                                :class="reconciliationSubMode === 'edifly' ? 'bg-aviation-600 text-white shadow-2xs' : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300'"
                                class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                            OASYS vs EDIFLY
                        </button>
                    </div>
                </div>

                {{-- Status Banner: Check if second dataset exists (PART 39) --}}
                <div class="p-6 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 text-center space-y-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-200 dark:bg-navy-800 text-slate-700 dark:text-slate-300 font-mono font-bold text-xs">
                        NOT AVAILABLE
                    </div>
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">Secondary Dataset Required</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-lg mx-auto">
                        Automated delta reconciliation requires a secondary APPS passenger manifest or EDIFLY telex transmission file. Comparison metrics are not fabricated to maintain operational flight accuracy.
                    </p>
                </div>
            </div>

            {{-- ══ SECTION 4B: FLIGHT MOVEMENT CHART ALL / DEP / ARR ════════════════ --}}
            <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                            Flight Movement Chart
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800"
                              x-text="'Scope: ' + (filters.analysis_level === 'DAILY' ? formatDateOption(filters.analysis_date) : (filters.analysis_level === 'MONTHLY' ? formatMonthHeader(filters.analysis_month) : filters.analysis_year))">
                        </span>
                    </div>
                    {{-- ALL / DEP / ARR mode switcher --}}
                    <div class="flex items-center gap-1 bg-slate-50 dark:bg-navy-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
                        <button type="button" @click="fdrChartMode = 'ALL'; renderFlightMovementChart()"
                                :class="fdrChartMode === 'ALL' ? 'bg-aviation-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60'"
                                class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                            ALL
                        </button>
                        <button type="button" @click="fdrChartMode = 'DEP'; renderFlightMovementChart()"
                                :class="fdrChartMode === 'DEP' ? 'bg-blue-700 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60'"
                                class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                            DEP
                        </button>
                        <button type="button" @click="fdrChartMode = 'ARR'; renderFlightMovementChart()"
                                :class="fdrChartMode === 'ARR' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200/60'"
                                class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer">
                            ARR
                        </button>
                    </div>
                </div>

                {{-- Legend --}}
                <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-600 dark:text-slate-400">
                    <template x-if="fdrChartMode === 'ALL' || fdrChartMode === 'DEP'">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm" style="background:#1D4ED8"></span>
                            Dep Domestic
                        </span>
                    </template>
                    <template x-if="fdrChartMode === 'ALL' || fdrChartMode === 'DEP'">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm" style="background:#60A5FA"></span>
                            Dep International
                        </span>
                    </template>
                    <template x-if="fdrChartMode === 'ALL' || fdrChartMode === 'ARR'">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm" style="background:#CA8A04"></span>
                            Arr Domestic
                        </span>
                    </template>
                    <template x-if="fdrChartMode === 'ALL' || fdrChartMode === 'ARR'">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm" style="background:#FDE047"></span>
                            Arr International
                        </span>
                    </template>
                </div>

                {{-- Chart Canvas --}}
                <div class="relative" style="height:280px;">
                    <canvas id="fdrMovementChart" style="display:block;"></canvas>
                    <template x-if="hourlyData.every(h => (h.dep_plan + h.arr_plan) === 0)">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <p class="text-slate-400 text-sm font-medium">Data tidak tersedia untuk scope ini</p>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ══ SECTION 5: DETAILED FLIGHT TABLE (PART 26, PART 27) ═══════════════ --}}
            <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                            Detailed Flight Movement Registry
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800"
                              x-text="'Analysis Scope: ' + (filters.analysis_level === 'DAILY' ? formatDateOption(filters.analysis_date) : (filters.analysis_level === 'MONTHLY' ? formatMonthHeader(filters.analysis_month) : filters.analysis_year))">
                            Analysis Date: {{ date('d-m-Y', strtotime($analysisDate)) }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-400 font-mono">
                        Showing records strictly for selected analysis scope &bull; Click row for modal
                    </div>
                </div>

                {{-- Flight Table --}}
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-navy-800/80 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="py-3 px-3">#</th>
                                <th class="py-3 px-3">Airline</th>
                                <th class="py-3 px-3">Flight No</th>
                                <th class="py-3 px-3">Leg</th>
                                <th class="py-3 px-3">Route</th>
                                <th class="py-3 px-3">Sched (SIBT/SOBT)</th>
                                <th class="py-3 px-3">Actual (AIBT/AOBT)</th>
                                <th class="py-3 px-3">Reg No</th>
                                <th class="py-3 px-3">Cap</th>
                                <th class="py-3 px-3">Load</th>
                                <th class="py-3 px-3">LF%</th>
                                <th class="py-3 px-3">Cargo</th>
                                <th class="py-3 px-3">Stand</th>
                                <th class="py-3 px-3">Runway</th>
                                <th class="py-3 px-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-[11px] font-mono">
                            <template x-for="r in flightRecords" :key="r.index">
                                <tr @click="openFlightModal(r)" class="hover:bg-aviation-50/50 dark:hover:bg-navy-800/50 transition cursor-pointer">
                                    <td class="py-2.5 px-3 text-slate-400" x-text="r.index"></td>
                                    <td class="py-2.5 px-3 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="r.air_line"></td>
                                    <td class="py-2.5 px-3 font-bold text-aviation-600 dark:text-aviation-400" x-text="r.flight_no"></td>
                                    <td class="py-2.5 px-3 font-sans">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                              :class="r.direction === 'ARRIVAL' ? 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'"
                                              x-text="r.leg"></span>
                                    </td>
                                    <td class="py-2.5 px-3" x-text="r.route"></td>
                                    <td class="py-2.5 px-3 text-slate-500 font-mono" x-text="r.sched_display || (r.direction === 'ARRIVAL' ? r.sibt : r.sobt)"></td>
                                    <td class="py-2.5 px-3 text-slate-800 dark:text-slate-200 font-mono font-bold" x-text="r.actual_display || (r.direction === 'ARRIVAL' ? r.aibt : r.aobt)"></td>
                                    <td class="py-2.5 px-3" x-text="r.reg_no"></td>
                                    <td class="py-2.5 px-3" x-text="r.cap"></td>
                                    <td class="py-2.5 px-3" x-text="r.load"></td>
                                    <td class="py-2.5 px-3 font-bold"
                                        :class="r.load_factor >= 85 ? 'text-emerald-600' : (r.load_factor >= 70 ? 'text-aviation-600' : 'text-amber-600')"
                                        x-text="r.load_factor !== 'N/A' ? r.load_factor + '%' : 'N/A'">
                                    </td>
                                    <td class="py-2.5 px-3" x-text="r.cargo_kg.toLocaleString()"></td>
                                    <td class="py-2.5 px-3" x-text="r.stand"></td>
                                    <td class="py-2.5 px-3" x-text="r.runway"></td>
                                    <td class="py-2.5 px-3 text-center font-sans">
                                        <template x-if="r.is_irregular">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">IRREG</span>
                                        </template>
                                        <template x-if="!r.is_irregular">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-400">NORM</span>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Table Pagination --}}
                <div class="flex items-center justify-between text-xs pt-2">
                    <div class="text-slate-400">
                        Page <span class="font-bold text-slate-800 dark:text-slate-200" x-text="pagination.current_page"></span> of <span x-text="pagination.total_pages"></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                                class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-navy-800 text-slate-700 dark:text-slate-300 disabled:opacity-40 disabled:cursor-not-allowed">
                            Previous
                        </button>
                        <button type="button" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.total_pages"
                                class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-navy-800 text-slate-700 dark:text-slate-300 disabled:opacity-40 disabled:cursor-not-allowed">
                            Next
                        </button>
                    </div>
                </div>

            </div>

        </div>

    </main>

    {{-- ══ FLIGHT DETAILS MODAL ════════════════════════════════════════════════ --}}
    <div x-show="selectedFlight !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="selectedFlight = null" class="bg-white dark:bg-navy-900 rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
            
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-aviation-600"></span>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white" x-text="'Flight Details: ' + (selectedFlight ? selectedFlight.flight_no : '')"></h3>
                </div>
                <button @click="selectedFlight = null" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <template x-if="selectedFlight">
                <div class="space-y-4 text-xs font-mono">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800">
                            <div class="text-[10px] text-slate-400 font-sans">Airline</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200" x-text="selectedFlight.air_line"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800">
                            <div class="text-[10px] text-slate-400 font-sans">Paired Flight</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200" x-text="selectedFlight.paired_no"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800">
                            <div class="text-[10px] text-slate-400 font-sans">Leg Direction</div>
                            <div class="font-bold text-aviation-600" x-text="selectedFlight.leg"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800">
                            <div class="text-[10px] text-slate-400 font-sans">Route</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200" x-text="selectedFlight.route"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800">
                            <div class="text-[10px] text-slate-400 font-sans">Registration</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200" x-text="selectedFlight.reg_no"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800">
                            <div class="text-[10px] text-slate-400 font-sans">MTOW</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200" x-text="selectedFlight.mtow"></div>
                        </div>
                    </div>

                    {{-- Timings --}}
                    <div class="p-3 rounded-xl bg-aviation-50/50 dark:bg-aviation-950/40 border border-aviation-100 dark:border-aviation-900/60 grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-[10px] font-sans text-aviation-700 dark:text-aviation-300 font-bold">Scheduled Time (SIBT/SOBT)</div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white font-mono" x-text="selectedFlight.sched_display || (selectedFlight.direction === 'ARRIVAL' ? selectedFlight.sibt : selectedFlight.sobt)"></div>
                        </div>
                        <div>
                            <div class="text-[10px] font-sans text-aviation-700 dark:text-aviation-300 font-bold">Actual Block Time (AIBT/AOBT)</div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white font-mono" x-text="selectedFlight.actual_display || (selectedFlight.direction === 'ARRIVAL' ? selectedFlight.aibt : selectedFlight.aobt)"></div>
                        </div>
                    </div>

                    {{-- Capacity & Load --}}
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800">
                            <div class="text-[10px] font-sans text-slate-400">Capacity</div>
                            <div class="text-base font-bold text-slate-900 dark:text-white" x-text="selectedFlight.cap"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800">
                            <div class="text-[10px] font-sans text-slate-400">Load</div>
                            <div class="text-base font-bold text-slate-900 dark:text-white" x-text="selectedFlight.load"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800">
                            <div class="text-[10px] font-sans text-slate-400">Load Factor</div>
                            <div class="text-base font-bold text-aviation-600" x-text="selectedFlight.load_factor !== 'N/A' ? selectedFlight.load_factor + '%' : 'N/A'"></div>
                        </div>
                    </div>

                    {{-- Passenger & Weight Breakdown --}}
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-800 text-[11px] space-y-1">
                        <div>Pax: Adult <strong x-text="selectedFlight.adult"></strong> &bull; Child <strong x-text="selectedFlight.child"></strong> &bull; Infant <strong x-text="selectedFlight.infant"></strong></div>
                        <div>Transit <strong x-text="selectedFlight.transit"></strong> &bull; Transfer <strong x-text="selectedFlight.transfer"></strong> &bull; Crew <strong x-text="selectedFlight.crw"></strong> (Ex. Crew <strong x-text="selectedFlight.ex_crw"></strong>)</div>
                        <div>Cargo <strong x-text="selectedFlight.cargo_kg + ' KG'"></strong> &bull; Baggage <strong x-text="selectedFlight.baggage_kg + ' KG'"></strong> &bull; POS <strong x-text="selectedFlight.pos_kg + ' KG'"></strong></div>
                        <div>Stand <strong x-text="selectedFlight.stand"></strong> &bull; Runway <strong x-text="selectedFlight.runway"></strong></div>
                    </div>
                </div>
            </template>

            <div class="text-right pt-2 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="selectedFlight = null" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-200 transition">
                    Close Details
                </button>
            </div>
        </div>
    </div>

    {{-- FOOTER --}}
    <footer class="w-full border-t border-slate-200/80 dark:border-slate-800/80 bg-white/70 dark:bg-navy-900/70 backdrop-blur-md px-4 sm:px-8 py-3 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
        <div>SlotWaves Aviation Control Room &copy; {{ date('Y') }} • FDR Analytical Engine</div>
        <div class="flex items-center gap-2">
            <span>OASYS Operational Reporting System</span>
            <span>•</span>
            <span class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">LIVE TELEMETRY</span>
        </div>
    </footer>

</div>

@push('scripts')
<script>
// Module-level non-reactive store for Chart.js instances.
// IMPORTANT: Alpine.js Proxy wraps reactive data objects, which corrupts Chart.js
// internal state. Chart instances MUST be stored outside Alpine's reactive scope.
const _fdrCharts = {
    chart1: null,
    chart2: null,
    chart3: null,
    chartPax: null,
    fdrMovement: null,
};

function fdrDashboardController() {
    return {
        uploadId: {{ $upload->id }},
        activeTab: 'overview',
        reconciliationSubMode: 'apps',
        availableDates: @json($availableDates),
        sourceSummary: @json($sourceSummary),
        filters: {
            analysis_level: '{{ $analysisLevel }}',
            analysis_date: '{{ $filters['analysis_date'] }}',
            analysis_month: '{{ $analysisMonth }}',
            analysis_year: '{{ $analysisYear }}',
            airport: '{{ $filters['airport'] }}',
            leg: '{{ $filters['leg'] }}',
            operator: '{{ $filters['operator'] }}',
            traffic: '{{ $filters['traffic'] }}',
            data_type: '{{ $filters['data_type'] }}',
            realization: '{{ $filters['realization'] }}',
            flight_no: '{{ $filters['flight_no'] }}',
            suffix: '{{ $filters['suffix'] }}',
            start_date: '{{ $filters['start_date'] }}',
            end_date: '{{ $filters['end_date'] }}',
            report_mode: {{ $filters['report_mode'] }},
            search: '{{ $filters['search'] }}',
        },
        activeChips: @json($filterResult['active_chips']),
        counterText: '{{ $filterResult['counter_text'] }}',
        kpis: @json($analytics['kpis']),
        hourlyData: @json($analytics['hourly_charts']['hourly_data']),
        schedVsReal: @json($analytics['schedule_vs_realization']),
        paxAnalytics: @json($analytics['passenger_analytics']),
        airlineRoute: @json($analytics['airline_route']),
        groundOps: @json($analytics['ground_operations']),
        activeReconciliation: @json($filters['report_mode'] == 7 ? $analytics['reconciliation_apps'] : $analytics['reconciliation_edifly']),
        reconciliation: @json($filterResult['reconciliation']),
        flightRecords: @json($records),
        pagination: {
            current_page: 1,
            per_page: 50,
            total_pages: Math.ceil({{ $filterResult['filtered_count'] }} / 50),
            total: {{ $filterResult['filtered_count'] }}
        },
        selectedFlight: null,
        isFiltering: false,
        activeRequestId: 0, // Token to resolve rapid race conditions
        fdrChartMode: 'ALL', // ALL / DEP / ARR

        // NOTE: chartInstances is intentionally NOT in Alpine reactive data.
        // Chart.js instances cannot be stored as Alpine Proxy objects.
        // Use the module-level _fdrCharts variable instead.

        init() {
            this.$nextTick(() => {
                this.initCharts();
            });
        },

        setTab(tab) {
            this.activeTab = tab;
            if (tab === 'overview') {
                this.filters.report_mode = 1;
            } else if (tab === 'hourly') {
                this.filters.report_mode = 5;
            } else if (tab === 'load_factor') {
                this.filters.report_mode = 2;
            } else if (tab === 'comparison') {
                this.filters.report_mode = 3;
            } else if (tab === 'reconciliation') {
                this.filters.report_mode = (this.reconciliationSubMode === 'apps') ? 7 : 8;
            }
        },

        setAnalysisLevel(level) {
            if (this.sourceSummary.source_type === 'DAILY') {
                this.filters.analysis_level = 'DAILY';
                return;
            }
            this.filters.analysis_level = level;
            this.triggerFilter(1);
        },

        formatDateOption(d) {
            if (!d) return '';
            const parts = d.split('-');
            if (parts.length === 3) {
                return `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
            return d;
        },

        formatDateHeader(d) {
            if (!d) return 'ALL DATES';
            const months = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
            const parts = d.split('-');
            if (parts.length === 3) {
                const day = parts[2];
                const mIdx = parseInt(parts[1], 10) - 1;
                const year = parts[0];
                return `${day} ${months[mIdx] || ''} ${year}`;
            }
            return d;
        },

        formatMonthHeader(m) {
            if (!m) return '';
            const months = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
            const parts = m.split('-');
            if (parts.length >= 2) {
                const mIdx = parseInt(parts[1], 10) - 1;
                const year = parts[0];
                return `${months[mIdx] || parts[1]} ${year}`;
            }
            return m;
        },

        stepAnalysisDate(step) {
            if (this.sourceSummary.source_type === 'DAILY') {
                return; // Locked
            }
            if (!this.availableDates || this.availableDates.length === 0) return;
            let idx = this.availableDates.indexOf(this.filters.analysis_date);
            if (idx === -1) idx = 0;
            let newIdx = idx + step;
            if (newIdx >= 0 && newIdx < this.availableDates.length) {
                this.filters.analysis_date = this.availableDates[newIdx];
                this.triggerFilter();
            }
        },

        initCharts() {
            if (!window.Chart) return;

            const hours = this.hourlyData.map(h => h.hour_label);

            // ── Color constants (Departure = Blue family, Arrival = Yellow/Amber family) ──
            // Departure: Domestic = dark blue #1D4ED8, International = light blue #60A5FA
            // Arrival: Domestic = dark amber #CA8A04, International = light yellow #FDE047

            // Chart 1: Arrival-Departure Movement (24h) — GROUPED BAR, no Runway Capacity
            const ctx1 = document.getElementById('mentorChart1Movement');
            if (ctx1) {
                _fdrCharts.chart1 = new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: hours,
                        datasets: [
                            {
                                label: 'Plan (PPRP)',
                                data: this.hourlyData.map(h => h.total_plan),
                                backgroundColor: '#FDBA74',
                                borderColor: '#FB923C',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                            {
                                label: 'Irregular Flt',
                                data: this.hourlyData.map(h => h.total_irregular),
                                backgroundColor: '#D97706',
                                borderColor: '#B45309',
                                borderWidth: 1,
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: (ctx) => `Hour: ${ctx[0].label}`,
                                    label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y} movements`
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
                        }
                    }
                });
            }

            // Chart 2: Departure Movement (24h) — Domestic DEP dark blue, Intl DEP light blue
            const ctx2 = document.getElementById('mentorChart2Departure');
            if (ctx2) {
                _fdrCharts.chart2 = new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: hours,
                        datasets: [
                            {
                                label: 'Dep Domestic',
                                data: this.hourlyData.map(h => h.dep_dom_plan ?? h.dep_plan),
                                backgroundColor: '#1D4ED8',
                                borderColor: '#1E40AF',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Dep International',
                                data: this.hourlyData.map(h => h.dep_int_plan ?? 0),
                                backgroundColor: '#60A5FA',
                                borderColor: '#3B82F6',
                                borderWidth: 1,
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: (ctx) => `Hour: ${ctx[0].label}`,
                                    label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y} A/C`
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
                        }
                    }
                });
            }

            // Chart 3: Arrival Movement (24h) — Domestic ARR dark amber, Intl ARR light yellow
            const ctx3 = document.getElementById('mentorChart3Arrival');
            if (ctx3) {
                _fdrCharts.chart3 = new Chart(ctx3, {
                    type: 'bar',
                    data: {
                        labels: hours,
                        datasets: [
                            {
                                label: 'Arr Domestic',
                                data: this.hourlyData.map(h => h.arr_dom_plan ?? h.arr_plan),
                                backgroundColor: '#CA8A04',
                                borderColor: '#A16207',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Arr International',
                                data: this.hourlyData.map(h => h.arr_int_plan ?? 0),
                                backgroundColor: '#FDE047',
                                borderColor: '#EAB308',
                                borderWidth: 1,
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: (ctx) => `Hour: ${ctx[0].label}`,
                                    label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y} A/C`
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
                        }
                    }
                });
            }

            // Passenger Composition Chart (Bar breakdown for single analysis date)
            const ctxPax = document.getElementById('passengerTrendChart');
            if (ctxPax) {
                const comp = this.paxAnalytics.composition || { adult: 0, child: 0, infant: 0, transit: 0, transfer: 0 };
                const hasBreakdown = (comp.adult + comp.child + comp.infant + comp.transit + comp.transfer) > 0;

                const paxLabels = hasBreakdown 
                    ? ['Adult', 'Child', 'Infant', 'Transit', 'Transfer']
                    : ['Total Pax'];
                const paxValues = hasBreakdown
                    ? [comp.adult, comp.child, comp.infant, comp.transit, comp.transfer]
                    : [this.paxAnalytics.total_load || this.kpis.total_passengers || 0];
                const paxColors = hasBreakdown
                    ? ['#0284C7', '#38BDF8', '#7DD3FC', '#F59E0B', '#10B981']
                    : ['#0284C7'];

                _fdrCharts.chartPax = new Chart(ctxPax, {
                    type: 'bar',
                    data: {
                        labels: paxLabels,
                        datasets: [
                            {
                                label: 'Passengers',
                                data: paxValues,
                                backgroundColor: paxColors,
                                borderRadius: 5,
                                borderWidth: 1,
                                borderColor: paxColors,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ` ${ctx.parsed.y.toLocaleString()} passengers`
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
                        }
                    }
                });
            }

            // Flight Movement Chart (ALL/DEP/ARR) - Section 4B
            this.renderFlightMovementChart();
        },

        renderFlightMovementChart() {
            if (!window.Chart) return;
            const ctx = document.getElementById('fdrMovementChart');
            if (!ctx) return;

            const hours = this.hourlyData.map(h => h.hour_label);
            const mode = this.fdrChartMode;

            // Color constants (MANDATORY — consistent across chart, legend, tooltip)
            const C = {
                DEP_DOM: '#1D4ED8',   // Departure Domestic: dark blue
                DEP_INT: '#60A5FA',   // Departure International: light blue
                ARR_DOM: '#CA8A04',   // Arrival Domestic: dark amber
                ARR_INT: '#FDE047',   // Arrival International: light yellow
            };

            let datasets = [];

            if (mode === 'DEP' || mode === 'ALL') {
                datasets.push({
                    label: 'Dep Domestic',
                    data: this.hourlyData.map(h => h.dep_dom_plan ?? h.dep_plan),
                    backgroundColor: C.DEP_DOM,
                    borderColor: '#1E40AF',
                    borderWidth: 1,
                    borderRadius: 3,
                });
                datasets.push({
                    label: 'Dep International',
                    data: this.hourlyData.map(h => h.dep_int_plan ?? 0),
                    backgroundColor: C.DEP_INT,
                    borderColor: '#3B82F6',
                    borderWidth: 1,
                    borderRadius: 3,
                });
            }

            if (mode === 'ARR' || mode === 'ALL') {
                datasets.push({
                    label: 'Arr Domestic',
                    data: this.hourlyData.map(h => h.arr_dom_plan ?? h.arr_plan),
                    backgroundColor: C.ARR_DOM,
                    borderColor: '#A16207',
                    borderWidth: 1,
                    borderRadius: 3,
                });
                datasets.push({
                    label: 'Arr International',
                    data: this.hourlyData.map(h => h.arr_int_plan ?? 0),
                    backgroundColor: C.ARR_INT,
                    borderColor: '#EAB308',
                    borderWidth: 1,
                    borderRadius: 3,
                });
            }

            // Destroy existing instance first
            if (_fdrCharts.fdrMovement) {
                _fdrCharts.fdrMovement.destroy();
                _fdrCharts.fdrMovement = null;
            }

            _fdrCharts.fdrMovement = new Chart(ctx, {
                type: 'bar',
                data: { labels: hours, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                font: { size: 11 },
                                padding: 12,
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: (ctx) => `Hour: ${ctx[0].label}`,
                                label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y} A/C`
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { precision: 0 } }
                    }
                }
            });
        },

        async triggerFilter(page = 1) {
            this.isFiltering = true;
            this.pagination.current_page = page;
            const currentReqId = ++this.activeRequestId;

            const params = new URLSearchParams({
                analysis_level: this.filters.analysis_level,
                analysis_date: this.filters.analysis_date,
                analysis_month: this.filters.analysis_month,
                analysis_year: this.filters.analysis_year,
                airport: this.filters.airport,
                leg: this.filters.leg,
                operator: this.filters.operator,
                traffic: this.filters.traffic,
                data_type: this.filters.data_type,
                realization: this.filters.realization,
                flight_no: this.filters.flight_no,
                suffix: this.filters.suffix,
                start_date: this.filters.start_date,
                end_date: this.filters.end_date,
                report_mode: this.filters.report_mode,
                search: this.filters.search,
                page: page,
                v: currentReqId
            });

            try {
                const res = await fetch(`/fdr/${this.uploadId}/filter?` + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) throw new Error('Filter query failed.');
                const data = await res.json();

                // Race condition protection: Discard if this response isn't for the latest request
                if (parseInt(data.version) !== this.activeRequestId) {
                    return;
                }

                // Update reactive state
                this.counterText = data.counter_text;
                this.activeChips = data.active_chips;
                this.kpis = data.kpis;
                this.hourlyData = data.hourly_charts.hourly_data;
                this.schedVsReal = data.sched_vs_real;
                this.paxAnalytics = data.pax_analytics;
                this.airlineRoute = data.airline_route;
                this.groundOps = data.ground_ops;
                this.activeReconciliation = (this.filters.report_mode == 7) ? data.reconciliation_apps : data.reconciliation_edifly;
                if (data.reconciliation) {
                    this.reconciliation = data.reconciliation;
                }
                this.flightRecords = data.records;
                this.pagination = data.pagination;

                // Update charts live
                this.updateChartsLive();

            } catch (err) {
                console.error(err);
            } finally {
                if (currentReqId === this.activeRequestId) {
                    this.isFiltering = false;
                }
            }
        },

        updateChartsLive() {
            if (!_fdrCharts.chart1) return;

            // Chart 1: All movement (no Runway Capacity — removed)
            _fdrCharts.chart1.data.datasets[0].data = this.hourlyData.map(h => h.total_plan);
            _fdrCharts.chart1.data.datasets[1].data = this.hourlyData.map(h => h.total_irregular);
            _fdrCharts.chart1.update();

            // Chart 2: Departure (Domestic = dark blue, International = light blue)
            if (_fdrCharts.chart2) {
                _fdrCharts.chart2.data.datasets[0].data = this.hourlyData.map(h => h.dep_dom_plan ?? h.dep_plan);
                _fdrCharts.chart2.data.datasets[1].data = this.hourlyData.map(h => h.dep_int_plan ?? 0);
                _fdrCharts.chart2.update();
            }

            // Chart 3: Arrival (Domestic = dark amber, International = light yellow)
            if (_fdrCharts.chart3) {
                _fdrCharts.chart3.data.datasets[0].data = this.hourlyData.map(h => h.arr_dom_plan ?? h.arr_plan);
                _fdrCharts.chart3.data.datasets[1].data = this.hourlyData.map(h => h.arr_int_plan ?? 0);
                _fdrCharts.chart3.update();
            }

            // Pax Composition Chart
            if (_fdrCharts.chartPax && this.paxAnalytics.composition) {
                const comp = this.paxAnalytics.composition;
                const hasBreakdown = (comp.adult + comp.child + comp.infant + comp.transit + comp.transfer) > 0;
                if (hasBreakdown) {
                    _fdrCharts.chartPax.data.labels = ['Adult', 'Child', 'Infant', 'Transit', 'Transfer'];
                    _fdrCharts.chartPax.data.datasets[0].data = [comp.adult, comp.child, comp.infant, comp.transit, comp.transfer];
                    _fdrCharts.chartPax.data.datasets[0].backgroundColor = ['#0284C7', '#38BDF8', '#7DD3FC', '#F59E0B', '#10B981'];
                    _fdrCharts.chartPax.data.datasets[0].borderColor = ['#0284C7', '#38BDF8', '#7DD3FC', '#F59E0B', '#10B981'];
                } else {
                    _fdrCharts.chartPax.data.labels = ['Total Pax'];
                    _fdrCharts.chartPax.data.datasets[0].data = [this.paxAnalytics.total_load || this.kpis.total_passengers || 0];
                    _fdrCharts.chartPax.data.datasets[0].backgroundColor = ['#0284C7'];
                    _fdrCharts.chartPax.data.datasets[0].borderColor = ['#0284C7'];
                }
                _fdrCharts.chartPax.update();
            }

            // Flight Movement Chart (ALL/DEP/ARR) — re-render with latest data
            this.renderFlightMovementChart();
        },

        changePage(p) {
            if (p >= 1 && p <= this.pagination.total_pages) {
                this.triggerFilter(p);
            }
        },

        removeFilter(key) {
            if (key === 'analysis_date') {
                if (this.availableDates && this.availableDates.length > 0) {
                    this.filters.analysis_date = this.availableDates[0];
                }
            }
            if (key === 'analysis_level') {
                this.filters.analysis_level = 'DAILY';
            }
            if (key === 'airport') this.filters.airport = 'ALL';
            if (key === 'leg') this.filters.leg = 'ALL';
            if (key === 'operator') this.filters.operator = 'ALL';
            if (key === 'traffic') this.filters.traffic = 'ALL';
            if (key === 'realization') this.filters.realization = 'ALL';
            if (key === 'flight_no') this.filters.flight_no = '';
            if (key === 'suffix') this.filters.suffix = '';
            if (key === 'date_range') {
                this.filters.start_date = '';
                this.filters.end_date = '';
            }
            if (key === 'search') this.filters.search = '';
            this.triggerFilter();
        },

        clearAllFilters() {
            if (this.availableDates && this.availableDates.length > 0) {
                this.filters.analysis_date = this.availableDates[0];
            }
            this.filters.analysis_level = 'DAILY';
            this.filters.airport = 'ALL';
            this.filters.leg = 'ALL';
            this.filters.operator = 'ALL';
            this.filters.traffic = 'ALL';
            this.filters.flight_no = '';
            this.filters.suffix = '';
            this.filters.search = '';
            this.triggerFilter();
        },

        openFlightModal(flight) {
            this.selectedFlight = flight;
        },

        getExportUrl(type) {
            const params = new URLSearchParams({
                analysis_level: this.filters.analysis_level,
                analysis_date: this.filters.analysis_date,
                analysis_month: this.filters.analysis_month,
                analysis_year: this.filters.analysis_year,
                airport: this.filters.airport,
                leg: this.filters.leg,
                operator: this.filters.operator,
                traffic: this.filters.traffic,
                data_type: this.filters.data_type,
                realization: this.filters.realization,
                flight_no: this.filters.flight_no,
                suffix: this.filters.suffix,
                start_date: this.filters.start_date,
                end_date: this.filters.end_date,
                report_mode: this.filters.report_mode,
                search: this.filters.search,
            });
            return `/fdr/${this.uploadId}/export/${type}?` + params.toString();
        }
    };
}
</script>
@endpush
@endsection
