@extends('layouts.app')

@section('title', 'SlotWaves — Master Reference Data (Airports & Airlines)')
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen transition-colors duration-150')

@push('head')
<style>
    .glass-card {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
    }
    html.dark .glass-card {
        background: #111927;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
    }
    .filter-input-md {
        background-color: #F8FAFC;
        border: 1px solid #CBD5E1;
        color: #1E293B;
        border-radius: 0.75rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 500;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    html.dark .filter-input-md {
        background-color: #0B111E;
        border-color: #1E293B;
        color: #F1F5F9;
    }
    .filter-input-md:focus {
        outline: none;
        border-color: #2764AA;
        box-shadow: 0 0 0 3px rgba(39, 100, 170, 0.2);
    }
</style>
<script>
function masterDataState() {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
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

    {{-- ══ TOPBAR ═════════════════════════════════════════════════════════════ --}}
    <header class="sticky top-0 z-30 w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-navy-900/90 backdrop-blur-md px-4 sm:px-8 py-3.5 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="w-9 h-9 rounded-xl bg-aviation-600 flex items-center justify-center shadow-md shadow-aviation-600/25 text-white hover:scale-105 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-base font-black tracking-tight text-slate-900 dark:text-white">Master Reference Database</h1>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">Verified</span>
                </div>
                <p class="text-[11px] text-slate-500 font-mono">
                    PT. Angkasa Pura Indonesia &bull; Ditjen Hubud Kemenhub
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50 dark:bg-navy-800/80 hover:bg-slate-100 transition shadow-2xs flex items-center gap-1">
                &larr; Upload Portal
            </a>
            <button @click="toggleTheme()" type="button"
                    class="p-2 rounded-xl text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-navy-800 border border-slate-200 dark:border-slate-700/60 hover:bg-slate-200 dark:hover:bg-navy-700 transition"
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
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-8 py-8 space-y-6 flex-1">

        {{-- ── 1. MANAGEMENT STATISTICS RIBBON ───────────────────────────────── --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-9 gap-2.5">
            <div class="stat-card-modern p-3 text-center">
                <div class="text-[10px] text-slate-500 uppercase font-semibold">Total Airports</div>
                <div class="text-xl font-black text-slate-900 dark:text-white font-mono mt-0.5">{{ $stats['total_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-3 text-center border-t-2 border-t-aviation-600">
                <div class="text-[10px] text-aviation-600 dark:text-aviation-400 uppercase font-semibold">InJourney (AP)</div>
                <div class="text-xl font-black text-aviation-600 dark:text-aviation-400 font-mono mt-0.5">{{ $stats['ap_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-3 text-center border-t-2 border-t-emerald-600">
                <div class="text-[10px] text-emerald-600 dark:text-emerald-400 uppercase font-semibold">UPT Hubud</div>
                <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">{{ $stats['upt_hubud_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-3 text-center border-t-2 border-t-amber-600">
                <div class="text-[10px] text-amber-600 dark:text-amber-400 uppercase font-semibold">UPTD Pemda</div>
                <div class="text-xl font-black text-amber-600 dark:text-amber-400 font-mono mt-0.5">{{ $stats['upt_pemda_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-3 text-center border-t-2 border-t-red-600">
                <div class="text-[10px] text-red-600 dark:text-red-400 uppercase font-semibold">TNI</div>
                <div class="text-xl font-black text-red-600 dark:text-red-400 font-mono mt-0.5">{{ $stats['tni_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-3 text-center border-t-2 border-t-purple-600">
                <div class="text-[10px] text-purple-600 dark:text-purple-400 uppercase font-semibold">Missionaris</div>
                <div class="text-xl font-black text-purple-600 dark:text-purple-400 font-mono mt-0.5">{{ $stats['missionaris_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-3 text-center border-t-2 border-t-indigo-600">
                <div class="text-[10px] text-indigo-600 dark:text-indigo-400 uppercase font-semibold">BUMN</div>
                <div class="text-xl font-black text-indigo-400 font-mono mt-0.5">{{ $stats['bumn_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-3 text-center border-t-2 border-t-teal-600">
                <div class="text-[10px] text-teal-600 dark:text-teal-400 uppercase font-semibold">Swasta</div>
                <div class="text-xl font-black text-teal-400 font-mono mt-0.5">{{ $stats['swasta_airports'] }}</div>
            </div>
            <div class="stat-card-modern p-3 text-center border-t-2 border-t-lime-600">
                <div class="text-[10px] text-lime-600 dark:text-lime-400 uppercase font-semibold">Masyarakat</div>
                <div class="text-xl font-black text-lime-500 font-mono mt-0.5">{{ $stats['masyarakat_airports'] }}</div>
            </div>
        </div>

        {{-- ── 2. TAB SWITCHER ───────────────────────────────────────────────── --}}
        <div class="flex border-b border-slate-200 dark:border-slate-800 gap-2 text-xs">
            <a href="{{ route('master-data.index', ['tab' => 'airports']) }}"
               class="px-5 py-3 font-bold font-mono transition border-b-2 flex items-center gap-2 {{ $tab === 'airports' ? 'border-aviation-600 text-aviation-600 dark:text-aviation-400 bg-aviation-50/50 dark:bg-aviation-950/40 rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <span>✈ Airports Registry</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $tab === 'airports' ? 'bg-aviation-600 text-white' : 'bg-slate-200 dark:bg-navy-800 text-slate-600 dark:text-slate-400' }}">{{ $stats['total_airports'] }}</span>
            </a>
            <a href="{{ route('master-data.index', ['tab' => 'airlines']) }}"
               class="px-5 py-3 font-bold font-mono transition border-b-2 flex items-center gap-2 {{ $tab === 'airlines' ? 'border-aviation-600 text-aviation-600 dark:text-aviation-400 bg-aviation-50/50 dark:bg-aviation-950/40 rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <span>🏢 Airlines &amp; Operators</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $tab === 'airlines' ? 'bg-aviation-600 text-white' : 'bg-slate-200 dark:bg-navy-800 text-slate-600 dark:text-slate-400' }}">{{ $stats['total_airlines'] }}</span>
            </a>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TAB 1: AIRPORTS MASTER DIRECTORY                                     --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($tab === 'airports')
        <div class="space-y-4" x-data="{ management: '{{ request('management', 'all') }}' }">
            
            {{-- Filter Form --}}
            <form method="GET" action="{{ route('master-data.index') }}" class="glass-card rounded-2xl p-5 flex flex-wrap items-center gap-3.5 shadow-sm">
                <input type="hidden" name="tab" value="airports">
                
                {{-- Operator / Management Filter --}}
                <div class="w-56">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">Pengelola</label>
                    <select name="management" x-model="management" class="w-full filter-input-md font-mono text-xs">
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

                {{-- Dynamic Region Filter: ONLY visible when Management == PT. Angkasa Pura Indonesia --}}
                <div class="w-36" x-show="management === 'PT. Angkasa Pura Indonesia' || management === 'ANGKASA_PURA_INDONESIA' || management === 'INJOURNEY'" x-transition x-cloak>
                    <label class="block text-[10px] text-aviation-600 dark:text-aviation-400 uppercase font-mono font-semibold mb-1">Region AP</label>
                    <select name="region" class="w-full filter-input-md font-mono text-xs border-aviation-400 dark:border-aviation-700">
                        <option value="all">Semua Region</option>
                        @foreach(['Region 1', 'Region 2', 'Region 3', 'Region 4', 'Region 5', 'Region 6'] as $reg)
                            <option value="{{ $reg }}" {{ in_array(request('region'), [$reg, str_replace(['1','2','3','4','5','6'], ['I','II','III','IV','V','VI'], $reg)]) ? 'selected' : '' }}>{{ $reg }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status / Traffic Filter (Domestik / Internasional) --}}
                <div class="w-32">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">Status / Tipe</label>
                    <select name="type" class="w-full filter-input-md font-mono text-xs">
                        <option value="all">All (Dom/Int'l)</option>
                        <option value="domestic" {{ request('type') === 'domestic' ? 'selected' : '' }}>Domestik</option>
                        <option value="international" {{ request('type') === 'international' ? 'selected' : '' }}>Internasional</option>
                    </select>
                </div>

                {{-- Dedicated IATA Search --}}
                <div class="w-24">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">IATA</label>
                    <input type="text" name="iata" value="{{ request('iata') }}" placeholder="BDO" maxlength="3"
                           class="w-full uppercase filter-input-md font-mono text-xs">
                </div>

                {{-- Dedicated ICAO Search --}}
                <div class="w-28">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">ICAO</label>
                    <input type="text" name="icao" value="{{ request('icao') }}" placeholder="WICC" maxlength="4"
                           class="w-full uppercase filter-input-md font-mono text-xs">
                </div>

                {{-- General Search --}}
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[10px] text-slate-500 uppercase font-mono font-semibold mb-1">Keyword</label>
                    <input type="text" name="ap_search" value="{{ request('ap_search') }}" placeholder="Nama Bandara, Kota, Provinsi..."
                           class="w-full filter-input-md text-xs">
                </div>

                <div class="flex items-end gap-2 pt-5">
                    <button type="submit" class="btn-aviation-primary px-4 py-2 text-xs font-bold font-mono rounded-xl transition shadow cursor-pointer">
                        Filter
                    </button>
                    <a href="{{ route('master-data.index', ['tab' => 'airports']) }}" class="px-3.5 py-2 bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 text-slate-600 dark:text-slate-300 text-xs font-mono rounded-xl transition">
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
            <div class="glass-card rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-sans text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-navy-950 border-b border-slate-200 dark:border-slate-800 text-[11px] font-mono text-slate-500 font-bold uppercase">
                                <th class="py-3 px-3 text-center w-12">No</th>
                                <th class="py-3 px-3 w-16">IATA</th>
                                <th class="py-3 px-3 w-16">ICAO</th>
                                <th class="py-3 px-4">Nama Bandar Udara</th>
                                <th class="py-3 px-3">Kota / Wilayah</th>
                                <th class="py-3 px-3">Provinsi</th>
                                <th class="py-3 px-3">Region</th>
                                <th class="py-3 px-3">Pengelola</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-3 text-center">Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono">
                            @forelse ($airports as $idx => $ap)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-navy-800/50 transition">
                                <td class="py-2.5 px-3 text-center text-slate-400">{{ ($airports->currentPage() - 1) * $airports->perPage() + $idx + 1 }}</td>
                                <td class="py-2.5 px-3">
                                    @if ($ap->iata_code)
                                        <span class="font-black text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 px-2 py-0.5 rounded-md text-[11px]">
                                            {{ $ap->iata_code }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 font-mono">—</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="font-mono text-slate-600 dark:text-slate-400 text-[11px]">
                                        {{ $ap->icao_code ?: '—' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 font-sans font-bold text-slate-900 dark:text-slate-100">
                                    {{ $ap->name }}
                                </td>
                                <td class="py-2.5 px-3 font-sans text-slate-600 dark:text-slate-300">{{ $ap->city ?: ($ap->area ?: '—') }}</td>
                                <td class="py-2.5 px-3 font-sans text-slate-600 dark:text-slate-300">{{ $ap->province ?: '—' }}</td>
                                <td class="py-2.5 px-3">
                                    @if ($ap->region)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                                            {{ $ap->region }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 font-sans">
                                    @if ($ap->isAngkasaPura())
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                                            PT. Angkasa Pura Indonesia
                                        </span>
                                    @elseif ($ap->isUpbuHubud())
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            UPT Ditjen Hubud
                                        </span>
                                    @elseif ($ap->isUptdPemda())
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            UPT Daerah / Pemda
                                        </span>
                                    @elseif ($ap->isTni())
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300 border border-red-200 dark:border-red-800">
                                            TNI
                                        </span>
                                    @elseif ($ap->isMissionaris())
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                            Missionaris
                                        </span>
                                    @elseif ($ap->isBumn())
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                            BUMN
                                        </span>
                                    @elseif ($ap->isSwasta())
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300 border border-teal-200 dark:border-teal-800">
                                            Swasta
                                        </span>
                                    @elseif ($ap->isMasyarakat())
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-lime-50 text-lime-700 dark:bg-lime-950 dark:text-lime-300 border border-lime-200 dark:border-lime-800">
                                            Masyarakat
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                            {{ $ap->management_name ?: $ap->management_type }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    @if ($ap->is_international)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800">
                                            INTL
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-400">
                                            DOM
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center text-[10px]">
                                    @if(str_contains($ap->data_source ?? '', 'INJOURNEY') || str_contains($ap->source ?? '', 'INJOURNEY'))
                                        <span class="px-1.5 py-0.5 rounded bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                                            INJOURNEY
                                        </span>
                                    @elseif(str_contains($ap->data_source ?? '', 'HUBUD') || str_contains($ap->source ?? '', 'HUBUD'))
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            HUBUD
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            REF
                                        </span>
                                    @endif
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

                {{-- Pagination Links --}}
                @if ($airports->hasPages())
                <div class="px-4 py-3 bg-slate-50 dark:bg-navy-950 border-t border-slate-200 dark:border-slate-800">
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
            <form method="GET" action="{{ route('master-data.index') }}" class="glass-card rounded-2xl p-5 flex flex-wrap items-center gap-3.5 shadow-sm">
                <input type="hidden" name="tab" value="airlines">
                
                {{-- Search --}}
                <div class="flex-1 min-w-[220px]">
                    <input type="text" name="al_search" value="{{ request('al_search') }}" placeholder="Cari Kode IATA, AOC, Nama Maskapai, Negara..."
                           class="w-full filter-input-md text-xs">
                </div>

                {{-- Category Filter --}}
                <div class="w-44">
                    <select name="category" class="w-full filter-input-md font-mono text-xs">
                        <option value="all">Semua Kategori</option>
                        <option value="domestic" {{ request('category') === 'domestic' ? 'selected' : '' }}>Domestik (AOC 121/135)</option>
                        <option value="international" {{ request('category') === 'international' ? 'selected' : '' }}>Internasional</option>
                        <option value="cargo" {{ request('category') === 'cargo' ? 'selected' : '' }}>Kargo Niaga</option>
                        <option value="charter" {{ request('category') === 'charter' ? 'selected' : '' }}>Charter / Perintis</option>
                    </select>
                </div>

                {{-- Status Filter --}}
                <div class="w-32">
                    <select name="al_status" class="w-full filter-input-md font-mono text-xs">
                        <option value="all">Semua Status</option>
                        <option value="active" {{ request('al_status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('al_status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn-aviation-primary px-4 py-2 text-xs font-bold font-mono rounded-xl transition shadow cursor-pointer">
                    Filter
                </button>
                <a href="{{ route('master-data.index', ['tab' => 'airlines']) }}" class="px-3.5 py-2 bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 text-slate-600 dark:text-slate-300 text-xs font-mono rounded-xl transition">
                    Reset
                </a>
            </form>

            {{-- Table --}}
            <div class="glass-card rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-sans text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-navy-950 border-b border-slate-200 dark:border-slate-800 text-[11px] font-mono text-slate-500 font-bold uppercase">
                                <th class="py-3 px-3 text-center w-12">No</th>
                                <th class="py-3 px-4 w-28">IATA Flight Prefix</th>
                                <th class="py-3 px-4 w-36">Hubud AOC / Org</th>
                                <th class="py-3 px-4">Nama Maskapai / Operator</th>
                                <th class="py-3 px-3">Kategori</th>
                                <th class="py-3 px-3">Negara Asal</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-3 text-center">Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 font-mono">
                            @forelse ($airlines as $idx => $al)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-navy-800/50 transition">
                                <td class="py-2.5 px-3 text-center text-slate-400">{{ $idx + 1 }}</td>
                                <td class="py-2.5 px-4">
                                    <span class="font-black text-aviation-700 dark:text-aviation-300 bg-aviation-50 dark:bg-aviation-950 border border-aviation-200 dark:border-aviation-800 px-2 py-0.5 rounded-md text-xs">
                                        {{ $al->airline_code }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4">
                                    @if($al->organization_code)
                                        <span class="font-mono text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 px-1.5 py-0.5 rounded text-[11px]">
                                            {{ $al->organization_code }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-4 font-sans font-bold text-slate-900 dark:text-slate-100">
                                    {{ $al->airline_name }}
                                </td>
                                <td class="py-2.5 px-3 font-sans">
                                    @if ($al->category === 'domestic')
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            Domestik
                                        </span>
                                    @elseif ($al->category === 'international')
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                                            Internasional
                                        </span>
                                    @elseif ($al->category === 'cargo')
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                            Kargo Niaga
                                        </span>
                                    @elseif ($al->category === 'charter')
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                            Charter / Perintis
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 font-sans text-slate-600 dark:text-slate-300">{{ $al->country ?: 'Indonesia' }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    @if ($al->status === 'active')
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            Active
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-500 dark:bg-navy-800 dark:text-slate-400">
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center text-[10px]">
                                    @if(str_contains($al->source, 'HUBUD'))
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            HUBUD
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            REF
                                        </span>
                                    @endif
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

    </main>

    <footer class="w-full border-t border-slate-200 dark:border-slate-800 py-4 text-center text-xs text-slate-400">
        SlotWaves Master Reference Database &bull; Synchronized with InJourney &amp; Ditjen Hubud
    </footer>

</div>
@endsection
