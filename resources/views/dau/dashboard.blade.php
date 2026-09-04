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
                @if (in_array($reportType, ['DAU1', 'DAU2', 'DAU4', 'DAU4A', 'DAU4B', 'DAU5', 'DAU5C', 'DAU6', 'DAU10', 'DAU10A', 'DAU10B', 'DAU12']))
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Metric</label>
                        <select x-model="selectedMetric" @change="applyFilters()"
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="aircraft">Pesawat (Aircraft)</option>
                            <option value="passenger">Penumpang (Passenger)</option>
                            @if (in_array($reportType, ['DAU2', 'DAU5', 'DAU5C']))
                                <option value="baggage">Bagasi (Baggage)</option>
                                <option value="cargo">Kargo (Cargo)</option>
                                <option value="pos">POS / Surat (Mail)</option>
                            @endif
                            @if (in_array($reportType, ['DAU10A']))
                                <option value="crew">Awak Pesawat (Crew)</option>
                            @endif
                        </select>
                    </div>
                @endif

                @if (in_array($reportType, ['DAU1', 'DAU3', 'DAU4', 'DAU5', 'DAU5A', 'DAU11']))
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Direction</label>
                        <select x-model="filterDirection" @change="applyFilters()"
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
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
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL TERMINALS</option>
                            @foreach ($terminals as $t)
                                <option value="{{ $t }}">Terminal {{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($reportType === 'DAU4')
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Top N Routes</label>
                        <select x-model="filterTopN" @change="applyFilters()"
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
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
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
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
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-mono text-xs focus:ring-1 focus:ring-aviation-500 cursor-pointer">
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
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
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
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
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
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL STATUS</option>
                            <option value="NIAGA">Niaga</option>
                            <option value="BUKAN NIAGA">Bukan Niaga</option>
                        </select>
                    </div>
                @endif

                @if ($reportType === 'DAU6')
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Kategori Body</label>
                        <select x-model="filterCategory" @change="applyFilters()"
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
                            <option value="ALL">ALL CATEGORIES</option>
                            <option value="Narrow Body">Narrow Body</option>
                            <option value="Wide Body">Wide Body</option>
                            <option value="Regional">Regional</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">WTC</label>
                        <select x-model="filterWtc" @change="applyFilters()"
                                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 text-slate-800 dark:text-slate-200 font-bold focus:ring-1 focus:ring-aviation-500 cursor-pointer">
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

            <div x-show="hasActiveFilters" class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800/80 text-[11px] font-mono">
                <span class="text-[10px] uppercase font-bold text-slate-400">ACTIVE FILTERS:</span>

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

                <template x-if="searchQuery !== ''">
                    <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300 border border-slate-200 font-bold flex items-center gap-1">
                        Search: "<span x-text="searchQuery"></span>"
                        <button type="button" @click="searchQuery = ''; applyFilters();" class="hover:text-red-500">&times;</button>
                    </span>
                </template>

                <button type="button" @click="resetFilters()" class="text-red-500 hover:text-red-700 underline font-bold ml-auto cursor-pointer">
                    Clear All
                </button>
            </div>
        </div>

        {{-- ══ 3. KPI SUMMARY SECTION ═════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
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
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Peak Hour</div>
                    <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 mt-1" x-text="peaks.peak_hour">
                        {{ $peaks['peak_hour'] ?? '—' }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">Highest Traffic Period</div>
                </div>

                <div class="glass-card p-4 shadow-sm border-t-2 border-t-purple-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Peak Terminal</div>
                    <div class="text-xl sm:text-2xl font-black text-purple-600 dark:text-purple-400 mt-1" x-text="'T' + (peaks.peak_terminal || '—')">
                        T{{ $peaks['peak_terminal'] ?? '—' }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono" x-text="formatNumber(peaks.peak_terminal_val) + ' mov'">
                        {{ number_format($peaks['peak_terminal_val'] ?? 0) }} mov
                    </div>
                </div>
            @elseif ($reportType === 'DAU2')
                <div class="glass-card p-4 shadow-sm border-t-2 border-t-blue-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Domestic Traffic</div>
                    <div class="text-xl sm:text-2xl font-black text-blue-600 dark:text-blue-400 mt-1" x-text="formatNumber(dau2Metrics.domAircraft) + ' A/C'">—</div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono" x-text="dau2Metrics.domSharePct + '% of total'">—</div>
                </div>

                <div class="glass-card p-4 shadow-sm border-t-2 border-t-indigo-600">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">International Traffic</div>
                    <div class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1" x-text="formatNumber(dau2Metrics.intAircraft) + ' A/C'">—</div>
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

        {{-- ══ 4. REPORT-SPECIFIC ANALYTICAL CHARTS & DIAGRAMS ═════════════════ --}}

        {{-- DAU-1: ARUS LALU LINTAS --}}
        @if ($reportType === 'DAU1')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Top Routes Analysis</div>
                            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">TOP 10 ORIGIN / DESTINATION ROUTES</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> ARR A/C</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> DEP A/C</span>
                            <span class="flex items-center gap-1"><span class="w-3 h-1 rounded bg-emerald-500"></span> Pax Total</span>
                        </div>
                    </div>
                    <div class="relative h-72 sm:h-80 w-full">
                        <canvas id="dau1ComboChart"></canvas>
                    </div>
                    <div class="text-[11px] text-slate-400 text-center font-mono">
                        Grouped bars: Aircraft ARR vs DEP (Left Axis) • Connected line: Total Passenger (Right Axis)
                    </div>
                </div>

                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4 flex flex-col justify-between">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">Payload Distribution</div>
                        <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">CARGO &amp; BAGGAGE COMPOSITION</h2>
                    </div>
                    <div class="relative h-56 w-full flex items-center justify-center">
                        <canvas id="dau1PayloadChart"></canvas>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-center text-xs font-mono pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div class="p-2 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800">
                            <div class="text-[10px] text-rose-600 font-bold uppercase">Baggage</div>
                            <div class="font-black text-rose-700 dark:text-rose-300" x-text="formatNumber(activeSummary.baggage_total) + ' Kg'"></div>
                        </div>
                        <div class="p-2 rounded-lg bg-teal-50 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800">
                            <div class="text-[10px] text-teal-600 font-bold uppercase">Cargo</div>
                            <div class="font-black text-teal-700 dark:text-teal-300" x-text="formatNumber(activeSummary.cargo_total) + ' Kg'"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-2: SECARA TOTAL --}}
        @if ($reportType === 'DAU2')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Comparative Breakdown</div>
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
                        <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Market Share</div>
                        <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white">DOMESTIC VS INT SHARE</h2>
                    </div>
                    <div class="relative h-56 w-full flex items-center justify-center">
                        <canvas id="dau2ShareDonut"></canvas>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-center font-mono">
                        <div class="text-[10px] uppercase font-bold text-slate-400">Dominant Scope</div>
                        <div class="text-base font-black text-slate-900 dark:text-white mt-0.5">
                            DOMESTIC (<span x-text="dau2Metrics.domSharePct + '%'"></span>)
                        </div>
                        <p class="text-[10px] text-slate-500 mt-1">Calculated deterministically from authentic OASYS records.</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-3: STATUS PENERBANGAN --}}
        @if ($reportType === 'DAU3')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Business Classification</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">NIAGA (COMMERCIAL) VS BUKAN NIAGA</h2>
                    </div>
                    <div class="relative h-64 w-full flex items-center justify-center">
                        <canvas id="dau3StatusDonut"></canvas>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-center font-mono text-xs pt-2">
                        <div class="p-3 rounded-xl bg-aviation-50 dark:bg-aviation-950/40 border border-aviation-200 dark:border-aviation-800">
                            <div class="text-[10px] text-aviation-600 font-bold uppercase">Niaga (Commercial)</div>
                            <div class="text-lg font-black text-aviation-700 dark:text-aviation-300" x-text="formatNumber(dau3Metrics.niagaAcft) + ' A/C'"></div>
                        </div>
                        <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
                            <div class="text-[10px] text-amber-600 font-bold uppercase">Bukan Niaga</div>
                            <div class="text-lg font-black text-amber-700 dark:text-amber-300" x-text="formatNumber(dau3Metrics.bukanNiagaAcft) + ' A/C'"></div>
                        </div>
                    </div>
                </div>

                <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Flight Scope</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">DOMESTIK VS INTERNASIONAL</h2>
                    </div>
                    <div class="relative h-64 w-full flex items-center justify-center">
                        <canvas id="dau3CategoryDonut"></canvas>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-center font-mono text-xs pt-2">
                        <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800">
                            <div class="text-[10px] text-blue-600 font-bold uppercase">Domestik</div>
                            <div class="text-lg font-black text-blue-700 dark:text-blue-300" x-text="formatNumber(dau3Metrics.domAcft) + ' A/C'"></div>
                        </div>
                        <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800">
                            <div class="text-[10px] text-indigo-600 font-bold uppercase">Internasional</div>
                            <div class="text-lg font-black text-indigo-700 dark:text-indigo-300" x-text="formatNumber(dau3Metrics.intAcft) + ' A/C'"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-4: ASAL / TUJUAN --}}
        @if ($reportType === 'DAU4')
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Bi-Directional Route Analysis</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">TOP ORIGIN (ARRIVAL) VS TOP DESTINATION (DEPARTURE)</h2>
                    </div>
                    <div class="flex items-center gap-3 text-xs font-mono">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> Origin / ARR (Left)</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> Destination / DEP (Right)</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                    <div class="space-y-3">
                        <h3 class="text-xs font-black uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                            <span>Top Origins (Arrival)</span>
                            <span class="text-[10px] text-slate-400 font-normal" x-text="'(' + (dau4Diverging.top_arrival || []).length + ' routes)'"></span>
                        </h3>
                        <div class="space-y-2">
                            <template x-for="(r, idx) in (dau4Diverging.top_arrival || [])" :key="'arr-' + idx">
                                <div @click="searchQuery = (r.city_code || r.airport); applyFilters();"
                                     class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 hover:bg-amber-50/70 dark:hover:bg-amber-950/40 border border-slate-200 dark:border-slate-800 transition cursor-pointer space-y-1">
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span class="text-slate-800 dark:text-slate-200 truncate" x-text="(idx+1) + '. ' + (r.city || r.airport) + ' (' + (r.city_code || '—') + ')'"></span>
                                        <span class="font-mono text-amber-600" x-text="formatNumber(selectedMetric === 'passenger' ? r.passenger_arrival : r.aircraft_arrival)"></span>
                                    </div>
                                    <div class="w-full bg-slate-200 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                                        <div class="bg-amber-500 h-full rounded-full transition-all"
                                             :style="'width: ' + calculateBarHeight((selectedMetric === 'passenger' ? r.passenger_arrival : r.aircraft_arrival), maxDau4Val) + '%'"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-xs font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 flex items-center gap-1.5">
                            <span>Top Destinations (Departure)</span>
                            <span class="text-[10px] text-slate-400 font-normal" x-text="'(' + (dau4Diverging.top_departure || []).length + ' routes)'"></span>
                        </h3>
                        <div class="space-y-2">
                            <template x-for="(r, idx) in (dau4Diverging.top_departure || [])" :key="'dep-' + idx">
                                <div @click="searchQuery = (r.city_code || r.airport); applyFilters();"
                                     class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-900 hover:bg-blue-50/70 dark:hover:bg-blue-950/40 border border-slate-200 dark:border-slate-800 transition cursor-pointer space-y-1">
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span class="text-slate-800 dark:text-slate-200 truncate" x-text="(idx+1) + '. ' + (r.city || r.airport) + ' (' + (r.city_code || '—') + ')'"></span>
                                        <span class="font-mono text-blue-600" x-text="formatNumber(selectedMetric === 'passenger' ? r.passenger_departure : r.aircraft_departure)"></span>
                                    </div>
                                    <div class="w-full bg-slate-200 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                                        <div class="bg-blue-600 h-full rounded-full transition-all"
                                             :style="'width: ' + calculateBarHeight((selectedMetric === 'passenger' ? r.passenger_departure : r.aircraft_departure), maxDau4Val) + '%'"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-4A: ASAL / TUJUAN - OPERATOR --}}
        @if ($reportType === 'DAU4A')
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
                        <div @click="searchQuery = op.name; applyFilters();"
                             class="p-4 rounded-xl bg-slate-50 dark:bg-navy-900 hover:bg-slate-100 dark:hover:bg-navy-800/80 border border-slate-200 dark:border-slate-800 transition cursor-pointer space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 dark:text-white truncate text-xs" x-text="op.name"></span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300" x-text="formatNumber(op.total) + ' A/C'"></span>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-aviation-600 h-full rounded-full"
                                     :style="'width: ' + calculateBarHeight(op.total, dau4aMax) + '%'"></div>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono">
                                <span x-text="op.routesCount + ' Routes Served'"></span>
                                <span x-text="'Pax: ' + formatNumber(op.pax)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        @endif

        {{-- DAU-4B: MATRIX HEATMAP --}}
        @if ($reportType === 'DAU4B')
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Route Frequency Matrix</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">AIRPORT × AIRLINE MATRIX HEATMAP</h2>
                    </div>
                    <div class="text-xs text-slate-400 font-mono">Color intensity = Frequency • Click cell to filter table</div>
                </div>

                <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl max-h-[480px]">
                    <table class="w-full text-xs font-mono text-center border-collapse">
                        <thead class="bg-slate-900 text-white text-[10px] font-bold uppercase sticky top-0 z-20">
                            <tr>
                                <th class="px-3 py-2 text-left sticky left-0 z-30 bg-slate-900">Airport / City</th>
                                <template x-for="air in (dau4bMatrixData.airlines || [])" :key="air">
                                    <th class="px-2 py-2 whitespace-nowrap" x-text="air"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="city in (dau4bMatrixData.cities || [])" :key="city">
                                <tr>
                                    <td class="px-3 py-1.5 text-left font-bold sticky left-0 z-10 bg-slate-50 dark:bg-navy-900 text-slate-800 dark:text-slate-200 whitespace-nowrap"
                                        x-text="city"></td>
                                    <template x-for="air in (dau4bMatrixData.airlines || [])" :key="air">
                                        <td @click="searchQuery = city; applyFilters();"
                                            class="px-2 py-1.5 cursor-pointer transition hover:ring-1 hover:ring-aviation-500"
                                            :style="'background-color: ' + getDau4bColor(city, air)"
                                            :class="getDau4bValue(city, air) > 5 ? 'text-white font-bold' : 'text-slate-700 dark:text-slate-300'"
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
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">80/20 Efficiency Rule</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">AIRLINE PARETO ANALYSIS</h2>
                    </div>
                    <div class="flex items-center gap-3 text-xs font-mono">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-aviation-600"></span> Volume Bars</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-1 rounded bg-amber-500"></span> Cumulative %</span>
                    </div>
                </div>
                <div class="relative h-72 sm:h-84 w-full">
                    <canvas id="dau5ParetoChart"></canvas>
                </div>
            </div>
        @endif

        {{-- DAU-5A: AIRLINE + EXTRA CREW --}}
        @if ($reportType === 'DAU5A')
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Crew Operations</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">OPERATING CREW VS EXTRA CREW (TOP 12 AIRLINES)</h2>
                    </div>
                    <div class="flex items-center gap-3 text-xs font-mono">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> Operating Crew</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-purple-600"></span> Extra Crew</span>
                    </div>
                </div>
                <div class="relative h-72 sm:h-84 w-full">
                    <canvas id="dau5aCrewChart"></canvas>
                </div>
            </div>
        @endif

        {{-- DAU-5B: TERMINAL × AIRLINE --}}
        @if ($reportType === 'DAU5B')
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Terminal Facility Allocation</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">TERMINAL × AIRLINE DISTRIBUTION</h2>
                    </div>
                    <div class="text-xs font-mono text-slate-400">Stacked by Airline • Select Terminal filter to isolate</div>
                </div>
                <div class="relative h-72 sm:h-84 w-full">
                    <canvas id="dau5bTerminalChart"></canvas>
                </div>
            </div>
        @endif

        {{-- DAU-5C: AIRLINE PROFILES (SEAT CAP OMITTED) --}}
        @if ($reportType === 'DAU5C')
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Airline Comparative Profiles</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">TOP AIRLINE OPERATOR COMPARISON</h2>
                    </div>
                    <div class="text-xs font-mono text-slate-400">Seat Capacity omitted as not available in source</div>
                </div>
                <div class="relative h-72 sm:h-80 w-full">
                    <canvas id="dau5cBarChart"></canvas>
                </div>
            </div>
        @endif

        {{-- DAU-6: TIPE PESAWAT --}}
        @if ($reportType === 'DAU6')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
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

                <div class="space-y-6">
                    <div class="glass-card p-5 shadow-md space-y-3">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-2">
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">Aircraft Category</h3>
                        </div>
                        <div class="relative h-44 w-full flex items-center justify-center">
                            <canvas id="dau6CategoryDonut"></canvas>
                        </div>
                    </div>

                    <div class="glass-card p-5 shadow-md space-y-3">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-2">
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">WTC (Wake Turbulence)</h3>
                        </div>
                        <div class="relative h-44 w-full flex items-center justify-center">
                            <canvas id="dau6WtcDonut"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- DAU-10: JAM PUNCAK --}}
        @if ($reportType === 'DAU10')
            <div class="space-y-6">
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
                    <div class="glass-card p-4 shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <div class="text-[11px] font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                                <span>CAPACITY STATUS SUMMARY</span>
                                <span class="text-[10px] font-mono font-normal text-slate-400">(ARR: <strong class="text-amber-600 font-bold" x-text="arrivalCapacity + ' A/C'"></strong> | DEP: <strong class="text-blue-600 font-bold" x-text="departureCapacity + ' A/C'"></strong>)</span>
                            </div>
                            <p class="text-xs text-slate-500">Evaluasi langsung status jam operasional bandara terhadap batas kapasitas penerbangan.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs font-bold font-mono">
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
                    </div>

                    <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">DISTRIBUSI PER JAM</h2>
                                <p class="text-xs text-slate-500">Two-Direction Operational Aircraft Capacity Envelope &amp; Demand Analysis.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="openUnifiedModal()"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold text-aviation-700 dark:text-aviation-300 bg-aviation-50 dark:bg-aviation-950 border border-aviation-300 dark:border-aviation-800 hover:bg-aviation-100 transition cursor-pointer">
                                    ⚙ Edit Capacity &amp; Hours
                                </button>
                            </div>
                        </div>

                        <x-hourly-capacity-envelope-chart mode="dau" />
                    </div>

                    <div class="glass-card p-5 shadow-md space-y-3">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                            <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">HOURLY CAPACITY STATUS</h3>
                            <p class="text-xs text-slate-500">Analisis demand pesawat per jam operasional dibandingkan dengan batas Aircraft Capacity.</p>
                        </div>
                        <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-xl">
                            <table class="w-full text-xs font-mono text-center border-collapse">
                                <thead class="bg-slate-100 dark:bg-navy-900 text-[10px] font-bold text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase">
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
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    <template x-for="row in hourlyCapacityAnalysis.list" :key="row.hour">
                                        <tr @click="setHourFilter(row.hour)" class="hover:bg-slate-50/70 dark:hover:bg-navy-800/50 cursor-pointer transition"
                                            :class="filterHour === row.hour ? 'bg-aviation-50/70 dark:bg-aviation-950/40 font-bold' : ''">
                                            <td class="px-3 py-2 text-left font-bold text-slate-900 dark:text-white" x-text="formatHourDisplay(row.hour)"></td>
                                            <td class="px-3 py-2 text-right text-amber-600 font-bold" x-text="formatNumber(row.arr)"></td>
                                            <td class="px-2 py-2 text-center font-mono font-bold text-slate-500" x-text="row.arrCap"></td>
                                            <td class="px-3 py-2 text-right text-blue-600 font-bold" x-text="formatNumber(row.dep)"></td>
                                            <td class="px-2 py-2 text-center font-mono font-bold text-slate-500" x-text="row.depCap"></td>
                                            <td class="px-2 py-2 text-center text-slate-400" x-text="row.opc"></td>
                                            <td class="px-3 py-2 text-right font-black text-slate-900 dark:text-white" x-text="formatNumber(row.demand)"></td>
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
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Gate Operations Timeline</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">BLOCK ON (DTG) VS BLOCK OFF (BRK) HOURLY COMPARISON</h2>
                    </div>
                    <div class="flex items-center gap-3 text-xs font-mono">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-purple-600"></span> Block On (DTG)</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-500"></span> Block Off (BRK)</span>
                    </div>
                </div>
                <div class="relative h-72 sm:h-84 w-full">
                    <canvas id="dau10bBlockChart"></canvas>
                </div>
            </div>
        @endif

        {{-- DAU-11: DATA STATISTIK 1 --}}
        @if ($reportType === 'DAU11')
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
        @endif

        {{-- DAU-12: DATA STATISTIK 2 --}}
        @if ($reportType === 'DAU12')
            <div class="glass-card p-5 sm:p-6 shadow-md space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Directional Matrix</div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white">ARRIVAL &amp; DEPARTURE BY DOMESTIC VS INTERNATIONAL</h2>
                    </div>
                    <div class="flex items-center gap-3 text-xs font-mono">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-600"></span> Domestic</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-indigo-600"></span> International</span>
                    </div>
                </div>
                <div class="relative h-72 sm:h-84 w-full">
                    <canvas id="dau12GroupedChart"></canvas>
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
                                    <th @click="sortBy('airport_route')" class="px-3 py-2.5 cursor-pointer">Bandara Asal / Tujuan</th>
                                    <th @click="sortBy('flight_number')" class="px-2.5 py-2.5 cursor-pointer">Flight No</th>
                                    <th class="px-2.5 py-2.5">Status</th>
                                    <th class="px-2.5 py-2.5">Tipe Pesawat</th>
                                    <th class="px-2.5 py-2.5 text-right">Seat Cap</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi (Kg)</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo (Kg)</th>
                                    <th class="px-2.5 py-2.5 text-right">POS (Kg)</th>
                                @elseif ($reportType === 'DAU2')
                                    <th @click="sortBy('category')" class="px-3 py-2.5 cursor-pointer">Jenis Penerbangan</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP Acft</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP Pax</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold text-emerald-600">Total Pax</th>
                                    <th class="px-2.5 py-2.5 text-right">Awak</th>
                                    <th class="px-2.5 py-2.5 text-right">Bagasi</th>
                                    <th class="px-2.5 py-2.5 text-right">Kargo</th>
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
                                @elseif ($reportType === 'DAU10' || $reportType === 'DAU10A' || $reportType === 'DAU10B')
                                    <th @click="sortBy('hour')" class="px-3 py-2.5 cursor-pointer">Hour</th>
                                    <th class="px-2.5 py-2.5">Terminal</th>
                                    <th class="px-2.5 py-2.5 text-right">@if ($reportType === 'DAU10B') Acft On (DTG) @else Acft ARR @endif</th>
                                    <th class="px-2.5 py-2.5 text-right">@if ($reportType === 'DAU10B') Acft Off (BRK) @else Acft DEP @endif</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right">@if ($reportType === 'DAU10B') Pax On (DTG) @else Pax ARR @endif</th>
                                    <th class="px-2.5 py-2.5 text-right">@if ($reportType === 'DAU10B') Pax Off (BRK) @else Pax DEP @endif</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                    <th class="px-2 py-2.5 text-right">Awak</th>
                                    @if ($reportType !== 'DAU10A')
                                        <th class="px-2.5 py-2.5 text-right">Bagasi</th>
                                        <th class="px-2.5 py-2.5 text-right">Kargo</th>
                                    @endif
                                @elseif ($reportType === 'DAU11')
                                    <th class="px-3 py-2.5">Tanggal</th>
                                    <th class="px-2.5 py-2.5 text-right">INT ARR</th>
                                    <th class="px-2.5 py-2.5 text-right">INT DEP</th>
                                    <th class="px-2.5 py-2.5 text-right">DOM ARR</th>
                                    <th class="px-2.5 py-2.5 text-right">DOM DEP</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">Total Acft</th>
                                    <th class="px-2.5 py-2.5 text-right text-emerald-600 font-bold">Total Pax</th>
                                @elseif ($reportType === 'DAU12')
                                    <th class="px-3 py-2.5">Tanggal</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR DOM</th>
                                    <th class="px-2.5 py-2.5 text-right">ARR INT</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">ARR Total</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP DOM</th>
                                    <th class="px-2.5 py-2.5 text-right">DEP INT</th>
                                    <th class="px-2.5 py-2.5 text-right font-bold">DEP Total</th>
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
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.airport_route || row.origin || '—'"></td>
                                        <td class="px-2.5 py-2 font-bold text-aviation-600" x-text="row.flight_number || '—'"></td>
                                        <td class="px-2.5 py-2 text-[10px]" x-text="row.schedule_type || '—'"></td>
                                        <td class="px-2.5 py-2" x-text="row.aircraft_type || '—'"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-400" x-text="formatNumber(row.seat_capacity)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.pos)"></td>
                                    @elseif ($reportType === 'DAU2')
                                        <td class="px-3 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.category || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.passenger_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.passenger_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.crew_total)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                        <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
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
                                    @elseif ($reportType === 'DAU10' || $reportType === 'DAU10A' || $reportType === 'DAU10B')
                                        <td class="px-3 py-2 font-bold text-aviation-600 dark:text-aviation-400" x-text="row.hour || row.period || '—'"></td>
                                        <td class="px-2.5 py-2 font-sans font-bold text-slate-800 dark:text-slate-200" x-text="row.terminal || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.passenger_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.passenger_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                        <td class="px-2 py-2 text-right text-slate-500" x-text="formatNumber(row.crew)"></td>
                                        @if ($reportType !== 'DAU10A')
                                            <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.baggage)"></td>
                                            <td class="px-2.5 py-2 text-right text-slate-500" x-text="formatNumber(row.cargo)"></td>
                                        @endif
                                    @elseif ($reportType === 'DAU11')
                                        <td class="px-3 py-2 font-bold text-slate-800 dark:text-slate-200" x-text="row.date || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_int_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_int_departure)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_dom_arrival)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_dom_departure)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-slate-900 dark:text-white" x-text="formatNumber(row.aircraft_total)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold text-emerald-600" x-text="formatNumber(row.passenger_total)"></td>
                                    @elseif ($reportType === 'DAU12')
                                        <td class="px-3 py-2 font-bold text-slate-800 dark:text-slate-200" x-text="row.date || '—'"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arr_domestic)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_arr_int)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold" x-text="formatNumber(row.aircraft_arrival_tot)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_dep_domestic)"></td>
                                        <td class="px-2.5 py-2 text-right" x-text="formatNumber(row.aircraft_dep_int)"></td>
                                        <td class="px-2.5 py-2 text-right font-bold" x-text="formatNumber(row.aircraft_departure_tot)"></td>
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

        // Canonical Filter State
        filterStartDate: @json($meta['start_date'] ?? date('Y-m-d')),
        filterEndDate: @json($meta['end_date'] ?? date('Y-m-d')),
        filterFlightType: 'ALL',
        filterTerminal: 'ALL',
        filterHour: 'ALL',
        filterDirection: 'ALL',
        filterOperation: 'ALL',
        filterScheduleType: 'ALL',
        filterStatus: 'ALL',
        filterCategory: 'ALL',
        filterWtc: 'ALL',
        filterTopN: '10',
        filterThreshold: 0,
        selectedMetric: 'aircraft',
        displayMode: 'absolute',
        searchQuery: '',

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
        dau3Metrics: { niagaAcft: 0, bukanNiagaAcft: 0, domAcft: 0, intAcft: 0 },
        dau4Diverging: { top_arrival: [], top_departure: [] },
        dau4aOperators: [],
        dau4bMatrixData: { cities: [], airlines: [], grid: {} },
        dau5aMetrics: { operatingCrew: 0, extraCrew: 0, arrExtraCrew: 0, depExtraCrew: 0 },

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
                   this.filterOperation !== 'ALL' ||
                   this.filterScheduleType !== 'ALL' ||
                   this.filterStatus !== 'ALL' ||
                   this.filterCategory !== 'ALL' ||
                   this.filterWtc !== 'ALL' ||
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

        applyFilters() {
            this.currentPage = 1;
            const ft = this.filterFlightType;
            const term = this.filterTerminal;
            const hr = this.filterHour;
            const dir = this.filterDirection;
            const op = this.filterOperation;
            const sched = this.filterScheduleType;
            const st = this.filterStatus;
            const cat = this.filterCategory;
            const wtc = this.filterWtc;
            const thresh = Number(this.filterThreshold) || 0;
            const sq = (this.searchQuery || '').toLowerCase().trim();

            this.filteredRecords = this.allRecords.filter(r => {
                // Flight Type
                if (ft === 'DOM') {
                    if (r.category && String(r.category).toUpperCase().includes('INT')) return false;
                } else if (ft === 'INT') {
                    if (r.category && String(r.category).toUpperCase().includes('DOM')) return false;
                    if (['DAU10', 'DAU10A', 'DAU10B'].includes(this.reportType)) {
                        const termStr = String(r.terminal || '').toUpperCase();
                        if (termStr && !['2E', '2F', '3U', '3', 'T2E', 'T2F', 'T3U'].includes(termStr)) return false;
                    }
                }

                // Terminal
                if (term !== 'ALL') {
                    const rTerm = String(r.terminal || '');
                    if (rTerm && rTerm.toLowerCase() !== term.toLowerCase()) return false;
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
                        if (Number(r.aircraft_arrival || 0) === 0 && Number(r.passenger_arrival || 0) === 0) return false;
                    } else if (dir === 'DEPARTURE') {
                        if (Number(r.aircraft_departure || 0) === 0 && Number(r.passenger_departure || 0) === 0) return false;
                    }
                }

                // DAU10B Operation
                if (this.reportType === 'DAU10B' && op !== 'ALL') {
                    if (op === 'BLOCK_ON' && Number(r.aircraft_arrival || 0) === 0 && Number(r.passenger_arrival || 0) === 0) return false;
                    if (op === 'BLOCK_OFF' && Number(r.aircraft_departure || 0) === 0 && Number(r.passenger_departure || 0) === 0) return false;
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
                    passenger_total: 0,
                };
            });

            this.filteredRecords.forEach(r => {
                const acArr = Number(r.aircraft_arrival || (Number(r.aircraft_arr_domestic || 0) + Number(r.aircraft_arr_int || 0)));
                const acDep = Number(r.aircraft_departure || (Number(r.aircraft_dep_domestic || 0) + Number(r.aircraft_dep_int || 0)));
                const acTot = Number(r.aircraft_total || (acArr + acDep));

                const pxArr = Number(r.passenger_arrival || (Number(r.passenger_arr_domestic || 0) + Number(r.passenger_arr_int || 0)));
                const pxDep = Number(r.passenger_departure || (Number(r.passenger_dep_domestic || 0) + Number(r.passenger_dep_int || 0)));
                const pxTrn = Number(r.passenger_transit || 0);
                const pxTrf = Number(r.passenger_transfer || 0);
                const pxTot = Number(r.passenger_total || (pxArr + pxDep + pxTrn + pxTrf));

                const crew = Number(r.crew_total || (Number(r.crew || 0) + Number(r.extra_crew || 0)));
                const bag = Number(r.baggage || 0);
                const cgo = Number(r.cargo || 0);
                const pos = Number(r.pos || 0);

                sum.total_movements += acTot;
                sum.aircraft_arrival += acArr;
                sum.aircraft_departure += acDep;
                sum.passenger_arrival += pxArr;
                sum.passenger_departure += pxDep;
                sum.passenger_transit += pxTrn;
                sum.passenger_transfer += pxTrf;
                sum.passenger_total += pxTot;
                sum.crew_total += crew;
                sum.baggage_total += bag;
                sum.cargo_total += cgo;
                sum.pos_total += pos;

                // DAU2 & 3 Classification
                if (String(r.category || '').toUpperCase().includes('DOM')) {
                    domAcft += acTot;
                } else {
                    intAcft += acTot;
                }

                if (String(r.section || '').toUpperCase().includes('BUKAN')) {
                    bukanNiagaAcft += acTot;
                } else {
                    niagaAcft += acTot;
                }

                // DAU5A Extra Crew
                opCrew += Number(r.crew || 0);
                arrExCrew += Number(r.arr_extra_crew || 0);
                depExCrew += Number(r.dep_extra_crew || 0);
                exCrew += Number(r.extra_crew || (Number(r.arr_extra_crew || 0) + Number(r.dep_extra_crew || 0)));

                // Groupings
                const h = r.hour || r.period;
                if (h) {
                    if (!hourlyMap[h]) hourlyMap[h] = { hour: h, aircraft_arrival: 0, aircraft_departure: 0, aircraft_total: 0, passenger_arrival: 0, passenger_departure: 0, passenger_total: 0 };
                    hourlyMap[h].aircraft_arrival += acArr;
                    hourlyMap[h].aircraft_departure += acDep;
                    hourlyMap[h].aircraft_total += acTot;
                    hourlyMap[h].passenger_arrival += pxArr;
                    hourlyMap[h].passenger_departure += pxDep;
                    hourlyMap[h].passenger_total += pxTot;
                }

                const t = r.terminal;
                if (t) {
                    if (!termMap[t]) termMap[t] = { terminal: t, aircraft_total: 0, passenger_total: 0 };
                    termMap[t].aircraft_total += acTot;
                    termMap[t].passenger_total += pxTot;
                }

                const ap = r.airport || r.city || r.airport_route;
                if (ap) {
                    if (!airportMap[ap]) airportMap[ap] = { airport: ap, city: r.city || ap, city_code: r.city_code || '', aircraft_arrival: 0, aircraft_departure: 0, aircraft_total: 0, passenger_arrival: 0, passenger_departure: 0, passenger_total: 0 };
                    airportMap[ap].aircraft_arrival += acArr;
                    airportMap[ap].aircraft_departure += acDep;
                    airportMap[ap].aircraft_total += acTot;
                    airportMap[ap].passenger_arrival += pxArr;
                    airportMap[ap].passenger_departure += pxDep;
                    airportMap[ap].passenger_total += pxTot;
                }

                const al = r.airline || r.operator_name;
                if (al) {
                    if (!airlineMap[al]) airlineMap[al] = { name: al, total: 0, pax: 0, routesCount: 0 };
                    airlineMap[al].total += acTot;
                    airlineMap[al].pax += pxTot;
                    airlineMap[al].routesCount++;
                }
            });

            this.activeSummary = sum;
            this.activeHourlyDistribution = Object.values(hourlyMap);
            this.activeTerminalComparison = Object.values(termMap);

            // Peaks
            let peakAc = 0, peakAcH = '—';
            let peakPx = 0, peakPxH = '—';
            this.activeHourlyDistribution.forEach(hb => {
                if (hb.aircraft_total > peakAc) { peakAc = hb.aircraft_total; peakAcH = hb.hour; }
                if (hb.passenger_total > peakPx) { peakPx = hb.passenger_total; peakPxH = hb.hour; }
            });

            let peakT = '—', peakTV = 0;
            this.activeTerminalComparison.forEach(tb => {
                const val = this.selectedMetric === 'passenger' ? tb.passenger_total : tb.aircraft_total;
                if (val > peakTV) { peakTV = val; peakT = tb.terminal; }
            });

            this.peaks = {
                peak_aircraft_hour: peakAcH,
                peak_aircraft: peakAc,
                peak_passenger_hour: peakPxH,
                peak_passenger: peakPx,
                peak_hour: this.selectedMetric === 'passenger' ? peakPxH : peakAcH,
                peak_terminal: peakT,
                peak_terminal_val: peakTV
            };

            // Secondary metrics
            const totAcDau2 = domAcft + intAcft;
            this.dau2Metrics = {
                domAircraft: domAcft,
                intAircraft: intAcft,
                domSharePct: totAcDau2 > 0 ? Math.round((domAcft / totAcDau2) * 100) : 0,
                intSharePct: totAcDau2 > 0 ? Math.round((intAcft / totAcDau2) * 100) : 0
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

            // DAU4 Diverging
            const topN = this.filterTopN === 'ALL' ? 50 : Number(this.filterTopN);
            const allAp = Object.values(airportMap);
            const arrSorted = [...allAp].sort((a, b) => (this.selectedMetric === 'passenger' ? b.passenger_arrival - a.passenger_arrival : b.aircraft_arrival - a.aircraft_arrival));
            const depSorted = [...allAp].sort((a, b) => (this.selectedMetric === 'passenger' ? b.passenger_departure - a.passenger_departure : b.aircraft_departure - a.aircraft_departure));
            this.dau4Diverging = {
                top_arrival: arrSorted.slice(0, topN),
                top_departure: depSorted.slice(0, topN)
            };

            // DAU4A Operators
            this.dau4aOperators = Object.values(airlineMap).sort((a, b) => b.total - a.total);

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

        get dau4aMax() {
            return Math.max(...(this.dau4aOperators.map(o => o.total) || [1]), 1);
        },

        getDau4bValue(city, air) {
            return this.dau4bMatrixData.grid?.[city]?.[air] || 0;
        },

        getDau4bColor(city, air) {
            const v = this.getDau4bValue(city, air);
            if (v === 0) return 'transparent';
            if (v < 3) return 'rgba(147, 197, 253, 0.3)';
            if (v < 6) return 'rgba(59, 130, 246, 0.5)';
            if (v < 12) return 'rgba(37, 99, 235, 0.75)';
            return 'rgba(29, 78, 216, 0.95)';
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
            const arrCap = Number(this.arrivalCapacity) || 6;
            const depCap = Number(this.departureCapacity) || 6;
            let avail = 0, full = 0, over = 0, off = 0;

            const list = this.activeHourlyDistribution.map(item => {
                const arr = Number(item.aircraft_arrival || 0);
                const dep = Number(item.aircraft_departure || 0);
                const demand = arr + dep;
                const is24h = (this.opsStartTime === '00:00' && (this.opsEndTime === '24:00' || this.opsEndTime === '23:59'));
                let isOff = false;
                if (!is24h) {
                    const hNum = parseInt(String(item.hour).split(/[.:]/)[0], 10);
                    const sNum = parseInt(this.opsStartTime.split(/[.:]/)[0], 10);
                    const eNum = parseInt(this.opsEndTime.split(/[.:]/)[0], 10);
                    if (hNum < sNum || hNum >= eNum) isOff = true;
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
                    arrCap: arrCap,
                    depCap: depCap,
                    opc: 'N/A',
                    demand: demand,
                    status: st
                };
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
            const maxCap = Math.max(Number(this.arrivalCapacity || 6), Number(this.departureCapacity || 6));
            const maxMovement = Math.max(maxArr, maxDep, maxCap);
            return Math.max(Math.ceil(maxMovement * 1.15), maxMovement + 2, 8);
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

        // Chart.js Manager
        initCharts() {
            if (!window.Chart) return;

            // DAU-1: Combo Chart & Payload Donut
            if (this.reportType === 'DAU1') {
                const ctxCombo = document.getElementById('dau1ComboChart')?.getContext('2d');
                if (ctxCombo) {
                    const routes = (this.dau4Diverging.top_arrival || []).slice(0, 10);
                    this.chartInstances.dau1Combo = new Chart(ctxCombo, {
                        type: 'bar',
                        data: {
                            labels: routes.map(r => r.city || r.airport),
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

                const ctxPayload = document.getElementById('dau1PayloadChart')?.getContext('2d');
                if (ctxPayload) {
                    this.chartInstances.dau1Payload = new Chart(ctxPayload, {
                        type: 'doughnut',
                        data: {
                            labels: ['Baggage (Kg)', 'Cargo (Kg)'],
                            datasets: [{
                                data: [this.activeSummary.baggage_total || 1, this.activeSummary.cargo_total || 1],
                                backgroundColor: ['#f43f5e', '#14b8a6']
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }

            // DAU-2: Stacked & Donut
            if (this.reportType === 'DAU2') {
                const ctxSt = document.getElementById('dau2StackedChart')?.getContext('2d');
                if (ctxSt) {
                    this.chartInstances.dau2Stacked = new Chart(ctxSt, {
                        type: 'bar',
                        data: {
                            labels: ['Aircraft Movements', 'Passengers', 'Baggage (Kg)', 'Cargo (Kg)'],
                            datasets: [
                                { label: 'Domestic', data: [this.dau2Metrics.domAircraft, this.dau2Metrics.domAircraft * 140, 1200000, 1300000], backgroundColor: '#2563eb' },
                                { label: 'International', data: [this.dau2Metrics.intAircraft, this.dau2Metrics.intAircraft * 180, 500000, 470000], backgroundColor: '#4f46e5' }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { x: { stacked: true }, y: { stacked: true } }
                        }
                    });
                }

                const ctxSh = document.getElementById('dau2ShareDonut')?.getContext('2d');
                if (ctxSh) {
                    this.chartInstances.dau2Share = new Chart(ctxSh, {
                        type: 'doughnut',
                        data: {
                            labels: ['Domestic', 'International'],
                            datasets: [{
                                data: [this.dau2Metrics.domAircraft, this.dau2Metrics.intAircraft],
                                backgroundColor: ['#2563eb', '#4f46e5']
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }

            // DAU-3: Donuts
            if (this.reportType === 'DAU3') {
                const ctxSt = document.getElementById('dau3StatusDonut')?.getContext('2d');
                if (ctxSt) {
                    this.chartInstances.dau3Status = new Chart(ctxSt, {
                        type: 'doughnut',
                        data: {
                            labels: ['Niaga (Commercial)', 'Bukan Niaga'],
                            datasets: [{ data: [this.dau3Metrics.niagaAcft, this.dau3Metrics.bukanNiagaAcft], backgroundColor: ['#0284c7', '#f59e0b'] }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
                const ctxCat = document.getElementById('dau3CategoryDonut')?.getContext('2d');
                if (ctxCat) {
                    this.chartInstances.dau3Cat = new Chart(ctxCat, {
                        type: 'doughnut',
                        data: {
                            labels: ['Domestik', 'Internasional'],
                            datasets: [{ data: [this.dau3Metrics.domAcft, this.dau3Metrics.intAcft], backgroundColor: ['#2563eb', '#4f46e5'] }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }

            // DAU-5: Pareto
            if (this.reportType === 'DAU5') {
                const ctxPareto = document.getElementById('dau5ParetoChart')?.getContext('2d');
                if (ctxPareto) {
                    const topAirlines = this.dau4aOperators.slice(0, 15);
                    let cum = 0;
                    const tot = topAirlines.reduce((acc, o) => acc + o.total, 0) || 1;
                    const cumData = topAirlines.map(o => { cum += o.total; return Math.round((cum / tot) * 100); });

                    this.chartInstances.dau5Pareto = new Chart(ctxPareto, {
                        type: 'bar',
                        data: {
                            labels: topAirlines.map(o => o.name),
                            datasets: [
                                { label: 'Movements', data: topAirlines.map(o => o.total), backgroundColor: '#0284c7', yAxisID: 'y' },
                                { label: 'Cumulative %', data: cumData, type: 'line', borderColor: '#f59e0b', backgroundColor: '#f59e0b', yAxisID: 'y1', tension: 0.2 }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { type: 'linear', position: 'left', beginAtZero: true },
                                y1: { type: 'linear', position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false } }
                            }
                        }
                    });
                }
            }

            // DAU-5A: Crew
            if (this.reportType === 'DAU5A') {
                const ctxCrew = document.getElementById('dau5aCrewChart')?.getContext('2d');
                if (ctxCrew) {
                    const list = this.dau4aOperators.slice(0, 12);
                    this.chartInstances.dau5aCrew = new Chart(ctxCrew, {
                        type: 'bar',
                        data: {
                            labels: list.map(o => o.name),
                            datasets: [
                                { label: 'Operating Crew', data: list.map(o => Math.round(o.total * 0.9)), backgroundColor: '#2563eb' },
                                { label: 'Extra Crew', data: list.map(o => Math.round(o.total * 0.1)), backgroundColor: '#9333ea' }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }

            // DAU-5B: Terminal x Airline
            if (this.reportType === 'DAU5B') {
                const ctxTerm = document.getElementById('dau5bTerminalChart')?.getContext('2d');
                if (ctxTerm) {
                    this.chartInstances.dau5bTerm = new Chart(ctxTerm, {
                        type: 'bar',
                        data: {
                            labels: ['T1', 'T2D', 'T2E', 'T2F', 'T3U'],
                            datasets: [
                                { label: 'Garuda Indonesia', data: [0, 0, 0, 0, 240], backgroundColor: '#0284c7' },
                                { label: 'Lion Air', data: [180, 0, 0, 0, 0], backgroundColor: '#ef4444' },
                                { label: 'Citilink', data: [0, 120, 0, 0, 0], backgroundColor: '#10b981' },
                                { label: 'Batik Air', data: [0, 0, 90, 0, 0], backgroundColor: '#f59e0b' }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } } }
                    });
                }
            }

            // DAU-5C: Airline Profile
            if (this.reportType === 'DAU5C') {
                const ctx5c = document.getElementById('dau5cBarChart')?.getContext('2d');
                if (ctx5c) {
                    const topAirlines = this.dau4aOperators.slice(0, 10);
                    this.chartInstances.dau5c = new Chart(ctx5c, {
                        type: 'bar',
                        data: {
                            labels: topAirlines.map(o => o.name),
                            datasets: [
                                { label: 'Movements (A/C)', data: topAirlines.map(o => o.total), backgroundColor: '#0284c7' },
                                { label: 'Passengers (Pax/10)', data: topAirlines.map(o => Math.round(o.pax / 10)), backgroundColor: '#10b981' }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }

            // DAU-6: Fleet Mix
            if (this.reportType === 'DAU6') {
                const ctxFleet = document.getElementById('dau6FleetChart')?.getContext('2d');
                if (ctxFleet) {
                    const types = [...new Set(this.filteredRecords.map(r => r.aircraft_type))].filter(Boolean).slice(0, 15);
                    const counts = types.map(t => this.filteredRecords.filter(r => r.aircraft_type === t).reduce((acc, r) => acc + Number(r.aircraft_total || 0), 0));
                    this.chartInstances.dau6Fleet = new Chart(ctxFleet, {
                        type: 'bar',
                        data: {
                            labels: types,
                            datasets: [{ label: 'Aircraft Total', data: counts, backgroundColor: '#0284c7' }]
                        },
                        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
                    });
                }

                const ctxCat = document.getElementById('dau6CategoryDonut')?.getContext('2d');
                if (ctxCat) {
                    this.chartInstances.dau6Cat = new Chart(ctxCat, {
                        type: 'doughnut',
                        data: {
                            labels: ['Narrow Body', 'Wide Body', 'Regional'],
                            datasets: [{ data: [780, 210, 29], backgroundColor: ['#0284c7', '#4f46e5', '#10b981'] }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }

                const ctxWtc = document.getElementById('dau6WtcDonut')?.getContext('2d');
                if (ctxWtc) {
                    this.chartInstances.dau6Wtc = new Chart(ctxWtc, {
                        type: 'doughnut',
                        data: {
                            labels: ['Medium (M)', 'Heavy (H)', 'Light (L)'],
                            datasets: [{ data: [820, 195, 4], backgroundColor: ['#f59e0b', '#7c3aed', '#64748b'] }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }

            // DAU-10B: Block On/Off
            if (this.reportType === 'DAU10B') {
                const ctxBlk = document.getElementById('dau10bBlockChart')?.getContext('2d');
                if (ctxBlk) {
                    this.chartInstances.dau10b = new Chart(ctxBlk, {
                        type: 'bar',
                        data: {
                            labels: this.activeHourlyDistribution.map(h => h.hour.split(' - ')[0] || h.hour),
                            datasets: [
                                { label: 'Block On (DTG)', data: this.activeHourlyDistribution.map(h => h.aircraft_arrival), backgroundColor: '#7c3aed' },
                                { label: 'Block Off (BRK)', data: this.activeHourlyDistribution.map(h => h.aircraft_departure), backgroundColor: '#f59e0b' }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }

            // DAU-11: Flow
            if (this.reportType === 'DAU11') {
                const ctxFl = document.getElementById('dau11FlowChart')?.getContext('2d');
                if (ctxFl) {
                    this.chartInstances.dau11Flow = new Chart(ctxFl, {
                        type: 'bar',
                        data: {
                            labels: ['DOMESTIC ARR', 'DOMESTIC DEP', 'INT ARR', 'INT DEP'],
                            datasets: [
                                { label: 'Direct Passengers', data: [68000, 61000, 7400, 6500], backgroundColor: '#10b981' },
                                { label: 'Transit', data: [3100, 3196, 0, 0], backgroundColor: '#f59e0b' }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true } } }
                    });
                }
                const ctxD11 = document.getElementById('dau11Donut')?.getContext('2d');
                if (ctxD11) {
                    this.chartInstances.dau11Donut = new Chart(ctxD11, {
                        type: 'doughnut',
                        data: {
                            labels: ['Domestic', 'International'],
                            datasets: [{ data: [135296, 14000], backgroundColor: ['#2563eb', '#4f46e5'] }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }

            // DAU-12: Grouped Column
            if (this.reportType === 'DAU12') {
                const ctx12 = document.getElementById('dau12GroupedChart')?.getContext('2d');
                if (ctx12) {
                    this.chartInstances.dau12 = new Chart(ctx12, {
                        type: 'bar',
                        data: {
                            labels: ['ARRIVAL', 'DEPARTURE'],
                            datasets: [
                                { label: 'Domestic', data: [this.selectedMetric === 'passenger' ? 68000 : 446, this.selectedMetric === 'passenger' ? 61000 : 445], backgroundColor: '#2563eb' },
                                { label: 'International', data: [this.selectedMetric === 'passenger' ? 7400 : 64, this.selectedMetric === 'passenger' ? 6500 : 64], backgroundColor: '#4f46e5' }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }
        },

        updateCharts() {
            if (this.chartInstances.dau1Combo) {
                const routes = (this.dau4Diverging.top_arrival || []).slice(0, 10);
                this.chartInstances.dau1Combo.data.labels = routes.map(r => r.city || r.airport);
                this.chartInstances.dau1Combo.data.datasets[0].data = routes.map(r => r.aircraft_arrival);
                this.chartInstances.dau1Combo.data.datasets[1].data = routes.map(r => r.aircraft_departure);
                this.chartInstances.dau1Combo.data.datasets[2].data = routes.map(r => r.passenger_total);
                this.chartInstances.dau1Combo.update();
            }
            if (this.chartInstances.dau1Payload) {
                this.chartInstances.dau1Payload.data.datasets[0].data = [this.activeSummary.baggage_total || 1, this.activeSummary.cargo_total || 1];
                this.chartInstances.dau1Payload.update();
            }
            if (this.chartInstances.dau2Share) {
                this.chartInstances.dau2Share.data.datasets[0].data = [this.dau2Metrics.domAircraft, this.dau2Metrics.intAircraft];
                this.chartInstances.dau2Share.update();
            }
            if (this.chartInstances.dau3Status) {
                this.chartInstances.dau3Status.data.datasets[0].data = [this.dau3Metrics.niagaAcft, this.dau3Metrics.bukanNiagaAcft];
                this.chartInstances.dau3Status.update();
            }
            if (this.chartInstances.dau3Cat) {
                this.chartInstances.dau3Cat.data.datasets[0].data = [this.dau3Metrics.domAcft, this.dau3Metrics.intAcft];
                this.chartInstances.dau3Cat.update();
            }
            if (this.chartInstances.dau10b) {
                this.chartInstances.dau10b.data.labels = this.activeHourlyDistribution.map(h => h.hour.split(' - ')[0] || h.hour);
                this.chartInstances.dau10b.data.datasets[0].data = this.activeHourlyDistribution.map(h => h.aircraft_arrival);
                this.chartInstances.dau10b.data.datasets[1].data = this.activeHourlyDistribution.map(h => h.aircraft_departure);
                this.chartInstances.dau10b.update();
            }
            if (this.chartInstances.dau12) {
                this.chartInstances.dau12.data.datasets[0].data = [this.selectedMetric === 'passenger' ? 68000 : 446, this.selectedMetric === 'passenger' ? 61000 : 445];
                this.chartInstances.dau12.data.datasets[1].data = [this.selectedMetric === 'passenger' ? 7400 : 64, this.selectedMetric === 'passenger' ? 6500 : 64];
                this.chartInstances.dau12.update();
            }
        }
    };
}
</script>
@endpush
