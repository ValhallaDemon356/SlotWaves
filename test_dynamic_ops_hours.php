<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Airport;
use App\Models\Flight;
use App\Models\TimelineSetting;
use App\Models\Upload;
use App\Services\CapacityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

echo "============================================================\n";
echo "SLOTWAVES — DYNAMIC OPS HOURS & RECALCULATION VERIFICATION\n";
echo "============================================================\n\n";

$passed = 0;
$total = 0;

function assertCondition($condition, $description, &$passed, &$total) {
    $total++;
    if ($condition) {
        $passed++;
        echo " [PASS] $description\n";
    } else {
        echo " [FAIL] $description\n";
    }
}

// 1. Setup sample flight schedule
$upload = Upload::create([
    'original_filename' => 'TEST_DYNAMIC_OPS.pdf',
    'stored_path' => 'uploads/test_dynamic_ops.pdf',
    'status' => 'completed',
    'total_rows' => 10,
    'valid_rows' => 10,
    'airport_iata' => 'BDO',
]);

// Clear previous test flights if any
Flight::where('upload_id', $upload->id)->delete();
TimelineSetting::where('upload_id', $upload->id)->delete();

// Create sample flights at various hours:
// Hour 05: 1 departure (05:30)
// Hour 07: 2 arrivals (07:10, 07:45), 1 departure (07:30) => 3 movements
// Hour 10: 2 arrivals (10:15, 10:45), 2 departures (10:00, 10:50) => 4 movements
// Hour 15: 5 arrivals (15:00, 15:10, 15:20, 15:30, 15:40), 4 departures (15:05, 15:15, 15:25, 15:35) => 9 movements (PEAK at 15:00)
// Hour 18: 1 arrival (18:15), 1 departure (18:45) => 2 movements
// Hour 19: 1 arrival (19:20) => 1 movement (outside 06-19 window)
// Hour 21: 1 departure (21:00) => 1 movement

$flightsData = [
    ['flight_number' => 'GA101', 'flight_type' => 'departure', 'scheduled_time' => '05:30'],
    ['flight_number' => 'JT750', 'flight_type' => 'arrival',   'scheduled_time' => '07:10'],
    ['flight_number' => 'JT751', 'flight_type' => 'departure', 'scheduled_time' => '07:30'],
    ['flight_number' => 'QG201', 'flight_type' => 'arrival',   'scheduled_time' => '07:45'],
    ['flight_number' => 'ID601', 'flight_type' => 'departure', 'scheduled_time' => '10:00'],
    ['flight_number' => 'ID602', 'flight_type' => 'arrival',   'scheduled_time' => '10:15'],
    ['flight_number' => 'ID603', 'flight_type' => 'arrival',   'scheduled_time' => '10:45'],
    ['flight_number' => 'ID604', 'flight_type' => 'departure', 'scheduled_time' => '10:50'],
    ['flight_number' => 'PK001', 'flight_type' => 'arrival',   'scheduled_time' => '15:00'],
    ['flight_number' => 'PK002', 'flight_type' => 'departure', 'scheduled_time' => '15:05'],
    ['flight_number' => 'PK003', 'flight_type' => 'arrival',   'scheduled_time' => '15:10'],
    ['flight_number' => 'PK004', 'flight_type' => 'departure', 'scheduled_time' => '15:15'],
    ['flight_number' => 'PK005', 'flight_type' => 'arrival',   'scheduled_time' => '15:20'],
    ['flight_number' => 'PK006', 'flight_type' => 'departure', 'scheduled_time' => '15:25'],
    ['flight_number' => 'PK007', 'flight_type' => 'arrival',   'scheduled_time' => '15:30'],
    ['flight_number' => 'PK008', 'flight_type' => 'departure', 'scheduled_time' => '15:35'],
    ['flight_number' => 'PK009', 'flight_type' => 'arrival',   'scheduled_time' => '15:40'],
    ['flight_number' => 'IW301', 'flight_type' => 'arrival',   'scheduled_time' => '18:15'],
    ['flight_number' => 'IW302', 'flight_type' => 'departure', 'scheduled_time' => '18:45'],
    ['flight_number' => 'SJ501', 'flight_type' => 'arrival',   'scheduled_time' => '19:20'],
    ['flight_number' => 'SJ502', 'flight_type' => 'departure', 'scheduled_time' => '21:00'],
];

