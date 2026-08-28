<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Airline;
use Illuminate\Http\Request;

class MasterDataViewController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tab = $request->input('tab', 'airports');

            // ── 1. Airports Query & Dynamic Filters ─────────────────────────────
            $airportQuery = Airport::query();

            // Combined Keyword Search
            if ($request->filled('ap_search')) {
                $s = trim($request->input('ap_search'));
                $airportQuery->where(function ($q) use ($s) {
                    $q->where('iata_code', 'LIKE', "%{$s}%")
                      ->orWhere('icao_code', 'LIKE', "%{$s}%")
                      ->orWhere('name', 'LIKE', "%{$s}%")
                      ->orWhere('city', 'LIKE', "%{$s}%")
                      ->orWhere('area', 'LIKE', "%{$s}%")
                      ->orWhere('province', 'LIKE', "%{$s}%")
                      ->orWhere('management_name', 'LIKE', "%{$s}%");
                });
            }

            // Dedicated IATA Filter
            if ($request->filled('iata')) {
                $airportQuery->where('iata_code', strtoupper(trim($request->input('iata'))));
            }

            // Dedicated ICAO Filter
            if ($request->filled('icao')) {
                $airportQuery->where('icao_code', strtoupper(trim($request->input('icao'))));
            }

            // Management Filter
            $isAngkasaPura = false;
            if ($request->filled('management') && $request->input('management') !== 'all') {
                $mgmt = $request->input('management');
                $norm = strtoupper(str_replace(['.', ' ', '/'], '_', $mgmt));
                if (str_contains($norm, 'INJOURNEY') || str_contains($norm, 'ANGKASA')) {
                    $isAngkasaPura = true;
                    $airportQuery->where('management_type', Airport::MANAGEMENT_INJOURNEY);
                } elseif (str_contains($norm, 'PEMDA') || str_contains($norm, 'DAERAH') || str_contains($norm, 'UPTD')) {
                    $airportQuery->where('management_type', Airport::MANAGEMENT_UPTD_PEMDA);
                } elseif (str_contains($norm, 'DITJEN') || str_contains($norm, 'HUBUD') || str_contains($norm, 'UPBU')) {
                    $airportQuery->where('management_type', Airport::MANAGEMENT_UPBU_HUBUD);
                } elseif (str_contains($norm, 'TNI')) {
                    $airportQuery->where('management_type', Airport::MANAGEMENT_TNI);
                } elseif (str_contains($norm, 'MISSION') || str_contains($norm, 'MISION')) {
                    $airportQuery->where('management_type', Airport::MANAGEMENT_MISSIONARIS);
                } elseif (str_contains($norm, 'BUMN')) {
                    $airportQuery->where('management_type', Airport::MANAGEMENT_BUMN);
                } elseif (str_contains($norm, 'SWASTA')) {
                    $airportQuery->where('management_type', Airport::MANAGEMENT_SWASTA);
                } elseif (str_contains($norm, 'MASYARAKAT')) {
                    $airportQuery->where('management_type', Airport::MANAGEMENT_MASYARAKAT);
                } else {
                    $airportQuery->where('management_type', Airport::MANAGEMENT_OTHER);
                }
            }

            // Region Filter: ONLY apply if management is PT. Angkasa Pura or 'all'
            if ($request->filled('region') && $request->input('region') !== 'all') {
                if (!$request->filled('management') || $request->input('management') === 'all' || $isAngkasaPura) {
                    $airportQuery->byRegion($request->input('region'));
                }
            }

            // Airport Type Filter (Domestic / International)
            if ($request->filled('type') && $request->input('type') !== 'all') {
                $airportQuery->where('airport_type', $request->input('type'));
            }

            // Usage Type Filter
            if ($request->filled('usage') && $request->input('usage') !== 'all') {
                $airportQuery->where('usage_type', 'LIKE', "%{$request->input('usage')}%");
            }

            // Status Filter
            if ($request->filled('status') && $request->input('status') !== 'all') {
                $isActive = $request->input('status') === 'active';
                $airportQuery->where('is_active', $isActive);
            }

            // Data Source Filter
            if ($request->filled('source') && $request->input('source') !== 'all') {
                $airportQuery->where(function ($q) use ($request) {
                    $src = $request->input('source');
                    $q->where('data_source', 'LIKE', "%{$src}%")
                      ->orWhere('source', 'LIKE', "%{$src}%");
                });
            }

            $totalFilteredAirports = (clone $airportQuery)->count();

            $airports = $airportQuery->orderByRaw("CASE WHEN region IS NULL THEN 99 ELSE 1 END")
                ->orderBy('region')
                ->orderByRaw("CASE WHEN iata_code IS NULL THEN 1 ELSE 0 END")
                ->orderBy('iata_code')
                ->orderBy('name')
                ->paginate(100)
                ->withQueryString();

            // ── 2. Airlines Query & Dynamic Filters ──────────────────────────────
            $airlineQuery = Airline::query();

            if ($request->filled('al_search')) {
                $s = trim($request->input('al_search'));
                $airlineQuery->where(function ($q) use ($s) {
                    $q->where('airline_code', 'LIKE', "%{$s}%")
                      ->orWhere('organization_code', 'LIKE', "%{$s}%")
                      ->orWhere('airline_name', 'LIKE', "%{$s}%")
                      ->orWhere('country', 'LIKE', "%{$s}%");
                });
            }

            if ($request->filled('category') && $request->input('category') !== 'all') {
                $airlineQuery->where('category', $request->input('category'));
            }

            if ($request->filled('al_status') && $request->input('al_status') !== 'all') {
                $airlineQuery->where('status', $request->input('al_status'));
            }

            if ($request->filled('al_source') && $request->input('al_source') !== 'all') {
                $airlineQuery->where('source', 'LIKE', "%{$request->input('al_source')}%");
            }

            $airlines = $airlineQuery->orderBy('category')->orderBy('airline_code')->get();

            // ── 3. High-Level Summary Statistics (Optimized Grouped DB Queries) ──
            $apCounts = Airport::where('is_active', true)
                ->selectRaw("management_type, COUNT(*) as aggregate_count")
                ->groupBy('management_type')
                ->pluck('aggregate_count', 'management_type');

            $totalAirports = $apCounts->sum();
            $intlAirports  = Airport::where('is_active', true)->where('is_international', true)->count();

            $alCounts = Airline::where('is_active', true)
                ->selectRaw("category, COUNT(*) as aggregate_count")
                ->groupBy('category')
                ->pluck('aggregate_count', 'category');

            $totalAirlines  = $alCounts->sum();
            $activeAirlines = Airline::where('is_active', true)->where('status', 'active')->count();

            $stats = [
                'total_airports'       => $totalAirports,
                'ap_airports'          => $apCounts[Airport::MANAGEMENT_INJOURNEY] ?? 0,
                'upt_hubud_airports'   => $apCounts[Airport::MANAGEMENT_UPBU_HUBUD] ?? 0,
                'upt_pemda_airports'   => $apCounts[Airport::MANAGEMENT_UPTD_PEMDA] ?? 0,
                'tni_airports'         => $apCounts[Airport::MANAGEMENT_TNI] ?? 0,
                'missionaris_airports' => $apCounts[Airport::MANAGEMENT_MISSIONARIS] ?? 0,
                'bumn_airports'        => $apCounts[Airport::MANAGEMENT_BUMN] ?? 0,
                'swasta_airports'      => $apCounts[Airport::MANAGEMENT_SWASTA] ?? 0,
                'masyarakat_airports'  => $apCounts[Airport::MANAGEMENT_MASYARAKAT] ?? 0,
                'other_airports'       => $apCounts[Airport::MANAGEMENT_OTHER] ?? 0,
                'int_airports'         => $intlAirports,
                'dom_airports'         => max(0, $totalAirports - $intlAirports),

                'total_airlines'       => $totalAirlines,
                'dom_airlines'         => $alCounts['domestic'] ?? 0,
                'int_airlines'         => $alCounts['international'] ?? 0,
                'cargo_airlines'       => $alCounts['cargo'] ?? 0,
                'charter_airlines'     => $alCounts['charter'] ?? 0,
                'active_airlines'      => $activeAirlines,
            ];

            return view('master-data.index', compact('airports', 'airlines', 'tab', 'stats', 'totalFilteredAirports'));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ], 500);
        }
    }
}
