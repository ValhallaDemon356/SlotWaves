{{-- ══ POLYMORPHIC VIEW: OASYS VS APPS DEPARTURE AUDIT (16 COLUMNS) ══════════ --}}
<div class="space-y-5" id="fdr-apps-audit-view">

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
            Audit Status: <span class="font-mono" :class="getAppsDeviation() === 0 ? 'text-emerald-700 font-black' : 'text-rose-600 font-black'" x-text="getAppsDeviation() === 0 ? 'BALANCED (DEV = 0) ✓' : 'DEVIATION DETECTED ⚠'"></span>
        </div>
    </div>

    {{-- 5 AUDIT KPI CARDS --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        
        {{-- 1. OASYS Flight Departure --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                </div>
                <span class="text-[10px] font-bold text-blue-600 uppercase">OASYS DEP</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">OASYS Flight Departure</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getAppsFltDep().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                    FLT DEP Manifest
                </div>
            </div>
        </div>

        {{-- 2. APPS Recorded --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <span class="text-[10px] font-bold text-cyan-600 uppercase">APPS SYSTEM</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">APPS Recorded</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getAppsRecorded().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5">
                    APPS Ingested
                </div>
            </div>
        </div>

        {{-- 3. Deviation (DEV) --}}
        <div class="bg-white rounded-2xl border p-3.5 shadow-2xs flex flex-col justify-between"
             :class="getAppsDeviation() === 0 ? 'border-emerald-200 bg-emerald-50/20' : 'border-rose-300 bg-rose-50/30'">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center"
                     :class="getAppsDeviation() === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                </div>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
                      :class="getAppsDeviation() === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                      x-text="getAppsDeviation() === 0 ? 'MATCH' : 'MISMATCH'"></span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Deviation (DEV)</div>
                <div class="text-xl font-black tracking-tight"
                     :class="getAppsDeviation() === 0 ? 'text-emerald-700' : 'text-rose-600'"
                     x-text="getAppsDeviation()">0</div>
                <div class="text-[10px] font-semibold mt-0.5"
                     :class="getAppsDeviation() === 0 ? 'text-emerald-600' : 'text-rose-500'"
                     x-text="getAppsDeviation() === 0 ? 'Zero Variance' : getAppsDeviation() + ' Flights Dev'"></div>
            </div>
        </div>

        {{-- 4. Total Departure Pax --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                </div>
                <span class="text-[10px] font-bold text-indigo-600 uppercase">DEP PAX</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Departure Pax</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getAppsTotalPax().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    Commercial Dep Volume
                </div>
            </div>
        </div>

        {{-- 5. Operating + Extra Crew --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                </div>
                <span class="text-[10px] font-bold text-purple-600 uppercase">CREW</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Operating + Extra Crew</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getAppsTotalCrew().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    Flight &amp; Cabin Personnel
                </div>
            </div>
        </div>

    </div>

    {{-- NESTED RECONCILIATION MATRIX (Parsed from Footer Sub-Table) --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-cyan-600"></span>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Nested Reconciliation Audit Matrix</h3>
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
                        <th class="py-2.5 px-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    {{-- Normal Dom --}}
                    <tr>
                        <td class="py-2 px-3 font-sans font-bold text-slate-800">Normal Domestic (Dom)</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('normalDom', 'fltDep')">0</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('normalDom', 'apps')">0</td>
                        <td class="py-2 px-3 text-center font-bold"
                            :class="getAppsMatrixVal('normalDom', 'dev') === 0 ? 'text-emerald-600' : 'text-rose-600'"
                            x-text="getAppsMatrixVal('normalDom', 'dev')">0</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                  :class="getAppsMatrixVal('normalDom', 'dev') === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                  x-text="getAppsMatrixVal('normalDom', 'dev') === 0 ? 'OK' : 'MISMATCH'"></span>
                        </td>
                    </tr>
                    {{-- Normal Int --}}
                    <tr>
                        <td class="py-2 px-3 font-sans font-bold text-slate-800">Normal International (Int)</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('normalInt', 'fltDep')">0</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('normalInt', 'apps')">0</td>
                        <td class="py-2 px-3 text-center font-bold"
                            :class="getAppsMatrixVal('normalInt', 'dev') === 0 ? 'text-emerald-600' : 'text-rose-600'"
                            x-text="getAppsMatrixVal('normalInt', 'dev')">0</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                  :class="getAppsMatrixVal('normalInt', 'dev') === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                  x-text="getAppsMatrixVal('normalInt', 'dev') === 0 ? 'OK' : 'MISMATCH'"></span>
                        </td>
                    </tr>
                    {{-- Transit Dom --}}
                    <tr>
                        <td class="py-2 px-3 font-sans font-bold text-slate-800">Transit Domestic (Dom)</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('transitDom', 'fltDep')">0</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('transitDom', 'apps')">0</td>
                        <td class="py-2 px-3 text-center font-bold"
                            :class="getAppsMatrixVal('transitDom', 'dev') === 0 ? 'text-emerald-600' : 'text-rose-600'"
                            x-text="getAppsMatrixVal('transitDom', 'dev')">0</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                  :class="getAppsMatrixVal('transitDom', 'dev') === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                  x-text="getAppsMatrixVal('transitDom', 'dev') === 0 ? 'OK' : 'MISMATCH'"></span>
                        </td>
                    </tr>
                    {{-- Transit Int --}}
                    <tr>
                        <td class="py-2 px-3 font-sans font-bold text-slate-800">Transit International (Int)</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('transitInt', 'fltDep')">0</td>
                        <td class="py-2 px-3 text-right" x-text="getAppsMatrixVal('transitInt', 'apps')">0</td>
                        <td class="py-2 px-3 text-center font-bold"
                            :class="getAppsMatrixVal('transitInt', 'dev') === 0 ? 'text-emerald-600' : 'text-rose-600'"
                            x-text="getAppsMatrixVal('transitInt', 'dev')">0</td>
                        <td class="py-2 px-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                  :class="getAppsMatrixVal('transitInt', 'dev') === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                  x-text="getAppsMatrixVal('transitInt', 'dev') === 0 ? 'OK' : 'MISMATCH'"></span>
                        </td>
                    </tr>
                    {{-- Total Row --}}
                    <tr class="bg-slate-50 font-black">
                        <td class="py-2.5 px-3 font-sans text-slate-900 uppercase">Total Reconciled</td>
                        <td class="py-2.5 px-3 text-right text-slate-900" x-text="getAppsMatrixVal('total', 'fltDep')">0</td>
                        <td class="py-2.5 px-3 text-right text-slate-900" x-text="getAppsMatrixVal('total', 'apps')">0</td>
                        <td class="py-2.5 px-3 text-center"
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

    {{-- 2 CHARTS --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        {{-- Chart 1: Departure OTP Variance (STAD vs OFF BLOCK TIME) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-cyan-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Departure OTP Variance (STAD vs OFF BLOCK)</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Off-block punctuality</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrAppsVarianceChart"></canvas>
            </div>
        </div>

        {{-- Chart 2: Departure Pax by Destination City --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-sky-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Departure Pax by Destination City</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Top passenger destinations</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrAppsDestChart"></canvas>
            </div>
        </div>

    </div>

    {{-- 16-COLUMN DEPARTURE VS APPS REGISTRY TABLE --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">OASYS Departure vs APPS Manifest Registry (16 Columns)</h3>
                <p class="text-[11px] text-slate-400">Individual flight departure manifest with passenger class breakdowns</p>
            </div>
            <div class="text-xs font-bold text-slate-600">
                Total Rows: <span class="font-mono text-slate-900" x-text="getAppsRecords().length"></span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[10px] uppercase font-black text-slate-500 tracking-wider">
                        <th class="py-2.5 px-3">#</th>
                        <th class="py-2.5 px-3">Airline &amp; Flight</th>
                        <th class="py-2.5 px-3">STAD</th>
                        <th class="py-2.5 px-3">Off Block Time</th>
                        <th class="py-2.5 px-3 text-center">Leg</th>
                        <th class="py-2.5 px-3 text-center">D/I</th>
                        <th class="py-2.5 px-3">City</th>
                        <th class="py-2.5 px-3 text-right">Adult</th>
                        <th class="py-2.5 px-3 text-right">Child</th>
                        <th class="py-2.5 px-3 text-right">Infant</th>
                        <th class="py-2.5 px-3 text-right">Transit</th>
                        <th class="py-2.5 px-3 text-right">Transfer</th>
                        <th class="py-2.5 px-3 text-right">Divert</th>
                        <th class="py-2.5 px-3 text-right">Missed</th>
                        <th class="py-2.5 px-3 text-right">Crew</th>
                        <th class="py-2.5 px-3 text-right">Ex. Crew</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    <template x-for="(r, idx) in getPaginatedAppsRecords()" :key="idx">
                        <tr class="hover:bg-cyan-50/30 transition">
                            <td class="py-2 px-3 text-slate-400 font-sans" x-text="((pagination.current_page - 1) * pagination.per_page) + idx + 1"></td>
                            <td class="py-2 px-3 font-sans font-bold text-blue-600" x-text="r.airline || r.air_line"></td>
                            <td class="py-2 px-3 text-slate-600" x-text="r.stad || r.sobt"></td>
                            <td class="py-2 px-3 font-bold text-slate-900" x-text="r.offBlockTime || r.aobt"></td>
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800" x-text="r.leg || 'D'"></span>
                            </td>
                            <td class="py-2 px-3 text-center font-sans">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="(r.di === 'I' || r.di === 'INTERNATIONAL') ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800'"
                                      x-text="r.di || 'D'"></span>
                            </td>
                            <td class="py-2 px-3 font-sans font-semibold text-slate-700" x-text="r.city || r.city_1"></td>
                            <td class="py-2 px-3 text-right text-slate-800" x-text="Number(r.adult || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-600" x-text="Number(r.child || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-600" x-text="Number(r.infant || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-600" x-text="Number(r.transit || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-600" x-text="Number(r.transfer || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-500" x-text="Number(r.divert || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-500" x-text="Number(r.missed || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-purple-700 font-bold" x-text="Number(r.crew || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-purple-600" x-text="Number(r.exCrew || 0).toLocaleString()"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

</div>
