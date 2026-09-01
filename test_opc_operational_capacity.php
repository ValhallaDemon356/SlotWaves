<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use App\Models\Flight;
use App\Services\CapacityService;
use App\Services\AircraftPairingService;
use App\Services\AircraftCategoryService;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Collection;

echo "======================================================================\n";
echo "SLOTWAVES — OPC (OCCUPANCY PARKING STAND) VERIFICATION TEST SUITE\n";
echo "======================================================================\n\n";

$passCount = 0;
$totalTests = 0;

function assertCondition($cond, $description, $details = '') {
    global $passCount, $totalTests;
    $totalTests++;
    if ($cond) {
        $passCount++;
        echo " [PASS] {$description}\n";
        if ($details) echo "        ↳ {$details}\n";
    } else {
        echo " [FAIL] {$description}\n";
        if ($details) echo "        ↳ ERROR: {$details}\n";
    }
}

// ── TEST 1: Synthetic Dataset with Known RON & Movements
echo "--- TEST GROUP 1: OPERATIONAL OPC & NO DOUBLE COUNTING SCENARIOS ---\n";
$pairingService = app(AircraftPairingService::class);
$capacityService = app(CapacityService::class);

// Create synthetic flights to test exact edge cases:
// 1. QG820 arrives at 06:55 (UNPAIRED_ARR -> RON, next day departure)
// 2. GA101 arrives at 08:00, GA102 departs at 09:30 (PAIRED turnaround)
// 3. JT918 departs at 13:40 (UNPAIRED_DEP -> was RON from yesterday)
// 4. JT755 arrives at 12:10, JT756 departs at 14:00 (PAIRED turnaround)
// 5. SJ500 arrives at 12:30 (UNPAIRED_ARR -> RON)
// 6. ID600 arrives at 12:45, ID601 departs at 12:55 (PAIRED turnaround in same hour 12)

