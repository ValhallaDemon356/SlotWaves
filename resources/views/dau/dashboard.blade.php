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
                    {{ strtoupper($conf['title'] ?? 'Data Angkutan Udara') }}
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
                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-aviation-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white">Filter &amp; Analytics Controls</span>
                    </div>
                    {{-- Filter state indicator & Record counter --}}
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider"
                          :class="hasActiveFilters ? 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700' : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700'"
                          x-text="hasActiveFilters ? 'FILTERED VIEW' : 'ALL DATA'"></span>
                    <span class="text-xs text-slate-500 font-mono">
                        Showing <strong class="text-slate-800 dark:text-slate-200" x-text="formatNumber(filteredRecords.length)"></strong>
                        of <span x-text="formatNumber(allRecords.length)"></span> records
                    </span>
                </div>

                @if (in_array($reportType, ['DAU1', 'DAU2', 'DAU3', 'DAU4', 'DAU4A', 'DAU10', 'DAU10A', 'DAU10B', 'DAU11', 'DAU12']))
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
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 text-xs font-sans">
                @if (in_array($reportType, ['DAU1', 'DAU2', 'DAU4', 'DAU4A', 'DAU4B', 'DAU5', 'DAU5C', 'DAU6', 'DAU10A', 'DAU10B', 'DAU11', 'DAU12']))
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Metric</label>
                        @if ($reportType === 'DAU1')
                            <div class="inline-flex w-full p-0.5 rounded-lg bg-slate-100 dark:bg-navy-900 border border-slate-200 dark:border-slate-700">
                                <button type="button" @click="selectedMetric = 'aircraft'; applyFilters();"
                                        :class="selectedMetric === 'aircraft' ? 'bg-aviation-600 text-white shadow-xs font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 font-bold'"
                                        class="flex-1 py-1 px-1 rounded-md text-[11px] transition cursor-pointer text-center">
                                    PESAWAT
                                </button>
                                <button type="button" @click="selectedMetric = 'passenger'; applyFilters();"
                                        :class="selectedMetric === 'passenger' ? 'bg-emerald-600 text-white shadow-xs font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 font-bold'"
                                        class="flex-1 py-1 px-1 rounded-md text-[11px] transition cursor-pointer text-center">
                                    PENUMPANG
                                </button>
                            </div>
                        @else
                            <select x-model="selectedMetric" @change="applyFilters()"
                                    :class="selectedMetric !== 'aircraft' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                    class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                                <option value="aircraft">Pesawat (Aircraft)</option>
                                <option value="passenger">Penumpang (Passenger)</option>
                                @if (in_array($reportType, ['DAU2', 'DAU5', 'DAU5C', 'DAU6']))
                                    <option value="baggage">Bagasi (Baggage)</option>
                                    <option value="cargo">Kargo (Cargo)</option>
                                    <option value="pos">POS / Surat (Mail)</option>
                                @endif
                                @if (in_array($reportType, ['DAU10A']))
                                    <option value="crew">Awak Pesawat (Crew)</option>
                                @endif
                            </select>
                        @endif
                    </div>
                @endif

                @if ($reportType === 'DAU1')
                    <div x-show="selectedMetric === 'passenger'" x-transition>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">PASSENGER TYPE</label>
                        <select x-model="filterPassengerType" @change="applyFilters()"
                                :class="filterPassengerType !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL (Dewasa, Anak, Bayi)</option>
                            <option value="ADULT">Dewasa (Adult)</option>
                            <option value="CHILD">Anak (Child)</option>
                            <option value="INFANT">Bayi (Infant)</option>
                        </select>
                    </div>
                @endif

                @if ($reportType === 'DAU2')
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Display Mode</label>
                        <select x-model="displayMode" @change="applyFilters()"
                                :class="displayMode !== 'absolute' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="absolute">Nilai Riil (Absolute)</option>
                            <option value="percentage">Persentase (Percentage %)</option>
                        </select>
                    </div>
                @endif

                @if (in_array($reportType, ['DAU1', 'DAU3', 'DAU4', 'DAU5', 'DAU5A', 'DAU5C', 'DAU6', 'DAU10', 'DAU10B', 'DAU11', 'DAU12']))
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Direction</label>
                        <select x-model="filterDirection" @change="applyFilters()"
                                :class="filterDirection !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL (ARR &amp; DEP)</option>
                            <option value="ARRIVAL">ARRIVAL (Kedatangan)</option>
                            <option value="DEPARTURE">DEPARTURE (Keberangkatan)</option>
                        </select>
                    </div>
                @endif

                @if (in_array($reportType, ['DAU5B', 'DAU10', 'DAU10A', 'DAU10B']) && !empty($terminals))
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Terminal</label>
                        <select x-model="filterTerminal" @change="applyFilters()"
                                :class="filterTerminal !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL TERMINALS</option>
                            @foreach ($terminals as $t)
                                <option value="{{ $t }}">Terminal {{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (in_array($reportType, ['DAU1', 'DAU4A', 'DAU4B', 'DAU5', 'DAU5A', 'DAU5B', 'DAU5C']) && !empty($airlines))
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Airline / Operator</label>
                        <select x-model="filterAirline" @change="applyFilters()"
                                :class="filterAirline !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL AIRLINES</option>
                            @foreach ($airlines as $al)
                                <option value="{{ $al }}">{{ $al }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (in_array($reportType, ['DAU1', 'DAU4', 'DAU4A', 'DAU4B']) && !empty($airports))
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Airport / Rute</label>
                        <select x-model="filterAirport" @change="applyFilters()"
                                :class="filterAirport !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL AIRPORTS</option>
                            @foreach ($airports as $ap)
                                <option value="{{ $ap }}">{{ $ap }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (in_array($reportType, ['DAU1', 'DAU5C', 'DAU6']) && !empty($aircraftTypes))
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Aircraft Type</label>
                        <select x-model="filterAircraftType" @change="applyFilters()"
                                :class="filterAircraftType !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL TYPES</option>
                            @foreach ($aircraftTypes as $at)
                                <option value="{{ $at }}">{{ $at }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($reportType === 'DAU4')
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Top N Routes</label>
                        <select x-model="filterTopN" @change="applyFilters()"
                                :class="filterTopN !== '10' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="5">TOP 5</option>
                            <option value="10">TOP 10</option>
                            <option value="20">TOP 20</option>
                            <option value="ALL">ALL ROUTES</option>
                        </select>
                    </div>
                @endif

                @if ($reportType === 'DAU4B')
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Min. Flights</label>
                        <select x-model="filterThreshold" @change="applyFilters()"
                                :class="filterThreshold !== 0 ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="0">&ge; 0 Flights (All)</option>
                            <option value="1">&ge; 1 Flight</option>
                            <option value="3">&ge; 3 Flights</option>
                            <option value="5">&ge; 5 Flights</option>
                            <option value="10">&ge; 10 Flights</option>
                        </select>
                    </div>
                @endif

                @if (in_array($reportType, ['DAU10', 'DAU10A', 'DAU10B']) && !empty($hours))
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Hour</label>
                        <select x-model="filterHour" @change="applyFilters()"
                                :class="filterHour !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-mono text-xs focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL 24 HOURS</option>
                            @foreach ($hours as $h)
                                <option value="{{ $h }}">{{ $h }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($reportType === 'DAU10B')
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Operation</label>
                        <select x-model="filterOperation" @change="applyFilters()"
                                :class="filterOperation !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL (BLOCK ON &amp; OFF)</option>
                            <option value="BLOCK_ON">BLOCK ON (DTG - Inbound)</option>
                            <option value="BLOCK_OFF">BLOCK OFF (BRK - Outbound)</option>
                        </select>
                    </div>
                @endif

                @if ($reportType === 'DAU1')
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Jadwal</label>
                        <select x-model="filterScheduleType" @change="applyFilters()"
                                :class="filterScheduleType !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL STATUS</option>
                            <option value="BERJADWAL">Berjadwal</option>
                            <option value="TIDAK">Tdk Berjadwal</option>
                        </select>
                    </div>
                @endif

                @if ($reportType === 'DAU3')
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Status Usaha</label>
                        <select x-model="filterStatus" @change="applyFilters()"
                                :class="filterStatus !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL STATUS</option>
                            <option value="NIAGA">Niaga</option>
                            <option value="BUKAN NIAGA">Bukan Niaga</option>
                        </select>
                    </div>
                @endif

                @if ($reportType === 'DAU6')
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">WTC</label>
                        <select x-model="filterWtc" @change="applyFilters()"
                                :class="filterWtc !== 'ALL' ? 'ring-2 ring-aviation-500 border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/30' : 'border-slate-200 dark:border-slate-700'"
                                class="w-full px-2.5 py-1.5 rounded-lg border bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL WTC</option>
                            <option value="Medium">Medium (M)</option>
                            <option value="Heavy">Heavy (H)</option>
                            <option value="Light">Light (L)</option>
                        </select>
                    </div>
                @endif

                <div class="col-span-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Search Table Data</label>
                    <div class="relative">
                        <input type="text" x-model="searchQuery" @input="applyFilters()"
                               placeholder="Cari rute, airline, tipe pesawat, kode..."
                               class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-1 focus:ring-aviation-500">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                <div class="flex items-end">
                    <button type="button" @click="resetFilters()"
                            class="w-full py-1.5 px-3 rounded-lg text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-navy-800 dark:hover:bg-navy-700 border border-slate-200 dark:border-slate-700 transition cursor-pointer flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Reset</span>
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800/80 text-[11px] font-mono">
                <span class="text-[10px] uppercase font-bold text-slate-400">ACTIVE FILTERS:</span>

                <template x-if="!hasActiveFilters">
                    <span class="text-slate-400 italic">All Data (No active filters)</span>
                </template>

                <template x-if="selectedMetric !== 'aircraft'">
                    <span class="px-2 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-300 font-bold flex items-center gap-1">
                        Metric: <span class="uppercase font-mono" x-text="selectedMetric"></span>
                        <button type="button" @click="selectedMetric = 'aircraft'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterFlightType !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 font-bold flex items-center gap-1">
                        Scope: <span x-text="filterFlightType"></span>
                        <button type="button" @click="setFlightType('ALL')" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterTerminal !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-slate-900 text-white dark:bg-white dark:text-slate-900 font-bold flex items-center gap-1">
                        Terminal: <span x-text="filterTerminal"></span>
                        <button type="button" @click="setTerminal('ALL')" class="hover:text-red-400">&times;</button>
                    </span>
                </template>

                <template x-if="filterAirline !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-200 font-bold flex items-center gap-1">
                        Airline: <span x-text="filterAirline"></span>
                        <button type="button" @click="filterAirline = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterAirport !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 font-bold flex items-center gap-1">
                        Airport: <span x-text="filterAirport"></span>
                        <button type="button" @click="filterAirport = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterAircraftType !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-purple-50 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border border-purple-200 font-bold flex items-center gap-1">
                        Aircraft: <span x-text="filterAircraftType"></span>
                        <button type="button" @click="filterAircraftType = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterHour !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-200 font-bold flex items-center gap-1">
                        Hour: <span x-text="filterHour"></span>
                        <button type="button" @click="setHourFilter('ALL')" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterDirection !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 font-bold flex items-center gap-1">
                        Direction: <span x-text="filterDirection"></span>
                        <button type="button" @click="filterDirection = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterOperation !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-purple-50 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border border-purple-200 font-bold flex items-center gap-1">
                        Operation: <span x-text="filterOperation"></span>
                        <button type="button" @click="filterOperation = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterScheduleType !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 font-bold flex items-center gap-1">
                        Jadwal: <span x-text="filterScheduleType"></span>
                        <button type="button" @click="filterScheduleType = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterStatus !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-teal-50 dark:bg-teal-950 text-teal-700 dark:text-teal-300 border border-teal-200 font-bold flex items-center gap-1">
                        Status: <span x-text="filterStatus"></span>
                        <button type="button" @click="filterStatus = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterCategory !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-sky-50 dark:bg-sky-950 text-sky-700 dark:text-sky-300 border border-sky-200 font-bold flex items-center gap-1">
                        Body: <span x-text="filterCategory"></span>
                        <button type="button" @click="filterCategory = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="filterWtc !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-orange-50 dark:bg-orange-950 text-orange-700 dark:text-orange-300 border border-orange-200 font-bold flex items-center gap-1">
                        WTC: <span x-text="filterWtc"></span>
                        <button type="button" @click="filterWtc = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="reportType === 'DAU1' && filterPassengerType !== 'ALL'">
                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 font-bold flex items-center gap-1">
                        Pax Type: <span x-text="filterPassengerType"></span>
                        <button type="button" @click="filterPassengerType = 'ALL'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="reportType === 'DAU2' && displayMode === 'percentage'">
                    <span class="px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 font-bold flex items-center gap-1">
                        Mode: <span>Percentage (%)</span>
                        <button type="button" @click="displayMode = 'absolute'; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="searchQuery !== ''">
                    <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300 border border-slate-200 font-bold flex items-center gap-1">
                        Search: "<span x-text="searchQuery"></span>"
                        <button type="button" @click="searchQuery = ''; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <template x-if="hasActiveFilters">
                    <button type="button" @click="resetFilters()" class="text-red-500 hover:text-red-700 underline font-bold ml-auto cursor-pointer">
                        Clear All
                    </button>
                </template>
            </div>
        </div>

        {{-- ══ 3. KPI SUMMARY SECTION ═════════════════════════════════════════ --}}
        @if ($reportType === 'DAU1')
            {{-- Directional Balance Congestion Alert Banner (Auto-triggers if Inbound > 70%) --}}
            <template x-if="dau1Ratios && dau1Ratios.isInboundHeavy">
                <div class="rounded-xl border border-amber-400/70 dark:border-amber-500/50 bg-amber-500/10 dark:bg-amber-950/40 p-3.5 sm:p-4 backdrop-blur-md flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-amber-900 dark:text-amber-200 shadow-sm animate-pulse mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-sm font-black text-lg">
                            ⚠️
                        </div>
                        <div>
                            <div class="font-black text-xs sm:text-sm tracking-wide uppercase flex items-center gap-2">
                                <span>Inbound Heavy — Apron Congestion Alert</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-600 text-white shadow-2xs" x-text="dau1Ratios.inboundRatio + '% Inbound'"></span>
                            </div>
                            <p class="text-[11px] sm:text-xs text-amber-800 dark:text-amber-300 mt-0.5 font-medium">
                                Inbound arrival traffic represents over 70% of total movements. High risk of apron stand occupancy bottlenecks, gate dwell time inflation, and ground handling congestion.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 self-end sm:self-center font-mono text-xs">
                        <div class="text-right">
                            <div class="text-[10px] uppercase font-bold text-amber-600 dark:text-amber-400">Directional Split</div>
                            <div class="font-black text-slate-900 dark:text-white" x-text="formatNumber(dau1Ratios.arrMovements) + ' ARR / ' + formatNumber(dau1Ratios.depMovements) + ' DEP'"></div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- DAU-01 Operational Intelligence Derived Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-3 sm:mb-4">
                {{-- CARD 1: PAX PER FLIGHT RATIO --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-cyan-500 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pax per Flight Ratio</div>
                        <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-800 font-mono">Load Density</span>
                    </div>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl sm:text-3xl font-black text-cyan-600 dark:text-cyan-400 font-mono" x-text="formatNumber(dau1Ratios.paxPerFlight)">
                            {{ number_format($dau1Ratios['pax_per_flight'] ?? 0, 1) }}
                        </span>
                        <span class="text-xs font-bold text-slate-500 font-mono">Pax / Flight</span>
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1.5 flex items-center justify-between font-mono">
                        <span>Total Pax: <strong class="text-slate-700 dark:text-slate-200" x-text="formatNumber(activeSummary.passenger_total)">{{ number_format($summary['passenger_total'] ?? 0) }}</strong></span>
                        <span>Mov: <strong class="text-slate-700 dark:text-slate-200" x-text="formatNumber(activeSummary.total_movements)">{{ number_format($summary['total_movements'] ?? 0) }}</strong></span>
                    </div>
                </div>

                {{-- CARD 2: BAGGAGE LOAD PER PAX --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-blue-600 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Baggage Load per Pax</div>
                        <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 font-mono">Checked Load</span>
                    </div>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl sm:text-3xl font-black text-blue-600 dark:text-blue-400 font-mono" x-text="formatNumber(dau1Ratios.baggagePerPax)">
                            {{ number_format($dau1Ratios['baggage_per_pax'] ?? 0, 1) }}
                        </span>
                        <span class="text-xs font-bold text-slate-500 font-mono">Kg / Pax</span>
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1.5 flex items-center justify-between font-mono">
                        <span>Total Bag: <strong class="text-slate-700 dark:text-slate-200" x-text="formatNumber(activeSummary.baggage_total) + ' kg'">{{ number_format($summary['baggage_total'] ?? 0) }} kg</strong></span>
                        <span x-text="dau1Ratios.paxPerFlight > 0 ? (Math.round(dau1Ratios.baggagePerPax * dau1Ratios.paxPerFlight) + ' kg/flt') : '—'"></span>
                    </div>
                </div>

                {{-- CARD 3: CARGO TO FLIGHT DENSITY --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-teal-600 relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cargo to Flight Density</div>
                        <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 border border-teal-200 dark:border-teal-800 font-mono">Freight Density</span>
                    </div>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl sm:text-3xl font-black text-teal-600 dark:text-teal-400 font-mono" x-text="formatNumber(dau1Ratios.cargoDensityTon)">
                            {{ number_format($dau1Ratios['cargo_density_ton'] ?? 0, 2) }}
                        </span>
                        <span class="text-xs font-bold text-slate-500 font-mono">Tons / Flight</span>
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1.5 flex items-center justify-between font-mono">
                        <span>Total Cargo: <strong class="text-slate-700 dark:text-slate-200" x-text="formatNumber(activeSummary.cargo_total) + ' kg'">{{ number_format($summary['cargo_total'] ?? 0) }} kg</strong></span>
                        <span x-text="formatNumber(dau1Ratios.cargoDensityKg) + ' kg/flt'"></span>
                    </div>
                </div>

                {{-- CARD 4: DIRECTIONAL BALANCE GAUGE & SPLIT --}}
                <div class="glass-card p-4 shadow-sm border-t-2 relative overflow-hidden"
                     :class="dau1Ratios.isInboundHeavy ? 'border-t-amber-500' : 'border-t-indigo-600'">
                    <div class="flex items-center justify-between">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Directional Balance</div>
                        <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded"
                              :class="dau1Ratios.isInboundHeavy ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-black' : 'bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 font-mono'"
                              x-text="dau1Ratios.directionalStatus">
                            {{ $dau1Ratios['directional_status'] ?? 'BALANCED' }}
                        </span>
                    </div>
                    <div class="mt-2 space-y-1.5">
                        <div class="flex items-center justify-between text-xs font-mono font-bold">
                            <span class="text-indigo-600 dark:text-indigo-400" x-text="'ARR ' + dau1Ratios.inboundRatio + '%'">ARR {{ $dau1Ratios['inbound_ratio'] ?? 50 }}%</span>
                            <span class="text-blue-600 dark:text-blue-400" x-text="'DEP ' + dau1Ratios.outboundRatio + '%'">DEP {{ $dau1Ratios['outbound_ratio'] ?? 50 }}%</span>
                        </div>
                        <div class="w-full h-2.5 rounded-full overflow-hidden bg-slate-100 dark:bg-navy-800 flex shadow-inner">
                            <div class="h-full bg-indigo-600 transition-all duration-300" :style="'width: ' + dau1Ratios.inboundRatio + '%'"></div>
                            <div class="h-full bg-blue-500 transition-all duration-300" :style="'width: ' + dau1Ratios.outboundRatio + '%'"></div>
                        </div>
                        <div class="text-[10px] text-slate-500 flex items-center justify-between font-mono">
                            <span x-text="formatNumber(dau1Ratios.arrMovements) + ' Inbound'">{{ number_format($dau1Ratios['arr_movements'] ?? 0) }} Inbound</span>
                            <span x-text="formatNumber(dau1Ratios.depMovements) + ' Outbound'">{{ number_format($dau1Ratios['dep_movements'] ?? 0) }} Outbound</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- DAU-01 PASSENGER MODE KPI CARDS (6 CARDS) --}}
            <div x-show="selectedMetric === 'passenger'" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
                {{-- 1. TOTAL PASSENGERS --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-emerald-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Passengers</div>
                    <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1" x-text="formatNumber(activeSummary.passenger_total)">
                        {{ number_format($summary['passenger_total'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        Arr: <span x-text="formatNumber(activeSummary.passenger_arrival)"></span> | Dep: <span x-text="formatNumber(activeSummary.passenger_departure)"></span>
                    </div>
                </div>

                {{-- 2. ADULT --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-blue-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Dewasa (Adult)</div>
                    <div class="text-xl sm:text-2xl font-black text-blue-600 dark:text-blue-400 mt-1" x-text="formatNumber(activeSummary.passenger_adult)">
                        {{ number_format($summary['passenger_adult'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        Arr: <span x-text="formatNumber(activeSummary.arr_adult)"></span> | Dep: <span x-text="formatNumber(activeSummary.dep_adult)"></span>
                    </div>
                </div>

                {{-- 3. CHILD --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-teal-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Anak (Child)</div>
                    <div class="text-xl sm:text-2xl font-black text-teal-600 dark:text-teal-400 mt-1" x-text="formatNumber(activeSummary.passenger_child)">
                        {{ number_format($summary['passenger_child'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        Arr: <span x-text="formatNumber(activeSummary.arr_child)"></span> | Dep: <span x-text="formatNumber(activeSummary.dep_child)"></span>
                    </div>
                </div>

                {{-- 4. INFANT --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-amber-500">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bayi (Infant)</div>
                    <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 mt-1" x-text="formatNumber(activeSummary.passenger_infant)">
                        {{ number_format($summary['passenger_infant'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        Arr: <span x-text="formatNumber(activeSummary.arr_infant)"></span> | Dep: <span x-text="formatNumber(activeSummary.dep_infant)"></span>
                    </div>
                </div>

                {{-- 5. ARRIVAL PASSENGERS --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-indigo-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Arrival Passengers</div>
                    <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1" x-text="formatNumber(activeSummary.passenger_arrival)">
                        {{ number_format($summary['passenger_arrival'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        Transit: <span x-text="formatNumber(activeSummary.passenger_transit)"></span>
                    </div>
                </div>

                {{-- 6. DEPARTURE PASSENGERS --}}
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-purple-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Departure Passengers</div>
                    <div class="text-xl sm:text-2xl font-black text-purple-600 dark:text-purple-400 mt-1" x-text="formatNumber(activeSummary.passenger_departure)">
                        {{ number_format($summary['passenger_departure'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        Transfer: <span x-text="formatNumber(activeSummary.passenger_transfer)"></span>
                    </div>
                </div>
            </div>
        @endif

        {{-- STANDARD / AIRCRAFT KPI GRID (SHOWN FOR AIRCRAFT MODE OR OTHER DAUS) --}}
        <div @if ($reportType === 'DAU1') x-show="selectedMetric !== 'passenger'" @endif class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
            <div class="glass-card p-4 shadow-sm border-t-2 border-t-aviation-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Movements</div>
                <div class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-1" x-text="formatNumber(activeSummary.total_movements)">
                    {{ number_format($summary['total_movements'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">
                    Arr: <span x-text="formatNumber(activeSummary.aircraft_arrival)">{{ number_format($summary['aircraft_arrival'] ?? 0) }}</span> | 
                    Dep: <span x-text="formatNumber(activeSummary.aircraft_departure)">{{ number_format($summary['aircraft_departure'] ?? 0) }}</span>
                </div>
            </div>

            <div class="glass-card p-4 shadow-sm border-t-2 border-t-emerald-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Passengers</div>
                <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1" x-text="formatNumber(activeSummary.passenger_total)">
                    {{ number_format($summary['passenger_total'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">
                    Arr: <span x-text="formatNumber(activeSummary.passenger_arrival)">{{ number_format($summary['passenger_arrival'] ?? 0) }}</span> | 
                    Dep: <span x-text="formatNumber(activeSummary.passenger_departure)">{{ number_format($summary['passenger_departure'] ?? 0) }}</span>
                </div>
            </div>

            @if (in_array($reportType, ['DAU10', 'DAU10A', 'DAU10B']))
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-amber-500">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400"
                         x-text="reportType === 'DAU10B' ? (selectedMetric === 'passenger' ? 'Peak Passenger Hour' : 'Peak Aircraft Hour') : 'Peak Hour'">Peak Hour</div>
                    <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 mt-1"
                         x-text="reportType === 'DAU10B' ? (selectedMetric === 'passenger' ? (peaks.peak_passenger_hour || '—') : (peaks.peak_aircraft_hour || '—')) : peaks.peak_hour">
                        {{ $peaks['peak_hour'] ?? '—' }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono"
                         x-text="reportType === 'DAU10B' ? (selectedMetric === 'passenger' ? formatNumber(peaks.peak_passenger) + ' PAX' : formatNumber(peaks.peak_aircraft) + ' A/C') : 'Highest Traffic Period'">
                        Highest Traffic Period
                    </div>
                </div>

                <div class="glass-card p-4 shadow-sm border-t-2 border-t-purple-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Peak Terminal</div>
                    <div class="text-xl sm:text-2xl font-black text-purple-600 dark:text-purple-400 mt-1"
                         x-text="!peaks.peak_terminal || ['—', '-', 'T-', 'T—'].includes(String(peaks.peak_terminal).trim()) ? '—' : ((String(peaks.peak_terminal).toUpperCase().startsWith('T') ? '' : 'T') + peaks.peak_terminal)">
                        {{ empty($peaks['peak_terminal']) || in_array(trim($peaks['peak_terminal'] ?? ''), ['—', '-', 'T-', 'T—']) ? '—' : ((str_starts_with(strtoupper($peaks['peak_terminal']), 'T') ? '' : 'T') . $peaks['peak_terminal']) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono"
                         x-text="reportType === 'DAU10B' ? (formatNumber(peaks.peak_terminal_val) + (selectedMetric === 'passenger' ? ' PAX' : ' A/C')) : (formatNumber(peaks.peak_terminal_val) + ' mov')">
                        {{ number_format($peaks['peak_terminal_val'] ?? 0) }} mov
                    </div>
                </div>
            @elseif ($reportType === 'DAU2')
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-blue-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="'Domestic ' + (dau2ActiveMetricLabel || 'Traffic')">Domestic Traffic</div>
                    <div class="text-xl sm:text-2xl font-black text-blue-600 dark:text-blue-400 mt-1" x-text="formatNumber(dau2Metrics.domValue) + ' ' + (dau2Metrics.unit || 'A/C')">—</div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono" x-text="dau2Metrics.domSharePct + '% of total'">—</div>
                </div>

                <div class="glass-card p-4 shadow-sm border-t-2 border-t-indigo-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="'Int\'l ' + (dau2ActiveMetricLabel || 'Traffic')">International Traffic</div>
                    <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1" x-text="formatNumber(dau2Metrics.intValue) + ' ' + (dau2Metrics.unit || 'A/C')">—</div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono" x-text="dau2Metrics.intSharePct + '% of total'">—</div>
                </div>
            @elseif ($reportType === 'DAU5A')
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-blue-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Operating Crew</div>
                    <div class="text-xl sm:text-2xl font-black text-blue-600 dark:text-blue-400 mt-1" x-text="formatNumber(dau5aMetrics.operatingCrew)">—</div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">Flight Duty Crew</div>
                </div>

                <div class="glass-card p-4 shadow-sm border-t-2 border-t-purple-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Extra Crew</div>
                    <div class="text-xl sm:text-2xl font-black text-purple-600 dark:text-purple-400 mt-1" x-text="formatNumber(dau5aMetrics.extraCrew)">—</div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">
                        ARR: <span x-text="formatNumber(dau5aMetrics.arrExtraCrew)"></span> | DEP: <span x-text="formatNumber(dau5aMetrics.depExtraCrew)"></span>
                    </div>
                </div>
            @else
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-slate-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Crew</div>
                    <div class="text-xl sm:text-2xl font-black text-slate-700 dark:text-slate-300 mt-1" x-text="formatNumber(activeSummary.crew_total)">
                        {{ number_format($summary['crew_total'] ?? 0) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">Flight &amp; Cabin Crew</div>
                </div>

                <div class="glass-card p-4 shadow-sm border-t-2 border-t-cyan-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Transit &amp; Transfer</div>
                    <div class="text-xl sm:text-2xl font-black text-cyan-600 dark:text-cyan-400 mt-1" x-text="formatNumber(activeSummary.passenger_transit + activeSummary.passenger_transfer)">
                        {{ number_format(($summary['passenger_transit'] ?? 0) + ($summary['passenger_transfer'] ?? 0)) }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">Transit: <span x-text="formatNumber(activeSummary.passenger_transit)"></span></div>
                </div>
            @endif

            <div class="glass-card p-4 shadow-sm border-t-2 border-t-rose-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Baggage (Kg)</div>
                <div class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400 mt-1" x-text="formatNumber(activeSummary.baggage_total)">
                    {{ number_format($summary['baggage_total'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">Gross Luggage</div>
            </div>

            <div class="glass-card p-4 shadow-sm border-t-2 border-t-teal-600">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cargo (Kg)</div>
                <div class="text-xl sm:text-2xl font-black text-teal-600 dark:text-teal-400 mt-1" x-text="formatNumber(activeSummary.cargo_total)">
                    {{ number_format($summary['cargo_total'] ?? 0) }}
                </div>
                <div class="text-[10px] text-slate-500 mt-1 font-mono">Freight Cargo</div>
            </div>
        </div>

        {{-- ══ 3B. DAU-02 COMPARATIVE BREAKDOWN TABLE MATRIX ════════════════════ --}}
        @if ($reportType === 'DAU2')
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4 border-t-2 border-t-aviation-600">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Perbandingan Komparatif Domestik vs Internasional</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">COMPARATIVE BREAKDOWN</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-500">Tampilan Nilai:</span>
                        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-0.5 bg-slate-100 dark:bg-navy-800">
                            <button type="button" @click="displayMode = 'absolute'; updateCharts();"
                                    :class="displayMode === 'absolute' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700'"
                                    class="px-2.5 py-1 text-xs rounded-md font-bold transition cursor-pointer">ABSOLUTE</button>
                            <button type="button" @click="displayMode = 'percentage'; updateCharts();"
                                    :class="displayMode === 'percentage' ? 'bg-white dark:bg-navy-900 text-aviation-600 dark:text-aviation-400 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-700'"
                                    class="px-2.5 py-1 text-xs rounded-md font-bold transition cursor-pointer">PERCENTAGE (%)</button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                    <table class="w-full text-xs font-sans border-collapse">
                        <thead class="bg-slate-50 dark:bg-navy-900 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="px-4 py-3 text-left">Metrik Operasional</th>
                                <th class="px-4 py-3 text-right text-blue-600 font-bold">DOMESTIK</th>
                                <th class="px-4 py-3 text-right text-indigo-600 font-bold">INTERNASIONAL</th>
                                <th class="px-4 py-3 text-right font-black text-slate-900 dark:text-white">TOTAL</th>
                                <th class="px-4 py-3 text-right text-slate-500 font-bold">Proporsi Dom / Int</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono text-xs">
                            <template x-for="item in dau2ComparativeList" :key="item.key">
                                <tr :class="selectedMetric === item.key ? 'bg-aviation-50/60 dark:bg-aviation-950/40 font-bold' : 'hover:bg-slate-50/50 dark:hover:bg-navy-800/40'" class="transition cursor-pointer" @click="selectedMetric = item.key; applyFilters();">
                                    <td class="px-4 py-2.5 font-sans flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full" :class="selectedMetric === item.key ? 'bg-aviation-600' : 'bg-slate-300 dark:bg-slate-600'"></span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="item.label"></span>
                                        <span class="text-[10px] text-slate-400 font-mono" x-text="'(' + item.unit + ')'"></span>
                                        <template x-if="selectedMetric === item.key">
                                            <span class="text-[9px] uppercase px-1.5 py-0.5 rounded bg-aviation-600 text-white font-sans font-black">ACTIVE</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-blue-600 font-bold">
                                        <span x-text="displayMode === 'percentage' ? (item.dom_share_str) : formatNumber(item.domestic)"></span>
                                        <template x-if="displayMode === 'absolute'">
                                            <span class="text-[10px] text-slate-400 font-normal ml-1" x-text="'(' + item.dom_share_str + ')'"></span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-indigo-600 font-bold">
                                        <span x-text="displayMode === 'percentage' ? (item.int_share_str) : formatNumber(item.international)"></span>
                                        <template x-if="displayMode === 'absolute'">
                                            <span class="text-[10px] text-slate-400 font-normal ml-1" x-text="'(' + item.int_share_str + ')'"></span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-black text-slate-900 dark:text-white">
                                        <span x-text="displayMode === 'percentage' ? '100.0%' : formatNumber(item.total)"></span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <div class="w-32 ml-auto flex h-2 rounded-full overflow-hidden bg-slate-200 dark:bg-navy-700">
                                            <div :style="'width: ' + item.dom_pct + '%'" class="bg-blue-600"></div>
                                            <div :style="'width: ' + item.int_pct + '%'" class="bg-indigo-600"></div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ══ 4. REPORT-SPECIFIC ANALYTICAL CHARTS & DIAGRAMS ═════════════════ --}}

        {{-- DAU-1: ARUS LALU LINTAS --}}
        @if ($reportType === 'DAU1')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400"
                                 x-text="selectedMetric === 'passenger' ? 'Passenger Breakdown by Route' : 'Top Routes Analysis'">Top Routes Analysis</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white"
                                x-text="selectedMetric === 'passenger' ? 'TOP ROUTES PASSENGER BREAKDOWN (ADULT • CHILD • INFANT)' : 'TOP 10 ORIGIN / DESTINATION ROUTES'">
                                TOP 10 ORIGIN / DESTINATION ROUTES
                            </h2>
                        </div>
                        <template x-if="selectedMetric === 'passenger'">
                            <div class="flex items-center gap-3 text-xs font-mono">
                                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> Dewasa</span>
                                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-500"></span> Anak</span>
                                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> Bayi</span>
                            </div>
                        </template>
                        <template x-if="selectedMetric !== 'passenger'">
                            <div class="flex items-center gap-3 text-xs font-mono">
                                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> ARR A/C</span>
                                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> DEP A/C</span>
                                <span class="flex items-center gap-1"><span class="w-3 h-1 rounded bg-emerald-500"></span> Pax Total</span>
                            </div>
                        </template>
                    </div>
                    <div class="relative h-72 sm:h-80 w-full">
                        <canvas id="dau1ComboChart"></canvas>
                    </div>
                    <div class="text-[11px] text-slate-400 text-center font-mono"
                         x-text="selectedMetric === 'passenger' ? 'Stacked bars: Dewasa (Adult), Anak (Child), Bayi (Infant) per Route • Derived from authentic OASYS records' : 'Grouped bars: Aircraft ARR vs DEP (Left Axis) • Connected line: Total Passenger (Right Axis)'">
                        Grouped bars: Aircraft ARR vs DEP (Left Axis) • Connected line: Total Passenger (Right Axis)
                    </div>
                </div>

                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4 flex flex-col justify-between">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400"
                             x-text="selectedMetric === 'passenger' ? 'Demographic Split' : 'Payload Distribution'">Demographic Split</div>
                        <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white"
                            x-text="selectedMetric === 'passenger' ? 'PASSENGER DEMOGRAPHIC BREAKDOWN' : 'CARGO & BAGGAGE PAYLOAD DENSITY'">
                            PASSENGER DEMOGRAPHIC BREAKDOWN
                        </h2>
                    </div>

                    {{-- Multi-segment Demographic Progress Bar (Live Adult / Child / Infant shares) --}}
                    <template x-if="selectedMetric === 'passenger'">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-mono">
                                <span class="font-bold text-blue-600 dark:text-blue-400 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                                    Dewasa <span x-text="'(' + dau1Ratios.adultPct + '%)'"></span>
                                </span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    Anak <span x-text="'(' + dau1Ratios.childPct + '%)'"></span>
                                </span>
                                <span class="font-bold text-amber-500 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    Bayi <span x-text="'(' + dau1Ratios.infantPct + '%)'"></span>
                                </span>
                            </div>
                            <div class="w-full h-3 rounded-full overflow-hidden bg-slate-100 dark:bg-navy-800 flex shadow-inner">
                                <div class="h-full bg-blue-600 transition-all duration-300" :style="'width: ' + dau1Ratios.adultPct + '%'"></div>
                                <div class="h-full bg-emerald-500 transition-all duration-300" :style="'width: ' + dau1Ratios.childPct + '%'"></div>
                                <div class="h-full bg-amber-500 transition-all duration-300" :style="'width: ' + dau1Ratios.infantPct + '%'"></div>
                            </div>
                        </div>
                    </template>

                    <div class="relative h-52 w-full flex items-center justify-center">
                        <canvas id="dau1PayloadChart"></canvas>
                    </div>

                    <template x-if="selectedMetric === 'passenger'">
                        <div class="grid grid-cols-3 gap-2 text-center text-xs font-mono pt-2 border-t border-slate-100 dark:border-slate-800">
                            <div class="p-2.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/40 border border-blue-200/80 dark:border-blue-800/80">
                                <div class="text-[9px] text-blue-600 font-bold uppercase">Dewasa (Adult)</div>
                                <div class="font-black text-blue-700 dark:text-blue-300 text-sm mt-0.5" x-text="formatNumber(activeSummary.passenger_adult)"></div>
                                <div class="text-[10px] text-blue-500 font-bold" x-text="dau1Ratios.adultPct + '%'"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/80">
                                <div class="text-[9px] text-emerald-600 font-bold uppercase">Anak (Child)</div>
                                <div class="font-black text-emerald-700 dark:text-emerald-300 text-sm mt-0.5" x-text="formatNumber(activeSummary.passenger_child)"></div>
                                <div class="text-[10px] text-emerald-500 font-bold" x-text="dau1Ratios.childPct + '%'"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/80">
                                <div class="text-[9px] text-amber-600 font-bold uppercase">Bayi (Infant)</div>
                                <div class="font-black text-amber-700 dark:text-amber-300 text-sm mt-0.5" x-text="formatNumber(activeSummary.passenger_infant)"></div>
                                <div class="text-[10px] text-amber-500 font-bold" x-text="dau1Ratios.infantPct + '%'"></div>
                            </div>
                        </div>
                    </template>
                    <template x-if="selectedMetric !== 'passenger'">
                        <div class="grid grid-cols-2 gap-2 text-center text-xs font-mono pt-2 border-t border-slate-100 dark:border-slate-800">
                            <div class="p-2.5 rounded-xl bg-rose-50/70 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-800/80">
                                <div class="text-[10px] text-rose-600 font-bold uppercase">Total Baggage</div>
                                <div class="font-black text-rose-700 dark:text-rose-300 text-sm mt-0.5" x-text="formatNumber(activeSummary.baggage_total) + ' Kg'"></div>
                                <div class="text-[10px] text-rose-500 font-bold" x-text="dau1Ratios.baggagePerPax + ' kg/pax'"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-teal-50/70 dark:bg-teal-950/40 border border-teal-200/80 dark:border-teal-800/80">
                                <div class="text-[10px] text-teal-600 font-bold uppercase">Total Cargo</div>
                                <div class="font-black text-teal-700 dark:text-teal-300 text-sm mt-0.5" x-text="formatNumber(activeSummary.cargo_total) + ' Kg'"></div>
                                <div class="text-[10px] text-teal-500 font-bold" x-text="dau1Ratios.cargoDensityTon + ' ton/flt'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        @endif

        {{-- DAU-2: SECARA TOTAL --}}
        @if ($reportType === 'DAU2')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">100% Stacked Comparison</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">DOMESTIC VS INTERNATIONAL BY METRIC</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> Domestic</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-indigo-600"></span> International</span>
                        </div>
                    </div>
                    <div class="relative h-72 sm:h-80 w-full">
                        <canvas id="dau2StackedChart"></canvas>
                    </div>
                </div>

                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4 flex flex-col justify-between">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400" x-text="'Market Share (' + (dau2ActiveMetricLabel || 'Selected') + ')'">Market Share</div>
                        <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">DOMESTIC VS INT SHARE</h2>
                    </div>
                    <div class="relative h-56 w-full flex items-center justify-center">
                        <canvas id="dau2ShareDonut"></canvas>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-center font-mono">
                        <div class="text-[10px] uppercase font-bold text-slate-400">Dominant Scope (<span x-text="dau2ActiveMetricLabel"></span>)</div>
                        <div class="text-base font-black text-slate-900 dark:text-white mt-0.5">
                            <span x-text="dau2Metrics.domSharePct >= dau2Metrics.intSharePct ? 'DOMESTIC' : 'INTERNASIONAL'"></span>
                            (<span x-text="(dau2Metrics.domSharePct >= dau2Metrics.intSharePct ? dau2Metrics.domSharePct : dau2Metrics.intSharePct) + '%'"></span>)
                        </div>
                        <p class="text-[10px] text-slate-500 mt-1">Calculated deterministically from authentic OASYS records.</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-3: STATUS PENERBANGAN & REGULARITAS (UPGRADED) --}}
        @if ($reportType === 'DAU3')
            <div class="space-y-6">
                {{-- 1. Operational Regularity & Apron Stand Impact Alert Banner --}}
                <div class="glass-card p-4 sm:p-5 shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-base shadow-xs"
                             :class="dau3Regularity.extra_flight_impact ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'">
                            <span x-text="dau3Regularity.extra_flight_impact ? '⚠️' : '✅'"></span>
                        </div>
                        <div>
                            <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                                <span>Apron Stand Stress &amp; Regularity Profile</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                      :class="dau3Regularity.extra_flight_impact ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border border-amber-300 dark:border-amber-700' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700'"
                                      x-text="dau3Regularity.extra_flight_impact ? ('Apron Stress: ' + dau3Regularity.extra_flight_pct + '% Non-Scheduled') : ('Normal Operations: ' + dau3Regularity.regularity_rate + '% Scheduled')">
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5"
                               x-text="dau3Regularity.extra_flight_impact ? 'Penerbangan non-berjadwal/extra flights melebihi ambang 5%, berpotensi membebani alokasi parking stand dan slot apron.' : 'Mayoritas pergerakan adalah penerbangan niaga berjadwal komersial, perputaran apron stabil.'">
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <div class="p-2.5 px-3 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-center font-mono">
                            <div class="text-[9px] font-bold uppercase text-slate-400">Regularity Rate</div>
                            <div class="text-base font-black text-aviation-600 dark:text-aviation-400" x-text="(dau3Regularity.regularity_rate || '100') + '%'"></div>
                        </div>
                        <div class="p-2.5 px-3 rounded-lg bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-center font-mono">
                            <div class="text-[9px] font-bold uppercase text-slate-400">Extra / Charter Share</div>
                            <div class="text-base font-black text-amber-600 dark:text-amber-400" x-text="(dau3Regularity.extra_flight_pct || '0') + '%'"></div>
                        </div>
                    </div>
                </div>

                {{-- 2. Visual Charts (Regularity Donut & Domestic vs International Scope) --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Regularity Split</div>
                            <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">SCHEDULED VS NON-SCHEDULED</h2>
                        </div>
                        <div class="relative h-60 w-full flex items-center justify-center">
                            <canvas id="dau3StatusDonut"></canvas>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center font-mono text-xs pt-1">
                            <div class="p-2.5 rounded-lg bg-aviation-50 dark:bg-aviation-950/40 border border-aviation-200 dark:border-aviation-800">
                                <div class="text-[9px] text-aviation-600 font-bold uppercase">Scheduled (Niaga)</div>
                                <div class="text-sm font-black text-aviation-700 dark:text-aviation-300" x-text="formatNumber(dau3Metrics.niagaAcft) + ' A/C'"></div>
                            </div>
                            <div class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
                                <div class="text-[9px] text-amber-600 font-bold uppercase">Non-Scheduled</div>
                                <div class="text-sm font-black text-amber-700 dark:text-amber-300" x-text="formatNumber(dau3Metrics.bukanNiagaAcft) + ' A/C'"></div>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Traffic Scope</div>
                            <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">DOMESTIK VS INTERNASIONAL</h2>
                        </div>
                        <div class="relative h-60 w-full flex items-center justify-center">
                            <canvas id="dau3CategoryDonut"></canvas>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center font-mono text-xs pt-1">
                            <div class="p-2.5 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800">
                                <div class="text-[9px] text-blue-600 font-bold uppercase">Domestik</div>
                                <div class="text-sm font-black text-blue-700 dark:text-blue-300" x-text="formatNumber(dau3Metrics.domAcft) + ' A/C'"></div>
                            </div>
                            <div class="p-2.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800">
                                <div class="text-[9px] text-indigo-600 font-bold uppercase">Internasional</div>
                                <div class="text-sm font-black text-indigo-700 dark:text-indigo-300" x-text="formatNumber(dau3Metrics.intAcft) + ' A/C'"></div>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Irregularity & Cancellation Card (Strict Source-First) --}}
                    <div class="glass-card p-5 sm:p-6 shadow-md space-y-4 flex flex-col justify-between">
                        <div class="space-y-2">
                            <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Irregularity &amp; Cancellations</div>
                                    <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">STATUS PEMBATALAN</h2>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                    SOURCE-FIRST
                                </span>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-900/60 border border-slate-200 dark:border-slate-800 space-y-3 mt-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Cancellation Rate</span>
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-200 dark:bg-navy-800 text-slate-700 dark:text-slate-300">N/A</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Diverted Flights</span>
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-200 dark:bg-navy-800 text-slate-700 dark:text-slate-300">N/A</span>
                                </div>
                                <p class="text-[10px] text-slate-400 leading-relaxed pt-1 border-t border-slate-200/60 dark:border-slate-800">
                                    Data pembatalan, pengalihan (divert), dan irregularitas teknis tidak tercatat pada arsip fisik DAU-03 OASYS. Widget menampilkan status N/A sesuai data asli.
                                </p>
                            </div>
                        </div>

                        <div class="p-3 rounded-lg bg-aviation-50/50 dark:bg-aviation-950/30 border border-aviation-100 dark:border-aviation-900 text-[11px] text-aviation-800 dark:text-aviation-300 font-medium">
                            💡 Total volume teranalisis: <span class="font-bold font-mono" x-text="formatNumber(activeSummary.total_movements) + ' pergerakan pesawat'"></span>.
                        </div>
                    </div>
                </div>
            </div>
        @endif


        {{-- DAU-4: ASAL / TUJUAN --}}
        @if ($reportType === 'DAU4')
            <div class="space-y-6">
                {{-- 1. Bi-Directional Top 10 Origin vs Destination --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Bi-Directional Route Intelligence</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">TOP 10 ORIGIN (ARRIVAL) VS TOP 10 DESTINATION (DEPARTURE)</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> Origin / ARR</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> Destination / DEP</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 font-mono">
                                Metric: <span class="text-aviation-600 uppercase" x-text="selectedMetric"></span>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        {{-- Top Origins (Arrival) --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between border-b border-amber-100 dark:border-amber-950/60 pb-2">
                                <h3 class="text-xs font-black uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                    <span>Top Origins (Arrival)</span>
                                    <span class="text-[10px] text-slate-400 font-normal" x-text="'(' + (dau4Diverging.top_arrival || []).length + ' routes)'"></span>
                                </h3>
                                <span class="text-[10px] font-mono text-amber-600 font-bold">INBOUND FLOW</span>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(r, idx) in (dau4Diverging.top_arrival || []).slice(0, 10)" :key="'arr-' + idx">
                                    <div @click="searchQuery = (r.city_code || r.airport); applyFilters();"
                                         class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 hover:bg-amber-50/70 dark:hover:bg-amber-950/40 border border-slate-200 dark:border-slate-800 transition cursor-pointer space-y-1.5 group">
                                        <div class="flex items-center justify-between text-xs font-bold">
                                            <div class="flex items-center gap-2 truncate">
                                                <span class="w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 text-[10px] flex items-center justify-center font-mono font-bold" x-text="idx + 1"></span>
                                                <span class="text-slate-800 dark:text-slate-200 truncate group-hover:text-amber-600 transition" x-text="(r.city || r.airport) + ' (' + (r.city_code || '—') + ')'"></span>
                                            </div>
                                            <span class="font-mono text-amber-600 font-black whitespace-nowrap" x-text="formatNumber(selectedMetric === 'passenger' ? r.passenger_arrival : r.aircraft_arrival)"></span>
                                        </div>
                                        <div class="w-full bg-slate-200 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                                            <div class="bg-amber-500 h-full rounded-full transition-all duration-500"
                                                 :style="'width: ' + calculateBarHeight((selectedMetric === 'passenger' ? r.passenger_arrival : r.aircraft_arrival), maxDau4Val) + '%'"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Top Destinations (Departure) --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between border-b border-blue-100 dark:border-blue-950/60 pb-2">
                                <h3 class="text-xs font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 flex items-center gap-1.5">
                                    <span>Top Destinations (Departure)</span>
                                    <span class="text-[10px] text-slate-400 font-normal" x-text="'(' + (dau4Diverging.top_departure || []).length + ' routes)'"></span>
                                </h3>
                                <span class="text-[10px] font-mono text-blue-600 font-bold">OUTBOUND FLOW</span>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(r, idx) in (dau4Diverging.top_departure || []).slice(0, 10)" :key="'dep-' + idx">
                                    <div @click="searchQuery = (r.city_code || r.airport); applyFilters();"
                                         class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 hover:bg-blue-50/70 dark:hover:bg-blue-950/40 border border-slate-200 dark:border-slate-800 transition cursor-pointer space-y-1.5 group">
                                        <div class="flex items-center justify-between text-xs font-bold">
                                            <div class="flex items-center gap-2 truncate">
                                                <span class="w-5 h-5 rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 text-[10px] flex items-center justify-center font-mono font-bold" x-text="idx + 1"></span>
                                                <span class="text-slate-800 dark:text-slate-200 truncate group-hover:text-blue-600 transition" x-text="(r.city || r.airport) + ' (' + (r.city_code || '—') + ')'"></span>
                                            </div>
                                            <span class="font-mono text-blue-600 font-black whitespace-nowrap" x-text="formatNumber(selectedMetric === 'passenger' ? r.passenger_departure : r.aircraft_departure)"></span>
                                        </div>
                                        <div class="w-full bg-slate-200 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                                            <div class="bg-blue-600 h-full rounded-full transition-all duration-500"
                                                 :style="'width: ' + calculateBarHeight((selectedMetric === 'passenger' ? r.passenger_departure : r.aircraft_departure), maxDau4Val) + '%'"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. "Top N + Others" Aggregator Card (100% Volume Breakdown) --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Route Traffic Pareto Concentration</div>
                            <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">TOP ROUTES VS TAIL ROUTES ("OTHERS / RUTE LAINNYA")</h2>
                        </div>
                        <div class="text-xs font-mono text-slate-400">100% Volume Distribution Clustered</div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 pt-1">
                        <div class="p-4 rounded-xl bg-aviation-50/60 dark:bg-aviation-950/40 border border-aviation-200 dark:border-aviation-800 space-y-2">
                            <div class="text-[10px] font-bold uppercase text-aviation-700 dark:text-aviation-300">Top 10 Routes Share</div>
                            <div class="text-2xl font-black text-aviation-700 dark:text-aviation-200 font-mono"
                                 x-text="top10RouteSharePct + '%'"></div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                Porsi pergerakan lalu lintas yang terkonsentrasi pada 10 rute utama bandara.
                            </p>
                        </div>
                        <div class="p-4 rounded-xl bg-amber-50/60 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 space-y-2">
                            <div class="text-[10px] font-bold uppercase text-amber-700 dark:text-amber-300">Others / Rute Lainnya Share</div>
                            <div class="text-2xl font-black text-amber-700 dark:text-amber-300 font-mono"
                                 x-text="othersRouteSharePct + '%'"></div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                Agregasi volume pergerakan rute sekunder &amp; perintis (long-tail routes).
                            </p>
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 space-y-2">
                            <div class="text-[10px] font-bold uppercase text-slate-500">Total Connected Network</div>
                            <div class="text-2xl font-black text-slate-800 dark:text-white font-mono"
                                 x-text="formatNumber((dau4Diverging.top_arrival || []).length) + ' Bandara'"></div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                Total jaringan destinasi &amp; asal penerbangan aktif pada periode ini.
                            </p>
                        </div>
                    </div>

                    {{-- Proportional Segmented 100% Progress Bar --}}
                    <div class="space-y-1.5 pt-2">
                        <div class="flex items-center justify-between text-xs font-mono font-bold">
                            <span class="text-aviation-600">Top 10 Routes: <span x-text="top10RouteSharePct + '%'"></span></span>
                            <span class="text-amber-600">Others / Rute Lainnya: <span x-text="othersRouteSharePct + '%'"></span></span>
                        </div>
                        <div class="w-full h-3.5 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden flex">
                            <div class="bg-aviation-600 h-full transition-all duration-500" :style="'width: ' + top10RouteSharePct + '%'"></div>
                            <div class="bg-amber-500 h-full transition-all duration-500" :style="'width: ' + othersRouteSharePct + '%'"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-4A: ASAL / TUJUAN - OPERATOR --}}
        @if ($reportType === 'DAU4A')
            <div class="space-y-6">
                {{-- 1. Route Market Share Analyzer --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Route Intelligence</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">ROUTE MARKET SHARE ANALYZER</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="dau4aRouteSelect" class="text-xs font-bold text-slate-500 whitespace-nowrap">Pilih Rute:</label>
                            <select id="dau4aRouteSelect" x-model="dau4aSelectedRoute"
                                    class="px-3 py-1.5 text-xs font-mono font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-aviation-500">
                                <template x-for="rKey in availableDau4aRoutes" :key="rKey">
                                    <option :value="rKey" x-text="rKey"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    {{-- Route Market Share Breakdown Display --}}
                    <div x-show="activeDau4aRouteBreakdown" class="space-y-4 pt-1">
                        <div class="flex flex-wrap items-center justify-between gap-3 p-3.5 rounded-xl bg-aviation-50/50 dark:bg-aviation-950/30 border border-aviation-100 dark:border-aviation-900">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-aviation-600 text-white flex items-center justify-center font-mono font-black text-sm">
                                    ✈
                                </div>
                                <div>
                                    <div class="text-[10px] font-bold uppercase text-slate-400">Rute Terpilih</div>
                                    <div class="text-base font-black text-slate-900 dark:text-white font-mono" x-text="activeDau4aRouteBreakdown?.route || dau4aSelectedRoute"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-6 font-mono text-xs">
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Total Volume</span>
                                    <span class="font-black text-slate-900 dark:text-white text-sm" x-text="formatNumber(activeDau4aRouteBreakdown?.total_movements) + ' A/C'"></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Leading Operator</span>
                                    <span class="font-black text-aviation-600 dark:text-aviation-400 text-sm" x-text="(activeDau4aRouteBreakdown?.dominant_carrier || '—') + ' (' + (activeDau4aRouteBreakdown?.dominant_share_pct || 0) + '%)'"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Stacked Horizontal Market Share Bar --}}
                        <div class="space-y-1.5">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Pangsa Pasar Maskapai pada Rute Ini (%)</div>
                            <div class="w-full h-7 rounded-xl overflow-hidden flex bg-slate-200 dark:bg-slate-800 shadow-inner">
                                <template x-for="(c, cIdx) in (activeDau4aRouteBreakdown?.carriers || [])" :key="'bar-' + cIdx">
                                    <div class="h-full transition-all duration-300 relative group flex items-center justify-center text-[10px] font-mono font-bold text-white px-1 overflow-hidden"
                                         :style="'width: ' + c.market_share_pct + '%; background-color: ' + getPaletteColor(cIdx)"
                                         :title="c.carrier + ': ' + formatNumber(c.movements) + ' flights (' + c.market_share_pct + '%)'">
                                        <span x-show="c.market_share_pct >= 8" x-text="c.carrier.split(' ')[0] + ' ' + c.market_share_pct + '%'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Carrier breakdown list --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                            <template x-for="(c, cIdx) in (activeDau4aRouteBreakdown?.carriers || [])" :key="'chip-' + cIdx">
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                    <div class="flex items-center gap-2 truncate">
                                        <span class="w-3 h-3 rounded-full flex-shrink-0" :style="'background-color: ' + getPaletteColor(cIdx)"></span>
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate" x-text="c.carrier"></span>
                                    </div>
                                    <div class="text-right font-mono text-xs whitespace-nowrap pl-2">
                                        <div class="font-black text-slate-900 dark:text-white" x-text="c.market_share_pct + '%'"></div>
                                        <div class="text-[10px] text-slate-400" x-text="formatNumber(c.movements) + ' A/C'"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- 2. Hierarchical Operator Matrix --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Hierarchical Operator Matrix</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">OPERATOR × ROUTE TRAFFIC VOLUME</h2>
                        </div>
                        <div class="text-xs text-slate-400 font-mono">Ranked by Volume • Click to filter table</div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
                        <template x-for="(op, idx) in dau4aOperators.slice(0, 12)" :key="'op-' + idx">
                            <div @click="filterAirline = op.name; applyFilters();"
                                 class="p-4 rounded-xl bg-slate-50 dark:bg-navy-900 hover:bg-slate-100 dark:hover:bg-navy-800/80 border border-slate-200 dark:border-slate-800 transition cursor-pointer space-y-2"
                                 :class="filterAirline === op.name ? 'ring-2 ring-aviation-500 bg-aviation-50/30 dark:bg-aviation-950/30' : ''">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-900 dark:text-white truncate text-xs" x-text="op.name"></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300"
                                          x-text="selectedMetric === 'passenger' ? (formatNumber(op.pax) + ' Pax') : (selectedMetric === 'baggage' ? (formatNumber(op.baggage) + ' Kg') : (selectedMetric === 'cargo' ? (formatNumber(op.cargo) + ' Kg') : (selectedMetric === 'pos' ? (formatNumber(op.pos) + ' Kg') : (formatNumber(op.total) + ' A/C'))))"></span>
                                </div>
                                <div class="w-full bg-slate-200 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-aviation-600 h-full rounded-full"
                                         :style="'width: ' + calculateBarHeight((selectedMetric === 'passenger' ? op.pax : (selectedMetric === 'baggage' ? op.baggage : (selectedMetric === 'cargo' ? op.cargo : (selectedMetric === 'pos' ? op.pos : op.total)))), dau4aMax) + '%'"></div>
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono">
                                    <span x-text="op.routesCount + ' Routes Served'"></span>
                                    <span x-text="'Pax: ' + formatNumber(op.pax)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-4B: MATRIX HEATMAP --}}
        @if ($reportType === 'DAU4B')
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Route Frequency Matrix Heatmap</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">AIRPORT × AIRLINE FREQUENCY DENSITY</h2>
                    </div>
                    {{-- Color Scale Legend --}}
                    <div class="flex items-center gap-3 text-xs font-mono">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded" style="background-color: rgba(56, 189, 248, 0.4)"></span> Low (1-4)</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded" style="background-color: rgba(245, 158, 11, 0.7)"></span> Mid (5-14)</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded" style="background-color: rgba(225, 29, 72, 0.9)"></span> Peak (15+)</span>
                    </div>
                </div>

                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl max-h-[500px]">
                    <table class="w-full text-xs font-mono text-center border-collapse">
                        <thead class="bg-slate-900 text-white text-[10px] font-bold uppercase sticky top-0 z-20">
                            <tr>
                                <th class="px-3 py-2 text-left sticky left-0 z-30 bg-slate-900 shadow-xs">Airport / Route</th>
                                <template x-for="air in (dau4bMatrixData.airlines || [])" :key="air">
                                    <th class="px-2.5 py-2 whitespace-nowrap" x-text="air"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="city in (dau4bMatrixData.cities || [])" :key="city">
                                <tr>
                                    <td class="px-3 py-2 text-left font-bold sticky left-0 z-10 bg-slate-50 dark:bg-navy-900 text-slate-800 dark:text-slate-200 whitespace-nowrap shadow-xs"
                                        x-text="city"></td>
                                    <template x-for="air in (dau4bMatrixData.airlines || [])" :key="air">
                                        <td @click="searchQuery = city; applyFilters();"
                                            class="px-2.5 py-2 cursor-pointer transition hover:ring-2 hover:ring-aviation-500 font-mono"
                                            :style="'background-color: ' + getDau4bColor(city, air)"
                                            :class="getDau4bValue(city, air) >= 5 ? 'text-white font-black' : 'text-slate-700 dark:text-slate-300'"
                                            x-text="getDau4bValue(city, air) > 0 ? getDau4bValue(city, air) : '—'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- DAU-5: PARETO AIRLINE --}}
        @if ($reportType === 'DAU5')
            <div class="space-y-6">
                {{-- 1. Herfindahl-Hirschman Index (HHI) & Anchor Carrier Badges --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- HHI Concentration KPI Card --}}
                    <div class="glass-card p-4 sm:p-5 shadow-md border-l-4 space-y-2"
                         :class="{
                             'border-l-emerald-500': (dau5ParetoIntel?.hhi_category === 'COMPETITIVE'),
                             'border-l-amber-500': (dau5ParetoIntel?.hhi_category === 'MODERATELY CONCENTRATED'),
                             'border-l-rose-500': (dau5ParetoIntel?.hhi_category === 'HIGHLY CONCENTRATED' || !dau5ParetoIntel?.hhi_category)
                         }">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Market Concentration</div>
                        <div class="flex items-center justify-between">
                            <span class="text-2xl font-black text-slate-900 dark:text-white font-mono"
                                  x-text="'HHI: ' + formatNumber(dau5ParetoIntel?.hhi_index || 0)"></span>
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono uppercase tracking-wider"
                                  :class="{
                                      'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': (dau5ParetoIntel?.hhi_category === 'COMPETITIVE'),
                                      'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': (dau5ParetoIntel?.hhi_category === 'MODERATELY CONCENTRATED'),
                                      'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': (dau5ParetoIntel?.hhi_category === 'HIGHLY CONCENTRATED' || !dau5ParetoIntel?.hhi_category)
                                  }"
                                  x-text="dau5ParetoIntel?.hhi_category || 'MODERATELY CONCENTRATED'"></span>
                        </div>
                        <p class="text-[11px] text-slate-500 leading-relaxed">
                            Herfindahl-Hirschman Index mengukur tingkat diversifikasi dan kompetisi maskapai pada bandara.
                        </p>
                    </div>

                    {{-- 80% Threshold Coverage --}}
                    <div class="glass-card p-4 sm:p-5 shadow-md border-l-4 border-l-aviation-500 space-y-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">80% Pareto Boundary</div>
                        <div class="flex items-center justify-between">
                            <span class="text-2xl font-black text-aviation-600 dark:text-aviation-400 font-mono"
                                  x-text="(dau5ParetoIntel?.airlines_at_80_pct || dau5ParetoInsight.airlinesAt80 || 0) + ' / ' + (dau5ParetoIntel?.total_airlines || dau5ParetoInsight.total || 0)"></span>
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300">
                                <span x-text="(dau5ParetoIntel?.cumulative_at_80_pct || dau5ParetoInsight.cumAt80 || 0).toFixed(1) + '%'"></span> VOL
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 leading-relaxed">
                            Jumlah maskapai yang mencakup 80% total operasional bandara berdasarkan aturan Pareto 80/20.
                        </p>
                    </div>

                    {{-- Tier-1 Anchor Carriers --}}
                    <div class="glass-card p-4 sm:p-5 shadow-md border-l-4 border-l-purple-500 space-y-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Tier-1 Anchor Airlines</div>
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <template x-for="al in (dau5ParetoIntel?.tier_1_anchor_airlines || [])" :key="al">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800"
                                      x-text="'★ ' + al"></span>
                            </template>
                            <span x-show="!(dau5ParetoIntel?.tier_1_anchor_airlines || []).length" class="text-xs text-slate-400">—</span>
                        </div>
                    </div>
                </div>

                {{-- 2. Enhanced Pareto Chart --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">80/20 Efficiency Rule</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">AIRLINE PARETO MOVEMENT DISTRIBUTION</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-aviation-600"></span> Volume Bars</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-1 rounded bg-amber-500"></span> Cumulative %</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-0.5 bg-red-500" style="border-top:2px dashed #ef4444;display:inline-block"></span> 80% Reference</span>
                        </div>
                    </div>

                    <div x-show="dau5ParetoNoData" class="flex flex-col items-center justify-center h-48 text-slate-400 dark:text-slate-500 gap-2">
                        <svg class="w-10 h-10 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        <div class="text-xs font-bold uppercase tracking-widest" x-text="'NO ' + selectedMetric.toUpperCase() + ' DATA AVAILABLE'"></div>
                    </div>

                    <div x-show="!dau5ParetoNoData" class="relative h-72 sm:h-84 w-full">
                        <canvas id="dau5ParetoChart" class="w-full h-full"></canvas>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-5A: AIRLINE + EXTRA CREW --}}
        @if ($reportType === 'DAU5A')
            <div class="space-y-6">
                {{-- Operating Crew vs Extra Crew Chart --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Crew Operations &amp; Positioning</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">OPERATING CREW VS EXTRA CREW (TOP AIRLINES)</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> Operating Crew</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-purple-600"></span> Extra Crew</span>
                        </div>
                    </div>
                    <div x-show="dau5aCrewNoData" class="flex flex-col items-center justify-center h-48 text-slate-400 dark:text-slate-500 gap-2">
                        <div class="text-xs font-bold uppercase tracking-widest">NO CREW DATA AVAILABLE</div>
                    </div>
                    <div x-show="!dau5aCrewNoData" class="relative h-72 sm:h-84 w-full">
                        <canvas id="dau5aCrewChart" class="w-full h-full"></canvas>
                    </div>
                </div>

                {{-- Grouped Operator Ratios & Efficiency Table --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Carrier Staffing Intensity</div>
                            <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">CREW-TO-MOVEMENT RATIOS PER CARRIER</h2>
                        </div>
                        <span class="text-xs font-mono text-slate-400">Total Crew = Operating + Extra Crew</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <template x-for="(op, opIdx) in (dau5aOps?.operators || []).slice(0, 9)" :key="'op-ops-' + opIdx">
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-slate-900 dark:text-white text-xs truncate" x-text="op.operator_name"></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300"
                                          x-text="op.crew_ratio + ' Crew/Flight'"></span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-[11px] font-mono pt-1 text-slate-600 dark:text-slate-400">
                                    <div>Movements: <span class="font-bold text-slate-800 dark:text-slate-200" x-text="formatNumber(op.movements)"></span></div>
                                    <div>Passengers: <span class="font-bold text-slate-800 dark:text-slate-200" x-text="formatNumber(op.passengers)"></span></div>
                                    <div>Op Crew: <span class="font-bold text-blue-600" x-text="formatNumber(op.operating_crew)"></span></div>
                                    <div>Extra Crew: <span class="font-bold text-purple-600" x-text="formatNumber(op.extra_crew)"></span></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-5B: TERMINAL × AIRLINE --}}
        @if ($reportType === 'DAU5B')
            <div class="space-y-6">
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Terminal Facility Allocation</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">100% STACKED TERMINAL × AIRLINE WORKLOAD</h2>
                        </div>
                        <div class="text-xs font-mono text-slate-400">Spotting Terminal Bottlenecks &amp; Stand Pressure</div>
                    </div>
                    <div x-show="dau5bTermNoData" class="flex flex-col items-center justify-center h-48 text-slate-400 dark:text-slate-500 gap-2">
                        <div class="text-xs font-bold uppercase tracking-widest">NO TERMINAL DATA AVAILABLE</div>
                    </div>
                    <div x-show="!dau5bTermNoData" class="relative h-72 sm:h-84 w-full">
                        <canvas id="dau5bTerminalChart" class="w-full h-full"></canvas>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-5C: AIRLINE PROFILES & CARRIER EFFICIENCY --}}
        @if ($reportType === 'DAU5C')
            <div class="space-y-6">
                {{-- 1. Strict Source-First Fallback / 4-Quadrant Status --}}
                <div class="p-4 rounded-xl border flex items-start gap-3.5 bg-slate-50 dark:bg-navy-900/70 border-slate-200 dark:border-slate-800">
                    <div class="p-2 rounded-lg bg-slate-200 dark:bg-navy-800 text-slate-600 dark:text-slate-300 font-mono text-xs font-black">
                        SOURCE-FIRST
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                            Status Analisis Efisiensi Kapasitas Kursi (Seat Capacity)
                        </h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            <span x-show="!dau5cEfficiency?.has_seat_capacity">
                                Kolom Kapasitas Tempat Duduk (Seat Capacity) dan Load Factor tidak tercatat pada berkas fisik OASYS DAU-05C ini. Sesuai prinsip integritas data SlotWaves, visualisasi 4-Kuadran disembunyikan secara elegan (N/A) untuk mencegah fabrikasi data.
                            </span>
                            <span x-show="dau5cEfficiency?.has_seat_capacity">
                                Evaluasi 4-Kuadran Load Factor vs Seat Capacity aktif berdasarkan data kapasitas yang valid.
                            </span>
                        </p>
                    </div>
                </div>

                {{-- 2. Airline Volume Profiles Chart --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Carrier Volume Comparison</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white"
                                x-text="'TOP AIRLINE OPERATOR COMPARISON: ' + dau5cChartLabel">TOP AIRLINE OPERATOR COMPARISON</h2>
                        </div>
                        <div class="text-xs font-mono text-slate-400"
                             x-text="'Ranked by ' + dau5cChartLabel + (filterDirection !== 'ALL' ? ' • ' + filterDirection : ' • ARR + DEP')">Ranked by selected metric</div>
                    </div>
                    <div x-show="dau5cNoData" class="flex flex-col items-center justify-center h-48 text-slate-400 dark:text-slate-500 gap-2">
                        <div class="text-xs font-bold uppercase tracking-widest">NO AIRLINE DATA AVAILABLE</div>
                    </div>
                    <div x-show="!dau5cNoData" class="relative h-72 sm:h-80 w-full">
                        <canvas id="dau5cBarChart" class="w-full h-full"></canvas>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-6: TIPE PESAWAT & STANDAR AERODROME ICAO --}}
        @if ($reportType === 'DAU6')
            <div class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Top 15 Aircraft Types --}}
                    <div class="lg:col-span-2 glass-card p-5 sm:p-6 shadow-md space-y-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Fleet Mix Distribution</div>
                                <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">TOP 15 AIRCRAFT TYPES</h2>
                            </div>
                            <span class="text-xs font-mono text-slate-400" x-text="'Metric: ' + selectedMetric"></span>
                        </div>
                        <div class="relative h-72 sm:h-80 w-full">
                            <canvas id="dau6FleetChart"></canvas>
                        </div>
                    </div>

                    {{-- Aerodrome Reference Codes & WTC --}}
                    <div class="space-y-6">
                        <div class="glass-card p-5 shadow-md space-y-3">
                            <div class="border-b border-slate-100 dark:border-slate-800 pb-2 flex items-center justify-between">
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">ICAO Aerodrome Code</h3>
                                <span class="text-[10px] font-mono text-aviation-600 font-bold">STAND CAPACITY</span>
                            </div>
                            <div class="relative h-44 w-full flex items-center justify-center">
                                <canvas id="dau6CategoryDonut"></canvas>
                            </div>
                            <div class="grid grid-cols-3 gap-1.5 text-center font-mono text-[10px] pt-1">
                                <div class="p-1.5 rounded bg-aviation-50 dark:bg-aviation-950/40 text-aviation-700 dark:text-aviation-300">
                                    <div class="font-bold">Code C</div>
                                    <div x-text="formatNumber(dau6Aerodrome?.icao_codes?.code_c || 0) + ' A/C'"></div>
                                </div>
                                <div class="p-1.5 rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300">
                                    <div class="font-bold">Code D/E/F</div>
                                    <div x-text="formatNumber(dau6Aerodrome?.icao_codes?.code_def || 0) + ' A/C'"></div>
                                </div>
                                <div class="p-1.5 rounded bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300">
                                    <div class="font-bold">Code A/B</div>
                                    <div x-text="formatNumber(dau6Aerodrome?.icao_codes?.code_ab || 0) + ' A/C'"></div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card p-5 shadow-md space-y-3">
                            <div class="border-b border-slate-100 dark:border-slate-800 pb-2 flex items-center justify-between">
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">WTC Safety Profile</h3>
                                <span class="px-2 py-0.5 rounded text-[9px] font-mono font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                      x-text="'RUNWAY SEPARATION: ' + (dau6Aerodrome?.runway_separation_workload || 'STANDARD')"></span>
                            </div>
                            <div class="relative h-40 w-full flex items-center justify-center">
                                <canvas id="dau6WtcDonut"></canvas>
                            </div>
                            <div class="grid grid-cols-3 gap-1.5 text-center font-mono text-[10px] pt-1">
                                <div class="p-1.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300">
                                    <div class="font-bold">Medium (M)</div>
                                    <div x-text="formatNumber(dau6Aerodrome?.wtc_breakdown?.medium || 0)"></div>
                                </div>
                                <div class="p-1.5 rounded bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300">
                                    <div class="font-bold">Heavy (H)</div>
                                    <div x-text="formatNumber(dau6Aerodrome?.wtc_breakdown?.heavy || 0)"></div>
                                </div>
                                <div class="p-1.5 rounded bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300">
                                    <div class="font-bold">Light (L)</div>
                                    <div x-text="formatNumber(dau6Aerodrome?.wtc_breakdown?.light || 0)"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-10: JAM PUNCAK --}}
        @if ($reportType === 'DAU10')
            <div class="space-y-6">
                {{-- 1. Top 3 Peak Hour Badges for Aircraft Movements and Passengers --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Aircraft Movement Peaks --}}
                    <div class="glass-card p-4 sm:p-5 shadow-md border-l-4 border-l-amber-500 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-base">✈️</span>
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">TOP 3 AIRCRAFT MOVEMENT PEAKS</h3>
                            </div>
                            <span class="text-[10px] font-mono text-amber-600 font-bold">RUNWAY DEMAND</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center font-mono">
                            <template x-for="(pk, pkIdx) in (dau10PeakIntel?.top_aircraft_peaks || [{hour: peaks.peak_aircraft_hour, value: peaks.peak_aircraft}]).slice(0, 3)" :key="'ac-pk-' + pkIdx">
                                <div class="p-2.5 rounded-xl border transition"
                                     :class="{
                                         'bg-amber-50 dark:bg-amber-950/50 border-amber-300 dark:border-amber-800 ring-1 ring-amber-400': pkIdx === 0,
                                         'bg-slate-50 dark:bg-navy-900 border-slate-200 dark:border-slate-800': pkIdx > 0
                                     }">
                                    <div class="text-[10px] font-black uppercase"
                                         :class="pkIdx === 0 ? 'text-amber-700 dark:text-amber-300' : 'text-slate-400'"
                                         x-text="pkIdx === 0 ? '🥇 Rank 1' : (pkIdx === 1 ? '🥈 Rank 2' : '🥉 Rank 3')"></div>
                                    <div class="text-xs font-black text-slate-800 dark:text-white mt-1" x-text="pk.hour"></div>
                                    <div class="text-sm font-black text-amber-600 mt-0.5" x-text="formatNumber(pk.value) + ' A/C'"></div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Passenger Terminal Peaks --}}
                    <div class="glass-card p-4 sm:p-5 shadow-md border-l-4 border-l-emerald-500 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-base">👥</span>
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">TOP 3 PASSENGER TERMINAL PEAKS</h3>
                            </div>
                            <span class="text-[10px] font-mono text-emerald-600 font-bold">TERMINAL CONCOURSE</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center font-mono">
                            <template x-for="(pk, pkIdx) in (dau10PeakIntel?.top_passenger_peaks || [{hour: peaks.peak_passenger_hour, value: peaks.peak_passenger}]).slice(0, 3)" :key="'px-pk-' + pkIdx">
                                <div class="p-2.5 rounded-xl border transition"
                                     :class="{
                                         'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-300 dark:border-emerald-800 ring-1 ring-emerald-400': pkIdx === 0,
                                         'bg-slate-50 dark:bg-navy-900 border-slate-200 dark:border-slate-800': pkIdx > 0
                                     }">
                                    <div class="text-[10px] font-black uppercase"
                                         :class="pkIdx === 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-400'"
                                         x-text="pkIdx === 0 ? '🥇 Rank 1' : (pkIdx === 1 ? '🥈 Rank 2' : '🥉 Rank 3')"></div>
                                    <div class="text-xs font-black text-slate-800 dark:text-white mt-1" x-text="pk.hour"></div>
                                    <div class="text-sm font-black text-emerald-600 mt-0.5" x-text="formatNumber(pk.value) + ' Pax'"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- 2. Synchronized Dual-Axis Peak Chart (Aircraft vs Passenger Overlaid Timeline) --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Synchronized Flow Analysis</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">SYNCHRONIZED DUAL-AXIS PEAK CHART (AIRCRAFT VS PASSENGERS)</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> Aircraft Movements (Left Axis)</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-1 bg-emerald-500 rounded-full"></span> Passengers (Right Axis)</span>
                        </div>
                    </div>

                    <div class="relative h-72 sm:h-84 w-full">
                        <canvas id="dau10DualPeakChart"></canvas>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs font-mono text-slate-500">
                        <span>💡 <strong class="text-slate-800 dark:text-slate-200">Terminal Lag Time:</strong> Puncak pergerakan penumpang sering mendahului keberangkatan (check-in/security) atau menyusul kedatangan pesawat (baggage claim).</span>
                    </div>
                </div>

                {{-- 3. Hourly Distribution Bars (Aircraft & Passenger) --}}
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

                    <div class="h-64 sm:h-72 w-full flex items-end gap-1.5 sm:gap-2 pt-6 pb-2 px-1 overflow-x-auto">
                        <template x-for="(item, idx) in activeHourlyDistribution" :key="idx">
                            <div @click="setHourFilter(item.hour)"
                                 class="flex-1 min-w-[32px] sm:min-w-[40px] flex flex-col items-center h-full justify-end group relative cursor-pointer"
                                 :class="item.hour === peaks.peak_aircraft_hour ? 'bg-amber-50/60 dark:bg-amber-950/40 rounded-lg ring-1 ring-amber-400' : ''">
                                
                                <div class="opacity-0 group-hover:opacity-100 transition pointer-events-none absolute bottom-full mb-2 z-30 bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-[10px] font-mono rounded-lg px-2.5 py-1.5 shadow-xl whitespace-nowrap">
                                    <div class="font-bold" x-text="'Jam: ' + item.hour"></div>
                                    <div x-text="'Arr: ' + formatNumber(item.aircraft_arrival)"></div>
                                    <div x-text="'Dep: ' + formatNumber(item.aircraft_departure)"></div>
                                    <div class="font-bold text-amber-400 dark:text-amber-600" x-text="'Total: ' + formatNumber(item.aircraft_total) + ' Acft'"></div>
                                    <div class="text-[9px] text-slate-400">Click to filter table</div>
                                </div>

                                <div class="w-full flex items-end justify-center gap-0.5 sm:gap-1 px-1" style="height: 100%;">
                                    <div class="w-1/3 bg-amber-500 hover:bg-amber-400 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(item.aircraft_arrival, maxAircraftPerHour) + '%'"></div>
                                    <div class="w-1/3 bg-blue-600 hover:bg-blue-500 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(item.aircraft_departure, maxAircraftPerHour) + '%'"></div>
                                    <div class="w-1/3 bg-slate-800 dark:bg-slate-200 transition-all rounded-t-sm font-bold"
                                         :style="'height: ' + calculateBarHeight(item.aircraft_total, maxAircraftPerHour) + '%'"></div>
                                </div>

                                <div class="text-[9px] font-mono text-slate-500 mt-2 truncate w-full text-center"
                                     x-text="item.hour.split(' - ')[0] || item.hour"></div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Hourly Passenger Movement --}}
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

                    <div class="h-64 sm:h-72 w-full flex items-end gap-1.5 sm:gap-2 pt-6 pb-2 px-1 overflow-x-auto">
                        <template x-for="(item, idx) in activeHourlyDistribution" :key="idx">
                            <div @click="setHourFilter(item.hour)"
                                 class="flex-1 min-w-[32px] sm:min-w-[40px] flex flex-col items-center h-full justify-end group relative cursor-pointer"
                                 :class="item.hour === peaks.peak_passenger_hour ? 'bg-emerald-50/60 dark:bg-emerald-950/40 rounded-lg ring-1 ring-emerald-400' : ''">
                                
                                <div class="opacity-0 group-hover:opacity-100 transition pointer-events-none absolute bottom-full mb-2 z-30 bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-[10px] font-mono rounded-lg px-2.5 py-1.5 shadow-xl whitespace-nowrap">
                                    <div class="font-bold" x-text="'Jam: ' + item.hour"></div>
                                    <div x-text="'Arr: ' + formatNumber(item.passenger_arrival) + ' Pax'"></div>
                                    <div x-text="'Dep: ' + formatNumber(item.passenger_departure) + ' Pax'"></div>
                                    <div class="font-bold text-emerald-400 dark:text-emerald-600" x-text="'Total: ' + formatNumber(item.passenger_total) + ' Pax'"></div>
                                    <div class="text-[9px] text-slate-400">Click to filter table</div>
                                </div>

                                <div class="w-full flex items-end justify-center gap-0.5 sm:gap-1 px-1" style="height: 100%;">
                                    <div class="w-1/3 bg-emerald-500 hover:bg-emerald-400 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(item.passenger_arrival, maxPassengerPerHour) + '%'"></div>
                                    <div class="w-1/3 bg-teal-600 hover:bg-teal-500 transition-all rounded-t-sm"
                                         :style="'height: ' + calculateBarHeight(item.passenger_departure, maxPassengerPerHour) + '%'"></div>
                                    <div class="w-1/3 bg-slate-800 dark:bg-slate-200 transition-all rounded-t-sm font-bold"
                                         :style="'height: ' + calculateBarHeight(item.passenger_total, maxPassengerPerHour) + '%'"></div>
                                </div>

                                <div class="text-[9px] font-mono text-slate-500 mt-2 truncate w-full text-center"
                                     x-text="item.hour.split(' - ')[0] || item.hour"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-10A: JAM PUNCAK MENURUT TERMINAL --}}
        @if ($reportType === 'DAU10A')
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-xs font-bold">
                        <button type="button" @click="dauViewMode = 'heatmap'"
                                :class="dauViewMode === 'heatmap' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-2xs' : 'text-slate-600 dark:text-slate-400'"
                                class="px-4 py-2 rounded-lg transition cursor-pointer">
                            Time × Terminal Heatmap
                        </button>
                        <button type="button" @click="dauViewMode = 'distribution'"
                                :class="dauViewMode === 'distribution' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-2xs' : 'text-slate-600 dark:text-slate-400'"
                                class="px-4 py-2 rounded-lg transition cursor-pointer">
                            Distribusi Per Jam (Capacity Envelope)
                        </button>
                    </div>
                </div>

                {{-- VIEW 1: HEATMAP --}}
                <div x-show="dauViewMode === 'heatmap'" class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Terminal Congestion Heatmap</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">TIME × TERMINAL HEATMAP MATRIX</h2>
                        </div>
                        <div class="text-xs font-mono text-slate-400">
                            Metric: <span class="uppercase font-bold" x-text="selectedMetric"></span> • Click cell to filter terminal &amp; hour
                        </div>
                    </div>

                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                        <table class="w-full text-xs font-mono text-center border-collapse">
                            <thead class="bg-slate-900 text-white text-[10px] font-bold uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left sticky left-0 z-10 bg-slate-900">Terminal</th>
                                    <template x-for="h in hours" :key="h">
                                        <th class="px-2 py-2" x-text="h.split(' - ')[0] || h"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @php
                                    $dau10aTerms = ['1', '2F', '3U', '1B', '2D', '2E', '1C'];
                                    foreach ($terminals as $tk) {
                                        if (!in_array($tk, $dau10aTerms)) $dau10aTerms[] = $tk;
                                    }
                                @endphp
                                @foreach ($dau10aTerms as $t)
                                    <tr>
                                        <td class="px-3 py-2 text-left font-bold sticky left-0 z-10 bg-slate-50 dark:bg-navy-900 text-slate-800 dark:text-slate-200">
                                            Terminal {{ $t }}
                                        </td>
                                        <template x-for="h in hours" :key="h">
                                            <td @click="filterByTerminalAndHour('{{ $t }}', h)"
                                                class="px-2 py-2 cursor-pointer transition hover:ring-2 hover:ring-aviation-500"
                                                :style="'background-color: ' + getHeatmapColor('{{ $t }}', h)"
                                                :class="getHeatmapValue('{{ $t }}', h) > 20 ? 'text-white font-bold' : 'text-slate-700 dark:text-slate-300'"
                                                x-text="getHeatmapValue('{{ $t }}', h) > 0 ? getHeatmapValue('{{ $t }}', h) : '—'"></td>
                                        </template>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- VIEW 2: DISTRIBUSI PER JAM --}}
                <div x-show="dauViewMode === 'distribution'" class="space-y-6">
                    {{-- METRIC SELECTION HEADER FOR DAU-10A (PART 48) --}}
                    <div class="glass-card p-3 shadow-xs border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="text-[11px] font-mono font-bold uppercase tracking-wider text-slate-500">METRIC:</span>
                            <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-navy-900 border border-slate-200 dark:border-slate-800">
                                <button type="button" @click="selectedMetric = 'aircraft'; applyFilters();"
                                        :class="selectedMetric === 'aircraft' ? 'bg-aviation-600 text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 font-bold'"
                                        class="px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer">
                                    PESAWAT
                                </button>
                                <button type="button" @click="selectedMetric = 'passenger'; applyFilters();"
                                        :class="selectedMetric === 'passenger' ? 'bg-emerald-600 text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 font-bold'"
                                        class="px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer">
                                    PENUMPANG
                                </button>
                                <button type="button" @click="selectedMetric = 'crew'; applyFilters();"
                                        :class="selectedMetric === 'crew' ? 'bg-purple-600 text-white shadow-sm font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 font-bold'"
                                        class="px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer">
                                    AWAK
                                </button>
                            </div>
                        </div>

                        <div class="text-xs font-mono text-slate-500 flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase text-slate-400">Mode Aktif:</span>
                            <span class="px-2 py-0.5 rounded font-bold uppercase"
                                  :class="{
                                      'bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300': selectedMetric === 'aircraft',
                                      'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300': selectedMetric === 'passenger',
                                      'bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300': selectedMetric === 'crew'
                                  }"
                                  x-text="selectedMetric === 'aircraft' ? 'Aircraft Capacity Envelope Active' : (selectedMetric === 'passenger' ? 'Passenger Distribution (No Capacity Envelope)' : 'Crew Distribution (No Capacity Envelope)')">
                            </span>
                        </div>
                    </div>

                    {{-- DYNAMIC SUMMARY CARD (AIRCRAFT VS PASSENGER VS CREW) --}}
                    <div class="glass-card p-4 shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <div class="text-[11px] font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                                <span x-text="selectedMetric === 'aircraft' ? 'CAPACITY STATUS SUMMARY' : (selectedMetric === 'passenger' ? 'PASSENGER TRAFFIC SUMMARY' : 'CREW TRAFFIC SUMMARY')"></span>
                                <span x-show="selectedMetric === 'aircraft'" class="text-[10px] font-mono font-normal text-slate-400">(ARR: <strong class="text-amber-600 font-bold" x-text="arrivalCapacity + ' A/C'"></strong> | DEP: <strong class="text-blue-600 font-bold" x-text="departureCapacity + ' A/C'"></strong>)</span>
                            </div>
                            <p class="text-xs text-slate-500"
                               x-text="selectedMetric === 'aircraft' ? 'Evaluasi langsung status jam operasional bandara terhadap batas kapasitas penerbangan.' : (selectedMetric === 'passenger' ? 'Ringkasan distribusi volume penumpang datang dan berangkat per jam.' : 'Ringkasan distribusi awak penerbangan dan extra crew per jam.')"></p>
                        </div>

                        {{-- Aircraft Summary Pills --}}
                        <div x-show="selectedMetric === 'aircraft'" class="flex flex-wrap items-center gap-2 text-xs font-bold font-mono">
                            <div class="px-3 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>AVAILABLE:</span>
                                <span class="font-black text-sm" x-text="hourlyCapacityAnalysis.summary.available"></span>
                                <span class="text-[10px] font-normal text-emerald-600">jam</span>
                            </div>
                            <div class="px-3 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span>FULL / MAX:</span>
                                <span class="font-black text-sm" x-text="hourlyCapacityAnalysis.summary.full"></span>
                                <span class="text-[10px] font-normal text-amber-600">jam</span>
                            </div>
                            <div class="px-3 py-1 rounded-lg bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                <span>OVER CAPACITY:</span>
                                <span class="font-black text-sm" x-text="hourlyCapacityAnalysis.summary.over"></span>
                                <span class="text-[10px] font-normal text-purple-600">jam</span>
                            </div>
                        </div>

                        {{-- Passenger Summary Pills --}}
                        <div x-show="selectedMetric === 'passenger'" class="flex flex-wrap items-center gap-2 text-xs font-bold font-mono">
                            <div class="px-3 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center gap-1.5">
                                <span class="text-[10px] text-emerald-600 uppercase font-bold">Total Pax:</span>
                                <span class="font-black text-sm" x-text="formatNumber(hourlyCapacityAnalysis.summary.totalDemand)"></span>
                                <span class="text-[10px] font-normal text-emerald-600">pax</span>
                            </div>
                            <div class="px-3 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 flex items-center gap-1.5">
                                <span class="text-[10px] text-amber-600 uppercase font-bold">Peak Hour:</span>
                                <span class="font-black text-sm" x-text="hourlyCapacityAnalysis.summary.peakHour"></span>
                            </div>
                        </div>

                        {{-- Crew Summary Pills --}}
                        <div x-show="selectedMetric === 'crew'" class="flex flex-wrap items-center gap-2 text-xs font-bold font-mono">
                            <div class="px-3 py-1 rounded-lg bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 flex items-center gap-1.5">
                                <span class="text-[10px] text-purple-600 uppercase font-bold">Total Awak:</span>
                                <span class="font-black text-sm" x-text="formatNumber(hourlyCapacityAnalysis.summary.totalDemand)"></span>
                                <span class="text-[10px] font-normal text-purple-600">crew</span>
                            </div>
                            <div class="px-3 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 flex items-center gap-1.5">
                                <span class="text-[10px] text-blue-600 uppercase font-bold">Peak Hour:</span>
                                <span class="font-black text-sm" x-text="hourlyCapacityAnalysis.summary.peakHour"></span>
                            </div>
                        </div>
                    </div>

                    {{-- AVERAGE BY DAYS CONTROL BAR (DAU-10A AIRCRAFT ONLY) --}}
                    <div x-show="reportType === 'DAU10A' && selectedMetric === 'aircraft' && dauViewMode === 'distribution'"
                         class="glass-card p-4 sm:p-5 shadow-md border-l-4 border-l-aviation-500 space-y-3 bg-gradient-to-r from-aviation-500/5 via-transparent to-transparent">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-2.5">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-aviation-100 dark:bg-aviation-950/60 text-aviation-600 dark:text-aviation-400 font-bold text-xs shadow-2xs">
                                    &Sigma;
                                </span>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-xs sm:text-sm font-black tracking-wider uppercase text-slate-900 dark:text-white">
                                            AVERAGE BY DAYS
                                        </h3>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-black"
                                              :class="averagePeriod === 'original' ? 'bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700' : 'bg-aviation-100 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-300 dark:border-aviation-800'"
                                              x-text="'AVERAGE: ' + (averagePeriod === 'original' ? 'ORIGINAL DATA' : (selectedAverageDays + ' DAYS'))">
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        Transformasi nilai bar jam pesawat berdasarkan pembagian hari: <span class="font-mono text-aviation-600 dark:text-aviation-400 font-bold">ceil(hourlyValue / N)</span>.
                                    </p>
                                </div>
                            </div>

                            {{-- Metadata & Scope Badges --}}
                            <div class="flex flex-wrap items-center gap-2 text-[10.5px] font-mono">
                                <div class="px-2 py-1 rounded-md bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 flex items-center gap-1 shadow-2xs">
                                    <span class="text-slate-400 uppercase text-[9px]">DATA PERIOD:</span>
                                    <strong class="font-bold" x-text="formatDateDisplay(averageWindowStartDate) + ' - ' + formatDateDisplay(averageWindowEndDate)"></strong>
                                </div>
                                <div class="px-2 py-1 rounded-md bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 flex items-center gap-1 shadow-2xs">
                                    <span class="text-slate-400 uppercase text-[9px]">AVAILABLE DATA DAYS:</span>
                                    <strong class="font-bold text-emerald-600 dark:text-emerald-400" x-text="totalAvailableDays"></strong>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 pt-1">
                            {{-- Period Option Buttons --}}
                            <div class="space-y-1.5 w-full lg:w-auto">
                                <div class="flex flex-wrap items-center gap-1.5 font-mono text-xs">
                                    {{-- ORIGINAL DATA Button --}}
                                    <button type="button"
                                            @click="setAverageDays('original')"
                                            :class="averagePeriod === 'original'
                                                ? 'bg-aviation-600 text-white font-black shadow-xs ring-2 ring-aviation-400/40 border-aviation-600'
                                                : 'bg-white dark:bg-navy-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-navy-800 font-bold'"
                                            class="px-3 py-1.5 rounded-lg border text-[11px] transition cursor-pointer">
                                        ORIGINAL DATA
                                    </button>

                                    {{-- Preset Days: 5, 15, 30, 60 --}}
                                    <template x-for="p in [5, 15, 30, 60]" :key="p">
                                        <button type="button"
                                                @click="setAverageDays(String(p), p)"
                                                :disabled="p > totalAvailableDays"
                                                :class="[
                                                    averagePeriod === String(p)
                                                        ? 'bg-aviation-600 text-white font-black shadow-xs ring-2 ring-aviation-400/40 border-aviation-600'
                                                        : (p > totalAvailableDays
                                                            ? 'opacity-40 bg-slate-100 dark:bg-navy-900 text-slate-400 border-slate-200 dark:border-slate-800 cursor-not-allowed'
                                                            : 'bg-white dark:bg-navy-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-navy-800 font-bold cursor-pointer')
                                                ]"
                                                class="px-2.5 py-1.5 rounded-lg border text-[11px] transition flex items-center gap-1">
                                            <span x-text="p + ' DAYS'"></span>
                                        </button>
                                    </template>

                                    {{-- CUSTOM Button --}}
                                    <button type="button"
                                            @click="setAverageDays('custom')"
                                            :disabled="totalAvailableDays <= 1"
                                            :class="averagePeriod === 'custom'
                                                ? 'bg-aviation-600 text-white font-black shadow-xs ring-2 ring-aviation-400/40 border-aviation-600'
                                                : (totalAvailableDays <= 1
                                                    ? 'opacity-40 bg-slate-100 dark:bg-navy-900 text-slate-400 border-slate-200 dark:border-slate-800 cursor-not-allowed'
                                                    : 'bg-white dark:bg-navy-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-navy-800 font-bold cursor-pointer')"
                                            class="px-2.5 py-1.5 rounded-lg border text-[11px] transition flex items-center gap-1">
                                        CUSTOM
                                    </button>

                                    {{-- Custom Days Input Form (when 'custom' selected) --}}
                                    <div x-show="averagePeriod === 'custom'" class="inline-flex items-center gap-1.5 ml-1">
                                        <span class="text-[10px] font-mono text-slate-400 font-bold">CUSTOM DAYS:</span>
                                        <input type="number"
                                               x-model="customInputDays"
                                               @keydown.enter="applyCustomAverageDays()"
                                               placeholder="30"
                                               min="1"
                                               :max="totalAvailableDays"
                                               class="w-16 px-2 py-1 text-xs font-mono font-bold rounded-lg border border-aviation-300 dark:border-aviation-700 bg-white dark:bg-navy-950 text-slate-900 dark:text-white focus:ring-2 focus:ring-aviation-500 text-center">
                                        <span class="text-[10px] font-mono text-slate-400 font-bold">DAYS</span>
                                        <span class="text-[9px] font-mono text-slate-400">(max: <span class="text-aviation-600 dark:text-aviation-400 font-bold" x-text="totalAvailableDays"></span>)</span>
                                        <button type="button"
                                                @click="applyCustomAverageDays()"
                                                class="px-2.5 py-1 rounded-lg bg-aviation-600 hover:bg-aviation-700 text-white font-mono font-bold text-[11px] transition cursor-pointer shadow-2xs">
                                            APPLY
                                        </button>
                                    </div>
                                </div>

                                {{-- Custom Day Validation Error Alert --}}
                                <div x-show="customDayError" class="text-[10.5px] font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1 pt-0.5" style="display: none;">
                                    <span>⚠️</span>
                                    <span x-text="customDayError"></span>
                                </div>

                                {{-- Single-Day Data Notice (shown when report only contains 1 operational date) --}}
                                <template x-if="totalAvailableDays <= 1">
                                    <div class="flex items-center gap-1.5 pt-1 text-[10px] font-mono text-slate-500 dark:text-slate-400">
                                        <span class="text-amber-500">ⓘ</span>
                                        <span>Report ini hanya memiliki <strong class="text-slate-700 dark:text-slate-200">1 hari data</strong>. Preset 5/15/30/60 DAYS dinonaktifkan. Upload laporan multi-hari untuk mengaktifkan fitur Average.</span>
                                    </div>
                                </template>
                            </div>

                            {{-- Right: Reset Button --}}
                            <div class="flex items-center gap-2">
                                <template x-if="averagePeriod !== 'original'">
                                    <button type="button"
                                            @click="resetAverageDays()"
                                            title="Kembalikan ke data asli tanpa pembagian rata-rata"
                                            class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-navy-800 border border-slate-300 dark:border-slate-700 hover:bg-slate-200 transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                                        <span>↺</span>
                                        <span>RESET TO ORIGINAL</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- CHART BOX --}}
                    <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">DISTRIBUSI PER JAM</h2>
                                <p class="text-xs text-slate-500"
                                   x-text="selectedMetric === 'aircraft' ? 'Two-Direction Operational Aircraft Capacity Envelope & Demand Analysis.' : (selectedMetric === 'passenger' ? 'Two-Direction Passenger Hourly Distribution (Arrivals Above, Departures Below).' : 'Two-Direction Crew Hourly Distribution (Operating Crew Above, Extra Crew Below).')"></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" x-show="selectedMetric === 'aircraft'" @click="openUnifiedModal()"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold text-aviation-700 dark:text-aviation-300 bg-aviation-50 dark:bg-aviation-950 border border-aviation-300 dark:border-aviation-800 hover:bg-aviation-100 transition cursor-pointer">
                                    ⚙ Edit Capacity &amp; Hours
                                </button>
                            </div>
                        </div>

                        <x-hourly-capacity-envelope-chart mode="dau" />
                    </div>

                    {{-- HOURLY STATUS / DISTRIBUTION DETAIL TABLE --}}
                    <div class="glass-card p-5 shadow-md space-y-3">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                            <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white"
                                x-text="selectedMetric === 'aircraft' ? 'HOURLY CAPACITY STATUS' : (selectedMetric === 'passenger' ? 'HOURLY PASSENGER STATUS' : 'HOURLY CREW STATUS')"></h3>
                            <p class="text-xs text-slate-500"
                               x-text="selectedMetric === 'aircraft' ? 'Analisis demand pesawat per jam operasional dibandingkan dengan batas Aircraft Capacity.' : (selectedMetric === 'passenger' ? 'Distribusi penumpang kedatangan (ARR), keberangkatan (DEP), transit, dan transfer per jam.' : 'Distribusi awak pesawat operasi (ARR) dan extra crew (DEP) per jam.')"></p>
                        </div>
                        <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                            <table class="w-full text-xs font-mono text-center border-collapse">
                                {{-- AIRCRAFT THEAD --}}
                                <thead x-show="selectedMetric === 'aircraft'" class="bg-slate-100 dark:bg-navy-900 text-[10px] font-bold text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Hour</th>
                                        <th class="px-3 py-2 text-right">ARR</th>
                                        <th class="px-2 py-2 text-center text-amber-600">ARR Cap</th>
                                        <th class="px-3 py-2 text-right">DEP</th>
                                        <th class="px-2 py-2 text-center text-blue-600">DEP Cap</th>
                                        <th class="px-2 py-2 text-center">OPC</th>
                                        <th class="px-3 py-2 text-right">Aircraft Demand</th>
                                        <th class="px-3 py-2 text-center">Status</th>
                                    </tr>
                                </thead>

                                {{-- PASSENGER THEAD --}}
                                <thead x-show="selectedMetric === 'passenger'" class="bg-slate-100 dark:bg-navy-900 text-[10px] font-bold text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Hour</th>
                                        <th class="px-3 py-2 text-right text-emerald-600">Passenger ARR</th>
                                        <th class="px-3 py-2 text-right text-blue-600">Passenger DEP</th>
                                        <th class="px-3 py-2 text-right">Transit</th>
                                        <th class="px-3 py-2 text-right">Transfer</th>
                                        <th class="px-3 py-2 text-right font-black text-amber-600">Total Passenger</th>
                                    </tr>
                                </thead>

                                {{-- CREW THEAD --}}
                                <thead x-show="selectedMetric === 'crew'" class="bg-slate-100 dark:bg-navy-900 text-[10px] font-bold text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Hour</th>
                                        <th class="px-3 py-2 text-right text-blue-600">Operating Crew (ARR)</th>
                                        <th class="px-3 py-2 text-right text-purple-600">Extra Crew (DEP)</th>
                                        <th class="px-3 py-2 text-right font-black">Total Crew</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    <template x-for="row in hourlyCapacityAnalysis.list" :key="row.hour">
                                        <tr @click="setHourFilter(row.hour)" class="hover:bg-slate-50/70 dark:hover:bg-navy-800/50 cursor-pointer transition"
                                            :class="filterHour === row.hour ? 'bg-aviation-50/70 dark:bg-aviation-950/40 font-bold' : ''">
                                            <td class="px-3 py-2 text-left font-bold text-slate-900 dark:text-white" x-text="formatHourDisplay(row.hour)"></td>

                                            {{-- AIRCRAFT CELLS --}}
                                            <template x-if="selectedMetric === 'aircraft'">
                                                <td class="px-3 py-2 text-right text-amber-600 font-bold" x-text="formatNumber(row.arr)"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'aircraft'">
                                                <td class="px-2 py-2 text-center font-mono font-bold text-slate-500" x-text="row.arrCap"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'aircraft'">
                                                <td class="px-3 py-2 text-right text-blue-600 font-bold" x-text="formatNumber(row.dep)"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'aircraft'">
                                                <td class="px-2 py-2 text-center font-mono font-bold text-slate-500" x-text="row.depCap"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'aircraft'">
                                                <td class="px-2 py-2 text-center text-slate-400" x-text="row.opc"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'aircraft'">
                                                <td class="px-3 py-2 text-right font-black text-slate-900 dark:text-white" x-text="formatNumber(row.demand)"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'aircraft'">
                                                <td class="px-3 py-2 text-center">
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider inline-block"
                                                          :class="{
                                                              'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': row.status === 'AVAILABLE',
                                                              'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': row.status === 'FULL / MAX',
                                                              'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300': row.status === 'OVER CAPACITY',
                                                              'bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-400': row.status === 'OFF HOURS'
                                                          }"
                                                          x-text="row.status"></span>
                                                </td>
                                            </template>

                                            {{-- PASSENGER CELLS --}}
                                            <template x-if="selectedMetric === 'passenger'">
                                                <td class="px-3 py-2 text-right text-emerald-600 font-bold" x-text="formatNumber(row.arr)"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'passenger'">
                                                <td class="px-3 py-2 text-right text-blue-600 font-bold" x-text="formatNumber(row.dep)"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'passenger'">
                                                <td class="px-3 py-2 text-right text-slate-500" x-text="formatNumber(row.transit)"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'passenger'">
                                                <td class="px-3 py-2 text-right text-slate-500" x-text="formatNumber(row.transfer)"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'passenger'">
                                                <td class="px-3 py-2 text-right font-black text-slate-900 dark:text-white" x-text="formatNumber(row.demand)"></td>
                                            </template>

                                            {{-- CREW CELLS --}}
                                            <template x-if="selectedMetric === 'crew'">
                                                <td class="px-3 py-2 text-right text-blue-600 font-bold" x-text="formatNumber(row.arr)"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'crew'">
                                                <td class="px-3 py-2 text-right text-purple-600 font-bold" x-text="formatNumber(row.dep)"></td>
                                            </template>
                                            <template x-if="selectedMetric === 'crew'">
                                                <td class="px-3 py-2 text-right font-black text-slate-900 dark:text-white" x-text="formatNumber(row.demand)"></td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-10B: BLOCK ON/OFF --}}
        @if ($reportType === 'DAU10B')
            <div class="space-y-6">
                {{-- 1. Apron Dwell & Stand Accumulation Alert --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Apron Stand Turnover &amp; Capacity Stress</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">HOURLY NET APRON FLOW DELTA (BLOCK ON - BLOCK OFF)</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-500"></span> Build-up (+Delta / Stand Dwell)</span>
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-blue-600"></span> Clearance (-Delta / Apron Release)</span>
                        </div>
                    </div>

                    {{-- Apron Accumulation Alert Banner --}}
                    <div class="p-3.5 rounded-xl border flex items-center justify-between gap-3 bg-amber-50/60 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800 text-xs">
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg">⚠️</span>
                            <div>
                                <span class="font-bold text-amber-800 dark:text-amber-300">APRON ACCUMULATION STATUS:</span>
                                <span class="text-slate-600 dark:text-slate-400 ml-1"
                                      x-text="dau10bDwell?.max_accumulation_hour ? ('Puncak akumulasi apron terjadi pada jam ' + dau10bDwell.max_accumulation_hour + '. Pesawat parkir menumpuk pada parking stand.') : 'Perputaran apron terkendali seimbang antara kedatangan (DTG) dan keberangkatan (BRK).'"></span>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded font-mono font-bold text-[10px] uppercase tracking-wider bg-amber-200 dark:bg-amber-900 text-amber-900 dark:text-amber-200 whitespace-nowrap"
                              x-text="dau10bDwell?.max_accumulation_hour ? 'STAND STRESS' : 'BALANCED'"></span>
                    </div>

                    {{-- Hourly Net Delta Directional Bars --}}
                    <div class="pt-2">
                        <div class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400 mb-2">Net Hourly Dwell (Delta = DTG - BRK)</div>
                        <div class="h-44 w-full flex items-center gap-1 sm:gap-2 px-1 overflow-x-auto border-b border-t border-slate-100 dark:border-slate-800 py-3">
                            <template x-for="(d, dIdx) in (dau10bDwell?.hourly_dwell || [])" :key="'dwell-' + dIdx">
                                <div class="flex-1 min-w-[28px] sm:min-w-[36px] flex flex-col items-center justify-center h-full group relative cursor-pointer"
                                     @click="setHourFilter(d.hour)">
                                    {{-- Tooltip --}}
                                    <div class="opacity-0 group-hover:opacity-100 transition pointer-events-none absolute bottom-full mb-2 z-30 bg-slate-900 text-white text-[10px] font-mono rounded-lg px-2.5 py-1.5 shadow-xl whitespace-nowrap">
                                        <div class="font-bold" x-text="'Jam: ' + d.hour"></div>
                                        <div x-text="'Block On (DTG): ' + d.block_on"></div>
                                        <div x-text="'Block Off (BRK): ' + d.block_off"></div>
                                        <div class="font-bold" :class="d.net_delta > 0 ? 'text-amber-400' : (d.net_delta < 0 ? 'text-blue-400' : 'text-slate-300')"
                                             x-text="'Net Delta: ' + (d.net_delta > 0 ? ('+' + d.net_delta) : d.net_delta) + ' A/C'"></div>
                                    </div>

                                    {{-- Bar positive or negative relative to center line --}}
                                    <div class="w-full flex flex-col items-center justify-center h-28 relative">
                                        <div class="w-full border-t border-slate-300 dark:border-slate-700 absolute top-1/2 left-0 z-10"></div>
                                        {{-- Positive bar (upwards) --}}
                                        <div class="w-2.5 sm:w-3.5 rounded-t-sm transition-all"
                                             :class="d.is_accumulation_alert ? 'bg-rose-500 hover:bg-rose-400' : 'bg-amber-500 hover:bg-amber-400'"
                                             :style="'height: ' + (d.net_delta > 0 ? Math.min(50, Math.abs(d.net_delta) * 10) : 0) + 'px; margin-bottom: ' + (d.net_delta > 0 ? '0' : '0') + '; transform: translateY(-50%);'"></div>
                                        {{-- Negative bar (downwards) --}}
                                        <div class="w-2.5 sm:w-3.5 bg-blue-600 hover:bg-blue-500 rounded-b-sm transition-all"
                                             :style="'height: ' + (d.net_delta < 0 ? Math.min(50, Math.abs(d.net_delta) * 10) : 0) + 'px; transform: translateY(50%);'"></div>
                                    </div>

                                    <div class="text-[9px] font-mono text-slate-500 mt-1 truncate w-full text-center"
                                         x-text="d.hour.split(' - ')[0] || d.hour"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- 2. Gate Operations Timeline Chart & Peaks --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Gate Operations Timeline</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white"
                                x-text="selectedMetric === 'passenger' ? 'BLOCK ON (DTG) VS BLOCK OFF (BRK) HOURLY PASSENGER COMPARISON' : 'BLOCK ON (DTG) VS BLOCK OFF (BRK) HOURLY AIRCRAFT COMPARISON'">
                                BLOCK ON (DTG) VS BLOCK OFF (BRK) HOURLY COMPARISON
                            </h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span x-show="filterOperation !== 'BLOCK_OFF' && filterDirection !== 'DEPARTURE'" class="flex items-center gap-1">
                                <span class="w-3 h-3 rounded bg-purple-600"></span>
                                <span x-text="selectedMetric === 'passenger' ? 'Block On (DTG) — Passenger' : 'Block On (DTG)'">Block On (DTG)</span>
                            </span>
                            <span x-show="filterOperation !== 'BLOCK_ON' && filterDirection !== 'ARRIVAL'" class="flex items-center gap-1">
                                <span class="w-3 h-3 rounded bg-amber-500"></span>
                                <span x-text="selectedMetric === 'passenger' ? 'Block Off (BRK) — Passenger' : 'Block Off (BRK)'">Block Off (BRK)</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300"
                                  x-show="filterOperation !== 'BLOCK_OFF' && filterDirection !== 'DEPARTURE' && peaks.peak_block_on_hour && peaks.peak_block_on_hour !== '—'">
                                Peak On: <span x-text="(peaks.peak_block_on_hour || '—') + ' (' + formatNumber(peaks.peak_block_on) + (selectedMetric === 'passenger' ? ' PAX' : ' A/C') + ')'"></span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                  x-show="filterOperation !== 'BLOCK_ON' && filterDirection !== 'ARRIVAL' && peaks.peak_block_off_hour && peaks.peak_block_off_hour !== '—'">
                                Peak Off: <span x-text="(peaks.peak_block_off_hour || '—') + ' (' + formatNumber(peaks.peak_block_off) + (selectedMetric === 'passenger' ? ' PAX' : ' A/C') + ')'"></span>
                            </span>
                        </div>
                    </div>

                    <div x-show="dau10bNoData" class="flex flex-col items-center justify-center h-64 text-slate-400 dark:text-slate-500 gap-2 text-center px-4">
                        <div class="text-sm font-bold tracking-wide">NO DATA AVAILABLE</div>
                    </div>

                    <div x-show="!dau10bNoData" class="relative h-72 sm:h-84 w-full">
                        <canvas id="dau10bBlockChart" class="w-full h-full"></canvas>
                    </div>

                    {{-- Hourly Summary Table --}}
                    <div x-show="!dau10bNoData && activeHourlyDistribution.length > 0" class="border-t border-slate-100 dark:border-slate-800 pt-4 mt-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                                 x-text="selectedMetric === 'passenger' ? 'Hourly Passenger Summary (DTG vs BRK)' : 'Hourly Aircraft Summary (DTG vs BRK)'">
                                Hourly Summary (DTG vs BRK)
                            </div>
                            <div class="text-[10px] font-mono text-slate-400">
                                Unit: <span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="selectedMetric === 'passenger' ? 'PAX' : 'A/C'"></span>
                            </div>
                        </div>
                        <div class="overflow-x-auto max-h-60 border border-slate-100 dark:border-slate-800 rounded-lg">
                            <table class="w-full text-left border-collapse text-xs font-mono">
                                <thead class="bg-slate-50 dark:bg-navy-800 sticky top-0 text-[10px] uppercase font-bold text-slate-500 dark:text-slate-400">
                                    <tr>
                                        <th class="px-3 py-2">Hour</th>
                                        <th class="px-3 py-2 text-right" x-text="selectedMetric === 'passenger' ? 'Block On (DTG) Pax' : 'Block On (DTG)'">Block On (DTG)</th>
                                        <th class="px-3 py-2 text-right" x-text="selectedMetric === 'passenger' ? 'Block Off (BRK) Pax' : 'Block Off (BRK)'">Block Off (BRK)</th>
                                        <th class="px-3 py-2 text-right font-black" x-text="selectedMetric === 'passenger' ? 'Total Pax' : 'Total Acft'">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    <template x-for="(h, hIdx) in activeHourlyDistribution" :key="hIdx">
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-navy-800/40">
                                            <td class="px-3 py-1.5 font-bold text-slate-700 dark:text-slate-300" x-text="h.hour"></td>
                                            <td class="px-3 py-1.5 text-right text-purple-600 dark:text-purple-400 font-bold"
                                                x-text="formatNumber(selectedMetric === 'passenger' ? h.passenger_arrival : h.aircraft_arrival)"></td>
                                            <td class="px-3 py-1.5 text-right text-amber-600 dark:text-amber-400 font-bold"
                                                x-text="formatNumber(selectedMetric === 'passenger' ? h.passenger_departure : h.aircraft_departure)"></td>
                                            <td class="px-3 py-1.5 text-right font-black text-slate-900 dark:text-white"
                                                x-text="formatNumber(selectedMetric === 'passenger' ? (Number(h.passenger_arrival || 0) + Number(h.passenger_departure || 0)) : (Number(h.aircraft_arrival || 0) + Number(h.aircraft_departure || 0)))"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-11: DATA STATISTIK 1 --}}
        @if ($reportType === 'DAU11')
            <div class="space-y-6">
                {{-- 1. 2x2 Operational Traffic Matrix & CIQ Facility Demand Indicator --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- 2x2 Traffic Matrix Quadrants --}}
                    <div class="lg:col-span-2 glass-card p-5 sm:p-6 shadow-md space-y-4">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Operational Matrix</div>
                                <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">2×2 TRAFFIC DEMAND QUADRANTS</h2>
                            </div>
                            <span class="text-xs font-mono text-slate-400">Dom/Int × ARR/DEP</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3.5 pt-1">
                            {{-- Dom ARR --}}
                            <div class="p-4 rounded-xl bg-blue-50/70 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 space-y-1">
                                <div class="text-[10px] font-black uppercase tracking-wider text-blue-700 dark:text-blue-300">DOMESTIC ARRIVAL</div>
                                <div class="text-xl font-black text-blue-800 dark:text-blue-200 font-mono"
                                     x-text="formatNumber(dau11TrafficMatrix?.matrix?.dom_arr?.movements || 0) + ' A/C'"></div>
                                <div class="text-xs font-mono text-slate-600 dark:text-slate-400"
                                     x-text="formatNumber(dau11TrafficMatrix?.matrix?.dom_arr?.passengers || 0) + ' Pax'"></div>
                            </div>
                            {{-- Dom DEP --}}
                            <div class="p-4 rounded-xl bg-cyan-50/70 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800 space-y-1">
                                <div class="text-[10px] font-black uppercase tracking-wider text-cyan-700 dark:text-cyan-300">DOMESTIC DEPARTURE</div>
                                <div class="text-xl font-black text-cyan-800 dark:text-cyan-200 font-mono"
                                     x-text="formatNumber(dau11TrafficMatrix?.matrix?.dom_dep?.movements || 0) + ' A/C'"></div>
                                <div class="text-xs font-mono text-slate-600 dark:text-slate-400"
                                     x-text="formatNumber(dau11TrafficMatrix?.matrix?.dom_dep?.passengers || 0) + ' Pax'"></div>
                            </div>
                            {{-- Int ARR --}}
                            <div class="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 space-y-1">
                                <div class="text-[10px] font-black uppercase tracking-wider text-indigo-700 dark:text-indigo-300">INTERNATIONAL ARRIVAL</div>
                                <div class="text-xl font-black text-indigo-800 dark:text-indigo-200 font-mono"
                                     x-text="formatNumber(dau11TrafficMatrix?.matrix?.int_arr?.movements || 0) + ' A/C'"></div>
                                <div class="text-xs font-mono text-slate-600 dark:text-slate-400"
                                     x-text="formatNumber(dau11TrafficMatrix?.matrix?.int_arr?.passengers || 0) + ' Pax'"></div>
                            </div>
                            {{-- Int DEP --}}
                            <div class="p-4 rounded-xl bg-purple-50/70 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800 space-y-1">
                                <div class="text-[10px] font-black uppercase tracking-wider text-purple-700 dark:text-purple-300">INTERNATIONAL DEPARTURE</div>
                                <div class="text-xl font-black text-purple-800 dark:text-purple-200 font-mono"
                                     x-text="formatNumber(dau11TrafficMatrix?.matrix?.int_dep?.movements || 0) + ' A/C'"></div>
                                <div class="text-xs font-mono text-slate-600 dark:text-slate-400"
                                     x-text="formatNumber(dau11TrafficMatrix?.matrix?.int_dep?.passengers || 0) + ' Pax'"></div>
                            </div>
                        </div>
                    </div>

                    {{-- CIQ Facility Demand Indicator --}}
                    <div class="glass-card p-5 sm:p-6 shadow-md space-y-4 flex flex-col justify-between">
                        <div class="space-y-2">
                            <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Border Control Readiness</div>
                                    <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">CIQ FACILITY DEMAND</h2>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold"
                                      :class="{
                                          'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': dau11TrafficMatrix?.ciq_demand?.demand_level === 'LIGHT',
                                          'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': dau11TrafficMatrix?.ciq_demand?.demand_level === 'MODERATE',
                                          'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': dau11TrafficMatrix?.ciq_demand?.demand_level === 'PEAK'
                                      }"
                                      x-text="dau11TrafficMatrix?.ciq_demand?.ciq_status || 'STANDARD'"></span>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 space-y-2 text-center">
                                <div class="text-[10px] font-bold uppercase text-slate-400">International Passenger Share</div>
                                <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400 font-mono"
                                     x-text="(dau11TrafficMatrix?.ciq_demand?.international_share_pct || 0) + '%'"></div>
                                <p class="text-[11px] text-slate-500 leading-relaxed"
                                   x-text="dau11TrafficMatrix?.ciq_demand?.description || 'Tingkat permintaan fasilitas Customs, Immigration, dan Quarantine.'"></p>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-400 font-mono border-t border-slate-100 dark:border-slate-800 pt-2">
                            Customs, Immigration &amp; Quarantine Demand Rate
                        </div>
                    </div>
                </div>

                {{-- 2. Flow and Scope Charts --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 glass-card p-5 sm:p-6 shadow-md space-y-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Traffic Stream Breakdown</div>
                                <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">DOMESTIC VS INTERNATIONAL FLOW (ARR &amp; DEP)</h2>
                            </div>
                            <span class="text-xs font-mono text-slate-400">Direct • Transit • Transfer</span>
                        </div>
                        <div class="relative h-72 sm:h-80 w-full">
                            <canvas id="dau11FlowChart"></canvas>
                        </div>
                    </div>

                    <div class="glass-card p-5 sm:p-6 shadow-md space-y-4 flex flex-col justify-between">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Scope Composition</div>
                            <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">DOMESTIC VS INT SHARE</h2>
                        </div>
                        <div class="relative h-56 w-full flex items-center justify-center">
                            <canvas id="dau11Donut"></canvas>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-center font-mono">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Total Passengers</div>
                            <div class="text-lg font-black text-emerald-600 mt-0.5" x-text="formatNumber(activeSummary.passenger_total)"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-12: DATA STATISTIK 2 --}}
        @if ($reportType === 'DAU12')
            <div class="space-y-6">
                {{-- 1. 2x2 Directional Matrix & CIQ Demand Indicator --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- 2x2 Traffic Matrix Quadrants --}}
                    <div class="lg:col-span-2 glass-card p-5 sm:p-6 shadow-md space-y-4">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Directional Matrix</div>
                                <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">2×2 DIRECTIONAL OPERATIONAL MATRIX</h2>
                            </div>
                            <span class="text-xs font-mono text-slate-400">ARR/DEP × DOM/INT</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3.5 pt-1">
                            <div class="p-4 rounded-xl bg-blue-50/70 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 space-y-1">
                                <div class="text-[10px] font-black uppercase tracking-wider text-blue-700 dark:text-blue-300">DOMESTIC ARRIVAL</div>
                                <div class="text-xl font-black text-blue-800 dark:text-blue-200 font-mono"
                                     x-text="formatNumber(dau12TrafficMatrix?.matrix?.dom_arr?.movements || dau12MatrixSummary.arr_dom || 0) + ' A/C'"></div>
                                <div class="text-xs font-mono text-slate-600 dark:text-slate-400"
                                     x-text="formatNumber(dau12TrafficMatrix?.matrix?.dom_arr?.passengers || 0) + ' Pax'"></div>
                            </div>
                            <div class="p-4 rounded-xl bg-cyan-50/70 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800 space-y-1">
                                <div class="text-[10px] font-black uppercase tracking-wider text-cyan-700 dark:text-cyan-300">DOMESTIC DEPARTURE</div>
                                <div class="text-xl font-black text-cyan-800 dark:text-cyan-200 font-mono"
                                     x-text="formatNumber(dau12TrafficMatrix?.matrix?.dom_dep?.movements || dau12MatrixSummary.dep_dom || 0) + ' A/C'"></div>
                                <div class="text-xs font-mono text-slate-600 dark:text-slate-400"
                                     x-text="formatNumber(dau12TrafficMatrix?.matrix?.dom_dep?.passengers || 0) + ' Pax'"></div>
                            </div>
                            <div class="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 space-y-1">
                                <div class="text-[10px] font-black uppercase tracking-wider text-indigo-700 dark:text-indigo-300">INTERNATIONAL ARRIVAL</div>
                                <div class="text-xl font-black text-indigo-800 dark:text-indigo-200 font-mono"
                                     x-text="formatNumber(dau12TrafficMatrix?.matrix?.int_arr?.movements || dau12MatrixSummary.arr_int || 0) + ' A/C'"></div>
                                <div class="text-xs font-mono text-slate-600 dark:text-slate-400"
                                     x-text="formatNumber(dau12TrafficMatrix?.matrix?.int_arr?.passengers || 0) + ' Pax'"></div>
                            </div>
                            <div class="p-4 rounded-xl bg-purple-50/70 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800 space-y-1">
                                <div class="text-[10px] font-black uppercase tracking-wider text-purple-700 dark:text-purple-300">INTERNATIONAL DEPARTURE</div>
                                <div class="text-xl font-black text-purple-800 dark:text-purple-200 font-mono"
                                     x-text="formatNumber(dau12TrafficMatrix?.matrix?.int_dep?.movements || dau12MatrixSummary.dep_int || 0) + ' A/C'"></div>
                                <div class="text-xs font-mono text-slate-600 dark:text-slate-400"
                                     x-text="formatNumber(dau12TrafficMatrix?.matrix?.int_dep?.passengers || 0) + ' Pax'"></div>
                            </div>
                        </div>
                    </div>

                    {{-- CIQ Facility Demand Indicator --}}
                    <div class="glass-card p-5 sm:p-6 shadow-md space-y-4 flex flex-col justify-between">
                        <div class="space-y-2">
                            <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">CIQ Readiness</div>
                                    <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">CIQ STAFFING DEMAND</h2>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold"
                                      :class="{
                                          'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': dau12TrafficMatrix?.ciq_demand?.demand_level === 'LIGHT',
                                          'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': dau12TrafficMatrix?.ciq_demand?.demand_level === 'MODERATE',
                                          'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': dau12TrafficMatrix?.ciq_demand?.demand_level === 'PEAK'
                                      }"
                                      x-text="dau12TrafficMatrix?.ciq_demand?.ciq_status || 'STANDARD'"></span>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 space-y-2 text-center">
                                <div class="text-[10px] font-bold uppercase text-slate-400">International Passenger Share</div>
                                <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400 font-mono"
                                     x-text="(dau12TrafficMatrix?.ciq_demand?.international_share_pct || 0) + '%'"></div>
                                <p class="text-[11px] text-slate-500 leading-relaxed"
                                   x-text="dau12TrafficMatrix?.ciq_demand?.description || 'Evaluasi rasio pergerakan internasional terhadap kapasitas CIQ.'"></p>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-400 font-mono border-t border-slate-100 dark:border-slate-800 pt-2">
                            Customs, Immigration &amp; Quarantine Demand Rate
                        </div>
                    </div>
                </div>

                {{-- 2. Directional Matrix Grouped Chart & Analytical Summary --}}
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Directional Matrix Comparison</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white"
                                x-text="'ARRIVAL & DEPARTURE BY DOMESTIC VS INTERNATIONAL — ' + (selectedMetric === 'passenger' ? 'PASSENGER' : 'AIRCRAFT')">
                                ARRIVAL &amp; DEPARTURE BY DOMESTIC VS INTERNATIONAL — AIRCRAFT
                            </h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1" x-show="filterFlightType !== 'INT'"><span class="w-3 h-3 rounded bg-blue-600"></span> Domestic</span>
                            <span class="flex items-center gap-1" x-show="filterFlightType !== 'DOM'"><span class="w-3 h-3 rounded bg-indigo-600"></span> International</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300">
                                Unit: <span class="text-aviation-600 dark:text-aviation-400" x-text="selectedMetric === 'passenger' ? 'PAX' : 'A/C'"></span>
                            </span>
                        </div>
                    </div>

                    <div x-show="dau12NoData" class="flex flex-col items-center justify-center h-64 text-slate-400 dark:text-slate-500 gap-2 text-center px-4">
                        <div class="text-sm font-bold tracking-wide">NO DATA AVAILABLE</div>
                    </div>

                    <div x-show="!dau12NoData" class="relative h-72 sm:h-84 w-full">
                        <canvas id="dau12GroupedChart" class="w-full h-full"></canvas>
                    </div>

                    {{-- Directional Matrix Analytical Summary Table --}}
                    <div x-show="!dau12NoData" class="border-t border-slate-100 dark:border-slate-800 pt-4 mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Directional Matrix Summary (<span x-text="selectedMetric === 'passenger' ? 'Passenger Traffic' : 'Aircraft Movements'"></span>)
                            </div>
                            <div class="text-[10px] font-mono text-slate-400">
                                Unit: <span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="selectedMetric === 'passenger' ? 'PAX' : 'A/C'"></span>
                            </div>
                        </div>
                        <div class="overflow-x-auto border border-slate-100 dark:border-slate-800 rounded-lg">
                            <table class="w-full text-left border-collapse text-xs font-mono">
                                <thead class="bg-slate-50 dark:bg-navy-800 text-[10px] uppercase font-bold text-slate-500 dark:text-slate-400">
                                    <tr>
                                        <th class="px-3 py-2">Direction</th>
                                        <th class="px-3 py-2 text-right" x-show="filterFlightType !== 'INT'">Domestic</th>
                                        <th class="px-3 py-2 text-right" x-show="filterFlightType !== 'DOM'">International</th>
                                        <th class="px-3 py-2 text-right font-black">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr x-show="filterDirection !== 'DEPARTURE'" class="hover:bg-slate-50/50 dark:hover:bg-navy-800/50">
                                        <td class="px-3 py-2 font-bold text-slate-700 dark:text-slate-300">ARRIVAL</td>
                                        <td class="px-3 py-2 text-right text-blue-600 dark:text-blue-400" x-show="filterFlightType !== 'INT'" x-text="formatNumber(dau12MatrixSummary.arr_dom)"></td>
                                        <td class="px-3 py-2 text-right text-indigo-600 dark:text-indigo-400" x-show="filterFlightType !== 'DOM'" x-text="formatNumber(dau12MatrixSummary.arr_int)"></td>
                                        <td class="px-3 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(dau12MatrixSummary.arr_tot)"></td>
                                    </tr>
                                    <tr x-show="filterDirection !== 'ARRIVAL'" class="hover:bg-slate-50/50 dark:hover:bg-navy-800/50">
                                        <td class="px-3 py-2 font-bold text-slate-700 dark:text-slate-300">DEPARTURE</td>
                                        <td class="px-3 py-2 text-right text-blue-600 dark:text-blue-400" x-show="filterFlightType !== 'INT'" x-text="formatNumber(dau12MatrixSummary.dep_dom)"></td>
                                        <td class="px-3 py-2 text-right text-indigo-600 dark:text-indigo-400" x-show="filterFlightType !== 'DOM'" x-text="formatNumber(dau12MatrixSummary.dep_int)"></td>
                                        <td class="px-3 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(dau12MatrixSummary.dep_tot)"></td>
                                    </tr>
                                    <tr class="bg-slate-50/60 dark:bg-navy-800/60 font-black">
                                        <td class="px-3 py-2 text-slate-900 dark:text-white">TOTAL</td>
                                        <td class="px-3 py-2 text-right text-blue-600 dark:text-blue-400" x-show="filterFlightType !== 'INT'" x-text="formatNumber(dau12MatrixSummary.tot_dom)"></td>
                                        <td class="px-3 py-2 text-right text-indigo-600 dark:text-indigo-400" x-show="filterFlightType !== 'DOM'" x-text="formatNumber(dau12MatrixSummary.tot_int)"></td>
                                        <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400" x-text="formatNumber(dau12MatrixSummary.grand_tot)"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endifSummary.tot_dom)"></td>
                                    <td class="px-3 py-2 text-right text-indigo-600 dark:text-indigo-400" x-show="filterFlightType !== 'DOM'" x-text="formatNumber(dau12MatrixSummary.tot_int)"></td>
                                    <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400" x-text="formatNumber(dau12MatrixSummary.grand_tot)"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══ 5. DETAIL ANALYTICAL TABLE ═══════════════════════════════════════ --}}
        <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                        <span>DETAILED OPERATIONAL RECORDS</span>
                        <span class="text-xs font-mono font-bold text-slate-400" x-text="'(' + formatNumber(filteredRecords.length) + ' rows)'"></span>
                    </h3>
                    <p class="text-xs text-slate-500">Authentic parsed records reflecting all active filters.</p>
                </div>
                <div class="text-xs font-mono text-slate-400">
                    Showing page <span class="font-bold text-slate-700 dark:text-slate-300" x-text="currentPage"></span> of <span class="font-bold text-slate-700 dark:text-slate-300" x-text="totalPages"></span>
                </div>
            </div>

            <template x-if="filteredRecords.length === 0">
                <div class="text-center py-12 space-y-3 bg-slate-50 dark:bg-navy-900/50 rounded-xl border border-dashed border-slate-300 dark:border-slate-800">
                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-navy-800 flex items-center justify-center mx-auto text-slate-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="text-sm font-bold text-slate-700 dark:text-slate-300">NO DATA FOR SELECTED FILTERS</div>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">There are no operational records matching the selected filter combination in the authentic report.</p>
                    <button type="button" @click="resetFilters()"
                            class="px-4 py-1.5 text-xs font-bold text-aviation-600 bg-aviation-50 hover:bg-aviation-100 dark:bg-aviation-950 dark:hover:bg-aviation-900 rounded-lg transition cursor-pointer">
                        Reset Filters
                    </button>
                </div>
            </template>

            <template x-if="filteredRecords.length > 0">
                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl max-h-[550px]">
                    <table class="w-full text-left text-xs font-sans border-collapse">
                        <thead class="bg-slate-50 dark:bg-navy-900 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 sticky top-0 z-20 shadow-2xs">
                            <tr>
                                <th class="px-3 py-2.5">#</th>
                                @if ($reportType === 'DAU1')
                                    {{-- AIRCRAFT HEADERS --}}
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th @click="sortBy('airport_route')" class="px-3 py-2.5 cursor-pointer">Bandara Asal / Tujuan</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th @click="sortBy('flight_number')" class="px-2.5 py-2.5 cursor-pointer">Flight No</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5">Status</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5">Tipe Pesawat</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right">Seat Cap</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right">ARR Acft</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right">DEP Acft</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right">Bagasi (Kg)</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right">Kargo (Kg)</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right">POS (Kg)</th>
                                    </template>

                                    {{-- PASSENGER HEADERS --}}
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th @click="sortBy('flight_number')" class="px-2.5 py-2.5 cursor-pointer">Flight No</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th @click="sortBy('airline')" class="px-3 py-2.5 cursor-pointer">Airline</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th @click="sortBy('airport_route')" class="px-3 py-2.5 cursor-pointer">Bandara / Rute</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-blue-600 font-bold">Dewasa (Adult)</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Anak (Child)</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-amber-600 font-bold">Bayi (Infant)</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right font-black text-slate-900 dark:text-white">Total Pax</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-slate-500">ARR Pax</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-slate-500">DEP Pax</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-slate-400">Transit</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-slate-400">Transfer</th>
                                    </template>
                                @elseif ($reportType === 'DAU2')
                                    <th @click="sortBy('category')" class="px-3 py-2.5 cursor-pointer">Jenis Penerbangan</th>
                                    <template x-if="selectedMetric === 'aircraft'">
                                        <th class="px-2.5 py-2.5 text-right">ARR Acft</th>
                                    </template>
                                    <template x-if="selectedMetric === 'aircraft'">
                                        <th class="px-2.5 py-2.5 text-right">DEP Acft</th>
                                    </template>
                                    <template x-if="selectedMetric === 'aircraft'">
                                        <th class="px-2.5 py-2.5 text-right font-bold text-slate-900 dark:text-white">Total Acft</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-emerald-600">ARR Pax</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-blue-600">DEP Pax</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right font-bold text-emerald-600">Total Pax</th>
                                    </template>
                                    <template x-if="selectedMetric === 'baggage'">
                                        <th class="px-2.5 py-2.5 text-right font-bold text-rose-600">Bagasi (Kg)</th>
                                    </template>
                                    <template x-if="selectedMetric === 'cargo'">
                                        <th class="px-2.5 py-2.5 text-right font-bold text-teal-600">Kargo (Kg)</th>
                                    </template>
                                    <template x-if="selectedMetric === 'pos'">
                                        <th class="px-2.5 py-2.5 text-right font-bold text-slate-600">POS (Kg)</th>
                                    </template>
                                    <th class="px-2.5 py-2.5 text-right text-slate-400">Awak</th>
                                @elseif ($reportType === 'DAU3')
                                    <th class="px-3 py-2.5">Status Usaha</th>
                                    <th class="px-3 py-2.5">Jenis Penerbangan</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold text-emerald-600">Total Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo</th>
                                @elseif ($reportType === 'DAU4')
                                    <th @click="sortBy('airport')" class="px-3 py-2.5 cursor-pointer">Airport</th>
                                    <th class="px-2.5 py-2.5">IATA</th>
                                    <th class="px-3 py-2.5">Kota</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">Awak</th>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo</th>
                                @elseif ($reportType === 'DAU4A')
                                    <th @click="sortBy('operator_name')" class="px-3 py-2.5 cursor-pointer">Operator / Airline</th>
                                    <th class="px-2 py-2.5">Kode</th>
                                    <th class="px-3 py-2.5">Airport</th>
                                    <th class="px-2 py-2.5">IATA</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">Awak</th>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo</th>
                                @elseif ($reportType === 'DAU4B')
                                    <th class="px-3 py-2.5 text-left">Kota / Rute</th>
                                    <th class="px-2 py-2.5">IATA</th>
                                    <th class="px-3 py-2.5 text-left">Airline / Operator</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold text-emerald-600">Total Pax</th>
                                @elseif ($reportType === 'DAU5' || $reportType === 'DAU5C')
                                    <th @click="sortBy('airline')" class="px-3 py-2.5 cursor-pointer">Airline / Operator</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP Pax</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">Awak</th>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi (Kg)</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo (Kg)</th>
                                @elseif ($reportType === 'DAU5A')
                                    <th @click="sortBy('airline')" class="px-3 py-2.5 cursor-pointer">Airline / Operator</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold text-emerald-600">Total Pax</th>
                                    <th class="px-2.5 py-2.5 text-right text-blue-600 font-bold">Operating Crew</th>
                                    <th class="px-2.5 py-2.5 text-right text-purple-600">ARR Ex Crew</th>
                                    <th class="px-2.5 py-2.5 text-right text-purple-600">DEP Ex Crew</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold text-purple-600">Total Extra Crew</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Awak</th>
                                @elseif ($reportType === 'DAU5B')
                                    <th class="px-3 py-2.5">Terminal</th>
                                    <th @click="sortBy('airline')" class="px-3 py-2.5 cursor-pointer">Airline</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold text-emerald-600">Total Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo</th>
                                @elseif ($reportType === 'DAU6')
                                    <th @click="sortBy('aircraft_type')" class="px-3 py-2.5 cursor-pointer">Tipe Pesawat</th>
                                    <th class="px-2.5 py-2.5">Kategori</th>
                                    <th class="px-2 py-2.5">WTC</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">Awak</th>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo</th>
                                @elseif ($reportType === 'DAU10A')
                                    <th @click="sortBy('hour')" class="px-3 py-2.5 cursor-pointer">Hour</th>
                                    <th class="px-2.5 py-2.5">Terminal</th>
                                    {{-- AIRCRAFT HEADERS --}}
                                    <template x-if="selectedMetric === 'aircraft'">
                                        <th class="px-2.5 py-2.5 text-right text-amber-600">Acft ARR</th>
                                    </template>
                                    <template x-if="selectedMetric === 'aircraft'">
                                        <th class="px-2.5 py-2.5 text-right text-blue-600">Acft DEP</th>
                                    </template>
                                    <template x-if="selectedMetric === 'aircraft'">
                                        <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    </template>
                                    {{-- PASSENGER HEADERS --}}
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-emerald-600">Pax ARR</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-blue-600">Pax DEP</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-slate-500">Transit</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-slate-500">Transfer</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right font-bold text-amber-600">Total Pax</th>
                                    </template>
                                    {{-- CREW HEADERS --}}
                                    <template x-if="selectedMetric === 'crew'">
                                        <th class="px-2.5 py-2.5 text-right text-blue-600">Operating Crew (ARR)</th>
                                    </template>
                                    <template x-if="selectedMetric === 'crew'">
                                        <th class="px-2.5 py-2.5 text-right text-purple-600">Extra Crew (DEP)</th>
                                    </template>
                                    <template x-if="selectedMetric === 'crew'">
                                        <th class="px-2.5 py-2.5 text-right font-bold">Total Crew</th>
                                    </template>
                                @elseif ($reportType === 'DAU10B')
                                    <th @click="sortBy('hour')" class="px-3 py-2.5 cursor-pointer">Hour</th>
                                    <th class="px-2.5 py-2.5">Terminal</th>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-purple-600 font-bold">Pax On (DTG)</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-amber-500 font-bold">Pax Off (BRK)</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-slate-500">Transit</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-slate-500">Transfer</th>
                                    </template>
                                    <template x-if="selectedMetric === 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-purple-600 font-bold">Acft On (DTG)</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right text-amber-500 font-bold">Acft Off (BRK)</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    </template>
                                    <template x-if="selectedMetric !== 'passenger'">
                                        <th class="px-2 py-2.5 text-right">Awak</th>
                                    </template>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo</th>
                                @elseif ($reportType === 'DAU10')
                                    <th @click="sortBy('hour')" class="px-3 py-2.5 cursor-pointer">Hour</th>
                                    <th class="px-2.5 py-2.5">Terminal</th>
                                    <th class="px-2.5 py-2.5 text-right">Acft ARR</th>
                                    <th class="px-2.5 py-2.5 text-right">Acft DEP</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">Pax ARR</th>
                                    <th class="px-2.5 py-2.5 text-right">Pax DEP</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                    <th class="px-2 py-2.5 text-right">Awak</th>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo</th>
                                @elseif ($reportType === 'DAU11')
                                    <th class="px-3 py-2.5">Tanggal</th>
                                    <th class="px-2.5 py-2.5 text-right" x-text="selectedMetric === 'passenger' ? 'INT ARR (Pax)' : 'INT ARR'"></th>
                                    <th class="px-2.5 py-2.5 text-right" x-text="selectedMetric === 'passenger' ? 'INT DEP (Pax)' : 'INT DEP'"></th>
                                    <th class="px-2.5 py-2.5 text-right" x-text="selectedMetric === 'passenger' ? 'DOM ARR (Pax)' : 'DOM ARR'"></th>
                                    <th class="px-2.5 py-2.5 text-right" x-text="selectedMetric === 'passenger' ? 'DOM DEP (Pax)' : 'DOM DEP'"></th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                @elseif ($reportType === 'DAU12')
                                    <th class="px-3 py-2.5">Tanggal</th>
                                    <th class="px-2.5 py-2.5 text-right" x-text="selectedMetric === 'passenger' ? 'ARR DOM (Pax)' : 'ARR DOM'"></th>
                                    <th class="px-2.5 py-2.5 text-right" x-text="selectedMetric === 'passenger' ? 'ARR INT (Pax)' : 'ARR INT'"></th>
                                    <th class="px-2.5 py-2.5 text-right font-bold" x-text="selectedMetric === 'passenger' ? 'ARR Tot (Pax)' : 'ARR Total'"></th>
                                    <th class="px-2.5 py-2.5 text-right" x-text="selectedMetric === 'passenger' ? 'DEP DOM (Pax)' : 'DEP DOM'"></th>
                                    <th class="px-2.5 py-2.5 text-right" x-text="selectedMetric === 'passenger' ? 'DEP INT (Pax)' : 'DEP INT'"></th>
                                    <th class="px-2.5 py-2.5 text-right font-bold" x-text="selectedMetric === 'passenger' ? 'DEP Tot (Pax)' : 'DEP Total'"></th>
                                    <th class="px-2.5 py-2.5 text-right font-black">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-black">Total Pax</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono text-[11px]">
                            <template x-for="(row, idx) in paginatedRecords" :key="idx">
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-navy-800/50 transition">
                                    <td class="px-3 py-2 font-bold text-slate-400" x-text="startIndex + idx + 1"></td>
                                    @if ($reportType === 'DAU1')
                                        {{-- AIRCRAFT ROWS --}}
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.airport_route || row.origin || '—'"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 font-bold text-aviation-600" x-text="row.flight_number || '—'"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-[10px]" x-text="row.schedule_type || '—'"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2" x-text="row.aircraft_type || '—'"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-400" x-text="formatNumber(row.seat_capacity)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.pos)"></td>
                                        </template>

                                        {{-- PASSENGER ROWS --}}
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 font-bold text-aviation-600" x-text="row.flight_number || '—'"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-3 py-2 font-sans text-slate-800 dark:text-slate-200" x-text="row.airline || '—'"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.airport_route || row.origin || '—'"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-bold text-blue-600" x-text="formatNumber(row.passenger_adult ?? (row.adult ?? ((row.arr_adult || 0) + (row.dep_adult || 0))))"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_child ?? (row.child ?? ((row.arr_child || 0) + (row.dep_child || 0))))"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-bold text-amber-600" x-text="formatNumber(row.passenger_infant ?? (row.infant ?? ((row.arr_infant || 0) + (row.dep_infant || 0))))"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-black text-slate-900 dark:text-white" x-text="formatNumber(row.passenger_total)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.passenger_arrival)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.passenger_departure)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-400" x-text="formatNumber(row.passenger_transit)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-400" x-text="formatNumber(row.passenger_transfer)"></td>
                                        </template>
                                    @elseif ($reportType === 'DAU2')
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.category || '—'"></td>
                                        <template x-if="selectedMetric === 'aircraft'">
                                            <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'aircraft'">
                                            <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'aircraft'">
                                            <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-emerald-600" x-text="formatNumber(row.passenger_arrival)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-blue-600" x-text="formatNumber(row.passenger_departure)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'baggage'">
                                            <td class="px-2.5 py-2 text-right font-bold text-rose-600" x-text="formatNumber(row.baggage)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'cargo'">
                                            <td class="px-2.5 py-2 text-right font-bold text-teal-600" x-text="formatNumber(row.cargo)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'pos'">
                                            <td class="px-2.5 py-2 text-right font-bold text-slate-600" x-text="formatNumber(row.pos)"></td>
                                        </template>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.crew_total)"></td>
                                    @elseif ($reportType === 'DAU3')
                                        <td class="px-3 py-2 font-sans font-bold text-aviation-600" x-text="row.section || '—'"></td>
                                        <td class="px-3 py-2 font-sans" x-text="row.category || '—'"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                    @elseif ($reportType === 'DAU4')
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.airport || '—'"></td>
                                        <td class="px-2.5 py-2 font-bold text-aviation-600" x-text="row.city_code || '—'"></td>
                                        <td class="px-3 py-2 font-sans" x-text="row.city || '—'"></td>
                                        <td class="px-2.5 py-2 text-right text-amber-600" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right text-blue-600" x-text="formatNumber(row.aircraft_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.crew_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                    @elseif ($reportType === 'DAU4A')
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.operator_name || row.airline || '—'"></td>
                                        <td class="px-2 py-2 text-aviation-600 font-bold" x-text="row.operator_code || row.airline_code || '—'"></td>
                                        <td class="px-3 py-2 font-sans" x-text="row.airport || '—'"></td>
                                        <td class="px-2 py-2" x-text="row.city_code || '—'"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.crew_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                    @elseif ($reportType === 'DAU4B')
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200 text-left" x-text="row.city || '—'"></td>
                                        <td class="px-2 py-2 text-aviation-600 font-bold" x-text="row.city_code || '—'"></td>
                                        <td class="px-3 py-2 font-sans text-left" x-text="row.airline || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                    @elseif ($reportType === 'DAU5' || $reportType === 'DAU5C')
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.airline || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.passenger_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.passenger_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.crew_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                    @elseif ($reportType === 'DAU5A')
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.airline || '—'"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-blue-600" x-text="formatNumber(row.crew)"></td>
                                        <td class="px-2.5 py-2 text-right text-purple-600" x-text="formatNumber(row.arr_extra_crew)"></td>
                                        <td class="px-2.5 py-2 text-right text-purple-600" x-text="formatNumber(row.dep_extra_crew)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-purple-600" x-text="formatNumber(row.extra_crew)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-700 dark:text-slate-300" x-text="formatNumber(row.crew_total)"></td>
                                    @elseif ($reportType === 'DAU5B')
                                        <td class="px-3 py-2 font-bold text-aviation-600" x-text="'T' + (row.terminal || '—')"></td>
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.airline || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                    @elseif ($reportType === 'DAU6')
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.aircraft_type || '—'"></td>
                                        <td class="px-2.5 py-2" x-text="row.category || 'Narrow Body'"></td>
                                        <td class="px-2 py-2 font-bold text-aviation-600" x-text="row.wtc || 'Medium'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.crew_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                    @elseif ($reportType === 'DAU10A')
                                        <td class="px-3 py-2 font-bold text-aviation-600 dark:text-aviation-400" x-text="row.hour || row.period || '—'"></td>
                                        <td class="px-2.5 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.terminal || '—'"></td>
                                        {{-- AIRCRAFT CELLS --}}
                                        <template x-if="selectedMetric === 'aircraft'">
                                            <td class="px-2.5 py-2 text-right text-amber-600" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'aircraft'">
                                            <td class="px-2.5 py-2 text-right text-blue-600" x-text="formatNumber(row.aircraft_departure)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'aircraft'">
                                            <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        </template>
                                        {{-- PASSENGER CELLS --}}
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-emerald-600" x-text="formatNumber(row.passenger_arrival)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-blue-600" x-text="formatNumber(row.passenger_departure)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.passenger_transit)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.passenger_transfer)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-bold text-amber-600" x-text="formatNumber(row.passenger_total)"></td>
                                        </template>
                                        {{-- CREW CELLS --}}
                                        <template x-if="selectedMetric === 'crew'">
                                            <td class="px-2.5 py-2 text-right text-blue-600 font-bold" x-text="formatNumber(row.crew)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'crew'">
                                            <td class="px-2.5 py-2 text-right text-purple-600 font-bold" x-text="formatNumber(row.extra_crew)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'crew'">
                                            <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.crew_total)"></td>
                                        </template>
                                    @elseif ($reportType === 'DAU10B')
                                        <td class="px-3 py-2 font-bold text-aviation-600 dark:text-aviation-400" x-text="row.hour || row.period || '—'"></td>
                                        <td class="px-2.5 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.terminal || '—'"></td>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-purple-600 font-mono font-bold" x-text="formatNumber(row.passenger_arrival)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-amber-500 font-mono font-bold" x-text="formatNumber(row.passenger_departure)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-500 font-mono" x-text="formatNumber(row.passenger_transit)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-slate-500 font-mono" x-text="formatNumber(row.passenger_transfer)"></td>
                                        </template>
                                        <template x-if="selectedMetric === 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-bold text-emerald-600 font-mono" x-text="formatNumber(row.passenger_total)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-purple-600 font-mono font-bold" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right text-amber-500 font-mono font-bold" x-text="formatNumber(row.aircraft_departure)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white font-mono" x-text="formatNumber(row.aircraft_total)"></td>
                                        </template>
                                        <template x-if="selectedMetric !== 'passenger'">
                                            <td class="px-2 py-2 text-right text-slate-500 font-mono" x-text="formatNumber(row.crew)"></td>
                                        </template>
                                        <td class="px-2.5 py-2 text-right text-slate-500 font-mono" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500 font-mono" x-text="formatNumber(row.cargo)"></td>
                                    @elseif ($reportType === 'DAU10')
                                        <td class="px-3 py-2 font-bold text-aviation-600 dark:text-aviation-400" x-text="row.hour || row.period || '—'"></td>
                                        <td class="px-2.5 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.terminal || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.passenger_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.passenger_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2 py-2 text-right text-slate-500" x-text="formatNumber(row.crew)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                    @elseif ($reportType === 'DAU11')
                                        <td class="px-3 py-2 font-bold text-slate-800 dark:text-slate-200" x-text="row.date || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(selectedMetric === 'passenger' ? (row.passenger_int_arrival ?? 0) : row.aircraft_int_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(selectedMetric === 'passenger' ? (row.passenger_int_departure ?? 0) : row.aircraft_int_departure)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(selectedMetric === 'passenger' ? (row.passenger_dom_arrival ?? 0) : row.aircraft_dom_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(selectedMetric === 'passenger' ? (row.passenger_dom_departure ?? 0) : row.aircraft_dom_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                    @elseif ($reportType === 'DAU12')
                                        <td class="px-3 py-2 font-bold text-slate-800 dark:text-slate-200" x-text="row.date || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(selectedMetric === 'passenger' ? (row.passenger_arr_domestic ?? 0) : row.aircraft_arr_domestic)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(selectedMetric === 'passenger' ? (row.passenger_arr_int ?? 0) : row.aircraft_arr_int)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold" x-text="formatNumber(selectedMetric === 'passenger' ? ((Number(row.passenger_arr_domestic || 0)) + (Number(row.passenger_arr_int || 0))) : row.aircraft_arrival_tot)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(selectedMetric === 'passenger' ? (row.passenger_dep_domestic ?? 0) : row.aircraft_dep_domestic)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(selectedMetric === 'passenger' ? (row.passenger_dep_int ?? 0) : row.aircraft_dep_int)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold" x-text="formatNumber(selectedMetric === 'passenger' ? ((Number(row.passenger_dep_domestic || 0)) + (Number(row.passenger_dep_int || 0))) : row.aircraft_departure_tot)"></td>
                                        <td class="px-2.5 py-2 text-right font-black text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-black text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                    @endif
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <template x-if="filteredRecords.length > 0">
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
            </template>
        </div>

    </main>

    {{-- ══ FOOTER ═══════════════════════════════════════════════════════════════ --}}
    <footer class="w-full border-t border-slate-200/80 dark:border-slate-800/80 py-3 px-4 text-center text-xs text-slate-400 font-mono">
        SlotWaves Report System • OASYS Source Verification Active • {{ $meta['airport_name'] ?? 'CGK' }}
    </footer>

    {{-- ══ MODAL: EDIT CAPACITY & OPS HOURS (DAU-10A) ═══════════════════════════ --}}
    <div x-show="unifiedModalOpen" x-transition class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4" style="display: none;">
        <div @click.away="closeUnifiedModal()" class="w-full max-w-lg bg-white dark:bg-navy-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-aviation-600"></span>
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider">EDIT AIRCRAFT CAPACITY &amp; OPERATING HOURS</h3>
                </div>
                <button type="button" @click="closeUnifiedModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg font-bold cursor-pointer">&times;</button>
            </div>

            <div class="space-y-4 text-xs">
                <div class="space-y-2 border-t border-slate-100 dark:border-slate-800 pt-3">
                    <div class="text-[10.5px] font-black uppercase tracking-wider text-aviation-600 dark:text-aviation-400">
                        AIRCRAFT CAPACITY / NAC
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3.5 rounded-xl bg-amber-50/60 dark:bg-amber-950/30 border-2 border-amber-300 dark:border-amber-700/60 space-y-1.5 shadow-2xs">
                            <label class="block font-black text-amber-900 dark:text-amber-200 text-xs">ARRIVAL CAPACITY</label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="1" max="150" x-model.number="modalArrCap" class="w-full px-3 py-1.5 text-base font-mono font-bold rounded-lg border border-amber-300 dark:border-amber-700 bg-white dark:bg-navy-950 text-amber-700 dark:text-amber-300 focus:ring-2 focus:ring-amber-500">
                                <span class="text-xs font-bold text-amber-700 dark:text-amber-300 font-mono">A/C</span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-blue-50/60 dark:bg-blue-950/30 border-2 border-blue-300 dark:border-blue-700/60 space-y-1.5 shadow-2xs">
                            <label class="block font-black text-blue-900 dark:text-blue-200 text-xs">DEPARTURE CAPACITY</label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="1" max="150" x-model.number="modalDepCap" class="w-full px-3 py-1.5 text-base font-mono font-bold rounded-lg border border-blue-300 dark:border-blue-700 bg-white dark:bg-navy-950 text-blue-700 dark:text-blue-300 focus:ring-2 focus:ring-blue-500">
                                <span class="text-xs font-bold text-blue-700 dark:text-blue-300 font-mono">A/C</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 border-t border-slate-100 dark:border-slate-800 pt-3">
                    <div class="text-[10.5px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                        OPERATING HOURS
                    </div>
                    <div class="p-3.5 rounded-xl bg-emerald-50/40 dark:bg-emerald-950/20 border-2 border-emerald-300 dark:border-emerald-700/60 space-y-2 shadow-2xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-black text-emerald-900 dark:text-emerald-200 text-xs mb-1">START TIME</label>
                                <input type="text" x-model="modalOpsStart" placeholder="00:00" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-navy-950 text-slate-900 dark:text-white font-mono text-center font-bold">
                            </div>
                            <div>
                                <label class="block font-black text-emerald-900 dark:text-emerald-200 text-xs mb-1">END TIME</label>
                                <input type="text" x-model="modalOpsEnd" placeholder="24:00" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-navy-950 text-slate-900 dark:text-white font-mono text-center font-bold">
                            </div>
                        </div>
                    </div>
                </div>

                <template x-if="modalError">
                    <div class="p-2.5 rounded-lg bg-red-50 dark:bg-red-950/40 text-red-600 text-xs font-semibold" x-text="modalError"></div>
                </template>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="closeUnifiedModal()" class="px-4 py-2 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                <button type="button" @click="applyUnifiedSettings()" class="px-5 py-2 rounded-lg text-xs font-bold text-white bg-aviation-600 hover:bg-aviation-700 transition cursor-pointer shadow-xs">Save</button>
            </div>
        </div>
    </div>



    <div x-show="opsToastOpen" x-transition class="fixed bottom-5 right-5 z-50 p-3.5 rounded-xl bg-slate-900/95 text-white shadow-2xl border border-slate-700 text-xs font-semibold flex items-center gap-2 font-mono" style="display: none;">
        <span class="text-emerald-400">&#10003;</span>
        <span x-text="opsToastMessage"></span>
    </div>

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
        airlines: @json($airlines),
        airports: @json($airports),
        aircraftTypes: @json($aircraftTypes ?? []),

        // Canonical Filter State
        filterStartDate: @json($meta['start_date'] ?? date('Y-m-d')),
        filterEndDate: @json($meta['end_date'] ?? date('Y-m-d')),
        filterFlightType: @json($filters['flight_type'] ?? 'ALL'),
        filterTerminal: @json($filters['terminal'] ?? 'ALL'),
        filterHour: @json($filters['hour'] ?? 'ALL'),
        filterDirection: @json($filters['direction'] ?? 'ALL'),
        filterAirline: @json($filters['airline'] ?? 'ALL'),
        filterAirport: @json($filters['airport'] ?? 'ALL'),
        filterAircraftType: @json($filters['aircraft_type'] ?? 'ALL'),
        filterOperation: @json($filters['operation'] ?? 'ALL'),
        filterScheduleType: @json($filters['schedule_type'] ?? 'ALL'),
        filterStatus: @json($filters['status'] ?? 'ALL'),
        filterCategory: @json($filters['category'] ?? 'ALL'),
        filterWtc: 'ALL',
        filterTopN: @json($filters['top_n'] ?? '10'),
        filterThreshold: Number(@json($filters['threshold'] ?? 0)),
        filterPassengerType: @json($filters['passenger_type'] ?? 'ALL'),
        selectedMetric: @json($filters['metric'] ?? 'aircraft'),
        displayMode: @json($filters['display_mode'] ?? 'absolute'),
        searchQuery: @json($filters['search'] ?? ''),

        // DAU-10A Capacity State
        dauViewMode: 'distribution',
        arrivalCapacity: Number(@json($initialArrivalCapacity ?? ($initialNac ?? 6))),
        departureCapacity: Number(@json($initialDepartureCapacity ?? ($initialNac ?? 6))),
        modalArrCap: Number(@json($initialArrivalCapacity ?? ($initialNac ?? 6))),
        modalDepCap: Number(@json($initialDepartureCapacity ?? ($initialNac ?? 6))),
        modalOpsStart: @json($opsStartTime ?? '06:00'),
        modalOpsEnd: @json($opsEndTime ?? '20:00'),
        modalError: '',
        unifiedModalOpen: false,
        opsToastOpen: false,
        opsToastMessage: '',
        timezoneMode: 'LOCAL',
        tzAbbr: @json($tzAbbr ?? 'WIB'),
        tzOffset: Number(@json($tzOffset ?? 7)),
        opsStartTime: @json($opsStartTime ?? '06:00'),
        opsEndTime: @json($opsEndTime ?? '20:00'),

        // DAU-10A Average By Days State (Aircraft Hourly Bar Transformation)
        availableDates: @json($availableDates ?? []),
        totalAvailableDays: Number(@json($totalAvailableDays ?? 1)),
        averagePeriod: 'original',
        selectedAverageDays: 1,
        customAverageDays: Math.min(30, Number(@json($totalAvailableDays ?? 1))),
        customInputDays: '',
        customDayError: '',
        averageWindowStartDate: @json(!empty($availableDates) ? $availableDates[0] : ($meta['start_date'] ?? '')),
        averageWindowEndDate: @json(!empty($availableDates) ? $availableDates[count($availableDates) - 1] : ($meta['end_date'] ?? '')),

        // Table Pagination & Sorting
        currentPage: 1,
        pageSize: 25,
        sortCol: 'no',
        sortAsc: true,

        // PDF Export state
        isExportingPdf: false,
        pdfButtonText: 'Export PDF',

        // Dynamic Computed State
        filteredRecords: [],
        activeSummary: {},
        peaks: {},
        activeHourlyDistribution: [],
        activeTerminalComparison: [],
        dau2Metrics: { domAircraft: 0, intAircraft: 0, domSharePct: 100, intSharePct: 0 },
        dau1Ratios: @json($dau1Ratios ?? []),
        dau2Comparative: @json($dau2Comparative ?? []),
        dau2ComparativeList: [],
        dau3Metrics: { niagaAcft: 0, bukanNiagaAcft: 0, domAcft: 0, intAcft: 0 },
        dau3Regularity: @json($dau3Regularity ?? []),
        dau4Diverging: { top_arrival: [], top_departure: [] },
        dau4Intelligence: @json($dau4Intelligence ?? []),
        dau4aOperators: [],
        dau4aMarketShare: @json($dau4aMarketShare ?? []),
        dau4aSelectedRoute: @json($dau4aMarketShare['default_route'] ?? ''),
        dau4bMatrixData: { cities: [], airlines: [], grid: {} },
        dau4bIntelligence: @json($dau4bIntelligence ?? []),
        dau5aMetrics: { operatingCrew: 0, extraCrew: 0, arrExtraCrew: 0, depExtraCrew: 0 },
        dau5ParetoNoData: false,
        dau5ParetoInsight: { airlinesAt80: 0, cumAt80: 0.0, total: 0 },
        dau5ParetoData: [],
        dau5ParetoIntel: @json($dau5ParetoIntel ?? []),
        dau5aCrewNoData: false,
        dau5aOps: @json($dau5aOps ?? []),
        dau5bTermNoData: false,
        dau5bAllocation: @json($dau5bAllocation ?? []),
        dau5cNoData: false,
        dau5cChartLabel: 'Aircraft Movements',
        dau5cEfficiency: @json($dau5cEfficiency ?? []),
        dau6Aerodrome: @json($dau6Aerodrome ?? []),
        dau10PeakIntel: @json($dau10PeakIntel ?? []),
        dau10bDwell: @json($dau10bDwell ?? []),
        dau11TrafficMatrix: @json($dau11TrafficMatrix ?? []),
        dau12TrafficMatrix: @json($dau12TrafficMatrix ?? []),
        dau10bNoData: false,
        dau10bFilterVersion: 0,
        dau10bIsUpdating: false,
        dau10bErrorMessage: '',
        _dau10bCachedChartData: null,
        _dau10bCachedSnapshot: null,

        // DAU-12 State
        dau12NoData: false,
        dau12FilterVersion: 0,
        dau12IsUpdating: false,
        dau12ErrorMessage: '',
        _dau12CachedChartData: null,
        _dau12CachedSnapshot: null,
        dau12MatrixSummary: {
            arr_dom: 0, arr_int: 0, arr_tot: 0,
            dep_dom: 0, dep_int: 0, dep_tot: 0,
            tot_dom: 0, tot_int: 0, grand_tot: 0
        },

        get isPassengerDataAvailable() {
            if (this.reportType !== 'DAU10B') return true;
            return this.allRecords.some(r => Number(r.passenger_total || r.passenger_arrival || r.passenger_departure || 0) > 0);
        },

        get dau2ActiveMetricLabel() {
            const map = {
                aircraft: 'AIRCRAFT MOVEMENTS',
                passenger: 'PASSENGERS (PAX)',
                baggage: 'BAGGAGE (KG)',
                cargo: 'CARGO (KG)',
                pos: 'POS / MAIL (KG)'
            };
            return map[this.selectedMetric] || String(this.selectedMetric).toUpperCase();
        },

        // Chart.js registry
        chartInstances: {},

        initDashboard() {
            const airportKey = (this.meta.airport_code || 'CGK').toLowerCase();
            const storedArr = localStorage.getItem(`slotwaves_arr_cap_${airportKey}`);
            const storedDep = localStorage.getItem(`slotwaves_dep_cap_${airportKey}`);
            const storedStart = localStorage.getItem(`slotwaves_ops_start_${airportKey}`);
            const storedEnd = localStorage.getItem(`slotwaves_ops_end_${airportKey}`);
            if (storedArr) this.arrivalCapacity = parseInt(storedArr, 10);
            if (storedDep) this.departureCapacity = parseInt(storedDep, 10);
            if (storedStart) this.opsStartTime = storedStart;
            if (storedEnd) this.opsEndTime = storedEnd;

            this.applyFilters();
            this.$nextTick(() => {
                this.initCharts();
            });
        },

        get hasActiveFilters() {
            return this.filterFlightType !== 'ALL' ||
                   this.filterTerminal !== 'ALL' ||
                   this.filterHour !== 'ALL' ||
                   this.filterDirection !== 'ALL' ||
                   this.filterAirline !== 'ALL' ||
                   this.filterAirport !== 'ALL' ||
                   this.filterAircraftType !== 'ALL' ||
                   this.filterOperation !== 'ALL' ||
                   this.filterScheduleType !== 'ALL' ||
                   this.filterStatus !== 'ALL' ||
                   this.filterCategory !== 'ALL' ||
                   this.filterWtc !== 'ALL' ||
                   this.filterPassengerType !== 'ALL' ||
                   this.displayMode !== 'absolute' ||
                   (['DAU1', 'DAU2', 'DAU5', 'DAU5C', 'DAU6', 'DAU10B', 'DAU11', 'DAU12'].includes(this.reportType) && this.selectedMetric !== 'aircraft') ||
                   this.searchQuery !== '';
        },

        get activeDateRange() {
            return this.meta.date_range || `${this.filterStartDate} s/d ${this.filterEndDate}`;
        },

        get activeFlightScope() {
            if (this.filterFlightType === 'DOM') return 'DOMESTIK';
            if (this.filterFlightType === 'INT') return 'INTERNASIONAL';
            return this.meta.flight_scope || 'DOMESTIK & INTERNASIONAL';
        },

        get activeTerminalScope() {
            if (this.filterTerminal !== 'ALL') return 'TERMINAL ' + this.filterTerminal;
            return this.meta.terminal_scope || 'ALL TERMINAL';
        },

        get aircraftCapacity() {
            return Math.max(Number(this.arrivalCapacity) || 6, Number(this.departureCapacity) || 6);
        },

        set aircraftCapacity(val) {
            this.arrivalCapacity = Number(val) || 6;
            this.departureCapacity = Number(val) || 6;
        },

        openUnifiedModal() {
            this.modalArrCap = this.arrivalCapacity;
            this.modalDepCap = this.departureCapacity;
            this.modalOpsStart = this.opsStartTime;
            this.modalOpsEnd = this.opsEndTime;
            this.modalError = '';
            this.unifiedModalOpen = true;
        },

        closeUnifiedModal() {
            this.unifiedModalOpen = false;
        },

        async applyUnifiedSettings() {
            const arr = parseInt(this.modalArrCap, 10);
            const dep = parseInt(this.modalDepCap, 10);
            if (isNaN(arr) || arr < 1) { this.modalError = 'Arrival Capacity minimal 1'; return; }
            if (isNaN(dep) || dep < 1) { this.modalError = 'Departure Capacity minimal 1'; return; }
            this.arrivalCapacity = arr;
            this.departureCapacity = dep;
            this.opsStartTime = this.modalOpsStart;
            this.opsEndTime = this.modalOpsEnd;
            this.updateAverageWindow();
            this.closeUnifiedModal();
            this.opsToastMessage = `Capacity (ARR: ${arr}, DEP: ${dep}) & Ops Hours (${this.opsStartTime}-${this.opsEndTime}) berhasil disimpan`;
            this.opsToastOpen = true;
            setTimeout(() => { this.opsToastOpen = false; }, 3000);
        },

        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('slotwaves-theme', this.theme);
            if (this.theme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
            this.updateCharts();
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
            this.filterFlightType = 'ALL';
            this.filterTerminal = 'ALL';
            this.filterHour = 'ALL';
            this.filterDirection = 'ALL';
            this.filterAirline = 'ALL';
            this.filterAirport = 'ALL';
            this.filterAircraftType = 'ALL';
            this.filterPassengerType = 'ALL';
            this.displayMode = 'absolute';
            if (['DAU1', 'DAU2', 'DAU5', 'DAU5C', 'DAU6', 'DAU10B', 'DAU11', 'DAU12'].includes(this.reportType)) {
                this.selectedMetric = 'aircraft';
            }
            this.filterOperation = 'ALL';
            this.filterScheduleType = 'ALL';
            this.filterStatus = 'ALL';
            this.filterCategory = 'ALL';
            this.filterWtc = 'ALL';
            this.filterTopN = '10';
            this.filterThreshold = 0;
            this.searchQuery = '';
            this.currentPage = 1;
            this.applyFilters();
        },

        createDau10bSnapshot() {
            return {
                version: ++this.dau10bFilterVersion,
                metric: this.selectedMetric || 'aircraft',
                direction: this.filterDirection || 'ALL',
                terminal: this.filterTerminal || 'ALL',
                hour: this.filterHour || 'ALL',
                operation: this.filterOperation || 'ALL',
                flightType: this.filterFlightType || 'ALL',
                search: (this.searchQuery || '').toLowerCase().trim(),
            };
        },

        filterDau10bRecords(rawRecords, snapshot) {
            if (!Array.isArray(rawRecords)) return [];
            const ft = snapshot.flightType;
            const term = snapshot.terminal;
            const hr = snapshot.hour;
            const dir = snapshot.direction;
            const op = snapshot.operation;
            const sq = snapshot.search;
            const metric = snapshot.metric;

            const intTerminals = ['2E', '2F', '3U', 'T2E', 'T2F', 'T3U', '3'];
            const isIntTerminal = (t) => intTerminals.some(x => String(t || '').toUpperCase().replace(/\s/g, '') === x);

            return rawRecords.filter(r => {
                if (!r) return false;

                // Flight Scope
                if (ft === 'DOM') {
                    if (r.category && String(r.category).toUpperCase().includes('INT')) return false;
                    if (!r.category && isIntTerminal(r.terminal)) return false;
                } else if (ft === 'INT') {
                    if (r.category && String(r.category).toUpperCase().includes('DOM')) return false;
                    if (!r.category && !isIntTerminal(r.terminal)) return false;
                }

                // Terminal
                if (term !== 'ALL') {
                    const rTerm = String(r.terminal || '').trim().toLowerCase();
                    const filterTerm = term.trim().toLowerCase();
                    if (rTerm !== filterTerm) return false;
                }

                // Hour
                if (hr !== 'ALL') {
                    const rHour = String(r.hour || r.period || '');
                    const cleanHr = hr.replace(/[^0-9]/g, '');
                    const cleanRHour = rHour.replace(/[^0-9]/g, '');
                    if (cleanHr && cleanRHour !== cleanHr && rHour !== hr) return false;
                }

                // Direction
                if (dir !== 'ALL') {
                    if (dir === 'ARRIVAL') {
                        const hasArr = (Number(r.aircraft_arrival || 0) > 0)
                                    || (Number(r.passenger_arrival || 0) > 0)
                                    || (Number(r.block_on_aircraft || 0) > 0)
                                    || (Number(r.block_on_passenger || 0) > 0);
                        if (!hasArr) return false;
                    } else if (dir === 'DEPARTURE') {
                        const hasDep = (Number(r.aircraft_departure || 0) > 0)
                                    || (Number(r.passenger_departure || 0) > 0)
                                    || (Number(r.block_off_aircraft || 0) > 0)
                                    || (Number(r.block_off_passenger || 0) > 0);
                        if (!hasDep) return false;
                    }
                }

                // Operation
                if (op !== 'ALL') {
                    if (metric === 'passenger') {
                        if (op === 'BLOCK_ON' && Number(r.passenger_arrival || r.block_on_passenger || 0) === 0) return false;
                        if (op === 'BLOCK_OFF' && Number(r.passenger_departure || r.block_off_passenger || 0) === 0) return false;
                    } else {
                        if (op === 'BLOCK_ON' && Number(r.aircraft_arrival || r.block_on_aircraft || 0) === 0) return false;
                        if (op === 'BLOCK_OFF' && Number(r.aircraft_departure || r.block_off_aircraft || 0) === 0) return false;
                    }
                }

                // Search Query
                if (sq !== '') {
                    const haystack = JSON.stringify(r).toLowerCase();
                    if (!haystack.includes(sq)) return false;
                }

                return true;
            });
        },

        aggregateDau10b(filteredRecords, snapshot, hoursList) {
            const sum = {
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
            (hoursList || []).forEach(h => {
                hourlyMap[h] = {
                    hour: h,
                    aircraft_arrival: 0,
                    aircraft_departure: 0,
                    aircraft_total: 0,
                    passenger_arrival: 0,
                    passenger_departure: 0,
                    passenger_transit: 0,
                    passenger_transfer: 0,
                    passenger_total: 0,
                    crew: 0,
                    extra_crew: 0,
                    crew_total: 0,
                };
            });

            const dir = snapshot.direction;
            const op = snapshot.operation;
            const isPax = snapshot.metric === 'passenger';

            const sanitize = (val) => {
                const n = Number(val);
                return Number.isFinite(n) ? n : 0;
            };

            filteredRecords.forEach(r => {
                const acArr = sanitize(r.aircraft_arrival || r.block_on_aircraft || 0);
                const acDep = sanitize(r.aircraft_departure || r.block_off_aircraft || 0);
                const acTot = sanitize(r.aircraft_total || (acArr + acDep));

                const pxArr = sanitize(r.passenger_arrival || r.block_on_passenger || 0);
                const pxDep = sanitize(r.passenger_departure || r.block_off_passenger || 0);
                const pxTrn = sanitize(r.passenger_transit || 0);
                const pxTrf = sanitize(r.passenger_transfer || 0);
                const pxTot = sanitize(r.passenger_total || (pxArr + pxDep + pxTrn + pxTrf));

                const crw = sanitize(r.crew || 0);
                const exCrw = sanitize(r.extra_crew || 0);
                const crwTot = sanitize(r.crew_total || (crw + exCrw));
                const bag = sanitize(r.baggage || 0);
                const cgo = sanitize(r.cargo || 0);
                const pos = sanitize(r.pos || 0);

                let effAcArr = (dir === 'DEPARTURE') ? 0 : acArr;
                let effAcDep = (dir === 'ARRIVAL') ? 0 : acDep;
                let effAcTot = (dir === 'ARRIVAL') ? acArr : ((dir === 'DEPARTURE') ? acDep : acTot);

                let effPxArr = (dir === 'DEPARTURE') ? 0 : pxArr;
                let effPxDep = (dir === 'ARRIVAL') ? 0 : pxDep;
                let effPxTrn = (dir === 'ARRIVAL' || dir === 'DEPARTURE') ? 0 : pxTrn;
                let effPxTrf = (dir === 'ARRIVAL' || dir === 'DEPARTURE') ? 0 : pxTrf;
                let effPxTot = (dir === 'ARRIVAL') ? pxArr : ((dir === 'DEPARTURE') ? pxDep : pxTot);

                if (op === 'BLOCK_ON') {
                    effAcDep = 0;
                    effPxDep = 0;
                    effAcTot = effAcArr;
                    effPxTot = effPxArr;
                } else if (op === 'BLOCK_OFF') {
                    effAcArr = 0;
                    effPxArr = 0;
                    effAcTot = effAcDep;
                    effPxTot = effPxDep;
                }

                sum.total_movements += effAcTot;
                sum.aircraft_arrival += effAcArr;
                sum.aircraft_departure += effAcDep;
                sum.passenger_arrival += effPxArr;
                sum.passenger_departure += effPxDep;
                sum.passenger_transit += effPxTrn;
                sum.passenger_transfer += effPxTrf;
                sum.passenger_total += effPxTot;
                sum.crew_total += crwTot;
                sum.baggage_total += bag;
                sum.cargo_total += cgo;
                sum.pos_total += pos;

                const h = r.hour || r.period;
                if (h) {
                    if (!hourlyMap[h]) {
                        hourlyMap[h] = {
                            hour: h,
                            aircraft_arrival: 0,
                            aircraft_departure: 0,
                            aircraft_total: 0,
                            passenger_arrival: 0,
                            passenger_departure: 0,
                            passenger_transit: 0,
                            passenger_transfer: 0,
                            passenger_total: 0,
                            crew: 0,
                            extra_crew: 0,
                            crew_total: 0,
                        };
                    }
                    hourlyMap[h].aircraft_arrival += effAcArr;
                    hourlyMap[h].aircraft_departure += effAcDep;
                    hourlyMap[h].aircraft_total += effAcTot;
                    hourlyMap[h].passenger_arrival += effPxArr;
                    hourlyMap[h].passenger_departure += effPxDep;
                    hourlyMap[h].passenger_transit += effPxTrn;
                    hourlyMap[h].passenger_transfer += effPxTrf;
                    hourlyMap[h].passenger_total += effPxTot;
                    hourlyMap[h].crew += crw;
                    hourlyMap[h].extra_crew += exCrw;
                    hourlyMap[h].crew_total += crwTot;
                }

                const t = r.terminal;
                if (t) {
                    if (!termMap[t]) termMap[t] = { terminal: t, aircraft_total: 0, passenger_total: 0, crew_total: 0 };
                    termMap[t].aircraft_total += effAcTot;
                    termMap[t].passenger_total += effPxTot;
                    termMap[t].crew_total += crwTot;
                }
            });

            const hourlyDist = Object.values(hourlyMap);
            const termComp = Object.values(termMap);

            let peakAc = 0, peakAcH = '—';
            let peakPx = 0, peakPxH = '—';
            let peakBlkOn = 0, peakBlkOnH = '—';
            let peakBlkOff = 0, peakBlkOffH = '—';

            hourlyDist.forEach(hb => {
                if (hb.aircraft_total > peakAc) { peakAc = hb.aircraft_total; peakAcH = hb.hour; }
                if (hb.passenger_total > peakPx) { peakPx = hb.passenger_total; peakPxH = hb.hour; }
                const onVal = isPax ? hb.passenger_arrival : hb.aircraft_arrival;
                const offVal = isPax ? hb.passenger_departure : hb.aircraft_departure;
                if (onVal > peakBlkOn) { peakBlkOn = onVal; peakBlkOnH = hb.hour; }
                if (offVal > peakBlkOff) { peakBlkOff = offVal; peakBlkOffH = hb.hour; }
            });

            let peakT = '—', peakTV = 0;
            termComp.forEach(tb => {
                const val = isPax ? tb.passenger_total : tb.aircraft_total;
                if (val > peakTV) { peakTV = val; peakT = tb.terminal; }
            });

            const peaks = {
                peak_aircraft_hour: peakAcH,
                peak_aircraft: peakAc,
                peak_passenger_hour: peakPxH,
                peak_passenger: peakPx,
                peak_hour: isPax ? peakPxH : peakAcH,
                peak_terminal: peakT,
                peak_terminal_val: peakTV,
                peak_block_on_hour: peakBlkOnH,
                peak_block_on: peakBlkOn,
                peak_block_off_hour: peakBlkOffH,
                peak_block_off: peakBlkOff,
            };

            const labels = hourlyDist.map(h => (h.hour ? String(h.hour).split(' - ')[0] : '—'));
            const onData = hourlyDist.map(h => isPax ? sanitize(h.passenger_arrival) : sanitize(h.aircraft_arrival));
            const offData = hourlyDist.map(h => isPax ? sanitize(h.passenger_departure) : sanitize(h.aircraft_departure));

            const showOn = (op !== 'BLOCK_OFF' && dir !== 'DEPARTURE');
            const showOff = (op !== 'BLOCK_ON' && dir !== 'ARRIVAL');

            const datasets = [];
            if (showOn) {
                datasets.push({
                    label: isPax ? 'Block On (DTG) — Passenger' : 'Block On (DTG)',
                    data: onData,
                    backgroundColor: '#7c3aed',
                    borderColor: '#6d28d9',
                    borderWidth: 1,
                    borderRadius: 4,
                });
            }
            if (showOff) {
                datasets.push({
                    label: isPax ? 'Block Off (BRK) — Passenger' : 'Block Off (BRK)',
                    data: offData,
                    backgroundColor: '#f59e0b',
                    borderColor: '#d97706',
                    borderWidth: 1,
                    borderRadius: 4,
                });
            }

            const allVals = datasets.flatMap(ds => ds.data);
            const maxVal = allVals.length > 0 ? Math.max(...allVals, 10) : 10;

            return {
                summary: sum,
                hourlyDistribution: hourlyDist,
                terminalComparison: termComp,
                peaks: peaks,
                chartData: {
                    labels: labels,
                    datasets: datasets,
                    maxVal: maxVal,
                    hoursList: hourlyDist.map(h => h.hour)
                }
            };
        },

        applyDau10bFilters() {
            this.currentPage = 1;
            this.dau10bIsUpdating = true;
            this.dau10bErrorMessage = '';

            try {
                const snapshot = this.createDau10bSnapshot();
                const nextFiltered = this.filterDau10bRecords(this.allRecords, snapshot);
                const nextAgg = this.aggregateDau10b(nextFiltered, snapshot, this.hours || []);

                // Stale check (Part 5)
                if (snapshot.version !== this.dau10bFilterVersion) {
                    return;
                }

                // Atomic commit (Part 21)
                this.filteredRecords = nextFiltered;
                this.activeSummary = nextAgg.summary;
                this.peaks = nextAgg.peaks;
                this.activeHourlyDistribution = nextAgg.hourlyDistribution;
                this.activeTerminalComparison = nextAgg.terminalComparison;
                this._dau10bCachedChartData = nextAgg.chartData;
                this._dau10bCachedSnapshot = snapshot;

                const totalChartVal = (nextAgg.chartData.datasets || []).reduce((acc, ds) => acc + ds.data.reduce((a, b) => a + b, 0), 0);
                this.dau10bNoData = (nextFiltered.length === 0 || totalChartVal === 0);

                this.renderDau10bChartAtomic(nextAgg.chartData, snapshot);
            } catch (err) {
                console.error('[SlotWaves DAU-10B] Filter error:', err);
                this.dau10bErrorMessage = 'Unable to update analysis.';
            } finally {
                this.dau10bIsUpdating = false;
            }
        },

        renderDau10bChartAtomic(chartData, snapshot) {
            if (snapshot && snapshot.version !== this.dau10bFilterVersion) {
                return;
            }

            if (!window.Chart) {
                setTimeout(() => {
                    if (!snapshot || snapshot.version === this.dau10bFilterVersion) {
                        this.renderDau10bChartAtomic(chartData, snapshot);
                    }
                }, 100);
                return;
            }

            if (this.dau10bNoData) {
                if (this.chartInstances.dau10b) {
                    try { this.chartInstances.dau10b.stop(); } catch(_) {}
                }
                return;
            }

            const canvas = document.getElementById('dau10bBlockChart');
            if (!canvas) return;

            if (canvas.offsetParent === null || canvas.clientWidth === 0) {
                this.$nextTick(() => {
                    if (!snapshot || snapshot.version === this.dau10bFilterVersion) {
                        this.renderDau10bChartAtomic(chartData, snapshot);
                    }
                });
                return;
            }

            const isPax = (snapshot ? snapshot.metric : this.selectedMetric) === 'passenger';
            const unit = isPax ? 'PAX' : 'A/C';
            const op = snapshot ? snapshot.operation : this.filterOperation;
            const term = snapshot ? snapshot.terminal : this.filterTerminal;
            const ft = snapshot ? snapshot.flightType : this.filterFlightType;
            const hoursList = (chartData && chartData.hoursList) ? chartData.hoursList : [];

            // Single Chart Instance Reuse (Part 14)
            const existingChart = this.chartInstances.dau10b;
            if (existingChart && existingChart.ctx && !existingChart.destroyed) {
                try {
                    existingChart.data.labels = chartData.labels;
                    existingChart.data.datasets = chartData.datasets;
                    if (existingChart.options && existingChart.options.scales && existingChart.options.scales.y) {
                        existingChart.options.scales.y.suggestedMax = Math.ceil(chartData.maxVal * 1.1);
                        existingChart.options.scales.y.ticks.callback = (val) => Number(val).toLocaleString('id-ID') + ' ' + unit;
                    }
                    if (existingChart.options && existingChart.options.plugins && existingChart.options.plugins.tooltip) {
                        existingChart.options.plugins.tooltip.callbacks.title = (items) => {
                            const idx = items[0]?.dataIndex;
                            const h = hoursList[idx] || items[0]?.label;
                            return 'Hour: ' + (h || items[0]?.label);
                        };
                        existingChart.options.plugins.tooltip.callbacks.label = (item) => `${item.dataset.label}: ${Number(item.raw || 0).toLocaleString('id-ID')} ${unit}`;
                        existingChart.options.plugins.tooltip.callbacks.afterBody = () => {
                            const lines = [];
                            lines.push('Metric: ' + (isPax ? 'Passenger' : 'Aircraft'));
                            lines.push('Terminal: ' + (term !== 'ALL' ? term : 'ALL'));
                            lines.push('Scope: ' + (ft !== 'ALL' ? ft : 'ALL'));
                            if (op !== 'ALL') {
                                lines.push('Operation: ' + (op === 'BLOCK_ON' ? 'Block On (DTG)' : 'Block Off (BRK)'));
                            }
                            return lines;
                        };
                    }
                    existingChart.update('none');
                    return;
                } catch (err) {
                    console.warn('[SlotWaves DAU-10B] In-place chart update warning, recreating:', err);
                    try { existingChart.destroy(); } catch (_) {}
                    this.chartInstances.dau10b = null;
                }
            }

            const ctxBlk = canvas.getContext('2d');
            if (!ctxBlk) return;

            try {
                this.chartInstances.dau10b = new Chart(ctxBlk, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels,
                        datasets: chartData.datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    boxWidth: 12,
                                    boxHeight: 12,
                                    font: { size: 11, family: 'ui-monospace, monospace' }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    title: (items) => {
                                        const idx = items[0]?.dataIndex;
                                        const h = hoursList[idx] || items[0]?.label;
                                        return 'Hour: ' + (h || items[0]?.label);
                                    },
                                    label: (item) => `${item.dataset.label}: ${Number(item.raw || 0).toLocaleString('id-ID')} ${unit}`,
                                    afterBody: () => {
                                        const lines = [];
                                        lines.push('Metric: ' + (isPax ? 'Passenger' : 'Aircraft'));
                                        lines.push('Terminal: ' + (term !== 'ALL' ? term : 'ALL'));
                                        lines.push('Scope: ' + (ft !== 'ALL' ? ft : 'ALL'));
                                        if (op !== 'ALL') {
                                            lines.push('Operation: ' + (op === 'BLOCK_ON' ? 'Block On (DTG)' : 'Block Off (BRK)'));
                                        }
                                        return lines;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10, family: 'ui-monospace, monospace' } }
                            },
                            y: {
                                beginAtZero: true,
                                suggestedMax: Math.ceil(chartData.maxVal * 1.1),
                                ticks: {
                                    font: { size: 10, family: 'ui-monospace, monospace' },
                                    callback: (val) => Number(val).toLocaleString('id-ID') + ' ' + unit
                                }
                            }
                        }
                    }
                });
            } catch (createErr) {
                console.error('[SlotWaves DAU-10B] Failed to create Chart instance:', createErr);
            }
        },

        // ══ DAU-12 ATOMIC STATE PIPELINE ═════════════════════════════════════
        createDau12Snapshot() {
            return {
                version: ++this.dau12FilterVersion,
                metric: this.selectedMetric || 'aircraft',
                direction: this.filterDirection || 'ALL',
                flightType: this.filterFlightType || 'ALL',
                search: (this.searchQuery || '').toLowerCase().trim(),
                startDate: this.filterStartDate,
                endDate: this.filterEndDate
            };
        },

        filterDau12Records(rawRecords, snapshot) {
            if (!Array.isArray(rawRecords)) return [];
            const dir = snapshot.direction;
            const ft = snapshot.flightType;
            const sq = snapshot.search;

            return rawRecords.filter(r => {
                // Search query
                if (sq !== '') {
                    const haystack = JSON.stringify(r).toLowerCase();
                    if (!haystack.includes(sq)) return false;
                }

                // Direction filter at record level
                if (dir === 'ARRIVAL') {
                    const hasArr = (Number(r.aircraft_arrival || 0) > 0)
                                || (Number(r.passenger_arrival || 0) > 0)
                                || (Number(r.aircraft_arr_domestic || 0) > 0)
                                || (Number(r.aircraft_arr_int || 0) > 0)
                                || (Number(r.passenger_arr_domestic || 0) > 0)
                                || (Number(r.passenger_arr_int || 0) > 0);
                    if (!hasArr) return false;
                } else if (dir === 'DEPARTURE') {
                    const hasDep = (Number(r.aircraft_departure || 0) > 0)
                                || (Number(r.passenger_departure || 0) > 0)
                                || (Number(r.aircraft_dep_domestic || 0) > 0)
                                || (Number(r.aircraft_dep_int || 0) > 0)
                                || (Number(r.passenger_dep_domestic || 0) > 0)
                                || (Number(r.passenger_dep_int || 0) > 0);
                    if (!hasDep) return false;
                }

                // Scope filter at record level
                if (ft === 'DOM') {
                    const hasDom = (Number(r.aircraft_arr_domestic || 0) > 0)
                                || (Number(r.aircraft_dep_domestic || 0) > 0)
                                || (Number(r.passenger_arr_domestic || 0) > 0)
                                || (Number(r.passenger_dep_domestic || 0) > 0);
                    if (!hasDom) return false;
                } else if (ft === 'INT') {
                    const hasInt = (Number(r.aircraft_arr_int || 0) > 0)
                                || (Number(r.aircraft_dep_int || 0) > 0)
                                || (Number(r.passenger_arr_int || 0) > 0)
                                || (Number(r.passenger_dep_int || 0) > 0);
                    if (!hasInt) return false;
                }

                return true;
            });
        },

        aggregateDau12(records, snapshot) {
            const isPax = snapshot.metric === 'passenger';
            const unit = isPax ? 'PAX' : 'A/C';
            const dir = snapshot.direction;
            const scope = snapshot.flightType;

            let arrDom = 0, arrInt = 0, depDom = 0, depInt = 0;
            let sumTotalMov = 0, sumPaxTot = 0;
            let sumAcArr = 0, sumAcDep = 0, sumPxArr = 0, sumPxDep = 0;

            records.forEach(r => {
                const acArrD = Number(r.aircraft_arr_domestic || 0);
                const acArrI = Number(r.aircraft_arr_int || 0);
                const acDepD = Number(r.aircraft_dep_domestic || 0);
                const acDepI = Number(r.aircraft_dep_int || 0);
                const acTot  = Number(r.aircraft_total || (acArrD + acArrI + acDepD + acDepI));

                const pxArrD = Number(r.passenger_arr_domestic || 0);
                const pxArrI = Number(r.passenger_arr_int || 0);
                const pxDepD = Number(r.passenger_dep_domestic || 0);
                const pxDepI = Number(r.passenger_dep_int || 0);
                const pxTot  = Number(r.passenger_total || (pxArrD + pxArrI + pxDepD + pxDepI));

                sumTotalMov += acTot;
                sumPaxTot += pxTot;
                sumAcArr += (acArrD + acArrI);
                sumAcDep += (acDepD + acDepI);
                sumPxArr += (pxArrD + pxArrI);
                sumPxDep += (pxDepD + pxDepI);

                if (isPax) {
                    arrDom += pxArrD;
                    arrInt += pxArrI;
                    depDom += pxDepD;
                    depInt += pxDepI;
                } else {
                    arrDom += acArrD;
                    arrInt += acArrI;
                    depDom += acDepD;
                    depInt += acDepI;
                }
            });

            // Adjust directional / scope matrix totals according to active filters
            const matrixSummary = {
                arr_dom: (dir === 'DEPARTURE' || scope === 'INT') ? 0 : arrDom,
                arr_int: (dir === 'DEPARTURE' || scope === 'DOM') ? 0 : arrInt,
                arr_tot: (dir === 'DEPARTURE') ? 0 : ((scope === 'DOM') ? arrDom : ((scope === 'INT') ? arrInt : (arrDom + arrInt))),
                dep_dom: (dir === 'ARRIVAL' || scope === 'INT') ? 0 : depDom,
                dep_int: (dir === 'ARRIVAL' || scope === 'DOM') ? 0 : depInt,
                dep_tot: (dir === 'ARRIVAL') ? 0 : ((scope === 'DOM') ? depDom : ((scope === 'INT') ? depInt : (depDom + depInt))),
                tot_dom: (scope === 'INT') ? 0 : ((dir === 'ARRIVAL') ? arrDom : ((dir === 'DEPARTURE') ? depDom : (arrDom + depDom))),
                tot_int: (scope === 'DOM') ? 0 : ((dir === 'ARRIVAL') ? arrInt : ((dir === 'DEPARTURE') ? depInt : (arrInt + depInt))),
                grand_tot: 0
            };
            matrixSummary.grand_tot = matrixSummary.arr_tot + matrixSummary.dep_tot;

            // Summary KPI
            let effMov = sumTotalMov;
            let effPax = sumPaxTot;
            if (dir === 'ARRIVAL') {
                effMov = sumAcArr;
                effPax = sumPxArr;
            } else if (dir === 'DEPARTURE') {
                effMov = sumAcDep;
                effPax = sumPxDep;
            }

            const summary = {
                total_movements: effMov,
                passenger_total: effPax,
                aircraft_arrival: (dir === 'DEPARTURE') ? 0 : sumAcArr,
                aircraft_departure: (dir === 'ARRIVAL') ? 0 : sumAcDep,
                passenger_arrival: (dir === 'DEPARTURE') ? 0 : sumPxArr,
                passenger_departure: (dir === 'ARRIVAL') ? 0 : sumPxDep,
            };

            // Determine X-axis categories based on Direction Filter (Part 16)
            let categories = [];
            let domData = [];
            let intData = [];

            if (dir === 'ARRIVAL') {
                categories = ['ARRIVAL'];
                domData = [arrDom];
                intData = [arrInt];
            } else if (dir === 'DEPARTURE') {
                categories = ['DEPARTURE'];
                domData = [depDom];
                intData = [depInt];
            } else {
                categories = ['ARRIVAL', 'DEPARTURE'];
                domData = [arrDom, depDom];
                intData = [arrInt, depInt];
            }

            // Determine Datasets based on Scope Filter (Part 17)
            const datasets = [];
            if (scope !== 'INT') {
                datasets.push({
                    label: 'Domestic',
                    data: domData,
                    backgroundColor: '#2563eb',
                    borderColor: '#1d4ed8',
                    borderWidth: 1,
                    borderRadius: 4
                });
            }
            if (scope !== 'DOM') {
                datasets.push({
                    label: 'International',
                    data: intData,
                    backgroundColor: '#4f46e5',
                    borderColor: '#4338ca',
                    borderWidth: 1,
                    borderRadius: 4
                });
            }

            const allVals = datasets.flatMap(ds => ds.data);
            const maxVal = allVals.length > 0 ? Math.max(...allVals, 10) : 10;
            const totalVisualVal = allVals.reduce((a, b) => a + Number(b || 0), 0);

            return {
                summary,
                matrixSummary,
                chartData: {
                    labels: categories,
                    datasets: datasets,
                    maxVal: maxVal,
                    totalVal: totalVisualVal,
                    unit: unit,
                    metric: isPax ? 'Passenger' : 'Aircraft'
                }
            };
        },

        applyDau12Filters() {
            this.currentPage = 1;
            this.dau12IsUpdating = true;
            this.dau12ErrorMessage = '';

            try {
                const snapshot = this.createDau12Snapshot();
                const nextFiltered = this.filterDau12Records(this.allRecords, snapshot);
                const nextAgg = this.aggregateDau12(nextFiltered, snapshot);

                // Discard stale calculation (Part 51)
                if (snapshot.version !== this.dau12FilterVersion) {
                    return;
                }

                // Atomic commit
                this.filteredRecords = nextFiltered;
                this.activeSummary = nextAgg.summary;
                this.dau12MatrixSummary = nextAgg.matrixSummary;
                this._dau12CachedChartData = nextAgg.chartData;
                this._dau12CachedSnapshot = snapshot;

                this.dau12NoData = (nextFiltered.length === 0 || nextAgg.chartData.totalVal === 0);

                this.renderDau12ChartAtomic(nextAgg.chartData, snapshot);
            } catch (err) {
                console.error('[SlotWaves DAU-12] Filter error:', err);
                this.dau12ErrorMessage = 'Unable to update analysis.';
            } finally {
                this.dau12IsUpdating = false;
            }
        },

        renderDau12ChartAtomic(chartData, snapshot) {
            if (snapshot && snapshot.version !== this.dau12FilterVersion) {
                return;
            }

            if (!window.Chart) {
                setTimeout(() => {
                    if (!snapshot || snapshot.version === this.dau12FilterVersion) {
                        this.renderDau12ChartAtomic(chartData, snapshot);
                    }
                }, 100);
                return;
            }

            if (this.dau12NoData) {
                if (this.chartInstances.dau12) {
                    try { this.chartInstances.dau12.stop(); } catch(_) {}
                }
                return;
            }

            const canvas = document.getElementById('dau12GroupedChart');
            if (!canvas) return;

            if (canvas.offsetParent === null || canvas.clientWidth === 0) {
                this.$nextTick(() => {
                    if (!snapshot || snapshot.version === this.dau12FilterVersion) {
                        this.renderDau12ChartAtomic(chartData, snapshot);
                    }
                });
                return;
            }

            const isPax = (snapshot ? snapshot.metric : this.selectedMetric) === 'passenger';
            const unit = isPax ? 'PAX' : 'A/C';
            const dir = snapshot ? snapshot.direction : this.filterDirection;
            const scope = snapshot ? snapshot.flightType : this.filterFlightType;

            // Pure non-reactive payload for Chart.js to prevent Alpine proxy loops
            const cleanLabels = [...chartData.labels];
            const cleanDatasets = (chartData.datasets || []).map(ds => ({
                label: ds.label,
                data: [...ds.data],
                backgroundColor: ds.backgroundColor,
                borderColor: ds.borderColor,
                borderWidth: ds.borderWidth,
                borderRadius: ds.borderRadius
            }));

            // In-place chart reuse (Part 52)
            const existingChart = this.chartInstances.dau12;
            if (existingChart && existingChart.ctx && !existingChart.destroyed) {
                try {
                    existingChart.data.labels = cleanLabels;
                    existingChart.data.datasets = cleanDatasets;
                    if (existingChart.options && existingChart.options.scales && existingChart.options.scales.y) {
                        existingChart.options.scales.y.suggestedMax = Math.ceil(chartData.maxVal * 1.15);
                        existingChart.options.scales.y.ticks.callback = (val) => Number(val).toLocaleString('id-ID') + ' ' + unit;
                    }
                    if (existingChart.options && existingChart.options.plugins && existingChart.options.plugins.tooltip) {
                        existingChart.options.plugins.tooltip.callbacks.title = (items) => {
                            const cat = items[0]?.label || '';
                            return 'Direction: ' + cat;
                        };
                        existingChart.options.plugins.tooltip.callbacks.label = (item) => {
                            return `${item.dataset.label}: ${Number(item.raw || 0).toLocaleString('id-ID')} ${unit}`;
                        };
                        existingChart.options.plugins.tooltip.callbacks.afterBody = () => {
                            const lines = [];
                            lines.push('Metric: ' + (isPax ? 'Passenger' : 'Aircraft'));
                            if (scope !== 'ALL') lines.push('Scope: ' + (scope === 'DOM' ? 'Domestic Only' : 'International Only'));
                            if (dir !== 'ALL') lines.push('Direction: ' + dir);
                            return lines;
                        };
                    }
                    existingChart.update('none');
                    return;
                } catch (err) {
                    console.warn('[SlotWaves DAU-12] In-place chart update warning, recreating:', err);
                    try { existingChart.destroy(); } catch (_) {}
                    this.chartInstances.dau12 = null;
                }
            }

            // Clean up any untracked chart on this canvas
            const untracked = window.Chart.getChart(canvas);
            if (untracked) {
                try { untracked.destroy(); } catch (_) {}
            }

            const ctx12 = canvas.getContext('2d');
            if (!ctx12) return;

            try {
                this.chartInstances.dau12 = new Chart(ctx12, {
                    type: 'bar',
                    data: {
                        labels: cleanLabels,
                        datasets: cleanDatasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    boxWidth: 12,
                                    boxHeight: 12,
                                    font: { size: 11, family: 'ui-monospace, monospace' }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    title: (items) => {
                                        const cat = items[0]?.label || '';
                                        return 'Direction: ' + cat;
                                    },
                                    label: (item) => `${item.dataset.label}: ${Number(item.raw || 0).toLocaleString('id-ID')} ${unit}`,
                                    afterBody: () => {
                                        const lines = [];
                                        lines.push('Metric: ' + (isPax ? 'Passenger' : 'Aircraft'));
                                        if (scope !== 'ALL') lines.push('Scope: ' + (scope === 'DOM' ? 'Domestic Only' : 'International Only'));
                                        if (dir !== 'ALL') lines.push('Direction: ' + dir);
                                        return lines;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { weight: 'bold', family: 'ui-monospace, monospace' } }
                            },
                            y: {
                                beginAtZero: true,
                                suggestedMax: Math.ceil(chartData.maxVal * 1.15),
                                ticks: {
                                    callback: (val) => Number(val).toLocaleString('id-ID') + ' ' + unit,
                                    font: { family: 'ui-monospace, monospace' }
                                }
                            }
                        }
                    }
                });
            } catch (createErr) {
                console.error('[SlotWaves DAU-12] Failed to create Chart instance:', createErr);
            }
        },

        applyFilters() {
            this.currentPage = 1;
            if (this.reportType === 'DAU10B') {
                this.applyDau10bFilters();
                return;
            }
            if (this.reportType === 'DAU12') {
                this.applyDau12Filters();
                return;
            }
            const ft = this.filterFlightType;
            const term = this.filterTerminal;
            const hr = this.filterHour;
            const dir = this.filterDirection;
            const alFilter = this.filterAirline;
            const apFilter = this.filterAirport;
            const actFilter = this.filterAircraftType;
            const paxType = this.filterPassengerType;
            const op = this.filterOperation;
            const sched = this.filterScheduleType;
            const st = this.filterStatus;
            const cat = this.filterCategory;
            const wtc = this.filterWtc;
            const thresh = Number(this.filterThreshold) || 0;
            const sq = (this.searchQuery || '').toLowerCase().trim();

            this.filteredRecords = this.allRecords.filter(r => {
                // Flight Type
                const intTerminals = ['2E', '2F', '3U', 'T2E', 'T2F', 'T3U', '3'];
                const isIntTerminal = (t) => intTerminals.some(x => String(t || '').toUpperCase().replace(/\s/g,'') === x);
                if (ft === 'DOM') {
                    if (r.category && String(r.category).toUpperCase().includes('INT')) return false;
                    // DAU10/10B: derive scope from terminal when category field is absent
                    if (!r.category && ['DAU10', 'DAU10B'].includes(this.reportType)) {
                        if (isIntTerminal(r.terminal)) return false;
                    }
                } else if (ft === 'INT') {
                    if (r.category && String(r.category).toUpperCase().includes('DOM')) return false;
                    // DAU10/10B: derive scope from terminal when category field is absent
                    if (!r.category && ['DAU10', 'DAU10B'].includes(this.reportType)) {
                        if (!isIntTerminal(r.terminal)) return false;
                    }
                    if (['DAU10A'].includes(this.reportType)) {
                        const termStr = String(r.terminal || '').toUpperCase();
                        if (termStr && !['2E', '2F', '3U', '3', 'T2E', 'T2F', 'T3U'].includes(termStr)) return false;
                    }
                }

                // Terminal
                if (term !== 'ALL') {
                    const rTerm = String(r.terminal || '');
                    if (rTerm && rTerm.toLowerCase() !== term.toLowerCase()) return false;
                }

                // Airline
                if (alFilter !== 'ALL') {
                    const rAl = String(r.airline || r.operator_name || '');
                    if (rAl.toLowerCase() !== alFilter.toLowerCase()) return false;
                }

                // Airport
                if (apFilter !== 'ALL') {
                    const rAp = String(r.airport || r.city || r.airport_route || r.origin || '');
                    if (rAp.toLowerCase() !== apFilter.toLowerCase()) return false;
                }

                // Aircraft Type
                if (actFilter !== 'ALL') {
                    const rAct = String(r.aircraft_type || '');
                    if (rAct.toLowerCase() !== actFilter.toLowerCase()) return false;
                }

                // Hour
                if (hr !== 'ALL') {
                    const rHour = String(r.hour || r.period || '');
                    const cleanHr = hr.replace(/[^0-9]/g, '');
                    const cleanRHour = rHour.replace(/[^0-9]/g, '');
                    if (cleanHr && cleanRHour !== cleanHr && rHour !== hr) return false;
                }

                // Direction
                if (dir !== 'ALL') {
                    if (dir === 'ARRIVAL') {
                        const hasArr = (Number(r.aircraft_arrival || 0) > 0)
                                    || (Number(r.passenger_arrival || 0) > 0)
                                    || (Number(r.aircraft_arr_domestic || 0) > 0)
                                    || (Number(r.aircraft_arr_int || 0) > 0)
                                    || (Number(r.passenger_arr_domestic || 0) > 0)
                                    || (Number(r.passenger_arr_int || 0) > 0)
                                    || (Number(r.aircraft_int_arrival || 0) > 0)
                                    || (Number(r.aircraft_dom_arrival || 0) > 0);
                        if (!hasArr) return false;
                    } else if (dir === 'DEPARTURE') {
                        const hasDep = (Number(r.aircraft_departure || 0) > 0)
                                    || (Number(r.passenger_departure || 0) > 0)
                                    || (Number(r.aircraft_dep_domestic || 0) > 0)
                                    || (Number(r.aircraft_dep_int || 0) > 0)
                                    || (Number(r.passenger_dep_domestic || 0) > 0)
                                    || (Number(r.passenger_dep_int || 0) > 0)
                                    || (Number(r.aircraft_int_departure || 0) > 0)
                                    || (Number(r.aircraft_dom_departure || 0) > 0);
                        if (!hasDep) return false;
                    }
                }

                // DAU1 Passenger mode: exclude pure cargo / 0 passenger flights if Metric is passenger
                if (this.reportType === 'DAU1' && this.selectedMetric === 'passenger') {
                    const totPx = Number(r.passenger_total ?? r.total_passengers ?? 0);
                    if (totPx <= 0) return false;
                }

                // Passenger Type (DAU1)
                if (this.reportType === 'DAU1' && paxType !== 'ALL') {
                    const ad = Number(r.passenger_adult ?? r.adult ?? (Number(r.arr_adult || 0) + Number(r.dep_adult || 0)));
                    const ch = Number(r.passenger_child ?? r.child ?? (Number(r.arr_child || 0) + Number(r.dep_child || 0)));
                    const inf = Number(r.passenger_infant ?? r.infant ?? (Number(r.arr_infant || 0) + Number(r.dep_infant || 0)));
                    if (paxType === 'ADULT' && ad <= 0) return false;
                    if (paxType === 'CHILD' && ch <= 0) return false;
                    if (paxType === 'INFANT' && inf <= 0) return false;
                }

                // DAU10B Operation
                if (this.reportType === 'DAU10B' && op !== 'ALL') {
                    if (this.selectedMetric === 'passenger') {
                        if (op === 'BLOCK_ON' && Number(r.passenger_arrival || 0) === 0) return false;
                        if (op === 'BLOCK_OFF' && Number(r.passenger_departure || 0) === 0) return false;
                    } else {
                        if (op === 'BLOCK_ON' && Number(r.aircraft_arrival || 0) === 0) return false;
                        if (op === 'BLOCK_OFF' && Number(r.aircraft_departure || 0) === 0) return false;
                    }
                }

                // Schedule Type
                if (sched !== 'ALL' && r.schedule_type) {
                    if (!String(r.schedule_type).toUpperCase().includes(sched)) return false;
                }

                // Status
                if (st !== 'ALL') {
                    const sec = String(r.section || r.status || '').toUpperCase();
                    if (!sec.includes(st)) return false;
                }

                // Category & WTC for DAU6
                if (cat !== 'ALL' && r.category) {
                    if (!String(r.category).toLowerCase().includes(cat.toLowerCase())) return false;
                }
                if (wtc !== 'ALL' && r.wtc) {
                    if (!String(r.wtc).toLowerCase().includes(wtc.toLowerCase())) return false;
                }

                // Threshold (DAU4B)
                if (thresh > 0) {
                    const acTot = Number(r.aircraft_total || r.total_flights || 0);
                    if (acTot < thresh) return false;
                }

                // Search Query
                if (sq !== '') {
                    const haystack = JSON.stringify(r).toLowerCase();
                    if (!haystack.includes(sq)) return false;
                }

                return true;
            });

            this.recalculateAnalytics();
            this.updateAverageWindow();
            this.$nextTick(() => {
                this.updateCharts();
            });
        },

        recalculateAnalytics() {
            const sum = {
                total_movements: 0,
                aircraft_arrival: 0,
                aircraft_departure: 0,
                passenger_arrival: 0,
                passenger_departure: 0,
                passenger_transit: 0,
                passenger_transfer: 0,
                passenger_total: 0,
                passenger_adult: 0,
                passenger_child: 0,
                passenger_infant: 0,
                arr_adult: 0,
                arr_child: 0,
                arr_infant: 0,
                dep_adult: 0,
                dep_child: 0,
                dep_infant: 0,
                crew_total: 0,
                baggage_total: 0,
                cargo_total: 0,
                pos_total: 0,
            };

            const hourlyMap = {};
            const termMap = {};
            const airportMap = {};
            const airlineMap = {};
            let domAcft = 0, intAcft = 0;
            let domPax = 0, intPax = 0;
            let domBag = 0, intBag = 0;
            let domCgo = 0, intCgo = 0;
            let domPos = 0, intPos = 0;
            let niagaAcft = 0, bukanNiagaAcft = 0;
            let opCrew = 0, exCrew = 0, arrExCrew = 0, depExCrew = 0;

            (this.hours || []).forEach(h => {
                hourlyMap[h] = {
                    hour: h,
                    aircraft_arrival: 0,
                    aircraft_departure: 0,
                    aircraft_total: 0,
                    passenger_arrival: 0,
                    passenger_departure: 0,
                    passenger_transit: 0,
                    passenger_transfer: 0,
                    passenger_total: 0,
                    crew: 0,
                    extra_crew: 0,
                    crew_total: 0,
                };
            });

            this.filteredRecords.forEach(r => {
                const dir = this.filterDirection;
                const pType = this.filterPassengerType;
                const op = this.filterOperation;

                const acArr = Number(r.aircraft_arrival || (Number(r.aircraft_arr_domestic || 0) + Number(r.aircraft_arr_int || 0)) || (Number(r.aircraft_dom_arrival || 0) + Number(r.aircraft_int_arrival || 0)));
                const acDep = Number(r.aircraft_departure || (Number(r.aircraft_dep_domestic || 0) + Number(r.aircraft_dep_int || 0)) || (Number(r.aircraft_dom_departure || 0) + Number(r.aircraft_int_departure || 0)));
                const acTot = Number(r.aircraft_total || r.total_flights || (acArr + acDep));

                const pxArr = Number(r.passenger_arrival || (Number(r.passenger_arr_domestic || 0) + Number(r.passenger_arr_int || 0)) || (Number(r.passenger_dom_arrival || 0) + Number(r.passenger_int_arrival || 0)));
                const pxDep = Number(r.passenger_departure || (Number(r.passenger_dep_domestic || 0) + Number(r.passenger_dep_int || 0)) || (Number(r.passenger_dom_departure || 0) + Number(r.passenger_int_departure || 0)));
                const pxTrn = Number(r.passenger_transit || 0);
                const pxTrf = Number(r.passenger_transfer || 0);
                const pxTot = Number(r.passenger_total || r.total_passengers || (pxArr + pxDep + pxTrn + pxTrf));

                const adArr = Number(r.arr_adult || (r.details?.arr_adult || 0));
                const chArr = Number(r.arr_child || (r.details?.arr_child || 0));
                const infArr = Number(r.arr_infant || (r.details?.arr_infant || 0));
                const adDep = Number(r.dep_adult || (r.details?.dep_adult || 0));
                const chDep = Number(r.dep_child || (r.details?.dep_child || 0));
                const infDep = Number(r.dep_infant || (r.details?.dep_infant || 0));
                const adTot = Number(r.passenger_adult ?? r.adult ?? (adArr + adDep));
                const chTot = Number(r.passenger_child ?? r.child ?? (chArr + chDep));
                const infTot = Number(r.passenger_infant ?? r.infant ?? (infArr + infDep));

                const crw = Number(r.crew || 0);
                const exCrw = Number(r.extra_crew || (Number(r.arr_extra_crew || 0) + Number(r.dep_extra_crew || 0)));
                const crwTot = Number(r.crew_total || (crw + exCrw));

                const crew = crwTot;
                const bag = Number(r.baggage || 0);
                const cgo = Number(r.cargo || 0);
                const pos = Number(r.pos || 0);

                // Effective directional values
                let effAcArr = (dir === 'DEPARTURE') ? 0 : acArr;
                let effAcDep = (dir === 'ARRIVAL') ? 0 : acDep;
                let effAcTot = (dir === 'ARRIVAL') ? acArr : ((dir === 'DEPARTURE') ? acDep : acTot);

                let effPxArr = (dir === 'DEPARTURE') ? 0 : pxArr;
                let effPxDep = (dir === 'ARRIVAL') ? 0 : pxDep;
                let effPxTrn = (dir === 'ARRIVAL' || dir === 'DEPARTURE') ? 0 : pxTrn;
                let effPxTrf = (dir === 'ARRIVAL' || dir === 'DEPARTURE') ? 0 : pxTrf;
                let effPxTot = (dir === 'ARRIVAL') ? pxArr : ((dir === 'DEPARTURE') ? pxDep : pxTot);

                let effAd = adTot, effCh = chTot, effInf = infTot;
                if (pType === 'ADULT') {
                    const effAdArr = (dir === 'DEPARTURE') ? 0 : adArr;
                    const effAdDep = (dir === 'ARRIVAL') ? 0 : adDep;
                    effPxTot = (dir === 'ARRIVAL') ? effAdArr : ((dir === 'DEPARTURE') ? effAdDep : adTot);
                    effPxArr = effAdArr;
                    effPxDep = effAdDep;
                    effCh = 0; effInf = 0;
                } else if (pType === 'CHILD') {
                    const effChArr = (dir === 'DEPARTURE') ? 0 : chArr;
                    const effChDep = (dir === 'ARRIVAL') ? 0 : chDep;
                    effPxTot = (dir === 'ARRIVAL') ? effChArr : ((dir === 'DEPARTURE') ? effChDep : chTot);
                    effPxArr = effChArr;
                    effPxDep = effChDep;
                    effAd = 0; effInf = 0;
                } else if (pType === 'INFANT') {
                    const effInfArr = (dir === 'DEPARTURE') ? 0 : infArr;
                    const effInfDep = (dir === 'ARRIVAL') ? 0 : infDep;
                    effPxTot = (dir === 'ARRIVAL') ? effInfArr : ((dir === 'DEPARTURE') ? effInfDep : infTot);
                    effPxArr = effInfArr;
                    effPxDep = effInfDep;
                    effAd = 0; effCh = 0;
                }

                // For DAU10B, Operation filter restricts active movement direction to Block On or Block Off
                if (this.reportType === 'DAU10B' && op !== 'ALL') {
                    if (op === 'BLOCK_ON') {
                        effAcDep = 0;
                        effPxDep = 0;
                        effAcTot = effAcArr;
                        effPxTot = effPxArr;
                    } else if (op === 'BLOCK_OFF') {
                        effAcArr = 0;
                        effPxArr = 0;
                        effAcTot = effAcDep;
                        effPxTot = effPxDep;
                    }
                }

                sum.total_movements += effAcTot;
                sum.aircraft_arrival += effAcArr;
                sum.aircraft_departure += effAcDep;
                sum.passenger_arrival += effPxArr;
                sum.passenger_departure += effPxDep;
                sum.passenger_transit += effPxTrn;
                sum.passenger_transfer += effPxTrf;
                sum.passenger_total += effPxTot;
                sum.passenger_adult += effAd;
                sum.passenger_child += effCh;
                sum.passenger_infant += effInf;
                sum.arr_adult += (dir === 'DEPARTURE' ? 0 : adArr);
                sum.arr_child += (dir === 'DEPARTURE' ? 0 : chArr);
                sum.arr_infant += (dir === 'DEPARTURE' ? 0 : infArr);
                sum.dep_adult += (dir === 'ARRIVAL' ? 0 : adDep);
                sum.dep_child += (dir === 'ARRIVAL' ? 0 : chDep);
                sum.dep_infant += (dir === 'ARRIVAL' ? 0 : infDep);
                sum.crew_total += crew;
                sum.baggage_total += bag;
                sum.cargo_total += cgo;
                sum.pos_total += pos;

                // DAU2 & 3 Classification
                if (String(r.category || '').toUpperCase().includes('DOM')) {
                    domAcft += effAcTot;
                    domPax += effPxTot;
                    domBag += bag;
                    domCgo += cgo;
                    domPos += pos;
                } else {
                    intAcft += effAcTot;
                    intPax += effPxTot;
                    intBag += bag;
                    intCgo += cgo;
                    intPos += pos;
                }

                if (String(r.section || '').toUpperCase().includes('BUKAN')) {
                    bukanNiagaAcft += effAcTot;
                } else {
                    niagaAcft += effAcTot;
                }

                // DAU5A Extra Crew
                opCrew += crw;
                arrExCrew += Number(r.arr_extra_crew || 0);
                depExCrew += Number(r.dep_extra_crew || 0);
                exCrew += exCrw;

                // Groupings
                const h = r.hour || r.period;
                if (h) {
                    if (!hourlyMap[h]) hourlyMap[h] = { hour: h, aircraft_arrival: 0, aircraft_departure: 0, aircraft_total: 0, passenger_arrival: 0, passenger_departure: 0, passenger_transit: 0, passenger_transfer: 0, passenger_total: 0, crew: 0, extra_crew: 0, crew_total: 0 };
                    hourlyMap[h].aircraft_arrival += effAcArr;
                    hourlyMap[h].aircraft_departure += effAcDep;
                    hourlyMap[h].aircraft_total += effAcTot;
                    hourlyMap[h].passenger_arrival += effPxArr;
                    hourlyMap[h].passenger_departure += effPxDep;
                    hourlyMap[h].passenger_transit += effPxTrn;
                    hourlyMap[h].passenger_transfer += effPxTrf;
                    hourlyMap[h].passenger_total += effPxTot;
                    hourlyMap[h].crew += crw;
                    hourlyMap[h].extra_crew += exCrw;
                    hourlyMap[h].crew_total += crwTot;
                }

                const t = r.terminal;
                if (t) {
                    if (!termMap[t]) termMap[t] = { terminal: t, aircraft_total: 0, passenger_total: 0, crew_total: 0 };
                    termMap[t].aircraft_total += effAcTot;
                    termMap[t].passenger_total += effPxTot;
                    termMap[t].crew_total += crwTot;
                }

                const ap = r.airport || r.city || r.airport_route;
                if (ap) {
                    if (!airportMap[ap]) airportMap[ap] = {
                        airport: ap,
                        city: r.city || ap,
                        city_code: r.city_code || '',
                        aircraft_arrival: 0,
                        aircraft_departure: 0,
                        aircraft_total: 0,
                        passenger_arrival: 0,
                        passenger_departure: 0,
                        passenger_total: 0,
                        passenger_adult: 0,
                        passenger_child: 0,
                        passenger_infant: 0
                    };
                    airportMap[ap].aircraft_arrival += effAcArr;
                    airportMap[ap].aircraft_departure += effAcDep;
                    airportMap[ap].aircraft_total += effAcTot;
                    airportMap[ap].passenger_arrival += effPxArr;
                    airportMap[ap].passenger_departure += effPxDep;
                    airportMap[ap].passenger_total += effPxTot;
                    airportMap[ap].passenger_adult += effAd;
                    airportMap[ap].passenger_child += effCh;
                    airportMap[ap].passenger_infant += effInf;
                }

                const al = r.airline || r.operator_name;
                if (al) {
                    if (!airlineMap[al]) airlineMap[al] = { name: al, total: 0, pax: 0, baggage: 0, cargo: 0, pos: 0, routesCount: 0 };
                    airlineMap[al].total += effAcTot;
                    airlineMap[al].pax += effPxTot;
                    airlineMap[al].baggage += bag;
                    airlineMap[al].cargo += cgo;
                    airlineMap[al].pos += pos;
                    airlineMap[al].routesCount++;
                }
            });

            this.activeSummary = sum;
            this.activeHourlyDistribution = Object.values(hourlyMap);
            this.activeTerminalComparison = Object.values(termMap);

            // Peaks
            let peakAc = 0, peakAcH = '—';
            let peakPx = 0, peakPxH = '—';
            let peakCrw = 0, peakCrwH = '—';
            let peakBlkOn = 0, peakBlkOnH = '—';
            let peakBlkOff = 0, peakBlkOffH = '—';
            const isPax = this.selectedMetric === 'passenger';
            this.activeHourlyDistribution.forEach(hb => {
                if (hb.aircraft_total > peakAc) { peakAc = hb.aircraft_total; peakAcH = hb.hour; }
                if (hb.passenger_total > peakPx) { peakPx = hb.passenger_total; peakPxH = hb.hour; }
                if ((hb.crew_total || 0) > peakCrw) { peakCrw = hb.crew_total; peakCrwH = hb.hour; }

                const onVal = isPax ? Number(hb.passenger_arrival || 0) : Number(hb.aircraft_arrival || 0);
                const offVal = isPax ? Number(hb.passenger_departure || 0) : Number(hb.aircraft_departure || 0);
                if (onVal > peakBlkOn) { peakBlkOn = onVal; peakBlkOnH = hb.hour; }
                if (offVal > peakBlkOff) { peakBlkOff = offVal; peakBlkOffH = hb.hour; }
            });

            let peakT = '—', peakTV = 0;
            this.activeTerminalComparison.forEach(tb => {
                const val = this.selectedMetric === 'passenger' ? tb.passenger_total : (this.selectedMetric === 'crew' ? (tb.crew_total || 0) : tb.aircraft_total);
                if (val > peakTV) { peakTV = val; peakT = tb.terminal; }
            });

            let peakHour = peakAcH;
            if (this.selectedMetric === 'passenger') peakHour = peakPxH;
            else if (this.selectedMetric === 'crew') peakHour = peakCrwH;

            this.peaks = {
                peak_aircraft_hour: peakAcH,
                peak_aircraft: peakAc,
                peak_passenger_hour: peakPxH,
                peak_passenger: peakPx,
                peak_crew_hour: peakCrwH,
                peak_crew: peakCrw,
                peak_hour: peakHour,
                peak_terminal: peakT,
                peak_terminal_val: peakTV,
                peak_block_on_hour: peakBlkOnH,
                peak_block_on: peakBlkOn,
                peak_block_off_hour: peakBlkOffH,
                peak_block_off: peakBlkOff
            };

            // Secondary metrics & DAU-02 Comparative Matrix
            const buildComp = (key, label, dom, int, unit) => {
                const tot = dom + int;
                const domPct = tot > 0 ? Math.round((dom / tot) * 1000) / 10 : 0;
                const intPct = tot > 0 ? Math.round((int / tot) * 1000) / 10 : 0;
                return {
                    key: key,
                    label: label,
                    unit: unit,
                    domestic: dom,
                    international: int,
                    total: tot,
                    dom_pct: domPct,
                    int_pct: intPct,
                    dom_share_str: domPct.toFixed(1) + '%',
                    int_share_str: intPct.toFixed(1) + '%'
                };
            };

            this.dau2Comparative = {
                aircraft: buildComp('aircraft', 'Aircraft Movements', domAcft, intAcft, 'A/C'),
                passenger: buildComp('passenger', 'Passengers', domPax, intPax, 'PAX'),
                baggage: buildComp('baggage', 'Baggage', domBag, intBag, 'KG'),
                cargo: buildComp('cargo', 'Cargo', domCgo, intCgo, 'KG'),
                pos: buildComp('pos', 'POS / Mail', domPos, intPos, 'KG'),
            };
            this.dau2ComparativeList = Object.values(this.dau2Comparative);

            const activeComp = this.dau2Comparative[this.selectedMetric] || this.dau2Comparative.aircraft;
            this.dau2Metrics = {
                domAircraft: domAcft,
                intAircraft: intAcft,
                domValue: activeComp.domestic,
                intValue: activeComp.international,
                totValue: activeComp.total,
                domSharePct: activeComp.dom_pct,
                intSharePct: activeComp.int_pct,
                unit: activeComp.unit
            };

            this.dau3Metrics = {
                niagaAcft: niagaAcft,
                bukanNiagaAcft: bukanNiagaAcft,
                domAcft: domAcft,
                intAcft: intAcft
            };

            this.dau5aMetrics = {
                operatingCrew: opCrew,
                extraCrew: exCrew,
                arrExtraCrew: arrExCrew,
                depExtraCrew: depExCrew
            };

            // DAU-01 Operational Intelligence Ratios & Directional Balance
            if (this.reportType === 'DAU1') {
                const totM = Number(sum.total_movements || sum.aircraft_total || 0);
                const totP = Number(sum.passenger_total || 0);
                const totB = Number(sum.baggage_total || 0);
                const totC = Number(sum.cargo_total || 0);
                const arrM = Number(sum.aircraft_arrival || 0);
                const depM = Number(sum.aircraft_departure || 0);
                const mSum = arrM + depM;

                const paxPerFl = totM > 0 ? Math.round((totP / totM) * 10) / 10 : 0;
                const bagPerPx = totP > 0 ? Math.round((totB / totP) * 10) / 10 : 0;
                const cgoPerFlTon = totM > 0 ? Math.round(((totC / 1000) / totM) * 100) / 100 : 0;
                const cgoPerFlKg = totM > 0 ? Math.round((totC / totM) * 10) / 10 : 0;

                const inRatio = mSum > 0 ? Math.round((arrM / mSum) * 1000) / 10 : 50.0;
                const outRatio = mSum > 0 ? Math.round((depM / mSum) * 1000) / 10 : 50.0;
                const isHeavy = inRatio > 70.0;
                let dirStatus = 'BALANCED';
                if (inRatio > 70.0) dirStatus = 'INBOUND HEAVY';
                else if (outRatio > 70.0) dirStatus = 'OUTBOUND HEAVY';
                else if (inRatio > 55.0) dirStatus = 'INBOUND SLIGHT';
                else if (outRatio > 55.0) dirStatus = 'OUTBOUND SLIGHT';

                const pAd = Number(sum.passenger_adult || 0);
                const pCh = Number(sum.passenger_child || 0);
                const pInf = Number(sum.passenger_infant || 0);
                const pSum = totP > 0 ? totP : (pAd + pCh + pInf);
                const adPct = pSum > 0 ? Math.round((pAd / pSum) * 1000) / 10 : 0;
                const chPct = pSum > 0 ? Math.round((pCh / pSum) * 1000) / 10 : 0;
                const infPct = pSum > 0 ? Math.round((pInf / pSum) * 1000) / 10 : 0;

                this.dau1Ratios = {
                    paxPerFlight: paxPerFl,
                    baggagePerPax: bagPerPx,
                    cargoDensityTon: cgoPerFlTon,
                    cargoDensityKg: cgoPerFlKg,
                    arrMovements: arrM,
                    depMovements: depM,
                    totalMovements: totM,
                    inboundRatio: inRatio,
                    outboundRatio: outRatio,
                    isInboundHeavy: isHeavy,
                    directionalStatus: dirStatus,
                    adult: pAd,
                    child: pCh,
                    infant: pInf,
                    totalPax: pSum,
                    adultPct: adPct,
                    childPct: chPct,
                    infantPct: infPct
                };
            }

            // DAU4 Diverging / DAU1 Route Ranking
            const topN = this.filterTopN === 'ALL' ? 50 : Number(this.filterTopN);
            const allAp = Object.values(airportMap);
            // isPax already declared above (line ~3288) — reuse it here
            const paxType = this.filterPassengerType;

            const arrSorted = [...allAp].sort((a, b) => {
                if (isPax) {
                    if (paxType === 'ADULT') return b.passenger_adult - a.passenger_adult;
                    if (paxType === 'CHILD') return b.passenger_child - a.passenger_child;
                    if (paxType === 'INFANT') return b.passenger_infant - a.passenger_infant;
                    return b.passenger_total - a.passenger_total;
                }
                return b.aircraft_arrival - a.aircraft_arrival;
            });
            const depSorted = [...allAp].sort((a, b) => {
                if (isPax) {
                    if (paxType === 'ADULT') return b.passenger_adult - a.passenger_adult;
                    if (paxType === 'CHILD') return b.passenger_child - a.passenger_child;
                    if (paxType === 'INFANT') return b.passenger_infant - a.passenger_infant;
                    return b.passenger_total - a.passenger_total;
                }
                return b.aircraft_departure - a.aircraft_departure;
            });
            this.dau4Diverging = {
                top_arrival: arrSorted.slice(0, topN),
                top_departure: depSorted.slice(0, topN)
            };

            // DAU4A Operators
            const opMetricKey = (this.selectedMetric === 'passenger') ? 'pax' :
                               (this.selectedMetric === 'baggage') ? 'baggage' :
                               (this.selectedMetric === 'cargo') ? 'cargo' :
                               (this.selectedMetric === 'pos') ? 'pos' : 'total';
            this.dau4aOperators = Object.values(airlineMap).sort((a, b) => (b[opMetricKey] || 0) - (a[opMetricKey] || 0));

            // DAU4B Matrix Data
            if (this.reportType === 'DAU4B') {
                const topCities = [...new Set(this.filteredRecords.map(r => r.city || r.airport))].filter(Boolean).slice(0, 20);
                const topAirlines = [...new Set(this.filteredRecords.map(r => r.airline || r.operator_name))].filter(Boolean).slice(0, 12);
                const grid = {};
                this.filteredRecords.forEach(r => {
                    const c = r.city || r.airport;
                    const a = r.airline || r.operator_name;
                    if (!c || !a) return;
                    if (!grid[c]) grid[c] = {};
                    grid[c][a] = (grid[c][a] || 0) + (this.selectedMetric === 'passenger' ? Number(r.passenger_total || 0) : Number(r.aircraft_total || 0));
                });
                this.dau4bMatrixData = { cities: topCities, airlines: topAirlines, grid: grid };
            }
        },

        get maxDau4Val() {
            let max = 1;
            (this.dau4Diverging.top_arrival || []).forEach(r => {
                const v = this.selectedMetric === 'passenger' ? r.passenger_arrival : r.aircraft_arrival;
                if (v > max) max = v;
            });
            (this.dau4Diverging.top_departure || []).forEach(r => {
                const v = this.selectedMetric === 'passenger' ? r.passenger_departure : r.aircraft_departure;
                if (v > max) max = v;
            });
            return max;
        },

        get top10RouteSharePct() {
            if (this.dau4Intelligence?.top_n_plus_others?.others) {
                const oth = Number(this.dau4Intelligence.top_n_plus_others.others.share_pct || 0);
                return Math.max(0, Math.round((100 - oth) * 10) / 10);
            }
            const arr = this.dau4Diverging.top_arrival || [];
            const totalTop = arr.slice(0, 10).reduce((s, r) => s + (this.selectedMetric === 'passenger' ? Number(r.passenger_arrival || 0) : Number(r.aircraft_arrival || 0)), 0);
            const totalAll = arr.reduce((s, r) => s + (this.selectedMetric === 'passenger' ? Number(r.passenger_arrival || 0) : Number(r.aircraft_arrival || 0)), 0);
            return totalAll > 0 ? Math.round((totalTop / totalAll) * 1000) / 10 : 80;
        },

        get othersRouteSharePct() {
            return Math.max(0, Math.round((100 - this.top10RouteSharePct) * 10) / 10);
        },

        get availableDau4aRoutes() {
            if (this.dau4aMarketShare?.routes && Object.keys(this.dau4aMarketShare.routes).length > 0) {
                return Object.keys(this.dau4aMarketShare.routes);
            }
            return [...new Set(this.filteredRecords.map(r => r.city || r.airport || r.airport_route))].filter(Boolean);
        },

        get activeDau4aRouteBreakdown() {
            const rKey = this.dau4aSelectedRoute || (this.availableDau4aRoutes[0] || '');
            if (!rKey) return null;
            if (this.dau4aMarketShare?.routes?.[rKey]) {
                return this.dau4aMarketShare.routes[rKey];
            }
            const recs = this.filteredRecords.filter(r => (r.city || r.airport || r.airport_route) === rKey);
            if (recs.length === 0) return null;
            const alMap = {};
            let total = 0;
            recs.forEach(r => {
                const al = r.airline || r.operator_name || 'Unknown';
                const v = this.selectedMetric === 'passenger' ? Number(r.passenger_total || 0) : Number(r.aircraft_total || 1);
                alMap[al] = (alMap[al] || 0) + v;
                total += v;
            });
            const carriers = Object.entries(alMap).map(([carrier, count]) => ({
                carrier,
                movements: count,
                market_share_pct: total > 0 ? Math.round((count / total) * 1000) / 10 : 0
            })).sort((a, b) => b.movements - a.movements);
            return {
                route: rKey,
                total_movements: total,
                dominant_carrier: carriers[0]?.carrier || '—',
                dominant_share_pct: carriers[0]?.market_share_pct || 0,
                carriers: carriers
            };
        },

        getPaletteColor(idx) {
            const colors = ['#0284c7', '#2563eb', '#7c3aed', '#f59e0b', '#10b981', '#ec4899', '#f97316', '#06b6d4', '#64748b'];
            return colors[idx % colors.length];
        },

        get dau4aMax() {
            const k = (this.selectedMetric === 'passenger') ? 'pax' : (this.selectedMetric === 'baggage') ? 'baggage' : (this.selectedMetric === 'cargo') ? 'cargo' : (this.selectedMetric === 'pos') ? 'pos' : 'total';
            return Math.max(...(this.dau4aOperators.map(o => o[k] || 0) || [1]), 1);
        },

        getDau4bValue(city, air) {
            return this.dau4bMatrixData.grid?.[city]?.[air] || 0;
        },

        getDau4bColor(city, air) {
            const v = this.getDau4bValue(city, air);
            if (v === 0) return 'transparent';
            if (v < 5) return 'rgba(56, 189, 248, 0.35)'; // Low: Light Blue
            if (v < 15) return 'rgba(245, 158, 11, 0.65)'; // Mid: Amber
            return 'rgba(225, 29, 72, 0.85)'; // Peak: Crimson
        },

        getHeatmapValue(term, hour) {
            const rec = this.filteredRecords.find(r => 
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
            const maxVal = this.selectedMetric === 'passenger' ? 3000 : 25;
            const ratio = Math.min(1, val / maxVal);
            if (ratio < 0.20) return 'rgba(147, 197, 253, 0.25)';
            if (ratio < 0.45) return 'rgba(96, 165, 250, 0.45)';
            if (ratio < 0.70) return 'rgba(59, 130, 246, 0.70)';
            return 'rgba(29, 78, 216, 0.90)';
        },

        get maxAircraftPerHour() {
            return Math.max(...this.activeHourlyDistribution.map(h => h.aircraft_total), 1);
        },

        get maxPassengerPerHour() {
            return Math.max(...this.activeHourlyDistribution.map(h => h.passenger_total), 1);
        },

        calculateBarHeight(val, max) {
            if (!val || val <= 0 || !max) return 3;
            return Math.max(4, Math.round((val / max) * 95));
        },

        formatNumber(val) {
            if (val === null || val === undefined || isNaN(val)) return '0';
            return Number(val).toLocaleString('id-ID');
        },

        formatHourDisplay(hourStr) {
            return hourStr || '—';
        },

        // Table Pagination & Sorting
        sortBy(col) {
            if (this.sortCol === col) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortCol = col;
                this.sortAsc = true;
            }
        },

        get sortedRecords() {
            const list = [...this.filteredRecords];
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

        // Export Query URLs
        get filterQueryParams() {
            const p = new URLSearchParams();
            p.set('flight_type', this.filterFlightType);
            p.set('terminal', this.filterTerminal);
            p.set('hour', this.filterHour);
            p.set('direction', this.filterDirection);
            p.set('metric', this.selectedMetric);
            p.set('passenger_type', this.filterPassengerType);
            p.set('display_mode', this.displayMode);
            p.set('airline', this.filterAirline);
            p.set('airport', this.filterAirport);
            p.set('aircraft_type', this.filterAircraftType);
            p.set('operation', this.filterOperation);
            p.set('schedule_type', this.filterScheduleType);
            p.set('status', this.filterStatus);
            p.set('category', this.filterCategory);
            p.set('wtc', this.filterWtc);
            p.set('top_n', this.filterTopN);
            p.set('threshold', this.filterThreshold);
            if (this.searchQuery) p.set('search', this.searchQuery);
            p.set('arr_nac', this.arrivalCapacity);
            p.set('dep_nac', this.departureCapacity);
            p.set('ops_start', this.opsStartTime);
            p.set('ops_end', this.opsEndTime);
            if (this.reportType === 'DAU10A' && this.selectedMetric === 'aircraft') {
                p.set('avg_period', this.averagePeriod);
                p.set('avg_days', this.selectedAverageDays);
            }
            return p.toString();
        },

        get exportPdfUrl() {
            return `{{ route('dau.export.pdf', $upload->id) }}?${this.filterQueryParams}`;
        },

        get exportCsvUrl() {
            return `{{ route('dau.export.excel', $upload->id) }}?${this.filterQueryParams}`;
        },

        async downloadPdfReport() {
            this.isExportingPdf = true;
            this.pdfButtonText = 'Generating PDF...';
            try {
                const res = await fetch(this.exportPdfUrl);
                if (!res.ok) throw new Error('PDF Export failed');
                const blob = await res.blob();
                const disposition = res.headers.get('content-disposition');
                let filename = 'DAU_Report.pdf';
                if (disposition && disposition.includes('filename=')) {
                    const m = disposition.match(/filename="?([^"]+)"?/);
                    if (m && m[1]) filename = m[1];
                }
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                this.pdfButtonText = 'Downloaded!';
                setTimeout(() => { this.pdfButtonText = 'Export PDF'; this.isExportingPdf = false; }, 1500);
            } catch (e) {
                alert('PDF download encountered an error.');
                this.pdfButtonText = 'Export PDF';
                this.isExportingPdf = false;
            }
        },

        // DAU-10A Capacity Analysis
        get hourlyCapacityAnalysis() {
            const isPax = this.selectedMetric === 'passenger';
            const isCrew = this.selectedMetric === 'crew';
            const arrCap = Number(this.arrivalCapacity) || 6;
            const depCap = Number(this.departureCapacity) || 6;
            let avail = 0, full = 0, over = 0, off = 0;

            const list = this.activeHourlyDistribution.map(item => {
                if (isPax) {
                    const arr = Number(item.passenger_arrival || 0);
                    const dep = Number(item.passenger_departure || 0);
                    const transit = Number(item.passenger_transit || 0);
                    const transfer = Number(item.passenger_transfer || 0);
                    const demand = Number(item.passenger_total || (arr + dep + transit + transfer));
                    return {
                        hour: item.hour,
                        shortLabel: String(item.hour).split(/[.:\s-–]/)[0].padStart(2, '0'),
                        label: item.hour,
                        isOps: true,
                        arr: arr,
                        dep: dep,
                        transit: transit,
                        transfer: transfer,
                        arrCap: null,
                        depCap: null,
                        arrStatus: '',
                        depStatus: '',
                        opc: 'N/A',
                        demand: demand,
                        status: ''
                    };
                } else if (isCrew) {
                    const arr = Number(item.crew || 0);
                    const dep = Number(item.extra_crew || 0);
                    const demand = Number(item.crew_total || (arr + dep));
                    return {
                        hour: item.hour,
                        shortLabel: String(item.hour).split(/[.:\s-–]/)[0].padStart(2, '0'),
                        label: item.hour,
                        isOps: true,
                        arr: arr,
                        dep: dep,
                        arrCap: null,
                        depCap: null,
                        arrStatus: '',
                        depStatus: '',
                        opc: 'N/A',
                        demand: demand,
                        status: ''
                    };
                } else {
                    const rawArr = Number(item.aircraft_arrival || 0);
                    const rawDep = Number(item.aircraft_departure || 0);

                    // Average By Days Transformation (strictly ceil rounding per directional movement)
                    let arr = rawArr;
                    let dep = rawDep;
                    if (this.reportType === 'DAU10A' && this.selectedMetric === 'aircraft' && this.averagePeriod !== 'original' && this.selectedAverageDays > 1) {
                        arr = Math.ceil(rawArr / this.selectedAverageDays);
                        dep = Math.ceil(rawDep / this.selectedAverageDays);
                    }
                    const demand = arr + dep;

                    const is24h = (this.opsStartTime === '00:00' && (this.opsEndTime === '24:00' || this.opsEndTime === '23:59'));
                    let isOff = false;
                    if (!is24h) {
                        const hNum = parseInt(String(item.hour).split(/[.:]/)[0], 10);
                        const sNum = parseInt(this.opsStartTime.split(/[.:]/)[0], 10);
                        const eNum = parseInt(this.opsEndTime.split(/[.:]/)[0], 10);
                        if (hNum < sNum || hNum >= eNum) isOff = true;
                    }

                    let arrSt = 'AVAILABLE';
                    if (isOff) {
                        arrSt = 'OFF HOURS';
                    } else if (arr > arrCap) {
                        arrSt = 'OVER CAPACITY';
                    } else if (arr === arrCap && arrCap > 0) {
                        arrSt = 'FULL / MAX';
                    }

                    let depSt = 'AVAILABLE';
                    if (isOff) {
                        depSt = 'OFF HOURS';
                    } else if (dep > depCap) {
                        depSt = 'OVER CAPACITY';
                    } else if (dep === depCap && depCap > 0) {
                        depSt = 'FULL / MAX';
                    }

                    let st = 'AVAILABLE';
                    if (isOff) {
                        st = 'OFF HOURS';
                        off++;
                    } else if (arr > arrCap || dep > depCap) {
                        st = 'OVER CAPACITY';
                        over++;
                    } else if (arr === arrCap || dep === depCap) {
                        st = 'FULL / MAX';
                        full++;
                    } else {
                        st = 'AVAILABLE';
                        avail++;
                    }

                    return {
                        hour: item.hour,
                        shortLabel: String(item.hour).split(/[.:\s-–]/)[0].padStart(2, '0'),
                        label: item.hour,
                        isOps: !isOff,
                        arr: arr,
                        dep: dep,
                        rawArr: rawArr,
                        rawDep: rawDep,
                        arrCap: arrCap,
                        depCap: depCap,
                        arrStatus: arrSt,
                        depStatus: depSt,
                        opc: 'N/A',
                        demand: demand,
                        status: st
                    };
                }
            });

            return {
                list: list,
                summary: { available: avail, full: full, over: over, off: off }
            };
        },

        get chartMaxScale() {
            const list = (this.hourlyCapacityAnalysis && this.hourlyCapacityAnalysis.list) ? this.hourlyCapacityAnalysis.list : [];
            const maxArr = Math.max(...list.map(d => Number(d.arr || 0)), 0);
            const maxDep = Math.max(...list.map(d => Number(d.dep || 0)), 0);
            if (this.selectedMetric === 'aircraft') {
                const maxCap = Math.max(Number(this.arrivalCapacity || 6), Number(this.departureCapacity || 6));
                const maxMovement = Math.max(maxArr, maxDep, maxCap);
                return Math.max(Math.ceil(maxMovement * 1.15), maxMovement + 2, 8);
            }
            const maxMovement = Math.max(maxArr, maxDep);
            return Math.max(Math.ceil(maxMovement * 1.15), maxMovement + 2, 8);
        },

        // DAU-10A Average By Days Actions
        setAverageDays(mode, days) {
            this.customDayError = '';
            this.averagePeriod = mode;
            if (mode === 'original') {
                this.selectedAverageDays = 1;
            } else if (mode === 'custom') {
                if (this.customInputDays) {
                    this.applyCustomAverageDays();
                    return;
                }
            } else {
                const n = parseInt(days || mode, 10);
                if (!isNaN(n) && n >= 1 && n <= this.totalAvailableDays) {
                    this.selectedAverageDays = n;
                }
            }
            this.updateAverageWindow();
            this.$nextTick(() => { this.updateCharts(); });
        },

        applyCustomAverageDays() {
            const raw = String(this.customInputDays || '').trim();
            this.customDayError = '';

            if (!/^\d+$/.test(raw)) {
                this.customDayError = 'Please enter a valid positive whole number of days.';
                return;
            }

            const num = parseInt(raw, 10);
            if (isNaN(num) || num < 1) {
                this.customDayError = 'Days must be an integer greater than or equal to 1.';
                return;
            }

            if (num > 365) {
                this.customDayError = 'Custom average period cannot exceed 365 days.';
                return;
            }

            this.selectedAverageDays = num;
            this.customAverageDays = num;
            this.averagePeriod = 'custom';
            this.updateAverageWindow();
            this.$nextTick(() => { this.updateCharts(); });
        },

        resetAverageDays() {
            this.averagePeriod = 'original';
            this.selectedAverageDays = 1;
            this.customInputDays = '';
            this.customDayError = '';
            this.updateAverageWindow();
            this.$nextTick(() => { this.updateCharts(); });
        },

        formatDateDisplay(isoDate) {
            if (!isoDate || typeof isoDate !== 'string') return '—';
            const clean = isoDate.trim();
            if (!clean) return '—';
            const parts = clean.split('-');
            if (parts.length === 3 && parts[0].length === 4) {
                return `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
            if (clean.includes('/')) {
                const slashParts = clean.split('/');
                if (slashParts.length === 3) {
                    if (slashParts[0].length === 4) {
                        return `${slashParts[2]}-${slashParts[1]}-${slashParts[0]}`;
                    }
                    return `${slashParts[0]}-${slashParts[1]}-${slashParts[2]}`;
                }
            }
            return clean;
        },

        updateAverageWindow() {
            const availDates = (this.availableDates && this.availableDates.length > 0)
                ? this.availableDates
                : (this.meta.start_date ? [this.meta.start_date] : []);
            const nDays = (this.averagePeriod === 'original' || !this.selectedAverageDays)
                ? this.totalAvailableDays
                : Math.min(this.selectedAverageDays, this.totalAvailableDays);
            const selectedDates = availDates.slice(0, nDays);
            this.averageDataUsedDays = (this.averagePeriod === 'original')
                ? this.totalAvailableDays
                : this.selectedAverageDays;
            this.averageWindowStartDate = selectedDates[0] || (this.meta.start_date ?? '');
            this.averageWindowEndDate = selectedDates[selectedDates.length - 1] || (this.meta.end_date ?? this.averageWindowStartDate);
        },

        get gridArrNacOffsetPx() {
            const ratio = Math.min(1, Math.max(0, (Number(this.arrivalCapacity) || 6) / this.chartMaxScale));
            return Math.round(ratio * 115);
        },

        get gridDepNacOffsetPx() {
            const ratio = Math.min(1, Math.max(0, (Number(this.departureCapacity) || 6) / this.chartMaxScale));
            return Math.round(ratio * 115);
        },

        get gridNacOffsetPx() {
            return this.gridArrNacOffsetPx;
        },

        get gridHalfNacOffsetPx() {
            const ratio = Math.min(1, Math.max(0, ((Number(this.arrivalCapacity) || 6) * 0.5) / this.chartMaxScale));
            return Math.round(ratio * 115);
        },

        get envelopeCoords() {
            if (this.selectedMetric !== 'aircraft') {
                return { left: 0, width: 100, top: 20, bottom: 20, isVisible: false };
            }
            const list = (this.hourlyCapacityAnalysis && this.hourlyCapacityAnalysis.list) ? this.hourlyCapacityAnalysis.list : [];
            const totalCols = list.length;
            if (totalCols === 0) {
                return { left: 0, width: 100, top: 20, bottom: 20, isVisible: false };
            }
            
            let startIndex = list.findIndex(d => d.isOps);
            let endIndex = -1;
            for (let i = list.length - 1; i >= 0; i--) {
                if (list[i].isOps) {
                    endIndex = i;
                    break;
                }
            }
            
            if (startIndex === -1 || endIndex === -1) {
                return { left: 0, width: 100, top: 20, bottom: 20, isVisible: false };
            }
            
            const leftPct = (startIndex / totalCols) * 100;
            const widthPct = ((endIndex - startIndex + 1) / totalCols) * 100;
            
            const arrRatio = Math.min(1, Math.max(0, (Number(this.arrivalCapacity) || 6) / this.chartMaxScale));
            const depRatio = Math.min(1, Math.max(0, (Number(this.departureCapacity) || 6) / this.chartMaxScale));
            const topPx = Math.max(4, Math.round(140 - (arrRatio * 115)));
            const bottomPx = Math.max(4, Math.round(140 - (depRatio * 115)));
            
            return {
                left: leftPct,
                width: widthPct,
                top: topPx,
                bottom: bottomPx,
                isVisible: true
            };
        },

        renderDau1Charts() {
            if (!window.Chart) return;
            const ctxCombo = document.getElementById('dau1ComboChart')?.getContext('2d');
            if (ctxCombo) {
                if (this.chartInstances.dau1Combo) {
                    this.chartInstances.dau1Combo.destroy();
                }
                const routes = (this.dau4Diverging.top_arrival || []).slice(0, 10);
                const labels = routes.map(r => r.city || r.airport || 'Route');

                if (this.selectedMetric === 'passenger') {
                    let datasets = [];
                    const pType = this.filterPassengerType;
                    if (pType === 'ADULT') {
                        datasets = [
                            { label: 'Dewasa (Adult)', data: routes.map(r => r.passenger_adult || 0), backgroundColor: '#2563eb' }
                        ];
                    } else if (pType === 'CHILD') {
                        datasets = [
                            { label: 'Anak (Child)', data: routes.map(r => r.passenger_child || 0), backgroundColor: '#0284c7' }
                        ];
                    } else if (pType === 'INFANT') {
                        datasets = [
                            { label: 'Bayi (Infant)', data: routes.map(r => r.passenger_infant || 0), backgroundColor: '#a855f7' }
                        ];
                    } else {
                        datasets = [
                            { label: 'Dewasa (Adult)', data: routes.map(r => r.passenger_adult || 0), backgroundColor: '#2563eb', stack: 'pax' },
                            { label: 'Anak (Child)', data: routes.map(r => r.passenger_child || 0), backgroundColor: '#0284c7', stack: 'pax' },
                            { label: 'Bayi (Infant)', data: routes.map(r => r.passenger_infant || 0), backgroundColor: '#a855f7', stack: 'pax' }
                        ];
                    }
                    this.chartInstances.dau1Combo = new Chart(ctxCombo, {
                        type: 'bar',
                        data: { labels: labels, datasets: datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        afterBody: (items) => {
                                            const idx = items[0]?.dataIndex;
                                            if (idx !== undefined && routes[idx]) {
                                                const r = routes[idx];
                                                return [
                                                    `Total Pax: ${(r.passenger_total || 0).toLocaleString('id-ID')}`,
                                                    `Dewasa: ${(r.passenger_adult || 0).toLocaleString('id-ID')}`,
                                                    `Anak: ${(r.passenger_child || 0).toLocaleString('id-ID')}`,
                                                    `Bayi: ${(r.passenger_infant || 0).toLocaleString('id-ID')}`
                                                ];
                                            }
                                            return [];
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: { stacked: true },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    title: { display: true, text: 'Passengers (Pax)' }
                                }
                            }
                        }
                    });
                } else {
                    this.chartInstances.dau1Combo = new Chart(ctxCombo, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                { label: 'Arrival A/C', data: routes.map(r => r.aircraft_arrival), backgroundColor: '#f59e0b', yAxisID: 'y' },
                                { label: 'Departure A/C', data: routes.map(r => r.aircraft_departure), backgroundColor: '#2563eb', yAxisID: 'y' },
                                { label: 'Total Passengers', data: routes.map(r => r.passenger_total), type: 'line', borderColor: '#10b981', backgroundColor: '#10b981', yAxisID: 'y1', tension: 0.3 }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: 'Aircraft Movements' } },
                                y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Passengers' } }
                            }
                        }
                    });
                }
            }

            const ctxPayload = document.getElementById('dau1PayloadChart')?.getContext('2d');
            if (ctxPayload) {
                if (this.chartInstances.dau1Payload) {
                    this.chartInstances.dau1Payload.destroy();
                }
                if (this.selectedMetric === 'passenger') {
                    const ad = this.activeSummary.passenger_adult || 0;
                    const ch = this.activeSummary.passenger_child || 0;
                    const inf = this.activeSummary.passenger_infant || 0;
                    const tot = (ad + ch + inf) || 1;
                    this.chartInstances.dau1Payload = new Chart(ctxPayload, {
                        type: 'doughnut',
                        data: {
                            labels: ['Dewasa (Adult)', 'Anak (Child)', 'Bayi (Infant)'],
                            datasets: [{
                                data: [ad, ch, inf],
                                backgroundColor: ['#2563eb', '#10b981', '#f59e0b'],
                                hoverBackgroundColor: ['#1d4ed8', '#059669', '#d97706'],
                                borderWidth: 2,
                                borderColor: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '68%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 10,
                                        font: { size: 11, weight: 'bold' },
                                        padding: 12
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) {
                                            const v = ctx.parsed || 0;
                                            const pct = ((v / tot) * 100).toFixed(1);
                                            return ` ${ctx.label}: ${v.toLocaleString('id-ID')} Pax (${pct}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    const bg = this.activeSummary.baggage_total || 0;
                    const cg = this.activeSummary.cargo_total || 0;
                    const totKg = (bg + cg) || 1;
                    this.chartInstances.dau1Payload = new Chart(ctxPayload, {
                        type: 'doughnut',
                        data: {
                            labels: ['Baggage (Kg)', 'Cargo (Kg)'],
                            datasets: [{
                                data: [bg, cg],
                                backgroundColor: ['#f43f5e', '#0d9488'],
                                hoverBackgroundColor: ['#e11d48', '#0f766e'],
                                borderWidth: 2,
                                borderColor: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '68%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 10,
                                        font: { size: 11, weight: 'bold' },
                                        padding: 12
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) {
                                            const v = ctx.parsed || 0;
                                            const pct = ((v / totKg) * 100).toFixed(1);
                                            return ` ${ctx.label}: ${v.toLocaleString('id-ID')} Kg (${pct}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
        },

        renderDau2Charts() {
            if (!window.Chart) return;
            const ctxSt = document.getElementById('dau2StackedChart')?.getContext('2d');
            if (ctxSt) {
                if (this.chartInstances.dau2Stacked) {
                    this.chartInstances.dau2Stacked.destroy();
                }
                const comp = this.dau2Comparative || {};
                const isPct = this.displayMode === 'percentage';
                const metricsList = [
                    { label: 'Aircraft Movements', key: 'aircraft' },
                    { label: 'Passengers', key: 'passenger' },
                    { label: 'Baggage (Kg)', key: 'baggage' },
                    { label: 'Cargo (Kg)', key: 'cargo' },
                    { label: 'POS / Mail (Kg)', key: 'pos' },
                ];
                const labels = metricsList.map(m => m.label);
                const domData = metricsList.map(m => {
                    const item = comp[m.key] || {};
                    return isPct ? (item.dom_pct || 0) : (item.domestic || 0);
                });
                const intData = metricsList.map(m => {
                    const item = comp[m.key] || {};
                    return isPct ? (item.int_pct || 0) : (item.international || 0);
                });

                this.chartInstances.dau2Stacked = new Chart(ctxSt, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: isPct ? 'Domestic (%)' : 'Domestic', data: domData, backgroundColor: '#2563eb' },
                            { label: isPct ? 'International (%)' : 'International', data: intData, backgroundColor: '#4f46e5' }
                        ]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                stacked: true,
                                max: isPct ? 100 : undefined,
                                ticks: {
                                    callback: (val) => isPct ? `${val}%` : Number(val).toLocaleString('id-ID')
                                }
                            },
                            y: { stacked: true }
                        }
                    }
                });
            }

            const ctxSh = document.getElementById('dau2ShareDonut')?.getContext('2d');
            if (ctxSh) {
                if (this.chartInstances.dau2Share) {
                    this.chartInstances.dau2Share.destroy();
                }
                const activeComp = (this.dau2Comparative && this.dau2Comparative[this.selectedMetric]) ? this.dau2Comparative[this.selectedMetric] : (this.dau2Comparative?.aircraft || {});
                const domVal = activeComp.domestic || 0;
                const intVal = activeComp.international || 0;
                this.chartInstances.dau2Share = new Chart(ctxSh, {
                    type: 'doughnut',
                    data: {
                        labels: ['Domestic', 'International'],
                        datasets: [{
                            data: [domVal || 1, intVal || 0],
                            backgroundColor: ['#2563eb', '#4f46e5']
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        },

        renderDau3Charts() {
            if (!window.Chart) return;
            const ctxSt = document.getElementById('dau3StatusDonut')?.getContext('2d');
            if (ctxSt) {
                if (this.chartInstances.dau3Status) this.chartInstances.dau3Status.destroy();
                this.chartInstances.dau3Status = new Chart(ctxSt, {
                    type: 'doughnut',
                    data: {
                        labels: ['Niaga (Commercial)', 'Bukan Niaga'],
                        datasets: [{ data: [this.dau3Metrics.niagaAcft || 1, this.dau3Metrics.bukanNiagaAcft || 0], backgroundColor: ['#0284c7', '#f59e0b'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
            const ctxCat = document.getElementById('dau3CategoryDonut')?.getContext('2d');
            if (ctxCat) {
                if (this.chartInstances.dau3Cat) this.chartInstances.dau3Cat.destroy();
                this.chartInstances.dau3Cat = new Chart(ctxCat, {
                    type: 'doughnut',
                    data: {
                        labels: ['Domestik', 'Internasional'],
                        datasets: [{ data: [this.dau3Metrics.domAcft || 1, this.dau3Metrics.intAcft || 0], backgroundColor: ['#2563eb', '#4f46e5'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        },

        renderDau5Charts() {
            if (!window.Chart) return;
            const ctxPareto = document.getElementById('dau5ParetoChart')?.getContext('2d');
            if (!ctxPareto) return;
            if (this.chartInstances.dau5Pareto) {
                this.chartInstances.dau5Pareto.stop();
                this.chartInstances.dau5Pareto.destroy();
                this.chartInstances.dau5Pareto = null;
            }

            const m = this.selectedMetric;
            const dir = this.filterDirection;

            // Aggregate per-airline from filteredRecords — respects metric AND direction filter
            const airlineData = {};
            this.filteredRecords.forEach(r => {
                const al = r.airline || r.operator_name;
                if (!al) return;
                if (!airlineData[al]) airlineData[al] = { name: al, val: 0, acArr: 0, acDep: 0 };

                let v = 0;
                if (m === 'passenger') {
                    v = dir === 'ARRIVAL'   ? Number(r.passenger_arrival || 0)
                      : dir === 'DEPARTURE' ? Number(r.passenger_departure || 0)
                      :                       Number(r.passenger_total || 0);
                } else if (m === 'baggage') {
                    v = Number(r.baggage || 0);
                } else if (m === 'cargo') {
                    v = Number(r.cargo || 0);
                } else if (m === 'pos') {
                    v = Number(r.pos || 0);
                } else {
                    // aircraft
                    const acArr = Number(r.aircraft_arrival || 0);
                    const acDep = Number(r.aircraft_departure || 0);
                    const acTot = Number(r.aircraft_total || (acArr + acDep));
                    v = dir === 'ARRIVAL'   ? acArr
                      : dir === 'DEPARTURE' ? acDep
                      :                       acTot;
                }
                airlineData[al].val    += v;
                airlineData[al].acArr  += Number(r.aircraft_arrival || 0);
                airlineData[al].acDep  += Number(r.aircraft_departure || 0);
            });

            // Sort descending by selected metric value
            const sorted = Object.values(airlineData)
                .filter(o => o.val > 0)
                .sort((a, b) => b.val - a.val);

            const total = sorted.reduce((acc, o) => acc + o.val, 0);

            // Empty state — legitimately no data for this metric
            if (sorted.length === 0 || total === 0) {
                this.dau5ParetoNoData = true;
                this.dau5ParetoInsight = { airlinesAt80: 0, cumAt80: 0, total: 0 };
                this.dau5ParetoData = [];
                return;
            }
            this.dau5ParetoNoData = false;

            // Calculate cumulative % (running sum / total * 100)
            let cumAcc = 0;
            const cumData = sorted.map(o => {
                cumAcc += o.val;
                return Math.round((cumAcc / total) * 10000) / 100; // 2 decimals
            });

            // 80% threshold dataset (horizontal reference line)
            const threshold80Data = sorted.map(() => 80);

            // Pareto insight: how many airlines reach 80%
            let airlinesAt80 = sorted.length;
            let cumAt80 = cumData[cumData.length - 1] || 0;
            for (let i = 0; i < cumData.length; i++) {
                if (cumData[i] >= 80) {
                    airlinesAt80 = i + 1;
                    cumAt80 = cumData[i];
                    break;
                }
            }
            this.dau5ParetoInsight = { airlinesAt80, cumAt80, total: sorted.length };
            this.dau5ParetoData = sorted.map((o, i) => ({
                rank: i + 1,
                name: o.name,
                val: o.val,
                share: total > 0 ? Math.round((o.val / total) * 10000) / 100 : 0,
                cumulative: cumData[i]
            }));

            const mLabel = m === 'passenger' ? 'Passengers'
                         : m === 'baggage'   ? 'Baggage (Kg)'
                         : m === 'cargo'     ? 'Cargo (Kg)'
                         : m === 'pos'       ? 'POS (Kg)'
                         :                    'Aircraft Movements';

            const labels = sorted.map(o => o.name);
            const seriesData = sorted.map(o => o.val);

            this.chartInstances.dau5Pareto = new Chart(ctxPareto, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: mLabel,
                            data: seriesData,
                            backgroundColor: 'rgba(2, 132, 199, 0.85)',
                            borderColor: '#0284c7',
                            borderWidth: 1,
                            yAxisID: 'y',
                            order: 3
                        },
                        {
                            label: 'Cumulative %',
                            data: cumData,
                            type: 'line',
                            borderColor: '#f59e0b',
                            backgroundColor: 'transparent',
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointBackgroundColor: '#f59e0b',
                            tension: 0.2,
                            yAxisID: 'y1',
                            order: 1
                        },
                        {
                            label: '80% Threshold',
                            data: threshold80Data,
                            type: 'line',
                            borderColor: '#ef4444',
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            borderDash: [6, 4],
                            pointRadius: 0,
                            tension: 0,
                            yAxisID: 'y1',
                            order: 2
                        }
                    ]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    if (ctx.datasetIndex === 0) {
                                        const share = total > 0
                                            ? (ctx.parsed.y / total * 100).toFixed(2)
                                            : '0.00';
                                        return [
                                            `${mLabel}: ${ctx.parsed.y.toLocaleString()}`,
                                            `Share: ${share}%`,
                                            `Cumulative: ${cumData[ctx.dataIndex]}%`
                                        ];
                                    }
                                    if (ctx.datasetIndex === 2) return '80% Threshold';
                                    return `Cumulative: ${ctx.parsed.y}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { ticks: { maxRotation: 45, font: { size: 10 } } },
                        y: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: { display: true, text: mLabel, font: { size: 10 } }
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            min: 0,
                            max: 100,
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Cumulative %', font: { size: 10 } },
                            ticks: { callback: v => v + '%' }
                        }
                    }
                }
            });
        },

        renderDau5aCharts() {
            if (!window.Chart) return;
            const ctxCrew = document.getElementById('dau5aCrewChart')?.getContext('2d');
            if (!ctxCrew) return;
            if (this.chartInstances.dau5aCrew) {
                this.chartInstances.dau5aCrew.stop();
                this.chartInstances.dau5aCrew.destroy();
                this.chartInstances.dau5aCrew = null;
            }

            const alMap5a = {};
            this.filteredRecords.forEach(r => {
                const al = r.airline || r.operator_name;
                if (!al) return;
                if (!alMap5a[al]) alMap5a[al] = { name: al, opCrew: 0, exCrew: 0, totalCrew: 0 };
                const op = Number(r.crew || 0);
                const ex = Number(r.extra_crew || (Number(r.arr_extra_crew || 0) + Number(r.dep_extra_crew || 0)));
                alMap5a[al].opCrew    += op;
                alMap5a[al].exCrew    += ex;
                alMap5a[al].totalCrew += (op + ex);
            });

            const list5a = Object.values(alMap5a)
                .filter(o => o.totalCrew > 0)
                .sort((a, b) => b.totalCrew - a.totalCrew)
                .slice(0, 12);

            // Empty state
            if (list5a.length === 0) {
                this.dau5aCrewNoData = true;
                return;
            }
            this.dau5aCrewNoData = false;

            this.chartInstances.dau5aCrew = new Chart(ctxCrew, {
                type: 'bar',
                data: {
                    labels: list5a.map(o => o.name),
                    datasets: [
                        {
                            label: 'Operating Crew',
                            data: list5a.map(o => o.opCrew),
                            backgroundColor: 'rgba(37, 99, 235, 0.85)',
                            borderColor: '#2563eb',
                            borderWidth: 1
                        },
                        {
                            label: 'Extra Crew',
                            data: list5a.map(o => o.exCrew),
                            backgroundColor: 'rgba(147, 51, 234, 0.85)',
                            borderColor: '#9333ea',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const o = list5a[ctx.dataIndex];
                                    if (ctx.datasetIndex === 0) return [`Operating Crew: ${o.opCrew}`, `Total Crew: ${o.totalCrew}`];
                                    return [`Extra Crew: ${o.exCrew}`, `Total Crew: ${o.totalCrew}`];
                                }
                            }
                        }
                    },
                    scales: {
                        x: { ticks: { maxRotation: 45, font: { size: 10 } } },
                        y: { beginAtZero: true, title: { display: true, text: 'Crew Count', font: { size: 10 } } }
                    }
                }
            });
        },

        renderDau5bCharts() {
            if (!window.Chart) return;
            const ctxTerm = document.getElementById('dau5bTerminalChart')?.getContext('2d');
            if (!ctxTerm) return;
            if (this.chartInstances.dau5bTerm) {
                this.chartInstances.dau5bTerm.stop();
                this.chartInstances.dau5bTerm.destroy();
                this.chartInstances.dau5bTerm = null;
            }

            const topTerminals = [...new Set(this.filteredRecords.map(r => r.terminal))].filter(Boolean);
            const topAirlines5b = [...new Set(this.filteredRecords.map(r => r.airline || r.operator_name))].filter(Boolean);

            // Sort airlines by total metric value descending, pick top 8
            const airlineTotals = {};
            this.filteredRecords.forEach(r => {
                const al = r.airline || r.operator_name;
                if (!al) return;
                const v = this.selectedMetric === 'passenger'
                    ? Number(r.passenger_total || 0)
                    : Number(r.aircraft_total || (Number(r.aircraft_arrival||0) + Number(r.aircraft_departure||0)));
                airlineTotals[al] = (airlineTotals[al] || 0) + v;
            });
            const sortedAirlines5b = topAirlines5b
                .sort((a, b) => (airlineTotals[b] || 0) - (airlineTotals[a] || 0))
                .slice(0, 8);

            // Empty state
            if (topTerminals.length === 0 || sortedAirlines5b.length === 0) {
                this.dau5bTermNoData = true;
                return;
            }
            this.dau5bTermNoData = false;

            const colors5b = ['#0284c7','#ef4444','#10b981','#f59e0b','#8b5cf6','#06b6d4','#f97316','#84cc16'];

            const datasets5b = sortedAirlines5b.map((al, idx) => {
                const data = topTerminals.map(term => {
                    return this.filteredRecords
                        .filter(r => String(r.terminal) === String(term) && (r.airline || r.operator_name) === al)
                        .reduce((acc, r) => acc + (
                            this.selectedMetric === 'passenger'
                                ? Number(r.passenger_total || 0)
                                : Number(r.aircraft_total || (Number(r.aircraft_arrival||0) + Number(r.aircraft_departure||0)))
                        ), 0);
                });
                return { label: al, data, backgroundColor: colors5b[idx % colors5b.length] };
            });

            const mLabelB = this.selectedMetric === 'passenger' ? 'Passengers' : 'Aircraft Movements';

            this.chartInstances.dau5bTerm = new Chart(ctxTerm, {
                type: 'bar',
                data: {
                    labels: topTerminals.map(t => 'Terminal ' + t),
                    datasets: datasets5b
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { font: { size: 10 } } },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        x: { stacked: true, ticks: { font: { size: 10 } } },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            title: { display: true, text: mLabelB, font: { size: 10 } }
                        }
                    }
                }
            });
        },

        renderDau5cCharts() {
            if (!window.Chart) return;
            const ctx5c = document.getElementById('dau5cBarChart')?.getContext('2d');
            if (!ctx5c) return;
            if (this.chartInstances.dau5c) {
                this.chartInstances.dau5c.stop();
                this.chartInstances.dau5c.destroy();
                this.chartInstances.dau5c = null;
            }

            const m   = this.selectedMetric;   // aircraft | passenger | baggage | cargo | pos
            const dir = this.filterDirection;  // ALL | ARRIVAL | DEPARTURE

            // ── Metric label & unit for axis / tooltip ──
            const mLabel = m === 'passenger' ? 'Passengers'
                         : m === 'baggage'   ? 'Bagasi (Kg)'
                         : m === 'cargo'     ? 'Kargo (Kg)'
                         : m === 'pos'       ? 'POS / Surat (Kg)'
                         :                    'Aircraft Movements';
            this.dau5cChartLabel = mLabel;

            // ── Bar color per metric ──
            const barColor   = m === 'passenger' ? 'rgba(16, 185, 129, 0.85)'
                             : m === 'baggage'   ? 'rgba(245, 158, 11, 0.85)'
                             : m === 'cargo'     ? 'rgba(139, 92, 246, 0.85)'
                             : m === 'pos'       ? 'rgba(236, 72, 153, 0.85)'
                             :                    'rgba(2, 132, 199, 0.85)';
            const borderColor = m === 'passenger' ? '#10b981'
                              : m === 'baggage'   ? '#f59e0b'
                              : m === 'cargo'     ? '#8b5cf6'
                              : m === 'pos'       ? '#ec4899'
                              :                    '#0284c7';

            // ── Aggregate per-airline using the active metric + direction ──
            const airlineMap5c = {};
            this.filteredRecords.forEach(r => {
                const al = r.airline || r.operator_name;
                if (!al) return;
                if (!airlineMap5c[al]) airlineMap5c[al] = { name: al, val: 0 };

                let v = 0;
                if (m === 'passenger') {
                    v = dir === 'ARRIVAL'   ? Number(r.passenger_arrival || 0)
                      : dir === 'DEPARTURE' ? Number(r.passenger_departure || 0)
                      :                       Number(r.passenger_total || 0);
                } else if (m === 'baggage') {
                    v = Number(r.baggage || 0);
                } else if (m === 'cargo') {
                    v = Number(r.cargo || 0);
                } else if (m === 'pos') {
                    v = Number(r.pos || 0);
                } else {
                    // aircraft — respect direction
                    const acArr = Number(r.aircraft_arrival || 0);
                    const acDep = Number(r.aircraft_departure || 0);
                    const acTot = Number(r.aircraft_total || (acArr + acDep));
                    v = dir === 'ARRIVAL'   ? acArr
                      : dir === 'DEPARTURE' ? acDep
                      :                       acTot;
                }
                airlineMap5c[al].val += v;
            });

            // Sort descending by the selected metric value, take top 10
            const top5c = Object.values(airlineMap5c)
                .filter(o => o.val > 0)
                .sort((a, b) => b.val - a.val)
                .slice(0, 10);

            // Empty state
            if (top5c.length === 0) {
                this.dau5cNoData = true;
                return;
            }
            this.dau5cNoData = false;

            const total5c = top5c.reduce((acc, o) => acc + o.val, 0);

            this.chartInstances.dau5c = new Chart(ctx5c, {
                type: 'bar',
                data: {
                    labels: top5c.map(o => o.name),
                    datasets: [
                        {
                            label: mLabel,
                            data: top5c.map(o => o.val),
                            backgroundColor: barColor,
                            borderColor: borderColor,
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const o = top5c[ctx.dataIndex];
                                    const share = total5c > 0
                                        ? (o.val / total5c * 100).toFixed(2)
                                        : '0.00';
                                    return [
                                        `${mLabel}: ${o.val.toLocaleString()}`,
                                        `Share: ${share}%`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: { ticks: { maxRotation: 45, font: { size: 10 } } },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: mLabel, font: { size: 10 } }
                        }
                    }
                }
            });
        },

        renderDau6Charts() {
            if (!window.Chart) return;
            const ctxFleet = document.getElementById('dau6FleetChart')?.getContext('2d');
            if (ctxFleet) {
                if (this.chartInstances.dau6Fleet) this.chartInstances.dau6Fleet.destroy();
                const m = this.selectedMetric;
                const getVal = (r) => {
                    if (m === 'passenger') return Number(r.passenger_total || 0);
                    if (m === 'baggage') return Number(r.baggage || 0);
                    if (m === 'cargo') return Number(r.cargo || 0);
                    if (m === 'pos') return Number(r.pos || 0);
                    return Number(r.aircraft_total || 0);
                };
                const fleetMap = {};
                this.filteredRecords.forEach(r => {
                    const t = r.aircraft_type || 'Unknown';
                    fleetMap[t] = (fleetMap[t] || 0) + getVal(r);
                });
                const sortedFleet = Object.entries(fleetMap).sort((a, b) => b[1] - a[1]).slice(0, 15);
                const types = sortedFleet.map(e => e[0]);
                const counts = sortedFleet.map(e => e[1]);
                const metricLabel = m === 'passenger' ? 'Passengers Total' :
                                   (m === 'baggage' ? 'Baggage (Kg)' :
                                   (m === 'cargo' ? 'Cargo (Kg)' :
                                   (m === 'pos' ? 'POS (Kg)' : 'Aircraft Movements')));
                this.chartInstances.dau6Fleet = new Chart(ctxFleet, {
                    type: 'bar',
                    data: {
                        labels: types,
                        datasets: [{ label: metricLabel, data: counts, backgroundColor: '#0284c7' }]
                    },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
                });
            }

            let nb = 0, wb = 0, reg = 0;
            let wtcM = 0, wtcH = 0, wtcL = 0;
            this.filteredRecords.forEach(r => {
                const val = Number(r.aircraft_total || 1);
                const cat = String(r.category || '').toUpperCase();
                const type = String(r.aircraft_type || '').toUpperCase();
                if (cat.includes('WIDE') || type.includes('A330') || type.includes('B777') || type.includes('B787') || type.includes('A350')) wb += val;
                else if (cat.includes('REGIONAL') || type.includes('ATR') || type.includes('DHC') || type.includes('C208')) reg += val;
                else nb += val;

                const wtc = String(r.wtc || '').toUpperCase();
                if (wtc.includes('H') || wtc.includes('HEAVY')) wtcH += val;
                else if (wtc.includes('L') || wtc.includes('LIGHT')) wtcL += val;
                else wtcM += val;
            });

            const ctxCat = document.getElementById('dau6CategoryDonut')?.getContext('2d');
            if (ctxCat) {
                if (this.chartInstances.dau6Cat) this.chartInstances.dau6Cat.destroy();
                this.chartInstances.dau6Cat = new Chart(ctxCat, {
                    type: 'doughnut',
                    data: {
                        labels: ['Code C (Narrow-Body)', 'Code D/E/F (Wide-Body)', 'Code A/B (Turboprop/Regional)'],
                        datasets: [{ data: [nb || 1, wb || 0, reg || 0], backgroundColor: ['#0284c7', '#4f46e5', '#94a3b8'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }

            const ctxWtc = document.getElementById('dau6WtcDonut')?.getContext('2d');
            if (ctxWtc) {
                if (this.chartInstances.dau6Wtc) this.chartInstances.dau6Wtc.destroy();
                this.chartInstances.dau6Wtc = new Chart(ctxWtc, {
                    type: 'doughnut',
                    data: {
                        labels: ['Medium (M)', 'Heavy (H)', 'Light (L)'],
                        datasets: [{ data: [wtcM || 1, wtcH || 0, wtcL || 0], backgroundColor: ['#f59e0b', '#7c3aed', '#64748b'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        },

        renderDau10Charts() {
            if (!window.Chart) return;
            const canvas = document.getElementById('dau10DualPeakChart');
            if (!canvas) return;
            if (this.chartInstances.dau10DualPeak) {
                this.chartInstances.dau10DualPeak.destroy();
                this.chartInstances.dau10DualPeak = null;
            }
            const hours = this.activeHourlyDistribution.map(h => (h.hour ? (h.hour.split(' - ')[0] || h.hour) : ''));
            const acData = this.activeHourlyDistribution.map(h => Number(h.aircraft_total || 0));
            const pxData = this.activeHourlyDistribution.map(h => Number(h.passenger_total || 0));

            const ctx = canvas.getContext('2d');
            if (!ctx) return;
            this.chartInstances.dau10DualPeak = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: hours,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Aircraft Movements (A/C)',
                            data: acData,
                            backgroundColor: 'rgba(245, 158, 11, 0.75)',
                            borderColor: '#f59e0b',
                            borderWidth: 1,
                            yAxisID: 'yAcft',
                            order: 2
                        },
                        {
                            type: 'line',
                            label: 'Passenger Volume (PAX)',
                            data: pxData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointBackgroundColor: '#10b981',
                            tension: 0.3,
                            yAxisID: 'yPax',
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { ticks: { font: { size: 10 } } },
                        yAcft: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: { display: true, text: 'Movements (A/C)', font: { size: 10 } }
                        },
                        yPax: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Passengers (PAX)', font: { size: 10 } }
                        }
                    }
                }
            });
        },

        renderDau10bCharts() {
            if (this._dau10bCachedChartData && this._dau10bCachedSnapshot) {
                this.renderDau10bChartAtomic(this._dau10bCachedChartData, this._dau10bCachedSnapshot);
            } else {
                const snapshot = this.createDau10bSnapshot();
                const nextFiltered = this.filterDau10bRecords(this.allRecords, snapshot);
                const nextAgg = this.aggregateDau10b(nextFiltered, snapshot, this.hours || []);
                this._dau10bCachedChartData = nextAgg.chartData;
                this._dau10bCachedSnapshot = snapshot;
                this.renderDau10bChartAtomic(nextAgg.chartData, snapshot);
            }
        },

        renderDau11Charts() {
            if (!window.Chart) return;
            let domArrDir = 0, domDepDir = 0, intArrDir = 0, intDepDir = 0;
            let domTransit = 0, domTransfer = 0;
            const isPax = this.selectedMetric === 'passenger';
            this.filteredRecords.forEach(r => {
                if (isPax) {
                    domArrDir += Number(r.passenger_dom_arrival || 0);
                    domDepDir += Number(r.passenger_dom_departure || 0);
                    intArrDir += Number(r.passenger_int_arrival || 0);
                    intDepDir += Number(r.passenger_int_departure || 0);
                } else {
                    domArrDir += Number(r.aircraft_dom_arrival || 0);
                    domDepDir += Number(r.aircraft_dom_departure || 0);
                    intArrDir += Number(r.aircraft_int_arrival || 0);
                    intDepDir += Number(r.aircraft_int_departure || 0);
                }
                domTransit += Number(r.passenger_transit || 0);
                domTransfer += Number(r.passenger_transfer || 0);
            });
            if (domArrDir === 0 && domDepDir === 0 && intArrDir === 0 && intDepDir === 0) {
                this.filteredRecords.forEach(r => {
                    if (isPax) {
                        domArrDir += Number(r.passenger_arrival || 0);
                        domDepDir += Number(r.passenger_departure || 0);
                    } else {
                        domArrDir += Number(r.aircraft_arrival || 0);
                        domDepDir += Number(r.aircraft_departure || 0);
                    }
                });
            }

            const ctxFl = document.getElementById('dau11FlowChart')?.getContext('2d');
            if (ctxFl) {
                if (this.chartInstances.dau11Flow) this.chartInstances.dau11Flow.destroy();
                this.chartInstances.dau11Flow = new Chart(ctxFl, {
                    type: 'bar',
                    data: {
                        labels: ['DOMESTIC ARR', 'DOMESTIC DEP', 'INT ARR', 'INT DEP'],
                        datasets: [
                            { label: isPax ? 'Direct Passengers' : 'Direct Aircraft', data: [domArrDir, domDepDir, intArrDir, intDepDir], backgroundColor: '#10b981' },
                            { label: 'Transit', data: [domTransit, domTransfer, 0, 0], backgroundColor: '#f59e0b' }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } } }
                });
            }
            const ctxD11 = document.getElementById('dau11Donut')?.getContext('2d');
            if (ctxD11) {
                if (this.chartInstances.dau11Donut) this.chartInstances.dau11Donut.destroy();
                const domTot = domArrDir + domDepDir + (isPax ? (domTransit + domTransfer) : 0);
                const intTot = intArrDir + intDepDir;
                this.chartInstances.dau11Donut = new Chart(ctxD11, {
                    type: 'doughnut',
                    data: {
                        labels: ['Domestic', 'International'],
                        datasets: [{ data: [domTot || 1, intTot || 0], backgroundColor: ['#2563eb', '#4f46e5'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        },

        renderDau12Charts() {
            if (this._dau12CachedChartData && this._dau12CachedSnapshot) {
                this.renderDau12ChartAtomic(this._dau12CachedChartData, this._dau12CachedSnapshot);
            } else {
                const snapshot = this.createDau12Snapshot();
                const nextFiltered = this.filterDau12Records(this.allRecords, snapshot);
                const nextAgg = this.aggregateDau12(nextFiltered, snapshot);
                this._dau12CachedChartData = nextAgg.chartData;
                this._dau12CachedSnapshot = snapshot;
                this.renderDau12ChartAtomic(nextAgg.chartData, snapshot);
            }
        },

        initCharts() {
            this.updateCharts();
        },

        _chartsUpdatePending: false,
        _chartsRerunRequested: false,
        updateCharts() {
            if (!window.Chart) {
                // Chart.js may not be loaded yet — retry after a short delay
                setTimeout(() => this.updateCharts(), 200);
                return;
            }
            if (this._chartsUpdatePending) {
                this._chartsRerunRequested = true;
                return;
            }
            this._chartsUpdatePending = true;
            const run = () => {
                this._chartsUpdatePending = false;
                this._doUpdateCharts();
                if (this._chartsRerunRequested) {
                    this._chartsRerunRequested = false;
                    this.updateCharts();
                }
            };
            if (typeof requestAnimationFrame !== 'undefined') {
                requestAnimationFrame(run);
            } else {
                run();
            }
        },

        _doUpdateCharts() {
            if (this.reportType === 'DAU1') this.renderDau1Charts();
            else if (this.reportType === 'DAU2') this.renderDau2Charts();
            else if (this.reportType === 'DAU3') this.renderDau3Charts();
            else if (this.reportType === 'DAU5') this.renderDau5Charts();
            else if (this.reportType === 'DAU5A') this.renderDau5aCharts();
            else if (this.reportType === 'DAU5B') this.renderDau5bCharts();
            else if (this.reportType === 'DAU5C') this.renderDau5cCharts();
            else if (this.reportType === 'DAU6') this.renderDau6Charts();
            else if (this.reportType === 'DAU10') this.renderDau10Charts();
            else if (this.reportType === 'DAU10B') this.renderDau10bCharts();
            else if (this.reportType === 'DAU11') this.renderDau11Charts();
            else if (this.reportType === 'DAU12') this.renderDau12Charts();
        }
    };
}
</script>
@endpush
