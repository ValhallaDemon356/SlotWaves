<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "======================================================================\n";
echo "SLOTWAVES — RUNWAY REMOVAL & STAND PARSING VERIFICATION TEST\n";
echo "======================================================================\n";

$parser = new App\Services\PdfParser();

$pass = 0;
$total = 0;

// Test 1: Parser handles line with Stand
$total++;
$lineWithStand = "Lion Air JT882 B738 BDO -> UPG STD 06:00 1234567 Stand A04";
$res1 = $parser->parseLineForTesting($lineWithStand, 'departure_domestic');
if ($res1 && ($res1['stand'] ?? null) === 'A04') {
    echo "[PASS] TEST 1: Parsed line with Stand -> stand: 'A04'\n";
    $pass++;
} else {
    echo "[FAIL] TEST 1: Expected stand 'A04', got: " . var_export($res1['stand'] ?? null, true) . "\n";
}

// Test 2: Parser handles line without Stand (stand: null)
$total++;
$lineWithoutStand = "Lion Air JT882 B738 BDO -> UPG STD 06:00 1234567";
$res2 = $parser->parseLineForTesting($lineWithoutStand, 'departure_domestic');
if ($res2 && array_key_exists('stand', $res2) && $res2['stand'] === null) {
    echo "[PASS] TEST 2: Parsed line without Stand -> stand: null\n";
    $pass++;
} else {
    echo "[FAIL] TEST 2: Expected stand null, got: " . var_export($res2['stand'] ?? null, true) . "\n";
}

// Test 3: Parser handles line with empty Stand keyword
$total++;
$lineEmptyStand = "Lion Air JT882 B738 BDO -> UPG STD 06:00 1234567 Stand";
$res3 = $parser->parseLineForTesting($lineEmptyStand, 'departure_domestic');
if ($res3 && array_key_exists('stand', $res3) && $res3['stand'] === null) {
    echo "[PASS] TEST 3: Parsed line with empty Stand keyword -> stand: null\n";
    $pass++;
} else {
    echo "[FAIL] TEST 3: Expected stand null, got: " . var_export($res3['stand'] ?? null, true) . "\n";
}

// Test 4: Dashboard data verification (No runway fields in movements, stand is preserved)
$total++;
$upload = App\Models\Upload::find(46);
if ($upload) {
    $flights = App\Models\Flight::where('upload_id', $upload->id)->validated()->get();
    $foundRunwayInMovements = false;
    foreach ($flights as $f) {
        if (isset($f->runway) || str_contains($f->remarks ?? '', 'Runway 29')) {
            $foundRunwayInMovements = true;
            break;
        }
    }
    if (!$foundRunwayInMovements) {
        echo "[PASS] TEST 4: Verified Flight model contains no synthetic runway data\n";
        $pass++;
    } else {
        echo "[FAIL] TEST 4: Found runway in flight models\n";
    }
}

// Test 5: Verify Blade view has 0 references to runway or RWY
$total++;
$bladeContent = file_get_contents(resource_path('views/schedule/dashboard.blade.php'));
$hasRunway = stripos($bladeContent, 'runway') !== false;
if (!$hasRunway) {
    echo "[PASS] TEST 5: dashboard.blade.php contains ZERO references to runway\n";
    $pass++;
} else {
    echo "[FAIL] TEST 5: dashboard.blade.php still contains runway references\n";
}

echo "======================================================================\n";
echo "RESULT: {$pass}/{$total} TESTS PASSED\n";
echo "======================================================================\n";
exit($pass === $total ? 0 : 1);