foreach ($flightsData as $fd) {
    Flight::create([
        'upload_id' => $upload->id,
        'flight_number' => $fd['flight_number'],
        'flight_type' => $fd['flight_type'],
        'scheduled_time' => $fd['scheduled_time'],
        'origin' => 'CGK',
        'destination' => 'BDO',
        'aircraft_type' => 'B738',
        'airline_name' => 'Test Airline',
        'operating_days' => '1234567',
        'validation_status' => 'valid',
        'parse_status' => 'valid',
    ]);
}

$flights = Flight::where('upload_id', $upload->id)->get();
$capacityService = app(CapacityService::class);

echo "TEST CASE 1: Default OPS Hours (06:00 → 19:00)\n";
$resDefault = $capacityService->calculate($flights, null, 6, 19);
assertCondition($resDefault['active_hours_count'] === 13, "Active hours count is 19 - 6 = 13 (not hardcoded 15)", $passed, $total);
assertCondition($resDefault['peak_demand'] === 9, "Peak demand is 9 movements in 06:00–19:00 window", $passed, $total);
assertCondition($resDefault['peak_hour'] === '15:00–15:59', "Peak hour is 15:00–15:59", $passed, $total);

echo "\nTEST CASE 2: OPS Hours Change to 06:00 → 18:00\n";
$res0618 = $capacityService->calculate($flights, null, 6, 18);
assertCondition($res0618['active_hours_count'] === 12, "Active hours count is 18 - 6 = 12", $passed, $total);
assertCondition($res0618['peak_demand'] === 9, "Peak demand remains 9 (15:00 is inside 06:00–17:59)", $passed, $total);

echo "\nTEST CASE 3: OPS Hours Change to 08:00 → 17:00\n";
$res0817 = $capacityService->calculate($flights, null, 8, 17);
assertCondition($res0817['active_hours_count'] === 9, "Active hours count is 17 - 8 = 9", $passed, $total);
assertCondition($res0817['peak_demand'] === 9, "Peak demand is 9 at 15:00–15:59", $passed, $total);

echo "\nTEST CASE 4: OPS Hours Change to 05:00 → 20:00\n";
$res0520 = $capacityService->calculate($flights, null, 5, 20);
assertCondition($res0520['active_hours_count'] === 15, "Active hours count is 20 - 5 = 15", $passed, $total);

echo "\nTEST CASE 5: Recalculate Peak Window when Window Excludes 15:00 (e.g. 06:00 → 14:00)\n";
$res0614 = $capacityService->calculate($flights, null, 6, 14);
assertCondition($res0614['active_hours_count'] === 8, "Active hours count is 14 - 6 = 8", $passed, $total);
assertCondition($res0614['peak_demand'] === 4, "Peak demand shifts from 9 to 4 (Hour 10 is the new peak in 06:00–13:59)", $passed, $total);
assertCondition($res0614['peak_hour'] === '10:00–10:59', "Peak hour shifts to 10:00–10:59", $passed, $total);

echo "\nTEST CASE 6: Operational Capacity Recalculation (Total Capacity = Active Hours * NAC)\n";
$nac = $resDefault['nac']; // 6
$totalCapDefault = $resDefault['active_hours_count'] * $nac; // 13 * 6 = 78
$totalCap0817 = $res0817['active_hours_count'] * $nac; // 9 * 6 = 54
assertCondition($totalCapDefault === 78, "Total capacity for 13 hours = 78 slots", $passed, $total);
assertCondition($totalCap0817 === 54, "Total capacity for 9 hours = 54 slots", $passed, $total);

echo "\nTEST CASE 7: Backend API PATCH /timeline/{upload}/ops-hours\n";
$timelineController = app(App\Http\Controllers\TimelineController::class);

