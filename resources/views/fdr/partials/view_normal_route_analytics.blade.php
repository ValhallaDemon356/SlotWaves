{{-- ══ ROUTE ANALYTICS, SPECIAL SUFFIXES & INTERACTIVE NETWORK MAP ═════════ --}}
<div class="space-y-5" id="fdr-route-network-section">

    {{-- ══ SPECIAL SUFFIX & IRREGULARITY BREAKDOWN ═════════════════════════════ --}}
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

        {{-- Suffix Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5">
            <template x-for="item in getSuffixDistribution()" :key="item.suffix">
                <div class="p-2.5 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-slate-100/60 transition flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="w-6 h-6 rounded-lg font-black text-xs flex items-center justify-center"
                              :class="item.colorClass"
                              x-text="item.suffix"></span>
                        <span class="text-[10px] font-mono font-bold text-slate-700" x-text="item.count + ' flt'"></span>
                    </div>
                    <div class="mt-2">
                        <div class="text-[10px] font-black text-slate-800 truncate" x-text="item.label"></div>
                        <div class="text-[9px] text-slate-400 truncate" x-text="item.category"></div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Leg Subtypes: SCHED / UNSCHED / CARGO --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-100 text-xs">
            <div class="p-3 rounded-xl bg-blue-50/60 border border-blue-200 flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase text-blue-800">Scheduled Commercial (SCHED)</div>
                    <div class="text-base font-black text-blue-900 mt-0.5" x-text="getLegSubtypeCount('SCHED').toLocaleString()">0</div>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-200/60 text-blue-800 font-mono" x-text="getLegSubtypePct('SCHED')">0%</span>
            </div>

            <div class="p-3 rounded-xl bg-amber-50/60 border border-amber-200 flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase text-amber-800">Unscheduled / Charter (UNSCHED)</div>
                    <div class="text-base font-black text-amber-900 mt-0.5" x-text="getLegSubtypeCount('UNSCHED').toLocaleString()">0</div>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-200/60 text-amber-800 font-mono" x-text="getLegSubtypePct('UNSCHED')">0%</span>
            </div>

            <div class="p-3 rounded-xl bg-purple-50/60 border border-purple-200 flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase text-purple-800">Dedicated Cargo (CARGO)</div>
                    <div class="text-base font-black text-purple-900 mt-0.5" x-text="getLegSubtypeCount('CARGO').toLocaleString()">0</div>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-purple-200/60 text-purple-800 font-mono" x-text="getLegSubtypePct('CARGO')">0%</span>
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
                                <div class="text-[9.5px] text-slate-400 font-sans" x-text="(r.delayed_flights != null ? r.delayed_flights : (r.delayedFlights || 0)) + ' delayed of ' + (r.total_flights || r.flights || r.count || 0) + ' flights' + ((r.delay_rate != null || r.delayRate != null) ? ' (' + (r.delay_rate != null ? r.delay_rate : r.delayRate) + '%)' : '')"></div>
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

    {{-- ══ INTERACTIVE INJOURNEY ROUTE NETWORK MAP (SVG) ═══════════════════════ --}}
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
                    <p class="text-[10px] text-slate-400">Visual operational connection graph mapped against 35 Indonesian airports</p>
                </div>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span> Active Hub</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span> Regional Station</span>
                <span class="flex items-center gap-1.5"><span class="w-4 h-0.5 bg-blue-500"></span> Route Trajectory</span>
            </div>
        </div>

        {{-- SVG Map Canvas --}}
        <div class="w-full h-72 rounded-xl bg-slate-900 relative overflow-hidden flex items-center justify-center p-2 select-none shadow-inner" id="injourney-network-container">
            <svg class="w-full h-full" viewBox="0 0 920 360" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <linearGradient id="routeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#38BDF8" stop-opacity="0.8" />
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.2" />
                    </linearGradient>
                    <filter id="glow">
                        <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
                        <feMerge>
                            <feMergeNode in="coloredBlur"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                </defs>

                {{-- Archipelago background grid / faint coastline guide --}}
                <g opacity="0.08" stroke="#FFFFFF" stroke-width="0.5">
                    <line x1="50" y1="90" x2="870" y2="90" />
                    <line x1="50" y1="180" x2="870" y2="180" />
                    <line x1="50" y1="270" x2="870" y2="270" />
                    <line x1="200" y1="40" x2="200" y2="320" />
                    <line x1="400" y1="40" x2="400" y2="320" />
                    <line x1="600" y1="40" x2="600" y2="320" />
                    <line x1="800" y1="40" x2="800" y2="320" />
                </g>

                {{-- Flight Route Connection Curves --}}
                <g>
                    <template x-for="(line, lidx) in getNetworkRouteLines()" :key="'line-' + lidx">
                        <path :d="line.pathD"
                              fill="none"
                              stroke="url(#routeGrad)"
                              :stroke-width="line.strokeWidth"
                              stroke-linecap="round"
                              filter="url(#glow)">
                            <title x-text="line.title"></title>
                        </path>
                    </template>
                </g>

                {{-- Airport Nodes (35 InJourney Stations) --}}
                <g>
                    <template x-for="(node, nidx) in getNetworkAirportNodes()" :key="'node-' + node.code">
                        <g :transform="'translate(' + node.x + ',' + node.y + ')'"
                           class="cursor-pointer group"
                           @mouseenter="hoveredAirport = node"
                           @mouseleave="hoveredAirport = null">
                            {{-- Outer Pulse circle for active hub --}}
                            <circle r="6" fill="#3B82F6" opacity="0.3" class="animate-ping" x-show="node.isActive"></circle>
                            {{-- Main Node circle --}}
                            <circle r="4" :fill="node.isActive ? '#60A5FA' : '#64748B'" :stroke="node.isActive ? '#FFFFFF' : '#334155'" stroke-width="1.5"></circle>
                            {{-- Airport Code Label --}}
                            <text :y="node.y > 280 ? -8 : 12"
                                  text-anchor="middle"
                                  :fill="node.isActive ? '#FFFFFF' : '#94A3B8'"
                                  font-size="9"
                                  font-weight="bold"
                                  font-family="monospace"
                                  letter-spacing="0.5"
                                  x-text="node.code"></text>
                        </g>
                    </template>
                </g>
            </svg>

            {{-- Floating Tooltip on Hover --}}
            <template x-if="hoveredAirport">
                <div class="absolute bottom-3 left-4 bg-slate-800/90 backdrop-blur-md border border-slate-700 text-white rounded-xl px-3 py-2 text-xs shadow-xl pointer-events-none space-y-0.5">
                    <div class="font-black flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                        <span x-text="hoveredAirport.code + ' — ' + hoveredAirport.name"></span>
                    </div>
                    <div class="text-[10px] text-slate-400" x-text="hoveredAirport.city + ' • ' + (hoveredAirport.flightsCount || 0) + ' Connected Flights'"></div>
                </div>
            </template>
        </div>
    </div>

</div>
