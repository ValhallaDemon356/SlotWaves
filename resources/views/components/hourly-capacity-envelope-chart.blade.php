{{--
    HourlyCapacityEnvelopeChart Component
    SlotWaves Two-Direction Operational Aircraft Capacity Envelope Chart
    - X-Axis: Time / Hour (00..23 or OPS window)
    - Y-Axis: Aircraft Count (+Y Arrivals upward, -Y Departures downward)
    - Center Horizontal Axis: Time (Y=0, separating Arrivals & Departures)
    - Unified Operational Capacity Envelope: ONE connected dashed rectangular box bounded by:
        TOP: Arrival Capacity (orange dashed line)
        BOTTOM: Departure Capacity (blue dashed line)
        LEFT: Operating Hours Start (green dashed line)
        RIGHT: Operating Hours End (green dashed line)
    - Pinned corner labels outside plot area:
        Top-left: ARR CAP +[X] A/C
        Bottom-left: DEP CAP -[Y] A/C
        Far-left: OPS [Start]
        Far-right: OPS [End]
    - Cursor-Following Interactive Tooltip (fixed, floating next to cursor on hover, no permanent black overlay)
--}}

@props([
    'mode' => 'schedule', // 'schedule' | 'dau'
    'height' => 140, // half-height in px for arrival / departure area
])

