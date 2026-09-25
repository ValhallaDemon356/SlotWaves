@extends('layouts.app')

@section('title', 'SlotWaves — Flight Schedule & Airport Intelligence Ingestion')
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between transition-colors duration-150')

@section('content')
<div x-data="unifiedReportPortal()" class="min-h-screen flex flex-col justify-between">

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
            <a href="{{ route('home') }}"
               class="text-xs font-semibold text-aviation-700 dark:text-aviation-300 px-3 py-1.5 rounded-lg border border-aviation-300 dark:border-aviation-700 bg-aviation-50 dark:bg-aviation-950/80 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Home</span>
            </a>

            <a href="{{ route('master-data.index') }}"
               class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-navy-800/80 hover:bg-slate-100 dark:hover:bg-navy-800 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7zm0 4h16M9 4v16"/>
                </svg>
                <span>Master Data</span>
            </a>

            <a href="{{ route('fdr.config') }}"
               class="text-xs font-bold text-aviation-700 dark:text-aviation-300 hover:text-aviation-800 px-3 py-1.5 rounded-lg border border-aviation-300 dark:border-aviation-700 bg-aviation-50 dark:bg-aviation-950/80 transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-aviation-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Flight Daily Report</span>
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

    {{-- ══ MAIN PORTAL CONTAINER ═══════════════════════════════════════════════ --}}
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-4xl space-y-6">

            {{-- ═══════════════════════════════════════════════════════════════════
                 STEP 1: SELECT TYPE DATA TO GENERATE
                 ═══════════════════════════════════════════════════════════════════ --}}
            <div x-show="currentStep === 'select'" x-transition class="glass-card p-6 sm:p-8 shadow-xl">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-aviation-50 dark:bg-aviation-950/80 border border-aviation-200 dark:border-aviation-800 text-aviation-700 dark:text-aviation-300 text-xs font-semibold mb-2.5">
                        <span class="radar-dot w-2 h-2 rounded-full bg-aviation-600 dark:bg-aviation-400"></span>
                        <span>Unified Report Pipeline</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                        Select Type Data to Generate
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1 max-w-xl mx-auto">
                        Each report type requires its own source-file template. Choose the report type to ingest, validate, and generate corresponding analytical dashboards.
                    </p>
                </div>

                {{-- ══ DEDICATED INDEPENDENT FLIGHT DAILY REPORT (FDR) PIPELINE ══ --}}
                <div class="mb-6 p-4 sm:p-5 rounded-2xl border-2 border-aviation-500/50 bg-gradient-to-r from-aviation-50/90 via-white to-blue-50/70 dark:from-navy-900 dark:via-navy-900/90 dark:to-aviation-950/40 shadow-sm">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-xl bg-aviation-600 text-white flex items-center justify-center font-black text-sm shadow-md shadow-aviation-600/20 shrink-0">
                                FDR
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white">
                                        Flight Daily Report (FDR)
                                    </h3>
                                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-aviation-600 text-white uppercase">
                                        OASYS System
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-xl">
                                    Operational Flight Daily Report analytics with 24-hour mentor charts, load factor analysis, and data reconciliation engine.
                                </p>
                            </div>
                        </div>

                        <a href="{{ route('fdr.config') }}"
                           class="w-full sm:w-auto px-5 py-2.5 rounded-xl font-bold text-xs text-white bg-aviation-600 hover:bg-aviation-700 shadow-md shadow-aviation-600/25 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
                            <span>Open FDR Config</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Report Types Organized by Category --}}
                <div class="space-y-5">
                    @foreach ($reportTypesGrouped as $categoryName => $types)
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2 flex items-center gap-2">
                                <span>{{ $categoryName }}</span>
                                <span class="flex-1 border-b border-slate-200 dark:border-slate-800"></span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                @foreach ($types as $item)
                                    <div @click="selectedReport = '{{ $item['id'] }}'"
                                         :class="selectedReport === '{{ $item['id'] }}' 
                                            ? 'border-aviation-600 bg-aviation-50/70 dark:bg-aviation-950/70 ring-2 ring-aviation-500/30' 
                                            : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-navy-900/60 hover:border-slate-300 dark:hover:border-slate-700 hover:bg-slate-50/50 dark:hover:bg-navy-800/40'"
                                         class="p-3.5 rounded-xl border transition-all duration-150 cursor-pointer flex flex-col justify-between group">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[11px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300">
                                                    {{ $item['number'] }}
                                                </span>
                                                <span class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-aviation-600 dark:group-hover:text-aviation-400">
                                                    {{ $item['name'] }}
                                                </span>
                                            </div>
                                            <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded uppercase"
                                                  :class="selectedReport === '{{ $item['id'] }}' ? 'bg-aviation-600 text-white' : 'bg-slate-100 dark:bg-navy-800 text-slate-400'">
                                                {{ implode(', ', $item['extensions']) }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 line-clamp-2 leading-relaxed">
                                            {{ $item['description'] }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Action: Continue to Upload --}}
                <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div class="text-xs text-slate-500">
                        <span class="font-medium">Selected:</span>
                        <span class="font-bold text-slate-900 dark:text-white" x-text="selectedReportConfig ? selectedReportConfig.name : 'None'"></span>
                    </div>
                    <button type="button" @click="proceedToUpload()" :disabled="!selectedReport"
                            :class="!selectedReport ? 'opacity-50 cursor-not-allowed' : 'btn-aviation-primary shadow-md shadow-aviation-600/25'"
                            class="py-2.5 px-6 rounded-xl font-bold text-xs sm:text-sm text-white flex items-center gap-2 transition duration-150 cursor-pointer">
                        <span>Continue</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </button>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════════
                 STEP 2: TYPE-SPECIFIC UPLOAD & STRICT VALIDATION
                 ═══════════════════════════════════════════════════════════════════ --}}
            <div x-show="currentStep === 'upload'" x-transition class="glass-card p-6 sm:p-8 shadow-xl">

                {{-- Step navigation & selected badge --}}
                <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-200 dark:border-slate-800">
                    <button type="button" @click="backToSelection()"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white inline-flex items-center gap-1.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                        <span>Change Report Type</span>
                    </button>

                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-aviation-50 dark:bg-aviation-950 border border-aviation-200 dark:border-aviation-800 text-aviation-700 dark:text-aviation-300 text-xs font-bold font-mono">
                        <span x-text="'#' + (selectedReportConfig ? selectedReportConfig.number : '')"></span>
                        <span x-text="selectedReportConfig ? selectedReportConfig.name : ''"></span>
                    </div>
                </div>

                {{-- Selected Report Format Guidelines --}}
                <div class="mb-5 p-4 rounded-xl bg-slate-50 dark:bg-navy-950/80 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-aviation-600 dark:text-aviation-400">Required Source Format</div>
                        <div class="text-sm font-black text-slate-900 dark:text-white mt-0.5" x-text="selectedReportConfig ? selectedReportConfig.template_label : ''"></div>
                        <div class="flex items-center gap-3 text-xs text-slate-500 mt-1 font-mono">
                            <span>Accepted: <strong class="text-slate-700 dark:text-slate-300" x-text="selectedReportConfig ? selectedReportConfig.extensions.map(e => '.' + e).join(', ') : ''"></strong></span>
                            <span>•</span>
                            <span>Template: <strong class="text-slate-700 dark:text-slate-300" x-text="selectedReportConfig ? selectedReportConfig.template_filename : ''"></strong></span>
                        </div>
                    </div>

                    {{-- Download Reference Template Button (For DAU reports) --}}
                    <template x-if="selectedReportConfig && !selectedReportConfig.is_pdf">
                        <a :href="'/templates/download/' + selectedReport" download
                           class="shrink-0 px-3.5 py-2 rounded-lg text-xs font-bold bg-white dark:bg-navy-800 border border-slate-200 dark:border-slate-700 text-aviation-600 dark:text-aviation-400 hover:bg-aviation-50 dark:hover:bg-navy-700 transition flex items-center gap-2 shadow-2xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            <span>Download Template</span>
                        </a>
                    </template>
                </div>

                {{-- Drag & Drop Upload Zone --}}
                <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data" id="report-upload-form" @submit.prevent="generateReport()">
                    @csrf
                    <input type="hidden" name="report_type" :value="selectedReport"/>

                    <div class="relative">
                        <label for="source_file"
                               @dragover.prevent="isDragging = true"
                               @dragleave.prevent="isDragging = false"
                               @drop.prevent="handleFileDrop($event)"
                               :class="isDragging ? 'border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/40 ring-4 ring-aviation-500/10' : 'border-slate-300 dark:border-slate-700/80 bg-slate-50/60 dark:bg-navy-950/60 hover:border-aviation-400 hover:bg-aviation-50/20 dark:hover:bg-navy-800/40'"
                               class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed rounded-xl cursor-pointer transition-all duration-200 group">
                            
                            <template x-if="!selectedFileName">
                                <div class="flex flex-col items-center justify-center p-5 text-center">
                                    <div class="w-12 h-12 rounded-xl bg-white dark:bg-navy-800 shadow-sm border border-slate-200 dark:border-slate-700 flex items-center justify-center text-aviation-600 dark:text-aviation-400 group-hover:scale-105 transition duration-150 mb-2.5">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                                        </svg>
                                    </div>
                                    <div class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                        Drag &amp; Drop <span x-text="selectedReportConfig ? selectedReportConfig.template_filename : 'File'"></span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        or <span class="text-aviation-600 dark:text-aviation-400 font-semibold underline underline-offset-2">Browse from your computer</span>
                                    </p>
                                    <div class="mt-2 flex items-center gap-2 text-[10px] text-slate-400 font-mono">
                                        <span class="px-2 py-0.5 rounded bg-slate-200/70 dark:bg-navy-800" x-text="selectedReportConfig ? selectedReportConfig.extensions.map(e => '.' + e.toUpperCase()).join(' / ') : ''"></span>
                                        <span>MAX 50 MB (Multi-Month Supported)</span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="selectedFileName">
                                <div class="flex flex-col items-center justify-center p-5 text-center">
                                    <div :class="validationStatus === 'valid' ? 'bg-emerald-50 text-emerald-600 border-emerald-300 dark:bg-emerald-950/60 dark:border-emerald-700' : (validationStatus === 'invalid' ? 'bg-red-50 text-red-600 border-red-300 dark:bg-red-950/60 dark:border-red-700' : 'bg-aviation-50 text-aviation-600 border-aviation-300 dark:bg-aviation-950/60 dark:border-aviation-700')"
                                         class="w-12 h-12 rounded-xl border flex items-center justify-center mb-2.5">
                                        <template x-if="isValidating">
                                            <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </template>
                                        <template x-if="!isValidating && validationStatus === 'valid'">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </template>
                                        <template x-if="!isValidating && validationStatus === 'invalid'">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </template>
                                    </div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-white max-w-sm truncate" x-text="selectedFileName"></div>
                                    <div class="text-[11px] text-slate-400 mt-0.5 font-mono" x-text="selectedFileSize"></div>
                                </div>
                            </template>

                            <input id="source_file" name="file" type="file" :accept="selectedReportConfig ? selectedReportConfig.extensions.map(e => '.' + e).join(',') : '*'" class="hidden" @change="handleFileSelect($event)"/>
                        </label>
                    </div>

                    {{-- ══ REAL-TIME PREVIEW VALIDATION CARD (MATCHED) ════════════════ --}}
                    <template x-if="validationStatus === 'valid'">
                        <div class="mt-4 p-4 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <div class="font-bold text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5 uppercase tracking-wider text-[11px]">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>DATA VALIDATION — READY TO GENERATE</span>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-bold font-mono text-[10px]">
                                    ✓ MATCHED
                                </span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-1 font-mono text-[11px]">
                                <div class="p-2 rounded-lg bg-white/80 dark:bg-navy-900/80 border border-emerald-100 dark:border-emerald-900/40">
                                    <div class="text-[10px] text-slate-400 font-sans">Report:</div>
                                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate" x-text="selectedReportConfig.name"></div>
                                </div>
                                <div class="p-2 rounded-lg bg-white/80 dark:bg-navy-900/80 border border-emerald-100 dark:border-emerald-900/40">
                                    <div class="text-[10px] text-slate-400 font-sans">Records Detected:</div>
                                    <div class="font-bold text-emerald-600 dark:text-emerald-400" x-text="validationResult.records_count ? (typeof validationResult.records_count === 'number' ? validationResult.records_count + ' records' : validationResult.records_count) : 'Verified'"></div>
                                </div>
                                <div class="p-2 rounded-lg bg-white/80 dark:bg-navy-900/80 border border-emerald-100 dark:border-emerald-900/40 col-span-2 sm:col-span-1">
                                    <div class="text-[10px] text-slate-400 font-sans">Template Check:</div>
                                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate" x-text="validationResult.expectedTemplate"></div>
                                </div>
                            </div>

                            <template x-if="validationResult.detected_columns && validationResult.detected_columns.length">
                                <div class="pt-1">
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">Detected Column Schema:</div>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        <template x-for="col in validationResult.detected_columns" :key="col">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-white/90 dark:bg-navy-900 text-slate-700 dark:text-slate-300 border border-emerald-200 dark:border-emerald-900 text-[10px] font-mono">
                                                <span class="text-emerald-500 font-bold">✓</span> <span x-text="col"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- ══ REAL-TIME PREVIEW VALIDATION CARD (REJECTED) ═══════════════ --}}
                    <template x-if="validationStatus === 'invalid'">
                        <div class="mt-4 p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 space-y-2 text-xs text-red-700 dark:text-red-300">
                            <div class="flex items-center justify-between">
                                <div class="font-bold flex items-center gap-1.5 uppercase tracking-wider text-[11px] text-red-800 dark:text-red-300">
                                    <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span x-text="errorCategoryTitle"></span>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/60 text-red-700 dark:text-red-300 font-bold font-mono text-[10px]" x-text="errorBadge">
                                    REJECTED
                                </span>
                            </div>

                            <div class="space-y-1 font-sans text-xs">
                                <template x-for="(err, idx) in validationErrors" :key="idx">
                                    <div class="flex items-start gap-1.5">
                                        <span class="text-red-500">•</span>
                                        <span x-text="err"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="pt-2 flex items-center justify-between border-t border-red-200 dark:border-red-800/60 text-[11px]">
                                <span>Please verify your file matches the required template or size path.</span>
                                <button type="button" @click="resetFile()" class="font-bold underline text-red-800 dark:text-red-300 hover:text-red-900 cursor-pointer">
                                    Replace File
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- ══ PROCESSING INDICATOR ═══════════════════════════════════════ --}}
                    <div x-show="isProcessing" x-transition class="mt-4 p-4 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-2.5">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin text-aviation-600 dark:text-aviation-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span x-text="processingStageTitle"></span>
                            </span>
                            <span class="font-mono text-aviation-600 dark:text-aviation-400 font-bold" x-text="progressText"></span>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-navy-800 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-aviation-600 h-full rounded-full transition-all duration-300" :style="'width:' + progressPercent + '%'"></div>
                        </div>

                        {{-- Step Tracker adapted to report type --}}
                        <template x-if="selectedReport === 'slot_schedule'">
                            <div class="grid grid-cols-6 gap-1 pt-0.5 text-[9.5px] font-mono text-slate-500 dark:text-slate-400 text-center">
                                <div :class="progressPercent >= 15 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Read</div>
                                <div :class="progressPercent >= 35 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Extract</div>
                                <div :class="progressPercent >= 55 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Match</div>
                                <div :class="progressPercent >= 75 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Timeline</div>
                                <div :class="progressPercent >= 90 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Capacity</div>
                                <div :class="progressPercent >= 98 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Export</div>
                            </div>
                        </template>
                        <template x-if="selectedReport !== 'slot_schedule'">
                            <div class="grid grid-cols-6 gap-1 pt-0.5 text-[9.5px] font-mono text-slate-500 dark:text-slate-400 text-center">
                                <div :class="progressPercent >= 15 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Read</div>
                                <div :class="progressPercent >= 35 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Extract</div>
                                <div :class="progressPercent >= 55 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Validate</div>
                                <div :class="progressPercent >= 75 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Normalize</div>
                                <div :class="progressPercent >= 90 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Aggregate</div>
                                <div :class="progressPercent >= 98 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ''">✓ Dashboard</div>
                            </div>
                        </template>
                    </div>

                    {{-- ══ GENERATE BUTTON (DISABLED UNTIL VALID) ════════════════════ --}}
                    <button type="submit" id="generate-btn" :disabled="validationStatus !== 'valid' || isProcessing"
                            :class="validationStatus !== 'valid' || isProcessing ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'btn-aviation-primary shadow-lg shadow-aviation-600/25'"
                            class="mt-4 w-full py-3 px-5 rounded-xl font-bold text-xs sm:text-sm text-white flex items-center justify-center gap-2 transition duration-200 cursor-pointer">
                        <template x-if="!isProcessing">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                                <span>Generate Report &amp; Analytics</span>
                            </span>
                        </template>
                        <template x-if="isProcessing">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span>Ingesting and Generating Report...</span>
                            </span>
                        </template>
                    </button>
                </form>

            </div>

            {{-- ═══════════════════════════════════════════════════════════════════
                 STEP 1.5: DAU-02 SELECT GENERATION MODE
                 ═══════════════════════════════════════════════════════════════════ --}}
            <div x-show="currentStep === 'dau_mode'" x-transition class="glass-card p-6 sm:p-8 shadow-xl">
                {{-- Step navigation & selected badge --}}
                <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-200 dark:border-slate-800">
                    <button type="button" @click="backToSelection()"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white inline-flex items-center gap-1.5 transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                        <span>Change Report Type</span>
                    </button>

                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-aviation-50 dark:bg-aviation-950 border border-aviation-200 dark:border-aviation-800 text-aviation-700 dark:text-aviation-300 text-xs font-bold font-mono">
                        <span>#3 DAU-02 (Secara Total)</span>
                    </div>
                </div>

                <div class="text-center mb-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-aviation-50 dark:bg-aviation-950/80 border border-aviation-200 dark:border-aviation-800 text-aviation-700 dark:text-aviation-300 text-xs font-semibold mb-2.5">
                        <span class="w-2 h-2 rounded-full bg-aviation-600 dark:bg-aviation-400 animate-pulse"></span>
                        <span>DAU-02 Pipeline Configuration</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                        SELECT GENERATION MODE
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1 max-w-xl mx-auto">
                        Choose whether to generate a standalone DAU-02 operational dashboard or compare historical reporting periods.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                    {{-- Mode 1: Single DAU-02 --}}
                    <div @click="selectDauMode('single')"
                         class="p-5 sm:p-6 rounded-2xl border-2 border-slate-200 dark:border-slate-800 hover:border-aviation-600 dark:hover:border-aviation-500 hover:bg-aviation-50/30 dark:hover:bg-aviation-950/30 transition-all duration-200 cursor-pointer flex flex-col justify-between group shadow-sm">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-navy-800 dark:text-slate-300">
                                    MODE 1
                                </span>
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-navy-800 dark:text-slate-400">
                                    Standard
                                </span>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-aviation-50 dark:bg-navy-800 border border-aviation-200 dark:border-navy-700 flex items-center justify-center text-aviation-600 dark:text-aviation-400 mb-3 group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white group-hover:text-aviation-600 dark:group-hover:text-aviation-400 transition">
                                1. Generate Single DAU-02
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                                Upload 1 DAU-02 Excel file to validate template structure and generate the standalone operational overview and comparative matrix dashboard.
                            </p>
                        </div>
                        <div class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs font-bold text-aviation-600 dark:text-aviation-400">
                            <span>Upload 1 File</span>
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </div>
                    </div>

                    {{-- Mode 2: Compare DAU-02 Historical Reports --}}
                    <div @click="selectDauMode('compare')"
                         class="p-5 sm:p-6 rounded-2xl border-2 border-slate-200 dark:border-slate-800 hover:border-aviation-600 dark:hover:border-aviation-500 hover:bg-aviation-50/30 dark:hover:bg-aviation-950/30 transition-all duration-200 cursor-pointer flex flex-col justify-between group shadow-sm relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-28 h-28 bg-aviation-500/10 rounded-full blur-xl pointer-events-none"></div>
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-aviation-100 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300">
                                    MODE 2
                                </span>
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                    ★ NEW FEATURE
                                </span>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-aviation-600 text-white shadow-md shadow-aviation-600/25 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                </svg>
                            </div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white group-hover:text-aviation-600 dark:group-hover:text-aviation-400 transition">
                                2. Compare DAU-02 Historical Reports
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                                Upload 2 or more DAU-02 Excel files for the same airport covering equivalent durations to generate grouped historical analytics, differences, and growth rates.
                            </p>
                        </div>
                        <div class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs font-bold text-aviation-600 dark:text-aviation-400">
                            <span>Minimum 2 Files Required</span>
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════════
                 STEP 2.5: DAU-02 HISTORICAL COMPARISON MULTI-FILE UPLOAD
                 ═══════════════════════════════════════════════════════════════════ --}}
            <div x-show="currentStep === 'dau_compare_upload'" x-transition class="glass-card p-6 sm:p-8 shadow-xl space-y-6">

                {{-- Navigation Header --}}
                <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                    <button type="button" @click="currentStep = 'dau_mode'"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white inline-flex items-center gap-1.5 transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                        <span>Back to Mode Selection</span>
                    </button>

                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-aviation-50 dark:bg-aviation-950 border border-aviation-200 dark:border-aviation-800 text-aviation-700 dark:text-aviation-300 text-xs font-bold font-mono">
                        <span>#3 DAU-02 Historical Comparison</span>
                    </div>
                </div>

                {{-- Header title & explanation --}}
                <div>
                    <h2 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                        COMPARISON REPORTS
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Upload minimum 2 DAU-02 reports for the same airport covering equivalent durations (e.g. 6 months vs 6 months).
                    </p>
                </div>

                {{-- Comparison Rules Checklist Callout --}}
                <div class="p-4 rounded-xl bg-aviation-50/60 dark:bg-aviation-950/40 border border-aviation-200 dark:border-aviation-800/60 text-xs space-y-2">
                    <div class="font-bold text-aviation-800 dark:text-aviation-300 text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Critical Comparison Validation Criteria</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] font-mono">
                        <div class="p-2 rounded-lg bg-white/80 dark:bg-navy-900/80 border border-aviation-100 dark:border-aviation-900/40">
                            <span class="text-slate-400 block text-[10px] font-sans">Report Type:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">DAU-02 Only</span>
                        </div>
                        <div class="p-2 rounded-lg bg-white/80 dark:bg-navy-900/80 border border-aviation-100 dark:border-aviation-900/40">
                            <span class="text-slate-400 block text-[10px] font-sans">Airport Match:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">Same Airport</span>
                        </div>
                        <div class="p-2 rounded-lg bg-white/80 dark:bg-navy-900/80 border border-aviation-100 dark:border-aviation-900/40">
                            <span class="text-slate-400 block text-[10px] font-sans">Duration:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">Equal Period Length</span>
                        </div>
                        <div class="p-2 rounded-lg bg-white/80 dark:bg-navy-900/80 border border-aviation-100 dark:border-aviation-900/40">
                            <span class="text-slate-400 block text-[10px] font-sans">Dates:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">Different Ranges</span>
                        </div>
                    </div>
                </div>

                {{-- Dynamic Report Cards Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <template x-for="(card, idx) in compareReports" :key="card.id">
                        <div class="rounded-xl border p-4 transition-all duration-200 relative flex flex-col justify-between"
                             :class="card.status === 'valid'
                                ? 'bg-emerald-50/40 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-800'
                                : (card.status === 'invalid'
                                    ? 'bg-red-50/40 dark:bg-red-950/20 border-red-300 dark:border-red-800'
                                    : 'bg-white dark:bg-navy-900/70 border-slate-200 dark:border-slate-800')">

                            {{-- Card Top Header --}}
                            <div class="flex items-center justify-between pb-2 border-b border-slate-200/60 dark:border-slate-800/60 mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300 font-mono font-bold text-xs flex items-center justify-center"
                                          x-text="idx + 1"></span>
                                    <span class="font-bold text-xs text-slate-900 dark:text-white" x-text="'Report #' + (idx + 1)"></span>
                                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-navy-800 text-slate-500">DAU-02</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <template x-if="card.status === 'valid'">
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold font-mono">
                                            ✓ VALID
                                        </span>
                                    </template>
                                    <template x-if="card.status === 'invalid'">
                                        <span class="px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/60 text-red-700 dark:text-red-300 text-[10px] font-bold font-mono">
                                            ✕ REJECTED
                                        </span>
                                    </template>

                                    {{-- Remove Button (Available if more than 2 cards exist or card is not empty) --}}
                                    <template x-if="compareReports.length > 2 || card.status !== 'idle'">
                                        <button type="button" @click="removeCompareReportCard(idx)"
                                                class="text-slate-400 hover:text-red-600 dark:hover:text-red-400 text-xs font-bold px-1.5 py-0.5 rounded hover:bg-red-50 dark:hover:bg-navy-800 transition cursor-pointer"
                                                title="Remove this report card">
                                            Remove
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Card Body: Idle / Drop Zone --}}
                            <template x-if="card.status === 'idle'">
                                <div>
                                    <label :for="'compare_file_' + card.id"
                                           @dragover.prevent="card.isDragging = true"
                                           @dragleave.prevent="card.isDragging = false"
                                           @drop.prevent="handleCompareFileDrop($event, idx)"
                                           :class="card.isDragging ? 'border-aviation-500 bg-aviation-50/50 dark:bg-aviation-950/40' : 'border-slate-300 dark:border-slate-700 hover:border-aviation-400 hover:bg-slate-50/60 dark:hover:bg-navy-800/40'"
                                           class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed rounded-xl cursor-pointer transition p-4 text-center group">
                                        <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-navy-800 flex items-center justify-center text-aviation-600 dark:text-aviation-400 group-hover:scale-105 transition mb-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                                            </svg>
                                        </div>
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                            Drag &amp; Drop DAU-02 Excel
                                        </div>
                                        <p class="text-[11px] text-slate-400 mt-0.5 font-mono">
                                            .xls, .xlsx (OASYS DAU-02)
                                        </p>
                                        <input :id="'compare_file_' + card.id" type="file" accept=".xls,.xlsx" class="hidden" @change="handleCompareFileSelect($event, idx)"/>
                                    </label>
                                </div>
                            </template>

                            {{-- Card Body: Uploading Spinner --}}
                            <template x-if="card.status === 'uploading'">
                                <div class="flex flex-col items-center justify-center h-36 p-4 text-center space-y-2">
                                    <svg class="w-7 h-7 animate-spin text-aviation-600 dark:text-aviation-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Validating DAU-02 &amp; Extracting Metadata...</div>
                                    <div class="text-[10px] text-slate-400 font-mono truncate max-w-xs" x-text="card.fileName"></div>
                                </div>
                            </template>

                            {{-- Card Body: Valid Card Details --}}
                            <template x-if="card.status === 'valid' && card.data">
                                <div class="space-y-2.5 text-xs py-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <span class="text-[10px] font-mono text-slate-400 block">Airport:</span>
                                            <span class="font-black text-slate-900 dark:text-white" x-text="card.data.airport"></span>
                                        </div>
                                        <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-white dark:bg-navy-900 text-aviation-700 dark:text-aviation-300 border border-emerald-200 dark:border-emerald-800" x-text="card.data.airport_code"></span>
                                    </div>

                                    <div class="p-2.5 rounded-lg bg-white/90 dark:bg-navy-900/90 border border-emerald-100 dark:border-emerald-900/40 space-y-1 font-mono text-[11px]">
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-400 font-sans text-[10px]">Period:</span>
                                            <span class="font-bold text-slate-800 dark:text-slate-200" x-text="card.data.display_date_range"></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-400 font-sans text-[10px]">Duration:</span>
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400" x-text="card.data.data_days + ' data days'"></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-400 font-sans text-[10px]">Records:</span>
                                            <span class="text-slate-600 dark:text-slate-300" x-text="card.data.records_count + ' categories'"></span>
                                        </div>
                                    </div>

                                    <div class="pt-1 flex items-center justify-between">
                                        <span class="text-[10px] text-slate-400 font-mono truncate max-w-[180px]" x-text="card.fileName"></span>
                                        <button type="button" @click="resetCompareReportCard(idx)" class="text-[11px] font-bold underline text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white cursor-pointer">
                                            Replace File
                                        </button>
                                    </div>
                                </div>
                            </template>

                            {{-- Card Body: Invalid Card --}}
                            <template x-if="card.status === 'invalid'">
                                <div class="space-y-2 text-xs py-1 text-red-700 dark:text-red-300">
                                    <div class="font-bold text-[11px] flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span>Invalid Report File</span>
                                    </div>
                                    <div class="text-[11px] bg-white/80 dark:bg-navy-900/80 p-2 rounded-lg border border-red-200 dark:border-red-900/40 font-mono" x-text="card.error"></div>
                                    <div class="flex justify-end pt-1">
                                        <button type="button" @click="resetCompareReportCard(idx)" class="text-xs font-bold underline text-red-800 dark:text-red-300 hover:text-red-900 cursor-pointer">
                                            Try Another File
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Add Another Report Button --}}
                <div class="flex items-center justify-between pt-1">
                    <button type="button" @click="addCompareReportCard()"
                            class="px-4 py-2 rounded-xl text-xs font-bold border border-dashed border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-aviation-500 hover:text-aviation-600 dark:hover:text-aviation-400 hover:bg-slate-50 dark:hover:bg-navy-900 transition flex items-center gap-2 cursor-pointer shadow-2xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        <span>+ Add Another Report</span>
                    </button>
                    <span class="text-[11px] text-slate-400 font-mono">2 files minimum (3+ supported)</span>
                </div>

                {{-- ══ COMPARISON VALIDATION SUMMARY BOX ═══════════════════════════ --}}
                <div class="p-4 rounded-xl border space-y-3"
                     :class="comparisonValidation.valid
                        ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800'
                        : (comparisonValidation.errors && comparisonValidation.errors.length
                            ? 'bg-red-50/50 dark:bg-red-950/30 border-red-200 dark:border-red-800'
                            : 'bg-slate-50 dark:bg-navy-950 border-slate-200 dark:border-slate-800')">

                    <div class="flex items-center justify-between text-xs font-bold">
                        <span class="uppercase tracking-wider text-[11px] flex items-center gap-2"
                              :class="comparisonValidation.valid ? 'text-emerald-800 dark:text-emerald-300' : 'text-slate-700 dark:text-slate-300'">
                            <span class="w-2 h-2 rounded-full" :class="comparisonValidation.valid ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                            <span>COMPARISON VALIDATION CHECKLIST</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
                              :class="comparisonValidation.valid ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-slate-200 dark:bg-navy-800 text-slate-600 dark:text-slate-400'">
                            <span x-text="comparisonValidation.valid ? '✓ READY TO COMPARE' : 'PENDING REQUIREMENTS'"></span>
                        </span>
                    </div>

                    {{-- 5 Validation Pillars --}}
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-[11px] font-mono">
                        <div class="p-2 rounded-lg bg-white/90 dark:bg-navy-900/90 border border-slate-200 dark:border-slate-800 flex items-center gap-1.5">
                            <span :class="comparisonValidation.checks && comparisonValidation.checks.same_dau_type ? 'text-emerald-600 font-black' : 'text-slate-400'">
                                <span x-text="comparisonValidation.checks && comparisonValidation.checks.same_dau_type ? '✓' : '•'"></span>
                            </span>
                            <span class="truncate">Same DAU Type</span>
                        </div>
                        <div class="p-2 rounded-lg bg-white/90 dark:bg-navy-900/90 border border-slate-200 dark:border-slate-800 flex items-center gap-1.5">
                            <span :class="comparisonValidation.checks && comparisonValidation.checks.same_airport ? 'text-emerald-600 font-black' : 'text-slate-400'">
                                <span x-text="comparisonValidation.checks && comparisonValidation.checks.same_airport ? '✓' : '•'"></span>
                            </span>
                            <span class="truncate">Same Airport</span>
                        </div>
                        <div class="p-2 rounded-lg bg-white/90 dark:bg-navy-900/90 border border-slate-200 dark:border-slate-800 flex items-center gap-1.5">
                            <span :class="comparisonValidation.checks && comparisonValidation.checks.different_period ? 'text-emerald-600 font-black' : 'text-slate-400'">
                                <span x-text="comparisonValidation.checks && comparisonValidation.checks.different_period ? '✓' : '•'"></span>
                            </span>
                            <span class="truncate">Different Period</span>
                        </div>
                        <div class="p-2 rounded-lg bg-white/90 dark:bg-navy-900/90 border border-slate-200 dark:border-slate-800 flex items-center gap-1.5">
                            <span :class="comparisonValidation.checks && comparisonValidation.checks.same_period_length ? 'text-emerald-600 font-black' : 'text-slate-400'">
                                <span x-text="comparisonValidation.checks && comparisonValidation.checks.same_period_length ? '✓' : '•'"></span>
                            </span>
                            <span class="truncate">Same Duration</span>
                        </div>
                        <div class="p-2 rounded-lg bg-white/90 dark:bg-navy-900/90 border border-slate-200 dark:border-slate-800 flex items-center gap-1.5">
                            <span :class="comparisonValidation.checks && comparisonValidation.checks.compatible_template ? 'text-emerald-600 font-black' : 'text-slate-400'">
                                <span x-text="comparisonValidation.checks && comparisonValidation.checks.compatible_template ? '✓' : '•'"></span>
                            </span>
                            <span class="truncate">Template Match</span>
                        </div>
                    </div>

                    {{-- Warning message for overlapping periods --}}
                    <template x-if="comparisonValidation.warnings && comparisonValidation.warnings.length">
                        <div class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-300 space-y-1">
                            <template x-for="(w, wIdx) in comparisonValidation.warnings" :key="wIdx">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-amber-600 font-bold">⚠</span>
                                    <span x-text="w"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Errors list --}}
                    <template x-if="comparisonValidation.errors && comparisonValidation.errors.length">
                        <div class="p-2.5 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 text-xs text-red-700 dark:text-red-300 space-y-1">
                            <template x-for="(err, eIdx) in comparisonValidation.errors" :key="eIdx">
                                <div class="flex items-start gap-1.5">
                                    <span class="text-red-500 font-bold">✕</span>
                                    <span x-text="err"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- ══ GENERATE BUTTON ═════════════════════════════════════════════ --}}
                <button type="button" @click="generateComparison()"
                        :disabled="!comparisonValidation.valid || isGeneratingComparison"
                        :class="!comparisonValidation.valid || isGeneratingComparison
                            ? 'opacity-50 cursor-not-allowed bg-slate-400 dark:bg-slate-700'
                            : 'btn-aviation-primary shadow-lg shadow-aviation-600/25 cursor-pointer'"
                        class="w-full py-3 px-5 rounded-xl font-bold text-xs sm:text-sm text-white flex items-center justify-center gap-2 transition duration-200">
                    <template x-if="!isGeneratingComparison">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                            <span>Generate Historical Comparison</span>
                        </span>
                    </template>
                    <template x-if="isGeneratingComparison">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span>Loading Comparison Dashboard...</span>
                        </span>
                    </template>
                </button>

            </div>

        </div>
    </main>

    {{-- ══ FOOTER WORKFLOW STEPS ═══════════════════════════════════════════════ --}}
    <footer class="w-full border-t border-slate-200/80 dark:border-slate-800/80 py-3.5 px-4 text-center">
        <div class="max-w-4xl mx-auto flex flex-wrap items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-mono">
            <span class="font-bold font-sans text-slate-700 dark:text-slate-300">Pipeline:</span>
            <span>1. Select Type</span> &rarr;
            <span>2. Upload File</span> &rarr;
            <span>3. Validate Template</span> &rarr;
            <span>4. Ingest &amp; Normalize</span> &rarr;
            <span>5. Dashboard</span> &rarr;
            <span>6. Export</span>
        </div>
    </footer>

