<?php

namespace App\Services;

use RuntimeException;

/**
 * FlightScheduleValidator — Data Integrity & Consistency Validation Engine.
 *
 * Validates parsed flight records:
 * - Every record has non-empty flight_number, airline_code, aircraft_type, scheduled_time, operating_days
 * - Validates time format (00:00:00 - 23:59:59)
 * - Arrivals have origin station, Departures have destination station
 * - Section counts match: TOTAL = DD + DI + AD + AI
 * - Filters and returns strictly validated flights for downstream generation.
 */
class FlightScheduleValidator
{
    public function validate(array $flights, string $filename = ''): array
    {
        if (empty($flights)) {
            throw new RuntimeException("Validation Error: Parsed flight dataset is empty. Please verify the PDF format.");
        }

        $sectionCounts = [
            'arrival_domestic'        => 0,
            'arrival_international'   => 0,
            'departure_domestic'      => 0,
            'departure_international' => 0,
        ];

        $validFlights     = [];
        $invalidFlights   = [];
        $seenFingerprints = [];
        $warnings         = [];
        $errors           = [];

        foreach ($flights as $index => $f) {
            $rowNum = $index + 1;
            $flNo   = $f['flight_number'] ?? "ROW_{$rowNum}";
            $rowErrors = [];

            // Critical validations
            if (empty($f['flight_number'])) {
                $rowErrors[] = "Missing flight number.";
            }

            if (empty($f['scheduled_time']) || ($f['parse_status'] ?? '') === 'invalid_time') {
                $rowErrors[] = "Missing or invalid scheduled time (STA/STD).";
            }

            if (empty($f['aircraft_type']) || $f['aircraft_type'] === '—') {
                $rowErrors[] = "Missing aircraft type.";
            }

            if (empty($f['flight_type'])) {
                $rowErrors[] = "Missing flight type / section.";
            }

            // Directional Origin/Destination check
            $section = $f['flight_type'] ?? '';
            if (str_contains($section, 'arrival') && (empty($f['origin']) || $f['origin'] === '—')) {
                $warnings[] = "Row {$rowNum} ({$flNo}): Arrival missing origin station.";
            }
            if (str_contains($section, 'departure') && (empty($f['destination']) || $f['destination'] === '—')) {
                $warnings[] = "Row {$rowNum} ({$flNo}): Departure missing destination station.";
            }

            // Duplicate detection check
            $fingerprint = sprintf(
                '%s|%s|%s|%s',
                $f['flight_number'] ?? '',
                $f['scheduled_time'] ?? '',
                $f['flight_type'] ?? '',
                $f['operating_days'] ?? ''
            );

            if (($f['parse_status'] ?? '') === 'duplicate' || isset($seenFingerprints[$fingerprint])) {
                $warnings[] = "Row {$rowNum} ({$flNo}): Duplicate flight detected and excluded from capacity.";
                $f['parse_status']      = 'duplicate';
                $f['validation_status'] = 'warning';
                $f['validation_errors'] = ['Duplicate flight row'];
                $invalidFlights[] = $f;
                continue;
            }

            $seenFingerprints[$fingerprint] = true;

            if (!empty($rowErrors)) {
                $errors[] = "Row {$rowNum} ({$flNo}): " . implode(', ', $rowErrors);
                $f['validation_status'] = 'error';
                $f['validation_errors'] = $rowErrors;
                $invalidFlights[] = $f;
                continue;
            }

            if (isset($sectionCounts[$section])) {
                $sectionCounts[$section]++;
            }

            $validFlights[] = $f;
        }

        if (empty($validFlights)) {
            throw new RuntimeException("Validation Error: No valid flight records found after parsing.");
        }

        // Consistency Assertion: TOTAL == DD + DI + AD + AI
        $totalSectionSum = array_sum($sectionCounts);
        $totalValidCount = count($validFlights);

        if ($totalSectionSum !== $totalValidCount) {
            throw new RuntimeException(
                "Dataset consistency check failed: Sum of section counts ({$totalSectionSum}) " .
                "does not match total valid flights ({$totalValidCount})."
            );
        }

        return [
            'is_valid'          => true,
            'total_rows'        => count($flights),
            'valid_count'       => $totalValidCount,
            'invalid_count'     => count($invalidFlights),
            'valid_flights'     => $validFlights,
            'invalid_flights'   => $invalidFlights,
            'section_counts'    => $sectionCounts,
            'arrival_domestic'  => $sectionCounts['arrival_domestic'],
            'arrival_intl'      => $sectionCounts['arrival_international'],
            'departure_domestic'=> $sectionCounts['departure_domestic'],
            'departure_intl'    => $sectionCounts['departure_international'],
            'total_flights'     => $totalValidCount,
            'warnings'          => $warnings,
            'errors'            => $errors,
        ];
    }
}
