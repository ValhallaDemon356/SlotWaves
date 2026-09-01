@extends('layouts.app')

@section('title', 'SlotWaves — Flight Schedule & Operations: ' . ($airport ? $airport->name : 'BDO'))
@section('bodyClass', 'bg-surface dark:bg-navy-950 text-slate-800 dark:text-slate-100 min-h-screen transition-colors duration-150')

@push('head')
<script>
function dashboardState(initialDos, initialMovements, initialOpsStart, initialOpsEnd, initialNac, initialTimezone, initialOffset, initialTzAbbr, initialRotations) {
    return {
        theme: localStorage.getItem('slotwaves-theme') || 'light',
        mobileNavOpen: false,
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
        cargoSeparateParking: true,
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
        rotations: initialRotations || [],
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

            // Restore custom Aircraft Capacity (NAC) from localStorage if available
            try {
                const savedNac = localStorage.getItem('slotwaves_nac_{{ $upload->id }}');
                if (savedNac) {
                    const parsedNac = parseInt(savedNac, 10);
                    if (!isNaN(parsedNac) && parsedNac >= 1 && parsedNac <= 100) {
                        this.nacLimit = parsedNac;
                        this.modalNac = parsedNac;
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
            return parseInt((this.opsEndTime || '20:00').split(':')[0], 10);
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

        get inOpsOpcCount() {
            return (this.rotations || []).filter(r => !r.is_cargo && (r.rotation_status === 'UNPAIRED_ARR' || r.is_ron)).length;
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

                const totalMovements = arrs.length + deps.length;
                const cargoTotal = cargoArrs.length + cargoDeps.length;
                const isOps = this.isOpsHour(h);

                // 1. Calculate OPC in hour h (RON aircraft parked in this hour)
                let opcCount = 0;
                for (const rot of (this.rotations || [])) {
                    if (rot.is_cargo || (rot.passenger_units || 1) <= 0) {
                        continue; // Exclude cargo
                    }

                    let s = rot.start_minute;
                    let e = rot.end_minute;
                    const status = rot.rotation_status;
                    const units = rot.passenger_units || 1;

                    if (this.timezoneMode === 'UTC') {
                        const offsetMin = this.timezoneOffset || 420;
                        s = (s - offsetMin) % 1440;
                        if (s < 0) s += 1440;
                        e = (e - offsetMin) % 1440;
                        if (e < 0) e += 1440;
                    }

                    if (status === 'UNPAIRED_DEP') {
                        const stdH = Math.floor(e / 60);
                        if (h < stdH) {
                            opcCount += units;
                        }
                    } else if (status === 'UNPAIRED_ARR') {
                        const staH = Math.floor(s / 60);
                        if (h > staH) {
                            opcCount += units;
                        }
                    } else if (status === 'PAIRED' && e < s) {
                        const staH = Math.floor(s / 60);
                        const stdH = Math.floor(e / 60);
                        if (h > staH || h < stdH) {
                            opcCount += units;
                        }
                    }
                }

                // 2. REFACTORED STANDARD: Aircraft Demand = Arrivals + Departures + OPC
                // No cumulative occupancy from previous hours. No subtracting Departures from Arrivals.
                const aircraftDemand = arrs.length + deps.length + opcCount;
                const utilization = this.nacLimit > 0 ? Math.round((aircraftDemand / this.nacLimit) * 100) : 0;

                // Peak tracking based on Aircraft Demand in active ops window
                if (isOps && aircraftDemand > maxInOps) {
                    maxInOps = aircraftDemand;
                    peakHourStr = `${String(h).padStart(2, '0')}:00–${String(h).padStart(2, '0')}:59`;
                }

                let status = 'OFF HOURS';
                let statusLabel = 'Off Hours';
                let statusKey = 'off-hours';
                let statusColor = 'slate';
                let remaining = 0;
                let exceeded = 0;

                // Status rules:
                // Demand < NAC => AVAILABLE
                // Demand === NAC => FULL / MAX
                // Demand > NAC => OVER CAPACITY
                if (isOps) {
                    if (aircraftDemand < this.nacLimit) {
                        status = 'AVAILABLE';
                        statusLabel = 'Available';
                        statusKey = 'available';
                        statusColor = 'emerald'; // Green (#16A34A)
                        remaining = this.nacLimit - aircraftDemand;
                        exceeded = 0;
                    } else if (aircraftDemand === this.nacLimit) {
                        status = 'FULL / MAX';
                        statusLabel = 'Full / Max';
                        statusKey = 'full';
                        statusColor = 'amber'; // Yellow/Amber (#D97706)
                        remaining = 0;
                        exceeded = 0;
                    } else {
                        status = 'OVER CAPACITY';
                        statusLabel = 'Over Capacity';
                        statusKey = 'over-capacity';
                        statusColor = 'purple'; // Purple (#9333EA)
                        remaining = 0;
                        exceeded = aircraftDemand - this.nacLimit;
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
                    opcCount: opcCount,
                    aircraftDemand: aircraftDemand,
                    utilization: utilization,
                    passengerArrCount: passengerArrs.length,
                    passengerDepCount: passengerDeps.length,
                    passengerCount: aircraftDemand, // Kept for backwards compatibility
                    cargoArrCount: cargoArrs.length,
                    cargoDepCount: cargoDeps.length,
                    cargoCount: cargoTotal,
                    totalMovements: totalMovements,
                    total: totalMovements,
                    occupied: aircraftDemand,
                    demand: aircraftDemand,
                    isPeak: false,
                    status: status,
                    statusLabel: statusLabel,
                    statusKey: statusKey,
                    statusColor: statusColor,
                    remaining: remaining,
                    exceeded: exceeded,
                    flights: [...arrs, ...deps],
                };

                allList.push(item);
                if (isOps) {
                    opsList.push(item);
                }
            }

            // Mark the peak hour items
            if (maxInOps > 0) {
                for (const item of allList) {
                    if (item.isOps && item.aircraftDemand === maxInOps) {
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
            const maxVal = Math.max(...this.activeHourlyDistribution.map(d => d.total + (d.opcCount || 0)), 0);
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

            try {
                localStorage.setItem('slotwaves_nac_{{ $upload->id }}', String(this.nacLimit));
            } catch (e) {}

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
                // Direction / OPC filter
                if (this.movementFilter === 'arrivals' && f.direction !== 'arrival') {
                    return false;
                }
                if (this.movementFilter === 'departures' && f.direction !== 'departure') {
                    return false;
                }
                if (this.movementFilter === 'opc' && !f.is_ron) {
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

        get opcMovementsCount() {
            return this.movements.filter(f => f.is_ron).length;
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

    $peakDemand = $capacityStats['peak_demand'] ?? 0;
    $nacLimit = $capacityStats['nac'] ?? 6;
    $peakUtilization = $nacLimit > 0 ? min(100, round(($peakDemand / $nacLimit) * 100)) : 0;
    $maxChartScale = max(10, $peakDemand ?: 8, $nacLimit + 2);
@endphp

<div x-data="dashboardState('{{ is_array($dosValue) ? implode('', $dosValue) : $dosValue }}', {{ Js::from($flightMovements) }}, '{{ $stats['ops_start'] }}', '{{ $stats['ops_end'] }}', {{ $capacityStats['nac'] ?? 6 }}, '{{ $airportTimezone ?? 'Asia/Jakarta' }}', {{ $timezoneOffset ?? 420 }}, '{{ $timezoneAbbr ?? 'WIB' }}', {{ Js::from($normalizedRotations ?? []) }})" class="min-h-screen flex flex-col">

    {{-- ══ 1. COMPACT AOCC HEADER & NAVIGATION ════════════════════════════════ --}}
    <nav class="sticky top-0 z-40 w-full border-b border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-navy-900/95 backdrop-blur-md px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between shadow-xs">
        
        {{-- Left: Brand, Badge & Main Navigation Tabs --}}
        <div class="flex items-center gap-5">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 group">
                <div class="w-8 h-8 rounded-lg bg-aviation-600 flex items-center justify-center shadow-xs text-white group-hover:scale-105 transition">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                    </svg>
                </div>
                <div>
                    <span class="text-sm font-black tracking-tight text-slate-900 dark:text-white">SlotWaves</span>
                    <span class="hidden sm:inline-block text-[9.5px] font-bold uppercase tracking-wider ml-1 px-1.5 py-0.5 rounded-full bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">AOCC</span>
                </div>
            </a>

            {{-- Center Navigation Links (Desktop) --}}
            <div class="hidden lg:flex items-center gap-1 border-l border-slate-200 dark:border-slate-800 pl-4">
                <a href="#overview" class="px-3 py-1.5 rounded-lg text-xs font-bold text-aviation-600 dark:text-aviation-400 bg-aviation-50 dark:bg-aviation-950/60 border border-aviation-200 dark:border-aviation-800">
                    Dashboard
                </a>
                <a href="#activity-section" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition">
                    Flight Activity
                </a>
                <a href="#flight-list-section" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition">
                    Pergerakan Hari Ini
                </a>
                <a href="{{ route('timeline.show', $upload->id) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition flex items-center gap-1">
                    <span>24-Hour Timeline</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
                <a href="{{ route('master-data.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition">
                    Master Data
                </a>
                <a href="{{ route('upload.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-aviation-600 dark:hover:text-aviation-300 hover:bg-slate-100 dark:hover:bg-navy-800 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Import PDF</span>
                </a>
            </div>
        </div>

        {{-- Right Controls: Timezone, Airport Station, Capacity, Timeline View & Theme --}}
        <div class="flex items-center gap-2">
            
            {{-- TIMEZONE [ LOCAL | UTC ] Mode Selector --}}
            <div class="inline-flex p-0.5 bg-slate-100 dark:bg-navy-950 rounded-lg border border-slate-200 dark:border-slate-800 text-xs font-bold font-mono">
                <button type="button" @click="timezoneMode = 'LOCAL'"
                        :class="timezoneMode === 'LOCAL' ? 'bg-aviation-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-2 py-0.5 rounded-md transition text-[10.5px] cursor-pointer"
                        title="Display in Airport Local Time">
                    LOCAL (<span x-text="timezoneAbbr"></span>)
                </button>
                <button type="button" @click="timezoneMode = 'UTC'"
                        :class="timezoneMode === 'UTC' ? 'bg-aviation-600 text-white shadow-2xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        class="px-2 py-0.5 rounded-md transition text-[10.5px] cursor-pointer"
                        title="Convert Schedule to Coordinated Universal Time (UTC)">
                    UTC
                </button>
            </div>

            {{-- Airport Selector / Station Badge --}}
            <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-navy-800 border border-slate-200 dark:border-slate-700/70 text-xs font-mono">
                <span class="font-black text-aviation-600 dark:text-aviation-400">{{ $airportCode }}</span>
                <span class="text-slate-400">&bull;</span>
                <span class="text-slate-600 dark:text-slate-300 truncate max-w-[120px]">{{ $airportName }}</span>
            </div>

            {{-- Active Slot Status Badge --}}
            <div class="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-[11px] font-bold text-emerald-700 dark:text-emerald-300">
                <span class="radar-dot w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Active Slot</span>
            </div>

            {{-- Timeline Shortcut Button --}}
            <a href="{{ route('timeline.show', $upload->id) }}"
               class="btn-aviation-primary px-3 py-1.5 rounded-lg text-xs font-bold inline-flex items-center gap-1.5 shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Timeline View</span>
            </a>

            {{-- Theme toggle --}}
            <button @click="toggleTheme()" type="button"
                    class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-navy-800 border border-slate-200 dark:border-slate-700/60 hover:bg-slate-200 dark:hover:bg-navy-700 transition cursor-pointer"
                    aria-label="Toggle theme">
                <template x-if="theme === 'dark'">
                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </template>
                <template x-if="theme === 'light'">
                    <svg class="w-4 h-4 text-aviation-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                </template>
            </button>

            {{-- Mobile menu button --}}
            <button @click="mobileNavOpen = !mobileNavOpen" type="button" class="lg:hidden p-1.5 rounded-lg bg-slate-100 dark:bg-navy-800 text-slate-600 dark:text-slate-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </nav>

    {{-- Mobile Navigation Dropdown --}}
    <div x-show="mobileNavOpen" x-transition class="lg:hidden bg-white dark:bg-navy-900 border-b border-slate-200 dark:border-slate-800 px-4 py-3 space-y-2 text-xs font-semibold">
        <a href="#overview" @click="mobileNavOpen = false" class="block py-1 text-aviation-600 dark:text-aviation-400 font-bold">Dashboard Overview</a>
        <a href="#activity-section" @click="mobileNavOpen = false" class="block py-1 text-slate-700 dark:text-slate-200">Today's Flight Activity</a>
        <a href="#filter-section" @click="mobileNavOpen = false" class="block py-1 text-slate-700 dark:text-slate-200">Flight Schedule Filter</a>
        <a href="#flight-list-section" @click="mobileNavOpen = false" class="block py-1 text-slate-700 dark:text-slate-200">List Pergerakan Hari Ini</a>
        <a href="{{ route('timeline.show', $upload->id) }}" class="block py-1 text-aviation-600 dark:text-aviation-400 font-bold">24-Hour Timeline View</a>
        <a href="{{ route('master-data.index') }}" class="block py-1 text-slate-700 dark:text-slate-200">Master Reference Data</a>
        <a href="{{ route('upload.index') }}" class="block py-1 text-slate-700 dark:text-slate-200">Import Schedule PDF</a>
    </div>

    {{-- ══ 2. HERO BANNER: AIRPORT OPERATIONS & SCHEDULE OVERVIEW ═══════════════ --}}
    <div id="overview" class="bg-gradient-to-r from-slate-900 via-navy-900 to-aviation-950 text-white px-4 sm:px-6 lg:px-8 py-6 shadow-md border-b border-aviation-900/60">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-5">
            
            <div>
                <div class="flex items-center gap-2 text-aviation-300 text-[11px] font-bold uppercase tracking-widest mb-1">
                    <span>AIRPORT OPERATIONS CONTROL CENTER</span>
                    <span>&bull;</span>
                    <span>FLIGHT INTELLIGENCE</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex flex-wrap items-center gap-2.5">
                    <span>BANDAR UDARA {{ $airportName }}</span>
                    <span class="px-2.5 py-0.5 rounded-lg bg-aviation-600 text-white text-lg sm:text-xl font-mono shadow-xs">{{ $airportCode }}</span>
                </h1>
                
                <div class="mt-2.5 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-slate-300">
                    <span class="inline-flex items-center gap-1.5 text-slate-200">
                        <svg class="w-4 h-4 text-aviation-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="font-mono">{{ $upload->original_filename }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="px-2 py-0.5 rounded text-[10.5px] font-black uppercase tracking-wider bg-aviation-800 text-aviation-200 border border-aviation-600/50">
                            {{ ucfirst($upload->season ?? 'Summer') }} Season
                        </span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-slate-400 font-mono text-[11px]">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ \Carbon\Carbon::parse($upload->updated_at)->format('d F Y, H:i') }} WIB
                    </span>
                    @if($upload->valid_rows > 0)
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-950/80 text-emerald-300 border border-emerald-700/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span>Pipeline Validated ({{ $upload->valid_rows }} rows)</span>
                    </span>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="flex items-center gap-2.5 shrink-0">
                <a href="{{ route('timeline.show', $upload->id) }}"
                   class="btn-aviation-primary px-4 py-2 rounded-xl text-xs sm:text-sm font-bold inline-flex items-center gap-2 shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span>24-Hour Timeline</span>
                </a>
                <a :href="'{{ $downloadCombinedUrl }}' + '&ops_start=' + opsStartTime + '&ops_end=' + opsEndTime"
                   class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold bg-white/10 hover:bg-white/20 border border-white/15 text-white transition inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>PDF Export</span>
                </a>
            </div>

        </div>
    </div>

    {{-- ══ 3. MAIN DASHBOARD CONTAINER ════════════════════════════════════════ --}}
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6 flex-1">

        {{-- ── 3.1 DASHBOARD HERO / 6 SUMMARY KPI CARDS ─────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-aviation-600"></span>
                    <span>Flight Operations Summary</span>
                </div>
                <div class="text-xs font-mono text-slate-500">
                    Showing <span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="movements.length">{{ $stats['total'] }}</span> Validated Flights
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

                {{-- Card 1: TOTAL FLIGHTS --}}
                <div class="stat-card-modern p-4 flex flex-col justify-between h-[108px]">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wide">TOTAL FLIGHTS</span>
                        <div class="w-6 h-6 rounded-md bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-black font-mono text-slate-900 dark:text-white leading-tight" x-text="movements.length">{{ $stats['total'] }}</div>
                        <div class="text-[10.5px] text-slate-400 mt-0.5"><span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="inOpsTotalCount"></span> in OPS window</div>
                    </div>
                </div>

                {{-- Card 2: ARRIVALS --}}
                <div class="stat-card-modern p-4 flex flex-col justify-between h-[108px] border-l-4 border-l-orange-500">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-orange-600 dark:text-orange-400">ARRIVALS</span>
                        <div class="w-6 h-6 rounded-md bg-orange-50 dark:bg-orange-950 flex items-center justify-center text-orange-600 dark:text-orange-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-black font-mono text-orange-600 dark:text-orange-400 leading-tight" x-text="inOpsArrivalsCount">{{ $stats['arrivals'] }}</div>
                        <div class="text-[10.5px] text-slate-400 mt-0.5">Inbound in OPS window</div>
                    </div>
                </div>

                {{-- Card 3: DEPARTURES --}}
                <div class="stat-card-modern p-4 flex flex-col justify-between h-[108px] border-l-4 border-l-blue-600">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-blue-600 dark:text-blue-400">DEPARTURES</span>
                        <div class="w-6 h-6 rounded-md bg-blue-50 dark:bg-blue-950 flex items-center justify-center text-blue-600 dark:text-blue-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-black font-mono text-blue-600 dark:text-blue-400 leading-tight" x-text="inOpsDeparturesCount">{{ $stats['departures'] }}</div>
                        <div class="text-[10.5px] text-slate-400 mt-0.5">Outbound in OPS window</div>
                    </div>
                </div>

                {{-- Card 4: AIRCRAFT CAPACITY (Interactive with EDIT modal) --}}
                <div @click="openCapacityModal()"
                     class="stat-card-modern p-4 flex flex-col justify-between h-[108px] cursor-pointer hover:border-aviation-400 dark:hover:border-aviation-600 transition group relative">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-aviation-600 dark:text-aviation-400 flex items-center gap-1">
                            <span>AIRCRAFT CAPACITY</span>
                            <span class="text-[9px] group-hover:scale-110 transition">⚙</span>
                        </span>
                        <span class="text-[9.5px] font-mono font-bold px-1.5 py-0.2 rounded bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800">NAC</span>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl font-black font-mono text-slate-900 dark:text-white leading-tight flex items-baseline gap-1.5">
                            <span x-text="nacLimit">{{ $airportCapacity }}</span>
                            <span class="text-xs text-slate-400 font-normal">A/C Max</span>
                        </div>
                        <div class="flex items-center justify-between text-[10.5px] text-slate-400 mt-0.5 font-mono">
                            <span>EXCLUDES CARGO</span>
                            <span class="text-[10px] font-bold text-aviation-600 dark:text-aviation-400 underline decoration-dotted">EDIT ⚙</span>
                        </div>
                    </div>
                </div>

                {{-- Card 5: UTILIZATION & PEAK --}}
                <div class="stat-card-modern p-4 flex flex-col justify-between h-[108px]">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wide">UTILIZATION</span>
                        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded"
                              :class="peakUtilization >= 100 ? 'bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300' : (peakUtilization >= 80 ? 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300')"
                              x-text="peakUtilization + '%'">
                            {{ $peakUtilization }}%
                        </span>
                    </div>
                    <div>
                        <div class="text-lg sm:text-xl font-black font-mono text-slate-900 dark:text-white leading-tight">
                            <span x-text="peakStats.peakDemand">{{ $peakDemand }}</span> <span class="text-xs text-slate-400 font-normal">/ <span x-text="nacLimit"></span> Peak</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-navy-950 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300"
                                 :class="peakUtilization >= 100 ? 'bg-purple-600' : (peakUtilization >= 80 ? 'bg-amber-500' : 'bg-emerald-500')"
                                 :style="'width: ' + Math.min(100, peakUtilization) + '%'"></div>
                        </div>
                    </div>
                </div>

                {{-- Card 6: OPS HOURS (Interactive with EDIT modal) --}}
                <div @click="openOpsModal()"
                     class="stat-card-modern p-4 flex flex-col justify-between h-[108px] cursor-pointer hover:border-emerald-400 dark:hover:border-emerald-600 transition group relative">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <span>OPS HOURS</span>
                            <span class="text-[9px] group-hover:scale-110 transition">⚙</span>
                        </span>
                        <span class="radar-dot w-2 h-2 rounded-full bg-emerald-500"></span>
                    </div>
                    <div>
                        <div class="text-sm sm:text-base font-black font-mono text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <span x-text="opsStartTime">{{ $stats['ops_start'] }}</span>
                            <span>&rarr;</span>
                            <span x-text="opsEndTime">{{ $stats['ops_end'] }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[10.5px] text-slate-400 mt-0.5 font-mono">
                            <span x-text="activeHoursCount + ' ACTIVE HOURS'">{{ $capacityStats['active_hours_count'] }} ACTIVE HOURS</span>
                            <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 underline decoration-dotted">EDIT</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── 3.2 TODAY'S FLIGHT ACTIVITY SECTION ────────────────────────── --}}
        <div id="activity-section" class="glass-card p-5 sm:p-6 shadow-xs space-y-4">
            
            {{-- Section Header & Segmented Controls --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3.5">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400 shadow-2xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                            </svg>
                        </div>
                        <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                            TODAY'S FLIGHT ACTIVITY
                        </h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Hourly flight movement distribution and operational capacity analysis.
                    </p>
                </div>

                {{-- Subsections: Distribusi Per Jam vs Operational Capacity + Capacity Button --}}
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="openCapacityModal()"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-aviation-50 text-aviation-700 dark:bg-aviation-950/80 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 hover:bg-aviation-100 dark:hover:bg-aviation-900 transition cursor-pointer shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-aviation-500"></span>
                        <span>AIRCRAFT CAPACITY: [<span class="font-mono font-black" x-text="nacLimit"></span> A/C]</span>
                        <span class="text-[10px] underline ml-0.5 font-bold">EDIT ⚙</span>
                    </button>
                    <div class="inline-flex p-0.5 bg-slate-100 dark:bg-navy-950 rounded-lg border border-slate-200 dark:border-slate-800 text-xs font-semibold">
                        <button type="button"
                                @click="activeChartMode = 'distribution'"
                                :class="activeChartMode === 'distribution' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-md transition flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span>Distribusi Per Jam</span>
                        </button>
                        <button type="button"
                                @click="activeChartMode = 'capacity'"
                                :class="activeChartMode === 'capacity' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                class="px-3 py-1.5 rounded-md transition flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span>Operational Capacity</span>
                        </button>
                    </div>
                </div>

            </div>

            {{-- 3-Column Layout: Summary (Left) + 24-Hour Chart (Center) + Reading Guide (Right) --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
                
                {{-- Column 1: Summary (Left: 3 cols) --}}
                <div class="lg:col-span-3 flex flex-col justify-between bg-slate-50/80 dark:bg-navy-950/80 border border-slate-200 dark:border-slate-800/80 rounded-xl p-4 space-y-3.5">
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2 mb-2.5">
                            <span class="text-[10.5px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-400">RINGKASAN HARI INI</span>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-200 dark:bg-navy-800 text-slate-700 dark:text-slate-300" x-text="inOpsTotalCount + ' Flights'">{{ $stats['total'] }} Flights</span>
                        </div>

                        {{-- Arrivals Stat --}}
                        <div class="p-2.5 rounded-lg bg-white dark:bg-navy-900 border border-orange-200/80 dark:border-orange-900/60 shadow-2xs mb-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-orange-500 inline-block"></span>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Arrivals</span>
                                </div>
                                <span class="text-lg font-black font-mono text-orange-600 dark:text-orange-400" x-text="inOpsArrivalsCount">{{ $stats['arrivals'] }}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Penerbangan Datang (OPS)</div>
                        </div>

                        {{-- Departures Stat --}}
                        <div class="p-2.5 rounded-lg bg-white dark:bg-navy-900 border border-blue-200/80 dark:border-blue-900/60 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600 inline-block"></span>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Departures</span>
                                </div>
                                <span class="text-lg font-black font-mono text-blue-600 dark:text-blue-400" x-text="inOpsDeparturesCount">{{ $stats['departures'] }}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Penerbangan Berangkat (OPS)</div>
                        </div>

                        {{-- OPC Stat --}}
                        <div class="p-2.5 rounded-lg bg-white dark:bg-navy-900 border border-purple-200/80 dark:border-purple-900/60 shadow-2xs mt-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-purple-600 inline-block"></span>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">OPC</span>
                                </div>
                                <span class="text-lg font-black font-mono text-purple-600 dark:text-purple-400" x-text="inOpsOpcCount">{{ $stats['opc'] ?? 0 }}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Occupancy Parking Stand</div>
                            <div class="text-[9.5px] text-purple-600/80 dark:text-purple-400/80 font-medium">RON Stand Occupied (Next Day Dep)</div>
                        </div>
                    </div>

                    {{-- Total Movements & Peak Highlights --}}
                    <div class="pt-2.5 border-t border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wide text-slate-600 dark:text-slate-400">Total Movements</span>
                            <span class="text-xl font-black font-mono text-slate-900 dark:text-white" x-text="inOpsTotalCount">{{ $stats['total'] }}</span>
                        </div>
                        <div class="text-[10px] text-slate-400">Arrivals + Departures dalam jam OPS</div>

                        {{-- Peak Window Mini Pill --}}
                        <div class="p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-between">
                            <div>
                                <div class="text-[9.5px] font-bold text-amber-700 dark:text-amber-300 uppercase">Peak Window</div>
                                <div class="text-xs font-black font-mono text-amber-600 dark:text-amber-400" x-text="peakStats.peakHour">{{ $capacityStats['peak_hour'] }}</div>
                            </div>
                            <span class="px-1.5 py-0.5 rounded text-[9.5px] font-black bg-amber-500 text-white shadow-2xs font-mono">
                                <span x-text="peakStats.peakDemand">{{ $capacityStats['peak_demand'] }}</span> mvm/hr
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Column 2: 24-Hour Movement Chart & Schedule (Center: 6 cols) --}}
                <div class="lg:col-span-6 xl:col-span-6 bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-2xs flex flex-col justify-between">
                    
                    {{-- MODE 1: DISTRIBUSI PER JAM (24-Hour Bar Chart) --}}
                    <div x-show="activeChartMode === 'distribution'" class="space-y-3 flex flex-col justify-between h-full">
                        {{-- Chart Header Badges & Segmented Window Toggle --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800/80 pb-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="openOpsModal()"
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 transition cursor-pointer">
                                    <span class="radar-dot w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>OPS HOURS <span x-text="opsStartTime"></span> &rarr; <span x-text="opsEndTime"></span> (<span x-text="activeHoursCount"></span>h)</span>
                                    <span class="text-[9px] underline ml-0.5 font-bold">Edit ⚙</span>
                                </button>
                                
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

                            {{-- Active Hour Filter Status --}}
                            <template x-if="selectedHour !== null">
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-300 dark:border-aviation-800 text-[10.5px] font-mono font-bold">
                                    <span>Filter: <span x-text="String(selectedHour).padStart(2, '0') + ':00'"></span></span>
                                    <button type="button" @click="clearHourFilter()" class="hover:text-red-500 font-bold ml-1 cursor-pointer">&times;</button>
                                </div>
                            </template>
                        </div>

                        {{-- Chart Visual Canvas --}}
                        <div class="relative pt-6 pb-1 overflow-visible">
                            {{-- Y-Axis Grid Reference Lines --}}
                            <div class="absolute inset-x-0 top-6 bottom-7 pointer-events-none flex flex-col justify-between opacity-20">
                                <div class="border-b border-dashed border-slate-300 dark:border-slate-700 w-full"></div>
                                <div class="border-b border-dashed border-slate-300 dark:border-slate-700 w-full"></div>
                                <div class="border-b border-dashed border-slate-300 dark:border-slate-700 w-full"></div>
                            </div>

                            {{-- Subtle Max Capacity Dashed Reference Line with Tooltip on Hover --}}
                            <div class="absolute inset-x-0 z-0 transition-all duration-200 pointer-events-auto"
                                 :style="'bottom: ' + nacLineBottomPx + 'px'">
                                <div class="relative w-full group/cap cursor-pointer py-1.5"
                                     @mouseenter="capacityHovered = true"
                                     @mouseleave="capacityHovered = false"
                                     tabindex="0"
                                     @focus="capacityHovered = true"
                                     @blur="capacityHovered = false"
                                     aria-label="Batas Aircraft Capacity">
                                    {{-- Subtle dashed line --}}
                                    <div class="w-full border-b-2 border-dashed transition-all duration-150"
                                         :class="capacityHovered ? 'border-aviation-500 dark:border-aviation-300 opacity-100' : 'border-aviation-500/40 dark:border-aviation-400/40 opacity-70'"></div>

                                    {{-- Tooltip on Hover ONLY (Batas Aircraft Capacity) --}}
                                    <div x-show="capacityHovered"
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="absolute left-1/2 -translate-x-1/2 -top-11 z-30 px-3 py-1.5 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-lg shadow-xl border border-slate-700 text-xs font-mono pointer-events-none text-center whitespace-nowrap"
                                         style="display: none;">
                                        <div class="font-bold text-aviation-300 text-[10px] uppercase tracking-wider">Batas Aircraft Capacity</div>
                                        <div class="font-black text-white text-xs" x-text="nacLimit + ' Aircraft'"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Dynamic Column Bars Grid --}}
                            <div class="relative z-10 items-end h-36 px-0.5"
                                 :style="'display: grid; grid-template-columns: repeat(' + activeHourlyDistribution.length + ', minmax(0, 1fr)); gap: ' + (activeHourlyDistribution.length > 16 ? '2px' : '4px') + '; min-height: 144px;'">
                                <template x-for="item in activeHourlyDistribution" :key="item.hour">
                                    <div @click="selectHour(item.hour)"
                                         @mouseenter="hoveredHour = item.hour"
                                         @mouseleave="hoveredHour = null"
                                         :class="selectedHour === item.hour ? 'chart-bar-highlight bg-aviation-50/80 dark:bg-aviation-950/60 rounded-lg ring-2 ring-aviation-500' : ''"
                                         class="flex flex-col items-center justify-end h-full group relative cursor-pointer p-0.5 rounded-md transition-all duration-150 hover:bg-slate-100 dark:hover:bg-navy-800">
                                        
                                        {{-- Top Status Badge --}}
                                        <template x-if="item.isOps && item.status === 'OVER CAPACITY'">
                                            <div class="absolute -top-3 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-purple-600 text-white shadow-2xs z-20 font-mono">
                                                OVER
                                            </div>
                                        </template>
                                        <template x-if="item.isOps && item.status === 'FULL / MAX'">
                                            <div class="absolute -top-3 px-1 py-0.2 rounded text-[7.5px] font-black uppercase tracking-wider bg-amber-500 text-white shadow-2xs z-20 font-mono">
                                                MAX
                                            </div>
                                        </template>

                                        {{-- Stacked Activity Bar Container --}}
                                        <div class="w-full flex flex-col justify-end gap-0.5 rounded-md overflow-hidden p-0.5 relative transition-all duration-200"
                                             :class="[
                                                 !item.isOps ? 'bg-slate-100/70 dark:bg-navy-950/70 border border-dashed border-slate-300/60 dark:border-slate-800 opacity-60' : (
                                                     item.status === 'OVER CAPACITY' ? 'bg-purple-500/15 border-2 border-purple-500 shadow-2xs' : (
                                                         item.status === 'FULL / MAX' ? 'bg-amber-500/15 border-2 border-amber-500' : 'bg-emerald-500/10 dark:bg-emerald-950/30 border border-emerald-500/30'
                                                     )
                                                 ),
                                                 item.isPeak ? 'peak-bar-glow' : ''
                                             ]"
                                             style="height: 98px">
                                            
                                            {{-- OPC Block (Purple) --}}
                                            <template x-if="item.opcCount > 0">
                                                <div class="w-full bg-purple-600 rounded-xs transition-all duration-300 group-hover:brightness-110" 
                                                     :style="'height: ' + Math.max(4, Math.round((item.opcCount / chartMaxScale) * 92)) + 'px'"></div>
                                            </template>

                                            {{-- Arrivals Block (Orange) --}}
                                            <template x-if="item.arrCount > 0">
                                                <div class="w-full bg-orange-500 rounded-xs transition-all duration-300 group-hover:brightness-110" 
                                                     :style="'height: ' + Math.max(4, Math.round((item.arrCount / chartMaxScale) * 92)) + 'px'"></div>
                                            </template>

                                            {{-- Departures Block (Blue) --}}
                                            <template x-if="item.depCount > 0">
                                                <div class="w-full bg-blue-600 rounded-xs transition-all duration-300 group-hover:brightness-110" 
                                                     :style="'height: ' + Math.max(4, Math.round((item.depCount / chartMaxScale) * 92)) + 'px'"></div>
                                            </template>

                                            {{-- Baseline tick for 0 movements and 0 OPC --}}
                                            <template x-if="item.total === 0 && (!item.opcCount || item.opcCount === 0)">
                                                <div class="w-full h-1 bg-slate-200 dark:bg-navy-800 rounded-xs"></div>
                                            </template>
                                        </div>

                                        {{-- Movement count number --}}
                                        <span class="text-[9px] sm:text-[10px] font-mono font-bold mt-1"
                                              :class="!item.isOps ? 'text-slate-400 dark:text-slate-500' : (item.status === 'OVER CAPACITY' ? 'text-purple-600 dark:text-purple-400 font-black' : (item.status === 'FULL / MAX' ? 'text-amber-600 dark:text-amber-400 font-black' : (item.total > 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-300 dark:text-slate-600')))"
                                              x-text="item.total > 0 ? item.total : '0'">
                                        </span>

                                        {{-- Hour X-Axis Label (Strict alignment) --}}
                                        <span class="text-[10px] sm:text-[11px] font-mono"
                                              :class="item.isOps ? 'text-slate-700 dark:text-slate-200 font-bold' : 'text-slate-400 dark:text-slate-500'"
                                              x-text="item.shortLabel">
                                        </span>

                                        {{-- Rich Tooltip on Hover --}}
                                        <div x-show="hoveredHour === item.hour"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             class="absolute bottom-full mb-3 flex flex-col z-50 w-64 p-3 bg-slate-900/95 dark:bg-navy-900/95 text-white backdrop-blur-md rounded-xl shadow-xl border border-slate-700 text-xs pointer-events-none transition-all duration-150"
                                             :class="item.hour <= 4 ? 'left-0' : (item.hour >= 19 ? 'right-0' : 'left-1/2 -translate-x-1/2')"
                                             style="display: none;">
                                            
                                            {{-- Tooltip Header --}}
                                            <div class="flex items-center justify-between border-b border-slate-700/80 pb-1 mb-1.5">
                                                <span class="font-mono font-bold text-slate-100 text-xs" x-text="item.label + ' (' + displayTimezoneLabel + ')'"></span>
                                                <span class="text-[9px] font-bold px-1.5 py-0.2 rounded uppercase font-mono"
                                                      :class="!item.isOps ? 'bg-slate-800 text-slate-400 border border-slate-700' : (item.status === 'OVER CAPACITY' ? 'bg-purple-950 text-purple-300 border border-purple-600' : (item.status === 'FULL / MAX' ? 'bg-amber-950 text-amber-300 border border-amber-600' : 'bg-emerald-950 text-emerald-300 border border-emerald-600'))"
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
                                                    <span class="font-bold" x-text="item.arrCount"></span>
                                                </div>
                                                <div class="flex items-center justify-between text-blue-300">
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
                                                        Departures:
                                                    </span>
                                                    <span class="font-bold" x-text="item.depCount"></span>
                                                </div>

                                                {{-- OPC (RON Parking) --}}
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

                                                <div class="flex items-center justify-between font-bold text-white pt-1.5 border-t border-slate-800">
                                                    <span>Aircraft Demand:</span>
                                                    <span class="text-xs font-black" :class="item.status === 'OVER CAPACITY' ? 'text-purple-400' : (item.status === 'FULL / MAX' ? 'text-amber-400' : 'text-emerald-400')" x-text="item.aircraftDemand + ' / ' + nacLimit + ' A/C'"></span>
                                                </div>
                                                <div class="flex items-center justify-between font-bold text-slate-300">
                                                    <span>Utilization:</span>
                                                    <span class="text-xs font-black" :class="item.utilization > 100 ? 'text-purple-400' : (item.utilization === 100 ? 'text-amber-400' : 'text-emerald-400')" x-text="item.utilization + '%'"></span>
                                                </div>
                                            </div>

                                            <div class="text-[9px] text-slate-400 mt-1.5 pt-1 border-t border-slate-800 text-center italic">
                                                Klik kolom untuk memfilter flight list
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Legend Strip --}}
                        <div class="flex flex-col gap-1.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 text-[11px] text-slate-500">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#16A34A] inline-block"></span>
                                        <strong class="text-slate-700 dark:text-slate-300">Available</strong>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#D97706] inline-block"></span>
                                        <strong class="text-slate-700 dark:text-slate-300">Full / Max</strong>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#9333EA] inline-block"></span>
                                        <strong class="text-slate-700 dark:text-slate-300">Over Capacity</strong>
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-600 inline-block"></span>
                                        <span class="text-slate-500 dark:text-slate-400">Off Hours</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 font-mono text-[10.5px] text-aviation-600 dark:text-aviation-400 cursor-pointer"
                                          @click="openCapacityModal()" title="Click to configure Aircraft Capacity">
                                        <span class="w-3.5 border-b-2 border-dashed border-aviation-500 inline-block"></span>
                                        <span>Aircraft Capacity (<span x-text="nacLimit"></span> A/C) ⚙</span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2.5 text-[10px] text-slate-400">
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-xs bg-orange-500 inline-block"></span> ARR</span>
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-xs bg-blue-600 inline-block"></span> DEP</span>
                                    <span class="inline-flex items-center gap-1" title="OPC: Pesawat RON yang masih menempati parking stand untuk keberangkatan pada hari berikutnya"><span class="w-2 h-2 rounded-xs bg-purple-600 inline-block"></span> OPC (RON)</span>
                                </div>
                            </div>
                            <div class="text-[10px] text-slate-400 dark:text-slate-500 italic">
                                <strong class="text-purple-600 dark:text-purple-400 not-italic font-semibold">OPC:</strong> Pesawat RON yang masih menempati parking stand untuk keberangkatan pada hari berikutnya.
                            </div>
                        </div>
                    </div>

                    {{-- MODE 2: OPERATIONAL CAPACITY (Schedule Overview / Agenda Board) --}}
                    <div x-show="activeChartMode === 'capacity'" class="space-y-2.5 flex flex-col justify-between h-full">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800/80 pb-2">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">OPERATIONAL CAPACITY &mdash; SCHEDULE OVERVIEW</h3>
                                    <span class="px-1.5 py-0.2 rounded-full text-[9.5px] font-black bg-aviation-100 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-mono">
                                        {{ $stats['total'] }} Flights
                                    </span>
                                </div>
                                <p class="text-[10.5px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    Jadwal pergerakan pesawat berdasarkan waktu kedatangan dan keberangkatan.
                                </p>
                            </div>

                            {{-- Time Filter Segmented Control --}}
                            <div class="inline-flex p-0.5 bg-slate-100 dark:bg-navy-950 rounded-lg border border-slate-200 dark:border-slate-800 text-[10px] font-semibold shrink-0">
                                <button type="button" 
                                        @click="scheduleTimeFilter = 'ops'"
                                        :class="scheduleTimeFilter === 'ops' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                        class="px-2 py-0.5 rounded transition flex items-center gap-1 cursor-pointer">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Show OPS Hours (<span x-text="opsStartTime"></span>–<span x-text="opsEndTime"></span>)</span>
                                </button>
                                <button type="button" 
                                        @click="scheduleTimeFilter = 'all'"
                                        :class="scheduleTimeFilter === 'all' ? 'bg-white dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                                        class="px-2 py-0.5 rounded transition flex items-center gap-1 cursor-pointer">
                                    <span>Show All Time (00:00–23:00)</span>
                                </button>
                            </div>
                        </div>

                        {{-- Schedule Table Container --}}
                        <div class="overflow-y-auto max-h-[360px] custom-scrollbar rounded-lg border border-slate-200 dark:border-slate-800 shadow-2xs bg-slate-50/40 dark:bg-navy-950/40">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="sticky top-0 z-20 bg-slate-100/95 dark:bg-navy-950/95 backdrop-blur-xs border-b border-slate-200 dark:border-slate-800 text-[10.5px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                    <tr>
                                        <th class="py-1.5 px-3 w-[42%] text-orange-600 dark:text-orange-400">
                                            <span class="flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                                                STA (ARRIVALS)
                                            </span>
                                        </th>
                                        <th class="py-1.5 px-2 text-center w-[16%] font-mono text-slate-700 dark:text-slate-300">
                                            TIME
                                        </th>
                                        <th class="py-1.5 px-3 w-[42%] text-blue-600 dark:text-blue-400 text-right">
                                            <span class="inline-flex items-center gap-1.5">
                                                STD (DEPARTURES)
                                                <span class="w-2 h-2 rounded-full bg-blue-600"></span>
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
                                            
                                            {{-- STA Arrivals --}}
                                            <td class="py-2 px-2.5 align-top">
                                                @if(count($arrList) > 0)
                                                    <div class="space-y-1.5">
                                                        @foreach($arrList as $f)
                                                            <div @click="openFlightDrawer(@js($f))"
                                                                 class="p-2 rounded-lg border border-orange-200/90 dark:border-orange-900/60 bg-orange-50/40 dark:bg-navy-950/70 hover:border-orange-400 transition cursor-pointer shadow-2xs group">
                                                                <div class="flex items-center justify-between font-mono">
                                                                    <div class="flex items-center gap-1.5">
                                                                        <span class="text-xs text-orange-600 dark:text-orange-400 font-bold">✈</span>
                                                                        <span class="text-xs font-black text-slate-900 dark:text-white">{{ $f['scheduled_time'] }}</span>
                                                                        <span class="text-xs font-black text-orange-600 dark:text-orange-400 ml-1">{{ $f['flight_number'] }}</span>
                                                                    </div>
                                                                    <span class="text-[9.5px] font-bold px-1.5 py-0.2 rounded bg-white dark:bg-navy-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800">{{ $f['aircraft_type'] }}</span>
                                                                </div>
                                                                <div class="flex items-center justify-between text-[10.5px] mt-1 pt-1 border-t border-orange-200/40 dark:border-orange-900/30">
                                                                    <div class="truncate text-slate-600 dark:text-slate-300 font-mono">
                                                                        From <strong class="text-slate-900 dark:text-white">{{ $f['origin'] }}</strong> ({{ $f['origin_name'] }})
                                                                    </div>
                                                                    @if(!empty($f['pairing']['is_ron']))
                                                                        <span class="text-[8px] font-mono font-black uppercase px-1 py-0.2 rounded bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">RON</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="text-[10px] font-mono text-slate-400 italic py-1 px-1">&mdash;</div>
                                                @endif
                                            </td>

                                            {{-- Hour Marker --}}
                                            <td class="py-2 px-2 text-center align-middle font-mono font-bold text-xs"
                                                :class="isOpsHour({{ $h }}) ? 'text-slate-900 dark:text-white bg-slate-50/80 dark:bg-navy-950/80' : 'text-slate-400 opacity-60'">
                                                {{ sprintf('%02d:00', $h) }}
                                            </td>

                                            {{-- STD Departures --}}
                                            <td class="py-2 px-2.5 align-top text-right">
                                                @if(count($depList) > 0)
                                                    <div class="space-y-1.5">
                                                        @foreach($depList as $f)
                                                            <div @click="openFlightDrawer(@js($f))"
                                                                 class="p-2 rounded-lg border border-blue-200/90 dark:border-blue-900/60 bg-blue-50/40 dark:bg-navy-950/70 hover:border-blue-400 transition cursor-pointer shadow-2xs group text-left">
                                                                <div class="flex items-center justify-between font-mono">
                                                                    <div class="flex items-center gap-1.5">
                                                                        <span class="text-xs text-blue-600 dark:text-blue-400 font-bold">✈</span>
                                                                        <span class="text-xs font-black text-slate-900 dark:text-white">{{ $f['scheduled_time'] }}</span>
                                                                        <span class="text-xs font-black text-blue-600 dark:text-blue-400 ml-1">{{ $f['flight_number'] }}</span>
                                                                    </div>
                                                                    <span class="text-[9.5px] font-bold px-1.5 py-0.2 rounded bg-white dark:bg-navy-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800">{{ $f['aircraft_type'] }}</span>
                                                                </div>
                                                                <div class="flex items-center justify-between text-[10.5px] mt-1 pt-1 border-t border-blue-200/40 dark:border-blue-900/30">
                                                                    <div class="truncate text-slate-600 dark:text-slate-300 font-mono">
                                                                        To <strong class="text-slate-900 dark:text-white">{{ $f['destination'] }}</strong> ({{ $f['destination_name'] }})
                                                                    </div>
                                                                    @if(!empty($f['pairing']['is_overnight_dep']))
                                                                        <span class="text-[8px] font-mono font-black uppercase px-1 py-0.2 rounded bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">RON</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="text-[10px] font-mono text-slate-400 italic py-1 px-1 text-right">&mdash;</div>
                                                @endif
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                {{-- Column 3: Cara Membaca Grafik / Panduan (Right: 3 cols) --}}
                <div class="lg:col-span-3 flex flex-col justify-between bg-slate-50/80 dark:bg-navy-950/80 border border-slate-200 dark:border-slate-800/80 rounded-xl p-4 space-y-3">
                    
                    {{-- Guide for Distribusi Per Jam --}}
                    <div x-show="activeChartMode === 'distribution'" class="space-y-2.5">
                        <div class="flex items-center gap-1.5 border-b border-slate-200 dark:border-slate-800 pb-2">
                            <svg class="w-4 h-4 text-aviation-600 dark:text-aviation-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white">PANDUAN OPERASIONAL</h3>
                        </div>

                        {{-- Legend items --}}
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-start gap-2 p-1.5 rounded-lg bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800/60">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#16A34A] shrink-0 mt-0.5"></span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white text-[11px]">Available</div>
                                    <div class="text-[10px] text-slate-500">Pergerakan di bawah batas Aircraft Capacity.</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 p-1.5 rounded-lg bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800/60">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#D97706] shrink-0 mt-0.5"></span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white text-[11px]">Full / Max</div>
                                    <div class="text-[10px] text-slate-500">Pergerakan tepat pada kapasitas maksimum.</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 p-1.5 rounded-lg bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800/60">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#9333EA] shrink-0 mt-0.5"></span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white text-[11px]">Over Capacity</div>
                                    <div class="text-[10px] text-slate-500">Pergerakan melebihi kapasitas toleransi apron.</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 p-1.5 rounded-lg bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800/60">
                                <span class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-600 shrink-0 mt-0.5"></span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white text-[11px]">Off Hours</div>
                                    <div class="text-[10px] text-slate-500">Di luar jam operasional (dikecualikan dari status).</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Guide for Operational Capacity (Cara Membaca Jadwal) --}}
                    <div x-show="activeChartMode === 'capacity'" class="space-y-2.5">
                        <div class="flex items-center gap-1.5 border-b border-slate-200 dark:border-slate-800 pb-2">
                            <svg class="w-4 h-4 text-aviation-600 dark:text-aviation-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-white">PARAMETER JADWAL</h3>
                        </div>

                        <div class="space-y-1.5 text-xs">
                            <div class="p-1.5 rounded-lg bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800/60">
                                <div class="flex items-center gap-1">
                                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300">STA</span>
                                    <span class="font-bold text-slate-800 dark:text-white text-[11px]">Scheduled Arrival</span>
                                </div>
                            </div>
                            <div class="p-1.5 rounded-lg bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800/60">
                                <div class="flex items-center gap-1">
                                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">STD</span>
                                    <span class="font-bold text-slate-800 dark:text-white text-[11px]">Scheduled Departure</span>
                                </div>
                            </div>
                            <div class="p-1.5 rounded-lg bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800/60">
                                <div class="flex items-center gap-1">
                                    <span class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">RON</span>
                                    <span class="font-bold text-slate-800 dark:text-white text-[11px]">Remain Over Night</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-2 rounded-lg bg-aviation-50 dark:bg-aviation-950/60 border border-aviation-200 dark:border-aviation-800 text-[10px] text-aviation-800 dark:text-aviation-300 font-mono">
                        💡 Klik baris flight untuk membuka Pairing &amp; Turnaround Intelligence.
                    </div>
                </div>

            </div>

        </div>

        {{-- ── 3.3 FLIGHT SCHEDULE FILTER (COMPACT CONTROL PANEL RIGHT AFTER ACTIVITY) ── --}}
        <div id="filter-section" class="glass-card p-5 sm:p-6 shadow-xs">
            <form action="{{ route('schedule.dashboard', $upload->id) }}" method="GET" id="filterForm">
                
                <div class="flex items-center justify-between mb-4 border-b border-slate-100 dark:border-slate-800 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                        </div>
                        <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">FILTER FLIGHT SCHEDULE</h2>
                    </div>
                    <span class="text-[11px] text-slate-400 font-mono">Operational Schedule Query Engine</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5 mb-4">

                    {{-- Season --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1">Slot Season</label>
                        <select name="season" class="w-full filter-select">
                            <option value="all" {{ $filters['season'] === 'all' ? 'selected' : '' }}>All Seasons</option>
                            <option value="summer" {{ $filters['season'] === 'summer' ? 'selected' : '' }}>Summer</option>
                            <option value="winter" {{ $filters['season'] === 'winter' ? 'selected' : '' }}>Winter</option>
                        </select>
                    </div>

                    {{-- Branch Airport --}}
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1">Branch (Airport Station)</label>
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
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1">Flight Type &amp; Number</label>
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
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1">D / I (Domestic / Int'l)</label>
                        <select name="di" class="w-full filter-select">
                            <option value="all" {{ $filters['di'] === 'all' ? 'selected' : '' }}>All (Domestic &amp; Int'l)</option>
                            <option value="domestic" {{ $filters['di'] === 'domestic' ? 'selected' : '' }}>Domestic</option>
                            <option value="international" {{ $filters['di'] === 'international' ? 'selected' : '' }}>International</option>
                        </select>
                    </div>

                    {{-- DOS Interactive Day Selector --}}
                    <div class="md:col-span-2 lg:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-1">
                            DOS (Operating Days Filter)
                        </label>
                        
                        <div class="flex flex-wrap items-center gap-1.5">
                            <input type="hidden" name="dos" :value="getDosValue()">

                            <button type="button" @click="isDaily ? deselectDaily() : selectDaily()"
                                    :class="isDaily ? 'bg-aviation-600 text-white font-bold border-aviation-600 shadow-2xs' : 'bg-slate-100 dark:bg-navy-950 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700/80 hover:bg-slate-200 dark:hover:bg-navy-800'"
                                    class="px-3 py-1 rounded-lg text-xs border transition flex-shrink-0 cursor-pointer">
                                Daily
                            </button>

                            <div class="flex items-center gap-1">
                                @foreach(['1'=>'Mon','2'=>'Tue','3'=>'Wed','4'=>'Thu','5'=>'Fri','6'=>'Sat','7'=>'Sun'] as $num => $dayName)
                                    <button type="button"
                                            @click="toggleDay('{{ $num }}')"
                                            :class="isSelected('{{ $num }}')
                                                ? 'bg-amber-500 text-white border-amber-600 font-bold shadow-2xs'
                                                : 'bg-slate-100 dark:bg-navy-950 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700/80 hover:bg-slate-200 dark:hover:bg-navy-800'"
                                            class="w-7 h-7 rounded-lg text-xs border transition flex items-center justify-center font-mono font-bold cursor-pointer"
                                            title="Day {{ $num }} ({{ $dayName }})">
                                        {{ $num }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Active Filter summary & Apply button --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3.5 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-slate-500 font-semibold text-[11px]">Active Query:</span>
                        @if($filters['season'] !== 'all')
                            <span class="px-2 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-semibold text-[11px]">Season: {{ ucfirst($filters['season']) }}</span>
                        @endif
                        @if($filters['branch'] !== 'ALL')
                            <span class="px-2 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-semibold text-[11px]">Branch: {{ $filters['branch'] }}</span>
                        @endif
                        @if($filters['flight'] !== 'all')
                            <span class="px-2 py-0.5 rounded-full bg-aviation-50 dark:bg-aviation-950 text-aviation-700 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800 font-semibold text-[11px]">Type: {{ ucfirst($filters['flight']) }}</span>
                        @endif
                        @if($filters['di'] !== 'all')
                            <span class="px-2 py-0.5 rounded-full bg-cyan-50 dark:bg-cyan-950 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800 font-semibold text-[11px]">D/I: {{ ucfirst($filters['di']) }}</span>
                        @endif
                        @if($filters['dos'] !== 'all' && $filters['dos'] !== '')
                            <span class="px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 font-semibold text-[11px]">
                                DOS: {{ $filters['dos'] === 'daily' || $filters['dos'] === '1234567' ? 'Daily' : $filters['dos'] }}
                            </span>
                        @endif
                        @if($filters['search'] !== '')
                            <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-navy-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-semibold text-[11px]">Search: "{{ $filters['search'] }}"</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('schedule.dashboard', $upload->id) }}"
                           class="px-3.5 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 border border-slate-200 dark:border-slate-700 rounded-lg transition">
                            Reset
                        </a>
                        <button type="submit"
                                class="btn-aviation-primary px-4 py-1.5 text-xs font-bold rounded-lg transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Apply Filters</span>
                        </button>
                    </div>
                </div>

            </form>
        </div>

        {{-- ── 3.4 LIST PERGERAKAN HARI INI (CLEAN AOCC TABLE) ───────────────── --}}
        <div id="flight-list-section" class="glass-card p-5 sm:p-6 shadow-xs space-y-4">
            
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3.5">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/>
                            </svg>
                        </div>
                        <h2 class="text-sm sm:text-base font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                            LIST PERGERAKAN HARI INI
                        </h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Menampilkan jadwal pergerakan penerbangan hari ini secara berurutan.
                    </p>
                </div>

                {{-- Action: Download PDF Report --}}
                <div class="flex items-center gap-2">
                    <a href="{{ $downloadDailyPdfUrl ?? route('schedule.report.daily-movements', array_merge(['upload' => $upload->id], request()->query())) }}"
                       class="btn-aviation-primary px-3.5 py-1.5 rounded-lg text-xs font-bold inline-flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Download PDF</span>
                    </a>
                </div>
            </div>

            {{-- Active Hour Filter Notification Banner --}}
            <div x-show="selectedHour !== null" 
                 x-transition
                 class="p-3 rounded-xl bg-aviation-50/90 dark:bg-aviation-950/80 border border-aviation-200 dark:border-aviation-800 flex items-center justify-between gap-3 shadow-2xs"
                 style="display: none;">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-aviation-600 animate-ping"></span>
                    <span class="text-xs text-aviation-900 dark:text-aviation-200 font-semibold font-mono">
                        Showing flights for <strong class="text-aviation-700 dark:text-aviation-300" x-text="String(selectedHour).padStart(2, '0') + ':00 – ' + String(selectedHour).padStart(2, '0') + ':59'"></strong> 
                        (<span class="font-bold" x-text="filteredMovements.length"></span> movements)
                    </span>
                </div>
                <button type="button" @click="clearHourFilter()"
                        class="px-2.5 py-0.5 rounded-lg text-xs font-bold bg-white dark:bg-navy-900 text-aviation-700 dark:text-aviation-300 hover:bg-aviation-100 dark:hover:bg-navy-800 border border-aviation-300 dark:border-aviation-700 transition cursor-pointer flex items-center gap-1">
                    <span>Clear Filter</span>
                    <span class="font-black">&times;</span>
                </button>
            </div>

            {{-- Interactive Filters Bar: Tabs & Search --}}
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-2.5">
                
                {{-- Tabs: Semua / Arrivals / Departures / OPC --}}
                <div class="inline-flex p-0.5 bg-slate-100 dark:bg-navy-950 rounded-lg border border-slate-200 dark:border-slate-800 text-xs font-semibold">
                    <button type="button" 
                            @click="movementFilter = 'all'"
                            :class="movementFilter === 'all' ? 'bg-white dark:bg-navy-800 text-slate-900 dark:text-white shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-3 py-1.5 rounded-md transition cursor-pointer">
                        Semua (<span x-text="movements.length"></span>)
                    </button>
                    <button type="button" 
                            @click="movementFilter = 'arrivals'"
                            :class="movementFilter === 'arrivals' ? 'bg-white dark:bg-navy-800 text-orange-600 dark:text-orange-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-3 py-1.5 rounded-md transition flex items-center gap-1.5 cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                        Arrivals (<span x-text="arrivalsCount"></span>)
                    </button>
                    <button type="button" 
                            @click="movementFilter = 'departures'"
                            :class="movementFilter === 'departures' ? 'bg-white dark:bg-navy-800 text-blue-600 dark:text-blue-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-3 py-1.5 rounded-md transition flex items-center gap-1.5 cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                        Departures (<span x-text="departuresCount"></span>)
                    </button>
                    <button type="button" 
                            @click="movementFilter = 'opc'"
                            :class="movementFilter === 'opc' ? 'bg-white dark:bg-navy-800 text-purple-600 dark:text-purple-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="px-3 py-1.5 rounded-md transition flex items-center gap-1.5 cursor-pointer">
                        <span class="w-2 h-2 rounded-full bg-purple-600"></span>
                        OPC (<span x-text="opcMovementsCount"></span>)
                    </button>
                </div>

                {{-- Search Flight Input --}}
                <div class="relative w-full md:w-80">
                    <input type="text" 
                           x-model="movementSearch" 
                           placeholder="Search flight, airline, route..." 
                           class="filter-select w-full pl-8 pr-7 text-xs font-mono">
                    <div class="absolute left-2.5 top-2 text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <button x-show="movementSearch !== ''" 
                            @click="movementSearch = ''" 
                            type="button" 
                            class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 cursor-pointer font-bold">
                        &times;
                    </button>
                </div>

            </div>

            {{-- Flight Movements Table (Clean AOCC Schema: Waktu, Flight, Route, Maskapai, Tipe Pesawat, Jenis) --}}
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xs">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-navy-950 text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 font-bold uppercase text-[10.5px]">
                            <th class="py-2.5 px-3.5 w-28">WAKTU (<span x-text="displayTimezoneLabel"></span>)</th>
                            <th class="py-2.5 px-3 w-32">FLIGHT</th>
                            <th class="py-2.5 px-3 w-48">ROUTE</th>
                            <th class="py-2.5 px-3 w-48">MASKAPAI</th>
                            <th class="py-2.5 px-3 w-28">TIPE PESAWAT</th>
                            <th class="py-2.5 px-3 text-center w-28">JENIS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-navy-900">
                        <template x-for="f in filteredMovements" :key="f.id">
                            <tr @click="openFlightDrawer(f)"
                                class="hover:bg-slate-50/80 dark:hover:bg-navy-800/50 transition cursor-pointer group">
                                
                                {{-- Waktu (STA / STD) --}}
                                <td class="py-2.5 px-3.5 font-mono align-top whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-black text-slate-900 dark:text-white" x-text="flightTime(f)"></span>
                                        <span class="px-1.5 py-0.2 rounded text-[8.5px] font-black uppercase font-mono"
                                              :class="f.direction === 'arrival' ? 'bg-orange-50 text-orange-700 dark:bg-orange-950 dark:text-orange-300 border border-orange-200 dark:border-orange-800' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border border-blue-200 dark:border-blue-800'"
                                              x-text="f.time_type">
                                        </span>
                                    </div>
                                    <div class="text-[9.5px] text-slate-400 font-sans" x-text="f.direction === 'arrival' ? 'Kedatangan' : 'Keberangkatan'"></div>
                                </td>

                                {{-- Flight Number --}}
                                <td class="py-2.5 px-3 font-mono align-top whitespace-nowrap">
                                    <div class="font-black text-xs text-aviation-600 dark:text-aviation-400 group-hover:underline flex items-center gap-1" x-text="f.flight_number"></div>
                                    <template x-if="f.pairing && f.pairing.is_ron">
                                        <div class="text-[9px] font-bold text-purple-600 dark:text-purple-400 flex items-center gap-0.5 mt-0.5">
                                            <span>🌙</span> <span>RON</span>
                                        </div>
                                    </template>
                                </td>

                                {{-- Route with Unicode Arrow --}}
                                <td class="py-2.5 px-3 font-mono align-top">
                                    <div class="font-bold text-slate-800 dark:text-slate-200 text-xs" x-text="f.origin + ' → ' + f.destination"></div>
                                    <div class="text-[10px] text-slate-400 truncate max-w-[170px]" x-text="f.origin_name + ' → ' + f.destination_name"></div>
                                </td>

                                {{-- Maskapai --}}
                                <td class="py-2.5 px-3 align-top">
                                    <div class="font-bold text-slate-800 dark:text-slate-200 text-xs truncate max-w-[170px]" x-text="f.airline_name"></div>
                                    <div class="text-[10px] text-slate-400 font-mono" x-text="'DOS: ' + f.operating_days"></div>
                                </td>

                                {{-- Tipe Pesawat --}}
                                <td class="py-2.5 px-3 font-mono align-top">
                                    <div class="font-bold text-slate-700 dark:text-slate-300 text-xs" x-text="f.aircraft_type"></div>
                                    <div class="text-[10px] text-slate-400 uppercase" x-text="f.traffic_badge"></div>
                                </td>

                                {{-- Jenis (Arrival / Departure) --}}
                                <td class="py-2.5 px-3 text-center align-top whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[9.5px] font-black uppercase tracking-wider"
                                          :class="f.direction === 'arrival' ? 'bg-orange-50 text-orange-700 dark:bg-orange-950 dark:text-orange-300 border border-orange-200 dark:border-orange-800' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border border-blue-200 dark:border-blue-800'">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="f.direction === 'arrival' ? 'bg-orange-500' : 'bg-blue-600'"></span>
                                        <span x-text="f.direction_label"></span>
                                    </span>
                                </td>

                            </tr>
                        </template>

                        {{-- Empty State Row --}}
                        <tr x-show="filteredMovements.length === 0" style="display: none;">
                            <td colspan="6" class="py-10 text-center text-slate-400">
                                <div class="font-bold text-slate-700 dark:text-slate-300 text-sm">Tidak ada pergerakan penerbangan ditemukan</div>
                                <div class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau reset filter jam.</div>
                                <button type="button" @click="clearHourFilter(); movementFilter='all'; movementSearch=''"
                                        class="mt-3 px-3 py-1 rounded-lg bg-aviation-50 dark:bg-navy-800 text-aviation-600 dark:text-aviation-400 text-xs font-bold border border-aviation-200 dark:border-aviation-700 hover:bg-aviation-100 transition cursor-pointer">
                                    Reset Filter
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Table Footer Summary --}}
            <div class="flex items-center justify-between text-xs text-slate-500 pt-1 border-t border-slate-100 dark:border-slate-800">
                <div>
                    Menampilkan <span class="font-bold text-aviation-600 dark:text-aviation-400 font-mono" x-text="filteredMovements.length"></span> dari <span class="font-mono font-bold" x-text="movements.length"></span> flights
                </div>
                <div class="text-[10.5px] text-slate-400 italic">
                    Klik baris untuk membuka Flight Detail Drawer
                </div>
            </div>

        </div>

    </main>

    {{-- ══ 4. FLIGHT DETAIL DRAWER (RIGHT-SIDE SLIDE-OVER) ═════════════════════ --}}
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
             x-transition:enter="transition ease-out duration-200 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="w-full max-w-md bg-white dark:bg-navy-900 border-l border-slate-200 dark:border-slate-800 h-full p-5 shadow-2xl flex flex-col justify-between overflow-y-auto">
            
            <div>
                {{-- Drawer Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-aviation-50 dark:bg-aviation-950 flex items-center justify-center text-aviation-600 dark:text-aviation-400 font-mono font-black text-base shadow-xs">
                            ✈
                        </div>
                        <div>
                            <div class="text-[9.5px] text-slate-400 font-bold uppercase tracking-wider">Flight Detail</div>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white font-mono" x-text="activeFlight ? activeFlight.flight_number : ''"></h3>
                        </div>
                    </div>
                    <button type="button" @click="closeFlightDrawer()"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-navy-800 transition cursor-pointer text-lg font-bold">
                        &times;
                    </button>
                </div>

                {{-- Drawer Body --}}
                <template x-if="activeFlight">
                    <div class="space-y-3.5 text-xs">
                        
                        {{-- Airline & Route Card --}}
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-800 dark:text-slate-100 font-black uppercase text-xs font-mono tracking-wider" x-text="activeFlight.airline_name || activeFlight.airline_code"></span>
                                <span class="px-2 py-0.5 rounded-full text-[9.5px] font-black uppercase tracking-wider bg-aviation-50 text-aviation-700 dark:bg-aviation-950 dark:text-aviation-300 border border-aviation-200 dark:border-aviation-800"
                                      x-text="activeFlight.category_badge || activeFlight.traffic_badge"></span>
                            </div>
                            <div class="text-xl font-black font-mono text-slate-900 dark:text-white flex items-center gap-2">
                                <span x-text="activeFlight.origin || '—'"></span>
                                <span class="text-aviation-600">&rarr;</span>
                                <span x-text="activeFlight.destination || '—'"></span>
                            </div>
                            <div class="text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                                <span x-text="activeFlight.origin_name || activeFlight.origin"></span> &rarr; <span x-text="activeFlight.destination_name || activeFlight.destination"></span>
                            </div>
                        </div>

                        {{-- Technical Flight Specifications (Exact Requested Fields) --}}
                        <div class="p-3.5 rounded-xl bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 space-y-2 font-mono">
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                                <span class="text-slate-400">Flight Number:</span>
                                <span class="font-bold text-slate-900 dark:text-white" x-text="activeFlight.flight_number"></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                                <span class="text-slate-400">Airline:</span>
                                <span class="font-bold text-slate-900 dark:text-white" x-text="activeFlight.airline_name"></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                                <span class="text-slate-400">STA / STD (Schedule):</span>
                                <span class="font-bold text-aviation-600 dark:text-aviation-400" x-text="activeFlight.scheduled_time + ' (' + displayTimezoneLabel + ')'"></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                                <span class="text-slate-400">Aircraft Type:</span>
                                <span class="font-bold text-amber-600 dark:text-amber-400" x-text="activeFlight.aircraft_type"></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                                <span class="text-slate-400">Airport Station:</span>
                                <span class="font-bold text-slate-900 dark:text-white">{{ $airportCode }} ({{ $airportName }})</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                                <span class="text-slate-400">Days of Operation (DOS):</span>
                                <span class="font-bold text-slate-900 dark:text-white" x-text="activeFlight.operating_days"></span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-slate-400">Category:</span>
                                <span class="font-bold uppercase text-slate-900 dark:text-white" x-text="activeFlight.traffic_badge"></span>
                            </div>
                        </div>

                        {{-- Pairing & Rotation Visualizer --}}
                        <template x-if="activeFlight.pairing">
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 space-y-2">
                                <div class="text-[10.5px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Aircraft Rotation &amp; Pairing</div>
                                <div class="p-2 rounded-lg bg-white dark:bg-navy-900 border border-slate-200 dark:border-slate-800 font-mono text-[11px] font-bold text-aviation-700 dark:text-aviation-300"
                                     x-text="activeFlight.pairing.summary_text"></div>
                            </div>
                        </template>

                    </div>
                </template>
            </div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 text-center">
                <button type="button" @click="closeFlightDrawer()" class="w-full py-2 bg-slate-100 dark:bg-navy-800 hover:bg-slate-200 dark:hover:bg-navy-700 rounded-lg text-xs font-bold text-slate-700 dark:text-slate-300 transition">
                    Close Detail
                </button>
            </div>

        </div>
    </div>

    {{-- ══ 5. MODALS: AIRCRAFT CAPACITY & OPS HOURS ═════════════════════════════ --}}
    {{-- Modal 1: Aircraft Capacity Setting --}}
    <div x-show="capacityModalOpen" x-transition class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4" style="display: none;">
        <div @click.away="closeCapacityModal()" class="w-full max-w-md bg-white dark:bg-navy-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-aviation-600"></span>
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider">AIRCRAFT CAPACITY (NAC)</h3>
                </div>
                <button @click="closeCapacityModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <div class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Maximum Aircraft (Simultaneous Apron / Stand Capacity)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" min="1" max="100" x-model.number="modalNac" class="filter-select w-full font-mono text-base font-bold">
                        <span class="text-xs font-bold text-slate-500 font-mono">AIRCRAFT</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Configured station: <strong class="text-aviation-600 dark:text-aviation-400">{{ $airportCode }} ({{ $airportName }})</strong>.</p>
                </div>

                {{-- Quick Presets for Indonesian Airports --}}
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Quick Presets</div>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" @click="modalNac = 6" :class="modalNac === 6 ? 'bg-aviation-600 text-white font-bold' : 'bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300'" class="px-2.5 py-1 rounded-md text-xs font-mono transition">6 (BDO)</button>
                        <button type="button" @click="modalNac = 8" :class="modalNac === 8 ? 'bg-aviation-600 text-white font-bold' : 'bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300'" class="px-2.5 py-1 rounded-md text-xs font-mono transition">8 (DJJ)</button>
                        <button type="button" @click="modalNac = 10" :class="modalNac === 10 ? 'bg-aviation-600 text-white font-bold' : 'bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300'" class="px-2.5 py-1 rounded-md text-xs font-mono transition">10 (LOP/SRG)</button>
                        <button type="button" @click="modalNac = 12" :class="modalNac === 12 ? 'bg-aviation-600 text-white font-bold' : 'bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300'" class="px-2.5 py-1 rounded-md text-xs font-mono transition">12 (JOG)</button>
                        <button type="button" @click="modalNac = 15" :class="modalNac === 15 ? 'bg-aviation-600 text-white font-bold' : 'bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300'" class="px-2.5 py-1 rounded-md text-xs font-mono transition">15 (SUB/UPG)</button>
                        <button type="button" @click="modalNac = 20" :class="modalNac === 20 ? 'bg-aviation-600 text-white font-bold' : 'bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300'" class="px-2.5 py-1 rounded-md text-xs font-mono transition">20 (DPS)</button>
                        <button type="button" @click="modalNac = 30" :class="modalNac === 30 ? 'bg-aviation-600 text-white font-bold' : 'bg-slate-100 dark:bg-navy-800 text-slate-700 dark:text-slate-300'" class="px-2.5 py-1 rounded-md text-xs font-mono transition">30 (CGK)</button>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-navy-950 border border-slate-200 dark:border-slate-800 flex items-center gap-2.5">
                    <input type="checkbox" id="cargo_cb" x-model="cargoSeparateParking" class="rounded text-aviation-600 focus:ring-aviation-500">
                    <label for="cargo_cb" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                        Cargo uses dedicated parking stand (Excluded from Passenger Capacity limit)
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="closeCapacityModal()" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-navy-800">Cancel</button>
                <button type="button" @click="applyCapacity()" class="btn-aviation-primary px-4 py-1.5 rounded-lg text-xs font-bold shadow-xs">Save Configuration</button>
            </div>
        </div>
    </div>

    {{-- Modal 2: OPS Hours Setting --}}
    <div x-show="opsModalOpen" x-transition class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4" style="display: none;">
        <div @click.away="closeOpsModal()" class="w-full max-w-md bg-white dark:bg-navy-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="radar-dot w-2 h-2 rounded-full bg-emerald-500"></span>
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider">EDIT OPERATIONAL HOURS</h3>
                </div>
                <button @click="closeOpsModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">OPS Start Time</label>
                        <input type="text" x-model="modalOpsStart" placeholder="06:00" class="filter-select w-full font-mono text-center font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">OPS End Time</label>
                        <input type="text" x-model="modalOpsEnd" placeholder="20:00" class="filter-select w-full font-mono text-center font-bold">
                    </div>
                </div>

                <template x-if="modalError">
                    <div class="p-2 rounded-lg bg-red-50 dark:bg-red-950/40 text-red-600 text-xs" x-text="modalError"></div>
                </template>

                <p class="text-[11px] text-slate-400">
                    Changing operational hours reactively updates Today's Flight Activity, Capacity calculations, and visual timeline references without reloading.
                </p>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="resetOpsHours()" class="text-xs font-semibold text-slate-500 hover:underline">Reset to Default</button>
                <div class="flex items-center gap-2">
                    <button type="button" @click="closeOpsModal()" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-navy-800">Cancel</button>
                    <button type="button" @click="applyOpsHours()" class="btn-aviation-primary px-4 py-1.5 rounded-lg text-xs font-bold shadow-xs">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Reactive Toast Notification --}}
    <div x-show="opsToastOpen" x-transition class="fixed bottom-5 right-5 z-50 p-3.5 rounded-xl bg-slate-900/95 text-white shadow-2xl border border-slate-700 text-xs font-semibold flex items-center gap-2 font-mono" style="display: none;">
        <span class="text-emerald-400">✓</span>
        <span x-text="opsToastMessage"></span>
    </div>

</div>
@endsection
