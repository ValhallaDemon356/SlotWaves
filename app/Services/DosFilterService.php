<?php

namespace App\Services;

/**
 * DosFilterService — Reusable DOS (Days of Operation) normalization and filtering engine.
 *
 * Business Rules:
 * - SINGLE DAY SELECTION (e.g. "1" or "2"):
 *   Matches any flight operating on that day (containment check: selectedDay ∈ flightDos).
 *   Example: DOS 1 matches "1", "1357", "1234567", "12", etc.
 *
 * - MULTI-DAY / EXACT PATTERN SELECTION (e.g. "2,4,6" or "1,3,5,7"):
 *   Matches ONLY flights whose normalized DOS is EXACTLY identical to the selected DOS pattern.
 *   Example: DOS 246 matches ONLY "246" (hides "1234567", "2", "24", "1246").
 *
 * - DAILY SELECTION ("Daily" or "1234567"):
 *   Matches ONLY flights whose normalized DOS is EXACTLY "1234567".
 */
class DosFilterService
{
    /**
     * Normalize a DOS string or array into a numerically sorted digit string ('1234567', '246', '1357').
     * Returns empty string if empty or 'all'.
     */
    public function normalizeDos($dos): string
    {
        if (is_array($dos)) {
            $dos = implode('', $dos);
        }

        $dos = strtolower(trim((string) $dos));

        if ($dos === '' || $dos === 'all') {
            return '';
        }

        if ($dos === 'daily') {
            return '1234567';
        }

        preg_match_all('/[1-7]/', $dos, $m);

        if (empty($m[0])) {
            return '';
        }

        $digits = array_unique($m[0]);
        sort($digits);

        return implode('', $digits);
    }

    /**
     * Check if a flight's DOS matches single day selection (containment).
     */
    public function matchesSingleDay(string $flightDos, string $selectedDay): bool
    {
        $normalizedFlight   = $this->normalizeDos($flightDos);
        $normalizedSelected = $this->normalizeDos($selectedDay);

        if ($normalizedSelected === '') {
            return true;
        }

        return str_contains($normalizedFlight, $normalizedSelected);
    }

    /**
     * Check if a flight's DOS matches multi-day selection (exact equality).
     */
    public function matchesExactPattern(string $flightDos, string $selectedDos): bool
    {
        $normalizedFlight   = $this->normalizeDos($flightDos);
        $normalizedSelected = $this->normalizeDos($selectedDos);

        if ($normalizedSelected === '') {
            return true;
        }

        return $normalizedFlight === $normalizedSelected;
    }

    /**
     * Master DOS matching rule:
     * - If selected DOS normalizes to empty/all: returns true.
     * - If selected DOS is a single day (length === 1): containment check.
     * - If selected DOS is multi-day (length > 1): exact equality check.
     */
    public function matches(?string $flightDos, ?string $selectedDos): bool
    {
        $normalizedSelected = $this->normalizeDos($selectedDos);

        if ($normalizedSelected === '') {
            return true;
        }

        $normalizedFlight = $this->normalizeDos($flightDos);

        if (strlen($normalizedSelected) === 1) {
            return str_contains($normalizedFlight, $normalizedSelected);
        }

        return $normalizedFlight === $normalizedSelected;
    }
}