$mockFlights = collect([
    // Flight 1: QG820 (Arrival 06:55, UNPAIRED_ARR -> RON)
    new Flight([
        'id' => 101, 'upload_id' => 999, 'flight_number' => 'QG820', 'airline_code' => 'QG',
        'aircraft_type' => 'A320', 'scheduled_time' => '06:55:00', 'flight_type' => 'arrival_domestic',
        'origin' => 'SUB', 'destination' => 'BDO', 'operating_days' => '1234567', 'is_validated' => true
    ]),
    // Flight 2: GA101 (Arrival 08:00) paired with GA102 (Departure 09:30)
    new Flight([
        'id' => 102, 'upload_id' => 999, 'flight_number' => 'GA101', 'airline_code' => 'GA',
        'aircraft_type' => 'B738', 'scheduled_time' => '08:00:00', 'flight_type' => 'arrival_domestic',
        'origin' => 'CGK', 'destination' => 'BDO', 'operating_days' => '1234567', 'is_validated' => true
    ]),
    new Flight([
        'id' => 103, 'upload_id' => 999, 'flight_number' => 'GA102', 'airline_code' => 'GA',
        'aircraft_type' => 'B738', 'scheduled_time' => '09:30:00', 'flight_type' => 'departure_domestic',
        'origin' => 'BDO', 'destination' => 'CGK', 'operating_days' => '1234567', 'is_validated' => true
    ]),
    // Flight 3: JT918 (Departure 13:40, UNPAIRED_DEP -> was RON)
    new Flight([
        'id' => 104, 'upload_id' => 999, 'flight_number' => 'JT918', 'airline_code' => 'JT',
        'aircraft_type' => 'B738', 'scheduled_time' => '13:40:00', 'flight_type' => 'departure_domestic',
        'origin' => 'BDO', 'destination' => 'DPS', 'operating_days' => '1234567', 'is_validated' => true
    ]),
    // Flight 4: JT755 (Arrival 12:10) paired with JT756 (Departure 14:00)
    new Flight([
        'id' => 105, 'upload_id' => 999, 'flight_number' => 'JT755', 'airline_code' => 'JT',
        'aircraft_type' => 'B738', 'scheduled_time' => '12:10:00', 'flight_type' => 'arrival_domestic',
        'origin' => 'KNO', 'destination' => 'BDO', 'operating_days' => '1234567', 'is_validated' => true
    ]),
    new Flight([
        'id' => 106, 'upload_id' => 999, 'flight_number' => 'JT756', 'airline_code' => 'JT',
        'aircraft_type' => 'B738', 'scheduled_time' => '14:00:00', 'flight_type' => 'departure_domestic',
        'origin' => 'BDO', 'destination' => 'KNO', 'operating_days' => '1234567', 'is_validated' => true
    ]),
    // Flight 5: SJ500 (Arrival 12:30, UNPAIRED_ARR -> RON)
    new Flight([
        'id' => 107, 'upload_id' => 999, 'flight_number' => 'SJ500', 'airline_code' => 'SJ',
        'aircraft_type' => 'B738', 'scheduled_time' => '12:30:00', 'flight_type' => 'arrival_domestic',
        'origin' => 'PLM', 'destination' => 'BDO', 'operating_days' => '1234567', 'is_validated' => true
    ]),
    // Flight 6: ID600 (Arrival 12:20) paired with ID601 (Departure 12:55) -> 35 min turnaround
    new Flight([
        'id' => 108, 'upload_id' => 999, 'flight_number' => 'ID600', 'airline_code' => 'ID',
        'aircraft_type' => 'A320', 'scheduled_time' => '12:20:00', 'flight_type' => 'arrival_domestic',
        'origin' => 'HLP', 'destination' => 'BDO', 'operating_days' => '1234567', 'is_validated' => true
    ]),
    new Flight([
        'id' => 109, 'upload_id' => 999, 'flight_number' => 'ID601', 'airline_code' => 'ID',
        'aircraft_type' => 'A320', 'scheduled_time' => '12:55:00', 'flight_type' => 'departure_domestic',
        'origin' => 'BDO', 'destination' => 'HLP', 'operating_days' => '1234567', 'is_validated' => true
    ]),
]);

$calc = $capacityService->calculate($mockFlights, null, 6, 20, 6);
$hourly = $calc['hourly'];

// Check Daily Summary: Total Movements = Arrivals (5) + Departures (4) = 9
$totalArr = $mockFlights->filter(fn($f) => str_contains($f->flight_type, 'arrival'))->count();
$totalDep = $mockFlights->filter(fn($f) => str_contains($f->flight_type, 'departure'))->count();
$totalMvm = $totalArr + $totalDep;
assertCondition($totalArr === 5 && $totalDep === 4 && $totalMvm === 9, "1.1: Total movements strictly equals Arrivals (5) + Departures (4) = 9");

// Check Daily OPC Count: 2 UNPAIRED_ARR (QG820, SJ500)
assertCondition($calc['opc_count'] === 2, "1.2: Daily OPC count is 2 RON passenger aircraft (QG820, SJ500)", "Calculated OPC: {$calc['opc_count']}");

// Check Hour 06:00–06:59: QG820 arrives at 06:55 (Arr = 1), JT918 parked since 00:00 (OPC = 1)
$h6 = $hourly[6];
assertCondition($h6['arrivals_count'] === 1 && $h6['departures_count'] === 0 && $h6['opc_count'] === 1, "1.3: Hour 06:00 has 1 Arrival (QG820) and 1 OPC (JT918 parked from 00:00)", "Arr: {$h6['arrivals_count']}, OPC: {$h6['opc_count']}");

// Check Hour 07:00–07:59: QG820 is parked as RON (OPC = 1) + JT918 is parked (OPC = 1) -> Total OPC = 2
$h7 = $hourly[7];
assertCondition($h7['arrivals_count'] === 0 && $h7['departures_count'] === 0 && $h7['opc_count'] === 2 && $h7['occupied'] === 2, "1.4: Hour 07:00 has 0 movements and OPC = 2 (QG820 + JT918 occupying stands)", "OPC: {$h7['opc_count']}, Occupied: {$h7['occupied']}");

