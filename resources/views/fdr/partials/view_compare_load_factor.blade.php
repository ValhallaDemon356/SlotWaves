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
            Airports/Records: <span class="text-slate-900 font-mono" x-text="getYoyRecords().length"></span>
        </div>
    </div>

    {{-- 5 YoY COMPARISON KPI CARDS --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        
        {{-- 1. Flight Movement YoY --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                      :class="parseFloat(getYoyKpi('flightRatio')) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                      x-text="getYoyKpi('flightRatio') + '%'"></span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Flight Movement YoY</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getYoyKpi('flightCurr').toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                    Prev: <span x-text="getYoyKpi('flightPrev').toLocaleString()">0</span> • Curr: <span x-text="getYoyKpi('flightCurr').toLocaleString()">0</span>
                </div>
            </div>
        </div>

        {{-- 2. Passenger Volume YoY --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                      :class="parseFloat(getYoyKpi('paxRatio')) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                      x-text="getYoyKpi('paxRatio') + '%'"></span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Passenger Volume YoY</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getYoyKpi('paxCurr').toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                    Prev: <span x-text="getYoyKpi('paxPrev').toLocaleString()">0</span> • Curr: <span x-text="getYoyKpi('paxCurr').toLocaleString()">0</span>
                </div>
            </div>
        </div>

        {{-- 3. Cargo Volume YoY --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                      :class="parseFloat(getYoyKpi('cargoRatio')) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                      x-text="getYoyKpi('cargoRatio') + '%'"></span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cargo Volume YoY (Kg)</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getYoyKpi('cargoCurr').toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                    Prev: <span x-text="getYoyKpi('cargoPrev').toLocaleString()">0</span> • Curr: <span x-text="getYoyKpi('cargoCurr').toLocaleString()">0</span>
                </div>
            </div>
        </div>

        {{-- 4. Seat Capacity YoY --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605"/></svg>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                      :class="parseFloat(getYoyKpi('seatcapRatio')) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                      x-text="getYoyKpi('seatcapRatio') + '%'"></span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Seat Capacity YoY</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getYoyKpi('seatcapCurr').toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                    Prev: <span x-text="getYoyKpi('seatcapPrev').toLocaleString()">0</span> • Curr: <span x-text="getYoyKpi('seatcapCurr').toLocaleString()">0</span>
                </div>
            </div>
        </div>

        {{-- 5. SLF Shift --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                      :class="(parseFloat(getYoyKpi('slfCurrRatio')) - parseFloat(getYoyKpi('slfPrevRatio'))) >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                      x-text="((parseFloat(getYoyKpi('slfCurrRatio')) - parseFloat(getYoyKpi('slfPrevRatio'))) >= 0 ? '+' : '') + (parseFloat(getYoyKpi('slfCurrRatio')) - parseFloat(getYoyKpi('slfPrevRatio'))).toFixed(1) + '% pts'"></span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">SLF Shift</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getYoyKpi('slfCurrRatio') + '%'">0.0%</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                    Prev: <span x-text="getYoyKpi('slfPrevRatio') + '%'">0.0%</span> • Curr: <span x-text="getYoyKpi('slfCurrRatio') + '%'">0.0%</span>
                </div>
            </div>
        </div>

    </div>

    {{-- 2 CHARTS (YoY Comparison & Ranking/Trend) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        {{-- Chart 1: Prev Year vs Curr Year Comparison --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">YoY Traffic Comparison (Prev vs Curr)</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Airports traffic overview</span>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="fdrYoyBarChart"></canvas>
            </div>
        </div>

        {{-- Chart 2: YoY Growth Ratio (%) Ranking or Trend --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900" x-text="activeReportType === 'COMPARE_LOAD_FACTOR_DAY' ? 'Daily Traffic Trend (Prev vs Curr)' : 'YoY Growth Ratio (%) Ranking'"></h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Growth trajectory</span>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="fdrYoyTrendChart"></canvas>
            </div>
        </div>

    </div>

    {{-- 20-COLUMN MULTI-HEADER YoY COMPARISON TABLE --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Year-over-Year Operational Comparison Matrix (20 Columns)</h3>
                <p class="text-[11px] text-slate-400">OASYS DAU Seat Load Factor comparative schema</p>
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
                        <th rowspan="2" class="py-2 px-3 border-r border-slate-200 text-left" x-text="activeReportType === 'COMPARE_LOAD_FACTOR_DAY' ? 'Airport & Date' : 'Airport'"></th>
                        <th colspan="3" class="py-1 px-3 border-r border-slate-200 bg-blue-50/70 text-blue-900">Flight Movement</th>
                        <th colspan="3" class="py-1 px-3 border-r border-slate-200 bg-emerald-50/70 text-emerald-900">Passenger Volume</th>
                        <th colspan="3" class="py-1 px-3 border-r border-slate-200 bg-amber-50/70 text-amber-900">Cargo (Kg)</th>
                        <th colspan="3" class="py-1 px-3 border-r border-slate-200 bg-indigo-50/70 text-indigo-900">Seat Capacity</th>
                        <th colspan="6" class="py-1 px-3 bg-purple-50/70 text-purple-900">Seat Load Factor (SLF)</th>
                    </tr>
                    {{-- Row 2: Sub-Headers --}}
                    <tr class="bg-slate-50 text-[9.5px] uppercase font-bold text-slate-500 border-b border-slate-200">
                        {{-- Flight --}}
                        <th class="py-1.5 px-2 text-right">Prev</th>
                        <th class="py-1.5 px-2 text-right">Curr</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">Ratio %</th>
                        {{-- Pax --}}
                        <th class="py-1.5 px-2 text-right">Prev</th>
                        <th class="py-1.5 px-2 text-right">Curr</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">Ratio %</th>
                        {{-- Cargo --}}
                        <th class="py-1.5 px-2 text-right">Prev</th>
                        <th class="py-1.5 px-2 text-right">Curr</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">Ratio %</th>
                        {{-- Seat Cap --}}
                        <th class="py-1.5 px-2 text-right">Prev</th>
                        <th class="py-1.5 px-2 text-right">Curr</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">Ratio %</th>
                        {{-- SLF Prev --}}
                        <th class="py-1.5 px-2 text-right">Prev Cap</th>
                        <th class="py-1.5 px-2 text-right">Prev Pax</th>
                        <th class="py-1.5 px-2 text-center border-r border-slate-200">Prev %</th>
                        {{-- SLF Curr --}}
                        <th class="py-1.5 px-2 text-right">Curr Cap</th>
                        <th class="py-1.5 px-2 text-right">Curr Pax</th>
                        <th class="py-1.5 px-2 text-center">Curr %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    <template x-for="(r, idx) in getYoyRecords()" :key="idx">
                        <tr class="hover:bg-purple-50/30 transition">
                            <td class="py-2 px-3 text-slate-400 font-sans border-r border-slate-100" x-text="idx + 1"></td>
                            <td class="py-2 px-3 font-sans font-bold text-slate-800 border-r border-slate-100">
                                <span x-text="r.airport || r.station"></span>
                                <template x-if="r.date">
                                    <div class="text-[9.5px] font-normal text-slate-400 font-mono" x-text="r.date"></div>
                                </template>
                            </td>
                            {{-- Flight --}}
                            <td class="py-2 px-2 text-right text-slate-600" x-text="Number(r.flightPrev || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right font-bold text-slate-900" x-text="Number(r.flightCurr || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center border-r border-slate-100">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="parseFloat(r.flightRatio || 0) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="(typeof r.flightRatio === 'string' && r.flightRatio.includes('%')) ? r.flightRatio : (r.flightRatio + '%')"></span>
                            </td>
                            {{-- Pax --}}
                            <td class="py-2 px-2 text-right text-slate-600" x-text="Number(r.paxPrev || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right font-bold text-slate-900" x-text="Number(r.paxCurr || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center border-r border-slate-100">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="parseFloat(r.paxRatio || 0) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="(typeof r.paxRatio === 'string' && r.paxRatio.includes('%')) ? r.paxRatio : (r.paxRatio + '%')"></span>
                            </td>
                            {{-- Cargo --}}
                            <td class="py-2 px-2 text-right text-slate-600" x-text="Number(r.cargoPrev || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right font-bold text-slate-900" x-text="Number(r.cargoCurr || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center border-r border-slate-100">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="parseFloat(r.cargoRatio || 0) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="(typeof r.cargoRatio === 'string' && r.cargoRatio.includes('%')) ? r.cargoRatio : (r.cargoRatio + '%')"></span>
                            </td>
                            {{-- Seat Cap --}}
                            <td class="py-2 px-2 text-right text-slate-600" x-text="Number(r.seatcapPrev || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right font-bold text-slate-900" x-text="Number(r.seatcapCurr || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center border-r border-slate-100">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="parseFloat(r.seatcapRatio || 0) >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="(typeof r.seatcapRatio === 'string' && r.seatcapRatio.includes('%')) ? r.seatcapRatio : (r.seatcapRatio + '%')"></span>
                            </td>
                            {{-- SLF Prev --}}
                            <td class="py-2 px-2 text-right text-slate-500 text-[10px]" x-text="Number(r.slfPrevSeatcap || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right text-slate-500 text-[10px]" x-text="Number(r.slfPrevPax || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center font-bold text-slate-700 border-r border-slate-100" x-text="(typeof r.slfPrevRatio === 'string' && r.slfPrevRatio.includes('%')) ? r.slfPrevRatio : (r.slfPrevRatio + '%')"></td>
                            {{-- SLF Curr --}}
                            <td class="py-2 px-2 text-right text-slate-700 text-[10px]" x-text="Number(r.slfCurrSeatcap || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-right text-slate-700 text-[10px]" x-text="Number(r.slfCurrPax || 0).toLocaleString()"></td>
                            <td class="py-2 px-2 text-center font-black"
                                :class="parseFloat(r.slfCurrRatio || 0) >= parseFloat(r.slfPrevRatio || 0) ? 'text-emerald-700' : 'text-rose-600'"
                                x-text="(typeof r.slfCurrRatio === 'string' && r.slfCurrRatio.includes('%')) ? r.slfCurrRatio : (r.slfCurrRatio + '%')"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

</div>
