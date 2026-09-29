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
                <h1 class="text-sm font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Flight Daily Report (FDR)</span>
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-black uppercase tracking-wider bg-purple-100 text-purple-800 border border-purple-300"
                          x-text="'SOURCE TYPE: ' + (sourceSummary.source_type || '{{ $sourceType ?? 'MONTHLY' }}')">
                        SOURCE TYPE: {{ $sourceType ?? ($sourceSummary['source_type'] ?? 'MONTHLY') }}
                    </span>
                    <span class="px-2 py-0.5 rounded-md font-black text-[10px] bg-blue-600 text-white uppercase tracking-wider">
                        <span x-text="filters.analysis_level === 'DAILY' ? 'PEAK DAILY ANALYSIS' : 'HOURLY ANALYSIS'">PEAK DAILY ANALYSIS</span>
                    </span>
                </h1>
                <p class="text-[11px] text-slate-500 font-medium">Operational flight movement analysis from OASYS Flight Daily Report</p>
                <div class="sr-only">FDR Intelligence • 3 Mentor Hourly Operational Charts • Chart 1: ARRIVAL–DEPARTURE MOVEMENT • Chart 2: DEPARTURE MOVEMENT • Chart 3: ARRIVAL MOVEMENT</div>
            </div>
        </div>

        {{-- Right: Source File & Source Period Metadata + Export Actions --}}
        <div class="flex flex-wrap items-center gap-2.5">
            
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

        {{-- ══ 2. FILTER BAR (COMPACT DEDICATED CARD) ═════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs space-y-3">
            
            {{-- Top Controls Grid: 7 inputs --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3 items-end">
                
                {{-- Airport Filter --}}
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Airport</label>
                    <select x-model="filters.airport" @change="triggerFilter()"
                            class="w-full text-xs font-semibold rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="ALL">ALL AIRPORTS</option>
                        <option value="CGK" selected>CGK — Soekarno-Hatta</option>
                        @foreach($airports as $ap)
                            @if($ap !== 'CGK' && $ap !== 'ALL')
                                <option value="{{ $ap }}">{{ $ap }}</option>
                            @endif
                        @endforeach
                    </select>
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
                            <option value="{{ $al }}">{{ $al }}</option>
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
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Date Range</label>
                    <div class="relative">
                        <template x-if="sourceSummary.source_type === 'DAILY'">
                            <input type="text" :value="filters.analysis_date || sourceSummary.period_start" readonly
                                   class="w-full text-xs font-bold font-mono rounded-xl border border-slate-200 bg-slate-100 text-slate-700 px-2.5 py-1.5 cursor-not-allowed">
                        </template>
                        <template x-if="sourceSummary.source_type !== 'DAILY'">
                            <select x-model="filters.analysis_date" @change="triggerFilter()"
                                    class="w-full text-xs font-bold font-mono rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                <option value="ALL">All Dates in Period</option>
                                <template x-for="d in availableDates" :key="d">
                                    <option :value="d" x-text="d"></option>
                                </template>
                            </select>
                        </template>
                    </div>
                </div>

                {{-- Search Filter --}}
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Search</label>
                    <div class="relative">
                        <input type="text" x-model.debounce.300ms="filters.search" @input="triggerFilter()"
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

                    {{-- Date Chip --}}
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        <span>Date: <strong class="font-mono" x-text="filters.analysis_date || sourceSummary.period_label"></strong></span>
                    </span>

                    {{-- Dynamic Chips from activeChips --}}
                    <template x-for="chip in activeChips" :key="chip.key">
                        <template x-if="chip.key !== 'airport' && chip.key !== 'analysis_date'">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                <span x-text="chip.label"></span>
                                <button type="button" @click="removeChip(chip.key)" class="hover:text-red-500 font-bold ml-0.5">&times;</button>
                            </span>
                        </template>
                    </template>

                    {{-- Clear All Button --}}
                    <button type="button" @click="clearAllFilters()" class="text-xs font-bold text-red-600 hover:text-red-700 ml-1 transition">
                        [ Clear All ]
                    </button>
                </div>

                {{-- Status & Count --}}
                <div class="flex items-center gap-3 text-slate-500 text-xs font-medium">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Showing <strong class="text-slate-900 font-bold" x-text="totalRecords">190</strong> records</span>
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
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="kpis.total_flights">190</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                        <span x-text="kpis.arrivals">95</span> Arr • <span x-text="kpis.departures">95</span> Dep
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
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="kpis.arrivals">95</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                        Dom: <span x-text="kpis.arrivals_dom ?? 95"></span> | Int: <span x-text="kpis.arrivals_int ?? 0"></span>
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
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="kpis.departures">95</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                        Dom: <span x-text="kpis.departures_dom ?? 95"></span> | Int: <span x-text="kpis.departures_int ?? 0"></span>
                    </div>
                </div>
            </div>

            {{-- 4. Total Passengers --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                    </div>
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800" x-text="kpis.pax_per_flight + ' Pax/Flight'">134.9 Pax/Flight</span>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Passengers</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="kpis.total_passengers.toLocaleString()">25,627</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                        Adl: <span x-text="kpis.adult_passengers ? kpis.adult_passengers.toLocaleString() : 0"></span> | Chd: <span x-text="kpis.child_passengers ?? 0"></span> | Inf: <span x-text="kpis.infant_passengers ?? 0"></span>
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
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="kpis.passenger_utilization || kpis.avg_load_factor">68.4%</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                        Cap: <span x-text="kpis.total_capacity ? kpis.total_capacity.toLocaleString() : 0"></span> | Load: <span x-text="kpis.total_load ? kpis.total_load.toLocaleString() : 0"></span>
                    </div>
                </div>
            </div>

            {{-- 6. Cargo (Ton) --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cargo (Ton)</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="kpis.cargo_ton ? Number(kpis.cargo_ton).toFixed(2) : '0.00'">208.97</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                        <span x-text="kpis.cargo_per_flight_t ?? '1.10'"></span> t/Flight
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
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="kpis.baggage_kg ? Math.round(kpis.baggage_kg).toLocaleString() : '0'">147,455</div>
                    <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                        <span x-text="kpis.baggage_per_flight_kg ?? '776'"></span> kg/Flight
                    </div>
                </div>
            </div>

            {{-- 8. Irregular Flights --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <span class="text-[10px] font-bold text-rose-600" x-text="(kpis.irregular_rate || 0) + '%'">6.3%</span>
                </div>
                <div class="mt-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Irregular Flights</div>
                    <div class="text-xl font-black text-slate-900 tracking-tight" x-text="schedVsReal.late_count || (kpis.irregularities ? kpis.irregularities.total : 0)">12</div>
                    <div class="text-[10px] font-semibold text-rose-500 mt-0.5">
                        <span x-text="schedVsReal.late_count ?? 8"></span> Delay | <span x-text="(kpis.irregularities ? (kpis.irregularities.divert + kpis.irregularities.miss + kpis.irregularities.unscheduled) : 0)"></span> Other
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
                    <span class="text-[10px] font-bold text-slate-400">Time Variance Analysis</span>
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

                {{-- Delay Distribution (minutes) Histogram --}}
                <div class="space-y-1.5">
                    <div class="text-[11px] font-bold text-slate-700">Delay Distribution (minutes)</div>
                    <div class="h-36 w-full relative">
                        <canvas id="fdrDelayHistChart"></canvas>
                    </div>
                </div>

                {{-- Bottom 4 Stat Cards --}}
                <div class="grid grid-cols-4 gap-2 pt-2 border-t border-slate-100 text-center">
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Average Delay</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="schedVsReal.avg_delay_minutes">-5.9 min</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Median Delay</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="schedVsReal.median_delay">0 min</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Min Delay</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="schedVsReal.min_delay">-1424 min</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <div class="text-[9px] uppercase font-bold text-slate-400">Max Delay</div>
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="schedVsReal.max_delay">+37 min</div>
                    </div>
                </div>

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
                    <div class="flex flex-col items-center bg-slate-50/60 rounded-xl p-3 border border-slate-100/80">
                        <div class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider mb-2">
                            Passenger Composition
                        </div>
                        <div class="relative w-36 h-36 flex items-center justify-center">
                            <canvas id="fdrPaxCompDonutChart"></canvas>
                            {{-- Centered Text --}}
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                                <span class="text-[8.5px] uppercase font-bold text-slate-400 leading-tight">Total Pax</span>
                                <span class="text-sm font-black text-slate-900 font-mono tracking-tight" x-text="((paxAnalytics.composition.adult || 0) + (paxAnalytics.composition.child || 0) + (paxAnalytics.composition.infant || 0)).toLocaleString()">
                                    25,627
                                </span>
                            </div>
                        </div>
                        {{-- Donut Legend --}}
                        <div class="flex items-center justify-center gap-3 mt-2 text-[10px] font-semibold text-slate-600">
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>Adult</span>
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>Child</span>
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>Infant</span>
                        </div>
                    </div>

                    {{-- DONUT 2: Payload Composition (Cargo, Baggage) --}}
                    <div class="flex flex-col items-center bg-slate-50/60 rounded-xl p-3 border border-slate-100/80">
                        <div class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider mb-2">
                            Payload Composition
                        </div>
                        <div class="relative w-36 h-36 flex items-center justify-center">
                            <canvas id="fdrPayloadCompDonutChart"></canvas>
                            {{-- Centered Text --}}
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                                <span class="text-[8.5px] uppercase font-bold text-slate-400 leading-tight">Total Payload</span>
                                <span class="text-xs font-black text-slate-900 font-mono tracking-tight" x-text="formatPayloadWeight((kpis.total_cargo_kg || 0) + (kpis.total_baggage_kg || 0))">
                                    356.4 t
                                </span>
                            </div>
                        </div>
                        {{-- Donut Legend --}}
                        <div class="flex items-center justify-center gap-3 mt-2 text-[10px] font-semibold text-slate-600">
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>Cargo</span>
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>Baggage</span>
                        </div>
                    </div>

                </div>

                {{-- Compact Flow Badges: Transit, Transfer, Crew (MUTUALLY EXCLUSIVE FROM PIE) --}}
                <div class="grid grid-cols-3 gap-2 text-center py-2 px-3 bg-slate-50 rounded-xl border border-slate-100">
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
                        <div class="text-xs font-black font-mono text-slate-800 mt-0.5" x-text="(paxAnalytics.composition.crew || 0).toLocaleString()">646</div>
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
                        <div class="text-lg font-black text-slate-900" x-text="(paxAnalytics.composition_arr.adult + paxAnalytics.composition_arr.child + paxAnalytics.composition_arr.infant).toLocaleString() + ' Pax'">
                            12,843 Pax
                        </div>
                        <div class="text-[10px] text-slate-500 font-medium">
                            Adl: <span x-text="paxAnalytics.composition_arr.adult.toLocaleString()">12,418</span> | Chd: <span x-text="paxAnalytics.composition_arr.child">361</span> | Inf: <span x-text="paxAnalytics.composition_arr.infant">64</span>
                        </div>
                        <div class="text-[10px] text-slate-500 font-medium pt-1 border-t border-amber-200/40">
                            Cargo: <span x-text="(paxAnalytics.payload_arr.cargo_kg / 1000).toFixed(2) + ' t'">106.42 t</span> | Bag: <span x-text="paxAnalytics.payload_arr.baggage_kg.toLocaleString() + ' kg'">73,541 kg</span>
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
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-blue-100 text-blue-900" x-text="'LF ' + (paxAnalytics.lf_avg_dep ? paxAnalytics.lf_avg_dep + '%' : '70.1%')">LF 70.1%</span>
                        </div>
                        <div class="text-lg font-black text-slate-900" x-text="(paxAnalytics.composition_dep.adult + paxAnalytics.composition_dep.child + paxAnalytics.composition_dep.infant).toLocaleString() + ' Pax'">
                            12,784 Pax
                        </div>
                        <div class="text-[10px] text-slate-500 font-medium">
                            Adl: <span x-text="paxAnalytics.composition_dep.adult.toLocaleString()">12,356</span> | Chd: <span x-text="paxAnalytics.composition_dep.child">396</span> | Inf: <span x-text="paxAnalytics.composition_dep.infant">32</span>
                        </div>
                        <div class="text-[10px] text-slate-500 font-medium pt-1 border-t border-blue-200/40">
                            Cargo: <span x-text="(paxAnalytics.payload_dep.cargo_kg / 1000).toFixed(2) + ' t'">102.55 t</span> | Bag: <span x-text="paxAnalytics.payload_dep.baggage_kg.toLocaleString() + ' kg'">73,914 kg</span>
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
                    <template x-for="item in airlineRoute.ranked_airlines.slice(0, 7)" :key="item.airline">
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
                            <template x-for="item in fleetPerformance.fleet_mix ? fleetPerformance.fleet_mix.slice(0, 6) : []" :key="item.desc">
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
                    <template x-for="item in airlineRoute.ranked_airlines.slice(0, 7)" :key="item.airline">
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

        {{-- ══ 5. FLIGHT / PASSENGER / CARGO ANALYTICS ════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
            
            {{-- Header --}}
            <div class="border-b border-slate-100 pb-3">
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
                            <button type="button" @click="setLegFilter('DEP')"
                                    :class="(filters.leg === 'DEP' || filters.leg === 'DEPARTURE') ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-3 py-1 text-xs rounded-md transition cursor-pointer">
                                DEP
                            </button>
                            <button type="button" @click="setLegFilter('ARR')"
                                    :class="(filters.leg === 'ARR' || filters.leg === 'ARRIVAL') ? 'bg-blue-600 text-white shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                    class="px-3 py-1 text-xs rounded-md transition cursor-pointer">
                                ARR
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

            {{-- VISUAL A: FLIGHT MOVEMENT (Grouped Bar Chart - Full Width) --}}
            <div class="p-4 rounded-xl border border-slate-200/90 bg-white shadow-2xs space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-2">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                            A. Flight Movement
                        </h3>
                        <p class="text-[10px] text-slate-400 font-medium">
                            Grouped bars: Arrival (Amber) vs Departure (Blue) &bull; Domestic (Light) vs International (Dark)
                        </p>
                    </div>

                    {{-- Dynamic Legend based on active filters --}}
                    <div class="flex flex-wrap items-center gap-3 text-[11px] font-semibold text-slate-600">
                        <template x-if="filters.leg !== 'DEP' && filters.leg !== 'DEPARTURE'">
                            <div class="flex items-center gap-3">
                                <template x-if="filters.traffic !== 'INTL' && filters.traffic !== 'INTERNATIONAL' && filters.traffic !== 'INT'">
                                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-xs bg-[#FCD34D] border border-[#F59E0B]"></span>Arr Dom</span>
                                </template>
                                <template x-if="filters.traffic !== 'DOM' && filters.traffic !== 'DOMESTIC'">
                                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-xs bg-[#D97706] border border-[#B45309]"></span>Arr Intl</span>
                                </template>
                            </div>
                        </template>
                        <template x-if="filters.leg !== 'ARR' && filters.leg !== 'ARRIVAL'">
                            <div class="flex items-center gap-3">
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

                <div class="h-56 w-full relative">
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
                                B. Passenger Trend
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium">
                                Clean line chart aligned to identical X-axis &amp; active filters
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-[10px] font-semibold text-slate-600">
                            <template x-if="filters.leg !== 'DEP' && filters.leg !== 'DEPARTURE'">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-1 rounded-full bg-[#F59E0B]"></span>Arr Pax</span>
                            </template>
                            <template x-if="filters.leg !== 'ARR' && filters.leg !== 'ARRIVAL'">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-1 rounded-full bg-[#2563EB]"></span>Dep Pax</span>
                            </template>
                        </div>
                    </div>
                    <div class="h-48 w-full relative">
                        <canvas id="fdrPaxTrendChart"></canvas>
                    </div>
                </div>

                {{-- C. CARGO TREND --}}
                <div class="p-4 rounded-xl border border-slate-200/90 bg-white shadow-2xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                C. Cargo Trend
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium">
                                Operational freight volume aligned to identical X-axis &amp; active filters
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-[10px] font-semibold text-slate-600">
                            <template x-if="filters.leg !== 'DEP' && filters.leg !== 'DEPARTURE'">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-1 rounded-full bg-[#10B981]"></span>Arr Cargo</span>
                            </template>
                            <template x-if="filters.leg !== 'ARR' && filters.leg !== 'ARRIVAL'">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-1 rounded-full bg-[#059669]"></span>Dep Cargo</span>
                            </template>
                        </div>
                    </div>
                    <div class="h-48 w-full relative">
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
                        <input type="text" x-model.debounce.300ms="filters.search" @input="triggerFilter()"
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
                        <option value="50">50 / page</option>
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
                            <th class="py-2.5 px-3">Stand</th>
                            <th class="py-2.5 px-3">Runway</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                        <template x-for="(r, idx) in flightRecords" :key="r.index || idx">
                            <tr @click="openFlightDetails(r)" class="hover:bg-blue-50/40 transition cursor-pointer">
                                <td class="py-2.5 px-3 text-slate-400 font-sans" x-text="((pagination.current_page - 1) * pagination.per_page) + idx + 1"></td>
                                <td class="py-2.5 px-3 font-sans font-bold text-slate-800" x-text="r.air_line"></td>
                                <td class="py-2.5 px-3 font-bold text-blue-600 hover:underline" x-text="r.flight_no"></td>
                                <td class="py-2.5 px-3 font-sans">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                          :class="r.direction === 'ARRIVAL' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'"
                                          x-text="r.direction === 'ARRIVAL' ? 'A SCHED' : 'D SCHED'"></span>
                                </td>
                                <td class="py-2.5 px-3 font-sans text-slate-700" x-text="r.route"></td>
                                <td class="py-2.5 px-3 text-slate-500 font-mono" x-text="r.sched_display || (r.direction === 'ARRIVAL' ? r.sibt : r.sobt)"></td>
                                <td class="py-2.5 px-3 font-bold font-mono"
                                    :class="r.actual_display && r.actual_display !== 'N/A' ? 'text-slate-900' : 'text-slate-400'"
                                    x-text="r.actual_display || (r.direction === 'ARRIVAL' ? r.aibt : r.aobt)"></td>
                                <td class="py-2.5 px-3 font-bold text-slate-700" x-text="r.reg_no"></td>
                                <td class="py-2.5 px-3 text-right text-slate-600" x-text="r.cap"></td>
                                <td class="py-2.5 px-3 text-right text-slate-600" x-text="r.load"></td>
                                <td class="py-2.5 px-3 text-right font-bold"
                                    :class="r.load_factor >= 85 ? 'text-emerald-600' : (r.load_factor >= 70 ? 'text-blue-600' : 'text-amber-600')"
                                    x-text="r.load_factor !== 'N/A' ? r.load_factor + '%' : 'N/A'"></td>
                                <td class="py-2.5 px-3 text-right font-sans text-slate-800 font-semibold" x-text="(r.adult + r.child + r.infant).toLocaleString()"></td>
                                <td class="py-2.5 px-3 text-right text-slate-600" x-text="r.cargo_kg ? r.cargo_kg.toLocaleString() : '0'"></td>
                                <td class="py-2.5 px-3 font-sans text-slate-700 font-semibold" x-text="r.stand"></td>
                                <td class="py-2.5 px-3 font-mono text-slate-600" x-text="r.runway"></td>
                                <td class="py-2.5 px-3 text-center font-sans">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                          :class="r.delay_minutes > 15 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'"
                                          x-text="r.delay_minutes > 15 ? 'DELAY' : 'NORM'"></span>
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
};

function fdrDashboardController() {
    return {
        uploadId: {{ $upload->id }},
        availableDates: @json($availableDates),
        sourceSummary: @json($sourceSummary),
        filters: {
            airport: '{{ $filters['airport'] }}',
            leg: '{{ in_array($filters['leg'], ['ARR','ARRIVAL']) ? 'ARR' : (in_array($filters['leg'], ['DEP','DEPARTURE']) ? 'DEP' : 'ALL') }}',
            operator: '{{ $filters['operator'] }}',
            traffic: '{{ in_array($filters['traffic'], ['DOM','DOMESTIC']) ? 'DOM' : (in_array($filters['traffic'], ['INTL','INTERNATIONAL','INT']) ? 'INTL' : 'ALL') }}',
            realization: '{{ $filters['realization'] }}',
            analysis_date: '{{ $filters['analysis_date'] }}',
            start_date: '{{ $filters['start_date'] }}',
            end_date: '{{ $filters['end_date'] }}',
            search: '{{ $filters['search'] }}',
            time_basis: '{{ $timeBasis }}',
            v: {{ time() }},
        },
        activeChips: @json($filterResult['active_chips']),
        kpis: @json($analytics['kpis']),
        schedVsReal: @json($analytics['schedule_vs_realization']),
        paxAnalytics: @json($analytics['passenger_analytics']),
        airlineRoute: @json($analytics['airline_route']),
        fleetPerformance: @json($analytics['fleet_performance'] ?? []),
        groundOps: @json($analytics['ground_operations']),
        combinedTrend: @json($analytics['combined_trend'] ?? null),
        flightRecords: @json($records),
        totalRecords: {{ $filterResult['filtered_count'] }},
        panel3Tab: 'operators',
        panel4Tab: 'stands',
        pagination: {
            current_page: 1,
            per_page: 10,
            total_pages: Math.ceil({{ $filterResult['filtered_count'] }} / 10),
            total: {{ $filterResult['filtered_count'] }}
        },
        selectedFlight: null,
        isFiltering: false,
        activeRequestId: 0,
        lastUpdatedTime: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),

        init() {
            this.$nextTick(() => {
                this.initAllCharts();
            });
        },

        initAllCharts() {
            if (!window.Chart) return;
            this.buildDelayHistChart();
            this.buildPaxDonutChart();
            this.buildPayloadDonutChart();
            this.buildFlightMovementChart();
            this.buildPaxTrendChart();
            this.buildCargoTrendChart();
        },

        buildDelayHistChart() {
            const ctx = document.getElementById('fdrDelayHistChart');
            if (!ctx) return;
            if (_fdrCharts.delayHist) {
                _fdrCharts.delayHist.destroy();
                _fdrCharts.delayHist = null;
            }

            const histData = this.schedVsReal.histogram || [];
            const labels = histData.map(b => b.label);
            const counts = histData.map(b => b.count);

            const colors = [
                '#EF4444', // <-60
                '#F97316', // -60~-31
                '#FBBF24', // -30~-16
                '#2DD4BF', // -15~-6
                '#10B981', // -5~5
                '#2DD4BF', // 6~15
                '#FBBF24', // 16~30
                '#F97316', // 31~60
                '#EF4444', // >60
            ];

            _fdrCharts.delayHist = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Flights',
                        data: counts,
                        backgroundColor: colors.slice(0, labels.length),
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
                            callbacks: {
                                title: (items) => `Variance: ${items[0].label} min`,
                                label: (item) => ` ${item.parsed.y} flights`
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10, weight: 'bold' }, color: '#64748B' }
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

            _fdrCharts.paxDonut = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Adult', 'Child', 'Infant'],
                    datasets: [{
                        data: [adult, child, infant],
                        backgroundColor: ['#2563EB', '#F59E0B', '#10B981'],
                        borderColor: '#FFFFFF',
                        borderWidth: 2,
                        hoverOffset: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1E293B',
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 10 },
                            padding: 8,
                            callbacks: {
                                label: (item) => {
                                    const val = item.parsed;
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0';
                                    return ` ${item.label}: ${val.toLocaleString()} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
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
            const total = cargo + baggage;

            _fdrCharts.payloadDonut = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Cargo', 'Baggage'],
                    datasets: [{
                        data: [cargo, baggage],
                        backgroundColor: ['#10B981', '#3B82F6'],
                        borderColor: '#FFFFFF',
                        borderWidth: 2,
                        hoverOffset: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1E293B',
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 10 },
                            padding: 8,
                            callbacks: {
                                label: (item) => {
                                    const val = item.parsed;
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0';
                                    const valFmt = val >= 1000 ? (val / 1000).toFixed(2) + ' t' : val.toLocaleString() + ' kg';
                                    return ` ${item.label}: ${valFmt} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        },

        buildFlightMovementChart() {
            const ctx = document.getElementById('fdrFlightMovementChart');
            if (!ctx) return;
            if (_fdrCharts.flightMovement) {
                _fdrCharts.flightMovement.destroy();
                _fdrCharts.flightMovement = null;
            }

            const trend = this.combinedTrend || {};
            const labels = trend.labels || [];
            const leg = (this.filters.leg || 'ALL').toUpperCase();
            const traffic = (this.filters.traffic || 'ALL').toUpperCase();

            const datasets = [];

            // Arrival Domestic: Light Amber (#FCD34D)
            const showArrDom = (leg !== 'DEP' && leg !== 'DEPARTURE') && (traffic !== 'INTL' && traffic !== 'INTERNATIONAL' && traffic !== 'INT');
            // Arrival International: Dark Amber (#D97706)
            const showArrInt = (leg !== 'DEP' && leg !== 'DEPARTURE') && (traffic !== 'DOM' && traffic !== 'DOMESTIC');
            // Departure Domestic: Light Blue (#93C5FD)
            const showDepDom = (leg !== 'ARR' && leg !== 'ARRIVAL') && (traffic !== 'INTL' && traffic !== 'INTERNATIONAL' && traffic !== 'INT');
            // Departure International: Dark Blue (#1E40AF)
            const showDepInt = (leg !== 'ARR' && leg !== 'ARRIVAL') && (traffic !== 'DOM' && traffic !== 'DOMESTIC');

            const arrDomData = trend.arr_dom_flights || [];
            const arrIntData = trend.arr_int_flights || [];
            const depDomData = trend.dep_dom_flights || [];
            const depIntData = trend.dep_int_flights || [];

            if (showArrDom) {
                datasets.push({
                    label: 'Arrival Domestic',
                    data: arrDomData,
                    backgroundColor: '#FCD34D',
                    borderColor: '#F59E0B',
                    borderWidth: 1,
                    borderRadius: 3,
                    categoryPercentage: 0.8,
                    barPercentage: 0.9,
                });
            }
            if (showArrInt) {
                datasets.push({
                    label: 'Arrival International',
                    data: arrIntData,
                    backgroundColor: '#D97706',
                    borderColor: '#B45309',
                    borderWidth: 1,
                    borderRadius: 3,
                    categoryPercentage: 0.8,
                    barPercentage: 0.9,
                });
            }
            if (showDepDom) {
                datasets.push({
                    label: 'Departure Domestic',
                    data: depDomData,
                    backgroundColor: '#93C5FD',
                    borderColor: '#3B82F6',
                    borderWidth: 1,
                    borderRadius: 3,
                    categoryPercentage: 0.8,
                    barPercentage: 0.9,
                });
            }
            if (showDepInt) {
                datasets.push({
                    label: 'Departure International',
                    data: depIntData,
                    backgroundColor: '#1E40AF',
                    borderColor: '#172554',
                    borderWidth: 1,
                    borderRadius: 3,
                    categoryPercentage: 0.8,
                    barPercentage: 0.9,
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
                            backgroundColor: '#1E293B',
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 10 },
                            padding: 8,
                            callbacks: {
                                title: (items) => {
                                    const idx = items[0].dataIndex;
                                    return trend.time_ranges ? trend.time_ranges[idx] : items[0].label;
                                },
                                label: (item) => ` ${item.dataset.label}: ${item.parsed.y} flights`,
                                afterBody: (items) => {
                                    const sum = items.reduce((acc, it) => acc + (it.parsed.y || 0), 0);
                                    return `Total: ${sum} flights`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10, weight: '600' }, color: '#64748B' }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Flights (Movements)', font: { size: 10, weight: 'bold' }, color: '#64748B' },
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
                    backgroundColor: 'rgba(245, 158, 11, 0.08)',
                    borderWidth: 2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    fill: leg === 'ARR' || leg === 'ARRIVAL',
                    tension: 0.25,
                });
            }

            if (leg !== 'ARR' && leg !== 'ARRIVAL') {
                datasets.push({
                    label: 'Departure Pax',
                    data: trend.dep_passengers || [],
                    borderColor: '#2563EB',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    borderWidth: 2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    fill: leg === 'DEP' || leg === 'DEPARTURE',
                    tension: 0.25,
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
                            backgroundColor: '#1E293B',
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 10 },
                            padding: 8,
                            callbacks: {
                                title: (items) => {
                                    const idx = items[0].dataIndex;
                                    return trend.time_ranges ? trend.time_ranges[idx] : items[0].label;
                                },
                                label: (item) => ` ${item.dataset.label}: ${item.parsed.y.toLocaleString()} Pax`
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

            // Format as Ton if magnitude is >= 1000 kg
            const totalKg = trend.total_cargo_kg || 0;
            const useTon = totalKg >= 1000;

            const arrData = useTon ? (trend.arr_cargo_ton || []) : (trend.arr_cargo_kg || []);
            const depData = useTon ? (trend.dep_cargo_ton || []) : (trend.dep_cargo_kg || []);

            const datasets = [];

            if (leg !== 'DEP' && leg !== 'DEPARTURE') {
                datasets.push({
                    label: 'Arrival Cargo',
                    data: arrData,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.08)',
                    borderWidth: 2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    fill: leg === 'ARR' || leg === 'ARRIVAL',
                    tension: 0.25,
                });
            }

            if (leg !== 'ARR' && leg !== 'ARRIVAL') {
                datasets.push({
                    label: 'Departure Cargo',
                    data: depData,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.08)',
                    borderWidth: 2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    fill: leg === 'DEP' || leg === 'DEPARTURE',
                    tension: 0.25,
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
                            backgroundColor: '#1E293B',
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 10 },
                            padding: 8,
                            callbacks: {
                                title: (items) => {
                                    const idx = items[0].dataIndex;
                                    return trend.time_ranges ? trend.time_ranges[idx] : items[0].label;
                                },
                                label: (item) => {
                                    const val = item.parsed.y;
                                    if (useTon) {
                                        const kg = Math.round(val * 1000);
                                        return ` ${item.dataset.label}: ${val.toFixed(2)} Ton (${kg.toLocaleString()} kg)`;
                                    }
                                    return ` ${item.dataset.label}: ${val.toLocaleString()} kg`;
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
                            title: { display: true, text: useTon ? 'Cargo (Ton)' : 'Cargo (kg)', font: { size: 10, weight: 'bold' }, color: '#059669' },
                            ticks: { font: { size: 9 }, color: '#059669' },
                            grid: { color: '#F1F5F9' }
                        }
                    }
                }
            });
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

            return `${legLbl} • ${trafLbl}`;
        },

        formatPayloadWeight(val) {
            if (!val || val === 0) return '0 kg';
            if (val >= 1000) return (val / 1000).toFixed(1) + ' t';
            return Math.round(val).toLocaleString() + ' kg';
        },

        triggerFilter(page = 1) {
            this.isFiltering = true;
            const reqId = ++this.activeRequestId;
            this.filters.v = Date.now();

            const params = new URLSearchParams({
                airport: this.filters.airport,
                leg: this.filters.leg,
                operator: this.filters.operator,
                traffic: this.filters.traffic,
                realization: this.filters.realization,
                analysis_date: this.filters.analysis_date,
                search: this.filters.search,
                page: page,
                per_page: this.pagination.per_page,
                time_basis: this.filters.time_basis,
                v: this.filters.v,
            });

            fetch(`/fdr/${this.uploadId}/filter?` + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (reqId !== this.activeRequestId) return; // Discard stale response
                this.isFiltering = false;
                this.kpis = data.kpis;
                this.schedVsReal = data.sched_vs_real;
                this.paxAnalytics = data.pax_analytics;
                this.airlineRoute = data.airline_route;
                this.fleetPerformance = data.fleet_performance || [];
                this.groundOps = data.ground_ops;
                this.combinedTrend = data.combined_trend;
                this.activeChips = data.active_chips || [];
                this.flightRecords = data.records || [];
                this.totalRecords = data.filtered_count ?? (data.pagination ? data.pagination.total : 0);
                this.pagination.current_page = data.pagination ? data.pagination.current_page : page;
                this.pagination.total_pages = data.pagination ? data.pagination.total_pages : 1;
                this.lastUpdatedTime = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

                this.$nextTick(() => {
                    this.initAllCharts();
                });
            })
            .catch(err => {
                this.isFiltering = false;
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

        removeChip(key) {
            if (key === 'airport') this.filters.airport = 'ALL';
            if (key === 'leg') this.filters.leg = 'ALL';
            if (key === 'operator') this.filters.operator = 'ALL';
            if (key === 'traffic') this.filters.traffic = 'ALL';
            if (key === 'realization') this.filters.realization = 'ALL';
            if (key === 'search') this.filters.search = '';
            if (key === 'flight_no') this.filters.search = '';
            this.triggerFilter(1);
        },

        clearAllFilters() {
            this.filters.airport = 'CGK';
            this.filters.leg = 'ALL';
            this.filters.operator = 'ALL';
            this.filters.traffic = 'ALL';
            this.filters.realization = 'ALL';
            this.filters.search = '';
            this.triggerFilter(1);
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

        getExportUrl(type) {
            const params = new URLSearchParams({
                airport: this.filters.airport,
                leg: this.filters.leg,
                operator: this.filters.operator,
                traffic: this.filters.traffic,
                realization: this.filters.realization,
                analysis_date: this.filters.analysis_date,
                search: this.filters.search,
                time_basis: this.filters.time_basis,
            });
            return `/fdr/${this.uploadId}/export/${type}?` + params.toString();
        }
    };
}
</script>
@endpush
@endsection
