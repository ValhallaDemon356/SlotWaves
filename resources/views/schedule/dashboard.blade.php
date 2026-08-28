@extends('layouts.app')

@section('title', 'SlotWaves — Flight Schedule & Operations: ' . ($airport ? $airport->name : 'BDO'))
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen transition-colors duration-150')

@push('head')
<script>
function dashboardState(initialDos, initialMovements, initialOpsStart, initialOpsEnd, initialNac, initialTimezone, initialOffset, initialTzAbbr) {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('slotwaves-theme', this.theme);
            if (this.theme === 'light') {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            } else {
                document.documentElement.classList.remove('light');
                document.documentElement.classList.add('dark');
            }
        },

        // Active view toggle
        activeChartMode: 'distribution', // 'distribution' | 'capacity'
        scheduleTimeFilter: 'ops', // 'ops' | 'all'
        activeCardTooltip: null, // hovered flight card id
        nacLimit: initialNac || 6,

        // Timezone Configuration (LOCAL vs UTC)
        timezoneMode: 'LOCAL', // 'LOCAL' | 'UTC'
        airportTimezone: initialTimezone || 'Asia/Jakarta',
        timezoneOffset: initialOffset || 420, // minutes (e.g. 420 for WIB, 480 for WITA, 540 for WIT)
        timezoneAbbr: initialTzAbbr || 'WIB',

        // Capacity Configuration & Modal State
        capacityModalOpen: false,
        modalNac: initialNac || 6,
        capacityHovered: false,
        saveSettingsUrl: '{{ route('schedule.operational-settings.save', $upload->id) }}',

        // Dynamic OPS Hours State
        opsStartTime: initialOpsStart || '06:00',
        opsEndTime: initialOpsEnd || '20:00',
        defaultOpsStart: '06:00',
        defaultOpsEnd: '20:00',
        opsSaveUrl: '{{ route('timeline.ops-hours.save', $upload->id) }}',
        csrfToken: '{{ csrf_token() }}',
        opsModalOpen: false,
        modalOpsStart: initialOpsStart || '06:00',
        modalOpsEnd: initialOpsEnd || '20:00',
        modalError: '',
        opsToastOpen: false,
        opsToastMessage: '',
        toastTimeout: null,

        // DOS filter state
        isDaily: false,
        selectedDays: [],
        showAllHours: {{ request()->has('show_all_hours') ? 'true' : 'false' }},

        // Interactive 24-Hour Activity Chart & Flight List state
        selectedHour: null, // null = all hours, 0..23 = specific hour
        movementFilter: 'all', // 'all', 'arrivals', 'departures'
        movementSearch: '',
        movements: initialMovements || [],
        hoveredHour: null,

        // Flight Detail Drawer State
        drawerOpen: false,
        activeFlight: null,

        // Developer Debugger Panel State
        showDebugPanel: false,
        debugTab: 'occupancy',
        debugHour: 15,

        // Tooltips & Modal
        infoTooltipOpen: false,
        glossaryModalOpen: false,

        init() {
            const rawDos = String(initialDos || '').trim().toLowerCase();
            if (rawDos === 'daily' || rawDos === '1234567') {
                this.isDaily = true;
                this.selectedDays = [];
            } else if (rawDos !== '' && rawDos !== 'all') {
                this.isDaily = false;
                const matches = rawDos.match(/[1-7]/g) || [];
                this.selectedDays = Array.from(new Set(matches)).sort();
            } else {
                this.isDaily = false;
                this.selectedDays = [];
            }

            // Restore custom OPS Hours from localStorage if available
            try {
                const saved = localStorage.getItem('slotwaves_ops_hours_{{ $upload->id }}');
                if (saved) {
                    const parsed = JSON.parse(saved);
                    if (parsed.start && parsed.end) {
                        const sH = parseInt(parsed.start.split(':')[0], 10);
                        const eH = parseInt(parsed.end.split(':')[0], 10);
                        if (!isNaN(sH) && !isNaN(eH) && sH >= 0 && sH < eH && eH <= 24) {
                            this.opsStartTime = `${String(sH).padStart(2, '0')}:00`;
                            this.opsEndTime   = `${String(eH).padStart(2, '0')}:00`;
                            this.modalOpsStart = this.opsStartTime;
                            this.modalOpsEnd   = this.opsEndTime;
                        }
                    }
                }
            } catch (e) {}
        },

        // Time conversion helpers
        convertHourToUtc(h) {
            const offsetH = Math.round(this.timezoneOffset / 60);
            let utcH = (h - offsetH) % 24;
            if (utcH < 0) utcH += 24;
            return utcH;
        },

        convertHourToUtcStr(h) {
            const utcH = this.convertHourToUtc(h);
            return `${String(utcH).padStart(2, '0')}:00`;
        },

        flightTime(f) {
            if (!f) return '—';
            if (this.timezoneMode === 'UTC') {
                return f.utc_time || f.scheduled_time;
            }
            return f.scheduled_time;
        },

        flightHour(f) {
            if (!f) return 0;
            if (this.timezoneMode === 'UTC') {
                return (f.utc_hour !== undefined && f.utc_hour !== null) ? f.utc_hour : this.convertHourToUtc(f.hour);
            }
            return f.hour;
        },

        get displayTimezoneLabel() {
            return this.timezoneMode === 'UTC' ? 'UTC' : `${this.timezoneAbbr}`;
        },

        // Reactive Computed OPS Hours
        get opsStartHour() {
            return parseInt((this.opsStartTime || '06:00').split(':')[0], 10);
        },

        get opsEndHour() {
            return parseInt((this.opsEndTime || '19:00').split(':')[0], 10);
        },

        get activeHoursCount() {
            return Math.max(0, this.opsEndHour - this.opsStartHour);
        },

        isOpsHour(h) {
            if (this.timezoneMode === 'UTC') {
                const utcStart = this.convertHourToUtc(this.opsStartHour);
                const utcEnd   = this.convertHourToUtc(this.opsEndHour);
                if (utcStart < utcEnd) {
                    return h >= utcStart && h < utcEnd;
                } else {
                    return h >= utcStart || h < utcEnd;
                }
            }
            return h >= this.opsStartHour && h < this.opsEndHour;
        },

        isFlightInOps(f) {
            return this.isOpsHour(this.flightHour(f));
        },

        get inOpsArrivalsCount() {
            return this.movements.filter(f => f.direction === 'arrival' && this.isFlightInOps(f)).length;
        },

        get inOpsDeparturesCount() {
            return this.movements.filter(f => f.direction === 'departure' && this.isFlightInOps(f)).length;
        },

        get inOpsTotalCount() {
            return this.inOpsArrivalsCount + this.inOpsDeparturesCount;
        },

        // Reactive 24-Hour & OPS Distribution Engine (Excluding Cargo from Passenger Capacity status)
        get hourlyDistribution() {
            const allList = [];
            const opsList = [];
            let maxInOps = 0;
            let peakHourStr = '—';

            for (let h = 0; h < 24; h++) {
                const arrs = this.movements.filter(f => f.direction === 'arrival' && this.flightHour(f) === h);
                const deps = this.movements.filter(f => f.direction === 'departure' && this.flightHour(f) === h);

                const passengerArrs = arrs.filter(f => !f.is_cargo);
                const passengerDeps = deps.filter(f => !f.is_cargo);
                const cargoArrs = arrs.filter(f => f.is_cargo);
                const cargoDeps = deps.filter(f => f.is_cargo);

                const passengerTotal = passengerArrs.length + passengerDeps.length;
                const cargoTotal = cargoArrs.length + cargoDeps.length;
                const total = passengerTotal + cargoTotal;
                const isOps = this.isOpsHour(h);

                // Peak tracking based on passenger movements in active ops window
                if (isOps && passengerTotal > maxInOps) {
                    maxInOps = passengerTotal;
                    peakHourStr = `${String(h).padStart(2, '0')}:00–${String(h).padStart(2, '0')}:59`;
                }

                let status = 'OFF HOURS';
                let statusLabel = 'Off Hours';
                let statusKey = 'off-hours';
                let statusColor = 'slate';
                let remaining = 0;
                let exceeded = 0;

                // Status is based strictly on PASSENGER aircraft vs Aircraft Capacity (Cargo excluded!)
                if (isOps) {
                    if (passengerTotal < this.nacLimit) {
                        status = 'AVAILABLE';
                        statusLabel = 'Available';
                        statusKey = 'available';
                        statusColor = 'emerald'; // Green (#22C55E)
                        remaining = this.nacLimit - passengerTotal;
                        exceeded = 0;
                    } else if (passengerTotal === this.nacLimit) {
                        status = 'FULL / MAX';
                        statusLabel = 'Full / Max';
                        statusKey = 'full';
                        statusColor = 'amber'; // Yellow/Orange (#F59E0B)
                        remaining = 0;
                        exceeded = 0;
                    } else {
                        status = 'OVER CAPACITY';
                        statusLabel = 'Over Capacity';
                        statusKey = 'over-capacity';
                        statusColor = 'purple'; // Purple (#8B5CF6)
                        remaining = 0;
                        exceeded = passengerTotal - this.nacLimit;
                    }
                }

                const item = {
                    hour: h,
                    label: `${String(h).padStart(2, '0')}:00–${String(h).padStart(2, '0')}:59`,
                    shortLabel: String(h).padStart(2, '0'),
                    timeLabel: `${String(h).padStart(2, '0')}:00`,
                    isOps: isOps,
                    arrCount: arrs.length,
                    depCount: deps.length,
                    passengerArrCount: passengerArrs.length,
                    passengerDepCount: passengerDeps.length,
                    passengerCount: passengerTotal,
                    cargoArrCount: cargoArrs.length,
                    cargoDepCount: cargoDeps.length,
                    cargoCount: cargoTotal,
                    total: total,
                    isPeak: false,
                    status: status,
                    statusLabel: statusLabel,
                    statusKey: statusKey,
                    statusColor: statusColor,
                    remaining: remaining,
                    exceeded: exceeded,
                    arrList: arrs,
                    depList: deps
                };

                allList.push(item);
                if (isOps) {
                    opsList.push(item);
                }
            }

            // Mark Peak strictly within active operational window
            if (maxInOps > 0) {
                for (const item of allList) {
                    if (item.isOps && item.passengerCount === maxInOps) {
                        item.isPeak = true;
                    }
                }
            }

            return {
                all: allList,
                ops: opsList,
                list: this.scheduleTimeFilter === 'ops' ? opsList : allList,
                peakHour: peakHourStr,
                peakDemand: maxInOps
            };
        },

        get activeHourlyDistribution() {
            return this.hourlyDistribution.list;
        },

        get peakStats() {
            return {
                peakHour: this.hourlyDistribution.peakHour,
                peakDemand: this.hourlyDistribution.peakDemand
            };
        },

        get chartMaxScale() {
            const maxVal = Math.max(...this.activeHourlyDistribution.map(d => d.total), 0);
            return Math.max(this.nacLimit + 2, maxVal + 2, 8);
        },

        get nacLineBottomPx() {
            return Math.round((this.nacLimit / this.chartMaxScale) * 110) + 26;
        },

        get peakUtilization() {
            return this.nacLimit > 0 ? Math.round((this.peakStats.peakDemand / this.nacLimit) * 100) : 0;
        },

        get totalOperationalCapacity() {
            return this.activeHoursCount * this.nacLimit;
        },

        get remainingCapacity() {
            return Math.max(0, this.nacLimit - this.peakStats.peakDemand);
        },

        // Aircraft Capacity Modal Actions
        openCapacityModal() {
            this.modalNac = this.nacLimit;
            this.capacityModalOpen = true;
        },

        closeCapacityModal() {
            this.capacityModalOpen = false;
        },

        applyCapacity() {
            const newNac = parseInt(this.modalNac, 10);
            if (isNaN(newNac) || newNac < 1 || newNac > 100) {
                return;
            }

            this.nacLimit = newNac;
            this.capacityModalOpen = false;
            this.showToast(`Aircraft Capacity updated to ${this.nacLimit} Aircraft (Excludes Cargo)`);

            // Async background sync to Airport Configuration backend
            fetch(this.saveSettingsUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    aircraft_capacity: this.nacLimit
                })
            }).catch(() => {});
        },

        // Dynamic OPS Hours Modal Actions
        openOpsModal() {
            this.modalOpsStart = this.opsStartTime;
            this.modalOpsEnd   = this.opsEndTime;
            this.modalError    = '';
            this.opsModalOpen  = true;
        },

        closeOpsModal() {
            this.opsModalOpen = false;
            this.modalError   = '';
        },

        applyOpsHours() {
            const sRaw = (this.modalOpsStart || '').trim();
            const eRaw = (this.modalOpsEnd || '').trim();
            const sH = parseInt(sRaw.split(':')[0], 10);
            const eH = parseInt(eRaw.split(':')[0], 10);

            if (isNaN(sH) || isNaN(eH) || sH < 0 || sH > 23 || eH < 1 || eH > 24) {
                this.modalError = 'Please enter valid operating hours (00:00 to 24:00).';
                return;
            }

            if (sH >= eH) {
                this.modalError = 'End time must be later than start time.';
                return;
            }

            this.opsStartTime = `${String(sH).padStart(2, '0')}:00`;
            this.opsEndTime   = `${String(eH).padStart(2, '0')}:00`;
            this.modalError   = '';

            // Save to localStorage
            try {
                localStorage.setItem('slotwaves_ops_hours_{{ $upload->id }}', JSON.stringify({
                    start: this.opsStartTime,
                    end: this.opsEndTime
                }));
            } catch (e) {}

            // Async background sync to TimelineSetting backend
            fetch(this.opsSaveUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ops_start: this.opsStartTime,
                    ops_end: this.opsEndTime
                })
            }).catch(() => {});

            this.showToast(`Operational hours updated to ${this.opsStartTime} → ${this.opsEndTime} (${this.activeHoursCount} Active Hours)`);
            this.opsModalOpen = false;
        },

        resetOpsHours() {
            this.modalOpsStart = this.defaultOpsStart;
            this.modalOpsEnd   = this.defaultOpsEnd;
            this.applyOpsHours();
            this.showToast('Operational hours reset to default (06:00 → 20:00)');
        },

        showToast(msg) {
            this.opsToastMessage = msg;
            this.opsToastOpen = true;
            if (this.toastTimeout) clearTimeout(this.toastTimeout);
            this.toastTimeout = setTimeout(() => {
                this.opsToastOpen = false;
            }, 3500);
        },

        // Exact Capacity Status calculation helper (Excluding Cargo)
        getCapacityStatus(totalMovements, nac = this.nacLimit, isOps = true) {
            if (!isOps) {
                return {
                    status: 'OFF HOURS',
                    key: 'off-hours',
                    color: 'slate',
                    text: 'Di luar jam operasional',
                    remaining: 0,
                    exceeded: 0,
                    diffText: 'Outside Operational Hours'
                };
            }
            if (totalMovements < nac) {
                const remaining = nac - totalMovements;
                return {
                    status: 'AVAILABLE',
                    key: 'available',
                    color: 'emerald',
                    text: 'Kapasitas masih tersedia',
                    remaining: remaining,
                    exceeded: 0,
                    diffText: `Available (${remaining} remaining)`
                };
            } else if (totalMovements === nac) {
                return {
                    status: 'FULL / MAX',
                    key: 'full',
                    color: 'amber',
                    text: 'Kapasitas tepat mencapai batas operasional',
                    remaining: 0,
                    exceeded: 0,
                    diffText: 'Full / Max Capacity (0 remaining)'
                };
            } else {
                const exceeded = totalMovements - nac;
                return {
                    status: 'OVER CAPACITY',
                    key: 'over-capacity',
                    color: 'purple',
                    text: `Melebihi kapasitas operasional sebesar ${exceeded} aircraft`,
                    remaining: 0,
                    exceeded: exceeded,
                    diffText: `Over Capacity (+${exceeded} above limit)`
                };
            }
        },

        toggleDay(day) {
            const strDay = String(day);
            this.isDaily = false;
            const idx = this.selectedDays.indexOf(strDay);
            if (idx !== -1) {
                this.selectedDays.splice(idx, 1);
            } else {
                this.selectedDays.push(strDay);
            }
        },

        selectDaily() {
            this.isDaily = true;
            this.selectedDays = [];
        },

        deselectDaily() {
            this.isDaily = false;
        },

        isSelected(day) {
            return !this.isDaily && this.selectedDays.includes(String(day));
        },

        getDosValue() {
            if (this.isDaily) return '1234567';
            if (this.selectedDays.length === 0) return 'all';
            return [...this.selectedDays].sort((a, b) => Number(a) - Number(b)).join('');
        },

        // Hourly Bar Chart Interaction
        selectHour(hour) {
            if (this.selectedHour === hour) {
                this.selectedHour = null;
            } else {
                this.selectedHour = hour;
                // Smooth scroll to flight list
                const el = document.getElementById('flight-list-section');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        },

        clearHourFilter() {
            this.selectedHour = null;
        },

        // Computed filtered flight movements
        get filteredMovements() {
            const q = (this.movementSearch || '').toLowerCase().trim();
            return this.movements.filter(f => {
                // Hour filter (respecting timezone mode)
                const fH = this.flightHour(f);
                if (this.selectedHour !== null && fH !== this.selectedHour) {
                    return false;
                }
                // Direction filter
                if (this.movementFilter === 'arrivals' && f.direction !== 'arrival') {
                    return false;
                }
                if (this.movementFilter === 'departures' && f.direction !== 'departure') {
                    return false;
                }
                // Search query
                if (q !== '') {
                    const matchFlight = (f.flight_number || '').toLowerCase().includes(q);
                    const matchAirline = (f.airline_name || '').toLowerCase().includes(q) || (f.airline_code || '').toLowerCase().includes(q);
                    const matchRoute = (f.route || '').toLowerCase().includes(q) || (f.origin_name || '').toLowerCase().includes(q) || (f.destination_name || '').toLowerCase().includes(q) || (f.origin || '').toLowerCase().includes(q) || (f.destination || '').toLowerCase().includes(q);
                    const matchAircraft = (f.aircraft_type || '').toLowerCase().includes(q);
                    if (!matchFlight && !matchAirline && !matchRoute && !matchAircraft) {
                        return false;
                    }
                }
                return true;
            });
        },

        get arrivalsCount() {
            return this.movements.filter(f => f.direction === 'arrival').length;
        },

        get departuresCount() {
            return this.movements.filter(f => f.direction === 'departure').length;
        },

        // Drawer
        openFlightDrawer(flight) {
            this.activeFlight = flight;
            this.drawerOpen = true;
        },
        closeFlightDrawer() {
            this.drawerOpen = false;
        }
    };
}
</script>
@endpush

