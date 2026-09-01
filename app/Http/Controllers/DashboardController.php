<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\Airport;
use App\Models\TimelineSetting;
use App\Services\ReportService;
use App\Services\FlightFilterService;
use App\Services\CapacityService;
use App\Services\AircraftCategoryService;
use App\Services\AirportResolverService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ReportService $reports,
        private FlightFilterService $filterService,
        private CapacityService $capacityService,
        private AircraftCategoryService $categoryService,
        private AirportResolverService $airportResolver
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Generated File Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Request $request, Upload $upload)
    {
        $this->assertCompleted($upload);
        session(['active_upload_id' => $upload->id]);

        $filters = $this->filterService->parseFilters($request->all());
        
        // Total un-filtered validated flight count
        $totalUnfiltered = $upload->flights()->validated()->count();

        // Get filtered flights collection
        $flights = $this->filterService->getFilteredFlights($upload, $filters);
        
        $airport = $this->reports->resolveAirportPublic($upload);

        // Airport Operational Settings: Capacity, Timezone, Operating Hours
        $airportCapacity = $airport ? $airport->getEffectiveCapacity() : (int) config('slotwaves.nac', 6);
        $airportTimezone = $airport ? $airport->getTimezone() : 'Asia/Jakarta';
        $timezoneOffset  = $airport ? $airport->getTimezoneOffsetMinutes() : 420;
        $timezoneAbbr    = $airport ? $airport->getTimezoneAbbreviation() : 'WIB';

        // Summary stats react dynamically to the filtered flight set!
        $stats = [
            'total'             => $flights->count(),
            'arrivals'          => $flights->filter(fn($f) => str_contains($f->flight_type, 'arrival'))->count(),
            'departures'        => $flights->filter(fn($f) => str_contains($f->flight_type, 'departure'))->count(),
            'domestic'          => $flights->filter(fn($f) => str_contains($f->flight_type, 'domestic'))->count(),
            'international'     => $flights->filter(fn($f) => str_contains($f->flight_type, 'international'))->count(),
            'aircraft_capacity' => $airportCapacity,
            'timezone'          => $airportTimezone,
            'timezone_offset'   => $timezoneOffset,
            'timezone_abbr'     => $timezoneAbbr,
        ];

        // Operating hours: prioritize query params (?ops_start=06:00&ops_end=19:00), then saved settings, then airport default, then fallback (6 and 20)
        $settings = TimelineSetting::where('upload_id', $upload->id)->first();
        
        $reqStart = $request->query('ops_start');
        $reqEnd   = $request->query('ops_end');

        if ($reqStart && str_contains($reqStart, ':')) {
            $opsStartHour = (int) explode(':', $reqStart)[0];
        } elseif ($reqStart !== null && is_numeric($reqStart)) {
            $opsStartHour = (int) $reqStart;
        } else {
            $opsStartHour = $settings ? (int) $settings->ops_start : ($airport ? $airport->getOpsStartHour() : 6);
        }

        if ($reqEnd && str_contains($reqEnd, ':')) {
            $opsEndHour = (int) explode(':', $reqEnd)[0];
        } elseif ($reqEnd !== null && is_numeric($reqEnd)) {
            $opsEndHour = (int) $reqEnd;
        } else {
            $opsEndHour = $settings ? (int) $settings->ops_end : ($airport ? $airport->getOpsEndHour() : 20);
        }

        if ($opsStartHour < 0 || $opsStartHour > 23 || $opsEndHour < 1 || $opsEndHour > 24 || $opsStartHour >= $opsEndHour) {
            $opsStartHour = 6;
            $opsEndHour   = 20;
        }

        $stats['ops_start_hour'] = $opsStartHour;
        $stats['ops_end_hour']   = $opsEndHour;
        $stats['ops_start']      = sprintf('%02d:00', $opsStartHour);
        $stats['ops_end']        = sprintf('%02d:00', $opsEndHour);
        $stats['active_hours']   = max(0, $opsEndHour - $opsStartHour);

        // Separate arrivals and departures for dashboard table sections
        $arrivals   = $flights->filter(fn($f) => str_contains($f->flight_type, 'arrival'))->values();
        $departures = $flights->filter(fn($f) => str_contains($f->flight_type, 'departure'))->values();

        // Calculate operational capacity stats dynamically from filtered flights and operational window
        $capacityStats = $this->capacityService->calculate($flights, null, $opsStartHour, $opsEndHour, $airportCapacity);
        $stats['opc']  = $capacityStats['opc_count'] ?? 0;

        // Build rotation lookup map for flight movements
        $pairingLookup = [];
        foreach ($capacityStats['rotations'] as $rot) {
            $status = $rot['rotation_status'];
            $isPaired = $rot['is_paired'];
            $arr = $rot['arrival'];
            $dep = $rot['departure'];

            if ($arr) {
                $arrId = $arr->id ?? "arr_{$arr->flight_number}_{$arr->scheduled_time}";
                $staTime = substr($arr->scheduled_time, 0, 5);
                $isRon = ($status === 'UNPAIRED_ARR' || empty($dep));

                $pairingLookup['arr_' . $arrId] = [
                    'rotation_id'          => $rot['rotation_id'],
                    'rotation_status'      => $status,
                    'is_paired'            => $isPaired,
                    'is_ron'               => $isRon,
                    'is_cargo'             => $rot['is_cargo'] ?? false,
                    'pairing_type'         => $isRon ? 'RON' : 'PAIRED',
                    'current_direction'    => 'arrival',
                    'current_flight_no'    => $arr->flight_number,
                    'current_time'         => $staTime,
                    'current_time_type'    => 'STA',
                    'paired_flight_number' => $dep ? $dep->flight_number : null,
                    'paired_time'          => $dep ? substr($dep->scheduled_time, 0, 5) : null,
                    'paired_time_type'     => $dep ? 'STD' : null,
                    'paired_destination'   => $dep ? ($dep->destination ?: '—') : null,
                    'turnaround_mins'      => $rot['turnaround_mins'] ?? null,
                    'summary_text'         => $dep
                        ? "{$arr->flight_number} ({$staTime}) → {$dep->flight_number} (" . substr($dep->scheduled_time, 0, 5) . ")"
                        : "{$arr->flight_number} ({$staTime}) → RON — Remain Over Night",
                    'ron_explanation'      => $isRon ? 'Remain Over Night — Pesawat menginap di bandara dan tidak memiliki jadwal keberangkatan lanjutan pada hari operasi ini.' : null,
                ];
            }

            if ($dep) {
                $depId = $dep->id ?? "dep_{$dep->flight_number}_{$dep->scheduled_time}";
                $stdTime = substr($dep->scheduled_time, 0, 5);
                $isOvernightDep = ($status === 'UNPAIRED_DEP' || empty($arr));

                $pairingLookup['dep_' . $depId] = [
                    'rotation_id'          => $rot['rotation_id'],
                    'rotation_status'      => $status,
                    'is_paired'            => $isPaired,
                    'is_ron'               => false,
                    'is_cargo'             => $rot['is_cargo'] ?? false,
                    'is_overnight_dep'     => $isOvernightDep,
                    'pairing_type'         => $isOvernightDep ? 'OVERNIGHT_DEP' : 'PAIRED',
                    'current_direction'    => 'departure',
                    'current_flight_no'    => $dep->flight_number,
                    'current_time'         => $stdTime,
                    'current_time_type'    => 'STD',
                    'paired_flight_number' => $arr ? $arr->flight_number : null,
                    'paired_time'          => $arr ? substr($arr->scheduled_time, 0, 5) : null,
                    'paired_time_type'     => $arr ? 'STA' : null,
                    'paired_origin'        => $arr ? ($arr->origin ?: '—') : null,
                    'turnaround_mins'      => $rot['turnaround_mins'] ?? null,
                    'summary_text'         => $arr
                        ? "{$arr->flight_number} (" . substr($arr->scheduled_time, 0, 5) . ") → {$dep->flight_number} ({$stdTime})"
                        : "Remain Over Night (RON) → {$dep->flight_number} ({$stdTime})",
                    'ron_explanation'      => $isOvernightDep ? 'Overnight Aircraft — Pesawat telah berada di bandara sejak awal hari operasi (RON dari jadwal sebelumnya) sebelum diberangkatkan.' : null,
                ];
            }
        }

        $airportCode = $airport ? strtoupper($airport->iata_code) : 'BDO';
        $airportName = $airport ? strtoupper($airport->name) : 'HUSEIN SASTRANEGARA';

        // Normalized Chronological Flight Movements
        $flightMovements = $flights->map(function ($f) use ($airport, $airportCode, $airportName, $airportTimezone, $pairingLookup) {
            $isArr = str_contains($f->flight_type, 'arrival');
            $time = substr($f->scheduled_time, 0, 5);
            $hour = (int) substr($f->scheduled_time, 0, 2);
            $timeType = $isArr ? 'STA' : 'STD';

            $originCode = $f->origin ?: ($isArr ? '—' : $airportCode);
            $originName = $f->originAirport?->name ?: ($originCode === $airportCode ? $airportName : $originCode);
            $destCode   = $f->destination ?: ($isArr ? $airportCode : '—');
            $destName   = $f->destinationAirport?->name ?: ($destCode === $airportCode ? $airportName : $destCode);

            $airlineName = $f->airline?->airline_name ?: ($f->airline_code ?: substr($f->flight_number, 0, 2));

            // Origin / Destination Airport Objects for Master Data (Management, Region, etc.)
            $stationAp = $isArr ? $f->originAirport : $f->destinationAirport;
            $management = $airport?->management_name ?? ($stationAp?->management_name ?? 'PT. Angkasa Pura Indonesia');
            $region     = $airport?->region ?? ($stationAp?->region ?? 'Region 1');

            $lookupKey = ($isArr ? 'arr_' : 'dep_') . $f->id;
            $pairing = $pairingLookup[$lookupKey] ?? [
                'is_paired'            => false,
                'is_ron'               => $isArr,
                'pairing_type'         => $isArr ? 'RON' : 'UNPAIRED',
                'paired_flight_number' => null,
                'paired_time'          => null,
                'paired_time_type'     => null,
                'turnaround_mins'      => null,
                'summary_text'         => $isArr ? "{$f->flight_number} ({$time}) → RON — Remain Over Night" : "{$f->flight_number} ({$time})",
                'ron_explanation'      => $isArr ? 'Remain Over Night — Pesawat menginap di bandara.' : null,
            ];

            $isCargo = $this->categoryService->isCargoFlight($f);
            $operationType = $isCargo ? 'CARGO' : 'PASSENGER';

            // UTC Time and Hour conversions
            $utcTime = $this->airportResolver->convertTimeToUtc($time, $airportTimezone);
            $utcHour = $this->airportResolver->convertHourToUtc($hour, $airportTimezone);

            return [
                'id'                   => $f->id,
                'flight_number'        => $f->flight_number,
                'airline_code'         => $f->airline_code ?: substr($f->flight_number, 0, 2),
                'airline_name'         => $airlineName,
                'aircraft_type'        => $f->aircraft_type ?: '—',
                'scheduled_time'       => $time,
                'hour'                 => $hour,
                'utc_time'             => $utcTime,
                'utc_hour'             => $utcHour,
                'time_type'            => $timeType,
                'direction'            => $isArr ? 'arrival' : 'departure',
                'direction_label'      => $isArr ? 'Arrival' : 'Departure',
                'traffic_type'         => str_contains($f->flight_type, 'international') ? 'international' : 'domestic',
                'traffic_badge'        => str_contains($f->flight_type, 'international') ? 'INT' : 'DOM',
                'flight_type'          => $f->flight_type,
                'category_badge'       => str_contains($f->flight_type, 'international') ? 'International' : 'Domestic',
                'is_cargo'             => $isCargo,
                'operation_type'       => $operationType,
                'origin'               => $originCode,
                'origin_name'          => $originName,
                'destination'          => $destCode,
                'destination_name'     => $destName,
                'route'                => "{$originCode} → {$destCode}",
                'operating_days'       => $f->operating_days ?: '1234567',
                'region'               => $region,
                'management'           => $management,
                'is_paired'            => $pairing['is_paired'],
                'is_ron'               => $pairing['is_ron'] ?? false,
                'pairing'              => $pairing,
                'pair_text'            => $pairing['summary_text'],
            ];
        })->sortBy('scheduled_time')->values();

        // Normalized rotations for client-side reactive aircraft occupancy calculations
        $normalizedRotations = array_map(function ($rot) {
            return [
                'rotation_id'     => $rot['rotation_id'],
                'rotation_status' => $rot['rotation_status'],
                'is_paired'       => $rot['is_paired'],
                'is_ron'          => ($rot['rotation_status'] === 'UNPAIRED_ARR' || empty($rot['departure'])),
                'is_cargo'        => $rot['is_cargo'] ?? false,
                'passenger_units' => $rot['passenger_units'] ?? 1,
                'start_minute'    => $rot['start_minute'],
                'end_minute'      => $rot['end_minute'],
                'sta'             => $rot['sta'] ?? null,
                'std'             => $rot['std'] ?? null,
                'aircraft_type'   => $rot['aircraft_type'] ?? '—',
                'category'        => $rot['category'] ?? 'PNB',
                'flight_number'   => !empty($rot['arrival'])
                    ? $rot['arrival']->flight_number
                    : (!empty($rot['departure']) ? $rot['departure']->flight_number : $rot['rotation_id']),
                'pair_label'      => (!empty($rot['arrival']) && !empty($rot['departure']))
                    ? "{$rot['arrival']->flight_number} → {$rot['departure']->flight_number}"
                    : (!empty($rot['arrival']) ? "{$rot['arrival']->flight_number} (Unpaired Arr)" : "RON → {$rot['departure']->flight_number}"),
            ];
        }, $capacityStats['rotations']);

        // Hourly flight schedule buckets for 24-hour agenda / movement board view
        $hourlySchedule = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlySchedule[$h] = [
                'hour' => $h,
                'label' => sprintf('%02d:00–%02d:59', $h, $h),
                'short_label' => sprintf('%02d:00', $h),
                'is_ops' => ($h >= $stats['ops_start_hour'] && $h < $stats['ops_end_hour']),
                'arrivals' => $flightMovements->where('direction', 'arrival')->where('hour', $h)->values()->all(),
                'departures' => $flightMovements->where('direction', 'departure')->where('hour', $h)->values()->all(),
            ];
        }

        // Airports list for Branch filter
        $airports = \App\Models\Airport::orderBy('iata_code')->get();

        // URLs with active query filters
        $queryParams = $request->query();
        $downloadCombinedUrl = route('schedule.report.download', array_merge(['upload' => $upload->id], $queryParams));
        $downloadDailyPdfUrl = route('schedule.report.daily-movements', array_merge(['upload' => $upload->id], $queryParams));
        $previewCombinedUrl  = route('schedule.preview.combined', array_merge(['upload' => $upload->id], $queryParams));
        $previewTimeUrl      = route('schedule.preview.time', array_merge(['upload' => $upload->id], $queryParams));
        $previewDosUrl       = route('schedule.preview.dos', array_merge(['upload' => $upload->id], $queryParams));

        return view('schedule.dashboard', compact(
            'upload',
            'airport',
            'airports',
            'stats',
            'flights',
            'flightMovements',
            'normalizedRotations',
            'hourlySchedule',
            'arrivals',
            'departures',
            'capacityStats',
            'totalUnfiltered',
            'filters',
            'airportCapacity',
            'airportTimezone',
            'timezoneOffset',
            'timezoneAbbr',
            'downloadCombinedUrl',
            'downloadDailyPdfUrl',
            'previewCombinedUrl',
            'previewTimeUrl',
            'previewDosUrl'
        ));
    }

    /**
     * Persist operational settings (Aircraft Capacity, Timezone, Ops Hours)
     * Updates airport configuration and timeline settings.
     */
    public function saveOperationalSettings(Request $request, Upload $upload)
    {
        $validated = $request->validate([
            'aircraft_capacity' => 'nullable|integer|min:1|max:100',
            'timezone'          => 'nullable|string|max:50',
            'ops_start'         => 'nullable|string|max:10',
            'ops_end'           => 'nullable|string|max:10',
        ]);

        $airport = $this->reports->resolveAirportPublic($upload);

        if ($airport) {
            $updates = [];
            if (!empty($validated['aircraft_capacity'])) {
                $updates['aircraft_capacity'] = (int) $validated['aircraft_capacity'];
            }
            if (!empty($validated['timezone'])) {
                $updates['timezone'] = $validated['timezone'];
            }
            if (!empty($validated['ops_start'])) {
                $updates['ops_start_time'] = str_contains($validated['ops_start'], ':') ? $validated['ops_start'] : sprintf('%02d:00', $validated['ops_start']);
            }
            if (!empty($validated['ops_end'])) {
                $updates['ops_end_time'] = str_contains($validated['ops_end'], ':') ? $validated['ops_end'] : sprintf('%02d:00', $validated['ops_end']);
            }
            if (!empty($updates)) {
                $airport->update($updates);
            }
        }

        // Also update TimelineSetting if ops hours provided
        if (!empty($validated['ops_start']) && !empty($validated['ops_end'])) {
            $s = (int) explode(':', (string)$validated['ops_start'])[0];
            $e = (int) explode(':', (string)$validated['ops_end'])[0];
            if ($e === 0) $e = 24;

            TimelineSetting::updateOrCreate(
                ['upload_id' => $upload->id],
                ['ops_start' => $s, 'ops_end' => $e]
            );
        }

        return response()->json([
            'success'           => true,
            'message'           => 'Airport Operational Settings updated successfully.',
            'aircraft_capacity' => $airport ? $airport->getEffectiveCapacity() : ($validated['aircraft_capacity'] ?? 6),
            'timezone'          => $airport ? $airport->getTimezone() : ($validated['timezone'] ?? 'Asia/Jakarta'),
            'ops_start'         => $airport ? $airport->ops_start_time : '06:00',
            'ops_end'           => $airport ? $airport->ops_end_time : '20:00',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Preview: COMBINED (renders in-browser as HTML with active filters)
    // ─────────────────────────────────────────────────────────────────────────

    public function previewCombined(Request $request, Upload $upload)
    {
        $this->assertCompleted($upload);
        $filters = $this->filterService->parseFilters($request->all());

        return response($this->reports->renderCombinedHtml($upload, $filters), 200)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Preview: TIME (renders in-browser as HTML with active filters)
    // ─────────────────────────────────────────────────────────────────────────

    public function previewTime(Request $request, Upload $upload)
    {
        $this->assertCompleted($upload);
        $filters = $this->filterService->parseFilters($request->all());

        return response($this->reports->renderTimeHtml($upload, $filters), 200)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Preview: DOS (renders in-browser as HTML with active filters)
    // ─────────────────────────────────────────────────────────────────────────

    public function previewDos(Request $request, Upload $upload)
    {
        $this->assertCompleted($upload);
        $filters = $this->filterService->parseFilters($request->all());

        return response($this->reports->renderDosHtml($upload, $filters), 200)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Download Combined PDF (TIME → page break → DOS with active filters)
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadCombined(Request $request, Upload $upload)
    {
        $this->assertCompleted($upload);
        $filters = $this->filterService->parseFilters($request->all());

        return $this->reports->generateCombinedPdf($upload, $filters);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Download Daily Flight Movement PDF (Dedicated flight movement report)
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadDailyMovements(Request $request, Upload $upload)
    {
        $this->assertCompleted($upload);
        $filters = $this->filterService->parseFilters($request->all());

        return $this->reports->generateDailyMovementsPdf($upload, $filters);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Guards
    // ─────────────────────────────────────────────────────────────────────────

    private function assertCompleted(Upload $upload): void
    {
        if ($upload->status !== 'completed') {
            abort(404, 'This schedule has not been successfully processed yet.');
        }

        if ($upload->flights()->doesntExist()) {
            abort(404, 'No flight data found for this upload.');
        }
    }
}
