<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FDR Data Not Ready — SlotWaves</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 min-h-screen flex flex-col justify-between font-sans antialiased text-slate-800">

    {{-- Top Header --}}
    <header class="w-full bg-white border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-3">
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
        <div class="flex items-center gap-2">
            <a href="{{ route('fdr.index') }}" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-100 transition">
                FDR Home
            </a>
        </div>
    </header>

    {{-- Center Not Ready State --}}
    <main class="flex-1 max-w-3xl w-full mx-auto px-4 py-12 flex flex-col items-center justify-center">
        <div class="w-full bg-white rounded-3xl border border-slate-200/90 shadow-sm p-8 sm:p-10 text-center">
            
            <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 mb-6 shadow-2xs">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 uppercase tracking-wider mb-3">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                Database Ingestion Incomplete
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-2">
                FDR DATA NOT READY
            </h1>

            <p class="text-sm text-slate-600 max-w-lg mx-auto mb-6">
                Normalized flight movement records have not been loaded into the database (<code class="font-mono text-slate-800 text-xs bg-slate-100 px-1.5 py-0.5 rounded">fdr_flights count = 0</code>). The dashboard will only render once database rows are verified.
            </p>

            <div class="bg-slate-50 rounded-2xl border border-slate-200/80 p-4 text-left max-w-lg mx-auto mb-8 text-xs space-y-2">
                <div class="flex justify-between items-center text-slate-500">
                    <span>Upload ID:</span>
                    <span class="font-mono font-bold text-slate-800">#{{ $upload->id }}</span>
                </div>
                <div class="flex justify-between items-center text-slate-500">
                    <span>Source File:</span>
                    <span class="font-bold text-slate-800 truncate max-w-[280px]">{{ $sourceFile }}</span>
                </div>
                <div class="flex justify-between items-center text-slate-500">
                    <span>Storage Source:</span>
                    <span class="font-mono text-[11px] text-slate-700 truncate max-w-[280px]" title="{{ $storedPath }}">{{ Str::limit($storedPath, 38) }}</span>
                </div>
                @if(isset($job))
                <div class="flex justify-between items-center text-slate-500">
                    <span>Job Status:</span>
                    <span class="font-bold text-amber-700">{{ $job->status }} ({{ $job->progress }}%)</span>
                </div>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('fdr.config', ['upload' => $upload->id, 'auto_start' => 1]) }}"
                   class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-blue-600 text-white font-bold text-xs uppercase tracking-wider hover:bg-blue-700 transition shadow-sm shadow-blue-500/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/></svg>
                    Resume Processing
                </a>

                <form method="POST" action="{{ $reprocessUrl }}" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-slate-900 text-white font-bold text-xs uppercase tracking-wider hover:bg-slate-800 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        Retry Reprocessing
                    </button>
                </form>

                <a href="{{ route('fdr.config', ['upload' => $upload->id]) }}"
                   class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs uppercase tracking-wider hover:bg-slate-100 transition">
                    Upload Settings
                </a>
            </div>

        </div>
    </main>

    {{-- Footer --}}
    <footer class="w-full border-t border-slate-200 py-3 text-center text-xs text-slate-400">
        SlotWaves Airport Analytic Intelligence &copy; {{ date('Y') }}
    </footer>

</body>
</html>
