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
                <p class="text-[11px] text-slate-500 mt-0.5">Commercial passenger utilization, seat capacity efficiency, and fleet load factor distribution</p>
            </div>
        </div>
        <div class="text-[11px] font-bold text-slate-600">
            Total Movements: <span class="text-slate-900 font-mono font-bold" x-text="getLfTotalFlights().toLocaleString()">0</span>
        </div>
    </div>

    {{-- ══ 4 CORE KPI SUMMARY CARDS ═══════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        {{-- 1. Rata-rata Load Factor (%) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 font-mono">Target &ge;75%</span>
            </div>
            <div class="mt-3">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Rata-rata Load Factor (%)</div>
                <div class="text-2xl font-black text-slate-900 tracking-tight" x-text="getLfAvgSlf() + '%'">0.0%</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-1 truncate">
                    Weighted SLF • Simple Avg: <span class="font-bold text-slate-700 font-mono" x-text="getLfSimpleAvgSlf() + '%'">0.0%</span>
                </div>
            </div>
        </div>

        {{-- 2. Total Kapasitas Kursi vs Realisasi Penumpang --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 font-mono" x-text="getLfTotalFlights() + ' Flights'"></span>
            </div>
            <div class="mt-3">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Kapasitas Kursi vs Penumpang</div>
                <div class="text-2xl font-black text-slate-900 tracking-tight" x-text="getLfTotalPax().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-1 truncate">
                    Offered Seats: <span class="font-bold text-slate-700 font-mono" x-text="getLfTotalSeatCap().toLocaleString()">0</span>
                </div>
            </div>
        </div>

        {{-- 3. Total Kursi Kosong (Unsold / Empty Seats) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 font-mono" x-text="getLfEmptySeatsRatio() + '% Empty'"></span>
            </div>
            <div class="mt-3">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Kursi Kosong</div>
                <div class="text-2xl font-black text-amber-600 tracking-tight" x-text="getLfEmptySeats().toLocaleString()">0</div>
                <div class="text-[10px] font-semibold text-slate-500 mt-1 truncate">
                    Unsold Seat Ratio: <span class="font-bold text-amber-700 font-mono" x-text="getLfEmptySeatsRatio() + '%'">0%</span>
                </div>
            </div>
        </div>

        {{-- 4. Rute Load Factor Tertinggi & Terendah --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 font-mono">Extreme Routes</span>
            </div>
            <div class="mt-3 space-y-1">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Rute Ekstrem (Max / Min)</div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-mono font-bold text-emerald-700 truncate" x-text="getLfTopRoute().route || 'N/A'">-</span>
                    <span class="font-mono font-black text-emerald-700" x-text="getLfTopRoute().slf || '0%'"></span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-mono font-bold text-rose-600 truncate" x-text="getLfLowRoute().route || 'N/A'">-</span>
                    <span class="font-mono font-black text-rose-600" x-text="getLfLowRoute().slf || '0%'"></span>
                </div>
            </div>
        </div>

    </div>

    {{-- ══ VISUALISASI & GRAFIK UTAMA (3 GRAFIK) ═══════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        {{-- Grafik 1: Peringkat Load Factor per Rute (Horizontal Bar Chart with Target Line at 75%) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Peringkat Load Factor per Rute (Arr &rarr; Dep)</h3>
                </div>
                <span class="text-[10px] font-bold text-emerald-700 font-mono bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Target Line: 75%</span>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="fdrSlfRankChart"></canvas>
            </div>
        </div>

        {{-- Grafik 2: Kapasitas Kursi vs Realisasi Penumpang (Combo Chart: Bars + LF Line) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Kapasitas Kursi vs Realisasi Penumpang</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Combo Bar &amp; Line</span>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="fdrSlfComboChart"></canvas>
            </div>
        </div>

    </div>

    {{-- Grafik 3: Analisis Load Factor per Tipe Pesawat --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Analisis Load Factor per Tipe Pesawat (Fleet Performance)</h3>
            </div>
            <span class="text-[10px] font-bold text-slate-400">Aircraft efficiency breakdown</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[10px] uppercase font-black text-slate-500 tracking-wider">
                        <th class="py-2.5 px-3">Tipe Pesawat</th>
                        <th class="py-2.5 px-3 text-right">Total Flights</th>
                        <th class="py-2.5 px-3 text-right">Offered Seats</th>
                        <th class="py-2.5 px-3 text-right">Pax Carried</th>
                        <th class="py-2.5 px-3 text-right">Empty Seats</th>
                        <th class="py-2.5 px-3 text-center">Avg Load Factor (%)</th>
                        <th class="py-2.5 px-3">Status Evaluasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    <template x-for="fleet in getLfFleetAnalysis()" :key="fleet.type">
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-2 px-3 font-bold text-slate-800" x-text="fleet.type"></td>
                            <td class="py-2 px-3 text-right" x-text="fleet.flights.toLocaleString()"></td>
                            <td class="py-2 px-3 text-right" x-text="fleet.seats.toLocaleString()"></td>
                            <td class="py-2 px-3 text-right font-bold text-slate-900" x-text="fleet.pax.toLocaleString()"></td>
                            <td class="py-2 px-3 text-right text-slate-500" x-text="(fleet.seats - fleet.pax).toLocaleString()"></td>
                            <td class="py-2 px-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                      :class="parseFloat(fleet.slf) >= 75 ? 'bg-emerald-100 text-emerald-800' : (parseFloat(fleet.slf) >= 50 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800')"
                                      x-text="fleet.slf + '%'"></span>
                            </td>
                            <td class="py-2 px-3 font-sans text-[10px]">
                                <span :class="parseFloat(fleet.slf) >= 75 ? 'text-emerald-700 font-bold' : (parseFloat(fleet.slf) >= 50 ? 'text-amber-700 font-medium' : 'text-rose-600 font-bold')"
                                      x-text="parseFloat(fleet.slf) >= 75 ? 'Optimal Utilization' : (parseFloat(fleet.slf) >= 50 ? 'Moderate Occupancy' : 'Capacity Underutilized')"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══ TABEL DINAMIS: SEAT LOAD FACTOR REGISTRY (13 KOLOM) ════════════════ --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Seat Load Factor Operational Registry (13 Columns)</h3>
                <p class="text-[11px] text-slate-400">Searchable and paginated flight level load factor table with color-coded badges</p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> &ge;75% Tinggi</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> 50%&ndash;74% Sedang</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500"></span> &lt;50% Rendah</span>
                <span class="font-bold text-slate-600 ml-2">Total Rows: <span class="font-mono text-slate-900" x-text="getLfRecords().length"></span></span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-[10px] uppercase font-black text-slate-500 tracking-wider">
                        <th class="py-2.5 px-3">No</th>
                        <th class="py-2.5 px-3">Flight Number</th>
                        <th class="py-2.5 px-3">Rute (Arr &rarr; Dep)</th>
                        <th class="py-2.5 px-3 text-center">Leg</th>
                        <th class="py-2.5 px-3">Scheduled Time</th>
                        <th class="py-2.5 px-3">Tipe Pesawat</th>
                        <th class="py-2.5 px-3">Reg No</th>
                        <th class="py-2.5 px-3 text-right">Seat Capacity</th>
                        <th class="py-2.5 px-3 text-right">Pax Carried</th>
                        <th class="py-2.5 px-3 text-center">Load Factor (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                    <template x-for="(r, idx) in getPaginatedLfRecords()" :key="idx">
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-2 px-3 text-slate-400" x-text="r.no || (idx + 1)"></td>
                            <td class="py-2 px-3 font-bold text-slate-900 font-sans" x-text="(r.airline || '') + ' ' + (r.flightNumber || r.flight_no || 'N/A')"></td>
                            <td class="py-2 px-3 text-slate-700">
                                <span class="font-bold text-blue-700" x-text="r.arrivalRoute || r.route || 'N/A'"></span>
                                <span class="text-slate-400 mx-1">&rarr;</span>
                                <span class="font-bold text-indigo-700" x-text="r.departureRoute || r.route || 'N/A'"></span>
                            </td>
                            <td class="py-2 px-3 text-center">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                      :class="(r.leg === 'A' || r.leg === 'ARRIVAL') ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'"
                                      x-text="r.leg || 'N/A'"></span>
                            </td>
                            <td class="py-2 px-3 text-slate-600" x-text="r.scheduledTime || r.sibt || r.sobt || 'N/A'"></td>
                            <td class="py-2 px-3 font-sans text-slate-700" x-text="r.aircraftType || r.typeOfAircraft || r.desc || 'N/A'"></td>
                            <td class="py-2 px-3 text-slate-600" x-text="r.aircraftReg || r.regNo || r.reg_no || 'N/A'"></td>
                            <td class="py-2 px-3 text-right font-bold text-slate-800" x-text="(Number(r.seatCapacity || r.cap) || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-right font-bold text-slate-900" x-text="(Number(r.pax || r.load) || 0).toLocaleString()"></td>
                            <td class="py-2 px-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black"
                                      :class="parseFloat(r.slfPercent || r.slf || r.load_factor || 0) >= 75 ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : (parseFloat(r.slfPercent || r.slf || r.load_factor || 0) >= 50 ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-rose-100 text-rose-800 border border-rose-300')"
                                      x-text="(Number(r.slfPercent || r.slf || r.load_factor || 0)).toFixed(1) + '%'"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination bar for Load Factor --}}
        <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
            <span class="text-slate-500">
                Page <span class="font-bold text-slate-800" x-text="pagination.current_page">1</span> of <span class="font-bold text-slate-800" x-text="pagination.total_pages">1</span>
            </span>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 disabled:opacity-40">Previous</button>
                <button type="button" @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.total_pages" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>

</div>
