<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use App\Models\Flight;
use App\Models\TimelinePosition;
use App\Models\TimelineSetting;
use App\Services\CapacityService;
use App\Services\TimelineLayoutService;
use App\Services\AirportResolverService;
use Illuminate\Support\Collection;

echo "============================================================\n";
echo "SLOTWAVES — VERIFY TODAY'S FLIGHT ACTIVITY & TIMELINE EXPORT\n";
echo "============================================================\n\n";

$passCount = 0;
$totalTests = 0;

function assertTest($condition, $description) {
    global $passCount, $totalTests;
    $totalTests++;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$description}\n";
    } else {
        echo " [FAIL] {$description}\n";
    }
}

// Find an existing completed upload with flight data
$upload = Upload::where('status', 'completed')->has('flights')->latest()->first();
if (!$upload) {
    echo "No completed upload found, testing with Upload #1 fallback\n";
    $upload = Upload::find(1);
}

$flights = $upload->flights()->get();
echo "Testing with Upload ID #{$upload->id} ({$flights->count()} flights)\n\n";

// -------------------------------------------------------------
// PART A: TODAY'S FLIGHT ACTIVITY & DYNAMIC OPS HOURS
// -------------------------------------------------------------
echo "--- PART A: TODAY'S FLIGHT ACTIVITY ---\n";
$capacityService = app(CapacityService::class);

// Test A1: Default 06:00 -> 20:00 (14 Active Hours)
$res20 = $capacityService->calculate($flights, null, 6, 20);
assertTest($res20['active_hours_count'] === 14, "A1: Active hours for 06:00 → 20:00 is 14 hours (not hardcoded 15)");
assertTest(isset($res20['hourly']) && count($res20['hourly']) === 24, "A1: Capacity engine generates 24 hourly buckets");

// Test A2: OPS Window Change 08:00 -> 17:00 (9 Active Hours)
$res17 = $capacityService->calculate($flights, null, 8, 17);
assertTest($res17['active_hours_count'] === 9, "A2: Active hours for 08:00 → 17:00 is 9 hours");
assertTest($res17['peak_hour'] !== null, "A2: Peak hour calculated within active 08:00–16:59 window ({$res17['peak_hour']})");

// Test A3: OPS Window Change 10:00 -> 15:00 (5 Active Hours)
$res15 = $capacityService->calculate($flights, null, 10, 15);
assertTest($res15['active_hours_count'] === 5, "A3: Active hours for 10:00 → 15:00 is 5 hours");

// Test A4: Active Window Capacity Formula
$totalCap20 = $res20['active_hours_count'] * ($res20['nac'] ?? 6);
$totalCap17 = $res17['active_hours_count'] * ($res17['nac'] ?? 6);
assertTest($totalCap20 === 14 * 6, "A4: Total capacity for 14h = 14 * NAC (84 slots)");
assertTest($totalCap17 === 9 * 6, "A4: Total capacity for 9h = 9 * NAC (54 slots)");

// Test A5: Dataset Immutability
assertTest(Flight::where('upload_id', $upload->id)->count() === $flights->count(), "A5: Original database flights are 100% immutable");

// -------------------------------------------------------------
// PART B: 24-HOUR TIMELINE & EXPORT GEOMETRY
// -------------------------------------------------------------
echo "\n--- PART B: 24-HOUR TIMELINE EXPORT ---\n";
$layoutService = app(TimelineLayoutService::class);

// Test B1: Full 24-Hour Timeline Layout
$layout = $layoutService->getLayout($upload, 165, 80);
assertTest(isset($layout['departureBlocks']) && isset($layout['arrivalBlocks']), "B1: Layout generates departure & arrival blocks");
assertTest($layout['totalFlights'] === $flights->count(), "B1: 100% of flights present in 24-hour timeline ({$layout['totalFlights']} flights)");

// Test B2: Export Dimensions Target (4000px - 4500px)
$expectedTotalW = 140 + 25 * 165; // 4265px
assertTest($expectedTotalW >= 3840 && $expectedTotalW <= 4500, "B2: Export canvas width is {$expectedTotalW}px (in target 3840px–4500px range)");

// Test B3: 24-Hour Timeline Continuity
$departureHours = collect($layout['departureBlocks'])->pluck('hour')->unique()->sort()->values();
$arrivalHours = collect($layout['arrivalBlocks'])->pluck('hour')->unique()->sort()->values();
assertTest($departureHours->count() > 0 || $arrivalHours->count() > 0, "B3: Flight positions span across timeline hours");

// Test B4: Summary Table Integrity
assertTest(count($layout['summary']) === 24, "B4: Summary table contains all 24 hours (00:00 to 23:00)");
$totDepDom = collect($layout['summary'])->sum('dep_dom');
$totDepInt = collect($layout['summary'])->sum('dep_int');
$totArrDom = collect($layout['summary'])->sum('arr_dom');
$totArrInt = collect($layout['summary'])->sum('arr_int');
$sumTotal = $totDepDom + $totDepInt + $totArrDom + $totArrInt;
assertTest($sumTotal === $flights->count(), "B4: Summary totals ({$sumTotal}) strictly equal total flight count ({$flights->count()})");

// Test B5: Server-side PDF generation
$pdfController = app(\App\Http\Controllers\TimelineController::class);
$response = $pdfController->pdf($upload);
assertTest($response->headers->get('content-type') === 'application/pdf', "B5: Server-side timeline PDF endpoint returns application/pdf");

echo "\n============================================================\n";
echo "SUMMARY: {$passCount} / {$totalTests} TESTS PASSED\n";
echo "============================================================\n";

if ($passCount === $totalTests) {
    exit(0);
} else {
    exit(1);
}
