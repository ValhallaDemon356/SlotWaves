<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\Upload;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * ReportService — generates TIME and DOS flight schedule reports as PDF or HTML preview.
 *
 * Source of truth: flights table (original schedule data).
 * Accepts filter parameters via FlightFilterService so exported reports match active UI filters.
 */
class ReportService
{
    public function __construct(private FlightFilterService $filterService) {}

    /**
     * Render Combined Flight Schedule as a Blade view (HTML string, for preview).
     */
    public function renderCombinedHtml(Upload $upload, array $filters = []): string
    {
        [$flights, $airport] = $this->load($upload, $filters);
        $dosGroups = $this->buildDosGroups($flights);

        return view('reports.combined', [
            'upload'    => $upload,
            'airport'   => $airport,
            'flights'   => $flights,
            'section'   => 'combined',
            'dosGroups' => $dosGroups,
        ])->render();
    }

    /**
     * Render TIME Flight Schedule as a Blade view (HTML string, for preview).
     */
    public function renderTimeHtml(Upload $upload, array $filters = []): string
    {
        [$flights, $airport] = $this->load($upload, $filters);

        return view('reports.combined', [
            'upload'    => $upload,
            'airport'   => $airport,
            'flights'   => $flights,
            'section'   => 'time',
            'dosGroups' => collect(),
        ])->render();
    }

    /**
     * Render DOS report as a Blade view (HTML string, for preview).
     */
    public function renderDosHtml(Upload $upload, array $filters = []): string
    {
        [$flights, $airport] = $this->load($upload, $filters);
        $dosGroups = $this->buildDosGroups($flights);

        return view('reports.combined', [
            'upload'    => $upload,
            'airport'   => $airport,
            'flights'   => $flights,
            'section'   => 'dos',
            'dosGroups' => $dosGroups,
        ])->render();
    }

    /**
     * Render Daily Flight Movement Report as a Blade view (HTML string).
     */
    public function renderDailyMovementsHtml(Upload $upload, array $filters = []): string
    {
        [$flights, $airport] = $this->load($upload, $filters);

        return view('reports.daily-movements', [
            'upload'  => $upload,
            'airport' => $airport,
            'flights' => $flights,
            'filters' => $filters,
        ])->render();
    }

    /**
     * Generate ONE combined PDF (TIME → page break → DOS) and return it as a
     * downloadable Response stream.
     */
    public function generateCombinedPdf(Upload $upload, array $filters = []): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        [$flights, $airport] = $this->load($upload, $filters);
        $dosGroups = $this->buildDosGroups($flights);

        $pdf = Pdf::loadView('reports.combined', [
            'upload'    => $upload,
            'airport'   => $airport,
            'flights'   => $flights,
            'section'   => 'combined',
            'dosGroups' => $dosGroups,
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'sans-serif',
            'chroot' => public_path(),
        ]);

        $filename = $this->buildFilename($upload, $airport, 'Combined_Flight_Schedule');

