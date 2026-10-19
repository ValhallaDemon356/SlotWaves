{{-- ══ ROUTE ANALYTICS, SPECIAL SUFFIXES & INTERACTIVE NETWORK MAP ═════════ --}}
<div class="space-y-5" id="fdr-route-network-section" x-data="{ hoveredRoute: null, hoveredAirport: null }">

    {{-- ══ SPECIAL FLIGHT SUFFIX & IRREGULARITY CLASSIFICATION ═════════════════ --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                </div>
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                        Special Flight Suffix &amp; Irregularity Classification
                    </h3>
                    <p class="text-[10px] text-slate-400">Official OASYS Flight Suffix Dictionary &amp; Leg Subtype telemetry</p>
                </div>
            </div>
            <div class="text-[11px] font-bold text-slate-500">
                Special Movements: <span class="text-purple-700 font-mono font-black" x-text="getSpecialSuffixTotalCount()">0</span>
            </div>
        </div>

        {{-- Leg Subtypes: SCHED / UNSCHED / CARGO (3 Core Cards on Top) --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div class="p-3.5 rounded-xl bg-blue-50/60 border border-blue-200 flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase text-blue-800">Scheduled Commercial (SCHED)</div>
                    <div class="text-lg font-black text-blue-900 mt-0.5" x-text="getLegSubtypeCount('SCHED').toLocaleString()">0</div>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-200/60 text-blue-800 font-mono" x-text="getLegSubtypePct('SCHED')">0%</span>
            </div>

            <div class="p-3.5 rounded-xl bg-amber-50/60 border border-amber-200 flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase text-amber-800">Unscheduled / Charter (UNSCHED)</div>
                    <div class="text-lg font-black text-amber-900 mt-0.5" x-text="getLegSubtypeCount('UNSCHED').toLocaleString()">0</div>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-200/60 text-amber-800 font-mono" x-text="getLegSubtypePct('UNSCHED')">0%</span>
            </div>

            <div class="p-3.5 rounded-xl bg-purple-50/60 border border-purple-200 flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase text-purple-800">Dedicated Cargo (CARGO)</div>
                    <div class="text-lg font-black text-purple-900 mt-0.5" x-text="getLegSubtypeCount('CARGO').toLocaleString()">0</div>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-purple-200/60 text-purple-800 font-mono" x-text="getLegSubtypePct('CARGO')">0%</span>
            </div>
        </div>

        {{-- Suffix Breakdown Pills Rendered Below When Present --}}
        <div x-show="getSuffixDistribution().length > 0" class="pt-2 border-t border-slate-100 space-y-2">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Flight Number Suffix Breakdown</div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
                <template x-for="item in getSuffixDistribution()" :key="item.suffix">
                    <div class="p-2 rounded-xl border border-slate-200/80 bg-slate-50/60 hover:bg-slate-100/70 transition flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="w-5 h-5 rounded-md font-black text-[10px] flex items-center justify-center font-mono"
                                  :class="item.colorClass"
                                  x-text="item.suffix"></span>
                            <span class="text-[10px] font-mono font-bold text-slate-700" x-text="item.count + ' flt'"></span>
                        </div>
                        <div class="mt-1.5">
                            <div class="text-[9.5px] font-bold text-slate-800 truncate" x-text="item.label"></div>
                            <div class="text-[8.5px] text-slate-400 truncate" x-text="item.category"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ══ ROUTE ANALYTICS (TOP 10 BUSIEST, MOST DELAYED, HIGHEST SLF) ═════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        {{-- Card 1: Top 10 Busiest Routes --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Top 10 Busiest Routes</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">By flight movements</span>
            </div>

            <div class="space-y-1.5 max-h-72 overflow-y-auto pr-1">
                <template x-for="(r, idx) in getBusiestRoutes()" :key="r.route || idx">
                    <div class="flex items-center justify-between p-2 rounded-xl bg-slate-50/60 border border-slate-100 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md bg-blue-100 text-blue-800 font-bold text-[10px] flex items-center justify-center font-sans" x-text="idx + 1"></span>
                            <div>
                                <div class="font-black text-slate-800 font-mono" x-text="r.route"></div>
                                <div class="text-[9.5px] text-slate-400 font-sans" x-text="(r.pax != null ? r.pax : (r.passengers || r.totalPax || 0)).toLocaleString() + ' Pax • LF ' + (r.avg_load_factor || (r.avgLf != null ? r.avgLf + '%' : (r.lf != null ? r.lf + '%' : '0%')))"></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="font-black font-mono text-blue-700" x-text="(r.count || r.flights || r.movements || r.total_flights || 0) + ' flights'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Card 2: Top 10 Most Delayed Routes --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Top 10 Most Delayed Routes</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Punctuality bottlenecks</span>
            </div>

            <div class="space-y-1.5 max-h-72 overflow-y-auto pr-1">
                <template x-for="(r, idx) in getMostDelayedRoutes()" :key="r.route || idx">
                    <div class="flex items-center justify-between p-2 rounded-xl bg-rose-50/40 border border-rose-100 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md bg-rose-100 text-rose-800 font-bold text-[10px] flex items-center justify-center font-sans" x-text="idx + 1"></span>
                            <div>
                                <div class="font-black text-slate-800 font-mono" x-text="r.route"></div>
                                <div class="text-[9.5px] text-slate-400 font-sans" x-text="(r.delayed_flights != null ? r.delayed_flights : (r.delayedFlights || 0)) + ' delayed of ' + (r.total_flights || r.flights || r.count || 0) + ' flights (' + ((typeof r.delay_rate === 'string' && r.delay_rate.includes('%')) ? r.delay_rate : ((r.delay_rate != null ? r.delay_rate : (r.delayRate != null ? r.delayRate : (r.delayPct || 0))) + '%')) + ')'"></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="font-black font-mono text-rose-700" x-text="'+' + (r.avg_delay != null ? r.avg_delay : (r.avgDelay != null ? r.avgDelay : (r.avg_delay_min || 0))) + ' min'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Card 3: Top 10 Highest SLF Routes --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Top 10 Highest SLF Routes</h3>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Highest commercial load</span>
            </div>

            <div class="space-y-1.5 max-h-72 overflow-y-auto pr-1">
                <template x-for="(r, idx) in getHighestSlfRoutes()" :key="r.route || idx">
                    <div class="flex items-center justify-between p-2 rounded-xl bg-emerald-50/40 border border-emerald-100 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-md bg-emerald-100 text-emerald-800 font-bold text-[10px] flex items-center justify-center font-sans" x-text="idx + 1"></span>
                            <div>
                                <div class="font-black text-slate-800 font-mono" x-text="r.route"></div>
                                <div class="text-[9.5px] text-slate-400 font-sans" x-text="(r.pax != null ? r.pax : (r.passengers || r.totalPax || 0)).toLocaleString() + ' / ' + (r.capacity || r.totalCapacity || 0).toLocaleString() + ' seats'"></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="font-black font-mono text-emerald-700" x-text="(typeof r.slf === 'string' && r.slf.includes('%')) ? r.slf : ((r.avg_load_factor || (r.avgLf != null ? r.avgLf + '%' : (r.slf != null ? r.slf + '%' : '0%'))))"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>

    {{-- ══ INTERACTIVE INJOURNEY ROUTE NETWORK MAP (SVG 1000x420) ══════════════ --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                </div>
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                        InJourney Airports Interactive Route Network
                    </h3>
                    <p class="text-[10px] text-slate-400">Directional quadratic Bezier trajectories &amp; airport nodes across 35 InJourney airports</p>
                </div>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse"></span> Active Hub</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Connected Station</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-600"></span> Regional Station</span>
                <span class="flex items-center gap-1.5"><span class="w-4 h-0.5 bg-cyan-400"></span> Trajectory</span>
            </div>
        </div>

        {{-- SVG Map Canvas (min-h-[380px] w-full) --}}
        <div class="w-full min-h-[380px] h-[380px] rounded-2xl bg-slate-950 relative overflow-hidden flex items-center justify-center p-2 select-none shadow-2xl border border-slate-900" id="injourney-network-container">
            <svg class="w-full h-full" viewBox="0 0 1000 420" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <linearGradient id="routeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#38BDF8" stop-opacity="0.9" />
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.4" />
                    </linearGradient>
                    <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                        <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
                        <feMerge>
                            <feMergeNode in="coloredBlur"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                    <marker id="routeArrow" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse">
                        <path d="M 0 1.5 L 8 5 L 0 8.5 z" fill="#38BDF8" opacity="0.95"/>
                    </marker>
                </defs>

                {{-- Archipelago background grid with coordinate labels --}}
                <g opacity="0.08" stroke="#FFFFFF" stroke-width="0.5">
                    <line x1="30" y1="70" x2="970" y2="70" />
                    <line x1="30" y1="140" x2="970" y2="140" />
                    <line x1="30" y1="210" x2="970" y2="210" />
                    <line x1="30" y1="280" x2="970" y2="280" />
                    <line x1="30" y1="350" x2="970" y2="350" />
                    <line x1="120" y1="30" x2="120" y2="390" />
                    <line x1="280" y1="30" x2="280" y2="390" />
                    <line x1="440" y1="30" x2="440" y2="390" />
                    <line x1="600" y1="30" x2="600" y2="390" />
                    <line x1="760" y1="30" x2="760" y2="390" />
                    <line x1="920" y1="30" x2="920" y2="390" />
                </g>

                {{-- Subtle coordinate text markers --}}
                <g font-size="8" font-family="monospace" fill="#334155" opacity="0.6">
                    <text x="35" y="66">20°N</text>
                    <text x="35" y="136">10°N</text>
                    <text x="35" y="206">0° (Equator)</text>
                    <text x="35" y="276">10°S</text>
                    <text x="35" y="346">20°S</text>
                    <text x="282" y="405">100°E</text>
                    <text x="442" y="405">110°E</text>
                    <text x="602" y="405">120°E</text>
                    <text x="762" y="405">130°E</text>
                    <text x="922" y="405">140°E</text>
                </g>

                {{-- Flight Route Directional Curves (Origin -> Destination) --}}
                <g id="network-route-layer"></g>

                {{-- Airport Nodes (35 InJourney Stations + Active Destinations) --}}
                <g id="network-node-layer"></g>
            </svg>

            {{-- Empty route data overlay (§12) --}}
            <div x-show="getNetworkRouteLines().length === 0"
                 class="absolute inset-0 flex flex-col items-center justify-center bg-slate-950/80 backdrop-blur-xs text-slate-400 z-10 pointer-events-none">
                <svg class="w-8 h-8 text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span class="text-xs font-semibold text-slate-400">No route data for current filter.</span>
            </div>

            {{-- Floating Interactive HUD Card for Routes --}}
            <template x-if="hoveredRoute">
                <div class="absolute bottom-3 left-4 bg-slate-900/95 backdrop-blur-md border border-cyan-500/40 text-white rounded-xl px-4 py-2.5 text-xs shadow-2xl pointer-events-none space-y-1 z-20">
                    <div class="font-black flex items-center gap-2 text-cyan-400">
                        <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                        <span x-text="hoveredRoute.route"></span>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-[11px] font-mono pt-1 border-t border-slate-800">
                        <div class="text-slate-400">Total Flights: <span class="text-white font-bold" x-text="(hoveredRoute.flights || 0) + ' flt'"></span></div>
                        <div class="text-slate-400">Total Pax: <span class="text-white font-bold" x-text="(hoveredRoute.pax || 0).toLocaleString()"></span></div>
                        <div class="text-slate-400">Avg SLF: <span class="text-emerald-400 font-bold" x-text="hoveredRoute.slf || '0.0%'"></span></div>
                        <div class="text-slate-400">Avg Delay: <span class="text-amber-400 font-bold" x-text="'+' + (hoveredRoute.avgDelay || 0) + ' min'"></span></div>
                    </div>
                </div>
            </template>

            {{-- Floating Interactive HUD Card for Airport Nodes --}}
            <template x-if="!hoveredRoute && hoveredAirport">
                <div class="absolute bottom-3 left-4 bg-slate-900/95 backdrop-blur-md border border-blue-500/40 text-white rounded-xl px-4 py-2.5 text-xs shadow-2xl pointer-events-none space-y-0.5 z-20">
                    <div class="font-black flex items-center gap-1.5 text-blue-400">
                        <span class="w-2 h-2 rounded-full" :class="hoveredAirport.isHub ? 'bg-cyan-400' : 'bg-blue-400'"></span>
                        <span x-text="hoveredAirport.code + ' — ' + hoveredAirport.name"></span>
                        <span x-show="hoveredAirport.isHub" class="px-1.5 py-0.2 rounded bg-cyan-500/20 text-cyan-300 text-[9px] uppercase font-bold">Active Hub</span>
                    </div>
                    <div class="text-[10px] text-slate-400" x-text="hoveredAirport.city + ' • ' + (hoveredAirport.flightsCount || 0) + ' Connected Movements'"></div>
                </div>
            </template>
        </div>
    </div>

</div>
