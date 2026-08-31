@extends('layouts.app')

@section('title', 'SlotWaves — Master Reference & Airport Configuration')
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen transition-colors duration-150')

@push('head')
<script>
function masterDataState() {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
        activeTab: '{{ $tab ?? 'airports' }}',
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
        }
    };
}
</script>
@endpush

@section('content')
<div x-data="masterDataState()" class="min-h-screen flex flex-col justify-between">

    {{-- ══ COMPACT TOPBAR ═════════════════════════════════════════════════════ --}}
    <header class="sticky top-0 z-30 w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-navy-900/90 backdrop-blur-md px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="w-8 h-8 rounded-lg bg-aviation-600 flex items-center justify-center shadow-xs text-white hover:scale-105 transition">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-sm font-black tracking-tight text-slate-900 dark:text-white">Master Reference Database</h1>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">Verified</span>
                </div>
                <p class="text-[11px] text-slate-500 font-mono">
                    PT. Angkasa Pura Indonesia &bull; Ditjen Hubud Kemenhub
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('home') }}"
               class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-navy-800/80 hover:bg-slate-100 dark:hover:bg-navy-800 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Home</span>
            </a>

            <a href="{{ route('master-data.index') }}"
               class="text-xs font-semibold text-aviation-700 dark:text-aviation-300 px-3 py-1.5 rounded-lg border border-aviation-300 dark:border-aviation-700 bg-aviation-50 dark:bg-aviation-950/80 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-aviation-600 dark:text-aviation-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7zm0 4h16M9 4v16"/>
                </svg>
                <span>Master Data</span>
            </a>
            <button @click="toggleTheme()" type="button"
                    class="p-2 rounded-lg text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-navy-800 border border-slate-200 dark:border-slate-700/60 hover:bg-slate-200 dark:hover:bg-navy-700 transition cursor-pointer"
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

    {{-- ══ MAIN CONTAINER ═════════════════════════════════════════════════════ --}}
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6 flex-1">

        {{-- ── 1. MANAGEMENT STATISTICS RIBBON ───────────────────────────────── --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-9 gap-2">
            <div class="stat-card-modern p-2.5 text-center">
                <div class="text-[9.5px] text-slate-500 uppercase font-semibold">Total Airports</div>
                <div class="text-lg font-black text-slate-900 dark:text-white font-mono mt-0.5">{{ $stats['total_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-2.5 text-center border-t-2 border-t-aviation-600">
                <div class="text-[9.5px] text-aviation-600 dark:text-aviation-400 uppercase font-semibold">InJourney (AP)</div>
                <div class="text-lg font-black text-aviation-600 dark:text-aviation-400 font-mono mt-0.5">{{ $stats['ap_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-2.5 text-center border-t-2 border-t-emerald-600">
                <div class="text-[9.5px] text-emerald-600 dark:text-emerald-400 uppercase font-semibold">UPT Hubud</div>
                <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">{{ $stats['upt_hubud_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-2.5 text-center border-t-2 border-t-amber-600">
                <div class="text-[9.5px] text-amber-600 dark:text-amber-400 uppercase font-semibold">UPTD Pemda</div>
                <div class="text-lg font-black text-amber-600 dark:text-amber-400 font-mono mt-0.5">{{ $stats['upt_pemda_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-2.5 text-center border-t-2 border-t-red-600">
                <div class="text-[9.5px] text-red-600 dark:text-red-400 uppercase font-semibold">TNI</div>
                <div class="text-lg font-black text-red-600 dark:text-red-400 font-mono mt-0.5">{{ $stats['tni_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-2.5 text-center border-t-2 border-t-purple-600">
                <div class="text-[9.5px] text-purple-600 dark:text-purple-400 uppercase font-semibold">Missionaris</div>
                <div class="text-lg font-black text-purple-600 dark:text-purple-400 font-mono mt-0.5">{{ $stats['missionaris_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-2.5 text-center border-t-2 border-t-indigo-600">
                <div class="text-[9.5px] text-indigo-600 dark:text-indigo-400 uppercase font-semibold">BUMN</div>
                <div class="text-lg font-black text-indigo-400 font-mono mt-0.5">{{ $stats['bumn_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-2.5 text-center border-t-2 border-t-teal-600">
                <div class="text-[9.5px] text-teal-600 dark:text-teal-400 uppercase font-semibold">Swasta</div>
                <div class="text-lg font-black text-teal-400 font-mono mt-0.5">{{ $stats['swasta_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-2.5 text-center border-t-2 border-t-lime-600">
                <div class="text-[9.5px] text-lime-600 dark:text-lime-400 uppercase font-semibold">Masyarakat</div>
                <div class="text-lg font-black text-lime-500 font-mono mt-0.5">{{ $stats['masyarakat_airports'] }}</div>
            </div>
        </div>

        {{-- ── 2. CONFIGURATION MANAGEMENT TABS ──────────────────────────────── --}}
        <div class="flex flex-wrap border-b border-slate-200 dark:border-slate-800 gap-1 text-xs">
            <a href="{{ route('master-data.index', ['tab' => 'airports']) }}"
               class="px-4 py-2.5 font-bold font-mono transition border-b-2 flex items-center gap-1.5 {{ $tab === 'airports' ? 'border-aviation-600 text-aviation-600 dark:text-aviation-400 bg-aviation-50/50 dark:bg-aviation-950/40 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <span>✈ Airports Registry</span>
                <span class="px-1.5 py-0.2 rounded-full text-[9.5px] {{ $tab === 'airports' ? 'bg-aviation-600 text-white' : 'bg-slate-200 dark:bg-navy-800 text-slate-600 dark:text-slate-400' }}">{{ $stats['total_airports'] }}</span>
            </a>
            <a href="{{ route('master-data.index', ['tab' => 'airlines']) }}"
               class="px-4 py-2.5 font-bold font-mono transition border-b-2 flex items-center gap-1.5 {{ $tab === 'airlines' ? 'border-aviation-600 text-aviation-600 dark:text-aviation-400 bg-aviation-50/50 dark:bg-aviation-950/40 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <span>🏢 Airlines &amp; Operators</span>
                <span class="px-1.5 py-0.2 rounded-full text-[9.5px] {{ $tab === 'airlines' ? 'bg-aviation-600 text-white' : 'bg-slate-200 dark:bg-navy-800 text-slate-600 dark:text-slate-400' }}">{{ $stats['total_airlines'] }}</span>
            </a>
            <a href="{{ route('master-data.index', ['tab' => 'aircraft']) }}"
               class="px-4 py-2.5 font-bold font-mono transition border-b-2 flex items-center gap-1.5 {{ $tab === 'aircraft' ? 'border-aviation-600 text-aviation-600 dark:text-aviation-400 bg-aviation-50/50 dark:bg-aviation-950/40 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <span>🛩 Aircraft Types &amp; WTC</span>
            </a>
            <a href="{{ route('master-data.index', ['tab' => 'capacity']) }}"
               class="px-4 py-2.5 font-bold font-mono transition border-b-2 flex items-center gap-1.5 {{ $tab === 'capacity' ? 'border-aviation-600 text-aviation-600 dark:text-aviation-400 bg-aviation-50/50 dark:bg-aviation-950/40 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <span>⚡ Apron Capacity Rules</span>
            </a>
            <a href="{{ route('master-data.index', ['tab' => 'opshours']) }}"
               class="px-4 py-2.5 font-bold font-mono transition border-b-2 flex items-center gap-1.5 {{ $tab === 'opshours' ? 'border-aviation-600 text-aviation-600 dark:text-aviation-400 bg-aviation-50/50 dark:bg-aviation-950/40 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <span>⏱ Operational Hours Matrix</span>
            </a>
            <a href="{{ route('master-data.index', ['tab' => 'timezone']) }}"
               class="px-4 py-2.5 font-bold font-mono transition border-b-2 flex items-center gap-1.5 {{ $tab === 'timezone' ? 'border-aviation-600 text-aviation-600 dark:text-aviation-400 bg-aviation-50/50 dark:bg-aviation-950/40 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <span>🌐 Timezone &amp; UTC Shift</span>
            </a>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TAB 1: AIRPORTS MASTER DIRECTORY                                     --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($tab === 'airports')
        <div class="space-y-4" x-data="{ management: '{{ request('management', 'all') }}' }">
            
            {{-- Filter Form --}}
            <form method="GET" action="{{ route('master-data.index') }}" class="glass-card rounded-xl p-4 flex flex-wrap items-center gap-3 shadow-xs">
                <input type="hidden" name="tab" value="airports">
                
                {{-- Operator / Management Filter --}}
                <div class="w-52">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">Pengelola</label>
                    <select name="management" x-model="management" class="w-full filter-select font-mono text-xs">
                        <option value="all">Semua Pengelola</option>
                        <option value="PT. Angkasa Pura Indonesia">PT. Angkasa Pura Indonesia (37)</option>
                        <option value="UPT Ditjen Hubud">UPT Ditjen Hubud (197)</option>
                        <option value="UPT Daerah / Pemda">UPT Daerah / Pemda (107)</option>
                        <option value="TNI">TNI (6)</option>
                        <option value="Missionaris">Missionaris (188)</option>
                        <option value="BUMN">BUMN (9)</option>
                        <option value="Swasta">Swasta (52)</option>
                        <option value="Masyarakat">Masyarakat (1)</option>
                        <option value="Other">Other / Reference Hubs (5)</option>
                    </select>
                </div>

                {{-- Dynamic Region Filter --}}
                <div class="w-32" x-show="management === 'PT. Angkasa Pura Indonesia' || management === 'ANGKASA_PURA_INDONESIA' || management === 'INJOURNEY'" x-transition x-cloak>
                    <label class="block text-[10px] text-aviation-600 dark:text-aviation-400 uppercase font-mono font-semibold mb-1">Region AP</label>
                    <select name="region" class="w-full filter-select font-mono text-xs border-aviation-400 dark:border-aviation-700">
                        <option value="all">Semua Region</option>
                        @foreach(['Region 1', 'Region 2', 'Region 3', 'Region 4', 'Region 5', 'Region 6'] as $reg)
                            <option value="{{ $reg }}" {{ in_array(request('region'), [$reg, str_replace(['1','2','3','4','5','6'], ['I','II','III','IV','V','VI'], $reg)]) ? 'selected' : '' }}>{{ $reg }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status / Traffic Filter --}}
                <div class="w-32">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">Status / Tipe</label>
                    <select name="type" class="w-full filter-select font-mono text-xs">
                        <option value="all">All (Dom/Int'l)</option>
                        <option value="domestic" {{ request('type') === 'domestic' ? 'selected' : '' }}>Domestik</option>
                        <option value="international" {{ request('type') === 'international' ? 'selected' : '' }}>Internasional</option>
                    </select>
                </div>

                {{-- Dedicated IATA Search --}}
                <div class="w-20">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">IATA</label>
                    <input type="text" name="iata" value="{{ request('iata') }}" placeholder="BDO" maxlength="3"
                           class="w-full uppercase filter-select font-mono text-xs">
                </div>

                {{-- Dedicated ICAO Search --}}
                <div class="w-24">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">ICAO</label>
                    <input type="text" name="icao" value="{{ request('icao') }}" placeholder="WICC" maxlength="4"
                           class="w-full uppercase filter-select font-mono text-xs">
                </div>

                {{-- General Search --}}
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">Keyword</label>
                    <input type="text" name="ap_search" value="{{ request('ap_search') }}" placeholder="Nama Bandara, Kota, Provinsi..."
                           class="w-full filter-select text-xs">
                </div>

                <div class="flex items-end gap-2 pt-4">
                    <button type="submit" class="btn-aviation-primary px-3.5 py-1.5 text-xs font-bold font-mono rounded-lg transition shadow-xs cursor-pointer">
                        Filter
                    </button>
                    <a href="{{ route('master-data.index', ['tab' => 'airports']) }}" class="px-3 py-1.5 bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 text-slate-600 dark:text-slate-300 text-xs font-mono rounded-lg transition">
                        Reset
                    </a>
                </div>
            </form>

            {{-- Table Summary Info --}}
            <div class="flex items-center justify-between text-xs font-mono text-slate-500 px-1">
                <div>
                    Showing <span class="font-bold text-slate-900 dark:text-slate-100">{{ $airports->firstItem() ?? 0 }}–{{ $airports->lastItem() ?? 0 }}</span> of <span class="font-bold text-slate-900 dark:text-slate-100">{{ $airports->total() }}</span> Airports (Total: <span class="font-bold text-aviation-600 dark:text-aviation-400">{{ $stats['total_airports'] }}</span>)
                </div>
                <div>Page {{ $airports->currentPage() }} of {{ $airports->lastPage() }}</div>
            </div>

            {{-- Table --}}
            <div class="glass-card rounded-xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-sans text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-navy-950 border-b border-slate-200 dark:border-slate-800 text-[10.5px] font-mono text-slate-500 font-bold uppercase">
                                <th class="py-2.5 px-3 text-center w-12">No</th>
                                <th class="py-2.5 px-3 w-16">IATA</th>
                                <th class="py-2.5 px-3 w-16">ICAO</th>
                                <th class="py-2.5 px-4">Nama Bandar Udara</th>
                                <th class="py-2.5 px-3">Kota / Wilayah</th>
                                <th class="py-2.5 px-3">Provinsi</th>
                                <th class="py-2.5 px-3">Region</th>
                                <th class="py-2.5 px-3">Pengelola</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-center">Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono">
                            @forelse ($airports as $idx => $ap)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-navy-800/50 transition">
                                <td class="py-2 px-3 text-center text-slate-400">{{ ($airports->currentPage() - 1) * $airports->perPage() + $idx + 1 }}</td>
                                <td class="py-2 px-3">
                                    @if ($ap->iata_code)
                                        <span class="font-black text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 px-1.5 py-0.2 rounded text-[11px]">
                                            {{ $ap->iata_code }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 font-mono">—</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 font-mono text-slate-600 dark:text-slate-400 text-[11px]">
                                    {{ $ap->icao_code ?: '—' }}
                                </td>
                                <td class="py-2 px-4 font-sans font-bold text-slate-900 dark:text-slate-100">
                                    {{ $ap->name }}
                                </td>
                                <td class="py-2 px-3 font-sans text-slate-600 dark:text-slate-300">{{ $ap->city ?: ($ap->area ?: '—') }}</td>
                                <td class="py-2 px-3 font-sans text-slate-600 dark:text-slate-300">{{ $ap->province ?: '—' }}</td>
                                <td class="py-2 px-3">
                                    @if ($ap->region)
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                                            {{ $ap->region }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 font-sans">
                                    @if ($ap->isAngkasaPura())
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                                            PT. Angkasa Pura Indonesia
                                        </span>
                                    @elseif ($ap->isUpbuHubud())
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            UPT Ditjen Hubud
                                        </span>
                                    @elseif ($ap->isUptdPemda())
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            UPT Daerah / Pemda
                                        </span>
                                    @elseif ($ap->isTni())
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300 border border-red-200 dark:border-red-800">
                                            TNI
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                            {{ $ap->management_name ?: $ap->management_type }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-center">
                                    @if ($ap->is_international)
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800">
                                            INTL
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-400">
                                            DOM
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-center text-[9.5px]">
                                    <span class="px-1 py-0.2 rounded bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                        {{ $ap->data_source ?? 'VERIFIED' }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-400 font-sans">
                                    Tidak ada data bandar udara yang cocok dengan kriteria filter.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($airports->hasPages())
                <div class="px-4 py-2.5 bg-slate-50 dark:bg-navy-950 border-t border-slate-200 dark:border-slate-800">
                    {{ $airports->links() }}
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TAB 2: AIRLINES MASTER DIRECTORY                                    --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($tab === 'airlines')
        <div class="space-y-4">
            {{-- Filter Form --}}
            <form method="GET" action="{{ route('master-data.index') }}" class="glass-card rounded-xl p-4 flex flex-wrap items-center gap-3 shadow-xs">
                <input type="hidden" name="tab" value="airlines">
                
                <div class="flex-1 min-w-[200px]">
                    <input type="text" name="al_search" value="{{ request('al_search') }}" placeholder="Cari Kode IATA, AOC, Nama Maskapai, Negara..."
                           class="w-full filter-select text-xs">
                </div>

                <div class="w-40">
                    <select name="category" class="w-full filter-select font-mono text-xs">
                        <option value="all">Semua Kategori</option>
                        <option value="domestic" {{ request('category') === 'domestic' ? 'selected' : '' }}>Domestik (AOC 121/135)</option>
                        <option value="international" {{ request('category') === 'international' ? 'selected' : '' }}>Internasional</option>
                        <option value="cargo" {{ request('category') === 'cargo' ? 'selected' : '' }}>Kargo Niaga</option>
                        <option value="charter" {{ request('category') === 'charter' ? 'selected' : '' }}>Charter / Perintis</option>
                    </select>
                </div>

                <div class="w-28">
                    <select name="al_status" class="w-full filter-select font-mono text-xs">
                        <option value="all">Semua Status</option>
                        <option value="active" {{ request('al_status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('al_status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn-aviation-primary px-3.5 py-1.5 text-xs font-bold font-mono rounded-lg transition shadow-xs cursor-pointer">
                    Filter
                </button>
                <a href="{{ route('master-data.index', ['tab' => 'airlines']) }}" class="px-3 py-1.5 bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 text-slate-600 dark:text-slate-300 text-xs font-mono rounded-lg transition">
                    Reset
                </a>
            </form>

            <div class="glass-card rounded-xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-sans text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-navy-950 border-b border-slate-200 dark:border-slate-800 text-[10.5px] font-mono text-slate-500 font-bold uppercase">
                                <th class="py-2.5 px-3 text-center w-12">No</th>
                                <th class="py-2.5 px-4 w-28">IATA Flight Prefix</th>
                                <th class="py-2.5 px-4 w-36">Hubud AOC / Org</th>
                                <th class="py-2.5 px-4">Nama Maskapai / Operator</th>
                                <th class="py-2.5 px-3">Kategori</th>
                                <th class="py-2.5 px-3">Negara Asal</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-center">Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono">
                            @forelse ($airlines as $idx => $al)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-navy-800/50 transition">
                                <td class="py-2 px-3 text-center text-slate-400">{{ $idx + 1 }}</td>
                                <td class="py-2 px-4">
                                    <span class="font-black text-aviation-700 dark:text-aviation-300 bg-aviation-50 dark:bg-aviation-950 border border-aviation-200 dark:border-aviation-800 px-2 py-0.2 rounded text-xs">
                                        {{ $al->airline_code }}
                                    </span>
                                </td>
                                <td class="py-2 px-4 font-mono text-slate-600 dark:text-slate-400 text-[11px]">
                                    {{ $al->organization_code ?: '—' }}
                                </td>
                                <td class="py-2 px-4 font-sans font-bold text-slate-900 dark:text-slate-100">
                                    {{ $al->airline_name }}
                                </td>
                                <td class="py-2 px-3 font-sans">
                                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold uppercase bg-slate-100 text-slate-700 dark:bg-navy-800 dark:text-slate-300">
                                        {{ $al->category }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 font-sans text-slate-600 dark:text-slate-300">{{ $al->country ?: 'Indonesia' }}</td>
                                <td class="py-2 px-3 text-center">
                                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold {{ $al->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500' }}">
                                        {{ ucfirst($al->status) }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center text-[9.5px]">
                                    <span class="px-1 py-0.2 rounded bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-400">
                                        {{ $al->source ?? 'HUBUD' }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-400 font-sans">
                                    Tidak ada data maskapai yang cocok dengan kriteria filter.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TAB 3: AIRCRAFT TYPES & WTC (ICAO STANDARD REFERENCE)               --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($tab === 'aircraft')
        <div class="space-y-4">
            <div class="glass-card p-5 rounded-xl space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">ICAO AIRCRAFT TYPE DESIGNATORS &amp; WTC MATRIX</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Reference registry of aircraft models, passenger capacity classes, and wake turbulence categories.</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                        ICAO DOC 8643
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black font-mono text-aviation-600 dark:text-aviation-400">B738 / B739 / A320</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">WTC: MEDIUM</span>
                        </div>
                        <div class="font-bold text-xs text-slate-800 dark:text-white">Narrow-Body Commercial Jet</div>
                        <div class="text-[11px] text-slate-500">150–215 Pax &bull; Apron Stand Class C</div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black font-mono text-aviation-600 dark:text-aviation-400">ATR72 / DH8D</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">WTC: MEDIUM</span>
                        </div>
                        <div class="font-bold text-xs text-slate-800 dark:text-white">Regional Turboprop</div>
                        <div class="text-[11px] text-slate-500">70–78 Pax &bull; Apron Stand Class C/B</div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black font-mono text-aviation-600 dark:text-aviation-400">B77W / A333 / B789</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300">WTC: HEAVY</span>
                        </div>
                        <div class="font-bold text-xs text-slate-800 dark:text-white">Wide-Body Long-Haul Jet</div>
                        <div class="text-[11px] text-slate-500">280–400 Pax &bull; Apron Stand Class E</div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black font-mono text-aviation-600 dark:text-aviation-400">B738F / A332F</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-slate-200 text-slate-800 dark:bg-navy-800 dark:text-slate-300">CARGO STAND</span>
                        </div>
                        <div class="font-bold text-xs text-slate-800 dark:text-white">Dedicated Freighter</div>
                        <div class="text-[11px] text-slate-500">Cargo Only &bull; Excluded from Pax limit</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TAB 4: APRON CAPACITY RULES                                         --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($tab === 'capacity')
        <div class="space-y-4">
            <div class="glass-card p-5 rounded-xl space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">APRON CAPACITY &amp; STAND ALLOCATION ENGINE</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Parameters governing hourly passenger aircraft limits and simultaneous apron occupancy.</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        Operational Rule
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">1. Aircraft Capacity (NAC)</div>
                        <div class="text-2xl font-black font-mono text-aviation-600 dark:text-aviation-400">6 &ndash; 20 A/C</div>
                        <p class="text-[11px] text-slate-500">
                            Maksimum jumlah pesawat komersial berpenumpang yang dapat menempati apron secara bersamaan dalam satu jendela jam operasional.
                        </p>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">2. Cargo Separation Rule</div>
                        <div class="text-2xl font-black font-mono text-amber-600 dark:text-amber-400">Excluded</div>
                        <p class="text-[11px] text-slate-500">
                            Penerbangan kargo niaga menggunakan parking stand khusus kargo dan tidak dihitung ke dalam kalkulasi batas kapasitas pesawat penumpang.
                        </p>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">3. Status Classification</div>
                        <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400">4 Levels</div>
                        <p class="text-[11px] text-slate-500">
                            Available (&lt; Limit), Full / Max (= Limit), Over Capacity (&gt; Limit), dan Off Hours (di luar jendela jam operasional).
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TAB 5: OPERATIONAL HOURS MATRIX                                     --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($tab === 'opshours')
        <div class="space-y-4">
            <div class="glass-card p-5 rounded-xl space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">OPERATIONAL HOURS REGISTRY</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Airport Station active operational windows for slot coordination.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1 font-mono">
                        <div class="text-xs font-bold text-aviation-600 dark:text-aviation-400">BDO &bull; Husein Sastranegara</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white">06:00 &rarr; 20:00</div>
                        <div class="text-[11px] text-slate-500 font-sans">14 Active Operating Hours &bull; UTC+7 (WIB)</div>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1 font-mono">
                        <div class="text-xs font-bold text-aviation-600 dark:text-aviation-400">DPS &bull; I Gusti Ngurah Rai</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white">00:00 &rarr; 24:00</div>
                        <div class="text-[11px] text-slate-500 font-sans">24 Hours Continuous &bull; UTC+8 (WITA)</div>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1 font-mono">
                        <div class="text-xs font-bold text-aviation-600 dark:text-aviation-400">UPG &bull; Sultan Hasanuddin</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white">00:00 &rarr; 24:00</div>
                        <div class="text-[11px] text-slate-500 font-sans">24 Hours Continuous &bull; UTC+8 (WITA)</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TAB 6: TIMEZONE & UTC SHIFT MATRIX                                  --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($tab === 'timezone')
        <div class="space-y-4">
            <div class="glass-card p-5 rounded-xl space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">INDONESIAN TIMEZONE &amp; UTC SHIFT MATRIX</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Real-time local vs UTC converter specification for flight slots.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1 font-mono">
                        <div class="text-xs font-bold text-aviation-600 dark:text-aviation-400">WIB (Western Indonesia)</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white">UTC + 07:00</div>
                        <div class="text-[11px] text-slate-500 font-sans">Java, Sumatra, West &amp; Central Kalimantan</div>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1 font-mono">
                        <div class="text-xs font-bold text-aviation-600 dark:text-aviation-400">WITA (Central Indonesia)</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white">UTC + 08:00</div>
                        <div class="text-[11px] text-slate-500 font-sans">Bali, NTB, NTT, Sulawesi, South &amp; East Kalimantan</div>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1 font-mono">
                        <div class="text-xs font-bold text-aviation-600 dark:text-aviation-400">WIT (Eastern Indonesia)</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white">UTC + 09:00</div>
                        <div class="text-[11px] text-slate-500 font-sans">Maluku, North Maluku, Papua</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </main>

    <footer class="w-full border-t border-slate-200 dark:border-slate-800 py-3.5 text-center text-xs text-slate-400">
        SlotWaves Master Reference Database &bull; Synchronized with InJourney &amp; Ditjen Hubud
    </footer>

</div>
@endsection