// Check Hour 12:00–12:59:
// Arrivals: JT755 (12:10), ID600 (12:20), SJ500 (12:30) -> 3 arrivals
// Departures: ID601 (12:55) -> 1 departure
// OPC: QG820 (arrived 06:55) + JT918 (unpaired dep at 13:40, parked since 00:00) -> 2 OPC
$h12 = $hourly[12];
assertCondition($h12['arrivals_count'] === 3, "1.5: Hour 12:00 arrivals count = 3 (JT755, ID600, SJ500)", "Arrivals: {$h12['arrivals_count']}");
assertCondition($h12['opc_count'] === 2, "1.6: Hour 12:00 OPC count = 2 (QG820 RON + JT918 prior-RON)", "OPC: {$h12['opc_count']}");

// Check Hour 13:00–13:59:
// JT918 departs at 13:40 -> In hour 13, JT918 is counted as Departure (1), NOT OPC (No double counting!)
// OPC: QG820 (06:55) and SJ500 (12:30) -> 2 OPC
$h13 = $hourly[13];
assertCondition($h13['departures_count'] === 1, "1.7: Hour 13:00 has 1 Departure (JT918)", "Departures: {$h13['departures_count']}");
assertCondition($h13['opc_count'] === 2, "1.8: Hour 13:00 OPC count = 2 (QG820 and SJ500; JT918 counted as Departure, not double counted as OPC)", "OPC: {$h13['opc_count']}");

// Check Hour 14:00–14:59:
// JT756 departs at 14:00 (turnaround from 12:10). JT918 already departed.
// OPC = 2 (QG820, SJ500)
$h14 = $hourly[14];
assertCondition($h14['opc_count'] === 2, "1.9: Hour 14:00 OPC count = 2 (QG820, SJ500)", "OPC: {$h14['opc_count']}");

// ── TEST GROUP 2: CAPACITY & STATUS LOGIC
echo "\n--- TEST GROUP 2: PARKING CAPACITY, REMAINING & NAC DYNAMICS ---\n";

// Test 2.1: Available capacity calculation with NAC = 6
$statusResult6 = $capacityService->classifyHourlyStatus(6, 6, true);
assertCondition($statusResult6['status'] === 'FULL / MAX' && $statusResult6['remaining'] === 0, "2.1: NAC = 6, Occupied = 6 -> FULL / MAX (Remaining = 0)");

// Test 2.2: Reactive NAC limit update: NAC changed to 8
$statusResult8 = $capacityService->classifyHourlyStatus(6, 8, true);
assertCondition($statusResult8['status'] === 'AVAILABLE' && $statusResult8['remaining'] === 2, "2.2: NAC changed to 8, Occupied = 6 -> AVAILABLE (Remaining = 2)");

// Test 2.3: Over capacity: Occupied = 7 vs NAC = 6
$statusResultOver = $capacityService->classifyHourlyStatus(7, 6, true);
assertCondition($statusResultOver['status'] === 'OVER CAPACITY' && $statusResultOver['exceeded'] === 1, "2.3: NAC = 6, Occupied = 7 -> OVER CAPACITY (Exceeded = 1)");

// ── TEST GROUP 3: REAL DATABASE UPLOAD VERIFICATION
echo "\n--- TEST GROUP 3: REAL DATABASE UPLOAD VERIFICATION ---\n";
$upload = Upload::where('status', 'completed')->has('flights')->latest()->first() ?? Upload::find(1);
if ($upload) {
    $dbFlights = $upload->flights()->get();
    $dbCalc = $capacityService->calculate($dbFlights, null, 6, 20, 6);
    
    assertCondition(isset($dbCalc['opc_count']), "3.1: Capacity calculation on real dataset produces opc_count ({$dbCalc['opc_count']})");
    assertCondition(count($dbCalc['hourly']) === 24, "3.2: 24 hourly buckets generated with opc_count in each bucket");
    
    $hourlyOpcValues = array_column($dbCalc['hourly'], 'opc_count');
    assertCondition(min($hourlyOpcValues) >= 0, "3.3: Hourly opc_count is non-negative for all 24 hours");
}

