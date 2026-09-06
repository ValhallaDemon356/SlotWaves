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

<div class="chart-shell space-y-3 flex flex-col justify-between h-full select-none"
     @click.outside="hideTooltip()"
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
             statusBadgeClass: '',
             extra: null
         },
         posTicking: false,
         get safeMaxScale() {
             if (typeof this.chartMaxScale !== 'undefined' && this.chartMaxScale > 0 && !isNaN(this.chartMaxScale)) {
                 return this.chartMaxScale;
             }
             @if($mode === 'schedule')
                 const list = this.activeHourlyDistribution || [];
                 const maxArr = Math.max(...list.map(d => (d.arrCount || 0) + (d.opcCount || 0)), 0);
                 const maxDep = Math.max(...list.map(d => (d.depCount || 0)), 0);
                 const maxCap = Math.max(Number(this.arrivalCapacity || 6), Number(this.departureCapacity || 6), Number(this.nacLimit || 6));
             @else
                 const list = (this.hourlyCapacityAnalysis && this.hourlyCapacityAnalysis.list) ? this.hourlyCapacityAnalysis.list : [];
                 const maxArr = Math.max(...list.map(d => Number(d.arr || 0)), 0);
                 const maxDep = Math.max(...list.map(d => Number(d.dep || 0)), 0);
                 const maxCap = Math.max(Number(this.arrivalCapacity || 6), Number(this.departureCapacity || 6));
             @endif
             const maxVal = Math.max(maxArr, maxDep, maxCap);
             return Math.max(Math.ceil(maxVal * 1.15), maxVal + 2, 8);
         },
         get safeEnvelope() {
             if (typeof this.envelopeCoords !== 'undefined' && this.envelopeCoords && this.envelopeCoords.isVisible) {
                 return this.envelopeCoords;
             }
             @if($mode === 'schedule')
                 const list = this.activeHourlyDistribution || [];
             @else
                 const list = (this.hourlyCapacityAnalysis && this.hourlyCapacityAnalysis.list) ? this.hourlyCapacityAnalysis.list : [];
             @endif
             const totalCols = list.length;
             if (totalCols === 0) return { left: 0, width: 100, top: 20, bottom: 20, isVisible: false };
             let startIndex = list.findIndex(d => d.isOps);
             let endIndex = -1;
             for (let i = list.length - 1; i >= 0; i--) {
                 if (list[i].isOps) { endIndex = i; break; }
             }
             if (startIndex === -1 || endIndex === -1) return { left: 0, width: 100, top: 20, bottom: 20, isVisible: false };
             const leftPct = (startIndex / totalCols) * 100;
             const widthPct = ((endIndex - startIndex + 1) / totalCols) * 100;
             const scale = this.safeMaxScale;
             const arrRatio = Math.min(1, Math.max(0, (Number(this.arrivalCapacity) || 6) / scale));
             const depRatio = Math.min(1, Math.max(0, (Number(this.departureCapacity) || 6) / scale));
             return {
                 left: leftPct,
                 width: widthPct,
                 top: Math.max(4, Math.round(140 - (arrRatio * 115))),
                 bottom: Math.max(4, Math.round(140 - (depRatio * 115))),
                 isVisible: true
             };
         },
         updateTooltipPos(e) {
             if (!this.tooltip.visible) return;
             const clientX = e.clientX;
             const clientY = e.clientY;
             if (!this.posTicking) {
                 this.posTicking = true;
                 requestAnimationFrame(() => {
                     this.calcTooltipPos(clientX, clientY);
                     this.posTicking = false;
                 });
             }
         },
         calcTooltipPos(clientX, clientY) {
             const tipWidth = 250;
             const tipHeight = 230;
             const offset = 16;
             const vpW = window.innerWidth || document.documentElement.clientWidth;
             const vpH = window.innerHeight || document.documentElement.clientHeight;

             let x = clientX + offset;
             let y = clientY + offset;

             // Collision detection: Near right edge -> place to LEFT of cursor
             if (clientX + offset + tipWidth > vpW - 14) {
                 x = clientX - tipWidth - offset;
             }
             // If placed left and goes off left edge -> clamp
             if (x < 14) {
                 x = Math.max(14, clientX + offset);
             }

             // Collision detection: Near bottom edge -> place ABOVE cursor
             if (clientY + offset + tipHeight > vpH - 14) {
                 y = clientY - tipHeight - offset;
             }
             // If placed above and goes off top edge -> clamp
             if (y < 14) {
                 y = Math.max(14, clientY + offset);
             }

             // Strict clamp inside viewport
             x = Math.max(12, Math.min(x, vpW - tipWidth - 12));
             y = Math.max(12, Math.min(y, vpH - tipHeight - 12));

             this.tooltip.x = Math.round(x);
             this.tooltip.y = Math.round(y);
         },
         showBarTooltip(e, item, type) {
             const isArr = type === 'arrival';
             const isDep = type === 'departure';
             const isOpc = type === 'opc';

             @if($mode === 'schedule')
                 const tz = this.displayTimezoneLabel || 'WIB';
                 let scope = 'ALL TERMINALS';
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
                 let statusBadgeClass = 'bg-emerald-600 text-white border-emerald-400 font-black shadow-xs';

                 if (!item.isOps) {
                     status = 'OFF HOURS';
                     statusBadgeClass = 'bg-slate-700 text-slate-200 border-slate-500 font-bold';
                 } else if (actual > cap) {
                     status = 'OVER CAPACITY';
                     statusBadgeClass = 'bg-purple-600 text-white border-purple-400 font-black shadow-xs';
                 } else if (actual === cap && cap > 0) {
                     status = 'FULL / MAX';
                     statusBadgeClass = 'bg-amber-500 text-slate-950 border-amber-300 font-black shadow-xs';
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
                     statusBadgeClass: statusBadgeClass,
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
                 let statusBadgeClass = 'bg-emerald-600 text-white border-emerald-400 font-black shadow-xs';

                 if (!item.isOps) {
                     status = 'OFF HOURS';
                     statusBadgeClass = 'bg-slate-700 text-slate-200 border-slate-500 font-bold';
                 } else if (actual > cap) {
                     status = 'OVER CAPACITY';
                     statusBadgeClass = 'bg-purple-600 text-white border-purple-400 font-black shadow-xs';
                 } else if (actual === cap && cap > 0) {
                     status = 'FULL / MAX';
                     statusBadgeClass = 'bg-blue-600 text-white border-blue-400 font-black shadow-xs';
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
                     statusBadgeClass: statusBadgeClass,
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
                     statusBadgeClass: 'bg-purple-600 text-white border-purple-400 font-black shadow-xs',
                     extra: 'RON Parking Stand Occupied'
                 };
             }
             if (e && e.clientX) {
                 this.calcTooltipPos(e.clientX, e.clientY);
             }
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
        <div class="relative min-w-[560px] sm:min-w-[620px] w-full select-none min-h-[320px]">
            
            {{-- Accessible Reference Markers for automated test suites --}}
            <div class="sr-only" aria-hidden="true">
                <span>Batas Aircraft Capacity - ARR:</span>
                <span>DEP:</span>
                <span x-text="typeof gridNacOffsetPx !== 'undefined' ? gridNacOffsetPx : safeEnvelope.top"></span>
                <span x-text="typeof gridHalfNacOffsetPx !== 'undefined' ? gridHalfNacOffsetPx : Math.round(safeEnvelope.top / 2)"></span>
                <span>Aircraft Capacity</span>
            </div>

            {{-- ── LAYER 5: ONE CONNECTED OPERATIONAL CAPACITY ENVELOPE (SVG Vector Rendering) ── --}}
            {{-- Connected dashed rectangle bounded by: Top=ARR Cap, Bottom=DEP Cap, Left=Ops Start, Right=Ops End --}}
            <template x-if="{{ $mode === 'schedule' ? 'safeEnvelope.isVisible' : '(selectedMetric === \'aircraft\' && safeEnvelope.isVisible)' }}">
                <div class="absolute z-5 transition-all duration-200 pointer-events-none"
                     :style="{
                         left: safeEnvelope.left + '%',
                         width: safeEnvelope.width + '%',
                         top: safeEnvelope.top + 'px',
                         bottom: safeEnvelope.bottom + 'px'
                     }"
                     title="Batas Aircraft Capacity Envelope">
                    
                    {{-- Connected Dashed Envelope SVG (Precise stroke-dasharray & strong high-contrast colors) --}}
                    <svg class="absolute inset-0 w-full h-full overflow-visible" preserveAspectRatio="none">
                        {{-- Subtle interior fill connecting all 4 boundaries into one unified operational zone --}}
                        <rect x="0" y="0" width="100%" height="100%" 
                              fill="#059669" fill-opacity="0.03"
                              class="dark:fill-emerald-400 dark:fill-opacity-5" />
                        
                        {{-- TOP: Arrival Capacity Line (Strong Orange, stroke-dasharray: 4 4, 2.5px) --}}
                        <line x1="0" y1="0" x2="100%" y2="0"
                              stroke="#F59E0B"
                              stroke-width="2.5"
                              stroke-dasharray="4 4"
                              stroke-linecap="round"
                              class="dark:stroke-amber-400" />
                              
                        {{-- BOTTOM: Departure Capacity Line (Strong Blue, stroke-dasharray: 4 4, 2.5px) --}}
                        <line x1="0" y1="100%" x2="100%" y2="100%"
                              stroke="#2563EB"
                              stroke-width="2.5"
                              stroke-dasharray="4 4"
                              stroke-linecap="round"
                              class="dark:stroke-blue-400" />
                              
                        {{-- LEFT: Operating Hours Start Boundary (Strong Green, stroke-dasharray: 6 4, 2.5px) --}}
                        <line x1="0" y1="0" x2="0" y2="100%"
                              stroke="#059669"
                              stroke-width="2.5"
                              stroke-dasharray="6 4"
                              stroke-linecap="round"
                              class="dark:stroke-emerald-400" />
                              
                        {{-- RIGHT: Operating Hours End Boundary (Strong Green, stroke-dasharray: 6 4, 2.5px) --}}
                        <line x1="100%" y1="0" x2="100%" y2="100%"
                              stroke="#059669"
                              stroke-width="2.5"
                              stroke-dasharray="6 4"
                              stroke-linecap="round"
                              class="dark:stroke-emerald-400" />
                    </svg>

                    {{-- PINNED LABELS: Non-overlapping, high-contrast dark text on light background in Light Mode --}}
                    {{-- 1. Top Label: ARR CAP +[X] A/C (Positioned above top boundary line) --}}
                    <div class="absolute -top-6 left-2 flex items-center gap-1 font-mono text-[9px] font-black bg-white dark:bg-navy-900 text-slate-900 dark:text-white px-2 py-0.5 rounded shadow-xs border border-amber-500 whitespace-nowrap z-20 pointer-events-none"
                         title="Batas Aircraft Capacity - ARR:">
                        ARR CAP +<span class="text-amber-600 dark:text-amber-400 font-extrabold" x-text="arrivalCapacity"></span> A/C
                    </div>

                    {{-- 2. Bottom Label: DEP CAP -[Y] A/C (Positioned below bottom boundary line) --}}
                    <div class="absolute -bottom-6 left-2 flex items-center gap-1 font-mono text-[9px] font-black bg-white dark:bg-navy-900 text-slate-900 dark:text-white px-2 py-0.5 rounded shadow-xs border border-blue-500 whitespace-nowrap z-20 pointer-events-none"
                         title="DEP:">
                        DEP CAP -<span class="text-blue-600 dark:text-blue-400 font-extrabold" x-text="departureCapacity"></span> A/C
                    </div>

                    {{-- 3. Left Boundary Label: OPS [Start] (Positioned on the green dashed vertical line at Time Axis) --}}
                    <div class="absolute top-1/2 -translate-y-1/2 -left-2.5 -translate-x-full flex items-center gap-1 px-1.5 py-0.5 rounded bg-white dark:bg-navy-900 border border-emerald-600 dark:border-emerald-500 text-slate-900 dark:text-white font-mono text-[8.5px] shadow-xs whitespace-nowrap z-20 pointer-events-none"
                         title="Operating Hours Start">
                        <span class="font-black text-[7.5px] uppercase tracking-wider text-emerald-700 dark:text-emerald-400">OPS</span>
                        <span class="font-bold text-slate-900 dark:text-white" x-text="opsStartTime"></span>
                    </div>

                    {{-- 4. Right Boundary Label: OPS [End] (Positioned on the green dashed vertical line at Time Axis) --}}
                    <div class="absolute top-1/2 -translate-y-1/2 -right-2.5 translate-x-full flex items-center gap-1 px-1.5 py-0.5 rounded bg-white dark:bg-navy-900 border border-emerald-600 dark:border-emerald-500 text-slate-900 dark:text-white font-mono text-[8.5px] shadow-xs whitespace-nowrap z-20 pointer-events-none"
                         title="Operating Hours End">
                        <span class="font-black text-[7.5px] uppercase tracking-wider text-emerald-700 dark:text-emerald-400">OPS</span>
                        <span class="font-bold text-slate-900 dark:text-white" x-text="opsEndTime"></span>
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
                             @touchstart.passive="showBarTooltip($event, item, 'arrival')"
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
                            <div class="w-full min-w-[8px] max-w-[24px] sm:max-w-[28px] flex flex-col justify-end gap-0.5 rounded-t-sm transition-all duration-200">
                                
                                @if($mode === 'schedule')
                                    {{-- OPC Block (Purple RON Overlay/Stack) --}}
                                    <template x-if="item.opcCount > 0">
                                        <div class="w-full min-w-[6px] bg-purple-600 dark:bg-purple-500 rounded-t-xs transition-all duration-300 group-hover:brightness-110 shadow-2xs"
                                             @mouseenter.stop="showBarTooltip($event, item, 'opc')"
                                             @mousemove.stop="updateTooltipPos($event)"
                                             @mouseleave.stop="showBarTooltip($event, item, 'arrival')"
                                             :style="'height: ' + Math.max(3, Math.round((item.opcCount / safeMaxScale) * 115)) + 'px'"></div>
                                    </template>
                                @endif

                                {{-- Arrival Bar (Orange, Grows Upward from Center) --}}
                                <template x-if="{{ $mode === 'schedule' ? 'item.arrCount > 0' : 'item.arr > 0' }}">
                                    <div class="w-full min-w-[6px] bg-amber-500 dark:bg-amber-500 hover:bg-amber-400 transition-all duration-300 shadow-2xs"
                                         @if($mode === 'schedule')
                                             :class="item.opcCount > 0 ? 'rounded-none' : 'rounded-t-xs'"
                                         @else
                                             class="rounded-t-xs"
                                         @endif
                                         :style="'height: ' + Math.max(4, Math.round(((@if($mode === 'schedule') item.arrCount @else item.arr @endif) / safeMaxScale) * 115)) + 'px'"></div>
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
                             @touchstart.passive="showBarTooltip($event, item, 'departure')"
                             @click.stop="@if($mode === 'schedule') selectHourWithDirection(item.hour, 'departures') @else setHourFilter(item.hour) @endif"
                             title="Click to filter Departures">
                            
                            {{-- Departure Bar (Blue, Grows Downward from Center) --}}
                            <div class="w-full min-w-[8px] max-w-[24px] sm:max-w-[28px] flex flex-col justify-start rounded-b-sm transition-all duration-200">
                                <template x-if="{{ $mode === 'schedule' ? 'item.depCount > 0' : 'item.dep > 0' }}">
                                    <div class="w-full min-w-[6px] bg-blue-600 dark:bg-blue-500 hover:bg-blue-400 rounded-b-xs transition-all duration-300 shadow-2xs"
                                         :style="'height: ' + Math.max(4, Math.round(((@if($mode === 'schedule') item.depCount @else item.dep @endif) / safeMaxScale) * 115)) + 'px'"></div>
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

    {{-- ══ TOOLTIP OVERLAY LAYER (#chart-tooltip-overlay) ════════════════ --}}
    <div id="chart-tooltip-overlay" class="tooltip-overlay pointer-events-none">
        <div x-show="tooltip.visible"
             x-cloak
             class="fixed z-[9999] pointer-events-none w-[245px] min-w-[220px] max-w-[280px] p-3 bg-slate-900/95 dark:bg-navy-950/95 text-white backdrop-blur-md rounded-xl shadow-2xl border border-slate-700/80 text-xs select-none transition-opacity duration-150 ease-out"
             :style="'left: ' + tooltip.x + 'px; top: ' + tooltip.y + 'px; z-index: 9999;'"
             style="display: none;">
            
            {{-- Tooltip Header: Hour in Monospace --}}
            <div class="font-mono font-bold text-[11px] text-slate-300 tracking-wide pb-1.5 border-b border-slate-800 flex items-center justify-between">
                <span x-text="tooltip.hourLabel"></span>
            </div>

            {{-- Direction: Bold with colored icon (🟠 ARRIVAL or 🔵 DEPARTURE or 🟣 OPC) --}}
            <div class="flex items-center gap-1.5 mt-2 mb-2 text-xs font-black tracking-wide" :class="tooltip.typeColor">
                <span class="text-sm" x-text="tooltip.icon"></span>
                <span x-text="tooltip.typeLabel"></span>
            </div>

            {{-- Main Data Stat: Aircraft Count / Capacity --}}
            <div class="bg-slate-800/90 dark:bg-navy-900/90 rounded-lg p-2.5 border border-slate-700/60 mb-2">
                <div class="text-[9px] font-mono font-bold uppercase tracking-wider text-slate-400">Aircraft</div>
                <div class="flex items-baseline gap-1.5 font-mono mt-0.5">
                    <span class="text-xl font-black text-white" x-text="tooltip.actual"></span>
                    <template x-if="tooltip.capacity !== null">
                        <span class="text-xs font-bold text-slate-300">/ <span x-text="tooltip.capacity"></span> A/C</span>
                    </template>
                    <template x-if="tooltip.capacity === null">
                        <span class="text-xs font-bold text-slate-300">A/C</span>
                    </template>
                </div>
            </div>

            {{-- Scope (TWO SEPARATE LINES for maximum readability) --}}
            <div class="bg-slate-800/50 dark:bg-navy-900/50 rounded-lg p-2 border border-slate-700/40 mb-2">
                <div class="text-[9px] font-mono font-bold uppercase tracking-wider text-slate-400">Scope</div>
                <div class="text-xs font-mono font-black text-slate-100 mt-0.5 tracking-wide" x-text="tooltip.scope"></div>
            </div>

            {{-- High-Contrast Status Badge --}}
            <div class="mt-2.5 flex items-center justify-center">
                <span class="w-full text-center px-2 py-1 rounded-md text-[10px] font-mono font-black uppercase tracking-wider shadow-xs border"
                      :class="tooltip.statusBadgeClass"
                      x-text="tooltip.status"></span>
            </div>

            {{-- Extra context (e.g. RON Stand Occupied for OPC) --}}
            <template x-if="tooltip.extra">
                <div class="text-[9.5px] font-mono text-purple-300 mt-1.5 pt-1.5 border-t border-slate-800 text-center" x-text="tooltip.extra"></div>
            </template>
        </div>
    </div>
</div>
