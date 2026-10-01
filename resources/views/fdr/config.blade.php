@extends('layouts.app')

@section('title', 'SlotWaves — Flight Daily Report (FDR) Configuration')
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between transition-colors duration-150')

@section('content')
<div class="min-h-screen flex flex-col justify-between" x-data="fdrConfigForm()">

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
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">FDR Analytical Module</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">OASYS Flight Daily Report Intelligence Pipeline</p>
            </div>
        </div>

        {{-- Breadcrumb Flow --}}
        <div class="hidden md:flex items-center gap-2 text-xs font-medium text-slate-400">
            <a href="{{ route('home') }}" class="hover:text-aviation-600 transition">Home</a>
            <span>&rarr;</span>
            <span class="text-slate-500">Select Type</span>
            <span>&rarr;</span>
            <span class="text-aviation-600 dark:text-aviation-400 font-bold px-2 py-0.5 rounded bg-aviation-50 dark:bg-aviation-950 border border-aviation-200 dark:border-aviation-800">Flight Daily Report</span>
            <span>&rarr;</span>
            <span class="text-slate-900 dark:text-white font-bold">FDR Config Form</span>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('home') }}" class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-navy-800 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Home</span>
            </a>
        </div>
    </header>

    {{-- ══ MAIN CONFIG CONTAINER ═══════════════════════════════════════════════ --}}
    <main class="flex-1 max-w-5xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        {{-- PAGE TITLE & METADATA OVERVIEW --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-navy-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950/80 border border-aviation-200 dark:border-aviation-800 text-aviation-700 dark:text-aviation-300 text-[11px] font-bold mb-1.5">
                    <span class="w-2 h-2 rounded-full bg-aviation-600 animate-pulse"></span>
                    <span>AOCC OASYS Operational Reporter</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    Flight Daily Report (FDR) Configuration
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Configure operational parameters, date boundaries, airline operators, and report mode filters.
                </p>
            </div>

            @if($upload)
                <div class="flex items-center gap-3 bg-slate-50 dark:bg-navy-800/80 p-3 rounded-xl border border-slate-200 dark:border-slate-700/60 text-xs">
                    <div>
                        <div class="text-[10px] uppercase font-bold text-slate-400">Current Dataset</div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 max-w-xs truncate">{{ $upload->original_filename }}</div>
                        <div class="text-[10px] text-aviation-600 dark:text-aviation-400 font-mono">{{ number_format($recordsCount) }} operational records mapped</div>
                    </div>
                    <button type="button" @click="showUploadModal = true" class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-navy-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:text-aviation-600 text-[11px] font-bold transition shadow-2xs">
                        Change File
                    </button>
                </div>
            @endif
        </div>

        {{-- FORM WRAPPER --}}
        <form method="GET" action="{{ $upload ? route('fdr.dashboard', $upload->id) : '#' }}" id="fdr-config-form" class="space-y-6">

            {{-- 1. PRIMARY OPERATIONAL PARAMETERS --}}
            <div class="bg-white dark:bg-navy-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm space-y-5">
                <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <span class="w-1.5 h-4 rounded-full bg-aviation-600"></span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">
                        Operational Scope &amp; Flight Filters
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">

                    {{-- Airport: Selectable (CGK, BDO, BTJ, etc.) --}}
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Airport <span class="text-aviation-600">*</span>
                        </label>
                        <select name="airport" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold focus:ring-aviation-500 focus:border-aviation-500 py-2.5 px-3">
                            <option value="ALL">ALL AIRPORTS</option>
                            @foreach($airports as $ap)
                                <option value="{{ $ap }}" {{ ($meta['airport'] ?? 'CGK') === $ap ? 'selected' : '' }}>
                                    {{ $ap }} — {{ $ap === 'CGK' ? 'Soekarno-Hatta' : ($ap === 'BDO' ? 'Husein Sastranegara' : ($ap === 'BTJ' ? 'Sultan Iskandar Muda' : $ap)) }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Selectable IATA station (e.g. CGK, BDO, BTJ)</p>
                    </div>

                    {{-- Leg: ALL / ARRIVAL / DEPARTURE --}}
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Flight Leg <span class="text-aviation-600">*</span>
                        </label>
                        <select name="leg" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold focus:ring-aviation-500 focus:border-aviation-500 py-2.5 px-3">
                            <option value="ALL" selected>ALL (Arrival &amp; Departure)</option>
                            <option value="ARRIVAL">ARRIVAL (Inbound Movements)</option>
                            <option value="DEPARTURE">DEPARTURE (Outbound Movements)</option>
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Movement directional classification</p>
                    </div>

                    {{-- Operator: ALL AIRLINE / Specific --}}
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Operator / Airline <span class="text-aviation-600">*</span>
                        </label>
                        <select name="operator" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold focus:ring-aviation-500 focus:border-aviation-500 py-2.5 px-3">
                            <option value="ALL">ALL AIRLINE</option>
                            @foreach($airlines as $al)
                                <option value="{{ $al }}">{{ $al }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Operator: Garuda Indonesia, Citilink, Lion, etc.</p>
                    </div>

                    {{-- Traffic: ALL / DOMESTIC / INTERNATIONAL --}}
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Traffic Type
                        </label>
                        <select name="traffic" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold focus:ring-aviation-500 focus:border-aviation-500 py-2.5 px-3">
                            <option value="ALL" selected>ALL TRAFFIC</option>
                            <option value="DOMESTIC">DOMESTIC</option>
                            <option value="INTERNATIONAL">INTERNATIONAL</option>
                        </select>
                    </div>

                    {{-- Data Type: OPERATIONAL DATA / REPORT DATA --}}
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Data Type
                        </label>
                        <select name="data_type" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold focus:ring-aviation-500 focus:border-aviation-500 py-2.5 px-3">
                            <option value="OPERATIONAL DATA" {{ ($meta['data_type'] ?? '') === 'OPERATIONAL DATA' ? 'selected' : '' }}>OPERATIONAL DATA</option>
                            <option value="REPORT DATA" {{ ($meta['data_type'] ?? '') === 'REPORT DATA' ? 'selected' : '' }}>REPORT DATA</option>
                        </select>
                    </div>

                    {{-- Realization: YES / NO (mapped from dataset metadata) --}}
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Realization Status
                        </label>
                        <select name="realization" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-semibold focus:ring-aviation-500 focus:border-aviation-500 py-2.5 px-3">
                            <option value="ALL">ALL (Both Realized &amp; Planned)</option>
                            <option value="YES" {{ ($meta['realization'] ?? 'YES') === 'YES' ? 'selected' : '' }}>YES (Realized Movements)</option>
                            <option value="NO" {{ ($meta['realization'] ?? '') === 'NO' ? 'selected' : '' }}>NO (Planned / Non-Realized)</option>
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Mapped from workbook header metadata: {{ $meta['realization'] ?? 'YES' }}</p>
                    </div>

                    {{-- Flight No & Suffix: Optional text/dropdown --}}
                    <div class="sm:col-span-2 lg:col-span-2">
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Flight No &amp; Suffix (Optional)
                        </label>
                        <div class="flex gap-2">
                            <input type="text" name="flight_no" placeholder="e.g. GA120, QG682"
                                   class="flex-1 rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-mono py-2.5 px-3 focus:ring-aviation-500 focus:border-aviation-500 uppercase">
                            <select name="suffix" class="w-28 rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-mono py-2.5 px-3">
                                <option value="">SUFFIX</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Filter specific flight number and turnaround suffix</p>
                    </div>

                    {{-- Date Range: Single day or range picker (e.g., 01-08-2026 → 31-08-2026) --}}
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Date Range
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" name="start_date" value="{{ $meta['period_start'] ?? '2026-08-01' }}"
                                   class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-mono py-2 px-2.5">
                            <input type="date" name="end_date" value="{{ $meta['period_end'] ?? '2026-08-31' }}"
                                   class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-navy-800 text-slate-900 dark:text-white text-xs font-mono py-2 px-2.5">
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Single day or range picker (01-08-2026 &rarr; 31-08-2026)</p>
                    </div>

                </div>
            </div>

            {{-- Default Report Mode: 1 (NORMAL) --}}
            <input type="hidden" name="report_mode" value="1">

            {{-- 2. SUBMIT & ACTION CONTROLS --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                <a href="{{ route('home') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    <span>Back to Portal Home</span>
                </a>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button type="submit" :disabled="!hasValidUpload"
                            class="w-full sm:w-auto py-3 px-8 rounded-xl font-bold text-xs sm:text-sm text-white bg-aviation-600 hover:bg-aviation-700 shadow-md shadow-aviation-600/25 flex items-center justify-center gap-2 transition duration-150 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        <span>Generate FDR Dashboard &rarr;</span>
                    </button>
                </div>
            </div>

        </form>

    </main>

    {{-- MODAL: UPLOAD WORKBOOK OR USE REFERENCE --}}
    <div x-show="showUploadModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="showUploadModal = false" class="bg-white dark:bg-navy-900 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="font-bold text-slate-900 dark:text-white text-sm">Upload Flight Daily Report Source</div>
                <button @click="showUploadModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('fdr.upload') }}" enctype="multipart/form-data" class="space-y-4" @submit.prevent="handleModalUpload($event)">
                @csrf
                <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-xl p-6 text-center hover:border-aviation-500 transition cursor-pointer">
                    <input type="file" name="fdr_file" accept=".xls,.xlsx,.csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream,text/csv" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-aviation-50 file:text-aviation-700 hover:file:bg-aviation-100 cursor-pointer" required>
                    <p class="text-[11px] text-slate-400 mt-2">Accepted formats: OASYS HTML (.xls), Excel (.xlsx), CSV (Up to 50 MB)</p>
                </div>

                {{-- Progress Bar during Chunked Transfer --}}
                <div x-show="isUploadingModal" x-cloak class="space-y-1.5 p-3 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 text-xs">
                    <div class="flex items-center justify-between font-mono text-[10.5px]">
                        <span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="modalUploadText"></span>
                        <span class="font-bold" x-text="modalUploadProgress + '%'"></span>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-navy-800 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-aviation-600 h-full rounded-full transition-all duration-200" :style="'width:' + modalUploadProgress + '%'"></div>
                    </div>
                </div>

                {{-- Modal Upload Paused Banner --}}
                <div x-show="isModalPaused" x-cloak class="space-y-2 p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700/60 text-xs">
                    <div class="font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">Upload Terhenti (PAUSED)</div>
                    <div class="text-[11px] text-amber-700 dark:text-amber-400 font-mono" x-text="modalPausedMessage"></div>
                    <div class="w-full bg-amber-200 dark:bg-amber-900/60 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-amber-500 h-full rounded-full transition-all duration-300"
                             :style="`width:${modalPausedTotalChunks > 0 ? Math.round((modalPausedChunkIdx / modalPausedTotalChunks) * 100) : 0}%`"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="resumeModalUpload()" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs transition cursor-pointer">Lanjutkan Upload</button>
                        <button type="button" @click="cancelModalUpload()" class="px-3 py-1.5 rounded-lg bg-white dark:bg-navy-800 border border-amber-300 text-amber-700 dark:text-amber-300 font-bold text-xs transition cursor-pointer">Batalkan</button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="button" @click="useReferenceDataset()" :disabled="isUploadingModal" class="text-xs text-aviation-600 dark:text-aviation-400 font-bold hover:underline disabled:opacity-50">
                        Use Reference OASYS FDR Dataset
                    </button>
                    <button type="submit" :disabled="isUploadingModal" class="py-2 px-5 rounded-xl bg-aviation-600 text-white font-bold text-xs hover:bg-aviation-700 shadow-sm transition disabled:opacity-50 cursor-pointer">
                        <span x-show="!isUploadingModal">Upload &amp; Parse</span>
                        <span x-show="isUploadingModal">Processing...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- FOOTER --}}
    <footer class="w-full border-t border-slate-200/80 dark:border-slate-800/80 bg-white/70 dark:bg-navy-900/70 backdrop-blur-md px-4 sm:px-8 py-3 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
        <div>SlotWaves Aviation Control Room &copy; {{ date('Y') }} • FDR Analytical Engine</div>
        <div class="flex items-center gap-2">
            <span>OASYS Operational Standard</span>
            <span>•</span>
            <span class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">READY</span>
        </div>
    </footer>

</div>

@push('scripts')
<script>
function fdrConfigForm() {
    return {
        selectedMode: 1,
        showUploadModal: false,
        hasValidUpload: {{ $upload ? 'true' : 'false' }},

        isUploadingModal: false,
        isModalPaused: false,
        modalUploadProgress: 0,
        modalUploadText: '',
        modalPausedMessage: '',
        modalPausedChunkIdx: 0,
        modalPausedTotalChunks: 0,
        modalFdrToken: null,
        modalSelectedFile: null,

        async init() {
            const token = localStorage.getItem('fdr_config_upload_token');
            if (token) {
                try {
                    const res = await fetch(`/upload/session/${token}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (data.success && (data.status === 'PAUSED' || data.status === 'UPLOADING' || data.status === 'IN_PROGRESS' || data.status === 'CREATED')) {
                            this.modalFdrToken = token;
                            this.isModalPaused = true;
                            this.showUploadModal = true;
                            this.modalPausedChunkIdx = (data.total_chunks || 0) - (data.missing_chunks ? data.missing_chunks.length : 0);
                            this.modalPausedTotalChunks = data.total_chunks || 0;
                            this.modalPausedMessage = `Sesi upload sebelumnya (${data.original_filename || 'file'}) ditemukan: ${this.modalPausedChunkIdx}/${this.modalPausedTotalChunks} chunk tersimpan. Pilih kembali file "${data.original_filename}" lalu klik "Lanjutkan Upload".`;
                        } else if (data.status === 'READY' || data.status === 'COMPLETED' || data.status === 'CANCELLED') {
                            localStorage.removeItem('fdr_config_upload_token');
                        }
                    }
                } catch (e) {
                    console.warn('Could not restore config upload session:', e);
                }
            }
        },

        async handleModalUpload(event) {
            const form = event.target;
            const fileInput = form.querySelector('input[name="fdr_file"]');
            const file = fileInput?.files?.[0];
            if (!file) return;

            this.modalSelectedFile = file;

            // If file <= 3 MB, standard form submission
            if (file.size <= 3 * 1024 * 1024) {
                form.submit();
                return;
            }

            this.isUploadingModal = true;
            this.isModalPaused = false;
            this.modalUploadProgress = 10;
            this.modalUploadText = 'Preparing chunked upload for ' + file.name + '...';

            const csrfToken = document.querySelector('input[name="_token"]')?.value || '{{ csrf_token() }}';
            const FDR_CHUNK_SIZE = 3 * 1024 * 1024; // 3 MiB
            const totalChunks = Math.ceil(file.size / FDR_CHUNK_SIZE);
            const fileSizeMB = (file.size / 1048576).toFixed(2);
            const fileHash = file.size + '_' + file.name;
            const delay = (ms) => new Promise(res => setTimeout(res, ms));

            try {
                // Step 1: Create or resume persistent upload session
                let uploadToken = this.modalFdrToken || localStorage.getItem('fdr_config_upload_token');
                let missingChunks = null;

                try {
                    const sessionRes = await fetch('{{ route("upload.session.create") }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            upload_token: uploadToken || undefined,
                            original_filename: file.name,
                            file_size: file.size,
                            total_chunks: totalChunks,
                            chunk_size: FDR_CHUNK_SIZE,
                            file_hash: fileHash,
                            report_type: 'fdr'
                        })
                    });
                    if (sessionRes.ok) {
                        const sessionData = await sessionRes.json();
                        if (sessionData.success) {
                            uploadToken = sessionData.upload_token;
                            missingChunks = sessionData.missing_chunks;
                            this.modalFdrToken = uploadToken;
                            localStorage.setItem('fdr_config_upload_token', uploadToken);

                            if (sessionData.already_complete) {
                                missingChunks = [];
                            }
                            const alreadyDone = totalChunks - (missingChunks ? missingChunks.length : totalChunks);
                            if (alreadyDone > 0) {
                                this.modalUploadText = `Resuming: ${alreadyDone} / ${totalChunks} chunks already uploaded...`;
                                this.modalUploadProgress = Math.round((alreadyDone / totalChunks) * 50);
                            }
                        }
                    }
                } catch (sessionErr) {
                    console.warn('Session create failed:', sessionErr);
                }

                if (!uploadToken) {
                    uploadToken = 'upl_fdr_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
                    this.modalFdrToken = uploadToken;
                    localStorage.setItem('fdr_config_upload_token', uploadToken);
                }

                const chunksToUpload = missingChunks !== null
                    ? missingChunks
                    : Array.from({ length: totalChunks }, (_, i) => i);

                let lastChunkData = null;

                for (let ci = 0; ci < chunksToUpload.length; ci++) {
                    const chunkIdx = chunksToUpload[ci];
                    const start = chunkIdx * FDR_CHUNK_SIZE;
                    const end = Math.min(file.size, start + FDR_CHUNK_SIZE);
                    const chunkBlob = file.slice(start, end);
                    const uploadedMB = (end / 1048576).toFixed(2);

                    const overallDone = (totalChunks - chunksToUpload.length) + ci + 1;
                    this.modalUploadProgress = Math.max(10, Math.round((overallDone / totalChunks) * 50));
                    this.modalUploadText = `Chunk ${chunkIdx + 1} / ${totalChunks} * ${uploadedMB} MB / ${fileSizeMB} MB`;

                    let chunkSuccess = false;
                    let lastErrorMsg = '';
                    const retryDelays = [500, 1000, 2000];

                    for (let attempt = 0; attempt <= retryDelays.length; attempt++) {
                        if (attempt > 0) {
                            const waitTime = retryDelays[attempt - 1] || 2000;
                            this.modalUploadText = `Retry ${attempt}/${retryDelays.length} chunk ${chunkIdx + 1}... (${waitTime}ms)`;
                            await delay(waitTime);
                        }

                        try {
                            const chunkForm = new FormData();
                            chunkForm.append('report_type', 'fdr');
                            chunkForm.append('upload_token', uploadToken);
                            chunkForm.append('chunk_index', chunkIdx);
                            chunkForm.append('total_chunks', totalChunks);
                            chunkForm.append('filename', file.name);
                            chunkForm.append('chunk', chunkBlob, file.name);
                            chunkForm.append('_token', csrfToken);

                            const chunkRes = await fetch('{{ route("upload.chunk") }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: chunkForm
                            });

                            const contentType = chunkRes.headers.get('content-type') || '';
                            let chunkData = null;

                            if (contentType.includes('application/json')) {
                                chunkData = await chunkRes.json();
                            } else {
                                const errorText = await chunkRes.text();
                                console.error('Non-JSON chunk response:', chunkRes.status, errorText.substring(0, 200));
                                lastErrorMsg = `HTTP ${chunkRes.status}: Unexpected server response.`;
                                if (chunkRes.status === 413) throw new Error('HTTP 413: Payload Too Large.');
                                continue;
                            }

                            if (!chunkRes.ok || !chunkData.success) {
                                const errObj = chunkData.error;
                                const msg = (typeof errObj === 'object' && errObj) ? (errObj.message || errObj.code) : (errObj || 'Chunk upload failed');
                                lastErrorMsg = `Chunk ${chunkIdx + 1}: ${msg}`;
                                if (chunkRes.status === 413 || chunkRes.status === 422) throw new Error(msg);
                                continue;
                            }

                            lastChunkData = chunkData;
                            chunkSuccess = true;
                            break;

                        } catch (networkErr) {
                            lastErrorMsg = networkErr.message || 'Network error';
                            if (networkErr.message && (networkErr.message.includes('413') || networkErr.message.includes('422'))) {
                                throw networkErr;
                            }
                        }
                    }

                    if (!chunkSuccess) {
                        // Pause server-side
                        try {
                            await fetch(`/upload/session/${uploadToken}/pause`, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({ reason: lastErrorMsg })
                            });
                        } catch (e) {}

                        this.isUploadingModal = false;
                        this.isModalPaused = true;
                        this.modalPausedChunkIdx = chunkIdx;
                        this.modalPausedTotalChunks = totalChunks;
                        this.modalPausedMessage = `Chunk ${chunkIdx + 1} / ${totalChunks} gagal setelah 3 retries. ${lastErrorMsg} -- Klik "Lanjutkan Upload".`;
                        this.modalUploadText = this.modalPausedMessage;
                        return;
                    }
                }

                // All chunks done
                localStorage.removeItem('fdr_config_upload_token');
                this.modalFdrToken = null;

                if (lastChunkData && lastChunkData.is_async_job && lastChunkData.job_id) {
                    this.modalUploadProgress = 55;
                    this.modalUploadText = 'Upload complete. Initializing streaming parser...';

                    try {
                        fetch(lastChunkData.process_url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken
                            }
                        }).catch(() => {});
                    } catch (e) {}

                    const pollUrl = lastChunkData.poll_url;
                    let jobReady = false;
                    let pollAttempts = 0;

                    while (!jobReady && pollAttempts < 120) {
                        pollAttempts++;
                        await delay(800);

                        try {
                            const pollRes = await fetch(pollUrl, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            if (!pollRes.ok) continue;

                            const job = await pollRes.json();
                            if (!job.success) continue;

                            if (job.status === 'READING') {
                                this.modalUploadProgress = Math.max(this.modalUploadProgress, 60);
                                this.modalUploadText = 'Reading OASYS tables & metadata...';
                            } else if (job.status === 'PARSING') {
                                const rowCount = job.processed_rows || 0;
                                this.modalUploadProgress = Math.max(this.modalUploadProgress, Math.min(85, 60 + Math.round((job.progress || 0) * 0.25)));
                                this.modalUploadText = `Streaming flight movements (${rowCount.toLocaleString()} rows)...`;
                            } else if (job.status === 'NORMALIZING') {
                                this.modalUploadProgress = 88;
                                this.modalUploadText = 'Normalizing routes, dates & realization...';
                            } else if (job.status === 'VALIDATING') {
                                this.modalUploadProgress = 94;
                                this.modalUploadText = 'Validating records & excluding summary rows...';
                            } else if (job.status === 'READY') {
                                this.modalUploadProgress = 100;
                                const movements = (job.processed_rows || job.total_rows || 0).toLocaleString();
                                this.modalUploadText = `Ready! Ingested ${movements} valid flight movements. Loading...`;
                                jobReady = true;
                                setTimeout(() => {
                                    window.location.href = job.result_url || lastChunkData.redirect_url;
                                }, 400);
                                return;
                            } else if (job.status === 'PAUSED') {
                                this.isUploadingModal = false;
                                this.isModalPaused = true;
                                this.modalPausedMessage = `Pemrosesan terhenti: ${job.error_message || 'Server timeout'}. Chunk upload selesai. Coba lagi.`;
                                this.modalUploadText = this.modalPausedMessage;
                                return;
                            } else if (job.status === 'FAILED') {
                                throw new Error(`FDR Ingestion failed: ${job.error_message || 'Processing error'}`);
                            }
                        } catch (pollErr) {
                            if (pollErr.message && pollErr.message.includes('FDR Ingestion failed')) throw pollErr;
                            console.warn('Poll error:', pollErr);
                        }
                    }

                    if (!jobReady) {
                        window.location.href = lastChunkData.redirect_url;
                        return;
                    }
                } else if (lastChunkData && lastChunkData.completed) {
                    this.modalUploadProgress = 100;
                    this.modalUploadText = 'Complete! Loading Configuration...';
                    setTimeout(() => { window.location.href = lastChunkData.redirect_url; }, 250);
                    return;
                }
            } catch (err) {
                this.isUploadingModal = false;
                this.modalUploadText = 'Upload gagal: ' + (err.message || 'Server error');
                this.modalUploadProgress = 0;
            }
        },

        async resumeModalUpload() {
            if (!this.modalSelectedFile) {
                alert('Pilih file terlebih dahulu untuk melanjutkan upload.');
                return;
            }
            if (!this.modalFdrToken) {
                const storedToken = localStorage.getItem('fdr_config_upload_token');
                if (storedToken) this.modalFdrToken = storedToken;
            }
            this.isModalPaused = false;
            this.modalPausedMessage = '';
            // Re-trigger upload with same file
            const fakeEvent = {
                target: {
                    querySelector: (sel) => sel === 'input[name="fdr_file"]'
                        ? { files: [this.modalSelectedFile] }
                        : null
                }
            };
            await this.handleModalUpload(fakeEvent);
        },

        async cancelModalUpload() {
            const token = this.modalFdrToken || localStorage.getItem('fdr_config_upload_token');
            if (token) {
                try {
                    await fetch(`/upload/session/${token}/cancel`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({})
                    });
                } catch (e) {}
            }
            localStorage.removeItem('fdr_config_upload_token');
            this.modalFdrToken = null;
            this.isModalPaused = false;
            this.modalPausedMessage = '';
            this.modalUploadProgress = 0;
            this.modalUploadText = '';
            this.modalSelectedFile = null;
        },

        async useReferenceDataset() {
            try {
                const res = await fetch('{{ route("fdr.use-reference") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await res.json();
                if (data.success && data.redirect_url) {
                    window.location.href = data.redirect_url;
                }
            } catch (err) {
                alert('Failed to load reference dataset.');
            }
        }
    };
}
</script>
@endpush
@endsection
