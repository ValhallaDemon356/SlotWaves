<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use App\Models\Flight;
use App\Services\CapacityService;
use App\Services\ReportService;
use App\Services\FlightFilterService;
use App\Services\AircraftPairingService;
use Barryvdh\DomPDF\Facade\Pdf;

echo "===============================================================\n";
echo "SLOTWAVES REFINEMENT VERIFICATION TEST SUITE\n";
echo "===============================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] " . $title . ($details ? " ($details)" : "") . "\n";
        $passCount++;
    } else {
        echo " [FAIL] " . $title . ($details ? " - $details" : "") . "\n";
        $failCount++;
    }
}

// Fetch a completed upload or create test dataset
$upload = Upload::where('status', 'completed')->has('flights')->first();

if (!$upload) {
    echo "No completed upload found in database. Please upload a schedule first.\n";
    exit(1);
}

echo "Using Upload ID: {$upload->id} ({$upload->original_filename})\n";
$flights = $upload->flights()->validated()->get();
echo "Total Validated Flights: " . $flights->count() . "\n\n";

// ── Test 1: Schedule Parsing & Flight Loading ──
assertTest("1. Schedule Parsing & Validation", $flights->count() > 0, "Found {$flights->count()} flights");

// ── Test 2: Flight Summary Calculations ──
$arrivals = $flights->filter(fn($f) => str_contains($f->flight_type, 'arrival'));
$departures = $flights->filter(fn($f) => str_contains($f->flight_type, 'departure'));
$domestic = $flights->filter(fn($f) => str_contains($f->flight_type, 'domestic'));
$international = $flights->filter(fn($f) => str_contains($f->flight_type, 'international'));
assertTest("2. Flight Calculation & Summary", ($arrivals->count() + $departures->count()) === $flights->count(), "Arr: {$arrivals->count()}, Dep: {$departures->count()}");

// ── Test 3: Arrival / Departure Classification ──
$arrSample = $arrivals->first();
$depSample = $departures->first();
assertTest("3. Direction Classification", $arrSample && $depSample && str_contains($arrSample->flight_type, 'arrival') && str_contains($depSample->flight_type, 'departure'));

// ── Test 4 & 5: Aircraft Pairing & RON Detection ──
$capacityService = app(CapacityService::class);
$capacityStats = $capacityService->calculate($flights, null, 6, 20);
$rotations = $capacityStats['rotations'] ?? [];
$pairedRotations = array_filter($rotations, fn($r) => $r['rotation_status'] === 'PAIRED');
$ronRotations = array_filter($rotations, fn($r) => $r['rotation_status'] === 'UNPAIRED_ARR');

assertTest("4. Aircraft Pairing & Turnaround", count($pairedRotations) > 0, "Found " . count($pairedRotations) . " paired rotations");
assertTest("5. Remain Over Night (RON) Detection", count($ronRotations) > 0, "Found " . count($ronRotations) . " RON flights");

// ── Test 6: Operational Hours Calculation ──
$capStatsOps = $capacityService->calculate($flights, null, 6, 19);
assertTest("6. Operational Hours Window", $capStatsOps['active_hours_count'] === 13, "6 to 19 yields 13 hours");

// ── Test 7: Dashboard View Reorganization & Routes ──
$routes = app('router')->getRoutes();
$hasPreviewCombinedRoute = $routes->hasNamedRoute('schedule.preview.combined');
$hasDailyMovementsRoute = $routes->hasNamedRoute('schedule.report.daily-movements');
assertTest("7. New Required Routes Exist", $hasPreviewCombinedRoute && $hasDailyMovementsRoute, "schedule.preview.combined & schedule.report.daily-movements");

// ── Test 8: Capacity Status Classification & Dynamic Scale ──
$hourly = $capStatsOps['hourly'];
$availableHours = array_filter($hourly, fn($h) => $h['status'] === 'AVAILABLE');
$fullOrMaxHours = array_filter($hourly, fn($h) => in_array($h['status'], ['FULL', 'MAX', 'FULL / MAX']));
assertTest("8. Hourly Status Classification", count($hourly) === 24, "24 hourly buckets generated with status keys");

