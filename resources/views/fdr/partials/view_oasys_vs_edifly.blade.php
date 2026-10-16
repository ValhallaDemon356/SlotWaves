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
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">14 COLUMNS</span>
                </div>
                <p class="text-[11px] text-slate-500 mt-0.5">Automated Type B / Edifly messaging gateway health, passenger &amp; cargo API status tracking</p>
            </div>
        </div>
        <div class="text-[11px] font-bold text-slate-600">
            PAX Integration: <span class="font-mono text-emerald-700 font-black" x-text="getEdiflyPaxIntegrationRate() + '%'"></span> • CARGO: <span class="font-mono text-blue-700 font-black" x-text="getEdiflyCargoIntegrationRate() + '%'"></span>
        </div>
    </div>

    {{-- 6 INTEGRATION & COMMENT-EXTRACTED KPI CARDS --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        
        {{-- 1. Total Movements --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                </div>
                <span class="text-[10px] font-bold text-blue-600 uppercase">Movements</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Movements</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getEdiflyTotalMovements().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    <span x-text="getEdiflyNarrowCount() + ' Narrow • ' + getEdiflyWideCount() + ' Wide'"></span>
                </div>
            </div>
        </div>

        {{-- 2. PAX API Integration Rate (%) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                      :class="parseFloat(getEdiflyPaxIntegrationRate()) >= 80 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                      x-text="getEdiflyPaxIntegrationRate() + '%'">0%</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">PAX API Status</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getEdiflyPaxIntegrationRate() + '%'">0.0%</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    <span class="text-emerald-700 font-bold" x-text="getEdiflyPaxIntegratedCount()">0</span> Int • <span class="text-rose-600 font-bold" x-text="getEdiflyPaxNoIntegrationCount()">0</span> Gap
                </div>
            </div>
        </div>

        {{-- 3. CARGO API Integration Rate (%) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                      :class="parseFloat(getEdiflyCargoIntegrationRate()) >= 80 ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800'"
                      x-text="getEdiflyCargoIntegrationRate() + '%'">0%</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">CARGO API Status</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getEdiflyCargoIntegrationRate() + '%'">0.0%</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    <span class="text-sky-700 font-bold" x-text="getEdiflyCargoIntegratedCount()">0</span> Int • <span class="text-rose-600 font-bold" x-text="getEdiflyCargoNoIntegrationCount()">0</span> Gap
                </div>
            </div>
        </div>

        {{-- 4. Actualized vs Pending Movements --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-teal-100 text-teal-800">Actualized</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Actualized Flights</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getEdiflyActualizedCount().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    Valid AOBT/AIBT • <span class="text-amber-600 font-bold" x-text="getEdiflyPendingCount()">0</span> Pending
                </div>
            </div>
        </div>

        {{-- 5. Total Passengers & Crew (Comment Extracted) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between" title="Aggregate extracted from OASYS HTML comment footer">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                </div>
                <span class="text-[9px] font-bold text-indigo-600 uppercase">Comment Total</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pax All (Comment)</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="Number(getEdiflyCommentTotals().paxAll || 0).toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate" :title="'Adult: ' + (getEdiflyCommentTotals().adult || 0) + ' • Crew: ' + (getEdiflyCommentTotals().crew || 0)">
                    Adl: <span x-text="Number(getEdiflyCommentTotals().adult || 0).toLocaleString()"></span> • Crw: <span x-text="Number(getEdiflyCommentTotals().crew || 0).toLocaleString()"></span>
                </div>
            </div>
        </div>

        {{-- 6. Total Cargo, Baggage & MTOW --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between" title="Aggregate extracted from OASYS HTML comment footer + row MTOW">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605"/></svg>
                </div>
                <span class="text-[9px] font-bold text-purple-600 uppercase">Payload Total</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cargo &amp; Payload</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="((Number(getEdiflyCommentTotals().cargoKg || 0))/1000).toFixed(0).toLocaleString() + ' Ton'">0 Ton</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    Bagg: <span x-text="((Number(getEdiflyCommentTotals().baggageKg || 0))/1000).toFixed(0) + 't'"></span> • MTOW: <span x-text="getEdiflyTotalMtow().toLocaleString() + 't'"></span>
                </div>
            </div>
        </div>

    </div>

    {{-- 2 CHARTS --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        {{-- Chart 1: Edifly API Integration Health Donut --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Edifly API Integration Health Donut</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Pax &amp; Cargo streams</span>
            </div>
            <div class="h-60 w-full relative flex items-center justify-center">
                <canvas id="fdrEdiflyDonutChart"></canvas>
            </div>
        </div>

        {{-- Chart 2: Integration Gap by Airline & Station Route --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Integration Gap by Airline &amp; Route</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Carrier linkage audit</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrEdiflyGapChart"></canvas>
            </div>
        </div>

    </div>

    {{-- 14-COLUMN EDIFLY STATUS TABLE WITH PILLS --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">OASYS vs EDIFLY Operational Registry (14 Columns)</h3>
                <p class="text-[11px] text-slate-400">Type B EDI message gateway verification status per flight movement</p>
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
                        <th class="py-2.5 px-3">Desc</th>
                        <th class="py-2.5 px-3">SOBT / SIBT</th>
                        <th class="py-2.5 px-3">AOBT / AIBT</th>
                        <th class="py-2.5 px-3 text-center">Leg</th>
                        <th class="py-2.5 px-3">Station 1</th>
                        <th class="py-2.5 px-3">Station 2</th>
                        <th class="py-2.5 px-3 text-right">MTOW</th>
                        <th class="py-2.5 px-3">Reg No</th>
                        <th class="py-2.5 px-3 text-center">API PAX EDIFLY</th>
                        <th class="py-2.5 px-3 text-center">API CARGO EDIFLY</th>
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
                            <td class="py-2 px-3 font-bold text-slate-900" x-text="r.aobtAibt || r.aobt || r.aibt || '-'"></td>
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                      :class="(r.leg === 'A' || r.leg === 'ARRIVAL') ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'"
                                      x-text="r.leg"></span>
                            </td>
                            <td class="py-2 px-3 font-sans font-semibold text-slate-700" x-text="r.station1 || r.city_1"></td>
                            <td class="py-2 px-3 font-sans font-semibold text-slate-700" x-text="r.station2 || r.city_2"></td>
                            <td class="py-2 px-3 text-right text-slate-800" x-text="Number(r.mtow || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-slate-700" x-text="r.regNo || r.reg_no || r.aircraftReg"></td>
                            {{-- Status Pills + Action Links --}}
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                      :class="(r.paxIntegrated ?? r.isPaxIntegrated) ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'"
                                      x-text="(r.paxIntegrated ?? r.isPaxIntegrated) ? 'INTEGRATED ✓' : 'NO INTEGRATION ✕'"></span>
                                <template x-if="r.paxCompareUrl || r.apiPaxLink">
                                    <a :href="r.paxCompareUrl || r.apiPaxLink" target="_blank" rel="noopener noreferrer" class="text-[9px] text-blue-600 hover:underline block mt-0.5 font-bold">compare pax &rarr;</a>
                                </template>
                            </td>
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                      :class="(r.cargoIntegrated ?? r.isCargoIntegrated) ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'"
                                      x-text="(r.cargoIntegrated ?? r.isCargoIntegrated) ? 'INTEGRATED ✓' : 'NO INTEGRATION ✕'"></span>
                                <template x-if="r.cargoCompareUrl || r.apiCargoLink">
                                    <a :href="r.cargoCompareUrl || r.apiCargoLink" target="_blank" rel="noopener noreferrer" class="text-[9px] text-blue-600 hover:underline block mt-0.5 font-bold">compare cargo &rarr;</a>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

</div>
