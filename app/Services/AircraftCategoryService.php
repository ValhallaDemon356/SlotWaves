<?php

namespace App\Services;

/**
 * AircraftCategoryService — Centralized aircraft classification and capacity units resolver.
 *
 * Classifications:
 * - NB  : Narrow Body (A319, A320, B737, B738, ATR72, D328, etc.)
 * - WB  : Wide Body (A330, B777, B787, B747, etc.)
 * - CNB : Cargo Narrow Body (B737F, B738F, etc.)
 * - CWB : Cargo Wide Body (B767F, B777F, B747F, A330F, etc.)
 * - UNKNOWN : Unrecognized type (graceful fallback)
 */
class AircraftCategoryService
{
    public const NB      = 'NB';
    public const WB      = 'WB';
    public const CNB     = 'CNB';
    public const CWB     = 'CWB';
    public const UNKNOWN = 'UNKNOWN';

    /**
     * Normalize raw aircraft string and classify into an aircraft category.
     */
    public function classify(?string $rawAircraftType): string
    {
        if (empty($rawAircraftType)) {
            return self::UNKNOWN;
        }

        $upperRaw   = strtoupper(trim($rawAircraftType));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $upperRaw);

        if ($normalized === '') {
            return self::UNKNOWN;
        }

        $isCargo = str_contains($upperRaw, 'CARGO') ||
                   str_contains($upperRaw, 'FREIGHT') ||
                   preg_match('/[0-9]F$/', $normalized);

        // Check Wide Body patterns
        $isWideBody = preg_match('/^(A330|A340|A350|A380|B777|B77W|B787|B788|B789|B747|B744|B767)/', $normalized);

        if ($isCargo) {
            return $isWideBody ? self::CWB : self::CNB;
        }

        if ($isWideBody) {
            return self::WB;
        }

        // Check Narrow Body & Turboprop patterns (A319, A320, B738, ATR72, D328, COMAC, ARJ21, C208, etc.)
        $isNarrowBodyOrTurboprop = preg_match('/^(A3|B73|ATR|D32|DASH|DH8|MA60|CN235|C208|MD80|MD90|E190|E195|COMAC|ARJ21)/', $normalized);

        if ($isNarrowBodyOrTurboprop) {
            return self::NB;
        }

        return self::UNKNOWN;
    }

    /**
     * Get configured capacity units for category.
     */
    public function getCapacityUnits(string $category): int
    {
        $unitsConfig = config('slotwaves.aircraft_capacity_units', []);
        return (int) ($unitsConfig[$category] ?? 1);
    }

    /**
     * Get human-readable label for category.
     */
    public function getCategoryLabel(string $category): string
    {
        return match ($category) {
            self::NB      => 'Narrow Body',
            self::WB      => 'Wide Body',
            self::CNB     => 'Cargo Narrow Body',
            self::CWB     => 'Cargo Wide Body',
            default       => 'Unknown',
        };
    }

    /**
     * Get compact badge label for category.
     */
    public function getCategoryBadge(string $category): string
    {
        return match ($category) {
            self::NB      => 'NB',
            self::WB      => 'WB',
            self::CNB     => 'CNB',
            self::CWB     => 'CWB',
            default       => 'UNK',
        };
    }

    /**
     * Determine operation type string (PASSENGER vs CARGO).
     */
    public function getOperationType(?string $rawAircraftType): string
    {
        return $this->isCargoType($rawAircraftType) ? 'CARGO' : 'PASSENGER';
    }

    /**
     * Check if raw aircraft type string represents a cargo aircraft.
     */
    public function isCargoType(?string $rawAircraftType): bool
    {
        if (empty($rawAircraftType)) {
            return false;
        }
        $upper = strtoupper(trim($rawAircraftType));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $upper);

        return str_contains($upper, 'CARGO') ||
               str_contains($upper, 'FREIGHT') ||
               str_contains($upper, 'CARRIER') ||
               preg_match('/[0-9]F\b/', $upper) ||
               preg_match('/[0-9]F$/', $normalized);
    }

    /**
     * Determine if a flight object, array, or type string is a Cargo flight.
     * Uses existing fields: category, flight_type, aircraft_type, remarks, raw_data.
     */
    public function isCargoFlight(mixed $flight): bool
    {
        if (empty($flight)) {
            return false;
        }

        if (is_string($flight)) {
            return $this->isCargoType($flight);
        }

        // Object or array extraction
        $aircraftType = is_object($flight) ? ($flight->aircraft_type ?? '') : ($flight['aircraft_type'] ?? '');
        $flightType   = is_object($flight) ? ($flight->flight_type ?? '') : ($flight['flight_type'] ?? '');
        $remarks      = is_object($flight) ? ($flight->remarks ?? '') : ($flight['remarks'] ?? '');
        $category     = is_object($flight) ? ($flight->category ?? '') : ($flight['category'] ?? '');
        $rawData      = is_object($flight) ? ($flight->raw_data ?? '') : ($flight['raw_data'] ?? '');

        if ($this->isCargoType($aircraftType)) {
            return true;
        }

        $upperFlightType = strtoupper((string) $flightType);
        if (str_contains($upperFlightType, 'CARGO') || str_contains($upperFlightType, 'FREIGHT')) {
            return true;
        }

        $upperRemarks = strtoupper((string) $remarks);
        if (str_contains($upperRemarks, 'CARGO') || str_contains($upperRemarks, 'FREIGHT')) {
            return true;
        }

        $upperCategory = strtoupper((string) $category);
        if (str_contains($upperCategory, 'CARGO') || str_contains($upperCategory, 'CNB') || str_contains($upperCategory, 'CWB')) {
            return true;
        }

        if (is_string($rawData) && (str_contains(strtoupper($rawData), 'CARGO') || str_contains(strtoupper($rawData), 'FREIGHT'))) {
            return true;
        }

        return false;
    }

    /**
     * Get passenger capacity units (Cargo aircraft always return 0 units for passenger capacity).
     */
    public function getPassengerCapacityUnits(string $category): int
    {
        if ($category === self::CNB || $category === self::CWB) {
            return 0;
        }
        return $this->getCapacityUnits($category);
    }
}
