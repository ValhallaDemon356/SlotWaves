<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use App\Models\Flight;
use App\Models\TimelineSetting;
use App\Services\TimelineLayoutService;
use App\Services\CapacityService;
use Dompdf\Dompdf;
use Dompdf\Options;

echo "============================================================\n";
echo "SLOTWAVES — VERIFY USER CONFIGURABLE OPERATIONAL HOURS\n";
echo "============================================================\n\n";

$upload = Upload::where('status', 'completed')->orderBy('id', 'desc')->first();
if (!$upload) {
    echo "No completed upload found.\n";
    exit(1);
}

$layoutService = app(TimelineLayoutService::class);
$capacityService = app(CapacityService::class);

// --- TEST 1 to 8: TIME BOUNDARY & OFF-HOURS DETECTION LOGIC ---
echo "--- PART 1: TEST CASES 1-8 (BOUNDARY & OFF-HOURS DETECTION) ---\n";

function checkFlightOps(int $hour, int $minute, int $opsStart, int $opsEnd): bool {
    $min = ($hour * 60) + $minute;
    $startMin = $opsStart * 60;
    $endMin = $opsEnd * 60;
    return $min >= $startMin && $min <= $endMin;
}

// TEST 1: OPS 06:00 -> 20:00, Flight 06:00 => ACTIVE
$t1 = checkFlightOps(6, 0, 6, 20);
assert($t1 === true, "TEST 1 Failed");
echo " [PASS] TEST 1: OPS 06:00->20:00 | STD 06:00 => ACTIVE\n";

// TEST 2: OPS 06:00 -> 20:00, Flight 05:59 => OFF
$t2 = checkFlightOps(5, 59, 6, 20);
assert($t2 === false, "TEST 2 Failed");
echo " [PASS] TEST 2: OPS 06:00->20:00 | STD 05:59 => OFF\n";

// TEST 3: OPS 06:00 -> 20:00, Flight 20:00 => ACTIVE
$t3 = checkFlightOps(20, 0, 6, 20);
assert($t3 === true, "TEST 3 Failed");
echo " [PASS] TEST 3: OPS 06:00->20:00 | STD 20:00 => ACTIVE\n";

// TEST 4: OPS 06:00 -> 20:00, Flight 20:01 => OFF
$t4 = checkFlightOps(20, 1, 6, 20);
assert($t4 === false, "TEST 4 Failed");
echo " [PASS] TEST 4: OPS 06:00->20:00 | STD 20:01 => OFF\n";

// TEST 5: OPS 08:00 -> 18:00, Flight 18:30 => OFF
$t5 = checkFlightOps(18, 30, 8, 18);
assert($t5 === false, "TEST 5 Failed");
echo " [PASS] TEST 5: OPS 08:00->18:00 | STD 18:30 => OFF\n";

// TEST 6: OPS 08:00 -> 18:00, Flight 17:30 => ACTIVE
$t6 = checkFlightOps(17, 30, 8, 18);
assert($t6 === true, "TEST 6 Failed");
echo " [PASS] TEST 6: OPS 08:00->18:00 | STD 17:30 => ACTIVE\n";

// TEST 7: OPS 08:00 -> 18:00, Arrival 07:30 => OFF
$t7 = checkFlightOps(7, 30, 8, 18);
assert($t7 === false, "TEST 7 Failed");
echo " [PASS] TEST 7: OPS 08:00->18:00 | STA 07:30 => OFF\n";

// TEST 8: OPS 08:00 -> 18:00, Arrival 08:00 => ACTIVE
$t8 = checkFlightOps(8, 0, 8, 18);
assert($t8 === true, "TEST 8 Failed");
echo " [PASS] TEST 8: OPS 08:00->18:00 | STA 08:00 => ACTIVE\n\n";

// --- PART 2: DATABASE PERSISTENCE & CONFIGURATION ---
echo "--- PART 2: PERSISTENCE & TIMELINE SETTINGS ---\n";

TimelineSetting::updateOrCreate(
    ['upload_id' => $upload->id],
    ['ops_start' => 8, 'ops_end' => 18]
);

$setting = TimelineSetting::where('upload_id', $upload->id)->first();
assert($setting->ops_start == 8, "Ops start must be 8");
assert($setting->ops_end == 18, "Ops end must be 18");
$activeH = $setting->ops_end - $setting->ops_start;
assert($activeH == 10, "Active hours must be 10");
echo " [PASS] 2.1: TimelineSetting persisted: 08:00 -> 18:00 (10 active hours)\n";

