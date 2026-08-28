<?php

namespace App\Services;

use App\Models\Airline;
use Illuminate\Support\Facades\Log;

/**
 * PdfParser — Universal Aviation Schedule PDF Extractor & Normalizer.
 *
 * Capabilities:
 * - Employs ScheduleTemplateDetector for multi-page (Summer 2018) & single-page landscape (BDO Agustus 2026) formats.
 * - Extracts Flight Number, Airline Code, Aircraft Type, Station IATA, Scheduled Time, and Days of Service (DOS).
 * - Matches Airline with Master Reference Database (`airlines` table).
 * - Resolves Station to 3-letter IATA with Master Reference Database (`airports` table).
 * - Normalizes Time formats: "06:55", "6:55", "0655", "06.55", "06 55" -> "06:55:00".
 * - Normalizes Operating Days: "1 2 3 4 5 6 7", "1 - 3 - 5 - 7", "- 2 - 4 - 6 -", etc.
 * - Detects duplicates and excludes invalid/missing time records from capacity dataset.
 */
class PdfParser
{
    private AirportResolverService $airportResolver;
    private ScheduleTemplateDetector $templateDetector;

    // ── Known aircraft type patterns ─────────────────────────────────────────
    private const AC_TYPES = [
        'ATR\s*72(?:-\d+)?',
        'ATR\s*42(?:-\d+)?',
        'A\s*3[012]\d',
        'A\s*319',
        'A\s*320',
        'A\s*321',
        'A\s*330',
        'B\s*7[3456]\d',
        'B\s*733',
        'B\s*735',
        'B\s*737',
        'B\s*738',
        'B\s*739',
        'B\s*747',
        'B\s*757',
        'B\s*767',
        'B\s*777',
        'COMAC\s*(?:27|ARJ21|C919)?',
        'ARJ\s*21',
        'C\s*208[B]?',
        'C208[B]?',
        'D\s*328',
        'D\s*320',
        'DO\s*328',
        'CRJ\s*\d{3}',
        'DHC\s*[-\s]?\d',
        'E\s*1[79]\d',
        'E\s*190',
        'MA\s*60',
        'CN\s*235',
        'F\s*50',
        'F\s*100',
        'SF\s*340',
        'SAAB\s*340',
        '[A-Z]{1,2}\s*\d{2,4}',
    ];

    public function __construct(
        ?AirportResolverService $airportResolver = null,
        ?ScheduleTemplateDetector $templateDetector = null
    ) {
        $this->airportResolver  = $airportResolver ?? new AirportResolverService();
        $this->templateDetector = $templateDetector ?? new ScheduleTemplateDetector($this->airportResolver);
    }

    private static function acTypePattern(): string
    {
        $alts = implode('|', self::AC_TYPES);
        return '/\b(' . $alts . ')(?:\s*-\s*\d+)?\b/i';
    }

    /**
     * Parse schedule PDF into structured, validated flight array + import summary.
     */
    public function parse(string $filePath): array
    {
        $detectionResult = $this->templateDetector->detect($filePath);
        $blocks = $detectionResult['blocks'];

        $flights          = [];
        $seenFingerprints = [];
        $totalRows        = 0;
        $invalidRows      = 0;
        $duplicateRows    = 0;

        foreach ($blocks as $block) {
            $sectionType = $block['section'];

            foreach ($block['lines'] as $line) {
                $totalRows++;
                $flight = $this->parseRow($line, $sectionType);

                if ($flight === null) {
                    $invalidRows++;
                    continue;
                }

                // Duplicate check
                $fingerprint = sprintf(
                    '%s|%s|%s|%s',
                    $flight['flight_number'],
                    $flight['scheduled_time'],
                    $flight['flight_type'],
                    $flight['operating_days']
                );

                if (isset($seenFingerprints[$fingerprint])) {
                    $duplicateRows++;
                    $flight['parse_status']      = 'duplicate';
                    $flight['validation_status'] = 'warning';
                    $flight['validation_errors'] = ['Duplicate flight row in PDF'];
                    // Keep in staging but mark duplicate
                    $flights[] = $flight;
                    continue;
                }

                $seenFingerprints[$fingerprint] = true;
                $flights[] = $flight;
            }
        }

        return [
            'flights'            => $flights,
            'detection'          => $detectionResult,
            'total_rows'         => $totalRows,
            'valid_rows'         => count(array_filter($flights, fn($f) => ($f['validation_status'] ?? 'valid') === 'valid' && ($f['parse_status'] ?? 'valid') === 'valid')),
            'invalid_rows'       => $invalidRows,
            'duplicate_rows'     => $duplicateRows,
            'parsing_confidence' => $detectionResult['confidence'] * 100,
        ];
    }

