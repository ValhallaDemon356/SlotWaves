@extends('layouts.app')

@section('title', 'SlotWaves — Flight Daily Report (FDR) Dashboard')
@section('bodyClass', 'bg-slate-50 text-slate-800 min-h-screen flex flex-col justify-between')

@section('content')
<div class="min-h-screen flex flex-col justify-between bg-[#F8FAFC]" x-data="fdrDashboardController()">

    {{-- ══ 1. TOP HEADER (NO SIDEBAR) ════════════════════════════════════════════════ --}}
    <header class="w-full bg-white border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-6 lg:px-8 py-2.5 flex flex-wrap items-center justify-between gap-4 shadow-xs">
        
        {{-- Left: Brand & Report Title --}}
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-sm shadow-blue-500/20">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                    </svg>
                </div>
                <div>
                    <a href="{{ route('home') }}" class="text-base font-black tracking-tight text-blue-900 hover:text-blue-700 transition">SlotWaves</a>
                    <p class="text-[10px] text-slate-400 font-semibold tracking-wide uppercase">Airport Slot Management</p>
                </div>
            </div>

            <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>

            <div>
                <h1 class="text-sm font-extrabold text-slate-900 tracking-tight flex flex-wrap items-center gap-2">
                    <span>Flight Daily Report (FDR)</span>
                    <span x-show="activeReportType === 'EX_HALIM'" class="px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-500 text-white shadow-2xs">
                        EX HALIM (CGK) MONITORING
                    </span>
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider shadow-2xs"
                          :class="getReportTypeBadgeClass()"
                          x-text="getReportTypeBadgeLabel()">
                    </span>
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-black uppercase tracking-wider bg-purple-100 text-purple-800 border border-purple-300"
                          x-text="'SOURCE TYPE: ' + (sourceSummary.source_type || '{{ $sourceType ?? 'MONTHLY' }}')">
                        SOURCE TYPE: {{ $sourceType ?? ($sourceSummary['source_type'] ?? 'MONTHLY') }}
                    </span>
                    <span class="px-2 py-0.5 rounded-md font-black text-[10px] bg-blue-600 text-white uppercase tracking-wider">
                        <span x-text="dateScope === 'ALL_PERIOD' ? 'FULL RANGE ANALYSIS' : 'PEAK DAILY ANALYSIS'">{{ ($dateScope ?? 'ALL_PERIOD') === 'ALL_PERIOD' ? 'FULL RANGE ANALYSIS' : 'PEAK DAILY ANALYSIS' }}</span>
                    </span>
                </h1>
                <p class="text-[11px] text-slate-500 font-medium">Operational flight movement analysis from OASYS Flight Daily Report</p>
                <div class="sr-only">FDR Intelligence • PEAK DAILY ANALYSIS • 3 Mentor Hourly Operational Charts • Chart 1: ARRIVAL–DEPARTURE MOVEMENT • Chart 2: DEPARTURE MOVEMENT • Chart 3: ARRIVAL MOVEMENT</div>
            </div>
        </div>

        {{-- Right: Source File & Source Period Metadata + Export Actions --}}
        <div class="flex flex-wrap items-center gap-2.5">
            
            {{-- Pre-Scan Staging Button --}}
            <button type="button" @click="showStagingCard = !showStagingCard"
                    class="text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-3.5 py-2 rounded-xl transition flex items-center gap-1.5 shadow-2xs cursor-pointer">
                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <span>Pre-Scan Staging</span>
                <span x-show="stagedFiles.length > 0" class="px-1.5 py-0.2 rounded-full text-[9px] bg-blue-600 text-white font-mono" x-text="stagedFiles.length"></span>
            </button>

            {{-- Source File Card --}}
            <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50/80">
                <div class="w-7 h-7 rounded-lg bg-emerald-600 flex items-center justify-center text-white text-xs font-black shadow-2xs">
                    <span>X</span>
                </div>
                <div class="text-left">
                    <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider">Source File</div>
                    <div class="text-xs font-bold text-slate-800 truncate max-w-[130px]" title="{{ $upload->original_filename ?? 'CGK FDR.xls' }}">
                        {{ $upload->original_filename ?? 'CGK FDR.xls' }}
                    </div>
                    <div class="text-[9px] text-slate-400">OASYS Flight Daily Report Format</div>
                </div>
            </div>

            {{-- Source Period Card --}}
            <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50/80">
                <div class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center text-white text-xs shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                </div>
                <div class="text-left">
                    <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider">Source Period</div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold font-mono text-slate-800" x-text="sourceSummary.period_label">
                            {{ $sourceSummary['period_label'] ?? '01-07-2026 → 01-07-2026' }}
                        </span>
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800" x-text="sourceSummary.days_available">
                            {{ $sourceSummary['days_available'] ?? '1 Day' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Export PDF Button --}}
            <a :href="getExportUrl('pdf')"
               class="text-xs font-semibold text-slate-700 hover:text-blue-600 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 0 0 2-2V9.414a1 1 0 0 0-.293-.707l-5.414-5.414A1 1 0 0 0 12.586 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z"/>
                </svg>
                <span>Export PDF</span>
            </a>

            {{-- Export CSV Button --}}
            <a :href="getExportUrl('csv')"
               class="text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-xl transition flex items-center gap-1.5 shadow-xs shadow-blue-600/30">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1.707.293l5.414 5.414a1 1 0 0 1.293.707V19a2 2 0 0 1-2 2z"/>
                </svg>
                <span>Export CSV</span>
            </a>
        </div>
    </header>

    {{-- ══ MAIN CONTENT AREA ═══════════════════════════════════════════════════ --}}
    <main class="flex-1 w-full max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-5">

        {{-- ══ STAGE A: PRE-SCAN & FILE STAGING ════════════════════════════════ --}}
        @include('fdr.partials.stage_a_staging')

        {{-- ══ REPORT TYPE SWITCHER BAR (When multiple reports available or zip uploaded) ═════ --}}
        <div x-show="availableReportTypes && availableReportTypes.length > 1"
             class="bg-white rounded-2xl border border-slate-200/90 p-3 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black text-xs shadow-2xs">
                    <span>★</span>
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Multi-Report Dashboard Switcher</span>
                    <div class="text-xs font-bold text-slate-700">Switch between parsed FDR reports in active archive:</div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <template x-for="rtype in availableReportTypes" :key="rtype">
                    <button type="button" @click="switchReportType(rtype)"
                            :class="activeReportType === rtype ? getSwitcherActiveClass(rtype) : 'bg-slate-100 text-slate-700 hover:bg-slate-200/80 border-slate-200/70'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold tracking-tight transition border cursor-pointer flex items-center gap-1.5 shadow-2xs">
                        <span class="w-2 h-2 rounded-full" :class="getSwitcherDotClass(rtype)"></span>
                        <span x-text="formatReportTypeName(rtype)"></span>
                        <span x-show="allParsedByType && allParsedByType[rtype] && allParsedByType[rtype].totalRecords != null"
                              class="px-1.5 py-0.2 rounded-full text-[9px] font-mono"
                              :class="activeReportType === rtype ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'"
                              x-text="allParsedByType[rtype].totalRecords"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- ══ POLYMORPHIC DASHBOARD VIEWS (STAGE B) ═══════════════════════════ --}}
        {{-- Case 2: LOAD_FACTOR (13 Columns) --}}
        <template x-if="activeReportType === 'LOAD_FACTOR'">
            @include('fdr.partials.view_load_factor')
        </template>

        {{-- Case 3: COMPARE_LOAD_FACTOR & COMPARE_LOAD_FACTOR_DAY (20 Columns YoY) --}}
        <template x-if="activeReportType === 'COMPARE_LOAD_FACTOR' || activeReportType === 'COMPARE_LOAD_FACTOR_DAY'">
            @include('fdr.partials.view_compare_load_factor')
        </template>

        {{-- Case 4: OASYS_VS_APPS / DEPARTURE_VS_APPS (16 Columns) --}}
        <template x-if="activeReportType === 'OASYS_VS_APPS' || activeReportType === 'DEPARTURE_VS_APPS'">
            @include('fdr.partials.view_oasys_vs_apps')
        </template>

        {{-- Case 5: OASYS_VS_EDIFLY / OASYS_EDIFLY (14 Columns) --}}
        <template x-if="activeReportType === 'OASYS_VS_EDIFLY' || activeReportType === 'OASYS_EDIFLY'">
            @include('fdr.partials.view_oasys_vs_edifly')
        </template>

        {{-- Case 1: NORMAL & EX_HALIM (33 Columns Full Operational Control Room) --}}
        <div x-show="activeReportType === 'NORMAL' || activeReportType === 'EX_HALIM'" class="space-y-5">

            {{-- Ex Halim Dedicated Banner --}}
            <div x-show="activeReportType === 'EX_HALIM'"
                 class="bg-gradient-to-r from-amber-500/15 via-orange-500/10 to-indigo-500/15 border-2 border-amber-300 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black text-sm shadow-sm shadow-amber-500/30">
                        <span>HLP</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-black uppercase tracking-wider text-amber-950">EX HALIM (CGK) FLIGHT MONITORING</h2>
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-200 text-amber-900 border border-amber-300">CGK HUB</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-indigo-100 text-indigo-800 border border-indigo-200"
                                  x-text="'OPERATOR: ' + (filters.operator === 'ALL' ? (sourceSummary.operator || 'ALL AIRLINE') : filters.operator)"></span>
                        </div>
                        <p class="text-xs text-slate-600 mt-0.5">Special operational monitoring for flights operating out of or rerouted to/from Halim Perdanakusuma (HLP) via CGK</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs font-bold text-slate-700 bg-white/80 px-3 py-1.5 rounded-xl border border-amber-200/80">
                    <span class="text-slate-400 font-semibold uppercase text-[10px]">Monitoring Period:</span>
                    <span class="font-mono text-slate-900" x-text="sourceSummary.period_label || '01-09-2026 → 30-09-2026'">01-09-2026 → 30-09-2026</span>
                </div>
            </div>

            {{-- Ex Halim Zero-State Verification Card --}}
            <div x-show="activeReportType === 'EX_HALIM' && (totalRecords === 0 || !kpis.total_flights)"
                 class="bg-white rounded-2xl border-2 border-dashed border-amber-300 p-8 text-center space-y-5 shadow-xs">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <div class="max-w-lg mx-auto space-y-1.5">
                    <h3 class="text-base font-extrabold text-slate-900 tracking-tight">Zero Ex-Halim Movements Verified</h3>
                    <p class="text-xs text-slate-500">
                        The OASYS Flight Daily Report for <span class="font-bold text-slate-800" x-text="filters.operator !== 'ALL' ? filters.operator : (sourceSummary.operator || 'selected airline')"></span> has been verified with 0 commercial movements operating via Halim Perdanakusuma (HLP) in CGK operations.
                    </p>
                    {{-- Parsed Form Metadata Badges --}}
                    <div class="flex flex-wrap items-center justify-center gap-2 text-[11px] font-mono pt-1 text-slate-600">
                        <span class="bg-slate-100 px-2 py-0.5 rounded border border-slate-200">Branch: <strong class="text-slate-800">CGK</strong></span>
                        <span class="bg-slate-100 px-2 py-0.5 rounded border border-slate-200">Operator: <strong class="text-slate-800" x-text="filters.operator !== 'ALL' ? filters.operator : (sourceSummary.operator || 'ALL AIRLINE')"></strong></span>
                        <span class="bg-slate-100 px-2 py-0.5 rounded border border-slate-200">Period: <strong class="text-slate-800" x-text="sourceSummary.period_label || '01-09-2026 s/d 30-09-2026'"></strong></span>
                        <span class="bg-emerald-50 text-emerald-800 px-2 py-0.5 rounded border border-emerald-200">Realization: <strong>YES</strong></span>
                        <span class="bg-blue-50 text-blue-800 px-2 py-0.5 rounded border border-blue-200">Payload: <strong>-PAX ALL -(0)-</strong></span>
                    </div>
                </div>

                {{-- 4 Ex-Halim KPI Summary Cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-2xl mx-auto pt-2">
                    {{-- 1. Total Pergerakan Pesawat Ex-Halim --}}
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70 text-center">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Total Pergerakan (CGK)</div>
                        <div class="text-xl font-black text-slate-900 font-mono mt-0.5" x-text="(Number(kpis.total_flights || 0)) + ' Movements'">0 Movements</div>
                        <div class="text-[9px] text-slate-500 font-medium mt-0.5">Arrival &amp; Departure</div>
                    </div>
                    {{-- 2. Total Penumpang & Kargo Ex-Halim --}}
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70 text-center">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Total Penumpang &amp; Kargo</div>
                        <div class="text-xl font-black text-slate-900 font-mono mt-0.5" x-text="Number(kpis.total_passengers || 0).toLocaleString() + ' Pax'">0 Pax</div>
                        <div class="text-[9px] text-slate-500 font-medium mt-0.5" x-text="Number(kpis.cargo_kg || 0).toLocaleString() + ' kg Cargo'">0 kg Cargo</div>
                    </div>
                    {{-- 3. Kontribusi Terhadap Total Trafik CGK (%) --}}
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70 text-center">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Kontribusi Trafik CGK</div>
                        <div class="text-xl font-black text-emerald-700 font-mono mt-0.5">0.0%</div>
                        <div class="text-[9px] text-slate-500 font-medium mt-0.5">Verified HLP Share</div>
                    </div>
                    {{-- 4. Rata-rata Load Factor Ex-Halim (%) --}}
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70 text-center">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Rata-rata Load Factor</div>
                        <div class="text-xl font-black text-purple-700 font-mono mt-0.5" x-text="kpis.avg_load_factor || '0.0%'">0.0%</div>
                        <div class="text-[9px] text-slate-500 font-medium mt-0.5">Ex-Halim Routes SLF</div>
                    </div>
                </div>

                <div class="text-[11px] text-slate-400 font-medium">
                    Audit Status: <strong>0 movements recorded</strong> in this monthly operational reporting cycle. Form metadata parsed successfully.
                </div>
            </div>

        {{-- ══ 2. FILTER BAR (COMPACT DEDICATED CARD) ═════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs space-y-3">
            
            {{-- Top Controls Grid: 7 inputs --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3 items-end">
                
                {{-- Airport Filter (Locked to Report Airport Scope, Prompt Items 3, 4, 14, 15, 16) --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-[11px] font-bold text-slate-500">Airport</label>
                        @if(count($airports) <= 1)
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-amber-100 text-amber-800 border border-amber-200" title="Airport scope is determined by OASYS BRANCH_CODE.">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                LOCKED
                            </span>
                        @endif
                    </div>
                    @if(count($airports) <= 1)
                        @php
                            $lockedAp = $airports[0] ?? ($meta['report_airport'] ?? ($meta['airport'] ?? 'CGK'));
                            $lockedName = \App\Services\FlightDailyReport\FlightDailyReportParser::resolveAirportName($lockedAp);
                        @endphp
                        <div class="relative group" title="Airport scope is determined by OASYS BRANCH_CODE.">
                            <input type="text" readonly
                                   value="{{ $lockedAp }} — {{ $lockedName }} 🔒"
                                   class="w-full text-xs font-bold rounded-xl border border-slate-300 bg-slate-100/90 px-2.5 py-1.5 text-slate-800 cursor-not-allowed select-none shadow-xs">
                            <div class="text-[9.5px] font-semibold text-slate-400 mt-0.5">
                                Source Airport from OASYS: <span class="font-bold text-slate-600">{{ $lockedAp }}</span>
                            </div>
                        </div>
                    @else
                        <select x-model="filters.airport" @change="triggerFilter()"
                                class="w-full text-xs font-semibold rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <option value="ALL">ALL REPORT AIRPORTS</option>
                            @foreach($airports as $ap)
                                <option value="{{ $ap }}">{{ $ap }} — {{ \App\Services\FlightDailyReport\FlightDailyReportParser::resolveAirportName($ap) }}</option>
                            @endforeach
                        </select>
                        <div class="text-[9.5px] font-semibold text-slate-400 mt-0.5">
                            Source Airport from OASYS: <span class="font-bold text-slate-600">{{ implode(', ', $airports) }}</span>
                        </div>
                    @endif
                </div>

                {{-- Leg Filter --}}
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Leg</label>
                    <select x-model="filters.leg" @change="triggerFilter()"
                            class="w-full text-xs font-semibold rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="ALL">ALL (Arrival &amp; Departure)</option>
                        <option value="ARR">Arrival Only</option>
                        <option value="DEP">Departure Only</option>
                        <option value="ARRIVAL" class="hidden">Arrival Only</option>
                        <option value="DEPARTURE" class="hidden">Departure Only</option>
                    </select>
                </div>

                {{-- Operator / Airline Filter --}}
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Operator / Airline</label>
                    <select x-model="filters.operator" @change="triggerFilter()"
                            class="w-full text-xs font-semibold rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="ALL">ALL AIRLINE</option>
                        @foreach($airlines as $al)
                            @if(strcasecmp($al, 'PAX ALL') !== 0 && strcasecmp($al, 'ALL AIRLINE') !== 0)
                                <option value="{{ $al }}">{{ $al }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                {{-- Traffic Type Filter --}}
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Traffic Type</label>
                    <select x-model="filters.traffic" @change="triggerFilter()"
                            class="w-full text-xs font-semibold rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="ALL">ALL TRAFFIC</option>
                        <option value="DOM">DOMESTIC</option>
                        <option value="INTL">INTERNATIONAL</option>
                        <option value="DOMESTIC" class="hidden">DOMESTIC</option>
                        <option value="INTERNATIONAL" class="hidden">INTERNATIONAL</option>
                    </select>
                </div>

                {{-- Realization Filter --}}
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Realization</label>
                    <select x-model="filters.realization" @change="triggerFilter()"
                            class="w-full text-xs font-semibold rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="ALL">ALL</option>
                        <option value="YES">YES (Actual)</option>
                        <option value="NO">NO (Planned Only)</option>
                    </select>
                </div>

                {{-- Date / Scope Filter --}}
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1 flex items-center justify-between">
                        <span>Period / Date</span>
                        <template x-if="dateScope !== 'ALL_PERIOD'">
                            <button type="button" @click="setFullRange()" class="text-[9px] text-blue-600 font-bold hover:underline cursor-pointer">
                                [ Full Range ]
                            </button>
                        </template>
                    </label>
                    <div class="relative">
                        <template x-if="sourceSummary.source_type === 'DAILY'">
                            <input type="text" :value="filters.analysis_date || sourceSummary.period_start" readonly
                                   class="w-full text-xs font-bold font-mono rounded-xl border border-slate-200 bg-slate-100 text-slate-700 px-2.5 py-1.5 cursor-not-allowed">
                        </template>
                        <template x-if="sourceSummary.source_type !== 'DAILY'">
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="setFullRange()"
                                        :class="dateScope === 'ALL_PERIOD' ? 'bg-blue-600 text-white font-black shadow-2xs border-blue-600' : 'bg-white text-slate-700 hover:bg-slate-100 font-bold border-slate-200'"
                                        class="px-2 py-1.5 rounded-xl text-[10px] uppercase tracking-tight transition cursor-pointer border whitespace-nowrap"
                                        title="View Full Authoritative Range">
                                    Full Range
                                </button>
                                <select x-model="selectedDayOption" @change="onDateSelectChange()"
                                        class="w-full text-xs font-bold font-mono rounded-xl border border-slate-200 bg-slate-50/50 px-2 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                    <option value="ALL_PERIOD">Select Date (All Available)...</option>
                                    <template x-for="d in availableDates" :key="d">
                                        <option :value="d" x-text="formatDateLabel(d)"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Search Filter --}}
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Search</label>
                    <div class="relative">
                        <input type="text" x-model="filters.search" @input.debounce.300ms="triggerFilter(1)" @keydown.enter.prevent="triggerFilter(1)"
                               placeholder="Flight no, route, reg, airline..."
                               class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50/50 pl-8 pr-2.5 py-1.5 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>

            </div>

            {{-- Bottom Row: Active Filter Chips & Counter --}}
            <div class="flex flex-wrap items-center justify-between gap-3 pt-2.5 border-t border-slate-100 text-xs">
                
                {{-- Active Chips List --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-400 mr-1">Active Filters:</span>
                    
                    {{-- Airport Chip --}}
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        <span>Airport: <strong x-text="filters.airport"></strong></span>
                    </span>

                    {{-- Period / Date Chip --}}
                    <template x-if="dateScope === 'ALL_PERIOD'">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                            <span>Period: <strong>FULL RANGE</strong></span>
                        </span>
                    </template>
                    <template x-if="dateScope === 'DAY' && filters.analysis_date">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                            <span>Date: <strong class="font-mono" x-text="formatDateDisplay(filters.analysis_date)"></strong></span>
                            <button type="button" @click="setFullRange()" class="hover:text-red-500 font-bold ml-0.5" title="Switch to Full Range">&times;</button>
                        </span>
                    </template>

                    {{-- Dynamic Chips from activeChips --}}
                    <template x-for="chip in activeChips" :key="chip.key">
                        <template x-if="chip.key !== 'airport' && chip.key !== 'analysis_date' && chip.key !== 'date_scope'">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                <span x-text="chip.label"></span>
                                <button type="button" @click="removeChip(chip.key)" class="hover:text-red-500 font-bold ml-0.5">&times;</button>
                            </span>
                        </template>
                    </template>

                    {{-- Clear All Button --}}
                    <button type="button" @click="clearAllFilters()" class="text-xs font-bold text-red-600 hover:text-red-700 ml-1 transition cursor-pointer">
                        [ Clear All ]
                    </button>
                </div>

                {{-- Status & Count --}}
                <div class="flex items-center gap-3 text-slate-500 text-xs font-medium">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Showing <strong class="text-slate-900 font-bold" x-text="Number(totalRecords || 0).toLocaleString()">{{ number_format($totalRecords ?? 0) }}</strong> of <strong class="text-slate-900 font-bold" x-text="Number(sourceSummary.total_flights || totalRecords || 0).toLocaleString()">{{ number_format($sourceSummary['total_flights'] ?? ($totalRecords ?? 0)) }}</strong> records</span>
                    </div>
                    <span>•</span>
                    <span class="text-[11px] text-slate-400">Last updated: <span x-text="lastUpdatedTime"></span></span>
                </div>

            </div>

        </div>

        {{-- ══ 3. TOP KPI SECTION (8 HORIZONTAL CARDS) ════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
            
            {{-- 1. Total Flights --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-600">↑ 0%</span>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Flights</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="Number(kpis.total_flights || 0).toLocaleString()">{{ number_format($analytics['kpis']['total_flights'] ?? 0) }}</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                        Sched: <span class="font-bold text-slate-700" x-text="Number(kpis.scheduled_flights || 0).toLocaleString()">{{ number_format($analytics['kpis']['scheduled_flights'] ?? 0) }}</span> • Realized: <span class="font-bold text-emerald-700" x-text="Number(kpis.actual_flights || kpis.realized_flights || kpis.total_flights || 0).toLocaleString()">{{ number_format($analytics['kpis']['actual_flights'] ?? ($analytics['kpis']['total_flights'] ?? 0)) }}</span>
                        <template x-if="(kpis.cancelled_or_hold_flights || 0) > 0">
                            <span> • Hold/Cnl: <span class="font-bold text-rose-600" x-text="Number(kpis.cancelled_or_hold_flights).toLocaleString()"></span></span>
                        </template>
                    </div>
                </div>
            </div>

            {{-- 2. Arrivals --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3"/></svg>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400" x-text="kpis.total_flights > 0 ? ((kpis.arrivals / kpis.total_flights) * 100).toFixed(1) + '%' : '0%'">50.0%</span>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Arrivals</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="Number(kpis.arrivals || 0).toLocaleString()">{{ number_format($analytics['kpis']['arrivals'] ?? 0) }}</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                        Dom: <span x-text="Number(kpis.arrivals_dom || 0).toLocaleString()">{{ number_format($analytics['kpis']['arrivals_dom'] ?? 0) }}</span> | Int: <span x-text="Number(kpis.arrivals_int || 0).toLocaleString()">{{ number_format($analytics['kpis']['arrivals_int'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            {{-- 3. Departures --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18"/></svg>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400" x-text="kpis.total_flights > 0 ? ((kpis.departures / kpis.total_flights) * 100).toFixed(1) + '%' : '0%'">50.0%</span>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Departures</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="Number(kpis.departures || 0).toLocaleString()">{{ number_format($analytics['kpis']['departures'] ?? 0) }}</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                        Dom: <span x-text="Number(kpis.departures_dom || 0).toLocaleString()">{{ number_format($analytics['kpis']['departures_dom'] ?? 0) }}</span> | Int: <span x-text="Number(kpis.departures_int || 0).toLocaleString()">{{ number_format($analytics['kpis']['departures_int'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            {{-- 4. Total Passengers --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                    </div>
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800" x-text="(kpis.pax_per_flight || 0) + ' Pax/Flight'">{{ $analytics['kpis']['pax_per_flight_display'] ?? (($analytics['kpis']['pax_per_flight'] ?? 0) . ' Pax/Flight') }}</span>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Passengers</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="Number(kpis.total_passengers || 0).toLocaleString()">{{ number_format($analytics['kpis']['total_passengers'] ?? 0) }}</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                        Adl: <span x-text="Number(kpis.adult_passengers || 0).toLocaleString()">{{ number_format($analytics['kpis']['adult_passengers'] ?? 0) }}</span> | Chd: <span x-text="Number(kpis.child_passengers || 0).toLocaleString()">{{ number_format($analytics['kpis']['child_passengers'] ?? 0) }}</span> | Inf: <span x-text="Number(kpis.infant_passengers || 0).toLocaleString()">{{ number_format($analytics['kpis']['infant_passengers'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            {{-- 5. Load Factor (Avg) --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Load Factor (Avg)</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="kpis.passenger_utilization || kpis.avg_load_factor || '0.0%'">{{ $analytics['kpis']['passenger_utilization'] ?? ($analytics['kpis']['avg_load_factor'] ?? '0.0%') }}</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                        Cap: <span x-text="Number(kpis.total_capacity || 0).toLocaleString()">{{ number_format($analytics['kpis']['total_capacity'] ?? 0) }}</span> | Load: <span x-text="Number(kpis.total_load || 0).toLocaleString()">{{ number_format($analytics['kpis']['total_load'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            {{-- 6. Cargo & Pos (Ton) --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400" x-text="(Number(kpis.pos_ton || 0) > 0) ? ('+ ' + Number(kpis.pos_ton).toFixed(1) + 't Pos') : ''"></span>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cargo &amp; Pos (Ton)</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="(Number(kpis.cargo_ton || 0) + Number(kpis.pos_ton || 0)).toFixed(2)">{{ number_format(($analytics['kpis']['cargo_ton'] ?? 0) + ($analytics['kpis']['pos_ton'] ?? 0), 2) }}</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                        Car: <span class="font-bold text-slate-700" x-text="Number(kpis.cargo_ton || 0).toFixed(2)">{{ number_format($analytics['kpis']['cargo_ton'] ?? 0, 2) }}</span>t | Pos: <span class="font-bold text-slate-700" x-text="Number(kpis.pos_ton || 0).toFixed(2)">{{ number_format($analytics['kpis']['pos_ton'] ?? 0, 2) }}</span>t
                    </div>
                </div>
            </div>

            {{-- 7. Baggage (Kg) --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-cyan-50 text-cyan-700 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v1.069m7.5 0a47.933 47.933 0 0 0-7.5 0"/></svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Baggage (Kg)</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="Number(kpis.baggage_kg || 0).toLocaleString()">{{ number_format($analytics['kpis']['baggage_kg'] ?? 0) }}</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                        <span x-text="Number(kpis.baggage_per_flight_kg || 0).toLocaleString()">{{ number_format($analytics['kpis']['baggage_per_flight_kg'] ?? 0) }}</span> kg/Flight
                    </div>
                </div>
            </div>

            {{-- 8. Irregular Flights & Operating Crew (CRW) --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-700 font-mono" x-text="(Number(kpis.operating_crew || 0)).toLocaleString() + ' CRW'">{{ number_format($analytics['kpis']['operating_crew'] ?? 0) }} CRW</span>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Irregular &amp; Crew</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="schedVsReal.late_count ?? (kpis.irregular_flights ?? 0)">{{ $analytics['schedule_vs_realization']['late_count'] ?? ($analytics['kpis']['irregular_flights'] ?? 0) }}</div>
                    <div class="text-[10px] font-semibold text-rose-500 mt-0.5 truncate">
                        <span x-text="schedVsReal.late_count ?? (kpis.delayed_flights ?? 0)">{{ $analytics['schedule_vs_realization']['late_count'] ?? ($analytics['kpis']['delayed_flights'] ?? 0) }}</span> Late | <span class="text-slate-600 font-bold" x-text="(Number(kpis.operating_crew || 0)).toLocaleString() + ' Crew'">{{ number_format($analytics['kpis']['operating_crew'] ?? 0) }} Crew</span>
                    </div>
                </div>
            </div>

        </div>

        {{-- ══ 4. VISUAL ANALYTICS — EXACTLY 4 PRIMARY PANELS (2x2 GRID) ══════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            {{-- ────────────────────────────────────────────────────────────────
                 PANEL 1: 1. SCHEDULE VS ACTUAL (SIBT/SOBT vs AIBT/AOBT)
                 ──────────────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between space-y-4">
                
                {{-- Panel Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        </div>
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">
                            1. Schedule vs Actual <span class="text-slate-400 font-semibold normal-case">(SIBT/SOBT vs AIBT/AOBT)</span>
                        </h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-[10px] font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded-lg border border-slate-200 transition">
                            <input type="checkbox" x-model="filterOutliers" @change="buildDelayHistChart()" class="rounded text-blue-600 focus:ring-0 w-3 h-3 cursor-pointer">
                            <span>Outlier Filter (&gt;360m)</span>
                        </label>
                        <span class="text-[10px] font-bold text-slate-400 hidden sm:inline">Time Variance</span>
                    </div>
                </div>

                {{-- Top 3 Status Cards (On-Time, Early, Late) --}}
                <div class="grid grid-cols-3 gap-2.5">
                    
                    {{-- On Time --}}
                    <div class="p-3 rounded-xl bg-emerald-50/60 border border-emerald-200 flex flex-col items-center justify-center text-center">
                        <div class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider">On Time</div>
                        <div class="text-xl font-black text-emerald-700 my-0.5" x-text="schedVsReal.on_time_count">159</div>
                        <div class="text-[10px] font-bold text-emerald-600" x-text="schedVsReal.on_time_percentage">83.7%</div>
                    </div>

                    {{-- Early (>15m) --}}
                    <div class="p-3 rounded-xl bg-blue-50/60 border border-blue-200 flex flex-col items-center justify-center text-center">
                        <div class="text-[10px] font-bold text-blue-800 uppercase tracking-wider">Early (&gt;15m)</div>
                        <div class="text-xl font-black text-blue-700 my-0.5" x-text="schedVsReal.early_count">10</div>
                        <div class="text-[10px] font-bold text-blue-600" x-text="schedVsReal.evaluated_count > 0 ? ((schedVsReal.early_count / schedVsReal.evaluated_count) * 100).toFixed(1) + '%' : '0%'">5.3%</div>
                    </div>

                    {{-- Late (>15m) --}}
                    <div class="p-3 rounded-xl bg-rose-50/60 border border-rose-200 flex flex-col items-center justify-center text-center">
                        <div class="text-[10px] font-bold text-rose-800 uppercase tracking-wider">Late (&gt;15m)</div>
                        <div class="text-xl font-black text-rose-700 my-0.5" x-text="schedVsReal.late_count">22</div>
                        <div class="text-[10px] font-bold text-rose-600" x-text="schedVsReal.evaluated_count > 0 ? ((schedVsReal.late_count / schedVsReal.evaluated_count) * 100).toFixed(1) + '%' : '0%'">11.6%</div>
                    </div>

                </div>

                {{-- SCHEDULE VARIANCE DISTRIBUTION Histogram --}}
                <div class="space-y-2">
                    <div>
                        <div class="text-[11px] font-black uppercase tracking-wider text-slate-800">SCHEDULE VARIANCE DISTRIBUTION</div>
                        <p class="text-[10px] text-slate-400 font-medium">How early or late actual movement occurred compared with schedule.</p>
                    </div>

                    {{-- Grouped visual banner: EARLY (4 buckets), ON TIME (1 bucket), LATE (4 buckets) --}}
                    <div class="grid grid-cols-9 gap-1 text-center text-[9px] font-bold">
                        <div class="col-span-4 bg-blue-50 text-blue-700 rounded-md py-1 border border-blue-200 flex items-center justify-center gap-1 shadow-2xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                            <span>EARLY (4 BUCKETS)</span>
                        </div>
                        <div class="col-span-1 bg-emerald-50 text-emerald-700 rounded-md py-1 border border-emerald-200 flex items-center justify-center gap-0.5 truncate px-0.5 shadow-2xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>ON TIME</span>
                        </div>
                        <div class="col-span-4 bg-rose-50 text-rose-700 rounded-md py-1 border border-rose-200 flex items-center justify-center gap-1 shadow-2xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                            <span>LATE (4 BUCKETS)</span>
                        </div>
                    </div>

                    <div class="h-36 w-full relative">
                        <canvas id="fdrDelayHistChart"></canvas>
                    </div>
                </div>

                {{-- Bottom 4 Stat Cards --}}
                <div class="grid grid-cols-4 gap-2 pt-2 border-t border-slate-100 text-center">
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Average Delay</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="filterOutliers ? (schedVsReal.avg_delay_filtered || schedVsReal.avg_delay_minutes) : schedVsReal.avg_delay_minutes">-5.9 min</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Median Delay</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="filterOutliers ? (schedVsReal.median_delay_filtered || schedVsReal.median_delay) : schedVsReal.median_delay">0 min</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Min Delay</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="filterOutliers ? (schedVsReal.min_delay_filtered || schedVsReal.min_delay) : schedVsReal.min_delay">-1424 min</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Max Delay</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="filterOutliers ? (schedVsReal.max_delay_filtered || schedVsReal.max_delay) : schedVsReal.max_delay">+37 min</div>
                    </div>
                </div>
                <template x-if="filterOutliers && schedVsReal.outliers_count > 0">
                    <div class="text-[9.5px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 text-center font-medium" x-text="schedVsReal.outliers_count + ' cross-midnight outlier(s) filtered (>360m)'"></div>
                </template>

            </div>

            {{-- ────────────────────────────────────────────────────────────────
                 PANEL 2: 2. PASSENGER COMPOSITION & PAYLOAD
                 ──────────────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between space-y-4">
                
                {{-- Panel Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        </div>
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">
                            2. Passenger Composition &amp; Payload
                        </h2>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400">Pax &amp; Payload Donut Telemetry</span>
                </div>

                {{-- Side-by-Side Donut Visualizations: Passenger & Payload --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    
                    {{-- DONUT 1: Passenger Composition (Adult, Child, Infant) --}}
                    <div class="flex flex-col items-center bg-slate-50/60 rounded-xl p-3.5 border border-slate-100/80">
                        <div class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider mb-2">
                            Passenger Composition
                        </div>
                        <div class="relative w-36 h-36 flex items-center justify-center">
                            <canvas id="fdrPaxCompDonutChart"></canvas>
                            {{-- Centered Text --}}
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center px-1">
                                <span class="text-[8px] uppercase font-extrabold text-slate-400 tracking-wider leading-tight">TOTAL PASSENGER</span>
                                <span class="text-sm font-black text-slate-900 font-mono tracking-tight mt-0.5"
                                      x-show="((paxAnalytics.composition.adult || 0) + (paxAnalytics.composition.child || 0) + (paxAnalytics.composition.infant || 0)) > 0"
                                      x-text="((paxAnalytics.composition.adult || 0) + (paxAnalytics.composition.child || 0) + (paxAnalytics.composition.infant || 0)).toLocaleString()">
                                    {{ number_format(($analytics['passenger_analytics']['composition']['adult'] ?? 0) + ($analytics['passenger_analytics']['composition']['child'] ?? 0) + ($analytics['passenger_analytics']['composition']['infant'] ?? 0)) }}
                                </span>
                                <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-tight mt-0.5"
                                      x-show="((paxAnalytics.composition.adult || 0) + (paxAnalytics.composition.child || 0) + (paxAnalytics.composition.infant || 0)) === 0">
                                    NO PASSENGER DATA
                                </span>
                            </div>
                        </div>
                        {{-- Donut Legend --}}
                        <div class="w-full flex flex-col gap-1 mt-3 pt-2 border-t border-slate-200/60 text-[10px] text-slate-600 font-medium">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-bold"><span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>Adult</span>
                                <span class="font-mono text-slate-700"><strong x-text="(paxAnalytics.composition.adult || 0).toLocaleString() + ' Pax'"></strong> (<span x-text="getPaxPct('adult')"></span>)</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-bold"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>Child</span>
                                <span class="font-mono text-slate-700"><strong x-text="(paxAnalytics.composition.child || 0).toLocaleString() + ' Pax'"></strong> (<span x-text="getPaxPct('child')"></span>)</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-bold"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>Infant</span>
                                <span class="font-mono text-slate-700"><strong x-text="(paxAnalytics.composition.infant || 0).toLocaleString() + ' Pax'"></strong> (<span x-text="getPaxPct('infant')"></span>)</span>
                            </div>
                            <template x-if="(paxAnalytics.composition.umroh || 0) > 0">
                                <div class="flex items-center justify-between text-purple-700 bg-purple-50/70 px-1 py-0.5 rounded">
                                    <span class="flex items-center gap-1.5 font-bold"><span class="w-2.5 h-2.5 rounded-full bg-purple-600"></span>Umroh Pax</span>
                                    <span class="font-mono"><strong x-text="(paxAnalytics.composition.umroh || 0).toLocaleString() + ' Pax'"></strong></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- DONUT 2: Payload Composition (Cargo, Baggage, Pos) --}}
                    <div class="flex flex-col items-center bg-slate-50/60 rounded-xl p-3.5 border border-slate-100/80">
                        <div class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider mb-2">
                            Payload Composition
                        </div>
                        <div class="relative w-36 h-36 flex items-center justify-center">
                            <canvas id="fdrPayloadCompDonutChart"></canvas>
                            {{-- Centered Text --}}
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center px-1">
                                <span class="text-[8px] uppercase font-extrabold text-slate-400 tracking-wider leading-tight">TOTAL PAYLOAD</span>
                                <span class="text-xs font-black text-slate-900 font-mono tracking-tight mt-0.5"
                                      x-show="((kpis.total_cargo_kg || 0) + (kpis.total_baggage_kg || 0) + (kpis.pos_kg || 0)) > 0"
                                      x-text="formatPayloadWeightKg((kpis.total_cargo_kg || 0) + (kpis.total_baggage_kg || 0) + (kpis.pos_kg || 0))">
                                    725,017 kg
                                </span>
                                <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-tight mt-0.5"
                                      x-show="((kpis.total_cargo_kg || 0) + (kpis.total_baggage_kg || 0) + (kpis.pos_kg || 0)) === 0">
                                    NO PAYLOAD DATA
                                </span>
                            </div>
                        </div>
                        {{-- Donut Legend --}}
                        <div class="w-full flex flex-col gap-1 mt-3 pt-2 border-t border-slate-200/60 text-[10px] text-slate-600 font-medium">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-bold"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>Cargo</span>
                                <span class="font-mono text-slate-700"><strong x-text="formatPayloadWeightKg(kpis.total_cargo_kg || 0)"></strong> (<span x-text="getPayloadPct('cargo')"></span>)</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-bold"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>Baggage</span>
                                <span class="font-mono text-slate-700"><strong x-text="formatPayloadWeightKg(kpis.total_baggage_kg || 0)"></strong> (<span x-text="getPayloadPct('baggage')"></span>)</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-bold"><span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>Pos / Mail</span>
                                <span class="font-mono text-slate-700"><strong x-text="formatPayloadWeightKg(kpis.pos_kg || 0)"></strong> (<span x-text="getPayloadPct('pos')"></span>)</span>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Compact Flow Badges: Transit, Transfer, Operating Crew, Crew / Flight --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center py-2 px-3 bg-slate-50 rounded-xl border border-slate-100">
                    <div>
                        <div class="text-[9px] uppercase font-bold text-slate-400">Transit Flow</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="(paxAnalytics.composition.transit || 0).toLocaleString()">0</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase font-bold text-slate-400">Transfer Flow</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="(paxAnalytics.composition.transfer || 0).toLocaleString()">0</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase font-bold text-slate-400">Operating Crew</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="(kpis.operating_crew || paxAnalytics.composition.crew || 0).toLocaleString()">646</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase font-bold text-slate-400">Crew / Flight</div>
                        <div class="text-xs font-black font-mono text-blue-700 mt-0.5" x-text="(kpis.crew_per_flight ? kpis.crew_per_flight : '0.0')">0.0</div>
                    </div>
                </div>

                {{-- Operational Telemetry: MTOW & Finalization Lock Status --}}
                <div class="grid grid-cols-3 gap-2 text-center py-2 px-3 bg-slate-50/70 rounded-xl border border-slate-100 text-xs">
                    <div>
                        <div class="text-[9px] uppercase font-bold text-slate-400">Total MTOW</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="Number(kpis.total_mtow_ton || 0).toLocaleString() + ' Ton'">0 Ton</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase font-bold text-slate-400">Avg MTOW / Flight</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="Number(kpis.avg_mtow_ton || 0).toFixed(1) + ' Ton'">0.0 Ton</div>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase font-bold text-slate-400">Data Final Status</div>
                        <div class="text-xs font-black mt-0.5"
                             :class="(kpis.final_no_count === 0 && (kpis.final_yes_count || 0) > 0) ? 'text-emerald-700' : 'text-amber-700'"
                             x-text="(kpis.final_no_count === 0 && (kpis.final_yes_count || 0) > 0) ? 'FINAL LOCKED (YES)' : ('FINAL: ' + (kpis.final_yes_count || 0) + 'Y / ' + (kpis.final_no_count || 0) + 'N')">
                            FINAL: YES
                        </div>
                    </div>
                </div>

                {{-- Two Directional Cards: Arrival Pax & Departure Pax (REACTIVE HIGHLIGHTING / MUTING) --}}
                <div class="grid grid-cols-2 gap-3">
                    
                    {{-- Arrival Pax Card --}}
                    <div class="p-3 rounded-xl transition duration-200 space-y-1.5"
                         :class="{
                             'bg-amber-50/80 border-2 border-amber-400 shadow-xs ring-2 ring-amber-400/20': filters.leg === 'ARR' || filters.leg === 'ARRIVAL',
                             'bg-slate-50/50 border border-slate-200 opacity-60 grayscale-[30%]': filters.leg === 'DEP' || filters.leg === 'DEPARTURE',
                             'bg-amber-50/50 border border-amber-200/80': filters.leg === 'ALL' || !filters.leg
                         }">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-xs font-black text-amber-900">
                                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3"/></svg>
                                <span>Arrival Pax</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-900" x-text="'LF ' + (paxAnalytics.lf_avg_arr ? paxAnalytics.lf_avg_arr + '%' : '66.8%')">LF 66.8%</span>
                        </div>
                        <div class="text-lg font-black text-slate-900" x-text="(((paxAnalytics.composition_arr || {}).adult || 0) + ((paxAnalytics.composition_arr || {}).child || 0) + ((paxAnalytics.composition_arr || {}).infant || 0)).toLocaleString() + ' Pax'">
                            {{ number_format((($analytics['passenger_analytics']['composition_arr']['adult'] ?? 0) + ($analytics['passenger_analytics']['composition_arr']['child'] ?? 0) + ($analytics['passenger_analytics']['composition_arr']['infant'] ?? 0))) }} Pax
                        </div>
                        <div class="text-[10px] text-slate-500 font-medium">
                            Adl: <span x-text="((paxAnalytics.composition_arr || {}).adult || 0).toLocaleString()">{{ number_format($analytics['passenger_analytics']['composition_arr']['adult'] ?? 0) }}</span> | Chd: <span x-text="(paxAnalytics.composition_arr || {}).child || 0">{{ number_format($analytics['passenger_analytics']['composition_arr']['child'] ?? 0) }}</span> | Inf: <span x-text="(paxAnalytics.composition_arr || {}).infant || 0">{{ number_format($analytics['passenger_analytics']['composition_arr']['infant'] ?? 0) }}</span>
                        </div>
                        <div class="text-[10px] text-slate-500 font-medium pt-1 border-t border-amber-200/40">
                            Cargo: <span x-text="(((paxAnalytics.payload_arr || {}).cargo_kg || 0) / 1000).toFixed(2) + ' t'">{{ number_format(($analytics['passenger_analytics']['payload_arr']['cargo_kg'] ?? 0) / 1000, 2) }} t</span> | Bag: <span x-text="((paxAnalytics.payload_arr || {}).baggage_kg || 0).toLocaleString() + ' kg'">{{ number_format($analytics['passenger_analytics']['payload_arr']['baggage_kg'] ?? 0) }} kg</span>
                        </div>
                    </div>

                    {{-- Departure Pax Card --}}
                    <div class="p-3 rounded-xl transition duration-200 space-y-1.5"
                         :class="{
                             'bg-blue-50/80 border-2 border-blue-400 shadow-xs ring-2 ring-blue-400/20': filters.leg === 'DEP' || filters.leg === 'DEPARTURE',
                             'bg-slate-50/50 border border-slate-200 opacity-60 grayscale-[30%]': filters.leg === 'ARR' || filters.leg === 'ARRIVAL',
                             'bg-blue-50/50 border border-blue-200/80': filters.leg === 'ALL' || !filters.leg
                         }">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-xs font-black text-blue-900">
                                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18"/></svg>
                                <span>Departure Pax</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-blue-100 text-blue-900" x-text="'LF ' + (paxAnalytics.lf_avg_dep ? paxAnalytics.lf_avg_dep + '%' : '{{ $analytics['passenger_analytics']['lf_avg_dep'] ?? '0.0%' }}')">LF {{ $analytics['passenger_analytics']['lf_avg_dep'] ?? '0.0%' }}</span>
                        </div>
                        <div class="text-lg font-black text-slate-900" x-text="(((paxAnalytics.composition_dep || {}).adult || 0) + ((paxAnalytics.composition_dep || {}).child || 0) + ((paxAnalytics.composition_dep || {}).infant || 0)).toLocaleString() + ' Pax'">
                            {{ number_format((($analytics['passenger_analytics']['composition_dep']['adult'] ?? 0) + ($analytics['passenger_analytics']['composition_dep']['child'] ?? 0) + ($analytics['passenger_analytics']['composition_dep']['infant'] ?? 0))) }} Pax
                        </div>
                        <div class="text-[10px] text-slate-500 font-medium">
                            Adl: <span x-text="((paxAnalytics.composition_dep || {}).adult || 0).toLocaleString()">{{ number_format($analytics['passenger_analytics']['composition_dep']['adult'] ?? 0) }}</span> | Chd: <span x-text="(paxAnalytics.composition_dep || {}).child || 0">{{ number_format($analytics['passenger_analytics']['composition_dep']['child'] ?? 0) }}</span> | Inf: <span x-text="(paxAnalytics.composition_dep || {}).infant || 0">{{ number_format($analytics['passenger_analytics']['composition_dep']['infant'] ?? 0) }}</span>
                        </div>
                        <div class="text-[10px] text-slate-500 font-medium pt-1 border-t border-blue-200/40">
                            Cargo: <span x-text="(((paxAnalytics.payload_dep || {}).cargo_kg || 0) / 1000).toFixed(2) + ' t'">{{ number_format(($analytics['passenger_analytics']['payload_dep']['cargo_kg'] ?? 0) / 1000, 2) }} t</span> | Bag: <span x-text="((paxAnalytics.payload_dep || {}).baggage_kg || 0).toLocaleString() + ' kg'">{{ number_format($analytics['passenger_analytics']['payload_dep']['baggage_kg'] ?? 0) }} kg</span>
                        </div>
                    </div>

                </div>

                {{-- Bottom 4 Metrics --}}
                <div class="grid grid-cols-4 gap-2 pt-2 border-t border-slate-100 text-center">
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Pax / Flight</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="kpis.pax_per_flight">134.9</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Cargo / Flight (t)</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="kpis.cargo_per_flight_t">1.10</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Baggage / Flight (kg)</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="kpis.baggage_per_flight_kg">776</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Crew / Flight</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="kpis.total_flights > 0 ? ((paxAnalytics.composition.crew || 0) / kpis.total_flights).toFixed(1) : '3.4'">3.4</div>
                    </div>
                </div>

            </div>

            {{-- ────────────────────────────────────────────────────────────────
                 PANEL 3: 3. OPERATOR & FLEET PERFORMANCE
                 ──────────────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between space-y-4">
                
                {{-- Panel Header with Sub-Tabs --}}
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                        </div>
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">
                            3. Operator &amp; Fleet Performance
                        </h2>
                    </div>

                    {{-- Tabs: Top Operators / Aircraft Types / Load Factor --}}
                    <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
                        <button type="button" @click="panel3Tab = 'operators'"
                                :class="panel3Tab === 'operators' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-lg text-[10px] font-bold transition cursor-pointer">
                            Top Operators
                        </button>
                        <button type="button" @click="panel3Tab = 'aircraft'"
                                :class="panel3Tab === 'aircraft' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-lg text-[10px] font-bold transition cursor-pointer">
                            Aircraft Types
                        </button>
                        <button type="button" @click="panel3Tab = 'lf'"
                                :class="panel3Tab === 'lf' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-lg text-[10px] font-bold transition cursor-pointer">
                            Load Factor by Operator
                        </button>
                    </div>
                </div>

                {{-- Tab 1: Top Operators Horizontal Bar Chart / List --}}
                <div x-show="panel3Tab === 'operators'" class="space-y-2.5">
                    <template x-for="item in (airlineRoute.ranked_airlines || []).slice(0, 7)" :key="item.airline">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-800" x-text="item.airline"></span>
                                <span class="font-mono text-slate-500 font-semibold">
                                     <strong class="text-slate-900" x-text="item.flights"></strong> 
                                     (<span x-text="kpis.total_flights > 0 ? ((item.flights / kpis.total_flights) * 100).toFixed(1) + '%' : '0%'"></span>)
                                </span>
                            </div>
                            <div class="w-full h-3 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full transition-all duration-300"
                                     :style="'width: ' + (kpis.total_flights > 0 ? Math.min(100, (item.flights / kpis.total_flights) * 100) : 0) + '%'"></div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Tab 2: Aircraft Types Table --}}
                <div x-show="panel3Tab === 'aircraft'" class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
                                <th class="py-1.5 px-2">Type / Model</th>
                                <th class="py-1.5 px-2 text-right">Movements</th>
                                <th class="py-1.5 px-2 text-right">Avg Cap</th>
                                <th class="py-1.5 px-2 text-right">Avg LF%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 font-mono">
                            <template x-for="item in (fleetPerformance.fleet_mix || []).slice(0, 6)" :key="item.desc">
                                <tr>
                                    <td class="py-1.5 px-2 font-sans font-bold text-slate-800 truncate max-w-[150px]" x-text="item.desc"></td>
                                    <td class="py-1.5 px-2 text-right text-slate-700" x-text="item.movements"></td>
                                    <td class="py-1.5 px-2 text-right text-slate-500" x-text="item.avg_cap || '—'"></td>
                                    <td class="py-1.5 px-2 text-right font-bold text-blue-600" x-text="item.avg_lf ? item.avg_lf + '%' : '—'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Tab 3: Load Factor by Operator --}}
                <div x-show="panel3Tab === 'lf'" class="space-y-2.5">
                    <template x-for="item in (airlineRoute.ranked_airlines || []).slice(0, 7)" :key="item.airline">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-800" x-text="item.airline"></span>
                                <span class="font-mono text-emerald-600 font-bold" x-text="item.avg_load_factor"></span>
                            </div>
                            <div class="w-full h-3 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full transition-all duration-300"
                                     :style="'width: ' + Math.min(100, item.lf_num || 0) + '%'"></div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Bottom Summary Row --}}
                <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 text-center">
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Total Operators</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="airlineRoute.ranked_airlines ? airlineRoute.ranked_airlines.length : 8">8</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Narrow Body</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5">
                            <span x-text="fleetPerformance.narrow_count ?? 152">152</span>
                            <span class="text-[10px] text-slate-400 font-medium" x-text="kpis.total_flights > 0 ? '(' + (((fleetPerformance.narrow_count ?? 152) / kpis.total_flights) * 100).toFixed(1) + '%)' : '(80.0%)'">(80.0%)</span>
                        </div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Wide Body</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5">
                            <span x-text="fleetPerformance.wide_count ?? 38">38</span>
                            <span class="text-[10px] text-slate-400 font-medium" x-text="kpis.total_flights > 0 ? '(' + (((fleetPerformance.wide_count ?? 38) / kpis.total_flights) * 100).toFixed(1) + '%)' : '(20.0%)'">(20.0%)</span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ────────────────────────────────────────────────────────────────
                 PANEL 4: 4. GROUND OPERATIONS & TURNAROUND
                 ──────────────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between space-y-4">
                
                {{-- Panel Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.333A12.018 12.018 0 0 0 12 9c-2.474 0-4.792.75-6.75 2.033V21"/></svg>
                        </div>
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">
                            4. Ground Operations &amp; Turnaround
                        </h2>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400">Stands, Runways &amp; Pairing</span>
                </div>

                {{-- Top 4 KPI Cards --}}
                <div class="grid grid-cols-4 gap-2 text-center">
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Avg Ground Time</div>
                        <div class="text-xs font-black font-mono text-slate-900 mt-0.5" x-text="groundOps.ground_time_mean ? groundOps.ground_time_mean + ' min' : '110.3 min'">110.3 min</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Median Ground</div>
                        <div class="text-xs font-black font-mono text-slate-900 mt-0.5" x-text="groundOps.ground_time_median ? groundOps.ground_time_median + ' min' : '76 min'">76 min</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Min / Max</div>
                        <div class="text-xs font-black font-mono text-slate-900 mt-0.5" x-text="(groundOps.ground_time_min ?? 50) + ' / ' + (groundOps.ground_time_max ?? 491) + ' min'">50 / 491 min</div>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Stand Changes</div>
                        <div class="text-xs font-black font-mono text-amber-600 mt-0.5" x-text="groundOps.stand_changes ?? 1">1</div>
                    </div>
                </div>

                {{-- Sub-Tabs: Top Stands, Runway Distribution, Turnaround Samples --}}
                <div class="space-y-2">
                    <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl w-fit">
                        <button type="button" @click="panel4Tab = 'stands'"
                                :class="panel4Tab === 'stands' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-lg text-[10px] font-bold transition cursor-pointer">
                            Top Stands
                        </button>
                        <button type="button" @click="panel4Tab = 'runway'"
                                :class="panel4Tab === 'runway' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-lg text-[10px] font-bold transition cursor-pointer">
                            Runway Distribution
                        </button>
                        <button type="button" @click="panel4Tab = 'turnaround'"
                                :class="panel4Tab === 'turnaround' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                class="px-2.5 py-1 rounded-lg text-[10px] font-bold transition cursor-pointer">
                            Turnaround Samples
                        </button>
                    </div>

                    {{-- Tab 1: Top Stands Table --}}
                    <div x-show="panel4Tab === 'stands'" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
                                    <th class="py-1.5 px-2">Stand</th>
                                    <th class="py-1.5 px-2 text-right">Movements</th>
                                    <th class="py-1.5 px-2 text-right">%</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 font-mono">
                                <template x-for="item in groundOps.stands ? groundOps.stands.slice(0, 5) : []" :key="item.stand">
                                    <tr>
                                        <td class="py-1.5 px-2 font-bold text-slate-800" x-text="item.stand"></td>
                                        <td class="py-1.5 px-2 text-right text-slate-700" x-text="item.count"></td>
                                        <td class="py-1.5 px-2 text-right text-blue-600 font-semibold" x-text="item.percentage + '%'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Tab 2: Runway Distribution Table --}}
                    <div x-show="panel4Tab === 'runway'" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
                                    <th class="py-1.5 px-2">Runway</th>
                                    <th class="py-1.5 px-2 text-right">Movements</th>
                                    <th class="py-1.5 px-2 text-right">%</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 font-mono">
                                <template x-for="item in groundOps.runways ? groundOps.runways.slice(0, 5) : []" :key="item.runway">
                                    <tr>
                                        <td class="py-1.5 px-2 font-bold text-slate-800" x-text="item.runway"></td>
                                        <td class="py-1.5 px-2 text-right text-slate-700" x-text="item.count"></td>
                                        <td class="py-1.5 px-2 text-right text-blue-600 font-semibold" x-text="item.percentage + '%'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Tab 3: Turnaround Samples Table --}}
                    <div x-show="panel4Tab === 'turnaround'" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-[10px] uppercase font-bold text-slate-400">
                                    <th class="py-1.5 px-2">Reg No</th>
                                    <th class="py-1.5 px-2">Arr Actual</th>
                                    <th class="py-1.5 px-2">Dep Actual</th>
                                    <th class="py-1.5 px-2 text-right">Duration</th>
                                    <th class="py-1.5 px-2 text-center">Stand</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 font-mono">
                                <template x-for="item in groundOps.turnaround_pairs ? groundOps.turnaround_pairs.slice(0, 5) : []" :key="item.reg_no + item.arr_time">
                                    <tr>
                                        <td class="py-1.5 px-2 font-bold text-slate-800" x-text="item.reg_no"></td>
                                        <td class="py-1.5 px-2 text-slate-600" x-text="item.arr_time"></td>
                                        <td class="py-1.5 px-2 text-slate-600" x-text="item.dep_time"></td>
                                        <td class="py-1.5 px-2 text-right font-bold text-blue-600" x-text="item.ground_min + ' min'"></td>
                                        <td class="py-1.5 px-2 text-center">
                                            <span class="px-1.5 py-0.5 rounded text-[10px]"
                                                  :class="item.stand_change ? 'bg-amber-100 text-amber-800 font-bold' : 'text-slate-500'"
                                                  x-text="item.stand_arr + (item.stand_change ? ' → ' + item.stand_dep : '')"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>

        </div>

        {{-- ══ ROUTE ANALYTICS, SPECIAL SUFFIXES & INJOURNEY NETWORK MAP ═══════ --}}
        @include('fdr.partials.view_normal_route_analytics')

        {{-- ══ 5. FLIGHT / PASSENGER / CARGO ANALYTICS ════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
            
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                            </svg>
                        </div>
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">
                            FLIGHT / PASSENGER / CARGO ANALYTICS
                        </h2>
                    </div>
                    <p class="text-[11px] text-slate-500 font-medium mt-1">
                        Operational trend based on the active FDR filters and analysis period.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-extrabold uppercase bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs">
                        SOURCE AIRPORT: <span class="text-blue-700 font-black">{{ $meta['report_airport'] ?? ($meta['airport'] ?? 'CGK') }}</span>
                    </span>
                </div>
            </div>

            {{-- Coordinated Segmented-Control Filter Bar directly above charts --}}
            <div class="flex flex-wrap items-center justify-between gap-4 p-3 bg-slate-50/90 rounded-xl border border-slate-200">
                <div class="flex flex-wrap items-center gap-4">
                    
                    {{-- 1. Flight Movement Segmented Control --}}
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Flight Movement</span>
                        <div class="inline-flex rounded-lg p-0.5 bg-white border border-slate-200 shadow-2xs">
                            <button type="button" @click="setLegFilter('ALL')"
                                    :class="(filters.leg === 'ALL' || !filters.leg) ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-3 py-1 text-xs rounded-md transition cursor-pointer">
                                ALL
                            </button>
                            <button type="button" @click="setLegFilter('ARR')"
                                    :class="(filters.leg === 'ARR' || filters.leg === 'ARRIVAL') ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-3 py-1 text-xs rounded-md transition cursor-pointer">
                                ARR
                            </button>
                            <button type="button" @click="setLegFilter('DEP')"
                                    :class="(filters.leg === 'DEP' || filters.leg === 'DEPARTURE') ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-3 py-1 text-xs rounded-md transition cursor-pointer">
                                DEP
                            </button>
                        </div>
                    </div>

                    <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

                    {{-- 2. Traffic Type Segmented Control --}}
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Traffic Type</span>
                        <div class="inline-flex rounded-lg p-0.5 bg-white border border-slate-200 shadow-2xs">
                            <button type="button" @click="setTrafficFilter('ALL')"
                                    :class="(filters.traffic === 'ALL' || !filters.traffic) ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-3 py-1 text-xs rounded-md transition cursor-pointer">
                                ALL
                            </button>
                            <button type="button" @click="setTrafficFilter('DOM')"
                                    :class="(filters.traffic === 'DOM' || filters.traffic === 'DOMESTIC') ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-3 py-1 text-xs rounded-md transition cursor-pointer">
                                DOM
                            </button>
                            <button type="button" @click="setTrafficFilter('INTL')"
                                    :class="(filters.traffic === 'INTL' || filters.traffic === 'INTERNATIONAL' || filters.traffic === 'INT') ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-3 py-1 text-xs rounded-md transition cursor-pointer">
                                INTL
                            </button>
                        </div>
                    </div>

                    <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

                    {{-- 3. Time Basis Segmented Control --}}
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Time Basis</span>
                        <div class="inline-flex rounded-lg p-0.5 bg-white border border-slate-200 shadow-2xs">
                            <button type="button" @click="setTimeBasis('scheduled')"
                                    :class="filters.time_basis === 'scheduled' ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-2.5 py-1 text-[11px] rounded-md transition cursor-pointer">
                                SCHEDULED
                            </button>
                            <button type="button" @click="setTimeBasis('actual')"
                                    :class="filters.time_basis === 'actual' ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-2.5 py-1 text-[11px] rounded-md transition cursor-pointer">
                                ACTUAL
                            </button>
                        </div>
                    </div>

                </div>

                {{-- Active Filter Display --}}
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">ACTIVE VIEW:</span>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-black bg-blue-50 text-blue-800 border border-blue-200 shadow-2xs"
                          x-text="getActiveViewLabel()">
                        ALL • ALL
                    </span>
                </div>
            </div>

            {{-- VISUAL A: FLIGHT MOVEMENT (Hourly Distribution - Full Width) --}}
            <div class="p-4 rounded-xl border border-slate-200/90 bg-white shadow-2xs space-y-3">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                FLIGHT MOVEMENT — HOURLY DISTRIBUTION
                            </h3>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-slate-100 text-slate-700 border border-slate-200"
                                  x-text="getHourlyPeriodContext()">
                                01 JUL 2026 → 31 DEC 2026 • 184 DAYS
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider"
                                  :class="filters.time_basis === 'actual' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200'"
                                  x-text="'TIME BASIS: ' + (filters.time_basis || 'actual').toUpperCase() + (filters.time_basis === 'actual' ? ' (AIBT/AOBT)' : ' (SIBT/SOBT)')">
                                TIME BASIS: ACTUAL (AIBT/AOBT)
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium mt-0.5">
                            Aggregated across selected source period &bull; Exactly 24 hourly buckets (00:00–23:00)
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        {{-- Peak Hour KPI Pill (Section 25) --}}
                        <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg bg-amber-50/90 border border-amber-200 text-amber-900 shadow-2xs">
                            <div class="flex flex-col">
                                <span class="text-[9px] font-extrabold uppercase tracking-wider text-amber-700">PEAK HOUR</span>
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-xs font-black font-mono text-amber-950" x-text="getHourlyPeakHourRange()">10:00–10:59</span>
                                    <span class="text-[11px] font-bold text-amber-800" x-text="getHourlyPeakMovements()">1,245 mov</span>
                                    <template x-if="getHourlyPeakAverage()">
                                        <span class="text-[10px] text-amber-700 font-medium" x-text="'• Avg ' + getHourlyPeakAverage() + '/day'"></span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Dynamic Legend based on active filters (Section 15, 17, 18) --}}
                        <div class="flex flex-wrap items-center gap-3 text-[11px] font-semibold text-slate-600">
                            <template x-if="filters.leg !== 'DEP' && filters.leg !== 'DEPARTURE'">
                                <div class="flex items-center gap-2">
                                    <template x-if="filters.traffic !== 'INTL' && filters.traffic !== 'INTERNATIONAL' && filters.traffic !== 'INT'">
                                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-xs bg-[#FCD34D] border border-[#F59E0B]"></span>Arr Dom</span>
                                    </template>
                                    <template x-if="filters.traffic !== 'DOM' && filters.traffic !== 'DOMESTIC'">
                                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-xs bg-[#D97706] border border-[#B45309]"></span>Arr Intl</span>
                                    </template>
                                </div>
                            </template>
                            <template x-if="filters.leg !== 'ARR' && filters.leg !== 'ARRIVAL'">
                                <div class="flex items-center gap-2">
                                    <template x-if="filters.traffic !== 'INTL' && filters.traffic !== 'INTERNATIONAL' && filters.traffic !== 'INT'">
                                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-xs bg-[#93C5FD] border border-[#3B82F6]"></span>Dep Dom</span>
                                    </template>
                                    <template x-if="filters.traffic !== 'DOM' && filters.traffic !== 'DOMESTIC'">
                                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-xs bg-[#1E40AF] border border-[#172554]"></span>Dep Intl</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="h-[300px] w-full relative">
                    <canvas id="fdrFlightMovementChart"></canvas>
                </div>
            </div>

            {{-- VISUAL B & C: PASSENGER TREND & CARGO TREND (Two Columns Side-by-Side) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                
                {{-- B. PASSENGER TREND --}}
                <div class="p-4 rounded-xl border border-slate-200/90 bg-white shadow-2xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                PASSENGER TREND
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium">
                                Passenger movement by time based on active filters.
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-[10px] font-semibold text-slate-600">
                            <template x-if="filters.leg !== 'DEP' && filters.leg !== 'DEPARTURE'">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-1 rounded-full bg-[#F59E0B]"></span>Arrival Pax</span>
                            </template>
                            <template x-if="filters.leg !== 'ARR' && filters.leg !== 'ARRIVAL'">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-1 rounded-full bg-[#2563EB]"></span>Departure Pax</span>
                            </template>
                        </div>
                    </div>
                    <div class="h-[240px] w-full relative">
                        <canvas id="fdrPaxTrendChart"></canvas>
                    </div>
                </div>

                {{-- C. CARGO TREND --}}
                <div class="p-4 rounded-xl border border-slate-200/90 bg-white shadow-2xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                CARGO TREND
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium">
                                Cargo movement by time based on active filters.
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-[10px] font-semibold text-slate-600">
                            <template x-if="filters.leg !== 'DEP' && filters.leg !== 'DEPARTURE'">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-1 rounded-full bg-[#14B8A6]"></span>Arrival Cargo</span>
                            </template>
                            <template x-if="filters.leg !== 'ARR' && filters.leg !== 'ARRIVAL'">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-1 rounded-full bg-[#059669]"></span>Departure Cargo</span>
                            </template>
                        </div>
                    </div>
                    <div class="h-[240px] w-full relative">
                        <canvas id="fdrCargoTrendChart"></canvas>
                    </div>
                </div>

            </div>

        </div>

        {{-- ══ 6. DETAILED FLIGHT MOVEMENT REGISTRY ════════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
            
            {{-- Header Row: Title, Record Count Badge, Search, Per-Page Selector --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-3">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">
                            Detailed Flight Movement Registry
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                            Total Records: <span x-text="totalRecords">190</span> (<span x-text="kpis.arrivals">95</span> Arrival / <span x-text="kpis.departures">95</span> Departure)
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-medium">Showing flight movements for selected period • Click row for details</p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- Search in Table --}}
                    <div class="relative">
                        <input type="text" x-model="filters.search" @input.debounce.300ms="triggerFilter(1)" @keydown.enter.prevent="triggerFilter(1)"
                               placeholder="Search flight no, route, reg, airline..."
                               class="text-xs rounded-xl border border-slate-200 bg-slate-50/50 pl-8 pr-3 py-1.5 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 w-64">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    {{-- Page Size Selector --}}
                    <select x-model.number="pagination.per_page" @change="changePerPage()"
                            class="text-xs font-bold rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option value="10">10 / page</option>
                        <option value="25">25 / page</option>
                        <option value="50" selected>50 / page</option>
                        <option value="100">100 / page</option>
                    </select>
                </div>
            </div>

            {{-- Detailed Table (16 Columns matching Mockup) --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs whitespace-nowrap">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/70 text-[10px] uppercase font-black text-slate-500 tracking-wider">
                            <th class="py-2.5 px-3">#</th>
                            <th class="py-2.5 px-3">Airline</th>
                            <th class="py-2.5 px-3">Flight No</th>
                            <th class="py-2.5 px-3">Leg</th>
                            <th class="py-2.5 px-3">Route</th>
                            <th class="py-2.5 px-3">Sched (SIBT/SOBT)</th>
                            <th class="py-2.5 px-3">Actual (AIBT/AOBT)</th>
                            <th class="py-2.5 px-3">Reg No</th>
                            <th class="py-2.5 px-3 text-right">CAP</th>
                            <th class="py-2.5 px-3 text-right">LOAD</th>
                            <th class="py-2.5 px-3 text-right">LF%</th>
                            <th class="py-2.5 px-3 text-right">Passenger</th>
                            <th class="py-2.5 px-3 text-right">Cargo (kg)</th>
                            <th class="py-2.5 px-3 text-right">Baggage (kg)</th>
                            <th class="py-2.5 px-3">Stand</th>
                            <th class="py-2.5 px-3">Runway</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                        {{-- 1. Loading State --}}
                        <template x-if="isFiltering">
                            <tr>
                                <td colspan="17" class="py-12 text-center text-slate-500 font-sans">
                                    <div class="inline-flex items-center gap-2">
                                        <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                        </svg>
                                        <span class="font-bold text-xs text-slate-700">Memuat data FDR...</span>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        {{-- 2. Error State --}}
                        <template x-if="!isFiltering && filterError">
                            <tr>
                                <td colspan="17" class="py-10 text-center text-rose-600 font-sans">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-8 h-8 text-rose-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span class="font-bold text-xs" x-text="filterError"></span>
                                        <button type="button" @click="triggerFilter(pagination.current_page)" class="mt-2 px-3 py-1 bg-rose-50 text-rose-700 text-[11px] font-bold rounded-lg border border-rose-200 hover:bg-rose-100">Coba Lagi</button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        {{-- 3. Empty State (Zero Filter Match) --}}
                        <template x-if="!isFiltering && !filterError && (!flightRecords || flightRecords.length === 0)">
                            <tr>
                                <td colspan="17" class="py-12 text-center text-slate-500 font-sans">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-10 h-10 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="font-bold text-xs text-slate-700">Tidak ada data FDR yang sesuai dengan filter.</span>
                                        <span class="text-[11px] text-slate-400 mt-0.5">Silakan reset filter atau gunakan parameter pencarian lain.</span>
                                        <button type="button" @click="clearAllFilters()" class="mt-3 px-3 py-1 bg-blue-50 text-blue-700 text-[11px] font-bold rounded-lg border border-blue-200 hover:bg-blue-100 transition">Reset Filter</button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        {{-- 4. Normal Flight Rows --}}
                        <template x-for="(r, idx) in (!isFiltering && !filterError ? (flightRecords || []) : [])" :key="r.id ? (uploadId + '-id-' + r.id) : (r.source_row_number ? (uploadId + '-srn-' + r.source_row_number) : (r.flight_no ? (uploadId + '-' + r.flight_no + '-' + idx) : (uploadId + '-row-' + idx)))">
                            <tr @click="openFlightDetails(r)" class="hover:bg-blue-50/40 transition cursor-pointer">
                                <td class="py-2.5 px-3 text-slate-400 font-sans" x-text="((pagination.current_page - 1) * pagination.per_page) + idx + 1"></td>
                                <td class="py-2.5 px-3 font-sans font-bold text-slate-800" x-text="r.air_line || r.airline || 'N/A'"></td>
                                <td class="py-2.5 px-3 font-bold text-blue-600 hover:underline" x-text="r.flight_no || r.flightNumber || 'N/A'"></td>
                                <td class="py-2.5 px-3 font-sans">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                          :class="(r.direction === 'ARRIVAL' || r.movementType === 'Arrival') ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'"
                                          x-text="r.leg || r.legRaw || ((r.direction === 'ARRIVAL' || r.movementType === 'Arrival') ? (r.sched_type === 'UNSCHED' || r.legSubtype === 'UNSCHED' ? 'A UNSCHED' : 'A SCHED') : (r.sched_type === 'UNSCHED' || r.legSubtype === 'UNSCHED' ? 'D UNSCHED' : 'D SCHED'))"></span>
                                </td>
                                <td class="py-2.5 px-3 font-sans text-slate-700" x-text="r.route || (r.origin && r.destination ? (r.origin + ' → ' + r.destination) : 'N/A')"></td>
                                <td class="py-2.5 px-3 text-slate-500 font-mono" x-text="r.sched_display || r.scheduledTime || ((r.direction === 'ARRIVAL' || r.movementType === 'Arrival') ? r.sibt : r.sobt) || '00:00'"></td>
                                <td class="py-2.5 px-3 font-bold font-mono"
                                    :class="(r.actual_display && r.actual_display !== 'N/A') || (r.actualTime && r.actualTime !== '') ? 'text-slate-900' : 'text-slate-400'"
                                    x-text="r.actual_display || r.actualTime || ((r.direction === 'ARRIVAL' || r.movementType === 'Arrival') ? r.aibt : r.aobt) || 'N/A'"></td>
                                <td class="py-2.5 px-3 font-bold text-slate-700" x-text="r.reg_no || r.aircraftReg || 'N/A'"></td>
                                <td class="py-2.5 px-3 text-right text-slate-600" x-text="r.cap != null ? r.cap : (r.capacity != null ? r.capacity : 0)"></td>
                                <td class="py-2.5 px-3 text-right text-slate-600" x-text="r.load != null ? r.load : (r.paxLoad != null ? r.paxLoad : 0)"></td>
                                <td class="py-2.5 px-3 text-right font-bold"
                                    :class="(r.load_factor >= 85 || r.loadFactorPercent >= 85) ? 'text-emerald-600' : ((r.load_factor >= 70 || r.loadFactorPercent >= 70) ? 'text-blue-600' : 'text-amber-600')"
                                    x-text="(r.load_factor != null && r.load_factor !== 'N/A') ? (r.load_factor + '%') : (r.loadFactorPercent != null ? (r.loadFactorPercent + '%') : '0%')"></td>
                                <td class="py-2.5 px-3 text-right font-sans text-slate-800 font-semibold" x-text="(Number(r.adult || 0) + Number(r.child || 0) + Number(r.infant || 0)).toLocaleString()"></td>
                                <td class="py-2.5 px-3 text-right text-slate-600" x-text="(r.cargo_kg != null ? Number(r.cargo_kg) : (r.cargoKg != null ? Number(r.cargoKg) : 0)).toLocaleString()"></td>
                                <td class="py-2.5 px-3 text-right text-slate-600" x-text="(r.baggage_kg != null ? Number(r.baggage_kg) : (r.baggageKg != null ? Number(r.baggageKg) : 0)).toLocaleString()"></td>
                                <td class="py-2.5 px-3 font-sans text-slate-700 font-semibold" x-text="r.stand || 'N/A'"></td>
                                <td class="py-2.5 px-3 font-mono text-slate-600" x-text="r.runway || 'N/A'"></td>
                                <td class="py-2.5 px-3 text-center font-sans">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                          :class="{
                                              'bg-sky-100 text-sky-700': (r.delay_minutes != null ? r.delay_minutes : r.delayMinutes) < -15,
                                              'bg-emerald-100 text-emerald-700': (r.delay_minutes != null ? r.delay_minutes : r.delayMinutes) >= -15 && (r.delay_minutes != null ? r.delay_minutes : r.delayMinutes) <= 15,
                                              'bg-rose-100 text-rose-700': (r.delay_minutes != null ? r.delay_minutes : r.delayMinutes) > 15,
                                              'bg-slate-100 text-slate-600': (r.delay_minutes === null || r.delay_minutes === undefined) && (r.delayMinutes === null || r.delayMinutes === undefined)
                                          }"
                                          x-text="(r.delay_minutes != null ? r.delay_minutes : r.delayMinutes) < -15 ? 'EARLY' : ((r.delay_minutes != null ? r.delay_minutes : r.delayMinutes) <= 15 ? 'ON TIME' : 'DELAYED')"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Table Pagination --}}
            <div class="flex items-center justify-between text-xs pt-3 border-t border-slate-100">
                <div class="text-slate-500">
                    Showing page <strong class="text-slate-900" x-text="pagination.current_page"></strong> of <strong class="text-slate-900" x-text="pagination.total_pages"></strong>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold disabled:opacity-40 disabled:cursor-not-allowed transition">
                        &larr; Prev
                    </button>
                    <div class="flex items-center gap-1">
                        <template x-for="p in getVisiblePageNumbers()" :key="p">
                            <button type="button" @click="changePage(p)"
                                    :class="p === pagination.current_page ? 'bg-blue-600 text-white font-bold' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-semibold'"
                                    class="w-7 h-7 rounded-lg text-xs flex items-center justify-center transition"
                                    x-text="p"></button>
                        </template>
                    </div>
                    <button type="button" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.total_pages"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold disabled:opacity-40 disabled:cursor-not-allowed transition">
                        Next &rarr;
                    </button>
                </div>
            </div>

        </div>

    </div>

    </main>

    {{-- ══ FLIGHT DETAILS MODAL ════════════════════════════════════════════════ --}}
    <div x-show="selectedFlight !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="selectedFlight = null" class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    <h3 class="text-sm font-black text-slate-900" x-text="'Flight Details: ' + (selectedFlight ? selectedFlight.flight_no : '')"></h3>
                </div>
                <button @click="selectedFlight = null" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <template x-if="selectedFlight">
                <div class="space-y-4 text-xs font-mono">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="text-[10px] text-slate-400 font-sans">Airline</div>
                            <div class="font-bold text-slate-800" x-text="selectedFlight.air_line"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="text-[10px] text-slate-400 font-sans">Paired Flight</div>
                            <div class="font-bold text-slate-800" x-text="selectedFlight.paired_no || 'N/A'"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="text-[10px] text-slate-400 font-sans">Leg Direction</div>
                            <div class="font-bold text-blue-600" x-text="selectedFlight.leg || selectedFlight.direction"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="text-[10px] text-slate-400 font-sans">Route</div>
                            <div class="font-bold text-slate-800" x-text="selectedFlight.route"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="text-[10px] text-slate-400 font-sans">Registration</div>
                            <div class="font-bold text-slate-800" x-text="selectedFlight.reg_no"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="text-[10px] text-slate-400 font-sans">Aircraft Type</div>
                            <div class="font-bold text-slate-800" x-text="selectedFlight.desc || 'N/A'"></div>
                        </div>
                    </div>

                    {{-- Timings --}}
                    <div class="p-3 rounded-xl bg-blue-50/50 border border-blue-100 grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-[10px] font-sans text-blue-800 font-bold">Scheduled Time (SIBT/SOBT)</div>
                            <div class="text-sm font-bold text-slate-900 font-mono" x-text="selectedFlight.sched_display || (selectedFlight.direction === 'ARRIVAL' ? selectedFlight.sibt : selectedFlight.sobt)"></div>
                        </div>
                        <div>
                            <div class="text-[10px] font-sans text-blue-800 font-bold">Actual Block Time (AIBT/AOBT)</div>
                            <div class="text-sm font-bold text-slate-900 font-mono" x-text="selectedFlight.actual_display || (selectedFlight.direction === 'ARRIVAL' ? selectedFlight.aibt : selectedFlight.aobt)"></div>
                        </div>
                    </div>

                    {{-- Capacity & Load --}}
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="text-[10px] font-sans text-slate-400">Capacity</div>
                            <div class="text-base font-bold text-slate-900" x-text="selectedFlight.cap"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="text-[10px] font-sans text-slate-400">Load</div>
                            <div class="text-base font-bold text-slate-900" x-text="selectedFlight.load"></div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="text-[10px] font-sans text-slate-400">Load Factor</div>
                            <div class="text-base font-bold text-blue-600" x-text="selectedFlight.load_factor !== 'N/A' ? selectedFlight.load_factor + '%' : 'N/A'"></div>
                        </div>
                    </div>

                    {{-- Passenger & Weight Breakdown --}}
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 text-[11px] space-y-1">
                        <div>Pax: Adult <strong x-text="selectedFlight.adult"></strong> &bull; Child <strong x-text="selectedFlight.child"></strong> &bull; Infant <strong x-text="selectedFlight.infant"></strong></div>
                        <div>Transit <strong x-text="selectedFlight.transit"></strong> &bull; Transfer <strong x-text="selectedFlight.transfer"></strong> &bull; Crew <strong x-text="selectedFlight.crw"></strong></div>
                        <div>Cargo <strong x-text="(selectedFlight.cargo_kg || 0) + ' KG'"></strong> &bull; Baggage <strong x-text="(selectedFlight.baggage_kg || 0) + ' KG'"></strong></div>
                        <div>Stand <strong x-text="selectedFlight.stand"></strong> &bull; Runway <strong x-text="selectedFlight.runway"></strong></div>
                    </div>
                </div>
            </template>

            <div class="text-right pt-2 border-t border-slate-100">
                <button type="button" @click="selectedFlight = null" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs hover:bg-slate-200 transition">
                    Close Details
                </button>
            </div>
        </div>
    </div>

    {{-- FOOTER --}}
    <footer class="w-full border-t border-slate-200 bg-white px-4 sm:px-8 py-3 flex items-center justify-between text-[11px] text-slate-500">
        <div>SlotWaves Aviation Control Room &copy; {{ date('Y') }} • FDR Operational Intelligence</div>
        <div class="flex items-center gap-2">
            <span>OASYS Operational Reporting System</span>
            <span>•</span>
            <span class="font-mono text-emerald-600 font-bold">ONLINE</span>
        </div>
    </footer>

</div>

@push('scripts')
<script>
// Module-level non-reactive store for Chart.js instances.
const _fdrCharts = {
    delayHist: null,
    paxDonut: null,
    payloadDonut: null,
    flightMovement: null,
    paxTrend: null,
    cargoTrend: null,
    // Polymorphic views
    slfHist: null,
    slfRoute: null,
    slfAircraft: null,
    slfHourly: null,
    yoyBar: null,
    yoyTrend: null,
    appsVariance: null,
    appsDest: null,
    ediflyDonut: null,
    ediflyGap: null,
};

// Static coordinate dictionary for 35 InJourney Airports + common domestic & international destinations
const INJOURNEY_AIRPORTS_STATIC = {
    // 35 InJourney Airports
    CGK: { code: 'CGK', name: 'Soekarno-Hatta', city: 'Jakarta', lat: -6.1256, lon: 106.6558 },
    BDO: { code: 'BDO', name: 'Husein Sastranegara', city: 'Bandung', lat: -6.9006, lon: 107.5761 },
    BKS: { code: 'BKS', name: 'Fatmawati Soekarno', city: 'Bengkulu', lat: -3.8644, lon: 102.3394 },
    BTJ: { code: 'BTJ', name: 'Sultan Iskandar Muda', city: 'Banda Aceh', lat: 5.5244, lon: 95.4208 },
    BWX: { code: 'BWX', name: 'Banyuwangi', city: 'Banyuwangi', lat: -8.3094, lon: 114.3397 },
    DJB: { code: 'DJB', name: 'Sultan Thaha', city: 'Jambi', lat: -1.6378, lon: 103.6444 },
    DTB: { code: 'DTB', name: 'Raja Sisingamangaraja XII', city: 'Siborong-Borong', lat: 2.2594, lon: 98.9933 },
    HLP: { code: 'HLP', name: 'Halim Perdanakusuma', city: 'Jakarta', lat: -6.2667, lon: 106.8908 },
    KJT: { code: 'KJT', name: 'Kertajati', city: 'Majalengka', lat: -6.6572, lon: 108.1883 },
    KNO: { code: 'KNO', name: 'Kualanamu', city: 'Medan', lat: 3.6422, lon: 98.8853 },
    PCB: { code: 'PCB', name: 'Pondok Cabe', city: 'Tangerang Selatan', lat: -6.3489, lon: 106.7644 },
    PDG: { code: 'PDG', name: 'Minangkabau', city: 'Padang', lat: -0.7869, lon: 100.2808 },
    PGK: { code: 'PGK', name: 'Depati Amir', city: 'Pangkal Pinang', lat: -2.1625, lon: 106.1389 },
    PKU: { code: 'PKU', name: 'Sultan Syarif Kasim II', city: 'Pekanbaru', lat: 0.4608, lon: 101.4447 },
    PKY: { code: 'PKY', name: 'Tjilik Riwut', city: 'Palangkaraya', lat: -2.2253, lon: 113.9436 },
    PLM: { code: 'PLM', name: 'Sultan Mahmud Badaruddin II', city: 'Palembang', lat: -2.8986, lon: 104.7003 },
    PNK: { code: 'PNK', name: 'Supadio', city: 'Pontianak', lat: -0.1506, lon: 109.4039 },
    PWL: { code: 'PWL', name: 'Jenderal Besar Soedirman', city: 'Purbalingga', lat: -7.4583, lon: 109.4194 },
    TJQ: { code: 'TJQ', name: 'H.A.S. Hanandjoeddin', city: 'Tanjung Pandan', lat: -2.7608, lon: 107.7547 },
    TKG: { code: 'TKG', name: 'Radin Inten II', city: 'Bandar Lampung', lat: -5.2425, lon: 105.1789 },
    TNJ: { code: 'TNJ', name: 'Raja Haji Fisabilillah', city: 'Tanjung Pinang', lat: 0.9239, lon: 104.5317 },
    BTH: { code: 'BTH', name: 'Hang Nadim', city: 'Batam', lat: 1.1211, lon: 104.1189 },
    AMQ: { code: 'AMQ', name: 'Pattimura', city: 'Ambon', lat: -3.7103, lon: 128.0892 },
    BDJ: { code: 'BDJ', name: 'Syamsudin Noor', city: 'Banjarmasin', lat: -3.4422, lon: 114.7578 },
    BIK: { code: 'BIK', name: 'Frans Kaisiepo', city: 'Biak', lat: -1.1906, lon: 136.1089 },
    BPN: { code: 'BPN', name: 'Sultan Aji Muhammad Sulaiman Sepinggan', city: 'Balikpapan', lat: -1.2683, lon: 116.8947 },
    DJJ: { code: 'DJJ', name: 'Sentani', city: 'Jayapura', lat: -2.5769, lon: 140.5161 },
    DPS: { code: 'DPS', name: 'I Gusti Ngurah Rai', city: 'Denpasar', lat: -8.7481, lon: 115.1672 },
    JOG: { code: 'JOG', name: 'Adisutjipto', city: 'Yogyakarta', lat: -7.7881, lon: 110.4319 },
    KOE: { code: 'KOE', name: 'El Tari', city: 'Kupang', lat: -10.1714, lon: 123.6711 },
    LOP: { code: 'LOP', name: 'Zainuddin Abdul Madjid', city: 'Lombok', lat: -8.7589, lon: 116.2753 },
    MDC: { code: 'MDC', name: 'Sam Ratulangi', city: 'Manado', lat: 1.5497, lon: 124.9264 },
    SRG: { code: 'SRG', name: 'Jenderal Ahmad Yani', city: 'Semarang', lat: -6.9742, lon: 110.3753 },
    SUB: { code: 'SUB', name: 'Juanda', city: 'Surabaya', lat: -7.3797, lon: 112.7875 },
    UPG: { code: 'UPG', name: 'Sultan Hasanuddin', city: 'Makassar', lat: -5.0617, lon: 119.5542 },
    YIA: { code: 'YIA', name: 'Yogyakarta International', city: 'Kulon Progo', lat: -7.9069, lon: 110.0578 },

    // Actual upload route fallbacks not guaranteed to exist in every airport master snapshot.
    // These are only coordinate fallbacks; route membership still comes exclusively from the uploaded FDR.
    AAP: { code: 'AAP', name: 'Aji Pangeran Tumenggung Pranoto', city: 'Samarinda', lat: -0.3744, lon: 117.2500 },
    DRW: { code: 'DRW', name: 'Darwin International', city: 'Darwin', lat: -12.4147, lon: 130.8766 },
    SOC: { code: 'SOC', name: 'Adi Soemarmo', city: 'Surakarta', lat: -7.5161, lon: 110.7569 },
    BEJ: { code: 'BEJ', name: 'Kalimarau', city: 'Berau', lat: 2.1555, lon: 117.4322 },
    BUU: { code: 'BUU', name: 'Muara Bungo', city: 'Bungo', lat: -1.1277, lon: 102.1350 },
    DMK: { code: 'DMK', name: 'Don Mueang International', city: 'Bangkok', lat: 13.9126, lon: 100.6068 },
    LLJ: { code: 'LLJ', name: 'Silampari', city: 'Lubuklinggau', lat: -3.2933, lon: 102.9140 },
    MLG: { code: 'MLG', name: 'Abdul Rachman Saleh', city: 'Malang', lat: -7.9266, lon: 112.7140 },
    NNG: { code: 'NNG', name: 'Nanning Wuxu International', city: 'Nanning', lat: 22.6083, lon: 108.1722 },
    PEN: { code: 'PEN', name: 'Penang International', city: 'Penang', lat: 5.2971, lon: 100.2769 },
    PKN: { code: 'PKN', name: 'Iskandar', city: 'Pangkalan Bun', lat: -2.7052, lon: 111.6736 },
    TRK: { code: 'TRK', name: 'Juwata', city: 'Tarakan', lat: 3.3267, lon: 117.5656 },
    KOS: { code: 'KOS', name: 'Sihanoukville International', city: 'Sihanoukville', lat: 10.5797, lon: 103.6360 },

    // Additional Domestic & International Destinations
    SOQ: { code: 'SOQ', name: 'Domine Eduard Osok', city: 'Sorong', lat: -0.9256, lon: 131.2872 },
    PLW: { code: 'PLW', name: 'Mutiara SIS Al-Jufrie', city: 'Palu', lat: -0.9183, lon: 119.9103 },
    TIM: { code: 'TIM', name: 'Mozes Kilangin', city: 'Timika', lat: -4.5286, lon: 136.8869 },
    MKQ: { code: 'MKQ', name: 'Mopah', city: 'Merauke', lat: -8.5203, lon: 140.4181 },
    KDI: { code: 'KDI', name: 'Haluoleo', city: 'Kendari', lat: -4.0819, lon: 122.4172 },
    GTO: { code: 'GTO', name: 'Djalaluddin', city: 'Gorontalo', lat: 0.6372, lon: 122.8519 },
    LUW: { code: 'LUW', name: 'Syukuran Aminuddin Amir', city: 'Luwuk', lat: -1.0375, lon: 122.7719 },
    TTE: { code: 'TTE', name: 'Sultan Babullah', city: 'Ternate', lat: 0.8319, lon: 127.3803 },
    LBJ: { code: 'LBJ', name: 'Komodo', city: 'Labuan Bajo', lat: -8.4878, lon: 119.8886 },
    BMU: { code: 'BMU', name: 'Sultan Muhammad Salahuddin', city: 'Bima', lat: -8.5394, lon: 118.6872 },
    SIN: { code: 'SIN', name: 'Singapore Changi', city: 'Singapore', lat: 1.3644, lon: 103.9915 },
    KUL: { code: 'KUL', name: 'Kuala Lumpur Intl', city: 'Kuala Lumpur', lat: 2.7456, lon: 101.7099 },
    HND: { code: 'HND', name: 'Tokyo Haneda', city: 'Tokyo', lat: 35.5494, lon: 139.7798 },
    NRT: { code: 'NRT', name: 'Tokyo Narita', city: 'Tokyo', lat: 35.7647, lon: 140.3863 },
    ICN: { code: 'ICN', name: 'Seoul Incheon', city: 'Seoul', lat: 37.4602, lon: 126.4407 },
    PVG: { code: 'PVG', name: 'Shanghai Pudong', city: 'Shanghai', lat: 31.1443, lon: 121.8083 },
    CAN: { code: 'CAN', name: 'Guangzhou Baiyun', city: 'Guangzhou', lat: 23.3924, lon: 113.2988 },
    SYD: { code: 'SYD', name: 'Sydney Kingsford Smith', city: 'Sydney', lat: -33.9399, lon: 151.1753 },
    MEL: { code: 'MEL', name: 'Melbourne Tullamarine', city: 'Melbourne', lat: -37.6690, lon: 144.8410 },
    PER: { code: 'PER', name: 'Perth Airport', city: 'Perth', lat: -31.9403, lon: 115.9668 },
    JED: { code: 'JED', name: 'King Abdulaziz Intl', city: 'Jeddah', lat: 21.6796, lon: 39.1565 },
    MED: { code: 'MED', name: 'Prince Mohammad bin Abdulaziz', city: 'Medina', lat: 24.5534, lon: 39.7051 },
    DOH: { code: 'DOH', name: 'Hamad Intl', city: 'Doha', lat: 25.2731, lon: 51.6081 },
    BKK: { code: 'BKK', name: 'Bangkok Suvarnabhumi', city: 'Bangkok', lat: 13.6900, lon: 100.7501 },
    HKG: { code: 'HKG', name: 'Hong Kong Intl', city: 'Hong Kong', lat: 22.3080, lon: 113.9185 },
};

function fdrDashboardController() {
    return {
        uploadId: {{ $upload->id }},
        activeReportType: '{{ $reportType ?? 'NORMAL' }}',
        isExHalim: {{ ($reportType ?? '') === 'EX_HALIM' ? 'true' : 'false' }},
        availableReportTypes: ['{{ $reportType ?? 'NORMAL' }}'],
        allParsedByType: {},
        allClientRecords: [],
        currentStage: 'dashboard',
        showStagingCard: false,
        isDragging: false,
        isScanning: false,
        scanError: null,
        stagedFiles: [],
        preScanData: null,
        mergedHtml: '',
        clientParsedData: @json($clientParsedData ?? null),
        filterOutliers: true,
        hoveredAirport: null,
        lockedAirport: '{{ strtoupper(trim($meta['report_airport'] ?? ($meta['airport'] ?? ($airports[0] ?? 'CGK')))) }}',
        abortController: null,
        availableDates: @json($availableDates),
        sourceSummary: @json($sourceSummary),
        dateScope: '{{ $dateScope ?? 'ALL_PERIOD' }}',
        selectedDayOption: '{{ (!empty($filters['analysis_date']) && ($dateScope ?? '') === 'DAY') ? $filters['analysis_date'] : 'ALL_PERIOD' }}',
        filters: {
            airport: '{{ $filters['airport'] }}',
            leg: '{{ in_array($filters['leg'], ['ARR','ARRIVAL']) ? 'ARR' : (in_array($filters['leg'], ['DEP','DEPARTURE']) ? 'DEP' : 'ALL') }}',
            operator: '{{ $filters['operator'] }}',
            traffic: '{{ in_array($filters['traffic'], ['DOM','DOMESTIC']) ? 'DOM' : (in_array($filters['traffic'], ['INTL','INTERNATIONAL','INT']) ? 'INTL' : 'ALL') }}',
            realization: '{{ $filters['realization'] }}',
            analysis_date: '{{ ($dateScope ?? '') === 'ALL_PERIOD' ? '' : ($filters['analysis_date'] ?? '') }}',
            start_date: '{{ $filters['start_date'] }}',
            end_date: '{{ $filters['end_date'] }}',
            search: '{{ $filters['search'] }}',
            time_basis: '{{ $timeBasis }}',
            v: {{ time() }},
        },
        activeChips: @json($filterResult['active_chips'] ?? []),
        kpis: @json($analytics['kpis'] ?? new \stdClass()),
        schedVsReal: @json($analytics['schedule_vs_realization'] ?? new \stdClass()),
        paxAnalytics: @json($analytics['passenger_analytics'] ?? new \stdClass()),
        airlineRoute: @json($analytics['airline_route'] ?? new \stdClass()),
        fleetPerformance: @json($analytics['fleet_performance'] ?? []),
        groundOps: @json($analytics['ground_operations'] ?? new \stdClass()),
        combinedTrend: @json($analytics['combined_trend'] ?? null),
        hourlyDistribution: @json($analytics['hourly_distribution'] ?? new \stdClass()),
        flightRecords: @json($records ?? []),
        totalRecords: {{ $filterResult['filtered_count'] ?? 0 }},
        panel3Tab: 'operators',
        panel4Tab: 'stands',
        pagination: {
            current_page: 1,
            per_page: 50,
            total_pages: Math.max(1, Math.ceil({{ $filterResult['filtered_count'] ?? 0 }} / 50)),
            total: {{ $filterResult['filtered_count'] ?? 0 }}
        },
        selectedFlight: null,
        isFiltering: false,
        filterError: null,
        activeRequestId: 0,
        lastUpdatedTime: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),

        init() {
            if (this.clientParsedData) {
                if (!this.allParsedByType) this.allParsedByType = {};
                this.allParsedByType[this.activeReportType] = this.clientParsedData;
            }
            this.$nextTick(() => {
                this.initAllCharts();
                this.renderNetworkMap();
                this.logReconciliation('INIT');
            });
        },

        safeChartInit(chartKey, initFn) {
            try {
                if (typeof initFn === 'function') {
                    initFn();
                }
            } catch (err) {
                console.error(`[FDR SafeChartInit] Chart [${chartKey}] initialization failed:`, err);
                if (!this._chartErrors) this._chartErrors = {};
                this._chartErrors[chartKey] = err.message || String(err);
            }
        },

        logReconciliation(source = 'INIT') {
            const summary = this.sourceSummary || {};
            const meta = (this.clientParsedData && this.clientParsedData.metadata) || {};
            console.log(`[FDR Data Reconciliation - ${source}]`, {
                source_rows: summary.source_rows ?? (meta.source_rows ?? this.totalRecords),
                movement_rows: summary.movement_rows ?? (meta.movement_rows ?? this.totalRecords),
                summary_rows: summary.summary_rows ?? (meta.summary_rows ?? 0),
                db_rows: summary.db_rows ?? this.totalRecords,
                total_flights: this.kpis ? this.kpis.total_flights : null,
                scheduled_flights: this.kpis ? this.kpis.scheduled_flights : null,
                actual_flights: this.kpis ? this.kpis.actual_flights : null,
                filtered_rows: this.totalRecords,
                displayed_rows: this.flightRecords ? this.flightRecords.length : 0,
                page_size: this.pagination ? this.pagination.per_page : 50,
                current_page: this.pagination ? this.pagination.current_page : 1,
                date_scope: this.dateScope,
                leg: this.filters.leg,
                traffic: this.filters.traffic,
                time_basis: this.filters.time_basis,
            });
        },

        initAllCharts() {
            if (!window.Chart) return;

            // Runtime Diagnostic Contract (§43)
            console.groupCollapsed(`[FDR Runtime Contract] uploadId=${this.uploadId} type=${this.activeReportType}`);
            console.log({
                uploadId: this.uploadId,
                reportType: this.activeReportType,
                filteredCount: this.totalRecords,
                kpis: this.kpis,
                hourlyDistribution: this.hourlyDistribution,
                combinedTrend: this.combinedTrend,
                airlineRoute: this.airlineRoute,
            });
            console.groupEnd();

            const type = this.activeReportType;
            if (type === 'LOAD_FACTOR') {
                this.safeChartInit('slfRank', () => this.buildSlfRankChart());
                this.safeChartInit('slfCombo', () => this.buildSlfComboChart());
            } else if (type === 'COMPARE_LOAD_FACTOR' || type === 'COMPARE_LOAD_FACTOR_DAY') {
                this.safeChartInit('yoyBar', () => this.buildYoyBarChart());
                this.safeChartInit('yoyTrend', () => this.buildYoyTrendChart());
            } else if (type === 'OASYS_VS_APPS' || type === 'DEPARTURE_VS_APPS') {
                this.safeChartInit('appsCompBar', () => this.buildAppsCompBarChart());
                this.safeChartInit('appsDiscrepancy', () => this.buildAppsDiscrepancyChart());
                this.safeChartInit('appsStatusDonut', () => this.buildAppsStatusDonutChart());
            } else if (type === 'OASYS_VS_EDIFLY' || type === 'OASYS_EDIFLY') {
                this.safeChartInit('ediflyDailySync', () => this.buildEdiflyDailySyncChart());
                this.safeChartInit('ediflyBreakdown', () => this.buildEdiflyBreakdownChart());
            } else {
                this.safeChartInit('delay', () => this.buildDelayHistChart());
                this.safeChartInit('pax', () => this.buildPaxDonutChart());
                this.safeChartInit('payload', () => this.buildPayloadDonutChart());
                this.safeChartInit('flight', () => this.buildFlightMovementChart());
                this.safeChartInit('passenger', () => this.buildPaxTrendChart());
                this.safeChartInit('cargo', () => this.buildCargoTrendChart());
            }
        },

        buildPaxCompDonutChart() {
            return this.buildPaxDonutChart();
        },

        buildPayloadCompDonutChart() {
            return this.buildPayloadDonutChart();
        },

        buildDelayHistChart() {
            const ctx = document.getElementById('fdrDelayHistChart');
            if (!ctx) return;
            if (_fdrCharts.delayHist) {
                _fdrCharts.delayHist.destroy();
                _fdrCharts.delayHist = null;
            }

            const rawHist = (this.filterOutliers && this.schedVsReal && this.schedVsReal.hist_bins_filtered)
                ? this.schedVsReal.hist_bins_filtered
                : (this.schedVsReal && this.schedVsReal.hist_bins && Array.isArray(this.schedVsReal.hist_bins) && this.schedVsReal.hist_bins.length === 9)
                    ? this.schedVsReal.hist_bins
                    : ((this.schedVsReal && this.schedVsReal.histogram) || []);

            const histData = Array.isArray(rawHist) ? rawHist : (rawHist && typeof rawHist === 'object' ? Object.values(rawHist) : []);
            if (!histData.length) {
                console.warn('[FDR DelayHistChart] No histogram bins available');
                return;
            }

            const labels = histData.map(b => (b && b.label) || '');
            const counts = histData.map(b => Number((b && b.count) || 0));
            const bgColors = histData.map(b => (b && b.color) || '#64748B');

            _fdrCharts.delayHist = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Flights',
                        data: counts,
                        backgroundColor: bgColors,
                        borderRadius: 4,
                        barPercentage: 0.65,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0F172A',
                            titleColor: '#94A3B8',
                            bodyColor: '#FFFFFF',
                            titleFont: { size: 10, weight: 'bold' },
                            bodyFont: { size: 11 },
                            padding: 8,
                            cornerRadius: 6,
                            callbacks: {
                                title: (items) => {
                                    const bin = histData[items[0].dataIndex] || {};
                                    return `Category: ${bin.group || 'VARIANCE'}`;
                                },
                                label: (item) => {
                                    const bin = histData[item.dataIndex] || {};
                                    return [
                                        `Variance: ${bin.desc || bin.label}`,
                                        `Flights: ${item.parsed.y}`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 8.5, weight: 'bold' },
                                color: '#64748B',
                                maxRotation: 25,
                                minRotation: 0,
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, font: { size: 10 }, color: '#94A3B8' },
                            grid: { color: '#F1F5F9' }
                        }
                    }
                }
            });
        },

        buildPaxDonutChart() {
            const ctx = document.getElementById('fdrPaxCompDonutChart');
            if (!ctx) return;
            if (_fdrCharts.paxDonut) {
                _fdrCharts.paxDonut.destroy();
                _fdrCharts.paxDonut = null;
            }

            const comp = this.paxAnalytics.composition || {};
            const adult = comp.adult || 0;
            const child = comp.child || 0;
            const infant = comp.infant || 0;
            const total = adult + child + infant;

            const hasData = total > 0;
            const dataVals = hasData ? [adult, child, infant] : [1];
            const bgColors = hasData ? ['#2563EB', '#F59E0B', '#10B981'] : ['#E2E8F0'];

            const metaSegments = [
                { formattedValue: adult.toLocaleString() + ' Pax', percentage: total > 0 ? ((adult / total) * 100).toFixed(1) + '%' : '0.0%' },
                { formattedValue: child.toLocaleString() + ' Pax', percentage: total > 0 ? ((child / total) * 100).toFixed(1) + '%' : '0.0%' },
                { formattedValue: infant.toLocaleString() + ' Pax', percentage: total > 0 ? ((infant / total) * 100).toFixed(1) + '%' : '0.0%' },
            ];

            _fdrCharts.paxDonut = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: hasData ? ['Adult', 'Child', 'Infant'] : ['No Data'],
                    datasets: [{
                        data: dataVals,
                        backgroundColor: bgColors,
                        borderColor: '#FFFFFF',
                        borderWidth: 2,
                        hoverOffset: hasData ? 4 : 0,
                        metaSegments: hasData ? metaSegments : null,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: false,
                            external: hasData ? (context) => this.renderExternalDonutTooltip(context) : null,
                        }
                    }
                }
            });

            ctx.onmouseleave = () => {
                const el = document.getElementById('fdr-donut-floating-tooltip');
                if (el) {
                    el.style.opacity = '0';
                    el.style.transform = 'scale(0.96)';
                }
            };
        },

        buildPayloadDonutChart() {
            const ctx = document.getElementById('fdrPayloadCompDonutChart');
            if (!ctx) return;
            if (_fdrCharts.payloadDonut) {
                _fdrCharts.payloadDonut.destroy();
                _fdrCharts.payloadDonut = null;
            }

            const cargo = Math.round(this.kpis.total_cargo_kg || 0);
            const baggage = Math.round(this.kpis.total_baggage_kg || 0);
            const pos = Math.round(this.kpis.pos_kg || 0);
            const total = cargo + baggage + pos;

            const hasData = total > 0;
            const dataVals = hasData ? [cargo, baggage, pos] : [1];
            const bgColors = hasData ? ['#10B981', '#3B82F6', '#8B5CF6'] : ['#E2E8F0'];

            const metaSegments = [
                { formattedValue: this.formatPayloadWeightKg(cargo), percentage: total > 0 ? ((cargo / total) * 100).toFixed(1) + '%' : '0.0%' },
                { formattedValue: this.formatPayloadWeightKg(baggage), percentage: total > 0 ? ((baggage / total) * 100).toFixed(1) + '%' : '0.0%' },
                { formattedValue: this.formatPayloadWeightKg(pos), percentage: total > 0 ? ((pos / total) * 100).toFixed(1) + '%' : '0.0%' },
            ];

            _fdrCharts.payloadDonut = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: hasData ? ['Cargo', 'Baggage', 'Pos / Mail'] : ['No Data'],
                    datasets: [{
                        data: dataVals,
                        backgroundColor: bgColors,
                        borderColor: '#FFFFFF',
                        borderWidth: 2,
                        hoverOffset: hasData ? 4 : 0,
                        metaSegments: hasData ? metaSegments : null,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: false,
                            external: hasData ? (context) => this.renderExternalDonutTooltip(context) : null,
                        }
                    }
                }
            });

            ctx.onmouseleave = () => {
                const el = document.getElementById('fdr-donut-floating-tooltip');
                if (el) {
                    el.style.opacity = '0';
                    el.style.transform = 'scale(0.96)';
                }
            };
        },

        renderExternalDonutTooltip(context) {
            let tooltipEl = document.getElementById('fdr-donut-floating-tooltip');
            if (!tooltipEl) {
                tooltipEl = document.createElement('div');
                tooltipEl.id = 'fdr-donut-floating-tooltip';
                tooltipEl.style.position = 'fixed';
                tooltipEl.style.zIndex = '9999';
                tooltipEl.style.pointerEvents = 'none';
                tooltipEl.style.backgroundColor = '#0F172A';
                tooltipEl.style.color = '#FFFFFF';
                tooltipEl.style.padding = '8px 12px';
                tooltipEl.style.borderRadius = '8px';
                tooltipEl.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.3)';
                tooltipEl.style.border = '1px solid rgba(255, 255, 255, 0.12)';
                tooltipEl.style.fontSize = '11px';
                tooltipEl.style.lineHeight = '1.35';
                tooltipEl.style.opacity = '0';
                tooltipEl.style.transition = 'opacity 130ms ease-out, transform 130ms ease-out';
                tooltipEl.style.transform = 'scale(0.96)';
                document.body.appendChild(tooltipEl);
            }

            const tooltipModel = context.tooltip;
            if (tooltipModel.opacity === 0 || !tooltipModel.dataPoints || tooltipModel.dataPoints.length === 0) {
                tooltipEl.style.opacity = '0';
                tooltipEl.style.transform = 'scale(0.96)';
                return;
            }

            const dp = tooltipModel.dataPoints[0];
            const segmentLabel = dp.label;
            const metaInfo = dp.dataset.metaSegments ? dp.dataset.metaSegments[dp.dataIndex] : null;

            let valueText = '';
            let pctText = '';
            if (metaInfo) {
                valueText = metaInfo.formattedValue;
                pctText = metaInfo.percentage;
            } else {
                valueText = (dp.parsed || 0).toLocaleString();
            }

            tooltipEl.innerHTML = `
                <div style="font-weight: 700; font-size: 11px; color: #FFFFFF; line-height: 1.2;">${segmentLabel}</div>
                <div style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; font-weight: 800; color: #F8FAFC; margin-top: 2px;">${valueText}</div>
                <div style="font-size: 10px; font-weight: 600; color: #94A3B8; margin-top: 2px;">${pctText}</div>
            `;

            const canvasRect = context.chart.canvas.getBoundingClientRect();
            const cursorX = canvasRect.left + tooltipModel.caretX;
            const cursorY = canvasRect.top + tooltipModel.caretY;

            const tipW = tooltipEl.offsetWidth || 120;
            const tipH = tooltipEl.offsetHeight || 60;
            const offset = 14;

            let posX = cursorX + offset;
            let posY = cursorY - (tipH / 2);

            if (posX + tipW > window.innerWidth - 12) {
                posX = cursorX - tipW - offset;
            }
            if (posY + tipH > window.innerHeight - 12) {
                posY = window.innerHeight - tipH - 12;
            }
            if (posY < 12) posY = 12;
            if (posX < 12) posX = 12;

            tooltipEl.style.left = posX + 'px';
            tooltipEl.style.top = posY + 'px';
            tooltipEl.style.opacity = '1';
            tooltipEl.style.transform = 'scale(1)';
        },

        buildFlightMovementChart() {
            const ctx = document.getElementById('fdrFlightMovementChart');
            if (!ctx) return;
            if (_fdrCharts.flightMovement) {
                _fdrCharts.flightMovement.destroy();
                _fdrCharts.flightMovement = null;
            }

            const dist = this.hourlyDistribution || {};
            const labels = dist.labels || [
                '00:00','01:00','02:00','03:00','04:00','05:00',
                '06:00','07:00','08:00','09:00','10:00','11:00',
                '12:00','13:00','14:00','15:00','16:00','17:00',
                '18:00','19:00','20:00','21:00','22:00','23:00'
            ];
            const leg = (this.filters.leg || 'ALL').toUpperCase();
            const traffic = (this.filters.traffic || 'ALL').toUpperCase();

            // Active series filters
            const showArrDom = (leg !== 'DEP' && leg !== 'DEPARTURE') && (traffic !== 'INTL' && traffic !== 'INTERNATIONAL' && traffic !== 'INT');
            const showArrInt = (leg !== 'DEP' && leg !== 'DEPARTURE') && (traffic !== 'DOM' && traffic !== 'DOMESTIC');
            const showDepDom = (leg !== 'ARR' && leg !== 'ARRIVAL') && (traffic !== 'INTL' && traffic !== 'INTERNATIONAL' && traffic !== 'INT');
            const showDepInt = (leg !== 'ARR' && leg !== 'ARRIVAL') && (traffic !== 'DOM' && traffic !== 'DOMESTIC');

            const datasets = [];

            // Stacking configuration (Section 15, 16, 17, 18, 19):
            // In ALL mode: exactly 2 compact columns side-by-side per hour:
            //   1) ARRIVAL stack (light amber #FCD34D + dark amber #D97706)
            //   2) DEPARTURE stack (light blue #93C5FD + dark blue #1E40AF)
            // In ARR mode: only 1 ARRIVAL stacked column
            // In DEP mode: only 1 DEPARTURE stacked column
            if (showArrDom) {
                datasets.push({
                    label: 'Arrival Domestic',
                    data: dist.arr_dom || new Array(24).fill(0),
                    backgroundColor: '#FCD34D',
                    borderColor: '#F59E0B',
                    borderWidth: 1,
                    borderRadius: (showArrInt ? 0 : 3),
                    stack: 'arrival',
                    categoryPercentage: 0.72,
                    barPercentage: 0.88,
                });
            }
            if (showArrInt) {
                datasets.push({
                    label: 'Arrival International',
                    data: dist.arr_int || new Array(24).fill(0),
                    backgroundColor: '#D97706',
                    borderColor: '#B45309',
                    borderWidth: 1,
                    borderRadius: { topLeft: 3, topRight: 3 },
                    stack: 'arrival',
                    categoryPercentage: 0.72,
                    barPercentage: 0.88,
                });
            }
            if (showDepDom) {
                datasets.push({
                    label: 'Departure Domestic',
                    data: dist.dep_dom || new Array(24).fill(0),
                    backgroundColor: '#93C5FD',
                    borderColor: '#3B82F6',
                    borderWidth: 1,
                    borderRadius: (showDepInt ? 0 : 3),
                    stack: 'departure',
                    categoryPercentage: 0.72,
                    barPercentage: 0.88,
                });
            }
            if (showDepInt) {
                datasets.push({
                    label: 'Departure International',
                    data: dist.dep_int || new Array(24).fill(0),
                    backgroundColor: '#1E40AF',
                    borderColor: '#172554',
                    borderWidth: 1,
                    borderRadius: { topLeft: 3, topRight: 3 },
                    stack: 'departure',
                    categoryPercentage: 0.72,
                    barPercentage: 0.88,
                });
            }

            _fdrCharts.flightMovement = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0F172A',
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 11 },
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                title: (items) => {
                                    const idx = items[0].dataIndex;
                                    const dateLabel = (this.dateScope === 'DAY' && this.filters.analysis_date)
                                        ? this.formatDateLabel(this.filters.analysis_date)
                                        : (dist.period_label || '01 Sep 2026 – 30 Sep 2026');
                                    const hStr = String(idx).padStart(2, '0');
                                    return `${dateLabel}\n${hStr}:00–${hStr}:59`;
                                },
                                label: (item) => {
                                    const isArr = item.dataset.stack === 'arrival';
                                    const dir = isArr ? 'Arrival' : 'Departure';
                                    const isDom = item.dataset.label.includes('Domestic');
                                    const traf = isDom ? 'Domestic' : 'International';
                                    const basis = ((dist.time_basis || this.filters.time_basis || 'actual').toUpperCase() === 'ACTUAL') ? 'Actual' : 'Scheduled';
                                    const count = item.parsed.y || 0;
                                    return [
                                        `Direction: ${dir}`,
                                        `Traffic Type: ${traf}`,
                                        `Time Basis: ${basis}`,
                                        `Movement Count: ${count.toLocaleString()} ${count === 1 ? 'Flight' : 'Flights'}`
                                    ];
                                },
                                afterBody: (items) => {
                                    const total = items.reduce((acc, it) => acc + (it.parsed.y || 0), 0);
                                    return `Total: ${total.toLocaleString()} Movements in this hour`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: false,
                            grid: { display: false },
                            ticks: { font: { size: 10, weight: '600' }, color: '#64748B' }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            title: { display: true, text: 'Flight Movements', font: { size: 10, weight: 'bold' }, color: '#64748B' },
                            ticks: { precision: 0, font: { size: 10 }, color: '#64748B' },
                            grid: { color: '#F1F5F9' }
                        }
                    }
                }
            });
        },

        buildPaxTrendChart() {
            const ctx = document.getElementById('fdrPaxTrendChart');
            if (!ctx) return;
            if (_fdrCharts.paxTrend) {
                _fdrCharts.paxTrend.destroy();
                _fdrCharts.paxTrend = null;
            }

            const trend = this.combinedTrend || {};
            const labels = trend.labels || [];
            const leg = (this.filters.leg || 'ALL').toUpperCase();

            const datasets = [];

            if (leg !== 'DEP' && leg !== 'DEPARTURE') {
                datasets.push({
                    label: 'Arrival Pax',
                    data: trend.arr_passengers || [],
                    borderColor: '#F59E0B',
                    backgroundColor: 'rgba(245, 158, 11, 0.12)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#FFFFFF',
                    pointBorderColor: '#F59E0B',
                    pointBorderWidth: 2,
                    fill: true,
                    tension: 0.35,
                });
            }

            if (leg !== 'ARR' && leg !== 'ARRIVAL') {
                datasets.push({
                    label: 'Departure Pax',
                    data: trend.dep_passengers || [],
                    borderColor: '#2563EB',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#FFFFFF',
                    pointBorderColor: '#2563EB',
                    pointBorderWidth: 2,
                    fill: true,
                    tension: 0.35,
                });
            }

            _fdrCharts.paxTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0F172A',
                            titleColor: '#94A3B8',
                            bodyColor: '#FFFFFF',
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 10 },
                            padding: 10,
                            cornerRadius: 6,
                            callbacks: {
                                title: (items) => {
                                    const idx = items[0].dataIndex;
                                    return trend.time_ranges ? trend.time_ranges[idx] : items[0].label;
                                },
                                label: (item) => ` ${item.dataset.label}: ${item.parsed.y.toLocaleString()} Pax`,
                                afterBody: (items) => {
                                    const idx = items[0].dataIndex;
                                    const lines = [];
                                    if (leg === 'ALL') {
                                        const arrDom = (trend.arr_dom_passengers && trend.arr_dom_passengers[idx]) || 0;
                                        const arrInt = (trend.arr_int_passengers && trend.arr_int_passengers[idx]) || 0;
                                        const depDom = (trend.dep_dom_passengers && trend.dep_dom_passengers[idx]) || 0;
                                        const depInt = (trend.dep_int_passengers && trend.dep_int_passengers[idx]) || 0;
                                        const arrTot = (trend.arr_passengers && trend.arr_passengers[idx]) || 0;
                                        const depTot = (trend.dep_passengers && trend.dep_passengers[idx]) || 0;
                                        lines.push('');
                                        lines.push(`ARRIVAL: Dom ${arrDom.toLocaleString()} | Int ${arrInt.toLocaleString()} (Total: ${arrTot.toLocaleString()} Pax)`);
                                        lines.push(`DEPARTURE: Dom ${depDom.toLocaleString()} | Int ${depInt.toLocaleString()} (Total: ${depTot.toLocaleString()} Pax)`);
                                        lines.push(`TOTAL: ${(arrTot + depTot).toLocaleString()} Pax`);
                                    } else if (leg === 'ARR' || leg === 'ARRIVAL') {
                                        const arrDom = (trend.arr_dom_passengers && trend.arr_dom_passengers[idx]) || 0;
                                        const arrInt = (trend.arr_int_passengers && trend.arr_int_passengers[idx]) || 0;
                                        const arrTot = (trend.arr_passengers && trend.arr_passengers[idx]) || 0;
                                        lines.push('');
                                        lines.push(`Domestic: ${arrDom.toLocaleString()} Pax`);
                                        lines.push(`International: ${arrInt.toLocaleString()} Pax`);
                                        lines.push(`Total Arrival: ${arrTot.toLocaleString()} Pax`);
                                    } else {
                                        const depDom = (trend.dep_dom_passengers && trend.dep_dom_passengers[idx]) || 0;
                                        const depInt = (trend.dep_int_passengers && trend.dep_int_passengers[idx]) || 0;
                                        const depTot = (trend.dep_passengers && trend.dep_passengers[idx]) || 0;
                                        lines.push('');
                                        lines.push(`Domestic: ${depDom.toLocaleString()} Pax`);
                                        lines.push(`International: ${depInt.toLocaleString()} Pax`);
                                        lines.push(`Total Departure: ${depTot.toLocaleString()} Pax`);
                                    }
                                    return lines;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 9, weight: '600' }, color: '#64748B' }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Passengers (Pax)', font: { size: 10, weight: 'bold' }, color: '#0284C7' },
                            ticks: {
                                callback: (val) => val >= 1000 ? (val / 1000) + 'k' : val,
                                font: { size: 9 }, color: '#0284C7'
                            },
                            grid: { color: '#F1F5F9' }
                        }
                    }
                }
            });
        },

        buildCargoTrendChart() {
            const ctx = document.getElementById('fdrCargoTrendChart');
            if (!ctx) return;
            if (_fdrCharts.cargoTrend) {
                _fdrCharts.cargoTrend.destroy();
                _fdrCharts.cargoTrend = null;
            }

            const trend = this.combinedTrend || {};
            const labels = trend.labels || [];
            const leg = (this.filters.leg || 'ALL').toUpperCase();

            const datasets = [];

            if (leg !== 'DEP' && leg !== 'DEPARTURE') {
                datasets.push({
                    label: 'Arrival Cargo',
                    data: trend.arr_cargo_kg || trend.arr_cargo || [],
                    borderColor: '#14B8A6',
                    backgroundColor: 'rgba(20, 184, 166, 0.12)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#FFFFFF',
                    pointBorderColor: '#14B8A6',
                    pointBorderWidth: 2,
                    fill: true,
                    tension: 0.35,
                });
            }

            if (leg !== 'ARR' && leg !== 'ARRIVAL') {
                datasets.push({
                    label: 'Departure Cargo',
                    data: trend.dep_cargo_kg || trend.dep_cargo || [],
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.12)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#FFFFFF',
                    pointBorderColor: '#059669',
                    pointBorderWidth: 2,
                    fill: true,
                    tension: 0.35,
                });
            }

            _fdrCharts.cargoTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0F172A',
                            titleColor: '#94A3B8',
                            bodyColor: '#FFFFFF',
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 10 },
                            padding: 10,
                            cornerRadius: 6,
                            callbacks: {
                                title: (items) => {
                                    const idx = items[0].dataIndex;
                                    return trend.time_ranges ? trend.time_ranges[idx] : items[0].label;
                                },
                                label: (item) => ` ${item.dataset.label}: ${Math.round(item.parsed.y).toLocaleString()} kg`,
                                afterBody: (items) => {
                                    const idx = items[0].dataIndex;
                                    const lines = [];
                                    if (leg === 'ALL') {
                                        const arrDom = Math.round((trend.arr_dom_cargo_kg && trend.arr_dom_cargo_kg[idx]) || 0);
                                        const arrInt = Math.round((trend.arr_int_cargo_kg && trend.arr_int_cargo_kg[idx]) || 0);
                                        const depDom = Math.round((trend.dep_dom_cargo_kg && trend.dep_dom_cargo_kg[idx]) || 0);
                                        const depInt = Math.round((trend.dep_int_cargo_kg && trend.dep_int_cargo_kg[idx]) || 0);
                                        const arrTot = Math.round((trend.arr_cargo_kg && trend.arr_cargo_kg[idx]) || 0);
                                        const depTot = Math.round((trend.dep_cargo_kg && trend.dep_cargo_kg[idx]) || 0);
                                        lines.push('');
                                        lines.push(`ARRIVAL CARGO: Dom ${arrDom.toLocaleString()} kg | Int ${arrInt.toLocaleString()} kg (Total: ${arrTot.toLocaleString()} kg)`);
                                        lines.push(`DEPARTURE CARGO: Dom ${depDom.toLocaleString()} kg | Int ${depInt.toLocaleString()} kg (Total: ${depTot.toLocaleString()} kg)`);
                                        lines.push(`TOTAL CARGO: ${(arrTot + depTot).toLocaleString()} kg`);
                                    } else if (leg === 'ARR' || leg === 'ARRIVAL') {
                                        const arrDom = Math.round((trend.arr_dom_cargo_kg && trend.arr_dom_cargo_kg[idx]) || 0);
                                        const arrInt = Math.round((trend.arr_int_cargo_kg && trend.arr_int_cargo_kg[idx]) || 0);
                                        const arrTot = Math.round((trend.arr_cargo_kg && trend.arr_cargo_kg[idx]) || 0);
                                        lines.push('');
                                        lines.push(`Domestic: ${arrDom.toLocaleString()} kg`);
                                        lines.push(`International: ${arrInt.toLocaleString()} kg`);
                                        lines.push(`Total: ${arrTot.toLocaleString()} kg`);
                                    } else {
                                        const depDom = Math.round((trend.dep_dom_cargo_kg && trend.dep_dom_cargo_kg[idx]) || 0);
                                        const depInt = Math.round((trend.dep_int_cargo_kg && trend.dep_int_cargo_kg[idx]) || 0);
                                        const depTot = Math.round((trend.dep_cargo_kg && trend.dep_cargo_kg[idx]) || 0);
                                        lines.push('');
                                        lines.push(`Domestic: ${depDom.toLocaleString()} kg`);
                                        lines.push(`International: ${depInt.toLocaleString()} kg`);
                                        lines.push(`Total: ${depTot.toLocaleString()} kg`);
                                    }
                                    return lines;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 9, weight: '600' }, color: '#64748B' }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Cargo (kg)', font: { size: 10, weight: 'bold' }, color: '#059669' },
                            ticks: {
                                callback: (val) => val >= 1000 ? (val / 1000).toLocaleString() + 'k kg' : val.toLocaleString() + ' kg',
                                font: { size: 9 }, color: '#059669'
                            },
                            grid: { color: '#F1F5F9' }
                        }
                    }
                }
            });
        },

        getPaxPct(type) {
            const comp = this.paxAnalytics.composition || {};
            const adult = comp.adult || 0;
            const child = comp.child || 0;
            const infant = comp.infant || 0;
            const total = adult + child + infant;
            if (total <= 0) return '0.0%';
            const val = comp[type] || 0;
            return ((val / total) * 100).toFixed(1) + '%';
        },

        getPayloadPct(type) {
            const cargo = Math.round(this.kpis.total_cargo_kg || 0);
            const baggage = Math.round(this.kpis.total_baggage_kg || 0);
            const pos = Math.round(this.kpis.pos_kg || 0);
            const total = cargo + baggage + pos;
            if (total <= 0) return '0.0%';
            let val = 0;
            if (type === 'cargo') val = cargo;
            else if (type === 'baggage') val = baggage;
            else if (type === 'pos') val = pos;
            return ((val / total) * 100).toFixed(1) + '%';
        },

        formatPayloadWeightKg(val) {
            val = Math.round(val || 0);
            return val.toLocaleString() + ' kg';
        },

        setLegFilter(val) {
            if (this.filters.leg === val) return;
            this.filters.leg = val;
            this.triggerFilter(1);
        },

        setTrafficFilter(val) {
            if (this.filters.traffic === val) return;
            this.filters.traffic = val;
            this.triggerFilter(1);
        },

        setTimeBasis(val) {
            if (this.filters.time_basis === val) return;
            this.filters.time_basis = val;
            this.triggerFilter(1);
        },

        getActiveViewLabel() {
            let legLbl = 'ALL';
            if (this.filters.leg === 'ARR' || this.filters.leg === 'ARRIVAL') legLbl = 'ARRIVAL';
            else if (this.filters.leg === 'DEP' || this.filters.leg === 'DEPARTURE') legLbl = 'DEPARTURE';

            let trafLbl = 'ALL';
            if (this.filters.traffic === 'DOM' || this.filters.traffic === 'DOMESTIC') trafLbl = 'DOMESTIC';
            else if (this.filters.traffic === 'INTL' || this.filters.traffic === 'INTERNATIONAL' || this.filters.traffic === 'INT') trafLbl = 'INTERNATIONAL';

            return `Movement: ${legLbl} • Traffic: ${trafLbl}`;
        },

        formatPayloadWeight(val) {
            return this.formatPayloadWeightKg(val);
        },

        setFullRange() {
            this.dateScope = 'ALL_PERIOD';
            this.filters.analysis_date = '';
            this.selectedDayOption = 'ALL_PERIOD';
            this.triggerFilter(1);
        },

        onDateSelectChange() {
            if (this.selectedDayOption === 'ALL_PERIOD' || !this.selectedDayOption) {
                this.setFullRange();
            } else {
                this.dateScope = 'DAY';
                this.filters.analysis_date = this.selectedDayOption;
                this.triggerFilter(1);
            }
        },

        formatDateLabel(dStr) {
            if (!dStr) return '';
            try {
                const parts = dStr.split('-');
                if (parts.length === 3) {
                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    const m = parseInt(parts[1], 10) - 1;
                    return `${parts[2]} ${months[m] || parts[1]} ${parts[0]}`;
                }
            } catch(e) {}
            return dStr;
        },

        formatDateDisplay(dStr) {
            return this.formatDateLabel(dStr);
        },

        getHourlyPeriodContext() {
            if (this.hourlyDistribution && this.hourlyDistribution.period_label) {
                const days = this.hourlyDistribution.period_days;
                return this.hourlyDistribution.period_label + (days ? ` • ${days} ${days === 1 ? 'DAY' : 'DAYS'}` : '');
            }
            if (this.dateScope === 'DAY' && this.filters.analysis_date) {
                return this.formatDateLabel(this.filters.analysis_date) + ' • 1 DAY';
            }
            return 'SELECTED PERIOD';
        },

        getHourlyPeakHourRange() {
            return (this.hourlyDistribution && this.hourlyDistribution.peak_hour && this.hourlyDistribution.peak_hour.time_range) || 'N/A';
        },

        getHourlyPeakMovements() {
            if (this.hourlyDistribution && this.hourlyDistribution.peak_hour && this.hourlyDistribution.peak_hour.movements != null) {
                return this.hourlyDistribution.peak_hour.movements.toLocaleString() + ' mov';
            }
            return '0 mov';
        },

        getHourlyPeakAverage() {
            if (this.hourlyDistribution && this.hourlyDistribution.peak_hour && this.hourlyDistribution.peak_hour.average_per_day) {
                return this.hourlyDistribution.peak_hour.average_per_day;
            }
            return null;
        },

        triggerFilter(page = 1) {
            this.isFiltering = true;
            this.filterError = null;

            // Client-side filtering when allClientRecords is available (e.g. staged upload or client parsed data)
            if (this.allClientRecords && this.allClientRecords.length > 0 && (this.activeReportType === 'NORMAL' || this.activeReportType === 'EX_HALIM')) {
                let filtered = [...this.allClientRecords];
                const leg = (this.filters.leg || 'ALL').toUpperCase();
                if (leg === 'ARR' || leg === 'ARRIVAL') {
                    filtered = filtered.filter(r => (r.leg || r.direction || r.movementType || '').toUpperCase().startsWith('A'));
                } else if (leg === 'DEP' || leg === 'DEPARTURE') {
                    filtered = filtered.filter(r => (r.leg || r.direction || r.movementType || '').toUpperCase().startsWith('D'));
                }

                const op = (this.filters.operator || 'ALL').toUpperCase();
                if (op !== 'ALL' && op !== '') {
                    filtered = filtered.filter(r => (r.airline || r.airlineCode || r.operator || '').toUpperCase() === op || (r.flight_no || r.flightNumber || '').toUpperCase().startsWith(op));
                }

                const trf = (this.filters.traffic || 'ALL').toUpperCase();
                if (trf === 'DOM' || trf === 'DOMESTIC') {
                    filtered = filtered.filter(r => (r.traffic || r.dom_int || '').toUpperCase().includes('DOM') || (!r.traffic && !r.dom_int));
                } else if (trf === 'INTL' || trf === 'INTERNATIONAL' || trf === 'INT') {
                    filtered = filtered.filter(r => (r.traffic || r.dom_int || '').toUpperCase().includes('INT'));
                }

                const search = (this.filters.search || '').trim().toLowerCase();
                if (search) {
                    filtered = filtered.filter(r => 
                        (r.flight_no || r.flightNumber || '').toLowerCase().includes(search) ||
                        (r.paired_no || r.pairedNo || '').toLowerCase().includes(search) ||
                        (r.route || '').toLowerCase().includes(search) ||
                        (r.reg_no || r.aircraftReg || '').toLowerCase().includes(search) ||
                        (r.desc || r.aircraftType || '').toLowerCase().includes(search)
                    );
                }

                this.totalRecords = filtered.length;
                this.pagination.total = filtered.length;
                this.pagination.total_pages = Math.max(1, Math.ceil(filtered.length / this.pagination.per_page));
                this.pagination.current_page = Math.min(page, this.pagination.total_pages || 1);
                const start = (this.pagination.current_page - 1) * this.pagination.per_page;
                this.flightRecords = filtered.slice(start, start + this.pagination.per_page);
                this.isFiltering = false;
                this.lastUpdatedTime = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                this.computeClientHourlyAndTrends(filtered);
                this.$nextTick(() => {
                    this.initAllCharts();
                });
                return;
            }

            const reqId = ++this.activeRequestId;
            this.filters.v = Date.now();

            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();

            // Ensure airport is locked if empty or ALL
            const activeAirport = (this.filters.airport && this.filters.airport !== 'ALL') ? this.filters.airport : this.lockedAirport;
            this.filters.airport = activeAirport;

            const params = new URLSearchParams({
                date_scope: this.dateScope,
                airport: activeAirport,
                leg: this.filters.leg || 'ALL',
                operator: this.filters.operator || 'ALL',
                traffic: this.filters.traffic || 'ALL',
                realization: this.filters.realization || 'ALL',
                analysis_date: this.dateScope === 'ALL_PERIOD' ? '' : (this.filters.analysis_date || ''),
                search: (this.filters.search || '').trim(),
                page: page,
                per_page: this.pagination.per_page,
                time_basis: this.filters.time_basis,
                v: this.filters.v,
            });

            fetch(`/fdr/${this.uploadId}/filter?` + params.toString(), {
                signal: this.abortController.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status + ': Gagal memuat data');
                }
                return res.json();
            })
            .then(data => {
                if (reqId !== this.activeRequestId) return; // Discard stale response
                this.isFiltering = false;
                this.filterError = null;
                this.dateScope = data.date_scope || this.dateScope;
                if (this.dateScope === 'ALL_PERIOD') {
                    this.selectedDayOption = 'ALL_PERIOD';
                    this.filters.analysis_date = '';
                } else if (data.analysis_date) {
                    this.selectedDayOption = data.analysis_date;
                    this.filters.analysis_date = data.analysis_date;
                }
                this.kpis = data.kpis;
                this.schedVsReal = data.sched_vs_real;
                this.paxAnalytics = data.pax_analytics;
                this.airlineRoute = data.airline_route;
                this.fleetPerformance = data.fleet_performance || [];
                this.groundOps = data.ground_ops;
                this.combinedTrend = data.combined_trend;
                this.hourlyDistribution = data.hourly_distribution || this.hourlyDistribution;
                this.activeChips = data.active_chips || [];
                this.flightRecords = data.records || [];
                this.totalRecords = data.filtered_count ?? (data.pagination ? data.pagination.total : 0);
                this.pagination.current_page = data.pagination ? data.pagination.current_page : page;
                this.pagination.total_pages = data.pagination ? data.pagination.total_pages : 1;
                this.lastUpdatedTime = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

                this.$nextTick(() => {
                    this.initAllCharts();
                    this.renderNetworkMap();
                    this.logReconciliation('FILTER_RESULT');
                });
            })
            .catch(err => {
                if (err.name === 'AbortError') {
                    return; // Normal cancellation of superseded request
                }
                if (reqId !== this.activeRequestId) return;
                this.isFiltering = false;
                this.filterError = 'FDR data gagal dimuat: ' + (err.message || 'Terjadi kesalahan sistem');
                console.error("FDR filter error:", err);
            });
        },

        changePage(p) {
            if (p < 1 || p > this.pagination.total_pages) return;
            this.triggerFilter(p);
        },

        changePerPage() {
            this.pagination.current_page = 1;
            this.triggerFilter(1);
        },

        computeClientHourlyAndTrends(filteredRecords) {
            if (!filteredRecords || !Array.isArray(filteredRecords)) return;
            const hourlyLabels = [
                '00:00', '01:00', '02:00', '03:00', '04:00', '05:00',
                '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
                '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
                '18:00', '19:00', '20:00', '21:00', '22:00', '23:00',
            ];
            const hourlyRanges = hourlyLabels.map((l, i) => `${l} - ${String(i).padStart(2, '0')}:59`);
            const arrDom = new Array(24).fill(0);
            const arrInt = new Array(24).fill(0);
            const depDom = new Array(24).fill(0);
            const depInt = new Array(24).fill(0);

            const arrPax = new Array(24).fill(0);
            const depPax = new Array(24).fill(0);
            const arrDomPax = new Array(24).fill(0);
            const arrIntPax = new Array(24).fill(0);
            const depDomPax = new Array(24).fill(0);
            const depIntPax = new Array(24).fill(0);

            const arrCargo = new Array(24).fill(0);
            const depCargo = new Array(24).fill(0);
            const arrDomCargo = new Array(24).fill(0);
            const arrIntCargo = new Array(24).fill(0);
            const depDomCargo = new Array(24).fill(0);
            const depIntCargo = new Array(24).fill(0);

            const INT_SET = new Set([
                'SIN', 'KUL', 'HND', 'NRT', 'ICN', 'PVG', 'CAN', 'SYD', 'MEL', 'PER',
                'JED', 'MED', 'DOH', 'BKK', 'HKG', 'DXB', 'AUH', 'KTM', 'DMK', 'MNL',
                'TPE', 'PEN', 'JHB', 'BKI', 'KCH', 'LGK', 'CNX', 'HKT', 'REP', 'PNH',
                'SGN', 'HAN', 'BNE', 'ADL', 'AKL', 'LHR', 'AMS', 'FRA', 'CDG', 'IST',
            ]);

            const useSched = (this.filters.time_basis || '').toUpperCase() === 'SCHEDULED';

            filteredRecords.forEach(r => {
                let h = 0;
                if (useSched) {
                    h = (r.scheduledHour != null && r.scheduledHour >= 0 && r.scheduledHour < 24)
                        ? r.scheduledHour
                        : (r.actualHour != null && r.actualHour >= 0 && r.actualHour < 24 ? r.actualHour : 0);
                } else {
                    h = (r.actualHour != null && r.actualHour >= 0 && r.actualHour < 24)
                        ? r.actualHour
                        : (r.scheduledHour != null && r.scheduledHour >= 0 && r.scheduledHour < 24 ? r.scheduledHour : 0);
                }

                const legVal = (r.leg || r.direction || r.movementType || '').toUpperCase();
                const isArr = legVal.startsWith('A') || legVal.includes('ARR');
                const orig = (r.origin || r.city_1 || '').toUpperCase();
                const dest = (r.destination || r.city_2 || '').toUpperCase();
                const isInt = INT_SET.has(orig) || INT_SET.has(dest) || (r.traffic && r.traffic.toUpperCase().includes('INT'));

                const pax = (r.paxLoad != null ? r.paxLoad : ((r.adult || 0) + (r.child || 0) + (r.infant || 0)));
                const cargo = (r.cargoKg != null ? r.cargoKg : (r.cargo_kg || 0));

                if (isArr) {
                    if (isInt) {
                        arrInt[h]++;
                        arrIntPax[h] += pax;
                        arrIntCargo[h] += cargo;
                    } else {
                        arrDom[h]++;
                        arrDomPax[h] += pax;
                        arrDomCargo[h] += cargo;
                    }
                    arrPax[h] += pax;
                    arrCargo[h] += cargo;
                } else {
                    if (isInt) {
                        depInt[h]++;
                        depIntPax[h] += pax;
                        depIntCargo[h] += cargo;
                    } else {
                        depDom[h]++;
                        depDomPax[h] += pax;
                        depDomCargo[h] += cargo;
                    }
                    depPax[h] += pax;
                    depCargo[h] += cargo;
                }
            });

            this.hourlyDistribution = {
                labels: hourlyLabels,
                arr_dom: arrDom,
                arr_int: arrInt,
                dep_dom: depDom,
                dep_int: depInt,
                time_basis: useSched ? 'scheduled' : 'actual',
            };

            this.combinedTrend = {
                labels: hourlyLabels,
                time_ranges: hourlyRanges,
                arr_passengers: arrPax,
                dep_passengers: depPax,
                arr_dom_passengers: arrDomPax,
                arr_int_passengers: arrIntPax,
                dep_dom_passengers: depDomPax,
                dep_int_passengers: depIntPax,
                arr_cargo: arrCargo,
                dep_cargo: depCargo,
                arr_cargo_kg: arrCargo,
                dep_cargo_kg: depCargo,
                arr_dom_cargo_kg: arrDomCargo,
                arr_int_cargo_kg: arrIntCargo,
                dep_dom_cargo_kg: depDomCargo,
                dep_int_cargo_kg: depIntCargo,
            };
        },

        removeChip(key) {
            if (key === 'airport') this.filters.airport = this.lockedAirport;
            if (key === 'leg') this.filters.leg = 'ALL';
            if (key === 'operator') this.filters.operator = 'ALL';
            if (key === 'traffic') this.filters.traffic = 'ALL';
            if (key === 'realization') this.filters.realization = 'ALL';
            if (key === 'search') this.filters.search = '';
            if (key === 'flight_no') this.filters.search = '';
            if (key === 'date_scope' || key === 'analysis_date') {
                this.setFullRange();
                return;
            }
            this.triggerFilter(1);
        },

        clearAllFilters() {
            this.filters.airport = this.lockedAirport;
            this.filters.leg = 'ALL';
            this.filters.operator = 'ALL';
            this.filters.traffic = 'ALL';
            this.filters.realization = 'ALL';
            this.filters.search = '';
            this.setFullRange();
        },

        openFlightDetails(r) {
            this.selectedFlight = r;
        },

        getVisiblePageNumbers() {
            const current = this.pagination.current_page;
            const total = this.pagination.total_pages;
            const pages = [];
            const maxVisible = 5;

            let start = Math.max(1, current - 2);
            let end = Math.min(total, start + maxVisible - 1);
            if (end - start < maxVisible - 1) {
                start = Math.max(1, end - maxVisible + 1);
            }

            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        },

        // ── Stage A: Pre-Scan & Multi-File Staging Methods ────────────────────────
        async handleFileInput(event) {
            const files = event.target.files;
            if (!files || files.length === 0) return;
            await this.processUploadedFileList(Array.from(files));
        },

        async handleFileDrop(event) {
            const files = event.dataTransfer ? event.dataTransfer.files : null;
            if (!files || files.length === 0) return;
            await this.processUploadedFileList(Array.from(files));
        },

        async processUploadedFileList(fileList) {
            this.isScanning = true;
            this.scanError = null;
            try {
                if (!window.fdrEngine) {
                    throw new Error('FDR Processing Engine is not ready. Please refresh the page.');
                }
                const result = await window.fdrEngine.processFdrFiles(fileList);
                this.preScanData = result.preScan;
                this.stagedFiles = result.files;
                this.mergedHtml = result.mergedHtml;
                this.clientParsedData = result.parsedData;
                this.activeReportType = result.preScan.reportType;
                this.availableReportTypes = result.availableReportTypes || [result.preScan.reportType];
                this.allParsedByType = result.allParsedByType || { [result.preScan.reportType]: result.parsedData };

                const hasHalim = fileList.some(f => /halim|hlp/i.test(f.name));
                if (hasHalim && result.preScan.reportType === 'NORMAL') {
                    this.isExHalim = true;
                } else if (result.preScan.reportType === 'EX_HALIM') {
                    this.isExHalim = true;
                }
            } catch (err) {
                console.error('Pre-scan error:', err);
                this.scanError = 'Gagal memproses file: ' + (err.message || 'Format file tidak didukung');
            } finally {
                this.isScanning = false;
            }
        },

        resetStagedFiles() {
            this.stagedFiles = [];
            this.preScanData = null;
            this.mergedHtml = '';
            this.clientParsedData = null;
            this.allClientRecords = [];
            this.availableReportTypes = ['{{ $reportType ?? 'NORMAL' }}'];
            this.allParsedByType = {};
            this.scanError = null;
        },

        hydrateClientDataForType(rtype) {
            const data = (this.allParsedByType && this.allParsedByType[rtype]) || this.clientParsedData;
            if (!data) return;

            if (rtype === 'NORMAL' || rtype === 'EX_HALIM') {
                this.allClientRecords = data.records || [];
                this.totalRecords = this.allClientRecords.length;
                this.pagination.total = this.allClientRecords.length;
                this.pagination.total_pages = Math.max(1, Math.ceil(this.pagination.total / this.pagination.per_page));
                this.pagination.current_page = 1;
                this.flightRecords = this.allClientRecords.slice(0, this.pagination.per_page);

                if (data.kpis) {
                    this.kpis = Object.assign({}, this.kpis, data.kpis);
                }
                if (data.hourlyDistribution) {
                    this.hourlyDistribution = data.hourlyDistribution;
                }
                if (data.combinedTrend) {
                    this.combinedTrend = data.combinedTrend;
                }
                if (data.airlineRoute || data.airline_route || data.top_busiest_routes) {
                    const ar = data.airlineRoute || data.airline_route || {};
                    this.airlineRoute = {
                        top_busiest_routes: data.top_busiest_routes || ar.top_busiest_routes || ar.top_routes || [],
                        top_delayed_routes: data.top_delayed_routes || ar.top_delayed_routes || ar.most_delayed_routes || [],
                        top_slf_routes: data.top_slf_routes || ar.top_slf_routes || ar.highest_slf_routes || [],
                        leg_subtypes: data.leg_subtypes || ar.leg_subtypes || { SCHED: 0, UNSCHED: 0, CARGO: 0 },
                        suffix_distribution: data.suffix_distribution || ar.suffix_distribution || ar.suffix_breakdown || [],
                    };
                }
                if (data.summary) {
                    this.sourceSummary = Object.assign({}, this.sourceSummary, {
                        total_rows: data.summary.totalRows || this.totalRecords,
                        period_label: data.summary.period_label || this.sourceSummary.period_label,
                        days_available: data.summary.days_available || this.sourceSummary.days_available,
                        source_type: data.summary.source_type || this.sourceSummary.source_type,
                    });
                }
            } else if (rtype === 'LOAD_FACTOR') {
                this.allClientRecords = data.records || [];
                this.totalRecords = this.allClientRecords.length;
                this.pagination.total = this.allClientRecords.length;
                this.pagination.total_pages = Math.max(1, Math.ceil(this.pagination.total / this.pagination.per_page));
                this.pagination.current_page = 1;
            } else if (rtype === 'COMPARE_LOAD_FACTOR' || rtype === 'COMPARE_LOAD_FACTOR_DAY') {
                this.allClientRecords = data.records || [];
                this.totalRecords = this.allClientRecords.length;
            } else if (rtype === 'OASYS_VS_APPS' || rtype === 'DEPARTURE_VS_APPS' || rtype === 'OASYS_VS_EDIFLY' || rtype === 'OASYS_EDIFLY') {
                this.allClientRecords = data.records || [];
                this.totalRecords = this.allClientRecords.length;
            }
        },

        generateDashboardFromStaged() {
            if (!this.preScanData) return;
            const targetType = this.isExHalim ? 'EX_HALIM' : this.preScanData.reportType;
            this.activeReportType = targetType;
            this.currentStage = 'dashboard';
            this.showStagingCard = false;

            this.hydrateClientDataForType(targetType);

            this.$nextTick(() => {
                this.initAllCharts();
            });
        },

        switchReportType(rtype) {
            if (this.activeReportType === rtype) return;
            this.activeReportType = rtype;
            this.isExHalim = (rtype === 'EX_HALIM');
            if (this.allParsedByType && this.allParsedByType[rtype]) {
                this.clientParsedData = this.allParsedByType[rtype];
            }
            this.hydrateClientDataForType(rtype);

            // Clear existing polymorphic charts
            Object.keys(_fdrCharts).forEach(k => {
                if (_fdrCharts[k]) {
                    try { _fdrCharts[k].destroy(); } catch (e) {}
                    _fdrCharts[k] = null;
                }
            });
            this.$nextTick(() => {
                this.initAllCharts();
            });
        },

        formatReportTypeName(rtype) {
            const map = {
                'NORMAL': 'Normal Operations',
                'EX_HALIM': 'Ex Halim (CGK)',
                'LOAD_FACTOR': 'Load Factor',
                'COMPARE_LOAD_FACTOR': 'Compare LF (YoY)',
                'COMPARE_LOAD_FACTOR_DAY': 'Compare LF (Daily)',
                'DEPARTURE_VS_APPS': 'Departure vs APPS',
                'OASYS_VS_APPS': 'Departure vs APPS',
                'OASYS_VS_EDIFLY': 'OASYS vs Edifly',
                'OASYS_EDIFLY': 'OASYS vs Edifly',
            };
            return map[rtype] || rtype;
        },

        getSwitcherActiveClass(rtype) {
            if (this.activeReportType === rtype) {
                return 'bg-slate-900 text-white border-slate-900 shadow-sm';
            }
            return 'bg-white text-slate-700 hover:bg-slate-50 border-slate-200 hover:border-slate-300';
        },

        getSwitcherDotClass(rtype) {
            if (this.activeReportType === rtype) {
                return 'bg-emerald-400 animate-pulse';
            }
            switch (rtype) {
                case 'NORMAL': return 'bg-blue-400';
                case 'EX_HALIM': return 'bg-amber-400';
                case 'LOAD_FACTOR': return 'bg-emerald-400';
                case 'COMPARE_LOAD_FACTOR': return 'bg-indigo-400';
                case 'COMPARE_LOAD_FACTOR_DAY': return 'bg-purple-400';
                case 'OASYS_VS_APPS': return 'bg-teal-400';
                case 'OASYS_VS_EDIFLY': return 'bg-sky-400';
                default: return 'bg-slate-400';
            }
        },

        // ── Template Badges & Helpers ─────────────────────────────────────────────
        getReportTypeBadgeClass() {
            const t = this.isExHalim ? 'EX_HALIM' : (this.activeReportType || 'NORMAL');
            switch (t) {
                case 'EX_HALIM': return 'bg-amber-100 text-amber-800 border border-amber-300';
                case 'LOAD_FACTOR': return 'bg-emerald-100 text-emerald-800 border border-emerald-300';
                case 'COMPARE_LOAD_FACTOR': return 'bg-indigo-100 text-indigo-800 border border-indigo-300';
                case 'COMPARE_LOAD_FACTOR_DAY': return 'bg-purple-100 text-purple-800 border border-purple-300';
                case 'DEPARTURE_VS_APPS':
                case 'OASYS_VS_APPS': return 'bg-teal-100 text-teal-800 border border-teal-300';
                case 'OASYS_EDIFLY':
                case 'OASYS_VS_EDIFLY': return 'bg-sky-100 text-sky-800 border border-sky-300';
                default: return 'bg-blue-100 text-blue-800 border border-blue-300';
            }
        },

        getReportTypeBadgeLabel() {
            const t = this.isExHalim ? 'EX_HALIM' : (this.activeReportType || 'NORMAL');
            return 'TEMPLATE: ' + t.replace(/_/g, ' ');
        },

        getTemplateBadgeClass(type) {
            switch (type) {
                case 'EX_HALIM': return 'bg-amber-500 text-white';
                case 'LOAD_FACTOR': return 'bg-emerald-600 text-white';
                case 'COMPARE_LOAD_FACTOR': return 'bg-indigo-600 text-white';
                case 'COMPARE_LOAD_FACTOR_DAY': return 'bg-purple-600 text-white';
                case 'DEPARTURE_VS_APPS':
                case 'OASYS_VS_APPS': return 'bg-teal-600 text-white';
                case 'OASYS_EDIFLY':
                case 'OASYS_VS_EDIFLY': return 'bg-sky-600 text-white';
                default: return 'bg-blue-600 text-white';
            }
        },

        getTemplateBadgeText(type) {
            return (type || 'NORMAL').replace(/_/g, ' ');
        },

        resolveAirportName(code) {
            if (window.fdrEngine && window.fdrEngine.INJOURNEY_AIRPORTS && window.fdrEngine.INJOURNEY_AIRPORTS[code]) {
                const a = window.fdrEngine.INJOURNEY_AIRPORTS[code];
                return `${a.name} (${a.city})`;
            }
            return code ? `${code} Airport` : 'Bandara';
        },

        formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        // ── Load Factor (13 Columns) Getters & Chart Builders ────────────────────
        getLfRecords() {
            if (this.clientParsedData && this.clientParsedData.records) {
                return this.clientParsedData.records;
            }
            return this.flightRecords || [];
        },

        getPaginatedLfRecords() {
            const recs = this.getLfRecords();
            const start = (this.pagination.current_page - 1) * this.pagination.per_page;
            return recs.slice(start, start + this.pagination.per_page);
        },

        getLfTotalFlights() {
            return this.getLfRecords().length;
        },

        getLfArrivals() {
            return this.getLfRecords().filter(r => (r.leg === 'A' || r.leg === 'ARRIVAL')).length;
        },

        getLfDepartures() {
            return this.getLfRecords().filter(r => (r.leg === 'D' || r.leg === 'DEPARTURE')).length;
        },

        getLfTotalSeatCap() {
            if (this.clientParsedData && this.clientParsedData.metadata && this.clientParsedData.metadata.totalSeatCapacity) {
                return this.clientParsedData.metadata.totalSeatCapacity;
            }
            return this.getLfRecords().reduce((acc, r) => acc + (Number(r.seatCapacity || r.cap) || 0), 0);
        },

        getLfTotalPax() {
            if (this.clientParsedData && this.clientParsedData.metadata && this.clientParsedData.metadata.totalPax) {
                return this.clientParsedData.metadata.totalPax;
            }
            return this.getLfRecords().reduce((acc, r) => acc + (Number(r.pax || r.load) || 0), 0);
        },

        getLfAvgSlf() {
            const cap = this.getLfTotalSeatCap();
            const pax = this.getLfTotalPax();
            if (cap === 0) return '0.0';
            return ((pax / cap) * 100).toFixed(1);
        },

        getLfSimpleAvgSlf() {
            const recs = this.getLfRecords();
            if (!recs.length) return '0.0';
            const sum = recs.reduce((acc, r) => acc + (parseFloat(r.slfPercent || r.slf || r.load_factor || 0) || 0), 0);
            return (sum / recs.length).toFixed(1);
        },

        getLfEmptySeats() {
            const cap = this.getLfTotalSeatCap();
            const pax = this.getLfTotalPax();
            return Math.max(0, cap - pax);
        },

        getLfEmptySeatsRatio() {
            const cap = this.getLfTotalSeatCap();
            const empty = this.getLfEmptySeats();
            if (cap === 0) return '0.0';
            return ((empty / cap) * 100).toFixed(1);
        },

        getLfTopRoute() {
            const recs = this.getLfRecords();
            if (!recs.length) return { route: 'N/A', slf: '0%' };
            const routeMap = {};
            recs.forEach(r => {
                const rt = (r.arrivalRoute && r.departureRoute) ? `${r.arrivalRoute} → ${r.departureRoute}` : (r.route || 'ROUTE');
                if (!routeMap[rt]) routeMap[rt] = { cap: 0, pax: 0 };
                routeMap[rt].cap += Number(r.seatCapacity || r.cap) || 0;
                routeMap[rt].pax += Number(r.pax || r.load) || 0;
            });
            let top = null, maxSlf = -1;
            Object.entries(routeMap).forEach(([rt, d]) => {
                if (d.cap > 0) {
                    const slf = (d.pax / d.cap) * 100;
                    if (slf > maxSlf) {
                        maxSlf = slf;
                        top = { route: rt, slf: slf.toFixed(1) + '%' };
                    }
                }
            });
            return top || { route: 'N/A', slf: '0%' };
        },

        getLfLowRoute() {
            const recs = this.getLfRecords();
            if (!recs.length) return { route: 'N/A', slf: '0%' };
            const routeMap = {};
            recs.forEach(r => {
                const rt = (r.arrivalRoute && r.departureRoute) ? `${r.arrivalRoute} → ${r.departureRoute}` : (r.route || 'ROUTE');
                if (!routeMap[rt]) routeMap[rt] = { cap: 0, pax: 0 };
                routeMap[rt].cap += Number(r.seatCapacity || r.cap) || 0;
                routeMap[rt].pax += Number(r.pax || r.load) || 0;
            });
            let low = null, minSlf = 999999;
            Object.entries(routeMap).forEach(([rt, d]) => {
                if (d.cap > 0) {
                    const slf = (d.pax / d.cap) * 100;
                    if (slf < minSlf) {
                        minSlf = slf;
                        low = { route: rt, slf: slf.toFixed(1) + '%' };
                    }
                }
            });
            return low || { route: 'N/A', slf: '0%' };
        },

        getLfFleetAnalysis() {
            const recs = this.getLfRecords();
            if (!recs.length) return [];
            const fleetMap = {};
            recs.forEach(r => {
                const ac = (r.typeOfAircraft || r.aircraft_type || r.aircraftType || r.desc || 'OTHER').toUpperCase().trim();
                if (!fleetMap[ac]) fleetMap[ac] = { type: ac, flights: 0, seats: 0, pax: 0 };
                fleetMap[ac].flights++;
                fleetMap[ac].seats += Number(r.seatCapacity || r.cap) || 0;
                fleetMap[ac].pax += Number(r.pax || r.load) || 0;
            });
            return Object.values(fleetMap).map(f => ({
                ...f,
                slf: f.seats > 0 ? ((f.pax / f.seats) * 100).toFixed(1) : '0.0'
            })).sort((a,b) => b.flights - a.flights);
        },

        buildSlfRankChart() {
            const ctx = document.getElementById('fdrSlfRankChart');
            if (!ctx) return;
            if (_fdrCharts.slfRank) {
                _fdrCharts.slfRank.destroy();
                _fdrCharts.slfRank = null;
            }
            const recs = this.getLfRecords();
            const routeMap = {};
            recs.forEach(r => {
                const rt = (r.arrivalRoute && r.departureRoute) ? `${r.arrivalRoute} → ${r.departureRoute}` : (r.route || 'ROUTE');
                if (!routeMap[rt]) routeMap[rt] = { cap: 0, pax: 0, count: 0 };
                routeMap[rt].cap += Number(r.seatCapacity || r.cap) || 0;
                routeMap[rt].pax += Number(r.pax || r.load) || 0;
                routeMap[rt].count++;
            });
            const ranked = Object.entries(routeMap).map(([rt, d]) => ({
                route: rt,
                slf: d.cap > 0 ? Number(((d.pax / d.cap) * 100).toFixed(1)) : 0
            })).sort((a,b) => b.slf - a.slf).slice(0, 8);

            const targetLinePlugin = {
                id: 'targetLine',
                afterDraw(chart) {
                    const xScale = chart.scales.x;
                    const yScale = chart.scales.y;
                    if (!xScale || !yScale) return;
                    const xPos = xScale.getPixelForValue(75);
                    const c = chart.ctx;
                    c.save();
                    c.beginPath();
                    c.setLineDash([5, 5]);
                    c.moveTo(xPos, yScale.top);
                    c.lineTo(xPos, yScale.bottom);
                    c.lineWidth = 2;
                    c.strokeStyle = '#10B981';
                    c.stroke();
                    c.restore();
                }
            };

            _fdrCharts.slfRank = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ranked.map(r => r.route),
                    datasets: [{
                        label: 'Load Factor (%)',
                        data: ranked.map(r => r.slf),
                        backgroundColor: ranked.map(r => r.slf >= 75 ? '#10B981' : (r.slf >= 50 ? '#F59E0B' : '#EF4444')),
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: (c) => `SLF: ${c.raw}% (Target 75%)` } }
                    },
                    scales: {
                        x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
                        y: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' } } }
                    }
                },
                plugins: [targetLinePlugin]
            });
        },

        buildSlfComboChart() {
            const ctx = document.getElementById('fdrSlfComboChart');
            if (!ctx) return;
            if (_fdrCharts.slfCombo) {
                _fdrCharts.slfCombo.destroy();
                _fdrCharts.slfCombo = null;
            }
            const recs = this.getLfRecords();
            const routeMap = {};
            recs.forEach(r => {
                const rt = (r.arrivalRoute && r.departureRoute) ? `${r.arrivalRoute} → ${r.departureRoute}` : (r.route || 'ROUTE');
                if (!routeMap[rt]) routeMap[rt] = { cap: 0, pax: 0, count: 0 };
                routeMap[rt].cap += Number(r.seatCapacity || r.cap) || 0;
                routeMap[rt].pax += Number(r.pax || r.load) || 0;
                routeMap[rt].count++;
            });
            const topRoutes = Object.entries(routeMap).sort((a,b) => b[1].count - a[1].count).slice(0, 7);
            _fdrCharts.slfCombo = new Chart(ctx, {
                data: {
                    labels: topRoutes.map(t => t[0]),
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Seat Capacity',
                            data: topRoutes.map(t => t[1].cap),
                            backgroundColor: '#93C5FD',
                            borderRadius: 4,
                            yAxisID: 'y'
                        },
                        {
                            type: 'bar',
                            label: 'Actual Pax',
                            data: topRoutes.map(t => t[1].pax),
                            backgroundColor: '#3B82F6',
                            borderRadius: 4,
                            yAxisID: 'y'
                        },
                        {
                            type: 'line',
                            label: 'Load Factor (%)',
                            data: topRoutes.map(t => t[1].cap > 0 ? Number(((t[1].pax / t[1].cap) * 100).toFixed(1)) : 0),
                            borderColor: '#10B981',
                            backgroundColor: '#10B981',
                            borderWidth: 2.5,
                            tension: 0.2,
                            pointRadius: 4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' } } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9, weight: 'bold' } } },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            grid: { color: '#F1F5F9' },
                            title: { display: true, text: 'Volume' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            max: 100,
                            grid: { drawOnChartArea: false },
                            ticks: { callback: v => v + '%' },
                            title: { display: true, text: 'LF (%)' }
                        }
                    }
                }
            });
        },

        // ── Compare Load Factor (YoY) Getters & Chart Builders ───────────────────
        getYoyRecords() {
            if (this.clientParsedData && this.clientParsedData.records) {
                return this.clientParsedData.records;
            }
            return [];
        },

        getYoyKpi(key) {
            const recs = this.getYoyRecords();
            if (!recs.length) return key.includes('Ratio') ? '0.0' : 0;
            const sum = (k) => recs.reduce((a, b) => a + (Number(b[k]) || 0), 0);
            const fp = sum('flightPrevYear'), fc = sum('flightCurrYear');
            const pp = sum('paxPrevYear'), pc = sum('paxCurrYear');
            const cp = sum('cargoPrevYear'), cc = sum('cargoCurrYear');
            const sp = sum('seatCapPrevYear'), sc = sum('seatCapCurrYear');

            switch (key) {
                case 'flightPrev': return fp;
                case 'flightCurr': return fc;
                case 'flightRatio': return fp > 0 ? ((fc / fp) * 100).toFixed(1) : '100.0';
                case 'paxPrev': return pp;
                case 'paxCurr': return pc;
                case 'paxRatio': return pp > 0 ? ((pc / pp) * 100).toFixed(1) : '100.0';
                case 'cargoPrev': return cp;
                case 'cargoCurr': return cc;
                case 'cargoRatio': return cp > 0 ? ((cc / cp) * 100).toFixed(1) : '100.0';
                case 'seatcapPrev': return sp;
                case 'seatcapCurr': return sc;
                case 'seatcapRatio': return sp > 0 ? ((sc / sp) * 100).toFixed(1) : '100.0';
                case 'slfPrevRatio': return sp > 0 ? ((pp / sp) * 100).toFixed(1) : '0.0';
                case 'slfCurrRatio': return sc > 0 ? ((pc / sc) * 100).toFixed(1) : '0.0';
                default: return 0;
            }
        },

        getDayName(dateStr) {
            if (!dateStr) return '-';
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const d = new Date(dateStr);
            return isNaN(d.getTime()) ? '-' : days[d.getDay()];
        },

        getYoyPeakDate() {
            const recs = this.getYoyRecords().filter(r => r.date);
            if (!recs.length) return 'N/A';
            let peak = recs[0];
            recs.forEach(r => {
                if ((Number(r.paxCurrYear) || 0) > (Number(peak.paxCurrYear) || 0)) {
                    peak = r;
                }
            });
            return peak.date ? `${peak.date} (${this.getDayName(peak.date)})` : (peak.airport || 'N/A');
        },

        getYoyPeakPax() {
            const recs = this.getYoyRecords().filter(r => r.date);
            if (!recs.length) return '0 Pax';
            let max = 0;
            recs.forEach(r => {
                const p = Number(r.paxCurrYear) || 0;
                if (p > max) max = p;
            });
            return max.toLocaleString() + ' Pax (Peak Day)';
        },

        getYoyLowDate() {
            const recs = this.getYoyRecords().filter(r => r.date);
            if (!recs.length) return 'N/A';
            let low = recs[0], minSlf = 999999;
            recs.forEach(r => {
                const cap = Number(r.seatCapCurrYear || r.seatCap2026 || 0);
                const pax = Number(r.paxCurrYear || r.pax2026 || 0);
                const slf = cap > 0 ? (pax / cap) * 100 : 0;
                if (slf < minSlf && cap > 0) {
                    minSlf = slf;
                    low = r;
                }
            });
            return low.date ? `${low.date} (${this.getDayName(low.date)})` : 'N/A';
        },

        getYoyLowPax() {
            const recs = this.getYoyRecords().filter(r => r.date);
            if (!recs.length) return '0 Pax';
            let minSlf = 999999, minPax = 0;
            recs.forEach(r => {
                const cap = Number(r.seatCapCurrYear || r.seatCap2026 || 0);
                const pax = Number(r.paxCurrYear || r.pax2026 || 0);
                const slf = cap > 0 ? (pax / cap) * 100 : 0;
                if (slf < minSlf && cap > 0) {
                    minSlf = slf;
                    minPax = pax;
                }
            });
            return minPax.toLocaleString() + ' Pax (Low Day)';
        },

        getYoyWeekdayWeekendLF() {
            const recs = this.getYoyRecords().filter(r => r.date);
            if (!recs.length) return { weekday2026: '0.0%', weekend2026: '0.0%', weekday2025: '0.0%', weekend2025: '0.0%' };
            let wdPax26 = 0, wdCap26 = 0, wePax26 = 0, weCap26 = 0;
            let wdPax25 = 0, wdCap25 = 0, wePax25 = 0, weCap25 = 0;
            recs.forEach(r => {
                const d = new Date(r.date);
                const day = d.getDay();
                const isWeekend = (day === 0 || day === 5 || day === 6);
                const p26 = Number(r.paxCurrYear || r.pax2026 || 0);
                const c26 = Number(r.seatCapCurrYear || r.seatCap2026 || 0);
                const p25 = Number(r.paxPrevYear || r.pax2025 || 0);
                const c25 = Number(r.seatCapPrevYear || r.seatCap2025 || 0);
                if (isWeekend) {
                    wePax26 += p26; weCap26 += c26;
                    wePax25 += p25; weCap25 += c25;
                } else {
                    wdPax26 += p26; wdCap26 += c26;
                    wdPax25 += p25; wdCap25 += c25;
                }
            });
            return {
                weekday2026: wdCap26 > 0 ? ((wdPax26 / wdCap26) * 100).toFixed(1) + '%' : '0.0%',
                weekend2026: weCap26 > 0 ? ((wePax26 / weCap26) * 100).toFixed(1) + '%' : '0.0%',
                weekday2025: wdCap25 > 0 ? ((wdPax25 / wdCap25) * 100).toFixed(1) + '%' : '0.0%',
                weekend2025: weCap25 > 0 ? ((wePax25 / weCap25) * 100).toFixed(1) + '%' : '0.0%',
            };
        },

        getYoyCalendarDays() {
            const recs = this.getYoyRecords().filter(r => r.date).sort((a,b) => (a.date || '').localeCompare(b.date || ''));
            const daysIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            return recs.map(r => {
                const d = new Date(r.date);
                const dayName = isNaN(d.getTime()) ? '-' : daysIndo[d.getDay()];
                const slf = parseFloat(r.slf2026Ratio || r.slfCurrRatio || (r.seatCapCurrYear > 0 ? ((r.paxCurrYear / r.seatCapCurrYear)*100) : 0)) || 0;
                return {
                    date: r.date,
                    dayName,
                    dayNum: (r.date || '').split('-').pop(),
                    slf: slf.toFixed(1),
                    pax: Number(r.paxCurrYear || 0),
                    cap: Number(r.seatCapCurrYear || 0),
                    flights: Number(r.flightCurrYear || 0)
                };
            });
        },

        buildYoyBarChart() {
            const ctx = document.getElementById('fdrYoyBarChart');
            if (!ctx) return;
            if (_fdrCharts.yoyBar) {
                _fdrCharts.yoyBar.destroy();
                _fdrCharts.yoyBar = null;
            }
            const isDaily = this.activeReportType === 'COMPARE_LOAD_FACTOR_DAY';
            const recs = isDaily
                ? this.getYoyRecords().filter(r => r.date).sort((a,b) => (a.date || '').localeCompare(b.date || ''))
                : this.getYoyRecords().slice(0, 10);

            _fdrCharts.yoyBar = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: recs.map(r => isDaily ? (r.date || '').split('-').slice(1).join('-') : (r.airport || 'AIRPORT')),
                    datasets: [
                        { label: '2025 Pax', data: recs.map(r => Number(r.paxPrevYear || r.pax2025 || 0)), backgroundColor: '#94A3B8', borderRadius: 4 },
                        { label: '2026 Pax', data: recs.map(r => Number(r.paxCurrYear || r.pax2026 || 0)), backgroundColor: '#3B82F6', borderRadius: 4 },
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9 } } },
                        y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
                    }
                }
            });
        },

        buildYoyTrendChart() {
            const ctx = document.getElementById('fdrYoyTrendChart');
            if (!ctx) return;
            if (_fdrCharts.yoyTrend) {
                _fdrCharts.yoyTrend.destroy();
                _fdrCharts.yoyTrend = null;
            }
            const isDaily = this.activeReportType === 'COMPARE_LOAD_FACTOR_DAY';
            if (isDaily) {
                const recs = this.getYoyRecords().filter(r => r.date).sort((a,b) => (a.date || '').localeCompare(b.date || ''));
                _fdrCharts.yoyTrend = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: recs.map(r => (r.date || '').split('-').slice(1).join('-')),
                        datasets: [
                            {
                                label: 'SLF 2025 (%)',
                                data: recs.map(r => parseFloat(r.slf2025Ratio || r.slfPrevRatio || 0)),
                                borderColor: '#94A3B8',
                                backgroundColor: '#94A3B8',
                                tension: 0.25,
                                pointRadius: 3
                            },
                            {
                                label: 'SLF 2026 (%)',
                                data: recs.map(r => parseFloat(r.slf2026Ratio || r.slfCurrRatio || 0)),
                                borderColor: '#8B5CF6',
                                backgroundColor: '#8B5CF6',
                                tension: 0.25,
                                pointRadius: 3
                            },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' } } } },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 9 } } },
                            y: { beginAtZero: false, min: 40, max: 100, grid: { color: '#F1F5F9' }, ticks: { callback: v => v + '%' } }
                        }
                    }
                });
            } else {
                // Aggregate: Delta / Variance Ratio Chart
                const recs = this.getYoyRecords().slice(0, 10);
                _fdrCharts.yoyTrend = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: recs.map(r => r.airport || 'AIRPORT'),
                        datasets: [{
                            label: 'Pax Growth Ratio (% RATIO)',
                            data: recs.map(r => parseFloat(r.paxRatio || 100) - 100),
                            backgroundColor: recs.map(r => parseFloat(r.paxRatio || 100) >= 100 ? '#10B981' : '#EF4444'),
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (c) => `Growth Δ: ${c.raw > 0 ? '+' : ''}${c.raw.toFixed(1)}%` } }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { grid: { color: '#F1F5F9' }, ticks: { callback: v => (v > 0 ? '+' : '') + v + '%' } }
                        }
                    }
                });
            }
        },

        // ── OASYS vs APPS Getters & Chart Builders ────────────────────────────────
        getAppsRecords() {
            if (this.clientParsedData && this.clientParsedData.records) {
                return this.clientParsedData.records;
            }
            return [];
        },

        getFilteredAppsRecords(discrepancyOnly) {
            const recs = this.getAppsRecords();
            if (!discrepancyOnly) return recs;
            return recs.filter(r => {
                const adult = Number(r.adult || 0);
                const missingOffBlock = !r.offBlockTime || r.offBlockTime.trim() === '' || r.offBlockTime.includes('-0001');
                return adult === 0 || missingOffBlock;
            });
        },

        getPaginatedAppsRecords(discrepancyOnly) {
            const recs = this.getFilteredAppsRecords(discrepancyOnly);
            const start = (this.pagination.current_page - 1) * this.pagination.per_page;
            return recs.slice(start, start + this.pagination.per_page);
        },

        getAppsDiscrepancyCount() {
            const recs = this.getAppsRecords();
            return recs.filter(r => {
                const adult = Number(r.adult || 0);
                const missingOffBlock = !r.offBlockTime || r.offBlockTime.trim() === '' || r.offBlockTime.includes('-0001');
                return adult === 0 || missingOffBlock;
            }).length;
        },

        getAppsDelayMinutes(r) {
            if (!r.stad || !r.offBlockTime) return 0;
            const parseMin = (s) => {
                const m = (s || '').match(/(\d{1,2}):(\d{2})/);
                return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : 0;
            };
            let diff = parseMin(r.offBlockTime) - parseMin(r.stad);
            if (diff < -720) diff += 1440;
            return diff;
        },

        getAppsDelayDisplay(r) {
            if (!r.offBlockTime || r.offBlockTime.trim() === '' || r.offBlockTime.includes('-0001')) return '-';
            const d = this.getAppsDelayMinutes(r);
            if (d === 0) return '0 min';
            return d > 0 ? `+${d} min` : `${d} min`;
        },

        getAppsValidationStatus(r) {
            const adult = Number(r.adult || 0);
            const missingOffBlock = !r.offBlockTime || r.offBlockTime.trim() === '' || r.offBlockTime.includes('-0001');
            if (missingOffBlock && adult === 0) return 'MISSING DATA';
            if (missingOffBlock) return 'NO OFF BLOCK';
            if (adult === 0) return 'ZERO PAX';
            return 'VALIDATED';
        },

        getAppsValidationBadgeClass(r) {
            const s = this.getAppsValidationStatus(r);
            if (s === 'VALIDATED') return 'bg-emerald-100 text-emerald-800 border border-emerald-300';
            if (s === 'ZERO PAX') return 'bg-amber-100 text-amber-800 border border-amber-300';
            return 'bg-rose-100 text-rose-800 border border-rose-300';
        },

        getAppsMatrixVal(category, metric) {
            if (this.clientParsedData && this.clientParsedData.reconciliationMatrix) {
                const matrix = this.clientParsedData.reconciliationMatrix;
                const row = metric === 'fltDep' ? 'fltDep' : (metric === 'apps' ? 'apps' : 'deviation');
                if (matrix[row]) {
                    return matrix[row][category] || 0;
                }
            }
            return 0;
        },

        getAppsTotalFltDep() {
            return this.getAppsMatrixVal('total', 'fltDep') || this.getAppsRecords().length;
        },

        getAppsFltDep() {
            return this.getAppsTotalFltDep();
        },

        getAppsTotalApps() {
            return this.getAppsMatrixVal('total', 'apps') || this.getAppsRecords().length;
        },

        getAppsRecorded() {
            return this.getAppsTotalApps();
        },

        getAppsTotalDev() {
            return this.getAppsMatrixVal('total', 'dev');
        },

        getAppsDeviation() {
            return this.getAppsTotalDev() || 0;
        },

        getAppsSyncAccuracy() {
            const flt = this.getAppsFltDep();
            const apps = this.getAppsRecorded();
            if (flt === 0) return '100.0';
            return ((apps / flt) * 100).toFixed(2);
        },

        getAppsTotalPax() {
            return this.getAppsRecords().reduce((acc, r) => acc + (Number(r.adult || 0) + Number(r.child || 0) + Number(r.infant || 0) + Number(r.transit || 0) + Number(r.transfer || 0)), 0);
        },

        getAppsTotalCrew() {
            return this.getAppsRecords().reduce((acc, r) => acc + (Number(r.crew || 0) + Number(r.exCrew || 0)), 0);
        },

        buildAppsCompBarChart() {
            const ctx = document.getElementById('fdrAppsCompBarChart');
            if (!ctx) return;
            if (_fdrCharts.appsCompBar) {
                _fdrCharts.appsCompBar.destroy();
                _fdrCharts.appsCompBar = null;
            }
            const categories = [
                { label: 'Normal Dom', key: 'normalDom' },
                { label: 'Normal Int', key: 'normalInt' },
                { label: 'Transit Dom', key: 'transitDom' },
                { label: 'Transit Int', key: 'transitInt' }
            ];
            _fdrCharts.appsCompBar = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: categories.map(c => c.label),
                    datasets: [
                        {
                            label: 'OASYS FLT DEP',
                            data: categories.map(c => this.getAppsMatrixVal(c.key, 'fltDep')),
                            backgroundColor: '#0284C7',
                            borderRadius: 4
                        },
                        {
                            label: 'APPS RECORDED',
                            data: categories.map(c => this.getAppsMatrixVal(c.key, 'apps')),
                            backgroundColor: '#10B981',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' } } } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
                    }
                }
            });
        },

        buildAppsDiscrepancyChart() {
            const ctx = document.getElementById('fdrAppsDiscrepancyChart');
            if (!ctx) return;
            if (_fdrCharts.appsDiscrepancy) {
                _fdrCharts.appsDiscrepancy.destroy();
                _fdrCharts.appsDiscrepancy = null;
            }
            const categories = [
                { label: 'Normal Dom', key: 'normalDom' },
                { label: 'Normal Int', key: 'normalInt' },
                { label: 'Transit Dom', key: 'transitDom' },
                { label: 'Transit Int', key: 'transitInt' }
            ];
            const devs = categories.map(c => this.getAppsMatrixVal(c.key, 'dev'));
            _fdrCharts.appsDiscrepancy = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: categories.map(c => c.label),
                    datasets: [{
                        label: 'Deviation (DEV)',
                        data: devs,
                        backgroundColor: devs.map(d => d === 0 ? '#10B981' : '#F43F5E'),
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { precision: 0 } }
                    }
                }
            });
        },

        buildAppsStatusDonutChart() {
            const ctx = document.getElementById('fdrAppsStatusDonutChart');
            if (!ctx) return;
            if (_fdrCharts.appsStatusDonut) {
                _fdrCharts.appsStatusDonut.destroy();
                _fdrCharts.appsStatusDonut = null;
            }
            const total = this.getAppsRecords().length;
            const anomalyCount = this.getAppsDiscrepancyCount();
            const matchedCount = Math.max(0, total - anomalyCount);
            _fdrCharts.appsStatusDonut = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Matched (Sesuai)', 'Anomali / Variance'],
                    datasets: [{
                        data: [matchedCount, anomalyCount],
                        backgroundColor: ['#10B981', '#F43F5E'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' } } } }
                }
            });
        },

        // ── OASYS vs EDIFLY Getters & Chart Builders ─────────────────────────────
        getEdiflyRecords() {
            if (this.clientParsedData && this.clientParsedData.records) {
                return this.clientParsedData.records;
            }
            return [];
        },

        getPaginatedEdiflyRecords() {
            const recs = this.getEdiflyRecords();
            const start = (this.pagination.current_page - 1) * this.pagination.per_page;
            return recs.slice(start, start + this.pagination.per_page);
        },

        getEdiflyTotalMovements() {
            return this.getEdiflyRecords().length;
        },

        getEdiflyPaxIntegrationRate() {
            const recs = this.getEdiflyRecords();
            if (!recs.length) return '0.0';
            const integrated = recs.filter(r => (r.paxIntegrated ?? r.isPaxIntegrated ?? !/NO INTEGRATION/i.test(r.apiPaxEdifly || ''))).length;
            return ((integrated / recs.length) * 100).toFixed(1);
        },

        getEdiflyCargoIntegrationRate() {
            const recs = this.getEdiflyRecords();
            if (!recs.length) return '0.0';
            const integrated = recs.filter(r => (r.cargoIntegrated ?? r.isCargoIntegrated ?? !/NO INTEGRATION/i.test(r.apiCargoEdifly || ''))).length;
            return ((integrated / recs.length) * 100).toFixed(1);
        },

        getEdiflyUnintegratedCount() {
            const recs = this.getEdiflyRecords();
            return recs.filter(r => {
                const p = (r.paxIntegrated ?? r.isPaxIntegrated ?? !/NO INTEGRATION/i.test(r.apiPaxEdifly || ''));
                const c = (r.cargoIntegrated ?? r.isCargoIntegrated ?? !/NO INTEGRATION/i.test(r.apiCargoEdifly || ''));
                return !p || !c;
            }).length;
        },

        getEdiflyTotalMtow() {
            return Math.round(this.getEdiflyRecords().reduce((acc, r) => acc + (parseFloat(r.mtow) || 0), 0));
        },

        getEdiflyNarrowCount() {
            return this.getEdiflyRecords().filter(r => /narrow/i.test(r.desc || r.aircraftType || '')).length;
        },

        getEdiflyWideCount() {
            return this.getEdiflyRecords().filter(r => /wide/i.test(r.desc || r.aircraftType || '')).length;
        },

        getEdiflyPaxIntegratedCount() {
            return this.getEdiflyRecords().filter(r => (r.paxIntegrated ?? r.isPaxIntegrated ?? !/NO INTEGRATION/i.test(r.apiPaxEdifly || ''))).length;
        },

        getEdiflyPaxNoIntegrationCount() {
            return this.getEdiflyRecords().length - this.getEdiflyPaxIntegratedCount();
        },

        getEdiflyCargoIntegratedCount() {
            return this.getEdiflyRecords().filter(r => (r.cargoIntegrated ?? r.isCargoIntegrated ?? !/NO INTEGRATION/i.test(r.apiCargoEdifly || ''))).length;
        },

        getEdiflyCargoNoIntegrationCount() {
            return this.getEdiflyRecords().length - this.getEdiflyCargoIntegratedCount();
        },

        getEdiflyActualizedCount() {
            return this.getEdiflyRecords().filter(r => (r.isActualized ?? (r.aobtAibt && r.aobtAibt.trim() !== '' && !r.aobtAibt.includes('-0001')))).length;
        },

        getEdiflyActualizedRate() {
            const recs = this.getEdiflyRecords();
            if (!recs.length) return '0.0';
            const act = this.getEdiflyActualizedCount();
            return ((act / recs.length) * 100).toFixed(1);
        },

        getEdiflyPendingCount() {
            return this.getEdiflyRecords().length - this.getEdiflyActualizedCount();
        },

        getEdiflyTimeDiffMinutes(r) {
            const sched = r.sobtSibt || r.sobt || r.sibt;
            const actual = r.aobtAibt || r.aobt || r.aibt;
            if (!sched || !actual || actual.includes('-0001')) return 0;
            const parseMin = (s) => {
                const m = (s || '').match(/(\d{1,2}):(\d{2})/);
                return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : 0;
            };
            let diff = parseMin(actual) - parseMin(sched);
            if (diff < -720) diff += 1440;
            return diff;
        },

        getEdiflyTimeDiffDisplay(r) {
            const actual = r.aobtAibt || r.aobt || r.aibt;
            if (!actual || actual.includes('-0001') || actual.trim() === '') return 'Pending';
            const d = this.getEdiflyTimeDiffMinutes(r);
            return d > 0 ? `+${d} min` : `${d} min`;
        },

        getEdiflyCommentTotals() {
            if (this.clientParsedData && this.clientParsedData.commentTotals) {
                return this.clientParsedData.commentTotals;
            }
            if (this.clientParsedData && this.clientParsedData.summary && this.clientParsedData.summary.commentTotals) {
                return this.clientParsedData.summary.commentTotals;
            }
            return {
                paxAll: 0,
                adult: 0,
                child: 0,
                infant: 0,
                transit: 0,
                transfer: 0,
                crew: 0,
                cargoKg: 0,
                baggageKg: 0,
                posKg: 0,
                standUse: 0,
                runwayUse: 0,
            };
        },

        buildEdiflyDailySyncChart() {
            const ctx = document.getElementById('fdrEdiflyDailySyncChart');
            if (!ctx) return;
            if (_fdrCharts.ediflyDailySync) {
                _fdrCharts.ediflyDailySync.destroy();
                _fdrCharts.ediflyDailySync = null;
            }
            const recs = this.getEdiflyRecords();
            // Aggregate by date (from SOBT/SIBT)
            const dateMap = {};
            recs.forEach(r => {
                const timeStr = r.sobtSibt || r.sobt || r.sibt || '';
                const m = timeStr.match(/(\d{2})-(\d{2})-(\d{4})/);
                const dKey = m ? `${m[1]}/${m[2]}` : 'Flight';
                if (!dateMap[dKey]) dateMap[dKey] = { sched: 0, act: 0, edifly: 0 };
                dateMap[dKey].sched++;
                if (r.aobtAibt && r.aobtAibt.trim() !== '' && !r.aobtAibt.includes('-0001')) dateMap[dKey].act++;
                if (r.paxIntegrated ?? r.isPaxIntegrated ?? !/NO INTEGRATION/i.test(r.apiPaxEdifly || '')) dateMap[dKey].edifly++;
            });
            const labels = Object.keys(dateMap).slice(0, 15);
            _fdrCharts.ediflyDailySync = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Scheduled (OASYS)', data: labels.map(l => dateMap[l].sched), borderColor: '#94A3B8', tension: 0.2 },
                        { label: 'Actualized (AOBT/AIBT)', data: labels.map(l => dateMap[l].act), borderColor: '#0284C7', tension: 0.2 },
                        { label: 'Edifly-Integrated', data: labels.map(l => dateMap[l].edifly), borderColor: '#10B981', tension: 0.2 },
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10, weight: 'bold' } } } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#F1F5F9' } }
                    }
                }
            });
        },

        buildEdiflyBreakdownChart() {
            const ctx = document.getElementById('fdrEdiflyBreakdownChart');
            if (!ctx) return;
            if (_fdrCharts.ediflyBreakdown) {
                _fdrCharts.ediflyBreakdown.destroy();
                _fdrCharts.ediflyBreakdown = null;
            }
            const recs = this.getEdiflyRecords();
            const routeMap = {};
            recs.forEach(r => {
                const rt = (r.station1 && r.station2) ? `${r.station1} → ${r.station2}` : 'ROUTE';
                const isGap = (r.paxIntegrated === false || /NO INTEGRATION/i.test(r.apiPaxEdifly || ''));
                if (isGap) {
                    routeMap[rt] = (routeMap[rt] || 0) + 1;
                }
            });
            const topGaps = Object.entries(routeMap).sort((a,b) => b[1] - a[1]).slice(0, 6);
            _fdrCharts.ediflyBreakdown = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: topGaps.length ? topGaps.map(g => g[0]) : ['All Routes Synced'],
                    datasets: [{
                        label: 'Unintegrated Flights',
                        data: topGaps.length ? topGaps.map(g => g[1]) : [0],
                        backgroundColor: '#F43F5E',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9, weight: 'bold' } } },
                        y: { beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { precision: 0 } }
                    }
                }
            });
        },

        // ── Normal View: Route Analytics, Suffixes & Network Map ──────────────────
        getSpecialSuffixTotalCount() {
            const dist = this.getSuffixDistribution();
            return dist.reduce((acc, d) => acc + (d.count || 0), 0);
        },

        getSuffixDistribution() {
            if (this.airlineRoute) {
                if (this.airlineRoute.suffix_distribution && this.airlineRoute.suffix_distribution.length > 0) return this.airlineRoute.suffix_distribution;
                if (this.airlineRoute.suffix_breakdown && Array.isArray(this.airlineRoute.suffix_breakdown) && this.airlineRoute.suffix_breakdown.length > 0) return this.airlineRoute.suffix_breakdown;
            }
            if (this.clientParsedData) {
                if (this.clientParsedData.suffix_distribution && this.clientParsedData.suffix_distribution.length > 0) return this.clientParsedData.suffix_distribution;
            }
            return [];
        },

        getLegSubtypeCount(type) {
            if (this.airlineRoute && this.airlineRoute.leg_subtypes && this.airlineRoute.leg_subtypes[type] != null) {
                return this.airlineRoute.leg_subtypes[type];
            }
            if (this.clientParsedData && this.clientParsedData.leg_subtypes && this.clientParsedData.leg_subtypes[type] != null) {
                return this.clientParsedData.leg_subtypes[type];
            }
            return 0;
        },

        getLegSubtypePct(type) {
            const total = (this.kpis && this.kpis.total_flights) || this.totalRecords || (this.allClientRecords ? this.allClientRecords.length : 0) || 1;
            const cnt = this.getLegSubtypeCount(type);
            return ((cnt / total) * 100).toFixed(1) + '%';
        },

        getBusiestRoutes() {
            if (this.airlineRoute) {
                if (this.airlineRoute.top_busiest_routes && this.airlineRoute.top_busiest_routes.length > 0) return this.airlineRoute.top_busiest_routes;
                if (this.airlineRoute.busiest_routes && this.airlineRoute.busiest_routes.length > 0) return this.airlineRoute.busiest_routes;
                if (this.airlineRoute.top_routes && this.airlineRoute.top_routes.length > 0) return this.airlineRoute.top_routes.slice(0, 10);
            }
            if (this.clientParsedData) {
                if (this.clientParsedData.top_busiest_routes && this.clientParsedData.top_busiest_routes.length > 0) return this.clientParsedData.top_busiest_routes;
                if (this.clientParsedData.busiest_routes && this.clientParsedData.busiest_routes.length > 0) return this.clientParsedData.busiest_routes;
                if (this.clientParsedData.top_routes && this.clientParsedData.top_routes.length > 0) return this.clientParsedData.top_routes.slice(0, 10);
            }
            return [];
        },

        getMostDelayedRoutes() {
            if (this.airlineRoute) {
                if (this.airlineRoute.top_delayed_routes && this.airlineRoute.top_delayed_routes.length > 0) return this.airlineRoute.top_delayed_routes;
                if (this.airlineRoute.most_delayed_routes && this.airlineRoute.most_delayed_routes.length > 0) return this.airlineRoute.most_delayed_routes;
            }
            if (this.clientParsedData) {
                if (this.clientParsedData.top_delayed_routes && this.clientParsedData.top_delayed_routes.length > 0) return this.clientParsedData.top_delayed_routes;
                if (this.clientParsedData.most_delayed_routes && this.clientParsedData.most_delayed_routes.length > 0) return this.clientParsedData.most_delayed_routes;
            }
            return [];
        },

        getHighestSlfRoutes() {
            if (this.airlineRoute) {
                if (this.airlineRoute.top_slf_routes && this.airlineRoute.top_slf_routes.length > 0) return this.airlineRoute.top_slf_routes;
                if (this.airlineRoute.highest_slf_routes && this.airlineRoute.highest_slf_routes.length > 0) return this.airlineRoute.highest_slf_routes;
            }
            if (this.clientParsedData) {
                if (this.clientParsedData.top_slf_routes && this.clientParsedData.top_slf_routes.length > 0) return this.clientParsedData.top_slf_routes;
                if (this.clientParsedData.highest_slf_routes && this.clientParsedData.highest_slf_routes.length > 0) return this.clientParsedData.highest_slf_routes;
            }
            return [];
        },

        projectGeo(lon, lat, code) {
            // Geographic pin overrides for international & distant border stations to prevent canvas clipping
            const overrides = {
                'AMS': { x: 55, y: 70 },
                'JED': { x: 75, y: 195 },
                'MED': { x: 75, y: 155 },
                'DOH': { x: 110, y: 220 },
                'BKK': { x: 260, y: 80 },
                'SIN': { x: 245, y: 155 },
                'KUL': { x: 215, y: 125 },
                'HKG': { x: 470, y: 75 },
                'CAN': { x: 510, y: 75 },
                'TPE': { x: 575, y: 70 },
                'PEK': { x: 620, y: 45 },
                'PVG': { x: 670, y: 65 },
                'ICN': { x: 720, y: 50 },
                'HND': { x: 790, y: 50 },
                'NRT': { x: 830, y: 55 },
                'PER': { x: 530, y: 380 },
                'MEL': { x: 810, y: 385 },
                'SYD': { x: 910, y: 375 },
            };
            if (code && overrides[code]) {
                return overrides[code];
            }

            // Indonesian archipelago bounds: Lon 95..141 -> x 170..860, Lat 6..-11 -> y 100..340
            let x = 170 + ((lon - 95.0) / 46.0) * 690;
            let y = 100 + ((6.0 - lat) / 17.0) * 240;

            if (x < 40) x = 40;
            if (x > 960) x = 960;
            if (y < 35) y = 35;
            if (y > 390) y = 390;

            return { x: Math.round(x), y: Math.round(y) };
        },

        getNetworkRoutes() {
            if (this.airlineRoute && Array.isArray(this.airlineRoute.network_routes)) {
                return this.airlineRoute.network_routes;
            }
            return this.getBusiestRoutes();
        },

        getNetworkAirportDictionary() {
            const dict = Object.assign({}, INJOURNEY_AIRPORTS_STATIC, (window.fdrEngine && window.fdrEngine.INJOURNEY_AIRPORTS) || {});
            const serverAirports = this.airlineRoute && this.airlineRoute.network_airports ? this.airlineRoute.network_airports : {};

            Object.keys(serverAirports).forEach((code) => {
                const item = serverAirports[code] || {};
                const lat = Number(item.lat);
                const lon = Number(item.lon);
                if (Number.isFinite(lat) && Number.isFinite(lon)) {
                    dict[code] = Object.assign({}, dict[code] || {}, {
                        code,
                        name: item.name || code,
                        city: item.city || '',
                        lat,
                        lon,
                    });
                }
            });

            return dict;
        },

        getNetworkAirportNodes() {
            const dict = this.getNetworkAirportDictionary();
            const hub = this.filters.airport || this.lockedAirport || 'CGK';
            const connectedAirports = new Set();
            this.getNetworkRoutes().forEach(r => {
                const c1 = r.origin ? r.origin.trim().toUpperCase() : null;
                const c2 = r.destination ? r.destination.trim().toUpperCase() : null;
                if (c1) connectedAirports.add(c1);
                if (c2) connectedAirports.add(c2);
                if ((!c1 || !c2) && r.route) {
                    const parts = r.route.split(/[\s\-→]+/);
                    parts.forEach(p => connectedAirports.add(p.trim().toUpperCase()));
                }
            });
            connectedAirports.add(hub);

            return Object.keys(dict).map(code => {
                const info = dict[code];
                const pt = this.projectGeo(info.lon, info.lat, code);
                const isHub = code === hub;
                const isActive = isHub || connectedAirports.has(code);
                return {
                    code,
                    name: info.name,
                    city: info.city,
                    x: pt.x,
                    y: pt.y,
                    isHub,
                    isActive,
                    flightsCount: isHub ? (this.kpis ? this.kpis.total_flights || 0 : 0) : 0,
                };
            });
        },

        getNetworkRouteLines() {
            const dict = this.getNetworkAirportDictionary();
            const routes = this.getNetworkRoutes();
            const lines = [];

            routes.forEach((r) => {
                let c1 = r.origin ? r.origin.trim().toUpperCase() : null;
                let c2 = r.destination ? r.destination.trim().toUpperCase() : null;
                if (!c1 || !c2) {
                    if (r.route) {
                        const parts = r.route.split(/[\s\-→]+/);
                        if (parts.length >= 2) {
                            c1 = parts[0].trim().toUpperCase();
                            c2 = parts[1].trim().toUpperCase();
                        }
                    }
                }
                if (!c1 || !c2) return;
                const a1 = dict[c1];
                const a2 = dict[c2];
                if (!a1 || !a2) return;

                const p1 = this.projectGeo(a1.lon, a1.lat, c1);
                const p2 = this.projectGeo(a2.lon, a2.lat, c2);

                const mx = (p1.x + p2.x) / 2;
                const dx = p2.x - p1.x;
                const dy = p2.y - p1.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                const curveHeight = Math.max(25, Math.min(80, dist * 0.2));
                const my = Math.min(p1.y, p2.y) - curveHeight;

                const pathD = `M ${p1.x} ${p1.y} Q ${mx} ${my} ${p2.x} ${p2.y}`;
                const flightCount = r.flights || r.count || r.movements || r.total_flights || 1;
                const strokeWidth = Math.max(1.5, Math.min(4.5, (flightCount / 30) + 1.2));

                const paxCount = r.pax != null ? r.pax : (r.passengers || r.totalPax || 0);
                const lfStr = r.avg_load_factor || (r.avgLf != null ? r.avgLf + '%' : (r.slf != null ? r.slf + '%' : 'N/A'));
                const avgDel = r.avg_delay != null ? r.avg_delay : (r.avgDelay != null ? r.avgDelay : (r.avg_delay_min || 0));

                lines.push({
                    route: r.route || `${c1} → ${c2}`,
                    origin: c1,
                    destination: c2,
                    pathD,
                    strokeWidth,
                    flights: flightCount,
                    pax: paxCount,
                    slf: lfStr,
                    avgDelay: avgDel,
                    title: `${c1} → ${c2}: ${flightCount} flights • ${paxCount.toLocaleString()} Pax • LF ${lfStr} • Delay +${avgDel}m`,
                });
            });

            return lines;
        },

        renderNetworkMap() {
            const routeContainer = document.getElementById('network-route-layer');
            const nodeContainer = document.getElementById('network-node-layer');
            if (!routeContainer || !nodeContainer) return;

            const lines = this.getNetworkRouteLines();
            const nodes = this.getNetworkAirportNodes();

            // Render route trajectories (Origin -> Destination)
            let routeHtml = '';
            lines.forEach((line, idx) => {
                routeHtml += `
                    <path d="${line.pathD}"
                          fill="none"
                          stroke="url(#routeGrad)"
                          stroke-width="${line.strokeWidth}"
                          stroke-linecap="round"
                          marker-end="url(#routeArrow)"
                          filter="url(#glow)"
                          class="cursor-pointer transition-all duration-200 hover:stroke-white hover:stroke-[3.5px] opacity-85 hover:opacity-100"
                          data-idx="${idx}">
                        <title>${line.title}</title>
                    </path>
                `;
            });
            routeContainer.innerHTML = routeHtml;

            // Render airport nodes & labels across 35 InJourney stations
            let nodeHtml = '';
            nodes.forEach((node) => {
                const isHub = node.isHub;
                const isActive = node.isActive;
                const r = isHub ? 5 : (isActive ? 4 : 3);
                const fill = isHub ? '#38BDF8' : (isActive ? '#60A5FA' : '#475569');
                const stroke = isHub ? '#FFFFFF' : (isActive ? '#93C5FD' : '#1E293B');
                const strokeWidth = isHub ? 2 : 1.5;
                const textY = node.y > 360 ? (node.y - 8) : (node.y + 12);
                const textFill = isHub ? '#38BDF8' : (isActive ? '#F8FAFC' : '#64748B');

                if (isHub) {
                    nodeHtml += `<circle cx="${node.x}" cy="${node.y}" r="8" fill="#38BDF8" opacity="0.4" class="animate-ping pointer-events-none"></circle>`;
                }

                nodeHtml += `
                    <circle cx="${node.x}" cy="${node.y}" r="${r}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}" class="cursor-pointer" data-code="${node.code}">
                        <title>${node.code} - ${node.name}</title>
                    </circle>
                    <text x="${node.x}" y="${textY}" text-anchor="middle" fill="${textFill}" font-size="9" font-weight="bold" font-family="monospace" letter-spacing="0.5" class="pointer-events-none select-none">${node.code}</text>
                `;
            });
            nodeContainer.innerHTML = nodeHtml;

            // Interactive HUD mouse listeners
            routeContainer.onmouseover = (e) => {
                const target = e.target.closest('path[data-idx]');
                if (target) {
                    const idx = parseInt(target.getAttribute('data-idx'));
                    this.hoveredRoute = lines[idx] || null;
                }
            };
            routeContainer.onmouseout = () => {
                this.hoveredRoute = null;
            };

            nodeContainer.onmouseover = (e) => {
                const target = e.target.closest('circle[data-code]');
                if (target) {
                    const code = target.getAttribute('data-code');
                    this.hoveredAirport = nodes.find(n => n.code === code) || null;
                }
            };
            nodeContainer.onmouseout = () => {
                this.hoveredAirport = null;
            };
        },

        getExportUrl(type) {
            const params = new URLSearchParams({
                date_scope: this.dateScope,
                airport: this.filters.airport,
                leg: this.filters.leg,
                operator: this.filters.operator,
                traffic: this.filters.traffic,
                realization: this.filters.realization,
                analysis_date: this.dateScope === 'ALL_PERIOD' ? '' : (this.filters.analysis_date || ''),
                search: this.filters.search,
                time_basis: this.filters.time_basis,
                report_type: this.isExHalim ? 'EX_HALIM' : this.activeReportType,
            });
            return `/fdr/${this.uploadId}/export/${type}?` + params.toString();
        }
    };
}
</script>
@endpush
@endsection