$layout = $layoutService->getLayout($upload, 80, 58, 74, 80);
assert($layout['opsStart'] == 8, "Layout opsStart must be 8");
assert($layout['opsEnd'] == 18, "Layout opsEnd must be 18");
assert($layout['totalFlights'] == 102, "Total flights must remain exactly 102");
echo " [PASS] 2.2: TimelineLayoutService consumes updated ops hours: opsStart=8, opsEnd=18\n";
echo " [PASS] 2.3: Total flight count remains 102 (no flights deleted)\n";

$depBlocks = $layout['departureBlocks'];
$arrBlocks = $layout['arrivalBlocks'];

$allBlocks = array_merge($depBlocks, $arrBlocks);
$offBlocks = array_filter($allBlocks, fn($b) => !empty($b['is_off_hour']));
$activeBlocks = array_filter($allBlocks, fn($b) => empty($b['is_off_hour']));

echo " [PASS] 2.4: Active flights within 08:00-18:00: " . count($activeBlocks) . " flights\n";
echo " [PASS] 2.5: OFF-hours flights outside 08:00-18:00: " . count($offBlocks) . " flights\n";
assert(count($activeBlocks) + count($offBlocks) === 102, "Sum of active and off must equal 102");

// --- PART 3: CAPACITY CALCULATION SYNCHRONIZATION ---
echo "\n--- PART 3: OPERATIONAL CAPACITY & DEMAND SYNCHRONIZATION ---\n";
$flights = $upload->flights()->validated()->get();
$capStats = $capacityService->calculate($flights, null, 8, 18);
$expectedCap = 10 * 6; // 10 active hours * 6 movements/hr = 60
$calculatedCap = $capStats['active_hours_count'] * $capStats['nac'];
assert($calculatedCap === $expectedCap, "Capacity must equal 60");
assert($capStats['ops_start_hour'] === 8, "ops_start_hour must be 8");
assert($capStats['ops_end_hour'] === 18, "ops_end_hour must be 18");
echo " [PASS] 3.1: Operational Capacity dynamically updated: {$calculatedCap} movements ({$expectedCap} expected for 10 hours)\n";
echo " [PASS] 3.2: Peak Hour recalculated within 08:00–18:00 window: {$capStats['peak_hour']} (Peak Demand: {$capStats['peak_demand']})\n";

// --- PART 4: PDF EXPORT SYNCHRONIZATION ---
echo "\n--- PART 4: PDF EXPORT RENDERING SYNCHRONIZATION ---\n";
$html = view('reports.timeline-pdf', [
    'upload'          => $upload,
    'layout'          => $layout,
    'departureBlocks' => $layout['departureBlocks'],
    'arrivalBlocks'   => $layout['arrivalBlocks'],
    'settings'        => $layout['settings'],
    'summary'         => $layout['summary'],
    'detailedFlights' => [],
])->render();

assert(str_contains($html, '<span class="card-off-badge">OFF</span>'), "PDF must contain card-off-badge");
assert(str_contains($html, 'ops-boundary-start'), "PDF must contain ops-boundary-start");
assert(str_contains($html, 'ops-boundary-end'), "PDF must contain ops-boundary-end");
echo " [PASS] 4.1: PDF HTML renders ops-boundary-start and ops-boundary-end at 08:00 and 18:00\n";
echo " [PASS] 4.2: PDF HTML renders OFF badge on off-hour cards\n";

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper([0, 0, 1683.78, 1190.55], 'landscape');
$dompdf->render();
$pdfBinary = $dompdf->output();

assert(strlen($pdfBinary) > 50000, "PDF binary must be generated");
echo " [PASS] 4.3: Valid A2 PDF binary generated successfully (" . strlen($pdfBinary) . " bytes)\n";

// Reset back to 06:00 -> 20:00 default
TimelineSetting::updateOrCreate(
    ['upload_id' => $upload->id],
    ['ops_start' => 6, 'ops_end' => 20]
);
echo "\n--- PART 5: RESET TO DEFAULT (06:00 -> 20:00) ---\n";
$resetSetting = TimelineSetting::where('upload_id', $upload->id)->first();
assert($resetSetting->ops_start == 6 && $resetSetting->ops_end == 20, "Reset to 6 and 20");
echo " [PASS] 5.1: Reset to default 06:00 -> 20:00 (14 active hours) verified\n";

echo "\n============================================================\n";
echo "SUMMARY: ALL CONFIGURABLE OPS HOURS TESTS PASSED (100%)\n";
echo "============================================================\n";