@section('content')

@php
    $airportCode = $airport ? strtoupper($airport->iata_code) : 'BDO';
    $airportName = $airport ? strtoupper($airport->name) : 'HUSEIN SASTRANEGARA';

    $dosValue = $filters['dos'] ?? 'all';

    $filterParams = array_filter([
        'season' => $filters['season'] !== 'all' ? $filters['season'] : null,
        'dos'    => $dosValue !== 'all' ? (is_array($dosValue) ? implode('', $dosValue) : $dosValue) : null,
        'branch' => $filters['branch'] !== 'ALL' ? $filters['branch'] : null,
        'flight' => $filters['flight'] !== 'all' ? $filters['flight'] : null,
        'di'     => $filters['di'] !== 'all' ? $filters['di'] : null,
        'search' => $filters['search'] !== '' ? $filters['search'] : null,
    ]);

    $timePreviewUrl      = route('schedule.preview.time', array_merge(['upload' => $upload->id], $filterParams));
    $dosPreviewUrl       = route('schedule.preview.dos', array_merge(['upload' => $upload->id], $filterParams));
    $downloadCombinedUrl = route('schedule.report.download', array_merge(['upload' => $upload->id], $filterParams));

    $formatDosValue = function(?string $days): string {
        if (!$days) return '—';
        if ($days === '1234567') return 'Daily';
        return implode(',', str_split($days));
    };

    // Calculate capacity utilization percentage
    $peakDemand = $capacityStats['peak_demand'] ?? 0;
    $nacLimit = $capacityStats['nac'] ?? 6;
    $peakUtilization = $nacLimit > 0 ? min(100, round(($peakDemand / $nacLimit) * 100)) : 0;
    $maxChartScale = max(10, $peakDemand ?: 8, $nacLimit + 2);
@endphp