</div>
@endsection

@push('scripts')
<script>
function unifiedReportPortal() {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
        currentStep: 'select', // 'select' | 'dau_mode' | 'upload' | 'dau_compare_upload'
        selectedReport: 'slot_schedule',
        reportRegistry: @json($allReportTypes),

        // DAU-02 Generation Mode: 'single' | 'compare'
        dauMode: 'single',
        compareReports: [
            { id: 1, status: 'idle', isDragging: false, file: null, fileName: '', error: null, data: null },
            { id: 2, status: 'idle', isDragging: false, file: null, fileName: '', error: null, data: null }
        ],
        comparisonValidation: {
            valid: false,
            checks: {
                same_dau_type: false,
                same_airport: false,
                different_period: false,
                same_period_length: false,
                compatible_template: false,
            },
            errors: [],
            warnings: []
        },
        isGeneratingComparison: false,

        isDragging: false,
        selectedFile: null,
        selectedFileName: '',
        selectedFileSize: '',
        
        isValidating: false,
        validationStatus: 'idle', // 'idle' | 'valid' | 'invalid' | 'validating'
        validationResult: {},
        validationErrors: [],
        errorCategoryTitle: 'INVALID FILE TEMPLATE',
        errorBadge: 'REJECTED',

        isProcessing: false,
        progressPercent: 0,
        progressText: '',
        processingStageTitle: 'Ingestion Pipeline Active',

        get selectedReportConfig() {
            return this.reportRegistry[this.selectedReport] || null;
        },

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

        proceedToUpload() {
            if (!this.selectedReport) return;
            if (this.selectedReport === 'DAU2') {
                this.currentStep = 'dau_mode';
                return;
            }
            this.currentStep = 'upload';
            this.resetFile();
        },

        selectDauMode(mode) {
            this.dauMode = mode;
            if (mode === 'single') {
                this.currentStep = 'upload';
                this.resetFile();
            } else if (mode === 'compare') {
                this.currentStep = 'dau_compare_upload';
                if (!this.compareReports || this.compareReports.length < 2) {
                    this.initCompareReports();
                } else {
                    this.evaluateComparison();
                }
            }
        },

        initCompareReports() {
            this.compareReports = [
                { id: 1, status: 'idle', isDragging: false, file: null, fileName: '', error: null, data: null },
                { id: 2, status: 'idle', isDragging: false, file: null, fileName: '', error: null, data: null }
            ];
            this.evaluateComparison();
        },

        addCompareReportCard() {
            this.compareReports.push({
                id: Date.now() + Math.random(),
                status: 'idle',
                isDragging: false,
                file: null,
                fileName: '',
                error: null,
                data: null
            });
            this.evaluateComparison();
        },

        removeCompareReportCard(idx) {
            this.compareReports.splice(idx, 1);
            if (this.compareReports.length < 2) {
                this.addCompareReportCard();
            }
            this.evaluateComparison();
        },

        resetCompareReportCard(idx) {
            if (this.compareReports[idx]) {
                this.compareReports[idx].status = 'idle';
                this.compareReports[idx].file = null;
                this.compareReports[idx].fileName = '';
                this.compareReports[idx].error = null;
                this.compareReports[idx].data = null;
            }
            this.evaluateComparison();
        },

        handleCompareFileSelect(ev, idx) {
            if (ev.target.files && ev.target.files.length) {
                this.uploadCompareReportFile(ev.target.files[0], idx);
            }
        },

        handleCompareFileDrop(ev, idx) {
            if (this.compareReports[idx]) {
                this.compareReports[idx].isDragging = false;
            }
            if (ev.dataTransfer.files && ev.dataTransfer.files.length) {
                this.uploadCompareReportFile(ev.dataTransfer.files[0], idx);
            }
        },

        async uploadCompareReportFile(file, idx) {
            if (!this.compareReports[idx]) return;
            const card = this.compareReports[idx];
            card.status = 'uploading';
            card.fileName = file.name;
            card.error = null;

            const csrfToken = document.querySelector('input[name="_token"]')?.value ||
                              document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const formData = new FormData();
            formData.append('file', file);
            formData.append('report_type', 'DAU2');
            formData.append('_token', csrfToken);

            try {
                const res = await fetch('{{ route("upload.compare-file") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                });

                let data = null;
                try {
                    data = await res.json();
                } catch (jsonErr) {
                    data = null;
                }

                if (res.ok && data && data.success) {
                    card.status = 'valid';
                    card.data = data;
                    card.error = null;
                } else {
                    card.status = 'invalid';
                    card.data = null;
                    const errs = this.formatErrors(data);
                    card.error = errs.join('; ');
                }
            } catch (err) {
                card.status = 'invalid';
                card.data = null;
                card.error = 'Network error during upload. Please try again.';
            }

            this.evaluateComparison();
        },

        async evaluateComparison() {
            const validCards = this.compareReports.filter(c => c.status === 'valid' && c.data && c.data.upload_id);

            if (validCards.length < 2) {
                this.comparisonValidation = {
                    valid: false,
                    checks: {
                        same_dau_type: validCards.length > 0,
                        same_airport: false,
                        different_period: false,
                        same_period_length: false,
                        compatible_template: validCards.length > 0,
                    },
                    errors: validCards.length === 1 ? ['At least 2 DAU-02 reports must be uploaded to evaluate comparison.'] : [],
                    warnings: []
                };
                return;
            }

            const csrfToken = document.querySelector('input[name="_token"]')?.value ||
                              document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const res = await fetch('{{ route("dau.compare.validate") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        report_ids: validCards.map(c => c.data.upload_id),
                        _token: csrfToken
                    })
                });

                const data = await res.json();
                this.comparisonValidation = data;
            } catch (err) {
                this.comparisonValidation = {
                    valid: false,
                    checks: {},
                    errors: ['Failed to validate comparison requirements.'],
                    warnings: []
                };
            }
        },

        generateComparison() {
            if (!this.comparisonValidation.valid || this.isGeneratingComparison) return;
            this.isGeneratingComparison = true;
            const validCards = this.compareReports.filter(c => c.status === 'valid' && c.data && c.data.upload_id);
            const reportIds = validCards.map(c => c.data.upload_id).join(',');
            window.location.href = `{{ route("dau.compare") }}?reports=${reportIds}`;
        },

        backToSelection() {
            this.currentStep = 'select';
            this.resetFile();
        },

        resetFile() {
            this.selectedFile = null;
            this.selectedFileName = '';
            this.selectedFileSize = '';
            this.validationStatus = 'idle';
            this.validationResult = {};
            this.validationErrors = [];
            this.errorCategoryTitle = 'INVALID FILE TEMPLATE';
            this.errorBadge = 'REJECTED';
            const input = document.getElementById('source_file');
            if (input) input.value = '';
        },

        formatErrors(data) {
            if (!data) return ['An unknown error occurred during validation.'];
            if (typeof data === 'string') return [data];
            
            const errors = [];
            if (data.errors) {
                if (Array.isArray(data.errors)) {
                    data.errors.forEach(e => {
                        if (typeof e === 'object' && e !== null) {
                            errors.push(e.message || JSON.stringify(e));
                        } else {
                            errors.push(String(e));
                        }
                    });
                } else if (typeof data.errors === 'object') {
                    Object.entries(data.errors).forEach(([field, val]) => {
                        if (Array.isArray(val)) {
                            val.forEach(item => errors.push(String(item)));
                        } else if (typeof val === 'object' && val !== null) {
                            errors.push(val.message || JSON.stringify(val));
                        } else {
                            errors.push(String(val));
                        }
                    });
                }
            }
            if (errors.length > 0) return errors;

            if (data.error && typeof data.error === 'string') {
                return [data.error];
            }
            if (data.message && typeof data.message === 'string') {
                return [data.message];
            }
            return ['Validation failed. Please verify the template structure.'];
        },

        handleFileSelect(ev) {
            if (ev.target.files && ev.target.files.length) {
                this.stageFileForValidation(ev.target.files[0]);
            }
        },

        handleFileDrop(ev) {
            this.isDragging = false;
            if (ev.dataTransfer.files && ev.dataTransfer.files.length) {
                const file = ev.dataTransfer.files[0];
                const input = document.getElementById('source_file');
                input.files = ev.dataTransfer.files;
                this.stageFileForValidation(file);
            }
        },

        async stageFileForValidation(file) {
            this.selectedFile = file;
            this.selectedFileName = file.name;
            this.selectedFileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            this.isValidating = true;
            this.validationStatus = 'validating';
            this.validationErrors = [];
            this.errorCategoryTitle = 'INVALID FILE TEMPLATE';
            this.errorBadge = 'REJECTED';

            // Absolute maximum threshold
            if (file.size > 50 * 1024 * 1024) {
                this.isValidating = false;
                this.validationStatus = 'invalid';
                this.errorCategoryTitle = 'FILE TOO LARGE FOR CURRENT UPLOAD PATH';
                this.errorBadge = 'OVERSIZED';
                this.validationErrors = ['The selected file (' + (file.size / 1048576).toFixed(1) + ' MB) exceeds the maximum supported limit (50 MB).'];
                return;
            }

            const csrfToken = document.querySelector('input[name="_token"]')?.value ||
                              document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const formData = new FormData();
            formData.append('report_type', this.selectedReport);
            formData.append('_token', csrfToken);

            // Probe mode for large files: slice first 256 KB for instant structural inspection
            const isLargeFile = file.size > 2 * 1024 * 1024 && this.selectedReport !== 'slot_schedule';
            if (isLargeFile) {
                const probeSlice = file.slice(0, 262144);
                formData.append('file', probeSlice, file.name);
                formData.append('is_probe', '1');
            } else {
                formData.append('file', file);
            }

            try {
                const res = await fetch('{{ route("upload.validate-template") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                });

                let data = null;
                try {
                    data = await res.json();
                } catch (jsonErr) {
                    data = null;
                }

                this.isValidating = false;

                if (res.status === 413) {
                    this.validationStatus = 'invalid';
                    this.errorCategoryTitle = 'FILE TOO LARGE FOR CURRENT UPLOAD PATH';
                    this.errorBadge = '413 OVERSIZED';
                    this.validationErrors = ['The serverless upload path cannot accept a single payload larger than 4.5 MB. Multi-month datasets will be processed via chunked transfer upon generation.'];
                    return;
                }

                if (res.ok && data && data.valid) {
                    this.validationStatus = 'valid';
                    this.validationResult = data;
                } else {
                    this.validationStatus = 'invalid';
                    this.errorCategoryTitle = (data && data.category_title) 
                        ? data.category_title 
                        : ((data && data.category === 'FILE_TOO_LARGE') ? 'FILE TOO LARGE FOR CURRENT UPLOAD PATH' : 'INVALID FILE TEMPLATE');
                    this.errorBadge = (data && data.category === 'FILE_TOO_LARGE') ? 'OVERSIZED' : 'REJECTED';
                    this.validationErrors = this.formatErrors(data);
                    this.validationResult = data || {};
                }
            } catch (err) {
                this.isValidating = false;
                this.validationStatus = 'invalid';
                this.errorCategoryTitle = 'UPLOAD FAILED';
                this.errorBadge = 'ERROR';
                this.validationErrors = ['Network or server error validating template. Please check your connection and try again.'];
            }
        },

        async generateReport() {
            if (this.validationStatus !== 'valid' || !this.selectedFile || this.isProcessing) {
                return;
            }

            this.isProcessing = true;
            this.progressPercent = 10;
            this.progressText = '1/5 Preparing source document...';

            const csrfToken = document.querySelector('input[name="_token"]')?.value ||
                              document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                // ── PIPELINE A: CHUNKED UPLOAD FOR LARGE MULTI-MONTH FILES (> 3.5 MB) ──
                if (this.selectedFile.size > 3.5 * 1024 * 1024) {
                    const chunkSize = 2.5 * 1024 * 1024; // 2.5 MB chunks (safe for Vercel 4.5 MB payload limit)
                    const totalChunks = Math.ceil(this.selectedFile.size / chunkSize);
                    const uploadToken = 'upl_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);

                    for (let chunkIdx = 0; chunkIdx < totalChunks; chunkIdx++) {
                        const start = chunkIdx * chunkSize;
                        const end = Math.min(this.selectedFile.size, start + chunkSize);
                        const chunkBlob = this.selectedFile.slice(start, end);

                        this.progressPercent = Math.round(15 + ((chunkIdx) / totalChunks) * 55);
                        this.progressText = `Uploading chunk ${chunkIdx + 1} of ${totalChunks} (${(this.selectedFile.size / 1048576).toFixed(1)} MB)...`;

                        const chunkForm = new FormData();
                        chunkForm.append('report_type', this.selectedReport);
                        chunkForm.append('upload_token', uploadToken);
                        chunkForm.append('chunk_index', chunkIdx);
                        chunkForm.append('total_chunks', totalChunks);
                        chunkForm.append('filename', this.selectedFile.name);
                        chunkForm.append('chunk', chunkBlob, this.selectedFile.name);
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

                        let chunkData = null;
                        try {
                            chunkData = await chunkRes.json();
                        } catch (e) {
                            throw new Error(`Server returned non-JSON response during chunk ${chunkIdx + 1} upload.`);
                        }

                        if (!chunkRes.ok || !chunkData.success) {
                            const errMsgs = this.formatErrors(chunkData);
                            throw new Error(errMsgs.join('; '));
                        }

                        if (chunkData.completed) {
                            this.progressPercent = 100;
                            this.progressText = 'Complete! Loading Dashboard...';
                            setTimeout(() => {
                                window.location.href = chunkData.redirect_url;
                            }, 250);
                            return;
                        }
                    }
                    return;
                }

                // ── PIPELINE B: STANDARD DIRECT UPLOAD (<= 3.5 MB) ─────────────────────
                this.progressPercent = 25;
                this.progressText = '1/5 Staging source file...';

                const formData = new FormData();
                formData.append('report_type', this.selectedReport);
                if (this.selectedReport === 'slot_schedule') {
                    formData.append('schedule_pdf', this.selectedFile);
                } else {
                    formData.append('dau_file', this.selectedFile);
                    formData.append('file', this.selectedFile);
                }
                formData.append('_token', csrfToken);

                const uploadRes = await fetch('{{ route("upload.store") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData
                });

                let uploadData = null;
                try {
                    uploadData = await uploadRes.json();
                } catch (e) {
                    throw new Error('Server returned invalid response during upload.');
                }

                if (!uploadRes.ok || !uploadData.success) {
                    const errMsgs = this.formatErrors(uploadData);
                    throw new Error(errMsgs.join('; '));
                }

                const uploadId = uploadData.upload_id;
                const processUrl = uploadData.process_url || `/upload/${uploadId}/process`;

                // If already completed immediately during store
                if (uploadData.status === 'completed') {
                    this.progressPercent = 100;
                    this.progressText = 'Report ready! Redirecting...';
                    setTimeout(() => {
                        window.location.href = uploadData.redirect_url;
                    }, 250);
                    return;
                }

                // Trigger Processing Stage if staged as pending
                this.progressPercent = 60;
                this.progressText = this.selectedReport === 'slot_schedule'
                    ? '2/5 Extracting & Validating Flights...'
                    : '2/5 Extracting & Normalizing Template Rows...';

                const procRes = await fetch(processUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ _token: csrfToken })
                });

                let procData = null;
                try {
                    procData = await procRes.json();
                } catch (e) {
                    throw new Error('Processing failed with an unexpected server response.');
                }

                if (!procRes.ok || !procData.success) {
                    const errMsgs = this.formatErrors(procData);
                    throw new Error(errMsgs.join('; '));
                }

                this.progressPercent = 100;
                this.progressText = 'Complete! Loading Dashboard...';

                setTimeout(() => {
                    window.location.href = procData.redirect_url || (this.selectedReport === 'slot_schedule'
                        ? `/schedule/${uploadId}/dashboard`
                        : `/dau/${uploadId}/dashboard`);
                }, 300);

            } catch (err) {
                this.isProcessing = false;
                this.validationStatus = 'invalid';
                this.errorCategoryTitle = 'PROCESSING FAILED';
                this.errorBadge = 'FAILED';
                this.validationErrors = [err.message || 'Processing failed. Please check the file and try again.'];
            }
        }
    };
}
</script>
@endpush
