{{-- ══ POLYMORPHIC VIEW: COMPARE LOAD FACTOR YoY (20 COLUMNS) ═══════════════ --}}
<div class="space-y-5" id="fdr-compare-load-factor-view">

    {{-- Banner Notice --}}
    <div class="bg-gradient-to-r from-purple-500/10 via-indigo-500/10 to-blue-500/10 border border-purple-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-purple-600 text-white flex items-center justify-center font-black text-xs shadow-2xs">
                <span>YoY</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-wider text-purple-900" x-text="activeReportType === 'COMPARE_LOAD_FACTOR_DAY' ? 'Compare Seat Load Factor Daily YoY' : 'Compare Seat Load Factor Annual YoY'"></span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-300">20 COLUMNS</span>
                </div>
                <p class="text-[11px] text-slate-500 mt-0.5">Year-over-Year traffic recovery, seat capacity growth, and SLF variance across InJourney stations</p>
            </div>
        </div>
        <div class="text-[11px] font-bold text-slate-600">
            Records Evaluated: <span class="text-slate-900 font-mono font-bold" x-text="getYoyRecords().length"></span>
        </div>
    </div>

    {{-- ══ CASE A: COMPARE LOAD FACTOR (AGGREGATE) — 2 CORE KPI SUMMARY CARDS ══ --}}
    <div x-show="activeReportType === 'COMPARE_LOAD_FACTOR'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        
        {{-- Card 1: Perbandingan Rata-rata Load Factor (%) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Perbandingan Rata-rata Load Factor (%)</div>
                        <h4 class="text-xs font-black text-slate-900">Seat Load Factor (SLF) 2026 vs 2025</h4>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded text-[11px] font-black"
                      :class="(parseFloat(getYoyKpi('slfCurrRatio')) - parseFloat(getYoyKpi('slfPrevRatio'))) >= 0 ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'"
                      x-text="((parseFloat(getYoyKpi('slfCurrRatio')) - parseFloat(getYoyKpi('slfPrevRatio'))) >= 0 ? '+' : '') + (parseFloat(getYoyKpi('slfCurrRatio')) - parseFloat(getYoyKpi('slfPrevRatio'))).toFixed(2) + '% pts'"></span>
            </div>

            <div class="grid grid-cols-2 gap-4 my-4 p-3.5 rounded-xl bg-slate-50/80 border border-slate-100">
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">SLF 2025 (Prev)</div>
                    <div class="text-2xl font-black text-slate-600 font-mono mt-0.5" x-text="getYoyKpi('slfPrevRatio') + '%'">0.0%</div>
                    <div class="text-[10px] text-slate-500 mt-0.5">Seat Cap: <span class="font-bold text-slate-700 font-mono" x-text="getYoyKpi('seatcapPrev').toLocaleString()">0</span></div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">SLF 2026 (Curr)</div>
                    <div class="text-2xl font-black text-purple-700 font-mono mt-0.5" x-text="getYoyKpi('slfCurrRatio') + '%'">0.0%</div>
                    <div class="text-[10px] text-slate-500 mt-0.5">Seat Cap: <span class="font-bold text-slate-700 font-mono" x-text="getYoyKpi('seatcapCurr').toLocaleString()">0</span></div>
                </div>
            </div>

            <div class="text-[11px] text-slate-500 font-medium flex items-center justify-between">
                <span>Weighted network load factor variance across operational airports</span>
                <span class="font-bold" :class="(parseFloat(getYoyKpi('slfCurrRatio')) - parseFloat(getYoyKpi('slfPrevRatio'))) >= 0 ? 'text-emerald-700' : 'text-rose-600'"
                      x-text="(parseFloat(getYoyKpi('slfCurrRatio')) - parseFloat(getYoyKpi('slfPrevRatio'))) >= 0 ? 'Capacity Expansion Maintained' : 'Load Factor Softening'"></span>
            </div>
        </div>

        {{-- Card 2: Pertumbuhan Kapasitas & Penumpang (Growth/Variance) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pertumbuhan Kapasitas &amp; Penumpang</div>
                        <h4 class="text-xs font-black text-slate-900">YoY Operational Variance Ratio (% RATIO)</h4>
                    </div>
                </div>
                <span class="text-[10px] font-mono text-slate-400 font-bold">2026 vs 2025</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 my-3">
                {{-- Seat Cap Ratio --}}
                <div class="p-2.5 rounded-xl bg-indigo-50/50 border border-indigo-100 text-center">
                    <div class="text-[9px] font-bold text-slate-400 uppercase">Seat Capacity</div>
                    <div class="text-base font-black text-indigo-700 font-mono mt-0.5" x-text="getYoyKpi('seatcapRatio') + '%'">0%</div>
                    <div class="text-[9px] font-bold mt-0.5"
                         :class="(parseFloat(getYoyKpi('seatcapRatio')) - 100) >= 0 ? 'text-emerald-700' : 'text-rose-600'"
                         x-text="((parseFloat(getYoyKpi('seatcapRatio')) - 100) >= 0 ? '+' : '') + (parseFloat(getYoyKpi('seatcapRatio')) - 100).toFixed(1) + '% Δ'"></div>
                </div>
                {{-- Pax Ratio --}}
                <div class="p-2.5 rounded-xl bg-emerald-50/50 border border-emerald-100 text-center">
                    <div class="text-[9px] font-bold text-slate-400 uppercase">Pax Volume</div>
                    <div class="text-base font-black text-emerald-700 font-mono mt-0.5" x-text="getYoyKpi('paxRatio') + '%'">0%</div>
                    <div class="text-[9px] font-bold mt-0.5"
                         :class="(parseFloat(getYoyKpi('paxRatio')) - 100) >= 0 ? 'text-emerald-700' : 'text-rose-600'"
                         x-text="((parseFloat(getYoyKpi('paxRatio')) - 100) >= 0 ? '+' : '') + (parseFloat(getYoyKpi('paxRatio')) - 100).toFixed(1) + '% Δ'"></div>
                </div>
                {{-- Flight Movements Ratio --}}
                <div class="p-2.5 rounded-xl bg-blue-50/50 border border-blue-100 text-center">
                    <div class="text-[9px] font-bold text-slate-400 uppercase">Movements</div>
                    <div class="text-base font-black text-blue-700 font-mono mt-0.5" x-text="getYoyKpi('flightRatio') + '%'">0%</div>
                    <div class="text-[9px] font-bold mt-0.5"
                         :class="(parseFloat(getYoyKpi('flightRatio')) - 100) >= 0 ? 'text-emerald-700' : 'text-rose-600'"
                         x-text="((parseFloat(getYoyKpi('flightRatio')) - 100) >= 0 ? '+' : '') + (parseFloat(getYoyKpi('flightRatio')) - 100).toFixed(1) + '% Δ'"></div>
                </div>
                {{-- Cargo Ratio --}}
                <div class="p-2.5 rounded-xl bg-amber-50/50 border border-amber-100 text-center">
                    <div class="text-[9px] font-bold text-slate-400 uppercase">Cargo (Kg)</div>
                    <div class="text-base font-black text-amber-700 font-mono mt-0.5" x-text="getYoyKpi('cargoRatio') + '%'">0%</div>
                    <div class="text-[9px] font-bold mt-0.5"
                         :class="(parseFloat(getYoyKpi('cargoRatio')) - 100) >= 0 ? 'text-emerald-700' : 'text-rose-600'"
                         x-text="((parseFloat(getYoyKpi('cargoRatio')) - 100) >= 0 ? '+' : '') + (parseFloat(getYoyKpi('cargoRatio')) - 100).toFixed(1) + '% Δ'"></div>
                </div>
            </div>

            <div class="text-[11px] text-slate-500 font-medium">
                Benchmark: <code class="bg-slate-100 text-slate-700 px-1 py-0.5 rounded text-[10px]">&gt;100% Expansion</code> | <code class="bg-slate-100 text-slate-700 px-1 py-0.5 rounded text-[10px]">&lt;100% Contraction</code>
            </div>
        </div>

    </div>

    {{-- ══ CASE B: COMPARE LOAD FACTOR DAY (DAILY) — 3 CORE KPI SUMMARY CARDS ══ --}}
    <div x-show="activeReportType === 'COMPARE_LOAD_FACTOR_DAY'" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        
        {{-- Card 1: Peak Day Load Factor --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-800">PEAK DAY 2026</span>
            </div>
            <div class="mt-3">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Peak Day Load Factor</div>
                <div class="text-xl font-black text-slate-900 font-mono tracking-tight mt-0.5" x-text="getYoyPeakDate()">-</div>
                <div class="text-[11px] font-bold text-emerald-700 mt-1" x-text="getYoyPeakPax()">0 Pax</div>
            </div>
        </div>

        {{-- Card 2: Low Day Load Factor --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181"/></svg>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-100 text-rose-800">LOW DAY 2026</span>
            </div>
            <div class="mt-3">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Low Day Load Factor</div>
                <div class="text-xl font-black text-slate-900 font-mono tracking-tight mt-0.5" x-text="getYoyLowDate()">-</div>
                <div class="text-[11px] font-bold text-rose-600 mt-1" x-text="getYoyLowPax()">0 Pax</div>
            </div>
        </div>

        {{-- Card 3: Rata-rata Weekday vs Weekend LF (%) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-indigo-100 text-indigo-800">WEEKDAY vs WEEKEND</span>
            </div>
            <div class="mt-3">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Weekday vs Weekend LF (%)</div>
                <div class="grid grid-cols-2 gap-2 mt-1 text-xs">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Weekday (Sen–Kam)</span>
                        <span class="font-bold text-slate-800 font-mono text-sm" x-text="getYoyWeekdayWeekendLF().weekday2026">0%</span>
                        <span class="text-[9px] text-slate-400 block" x-text="'2025: ' + getYoyWeekdayWeekendLF().weekday2025"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Weekend (Jum–Min)</span>
                        <span class="font-bold text-indigo-700 font-mono text-sm" x-text="getYoyWeekdayWeekendLF().weekend2026">0%</span>
                        <span class="text-[9px] text-slate-400 block" x-text="'2025: ' + getYoyWeekdayWeekendLF().weekend2025"></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ══ VISUALISASI & GRAFIK UTAMA ═══════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        {{-- Chart 1: Side-by-Side Grouped Bar Chart (2025 vs 2026) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900" x-text="activeReportType === 'COMPARE_LOAD_FACTOR_DAY' ? 'Daily Volume Comparison (2025 vs 2026)' : 'Side-by-Side Traffic Comparison (2025 vs 2026)'"></h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Pax &amp; Seat Capacity</span>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="fdrYoyBarChart"></canvas>
            </div>
        </div>

        {{-- Chart 2: Variance / Delta Chart OR Multi-Line Daily SLF Trend --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900" x-text="activeReportType === 'COMPARE_LOAD_FACTOR_DAY' ? 'Daily SLF Trend (%) (2025 vs 2026)' : 'Variance / Delta Chart (Growth Ratio %)'"></h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400" x-text="activeReportType === 'COMPARE_LOAD_FACTOR_DAY' ? 'Day 1 to End-of-Month' : 'Deviation from 100% baseline'"></span>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="fdrYoyTrendChart"></canvas>
            </div>
        </div>

    </div>

    {{-- ══ CALENDAR HEATMAP / MATRIKS HARIAN (FOR COMPARE_LOAD_FACTOR_DAY) ══════ --}}
    <div x-show="activeReportType === 'COMPARE_LOAD_FACTOR_DAY'" class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Calendar Heatmap / Matriks Harian Seat Load Factor (2026)</h3>
            </div>
            <div class="flex items-center gap-3 text-[10px] font-bold">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-emerald-500"></span> &ge;80% Peak</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-teal-500"></span> 75-79% Good</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-amber-500"></span> 65-74% Mod</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-rose-500"></span> &lt;65% Soft</span>
            </div>
        </div>

        {{-- Heatmap Calendar Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-10 gap-2">
            <template x-for="day in getYoyCalendarDays()" :key="day.date">
                <div class="p-2.5 rounded-xl border transition text-center hover:scale-105 cursor-pointer"
                     :class="parseFloat(day.slf) >= 80 ? 'bg-emerald-50/80 border-emerald-300' : (parseFloat(day.slf) >= 75 ? 'bg-teal-50/80 border-teal-300' : (parseFloat(day.slf) >= 65 ? 'bg-amber-50/80 border-amber-300' : 'bg-rose-50/80 border-rose-300'))"
                     :title="day.date + ' (' + day.dayName + ')\nSLF: ' + day.slf + '%\nPax: ' + day.pax.toLocaleString() + '\nCap: ' + day.cap.toLocaleString()">
                    <div class="text-[9px] font-bold uppercase text-slate-400" x-text="day.dayName"></div>
                    <div class="text-sm font-black font-mono mt-0.5 text-slate-800" x-text="day.dayNum"></div>
                    <div class="text-xs font-black font-mono mt-1"
                         :class="parseFloat(day.slf) >= 80 ? 'text-emerald-700' : (parseFloat(day.slf) >= 75 ? 'text-teal-700' : (parseFloat(day.slf) >= 65 ? 'text-amber-700' : 'text-rose-600'))"
                         x-text="day.slf + '%'"></div>
                    <div class="text-[9px] text-slate-500 font-mono mt-0.5" x-text="(day.pax/1000).toFixed(1) + 'k pax'"></div>
                </div>
            </template>
        </div>
    </div>

    {{-- ══ 20-COLUMN MULTI-HEADER YoY COMPARISON TABLE ═══════════════════════════ --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900" x-text="activeReportType === 'COMPARE_LOAD_FACTOR_DAY' ? 'Chronological Daily YoY Operational Comparison Matrix (20 Columns)' : 'Airport YoY Operational Comparison Matrix (20 Columns)'"></h3>
                <p class="text-[11px] text-slate-400">Complete DAU OASYS comparative schema with Flights, Seat Capacity, Pax, Cargo, and SLF variance</p>
            </div>
            <div class="text-xs font-bold text-slate-600">
                Total Rows: <span class="font-mono text-slate-900" x-text="getYoyRecords().length"></span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap border-collapse">
                <thead>
                    {{-- Row 1: Group Headers --}}
                    <tr class="bg-slate-100/80 text-[10px] uppercase font-black text-slate-700 tracking-wider text-center border-b border-slate-200">
                        <th rowspan="2" class="py-2 px-3 border-r border-slate-200 text-left">#</th>
                        <th rowspan="2" class="py-2 px-3 border-r border-slate-200 text-left" x-text="activeReportType === 'COMPARE_LOAD_FACTOR_DAY' ? 'Tanggal & Hari' : 'Airport / Kategori'"></th>
                        <th colspan="3" class="py-1 px-3 border-r border-slate-200 bg-blue-50/70 text-blue-900">Flight Movements</th>
                        <th colspan="3" class="py-1 px-3 border-r border-slate-200 bg-indigo-50/70 text-indigo-900">Seat Capacity</th>
                        <th colspan="3" class="py-1 px-3 border-r border-slate-200 bg-emerald-50/70 text-emerald-900">Passenger Volume</th>
                        <th colspan="3" class="py-1 px-3 border-r border-slate-200 bg-amber-50/70 text-amber-900">Cargo (Kg)</th>
                        <th colspan="6" class="py-1 px-3 bg-purple-50/70 text-purple-900">Seat Load Factor (SLF)</th>
                    </tr>
                    {{-- Row 2: Sub-Headers --}}
                    <tr class="bg-slate-50 text-[9.5px] uppercase font-bold text-slate-500 border-b border-slate-200">
                        {{-- Flight --}}
                        <th class="py-1.5 px-2 text-right">2025</th>
                        <th class="py-1.5 px-2 text-right">2026</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">Ratio %</th>
                        {{-- Seat Cap --}}
                        <th class="py-1.5 px-2 text-right">2025</th>
                        <th class="py-1.5 px-2 text-right">2026</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">Ratio %</th>
                        {{-- Pax --}}
                        <th class="py-1.5 px-2 text-right">2025</th>
                        <th class="py-1.5 px-2 text-right">2026</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">Ratio %</th>
                        {{-- Cargo --}}
                        <th class="py-1.5 px-2 text-right">2025</th>
                        <th class="py-1.5 px-2 text-right">2026</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">Ratio %</th>
                        {{-- SLF Prev --}}
                        <th class="py-1.5 px-2 text-right">Cap '25</th>
                        <th class="py-1.5 px-2 text-right">Pax '25</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">SLF '25</th>
                        {{-- SLF Curr --}}
                        <th class="py-1.5 px-2 text-right">Cap '26</th>
                        <th class="py-1.5 px-2 text-right">Pax '26</th>
                        <th class="py-1.5 px-2 text-center">SLF '26</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    <template x-for="(r, idx) in getYoyRecords()" :key="r.airport ? (uploadId + '-yoy-' + r.airport + '-' + idx) : (uploadId + '-yoy-' + idx)">
                        <tr class="hover:bg-purple-50/30 transition">
                            <td class="py-2 px-3 text-slate-400 font-sans border-r border-slate-100" x-text="idx + 1"></td>
                            <td class="py-2 px-3 font-sans font-bold text-slate-800 border-r border-slate-100">
                                <span x-text="r.airport || r.station"></span>
                                <template x-if="r.date">
                                    <div class="text-[9.5px] font-normal text-slate-500 font-mono" x-text="r.date + ' (' + getDayName(r.date) + ')'"></div>
                                </template>
                            </td>
                            {{-- Flight --}}
                            <td class="py-2 px-2 text-right text-slate-600" x-text="Number(r.flightPrevYear || r.flightPrev || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right font-bold text-slate-900" x-text="Number(r.flightCurrYear || r.flightCurr || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center border-r border-slate-100">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="parseFloat(r.flightRatio || 0) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="(typeof r.flightRatio === 'string' && r.flightRatio.includes('%')) ? r.flightRatio : (r.flightRatio + '%')"></span>
                            </td>
                            {{-- Seat Cap --}}
                            <td class="py-2 px-2 text-right text-slate-600" x-text="Number(r.seatCapPrevYear || r.seatcapPrev || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right font-bold text-slate-900" x-text="Number(r.seatCapCurrYear || r.seatcapCurr || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center border-r border-slate-100">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="parseFloat(r.seatCapRatio || r.seatcapRatio || 0) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="(typeof (r.seatCapRatio || r.seatcapRatio) === 'string' && (r.seatCapRatio || r.seatcapRatio).includes('%')) ? (r.seatCapRatio || r.seatcapRatio) : ((r.seatCapRatio || r.seatcapRatio) + '%')"></span>
                            </td>
                            {{-- Pax --}}
                            <td class="py-2 px-2 text-right text-slate-600" x-text="Number(r.paxPrevYear || r.paxPrev || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right font-bold text-slate-900" x-text="Number(r.paxCurrYear || r.paxCurr || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center border-r border-slate-100">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="parseFloat(r.paxRatio || 0) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="(typeof r.paxRatio === 'string' && r.paxRatio.includes('%')) ? r.paxRatio : (r.paxRatio + '%')"></span>
                            </td>
                            {{-- Cargo --}}
                            <td class="py-2 px-2 text-right text-slate-600" x-text="Number(r.cargoPrevYear || r.cargoPrev || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right font-bold text-slate-900" x-text="Number(r.cargoCurrYear || r.cargoCurr || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center border-r border-slate-100">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="parseFloat(r.cargoRatio || 0) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="(typeof r.cargoRatio === 'string' && r.cargoRatio.includes('%')) ? r.cargoRatio : (r.cargoRatio + '%')"></span>
                            </td>
                            {{-- SLF Prev --}}
                            <td class="py-2 px-2 text-right text-slate-500 text-[10px]" x-text="Number(r.seatCap2025 || r.slfPrevSeatcap || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right text-slate-500 text-[10px]" x-text="Number(r.pax2025 || r.slfPrevPax || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center font-bold text-slate-700 border-r border-slate-100" x-text="(typeof (r.slf2025Ratio || r.slfPrevRatio) === 'string' && (r.slf2025Ratio || r.slfPrevRatio).includes('%')) ? (r.slf2025Ratio || r.slfPrevRatio) : ((r.slf2025Ratio || r.slfPrevRatio) + '%')"></td>
                            {{-- SLF Curr --}}
                            <td class="py-2 px-2 text-right text-slate-700 text-[10px]" x-text="Number(r.seatCap2026 || r.slfCurrSeatcap || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right text-slate-700 text-[10px]" x-text="Number(r.pax2026 || r.slfCurrPax || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center font-black"
                                :class="parseFloat(r.slf2026Ratio || r.slfCurrRatio || 0) >= parseFloat(r.slf2025Ratio || r.slfPrevRatio || 0) ? 'text-emerald-700' : 'text-rose-600'"
                                x-text="(typeof (r.slf2026Ratio || r.slfCurrRatio) === 'string' && (r.slf2026Ratio || r.slfCurrRatio).includes('%')) ? (r.slf2026Ratio || r.slfCurrRatio) : ((r.slf2026Ratio || r.slfCurrRatio) + '%')"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

</div>
