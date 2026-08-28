<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Models\Airline;
use App\Models\Flight;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MasterDataController extends Controller
{
    /**
     * GET /api/airports
     * Supports: search, management_type, region, airport_type, usage_type, iata, icao, data_source
     */
    public function airports(Request $request): JsonResponse
    {
        $query = Airport::query();

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('iata_code', 'LIKE', "%{$s}%")
                  ->orWhere('icao_code', 'LIKE', "%{$s}%")
                  ->orWhere('name', 'LIKE', "%{$s}%")
                  ->orWhere('city', 'LIKE', "%{$s}%")
                  ->orWhere('province', 'LIKE', "%{$s}%");
            });
        }

        if ($request->filled('iata')) {
            $query->where('iata_code', strtoupper(trim($request->input('iata'))));
        }

        if ($request->filled('icao')) {
            $query->where('icao_code', strtoupper(trim($request->input('icao'))));
        }

        $isAngkasaPura = false;
        if ($request->filled('management_type') && $request->input('management_type') !== 'all') {
            $mt = strtoupper(str_replace(['.', ' ', '/'], '_', $request->input('management_type')));
            if (str_contains($mt, 'INJOURNEY') || str_contains($mt, 'ANGKASA')) {
                $isAngkasaPura = true;
                $query->where(function ($q) {
                    $q->where('management_type', Airport::MANAGEMENT_INJOURNEY)
                      ->orWhere('management_name', 'PT. Angkasa Pura Indonesia');
                });
            } elseif (str_contains($mt, 'DITJEN') || str_contains($mt, 'HUBUD') || str_contains($mt, 'UPBU')) {
                $query->where(function ($q) {
                    $q->where('management_type', Airport::MANAGEMENT_UPBU_HUBUD)
                      ->orWhere('management_name', 'UPT Ditjen Hubud');
                });
            } elseif (str_contains($mt, 'PEMDA') || str_contains($mt, 'DAERAH') || str_contains($mt, 'UPTD')) {
                $query->where(function ($q) {
                    $q->where('management_type', Airport::MANAGEMENT_UPTD_PEMDA)
                      ->orWhere('management_name', 'UPT Daerah/Pemda');
                });
            } else {
                $query->where('management_type', $request->input('management_type'));
            }
        }

        // Region filter: ONLY apply if management is PT. Angkasa Pura or unspecified
        if ($request->filled('region') && $request->input('region') !== 'all') {
            // If explicit non-AP management was selected, ignore region filter
            if (!$request->filled('management_type') || $isAngkasaPura) {
                $r = trim($request->input('region'));
                $query->byRegion($r);
            }
        }

        if ($request->filled('airport_type') && $request->input('airport_type') !== 'all') {
            $query->where('airport_type', strtolower(trim($request->input('airport_type'))));
        }

        if ($request->filled('usage_type') && $request->input('usage_type') !== 'all') {
            $query->where('usage_type', 'LIKE', "%{$request->input('usage_type')}%");
        }

        if ($request->filled('data_source') && $request->input('data_source') !== 'all') {
            $query->where(function ($q) use ($request) {
                $src = $request->input('data_source');
                $q->where('data_source', $src)->orWhere('source', $src);
            });
        }

        $airports = $query->orderBy('iata_code')->get();

        return response()->json([
            'success' => true,
            'count'   => $airports->count(),
            'data'    => $airports,
        ]);
    }

    /**
     * GET /api/airlines
     * Supports: search, category, status
     */
    public function airlines(Request $request): JsonResponse
    {
        $query = Airline::query();

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('airline_code', 'LIKE', "%{$s}%")
                  ->orWhere('organization_code', 'LIKE', "%{$s}%")
                  ->orWhere('airline_name', 'LIKE', "%{$s}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', strtolower(trim($request->input('category'))));
        }

        if ($request->filled('status')) {
            $query->where('status', strtolower(trim($request->input('status'))));
        }

        $airlines = $query->orderBy('airline_code')->get();

        return response()->json([
            'success' => true,
            'count'   => $airlines->count(),
            'data'    => $airlines,
        ]);
    }

    /**
     * GET /api/flights
     * Supports: upload_id, airline_code, origin, destination, search
     */
    public function flights(Request $request): JsonResponse
    {
        $query = Flight::with(['airline', 'originAirport', 'destinationAirport']);

        if ($request->filled('upload_id')) {
            $query->where('upload_id', $request->input('upload_id'));
        }

        if ($request->filled('airline_code')) {
            $query->where('airline_code', strtoupper(trim($request->input('airline_code'))));
        }

        if ($request->filled('origin')) {
            $query->where('origin', strtoupper(trim($request->input('origin'))));
        }

        if ($request->filled('destination')) {
            $query->where('destination', strtoupper(trim($request->input('destination'))));
        }

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('flight_number', 'LIKE', "%{$s}%")
                  ->orWhere('airline_code', 'LIKE', "%{$s}%")
                  ->orWhere('origin', 'LIKE', "%{$s}%")
                  ->orWhere('destination', 'LIKE', "%{$s}%");
            });
        }

        $flights = $query->orderBy('scheduled_time')->limit(500)->get()->map(function ($f) {
            return [
                'id'               => $f->id,
                'flight_number'    => $f->flight_number,
                'airline_code'     => $f->airline_code,
                'airline_name'     => $f->airline?->airline_name ?? $f->airline_name,
                'aircraft_type'    => $f->aircraft_type,
                'origin_iata'      => $f->origin,
                'origin_name'      => $f->originAirport?->name ?? $f->origin,
                'destination_iata' => $f->destination,
                'destination_name' => $f->destinationAirport?->name ?? $f->destination,
                'scheduled_time'   => $f->scheduled_time,
                'flight_type'      => $f->flight_type,
                'operating_days'   => $f->operating_days,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => $flights->count(),
            'data'    => $flights,
        ]);
    }
}