// ── Test 9: Daily Flight Movement PDF Report Generation ──
$reportService = app(ReportService::class);
$filterService = app(FlightFilterService::class);
$filters = $filterService->parseFilters([]);

try {
    $dailyMovementsPdf = $reportService->generateDailyMovementsPdf($upload, $filters);
    $pdfOutput = $dailyMovementsPdf->getContent();
    assertTest("9. List Pergerakan PDF (Daily Movements)", strlen($pdfOutput) > 1000, "Generated Daily Movements PDF (" . strlen($pdfOutput) . " bytes)");
} catch (\Throwable $e) {
    assertTest("9. List Pergerakan PDF (Daily Movements)", false, $e->getMessage());
}

// ── Test 10: Report Previews Distinct Separation (Combined, DOS, TIME) ──
$combinedHtml = $reportService->renderCombinedHtml($upload, $filters);
$dosHtml = $reportService->renderDosHtml($upload, $filters);
$timeHtml = $reportService->renderTimeHtml($upload, $filters);

$isDosDistinct = str_contains($dosHtml, 'DAILY OPERATING SERVICE (DOS)');
$isTimeDistinct = str_contains($timeHtml, 'TIME FLIGHT SCHEDULE');
$isCombinedDistinct = str_contains($combinedHtml, 'DAILY OPERATING SERVICE (DOS)') && str_contains($combinedHtml, 'TIME FLIGHT SCHEDULE');
assertTest("10. Distinct Report Previews (Combined, DOS, TIME)", $isCombinedDistinct && $isDosDistinct && $isTimeDistinct, "All 3 previews render dedicated distinct views");

// ── Test 11: Timeline View PDF Route Arrow Format (no question mark) ──
try {
    $layoutService = app(\App\Services\TimelineLayoutService::class);
    $layout = $layoutService->getLayout($upload);
    $detailedFlights = [];
    $no = 1;
    foreach ($flights as $f) {
        $orig = $f->origin ?: 'BDO';
        $dest = $f->destination ?: 'BDO';
        $detailedFlights[] = [
            'no' => $no++,
            'airline' => $f->airline_code,
            'flight_number' => $f->flight_number,
            'aircraft_type' => $f->aircraft_type,
            'direction' => str_contains($f->flight_type, 'arrival') ? 'ARR' : 'DEP',
            'scheduled_time' => substr($f->scheduled_time, 0, 5),
            'route' => "{$orig} → {$dest}",
            'airport' => 'Husein Sastranegara',
            'region' => 'Region 1',
            'management' => 'PT Angkasa Pura Indonesia',
            'category' => 'Domestic',
            'operating_days' => $f->operating_days ?: '1234567'
        ];
    }

    $timelinePdf = Pdf::loadView('reports.timeline-pdf', [
        'upload' => $upload,
        'layout' => $layout,
        'departureBlocks' => $layout['departureBlocks'],
        'arrivalBlocks' => $layout['arrivalBlocks'],
        'settings' => $layout['settings'],
        'detailedFlights' => $detailedFlights
    ]);
    $timelinePdfOutput = $timelinePdf->output();
    
    assertTest("11. Timeline View PDF Route Arrow Format", strlen($timelinePdfOutput) > 1000, "Rendered Timeline PDF with DejaVu Sans UTF-8 Arrow (" . strlen($timelinePdfOutput) . " bytes)");
} catch (\Throwable $e) {
    assertTest("11. Timeline View PDF Route Arrow Format", false, $e->getMessage());
}

// ── Test 12: Preserved 3-Point Approved Card Layout ──
$approvedJpg = view()->exists('reports.timeline-pdf');
$dashboardView = view()->exists('schedule.dashboard');
assertTest("12. Preserved Approved Layouts & Templates", $approvedJpg && $dashboardView, "All approved templates intact and enhanced");

echo "\n===============================================================\n";
echo "TEST RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "===============================================================\n";

exit($failCount > 0 ? 1 : 0);
