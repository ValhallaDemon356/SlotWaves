{{-- ══ STAGE A: PRE-SCAN & FILE STAGING CARD ════════════════════════════════ --}}
<div x-show="showStagingCard || currentStage === 'staging'"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-2"
     class="bg-white rounded-2xl border-2 border-blue-200/90 p-5 sm:p-6 shadow-sm space-y-5"
     id="fdr-staging-card">

    {{-- Staging Card Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-sm shadow-blue-500/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-blue-100 text-blue-800 border border-blue-200">Stage A: Pre-Scan &amp; Staging</span>
                    <span class="text-xs font-bold text-slate-400">InJourney Airports OASYS</span>
                </div>
                <h2 class="text-base font-black text-slate-900 tracking-tight">OASYS FDR Pre-Scan &amp; Multi-File Verification</h2>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <template x-if="stagedFiles.length > 0">
                <button type="button" @click="resetStagedFiles()"
                        class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Clear Files</span>
                </button>
            </template>
            <template x-if="currentStage === 'dashboard'">
                <button type="button" @click="showStagingCard = false"
                        class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition cursor-pointer">
                    Close Drawer &times;
                </button>
            </template>
        </div>
    </div>

    {{-- Drag & Drop Upload Zone --}}
    <div class="relative border-2 border-dashed border-blue-300 hover:border-blue-500 rounded-2xl bg-blue-50/30 hover:bg-blue-50/60 p-6 sm:p-8 text-center transition cursor-pointer group"
         @dragover.prevent="isDragging = true"
         @dragleave.prevent="isDragging = false"
         @drop.prevent="isDragging = false; handleFileDrop($event)"
         @click="$refs.fdrFileInput.click()"
         :class="isDragging ? 'border-blue-600 bg-blue-100/40 ring-4 ring-blue-500/10' : ''">

        <input type="file" x-ref="fdrFileInput" class="hidden" multiple accept=".xls,.xlsx,.zip,application/vnd.ms-excel,application/zip" @change="handleFileInput($event)">

        <div class="flex flex-col items-center justify-center space-y-2 pointer-events-none">
            <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center group-hover:scale-105 transition shadow-2xs">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-black text-slate-800">
                    Drop OASYS FDR <span class="text-blue-600">.xls</span> HTML exports or a <span class="text-purple-600">.zip</span> archive here
                </p>
                <p class="text-xs text-slate-500 mt-0.5">
                    Supports multiple files (e.g. <span class="font-mono font-semibold text-slate-600">CGK 8B FDR.xls, CGK GA FDR.xls, CGK QG FDR.xls</span>) with automatic row merging
                </p>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-2 pt-1 text-[10px] font-bold text-slate-400">
                <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200">7 FDR Templates</span>
                <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200">ZIP Auto-Extract</span>
                <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200">Instant Pre-Scan</span>
                <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200">Ex Halim (CGK) Support</span>
            </div>
        </div>

        {{-- Scanning Spinner Overlay --}}
        <div x-show="isScanning" class="absolute inset-0 bg-white/80 backdrop-blur-xs rounded-2xl flex flex-col items-center justify-center gap-2">
            <svg class="animate-spin h-7 w-7 text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span class="text-xs font-bold text-slate-700">Pre-scanning OASYS HTML content &amp; extracting metadata...</span>
        </div>
    </div>

    {{-- Error Alert --}}
    <div x-show="scanError" x-cloak class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span x-text="scanError"></span>
        </div>
        <button type="button" @click="scanError = null" class="text-rose-500 hover:text-rose-800 font-bold">&times;</button>
    </div>

    {{-- ══ PRE-SCAN & DATA VERIFICATION RESULTS CARD ════════════════════════ --}}
    <template x-if="preScanData !== null">
        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-4">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200/70 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-700">Detected Report Template:</span>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-black uppercase tracking-wider shadow-2xs"
                          :class="getTemplateBadgeClass(isExHalim ? 'EX_HALIM' : preScanData.reportType)"
                          x-text="getTemplateBadgeText(isExHalim ? 'EX_HALIM' : preScanData.reportType)">
                    </span>
                </div>

                {{-- Special Toggle for Normal vs Ex Halim (CGK) --}}
                <div x-show="preScanData.reportType === 'NORMAL' || preScanData.reportType === 'EX_HALIM'" class="flex items-center gap-2 bg-amber-50/80 border border-amber-200 px-3 py-1 rounded-xl">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-amber-900 select-none">
                        <input type="checkbox" x-model="isExHalim" class="rounded text-amber-600 focus:ring-amber-500 w-4 h-4 cursor-pointer">
                        <span>Mark as Ex Halim (CGK) Report</span>
                    </label>
                    <span class="text-[10px] text-amber-600 font-medium hidden md:inline">• Identical 33-col OASYS schema</span>
                </div>
            </div>

            {{-- Extracted Metadata Pills Grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Airport / Branch</div>
                    <div class="text-sm font-black text-slate-800 font-mono mt-0.5" x-text="preScanData.metadata.branchCode || 'CGK'"></div>
                    <div class="text-[9.5px] text-slate-500" x-text="resolveAirportName(preScanData.metadata.branchCode || 'CGK')"></div>
                </div>

                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Date Period</div>
                    <div class="text-xs font-black text-slate-800 font-mono mt-0.5 truncate" x-text="preScanData.metadata.period || 'Period from file'" :title="preScanData.metadata.period"></div>
                    <div class="text-[9.5px] text-slate-500">transactions_dateFDR</div>
                </div>

                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Operator Scope</div>
                    <div class="text-sm font-black text-slate-800 font-mono mt-0.5" x-text="preScanData.metadata.operator || 'ALL'"></div>
                    <div class="text-[9.5px] text-slate-500">OPERATOR tag</div>
                </div>

                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Realization Mode</div>
                    <div class="text-sm font-black mt-0.5" :class="preScanData.metadata.realization === 'NO' ? 'text-amber-600' : 'text-emerald-600'" x-text="'REAL = ' + (preScanData.metadata.realization || 'YES')"></div>
                    <div class="text-[9.5px] text-slate-500" x-text="preScanData.metadata.realization === 'NO' ? 'Contains Hold/Cancel' : 'Operational Realized'"></div>
                </div>

                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Detected Rows</div>
                    <div class="text-sm font-black text-blue-600 font-mono mt-0.5" x-text="preScanData.metadata.rowCount.toLocaleString() + ' Rows'"></div>
                    <div class="text-[9.5px] text-slate-500" x-text="preScanData.metadata.columnCount + ' Columns mapped'"></div>
                </div>
            </div>

            {{-- Multi-file staging summary list --}}
            <div x-show="stagedFiles.length > 0" class="space-y-1.5 pt-1">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                    <span>Staged Files in Ingestion Queue (<span x-text="stagedFiles.length"></span>)</span>
                    <span class="text-slate-500">Combined rows will merge into unified dataset</span>
                </div>
                <div class="max-h-32 overflow-y-auto space-y-1 pr-1 font-mono text-[11px]">
                    <template x-for="(file, idx) in stagedFiles" :key="file.name + idx">
                        <div class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-700">
                            <div class="flex items-center gap-2 truncate">
                                <span class="text-slate-400 font-sans" x-text="idx + 1 + '.'"></span>
                                <span class="font-bold text-slate-800 truncate" x-text="file.name"></span>
                                <span class="text-[10px] text-slate-400" x-text="'(' + formatFileSize(file.size) + ')'"></span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-100" x-text="file.rowCount ? file.rowCount + ' rows' : 'Processed'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- PRIMARY ACTION BUTTON: GENERATE FDR DASHBOARD --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-slate-200/80">
                <div class="text-[11px] text-slate-500">
                    Ready to switch to <strong class="text-slate-800" x-text="isExHalim ? 'EX HALIM (CGK)' : preScanData.reportType"></strong> layout with deep telemetry analytics.
                </div>
                <button type="button" @click="generateDashboardFromStaged()"
                        class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-black tracking-wide uppercase shadow-sm shadow-blue-600/30 transition flex items-center justify-center gap-2 cursor-pointer hover:scale-[1.01] active:scale-[0.99]">
                    <svg class="w-4 h-4 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Generate FDR Dashboard</span>
                </button>
            </div>

        </div>
    </template>

</div>
