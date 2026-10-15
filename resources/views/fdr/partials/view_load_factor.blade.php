{{-- ══ POLYMORPHIC VIEW: SEAT LOAD FACTOR (13 COLUMNS) ══════════════════════ --}}
<div class="space-y-5" id="fdr-load-factor-view">

    {{-- Banner Notice --}}
    <div class="bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-blue-500/10 border border-emerald-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-black text-xs shadow-2xs">
                <span>LF</span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-900">Seat Load Factor (SLF) Intelligence Mode</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">13 COLUMNS</span>
                </div>
                <p class="text-[11px] text-slate-500 mt-0.5">Commercial passenger utilization, seat capacities, and airline load factor distribution</p>
            </div>
        </div>
        <div class="text-[11px] font-bold text-slate-600">
            Total Movements: <span class="text-slate-900 font-mono" x-text="getLfTotalFlights().toLocaleString()"></span>
        </div>
    </div>

    {{-- 5 KPI CARDS --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        {{-- 1. Total Flights --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                </div>
                <span class="text-[10px] font-bold text-blue-600 uppercase">Movement</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Flights</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getLfTotalFlights().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    Arr: <span x-text="getLfArrivals()">0</span> • Dep: <span x-text="getLfDepartures()">0</span>
                </div>
            </div>
        </div>

        {{-- 2. Total Seat Capacity --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                </div>
                <span class="text-[10px] font-bold text-indigo-600 uppercase">Capacity</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Seat Capacity</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getLfTotalSeatCap().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    Seats Offered in Period
                </div>
            </div>
        </div>

        {{-- 3. Total Commercial Pax --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                </div>
                <span class="text-[10px] font-bold text-emerald-600 uppercase">Passengers</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Commercial Pax</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getLfTotalPax().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    Commercial Passengers Flown
                </div>
            </div>
        </div>

        {{-- 4. Average SLF (%) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                </div>
                <span class="text-[10px] font-bold text-purple-600 uppercase">Utilization</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Average SLF (%)</div>
                <div class="text-xl font-black text-slate-900 tracking-tight" x-text="getLfAvgSlf() + '%'">0.0%</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    Pax / Capacity Utilization
                </div>
            </div>
        </div>

        {{-- 5. High vs Low SLF Flights --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z"/></svg>
                </div>
                <span class="text-[10px] font-bold text-emerald-600">High / Low</span>
            </div>
            <div class="mt-2">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">High SLF vs Low SLF</div>
                <div class="text-xl font-black text-slate-900 tracking-tight truncate">
                    <span class="text-emerald-600" x-text="getLfHighSlfCount()">0</span> <span class="text-slate-300 font-normal">/</span> <span class="text-rose-500" x-text="getLfLowSlfCount()">0</span>
                </div>
                <div class="text-[10px] font-semibold text-slate-500 mt-0.5 truncate">
                    &gt;80% High • &lt;50% Low
                </div>
            </div>
        </div>
    </div>

    {{-- 4 CHARTS (2x2 GRID) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        {{-- Chart 1: SLF Distribution Histogram --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">SLF Distribution Histogram</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Flight load buckets</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrSlfHistChart"></canvas>
            </div>
        </div>

        {{-- Chart 2: Capacity vs Commercial Pax by Route --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Capacity vs Commercial Pax by Route</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Top commercial routes</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrSlfRouteChart"></canvas>
            </div>
        </div>

        {{-- Chart 3: SLF by Aircraft Type & Airline --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">SLF by Aircraft Type &amp; Airline</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Fleet efficiency</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrSlfAircraftChart"></canvas>
            </div>
        </div>

        {{-- Chart 4: Hourly Scheduled Seat Capacity Trend --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Hourly Scheduled Seat Capacity Trend</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">24-hour departure profile</span>
            </div>
            <div class="h-60 w-full relative">
                <canvas id="fdrSlfHourlyChart"></canvas>
            </div>
        </div>

    </div>

    {{-- 13-COLUMN TABLE WITH VISUAL PROGRESS BARS ON SLF --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Seat Load Factor Operational Registry (13 Columns)</h3>
                <p class="text-[11px] text-slate-400">Strict OASYS Seat Load Factor export schema</p>
            </div>
            <div class="text-xs font-bold text-slate-600">
                Total Rows: <span class="font-mono text-slate-900" x-text="getLfRecords().length"></span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[10px] uppercase font-black text-slate-500 tracking-wider">
                        <th class="py-2.5 px-3">#</th>
                        <th class="py-2.5 px-3">Airlines</th>
                        <th class="py-2.5 px-3">Flight Number</th>
                        <th class="py-2.5 px-3">Scheduled Time</th>
                        <th class="py-2.5 px-3">Aircraft Type</th>
                        <th class="py-2.5 px-3">Reg No</th>
                        <th class="py-2.5 px-3 text-center">Leg</th>
                        <th class="py-2.5 px-3">Arrival Route</th>
                        <th class="py-2.5 px-3">Departure Route</th>
                        <th class="py-2.5 px-3 text-right">Seat Cap</th>
                        <th class="py-2.5 px-3 text-right">Pax</th>
                        <th class="py-2.5 px-3 text-center">SLF (%)</th>
                        <th class="py-2.5 px-3 text-center">Airport</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    <template x-for="(r, idx) in getPaginatedLfRecords()" :key="idx">
                        <tr class="hover:bg-blue-50/40 transition">
                            <td class="py-2.5 px-3 text-slate-400 font-sans" x-text="((pagination.current_page - 1) * pagination.per_page) + idx + 1"></td>
                            <td class="py-2.5 px-3 font-sans font-bold text-slate-800" x-text="r.airlines || r.air_line"></td>
                            <td class="py-2.5 px-3 font-bold text-blue-600" x-text="r.flightNumber || r.flight_no"></td>
                            <td class="py-2.5 px-3 text-slate-600" x-text="r.scheduledTime || r.sibt || r.sobt"></td>
                            <td class="py-2.5 px-3 font-sans text-slate-700 font-semibold" x-text="r.typeOfAircraft || r.aircraft_type || r.desc"></td>
                            <td class="py-2.5 px-3 text-slate-700" x-text="r.aircraftRegNo || r.reg_no"></td>
                            <td class="py-2.5 px-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                      :class="(r.leg === 'A' || r.leg === 'ARRIVAL') ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'"
                                      x-text="r.leg"></span>
                            </td>
                            <td class="py-2.5 px-3 font-sans text-slate-700" x-text="r.arrivalRoute || r.city_1"></td>
                            <td class="py-2.5 px-3 font-sans text-slate-700" x-text="r.departureRoute || r.city_2"></td>
                            <td class="py-2.5 px-3 text-right text-slate-800 font-bold" x-text="Number(r.seatCapacity || r.cap || 0).toLocaleString()"></td>
                            <td class="py-2.5 px-3 text-right text-emerald-700 font-bold" x-text="Number(r.pax || r.load || 0).toLocaleString()"></td>
                            <td class="py-2.5 px-3 text-center font-sans">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-16 bg-slate-100 rounded-full h-2 overflow-hidden">
                                        <div class="h-2 rounded-full"
                                             :style="'width: ' + Math.min(100, parseFloat(r.slf || r.load_factor || 0)) + '%; background-color: ' + (parseFloat(r.slf || r.load_factor || 0) >= 80 ? '#10B981' : (parseFloat(r.slf || r.load_factor || 0) >= 60 ? '#3B82F6' : '#EF4444'))"></div>
                                    </div>
                                    <span class="font-bold text-xs"
                                          :class="parseFloat(r.slf || r.load_factor || 0) >= 80 ? 'text-emerald-600' : (parseFloat(r.slf || r.load_factor || 0) >= 60 ? 'text-blue-600' : 'text-rose-600')"
                                          x-text="(typeof r.slf === 'string' && r.slf.includes('%')) ? r.slf : (parseFloat(r.slf || r.load_factor || 0).toFixed(1) + '%')"></span>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-center font-bold text-slate-700 font-sans" x-text="r.airport || 'CGK'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

</div>
