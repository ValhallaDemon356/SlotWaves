@extends('layouts.app')

@section('title', 'SlotWaves — 24-Hour Timeline: ' . $upload->original_filename)
@section('bodyClass', 'bg-navy-950 text-slate-100 min-h-screen')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/interactjs@1.10.27/dist/interact.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
/* ── Core reset for timeline rendering ────────────────────────────── */
* { box-sizing: border-box; }
.tl-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.tl-canvas { position: relative; width: max-content; }

/* ── Hour & Minute grid lines ─────────────────────────────────────── */
.tl-col-border { border-right: 1px solid #1e293b; position: relative; }
.tl-sub-tick   { position: absolute; top: 0; bottom: 0; border-right: 1px dashed rgba(255,255,255,0.06); pointer-events: none; }
.tl-row-border { border-bottom: 1px solid #1e293b; }

/* ── Off-hour shading ─────────────────────────────────────────────── */
.off-hr { background: rgba(5, 9, 20, 0.92); }

/* ── Flight cards (Strict non-overlapping visual lane layout) ─────── */
.slot-block {
    position: absolute;
    cursor: grab;
    user-select: none;
    z-index: 10;
    will-change: transform;
    min-width: 96px;
    min-height: 48px;
}
.slot-block:active { cursor: grabbing; }
.slot-block.dragging {
    z-index: 60 !important;
    box-shadow: 0 16px 45px rgba(0,0,0,.85);
    opacity: .95;
}
.slot-block-inner {
    width: 100%;
    height: 100%;
    border-radius: 12px;
    padding: 6px 9px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border: 1px solid rgba(255,255,255,.32);
    overflow: hidden;
    box-shadow: 0 4px 14px rgba(0,0,0,0.35);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.slot-block-inner:hover {
    border-color: rgba(255,255,255,0.8);
    box-shadow: 0 8px 24px rgba(0,0,0,0.6);
    transform: translateY(-1px);
}

/* Card typography & hierarchy */
.slot-flight-no  { font-size: 13px; font-weight: 900; letter-spacing: .02em; color: #ffffff; line-height: 1.1; font-family: 'JetBrains Mono', monospace; }
.slot-ac-type    { font-size: 10.5px; font-weight: 700; color: rgba(255,255,255,.92); font-family: 'Inter', sans-serif; line-height: 1.1; }
.slot-iata       { font-size: 12px; font-weight: 900; letter-spacing: .04em; color: #ffffff; line-height: 1.1; font-family: 'JetBrains Mono', monospace; }

/* Off-hour badge */
.off-hour-badge  { position: absolute; top: 3px; right: 4px; font-size: 7px; font-weight: 800; background: rgba(0,0,0,0.6); padding: 1px 4px; border-radius: 3px; color: #f87171; }

/* Debug info pill */
.debug-pill {
    position: absolute; top: 3px; right: 4px;
    font-size: 7.5px; font-weight: 800; font-family: monospace;
    background: rgba(0,0,0,0.75); color: #38bdf8; padding: 1px 4px; border-radius: 3px;
}

/* Temporary drag conflict indicator */
.drag-status-badge {
    position: absolute; top: -20px; left: 50%; transform: translateX(-50%);
    font-size: 9px; font-weight: 900; padding: 1px 7px; border-radius: 4px;
    white-space: nowrap; pointer-events: none; z-index: 100;
}
.status-avail { background: #15803d; color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.5); }
.status-conflict { background: #b91c1c; color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.5); }

/* ── Operating-hours frame ───────────────────────────────────────── */
.ops-frame {
    position: absolute;
    top: 0; bottom: 0;
    border: 2px dashed rgba(59,130,246,.55);
    background: rgba(39,100,170,.04);
    pointer-events: none;
    z-index: 5;
    transition: left .08s ease, width .08s ease;
}
.ops-handle:hover { background: rgba(59,130,246,.55); }
.ops-handle.left  { left: 0; border-right: 2px solid rgba(59,130,246,.8); }
.ops-handle.right { right: 0; border-left: 2px solid rgba(59,130,246,.8); }

/* ── Section label strip ─────────────────────────────────────────── */
.section-label {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 80px;
    display: flex; align-items: center; justify-content: center;
    background: #050914;
    border-right: 1px solid #1e293b;
    z-index: 25;
}
.section-label span {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    font-size: 10px; font-weight: 900;
    letter-spacing: .15em; text-transform: uppercase;
}

/* ── Top bar toolbar ─────────────────────────────────────────────── */
.topbar { position: sticky; top: 0; z-index: 100; }

/* ── Non-blocking Tooltip ────────────────────────────────────────── */
.tl-tooltip {
    position: fixed;
    z-index: 999;
    pointer-events: none;
    background: #090E1A;
    border: 1px solid #1E293B;
    border-radius: 14px;
    padding: 12px 15px;
    box-shadow: 0 16px 36px rgba(0,0,0,0.85);
    font-size: 11px;
    color: #F8FAFC;
    max-width: 300px;
}

/* ── Toast notification ──────────────────────────────────────────── */
#toast {
    position: fixed; bottom: 24px; right: 24px;
    padding: 10px 18px; border-radius: 12px;
    font-size: 12px; font-weight: 700;
    pointer-events: none;
    opacity: 0; transition: opacity .25s ease;
    z-index: 999;
}
#toast.show { opacity: 1; }
#toast.ok   { background: #15803d; color: #fff; box-shadow: 0 8px 24px rgba(21,128,61,0.4); }
#toast.err  { background: #b91c1c; color: #fff; box-shadow: 0 8px 24px rgba(185,28,28,0.4); }
</style>
@endpush

@section('content')

{{-- Toast notification --}}
<div id="toast"></div>

<div x-data="slotTimeline()" x-init="init()" class="min-h-screen flex flex-col bg-navy-950 text-slate-100">

{{-- ══ TOP BAR TOOLBAR ══════════════════════════════════════════════════════ --}}
<div class="topbar bg-navy-900/90 backdrop-blur-md border-b border-slate-800/80 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 shadow-xl print:hidden">

    {{-- Brand & Upload info --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('schedule.dashboard', $upload->id) }}" class="w-8 h-8 rounded-xl bg-aviation-600 flex items-center justify-center shadow-md shadow-aviation-600/30 text-white hover:scale-105 transition">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
            </svg>
        </a>
        <div>
            <div class="flex items-center gap-2">
                <span class="text-sm font-black text-white tracking-tight">24-Hour Timeline</span>
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-aviation-950 text-aviation-300 border border-aviation-800 font-mono">BDO Operations</span>
            </div>
            <div class="text-[10px] text-slate-400 font-mono max-w-[240px] truncate">{{ $upload->original_filename }}</div>
        </div>
    </div>

    {{-- Timeline Controls --}}
    <div class="flex flex-wrap items-center gap-2.5">
        
        {{-- Zoom slider --}}
        <div class="flex items-center gap-2 bg-navy-950 px-3 py-1.5 rounded-xl border border-slate-800">
            <span class="text-[11px] text-slate-400 font-mono">Zoom</span>
            <input type="range" min="100" max="220" step="5" x-model.number="colW" @input="calculateTimelineLayout()"
                   class="w-20 sm:w-24 h-1 accent-aviation-500 cursor-pointer">
            <span class="text-[11px] text-aviation-400 font-mono w-9 text-right" x-text="colW + 'px'"></span>
        </div>

        {{-- Fit 24H button --}}
        <button type="button" @click="fitTimeline()"
                class="px-2.5 py-1.5 rounded-xl bg-navy-950 hover:bg-navy-800 border border-slate-800 text-[11px] font-mono font-bold text-slate-300 hover:text-white transition cursor-pointer">
            Fit 24H
        </button>

        {{-- Ops Hours Trigger & Control --}}
        <button type="button" @click="openOpsEditor()"
                class="flex items-center gap-2 bg-navy-950 hover:bg-navy-800 border border-slate-800 hover:border-aviation-500/50 px-3 py-1.5 rounded-xl text-[11px] font-mono font-bold text-slate-300 hover:text-white transition cursor-pointer shadow-sm">
            <span class="w-2 h-2 rounded-full bg-aviation-400"></span>
            <span class="text-slate-400">OPS</span>
            <span class="text-aviation-400" x-text="pad(opsStart)+':00 → '+pad(opsEnd)+':00'"></span>
            <span class="text-[10px] text-slate-500 font-sans font-normal" x-text="'('+activeHours+'h)'"></span>
            <span class="text-slate-400 hover:text-aviation-300">EDIT ⚙</span>
        </button>

        {{-- Snap Grid Interval --}}
        <div class="hidden sm:flex items-center gap-1.5 bg-navy-950 px-3 py-1.5 rounded-xl border border-slate-800">
            <span class="text-[11px] text-slate-400 font-mono">Grid</span>
            <select x-model.number="gridInterval" class="bg-navy-900 text-aviation-400 text-[11px] font-mono font-bold border border-slate-700 rounded-md px-1.5 py-0.5 focus:outline-none">
                <option value="1">1 min</option>
                <option value="5">5 min</option>
                <option value="10">10 min</option>
                <option value="15">15 min</option>
            </select>
        </div>

        {{-- Row Height slider --}}
        <div class="hidden md:flex items-center gap-2 bg-navy-950 px-3 py-1.5 rounded-xl border border-slate-800">
            <span class="text-[11px] text-slate-400 font-mono">Row</span>
            <input type="range" min="48" max="96" step="4" x-model.number="rowH"
                   class="w-16 h-1 accent-aviation-500 cursor-pointer">
            <span class="text-[11px] text-aviation-400 font-mono w-7 text-right" x-text="rowH + 'px'"></span>
        </div>

        {{-- Debug Mode Toggle --}}
        <button type="button" @click="debugMode = !debugMode"
                class="px-2.5 py-1.5 rounded-xl border text-[11px] font-mono font-bold transition cursor-pointer select-none"
                :class="debugMode ? 'bg-sky-950 border-sky-500 text-sky-300' : 'bg-navy-950 border-slate-800 text-slate-400 hover:text-slate-200'">
            ⚙ Debug
        </button>
    </div>

    {{-- Actions / Export --}}
    <div class="flex items-center gap-2">
        <a href="{{ route('schedule.dashboard', $upload->id) }}" class="text-[11px] font-semibold text-slate-400 hover:text-white px-2.5 py-1.5 rounded-lg hover:bg-navy-800 transition">
            &larr; Dashboard
        </a>
        <button @click="downloadJpg" class="btn-aviation-primary text-[11px] px-3.5 py-1.5 rounded-xl font-bold transition shadow cursor-pointer flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export JPG
        </button>
        <button @click="printPdf" class="text-[11px] bg-navy-800 hover:bg-navy-700 border border-slate-700 px-3.5 py-1.5 rounded-xl font-bold text-slate-200 transition cursor-pointer flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print PDF
        </button>
    </div>
</div>

{{-- ══ OPS HOURS EDIT MODAL ══════════════════════════════════════════════════ --}}
<div x-show="showOpsModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-4"
     @keydown.escape.window="showOpsModal = false">
    <div class="bg-navy-900 border border-slate-700 rounded-2xl p-5 w-full max-w-sm shadow-2xl" @click.away="showOpsModal = false">
        <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full bg-aviation-500"></div>
                <h3 class="text-sm font-black text-white uppercase tracking-wider">Operational Hours</h3>
            </div>
            <button @click="showOpsModal = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
        </div>
        <div class="space-y-4 text-xs font-mono">
            <div>
                <label class="block text-slate-400 mb-1 font-bold">Start Time (OPS START)</label>
                <select x-model.number="tempOpsStart" class="w-full bg-navy-950 border border-slate-700 rounded-xl px-3 py-2 text-white font-bold focus:border-aviation-500 focus:outline-none">
                    <template x-for="h in 24" :key="h">
                        <option :value="h-1" x-text="pad(h-1)+':00'"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-slate-400 mb-1 font-bold">End Time (OPS END)</label>
                <select x-model.number="tempOpsEnd" class="w-full bg-navy-950 border border-slate-700 rounded-xl px-3 py-2 text-white font-bold focus:border-aviation-500 focus:outline-none">
                    <template x-for="h in 24" :key="h">
                        <option :value="h" x-text="pad(h)+':00'"></option>
                    </template>
                </select>
            </div>
            <div class="p-3 rounded-xl bg-navy-950/80 border border-slate-800 flex justify-between items-center text-[11px]">
                <span class="text-slate-400 font-sans">Active Operational Window:</span>
                <span class="font-bold text-aviation-400 font-mono" x-text="Math.max(0, tempOpsEnd - tempOpsStart) + ' Hours'"></span>
            </div>
            <template x-if="tempOpsEnd <= tempOpsStart">
                <div class="text-red-400 text-[11px] font-bold">End time must be later than start time.</div>
            </template>
        </div>
        <div class="flex justify-end gap-2 mt-5 pt-3 border-t border-slate-800">
            <button type="button" @click="showOpsModal = false" class="px-3.5 py-1.5 rounded-xl bg-navy-950 hover:bg-navy-800 border border-slate-700 text-slate-300 text-xs font-bold transition">Cancel</button>
            <button type="button" @click="applyOpsHours()" class="px-4 py-1.5 rounded-xl bg-aviation-600 hover:bg-aviation-500 text-white text-xs font-bold transition shadow-md shadow-aviation-600/30">Apply</button>
        </div>
    </div>
</div>

{{-- ══ VISIBLE BROWSER TIMELINE CANVAS ══════════════════════════════════════ --}}
<div id="timeline-content" class="flex-1 p-4 sm:p-6">

    {{-- Board Title & Station Header --}}
    <div class="text-center mb-5">
        <h2 class="text-xl sm:text-2xl font-black tracking-[.16em] uppercase text-white">Airport Operational Slot Schedule</h2>
        <p class="text-[11px] text-slate-400 mt-1 font-mono">
            Bandara Husein Sastranegara &bull; BDO &nbsp;|&nbsp; Generated {{ now()->format('d M Y H:i') }} WIB &nbsp;|&nbsp; {{ $layout['totalFlights'] }} Scheduled Flights
        </p>
    </div>

    {{-- Legend Ribbon --}}
    <div class="flex flex-wrap justify-center items-center gap-4 sm:gap-6 mb-5 text-xs">
        @foreach([
            ['#1e40af','Departure Domestic (Dark Blue)'],
            ['#3b82f6','Departure International (Light Blue)'],
            ['#975432','Arrival Domestic (Warm Orange)'],
            ['#e9a52f','Arrival International (Light Orange)'],
        ] as [$c,$l])
        <div class="flex items-center gap-1.5">
            <div class="w-4 h-3 rounded-md shadow-xs" style="background:{{ $c }}"></div>
            <span class="text-[11px] font-semibold text-slate-300">{{ $l }}</span>
        </div>
        @endforeach
        <div class="flex items-center gap-1.5">
            <div class="w-4 h-3 rounded-md bg-navy-950 border border-slate-800"></div>
            <span class="text-[11px] font-semibold text-slate-500">Off-Hour Shading</span>
        </div>
    </div>

    {{-- ── TIMELINE CONTAINER & SYNCHRONIZED BOTTOM TABLE ────────────────── --}}
    <div class="tl-wrap rounded-2xl border border-slate-800 shadow-2xl bg-navy-900 overflow-x-auto custom-scrollbar">
    <div class="tl-canvas" :style="'width:'+canvasWidth+'px'">

        {{-- Hour Axis Header Row (Top of canvas) --}}
        <div class="flex bg-navy-950 border-b border-slate-800" style="padding-left:80px">
            <template x-for="h in 24" :key="h">
                <div class="tl-col-border flex-shrink-0 text-center text-[10px] font-black text-aviation-400 font-mono py-2.5"
                     :style="'width:'+colW+'px'">
                    <span x-text="pad(h-1)+':00'"></span>
                    <div class="tl-sub-tick" style="left: 25%"></div>
                    <div class="tl-sub-tick" style="left: 50%"></div>
                    <div class="tl-sub-tick" style="left: 75%"></div>
                </div>
            </template>
            <div class="tl-col-border flex-shrink-0 text-center text-[10px] font-black text-amber-400 font-mono py-2.5"
                 :style="'width:'+colW+'px'">
                TOT
            </div>
        </div>

        {{-- Operating Hours Frame spans Departure & Arrival Bands --}}
        <div class="relative" :style="'height:'+totalSectionHeight+'px'">

            {{-- Ops Frame decoration --}}
            <div class="ops-frame" :style="'left:'+opsLeft+'px; width:'+opsWidth+'px'">
                <div class="ops-handle left" id="oph-left"></div>
                <div class="ops-handle right" id="oph-right"></div>
            </div>

            {{-- ── DEPARTURE BAND ─────────────────────────────────────────── --}}
            <div class="relative tl-row-border" :style="'height:'+depBandH+'px'">
                {{-- Section Tag --}}
                <div class="section-label"><span class="text-aviation-400 font-black">Departure</span></div>

                {{-- Background Hour Grid --}}
                <div class="flex" style="margin-left:80px; height:100%">
                    <template x-for="h in 24" :key="h">
                        <div class="tl-col-border flex-shrink-0 h-full"
                             :class="offHour(h-1) ? 'off-hr' : ''"
                             :style="'width:'+colW+'px'">
                            <div class="tl-sub-tick" style="left: 25%"></div>
                            <div class="tl-sub-tick" style="left: 50%"></div>
                            <div class="tl-sub-tick" style="left: 75%"></div>
                        </div>
                    </template>
                    {{-- Grid cell buffer for TOT column --}}
                    <div class="tl-col-border flex-shrink-0 h-full" :style="'width:'+colW+'px'"></div>
                </div>

                {{-- Departure Flight Cards --}}
                <div id="dep-blocks" class="absolute inset-0 pointer-events-none" style="margin-left:0; overflow:visible">
                    <template x-for="p in departures" :key="p.id">
                        <div class="slot-block pointer-events-auto"
                             :id="'blk-'+p.id"
                             :data-id="p.id"
                             :data-section="'departure'"
                             :style="blockStyle(p)"
                             @mouseenter="showTooltip($event, p)"
                             @mouseleave="hideTooltip()">

                            <template x-if="draggingBlockId === p.id">
                                <span class="drag-status-badge" :class="dragConflict ? 'status-conflict' : 'status-avail'"
                                      x-text="dragConflict ? '⚠ Preview Collision' : '✓ Preview Position'"></span>
                            </template>

                            {{-- Card Structure: Top-Left Flight Number | Top-Right OFF | Bottom-Left Aircraft | Bottom-Right Destination IATA --}}
                            <div class="slot-block-inner relative flex flex-col justify-between" :style="'background:'+p.color_hex">
                                <template x-if="debugMode">
                                    <span class="debug-pill" x-text="'ID:'+p.id+' L:'+p.row"></span>
                                </template>

                                {{-- Top-Left: Flight Number & Top-Right: OFF Badge --}}
                                <div class="flex items-center justify-between leading-none">
                                    <div class="slot-flight-no text-white font-black text-[13px] font-mono tracking-tight leading-none" x-text="p.flight.flight_number"></div>
                                    <template x-if="isFlightOff(p)">
                                        <span class="px-1 py-0.5 rounded text-[8.5px] font-black font-mono tracking-wider bg-red-600 text-white shadow-xs leading-none">OFF</span>
                                    </template>
                                </div>

                                {{-- Bottom Row: Left Aircraft Type | Right Destination IATA --}}
                                <div class="flex items-end justify-between leading-none mt-auto">
                                    <div class="slot-ac-type text-slate-100 font-bold text-[10.5px] font-sans truncate flex-1 mr-1 leading-none" x-text="p.flight.aircraft_type || 'N/A'"></div>
                                    <div class="slot-iata text-white font-black text-[12px] font-mono tracking-wider uppercase shrink-0 leading-none" x-text="p.flight.destination_iata || 'DEP'"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ── ARRIVAL BAND ───────────────────────────────────────────── --}}
            <div class="relative" :style="'height:'+arrBandH+'px'">
                <div class="section-label"><span class="text-arrival-400 font-black">Arrival</span></div>

                {{-- Background Hour Grid --}}
                <div class="flex" style="margin-left:80px; height:100%">
                    <template x-for="h in 24" :key="h">
                        <div class="tl-col-border flex-shrink-0 h-full"
                             :class="offHour(h-1) ? 'off-hr' : ''"
                             :style="'width:'+colW+'px'">
                            <div class="tl-sub-tick" style="left: 25%"></div>
                            <div class="tl-sub-tick" style="left: 50%"></div>
                            <div class="tl-sub-tick" style="left: 75%"></div>
                        </div>
                    </template>
                    <div class="tl-col-border flex-shrink-0 h-full" :style="'width:'+colW+'px'"></div>
                </div>

                {{-- Arrival Flight Cards --}}
                <div id="arr-blocks" class="absolute inset-0 pointer-events-none" style="margin-left:0; overflow:visible">
                    <template x-for="p in arrivals" :key="p.id">
                        <div class="slot-block pointer-events-auto"
                             :id="'blk-'+p.id"
                             :data-id="p.id"
                             :data-section="'arrival'"
                             :style="blockStyle(p)"
                             @mouseenter="showTooltip($event, p)"
                             @mouseleave="hideTooltip()">

                            <template x-if="draggingBlockId === p.id">
                                <span class="drag-status-badge" :class="dragConflict ? 'status-conflict' : 'status-avail'"
                                      x-text="dragConflict ? '⚠ Preview Collision' : '✓ Preview Position'"></span>
                            </template>

                            {{-- Card Structure: Top-Left Flight Number | Top-Right OFF | Bottom-Left Aircraft | Bottom-Right Origin IATA --}}
                            <div class="slot-block-inner relative flex flex-col justify-between" :style="'background:'+p.color_hex">
                                <template x-if="debugMode">
                                    <span class="debug-pill" x-text="'ID:'+p.id+' L:'+p.row"></span>
                                </template>

                                {{-- Top-Left: Flight Number & Top-Right: OFF Badge --}}
                                <div class="flex items-center justify-between leading-none">
                                    <div class="slot-flight-no text-white font-black text-[13px] font-mono tracking-tight leading-none" x-text="p.flight.flight_number"></div>
                                    <template x-if="isFlightOff(p)">
                                        <span class="px-1 py-0.5 rounded text-[8.5px] font-black font-mono tracking-wider bg-red-600 text-white shadow-xs leading-none">OFF</span>
                                    </template>
                                </div>

                                {{-- Bottom Row: Left Aircraft Type | Right Origin IATA --}}
                                <div class="flex items-end justify-between leading-none mt-auto">
                                    <div class="slot-ac-type text-slate-100 font-bold text-[10.5px] font-sans truncate flex-1 mr-1 leading-none" x-text="p.flight.aircraft_type || 'N/A'"></div>
                                    <div class="slot-iata text-white font-black text-[12px] font-mono tracking-wider uppercase shrink-0 leading-none" x-text="p.flight.origin_iata || 'ARR'"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>

        {{-- ── BOTTOM SYNCHRONIZED SUMMARY TABLE ───────────────────────────── --}}
        <div class="border-t border-slate-800 bg-navy-950">
        <table class="text-[11px] border-collapse" :style="'width:'+canvasWidth+'px'">
            <thead>
                <tr class="bg-navy-950 text-slate-400 font-mono text-[9.5px] border-b border-slate-800">
                    <th class="py-2.5 px-3 text-left font-bold sticky left-0 bg-navy-950 z-10" style="width:80px">TYPE</th>
                    <template x-for="h in 24" :key="h">
                        <th class="py-2.5 text-center font-bold"
                            :style="'width:'+colW+'px'"
                            x-text="pad(h-1)+':00'">
                        </th>
                    </template>
                    <th class="py-2.5 px-3 text-center font-bold text-amber-400" :style="'width:'+colW+'px'">TOT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/40">
                <template x-for="row in summaryRows" :key="row.key">
                    <tr>
                        <td class="py-2 px-3 font-bold sticky left-0 bg-navy-900 z-10" :style="'color:'+row.color" x-text="row.label"></td>
                        <template x-for="h in 24" :key="h">
                            <td class="py-2 text-center font-mono"
                                :class="summaryVal(h-1,row.key) > 0 ? 'text-white font-bold' : 'text-slate-700'"
                                x-text="summaryVal(h-1,row.key) || '·'">
                            </td>
                        </template>
                        <td class="py-2 px-3 text-center font-black" :style="'color:'+row.color" x-text="rowTotal(row.key)"></td>
                    </tr>
                </template>
                <tr class="bg-navy-950 text-white font-black">
                    <td class="py-2.5 px-3 text-[10px] uppercase tracking-wide sticky left-0 bg-navy-950 z-10">Total</td>
                    <template x-for="h in 24" :key="h">
                        <td class="py-2.5 text-center font-mono text-amber-400"
                            x-text="hourTotal(h-1)||'·'">
                        </td>
                    </template>
                    <td class="py-2.5 px-3 text-center text-amber-400" x-text="grandTotal()"></td>
                </tr>
            </tbody>
        </table>
        </div>

    </div>
    </div>

    {{-- ── HOVER TOOLTIP (FULL MASTER DATABASE METADATA) ──────────────────── --}}
    <div x-show="tooltip && tooltip.show" x-transition.opacity
         class="tl-tooltip"
         :style="'left:'+(tooltip ? tooltip.x : 0)+'px; top:'+(tooltip ? tooltip.y : 0)+'px;'"
         style="display: none;">
        <template x-if="tooltip && tooltip.show && tooltip.item && tooltip.item.flight">
            <div class="space-y-2">
                <div class="border-b border-slate-800 pb-1.5">
                    <div class="font-bold text-amber-400 text-xs font-mono"
                         x-text="tooltip.item.flight.airline_name + ' (' + tooltip.item.flight.airline_code + ')'">
                    </div>
                    <div class="text-white text-sm font-extrabold font-mono mt-0.5"
                         x-text="tooltip.item.flight.flight_number + ' (' + (tooltip.item.section === 'arrival' ? 'Arrival' : 'Departure') + ')'">
                    </div>
                </div>
                <div class="text-[10px] text-slate-300 font-mono space-y-1">
                    <div><span class="text-slate-400">Aircraft:</span> <span class="font-bold text-white" x-text="tooltip.item.flight.aircraft_type || 'N/A'"></span></div>
                    <div><span class="text-slate-400" x-text="tooltip.item.section === 'arrival' ? 'STA:' : 'STD:'"></span> <span class="font-bold text-aviation-300" x-text="tooltip.item.flight.scheduled_time.slice(0,5)"></span></div>
                    <div>
                        <span class="text-slate-400">Route:</span>
                        <span class="font-bold text-amber-300" x-text="tooltip.item.flight.route_label"></span>
                    </div>
                    <div><span class="text-slate-400">Airport:</span> <span class="font-bold text-slate-200" x-text="tooltip.item.flight.remote_airport_name"></span></div>
                    <div><span class="text-slate-400">Region:</span> <span class="font-bold text-aviation-300" x-text="tooltip.item.flight.remote_region"></span></div>
                    <div><span class="text-slate-400">Management:</span> <span class="font-bold text-slate-200" x-text="tooltip.item.flight.remote_management"></span></div>
                    <div><span class="text-slate-400">Category:</span> <span class="font-bold text-slate-200" x-text="tooltip.item.flight.category"></span></div>
                    <div><span class="text-slate-400">DOS:</span> <span class="font-bold text-emerald-400" x-text="tooltip.item.flight.operating_days || '1234567'"></span></div>
                </div>
            </div>
        </template>
    </div>

</div>

</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function slotTimeline() {
    return {
        colW:          120,        // px width per hour column (min 100px)
        rowH:          64,         // px height per row
        gridInterval:  5,          // default snap interval: 5 minutes
        debugMode:     false,

        uploadId: {{ $upload->id }},

        draggingBlockId: null,
        dragConflict:    false,

        tooltip: {
            show: false,
            x: 0,
            y: 0,
            item: null
        },

        showTooltip(ev, item) {
            const rect = ev.currentTarget.getBoundingClientRect();
            this.tooltip.x = rect.right + 10;
            this.tooltip.y = rect.top;
            if (this.tooltip.x + 300 > window.innerWidth) {
                this.tooltip.x = Math.max(10, rect.left - 300);
            }
            this.tooltip.item = item;
            this.tooltip.show = true;
        },

        hideTooltip() {
            this.tooltip.show = false;
        },

        get blockW() {
            return Math.max(92, this.colW - 10);
        },

        showOpsModal: false,
        tempOpsStart: {{ $settings->ops_start ?? 6 }},
        tempOpsEnd:   {{ $settings->ops_end   ?? 20 }},

        opsStart: {{ $settings->ops_start ?? 6 }},
        opsEnd:   {{ $settings->ops_end   ?? 20 }},
        _opsUrl: '{{ route('timeline.ops-hours.save', $upload->id) }}',
        _csrf:   document.head ? (document.head.querySelector('meta[name="csrf-token"]')?.content ?? '') : '',

        departures: @json($departureBlocks),
        arrivals:   @json($arrivalBlocks),

        summaryRows: [
            { key:'dep_dom', label:'Dep Dom', color:'#3b82f6' },
            { key:'dep_int', label:'Dep Int', color:'#60a5fa' },
            { key:'arr_dom', label:'Arr Dom', color:'#d97706' },
            { key:'arr_int', label:'Arr Int', color:'#f59e0b' },
        ],

        minuteOfDay(hour, offsetMinutes) {
            return (parseInt(hour) * 60) + (parseInt(offsetMinutes) || 0);
        },

        getTimelineX(minuteOfDay) {
            return Math.round((minuteOfDay / 60) * this.colW);
        },

        calculateTimelineLayout() {
            this.allocateLanesForSection(this.departures);
            this.allocateLanesForSection(this.arrivals);
        },

        allocateLanesForSection(blocks) {
            if (!blocks || !blocks.length) return;

            blocks.forEach(b => {
                b.startMinutes = this.minuteOfDay(b.hour, b.offset_minutes);
            });

            blocks.sort((a, b) => {
                if (a.startMinutes !== b.startMinutes) return a.startMinutes - b.startMinutes;
                return String(a.flight.flight_number).localeCompare(String(b.flight.flight_number));
            });

            const cardWidthPx = this.blockW;
            const minGapPx = 6;
            const laneEndX = [];

            blocks.forEach(b => {
                const startX = 80 + this.getTimelineX(b.startMinutes);
                const endX   = startX + cardWidthPx;

                let assignedLane = -1;
                for (let l = 0; l < laneEndX.length; l++) {
                    if (startX >= laneEndX[l] + minGapPx) {
                        assignedLane = l;
                        laneEndX[l] = endX;
                        break;
                    }
                }
                if (assignedLane === -1) {
                    assignedLane = laneEndX.length;
                    laneEndX.push(endX);
                }

                b.row   = assignedLane;
                b.x     = startX;
                b.width = cardWidthPx;
                b.lane  = assignedLane;
            });
        },

        fitTimeline() {
            const viewportW = window.innerWidth - 80;
            const calculatedColW = Math.max(100, Math.min(220, Math.floor(viewportW / 25)));
            this.colW = calculatedColW;
            this.calculateTimelineLayout();
            this.toast(`Scale fitted to ${calculatedColW}px per hour`, true);
        },

        get canvasWidth() { return 80 + (25 * this.colW); },

        get depMaxRows() {
            if (!this.departures.length) return 2;
            return Math.max(3, Math.max(...this.departures.map(d => d.row)) + 1);
        },
        get arrMaxRows() {
            if (!this.arrivals.length) return 2;
            return Math.max(3, Math.max(...this.arrivals.map(a => a.row)) + 1);
        },
        get depBandH()  { return this.depMaxRows * this.rowH + 8; },
        get arrBandH()  { return this.arrMaxRows * this.rowH + 8; },
        get totalSectionHeight() { return this.depBandH + this.arrBandH; },

        get opsLeft()  { return 80 + (this.opsStart * this.colW); },
        get opsWidth() { return (this.opsEnd - this.opsStart) * this.colW; },

        get activeHours() {
            return Math.max(0, this.opsEnd - this.opsStart);
        },

        pad(h) { return String(h).padStart(2,'0'); },
        offHour(hour) { return hour < this.opsStart || hour >= this.opsEnd; },

        isFlightOff(p) {
            const min = this.minuteOfDay(p.hour, p.offset_minutes);
            const startMin = this.opsStart * 60;
            const endMin   = this.opsEnd * 60;
            return min < startMin || min > endMin;
        },

        openOpsEditor() {
            this.tempOpsStart = this.opsStart;
            this.tempOpsEnd   = this.opsEnd;
            this.showOpsModal = true;
        },

        applyOpsHours() {
            if (this.tempOpsEnd <= this.tempOpsStart) {
                this.toast('End time must be later than start time.', false);
                return;
            }
            fetch(this._opsUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this._csrf || document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ ops_start: this.tempOpsStart, ops_end: this.tempOpsEnd })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    this.opsStart = this.tempOpsStart;
                    this.opsEnd   = this.tempOpsEnd;
                    this.showOpsModal = false;
                    this.calculateTimelineLayout();
                    this.toast('Operating hours updated to ' + this.pad(this.opsStart) + ':00 → ' + this.pad(this.opsEnd) + ':00 ✓', true);
                } else {
                    this.toast(d.message || 'Failed to save operating hours', false);
                }
            })
            .catch(() => this.toast('Failed to save operating hours', false));
        },

        pixelToMinuteOfDay(px) {
            const relativePx = Math.max(0, px - 80);
            const totalMinutes = Math.round((relativePx / this.colW) * 60);
            return Math.max(0, Math.min(1439, totalMinutes));
        },

        blockStyle(pos) {
            const min  = this.minuteOfDay(pos.hour, pos.offset_minutes);
            const left = 80 + this.getTimelineX(min);
            const top  = pos.row * this.rowH + 4;
            return `left:${left}px; top:${top}px; width:${this.blockW}px; height:${this.rowH - 8}px;`;
        },

        summaryVal(hour, key) {
            const typeMap = { dep_dom:'departure_domestic', dep_int:'departure_international',
                              arr_dom:'arrival_domestic',   arr_int:'arrival_international' };
            return [...this.departures, ...this.arrivals]
                   .filter(p => p.hour === hour && p.flight.flight_type === typeMap[key]).length;
        },
        rowTotal(key)   { let t=0; for(let h=0;h<24;h++) t+=this.summaryVal(h,key); return t; },
        hourTotal(hour) { return ['dep_dom','dep_int','arr_dom','arr_int'].reduce((s,k)=>s+this.summaryVal(hour,k),0); },
        grandTotal()    { return this.departures.length + this.arrivals.length; },

        saveOpsHours() {
            if (this.opsStart >= this.opsEnd) {
                this.toast('Ops start must be before ops end', false);
                return;
            }
            fetch(this._opsUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this._csrf || document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ ops_start: this.opsStart, ops_end: this.opsEnd })
            })
            .then(r => r.json())
            .then(d => { if (d.success) this.toast('Operating hours updated ✓', true); })
            .catch(() => this.toast('Failed to save operating hours', false));
        },

        toast(msg, ok) {
            const el = document.getElementById('toast');
            if (!el) return;
            el.textContent = msg;
            el.className = 'show ' + (ok ? 'ok' : 'err');
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { el.className = ''; }, 2500);
        },

        init() {
            this.calculateTimelineLayout();
            this.$nextTick(() => {
                this.initDrag();
                this.initOpsFrame();
            });
        },

        initDrag() {
            const vm = this;

            interact('.slot-block').draggable({
                inertia: false,
                autoScroll: { container: document.querySelector('.tl-wrap'), speed: 400 },
                listeners: {
                    start(ev) {
                        const id = parseInt(ev.target.dataset.id);
                        vm.draggingBlockId = id;
                        vm.dragConflict = false;
                        ev.target.style.transition = 'none';
                        ev.target.classList.add('dragging');
                        ev.target._dx = 0;
                        ev.target._dy = 0;
                    },

                    move(ev) {
                        const id = parseInt(ev.target.dataset.id);
                        const section = ev.target.dataset.section;
                        const list = section === 'departure' ? vm.departures : vm.arrivals;

                        ev.target._dx = (ev.target._dx || 0) + ev.dx;
                        ev.target._dy = (ev.target._dy || 0) + ev.dy;
                        ev.target.style.transform = `translate(${ev.target._dx}px,${ev.target._dy}px)`;

                        const containerEl = document.getElementById(section === 'departure' ? 'dep-blocks' : 'arr-blocks');
                        if (!containerEl) return;
                        const containerRect = containerEl.getBoundingClientRect();
                        const blockRect = ev.target.getBoundingClientRect();

                        const relX = blockRect.left - containerRect.left;
                        const targetMinuteOfCanvas = vm.pixelToMinuteOfDay(relX);
                        const interval = vm.gridInterval || 5;
                        const snappedMinute = Math.round(targetMinuteOfCanvas / interval) * interval;

                        const targetRow = Math.max(0, Math.round((blockRect.top - containerRect.top) / vm.rowH));

                        const collision = list.some(b => {
                            if (b.id === id) return false;
                            const bMin = vm.minuteOfDay(b.hour, b.offset_minutes);
                            return b.row === targetRow && Math.abs(bMin - snappedMinute) < 25;
                        });

                        vm.dragConflict = collision;
                    },

                    end(ev) {
                        vm.draggingBlockId = null;
                        vm.dragConflict = false;

                        ev.target.classList.remove('dragging');
                        ev.target.style.transition = 'transform 0.25s ease-out';
                        ev.target.style.transform = 'translate(0px, 0px)';

                        setTimeout(() => {
                            ev.target.style.transition = '';
                            ev.target._dx = 0;
                            ev.target._dy = 0;
                        }, 250);
                    }
                }
            });
        },

        initOpsFrame() {
            const vm = this;
            let dragAccumX = 0;

            interact('#ops-hours-frame').draggable({
                listeners: {
                    start() { dragAccumX = 0; },
                    move(ev) {
                        dragAccumX += ev.dx;
                        const shift = Math.round(dragAccumX / vm.colW);
                        if (shift !== 0) {
                            const ns = vm.opsStart + shift;
                            const ne = vm.opsEnd   + shift;
                            if (ns >= 0 && ne <= 24 && ns < ne) {
                                vm.opsStart = ns;
                                vm.opsEnd   = ne;
                                dragAccumX  = 0;
                            }
                        }
                    }
                }
            });

            let leftAccum = 0, rightAccum = 0;

            interact('#oph-left').draggable({
                listeners: {
                    start() { leftAccum = 0; },
                    move(ev) {
                        leftAccum += ev.dx;
                        const shift = Math.round(leftAccum / vm.colW);
                        if (shift !== 0) {
                            const ns = vm.opsStart + shift;
                            if (ns >= 0 && ns < vm.opsEnd - 1) {
                                vm.opsStart = ns;
                                leftAccum   = 0;
                            }
                        }
                    }
                }
            });

            interact('#oph-right').draggable({
                listeners: {
                    start() { rightAccum = 0; },
                    move(ev) {
                        rightAccum += ev.dx;
                        const shift = Math.round(rightAccum / vm.colW);
                        if (shift !== 0) {
                            const ne = vm.opsEnd + shift;
                            if (ne <= 24 && ne > vm.opsStart + 1) {
                                vm.opsEnd  = ne;
                                rightAccum = 0;
                            }
                        }
                    }
                }
            });
        },

        drawExportFlightCard(ctx, flight, cardX, cardY, width, height, bgColor, isOff = false) {
            const PADDING = 12;

            ctx.save();

            // 1. Draw rounded card background
            const radius = 8;
            ctx.beginPath();
            ctx.moveTo(cardX + radius, cardY);
            ctx.lineTo(cardX + width - radius, cardY);
            ctx.quadraticCurveTo(cardX + width, cardY, cardX + width, cardY + radius);
            ctx.lineTo(cardX + width, cardY + height - radius);
            ctx.quadraticCurveTo(cardX + width, cardY + height, cardX + width - radius, cardY + height);
            ctx.lineTo(cardX + radius, cardY + height);
            ctx.quadraticCurveTo(cardX, cardY + height, cardX, cardY + height - radius);
            ctx.lineTo(cardX, cardY + radius);
            ctx.quadraticCurveTo(cardX, cardY, cardX + radius, cardY);
            ctx.closePath();

            ctx.fillStyle = bgColor;
            ctx.fill();

            // Card border
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.85)';
            ctx.lineWidth = 2.5;
            ctx.stroke();

            // 2. Text layout:
            // TOP-LEFT: Flight Number
            const fn = String(flight.flight_number || '');
            ctx.textAlign    = 'left';
            ctx.textBaseline = 'top';
            ctx.fillStyle    = '#ffffff';
            ctx.font         = 'bold 20px Consolas, Monaco, "Courier New", monospace';
            ctx.fillText(fn, cardX + PADDING, cardY + PADDING, width - (PADDING * 2) - (isOff ? 45 : 0));

            // TOP-RIGHT: OFF Badge (when flight is outside operational window)
            if (isOff) {
                const badgeW = 40;
                const badgeH = 20;
                const badgeX = cardX + width - PADDING - badgeW;
                const badgeY = cardY + PADDING - 2;

                ctx.fillStyle = '#dc2626';
                ctx.beginPath();
                if (ctx.roundRect) {
                    ctx.roundRect(badgeX, badgeY, badgeW, badgeH, 4);
                } else {
                    ctx.rect(badgeX, badgeY, badgeW, badgeH);
                }
                ctx.fill();

                ctx.fillStyle    = '#ffffff';
                ctx.font         = 'bold 12px Consolas, Monaco, "Courier New", monospace';
                ctx.textAlign    = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('OFF', badgeX + (badgeW / 2), badgeY + (badgeH / 2));
            }

            // BOTTOM-LEFT: Aircraft Type
            const ac = String(flight.aircraft_type || 'N/A');
            ctx.textAlign    = 'left';
            ctx.textBaseline = 'bottom';
            ctx.fillStyle    = 'rgba(255, 255, 255, 0.95)';
            ctx.font         = 'bold 16px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.fillText(ac, cardX + PADDING, cardY + height - PADDING, (width * 0.55) - PADDING);

            // BOTTOM-RIGHT: Origin Airport Code (IATA)
            const origIata = String(flight.origin_iata || flight.origin || 'BDO').toUpperCase();
            ctx.textAlign    = 'right';
            ctx.textBaseline = 'bottom';
            ctx.fillStyle    = '#ffffff';
            ctx.font         = 'bold 18px Consolas, Monaco, "Courier New", monospace';
            ctx.fillText(origIata, cardX + width - PADDING, cardY + height - PADDING);

            ctx.restore();
        },

        async renderNativeExportCanvas() {
            if (document.fonts && document.fonts.ready) {
                await document.fonts.ready;
            }

            const EXP_LBL  = 250; // Left section tag width (px)
            const EXP_COL  = 250; // Hour column width (px) -> 24 * 250 = 6000px
            const EXP_TOT  = 250; // TOT column width (px)
            const totalW   = EXP_LBL + (25 * EXP_COL); // 250 + 6000 + 250 = 6500px

            const CARD_W   = 200; // Flight card width
            const CARD_H   = 110; // Flight card height
            const LANE_H   = 135; // Lane height (CARD_H + 25px gap)

            const opsStart = this.opsStart;
            const opsEnd   = this.opsEnd;

            // 1. Allocate vertical lanes for departures and arrivals
            const allocate = (blocks) => {
                if (!blocks || !blocks.length) return [];
                const out = blocks.map(b => Object.assign({}, b));
                out.sort((a, b) => {
                    const ma = (parseInt(a.hour) * 60) + (parseInt(a.offset_minutes) || 0);
                    const mb = (parseInt(b.hour) * 60) + (parseInt(b.offset_minutes) || 0);
                    if (ma !== mb) return ma - mb;
                    return String(a.flight.flight_number).localeCompare(String(b.flight.flight_number));
                });
                const laneEndX = [];
                out.forEach(b => {
                    const min = (parseInt(b.hour) * 60) + (parseInt(b.offset_minutes) || 0);
                    const startX = EXP_LBL + Math.round((min / 60) * EXP_COL);
                    const endX = startX + CARD_W;
                    let lane = -1;
                    for (let l = 0; l < laneEndX.length; l++) {
                        if (startX >= laneEndX[l] + 12) {
                            lane = l;
                            laneEndX[l] = endX;
                            break;
                        }
                    }
                    if (lane === -1) {
                        lane = laneEndX.length;
                        laneEndX.push(endX);
                    }
                    b._row = lane;
                    b._x = startX;
                });
                return out;
            };

            const depBlocks = allocate(this.departures);
            const arrBlocks = allocate(this.arrivals);

            const depLanes = depBlocks.length ? Math.max(3, Math.max(...depBlocks.map(d => d._row)) + 1) : 2;
            const arrLanes = arrBlocks.length ? Math.max(3, Math.max(...arrBlocks.map(a => a._row)) + 1) : 2;

            const depBandH = (depLanes * LANE_H) + 24;
            const arrBandH = (arrLanes * LANE_H) + 24;

            // Page Geometry
            const PAGE_PAD_X    = 50;
            const PAGE_PAD_Y    = 50;
            const HEADER_H      = 170; // Title + Subtitle + Legend
            const SCALE_HDR_H   = 56;
            const SUMMARY_ROW_H = 50;
            const SUMMARY_HDR_H = 46;
            const SUMMARY_TBL_H = SUMMARY_HDR_H + (5 * SUMMARY_ROW_H); // 5 rows (Dep Dom, Dep Int, Arr Dom, Arr Int, Grand Total)
            const FOOTER_H      = 60;

            const logicalW = totalW + (PAGE_PAD_X * 2);
            const logicalH = PAGE_PAD_Y + HEADER_H + SCALE_HDR_H + depBandH + arrBandH + 30 + SUMMARY_TBL_H + FOOTER_H + PAGE_PAD_Y;

            // 2. High Resolution Canvas
            const EXPORT_SCALE = 1; // 6600px wide native canvas
            const canvas = document.createElement('canvas');
            canvas.width  = logicalW * EXPORT_SCALE;
            canvas.height = logicalH * EXPORT_SCALE;
            const ctx = canvas.getContext('2d');
            ctx.scale(EXPORT_SCALE, EXPORT_SCALE);

            // Fill Page Background
            ctx.fillStyle = '#020617';
            ctx.fillRect(0, 0, logicalW, logicalH);

            // 3. Draw Header
            const contentX = PAGE_PAD_X;
            let currY = PAGE_PAD_Y;

            // Title
            ctx.textAlign    = 'center';
            ctx.textBaseline = 'top';
            ctx.fillStyle    = '#ffffff';
            ctx.font         = '900 46px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.fillText('AIRPORT OPERATIONAL SLOT SCHEDULE', logicalW / 2, currY);
            currY += 56;

            // Subtitle
            const nowStr = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
                + ' ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const totalFlights = this.departures.length + this.arrivals.length;
            ctx.fillStyle = '#94a3b8';
            ctx.font      = '600 22px Consolas, Monaco, "Courier New", monospace';
            ctx.fillText(`Bandara Husein Sastranegara · BDO  |  Generated ${nowStr} WIB  |  ${totalFlights} Total Flights`, logicalW / 2, currY);
            currY += 44;

            // Legend
            const legendItems = [
                { color: '#1e40af', label: 'Departure Domestic' },
                { color: '#3b82f6', label: 'Departure International' },
                { color: '#b45309', label: 'Arrival Domestic' },
                { color: '#f59e0b', label: 'Arrival International' },
                { color: '#090e1a', stroke: '#334155', label: 'Off Hour Shading' },
            ];
            let legTotalW = 0;
            ctx.font = '700 20px system-ui, sans-serif';
            legendItems.forEach(item => {
                legTotalW += 28 + 12 + ctx.measureText(item.label).width + 36;
            });
            legTotalW -= 36;
            let legX = (logicalW - legTotalW) / 2;
            legendItems.forEach(item => {
                ctx.fillStyle = item.color;
                ctx.fillRect(legX, currY + 2, 28, 18);
                ctx.strokeStyle = item.stroke || 'rgba(255,255,255,0.6)';
                ctx.lineWidth = 1.5;
                ctx.strokeRect(legX, currY + 2, 28, 18);
                ctx.fillStyle = '#cbd5e1';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'top';
                ctx.fillText(item.label, legX + 40, currY);
                legX += 28 + 12 + ctx.measureText(item.label).width + 36;
            });
            currY += 48;

            // 4. Draw Timeline Container Frame
            const tlX = contentX;
            const tlY = currY;
            const tlH = SCALE_HDR_H + depBandH + arrBandH;

            ctx.fillStyle = '#090e1a';
            ctx.fillRect(tlX, tlY, totalW, tlH);
            ctx.strokeStyle = '#1e293b';
            ctx.lineWidth = 3;
            ctx.strokeRect(tlX, tlY, totalW, tlH);

            // Hour Header (00:00 -> 23:00 + TOT)
            ctx.fillStyle = '#020617';
            ctx.fillRect(tlX, tlY, totalW, SCALE_HDR_H);
            ctx.strokeStyle = '#1e293b';
            ctx.beginPath();
            ctx.moveTo(tlX, tlY + SCALE_HDR_H);
            ctx.lineTo(tlX + totalW, tlY + SCALE_HDR_H);
            ctx.stroke();

            for (let h = 0; h < 24; h++) {
                const colX = tlX + EXP_LBL + (h * EXP_COL);
                ctx.strokeStyle = '#1e293b';
                ctx.beginPath();
                ctx.moveTo(colX, tlY);
                ctx.lineTo(colX, tlY + SCALE_HDR_H);
                ctx.stroke();

                ctx.fillStyle = '#60a5fa';
                ctx.font = '900 22px Consolas, Monaco, "Courier New", monospace';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(`${String(h).padStart(2, '0')}:00`, colX + (EXP_COL / 2), tlY + (SCALE_HDR_H / 2));
            }
            // TOT column in header
            const totHdrX = tlX + EXP_LBL + (24 * EXP_COL);
            ctx.strokeStyle = '#1e293b';
            ctx.beginPath();
            ctx.moveTo(totHdrX, tlY);
            ctx.lineTo(totHdrX, tlY + SCALE_HDR_H);
            ctx.stroke();
            ctx.fillStyle = '#f59e0b';
            ctx.font = '900 22px Consolas, Monaco, "Courier New", monospace';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('TOT', totHdrX + (EXP_TOT / 2), tlY + (SCALE_HDR_H / 2));

            // Helper: Draw Section Band (DEP / ARR)
            const drawBand = (bandStartY, bandH, labelText, labelColor, blocks) => {
                // Divider line under band
                ctx.strokeStyle = '#1e293b';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(tlX, bandStartY + bandH);
                ctx.lineTo(tlX + totalW, bandStartY + bandH);
                ctx.stroke();

                // Left Tag
                ctx.fillStyle = '#020617';
                ctx.fillRect(tlX, bandStartY, EXP_LBL, bandH);
                ctx.strokeStyle = '#1e293b';
                ctx.beginPath();
                ctx.moveTo(tlX + EXP_LBL, bandStartY);
                ctx.lineTo(tlX + EXP_LBL, bandStartY + bandH);
                ctx.stroke();

                ctx.fillStyle = labelColor;
                ctx.font = '900 22px system-ui, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(labelText, tlX + (EXP_LBL / 2), bandStartY + (bandH / 2));

                // Background Vertical Grid Lines for hours 0..24
                for (let h = 0; h <= 25; h++) {
                    const gx = tlX + EXP_LBL + (h * EXP_COL);
                    ctx.strokeStyle = '#1e293b';
                    ctx.lineWidth = 2;
                    ctx.beginPath();
                    ctx.moveTo(gx, bandStartY);
                    ctx.lineTo(gx, bandStartY + bandH);
                    ctx.stroke();
                }

                // Off-hour Shading
                if (opsStart > 0) {
                    ctx.fillStyle = 'rgba(2,6,23,0.85)';
                    ctx.fillRect(tlX + EXP_LBL, bandStartY, opsStart * EXP_COL, bandH);
                }
                if (opsEnd < 24) {
                    ctx.fillStyle = 'rgba(2,6,23,0.85)';
                    ctx.fillRect(tlX + EXP_LBL + (opsEnd * EXP_COL), bandStartY, (24 - opsEnd) * EXP_COL, bandH);
                }

                // Draw Flight Cards with dedicated renderer & OFF detection
                blocks.forEach(b => {
                    const cardX = tlX + b._x;
                    const cardY = bandStartY + 12 + (b._row * LANE_H);
                    const min = (parseInt(b.hour) * 60) + (parseInt(b.offset_minutes) || 0);
                    const isOff = (min < opsStart * 60 || min > opsEnd * 60);
                    this.drawExportFlightCard(ctx, b.flight, cardX, cardY, CARD_W, CARD_H, b.color_hex || '#3b82f6', isOff);
                });
            };

            // Draw Departure Band
            let bandY = tlY + SCALE_HDR_H;
            drawBand(bandY, depBandH, 'DEPARTURE', '#60a5fa', depBlocks);

            // Draw Arrival Band
            bandY += depBandH;
            drawBand(bandY, arrBandH, 'ARRIVAL', '#f59e0b', arrBlocks);

            // Draw Blue Dashed Boundary Lines for Operational Hours across both bands
            const startBoundaryX = tlX + EXP_LBL + (opsStart * EXP_COL);
            const endBoundaryX   = tlX + EXP_LBL + (opsEnd * EXP_COL);

            ctx.save();
            ctx.strokeStyle = '#38bdf8';
            ctx.lineWidth = 3.5;
            ctx.setLineDash([12, 8]);

            // Start boundary
            ctx.beginPath();
            ctx.moveTo(startBoundaryX, tlY + SCALE_HDR_H);
            ctx.lineTo(startBoundaryX, tlY + tlH);
            ctx.stroke();

            // End boundary
            ctx.beginPath();
            ctx.moveTo(endBoundaryX, tlY + SCALE_HDR_H);
            ctx.lineTo(endBoundaryX, tlY + tlH);
            ctx.stroke();

            ctx.restore();

            // 5. Draw Summary Table below Timeline
            const tblY = tlY + tlH + 30;

            // Summary Table Header
            ctx.fillStyle = '#020617';
            ctx.fillRect(tlX, tblY, totalW, SUMMARY_HDR_H);
            ctx.strokeStyle = '#1e293b';
            ctx.lineWidth = 2;
            ctx.strokeRect(tlX, tblY, totalW, SUMMARY_HDR_H);

            // TYPE column
            ctx.fillStyle = '#94a3b8';
            ctx.font = 'bold 19px Consolas, Monaco, monospace';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText('TYPE', tlX + 16, tblY + (SUMMARY_HDR_H / 2));

            for (let h = 0; h < 24; h++) {
                const colX = tlX + EXP_LBL + (h * EXP_COL);
                ctx.strokeStyle = '#1e293b';
                ctx.strokeRect(colX, tblY, EXP_COL, SUMMARY_HDR_H);
                ctx.fillStyle = '#94a3b8';
                ctx.textAlign = 'center';
                ctx.fillText(`${String(h).padStart(2, '0')}:00`, colX + (EXP_COL / 2), tblY + (SUMMARY_HDR_H / 2));
            }
            // TOT
            const totTblX = tlX + EXP_LBL + (24 * EXP_COL);
            ctx.strokeStyle = '#1e293b';
            ctx.strokeRect(totTblX, tblY, EXP_TOT, SUMMARY_HDR_H);
            ctx.fillStyle = '#f59e0b';
            ctx.textAlign = 'center';
            ctx.fillText('TOT', totTblX + (EXP_TOT / 2), tblY + (SUMMARY_HDR_H / 2));

            // Summary Table Rows
            const typeRows = [
                { key: 'dep_dom', label: 'Dep Dom', color: '#60a5fa', typeStr: 'departure_domestic' },
                { key: 'dep_int', label: 'Dep Int', color: '#93c5fd', typeStr: 'departure_international' },
                { key: 'arr_dom', label: 'Arr Dom', color: '#fbbf24', typeStr: 'arrival_domestic' },
                { key: 'arr_int', label: 'Arr Int', color: '#fde047', typeStr: 'arrival_international' },
            ];
            const allFlights = [...this.departures, ...this.arrivals];

            let rowY = tblY + SUMMARY_HDR_H;
            typeRows.forEach(r => {
                ctx.fillStyle = '#020617';
                ctx.fillRect(tlX, rowY, totalW, SUMMARY_ROW_H);
                ctx.strokeStyle = '#1e293b';
                ctx.strokeRect(tlX, rowY, EXP_LBL, SUMMARY_ROW_H);

                ctx.fillStyle = r.color;
                ctx.font = 'bold 19px Consolas, Monaco, monospace';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(r.label, tlX + 16, rowY + (SUMMARY_ROW_H / 2));

                let rowTot = 0;
                for (let h = 0; h < 24; h++) {
                    const colX = tlX + EXP_LBL + (h * EXP_COL);
                    ctx.strokeStyle = '#1e293b';
                    ctx.strokeRect(colX, rowY, EXP_COL, SUMMARY_ROW_H);

                    const count = allFlights.filter(b => b.hour === h && b.flight.flight_type === r.typeStr).length;
                    rowTot += count;

                    ctx.textAlign = 'center';
                    if (count > 0) {
                        ctx.fillStyle = '#ffffff';
                        ctx.font = 'bold 19px Consolas, Monaco, monospace';
                        ctx.fillText(String(count), colX + (EXP_COL / 2), rowY + (SUMMARY_ROW_H / 2));
                    } else {
                        ctx.fillStyle = '#475569';
                        ctx.font = '19px Consolas, Monaco, monospace';
                        ctx.fillText('·', colX + (EXP_COL / 2), rowY + (SUMMARY_ROW_H / 2));
                    }
                }

                // Row Total
                ctx.strokeStyle = '#1e293b';
                ctx.strokeRect(totTblX, rowY, EXP_TOT, SUMMARY_ROW_H);
                ctx.fillStyle = r.color;
                ctx.font = 'bold 19px Consolas, Monaco, monospace';
                ctx.textAlign = 'center';
                ctx.fillText(String(rowTot), totTblX + (EXP_TOT / 2), rowY + (SUMMARY_ROW_H / 2));

                rowY += SUMMARY_ROW_H;
            });

            return canvas;
        },

        printPdf() {
            this.toast('Opening multi-page Operational Slot Schedule PDF report...', true);
            window.open('{{ route('timeline.pdf', $upload->id) }}', '_blank');
        },

        async downloadJpg() {
            const vm = this;
            vm.toast('Generating high-resolution 24-Hour Timeline JPG...', true);

            try {
                const canvas = await this.renderNativeExportCanvas();
                if (!canvas) {
                    vm.toast('Failed to render timeline canvas', false);
                    return;
                }

                const a = document.createElement('a');
                a.download = `SlotWaves_24-Hour_Timeline_${this.uploadId}.jpg`;
                a.href = canvas.toDataURL('image/jpeg', 0.98);
                a.click();
                vm.toast('High-resolution JPG exported successfully ✓', true);
            } catch (err) {
                console.error("JPG Export Error:", err);
                vm.toast('Failed to export JPG', false);
            }
        },
    };
}
</script>
@endpush
