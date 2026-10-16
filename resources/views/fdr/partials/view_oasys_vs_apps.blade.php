{{-- ══ POLYMORPHIC VIEW: OASYS VS APPS DEPARTURE AUDIT (16 COLUMNS) ══════════ --}}
<div class="space-y-5" id="fdr-apps-audit-view" x-data="{ appsDiscrepancyOnly: false }">

    {{-- Banner Notice --}}
    <div class="bg-gradient-to-r from-cyan-500/10 via-sky-500/10 to-blue-500/10 border border-cyan-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-cyan-600 text-white flex items-center justify-center font-black text-xs shadow-2xs">
                <span>APPS</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-wider text-cyan-900">OASYS Departure vs APPS Reconciliation Audit</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-100 text-cyan-800 border border-cyan-300">16 COLUMNS + MATRIX</span>
                </div>
                <p class="text-[11px] text-slate-500 mt-0.5">Automated Passenger Processing System (APPS) departure manifest reconciliation and OTP variance audit</p>
            </div>
        </div>
        <div class="text-[11px] font-bold text-slate-600">
            Reconciliation Audit: <span class="font-mono" :class="getAppsDeviation() === 0 ? 'text-emerald-700 font-black' : 'text-rose-600 font-black'" x-text="getAppsDeviation() === 0 ? 'BALANCED (DEV = 0) ✓' : 'DEVIATION DETECTED (' + getAppsDeviation() + ' DEV) ⚠'"></span>
        </div>
    </div>

    {{-- ══ 3 CORE AUDIT KPI CARDS ══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        
        {{-- 1. Persentase Kecocokan Data (Match Rate %) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Persentase Kecocokan Data</div>
                        <h4 class="text-xs font-black text-slate-900">Match Rate (APPS / FLT DEP)</h4>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase font-mono"
                      :class="parseFloat(getAppsSyncAccuracy()) >= 99 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                      x-text="parseFloat(getAppsSyncAccuracy()) >= 99 ? 'OPTIMAL SYNC' : 'AUDIT REQUIRED'"></span>
            </div>

            <div class="my-4">
                <div class="text-3xl font-black text-slate-900 tracking-tight font-mono" x-text="getAppsSyncAccuracy() + '%'">100.0%</div>
                <div class="text-xs font-semibold text-slate-500 mt-1">
                    APPS Synced: <span class="font-bold text-slate-800 font-mono" x-text="getAppsRecorded().toLocaleString()">0</span> of <span class="font-bold text-slate-800 font-mono" x-text="getAppsFltDep().toLocaleString()">0</span> Flights
                </div>
            </div>

            <div class="text-[11px] text-slate-400 font-medium">
                Reconciled from official nested OASYS APPS audit sub-table
            </div>
        </div>

        {{-- 2. Total Penerbangan Selisih (Discrepancy Flights) --}}
        <div class="bg-white rounded-2xl border p-5 shadow-2xs flex flex-col justify-between"
             :class="getAppsDeviation() === 0 ? 'border-emerald-200 bg-emerald-50/20' : 'border-rose-300 bg-rose-50/20'">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                         :class="getAppsDeviation() === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Penerbangan Selisih</div>
                        <h4 class="text-xs font-black text-slate-900">Discrepancy Movements (DEV)</h4>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                      :class="getAppsDeviation() === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                      x-text="getAppsDeviation() === 0 ? 'ZERO DEV' : 'DISCREPANCY'"></span>
            </div>

            <div class="my-4">
                <div class="text-3xl font-black tracking-tight font-mono"
                     :class="getAppsDeviation() === 0 ? 'text-emerald-700' : 'text-rose-600'"
                     x-text="getAppsDeviation() + ' Flights'">0 Flights</div>
                <div class="text-xs font-semibold text-slate-600 mt-1">
                    Normal Dom Dev: <span class="font-bold text-slate-800 font-mono" x-text="getAppsMatrixVal('normalDom', 'dev')">0</span> • Int Dev: <span class="font-bold text-slate-800 font-mono" x-text="getAppsMatrixVal('normalInt', 'dev')">0</span>
                </div>
            </div>

            <div class="text-[11px] font-medium"
                 :class="getAppsDeviation() === 0 ? 'text-emerald-600' : 'text-rose-600'"
                 x-text="getAppsDeviation() === 0 ? 'Zero flight discrepancy verified across domestic & international' : 'Action Required: Flight manifest missing or unmatched in APPS clearance'">
            </div>
        </div>

        {{-- 3. Total Selisih & Volume Penumpang --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Volume &amp; Selisih Penumpang</div>
                        <h4 class="text-xs font-black text-slate-900">Departure Pax &amp; Operating Crew</h4>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-indigo-100 text-indigo-800 font-mono" x-text="getAppsRecords().length + ' Manifests'"></span>
            </div>

            <div class="my-4">
                <div class="text-3xl font-black text-slate-900 tracking-tight font-mono" x-text="getAppsTotalPax().toLocaleString() + ' Pax'">0 Pax</div>
                <div class="text-xs font-semibold text-slate-500 mt-1 truncate">
                    Crew: <span class="font-bold text-purple-700 font-mono" x-text="getAppsTotalCrew().toLocaleString()">0</span> • Anomalies: <span class="font-bold text-rose-600 font-mono" x-text="getAppsDiscrepancyCount()">0</span>
                </div>
            </div>

            <div class="text-[11px] text-slate-400 font-medium">
                Adult + Child + Infant + Transit + Transfer + Divert + Missed
            </div>
        </div>

    </div>

    {{-- ══ NESTED RECONCILIATION AUDIT MATRIX (PARSED FROM FOOTER SUB-TABLE) ══ --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-cyan-600"></span>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Nested Reconciliation Audit Matrix (APPS vs FLT DEP)</h3>
            </div>
            <span class="text-[10px] font-bold text-slate-400">Normal vs Transit | Domestic vs International</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[10px] uppercase font-black text-slate-500 tracking-wider">
                        <th class="py-2.5 px-3">Flight Category</th>
                        <th class="py-2.5 px-3 text-right">OASYS FLT DEP</th>
                        <th class="py-2.5 px-3 text-right">APPS RECORDED</th>
                        <th class="py-2.5 px-3 text-center">DEVIATION (DEV)</th>
                        <th class="py-2.5 px-3 text-center">Audit Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    {{-- Normal Dom --}}
                    <tr>
                        <td class="py-2 px-3 font-sans font-bold text-slate-800">Normal Domestic (Dom)</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('normalDom', 'fltDep')">0</td>
                        <td class="py-2 px-3 text-right font-bold text-slate-900" x-text="getAppsMatrixVal('normalDom', 'apps')">0</td>
                        <td class="py-2 px-3 text-center font-black"
                            :class="getAppsMatrixVal('normalDom', 'dev') === 0 ? 'text-emerald-600' : 'text-rose-600'"
                            x-text="getAppsMatrixVal('normalDom', 'dev')">0</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                  :class="getAppsMatrixVal('normalDom', 'dev') === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                  x-text="getAppsMatrixVal('normalDom', 'dev') === 0 ? 'MATCH' : 'DEVIATION'"></span>
                        </td>
                    </tr>
                    {{-- Normal Int --}}
                    <tr>
                        <td class="py-2 px-3 font-sans font-bold text-slate-800">Normal International (Int)</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('normalInt', 'fltDep')">0</td>
                        <td class="py-2 px-3 text-right font-bold text-slate-900" x-text="getAppsMatrixVal('normalInt', 'apps')">0</td>
                        <td class="py-2 px-3 text-center font-black"
                            :class="getAppsMatrixVal('normalInt', 'dev') === 0 ? 'text-emerald-600' : 'text-rose-600'"
                            x-text="getAppsMatrixVal('normalInt', 'dev')">0</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                  :class="getAppsMatrixVal('normalInt', 'dev') === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                  x-text="getAppsMatrixVal('normalInt', 'dev') === 0 ? 'MATCH' : 'DEVIATION'"></span>
                        </td>
                    </tr>
                    {{-- Transit Dom --}}
                    <tr>
                        <td class="py-2 px-3 font-sans font-bold text-slate-800">Transit Domestic (Dom)</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('transitDom', 'fltDep')">0</td>
                        <td class="py-2 px-3 text-right font-bold text-slate-900" x-text="getAppsMatrixVal('transitDom', 'apps')">0</td>
                        <td class="py-2 px-3 text-center font-black"
                            :class="getAppsMatrixVal('transitDom', 'dev') === 0 ? 'text-emerald-600' : 'text-rose-600'"
                            x-text="getAppsMatrixVal('transitDom', 'dev')">0</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                  :class="getAppsMatrixVal('transitDom', 'dev') === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                  x-text="getAppsMatrixVal('transitDom', 'dev') === 0 ? 'MATCH' : 'DEVIATION'"></span>
                        </td>
                    </tr>
                    {{-- Transit Int --}}
                    <tr>
                        <td class="py-2 px-3 font-sans font-bold text-slate-800">Transit International (Int)</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('transitInt', 'fltDep')">0</td>
                        <td class="py-2 px-3 text-right font-bold text-slate-900" x-text="getAppsMatrixVal('transitInt', 'apps')">0</td>
                        <td class="py-2 px-3 text-center font-black"
                            :class="getAppsMatrixVal('transitInt', 'dev') === 0 ? 'text-emerald-600' : 'text-rose-600'"
                            x-text="getAppsMatrixVal('transitInt', 'dev')">0</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                  :class="getAppsMatrixVal('transitInt', 'dev') === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                  x-text="getAppsMatrixVal('transitInt', 'dev') === 0 ? 'MATCH' : 'DEVIATION'"></span>
                        </td>
                    </tr>
                    {{-- Total Row --}}
                    <tr class="bg-slate-50 font-black">
                        <td class="py-2.5 px-3 font-sans text-slate-900 uppercase">Total Reconciled Movements</td>
                        <td class="py-2.5 px-3 text-right text-slate-900 text-sm" x-text="getAppsMatrixVal('total', 'fltDep')">0</td>
                        <td class="py-2.5 px-3 text-right text-slate-900 text-sm" x-text="getAppsMatrixVal('total', 'apps')">0</td>
                        <td class="py-2.5 px-3 text-center text-sm"
                            :class="getAppsMatrixVal('total', 'dev') === 0 ? 'text-emerald-700' : 'text-rose-600'"
                            x-text="getAppsMatrixVal('total', 'dev')">0</td>
                        <td class="py-2.5 px-3 text-center">
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-black"
                                  :class="getAppsMatrixVal('total', 'dev') === 0 ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'"
                                  x-text="getAppsMatrixVal('total', 'dev') === 0 ? 'VERIFIED' : 'ACTION REQUIRED'"></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══ VISUALISASI & GRAFIK UTAMA (3 CHARTS) ═══════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        {{-- Chart 1: OASYS vs APPS Comparison Bar Chart --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-cyan-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">OASYS vs APPS Comparison</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">FLT DEP vs APPS</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrAppsCompBarChart"></canvas>
            </div>
        </div>

        {{-- Chart 2: Discrepancy / Variance Chart --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Discrepancy / Variance Breakdown</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">DEV distribution</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrAppsDiscrepancyChart"></canvas>
            </div>
        </div>

        {{-- Chart 3: Status Rekonsiliasi (Donut Chart) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Status Rekonsiliasi</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Punctuality &amp; Integrity</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrAppsStatusDonutChart"></canvas>
            </div>
        </div>

    </div>

    {{-- ══ 16-COLUMN RECONCILIATION AUDIT TABLE ════════════════════════════════ --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Tabel Audit Rekonsiliasi (16 Kolom)</h3>
                <p class="text-[11px] text-slate-400">Flight level clearance validation between OASYS schedule and APPS passenger manifests</p>
            </div>
            <div class="flex items-center gap-3">
                {{-- Quick-filter toggle button: Tampilkan Hanya Data Selisih / Anomali --}}
                <button type="button"
                        @click="appsDiscrepancyOnly = !appsDiscrepancyOnly; pagination.current_page = 1"
                        :class="appsDiscrepancyOnly ? 'bg-rose-600 text-white font-black shadow-sm ring-2 ring-rose-400' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold border border-slate-200'"
                        class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    <span>Tampilkan Hanya Data Selisih / Anomali</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
                          :class="appsDiscrepancyOnly ? 'bg-white text-rose-700 font-bold' : 'bg-rose-100 text-rose-700 font-bold'"
                          x-text="getAppsDiscrepancyCount()">0</span>
                </button>
                <div class="text-xs font-bold text-slate-600">
                    Total: <span class="font-mono text-slate-900" x-text="getFilteredAppsRecords(appsDiscrepancyOnly).length"></span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[10px] uppercase font-black text-slate-500 tracking-wider">
                        <th class="py-2.5 px-3">#</th>
                        <th class="py-2.5 px-3">Flight Number</th>
                        <th class="py-2.5 px-3">STAD</th>
                        <th class="py-2.5 px-3">Off Block Time</th>
                        <th class="py-2.5 px-3 text-center">Delay</th>
                        <th class="py-2.5 px-3 text-center">Leg</th>
                        <th class="py-2.5 px-3 text-center">D/I</th>
                        <th class="py-2.5 px-3">City</th>
                        <th class="py-2.5 px-3 text-right">Adult</th>
                        <th class="py-2.5 px-3 text-right">Child</th>
                        <th class="py-2.5 px-3 text-right">Infant</th>
                        <th class="py-2.5 px-3 text-right">Transit</th>
                        <th class="py-2.5 px-3 text-right">Crew</th>
                        <th class="py-2.5 px-3 text-center">Status Validasi</th>
                        <th class="py-2.5 px-3 text-center">APPS Link</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    <template x-for="(r, idx) in getPaginatedAppsRecords(appsDiscrepancyOnly)" :key="idx">
                        <tr class="hover:bg-cyan-50/30 transition"
                            :class="(Number(r.adult || 0) === 0 || !r.offBlockTime || r.offBlockTime.trim() === '') ? 'bg-rose-50/40' : ''">
                            <td class="py-2 px-3 text-slate-400 font-sans" x-text="((pagination.current_page - 1) * pagination.per_page) + idx + 1"></td>
                            <td class="py-2 px-3 font-sans font-bold text-slate-900">
                                <span class="text-blue-700" x-text="(r.airline || '') + (r.flightNo ? ' ' + r.flightNo : '')"></span>
                            </td>
                            <td class="py-2 px-3 text-slate-600" x-text="r.stad || r.sobt || '-'"></td>
                            <td class="py-2 px-3 font-bold"
                                :class="(!r.offBlockTime || r.offBlockTime.trim() === '' || r.offBlockTime.includes('-0001')) ? 'text-rose-600 italic' : 'text-slate-900'"
                                x-text="(!r.offBlockTime || r.offBlockTime.trim() === '' || r.offBlockTime.includes('-0001')) ? 'MISSING' : r.offBlockTime"></td>
                            <td class="py-2 px-3 text-center">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="getAppsDelayMinutes(r) > 15 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'"
                                      x-text="getAppsDelayDisplay(r)">-</span>
                            </td>
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800" x-text="r.leg || 'D'"></span>
                            </td>
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="(r.di === 'I' || r.di === 'INTERNATIONAL') ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800'"
                                      x-text="r.di || 'D'"></span>
                            </td>
                            <td class="py-2 px-3 font-sans font-semibold text-slate-700" x-text="r.city || r.city_1"></td>
                            <td class="py-2 px-3 text-right"
                                :class="Number(r.adult || 0) === 0 ? 'text-rose-600 font-black' : 'text-slate-800'"
                                x-text="Number(r.adult || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-600" x-text="Number(r.child || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-600" x-text="Number(r.infant || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-600" x-text="Number(r.transit || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-purple-700 font-bold" x-text="Number(r.crew || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                                      :class="getAppsValidationBadgeClass(r)"
                                      x-text="getAppsValidationStatus(r)"></span>
                            </td>
                            <td class="py-2 px-3 text-center font-sans">
                                <template x-if="r.linkHref || r.verifyUrl">
                                    <a :href="r.linkHref || r.verifyUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 hover:underline font-bold inline-flex items-center gap-1 text-[10px]">
                                        <span>Verify</span>
                                        <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </template>
                                <template x-if="!r.linkHref && !r.verifyUrl">
                                    <span class="text-slate-400 text-[10px]">-</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination bar for APPS --}}
        <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
            <span class="text-slate-500">
                Page <span class="font-bold text-slate-800" x-text="pagination.current_page">1</span> of <span class="font-bold text-slate-800" x-text="Math.ceil(getFilteredAppsRecords(appsDiscrepancyOnly).length / pagination.per_page) || 1">1</span>
            </span>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 disabled:opacity-40">Previous</button>
                <button type="button" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page >= Math.ceil(getFilteredAppsRecords(appsDiscrepancyOnly).length / pagination.per_page)" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>

</div>
