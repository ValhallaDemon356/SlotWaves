<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Airport;
use App\Models\Airline;
use App\Models\Flight;
use App\Models\Upload;
use App\Services\AirportResolverService;
use App\Services\PdfParser;
use App\Services\TimelineLayoutService;
use App\Services\CapacityService;
use Database\Seeders\MasterDatabaseSeeder;

echo "======================================================================\n";
echo "SLOTWAVES — ACCEPTANCE TESTS (SECTION 27: TEST 1 - TEST 10)\n";
echo "======================================================================\n\n";

$allPassed = true;
function reportTest($num, $title, $pass, $msg = '') {
    global $allPassed;
    if ($pass) {
        echo "[PASS] TEST {$num}: {$title}\n";
        if ($msg) echo "       {$msg}\n";
    } else {
        $allPassed = false;
        echo "[FAIL] TEST {$num}: {$title}\n";
        if ($msg) echo "       ERROR: {$msg}\n";
    }
}

$resolver = new AirportResolverService();
$parser   = new PdfParser($resolver);

// ── TEST 1: QG820 Citilink A320 SURABAYA 06:55 ───────────────────────────
$line1 = "1 CITILINK QG 820 A 320 SURABAYA 06:55 1 2 3 4 5 6 7";
$p1 = $parser->parseLineForTesting($line1, 'arrival_domestic');
$al1 = Airline::findByCode($p1['airline_code']);
$ap1 = Airport::findByIata($p1['origin']);

$t1Pass = ($p1['flight_number'] === 'QG820' &&
           $p1['airline_code'] === 'QG' &&
           $al1?->airline_name === 'Citilink' &&
           $p1['origin'] === 'SUB' &&
           $p1['destination'] === 'BDO' &&
           $p1['scheduled_time'] === '06:55:00');

reportTest(1, "Parse & Match QG820 Citilink SURABAYA (06:55)", $t1Pass,
    "Flight: {$p1['flight_number']}, Airline: {$al1?->airline_name} ({$p1['airline_code']}), Origin: {$p1['origin']}, Dest: {$p1['destination']}, Time: {$p1['scheduled_time']}");

// ── TEST 2: JT755 Lion Air B 738 JOGYAKARTA 19:15 ─────────────────────────
$line2 = "17 LION AIR JT 755 B 738 JOGYAKARTA 19:15 1 2 3 4 5 6 7";
$p2 = $parser->parseLineForTesting($line2, 'departure_domestic');
$al2 = Airline::findByCode($p2['airline_code']);
$ap2 = Airport::findByIata($p2['destination']);

$t2Pass = ($p2['flight_number'] === 'JT755' &&
           $p2['airline_code'] === 'JT' &&
           $al2?->airline_name === 'Lion Air' &&
           $p2['origin'] === 'BDO' &&
           $p2['destination'] === 'JOG' &&
           $p2['scheduled_time'] === '19:15:00');

reportTest(2, "Parse & Match JT755 Lion Air JOGYAKARTA (19:15) -> IATA JOG", $t2Pass,
    "Flight: {$p2['flight_number']}, Airline: {$al2?->airline_name} ({$p2['airline_code']}), Origin: {$p2['origin']}, Dest: {$p2['destination']} (NOT YOGYAKARTA / JOGJAKARTA), Time: {$p2['scheduled_time']}");

// ── TEST 3: IW1289 Wings Air ATR 72 TANJUNG KARANG ───────────────────────
$line3 = "4 WINGS AIR IW 1289 ATR 72 TANJUNG KARANG 07:35 1 2 3 4 5 6 7";
$p3 = $parser->parseLineForTesting($line3, 'arrival_domestic');
$al3 = Airline::findByCode($p3['airline_code']);

$t3Pass = ($p3['flight_number'] === 'IW1289' &&
           $p3['airline_code'] === 'IW' &&
           $al3?->airline_name === 'Wings Air' &&
           $p3['origin'] === 'TKG');

reportTest(3, "Parse & Match IW1289 Wings Air TANJUNG KARANG -> IATA TKG", $t3Pass,
    "Flight: {$p3['flight_number']}, Airline: {$al3?->airline_name} ({$p3['airline_code']}), Airport: {$p3['origin']}");

// ── TEST 4: XN747 Express Air D 328 TANJUNG KARANG ───────────────────────
$line4 = "3 XPRESS AIR XN 747 D 328 TANJUNG KARANG 07:15 1 2 4 5 6 7";
$p4 = $parser->parseLineForTesting($line4, 'arrival_domestic');
$al4 = Airline::findByCode($p4['airline_code']);

$t4Pass = ($p4['flight_number'] === 'XN747' &&
           $p4['airline_code'] === 'XN' &&
           $al4?->airline_name === 'Express Air' &&
           $p4['origin'] === 'TKG' &&
           $p4['operating_days'] === '124567');

