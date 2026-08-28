<?php

namespace App\Services;

use App\Models\Upload;
use App\Models\TimelineSetting;
use App\Models\Airport;
use App\Models\Airline;

/**
 * TimelineLayoutService — Unified single layout engine for 24-Hour Timeline.
 * Pre-computes exact visual coordinates, visual lane packing, category colors,
 * operating hours shading, and summary totals for both Web UI and High-Res Exports (JPG/PDF).
 */
class TimelineLayoutService
{
    public function __construct(
        private AirportResolverService $airportResolver
    ) {}

    public function getLayout(Upload $upload, int $colW = 120, int $rowH = 64, ?int $customBlockW = null, ?int $customLabelOffset = null): array
    {
        $settings = TimelineSetting::firstOrCreate(
            ['upload_id' => $upload->id],
            ['ops_start' => 6, 'ops_end' => 20]
        );

        $positions = $upload->timelinePositions()
            ->with(['flight.airline', 'flight.originAirport', 'flight.destinationAirport'])
            ->orderBy('section')
            ->orderBy('hour')
            ->orderBy('row')
            ->get();

        $departures = $positions->where('section', 'departure')->values();
        $arrivals   = $positions->where('section', 'arrival')->values();

        $getColor = function ($p) {
            $type = $p->flight->flight_type ?? '';
            if ($type === 'departure_domestic')      return '#1e40af'; // Darker Blue
            if ($type === 'departure_international') return '#3b82f6'; // Lighter Blue
            if ($type === 'arrival_domestic')       return '#b45309'; // Darker Orange
            if ($type === 'arrival_international')  return '#f59e0b'; // Lighter Yellow/Orange
            return $p->color_hex ?: '#3b82f6';
        };

        if ($customBlockW !== null) {
            $blockW = $customBlockW;
        } else {
            $blockW = $colW >= 200 ? 220 : ($colW >= 160 ? 150 : max((int)round($colW * 0.88), $colW - 7));
        }

        if ($customLabelOffset !== null) {
            $labelOffset = $customLabelOffset;
        } else {
            $labelOffset = $colW >= 200 ? 250 : ($colW >= 160 ? 140 : (int)round($colW * 0.90));
        }

        // Preload airport cache for ultra-fast lookup
        $airportsCache = Airport::all()->keyBy('iata_code');
        $airlinesCache = Airline::all()->keyBy('airline_code');

        $opsStartMin = ((int)$settings->ops_start) * 60;
        $opsEndMin   = ((int)$settings->ops_end) * 60;

        $mapBlocks = function ($list) use ($colW, $getColor, $blockW, $labelOffset, $airportsCache, $airlinesCache, $opsStartMin, $opsEndMin) {
            $blocks = [];
            foreach ($list as $p) {
                $hour = (int) $p->hour;
                $off  = (int) ($p->offset_minutes ?? 0);
                $min  = ($hour * 60) + $off;

                $isWithinOps = ($min >= $opsStartMin && $min <= $opsEndMin);
                $isOffHour   = !$isWithinOps;

                $flight = $p->flight;
                $airlineCode = strtoupper($flight->airline_code ?? substr($flight->flight_number ?? '', 0, 2));
                $airline = $airlinesCache->get($airlineCode);
                $airlineName = $airline ? $airline->airline_name : $this->airportResolver->getAirlineName($airlineCode);

                $origIata = strtoupper($flight->origin ?? 'BDO');
                $destIata = strtoupper($flight->destination ?? 'BDO');

                $origAirport = $airportsCache->get($origIata);
                $destAirport = $airportsCache->get($destIata);

                $isArrival = str_contains($flight->flight_type ?? '', 'arrival');
                $routeLabel = "{$origIata} → {$destIata}";

                // Remote airport is the non-BDO station
                $remoteAirport = $isArrival ? $origAirport : $destAirport;
                $remoteIata    = $isArrival ? $origIata : $destIata;

                $blocks[] = [
                    'id'             => $p->id,
                    'hour'           => $hour,
                    'offset_minutes' => $off,
                    'startMinutes'   => $min,
                    'is_off_hour'    => $isOffHour,
                    'color_hex'      => $getColor($p),
                    'section'        => $p->section,
                    'flight' => [
                        'flight_number'          => $flight->flight_number,
                        'airline_code'           => $airlineCode,
                        'airline_name'           => $airlineName,
                        'aircraft_type'          => $this->airportResolver->normalizeAircraftType($flight->aircraft_type),
                        'origin'                 => $origIata,
                        'origin_iata'            => $origIata,
                        'origin_name'            => $origAirport?->name ?? $origIata,
                        'origin_full_label'      => $origAirport ? "{$origIata} — {$origAirport->name}" : $origIata,
                        'origin_region'          => $origAirport?->region ?? '—',
                        'origin_management'      => $origAirport?->management_type ?? '—',
                        'destination'            => $destIata,
                        'destination_iata'       => $destIata,
                        'destination_name'       => $destAirport?->name ?? $destIata,
                        'destination_full_label' => $destAirport ? "{$destIata} — {$destAirport->name}" : $destIata,
                        'destination_region'     => $destAirport?->region ?? '—',
                        'destination_management' => $destAirport?->management_type ?? '—',
                        'remote_airport_name'    => $remoteAirport?->name ?? $remoteIata,
                        'remote_region'          => $remoteAirport?->region ?? ($remoteAirport?->management_type === 'UPT Daerah/Pemda' ? 'UPT Daerah/Pemda' : '—'),
                        'remote_management'      => $remoteAirport?->management_type ?? 'PT. Angkasa Pura Indonesia',
                        'route_label'            => $routeLabel,
                        'scheduled_time'         => $flight->scheduled_time,
                        'operating_days'         => $flight->operating_days ?? '1234567',
                        'flight_type'            => $flight->flight_type,
                        'category'               => str_contains($flight->flight_type, 'international') ? 'International' : 'Domestic',
                        'is_off_hour'            => $isOffHour,
                    ],
                ];
            }

            // Visual lane allocation algorithm (identical 1:1 logic as browser JS)
            usort($blocks, function ($a, $b) {
                if ($a['startMinutes'] !== $b['startMinutes']) {
                    return $a['startMinutes'] - $b['startMinutes'];
                }
                return strcmp($a['flight']['flight_number'], $b['flight']['flight_number']);
            });

            $minGapPx = (int)round($colW * 0.05);
            $laneEndX = [];

            foreach ($blocks as &$b) {
                $startX = $labelOffset + (int) round(($b['startMinutes'] / 60) * $colW);
                $endX   = $startX + $blockW;

                $assignedLane = -1;
                for ($l = 0; $l < count($laneEndX); $l++) {
                    if ($startX >= $laneEndX[$l] + $minGapPx) {
                        $assignedLane = $l;
                        $laneEndX[$l] = $endX;
                        break;
                    }
                }
                if ($assignedLane === -1) {
                    $assignedLane = count($laneEndX);
                    $laneEndX[] = $endX;
                }

                $b['row']   = $assignedLane;
                $b['lane']  = $assignedLane;
                $b['x']     = $startX;
                $b['width'] = $blockW;
            }
            unset($b);

            return $blocks;
        };

        $depBlocks = $mapBlocks($departures);
        $arrBlocks = $mapBlocks($arrivals);

        $depMaxRows = count($depBlocks) > 0 ? max(3, max(array_column($depBlocks, 'row')) + 1) : 2;
        $arrMaxRows = count($arrBlocks) > 0 ? max(3, max(array_column($arrBlocks, 'row')) + 1) : 2;

        $depBandH = ($depMaxRows * $rowH) + 12;
        $arrBandH = ($arrMaxRows * $rowH) + 12;
        $totalSectionHeight = $depBandH + $arrBandH;

        // 24 Hours + 1 TOT column
        $totWidth = $colW;
        $canvasWidth = $labelOffset + (24 * $colW) + $totWidth;

        $summary = [];
        for ($h = 0; $h < 24; $h++) {
            $summary[$h] = ['dep_dom' => 0, 'dep_int' => 0, 'arr_dom' => 0, 'arr_int' => 0];
        }

        foreach (array_merge($depBlocks, $arrBlocks) as $b) {
            $h = $b['hour'];
            $type = $b['flight']['flight_type'] ?? '';
            if ($type === 'departure_domestic')      $summary[$h]['dep_dom']++;
            elseif ($type === 'departure_international') $summary[$h]['dep_int']++;
            elseif ($type === 'arrival_domestic')       $summary[$h]['arr_dom']++;
            elseif ($type === 'arrival_international')  $summary[$h]['arr_int']++;
        }

        return [
            'settings'           => $settings,
            'colW'               => $colW,
            'rowH'               => $rowH,
            'blockW'             => $blockW,
            'labelOffset'        => $labelOffset,
            'totWidth'           => $totWidth,
            'canvasWidth'        => $canvasWidth,
            'depMaxRows'         => $depMaxRows,
            'arrMaxRows'         => $arrMaxRows,
            'depBandH'           => $depBandH,
            'arrBandH'           => $arrBandH,
            'totalSectionHeight' => $totalSectionHeight,
            'departureBlocks'    => $depBlocks,
            'arrivalBlocks'      => $arrBlocks,
            'summary'            => $summary,
            'opsStart'           => $settings->ops_start,
            'opsEnd'             => $settings->ops_end,
            'opsLeft'            => $labelOffset + ($settings->ops_start * $colW),
            'opsWidth'           => ($settings->ops_end - $settings->ops_start) * $colW,
            'totalFlights'       => count($depBlocks) + count($arrBlocks),
        ];
    }
}
