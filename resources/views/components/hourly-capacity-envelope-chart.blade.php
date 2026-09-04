{{--
    HourlyCapacityEnvelopeChart Component
    SlotWaves Two-Direction Operational Aircraft Capacity Envelope Chart
    - X-Axis: Time / Hour (00..23 or OPS window)
    - Y-Axis: Aircraft Count (+Y Arrivals upward, -Y Departures downward)
    - Center Horizontal Axis: Time (Y=0, separating Arrivals & Departures)
    - Dynamic Dashed Capacity Lines (Behind Bars): Independent +ARR and -DEP Capacity limits
    - Dynamic Dashed Capacity Envelope: Bounded by [Ops Start, Ops End] horizontally and [-DEP Cap, +ARR Cap] vertically
--}}

@props([
    'mode' => 'schedule', // 'schedule' | 'dau'
    'height' => 140, // half-height in px for arrival / departure area
])

<div class="space-y-3 flex flex-col justify-between h-full select-none"
     x-data="{
         hoveredBoundary: null,
         hoveredCapLine: null,
     }">
    
    @if($mode === 'schedule')
        {{-- Chart Header Badges & Segmented Window Toggle --}}
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800/80 pb-2">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Aircraft Capacity Badge & Edit Button --}}
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10.5px] font-mono bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
                    <span class="text-[9.5px] font-black uppercase tracking-wider text-slate-400">AIRCRAFT CAPACITY</span>
                    <span class="text-slate-300 dark:text-slate-700">|</span>
                    <span class="text-amber-600 dark:text-amber-400 font-bold">ARR: <strong x-text="arrivalCapacity"></strong> A/C</span>
                    <span class="text-slate-300 dark:text-slate-700">|</span>
                    <span class="text-blue-600 dark:text-blue-400 font-bold">DEP: <strong x-text="departureCapacity"></strong> A/C</span>
                    <button type="button" @click="openUnifiedModal()" class="ml-1 text-[9.5px] font-black text-aviation-600 dark:text-aviation-400 hover:underline cursor-pointer">EDIT ⚙</button>
                </div>

                {{-- Ops Hours Badge & Edit Button --}}
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10.5px] font-mono bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
                    <span class="text-[9.5px] font-black uppercase tracking-wider text-slate-400">OPS HOURS</span>
                    <span class="text-slate-300 dark:text-slate-700">|</span>
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold"><span x-text="opsStartTime"></span> &rarr; <span x-text="opsEndTime"></span></span>
                    <button type="button" @click="openUnifiedModal()" class="ml-1 text-[9.5px] font-black text-aviation-600 dark:text-aviation-400 hover:underline cursor-pointer">EDIT ⚙</button>
                </div>
                
                {{-- Segmented Time Filter: OPS Window vs All 24 Hours --}}
                <div class="inline-flex p-0.5 bg-slate-100 dark:bg-navy-950 rounded-md border border-slate-200 dark:border-slate-800 text-[10px] font-semibold">
                    <button type="button" 
                            @click="scheduleTimeFilter = 'ops'"
                            :class="scheduleTimeFilter === 'ops' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-2 py-0.5 rounded transition flex items-center gap-1 cursor-pointer">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span>OPS Window (<span x-text="activeHoursCount"></span>h)</span>
                    </button>
                    <button type="button" 
                            @click="scheduleTimeFilter = 'all'"
                            :class="scheduleTimeFilter === 'all' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-2 py-0.5 rounded transition flex items-center gap-1 cursor-pointer">
                        <span>24 Hours</span>
                    </button>
                </div>
            </div>

            {{-- Active Filter Pills --}}
            <div class="flex items-center gap-2">
                <template x-if="movementFilter !== 'all'">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider font-mono"
                          :class="movementFilter === 'arrivals' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border border-blue-300'">
                        <span x-text="movementFilter === 'arrivals' ? 'ARR Only' : 'DEP Only'"></span>
                        <button type="button" @click="movementFilter = 'all'" class="hover:text-red-500 font-bold cursor-pointer">&times;</button>
                    </span>
                </template>

                <template x-if="selectedHour !== null">
                    <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-300 dark:border-aviation-800 text-[10.5px] font-mono font-bold">
                        <span>Hour: <span x-text="String(selectedHour).padStart(2, '0') + ':00'"></span></span>
                        <button type="button" @click="clearHourFilter()" class="hover:text-red-500 font-bold ml-1 cursor-pointer">&times;</button>
                    </div>
                </template>
            </div>
        </div>
    @else
        {{-- DAU Mode Header Control Bar --}}
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800/80 pb-2">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Aircraft Capacity Badge & Edit Button --}}
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10.5px] font-mono bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
                    <span class="text-[9.5px] font-black uppercase tracking-wider text-slate-400">AIRCRAFT CAPACITY</span>
                    <span class="text-slate-300 dark:text-slate-700">|</span>
                    <span class="text-amber-600 dark:text-amber-400 font-bold">ARR: <strong x-text="arrivalCapacity"></strong> A/C</span>
                    <span class="text-slate-300 dark:text-slate-700">|</span>
                    <span class="text-blue-600 dark:text-blue-400 font-bold">DEP: <strong x-text="departureCapacity"></strong> A/C</span>
                    <button type="button" @click="openUnifiedModal()" class="ml-1 text-[9.5px] font-black text-aviation-600 dark:text-aviation-400 hover:underline cursor-pointer">EDIT ⚙</button>
                </div>

                {{-- Ops Hours Badge & Edit Button --}}
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10.5px] font-mono bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
                    <span class="text-[9.5px] font-black uppercase tracking-wider text-slate-400">OPS HOURS</span>
                    <span class="text-slate-300 dark:text-slate-700">|</span>
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold"><span x-text="opsStartTime"></span> &rarr; <span x-text="opsEndTime"></span></span>
                    <button type="button" @click="openUnifiedModal()" class="ml-1 text-[9.5px] font-black text-aviation-600 dark:text-aviation-400 hover:underline cursor-pointer">EDIT ⚙</button>
                </div>

                <template x-if="filterTerminal !== 'ALL'">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-mono font-bold bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-2xs">
                        <span>TERMINAL: <span x-text="filterTerminal"></span></span>
                        <button type="button" @click="setTerminal('ALL')" class="hover:text-red-400 font-bold ml-1 cursor-pointer">&times;</button>
                    </span>
                </template>
            </div>

            <template x-if="filterHour !== 'ALL'">
                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-300 dark:border-aviation-800 text-[10.5px] font-mono font-bold">
                    <span>Hour: <span x-text="filterHour"></span></span>
                    <button type="button" @click="setHourFilter('ALL')" class="hover:text-red-500 font-bold ml-1 cursor-pointer">&times;</button>
                </div>
            </template>
        </div>
    @endif

    {{-- ══ TWO-DIRECTION CHART VISUAL CANVAS ══════════════════════════════ --}}
    <div class="relative py-2 overflow-x-auto custom-scrollbar" id="two-direction-capacity-chart-container">
        <div class="relative min-w-[560px] sm:min-w-[620px] w-full select-none">
            
            {{-- ── LAYER 0: DASHED CAPACITY REFERENCE LINES & LABELS (BEHIND BARS) ── --}}
            <div class="absolute inset-x-0 inset-y-0 z-0">
                
                {{-- 1. DASHED ARRIVAL CAPACITY REFERENCE LINE (+ARR Capacity, Upper Side) --}}
                <div class="absolute inset-x-0 border-b border-dashed border-amber-500/50 dark:border-amber-400/40 flex items-center justify-between pointer-events-none"
                     :style="'bottom: ' + (140 + 32 + gridArrNacOffsetPx) + 'px;'"
                     @if($mode !== 'schedule') x-show="selectedMetric === 'aircraft'" @endif>
                    
                    {{-- Interactive Hover Detector on ARR Capacity Line --}}
                    <div class="absolute inset-x-0 -translate-y-1/2 h-5 cursor-crosshair pointer-events-auto"
                         @mouseenter="hoveredCapLine = 'arr'"
                         @mouseleave="hoveredCapLine = null"
                         tabindex="0">
                    </div>

                    <span class="text-[8.5px] font-mono font-bold text-amber-600 dark:text-amber-400 bg-white/90 dark:bg-navy-900/90 px-1.5 py-0.5 rounded-r shadow-2xs border-r border-amber-300/40 pointer-events-none">
                        ARR CAP: +<span x-text="arrivalCapacity"></span> A/C
                    </span>
                    <span class="text-[8px] font-mono text-amber-600/70 dark:text-amber-400/70 pr-1 pointer-events-none">
                        &uarr; Batas Kedatangan
                    </span>
                </div>

                {{-- ARR Capacity Line Hover Tooltip --}}
                <div x-show="hoveredCapLine === 'arr'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-1/2 -translate-x-1/2 z-50 px-3 py-1.5 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-lg shadow-2xl border border-amber-500/60 text-xs font-mono pointer-events-none text-center whitespace-nowrap"
                     :style="'bottom: ' + (140 + 32 + gridArrNacOffsetPx + 10) + 'px;'"
                     style="display: none;">
                    <div class="font-bold text-amber-300 text-[10px] uppercase tracking-wider">Arrival Capacity <span class="opacity-80">(Batas Aircraft Capacity - ARR: <span x-text="arrivalCapacity"></span> A/C)</span></div>
                    <div class="font-black text-white text-xs" x-text="arrivalCapacity + ' A/C'"></div>
                </div>

                {{-- +50% ARR Grid Line --}}
                <div class="absolute inset-x-0 border-b border-dashed border-slate-200 dark:border-slate-800 flex items-center justify-between pointer-events-none"
                     :style="'bottom: ' + (140 + 32 + Math.round(gridArrNacOffsetPx / 2)) + 'px;'"
                     @if($mode !== 'schedule') x-show="selectedMetric === 'aircraft'" @endif>
                    <span class="text-[8px] font-mono text-slate-400 dark:text-slate-600 px-1">
                        +<span x-text="Math.round(arrivalCapacity / 2)"></span>
                    </span>
                </div>

                {{-- -50% DEP Grid Line --}}
                <div class="absolute inset-x-0 border-b border-dashed border-slate-200 dark:border-slate-800 flex items-center justify-between pointer-events-none"
                     :style="'top: ' + (140 + 32 + Math.round(gridDepNacOffsetPx / 2)) + 'px;'"
                     @if($mode !== 'schedule') x-show="selectedMetric === 'aircraft'" @endif>
                    <span class="text-[8px] font-mono text-slate-400 dark:text-slate-600 px-1">
                        -<span x-text="Math.round(departureCapacity / 2)"></span>
                    </span>
                </div>

                {{-- 2. DASHED DEPARTURE CAPACITY REFERENCE LINE (-DEP Capacity, Lower Side) --}}
                <div class="absolute inset-x-0 border-b border-dashed border-blue-500/50 dark:border-blue-400/40 flex items-center justify-between pointer-events-none"
                     :style="'top: ' + (140 + 32 + gridDepNacOffsetPx) + 'px;'"
                     @if($mode !== 'schedule') x-show="selectedMetric === 'aircraft'" @endif>
                    
                    {{-- Interactive Hover Detector on DEP Capacity Line --}}
                    <div class="absolute inset-x-0 -translate-y-1/2 h-5 cursor-crosshair pointer-events-auto"
                         @mouseenter="hoveredCapLine = 'dep'"
                         @mouseleave="hoveredCapLine = null"
                         tabindex="0">
                    </div>

                    <span class="text-[8.5px] font-mono font-bold text-blue-600 dark:text-blue-400 bg-white/90 dark:bg-navy-900/90 px-1.5 py-0.5 rounded-r shadow-2xs border-r border-blue-300/40 pointer-events-none">
                        DEP CAP: -<span x-text="departureCapacity"></span> A/C
                    </span>
                    <span class="text-[8px] font-mono text-blue-600/70 dark:text-blue-400/70 pr-1 pointer-events-none">
                        &darr; Batas Keberangkatan
                    </span>
                </div>

                {{-- DEP Capacity Line Hover Tooltip --}}
                <div x-show="hoveredCapLine === 'dep'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute left-1/2 -translate-x-1/2 z-50 px-3 py-1.5 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-lg shadow-2xl border border-blue-500/60 text-xs font-mono pointer-events-none text-center whitespace-nowrap"
                     :style="'top: ' + (140 + 32 + gridDepNacOffsetPx + 10) + 'px;'"
                     style="display: none;">
                    <div class="font-bold text-blue-300 text-[10px] uppercase tracking-wider">Departure Capacity <span class="opacity-80">(Batas Aircraft Capacity - DEP: <span x-text="departureCapacity"></span> A/C)</span></div>
                    <div class="font-black text-white text-xs" x-text="departureCapacity + ' A/C'"></div>
                </div>

            </div>

            {{-- ── LAYER 1: DYNAMIC DASHED CAPACITY ENVELOPE (Ops Start/End & -DEP / +ARR) ── --}}
            <template x-if="@if($mode === 'schedule') envelopeCoords.isVisible @else (selectedMetric === 'aircraft' && envelopeCoords.isVisible) @endif">
                <div class="absolute z-1 transition-all duration-200 pointer-events-none rounded-sm border-t-2 border-b-2 border-l-2 border-r-2 border-dashed border-t-amber-500 border-b-blue-500 border-l-emerald-500 border-r-emerald-500 bg-emerald-500/[0.02] dark:bg-emerald-500/[0.03] shadow-[0_0_15px_rgba(16,185,129,0.05)]"
                     :style="{
                         left: envelopeCoords.left + '%',
                         width: envelopeCoords.width + '%',
                         top: envelopeCoords.top + 'px',
                         bottom: envelopeCoords.bottom + 'px'
                     }">
                    
                    {{-- PINNED ENVELOPE LABELS (Non-overlapping, clear boundaries) --}}
                    {{-- 1. Upper boundary label (Arrival Capacity) --}}
                    <div class="absolute -top-5.5 left-2 flex items-center gap-1 font-mono text-[8.5px] font-black text-amber-600 dark:text-amber-400 bg-white/95 dark:bg-navy-900/95 px-1.5 py-0.5 rounded shadow-2xs border border-amber-400/80 dark:border-amber-600/80 whitespace-nowrap z-20 pointer-events-none">
                        ARR CAP: +<span x-text="arrivalCapacity"></span> A/C
                    </div>

                    {{-- 2. Lower boundary label (Departure Capacity) --}}
                    <div class="absolute -bottom-5.5 left-2 flex items-center gap-1 font-mono text-[8.5px] font-black text-blue-600 dark:text-blue-400 bg-white/95 dark:bg-navy-900/95 px-1.5 py-0.5 rounded shadow-2xs border border-blue-400/80 dark:border-blue-600/80 whitespace-nowrap z-20 pointer-events-none">
                        DEP CAP: -<span x-text="departureCapacity"></span> A/C
                    </div>

                    {{-- 3. Left boundary label (Operating Hours Start) --}}
                    <div class="absolute -top-7 -translate-x-1/2 flex flex-col items-center px-1.5 py-0.5 rounded bg-white/95 dark:bg-navy-900/95 border border-emerald-500 text-emerald-700 dark:text-emerald-400 font-mono text-[8px] leading-tight shadow-xs whitespace-nowrap z-20 pointer-events-none">
                        <span class="text-[6.5px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">OPS START</span>
                        <span class="font-black text-slate-900 dark:text-white" x-text="opsStartTime"></span>
                    </div>

                    {{-- 4. Right boundary label (Operating Hours End) --}}
                    <div class="absolute -top-7 -translate-x-1/2 flex flex-col items-center px-1.5 py-0.5 rounded bg-white/95 dark:bg-navy-900/95 border border-emerald-500 text-emerald-700 dark:text-emerald-400 font-mono text-[8px] leading-tight shadow-xs whitespace-nowrap z-20 pointer-events-none">
                        <span class="text-[6.5px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">OPS END</span>
                        <span class="font-black text-slate-900 dark:text-white" x-text="opsEndTime"></span>
                    </div>

                    {{-- TOP BOUNDARY HIT ZONE (+ARR Capacity) --}}
                    <div class="absolute inset-x-0 -top-2.5 h-5 cursor-pointer pointer-events-auto group/topcap"
                         @mouseenter="hoveredBoundary = 'top'"
                         @mouseleave="hoveredBoundary = null"
                         tabindex="0">
                        <div class="w-full h-0.5 transition-all"
                             :class="hoveredBoundary === 'top' ? 'bg-amber-500 shadow-sm' : 'bg-transparent'"></div>
                        
                        <div x-show="hoveredBoundary === 'top'"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-1/2 -translate-x-1/2 -top-11 z-50 px-2.5 py-1 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-lg shadow-xl border border-slate-700 text-xs font-mono pointer-events-none text-center whitespace-nowrap"
                             style="display: none;">
                            <div class="font-bold text-amber-300 text-[9.5px] uppercase tracking-wider">Arrival Capacity <span class="opacity-80">(Batas Aircraft Capacity - ARR: <span x-text="arrivalCapacity"></span> A/C)</span></div>
                            <div class="font-black text-white text-xs" x-text="arrivalCapacity + ' A/C'"></div>
                        </div>
                    </div>

                    {{-- BOTTOM BOUNDARY HIT ZONE (-DEP Capacity) --}}
                    <div class="absolute inset-x-0 -bottom-2.5 h-5 cursor-pointer pointer-events-auto group/botcap"
                         @mouseenter="hoveredBoundary = 'bottom'"
                         @mouseleave="hoveredBoundary = null"
                         tabindex="0">
                        <div class="w-full h-0.5 transition-all"
                             :class="hoveredBoundary === 'bottom' ? 'bg-blue-500 shadow-sm' : 'bg-transparent'"></div>
                        
                        <div x-show="hoveredBoundary === 'bottom'"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-1/2 -translate-x-1/2 -bottom-11 z-50 px-2.5 py-1 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-lg shadow-xl border border-slate-700 text-xs font-mono pointer-events-none text-center whitespace-nowrap"
                             style="display: none;">
                            <div class="font-bold text-blue-300 text-[9.5px] uppercase tracking-wider">Departure Capacity <span class="opacity-80">(Batas Aircraft Capacity - DEP: <span x-text="departureCapacity"></span> A/C)</span></div>
                            <div class="font-black text-white text-xs" x-text="departureCapacity + ' A/C'"></div>
                        </div>
                    </div>

                    {{-- LEFT BOUNDARY HIT ZONE (OPS START) --}}
                    <div class="absolute inset-y-0 -left-2.5 w-5 cursor-pointer pointer-events-auto group/leftops"
                         @mouseenter="hoveredBoundary = 'left'"
                         @mouseleave="hoveredBoundary = null"
                         tabindex="0">
                        <div class="h-full w-0.5 transition-all"
                             :class="hoveredBoundary === 'left' ? 'bg-emerald-500 shadow-sm' : 'bg-transparent'"></div>
                        
                        <div x-show="hoveredBoundary === 'left'"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute -top-11 left-0 z-50 px-2.5 py-1 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-lg shadow-xl border border-slate-700 text-xs font-mono pointer-events-none text-center whitespace-nowrap"
                             style="display: none;">
                            <div class="font-bold text-emerald-300 text-[9.5px] uppercase tracking-wider">Operating Hours Start</div>
                            <div class="font-black text-white text-xs" x-text="opsStartTime"></div>
                        </div>
                    </div>

                    {{-- RIGHT BOUNDARY HIT ZONE (OPS END) --}}
                    <div class="absolute inset-y-0 -right-2.5 w-5 cursor-pointer pointer-events-auto group/rightops"
                         @mouseenter="hoveredBoundary = 'right'"
                         @mouseleave="hoveredBoundary = null"
                         tabindex="0">
                        <div class="h-full w-0.5 transition-all"
                             :class="hoveredBoundary === 'right' ? 'bg-emerald-500 shadow-sm' : 'bg-transparent'"></div>
                        
                        <div x-show="hoveredBoundary === 'right'"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute -top-11 right-0 z-50 px-2.5 py-1 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-lg shadow-xl border border-slate-700 text-xs font-mono pointer-events-none text-center whitespace-nowrap"
                             style="display: none;">
                            <div class="font-bold text-emerald-300 text-[9.5px] uppercase tracking-wider">Operating Hours End</div>
                            <div class="font-black text-white text-xs" x-text="opsEndTime"></div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ── LAYER 10: SYNCHRONIZED COLUMNS GRID (Arrivals Above + Time Center + Departures Below) ── --}}
            {{-- Placed in relative z-10 so actual data bars remain in front of reference lines and never get obscured --}}
            <div class="relative z-10 w-full"
                 :style="'display: grid; grid-template-columns: repeat(' + @if($mode === 'schedule') activeHourlyDistribution.length @else hourlyCapacityAnalysis.list.length @endif + ', minmax(0, 1fr)); gap: ' + ((@if($mode === 'schedule') activeHourlyDistribution.length @else hourlyCapacityAnalysis.list.length @endif) > 16 ? '2px' : '4px') + ';'">
                
                <template x-for="item in @if($mode === 'schedule') activeHourlyDistribution @else hourlyCapacityAnalysis.list @endif" :key="item.hour">
                    <div class="flex flex-col items-center h-full group relative cursor-pointer select-none transition-all duration-150 rounded-md"
                         :class="[
                             @if($mode === 'schedule') (selectedHour === item.hour) @else (filterHour === item.hour) @endif ? 'bg-aviation-50/80 dark:bg-aviation-950/60 ring-2 ring-aviation-500 shadow-sm' : 'hover:bg-slate-100/60 dark:hover:bg-navy-800/40',
                             item.isPeak ? 'peak-bar-glow' : ''
                         ]"
                         @mouseenter="hoveredHour = item.hour"
                         @mouseleave="hoveredHour = null">
                        
                        {{-- ── UPPER SECTION: ARRIVALS (+Y, Grows UPWARD from Center) ── --}}
                        <div class="w-full h-[140px] flex flex-col justify-end items-center px-0.5 pb-1 relative"
                             @click.stop="@if($mode === 'schedule') selectHourWithDirection(item.hour, 'arrivals') @else setHourFilter(item.hour) @endif"
                             title="Click to filter Arrivals">
                            
                            {{-- Top Status Pill (OVER / MAX) --}}
                            <template x-if="item.isOps && item.status === 'OVER CAPACITY'">
                                <div class="absolute top-1 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-purple-600 text-white shadow-2xs z-20 font-mono">
                                    OVER
                                </div>
                            </template>
                            <template x-if="item.isOps && item.status === 'FULL / MAX'">
                                <div class="absolute top-1 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-amber-500 text-white shadow-2xs z-20 font-mono">
                                    MAX
                                </div>
                            </template>

                            {{-- Numerical Arrival Count (Readable above bar) --}}
                            <template x-if="@if($mode === 'schedule') (item.arrCount > 0 || item.opcCount > 0) @else (item.arr > 0) @endif">
                                <span class="text-[8.5px] sm:text-[9.5px] font-mono font-bold text-amber-600 dark:text-amber-400 mb-0.5">
                                    <span x-text="@if($mode === 'schedule') item.arrCount @else item.arr @endif"></span>
                                    @if($mode === 'schedule')
                                        <template x-if="item.opcCount > 0">
                                            <span class="text-[7.5px] text-purple-600 dark:text-purple-400" x-text="'+' + item.opcCount"></span>
                                        </template>
                                    @endif
                                </span>
                            </template>

                            {{-- Stacked Activity Bar: OPC (Purple) on Top of Arrivals (Orange) --}}
                            <div class="w-full max-w-[24px] sm:max-w-[28px] flex flex-col justify-end gap-0.5 rounded-t-sm transition-all duration-200">
                                
                                @if($mode === 'schedule')
                                    {{-- OPC Block (Purple RON Overlay/Stack) --}}
                                    <template x-if="item.opcCount > 0">
                                        <div class="w-full bg-purple-600 dark:bg-purple-500 rounded-t-xs transition-all duration-300 group-hover:brightness-110 shadow-2xs"
                                             :style="'height: ' + Math.max(3, Math.round((item.opcCount / chartMaxScale) * 115)) + 'px'"></div>
                                    </template>
                                @endif

                                {{-- Arrival Bar (Orange, Grows Upward from Center) --}}
                                <template x-if="@if($mode === 'schedule') item.arrCount > 0 @else item.arr > 0 @endif">
                                    <div class="w-full bg-amber-500 dark:bg-amber-500 hover:bg-amber-400 transition-all duration-300 shadow-2xs"
                                         @if($mode === 'schedule')
                                             :class="item.opcCount > 0 ? 'rounded-none' : 'rounded-t-xs'"
                                         @else
                                             class="rounded-t-xs"
                                         @endif
                                         :style="'height: ' + Math.max(3, Math.round(((@if($mode === 'schedule') item.arrCount @else item.arr @endif) / chartMaxScale) * 115)) + 'px'"></div>
                                </template>

                                {{-- Baseline tick if 0 arrivals --}}
                                <template x-if="@if($mode === 'schedule') (item.arrCount === 0 && (!item.opcCount || item.opcCount === 0)) @else (item.arr === 0) @endif">
                                    <div class="w-full max-w-[14px] mx-auto h-0.5 bg-slate-200 dark:bg-navy-800 rounded-xs"></div>
                                </template>
                            </div>
                        </div>

                        {{-- ── CENTER TIME AXIS: TIME (Y=0 Baseline separating Arrival & Departure) ── --}}
                        <div class="w-full h-8 flex items-center justify-center border-y border-slate-200/90 dark:border-slate-800 bg-slate-100/90 dark:bg-navy-950/90 relative z-20 transition-colors"
                             :class="[
                                 item.isOps ? 'bg-slate-100/90 dark:bg-navy-950/90' : 'bg-slate-200/40 dark:bg-navy-950/40 opacity-75',
                                 @if($mode === 'schedule') (selectedHour === item.hour) @else (filterHour === item.hour) @endif ? 'border-aviation-500 dark:border-aviation-400 bg-aviation-100/60 dark:bg-aviation-950/80' : ''
                             ]"
                             @click.stop="@if($mode === 'schedule') selectHour(item.hour) @else setHourFilter(item.hour) @endif"
                             title="Click to filter this hour">
                            <span class="text-[9.5px] sm:text-[10.5px] font-mono transition-colors"
                                  :class="[
                                      @if($mode === 'schedule') (selectedHour === item.hour) @else (filterHour === item.hour) @endif ? 'font-black text-aviation-700 dark:text-aviation-300' : (
                                          item.isOps ? 'font-bold text-slate-800 dark:text-slate-200 group-hover:text-aviation-600' : 'text-slate-400 dark:text-slate-500 font-normal'
                                      )
                                  ]"
                                  x-text="item.shortLabel">
                            </span>
                        </div>

                        {{-- ── LOWER SECTION: DEPARTURES (-Y, Grows DOWNWARD from Center) ── --}}
                        <div class="w-full h-[140px] flex flex-col justify-start items-center px-0.5 pt-1 relative"
                             @click.stop="@if($mode === 'schedule') selectHourWithDirection(item.hour, 'departures') @else setHourFilter(item.hour) @endif"
                             title="Click to filter Departures">
                            
                            {{-- Departure Bar (Blue, Grows Downward from Center) --}}
                            <div class="w-full max-w-[24px] sm:max-w-[28px] flex flex-col justify-start rounded-b-sm transition-all duration-200">
                                <template x-if="@if($mode === 'schedule') item.depCount > 0 @else item.dep > 0 @endif">
                                    <div class="w-full bg-blue-600 dark:bg-blue-500 hover:bg-blue-400 rounded-b-xs transition-all duration-300 shadow-2xs"
                                         :style="'height: ' + Math.max(3, Math.round(((@if($mode === 'schedule') item.depCount @else item.dep @endif) / chartMaxScale) * 115)) + 'px'"></div>
                                </template>

                                {{-- Baseline tick if 0 departures --}}
                                <template x-if="@if($mode === 'schedule') item.depCount === 0 @else item.dep === 0 @endif">
                                    <div class="w-full max-w-[14px] mx-auto h-0.5 bg-slate-200 dark:bg-navy-800 rounded-xs"></div>
                                </template>
                            </div>

                            {{-- Numerical Departure Count (Readable below bar) --}}
                            <template x-if="@if($mode === 'schedule') item.depCount > 0 @else item.dep > 0 @endif">
                                <span class="text-[8.5px] sm:text-[9.5px] font-mono font-bold text-blue-600 dark:text-blue-400 mt-0.5"
                                      x-text="@if($mode === 'schedule') item.depCount @else item.dep @endif">
                                </span>
                            </template>

                            {{-- Bottom Status Pill (OVER / MAX for DEP) --}}
                            <template x-if="item.isOps && ((@if($mode === 'schedule') item.depCount @else item.dep @endif) > departureCapacity)">
                                <div class="absolute bottom-1 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-purple-600 text-white shadow-2xs z-20 font-mono">
                                    OVER
                                </div>
                            </template>
                            <template x-if="item.isOps && ((@if($mode === 'schedule') item.depCount @else item.dep @endif) === departureCapacity && departureCapacity > 0)">
                                <div class="absolute bottom-1 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-blue-500 text-white shadow-2xs z-20 font-mono">
                                    MAX
                                </div>
                            </template>
                        </div>

                        {{-- ── RICH TOOLTIP ON COLUMN HOVER ── --}}
                        <div x-show="hoveredHour === item.hour"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute bottom-full mb-2 flex flex-col z-50 w-64 p-3 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-xl shadow-2xl border border-slate-700 text-xs pointer-events-none transition-all duration-150 text-left"
                             :class="item.hour <= 4 ? 'left-0' : (item.hour >= 19 ? 'right-0' : 'left-1/2 -translate-x-1/2')"
                             style="display: none;">
                            
                            {{-- Tooltip Header --}}
                            <div class="flex items-center justify-between border-b border-slate-700/80 pb-1 mb-1.5">
                                <div>
                                    <div class="font-mono font-bold text-slate-100 text-xs" 
                                         x-text="item.label + ' (' + (@if($mode === 'schedule') displayTimezoneLabel @else tzAbbr @endif) + ')'"></div>
                                    @if($mode !== 'schedule')
                                        <template x-if="filterTerminal !== 'ALL'">
                                            <div class="text-[10px] font-bold text-slate-300 font-mono">
                                                Terminal: <span class="text-white font-black" x-text="filterTerminal"></span>
                                            </div>
                                        </template>
                                    @endif
                                </div>
                                <span class="text-[9px] font-bold px-1.5 py-0.2 rounded uppercase font-mono"
                                      :class="{
                                          'bg-slate-800 text-slate-400 border border-slate-700': !item.isOps || item.status === 'OFF HOURS',
                                          'bg-purple-950 text-purple-300 border border-purple-600': item.status === 'OVER CAPACITY',
                                          'bg-amber-950 text-amber-300 border border-amber-600': item.status === 'FULL / MAX',
                                          'bg-emerald-950 text-emerald-300 border border-emerald-600': item.status === 'AVAILABLE'
                                      }"
                                      x-text="item.status">
                                </span>
                            </div>

                            {{-- Tooltip Breakdown --}}
                            <div class="space-y-1.5 font-mono text-[11px]">
                                <div class="flex items-center justify-between text-orange-400">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-orange-500 inline-block"></span>
                                        Arrivals:
                                    </span>
                                    <span class="font-bold" x-text="@if($mode === 'schedule') item.arrCount @else item.arr @endif"></span>
                                </div>
                                <div class="flex items-center justify-between text-blue-300">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
                                        Departures:
                                    </span>
                                    <span class="font-bold" x-text="@if($mode === 'schedule') item.depCount @else item.dep @endif"></span>
                                </div>

                                @if($mode === 'schedule')
                                    <div class="flex items-center justify-between text-purple-300">
                                        <span class="flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-purple-500 inline-block"></span>
                                            OPC (RON Parking):
                                        </span>
                                        <span class="font-bold" x-text="item.opcCount || 0"></span>
                                    </div>

                                    <template x-if="item.cargoCount > 0">
                                        <div class="flex items-center justify-between text-amber-300 text-[10px] bg-amber-950/40 px-2 py-0.5 rounded border border-amber-800/40">
                                            <span>Cargo (Separate Stand):</span>
                                            <span class="font-bold" x-text="item.cargoCount + ' Movements'"></span>
                                        </div>
                                    </template>
                                @else
                                    <div class="flex items-center justify-between text-slate-400">
                                        <span class="flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-purple-500/50 inline-block"></span>
                                            OPC:
                                        </span>
                                        <span>N/A</span>
                                    </div>
                                @endif

                                {{-- Independent ARR and DEP Capacity Thresholds --}}
                                <div class="pt-1.5 border-t border-slate-800 space-y-1">
                                    <div class="flex items-center justify-between text-amber-300">
                                        <span>ARR Capacity:</span>
                                        <span class="font-bold" x-text="(@if($mode === 'schedule') item.arrCount @else item.arr @endif) + ' / ' + arrivalCapacity + ' A/C'"></span>
                                    </div>
                                    <div class="flex items-center justify-between text-blue-300">
                                        <span>DEP Capacity:</span>
                                        <span class="font-bold" x-text="(@if($mode === 'schedule') item.depCount @else item.dep @endif) + ' / ' + departureCapacity + ' A/C'"></span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between font-bold text-white pt-1 border-t border-slate-800">
                                    <span>Total Demand:</span>
                                    <span class="text-xs font-black"
                                          :class="{
                                              'text-purple-400': item.status === 'OVER CAPACITY',
                                              'text-amber-400': item.status === 'FULL / MAX',
                                              'text-emerald-400': item.status === 'AVAILABLE',
                                              'text-slate-400': item.status === 'OFF HOURS'
                                          }"
                                          x-text="(@if($mode === 'schedule') item.aircraftDemand @else item.demand @endif) + ' A/C'"></span>
                                </div>
                                <div class="flex items-center justify-between font-bold text-slate-300">
                                    <span>Max Utilization:</span>
                                    <span class="text-xs font-black"
                                          :class="item.utilization > 100 ? 'text-purple-400' : (item.utilization === 100 ? 'text-amber-400' : 'text-emerald-400')"
                                          x-text="item.utilization + '%'"></span>
                                </div>
                                <div class="flex items-center justify-between font-bold pt-0.5">
                                    <span>Status:</span>
                                    <span :class="{
                                        'text-emerald-400': item.status === 'AVAILABLE',
                                        'text-amber-400': item.status === 'FULL / MAX',
                                        'text-purple-400': item.status === 'OVER CAPACITY',
                                        'text-slate-400': item.status === 'OFF HOURS'
                                    }" x-text="item.status"></span>
                                </div>
                            </div>

                            <div class="text-[9px] text-slate-400 mt-1.5 pt-1 border-t border-slate-800 text-center italic">
                                Klik kolom untuk filter (Atas: ARR &bull; Bawah: DEP)
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ══ CHART LEGEND & EDIT CONTROLS ════════════════════════════════════ --}}
    <div class="flex flex-col gap-1.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 text-[11px] text-slate-500 select-none">
        <div class="flex flex-wrap items-center justify-between gap-3">
            {{-- Primary Legend Elements --}}
            <div class="flex flex-wrap items-center gap-3">
                {{-- 1. Arrival Bars --}}
                <span class="inline-flex items-center gap-1.5" title="Arrival (Tumbuh ke atas / Positif)">
                    <span class="w-2.5 h-2.5 rounded-xs bg-amber-500 inline-block shadow-2xs"></span>
                    <strong class="text-slate-700 dark:text-slate-200">Arrival</strong>
                    <span class="text-amber-500 font-bold font-mono text-[10px]">ARR &uarr;</span>
                </span>

                {{-- 2. Departure Bars --}}
                <span class="inline-flex items-center gap-1.5" title="Departure (Tumbuh ke bawah / Visual negatif)">
                    <span class="w-2.5 h-2.5 rounded-xs bg-blue-600 inline-block shadow-2xs"></span>
                    <strong class="text-slate-700 dark:text-slate-200">Departure</strong>
                    <span class="text-blue-500 font-bold font-mono text-[10px]">DEP &darr;</span>
                </span>

                @if($mode === 'schedule')
                    {{-- 3. OPC (RON) --}}
                    <span class="inline-flex items-center gap-1.5" title="OPC: Pesawat RON yang masih menempati parking stand untuk keberangkatan pada hari berikutnya">
                        <span class="w-2.5 h-2.5 rounded-xs bg-purple-600 inline-block shadow-2xs"></span>
                        <strong class="text-slate-700 dark:text-slate-200">OPC (RON)</strong>
                    </span>
                @endif

                {{-- 4. Operating Hours Boundary --}}
                <span class="inline-flex items-center gap-1.5" title="Operating Hours: Batas jam operasional horizontal aktif bandara">
                    <span class="w-3 h-3 border-2 border-dashed border-emerald-500 bg-emerald-500/10 inline-block rounded-2xs"></span>
                    <strong class="text-emerald-700 dark:text-emerald-400">Operating Hours</strong>
                </span>

                {{-- 5. Arrival Capacity Boundary --}}
                <span class="inline-flex items-center gap-1.5 font-mono cursor-pointer hover:underline"
                      @click="openUnifiedModal()"
                      title="Click to configure Arrival Capacity">
                    <span class="w-4 border-b-2 border-dashed border-amber-500 inline-block"></span>
                    <strong class="text-amber-600 dark:text-amber-400">Arrival Capacity</strong>
                    <span class="text-[10px] text-slate-400">(+<span x-text="arrivalCapacity"></span>)</span>
                </span>

                {{-- 6. Departure Capacity Boundary --}}
                <span class="inline-flex items-center gap-1.5 font-mono cursor-pointer hover:underline"
                      @click="openUnifiedModal()"
                      title="Click to configure Departure Capacity">
                    <span class="w-4 border-b-2 border-dashed border-blue-500 inline-block"></span>
                    <strong class="text-blue-600 dark:text-blue-400">Departure Capacity</strong>
                    <span class="text-[10px] text-slate-400">(-<span x-text="departureCapacity"></span>)</span>
                </span>
            </div>

            {{-- Status Indicators Summary --}}
            <div class="flex flex-wrap items-center gap-2.5 text-[10px]">
                <span class="inline-flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    <span class="text-slate-600 dark:text-slate-400">Available</span>
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                    <span class="text-slate-600 dark:text-slate-400">Full / Max</span>
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-purple-600 inline-block"></span>
                    <span class="text-slate-600 dark:text-slate-400">Over Capacity</span>
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-slate-400 inline-block"></span>
                    <span class="text-slate-500">Off Hours</span>
                </span>
            </div>
        </div>

        @if($mode === 'schedule')
            <div class="text-[10px] text-slate-400 dark:text-slate-500 italic">
                <strong class="text-purple-600 dark:text-purple-400 not-italic font-semibold">OPC:</strong> Pesawat RON yang masih menempati parking stand untuk keberangkatan pada hari berikutnya. Total pergerakan operasional = ARR + DEP.
            </div>
        @endif
    </div>
</div>