<div x-data="dashboardState('{{ is_array($dosValue) ? implode('', $dosValue) : $dosValue }}', {{ Js::from($flightMovements) }}, '{{ $stats['ops_start'] }}', '{{ $stats['ops_end'] }}', {{ $capacityStats['nac'] ?? 6 }}, '{{ $airportTimezone ?? 'Asia/Jakarta' }}', {{ $timezoneOffset ?? 420 }}, '{{ $timezoneAbbr ?? 'WIB' }}')" class="min-h-screen flex flex-col">

    {{-- ══ TOP APPLICATION NAVIGATION SHELL ══════════════════════════════════ --}}
    <nav class="sticky top-0 z-40 w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-navy-900/90 backdrop-blur-md px-4 sm:px-8 py-3 flex items-center justify-between shadow-xs">
        
        {{-- Left: Brand & Station --}}
        <div class="flex items-center gap-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl bg-aviation-600 flex items-center justify-center shadow-md shadow-aviation-600/25 text-white group-hover:scale-105 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                    </svg>
                </div>
                <div>
                    <span class="text-base font-black tracking-tight text-slate-900 dark:text-white">SlotWaves</span>
                    <span class="hidden sm:inline-block text-[10px] font-bold uppercase tracking-wider ml-1.5 px-2 py-0.5 rounded-full bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">Operations</span>
                </div>
            </a>

            {{-- Center Navigation Links --}}
            <div class="hidden lg:flex items-center gap-1.5 border-l border-slate-200 dark:border-slate-800 pl-5">
                <a href="#overview" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-aviation-600 dark:text-aviation-400 bg-aviation-50 dark:bg-aviation-950/60 border border-aviation-200 dark:border-aviation-800">
                    Dashboard
                </a>
                <a href="#activity-section" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition">
                    Flight Activity
                </a>
                <a href="#flight-list-section" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition">
                    Pergerakan Hari Ini
                </a>
                <a href="{{ route('timeline.show', $upload->id) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition flex items-center gap-1">
                    <span>24-Hour Timeline</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
                <a href="{{ route('master-data.index') }}" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition">
                    Master Data
                </a>
            </div>
        </div>

        {{-- Right: Status, Timezone, Capacity & Theme --}}
        <div class="flex items-center gap-2.5">
            {{-- TIMEZONE [ LOCAL | UTC ] Mode Selector --}}
            <div class="inline-flex p-0.5 bg-slate-100 dark:bg-navy-950 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-bold font-mono">
                <button type="button" @click="timezoneMode = 'LOCAL'"
                        :class="timezoneMode === 'LOCAL' ? 'bg-aviation-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-2.5 py-1 rounded-lg transition text-[11px] cursor-pointer"
                        title="Display in Airport Local Time">
                    LOCAL (<span x-text="timezoneAbbr"></span>)
                </button>
                <button type="button" @click="timezoneMode = 'UTC'"
                        :class="timezoneMode === 'UTC' ? 'bg-aviation-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-2.5 py-1 rounded-lg transition text-[11px] cursor-pointer"
                        title="Convert Schedule to Coordinated Universal Time (UTC)">
                    UTC
                </button>
            </div>

            {{-- Aircraft Capacity configure button --}}
            <button type="button" @click="openCapacityModal()"
                    class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-navy-800 border border-slate-200 dark:border-slate-700/70 hover:border-aviation-400 dark:hover:border-aviation-600 transition text-xs font-mono cursor-pointer group"
                    title="Configure Aircraft Capacity">
                <span class="text-slate-500 dark:text-slate-400">Capacity:</span>
                <span class="font-black text-aviation-600 dark:text-aviation-400" x-text="nacLimit + ' A/C'"></span>
                <span class="text-[10px] text-slate-400 group-hover:text-aviation-500 transition">⚙</span>
            </button>

            {{-- Airport station badge --}}
            <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-navy-800 border border-slate-200 dark:border-slate-700/70 text-xs font-mono">
                <span class="font-black text-aviation-600 dark:text-aviation-400">{{ $airportCode }}</span>
                <span class="text-slate-400">&bull;</span>
                <span class="text-slate-600 dark:text-slate-300 truncate max-w-[140px]">{{ $airportName }}</span>
            </div>

            {{-- Schedule Ready Badge --}}
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                <span class="radar-dot w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Active Slot</span>
            </div>

            {{-- Timeline Shortcut Button --}}
            <a href="{{ route('timeline.show', $upload->id) }}"
               class="hidden sm:inline-flex btn-aviation-primary px-3.5 py-1.5 rounded-xl text-xs font-bold items-center gap-1.5 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Timeline View
            </a>

            {{-- Theme toggle --}}
            <button @click="toggleTheme()" type="button"
                    class="p-2 rounded-xl text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-navy-800 border border-slate-200 dark:border-slate-700/60 hover:bg-slate-200 dark:hover:bg-navy-700 transition cursor-pointer"
                    aria-label="Toggle theme">
                <template x-if="theme === 'dark'">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </template>
                <template x-if="theme === 'light'">
                    <svg class="w-4 h-4 text-aviation-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                </template>
            </button>
        </div>
    </nav>

    {{-- ══ HERO BANNER: AIRPORT OPERATIONS & SCHEDULE OVERVIEW ═══════════════ --}}
    <div id="overview" class="bg-gradient-to-r from-slate-900 via-navy-900 to-aviation-950 text-white px-4 sm:px-8 py-8 shadow-lg border-b border-aviation-900/60">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
            
            <div>
                <div class="flex items-center gap-2 text-aviation-300 text-xs font-bold uppercase tracking-widest mb-1.5">
                    <span>AIRPORT OPERATIONS CONTROL</span>
                    <span>&bull;</span>
                    <span>FLIGHT INTELLIGENCE</span>
                </div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-white flex flex-wrap items-center gap-3">
                    <span>BANDAR UDARA {{ $airportName }}</span>
                    <span class="px-2.5 py-0.5 rounded-xl bg-aviation-600 text-white text-xl sm:text-2xl font-mono shadow-sm">{{ $airportCode }}</span>
                </h1>
                
                <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-slate-300">
                    <span class="inline-flex items-center gap-1.5 text-slate-200">
                        <svg class="w-4 h-4 text-aviation-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        {{ $upload->original_filename }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black uppercase tracking-wider bg-aviation-800 text-aviation-200 border border-aviation-600/50">
                            {{ ucfirst($upload->season ?? 'Summer') }} Season
                        </span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-slate-400 font-mono">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ \Carbon\Carbon::parse($upload->updated_at)->format('d F Y, H:i') }} WIB
                    </span>
                    @if($upload->valid_rows > 0)
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-950/80 text-emerald-300 border border-emerald-700/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Pipeline Validated ({{ $upload->valid_rows }} / {{ $upload->total_rows ?: $upload->valid_rows }} rows)
                    </span>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('timeline.show', $upload->id) }}"
                   class="btn-aviation-primary px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold inline-flex items-center gap-2 shadow-lg shadow-aviation-600/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    24-Hour Timeline
                </a>
                <a :href="'{{ $downloadCombinedUrl }}' + '&ops_start=' + opsStartTime + '&ops_end=' + opsEndTime"
                   class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-white/10 hover:bg-white/20 border border-white/15 text-white transition inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download PDF
                </a>
            </div>

        </div>
    </div>

    {{-- ══ MAIN DASHBOARD CONTAINER ═══════════════════════════════════════════ --}}
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-8 py-8 space-y-8 flex-1">

        {{-- ── 1. FLIGHT INTELLIGENCE KPI CARDS ─────────────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-3.5">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-aviation-600"></span>
                    <span>Flight Intelligence Overview</span>
                </div>
                <div class="text-xs font-mono text-slate-500">
                    Showing <span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="movements.length">{{ $stats['total'] }}</span> of {{ $totalUnfiltered }} Validated Flights
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">

                {{-- Total Flights --}}
                <div class="stat-card-modern p-4.5 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wide">Total Flights</span>
                        <div class="w-7 h-7 rounded-lg bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                        </div>
                    </div>
                    <div>
                        <div class="text-3xl font-black font-mono text-slate-900 dark:text-white" x-text="movements.length">{{ $stats['total'] }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5"><span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="inOpsTotalCount"></span> in OPS window</div>
                    </div>
                </div>

                {{-- Arrivals --}}
                <div class="stat-card-modern p-4.5 flex flex-col justify-between border-l-4 border-l-arrival-600 dark:border-l-arrival-500">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wide text-arrival-700 dark:text-arrival-300">Arrivals</span>
                        <div class="w-7 h-7 rounded-lg bg-arrival-50 dark:bg-arrival-950 flex items-center justify-center text-arrival-600 dark:text-arrival-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        </div>
                    </div>
                    <div>
                        <div class="text-3xl font-black font-mono text-arrival-700 dark:text-arrival-300" x-text="inOpsArrivalsCount">{{ $stats['arrivals'] }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Inbound in OPS window</div>
                    </div>
                </div>

                {{-- Departures --}}
                <div class="stat-card-modern p-4.5 flex flex-col justify-between border-l-4 border-l-aviation-600 dark:border-l-aviation-500">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wide text-aviation-700 dark:text-aviation-300">Departures</span>
                        <div class="w-7 h-7 rounded-lg bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        </div>
                    </div>
                    <div>
                        <div class="text-3xl font-black font-mono text-aviation-700 dark:text-aviation-300" x-text="inOpsDeparturesCount">{{ $stats['departures'] }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Outbound in OPS window</div>
                    </div>
                </div>

                {{-- Capacity Utilization --}}
                <div class="stat-card-modern p-4.5 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wide">Utilization</span>
                        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded"
                              :class="peakUtilization >= 100 ? 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-300' : (peakUtilization >= 80 ? 'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300')"
                              x-text="peakUtilization + '%'">
                            {{ $peakUtilization }}%
                        </span>
                    </div>
                    <div>
                        <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">
                            <span x-text="peakStats.peakDemand">{{ $peakDemand }}</span> <span class="text-xs text-slate-400 font-normal">/ <span x-text="nacLimit"></span> NAC</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-navy-950 h-1.5 rounded-full mt-2 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300"
                                 :class="peakUtilization >= 100 ? 'bg-red-500' : (peakUtilization >= 80 ? 'bg-amber-500' : 'bg-emerald-500')"
                                 :style="'width: ' + Math.min(100, peakUtilization) + '%'"></div>
                        </div>
                    </div>
                </div>

                {{-- Peak Hour --}}
                <div class="stat-card-modern p-4.5 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">Peak Window</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-300">MAX</span>
                    </div>
                    <div>
                        <div class="text-lg font-black font-mono text-amber-600 dark:text-amber-400 truncate" x-text="peakStats.peakHour">
                            {{ $capacityStats['peak_hour'] }}
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5 font-mono"><span x-text="peakStats.peakDemand">{{ $capacityStats['peak_demand'] }}</span> movements/hr</div>
                    </div>
                </div>

                {{-- Operations Window (INTERACTIVE & EDITABLE) --}}
                <div @click="openOpsModal()"
                     class="stat-card-modern p-4.5 flex flex-col justify-between cursor-pointer hover:border-emerald-400 dark:hover:border-emerald-600 hover:shadow-md transition-all group relative">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-2">
                        <span class="text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                            <span>Ops Hours</span>
                            <span class="text-[10px] opacity-70 group-hover:opacity-100 group-hover:scale-110 transition">⚙</span>
                        </span>
                        <span class="radar-dot w-2 h-2 rounded-full bg-emerald-500"></span>
                    </div>
                    <div>
                        <div class="text-base font-black font-mono text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <span x-text="opsStartTime">{{ $stats['ops_start'] }}</span>
                            <span>&rarr;</span>
                            <span x-text="opsEndTime">{{ $stats['ops_end'] }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-400 mt-0.5 font-mono">
                            <span x-text="activeHoursCount + ' Active Hours'">{{ $capacityStats['active_hours_count'] }} Active Hours</span>
                            <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 underline decoration-dotted group-hover:text-emerald-500">Edit</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── 2. TODAY'S FLIGHT ACTIVITY SECTION ────────────────────────── --}}
        <div id="activity-section" class="glass-card p-6 shadow-sm space-y-5">
            
            {{-- Section Header & Controls --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                
                {{-- Title, Subtitle, Info Tooltip --}}
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400 shadow-xs">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                            </svg>
                        </div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                            TODAY'S FLIGHT ACTIVITY
                        </h2>

                        {{-- Interactive Tooltip Popover --}}
                        <div class="relative inline-block" x-data="{ tooltipOpen: false }">
                            <button type="button" 
                                    @mouseenter="tooltipOpen = true" 
                                    @mouseleave="tooltipOpen = false" 
                                    @click="tooltipOpen = !tooltipOpen"
                                    class="w-5 h-5 rounded-full bg-slate-100 dark:bg-navy-800 text-slate-500 hover:text-aviation-600 hover:bg-aviation-50 dark:hover:bg-navy-700 transition flex items-center justify-center text-xs font-bold font-mono cursor-pointer"
                                    aria-label="Info Tooltip">
                                i
                            </button>

                            <div x-show="tooltipOpen" 
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute left-0 top-7 z-50 w-72 sm:w-80 p-3.5 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-2xl shadow-2xl border border-slate-700 text-xs leading-relaxed pointer-events-none"
                                 style="display: none;">
                                <div class="font-bold text-aviation-300 text-xs mb-1.5 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Informasi Distribusi Pergerakan
                                </div>
                                <p class="text-slate-300 text-[11px] leading-normal">
                                    Grafik menunjukkan jumlah pergerakan pesawat pada setiap jam. Arrival ditampilkan dengan warna orange, Departure dengan warna blue. Area hijau menunjukkan jam operasional bandara yang dapat disesuaikan.
                                </p>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Distribusi pergerakan pesawat hari ini berdasarkan waktu kedatangan dan keberangkatan.
                    </p>
                </div>

                {{-- Right: Segmented Control --}}
                <div class="flex items-center gap-2">
                    <div class="inline-flex p-1 bg-slate-100 dark:bg-navy-950 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-semibold">
                        <button type="button"
                                @click="activeChartMode = 'distribution'"
                                :class="activeChartMode === 'distribution' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                class="px-3.5 py-1.5 rounded-lg transition flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            Distribusi Per Jam
                        </button>
                        <button type="button"
                                @click="activeChartMode = 'capacity'"
                                :class="activeChartMode === 'capacity' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                class="px-3.5 py-1.5 rounded-lg transition flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Operational Capacity
                        </button>
                    </div>
                </div>

            </div>

            {{-- 3-Column Responsive Layout: Summary (Left) + 24-Hour Chart (Center) + Reading Guide (Right) --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
                
                {{-- ── COLUMN 1: RINGKASAN HARI INI (Left: 3 cols) ── --}}
                <div class="lg:col-span-3 flex flex-col justify-between bg-slate-50/80 dark:bg-navy-950/80 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-4.5 space-y-4">
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2.5 mb-3">
                            <span class="text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-400">RINGKASAN HARI INI</span>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-200 dark:bg-navy-800 text-slate-700 dark:text-slate-300" x-text="inOpsTotalCount + ' Flights'">{{ $stats['total'] }} Flights</span>
                        </div>

                        {{-- Arrivals Stat --}}
                        <div class="p-3 rounded-xl bg-white dark:bg-navy-900 border border-arrival-200/80 dark:border-arrival-900/60 shadow-2xs mb-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-arrival-500 inline-block"></span>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Arrivals</span>
                                </div>
                                <span class="text-xl font-black font-mono text-arrival-600 dark:text-arrival-400" x-text="inOpsArrivalsCount">{{ $stats['arrivals'] }}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Penerbangan Datang (OPS)</div>
                        </div>

                        {{-- Departures Stat --}}
                        <div class="p-3 rounded-xl bg-white dark:bg-navy-900 border border-aviation-200/80 dark:border-aviation-900/60 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-aviation-600 inline-block"></span>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Departures</span>
                                </div>
                                <span class="text-xl font-black font-mono text-aviation-600 dark:text-aviation-400" x-text="inOpsDeparturesCount">{{ $stats['departures'] }}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Penerbangan Berangkat (OPS)</div>
                        </div>
                    </div>

                    {{-- Total Movements & Peak Highlights --}}
                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wide text-slate-600 dark:text-slate-400">Total Movements</span>
                            <span class="text-2xl font-black font-mono text-slate-900 dark:text-white" x-text="inOpsTotalCount">{{ $stats['total'] }}</span>
                        </div>
                        <div class="text-[10px] text-slate-400">Arrivals + Departures dalam jam OPS</div>

                        {{-- Peak Window Mini Pill --}}
                        <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-between">
                            <div>
                                <div class="text-[10px] font-bold text-amber-700 dark:text-amber-300 uppercase">Peak Window</div>
                                <div class="text-xs font-black font-mono text-amber-600 dark:text-amber-400" x-text="peakStats.peakHour">{{ $capacityStats['peak_hour'] }}</div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-500 text-white shadow-2xs font-mono">
                                <span x-text="peakStats.peakDemand">{{ $capacityStats['peak_demand'] }}</span> mvm/hr
                            </span>
                        </div>
                    </div>
                </div>

                {{-- ── COLUMN 2: 24-HOUR HOURLY MOVEMENT CHART & SCHEDULE OVERVIEW (Center: 6 cols) ── --}}
                <div class="lg:col-span-6 xl:col-span-6 bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4.5 shadow-xs flex flex-col justify-between">
                    
                    {{-- ── MODE 1: DISTRIBUSI PER JAM (24-Hour Bar Chart) ── --}}
                    <div x-show="activeChartMode === 'distribution'" class="space-y-3.5 flex flex-col justify-between h-full">
                        {{-- Chart Header Badges & Segmented Window Toggle --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800/80 pb-2.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="openOpsModal()"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 transition cursor-pointer">
                                    <span class="radar-dot w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>OPS HOURS <span x-text="opsStartTime"></span> &rarr; <span x-text="opsEndTime"></span> (<span x-text="activeHoursCount"></span> Active Hours)</span>
                                    <span class="text-[9px] underline ml-1 font-bold">Edit ⚙</span>
                                </button>
                                
                                {{-- Segmented Time Filter: OPS Window vs All 24 Hours --}}
                                <div class="inline-flex p-0.5 bg-slate-100 dark:bg-navy-950 rounded-lg border border-slate-200 dark:border-slate-800 text-[10px] font-semibold">
                                    <button type="button" 
                                            @click="scheduleTimeFilter = 'ops'"
                                            :class="scheduleTimeFilter === 'ops' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                            class="px-2 py-0.5 rounded-md transition flex items-center gap-1 cursor-pointer">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>OPS Window (<span x-text="activeHoursCount"></span>h)</span>
                                    </button>
                                    <button type="button" 
                                            @click="scheduleTimeFilter = 'all'"
                                            :class="scheduleTimeFilter === 'all' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                            class="px-2 py-0.5 rounded-md transition flex items-center gap-1 cursor-pointer">
                                        <span>24 Hours</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Active Hour Filter Status --}}
                            <template x-if="selectedHour !== null">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-300 dark:border-aviation-800 text-[11px] font-mono font-bold">
                                    <span>Filter: <span x-text="String(selectedHour).padStart(2, '0') + ':00'"></span></span>
                                    <button type="button" @click="clearHourFilter()" class="hover:text-red-500 font-bold ml-1 cursor-pointer">&times;</button>
                                </div>
                            </template>
                        </div>

                        {{-- Chart Visual Canvas (Dynamic Column Bars with Reactive Alpine rendering) --}}
                        <div class="relative pt-6 pb-1 overflow-visible">
                            {{-- Y-Axis Grid Reference Lines --}}
                            <div class="absolute inset-x-0 top-6 bottom-7 pointer-events-none flex flex-col justify-between opacity-20">
                                <div class="border-b border-dashed border-slate-300 dark:border-slate-700 w-full"></div>
                                <div class="border-b border-dashed border-slate-300 dark:border-slate-700 w-full"></div>
                                <div class="border-b border-dashed border-slate-300 dark:border-slate-700 w-full"></div>
                            </div>

                            {{-- Dynamic Horizontal Max Capacity Line (Subtle Reference Layer Behind Bars) --}}
                            <div class="absolute inset-x-0 z-0 transition-all duration-200 pointer-events-auto"
                                 :style="'bottom: ' + nacLineBottomPx + 'px'">
                                <div class="relative w-full group/cap cursor-pointer py-1.5"
                                     @mouseenter="capacityHovered = true"
                                     @mouseleave="capacityHovered = false"
                                     tabindex="0"
                                     @focus="capacityHovered = true"
                                     @blur="capacityHovered = false"
                                     aria-label="Batas Aircraft Capacity">
                                    {{-- Subtle dashed reference line with smooth hover effect --}}
                                    <div class="w-full border-b-2 border-dashed transition-all duration-150"
                                         :class="capacityHovered ? 'border-aviation-500 dark:border-aviation-300 opacity-100' : 'border-aviation-500/40 dark:border-aviation-400/40 opacity-70'"></div>

                                    {{-- Tooltip on Hover / Focus ONLY (No permanent label covering chart) --}}
                                    <div x-show="capacityHovered"
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="absolute left-1/2 -translate-x-1/2 -top-11 z-30 px-3 py-1.5 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-xl shadow-2xl border border-slate-700 text-xs font-mono pointer-events-none text-center whitespace-nowrap"
                                         style="display: none;">
                                        <div class="font-bold text-aviation-300 text-[10px] uppercase tracking-wider">Batas Aircraft Capacity</div>
                                        <div class="font-black text-white text-xs" x-text="nacLimit + ' Aircraft'"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Dynamic Column Bars Grid (Relative z-10 so bars and badges sit comfortably in front) --}}
                            <div class="relative z-10 items-end h-36 px-0.5"
                                 :style="'display: grid; grid-template-columns: repeat(' + activeHourlyDistribution.length + ', minmax(0, 1fr)); gap: ' + (activeHourlyDistribution.length > 16 ? '2px' : '4px') + '; min-height: 144px;'">
                                <template x-for="item in activeHourlyDistribution" :key="item.hour">
                                    <div @click="selectHour(item.hour)"
                                         @mouseenter="hoveredHour = item.hour"
                                         @mouseleave="hoveredHour = null"
                                         :class="selectedHour === item.hour ? 'chart-bar-highlight bg-aviation-50/80 dark:bg-aviation-950/60 rounded-xl ring-2 ring-aviation-500' : ''"
                                         class="flex flex-col items-center justify-end h-full group relative cursor-pointer p-0.5 rounded-lg transition-all duration-150 hover:bg-slate-100 dark:hover:bg-navy-800">
                                        
                                        {{-- Top Status Badge --}}
                                        <template x-if="item.isOps && item.status === 'OVER CAPACITY'">
                                            <div class="absolute -top-3.5 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-purple-600 text-white shadow-xs z-20 font-mono">
                                                OVER
                                            </div>
                                        </template>
                                        <template x-if="item.isOps && item.status === 'FULL / MAX'">
                                            <div class="absolute -top-3.5 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-amber-500 text-white shadow-xs z-20 font-mono">
                                                MAX
                                            </div>
                                        </template>

                                        {{-- Stacked Activity Bar Container --}}
                                        <div class="w-full flex flex-col justify-end gap-0.5 rounded-md overflow-hidden p-0.5 relative transition-all duration-200"
                                             :class="[
                                                 !item.isOps ? 'bg-slate-100/70 dark:bg-navy-950/70 border border-dashed border-slate-300/60 dark:border-slate-800 opacity-60' : (
                                                     item.status === 'OVER CAPACITY' ? 'bg-purple-500/15 border-2 border-purple-500 shadow-xs' : (
                                                         item.status === 'FULL / MAX' ? 'bg-amber-500/15 border-2 border-amber-500' : 'bg-emerald-500/10 dark:bg-emerald-950/30 border border-emerald-500/30'
                                                     )
                                                 ),
                                                 item.isPeak ? 'peak-bar-glow' : ''
                                             ]"
                                             style="height: 98px">
                                            
                                            {{-- Arrivals Block (Orange) --}}
                                            <template x-if="item.arrCount > 0">
                                                <div class="w-full bg-arrival-500 rounded-xs transition-all duration-300 group-hover:brightness-110" 
                                                     :style="'height: ' + Math.max(4, Math.round((item.arrCount / chartMaxScale) * 92)) + 'px'"></div>
                                            </template>

                                            {{-- Departures Block (Blue) --}}
                                            <template x-if="item.depCount > 0">
                                                <div class="w-full bg-aviation-600 rounded-xs transition-all duration-300 group-hover:brightness-110" 
                                                     :style="'height: ' + Math.max(4, Math.round((item.depCount / chartMaxScale) * 92)) + 'px'"></div>
                                            </template>

                                            {{-- Baseline tick for 0 movements --}}
                                            <template x-if="item.total === 0">
                                                <div class="w-full h-1 bg-slate-200 dark:bg-navy-800 rounded-xs"></div>
                                            </template>
                                        </div>

                                        {{-- Movement count number --}}
                                        <span class="text-[9px] sm:text-[10px] font-mono font-bold mt-1"
                                              :class="!item.isOps ? 'text-slate-400 dark:text-slate-500' : (item.status === 'OVER CAPACITY' ? 'text-purple-600 dark:text-purple-400 font-black' : (item.status === 'FULL / MAX' ? 'text-amber-600 dark:text-amber-400 font-black' : (item.total > 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-300 dark:text-slate-600')))"
                                              x-text="item.total > 0 ? item.total : '0'">
                                        </span>

                                        {{-- Hour X-Axis Label --}}
                                        <span class="text-[10px] sm:text-[11px] font-mono"
                                              :class="item.isOps ? 'text-slate-700 dark:text-slate-200 font-bold' : 'text-slate-400 dark:text-slate-500'"
                                              x-text="item.shortLabel">
                                        </span>

                                        {{-- Interactive Rich Tooltip on Hover --}}
                                        <div x-show="hoveredHour === item.hour"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             class="absolute bottom-full mb-3 flex flex-col z-50 w-64 p-3.5 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-2xl shadow-2xl border border-slate-700 text-xs pointer-events-none transition-all duration-150"
                                             :class="item.hour <= 4 ? 'left-0' : (item.hour >= 19 ? 'right-0' : 'left-1/2 -translate-x-1/2')"
                                             style="display: none;">
                                            
                                            {{-- Tooltip Header --}}
                                            <div class="flex items-center justify-between border-b border-slate-700/80 pb-1.5 mb-1.5">
                                                <span class="font-mono font-bold text-slate-100 text-xs" x-text="item.label + ' (' + displayTimezoneLabel + ')'"></span>
                                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase font-mono"
                                                      :class="!item.isOps ? 'bg-slate-800 text-slate-400 border border-slate-700' : (item.status === 'OVER CAPACITY' ? 'bg-purple-950 text-purple-300 border border-purple-600' : (item.status === 'FULL / MAX' ? 'bg-amber-950 text-amber-300 border border-amber-600' : 'bg-emerald-950 text-emerald-300 border border-emerald-600'))"
                                                      x-text="item.status">
                                                </span>
                                            </div>

                                            {{-- Tooltip Movements Breakdown --}}
                                            <div class="space-y-1.5 font-mono text-[11px]">
                                                <div class="flex items-center justify-between text-arrival-400">
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="w-2 h-2 rounded-full bg-arrival-500 inline-block"></span>
                                                        Arrivals:
                                                    </span>
                                                    <span class="font-bold" x-text="item.arrCount + ' (Pax: ' + item.passengerArrCount + (item.cargoArrCount > 0 ? ', Cargo: ' + item.cargoArrCount : '') + ')'"></span>
                                                </div>
                                                <div class="flex items-center justify-between text-aviation-300">
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="w-2 h-2 rounded-full bg-aviation-600 inline-block"></span>
                                                        Departures:
                                                    </span>
                                                    <span class="font-bold" x-text="item.depCount + ' (Pax: ' + item.passengerDepCount + (item.cargoDepCount > 0 ? ', Cargo: ' + item.cargoDepCount : '') + ')'"></span>
                                                </div>

                                                <template x-if="item.cargoCount > 0">
                                                    <div class="flex items-center justify-between text-amber-300 text-[10px] bg-amber-950/40 px-2 py-1 rounded border border-amber-800/40">
                                                        <span>Cargo (Separate Stand):</span>
                                                        <span class="font-bold" x-text="item.cargoCount + ' Movements'"></span>
                                                    </div>
                                                </template>

                                                <div class="flex items-center justify-between font-bold text-white pt-1 border-t border-slate-800">
                                                    <span>Pax Aircraft Capacity:</span>
                                                    <span class="text-xs font-black text-emerald-400" x-text="item.passengerCount + ' / ' + nacLimit + ' A/C'"></span>
                                                </div>

                                                <template x-if="item.isOps">
                                                    <div class="pt-1 border-t border-slate-800 text-[10px]"
                                                         :class="item.status === 'OVER CAPACITY' ? 'text-purple-300' : (item.status === 'FULL / MAX' ? 'text-amber-300' : 'text-emerald-300')">
                                                        <template x-if="item.status === 'AVAILABLE'">
                                                            <span x-text="'Capacity Available (' + item.remaining + ' remaining below Capacity ' + nacLimit + ')'"></span>
                                                        </template>
                                                        <template x-if="item.status === 'FULL / MAX'">
                                                            <span x-text="'Full / Max Capacity (0 remaining, reached Capacity ' + nacLimit + ')'"></span>
                                                        </template>
                                                        <template x-if="item.status === 'OVER CAPACITY'">
                                                            <span x-text="'Over Capacity (+' + item.exceeded + ' movements above Capacity ' + nacLimit + ')'"></span>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>

                                            <div class="text-[9px] text-slate-400 mt-2 pt-1.5 border-t border-slate-800 text-center italic">
                                                Klik untuk memfilter flight list
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Bottom Compact Legend Strip --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 text-[11px] text-slate-500">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#22C55E] inline-block"></span>
                                    <strong class="text-slate-700 dark:text-slate-300">Available</strong>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#F59E0B] inline-block"></span>
                                    <strong class="text-slate-700 dark:text-slate-300">Full / Max</strong>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#8B5CF6] inline-block"></span>
                                    <strong class="text-slate-700 dark:text-slate-300">Over Capacity</strong>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-600 inline-block"></span>
                                    <span class="text-slate-500 dark:text-slate-400">Off Hours</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5 font-mono text-[10.5px] text-aviation-600 dark:text-aviation-400 cursor-pointer"
                                      @click="openCapacityModal()" title="Click to configure Aircraft Capacity">
                                    <span class="w-4 border-b-2 border-dashed border-aviation-500 inline-block"></span>
                                    <span>Aircraft Capacity (<span x-text="nacLimit"></span> A/C) ⚙</span>
                                </span>
                            </div>
                            <div class="flex items-center gap-2.5 text-[10px] text-slate-400">
                                <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-xs bg-arrival-500 inline-block"></span> ARR</span>
                                <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-xs bg-aviation-600 inline-block"></span> DEP</span>
                            </div>
                        </div>
                    </div>

                    {{-- ── MODE 2: OPERATIONAL CAPACITY (Schedule Overview / Agenda Board) ── --}}
                    <div x-show="activeChartMode === 'capacity'" class="space-y-3 flex flex-col justify-between h-full">
                        {{-- Schedule Header & Time Filters --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 border-b border-slate-100 dark:border-slate-800/80 pb-2.5">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">OPERATIONAL CAPACITY &mdash; SCHEDULE OVERVIEW</h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-aviation-100 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-mono">
                                        {{ $stats['total'] }} Flights
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Jadwal pergerakan pesawat berdasarkan waktu kedatangan dan keberangkatan.
                                </p>
                            </div>

                            {{-- Time Filter Segmented Control --}}
                            <div class="inline-flex p-1 bg-slate-100 dark:bg-navy-950 rounded-xl border border-slate-200 dark:border-slate-800 text-[11px] font-semibold shrink-0">
                                <button type="button" 
                                        @click="scheduleTimeFilter = 'ops'"
                                        :class="scheduleTimeFilter === 'ops' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                        class="px-2.5 py-1 rounded-lg transition flex items-center gap-1.5 cursor-pointer">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Show OPS Hours (<span x-text="opsStartTime"></span>–<span x-text="opsEndTime"></span>)</span>
                                </button>
                                <button type="button" 
                                        @click="scheduleTimeFilter = 'all'"
                                        :class="scheduleTimeFilter === 'all' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                        class="px-2.5 py-1 rounded-lg transition flex items-center gap-1.5 cursor-pointer">
                                    <span>Show All Time (00:00–23:00)</span>
                                </button>
                            </div>
                        </div>

                        {{-- Schedule Table Container (Vertical Scrolling & Sticky Header) --}}
                        <div class="overflow-y-auto max-h-[380px] custom-scrollbar rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs bg-slate-50/40 dark:bg-navy-950/40">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="sticky top-0 z-20 bg-slate-100/95 dark:bg-navy-950/95 backdrop-blur-xs border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                    <tr>
                                        <th class="py-2 px-3 w-[42%] text-arrival-600 dark:text-arrival-400">
                                            <span class="flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-arrival-500"></span>
                                                STA (ARRIVALS)
                                            </span>
                                        </th>
                                        <th class="py-2 px-2 text-center w-[16%] font-mono text-slate-700 dark:text-slate-300">
                                            TIME
                                        </th>
                                        <th class="py-2 px-3 w-[42%] text-aviation-600 dark:text-aviation-400 text-right">
                                            <span class="inline-flex items-center gap-1.5">
                                                STD (DEPARTURES)
                                                <span class="w-2 h-2 rounded-full bg-aviation-600"></span>
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-navy-900">
                                    @foreach($hourlySchedule as $h => $hData)
                                        @php
                                            $arrList = $hData['arrivals'];
                                            $depList = $hData['departures'];
                                        @endphp
                                        <tr x-show="scheduleTimeFilter === 'all' || isOpsHour({{ $h }})"
                                            class="hover:bg-slate-50/60 dark:hover:bg-navy-800/40 transition">
                                            
                                            {{-- Left: STA (Arrivals) Flight Cards --}}
                                            <td class="py-2 px-2.5 align-top">
                                                @if(count($arrList) > 0)
                                                    <div class="space-y-1.5">
                                                        @foreach($arrList as $f)
                                                            <div @click="openFlightDrawer(@js($f))"
                                                                 @mouseenter="activeCardTooltip = {{ $f['id'] }}"
                                                                 @mouseleave="activeCardTooltip = null"
                                                                 class="relative p-2 rounded-xl border border-arrival-200/90 dark:border-arrival-900/60 bg-arrival-50/50 dark:bg-navy-950/70 hover:bg-arrival-100/70 dark:hover:bg-navy-800/80 hover:border-arrival-400 transition-all duration-150 cursor-pointer shadow-2xs group">
                                                                
                                                                {{-- Top: Plane Icon + STA + Flight No + Aircraft --}}
                                                                <div class="flex items-center justify-between font-mono">
                                                                    <div class="flex items-center gap-1.5">
                                                                        <span class="text-xs text-arrival-600 dark:text-arrival-400 font-bold">✈</span>
                                                                        <span class="text-xs font-black text-slate-900 dark:text-white">{{ $f['scheduled_time'] }}</span>
                                                                        <span class="text-xs font-black text-arrival-600 dark:text-arrival-400 ml-1">{{ $f['flight_number'] }}</span>
                                                                    </div>
                                                                    <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-white dark:bg-navy-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800">{{ $f['aircraft_type'] }}</span>
                                                                </div>

                                                                {{-- Bottom: From Airport & City + Traffic + Pairing info --}}
                                                                <div class="flex items-center justify-between text-[11px] mt-1.5 pt-1 border-t border-arrival-200/40 dark:border-arrival-900/30">
                                                                    <div class="flex items-center gap-1 truncate text-slate-600 dark:text-slate-300">
                                                                        <span class="text-[9px] font-bold uppercase text-slate-400 font-mono">From</span>
                                                                        <span class="font-bold text-slate-900 dark:text-white font-mono">{{ $f['origin'] }}</span>
                                                                        <span class="text-slate-400 text-[10px] truncate max-w-[100px]">({{ $f['origin_name'] }})</span>
                                                                    </div>
                                                                    <div class="flex items-center gap-1 shrink-0 ml-1">
                                                                        @if(!empty($f['pairing']['is_ron']))
                                                                            <span class="text-[8.5px] font-mono font-black uppercase px-1.5 py-0.2 rounded bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">RON</span>
                                                                        @elseif(!empty($f['pairing']['is_paired']))
                                                                            <span class="text-[8.5px] font-mono font-black uppercase px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">PAIR</span>
                                                                        @endif
                                                                        <span class="text-[9px] font-mono font-bold uppercase px-1.5 py-0.2 rounded bg-arrival-100 dark:bg-arrival-950 text-arrival-700 dark:text-arrival-300 border border-arrival-200 dark:border-arrival-800">{{ $f['traffic_badge'] }}</span>
                                                                    </div>
                                                                </div>

                                                                {{-- Rich Tooltip on Hover --}}
                                                                <div x-show="activeCardTooltip === {{ $f['id'] }}"
                                                                     x-transition:enter="transition ease-out duration-100"
                                                                     x-transition:enter-start="opacity-0 scale-95"
                                                                     x-transition:enter-end="opacity-100 scale-100"
                                                                     class="absolute left-0 bottom-full mb-2 z-50 w-64 p-3 bg-slate-900/95 dark:bg-navy-900/95 text-white rounded-xl shadow-2xl border border-slate-700 text-xs pointer-events-none"
                                                                     style="display: none;">
                                                                    <div class="flex items-center justify-between border-b border-slate-700 pb-1 mb-1.5">
                                                                        <span class="font-bold font-mono text-arrival-400 text-xs">{{ $f['flight_number'] }} &bull; {{ $f['airline_name'] }}</span>
                                                                        <span class="text-[9px] font-mono font-bold uppercase px-1.5 py-0.2 rounded bg-arrival-950 text-arrival-300 border border-arrival-700">ARR</span>
                                                                    </div>
                                                                    <div class="space-y-1 font-mono text-[11px]">
                                                                        <div class="flex justify-between">
                                                                            <span class="text-slate-400">STA:</span>
                                                                            <span class="font-bold text-white">{{ $f['scheduled_time'] }}</span>
                                                                        </div>
                                                                        <div class="flex justify-between">
                                                                            <span class="text-slate-400">Aircraft:</span>
                                                                            <span class="text-amber-400">{{ $f['aircraft_type'] }}</span>
                                                                        </div>
                                                                        <div class="flex justify-between">
                                                                            <span class="text-slate-400">From:</span>
                                                                            <span class="text-slate-200">{{ $f['origin'] }} &mdash; {{ $f['origin_name'] }}</span>
                                                                        </div>
                                                                        <div class="flex justify-between">
                                                                            <span class="text-slate-400">Rotation:</span>
                                                                            <span class="text-aviation-300 font-bold">{{ $f['pairing']['summary_text'] ?? '—' }}</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-[9px] text-slate-400 mt-1.5 text-center italic border-t border-slate-800 pt-1">
                                                                        Klik untuk membuka detail flight &amp; pairing
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="text-[11px] font-mono text-slate-400 italic py-1 px-1">
                                                        &mdash; No arrivals
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Center: TIME Hour Bucket --}}
                                            <td class="py-2 px-2 text-center align-middle whitespace-nowrap">
                                                <div class="inline-flex flex-col items-center">
                                                    <span class="px-2 py-0.8 rounded-xl text-[10px] font-mono font-black border shadow-2xs"
                                                          :class="isOpsHour({{ $h }}) ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 text-slate-500 dark:bg-navy-950 dark:text-slate-400 border-slate-200 dark:border-slate-800'">
                                                        {{ sprintf('%02d:00–%02d:59', $h, $h) }}
                                                    </span>
                                                    <span class="text-[8.5px] font-bold uppercase tracking-wider mt-0.5"
                                                          :class="isOpsHour({{ $h }}) ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                                                          x-text="isOpsHour({{ $h }}) ? 'OPS' : 'NON-OPS'">
                                                    </span>
                                                </div>
                                            </td>

                                            {{-- Right: STD (Departures) Flight Cards --}}
                                            <td class="py-2 px-2.5 align-top">
                                                @if(count($depList) > 0)
                                                     <div class="space-y-1.5">
                                                        @foreach($depList as $f)
                                                            <div @click="openFlightDrawer(@js($f))"
                                                                 @mouseenter="activeCardTooltip = {{ $f['id'] }}"
                                                                 @mouseleave="activeCardTooltip = null"
                                                                 class="relative p-2 rounded-xl border border-aviation-200/90 dark:border-aviation-900/60 bg-aviation-50/50 dark:bg-navy-950/70 hover:bg-aviation-100/70 dark:hover:bg-navy-800/80 hover:border-aviation-400 transition-all duration-150 cursor-pointer shadow-2xs group text-left">
                                                                
                                                                {{-- Top: Plane Icon + STD + Flight No + Aircraft --}}
                                                                <div class="flex items-center justify-between font-mono">
                                                                    <div class="flex items-center gap-1.5">
                                                                        <span class="text-xs text-aviation-600 dark:text-aviation-400 font-bold">✈</span>
                                                                        <span class="text-xs font-black text-slate-900 dark:text-white">{{ $f['scheduled_time'] }}</span>
                                                                        <span class="text-xs font-black text-aviation-600 dark:text-aviation-400 ml-1">{{ $f['flight_number'] }}</span>
                                                                    </div>
                                                                    <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-white dark:bg-navy-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800">{{ $f['aircraft_type'] }}</span>
                                                                </div>

                                                                {{-- Bottom: To Airport & City + Traffic + Pairing --}}
                                                                <div class="flex items-center justify-between text-[11px] mt-1.5 pt-1 border-t border-aviation-200/40 dark:border-aviation-900/30">
                                                                    <div class="flex items-center gap-1 truncate text-slate-600 dark:text-slate-300">
                                                                        <span class="text-[9px] font-bold uppercase text-slate-400 font-mono">To</span>
                                                                        <span class="font-bold text-slate-900 dark:text-white font-mono">{{ $f['destination'] }}</span>
                                                                        <span class="text-slate-400 text-[10px] truncate max-w-[100px]">({{ $f['destination_name'] }})</span>
                                                                    </div>
                                                                    <div class="flex items-center gap-1 shrink-0 ml-1">
                                                                        @if(!empty($f['pairing']['is_overnight_dep']))
                                                                            <span class="text-[8.5px] font-mono font-black uppercase px-1.5 py-0.2 rounded bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">RON</span>
                                                                        @elseif(!empty($f['pairing']['is_paired']))
                                                                            <span class="text-[8.5px] font-mono font-black uppercase px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">PAIR</span>
                                                                        @endif
                                                                        <span class="text-[9px] font-mono font-bold uppercase px-1.5 py-0.2 rounded bg-aviation-100 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">{{ $f['traffic_badge'] }}</span>
                                                                    </div>
                                                                </div>

                                                                {{-- Rich Tooltip on Hover --}}
                                                                <div x-show="activeCardTooltip === {{ $f['id'] }}"
                                                                     x-transition:enter="transition ease-out duration-100"
                                                                     x-transition:enter-start="opacity-0 scale-95"
                                                                     x-transition:enter-end="opacity-100 scale-100"
                                                                     class="absolute right-0 bottom-full mb-2 z-50 w-64 p-3 bg-slate-900/95 dark:bg-navy-900/95 text-white rounded-xl shadow-2xl border border-slate-700 text-xs pointer-events-none"
                                                                     style="display: none;">
                                                                    <div class="flex items-center justify-between border-b border-slate-700 pb-1 mb-1.5">
                                                                        <span class="font-bold font-mono text-aviation-400 text-xs">{{ $f['flight_number'] }} &bull; {{ $f['airline_name'] }}</span>
                                                                        <span class="text-[9px] font-mono font-bold uppercase px-1.5 py-0.2 rounded bg-aviation-950 text-aviation-300 border border-aviation-700">DEP</span>
                                                                    </div>
                                                                    <div class="space-y-1 font-mono text-[11px]">
                                                                        <div class="flex justify-between">
                                                                            <span class="text-slate-400">STD:</span>
                                                                            <span class="font-bold text-white">{{ $f['scheduled_time'] }}</span>
                                                                        </div>
                                                                        <div class="flex justify-between">
                                                                            <span class="text-slate-400">Aircraft:</span>
                                                                            <span class="text-amber-400">{{ $f['aircraft_type'] }}</span>
                                                                        </div>
                                                                        <div class="flex justify-between">
                                                                            <span class="text-slate-400">To:</span>
                                                                            <span class="text-slate-200">{{ $f['destination'] }} &mdash; {{ $f['destination_name'] }}</span>
                                                                        </div>
                                                                        <div class="flex justify-between">
                                                                            <span class="text-slate-400">Rotation:</span>
                                                                            <span class="text-aviation-300 font-bold">{{ $f['pairing']['summary_text'] ?? '—' }}</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-[9px] text-slate-400 mt-1.5 text-center italic border-t border-slate-800 pt-1">
                                                                        Klik untuk membuka detail flight &amp; pairing
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="text-[11px] font-mono text-slate-400 italic py-1 px-1 text-right">
                                                        No departures &mdash;
                                                    </div>
                                                @endif
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Bottom Note --}}
                        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800">
                            <span>Airport Flight Operations Movement Board &bull; Sorted chronologically by STA / STD</span>
                            <span class="text-aviation-600 dark:text-aviation-400 font-semibold">Klik kartu untuk membuka Flight Intelligence</span>
                        </div>
                    </div>

                </div>

                {{-- ── COLUMN 3: CARA MEMBACA GRAFIK / JADWAL (Right: 3 cols) ── --}}
                <div class="lg:col-span-3 flex flex-col justify-between bg-slate-50/80 dark:bg-navy-950/80 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-4.5 space-y-3.5">
                    
                    {{-- Guide for Distribusi Per Jam --}}
                    <div x-show="activeChartMode === 'distribution'" class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2.5">
                            <svg class="w-4 h-4 text-aviation-600 dark:text-aviation-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white">CARA MEMBACA GRAFIK</h3>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            Panduan membaca pergerakan pesawat dan status kapasitas operasional bandara.
                        </p>

                        {{-- Legend items --}}
                        <div class="space-y-2 text-xs">
                            <div class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-white dark:hover:bg-navy-900 transition">
                                <span class="w-3 h-3 rounded-full bg-[#22C55E] shrink-0 mt-0.5"></span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">Available</div>
                                    <div class="text-[10px] text-slate-500">Pergerakan pesawat masih berada di bawah kapasitas limit NAC.</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-white dark:hover:bg-navy-900 transition">
                                <span class="w-3 h-3 rounded-full bg-[#F59E0B] shrink-0 mt-0.5"></span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">Full / Max</div>
                                    <div class="text-[10px] text-slate-500">Pergerakan pesawat tepat mencapai kapasitas maksimum ({{ $capacityStats['nac'] ?? 6 }} A/C).</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-white dark:hover:bg-navy-900 transition">
                                <span class="w-3 h-3 rounded-full bg-[#8B5CF6] shrink-0 mt-0.5"></span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">Over Capacity</div>
                                    <div class="text-[10px] text-slate-500">Pergerakan melebihi batas toleransi kapasitas operasional.</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-white dark:hover:bg-navy-900 transition">
                                <span class="w-3 h-3 rounded-md bg-slate-300 dark:bg-navy-800 shrink-0 mt-0.5"></span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">Off Hours (Di Luar OPS)</div>
                                    <div class="text-[10px] text-slate-500">Di luar jam operasional bandara &mdash; dikecualikan dari status kapasitas.</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-white dark:hover:bg-navy-900 transition">
                                <span class="w-4 border-b-2 border-dashed border-aviation-500 shrink-0 mt-1.5"></span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">Max Aircraft Capacity</div>
                                    <div class="text-[10px] text-slate-500">Garis batas daya tampung simultan apron ({{ $capacityStats['nac'] ?? 6 }} pesawat).</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Guide for Operational Capacity (Cara Membaca Jadwal) --}}
                    <div x-show="activeChartMode === 'capacity'" class="space-y-3">
                        <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2.5">
                            <svg class="w-4 h-4 text-aviation-600 dark:text-aviation-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white">CARA MEMBACA JADWAL</h3>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            Panduan parameter pergerakan operasional pesawat pada movement board.
                        </p>

                        {{-- Parameter List --}}
                        <div class="space-y-2 text-xs">
                            <div class="flex items-start gap-2 p-1.5 rounded-lg hover:bg-white dark:hover:bg-navy-900 transition">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-black bg-arrival-100 text-arrival-700 dark:bg-arrival-950 dark:text-arrival-300 border border-arrival-200 dark:border-arrival-800 shrink-0">STA</span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">Scheduled Time of Arrival</div>
                                    <div class="text-[10px] text-slate-500">Jadwal waktu kedatangan penerbangan.</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 p-1.5 rounded-lg hover:bg-white dark:hover:bg-navy-900 transition">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-black bg-aviation-100 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 shrink-0">STD</span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">Scheduled Time of Departure</div>
                                    <div class="text-[10px] text-slate-500">Jadwal waktu keberangkatan penerbangan.</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 p-1.5 rounded-lg hover:bg-white dark:hover:bg-navy-900 transition">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-black bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800 shrink-0">RON</span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">Remain Over Night</div>
                                    <div class="text-[10px] text-slate-500">Pesawat menginap di apron bandara.</div>
                                </div>
                            </div>

                            <div class="pt-1.5 border-t border-slate-200 dark:border-slate-800 space-y-1.5">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-arrival-500"></span>
                                        <strong>DOM:</strong>
                                    </span>
                                    <span class="text-slate-500 text-[10px]">Rute Domestik</span>
                                </div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-aviation-600"></span>
                                        <strong>INTL:</strong>
                                    </span>
                                    <span class="text-slate-500 text-[10px]">Rute Internasional</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Helpful interactive tip --}}
                    <div class="p-2.5 rounded-xl bg-aviation-50 dark:bg-aviation-950/60 border border-aviation-200 dark:border-aviation-800 text-[10px] text-aviation-800 dark:text-aviation-300">
                        <strong>Tips Operasional:</strong> Klik kartu penerbangan untuk melihat rotasi pairing atau status RON (Remain Over Night).
                    </div>
                </div>

            </div>

        </div>

        {{-- ── 3. FLIGHT SCHEDULE FILTER (FORM QUERY) ─────────────────────────── --}}
        <div id="filter-section" class="glass-card p-6 shadow-sm">
            <form action="{{ route('schedule.dashboard', $upload->id) }}" method="GET" id="filterForm">
                
                <div class="flex items-center justify-between mb-5 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                        </div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">FLIGHT SCHEDULE FILTER</h2>
                    </div>
                    <span class="text-xs text-slate-400 font-medium">Dynamically query active operational dataset</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-5">

                    {{-- Season --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1.5">Slot Season</label>
                        <select name="season" class="w-full filter-select">
                            <option value="all" {{ $filters['season'] === 'all' ? 'selected' : '' }}>All Seasons</option>
                            <option value="summer" {{ $filters['season'] === 'summer' ? 'selected' : '' }}>Summer</option>
                            <option value="winter" {{ $filters['season'] === 'winter' ? 'selected' : '' }}>Winter</option>
                        </select>
                    </div>

                    {{-- Branch Airport --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1.5">Branch (Airport Station)</label>
                        <select name="branch" class="w-full filter-select">
                            <option value="all" {{ $filters['branch'] === 'ALL' ? 'selected' : '' }}>All Branches</option>
                            @foreach($airports as $ap)
                                <option value="{{ $ap->iata_code }}" {{ $filters['branch'] === $ap->iata_code ? 'selected' : '' }}>
                                    {{ $ap->iata_code }} &mdash; {{ $ap->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Flight Type & Flight Number --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1.5">Flight Type &amp; Number</label>
                        <div class="flex gap-2">
                            <select name="flight" class="w-1/2 filter-select">
                                <option value="all" {{ $filters['flight'] === 'all' ? 'selected' : '' }}>All Flights</option>
                                <option value="arrivals" {{ $filters['flight'] === 'arrivals' ? 'selected' : '' }}>Arrivals</option>
                                <option value="departures" {{ $filters['flight'] === 'departures' ? 'selected' : '' }}>Departures</option>
                            </select>
                            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Flight No..." class="w-1/2 filter-select font-mono uppercase">
                        </div>
                    </div>

                    {{-- D / I --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1.5">D / I (Domestic / Int'l)</label>
                        <select name="di" class="w-full filter-select">
                            <option value="all" {{ $filters['di'] === 'all' ? 'selected' : '' }}>All (Domestic &amp; Int'l)</option>
                            <option value="domestic" {{ $filters['di'] === 'domestic' ? 'selected' : '' }}>Domestic</option>
                            <option value="international" {{ $filters['di'] === 'international' ? 'selected' : '' }}>International</option>
                        </select>
                    </div>

                    {{-- DOS Interactive Day Selector --}}
                    <div class="md:col-span-2 lg:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1.5">
                            DOS (Operating Days Filter)
                        </label>
                        
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="hidden" name="dos" :value="getDosValue()">

                            <button type="button" @click="isDaily ? deselectDaily() : selectDaily()"
                                    :class="isDaily ? 'bg-aviation-600 text-white font-bold border-aviation-600 shadow-sm' : 'bg-slate-100 dark:bg-navy-950 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700/80 hover:bg-slate-200 dark:hover:bg-navy-800'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs border transition flex-shrink-0 cursor-pointer">
                                Daily
                            </button>

                            <div class="flex items-center gap-1.5 overflow-x-auto">
                                @foreach(['1'=>'Mon','2'=>'Tue','3'=>'Wed','4'=>'Thu','5'=>'Fri','6'=>'Sat','7'=>'Sun'] as $num => $dayName)
                                    <button type="button"
                                            @click="toggleDay('{{ $num }}')"
                                            :class="isSelected('{{ $num }}')
                                                ? 'bg-amber-500 text-white border-amber-600 font-bold shadow-xs'
                                                : 'bg-slate-100 dark:bg-navy-950 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700/80 hover:bg-slate-200 dark:hover:bg-navy-800'"
                                            class="w-8 h-8 rounded-xl text-xs border transition flex items-center justify-center font-mono font-bold cursor-pointer"
                                            title="Day {{ $num }} ({{ $dayName }})">
                                        {{ $num }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Active Filter summary & Apply button --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-slate-500 font-semibold">Active:</span>
                        @if($filters['season'] !== 'all')
                            <span class="px-2.5 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-semibold">Season: {{ ucfirst($filters['season']) }}</span>
                        @endif
                        @if($filters['branch'] !== 'ALL')
                            <span class="px-2.5 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-semibold">Branch: {{ $filters['branch'] }}</span>
                        @endif
                        @if($filters['flight'] !== 'all')
                            <span class="px-2.5 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-semibold">Type: {{ ucfirst($filters['flight']) }}</span>
                        @endif
                        @if($filters['di'] !== 'all')
                            <span class="px-2.5 py-0.5 rounded-full bg-cyan-50 dark:bg-cyan-950 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800 font-semibold">D/I: {{ ucfirst($filters['di']) }}</span>
                        @endif
                        @if($filters['dos'] !== 'all' && $filters['dos'] !== '')
                            <span class="px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 font-semibold">
                                DOS: {{ $filters['dos'] === 'daily' || $filters['dos'] === '1234567' ? 'Daily (1234567)' : (strlen($filters['dos']) === 1 ? 'Day ' . $filters['dos'] : 'Exact (' . $filters['dos'] . ')') }}
                            </span>
                        @endif
                        @if($filters['search'] !== '')
                            <span class="px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-navy-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold">Search: "{{ $filters['search'] }}"</span>
                        @endif
                        @if($filters['season'] === 'all' && $filters['branch'] === 'ALL' && $filters['flight'] === 'all' && $filters['di'] === 'all' && ($filters['dos'] === 'all' || $filters['dos'] === '') && $filters['search'] === '')
                            <span class="text-slate-400 italic">None (All Flights Visible)</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('schedule.dashboard', $upload->id) }}"
                           class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 border border-slate-200 dark:border-slate-700 rounded-xl transition">
                            Reset
                        </a>
                        <button type="submit"
                                class="btn-aviation-primary px-5 py-2 text-xs font-bold rounded-xl transition shadow-md flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Apply Filters
                        </button>
                    </div>
                </div>

            </form>
        </div>

        {{-- ── 4. LIST PERGERAKAN HARI INI (INTERACTIVE FLIGHT LIST CONNECTED TO CHART) ── --}}
        <div id="flight-list-section" class="glass-card p-6 shadow-sm space-y-5">
            
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-xl bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/>
                            </svg>
                        </div>
                        <h2 class="text-base font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                            LIST PERGERAKAN HARI INI
                        </h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Menampilkan jadwal pergerakan penerbangan hari ini secara berurutan.
                    </p>
                </div>

                {{-- Action: Download PDF Button (Daily Flight Movements Report) --}}
                <div class="flex items-center gap-2">
                    <a href="{{ $downloadDailyPdfUrl ?? route('schedule.report.daily-movements', array_merge(['upload' => $upload->id], request()->query())) }}"
                       class="btn-aviation-primary px-4 py-2 rounded-xl text-xs font-bold inline-flex items-center gap-1.5 shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download PDF
                    </a>
                </div>
            </div>

            {{-- Active Hour Filter Notification Banner --}}
            <div x-show="selectedHour !== null" 
                 x-transition
                 class="p-3.5 rounded-2xl bg-aviation-50/90 dark:bg-aviation-950/80 border border-aviation-200 dark:border-aviation-800 flex items-center justify-between gap-3 shadow-2xs"
                 style="display: none;">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-aviation-600 animate-ping"></span>
                    <span class="text-xs text-aviation-900 dark:text-aviation-200 font-semibold font-mono">
                        Showing flights for <strong class="text-aviation-700 dark:text-aviation-300" x-text="String(selectedHour).padStart(2, '0') + ':00 – ' + String(selectedHour).padStart(2, '0') + ':59'"></strong> 
                        (<span class="font-bold" x-text="filteredMovements.length"></span> movements)
                    </span>
                </div>
                <button type="button" @click="clearHourFilter()"
                        class="px-3 py-1 rounded-xl text-xs font-bold bg-white dark:bg-navy-900 text-aviation-700 dark:text-aviation-300 hover:bg-aviation-100 dark:hover:bg-navy-800 border border-aviation-300 dark:border-aviation-700 transition cursor-pointer flex items-center gap-1">
                    <span>Clear Time Filter</span>
                    <span class="font-black">&times;</span>
                </button>
            </div>

            {{-- Interactive Filters Bar: Tabs & Search --}}
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                
                {{-- Tabs: Semua / Arrivals / Departures --}}
                <div class="inline-flex p-1 bg-slate-100 dark:bg-navy-950 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-semibold">
                    <button type="button" 
                            @click="movementFilter = 'all'"
                            :class="movementFilter === 'all' ? 'bg-white dark:bg-navy-800 text-slate-900 dark:text-white shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-4 py-2 rounded-lg transition cursor-pointer">
                        Semua (<span x-text="movements.length"></span>)
                    </button>
                    <button type="button" 
                            @click="movementFilter = 'arrivals'"
                            :class="movementFilter === 'arrivals' ? 'bg-white dark:bg-navy-800 text-arrival-600 dark:text-arrival-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-4 py-2 rounded-lg transition flex items-center gap-1.5 cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-arrival-500"></span>
                        Arrivals (<span x-text="arrivalsCount"></span>)
                    </button>
                    <button type="button" 
                            @click="movementFilter = 'departures'"
                            :class="movementFilter === 'departures' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-4 py-2 rounded-lg transition flex items-center gap-1.5 cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-aviation-600"></span>
                        Departures (<span x-text="departuresCount"></span>)
                    </button>
                </div>

                {{-- Search Flight Input --}}
                <div class="relative w-full md:w-80">
                    <input type="text" 
                           x-model="movementSearch" 
                           placeholder="Search flight no, airline, route, aircraft..." 
                           class="filter-select w-full pl-9 pr-8 text-xs font-mono">
                    <div class="absolute left-3 top-2.5 text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <button x-show="movementSearch !== ''" 
                            @click="movementSearch = ''" 
                            type="button" 
                            class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 cursor-pointer">
                        &times;
                    </button>
                </div>

            </div>

            {{-- Flight Movements Table --}}
            <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-navy-950 text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 font-bold uppercase text-[11px]">
                            <th class="py-3 px-4 w-28">
                                <span class="cursor-help" title="Waktu pergerakan penerbangan (STA untuk kedatangan, STD untuk keberangkatan)">
                                    WAKTU (<span x-text="displayTimezoneLabel"></span>) ⓘ
                                </span>
                            </th>
                            <th class="py-3 px-3 w-32">FLIGHT</th>
                            <th class="py-3 px-3 w-48">ROUTE</th>
                            <th class="py-3 px-3 w-48">MASKAPAI</th>
                            <th class="py-3 px-3 w-28">TIPE PESAWAT</th>
                            <th class="py-3 px-3 text-center w-28">JENIS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-navy-900">
                        <template x-for="f in filteredMovements" :key="f.id">
                            <tr @click="openFlightDrawer(f)"
                                class="hover:bg-slate-50/80 dark:hover:bg-navy-800/50 transition cursor-pointer group">
                                
                                {{-- Waktu (STA / STD) --}}
                                <td class="py-3 px-4 font-mono align-top whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-sm font-black text-slate-900 dark:text-white" x-text="flightTime(f)"></span>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase font-mono tracking-wider"
                                              :class="f.direction === 'arrival' ? 'bg-arrival-50 text-arrival-700 dark:bg-arrival-950 dark:text-arrival-300 border border-arrival-200 dark:border-arrival-800' : 'bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800'"
                                              :title="f.direction === 'arrival' ? 'STA: Scheduled Time of Arrival (Jadwal kedatangan pesawat)' : 'STD: Scheduled Time of Departure (Jadwal keberangkatan pesawat)'"
                                              x-text="f.time_type">
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 font-sans" x-text="f.direction === 'arrival' ? 'Kedatangan' : 'Keberangkatan'"></div>
                                </td>

                                {{-- Flight Number --}}
                                <td class="py-3 px-3 font-mono align-top whitespace-nowrap">
                                    <div class="font-black text-sm text-aviation-600 dark:text-aviation-400 group-hover:underline flex items-center gap-1" x-text="f.flight_number"></div>
                                    <template x-if="f.pairing && f.pairing.is_ron">
                                        <div class="text-[9.5px] font-bold text-purple-600 dark:text-purple-400 flex items-center gap-0.5 mt-0.5">
                                            <span>🌙</span> <span>RON</span>
                                        </div>
                                    </template>
                                    <template x-if="f.pairing && f.pairing.is_paired">
                                        <div class="text-[9.5px] font-bold text-aviation-600 dark:text-aviation-400 flex items-center gap-0.5 mt-0.5"
                                             x-text="'→ ' + (f.direction === 'arrival' ? f.pairing.paired_flight_number : f.pairing.paired_flight_number)"></div>
                                    </template>
                                </td>

                                {{-- Route --}}
                                <td class="py-3 px-3 font-mono align-top">
                                    <div class="font-bold text-slate-800 dark:text-slate-200 text-xs" x-text="f.route"></div>
                                    <div class="text-[10px] text-slate-400 truncate max-w-[170px]" x-text="f.origin_name + ' → ' + f.destination_name"></div>
                                </td>

                                {{-- Maskapai --}}
                                <td class="py-3 px-3 align-top">
                                    <div class="font-bold text-slate-800 dark:text-slate-200 text-xs truncate max-w-[170px]" x-text="f.airline_name"></div>
                                    <div class="text-[10px] text-slate-400 font-mono" x-text="'DOS: ' + f.operating_days"></div>
                                </td>

                                {{-- Tipe Pesawat --}}
                                <td class="py-3 px-3 font-mono align-top">
                                    <div class="font-bold text-slate-700 dark:text-slate-300 text-xs" x-text="f.aircraft_type"></div>
                                    <div class="text-[10px] text-slate-400 uppercase" x-text="f.traffic_badge"></div>
                                </td>

                                {{-- Jenis (Arrival / Departure) --}}
                                <td class="py-3 px-3 text-center align-top whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider"
                                          :class="f.direction === 'arrival' ? 'bg-arrival-50 text-arrival-700 dark:bg-arrival-950 dark:text-arrival-300 border border-arrival-200 dark:border-arrival-800' : 'bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800'">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="f.direction === 'arrival' ? 'bg-arrival-500' : 'bg-aviation-600'"></span>
                                        <span x-text="f.direction_label"></span>
                                    </span>
                                </td>

                            </tr>
                        </template>

                        {{-- Empty State Row --}}
                        <tr x-show="filteredMovements.length === 0" style="display: none;">
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-navy-950 flex items-center justify-center mx-auto mb-3 text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <div class="font-bold text-slate-700 dark:text-slate-300 text-sm">Tidak ada pergerakan penerbangan ditemukan</div>
                                <div class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau reset filter jam.</div>
                                <button type="button" @click="clearHourFilter(); movementFilter='all'; movementSearch=''"
                                        class="mt-3.5 px-4 py-1.5 rounded-xl bg-aviation-50 dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 text-xs font-bold border border-aviation-200 dark:border-aviation-700 hover:bg-aviation-100 transition cursor-pointer">
                                    Reset Filter
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Table Footer Summary --}}
            <div class="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
                <div>
                    Menampilkan <span class="font-bold text-aviation-600 dark:text-aviation-400 font-mono" x-text="filteredMovements.length"></span> dari <span class="font-mono font-bold" x-text="movements.length"></span> pergerakan penerbangan
                </div>
                <div class="text-[11px] text-slate-400 italic">
                    Klik baris untuk membuka detail Flight Intelligence &amp; Pairing
                </div>
            </div>

        </div>

        {{-- ── 5. FILTERED REPORT PREVIEWS & DOCUMENT GENERATION ───────────────────────────────── --}}
        <div class="glass-card p-6 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <svg class="w-5 h-5 text-aviation-600 dark:text-aviation-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <h3 class="font-bold text-slate-900 dark:text-white text-base uppercase tracking-wider">FILTERED REPORT PREVIEWS &amp; DOCUMENT GENERATION</h3>
                    </div>
                    <p class="text-xs text-slate-500">
                        Export high-fidelity operational reports inheriting active slot query parameters.
                    </p>
                </div>
                <a href="{{ $downloadCombinedUrl ?? route('schedule.report.download', array_merge(['upload' => $upload->id], request()->query())) }}"
                   class="btn-aviation-primary flex-shrink-0 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Combined PDF
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Preview Combined Report --}}
                <a href="{{ $previewCombinedUrl ?? route('schedule.preview.combined', array_merge(['upload' => $upload->id], request()->query())) }}" target="_blank"
                   class="p-4 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 hover:border-emerald-400 transition flex items-center justify-between group">
                    <div>
                        <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider mb-0.5">Preview Combined</div>
                        <div class="text-sm font-bold text-slate-800 dark:text-white group-hover:text-emerald-600">Combined Schedule Report</div>
                        <div class="text-xs text-slate-400 mt-1 font-mono">TIME + DOS Consolidated View</div>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 group-hover:text-emerald-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>

                {{-- Preview TIME Report --}}
                <a href="{{ $timePreviewUrl ?? route('schedule.preview.time', array_merge(['upload' => $upload->id], request()->query())) }}" target="_blank"
                   class="p-4 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 hover:border-aviation-400 transition flex items-center justify-between group">
                    <div>
                        <div class="text-xs font-bold text-aviation-600 dark:text-aviation-400 uppercase tracking-wider mb-0.5">Preview TIME Report</div>
                        <div class="text-sm font-bold text-slate-800 dark:text-white group-hover:text-aviation-600">TIME Flight Schedule</div>
                        <div class="text-xs text-slate-400 mt-1 font-mono">Chronological time windows</div>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 group-hover:text-aviation-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>

                {{-- Preview DOS Report --}}
                <a href="{{ $dosPreviewUrl ?? route('schedule.preview.dos', array_merge(['upload' => $upload->id], request()->query())) }}" target="_blank"
                   class="p-4 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 hover:border-amber-400 transition flex items-center justify-between group">
                    <div>
                        <div class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-0.5">Preview DOS Report</div>
                        <div class="text-sm font-bold text-slate-800 dark:text-white group-hover:text-amber-600">Daily Operating Service (DOS)</div>
                        <div class="text-xs text-slate-400 mt-1 font-mono">Day-of-service sequence overview</div>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 group-hover:text-amber-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            </div>
        </div>

        {{-- ── 6. OPTIONAL DEVELOPER DEBUGGER PANEL ───────────────────────────── --}}
        <div class="flex items-center justify-between text-xs text-slate-400 px-2">
            <span class="font-mono text-[11px]">SlotWaves Operations Engine &bull; Station {{ $airportCode }}</span>
            <button type="button" @click="showDebugPanel = !showDebugPanel"
                    class="text-[11px] font-mono text-slate-500 hover:text-aviation-600 dark:hover:text-aviation-400 transition cursor-pointer">
                <span x-text="showDebugPanel ? '▲ Hide Developer Debugger' : '▼ Developer & Pairing Inspector'"></span>
            </button>
        </div>

        <div x-show="showDebugPanel" x-transition class="glass-card p-5 space-y-4 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-aviation-500"></span>
                    <h3 class="font-mono font-bold text-slate-900 dark:text-aviation-300 text-xs uppercase">Developer &amp; Pairing Debugger</h3>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="debugTab = 'occupancy'"
                            :class="debugTab === 'occupancy' ? 'bg-aviation-600 text-white font-bold' : 'bg-slate-100 dark:bg-navy-950 text-slate-700 dark:text-slate-300'"
                            class="px-3 py-1 rounded-lg text-xs transition cursor-pointer">
                        Occupancy Inspector
                    </button>
                    <button type="button" @click="debugTab = 'pairing'"
                            :class="debugTab === 'pairing' ? 'bg-aviation-600 text-white font-bold' : 'bg-slate-100 dark:bg-navy-950 text-slate-700 dark:text-slate-300'"
                            class="px-3 py-1 rounded-lg text-xs transition cursor-pointer">
                        Pairing Log
                    </button>
                </div>
            </div>

            {{-- Tab 1: Hourly Occupancy Inspector --}}
            <div x-show="debugTab === 'occupancy'" class="space-y-4">
                <div class="flex items-center gap-3">
                    <label class="text-xs font-mono text-slate-500">Select Hour Window:</label>
                    <select x-model.number="debugHour" class="filter-select font-mono w-44 text-xs">
                        @foreach(range(0, 23) as $h)
                            <option value="{{ $h }}">{{ sprintf('%02d:00–%02d:59', $h, $h) }}</option>
                        @endforeach
                    </select>
                </div>

                @foreach($capacityStats['hourly'] as $h => $hData)
                    <div x-show="debugHour === {{ $h }}" class="space-y-3 bg-white dark:bg-navy-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                        <div class="flex flex-wrap items-center justify-between font-mono text-xs border-b border-slate-100 dark:border-slate-800 pb-2">
                            <div>TIME: <span class="font-bold text-slate-900 dark:text-white">{{ $hData['label'] }}</span></div>
                            <div>NAC: <span class="text-aviation-600 dark:text-aviation-400 font-bold">{{ $hData['nac'] }}</span></div>
                            <div>OCCUPIED: <span class="text-amber-600 dark:text-amber-400 font-bold">{{ $hData['occupied'] }}</span></div>
                            <div>REMAINING: <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $hData['remaining'] }}</span></div>
                            <div>STATUS: <span class="text-purple-600 dark:text-purple-300 font-bold">{{ $hData['status'] }}</span></div>
                        </div>

                        <div class="text-xs font-mono text-slate-500 font-bold uppercase">Aircraft Occupying Apron at {{ sprintf('%02d:00', $h) }}:</div>
                        @if(!empty($hData['occupied_aircraft']))
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs font-mono">
                                @foreach($hData['occupied_aircraft'] as $idx => $occ)
                                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-white">{{ $idx + 1 }}. {{ $occ['flight_number'] }} ({{ $occ['aircraft_type'] }})</div>
                                            <div class="text-[10px] text-slate-500">{{ $occ['status_text'] }}</div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $occ['rotation_status'] === 'PAIRED' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                            {{ $occ['rotation_status'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-xs italic text-slate-400">No aircraft occupying capacity during this hour window.</div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Tab 2: Pairing Decision Log --}}
            <div x-show="debugTab === 'pairing'" class="space-y-3">
                <div class="max-h-80 overflow-y-auto space-y-2 font-mono text-xs custom-scrollbar">
                    @forelse($capacityStats['decision_log'] as $logItem)
                        <div class="p-3 rounded-xl bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-amber-700 dark:text-amber-300">Arrival: {{ $logItem['arrival_flight'] }} (STA {{ $logItem['sta'] }}, DOS {{ $logItem['dos'] }})</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ str_contains($logItem['result'], 'PAIRED') ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' }}">
                                    {{ $logItem['result'] }}
                                </span>
                            </div>
                            @if(!empty($logItem['rotation']))
                                <div class="text-slate-700 dark:text-slate-300 text-[11px]">Rotation: <span class="font-bold text-aviation-600 dark:text-aviation-400">{{ $logItem['rotation'] }}</span> (Turnaround: {{ $logItem['turnaround'] }})</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-slate-400 italic">No decision logs generated.</div>
                    @endforelse
                </div>
            </div>
        </div>

    </main>

    {{-- ══ FLIGHT INTELLIGENCE DETAIL DRAWER / BOTTOM SHEET ══════════════════ --}}
    <div x-show="drawerOpen" x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex justify-end"
         style="display: none;">
        
        <div @click.away="closeFlightDrawer()"
             x-show="drawerOpen"
             x-transition:enter="transition ease-out duration-250 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="w-full max-w-md bg-white dark:bg-navy-900 border-l border-slate-200 dark:border-slate-800 h-full p-6 shadow-2xl flex flex-col justify-between overflow-y-auto">
            
            <div>
                {{-- Drawer Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400 font-mono font-black text-lg shadow-xs">
                            ✈
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Flight Intelligence</div>
                            <h3 class="text-xl font-black text-slate-900 dark:text-white font-mono" x-text="activeFlight ? activeFlight.flight_number : ''"></h3>
                        </div>
                    </div>
                    <button type="button" @click="closeFlightDrawer()"
                            class="p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer">
                        &times;
                    </button>
                </div>

                {{-- Drawer Body --}}
                <template x-if="activeFlight">
                    <div class="space-y-4 text-xs">
                        
                        {{-- Airline, Category & Route Card --}}
                        <div class="p-4.5 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-800 dark:text-slate-100 font-black uppercase text-xs font-mono tracking-wider" x-text="activeFlight.airline_name || activeFlight.airline_code"></span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800"
                                      x-text="activeFlight.category_badge || activeFlight.traffic_badge"></span>
                            </div>
                            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white flex items-center gap-2">
                                <span x-text="activeFlight.origin || '—'"></span>
                                <span class="text-aviation-600">&rarr;</span>
                                <span x-text="activeFlight.destination || '—'"></span>
                            </div>
                            <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                <span x-text="activeFlight.origin_name || activeFlight.origin"></span> &rarr; <span x-text="activeFlight.destination_name || activeFlight.destination"></span>
                            </div>
                        </div>

                        {{-- ── ANIMATED AIRCRAFT PAIRING & ROTATION VISUALIZER ── --}}
                        <div class="p-4.5 rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100/60 dark:from-navy-950 dark:to-navy-900 border border-slate-200 dark:border-slate-800 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-aviation-600 dark:text-aviation-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    AIRCRAFT PAIRING &amp; ROTATION
                                </span>
                                <template x-if="activeFlight.pairing">
                                    <span class="px-2 py-0.5 rounded text-[9.5px] font-black uppercase font-mono tracking-wider"
                                          :class="activeFlight.pairing.is_ron ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800' : (activeFlight.pairing.is_paired ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 text-slate-600 dark:bg-navy-900 dark:text-slate-400 border border-slate-200 dark:border-slate-800')"
                                          x-text="activeFlight.pairing.is_ron ? 'RON (OVERNIGHT)' : (activeFlight.pairing.is_paired ? 'PAIRED ROTATION' : (activeFlight.pairing.is_overnight_dep ? 'OVERNIGHT DEPARTURE' : 'UNPAIRED'))">
                                    </span>
                                </template>
                            </div>

                            {{-- Case 1: Paired Turnaround Rotation --}}
                            <template x-if="activeFlight.pairing && activeFlight.pairing.is_paired">
                                <div class="space-y-2 pt-1">
                                    {{-- Flow cards --}}
                                    <div class="grid grid-cols-1 gap-2">
                                        {{-- Inbound Arrival --}}
                                        <div class="p-3 rounded-xl bg-arrival-50/80 dark:bg-navy-900/90 border border-arrival-200 dark:border-arrival-900/60 flex items-center justify-between">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-arrival-500 text-white flex items-center justify-center font-bold text-xs">
                                                    🛬
                                                </div>
                                                <div>
                                                    <div class="text-[9px] font-bold uppercase tracking-wider text-arrival-700 dark:text-arrival-300 font-mono">
                                                        Inbound Arrival (STA)
                                                    </div>
                                                    <div class="font-mono font-black text-xs text-slate-900 dark:text-white"
                                                         x-text="(activeFlight.direction === 'arrival' ? activeFlight.flight_number : activeFlight.pairing.paired_flight_number) + ' · ' + (activeFlight.direction === 'arrival' ? activeFlight.scheduled_time : activeFlight.pairing.paired_time)"></div>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-white dark:bg-navy-950 text-arrival-700 dark:text-arrival-300 border border-arrival-200 dark:border-arrival-800">STA</span>
                                        </div>

                                        {{-- Animated Turnaround Connector --}}
                                        <div class="flex items-center justify-center gap-2 py-0.5 relative">
                                            <div class="h-0.5 flex-1 bg-gradient-to-r from-arrival-400 via-aviation-500 to-aviation-600"></div>
                                            <div class="px-2.5 py-1 rounded-full bg-slate-900 text-white text-[10px] font-mono font-bold flex items-center gap-1.5 shadow-xs">
                                                <span>⏱ TURNAROUND</span>
                                                <template x-if="activeFlight.pairing.turnaround_mins">
                                                    <span class="text-amber-400 font-black" x-text="'(' + activeFlight.pairing.turnaround_mins + 'm)'"></span>
                                                </template>
                                                <span class="text-aviation-400 animate-bounce">↓</span>
                                            </div>
                                            <div class="h-0.5 flex-1 bg-gradient-to-r from-arrival-400 via-aviation-500 to-aviation-600"></div>
                                        </div>

                                        {{-- Outbound Departure --}}
                                        <div class="p-3 rounded-xl bg-aviation-50/80 dark:bg-navy-900/90 border border-aviation-200 dark:border-aviation-900/60 flex items-center justify-between">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-aviation-600 text-white flex items-center justify-center font-bold text-xs">
                                                    🛫
                                                </div>
                                                <div>
                                                    <div class="text-[9px] font-bold uppercase tracking-wider text-aviation-700 dark:text-aviation-300 font-mono">
                                                        Outbound Departure (STD)
                                                    </div>
                                                    <div class="font-mono font-black text-xs text-slate-900 dark:text-white"
                                                         x-text="(activeFlight.direction === 'departure' ? activeFlight.flight_number : activeFlight.pairing.paired_flight_number) + ' · ' + (activeFlight.direction === 'departure' ? activeFlight.scheduled_time : activeFlight.pairing.paired_time)"></div>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-white dark:bg-navy-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">STD</span>
                                        </div>
                                    </div>

                                    {{-- Summary strip --}}
                                    <div class="p-2 rounded-xl bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 text-center font-mono font-bold text-xs text-aviation-700 dark:text-aviation-300"
                                         x-text="activeFlight.pairing.summary_text">
                                    </div>
                                </div>
                            </template>

                            {{-- Case 2: RON (Remain Over Night) --}}
                            <template x-if="activeFlight.pairing && activeFlight.pairing.is_ron">
                                <div class="space-y-2 pt-1">
                                    <div class="grid grid-cols-1 gap-2">
                                        <div class="p-3 rounded-xl bg-arrival-50/80 dark:bg-navy-900/90 border border-arrival-200 dark:border-arrival-900/60 flex items-center justify-between">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-arrival-500 text-white flex items-center justify-center font-bold text-xs">
                                                    🛬
                                                </div>
                                                <div>
                                                    <div class="text-[9px] font-bold uppercase tracking-wider text-arrival-700 dark:text-arrival-300 font-mono">
                                                        Inbound Arrival (STA)
                                                    </div>
                                                    <div class="font-mono font-black text-xs text-slate-900 dark:text-white"
                                                         x-text="activeFlight.flight_number + ' · ' + activeFlight.scheduled_time"></div>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-white dark:bg-navy-950 text-arrival-700 dark:text-arrival-300 border border-arrival-200 dark:border-arrival-800">STA</span>
                                        </div>

                                        <div class="flex items-center justify-center py-0.5">
                                            <span class="text-purple-600 dark:text-purple-400 text-base font-black animate-bounce">↓</span>
                                        </div>

                                        <div class="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800 flex items-center justify-between">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-purple-600 text-white flex items-center justify-center font-bold text-xs">
                                                    🌙
                                                </div>
                                                <div>
                                                    <div class="text-[9px] font-bold uppercase tracking-wider text-purple-700 dark:text-purple-300 font-mono">
                                                        RON &mdash; Remain Over Night
                                                    </div>
                                                    <div class="font-bold text-xs text-purple-900 dark:text-purple-200">
                                                        Menginap di apron bandara
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-black bg-purple-600 text-white shadow-xs">RON</span>
                                        </div>
                                    </div>

                                    <div class="p-2.5 rounded-xl bg-purple-50/50 dark:bg-purple-950/30 border border-purple-200/80 dark:border-purple-800/60 text-[11px] text-purple-900 dark:text-purple-200 space-y-0.5">
                                        <div class="font-mono font-bold text-xs" x-text="activeFlight.flight_number + ' (' + activeFlight.scheduled_time + ') → RON — Remain Over Night'"></div>
                                        <div class="text-[10px] text-purple-700 dark:text-purple-300" x-text="activeFlight.pairing.ron_explanation"></div>
                                    </div>
                                </div>
                            </template>

                            {{-- Case 3: Overnight Departure --}}
                            <template x-if="activeFlight.pairing && activeFlight.pairing.is_overnight_dep">
                                <div class="space-y-2 pt-1">
                                    <div class="grid grid-cols-1 gap-2">
                                        <div class="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800 flex items-center justify-between">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-purple-600 text-white flex items-center justify-center font-bold text-xs">
                                                    🌙
                                                </div>
                                                <div>
                                                    <div class="text-[9px] font-bold uppercase tracking-wider text-purple-700 dark:text-purple-300 font-mono">
                                                        Overnight Aircraft (From 00:00)
                                                    </div>
                                                    <div class="font-bold text-xs text-purple-900 dark:text-purple-200">
                                                        Berada di bandara sejak awal hari operasi
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-black bg-purple-600 text-white shadow-xs">RON</span>
                                        </div>

                                        <div class="flex items-center justify-center py-0.5">
                                            <span class="text-aviation-600 dark:text-aviation-400 text-base font-black animate-bounce">↓</span>
                                        </div>

                                        <div class="p-3 rounded-xl bg-aviation-50/80 dark:bg-navy-900/90 border border-aviation-200 dark:border-aviation-900/60 flex items-center justify-between">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-aviation-600 text-white flex items-center justify-center font-bold text-xs">
                                                    🛫
                                                </div>
                                                <div>
                                                    <div class="text-[9px] font-bold uppercase tracking-wider text-aviation-700 dark:text-aviation-300 font-mono">
                                                        Outbound Departure (STD)
                                                    </div>
                                                    <div class="font-mono font-black text-xs text-slate-900 dark:text-white"
                                                         x-text="activeFlight.flight_number + ' · ' + activeFlight.scheduled_time"></div>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-white dark:bg-navy-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">STD</span>
                                        </div>
                                    </div>

                                    <div class="p-2.5 rounded-xl bg-aviation-50/50 dark:bg-aviation-950/30 border border-aviation-200/80 dark:border-aviation-800/60 text-[11px] text-aviation-900 dark:text-aviation-200 space-y-0.5">
                                        <div class="font-mono font-bold text-xs" x-text="'Remain Over Night (RON) → ' + activeFlight.flight_number + ' (' + activeFlight.scheduled_time + ')'"></div>
                                        <div class="text-[10px] text-aviation-700 dark:text-aviation-300" x-text="activeFlight.pairing.ron_explanation"></div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Scheduled Time --}}
                        <div class="p-4.5 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1.5">
                            <div class="text-[10px] font-bold uppercase text-slate-400 font-mono">Scheduled Time</div>
                            <div class="text-lg font-black text-slate-900 dark:text-white font-mono" x-text="(activeFlight.time_type ? activeFlight.time_type + ' ' : '') + activeFlight.scheduled_time"></div>
                        </div>

                        {{-- Aircraft Type --}}
                        <div class="p-4.5 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1.5">
                            <div class="text-[10px] font-bold uppercase text-slate-400 font-mono">Aircraft Type</div>
                            <div class="text-lg font-black text-amber-600 dark:text-amber-400 font-mono" x-text="activeFlight.aircraft_type || '—'"></div>
                        </div>

                        {{-- Airport Master Data --}}
                        <div class="p-4.5 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-2.5">
                            <div class="text-[10px] font-bold uppercase text-slate-400 font-mono">Airport Master Data</div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-[10px] text-slate-400 font-mono">Management</div>
                                    <div class="font-bold text-slate-800 dark:text-slate-200 text-xs" x-text="activeFlight.management || 'PT. Angkasa Pura Indonesia'"></div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-slate-400 font-mono">Region</div>
                                    <div class="font-bold text-aviation-600 dark:text-aviation-400 text-xs font-mono" x-text="activeFlight.region || 'Region 1'"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Operating Days (DOS) --}}
                        <div class="p-4.5 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-1.5">
                            <div class="text-[10px] font-bold uppercase text-slate-400 font-mono">Operating Days (DOS)</div>
                            <div class="font-mono font-black text-lg text-slate-900 dark:text-white" x-text="activeFlight.operating_days || '1234567'"></div>
                            <div class="text-xs text-slate-500 font-mono">
                                <template x-if="activeFlight.operating_days === '1234567'">
                                    <span>Operates Daily (All 7 Days)</span>
                                </template>
                                <template x-if="activeFlight.operating_days !== '1234567'">
                                    <span>Operates on scheduled operating day sequence</span>
                                </template>
                            </div>
                        </div>

                    </div>
                </template>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button type="button" @click="closeFlightDrawer()"
                        class="px-5 py-2.5 bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition cursor-pointer">
                    Close Flight Detail
                </button>
            </div>

        </div>
    </div>

    {{-- ══ OPERATIONAL HOURS CONFIGURATION MODAL ════════════════════════════ --}}
    <div x-show="opsModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        
        <div @click.away="closeOpsModal()"
             x-show="opsModalOpen"
             x-transition:enter="transition ease-out duration-200 transform"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150 transform"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="w-full max-w-md bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-2xl space-y-5">
            
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white uppercase tracking-wide">OPERATING HOURS (OPS)</h3>
                        <p class="text-xs text-slate-400">Atur jam operasional analisis bandara</p>
                    </div>
                </div>
                <button type="button" @click="closeOpsModal()"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer text-lg leading-none">
                    &times;
                </button>
            </div>

            {{-- Modal Body: Inputs --}}
            <div class="space-y-4">
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                    <strong class="text-slate-800 dark:text-white">Parameter Analisis:</strong> Mengubah jam operasional akan langsung menghitung ulang pergerakan, kapasitas operasional, utilisasi, dan peak window tanpa memodifikasi sumber data penerbangan.
                </div>

                <div class="grid grid-cols-2 gap-4 font-mono">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1.5">
                            Start Time
                        </label>
                        <input type="time" x-model="modalOpsStart" 
                               class="filter-select w-full text-center font-bold text-sm bg-white dark:bg-navy-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase mb-1.5">
                            End Time
                        </label>
                        <input type="time" x-model="modalOpsEnd" 
                               class="filter-select w-full text-center font-bold text-sm bg-white dark:bg-navy-900">
                    </div>
                </div>

                {{-- Inline Error Message --}}
                <template x-if="modalError">
                    <div class="p-2.5 rounded-xl bg-red-50 dark:bg-red-950/80 border border-red-200 dark:border-red-800 text-xs text-red-600 dark:text-red-300 font-semibold flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-text="modalError"></span>
                    </div>
                </template>
            </div>

            {{-- Modal Footer Actions --}}
            <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="resetOpsHours()"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 transition cursor-pointer">
                    Reset to Default
                </button>

                <div class="flex items-center gap-2">
                    <button type="button" @click="closeOpsModal()"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" @click="applyOpsHours()"
                            class="btn-aviation-primary px-5 py-2 rounded-xl text-xs font-bold transition shadow-md flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Apply
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- ══ AIRCRAFT CAPACITY CONFIGURATION MODAL ════════════════════════════ --}}
    <div x-show="capacityModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        
        <div @click.away="closeCapacityModal()"
             x-show="capacityModalOpen"
             x-transition:enter="transition ease-out duration-200 transform"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150 transform"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="w-full max-w-md bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-2xl space-y-5">
            
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400 font-bold shadow-xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white uppercase tracking-wide">AIRCRAFT CAPACITY</h3>
                        <p class="text-xs text-slate-400">{{ $airportName }} ({{ $airportCode }})</p>
                    </div>
                </div>
                <button type="button" @click="closeCapacityModal()"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer text-lg leading-none">
                    &times;
                </button>
            </div>

            {{-- Modal Body: Inputs & Notes --}}
            <div class="space-y-4">
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                    <strong class="text-slate-800 dark:text-white">Kapasitas Maksimal Penumpang:</strong> Mengatur batas jumlah pesawat penumpang yang dapat dilayani secara simultan per jam di apron.
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase">
                        Maximum Aircraft Capacity (Apron Limit)
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="number" min="1" max="100" x-model.number="modalNac" 
                               class="filter-select w-32 text-center font-black text-base bg-white dark:bg-navy-900 font-mono">
                        <span class="text-xs text-slate-500 font-mono">Aircraft / Stand</span>
                    </div>
                </div>

                {{-- Cargo Exclusion Notice --}}
                <div class="p-3 rounded-2xl bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 text-xs text-emerald-800 dark:text-emerald-300 flex items-start gap-2.5">
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold text-base leading-none">✓</span>
                    <div>
                        <strong class="block font-bold">Cargo uses separate parking stand / capacity</strong>
                        <span class="text-[11px] text-emerald-700 dark:text-emerald-400 leading-tight">Pesawat kargo otomatis dikecualikan dari kalkulasi pemakaian kapasitas penumpang.</span>
                    </div>
                </div>

                {{-- Airport Timezone Info --}}
                <div class="p-3 rounded-2xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 text-xs flex items-center justify-between font-mono">
                    <span class="text-slate-500 dark:text-slate-400">Airport Timezone:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $airportTimezone ?? 'Asia/Jakarta' }} ({{ $timezoneAbbr ?? 'WIB' }})</span>
                </div>
            </div>

            {{-- Modal Footer Actions --}}
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="closeCapacityModal()"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer">
                    Cancel
                </button>
                <button type="button" @click="applyCapacity()"
                        class="btn-aviation-primary px-5 py-2 rounded-xl text-xs font-bold transition shadow-md flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Save Capacity
                </button>
            </div>

        </div>
    </div>

    {{-- ══ NOTIFICATION FEEDBACK TOAST ═══════════════════════════════════════ --}}
    <div x-show="opsToastOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="fixed bottom-6 right-6 z-50 p-4 rounded-2xl bg-slate-900 dark:bg-navy-900 text-white shadow-2xl border border-slate-700 flex items-center gap-3 text-xs max-w-sm"
         style="display: none;">
        <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="flex-1 font-medium leading-tight" x-text="opsToastMessage"></div>
        <button type="button" @click="opsToastOpen = false" class="text-slate-400 hover:text-white text-lg leading-none cursor-pointer">&times;</button>
    </div>

</div>
@endsection
