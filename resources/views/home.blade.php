@extends('layouts.app')

@section('title', 'SlotWaves — Flight Schedule & Airport Slot Intelligence')
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between transition-colors duration-150')

@section('content')
<div x-data="uploadPortal()" class="min-h-screen flex flex-col justify-between">

    {{-- ══ COMPACT TOPBAR NAVIGATION ══════════════════════════════════════════ --}}
    <header class="w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-navy-900/90 backdrop-blur-md sticky top-0 z-30 px-4 sm:px-8 py-3 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-aviation-600 flex items-center justify-center shadow-sm text-white">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-black tracking-tight text-slate-900 dark:text-white">SlotWaves</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">AOCC Operations</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Airport Operational Slot &amp; Flight Intelligence</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('master-data.index') }}"
               class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-navy-800/80 hover:bg-slate-100 dark:hover:bg-navy-800 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7zm0 4h16M9 4v16"/>
                </svg>
                <span>Master Data</span>
            </a>

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

    {{-- ══ MAIN INGESTION PORTAL ═══════════════════════════════════════════════ --}}
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-xl space-y-4">

            {{-- Portal Card --}}
            <div class="glass-card p-6 sm:p-8 shadow-lg dark:shadow-xl">

                {{-- Header Inside Card --}}
                <div class="text-center mb-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-aviation-50 dark:bg-aviation-950/80 border border-aviation-200 dark:border-aviation-800 text-aviation-700 dark:text-aviation-300 text-xs font-semibold mb-2.5">
                        <span class="radar-dot w-2 h-2 rounded-full bg-aviation-600 dark:bg-aviation-400"></span>
                        <span>FLIGHT INTELLIGENCE PIPELINE</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                        Import Flight Schedule
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1 max-w-md mx-auto">
                        Upload standard airport slot schedule PDF to extract flights, calculate capacity demand, and generate 24-hour interactive timelines.
                    </p>
                </div>

                {{-- Pipeline Stepper Visual Guide --}}
                <div class="mb-5 p-3 rounded-xl bg-slate-50/80 dark:bg-navy-950/80 border border-slate-200/80 dark:border-slate-800">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2 text-center">Pipeline Workflow</div>
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-1 text-[10.5px] font-mono text-center">
                        <div class="p-1 rounded bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-semibold">1. PDF Read</div>
                        <div class="p-1 rounded bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-semibold">2. Extract</div>
                        <div class="p-1 rounded bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-semibold">3. Match</div>
                        <div class="p-1 rounded bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-semibold">4. Timeline</div>
                        <div class="p-1 rounded bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-semibold">5. Capacity</div>
                        <div class="p-1 rounded bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-semibold">6. Export</div>
                    </div>
                </div>

                {{-- Error notification --}}
                @if ($errors->any())
                    <div class="mb-5 p-3.5 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 rounded-xl text-red-700 dark:text-red-300 text-xs flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <div class="font-bold">Upload / Parsing Notice</div>
                            <div class="mt-0.5 font-mono text-[11px]">{{ $errors->first() }}</div>
                        </div>
                    </div>
                @endif

                {{-- Form --}}
                <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data" id="upload-form" @submit="startProcessing($event)">
                    @csrf

                    {{-- Drag and drop target --}}
                    <div class="relative">
                        <label for="schedule_pdf"
                               @dragover.prevent="isDragging = true"
                               @dragleave.prevent="isDragging = false"
                               @drop.prevent="handleDrop($event)"
                               :class="isDragging ? 'border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/40 ring-4 ring-aviation-500/10' : 'border-slate-300 dark:border-slate-700/80 bg-slate-50/60 dark:bg-navy-950/60 hover:border-aviation-400 hover:bg-aviation-50/20 dark:hover:bg-navy-800/40'"
                               class="flex flex-col items-center justify-center w-full h-52 border-2 border-dashed rounded-xl cursor-pointer transition-all duration-200 group">
                            
                            <template x-if="!selectedFileName">
                                <div class="flex flex-col items-center justify-center p-5 text-center">
                                    <div class="w-12 h-12 rounded-xl bg-white dark:bg-navy-800 shadow-sm border border-slate-200 dark:border-slate-700 flex items-center justify-center text-aviation-600 dark:text-aviation-400 group-hover:scale-105 transition duration-150 mb-3">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                                        </svg>
                                    </div>
                                    <div class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                        Drag &amp; Drop Flight Schedule PDF
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        or <span class="text-aviation-600 dark:text-aviation-400 font-semibold underline underline-offset-2">Browse from your computer</span>
                                    </p>
                                    <div class="mt-2.5 flex items-center gap-2 text-[10px] text-slate-400 font-mono">
                                        <span class="px-2 py-0.5 rounded bg-slate-200/70 dark:bg-navy-800">.PDF FORMAT</span>
                                        <span>MAX 20 MB</span>
                                    </div>
                                </div>
                            </template>

                            {{-- Selected file preview state --}}
                            <template x-if="selectedFileName">
                                <div class="flex flex-col items-center justify-center p-5 text-center">
                                    <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-300 dark:border-emerald-700 flex items-center justify-center text-emerald-600 dark:text-emerald-400 mb-2.5">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-white max-w-sm truncate" x-text="selectedFileName"></div>
                                    <div class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        File Ready to Ingest
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5 font-mono" x-text="selectedFileSize"></div>
                                </div>
                            </template>

                            <input id="schedule_pdf" name="schedule_pdf" type="file" accept=".pdf" class="hidden" @change="handleFileChange($event)"/>
                        </label>
                    </div>

                    {{-- Dynamic Pipeline Progress Indicator on Submit --}}
                    <div x-show="isProcessing" x-transition class="mt-4 p-3.5 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-2.5">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin text-aviation-600 dark:text-aviation-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Pipeline Ingestion Active
                            </span>
                            <span class="font-mono text-aviation-600 dark:text-aviation-400 font-bold" x-text="progressText"></span>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-navy-800 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-aviation-600 h-full rounded-full transition-all duration-300" :style="'width:' + progressPercent + '%'"></div>
                        </div>
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-1 pt-0.5 text-[9.5px] font-mono text-slate-500 dark:text-slate-400">
                            <div class="flex items-center justify-center gap-0.5" :class="progressPercent >= 15 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">
                                <span>✓</span> Read
                            </div>
                            <div class="flex items-center justify-center gap-0.5" :class="progressPercent >= 35 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">
                                <span>✓</span> Extract
                            </div>
                            <div class="flex items-center justify-center gap-0.5" :class="progressPercent >= 55 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">
                                <span>✓</span> Match
                            </div>
                            <div class="flex items-center justify-center gap-0.5" :class="progressPercent >= 75 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">
                                <span>✓</span> Timeline
                            </div>
                            <div class="flex items-center justify-center gap-0.5" :class="progressPercent >= 90 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">
                                <span>✓</span> Capacity
                            </div>
                            <div class="flex items-center justify-center gap-0.5" :class="progressPercent >= 98 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">
                                <span>✓</span> Export
                            </div>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit" id="submit-btn" :disabled="!selectedFileName || isProcessing"
                            :class="!selectedFileName || isProcessing ? 'opacity-60 cursor-not-allowed' : 'btn-aviation-primary shadow-md shadow-aviation-600/25'"
                            class="mt-4 w-full py-3 px-5 rounded-xl font-bold text-xs sm:text-sm text-white flex items-center justify-center gap-2 transition duration-200 cursor-pointer">
                        <template x-if="!isProcessing">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                                </svg>
                                <span>Generate Operational Timeline &amp; Capacity</span>
                            </span>
                        </template>
                        <template x-if="isProcessing">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span>Processing Schedule PDF...</span>
                            </span>
                        </template>
                    </button>
                </form>

                {{-- Master Data Quick Access --}}
                <div class="mt-5 pt-4 border-t border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Hubud &amp; InJourney Verified Reference</span>
                    </span>
                    <a href="{{ route('master-data.index') }}" class="font-semibold text-aviation-600 dark:text-aviation-400 hover:underline inline-flex items-center gap-1">
                        <span>Master Registry</span> &rarr;
                    </a>
                </div>

            </div>

        </div>
    </main>

    {{-- ══ FOOTER WORKFLOW STEPS ═══════════════════════════════════════════════ --}}
    <footer class="w-full border-t border-slate-200/80 dark:border-slate-800/80 py-3.5 px-4 text-center">
        <div class="max-w-4xl mx-auto flex flex-wrap items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="font-bold text-slate-700 dark:text-slate-300">SlotWaves Pipeline:</span>
            @foreach (['1. PDF Ingestion', '2. Regex Multi-Parser', '3. Airport & Airline Match', '4. Capacity Calculations', '5. 24-Hour Timeline', '6. High-Res Export'] as $step)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-[11px] font-medium shadow-2xs font-mono">
                    {{ $step }}
                </span>
            @endforeach
        </div>
    </footer>

