{{--
    SlotWaves: Direct-to-Supabase Storage FDR Dropzone Component
    Bypasses Vercel 4.5MB Serverless Limit for large datasets up to 100MB
--}}

@props([
    'bucket' => config('services.supabase.bucket', 'fdr-datasets'),
    'endpoint' => route('fdr.handle-uploaded'),
    'maxSizeMb' => 100,
])

<div x-data="fdrSupabaseUploader({
    supabaseUrl: '{{ config('services.supabase.url') }}',
    supabaseKey: '{{ config('services.supabase.anon_key') }}',
    bucket: '{{ $bucket }}',
    backendEndpoint: '{{ $endpoint }}',
    csrfToken: '{{ csrf_token() }}',
    maxSizeMb: {{ $maxSizeMb }}
})" class="w-full space-y-4">

    {{-- ══ MAIN DROPZONE CONTAINER ══ --}}
    <div class="relative rounded-2xl border-2 border-dashed transition-all duration-200 overflow-hidden"
         :class="{
             'border-aviation-500 bg-aviation-50/40 dark:bg-aviation-950/30 ring-4 ring-aviation-500/10': isDragging,
             'border-slate-300 dark:border-slate-700 bg-white/70 dark:bg-navy-900/60 hover:border-aviation-400': !isDragging && !uploadState.error,
             'border-red-400 dark:border-red-600 bg-red-50/40 dark:bg-red-950/20': uploadState.error
         }"
         @dragover.prevent="isDragging = true"
         @dragleave.prevent="isDragging = false"
         @drop.prevent="handleDrop($event)">

        {{-- Hidden native file input --}}
        <input type="file"
               x-ref="fileInput"
               @change="handleFilePicked($event)"
               accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
               class="hidden"
               id="fdr-supabase-file-input">

        {{-- ── IDLE / INITIAL STATE ── --}}
        <div x-show="uploadState.status === 'idle'" class="p-8 sm:p-10 flex flex-col items-center justify-center text-center">
            {{-- Cloud Upload Icon --}}
            <div class="w-16 h-16 rounded-2xl bg-aviation-50 dark:bg-aviation-950/80 border border-aviation-200 dark:border-aviation-800 flex items-center justify-center text-aviation-600 dark:text-aviation-400 mb-4 shadow-sm group-hover:scale-105 transition-transform duration-200">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
                </svg>
            </div>

            <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">
                Drag &amp; Drop Flight Daily Report (FDR)
            </h3>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md">
                Direct client-to-storage upload bypassing serverless payload limits.
                <button type="button" @click="$refs.fileInput.click()" class="text-aviation-600 dark:text-aviation-400 font-semibold underline underline-offset-2 hover:text-aviation-700">
                    Browse files
                </button>
            </p>

            {{-- Feature Badges --}}
            <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-[11px] font-mono">
                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                    .XLSX / .XLS
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">
                    Max 100 MB Limit
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Direct Supabase S3
                </span>
            </div>
        </div>

        {{-- ── FILE SELECTED & PRE-VALIDATION STATE ── --}}
        <div x-show="uploadState.status === 'selected'" x-cloak class="p-6 sm:p-8 space-y-4">
            <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-aviation-500/10 dark:bg-aviation-400/10 border border-aviation-500/20 flex items-center justify-center text-aviation-600 dark:text-aviation-400 font-bold text-xs uppercase">
                        <span x-text="selectedFile?.name.split('.').pop() || 'XLS'"></span>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-900 dark:text-white truncate max-w-sm" x-text="selectedFile?.name"></div>
                        <div class="text-xs text-slate-400 font-mono" x-text="formatBytes(selectedFile?.size || 0)"></div>
                    </div>
                </div>

                <button type="button" @click="resetUploader()" class="text-xs text-slate-400 hover:text-red-500 transition">
                    Change File
                </button>
            </div>

            {{-- Automated Detection Preview Cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800/80 border border-slate-200/80 dark:border-slate-700/80">
                    <div class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">Airline / Operator</div>
                    <div class="font-bold text-aviation-600 dark:text-aviation-400 truncate mt-0.5" x-text="detectedMetadata.airline || 'Auto-Detect'"></div>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800/80 border border-slate-200/80 dark:border-slate-700/80">
                    <div class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">Target Airport</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate mt-0.5" x-text="detectedMetadata.airport || 'CGK'"></div>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800/80 border border-slate-200/80 dark:border-slate-700/80">
                    <div class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">Period / Range</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate mt-0.5" x-text="detectedMetadata.period || 'Full Season'"></div>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-navy-800/80 border border-slate-200/80 dark:border-slate-700/80">
                    <div class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">Upload Target</div>
                    <div class="font-bold text-emerald-600 dark:text-emerald-400 truncate mt-0.5">Direct Storage</div>
                </div>
            </div>

            {{-- Action Button --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="resetUploader()" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-navy-800 rounded-xl transition">
                    Cancel
                </button>
                <button type="button" @click="startDirectUpload()" class="px-5 py-2 text-xs font-bold text-white bg-aviation-600 hover:bg-aviation-700 active:scale-98 rounded-xl shadow-md transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Start Direct Upload (100MB)</span>
                </button>
            </div>
        </div>

        {{-- ── UPLOADING & PROCESSING STATE (PROGRESS BAR) ── --}}
        <div x-show="uploadState.status === 'uploading' || uploadState.status === 'processing'" x-cloak class="p-8 space-y-5">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2.5">
                    <span class="w-3 h-3 rounded-full bg-aviation-500 animate-ping"></span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 text-sm" x-text="uploadState.statusTitle"></span>
                </div>
                <span class="font-mono font-bold text-aviation-600 dark:text-aviation-400 text-sm" x-text="`${uploadState.progress}%`"></span>
            </div>

            {{-- Progress Bar --}}
            <div class="w-full h-3 rounded-full bg-slate-100 dark:bg-navy-800 overflow-hidden p-0.5 border border-slate-200 dark:border-slate-700">
                <div class="h-full rounded-full bg-linear-to-r from-aviation-600 to-aviation-400 transition-all duration-150 ease-out"
                     :style="`width: ${uploadState.progress}%`"></div>
            </div>

            {{-- Upload Stats & Details --}}
            <div class="flex items-center justify-between text-[11px] text-slate-400 font-mono">
                <span x-text="uploadState.detailText"></span>
                <span x-text="uploadState.speedText"></span>
            </div>
        </div>

        {{-- ── SUCCESS STATE ── --}}
        <div x-show="uploadState.status === 'completed'" x-cloak class="p-8 text-center space-y-3">
            <div class="w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h4 class="text-base font-bold text-slate-900 dark:text-white">Upload &amp; Streaming Extraction Completed!</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400" x-text="uploadState.successMessage"></p>
            <div class="pt-2">
                <a :href="uploadState.redirectUrl" class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md transition">
                    <span>Open Flight Intelligence Dashboard</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
        </div>

        {{-- ── ERROR STATE ── --}}
        <div x-show="uploadState.error" x-cloak class="p-6 bg-red-50 dark:bg-red-950/40 border-t border-red-200 dark:border-red-800 text-xs text-red-700 dark:text-red-300 flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="flex-1 space-y-1">
                <div class="font-bold text-red-800 dark:text-red-200" x-text="uploadState.errorTitle || 'Upload Error'"></div>
                <div x-text="uploadState.errorMessage"></div>
                <div class="pt-2">
                    <button type="button" @click="resetUploader()" class="px-3 py-1 bg-red-600 text-white rounded-lg font-semibold text-[11px] hover:bg-red-700 transition">
                        Try Again
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
function fdrSupabaseUploader(config) {
    return {
        supabaseUrl: config.supabaseUrl,
        supabaseKey: config.supabaseKey,
        bucket: config.bucket || 'fdr-datasets',
        backendEndpoint: config.backendEndpoint,
        csrfToken: config.csrfToken,
        maxSizeBytes: (config.maxSizeMb || 100) * 1024 * 1024,

        isDragging: false,
        selectedFile: null,

        detectedMetadata: {
            airline: null,
            airport: 'CGK',
            period: null,
            realization: 'YES'
        },

        uploadState: {
            status: 'idle', // idle, selected, uploading, processing, completed
            progress: 0,
            statusTitle: '',
            detailText: '',
            speedText: '',
            error: false,
            errorTitle: '',
            errorMessage: '',
            successMessage: '',
            redirectUrl: '#'
        },

        handleDrop(e) {
            this.isDragging = false;
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                this.processSelectedFile(files[0]);
            }
        },

        handleFilePicked(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                this.processSelectedFile(files[0]);
            }
        },

        processSelectedFile(file) {
            this.uploadState.error = false;

            // 1. File Size Validation (Max 100MB)
            if (file.size > this.maxSizeBytes) {
                this.uploadState.error = true;
                this.uploadState.errorTitle = 'FILE EXCEEDS MAXIMUM LIMIT';
                this.uploadState.errorMessage = `Selected file (${(file.size / 1048576).toFixed(1)} MB) exceeds the maximum allowed limit of ${config.maxSizeMb || 100} MB.`;
                return;
            }

            // 2. Extension Validation
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['xlsx', 'xls', 'csv'].includes(ext)) {
                this.uploadState.error = true;
                this.uploadState.errorTitle = 'UNSUPPORTED FILE TYPE';
                this.uploadState.errorMessage = `Only Excel (.xlsx, .xls) and CSV datasets are accepted for Flight Daily Report.`;
                return;
            }

            this.selectedFile = file;
            this.inspectFilenameAndPath(file.name);
            this.uploadState.status = 'selected';
        },

        inspectFilenameAndPath(filename) {
            // Automated Airline and Date Range Detection from standard FDR path patterns:
            // e.g.: "Flight Daily Report/(01-01-2026) - (30-06-2026)/Realization/CGK GA FDR .xlsx"
            // e.g.: "Flight Daily Report/(01-08-2026) - (31-08-2026)/Realization/LION/CGK FDR.xlsx"
            let airline = null;
            if (/\b(GA|GARUDA)\b/i.test(filename)) {
                airline = 'Garuda Indonesia';
            } else if (/\b(LION|JT)\b/i.test(filename)) {
                airline = 'Lion Air';
            } else if (/\b(CITILINK|QG|CTV)\b/i.test(filename)) {
                airline = 'Citilink';
            } else if (/\b(BATIK|ID)\b/i.test(filename)) {
                airline = 'Batik Air';
            } else if (/\b(SUPER\s*AIR\s*JET|IU)\b/i.test(filename)) {
                airline = 'Super Air Jet';
            }

            let airport = 'CGK';
            const mAir = filename.match(/\b(CGK|SUB|DPS|KNO|UPG|BDO|JOG|SRG|YIA|BPN|BDJ|MDC)\b/i);
            if (mAir) airport = mAir[1].toUpperCase();

            let period = null;
            const mDates = filename.match(/\(?(\d{2}-\d{2}-\d{4})\)?\s*-\s*\(?(\d{2}-\d{2}-\d{4})\)?/);
            if (mDates) {
                period = `${mDates[1]} to ${mDates[2]}`;
            }

            this.detectedMetadata = {
                airline: airline,
                airport: airport,
                period: period,
                realization: /REALIZATION/i.test(filename) ? 'YES' : 'YES'
            };
        },

        async startDirectUpload() {
            if (!this.selectedFile) return;

            const file = this.selectedFile;
            this.uploadState.status = 'uploading';
            this.uploadState.progress = 0;
            this.uploadState.statusTitle = 'Uploading directly to Supabase Storage...';
            this.uploadState.detailText = `0 MB of ${(file.size / 1048576).toFixed(1)} MB`;
            this.uploadState.speedText = 'Initiating stream...';

            // Generate clean unique storage path: raw_fdr/YYYY/MM/timestamp_filename
            const date = new Date();
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const timestamp = Date.now();
            const safeName = file.name.replace(/[^a-zA-Z0-9._-]/g, '_');
            const storagePath = `raw_fdr/${year}/${month}/${timestamp}_${safeName}`;

            const startTime = Date.now();

            try {
                // Execute direct upload using standard XMLHttpRequest for true byte-level progress reporting
                await this.uploadViaXhr(file, storagePath, (loaded, total) => {
                    const pct = Math.round((loaded / total) * 100);
                    this.uploadState.progress = Math.min(99, pct);
                    const loadedMb = (loaded / 1048576).toFixed(1);
                    const totalMb = (total / 1048576).toFixed(1);
                    this.uploadState.detailText = `${loadedMb} MB of ${totalMb} MB`;

                    const elapsedSec = (Date.now() - startTime) / 1000;
                    if (elapsedSec > 0.5) {
                        const speedKb = Math.round((loaded / 1024) / elapsedSec);
                        this.uploadState.speedText = speedKb > 1024 ? `${(speedKb / 1024).toFixed(1)} MB/s` : `${speedKb} KB/s`;
                    }
                });

                // Direct upload completed: now notify backend with metadata
                this.uploadState.status = 'processing';
                this.uploadState.progress = 100;
                this.uploadState.statusTitle = 'Validating and Streaming Extraction in Laravel...';
                this.uploadState.detailText = 'Memory-safe streaming parser active on Vercel runtime';
                this.uploadState.speedText = 'Parsing rows sequentially';

                const response = await fetch(this.backendEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        file_path: storagePath,
                        bucket_name: this.bucket,
                        original_filename: file.name,
                        airline_type: this.detectedMetadata.airline
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.error || (data.errors ? data.errors.join('; ') : 'Backend processing failed'));
                }

                this.uploadState.status = 'completed';
                this.uploadState.successMessage = data.message || `Successfully ingested ${data.valid_rows || 'all'} flight movements.`;
                this.uploadState.redirectUrl = data.redirect_url || '#';

                // Automatically redirect after a brief moment
                setTimeout(() => {
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    }
                }, 1200);

            } catch (err) {
                console.error('Direct Supabase Upload Error:', err);
                this.uploadState.error = true;
                this.uploadState.errorTitle = 'UPLOAD OR PROCESSING FAILED';
                this.uploadState.errorMessage = err.message || 'An unexpected error occurred during direct upload.';
                this.uploadState.status = 'selected';
            }
        },

        uploadViaXhr(file, storagePath, onProgress) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                const uploadUrl = `${this.supabaseUrl.replace(/\/+$/, '')}/storage/v1/object/${this.bucket}/${storagePath}`;

                xhr.open('POST', uploadUrl, true);
                xhr.setRequestHeader('apikey', this.supabaseKey);
                xhr.setRequestHeader('Authorization', `Bearer ${this.supabaseKey}`);
                xhr.setRequestHeader('x-upsert', 'true');

                if (xhr.upload && onProgress) {
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            onProgress(e.loaded, e.total);
                        }
                    };
                }

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(xhr.response);
                    } else {
                        reject(new Error(`Supabase Storage returned HTTP ${xhr.status}: ${xhr.responseText}`));
                    }
                };

                xhr.onerror = () => {
                    reject(new Error('Network error connecting to Supabase Storage. Check CORS and connectivity.'));
                };

                xhr.send(file);
            });
        },

        resetUploader() {
            this.selectedFile = null;
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
            this.uploadState = {
                status: 'idle',
                progress: 0,
                statusTitle: '',
                detailText: '',
                speedText: '',
                error: false,
                errorTitle: '',
                errorMessage: '',
                successMessage: '',
                redirectUrl: '#'
            };
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };
}
</script>
