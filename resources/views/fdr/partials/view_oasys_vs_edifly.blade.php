{{-- ══ POLYMORPHIC VIEW: OASYS VS EDIFLY INTEGRATION (14 COLUMNS) ═══════════ --}}
<div class="space-y-5" id="fdr-edifly-integration-view">

    {{-- Banner Notice --}}
    <div class="bg-gradient-to-r from-blue-500/10 via-sky-500/10 to-teal-500/10 border border-blue-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black text-xs shadow-2xs">
                <span>EDI</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-wider text-blue-900">OASYS vs EDIFLY API Integration Telemetry</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">14 COLUMNS + FOOTER TOTALS</span>
                </div>
                <p class="text-[11px] text-slate-500 mt-0.5">Automated Type B / Edifly messaging gateway health, passenger &amp; cargo API status tracking</p>
            </div>
        </div>
        <div class="text-[11px] font-bold text-slate-600">
            PAX Integration: <span class="font-mono text-emerald-700 font-black" x-text="getEdiflyPaxIntegrationRate() + '%'"></span> • CARGO: <span class="font-mono text-blue-700 font-black" x-text="getEdiflyCargoIntegrationRate() + '%'"></span>
        </div>
    </div>

    {{-- ══ 3 CORE INTEGRATION KPI CARDS ═════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        
        {{-- 1. Tingkat Sinkronisasi OASYS–EDIFLY (%) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tingkat Sinkronisasi OASYS–EDIFLY</div>
                        <h4 class="text-xs font-black text-slate-900">API Messaging Integration Health</h4>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase font-mono"
                      :class="parseFloat(getEdiflyPaxIntegrationRate()) >= 80 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                      x-text="parseFloat(getEdiflyPaxIntegrationRate()) >= 80 ? 'HEALTHY' : 'GAPS DETECTED'"></span>
            </div>

            <div class="my-4">
                <div class="text-3xl font-black text-slate-900 tracking-tight font-mono" x-text="getEdiflyPaxIntegrationRate() + '%'">0.0%</div>
                <div class="text-xs font-semibold text-slate-500 mt-1">
                    Cargo API: <span class="font-bold text-blue-700 font-mono" x-text="getEdiflyCargoIntegrationRate() + '%'">0.0%</span> • Actualized: <span class="font-bold text-teal-700 font-mono" x-text="getEdiflyActualizedRate() + '%'">0.0%</span>
                </div>
            </div>

            <div class="text-[11px] text-slate-400 font-medium">
                Type B EDI message gateway verification vs SOBT/AOBT completeness
            </div>
        </div>

        {{-- 2. Total Selisih Data & Volume OASYS (Comment Totals) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Volume OASYS (Comment Totals)</div>
                        <h4 class="text-xs font-black text-slate-900">Pax All, Cargo &amp; MTOW</h4>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-indigo-100 text-indigo-800 font-mono" x-text="getEdiflyTotalMovements() + ' Movements'"></span>
            </div>

            <div class="my-4">
                <div class="text-3xl font-black text-slate-900 tracking-tight font-mono" x-text="Number(getEdiflyCommentTotals().paxAll || 0).toLocaleString() + ' Pax'">0 Pax</div>
                <div class="text-xs font-semibold text-slate-500 mt-1 truncate">
                    Cargo: <span class="font-bold text-slate-700 font-mono" x-text="((Number(getEdiflyCommentTotals().cargoKg || 0))/1000).toFixed(1) + 't'">0t</span> • Baggage: <span class="font-bold text-slate-700 font-mono" x-text="((Number(getEdiflyCommentTotals().baggageKg || 0))/1000).toFixed(1) + 't'">0t</span> • MTOW: <span class="font-bold text-slate-700 font-mono" x-text="getEdiflyTotalMtow().toLocaleString() + 't'">0t</span>
                </div>
            </div>

            <div class="text-[11px] text-slate-400 font-medium">
                Extracted from OASYS HTML comment footer row (<code class="bg-slate-100 text-slate-700 px-1 py-0.5 rounded text-[10px]">&lt;!-- -PAX ALL- --&gt;</code>)
            </div>
        </div>

        {{-- 3. Penerbangan Tanpa Pesan EDIFLY (Unreported/Missing EDIFLY) --}}
        <div class="bg-white rounded-2xl border p-5 shadow-2xs flex flex-col justify-between"
             :class="getEdiflyUnintegratedCount() === 0 ? 'border-emerald-200 bg-emerald-50/20' : 'border-rose-300 bg-rose-50/20'">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                         :class="getEdiflyUnintegratedCount() === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Penerbangan Tanpa Pesan EDIFLY</div>
                        <h4 class="text-xs font-black text-slate-900">Unreported / Missing Messages</h4>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                      :class="getEdiflyUnintegratedCount() === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                      x-text="getEdiflyUnintegratedCount() === 0 ? 'ALL INTEGRATED' : 'INTEGRATION GAP'"></span>
            </div>

            <div class="my-4">
                <div class="text-3xl font-black tracking-tight font-mono"
                     :class="getEdiflyUnintegratedCount() === 0 ? 'text-emerald-700' : 'text-rose-600'"
                     x-text="getEdiflyUnintegratedCount() + ' Flights'">0 Flights</div>
                <div class="text-xs font-semibold text-slate-600 mt-1">
                    PAX Gap: <span class="font-bold text-rose-600 font-mono" x-text="getEdiflyPaxNoIntegrationCount()">0</span> • CARGO Gap: <span class="font-bold text-rose-600 font-mono" x-text="getEdiflyCargoNoIntegrationCount()">0</span> • Pending: <span class="font-bold text-amber-600 font-mono" x-text="getEdiflyPendingCount()">0</span>
                </div>
            </div>

            <div class="text-[11px] font-medium"
                 :class="getEdiflyUnintegratedCount() === 0 ? 'text-emerald-600' : 'text-rose-600'"
                 x-text="getEdiflyUnintegratedCount() === 0 ? 'Full message integration active on all movements' : 'Movements with NO INTEGRATION on Pax/Cargo or missing AOBT/AIBT'">
            </div>
        </div>

    </div>

    {{-- ══ VISUALISASI & GRAFIK UTAMA (2 CHARTS) ═══════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        {{-- Chart 1: Tren Sinkronisasi Harian (OASYS vs EDIFLY) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Tren Sinkronisasi Harian (OASYS vs EDIFLY)</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Scheduled vs Actualized vs Edifly-Reported</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrEdiflyDailySyncChart"></canvas>
            </div>
        </div>

        {{-- Chart 2: Breakdown Kategori Selisih per Rute/Penerbangan --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Breakdown Kategori Selisih per Rute &amp; Armada</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">NO INTEGRATION by route &amp; fleet</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrEdiflyBreakdownChart"></canvas>
            </div>
        </div>

    </div>

    {{-- ══ 14-COLUMN EDIFLY STATUS TABLE WITH PILLS & COMPARE AUDIT LINKS ════════ --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Matriks Komparasi OASYS vs EDIFLY (14 Kolom)</h3>
                <p class="text-[11px] text-slate-400">Type B EDI message gateway verification status per flight movement with schedule deviation</p>
            </div>
            <div class="text-xs font-bold text-slate-600">
                Total Rows: <span class="font-mono text-slate-900" x-text="getEdiflyRecords().length"></span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[10px] uppercase font-black text-slate-500 tracking-wider">
                        <th class="py-2.5 px-3">#</th>
                        <th class="py-2.5 px-3">Airline</th>
                        <th class="py-2.5 px-3">Flight No</th>
                        <th class="py-2.5 px-3">Paired No</th>
                        <th class="py-2.5 px-3">Aircraft Desc</th>
                        <th class="py-2.5 px-3">SOBT / SIBT</th>
                        <th class="py-2.5 px-3">AOBT / AIBT</th>
                        <th class="py-2.5 px-3 text-center">Selisih (Δ Min)</th>
                        <th class="py-2.5 px-3">Route (Station 1 &rarr; 2)</th>
                        <th class="py-2.5 px-3 text-right">MTOW</th>
                        <th class="py-2.5 px-3">Reg No</th>
                        <th class="py-2.5 px-3 text-center">Status API PAX</th>
                        <th class="py-2.5 px-3 text-center">Status API CARGO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    <template x-for="(r, idx) in getPaginatedEdiflyRecords()" :key="idx">
                        <tr class="hover:bg-blue-50/40 transition">
                            <td class="py-2 px-3 text-slate-400 font-sans" x-text="((pagination.current_page - 1) * pagination.per_page) + idx + 1"></td>
                            <td class="py-2 px-3 font-sans font-bold text-slate-800" x-text="r.airline || r.air_line"></td>
                            <td class="py-2 px-3 font-bold text-blue-600" x-text="r.flightNo || r.flight_no || r.flightNumber"></td>
                            <td class="py-2 px-3 text-slate-600" x-text="r.pairedNo || r.paired_no || '-'"></td>
                            <td class="py-2 px-3 font-sans text-slate-600" x-text="r.desc || r.aircraftType || '-'"></td>
                            <td class="py-2 px-3 text-slate-600" x-text="r.sobtSibt || r.sobt || r.sibt"></td>
                            <td class="py-2 px-3 font-bold"
                                :class="(!r.aobtAibt || r.aobtAibt.trim() === '' || r.aobtAibt.includes('-0001')) ? 'text-amber-600 italic' : 'text-slate-900'"
                                x-text="(!r.aobtAibt || r.aobtAibt.trim() === '' || r.aobtAibt.includes('-0001')) ? 'PENDING' : r.aobtAibt"></td>
                            <td class="py-2 px-3 text-center">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="getEdiflyTimeDiffMinutes(r) > 15 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'"
                                      x-text="getEdiflyTimeDiffDisplay(r)">-</span>
                            </td>
                            <td class="py-2 px-3 font-sans font-semibold text-slate-700">
                                <span class="text-blue-700 font-bold" x-text="r.station1 || r.city_1"></span>
                                <span class="text-slate-400 mx-0.5">&rarr;</span>
                                <span class="text-indigo-700 font-bold" x-text="r.station2 || r.city_2"></span>
                            </td>
                            <td class="py-2 px-3 text-right text-slate-800" x-text="Number(r.mtow || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-slate-700" x-text="r.regNo || r.reg_no || r.aircraftReg"></td>
                            {{-- Status Pills + Clickable Action Links --}}
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                      :class="(r.paxIntegrated ?? r.isPaxIntegrated) ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'"
                                      x-text="(r.paxIntegrated ?? r.isPaxIntegrated) ? 'INTEGRATED ✓' : 'NO INTEGRATION ✕'"></span>
                                <template x-if="r.paxCompareUrl || r.apiPaxLink">
                                    <a :href="r.paxCompareUrl || r.apiPaxLink" target="_blank" rel="noopener noreferrer" class="text-[9px] text-blue-600 hover:underline block mt-0.5 font-bold">Compare Pax &rarr;</a>
                                </template>
                            </td>
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                      :class="(r.cargoIntegrated ?? r.isCargoIntegrated) ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'"
                                      x-text="(r.cargoIntegrated ?? r.isCargoIntegrated) ? 'INTEGRATED ✓' : 'NO INTEGRATION ✕'"></span>
                                <template x-if="r.cargoCompareUrl || r.apiCargoLink">
                                    <a :href="r.cargoCompareUrl || r.apiCargoLink" target="_blank" rel="noopener noreferrer" class="text-[9px] text-blue-600 hover:underline block mt-0.5 font-bold">Compare Cargo &rarr;</a>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination bar for Edifly --}}
        <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
            <span class="text-slate-500">
                Page <span class="font-bold text-slate-800" x-text="pagination.current_page">1</span> of <span class="font-bold text-slate-800" x-text="Math.ceil(getEdiflyRecords().length / pagination.per_page) || 1">1</span>
            </span>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 disabled:opacity-40">Previous</button>
                <button type="button" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page >= Math.ceil(getEdiflyRecords().length / pagination.per_page)" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>

</div>