</div>
@endsection

@push('scripts')
<script>
function uploadPortal() {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
        isDragging: false,
        selectedFileName: '',
        selectedFileSize: '',
        isProcessing: false,
        progressPercent: 15,
        progressText: 'Reading Schedule...',

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
        },

        handleFileChange(ev) {
            if (ev.target.files && ev.target.files.length) {
                const file = ev.target.files[0];
                this.selectedFileName = file.name;
                this.selectedFileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            }
        },

        handleDrop(ev) {
            this.isDragging = false;
            if (ev.dataTransfer.files && ev.dataTransfer.files.length) {
                const file = ev.dataTransfer.files[0];
                if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                    const input = document.getElementById('schedule_pdf');
                    input.files = ev.dataTransfer.files;
                    this.selectedFileName = file.name;
                    this.selectedFileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                }
            }
        },

        startProcessing(ev) {
            this.isProcessing = true;
            this.progressPercent = 20;
            this.progressText = '1/6 Reading PDF Document...';

            setTimeout(() => {
                this.progressPercent = 40;
                this.progressText = '2/6 Extracting Flights...';
            }, 350);

            setTimeout(() => {
                this.progressPercent = 65;
                this.progressText = '3/6 Matching Registry...';
            }, 700);

            setTimeout(() => {
                this.progressPercent = 85;
                this.progressText = '4/6 Generating 24H Timeline...';
            }, 1050);

            setTimeout(() => {
                this.progressPercent = 95;
                this.progressText = '5/6 Calculating Capacity...';
            }, 1400);
        }
    };
}
</script>
@endpush