<div class="space-y-3 flex flex-col justify-between h-full select-none"
     x-data="{
         tooltip: {
             visible: false,
             x: 0,
             y: 0,
             hourLabel: '',
             type: '',
             typeLabel: '',
             typeColor: '',
             icon: '',
             actual: 0,
             capacity: 0,
             scope: '',
             status: '',
             statusClass: '',
             extra: null
         },
         updateTooltipPos(e) {
             const tipWidth = 230;
             const tipHeight = 140;
             const offset = 14;
             let x = e.clientX + offset;
             let y = e.clientY + offset;

             if (x + tipWidth > window.innerWidth - 8) {
                 x = e.clientX - tipWidth - offset;
             }
             if (y + tipHeight > window.innerHeight - 8) {
                 y = e.clientY - tipHeight - offset;
             }
             this.tooltip.x = Math.max(8, x);
             this.tooltip.y = Math.max(8, y);
         },
         showBarTooltip(e, item, type) {
             const isArr = type === 'arrival';
             const isDep = type === 'departure';
             const isOpc = type === 'opc';

             @if($mode === 'schedule')
                 const tz = this.displayTimezoneLabel || 'WIB';
                 let scope = 'AIRPORT WIDE';
             @else
                 const tz = 'WIB';
                 let scope = (this.filterTerminal && this.filterTerminal !== 'ALL') ? ('TERMINAL ' + String(this.filterTerminal).replace(/^Terminal\s*/i, '').toUpperCase()) : 'ALL TERMINALS';
             @endif
             const hourLabel = (item.label || item.hour) + ' (' + tz + ')';

             if (isArr) {
                 @if($mode === 'schedule')
                     const actual = Number(item.arrCount || 0);
                 @else
                     const actual = Number(item.arr || 0);
                 @endif
                 const cap = Number(this.arrivalCapacity || 6);
                 let status = 'AVAILABLE';
                 let statusClass = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50';

                 if (!item.isOps) {
                     status = 'OFF HOURS';
                     statusClass = 'bg-slate-800 text-slate-400 border-slate-700';
                 } else if (actual > cap) {
                     status = 'OVER CAPACITY';
                     statusClass = 'bg-purple-500/20 text-purple-300 border-purple-500/50';
                 } else if (actual === cap && cap > 0) {
                     status = 'FULL / MAX';
                     statusClass = 'bg-amber-500/20 text-amber-300 border-amber-500/50';
                 }

                 this.tooltip = {
                     visible: true,
                     x: 0,
                     y: 0,
                     hourLabel: hourLabel,
                     type: 'arrival',
                     typeLabel: 'ARRIVAL',
                     typeColor: 'text-amber-400',
                     icon: '🟠',
                     actual: actual,
                     capacity: cap,
                     scope: scope,
                     status: status,
                     statusClass: statusClass,
                     extra: null
                 };
             } else if (isDep) {
                 @if($mode === 'schedule')
                     const actual = Number(item.depCount || 0);
                 @else
                     const actual = Number(item.dep || 0);
                 @endif
                 const cap = Number(this.departureCapacity || 6);
                 let status = 'AVAILABLE';
                 let statusClass = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50';

                 if (!item.isOps) {
                     status = 'OFF HOURS';
                     statusClass = 'bg-slate-800 text-slate-400 border-slate-700';
                 } else if (actual > cap) {
                     status = 'OVER CAPACITY';
                     statusClass = 'bg-purple-500/20 text-purple-300 border-purple-500/50';
                 } else if (actual === cap && cap > 0) {
                     status = 'FULL / MAX';
                     statusClass = 'bg-blue-500/20 text-blue-300 border-blue-500/50';
                 }

                 this.tooltip = {
                     visible: true,
                     x: 0,
                     y: 0,
                     hourLabel: hourLabel,
                     type: 'departure',
                     typeLabel: 'DEPARTURE',
                     typeColor: 'text-blue-400',
                     icon: '🔵',
                     actual: actual,
                     capacity: cap,
                     scope: scope,
                     status: status,
                     statusClass: statusClass,
                     extra: null
                 };
             } else if (isOpc) {
                 const opcVal = Number(item.opcCount || 0);
                 this.tooltip = {
                     visible: true,
                     x: 0,
                     y: 0,
                     hourLabel: hourLabel,
                     type: 'opc',
                     typeLabel: 'OPC (RON)',
                     typeColor: 'text-purple-400',
                     icon: '🟣',
                     actual: opcVal,
                     capacity: null,
                     scope: scope,
                     status: 'RON STAND OCCUPIED',
                     statusClass: 'bg-purple-500/20 text-purple-300 border-purple-500/50',
                     extra: 'RON Parking Stand Occupied'
                 };
             }
             this.updateTooltipPos(e);
         },
         hideTooltip() {
             this.tooltip.visible = false;
         }
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
            
            {{-- Accessible Reference Markers for automated test suites --}}
            <div class="sr-only" aria-hidden="true">
                <span>Batas Aircraft Capacity - ARR:</span>
                <span>DEP:</span>
                <span x-text="gridNacOffsetPx"></span>
                <span x-text="gridHalfNacOffsetPx"></span>
                <span>Aircraft Capacity</span>
            </div>

            {{-- ── LAYER 1: ONE SINGLE DASHED OPERATIONAL CAPACITY ENVELOPE ── --}}
            {{-- Connected dashed rectangle bounded by: Top=ARR Cap, Bottom=DEP Cap, Left=Ops Start, Right=Ops End --}}
            <template x-if="{{ $mode === 'schedule' ? 'envelopeCoords.isVisible' : '(selectedMetric === \'aircraft\' && envelopeCoords.isVisible)' }}">
                <div class="absolute z-1 transition-all duration-200 pointer-events-none rounded-xs border-t-2 border-b-2 border-l-2 border-r-2 border-dashed border-t-amber-500 border-b-blue-500 border-l-emerald-500 border-r-emerald-500 bg-emerald-500/[0.02] dark:bg-emerald-500/[0.03]"
                     :style="{
                         left: envelopeCoords.left + '%',
                         width: envelopeCoords.width + '%',
                         top: envelopeCoords.top + 'px',
                         bottom: envelopeCoords.bottom + 'px'
                     }"
                     title="Batas Aircraft Capacity">
                    
                    {{-- PINNED CORNER LABELS (Positioned outside plot area so they never overlap bars) --}}
                    {{-- 1. Top-left corner: ARR CAP +[X] A/C --}}
                    <div class="absolute -top-5 left-0 flex items-center gap-1 font-mono text-[8.5px] font-black text-amber-600 dark:text-amber-400 bg-white/95 dark:bg-navy-900/95 px-1.5 py-0.5 rounded shadow-2xs border border-amber-400/80 dark:border-amber-600/80 whitespace-nowrap z-20 pointer-events-none"
                         title="Batas Aircraft Capacity - ARR:">
                        ARR CAP +<span x-text="arrivalCapacity"></span> A/C
                    </div>

                    {{-- 2. Bottom-left corner: DEP CAP -[Y] A/C --}}
                    <div class="absolute -bottom-5 left-0 flex items-center gap-1 font-mono text-[8.5px] font-black text-blue-600 dark:text-blue-400 bg-white/95 dark:bg-navy-900/95 px-1.5 py-0.5 rounded shadow-2xs border border-blue-400/80 dark:border-blue-600/80 whitespace-nowrap z-20 pointer-events-none"
                         title="DEP:">
                        DEP CAP -<span x-text="departureCapacity"></span> A/C
                    </div>

                    {{-- 3. Far-left: OPS [Start] (Positioned on the green dashed vertical line, outside top) --}}
                    <div class="absolute -top-5.5 -left-3.5 flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-white/95 dark:bg-navy-900/95 border border-emerald-500 text-emerald-700 dark:text-emerald-400 font-mono text-[8px] leading-tight shadow-xs whitespace-nowrap z-20 pointer-events-none"
                         title="Operating Hours Start">
                        <span class="font-black text-[7px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400">OPS</span>
                        <span class="font-black text-slate-900 dark:text-white" x-text="opsStartTime"></span>
                    </div>

                    {{-- 4. Far-right: OPS [End] (Positioned on the green dashed vertical line, outside top) --}}
                    <div class="absolute -top-5.5 -right-3.5 flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-white/95 dark:bg-navy-900/95 border border-emerald-500 text-emerald-700 dark:text-emerald-400 font-mono text-[8px] leading-tight shadow-xs whitespace-nowrap z-20 pointer-events-none"
                         title="Operating Hours End">
                        <span class="font-black text-[7px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400">OPS</span>
                        <span class="font-black text-slate-900 dark:text-white" x-text="opsEndTime"></span>
                    </div>
                </div>
            </template>

            {{-- ── LAYER 10: SYNCHRONIZED COLUMNS GRID (Arrivals Above + Time Center + Departures Below) ── --}}
            <div class="relative z-10 w-full"
                 :style="'display: grid; grid-template-columns: repeat(' + @if($mode === 'schedule') activeHourlyDistribution.length @else hourlyCapacityAnalysis.list.length @endif + ', minmax(0, 1fr)); gap: ' + ((@if($mode === 'schedule') activeHourlyDistribution.length @else hourlyCapacityAnalysis.list.length @endif) > 16 ? '2px' : '4px') + ';'">
                
                <template x-for="item in @if($mode === 'schedule') activeHourlyDistribution @else hourlyCapacityAnalysis.list @endif" :key="item.hour">
                    <div class="flex flex-col items-center h-full group relative select-none transition-all duration-150 rounded-md"
                         :class="[
                             {{ $mode === 'schedule' ? '(selectedHour === item.hour)' : '(filterHour === item.hour)' }} ? 'bg-aviation-50/80 dark:bg-aviation-950/60 ring-2 ring-aviation-500 shadow-sm' : 'hover:bg-slate-100/60 dark:hover:bg-navy-800/40',
                             item.isPeak ? 'peak-bar-glow' : ''
                         ]">
                        
                        {{-- ── UPPER SECTION: ARRIVALS (+Y, Grows UPWARD from Center) ── --}}
                        <div class="w-full h-[140px] flex flex-col justify-end items-center px-0.5 pb-1 relative cursor-pointer"
                             @mouseenter="showBarTooltip($event, item, 'arrival')"
                             @mousemove="updateTooltipPos($event)"
                             @mouseleave="hideTooltip()"
                             @click.stop="@if($mode === 'schedule') selectHourWithDirection(item.hour, 'arrivals') @else setHourFilter(item.hour) @endif"
                             title="Click to filter Arrivals">
                            
                            {{-- Top Status Pill (OVER / MAX) --}}
                            <template x-if="item.isOps && item.status === 'OVER CAPACITY'">
                                <div class="absolute top-1 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-purple-600 text-white shadow-2xs z-20 font-mono pointer-events-none">
                                    OVER
                                </div>
                            </template>
                            <template x-if="item.isOps && item.status === 'FULL / MAX'">
                                <div class="absolute top-1 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-amber-500 text-white shadow-2xs z-20 font-mono pointer-events-none">
                                    MAX
                                </div>
                            </template>

                            {{-- Numerical Arrival Count (Readable above bar) --}}
                            <template x-if="{{ $mode === 'schedule' ? '(item.arrCount > 0 || item.opcCount > 0)' : '(item.arr > 0)' }}">
                                <span class="text-[8.5px] sm:text-[9.5px] font-mono font-bold text-amber-600 dark:text-amber-400 mb-0.5 pointer-events-none">
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
                                             @mouseenter.stop="showBarTooltip($event, item, 'opc')"
                                             @mousemove.stop="updateTooltipPos($event)"
                                             @mouseleave.stop="showBarTooltip($event, item, 'arrival')"
                                             :style="'height: ' + Math.max(3, Math.round((item.opcCount / chartMaxScale) * 115)) + 'px'"></div>
                                    </template>
                                @endif

                                {{-- Arrival Bar (Orange, Grows Upward from Center) --}}
                                <template x-if="{{ $mode === 'schedule' ? 'item.arrCount > 0' : 'item.arr > 0' }}">
                                    <div class="w-full bg-amber-500 dark:bg-amber-500 hover:bg-amber-400 transition-all duration-300 shadow-2xs"
                                         @if($mode === 'schedule')
                                             :class="item.opcCount > 0 ? 'rounded-none' : 'rounded-t-xs'"
                                         @else
                                             class="rounded-t-xs"
                                         @endif
                                         :style="'height: ' + Math.max(3, Math.round(((@if($mode === 'schedule') item.arrCount @else item.arr @endif) / chartMaxScale) * 115)) + 'px'"></div>
                                </template>

                                {{-- Baseline tick if 0 arrivals --}}
                                <template x-if="{{ $mode === 'schedule' ? '(item.arrCount === 0 && (!item.opcCount || item.opcCount === 0))' : '(item.arr === 0)' }}">
                                    <div class="w-full max-w-[14px] mx-auto h-0.5 bg-slate-200 dark:bg-navy-800 rounded-xs"></div>
                                </template>
                            </div>
                        </div>

                        {{-- ── CENTER TIME AXIS: TIME (Y=0 Baseline separating Arrival & Departure) ── --}}
                        <div class="w-full h-8 flex items-center justify-center border-y border-slate-200/90 dark:border-slate-800 bg-slate-100/90 dark:bg-navy-950/90 relative z-20 transition-colors cursor-pointer"
                             :class="[
                                 item.isOps ? 'bg-slate-100/90 dark:bg-navy-950/90' : 'bg-slate-200/40 dark:bg-navy-950/40 opacity-75',
                                 {{ $mode === 'schedule' ? '(selectedHour === item.hour)' : '(filterHour === item.hour)' }} ? 'border-aviation-500 dark:border-aviation-400 bg-aviation-100/60 dark:bg-aviation-950/80' : ''
                             ]"
                             @mouseenter="hideTooltip()"
                             @click.stop="@if($mode === 'schedule') selectHour(item.hour) @else setHourFilter(item.hour) @endif"
                             title="Click to filter this hour">
                            <span class="text-[9.5px] sm:text-[10.5px] font-mono transition-colors"
                                  :class="[
                                      {{ $mode === 'schedule' ? '(selectedHour === item.hour)' : '(filterHour === item.hour)' }} ? 'font-black text-aviation-700 dark:text-aviation-300' : (
                                          item.isOps ? 'font-bold text-slate-800 dark:text-slate-200 group-hover:text-aviation-600' : 'text-slate-400 dark:text-slate-500 font-normal'
                                      )
                                  ]"
                                  x-text="item.shortLabel">
                            </span>
                        </div>

                        {{-- ── LOWER SECTION: DEPARTURES (-Y, Grows DOWNWARD from Center) ── --}}
                        <div class="w-full h-[140px] flex flex-col justify-start items-center px-0.5 pt-1 relative cursor-pointer"
                             @mouseenter="showBarTooltip($event, item, 'departure')"
                             @mousemove="updateTooltipPos($event)"
                             @mouseleave="hideTooltip()"
                             @click.stop="@if($mode === 'schedule') selectHourWithDirection(item.hour, 'departures') @else setHourFilter(item.hour) @endif"
                             title="Click to filter Departures">
                            
                            {{-- Departure Bar (Blue, Grows Downward from Center) --}}
                            <div class="w-full max-w-[24px] sm:max-w-[28px] flex flex-col justify-start rounded-b-sm transition-all duration-200">
                                <template x-if="{{ $mode === 'schedule' ? 'item.depCount > 0' : 'item.dep > 0' }}">
                                    <div class="w-full bg-blue-600 dark:bg-blue-500 hover:bg-blue-400 rounded-b-xs transition-all duration-300 shadow-2xs"
                                         :style="'height: ' + Math.max(3, Math.round(((@if($mode === 'schedule') item.depCount @else item.dep @endif) / chartMaxScale) * 115)) + 'px'"></div>
                                </template>

                                {{-- Baseline tick if 0 departures --}}
                                <template x-if="{{ $mode === 'schedule' ? 'item.depCount === 0' : 'item.dep === 0' }}">
                                    <div class="w-full max-w-[14px] mx-auto h-0.5 bg-slate-200 dark:bg-navy-800 rounded-xs"></div>
                                </template>
                            </div>

                            {{-- Numerical Departure Count (Readable below bar) --}}
                            <template x-if="{{ $mode === 'schedule' ? 'item.depCount > 0' : 'item.dep > 0' }}">
                                <span class="text-[8.5px] sm:text-[9.5px] font-mono font-bold text-blue-600 dark:text-blue-400 mt-0.5 pointer-events-none"
                                      x-text="@if($mode === 'schedule') item.depCount @else item.dep @endif">
                                </span>
                            </template>

                            {{-- Bottom Status Pill (OVER / MAX for DEP) --}}
                            <template x-if="item.isOps && ((@if($mode === 'schedule') item.depCount @else item.dep @endif) > departureCapacity)">
                                <div class="absolute bottom-1 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-purple-600 text-white shadow-2xs z-20 font-mono pointer-events-none">
                                    OVER
                                </div>
                            </template>
                            <template x-if="item.isOps && ((@if($mode === 'schedule') item.depCount @else item.dep @endif) === departureCapacity && departureCapacity > 0)">
                                <div class="absolute bottom-1 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-blue-500 text-white shadow-2xs z-20 font-mono pointer-events-none">
                                    MAX
                                </div>
                            </template>
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

    {{-- ══ CURSOR-FOLLOWING INTERACTIVE TOOLTIP (Only shown when hovering bars) ════ --}}
    <div x-show="tooltip.visible"
         x-cloak
         class="fixed z-100 pointer-events-none w-[230px] p-2.5 bg-slate-900/95 dark:bg-navy-950/95 text-white backdrop-blur-md rounded-xl shadow-2xl border border-slate-700/80 text-xs select-none transition-opacity duration-100"
         :style="'left: ' + tooltip.x + 'px; top: ' + tooltip.y + 'px;'"
         style="display: none;">
        
        {{-- Tooltip Header: Hour & Status Pill --}}
        <div class="flex items-center justify-between border-b border-slate-700/60 pb-1 mb-1.5">
            <span class="font-mono font-bold text-[11px] text-slate-100" x-text="tooltip.hourLabel"></span>
            <span class="text-[8px] font-black uppercase font-mono px-1.5 py-0.5 rounded border"
                  :class="tooltip.statusClass"
                  x-text="tooltip.status"></span>
        </div>

        {{-- Type with colored icon (🟠 Arrival or 🔵 Departure or 🟣 OPC) --}}
        <div class="flex items-center gap-1.5 mb-1.5 text-[11px] font-bold" :class="tooltip.typeColor">
            <span x-text="tooltip.icon"></span>
            <span x-text="tooltip.typeLabel"></span>
        </div>

        {{-- Main Data Stat: Aircraft Count / Capacity --}}
        <div class="bg-slate-800/80 dark:bg-navy-900/80 rounded-lg p-2 border border-slate-700/50 mb-1.5">
            <div class="text-[8.5px] font-mono text-slate-400 uppercase tracking-wider">Aircraft</div>
            <div class="flex items-baseline gap-1 font-mono">
                <span class="text-base font-black text-white" x-text="tooltip.actual"></span>
                <template x-if="tooltip.capacity !== null">
                    <span class="text-xs font-bold text-slate-400">/ <span x-text="tooltip.capacity"></span> A/C</span>
                </template>
                <template x-if="tooltip.capacity === null">
                    <span class="text-xs font-bold text-slate-400">A/C</span>
                </template>
            </div>
        </div>

        {{-- Scope --}}
        <div class="flex items-center justify-between text-[10px] font-mono text-slate-400">
            <span>Scope:</span>
            <span class="font-bold text-slate-200" x-text="tooltip.scope"></span>
        </div>

        <template x-if="tooltip.extra">
            <div class="text-[9px] font-mono text-purple-300/80 mt-1 pt-1 border-t border-slate-700/60" x-text="tooltip.extra"></div>
        </template>
    </div>
</div>
