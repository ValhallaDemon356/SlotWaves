<?php

namespace App\Services;

use App\Models\Upload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * FlightFilterService — Single source of truth for filtering flight schedule queries.
 *
 * Used by:
 * - Generated Schedule Dashboard
 * - Summary Statistics calculation
 * - Operational Capacity calculation
 * - TIME Report (Preview & Download)
 * - DOS Report (Preview & Download)
 * - Combined PDF Export
 */
class FlightFilterService
{
    public function __construct(
        private DosFilterService $dosFilterService
    ) {}

    public function buildQuery(Upload $upload, array $filters = []): Builder
    {
        $query = \App\Models\Flight::query()->where('upload_id', $upload->id)->validated();

        // 1. Season Filter (summer / winter)
        $season = strtolower(trim($filters['season'] ?? 'all'));
        if ($season !== '' && $season !== 'all') {
            $uploadSeason = strtolower($upload->season ?? 'summer');
            if ($uploadSeason !== $season) {
                return $query->whereRaw('1 = 0');
            }
        }

        // 2. Branch / Airport Filter (CGK, BDO, HLP, KJT)
        $branch = strtoupper(trim($filters['branch'] ?? 'all'));
        if ($branch !== '' && $branch !== 'ALL') {
            $uploadAirportCode = strtoupper($upload->airport?->iata_code ?? '');
            if ($uploadAirportCode !== '' && $uploadAirportCode !== $branch) {
                return $query->whereRaw('1 = 0');
            }
        }

        // 3. DOS Filter (Single-day containment vs Multi-day exact pattern)
        $dosInput = $filters['dos'] ?? 'all';
        $normalizedDos = $this->dosFilterService->normalizeDos($dosInput);

        if ($normalizedDos !== '') {
            if (strlen($normalizedDos) === 1) {
                // Single day selection: containment match (e.g., DOS 1 matches "1", "1357", "1234567")
                $query->where('operating_days', 'LIKE', "%{$normalizedDos}%");
            } else {
                // Multi-day selection or Daily: exact pattern match (e.g., DOS 246 matches ONLY "246")
                $query->where('operating_days', '=', $normalizedDos);
            }
        }

        // 4. Flight Direction Filter (all / arrivals / departures)
        $flightDir = strtolower(trim($filters['flight'] ?? 'all'));
        if ($flightDir === 'arrivals' || $flightDir === 'arrival') {
            $query->where('flight_type', 'LIKE', 'arrival%');
        } elseif ($flightDir === 'departures' || $flightDir === 'departure') {
            $query->where('flight_type', 'LIKE', 'departure%');
        }

        // Search flight number, airline, or station
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('flight_number', 'LIKE', "%{$search}%")
                  ->orWhere('airline_code', 'LIKE', "%{$search}%")
                  ->orWhere('origin', 'LIKE', "%{$search}%")
                  ->orWhere('destination', 'LIKE', "%{$search}%");
            });
        }

        // 5. D / I Filter (all / domestic / international)
        $di = strtolower(trim($filters['di'] ?? 'all'));
        if ($di === 'domestic' || $di === 'dom') {
            $query->where('flight_type', 'LIKE', '%domestic');
        } elseif ($di === 'international' || $di === 'int') {
            $query->where('flight_type', 'LIKE', '%international');
        }

        return $query;
    }

    /**
     * Get filtered flights collection ordered by section and scheduled_time.
     */
    public function getFilteredFlights(Upload $upload, array $filters = []): Collection
    {
        return $this->buildQuery($upload, $filters)
            ->with(['airline', 'originAirport', 'destinationAirport', 'pairedFlight'])
            ->orderByRaw("CASE flight_type WHEN 'arrival_domestic' THEN 1 WHEN 'arrival_international' THEN 2 WHEN 'departure_domestic' THEN 3 WHEN 'departure_international' THEN 4 ELSE 5 END")
            ->orderBy('scheduled_time')
            ->get();
    }

    /**
     * Normalize and extract clean filter array from request inputs.
     */
    public function parseFilters(array $input): array
    {
        return [
            'season' => strtolower(trim($input['season'] ?? 'all')),
            'dos'    => $input['dos'] ?? 'all',
            'branch' => strtoupper(trim($input['branch'] ?? 'all')),
            'flight' => strtolower(trim($input['flight'] ?? 'all')),
            'di'     => strtolower(trim($input['di'] ?? 'all')),
            'search' => trim($input['search'] ?? ''),
        ];
    }

    /**
     * Helper delegate for normalizing DOS strings.
     */
    public function normalizeDos($dos): ?string
    {
        $normalized = $this->dosFilterService->normalizeDos($dos);
        return $normalized === '' ? null : $normalized;
    }
}