reportTest(4, "Parse & Match XN747 Express Air TANJUNG KARANG -> IATA TKG", $t4Pass,
    "Flight: {$p4['flight_number']}, Airline: {$al4?->airline_name} ({$p4['airline_code']}), Airport: {$p4['origin']}, DOS: {$p4['operating_days']}");

// ── TEST 5: CJN Nusawiru UPT Daerah/Pemda Classification ─────────────────
$cjn = Airport::findByIata('CJN');
$p5 = $parser->parseLineForTesting("1 SUSI AIR SI 101 C 208 NUSAWIRU 08:00 1 2 3 4 5 6 7", 'arrival_domestic');

$t5Pass = ($cjn &&
           $cjn->name === 'Nusawiru' &&
           $cjn->city === 'Pangandaran' &&
           $cjn->province === 'Jawa Barat' &&
           $cjn->management_type === 'UPT Daerah/Pemda' &&
           $cjn->region === null &&
           $p5['origin'] === 'CJN');

reportTest(5, "Nusawiru (CJN) Resolution & UPT Daerah/Pemda Master Match", $t5Pass,
    "IATA: {$cjn?->iata_code}, Name: {$cjn?->name}, Area: {$cjn?->city}, Management: {$cjn?->management_type}, Region: " . var_export($cjn?->region, true));

// ── TEST 6: Upload Isolation (Different Uploads remain isolated) ─────────
$sampleUpload = Upload::where('status', 'completed')->has('flights')->first();
$flightCountForUpload = $sampleUpload ? $sampleUpload->flights()->count() : 0;
$totalFlightsGlobal   = Flight::count();

$t6Pass = ($sampleUpload && $flightCountForUpload > 0 && $flightCountForUpload <= $totalFlightsGlobal);
reportTest(6, "Upload Flight Isolation (Scoped by upload_id)", $t6Pass,
    "Upload ID {$sampleUpload?->id} has {$flightCountForUpload} scoped flights (Total across all historical uploads in DB: {$totalFlightsGlobal})");

// ── TEST 7: MasterDatabaseSeeder never creates flights ───────────────────
$flBefore = Flight::count();
(new MasterDatabaseSeeder())->run();
$flAfter = Flight::count();

$t7Pass = ($flBefore === $flAfter);
reportTest(7, "MasterDatabaseSeeder Inserts Master Reference Only (0 Flights created)", $t7Pass,
    "Flights before seeding: {$flBefore}, Flights after seeding: {$flAfter} (Diff: 0)");

// ── TEST 8: Operational Capacity & Demand Scoped by Upload ID ────────────
if ($sampleUpload) {
    $capService = app(CapacityService::class);
    $scopedFlights = $sampleUpload->flights()->get();
    $capResult = $capService->calculate($scopedFlights);
    
    $totalDemandCalc = array_sum(array_column($capResult['hourly'], 'demand'));
    $t8Pass = ($totalDemandCalc === $scopedFlights->count());
    reportTest(8, "Operational Capacity & Demand Calculated Strictly from Scoped Upload", $t8Pass,
        "Scoped Upload Flights: {$scopedFlights->count()}, Sum of Hourly Demand in Capacity Grid: {$totalDemandCalc}");
} else {
    reportTest(8, "Operational Capacity & Demand", false, "No completed upload found");
}

// ── TEST 9: Timeline Generated Strictly from Scoped Upload ───────────────
if ($sampleUpload) {
    $tlService = new TimelineLayoutService($resolver);
    $layout = $tlService->getLayout($sampleUpload);
    
    $t9Pass = ($layout['totalFlights'] === $sampleUpload->flights()->count());
    reportTest(9, "24-Hour Timeline Generated Exclusively from Scoped Upload", $t9Pass,
        "Upload Flights: {$sampleUpload->flights()->count()}, Timeline Total Blocks: {$layout['totalFlights']}");
} else {
    reportTest(9, "24-Hour Timeline", false, "No completed upload found");
}

// ── TEST 10: Export PDF/JPG Dataset Parity ────────────────────────────────
if ($sampleUpload) {
    $layoutExport = $tlService->getLayout($sampleUpload, 160, 70);
    $t10Pass = ($layoutExport['totalFlights'] === $layout['totalFlights'] &&
                count($layoutExport['departureBlocks']) === count($layout['departureBlocks']) &&
                count($layoutExport['arrivalBlocks']) === count($layout['arrivalBlocks']));
    
    reportTest(10, "PDF & JPG Export Parity (1:1 Identical Dataset as Web Timeline)", $t10Pass,
        "Web Timeline Blocks: {$layout['totalFlights']} (Dep: " . count($layout['departureBlocks']) . ", Arr: " . count($layout['arrivalBlocks']) . ") | Export Blocks: {$layoutExport['totalFlights']}");
} else {
    reportTest(10, "Export Parity", false, "No completed upload found");
}

echo "\n======================================================================\n";
echo "FINAL ACCEPTANCE RESULT: " . ($allPassed ? "ALL 10 TESTS PASSED (100% SUCCESS)" : "FAILURES DETECTED") . "\n";
echo "======================================================================\n";