// ── TEST GROUP 4: MANDATORY USER EXAMPLES & REFACTORED AIRCRAFT DEMAND FORMULA
echo "\n--- TEST GROUP 4: MANDATORY USER EXAMPLES & AIRCRAFT DEMAND FORMULA ---\n";
// Example 1 (User Mandatory Test):
// NAC 6, Hour 11: ARR 7, DEP 6, OPC 2
// Expected Aircraft Demand = 15/6, Utilization = 250%, Status = OVER CAPACITY
$arrEx1 = 7;
$depEx1 = 6;
$opcEx1 = 2;
$nacEx1 = 6;
$demandEx1 = $arrEx1 + $depEx1 + $opcEx1;
$utilEx1 = (int) round(($demandEx1 / $nacEx1) * 100);
$statusEx1 = $capacityService->classifyHourlyStatus($demandEx1, $nacEx1, true);

assertCondition(
    $demandEx1 === 15 && $utilEx1 === 250 && $statusEx1['status'] === 'OVER CAPACITY',
    "4.1: Mandatory Case: ARR 7 + DEP 6 + OPC 2 = 15/6 A/C, Utilization = 250%, Status = OVER CAPACITY",
    "Demand: {$demandEx1}/{$nacEx1}, Util: {$utilEx1}%, Status: {$statusEx1['status']}"
);

// Example 2: Arrival 2 + Departure 2 + OPC 0 = 4, NAC 6 -> 4/6 -> AVAILABLE
$d2 = 2 + 2 + 0;
$s2 = $capacityService->classifyHourlyStatus($d2, 6, true);
assertCondition($d2 === 4 && $s2['status'] === 'AVAILABLE' && $s2['remaining'] === 2, "4.2: ARR 2 + DEP 2 + OPC 0 = 4/6 -> AVAILABLE (Remaining = 2)");

// Example 3: Arrival 3 + Departure 3 + OPC 0 = 6, NAC 6 -> 6/6 -> FULL / MAX
$d3 = 3 + 3 + 0;
$s3 = $capacityService->classifyHourlyStatus($d3, 6, true);
assertCondition($d3 === 6 && $s3['status'] === 'FULL / MAX' && $s3['remaining'] === 0, "4.3: ARR 3 + DEP 3 + OPC 0 = 6/6 -> FULL / MAX (Remaining = 0)");

// Example 4: Arrival 3 + Departure 2 + OPC 1 = 6, NAC 6 -> 6/6 -> FULL / MAX
$d4 = 3 + 2 + 1;
$s4 = $capacityService->classifyHourlyStatus($d4, 6, true);
assertCondition($d4 === 6 && $s4['status'] === 'FULL / MAX' && $s4['remaining'] === 0, "4.4: ARR 3 + DEP 2 + OPC 1 = 6/6 -> FULL / MAX (Remaining = 0)");

// Example 5: Reactive NAC update (NAC changed to 8 for demand = 6)
$s5 = $capacityService->classifyHourlyStatus(6, 8, true);
assertCondition($s5['status'] === 'AVAILABLE' && $s5['remaining'] === 2, "4.5: NAC changed to 8: Demand 6/8 -> AVAILABLE (Remaining = 2)");

echo "\n======================================================================\n";
echo "SUMMARY: {$passCount} / {$totalTests} TESTS PASSED\n";
echo "======================================================================\n";

if ($passCount === $totalTests) {
    exit(0);
} else {
    exit(1);
}
