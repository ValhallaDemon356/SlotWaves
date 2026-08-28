<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\TimelinePosition;
use App\Models\TimelineSetting;
use App\Services\AirportResolverService;
use App\Services\TimelineLayoutService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function __construct(
        private AirportResolverService $airportResolver,
        private TimelineLayoutService $layoutService
    ) {}

    /**
     * Display the 24-Hour Timeline for a specific upload.
     * READ-ONLY: Never creates, mutates, or re-parses upload or flight records.
     */
    public function show(Upload $upload)
    {
        if ($upload->status !== 'completed') {
            return redirect()->route('home')
                ->withErrors(['pdf' => 'Timeline is not ready or failed to process.']);
        }

        $layout = $this->layoutService->getLayout($upload, 120, 64);

        $upload = $upload;
        $departures = collect($layout['departureBlocks']);
        $arrivals   = collect($layout['arrivalBlocks']);

        $departureBlocks = $layout['departureBlocks'];
        $arrivalBlocks   = $layout['arrivalBlocks'];

        $summary  = $layout['summary'];
        $settings = $layout['settings'];

        $legend = [
            ['color' => '#1e40af', 'label' => 'Departure Domestic (Dark Blue)'],
            ['color' => '#3b82f6', 'label' => 'Departure International (Light Blue)'],
            ['color' => '#b45309', 'label' => 'Arrival Domestic (Dark Orange)'],
            ['color' => '#f59e0b', 'label' => 'Arrival International (Light Orange)'],
        ];

        return view('timeline', compact(
            'upload', 'departures', 'arrivals',
            'departureBlocks', 'arrivalBlocks',
            'summary', 'legend', 'settings', 'layout'
        ));
    }

    /**
     * Generate or stream high-fidelity Multi-Page Landscape Operational Slot Schedule PDF Report.
     * Page 1: 24-Hour Timeline Overview
     * Pages 2+: Comprehensive Flight Schedule Details Table
     */
    public function pdf(Upload $upload)
    {
        if ($upload->status !== 'completed') {
            abort(404, 'Timeline is not ready.');
        }

        // Dedicated PDF export resolution fitting 100% of A2 printable width (canvas = 2080px <= 2185px page printable)
        $layout = $this->layoutService->getLayout($upload, 80, 58, 74, 80);

        // Fetch all validated flights for comprehensive schedule details table
        $flights = $upload->flights()
            ->with(['airline', 'originAirport', 'destinationAirport'])
            ->orderBy('scheduled_time')
            ->orderBy('flight_number')
            ->get();

        $airportsCache = \App\Models\Airport::all()->keyBy('iata_code');
        $airlinesCache = \App\Models\Airline::all()->keyBy('airline_code');

        $detailedFlights = [];
        $no = 1;
        foreach ($flights as $f) {
            $airlineCode = strtoupper($f->airline_code ?? substr($f->flight_number ?? '', 0, 2));
            $airlineObj  = $airlinesCache->get($airlineCode);
            $airlineName = $airlineObj ? $airlineObj->airline_name : $this->airportResolver->getAirlineName($airlineCode);
            $airlineFull = $airlineName ? "{$airlineName} ({$airlineCode})" : $airlineCode;

            $origIata = strtoupper($f->origin ?? 'BDO');
            $destIata = strtoupper($f->destination ?? 'BDO');

            $origAirport = $airportsCache->get($origIata);
            $destAirport = $airportsCache->get($destIata);

            $isArrival = strtolower($f->direction ?? '') === 'arrival' || str_contains($f->flight_type ?? '', 'arrival');
            $remoteAirport = $isArrival ? $origAirport : $destAirport;
            $remoteIata    = $isArrival ? $origIata : $destIata;

            $remoteName = $remoteAirport?->name ?? $remoteIata;
            $airportLabel = "{$remoteName} ({$remoteIata})";

            $region = $remoteAirport?->region;
            if (empty($region)) {
                $region = ($remoteAirport?->management_type === 'UPT Daerah/Pemda' ? 'UPT Daerah/Pemda' : '—');
            }

            $management = $remoteAirport?->management_name ?: ($remoteAirport?->management_type ?: '—');
            if ($management === 'Other' || empty($management)) {
                $management = '—';
            }

            $category = (strtolower($f->traffic_type ?? '') === 'international' || str_contains($f->flight_type ?? '', 'international'))
                ? 'International' : 'Domestic';

            $direction = $isArrival ? 'ARR' : 'DEP';
            $route = "{$origIata} → {$destIata}";

            $detailedFlights[] = [
                'no'             => $no++,
                'airline'        => $airlineFull,
                'flight_number'  => $f->flight_number,
                'aircraft_type'  => $this->airportResolver->normalizeAircraftType($f->aircraft_type),
                'direction'      => $direction,
                'scheduled_time' => $f->scheduled_time ? substr($f->scheduled_time, 0, 5) : '—',
                'route'          => $route,
                'airport'        => $airportLabel,
                'region'         => $region ?: '—',
                'management'     => $management ?: '—',
                'category'       => $category,
                'operating_days' => $f->operating_days ?: '1234567',
            ];
        }

        $airport = $upload->airport ?: \App\Models\Airport::findByIata('BDO');

        // A3/A2 Landscape paper size: 1683.78pt x 1190.55pt (594mm x 420mm)
        $pdf = Pdf::loadView('reports.timeline-pdf', [
            'upload'          => $upload,
            'airport'         => $airport,
            'layout'          => $layout,
            'departureBlocks' => $layout['departureBlocks'],
            'arrivalBlocks'   => $layout['arrivalBlocks'],
            'settings'        => $layout['settings'],
            'summary'         => $layout['summary'],
            'detailedFlights' => $detailedFlights,
        ])
        ->setPaper([0, 0, 1683.78, 1190.55], 'landscape')
        ->setOptions([
            'isRemoteEnabled'      => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont'          => 'DejaVu Sans',
            'chroot'               => public_path(),
        ]);

        $filename = "SlotWaves_24-Hour_Timeline_{$upload->id}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Persist operating hours from the timeline.
     * IMPORTANT: This only updates the viewport setting.
     * It NEVER modifies flight.scheduled_time values.
     */
    public function saveOpsHours(Request $request, Upload $upload)
    {
        $startInput = $request->input('ops_start');
        $endInput   = $request->input('ops_end');

        if ($startInput === null || $endInput === null) {
            return response()->json([
                'success' => false,
                'message' => 'Both ops_start and ops_end are required.',
            ], 422);
        }

        // Support string format "06:00" and integer format 6
        if (is_string($startInput) && str_contains($startInput, ':')) {
            $start = (int) explode(':', $startInput)[0];
        } else {
            $start = (int) $startInput;
        }

        if (is_string($endInput) && str_contains($endInput, ':')) {
            $end = (int) explode(':', $endInput)[0];
        } else {
            $end = (int) $endInput;
        }

        if ($start < 0 || $start > 23 || $end < 1 || $end > 24) {
            return response()->json([
                'success' => false,
                'message' => 'Start hour must be 00–23 and End hour must be 01–24.',
            ], 422);
        }

        if ($start >= $end) {
            return response()->json([
                'success' => false,
                'message' => 'End time must be later than start time.',
            ], 422);
        }

        TimelineSetting::updateOrCreate(
            ['upload_id' => $upload->id],
            [
                'ops_start' => $start,
                'ops_end'   => $end,
            ]
        );

        $activeHours = max(0, $end - $start);

        return response()->json([
            'success'        => true,
            'ops_start'      => sprintf('%02d:00', $start),
            'ops_end'        => sprintf('%02d:00', $end),
            'ops_start_hour' => $start,
            'ops_end_hour'   => $end,
            'active_hours'   => $activeHours,
        ]);
    }

    public function updatePosition(Request $request, TimelinePosition $position)
    {
        $validated = $request->validate([
            'hour'           => 'required|integer|min:0|max:23',
            'row'            => 'required|integer|min:0|max:50',
            'offset_minutes' => 'required|integer|min:0|max:59',
            'section'        => 'required|string|in:departure,arrival',
        ]);

        // Collision validation check
        $exists = TimelinePosition::where('upload_id', $position->upload_id)
            ->where('id', '!=', $position->id)
            ->where('hour', $validated['hour'])
            ->where('row', $validated['row'])
            ->where('section', $validated['section'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Target cell is already occupied.'
            ], 422);
        }

        $position->update($validated);

        return response()->json(['success' => true]);
    }
}
