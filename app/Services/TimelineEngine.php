<?php

namespace App\Services;

use App\Models\Upload;
use App\Models\TimelinePosition;
use App\Models\Flight;

/**
 * TimelineEngine — converts parsed flights into pre-computed timeline positions.
 */
class TimelineEngine
{
    // Assign block colors per flight type as per specification
    private const COLORS = [
        'departure_domestic'      => '#1E3A8A', // Dark Blue
        'departure_international' => '#60A5FA', // Light Blue
        'arrival_domestic'        => '#D97706', // Dark Yellow/Orange
        'arrival_international'   => '#FDE047', // Light Yellow
    ];

    private const SECTION_MAP = [
        'departure_domestic'      => 'departure',
        'departure_international' => 'departure',
        'arrival_domestic'        => 'arrival',
        'arrival_international'   => 'arrival',
    ];

    // Default block duration in minutes (visual width of each slot block)
    private const BLOCK_DURATION = 30;

    public function build(Upload $upload, $flights = null): void
    {
        // Clear old positions for this upload (re-run safe)
        TimelinePosition::where('upload_id', $upload->id)->delete();

        // If not provided, fetch flights for this upload
        if ($flights === null) {
            $flights = $upload->flights()->orderBy('scheduled_time')->get();
        }

        // Track stacking: ['section:hour' => currentRow]
        $stackTracker = [];

        $positions = [];
        $now = now();

        foreach ($flights as $flight) {
            $section = self::SECTION_MAP[$flight->flight_type] ?? 'departure';
            $color   = self::COLORS[$flight->flight_type] ?? '#64748B';

            // Parse scheduled_time → hour + offset
            [$hour, $minute] = $this->parseTime($flight->scheduled_time);

            // Stacking
            $stackKey = "{$section}:{$hour}";
            if (!isset($stackTracker[$stackKey])) {
                $stackTracker[$stackKey] = 0;
            }
            $row = $stackTracker[$stackKey];
            $stackTracker[$stackKey]++;

            $positions[] = [
                'upload_id'        => $upload->id,
                'flight_id'        => $flight->id,
                'hour'             => $hour,
                'row'              => $row,
                'offset_minutes'   => $minute,
                'color_hex'        => $color,
                'duration_minutes' => self::BLOCK_DURATION,
                'section'          => $section,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        if (!empty($positions)) {
            foreach (array_chunk($positions, 100) as $chunk) {
                TimelinePosition::insert($chunk);
            }
        }
    }

    private function parseTime(string $time): array
    {
        $parts  = explode(':', $time);
        $hour   = (int)($parts[0] ?? 0);
        $minute = (int)($parts[1] ?? 0);
        return [min($hour, 23), min($minute, 59)];
    }
}