// Test invalid: start >= end
$reqInvalid = Request::create("/timeline/{$upload->id}/ops-hours", 'PATCH', [
    'ops_start' => '18:00',
    'ops_end'   => '08:00',
]);
$respInvalid = $timelineController->saveOpsHours($reqInvalid, $upload);
assertCondition($respInvalid->getStatusCode() === 422, "API rejects invalid start >= end with 422 Unprocessable", $passed, $total);
$jsonInvalid = json_decode($respInvalid->getContent(), true);
assertCondition($jsonInvalid['message'] === 'End time must be later than start time.', "Returns exact error: 'End time must be later than start time.'", $passed, $total);

// Test valid: string format "08:00" and "17:00"
$reqValid = Request::create("/timeline/{$upload->id}/ops-hours", 'PATCH', [
    'ops_start' => '08:00',
    'ops_end'   => '17:00',
]);
$respValid = $timelineController->saveOpsHours($reqValid, $upload);
assertCondition($respValid->getStatusCode() === 200, "API accepts valid time strings with 200 OK", $passed, $total);
$jsonValid = json_decode($respValid->getContent(), true);
assertCondition($jsonValid['active_hours'] === 9 && $jsonValid['ops_start'] === '08:00' && $jsonValid['ops_end'] === '17:00', "API returns formatted start, end, and 9 active hours", $passed, $total);

// Verify database persistence
$savedSetting = TimelineSetting::where('upload_id', $upload->id)->first();
assertCondition($savedSetting && $savedSetting->ops_start === 8 && $savedSetting->ops_end === 17, "TimelineSetting persisted ops_start = 8, ops_end = 17 in database", $passed, $total);

echo "\nTEST CASE 8: Dashboard Controller Inherits Saved Database Settings & Query Params\n";
$dashboardController = app(App\Http\Controllers\DashboardController::class);

// Dashboard view with saved settings (08:00–17:00)
$reqDashSaved = Request::create("/schedule/{$upload->id}/dashboard", 'GET');
$respDashSaved = $dashboardController->show($reqDashSaved, $upload);
$viewDataSaved = $respDashSaved->getData();
assertCondition($viewDataSaved['stats']['ops_start'] === '08:00' && $viewDataSaved['stats']['ops_end'] === '17:00' && $viewDataSaved['stats']['active_hours'] === 9, "Dashboard reads persisted 08:00 → 17:00 and 9 Active Hours", $passed, $total);

// Dashboard view with explicit query param overrides
$reqDashQuery = Request::create("/schedule/{$upload->id}/dashboard?ops_start=07:00&ops_end=21:00", 'GET');
$respDashQuery = $dashboardController->show($reqDashQuery, $upload);
$viewDataQuery = $respDashQuery->getData();
assertCondition($viewDataQuery['stats']['ops_start'] === '07:00' && $viewDataQuery['stats']['ops_end'] === '21:00' && $viewDataQuery['stats']['active_hours'] === 14, "Dashboard prioritizes query params 07:00 → 21:00 (14 Active Hours)", $passed, $total);

echo "\nTEST CASE 9: Dataset Immutability (All flights preserved in database)\n";
$remainingFlights = Flight::where('upload_id', $upload->id)->count();
assertCondition($remainingFlights === count($flightsData), "Flight database records are 100% immutable (all {$remainingFlights} flights intact)", $passed, $total);

echo "\nTEST CASE 10: 24-Hour Timeline & PDF Generation Continuity\n";
$reportService = app(App\Services\ReportService::class);
$htmlPreview = $reportService->renderTimeHtml($upload, ['ops_start' => '08:00', 'ops_end' => '17:00']);
assertCondition(str_contains($htmlPreview, 'GA101') && str_contains($htmlPreview, 'PK001') && str_contains($htmlPreview, 'SJ502'), "PDF Report renders flights across full timeline regardless of OPS window", $passed, $total);

echo "\n============================================================\n";
echo "SUMMARY: $passed / $total TESTS PASSED (" . round(($passed / $total) * 100) . "%)\n";
echo "============================================================\n";

// Cleanup test upload
Flight::where('upload_id', $upload->id)->delete();
TimelineSetting::where('upload_id', $upload->id)->delete();
$upload->delete();

exit($passed === $total ? 0 : 1);
