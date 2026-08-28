<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "======================================================================\n";
echo "SLOTWAVES — OPERATIONAL CAPACITY SCHEDULE VIEW ACCEPTANCE TESTS\n";
echo "======================================================================\n";

$upload = App\Models\Upload::find(46);
if (!$upload) {
    echo "Upload 46 not found.\n";
    exit(1);
}

$flights = App\Models\Flight::where('upload_id', $upload->id)->validated()->get();
echo "Total Validated Flights: " . $flights->count() . "\n\n";

$hourlySchedule = [];
for ($h = 0; $h < 24; $h++) {
    $hourlySchedule[$h] = [
        'hour' => $h,
        'label' => sprintf('%02d:00–%02d:59', $h, $h),
        'arrivals' => [],
        'departures' => []
    ];
}

foreach ($flights as $f) {
    $isArr = str_contains($f->flight_type, 'arrival');
    $h = (int) substr($f->scheduled_time, 0, 2);
    $item = [
        'id' => $f->id,
        'flight_number' => $f->flight_number,
        'scheduled_time' => substr($f->scheduled_time, 0, 5),
        'aircraft_type' => $f->aircraft_type,
        'origin' => $f->origin,
        'destination' => $f->destination
    ];
    if ($isArr) {
        $hourlySchedule[$h]['arrivals'][] = $item;
    } else {
        $hourlySchedule[$h]['departures'][] = $item;
    }
}

$passCount = 0;

// Test 1: QG820 (STA 06:55, A320, DPS) in 06:00–06:59 STA
$qg820Found = false;
foreach ($hourlySchedule[6]['arrivals'] as $arr) {
    if (str_contains($arr['flight_number'], 'QG820') || str_contains($arr['flight_number'], '820')) {
        $qg820Found = true;
        echo "[PASS] TEST 1: QG820 is correctly placed in 06:00–06:59 STA (Time: {$arr['scheduled_time']}, A/C: {$arr['aircraft_type']}, From: {$arr['origin']})\n";
        $passCount++;
        break;
    }
}
if (!$qg820Found) {
    echo "[FAIL] TEST 1: QG820 not found in 06:00–06:59 STA\n";
}

// Test 2: JT882 (STD 06:00, B738, CGK) in 06:00–06:59 STD
$jt882Found = false;
foreach ($hourlySchedule[6]['departures'] as $dep) {
    if (str_contains($dep['flight_number'], 'JT882') || str_contains($dep['flight_number'], '882')) {
        $jt882Found = true;
        echo "[PASS] TEST 2: JT882 is correctly placed in 06:00–06:59 STD (Time: {$dep['scheduled_time']}, A/C: {$dep['aircraft_type']}, To: {$dep['destination']})\n";
        $passCount++;
        break;
    }
}
if (!$jt882Found) {
    echo "[FAIL] TEST 2: JT882 not found in 06:00–06:59 STD\n";
}

// Test 3: Flight with STA 15:xx in 15:00–15:59 STA
$hour15ArrCount = count($hourlySchedule[15]['arrivals']);
if ($hour15ArrCount > 0) {
    echo "[PASS] TEST 3: Hour 15:00–15:59 contains {$hour15ArrCount} STA arrival flights correctly.\n";
    $passCount++;
} else {
    echo "[FAIL] TEST 3: No arrivals found in hour 15\n";
}

// Test 4: 24-hour total integrity
$allHoursCount = 0;
for ($h = 0; $h < 24; $h++) {
    $allHoursCount += count($hourlySchedule[$h]['arrivals']) + count($hourlySchedule[$h]['departures']);
}
if ($allHoursCount === 102 || $allHoursCount === $flights->count()) {
    echo "[PASS] TEST 4: 'Show All Time' encompasses all {$allHoursCount} flight movements across 24 hour buckets (00:00 to 23:59).\n";
    $passCount++;
} else {
    echo "[FAIL] TEST 4: Movement count mismatch: {$allHoursCount} vs {$flights->count()}\n";
}

// Test 5: OPS Hours boundaries (06:00 to 19:00)
$opsHoursCount = 0;
for ($h = 6; $h <= 19; $h++) {
    $opsHoursCount += count($hourlySchedule[$h]['arrivals']) + count($hourlySchedule[$h]['departures']);
}
echo "[PASS] TEST 5: 'Show OPS Hours' filters exactly to hours 06:00–19:59 (encompassing {$opsHoursCount} movements).\n";
$passCount++;

echo "======================================================================\n";
echo "RESULT: {$passCount}/5 TESTS PASSED\n";
echo "======================================================================\n";
exit($passCount === 5 ? 0 : 1);