    public function parseLineForTesting(string $line, string $section): ?array
    {
        return $this->parseRow($line, $section);
    }

    private function parseRow(string $line, string $section): ?array
    {
        $stripped = preg_replace('/^\d+\s*/', '', trim($line));
        if ($stripped === '') {
            return null;
        }

        // ── Step A: Extract FLIGHT NUMBER & AIRLINE PREFIX ─────────────────
        if (!preg_match('/\b([A-Z0-9]{2})\s?(\d{1,4}[A-Z]?)\b/', $stripped, $flM, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $flightCode   = strtoupper($flM[1][0]);
        $flightDigits = $flM[2][0];
        $flightNumber = $flightCode . $flightDigits;
        $flightEnd    = $flM[0][1] + strlen($flM[0][0]);
        $airlineCode  = $flightCode;

        $rest = substr($stripped, $flightEnd);

        // ── Step B: Extract AIRCRAFT TYPE ─────────────────────────────────
        $acPattern = self::acTypePattern();
        if (!preg_match($acPattern, $rest, $acM, PREG_OFFSET_CAPTURE)) {
            $aircraftType = null;
            $afterAc      = $rest;
        } else {
            $rawAc        = trim($acM[1][0]);
            $aircraftType = $this->normalizeAircraftType($rawAc);
            $afterAc      = substr($rest, $acM[0][1] + strlen($acM[0][0]));
        }

        // ── Step C: Extract SCHEDULED TIME (06:55, 6:55, 06.55, 0655, 06 55)
        $timeParsed = $this->extractTime($afterAc);
        if (!$timeParsed) {
            return null;
        }

        $scheduledTime = $timeParsed['time_formatted'];
        $timeStart     = $timeParsed['offset'];
        $timeLength    = $timeParsed['length'];

        // ── Step D: Extract STATION / ROUTE ───────────────────────────────
        $stationRaw  = trim(substr($afterAc, 0, $timeStart));
        $stationRaw  = trim(preg_replace('/^\d+\s*/', '', $stationRaw));
        $stationIata = $stationRaw !== '' ? $this->airportResolver->getIataCode($stationRaw) : null;

        // ── Step E: Extract OPERATING DAYS (DOS) ───────────────────────────
        $afterTime     = substr($afterAc, $timeStart + $timeLength);
        $operatingDays = $this->extractDos($afterTime);

        // ── Step F: Extract Optional STAND (e.g. "Stand A04", "Stand 03", etc.)
        $stand = null;
        if (preg_match('/\bStand\s*([A-Z0-9]{1,4})\b/i', $afterTime, $standM)) {
            $stand = strtoupper(trim($standM[1]));
        }

        // Direction and Traffic Classification
        $isArrival       = str_contains($section, 'arrival');
        $isInternational = str_contains($section, 'international');

        $direction   = $isArrival ? 'arrival' : 'departure';
        $trafficType = $isInternational ? 'international' : 'domestic';

        $origin      = $isArrival ? ($stationIata ?: '—') : 'BDO';
        $destination = !$isArrival ? ($stationIata ?: '—') : 'BDO';

        // Master Airline Resolution Check
        $airline = Airline::findByCode($airlineCode);
        $parseStatus = 'valid';
        $valStatus   = 'valid';
        $valErrors   = [];

        if (!$airline) {
            $valErrors[] = "Airline code {$airlineCode} not found in master database.";
            $valStatus   = 'warning';
        }

        if ($stationIata === '—' || $stationIata === null) {
            $valErrors[] = "Station '{$stationRaw}' could not be matched to master airport IATA.";
            $valStatus   = 'warning';
        }

        return [
            'flight_number'     => $flightNumber,
            'airline_code'      => $airlineCode,
            'aircraft_type'     => $aircraftType ?: '—',
            'origin'            => $origin,
            'destination'       => $destination,
            'scheduled_time'    => $scheduledTime,
            'operating_days'    => $operatingDays,
            'direction'         => $direction,
            'traffic_type'      => $trafficType,
            'flight_type'       => $section,
            'stand'             => $stand,
            'slot_status'       => 'available',
            'parse_status'      => $parseStatus,
            'validation_status' => $valStatus,
            'validation_errors' => !empty($valErrors) ? $valErrors : null,
            'remarks'           => '',
            'raw_data'          => json_encode([
                'raw_line'    => $line,
                'section'     => $section,
                'raw_station' => $stationRaw,
                'raw_time'    => $timeParsed['raw_text'],
                'raw_dos'     => trim($afterTime),
            ]),
        ];
    }

    /**
     * Extract and normalize time into HH:MM:00 format.
     */
    private function extractTime(string $text): ?array
    {
        // 1. HH:MM or HH.MM or H:MM (e.g. 06:55, 6:55, 06.55, 19:15)
        if (preg_match('/\b(\d{1,2})[.:](\d{2})\b/', $text, $m, PREG_OFFSET_CAPTURE)) {
            $hour = (int) $m[1][0];
            $min  = (int) $m[2][0];
            if ($hour >= 0 && $hour <= 23 && $min >= 0 && $min <= 59) {
                return [
                    'time_formatted' => sprintf('%02d:%02d:00', $hour, $min),
                    'offset'         => $m[0][1],
                    'length'         => strlen($m[0][0]),
                    'raw_text'       => $m[0][0],
                ];
            }
        }

        // 2. 4-digit military time (e.g. 0655, 1915)
        if (preg_match('/\b(\d{2})(\d{2})\b/', $text, $m, PREG_OFFSET_CAPTURE)) {
            $hour = (int) $m[1][0];
            $min  = (int) $m[2][0];
            if ($hour >= 0 && $hour <= 23 && $min >= 0 && $min <= 59) {
                return [
                    'time_formatted' => sprintf('%02d:%02d:00', $hour, $min),
                    'offset'         => $m[0][1],
                    'length'         => strlen($m[0][0]),
                    'raw_text'       => $m[0][0],
                ];
            }
        }

        // 3. Space-separated time (e.g. "06 55")
        if (preg_match('/\b(\d{1,2})\s+(\d{2})\b/', $text, $m, PREG_OFFSET_CAPTURE)) {
            $hour = (int) $m[1][0];
            $min  = (int) $m[2][0];
            if ($hour >= 0 && $hour <= 23 && $min >= 0 && $min <= 59) {
                return [
                    'time_formatted' => sprintf('%02d:%02d:00', $hour, $min),
                    'offset'         => $m[0][1],
                    'length'         => strlen($m[0][0]),
                    'raw_text'       => $m[0][0],
                ];
            }
        }

        return null;
    }

    /**
     * Extract and normalize Days of Service (DOS).
     */
    private function extractDos(string $text): string
    {
        preg_match_all('/[1-7]/', $text, $daysM);
        $dos = implode('', $daysM[0]);
        return $dos !== '' ? $dos : '1234567';
    }

    /**
     * Normalize aircraft type strings.
     */
    private function normalizeAircraftType(string $raw): string
    {
        $upper = strtoupper(trim(preg_replace('/\s+/', ' ', $raw)));

        if (preg_match('/A\s*320/i', $upper)) return 'A320';
        if (preg_match('/A\s*319/i', $upper)) return 'A319';
        if (preg_match('/A\s*321/i', $upper)) return 'A321';
        if (preg_match('/A\s*330/i', $upper)) return 'A330';
        if (preg_match('/B\s*738/i', $upper)) return 'B738';
        if (preg_match('/B\s*737/i', $upper)) return 'B737';
        if (preg_match('/B\s*733/i', $upper)) return 'B733';
        if (preg_match('/B\s*735/i', $upper)) return 'B735';
        if (preg_match('/B\s*739/i', $upper)) return 'B739';
        if (preg_match('/B\s*777/i', $upper)) return 'B777';
        if (preg_match('/ATR\s*72/i', $upper)) return 'ATR72';
        if (preg_match('/ATR\s*42/i', $upper)) return 'ATR42';
        if (preg_match('/D\s*328|DO\s*328/i', $upper)) return 'D328';
        if (preg_match('/COMAC\s*27|COMAC\s*ARJ21|ARJ\s*21/i', $upper)) return 'ARJ21';
        if (preg_match('/C\s*208[B]?|C208[B]?/i', $upper)) return 'C208';

        return preg_replace('/\s+/', '', $upper);
    }
}
