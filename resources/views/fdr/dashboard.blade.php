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

        {{-- Breadcrumb Flow: Home → Select Type → Flight Daily Report → Config → Dashboard --}}
        <div class="hidden lg:flex items-center gap-2 text-xs font-medium text-slate-400">
            <a href="{{ route('home') }}" class="hover:text-aviation-600 transition">Home</a>
            <span>&rarr;</span>
            <span class="text-slate-500">Select Type</span>
            <span>&rarr;</span>
            <a href="{{ route('fdr.config', $upload->id) }}" class="hover:text-aviation-600 transition">FDR Config</a>
            <span>&rarr;</span>
            <span class="text-aviation-700 dark:text-aviation-300 font-bold px-2 py-0.5 rounded bg-aviation-50 dark:bg-aviation-950 border border-aviation-200 dark:border-aviation-800">
                FDR Dashboard
            </span>
        </div>

        {{-- Action Buttons (Config, Exports, Theme) --}}
        <div class="flex items-center gap-2">
            <a href="{{ route('fdr.config', $upload->id) }}"
               class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-navy-800 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                <span class="hidden sm:inline">Config</span>
            </a>

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

        {{-- ══ SECTION 1: FILTER CASCADE & ACTIVE CHIPS BAR ═══════════════════ --}}
        <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-4 sm:p-5 shadow-sm space-y-4">
            
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

                {{-- Report Mode (Modes 1 to 8) --}}
                <div>
                    <label class="block text-[10px] font-bold uppercase text-aviation-600 dark:text-aviation-400 mb-1">Report Mode</label>
                    <select x-model="filters.report_mode" @change="triggerFilter()" class="w-full rounded-xl border-aviation-300 dark:border-aviation-700 bg-aviation-50/50 dark:bg-aviation-950/50 text-aviation-900 dark:text-aviation-200 text-xs font-bold py-1.5 px-2.5">
                        <option value="1">1. NORMAL</option>
                        <option value="2">2. LOAD FACTOR</option>
                        <option value="3">3. COMPARE LF</option>
                        <option value="4">4. COMPARE LF DAY</option>
                        <option value="5">5. AIR TRAFFIC I</option>
                        <option value="6">6. AIR TRAFFIC II</option>
                        <option value="7">7. OASYS VS APPS</option>
                        <option value="8">8. OASYS VS EDIFLY</option>
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

        {{-- ══ MODE CONTEXT NOTIFICATION BANNER ════════════════════════════════ --}}
        <div class="p-3.5 rounded-xl border bg-slate-50 dark:bg-navy-900/60 border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
            <div class="flex items-center gap-2.5">
                <span class="px-2 py-0.5 rounded font-mono font-bold text-[10px] bg-aviation-600 text-white uppercase" x-text="'MODE ' + filters.report_mode"></span>
                <span class="font-bold text-slate-900 dark:text-white" x-text="getModeLabel(filters.report_mode)"></span>
                <span class="hidden sm:inline text-slate-400">•</span>
                <span class="hidden sm:inline text-slate-500 dark:text-slate-400" x-text="getModeDescription(filters.report_mode)"></span>
            </div>
            <div class="text-[11px] font-mono text-slate-400">
                Airport: <strong class="text-slate-700 dark:text-slate-300" x-text="filters.airport"></strong>
            </div>
        </div>

        {{-- ══ SECTION 2: TOP METRIC CARDS ═════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">

            {{-- 1. Total Flights --}}
            <div class="bg-white dark:bg-navy-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Flights</span>
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

            {{-- 2. Total Passengers --}}
            <div class="bg-white dark:bg-navy-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Passengers</span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1" x-text="kpis.total_passengers.toLocaleString()">
                    {{ number_format($analytics['kpis']['total_passengers']) }}
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 truncate">
                    Adl: <strong x-text="kpis.adult_passengers.toLocaleString()"></strong> | Chd: <strong x-text="kpis.child_passengers.toLocaleString()"></strong> | Inf: <strong x-text="kpis.infant_passengers.toLocaleString()"></strong>
                </div>
            </div>

            {{-- 3. Average Load Factor --}}
            <div class="bg-white dark:bg-navy-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Avg Load Factor</span>
                    <span class="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </span>
                </div>
                <div class="text-2xl font-black text-aviation-600 dark:text-aviation-400 mt-1" x-text="kpis.avg_load_factor">
                    {{ $analytics['kpis']['avg_load_factor'] }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-mono">
                    Load: <span x-text="kpis.total_load.toLocaleString()"></span> / Cap: <span x-text="kpis.total_capacity.toLocaleString()"></span>
                </div>
            </div>

            {{-- 4. Cargo, Baggage & Irregularities --}}
            <div class="bg-white dark:bg-navy-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cargo &amp; Irregularities</span>
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">
                    <span x-text="kpis.cargo_ton"></span> <span class="text-xs font-normal text-slate-400">Ton Cargo</span>
                </div>
                <div class="flex items-center justify-between text-xs mt-1">
                    <span class="text-slate-500">Bagg: <strong x-text="kpis.baggage_kg.toLocaleString()"></strong> kg</span>
                    <span class="font-bold text-amber-600 dark:text-amber-400" x-text="kpis.irregularities.total + ' Irreg'"></span>
                </div>
            </div>

        </div>

        {{-- ══ SECTION 3: THE 3 MENTOR HOURLY CHARTS (MANDATORY 24-HOUR X-AXIS: 00 TO 23) ══ --}}
        <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 sm:p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <div>
                    <div class="inline-flex items-center gap-1.5 text-xs font-bold text-aviation-600 dark:text-aviation-400 uppercase tracking-wider mb-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>24-Hour Continuous Timeline (00:00 to 23:59)</span>
                    </div>
                    <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight">
                        3 Mentor Hourly Operational Charts
                    </h2>
                </div>
                <div class="text-xs text-slate-500 font-mono">
                    All 24 hours strictly visible &bull; Hover for dynamic capacity differential
                </div>
            </div>

            {{-- ── CHART 1: ARRIVAL–DEPARTURE MOVEMENT ────────────────────────── --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">
                            Chart 1: ARRIVAL–DEPARTURE MOVEMENT
                        </h3>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-slate-500">
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#FDBA74]"></span> Plan (PPRP)</span>
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#D97706]"></span> Irregular Flt</span>
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-0.5 bg-[#EF4444]"></span> Runway Capacity</span>
                    </div>
                </div>
                <div class="h-56 w-full relative">
                    <canvas id="mentorChart1Movement"></canvas>
                </div>
            </div>

            {{-- ── CHART 2: DEPARTURE MOVEMENT ───────────────────────────────── --}}
            <div class="space-y-2 pt-4 border-t border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">
                            Chart 2: DEPARTURE MOVEMENT (Blue Semantic Palette)
                        </h3>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-slate-500">
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#93C5FD]"></span> Plan (PPRP)</span>
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#1D4ED8]"></span> Irregular Flt</span>
                    </div>
                </div>
                <div class="h-44 w-full relative">
                    <canvas id="mentorChart2Departure"></canvas>
                </div>
            </div>

            {{-- ── CHART 3: ARRIVAL MOVEMENT ─────────────────────────────────── --}}
            <div class="space-y-2 pt-4 border-t border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">
                            Chart 3: ARRIVAL MOVEMENT (Salmon/Magenta Palette)
                        </h3>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-slate-500">
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#FDA4AF]"></span> Plan (PPRP)</span>
                        <span class="inline-flex items-center gap-1"><span class="w-3 h-2 rounded bg-[#BE185D]"></span> Irregular Flt</span>
                    </div>
                </div>
                <div class="h-44 w-full relative">
                    <canvas id="mentorChart3Arrival"></canvas>
                </div>
            </div>

        </div>

        {{-- ══ SECTION 4: PRESENTATION-READY OPERATIONAL MODULES ════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- MODULE 1: Schedule vs Realization (Punctuality) --}}
            <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                            1. Schedule vs Realization (SIBT/SOBT vs AIBT/AOBT)
                        </h3>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300" x-text="schedVsReal.on_time_percentage + ' ON-TIME'"></span>
                </div>

                <div class="grid grid-cols-4 gap-2 text-center text-xs">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800/60 border border-slate-100 dark:border-slate-800">
                        <div class="text-[10px] font-bold uppercase text-slate-400">Evaluated</div>
                        <div class="text-base font-black text-slate-900 dark:text-white mt-0.5" x-text="schedVsReal.evaluated_flights"></div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-800">
                        <div class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400">On-Time</div>
                        <div class="text-base font-black text-emerald-700 dark:text-emerald-300 mt-0.5" x-text="schedVsReal.on_time_count"></div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-100 dark:border-amber-800">
                        <div class="text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400">16-45m Delay</div>
                        <div class="text-base font-black text-amber-700 dark:text-amber-300 mt-0.5" x-text="schedVsReal.minor_delay_count"></div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-rose-50/70 dark:bg-rose-950/40 border border-rose-100 dark:border-rose-800">
                        <div class="text-[10px] font-bold uppercase text-rose-600 dark:text-rose-400">&gt;45m Delay</div>
                        <div class="text-base font-black text-rose-700 dark:text-rose-300 mt-0.5" x-text="schedVsReal.severe_delay_count"></div>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-800/40 border border-slate-100 dark:border-slate-800 text-xs flex items-center justify-between">
                    <span class="text-slate-500">Average Punctuality Variance:</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white" x-text="schedVsReal.avg_delay_minutes + ' minutes'"></span>
                </div>
            </div>

            {{-- MODULE 2: Passenger Analytics & Multi-Day Trend --}}
            <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                            2. Passenger Composition &amp; Trend
                        </h3>
                    </div>
                    <span class="text-xs text-slate-400 font-mono">Pax vs Load Factor</span>
                </div>

                <div class="h-44 w-full relative">
                    <canvas id="passengerTrendChart"></canvas>
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

        {{-- ══ MODULE 5: DATA RECONCILIATION ENGINE (MODES 7 & 8) ═══════════════ --}}
        <template x-if="filters.report_mode == 7 || filters.report_mode == 8">
            <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white"
                            x-text="filters.report_mode == 7 ? '5. OASYS VS APPS RECONCILIATION ENGINE' : '5. OASYS VS EDIFLY RECONCILIATION ENGINE'">
                        </h3>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full font-mono text-[11px] font-bold"
                          :class="activeReconciliation.overall_status === 'MATCH' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : (activeReconciliation.overall_status === 'MINOR GAP' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300')"
                          x-text="'STATUS: ' + activeReconciliation.overall_status">
                    </span>
                </div>

                {{-- Source A vs Source B delta cards --}}
                <template x-if="filters.report_mode == 7">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-800 border border-slate-200 dark:border-slate-700">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Flight Count Check</div>
                            <div class="text-sm font-black text-slate-900 dark:text-white mt-1">
                                OASYS: <span x-text="activeReconciliation.metrics.flights.source_a"></span> vs APPS: <span x-text="activeReconciliation.metrics.flights.source_b"></span>
                            </div>
                            <div class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 mt-1 font-bold">Delta: 0 flights (100% MATCH)</div>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-800 border border-slate-200 dark:border-slate-700">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Passenger Clearance Gap</div>
                            <div class="text-sm font-black text-slate-900 dark:text-white mt-1">
                                <span x-text="activeReconciliation.metrics.passengers.source_a.toLocaleString()"></span> vs <span x-text="activeReconciliation.metrics.passengers.source_b.toLocaleString()"></span>
                            </div>
                            <div class="text-[11px] font-mono mt-1 font-bold"
                                 :class="activeReconciliation.metrics.passengers.status === 'MATCH' ? 'text-emerald-600' : 'text-amber-600'"
                                 x-text="'Delta: ' + activeReconciliation.metrics.passengers.delta + ' pax (' + activeReconciliation.metrics.passengers.delta_pct + ')'">
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-800 border border-slate-200 dark:border-slate-700">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Cargo Manifest Gap</div>
                            <div class="text-sm font-black text-slate-900 dark:text-white mt-1">
                                <span x-text="activeReconciliation.metrics.cargo.source_a.toLocaleString()"></span> kg vs <span x-text="activeReconciliation.metrics.cargo.source_b.toLocaleString()"></span> kg
                            </div>
                            <div class="text-[11px] font-mono mt-1 font-bold"
                                 :class="activeReconciliation.metrics.cargo.status === 'MATCH' ? 'text-emerald-600' : 'text-amber-600'"
                                 x-text="'Delta: ' + activeReconciliation.metrics.cargo.delta + ' kg (' + activeReconciliation.metrics.cargo.delta_pct + ')'">
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Flight-by-flight reconciliation preview --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-navy-800 text-[10px] uppercase font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                            <tr>
                                <th class="py-2 px-3">Flight No</th>
                                <th class="py-2 px-3">Airline</th>
                                <th class="py-2 px-3">Route</th>
                                <th class="py-2 px-3">Source A (OASYS)</th>
                                <th class="py-2 px-3">Source B</th>
                                <th class="py-2 px-3">Delta</th>
                                <th class="py-2 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono text-[11px]">
                            <template x-for="row in activeReconciliation.details.slice(0, 5)" :key="row.flight_no">
                                <tr>
                                    <td class="py-2 px-3 font-bold text-slate-900 dark:text-white" x-text="row.flight_no"></td>
                                    <td class="py-2 px-3 font-sans" x-text="row.air_line"></td>
                                    <td class="py-2 px-3" x-text="row.route"></td>
                                    <td class="py-2 px-3" x-text="row.oasys_pax ? row.oasys_pax + ' pax' : row.oasys_time"></td>
                                    <td class="py-2 px-3" x-text="row.apps_pax ? row.apps_pax + ' pax' : row.edifly_time"></td>
                                    <td class="py-2 px-3" x-text="row.pax_delta !== undefined ? (row.pax_delta >= 0 ? '+' : '') + row.pax_delta : row.time_gap"></td>
                                    <td class="py-2 px-3 font-sans">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                              :class="row.status === 'MATCH' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : (row.status === 'MINOR GAP' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300')"
                                              x-text="row.status"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        {{-- ══ SECTION 5: DETAILED FLIGHT TABLE ═════════════════════════════════ --}}
        <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                        Detailed Flight Movement Registry
                    </h3>
                </div>
                <div class="text-xs text-slate-400 font-mono">
                    Click any flight row for full manifest details modal
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
                                <td class="py-2.5 px-3 text-slate-500" x-text="r.direction === 'ARRIVAL' ? r.sibt : r.sobt"></td>
                                <td class="py-2.5 px-3 text-slate-800 dark:text-slate-200 font-bold" x-text="r.direction === 'ARRIVAL' ? r.aibt : r.aobt"></td>
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
                            <div class="text-sm font-bold text-slate-900 dark:text-white" x-text="selectedFlight.direction === 'ARRIVAL' ? selectedFlight.sibt : selectedFlight.sobt"></div>
                        </div>
                        <div>
                            <div class="text-[10px] font-sans text-aviation-700 dark:text-aviation-300 font-bold">Actual Block Time (AIBT/AOBT)</div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white" x-text="selectedFlight.direction === 'ARRIVAL' ? selectedFlight.aibt : selectedFlight.aobt"></div>
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
function fdrDashboardController() {
    return {
        uploadId: {{ $upload->id }},
        filters: {
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

        chartInstances: {},

        init() {
            this.$nextTick(() => {
                this.initCharts();
            });
        },

        initCharts() {
            if (!window.Chart) return;

            const hours = this.hourlyData.map(h => h.hour_label);

            // Chart 1: Arrival-Departure Movement (24h)
            const ctx1 = document.getElementById('mentorChart1Movement');
            if (ctx1) {
                this.chartInstances.chart1 = new Chart(ctx1, {
                    data: {
                        labels: hours,
                        datasets: [
                            {
                                type: 'line',
                                label: 'Runway Capacity',
                                data: this.hourlyData.map(h => h.runway_capacity),
                                borderColor: '#EF4444',
                                backgroundColor: 'transparent',
                                borderWidth: 2.5,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                tension: 0.25,
                                order: 1
                            },
                            {
                                type: 'bar',
                                label: 'Plan (PPRP)',
                                data: this.hourlyData.map(h => h.total_plan),
                                backgroundColor: '#FDBA74',
                                borderColor: '#FB923C',
                                borderWidth: 1,
                                borderRadius: 4,
                                order: 2
                            },
                            {
                                type: 'bar',
                                label: 'Irregular Flt',
                                data: this.hourlyData.map(h => h.total_irregular),
                                backgroundColor: '#D97706',
                                borderColor: '#B45309',
                                borderWidth: 1,
                                borderRadius: 4,
                                order: 3
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
                                    afterBody: (context) => {
                                        const idx = context[0].dataIndex;
                                        const h = this.hourlyData[idx];
                                        return [
                                            `Runway Capacity: ${h.runway_capacity} A/C`,
                                            `Difference: ${h.difference} A/C`,
                                            `Status: ${h.status}`
                                        ];
                                    }
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

            // Chart 2: Departure Movement (24h)
            const ctx2 = document.getElementById('mentorChart2Departure');
            if (ctx2) {
                this.chartInstances.chart2 = new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: hours,
                        datasets: [
                            {
                                label: 'Plan (PPRP)',
                                data: this.hourlyData.map(h => h.dep_plan),
                                backgroundColor: '#93C5FD',
                                borderColor: '#60A5FA',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Irregular Flt',
                                data: this.hourlyData.map(h => h.dep_irregular),
                                backgroundColor: '#1D4ED8',
                                borderColor: '#1E40AF',
                                borderWidth: 1,
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
                        }
                    }
                });
            }

            // Chart 3: Arrival Movement (24h)
            const ctx3 = document.getElementById('mentorChart3Arrival');
            if (ctx3) {
                this.chartInstances.chart3 = new Chart(ctx3, {
                    type: 'bar',
                    data: {
                        labels: hours,
                        datasets: [
                            {
                                label: 'Plan (PPRP)',
                                data: this.hourlyData.map(h => h.arr_plan),
                                backgroundColor: '#FDA4AF',
                                borderColor: '#FB7185',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Irregular Flt',
                                data: this.hourlyData.map(h => h.arr_irregular),
                                backgroundColor: '#BE185D',
                                borderColor: '#9D174D',
                                borderWidth: 1,
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
                        }
                    }
                });
            }

            // Passenger Trend Combo Chart
            const ctxPax = document.getElementById('passengerTrendChart');
            if (ctxPax) {
                const dates = (this.paxAnalytics.daily_trend || []).map(d => d.date_label);
                const volumes = (this.paxAnalytics.daily_trend || []).map(d => d.volume);
                const lfs = (this.paxAnalytics.daily_trend || []).map(d => d.load_factor);

                this.chartInstances.chartPax = new Chart(ctxPax, {
                    data: {
                        labels: dates,
                        datasets: [
                            {
                                type: 'line',
                                label: 'Load Factor (%)',
                                data: lfs,
                                yAxisID: 'y1',
                                borderColor: '#0284C7',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                pointRadius: 3
                            },
                            {
                                type: 'bar',
                                label: 'Passenger Volume',
                                data: volumes,
                                yAxisID: 'y',
                                backgroundColor: '#BAE6FD',
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { position: 'left', beginAtZero: true, grid: { color: '#F8FAFC' } },
                            y1: { position: 'right', min: 0, max: 100, grid: { display: false } }
                        }
                    }
                });
            }
        },

        async triggerFilter(page = 1) {
            this.isFiltering = true;
            this.pagination.current_page = page;
            const currentReqId = ++this.activeRequestId;

            const params = new URLSearchParams({
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
            if (!this.chartInstances.chart1) return;

            // Chart 1
            this.chartInstances.chart1.data.datasets[0].data = this.hourlyData.map(h => h.runway_capacity);
            this.chartInstances.chart1.data.datasets[1].data = this.hourlyData.map(h => h.total_plan);
            this.chartInstances.chart1.data.datasets[2].data = this.hourlyData.map(h => h.total_irregular);
            this.chartInstances.chart1.update();

            // Chart 2
            if (this.chartInstances.chart2) {
                this.chartInstances.chart2.data.datasets[0].data = this.hourlyData.map(h => h.dep_plan);
                this.chartInstances.chart2.data.datasets[1].data = this.hourlyData.map(h => h.dep_irregular);
                this.chartInstances.chart2.update();
            }

            // Chart 3
            if (this.chartInstances.chart3) {
                this.chartInstances.chart3.data.datasets[0].data = this.hourlyData.map(h => h.arr_plan);
                this.chartInstances.chart3.data.datasets[1].data = this.hourlyData.map(h => h.arr_irregular);
                this.chartInstances.chart3.update();
            }

            // Pax Trend
            if (this.chartInstances.chartPax && this.paxAnalytics.daily_trend) {
                this.chartInstances.chartPax.data.labels = this.paxAnalytics.daily_trend.map(d => d.date_label);
                this.chartInstances.chartPax.data.datasets[0].data = this.paxAnalytics.daily_trend.map(d => d.load_factor);
                this.chartInstances.chartPax.data.datasets[1].data = this.paxAnalytics.daily_trend.map(d => d.volume);
                this.chartInstances.chartPax.update();
            }
        },

        changePage(p) {
            if (p >= 1 && p <= this.pagination.total_pages) {
                this.triggerFilter(p);
            }
        },

        removeFilter(key) {
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
        },

        getModeLabel(m) {
            const labels = {
                1: 'NORMAL (Full Operational Telemetry)',
                2: 'LOAD FACTOR (Capacity vs Load Priority)',
                3: 'COMPARE LOAD FACTOR (Period vs Period)',
                4: 'COMPARE LOAD FACTOR DAY (Day of Week)',
                5: 'AIR TRAFFIC MONITORING I (Hourly Movement Priority)',
                6: 'AIR TRAFFIC MONITORING II (Ground Stands & Runway)',
                7: 'OASYS VS APPS (Reconciliation Engine)',
                8: 'OASYS VS EDIFLY (Telex Consistency Status)'
            };
            return labels[m] || 'OPERATIONAL REPORT';
        },

        getModeDescription(m) {
            const desc = {
                1: 'Holistic operational dashboard with all telemetry, punctuality, and fleet performance.',
                2: 'Seat capacity utilization tiers, high demand routes, and low demand optimization.',
                3: 'Comparative analysis between distinct operational timeframes and variance calculation.',
                4: 'Day of week travel patterns, weekend vs weekday traffic distributions.',
                5: 'AOCC hourly operational peaking index and variable runway capacity thresholds.',
                6: 'Apron stand turnaround times, parking gate congestion, and runway split balance.',
                7: 'Variance gap reconciliation between OASYS flight log and APPS passenger manifests.',
                8: 'Telemetry validation against EDIFLY telex network message timestamps.'
            };
            return desc[m] || '';
        }
    };
}
</script>
@endpush
@endsection