        return $pdf->download($filename);
    }

    /**
     * Generate Dedicated Daily Flight Movement PDF Report and return it as a
     * downloadable Response stream.
     */
    public function generateDailyMovementsPdf(Upload $upload, array $filters = []): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        [$flights, $airport] = $this->load($upload, $filters);

        $pdf = Pdf::loadView('reports.daily-movements', [
            'upload'  => $upload,
            'airport' => $airport,
            'flights' => $flights,
            'filters' => $filters,
        ])
        ->setPaper('a4', 'landscape')
        ->setOptions([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'sans-serif',
            'chroot' => public_path(),
        ]);

        $filename = $this->buildFilename($upload, $airport, 'Daily_Flight_Movement');

        return $pdf->download($filename);
    }

    /**
     * Get report-specific data structure for Combined preview.
     */
    public function getCombinedPreviewData(Upload $upload, array $filters = []): array
    {
        [$flights, $airport] = $this->load($upload, $filters);
        $dosGroups = $this->buildDosGroups($flights);

        return [
            'type'      => 'COMBINED',
            'upload'    => $upload,
            'airport'   => $airport,
            'flights'   => $flights,
            'dosGroups' => $dosGroups,
            'total'     => $flights->count(),
        ];
    }

    /**
     * Get report-specific data structure for DOS preview.
     */
    public function getDosPreviewData(Upload $upload, array $filters = []): array
    {
        [$flights, $airport] = $this->load($upload, $filters);
        $dosGroups = $this->buildDosGroups($flights);

        return [
            'type'      => 'DOS',
            'upload'    => $upload,
            'airport'   => $airport,
            'dosGroups' => $dosGroups,
            'total'     => $flights->count(),
        ];
    }

    /**
     * Get report-specific data structure for TIME preview.
     */
    public function getTimePreviewData(Upload $upload, array $filters = []): array
    {
        [$flights, $airport] = $this->load($upload, $filters);

        return [
            'type'       => 'TIME',
            'upload'     => $upload,
            'airport'    => $airport,
            'arrivals'   => $flights->filter(fn($f) => str_contains($f->flight_type, 'arrival'))->sortBy('scheduled_time')->values(),
            'departures' => $flights->filter(fn($f) => str_contains($f->flight_type, 'departure'))->sortBy('scheduled_time')->values(),
            'total'      => $flights->count(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load filtered flights for the upload, sorted by section then scheduled time.
     * Also resolves the airport from upload or filename.
     *
     * @return array{0: Collection, 1: Airport|null}
     */
    private function load(Upload $upload, array $filters = []): array
    {
        $flights = $this->filterService->getFilteredFlights($upload, $filters);
        $airport = $this->resolveAirport($upload);

        return [$flights, $airport];
    }

    /**
     * Public helper to resolve airport from upload relationship or original filename.
     */
    public function resolveAirportPublic(Upload $upload): ?Airport
    {
        return $this->resolveAirport($upload);
    }

    private function resolveAirport(Upload $upload): ?Airport
    {
        if ($upload->airport) {
            return $upload->airport;
        }

        // Match 2–4 uppercase letters that look like an IATA/ICAO code in the filename
        if (preg_match('/\b([A-Z]{3,4})\b/', strtoupper($upload->original_filename), $m)) {
            $airport = Airport::findByIata($m[1]);
            if ($airport) {
                return $airport;
            }
        }

        // Try all tokens
        $tokens = preg_split('/[\s\-_\.]+/', strtoupper($upload->original_filename));
        foreach ($tokens as $token) {
            if (strlen($token) === 3 && ctype_alpha($token)) {
                $airport = Airport::findByIata($token);
                if ($airport) {
                    return $airport;
                }
            }
        }

        return null;
    }

    /**
     * Group flights by their operating_days pattern.
     * Returns a sorted Collection of ['label' => string, 'flights' => Collection].
     */
    private function buildDosGroups(Collection $flights): Collection
    {
        $groups = [];

        foreach ($flights as $flight) {
            $days = $flight->operating_days ?? '1234567';
            if (!isset($groups[$days])) {
                $groups[$days] = [];
            }
            $groups[$days][] = $flight;
        }

        // Sort: ALL DOS first (1234567), then others alphabetically by key
        uksort($groups, function ($a, $b) {
            if ($a === '1234567') return -1;
            if ($b === '1234567') return  1;
            return strcmp($a, $b);
        });

        $result = collect();
        foreach ($groups as $days => $groupFlights) {
            $result->push([
                'days'    => $days,
                'label'   => $this->operatingDaysLabel($days),
                'flights' => collect($groupFlights)->sortBy('scheduled_time')->values(),
            ]);
        }

        return $result;
    }

    /**
     * Convert numeric operating_days string to human-readable label.
     */
    public function operatingDaysLabel(string $days): string
    {
        $map = [
            '1' => 'MON',
            '2' => 'TUE',
            '3' => 'WED',
            '4' => 'THU',
            '5' => 'FRI',
            '6' => 'SAT',
            '7' => 'SUN',
        ];

        if ($days === '1234567') {
            return 'ALL DOS';
        }

        $parts = [];
        for ($i = 0; $i < strlen($days); $i++) {
            $d = $days[$i];
            if (isset($map[$d])) {
                $parts[] = $map[$d];
            }
        }

        return implode(' / ', $parts);
    }

    private function buildFilename(Upload $upload, ?Airport $airport, string $reportType = 'Flight_Schedule'): string
    {
        $iata = $airport ? $airport->iata_code : 'SCH';
        $date = now()->format('Y-m-d');
        return "SlotWaves_{$iata}_{$reportType}_{$date}.pdf";
    }
}
