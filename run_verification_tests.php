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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "======================================================================\n";
echo "SLOTWAVES — SYSTEM VERIFICATION SUITE (TEST 1 - TEST 10)\n";
echo "======================================================================\n\n";

$allPassed = true;

function testAssert($testNo, $title, $condition, $details = '') {
    global $allPassed;
    if ($condition) {
        echo "[PASS] TEST {$testNo}: {$title}\n";
        if ($details) echo "       {$details}\n";
    } else {
        $allPassed = false;
        echo "[FAIL] TEST {$testNo}: {$title}\n";
        if ($details) echo "       ERROR: {$details}\n";
    }
}

// ── TEST 1: Database Connection ──────────────────────────────────────────
try {
    $dbName = DB::connection()->getDatabaseName();
    $dbHost = DB::connection()->getConfig('host');
    $dbUser = DB::connection()->getConfig('username');
    testAssert(1, "Database Connection to MySQL 'slotwaves'", 
        $dbName === 'slotwaves' && $dbHost === '127.0.0.1', 
        "Connected to {$dbName} on {$dbHost}:3306 with user '{$dbUser}'"
    );
} catch (\Throwable $e) {
    testAssert(1, "Database Connection", false, $e->getMessage());
}

// ── TEST 2: Schema Columns ───────────────────────────────────────────────
$hasAirlines = Schema::hasTable('airlines');
$airportCols = [
    'province', 'region', 'airport_type', 'management_type',
    'management_name', 'is_international', 'is_active'
];
$hasAllCols = true;
foreach ($airportCols as $c) {
    if (!Schema::hasColumn('airports', $c)) {
        $hasAllCols = false;
        break;
    }
}
testAssert(2, "Schema Structure for Master Airlines & Airports", 
    $hasAirlines && $hasAllCols, 
    "Airlines table exists: " . ($hasAirlines ? 'YES' : 'NO') . ", all 7 new columns in airports: " . ($hasAllCols ? 'YES' : 'NO')
);

// ── TEST 3: Master Data Counts ───────────────────────────────────────────
$apCount = Airport::where('management_type', 'PT. Angkasa Pura Indonesia')->count();
$uptCount = Airport::where('management_type', 'UPT Daerah/Pemda')->count();
$totalAirports = Airport::count();
$domAirlines = Airline::where('category', 'domestic')->count();
$intAirlines = Airline::where('category', 'international')->count();
$totalAirlines = Airline::count();

testAssert(3, "Master Data Seeding Verification (PDF Reference)", 
    $apCount === 37 && $uptCount >= 1 && $domAirlines >= 16 && $intAirlines >= 30, 
    "PT. Angkasa Pura Airports: {$apCount}/37, UPT Airports: {$uptCount}, Domestic Airlines: {$domAirlines}/16, International Airlines: {$intAirlines}/30"
);

// ── TEST 4: Flight Normalization & Canonical IATA Format ─────────────────
$cityNames = ['SURABAYA', 'DENPASAR', 'SEMARANG', 'TANJUNG KARANG', 'PEKANBARU', 'JOGYAKARTA', 'JOGJAKARTA', 'YOGYAKARTA', 'SOLO', 'BALIKPAPAN', 'BANJARMASIN', 'BATAM', 'HALIM PERDANAKUSUMA', 'KUALANAMU', 'PALEMBANG', 'PADANG', 'PONTIANAK', 'LOMBOK', 'PANGKALPINANG', 'KUALALUMPUR', 'SINGAPURA'];
$unnormalizedOrigin = Flight::whereIn('origin', $cityNames)->count();
$unnormalizedDest   = Flight::whereIn('destination', $cityNames)->count();
$nullOrigins        = Flight::whereNull('origin')->count();
$nullDestinations   = Flight::whereNull('destination')->count();
$invalidIataOrigin  = Flight::whereRaw('LENGTH(origin) != 3')->count();
$invalidIataDest    = Flight::whereRaw('LENGTH(destination) != 3')->count();

testAssert(4, "Flight Normalization & IATA Format in Database", 
    $unnormalizedOrigin === 0 && $unnormalizedDest === 0 && $nullOrigins === 0 && $nullDestinations === 0 && $invalidIataOrigin === 0 && $invalidIataDest === 0, 
    "Unnormalized Origins: {$unnormalizedOrigin}, Unnormalized Dests: {$unnormalizedDest}, Null Origins: {$nullOrigins}, Null Dests: {$nullDestinations}, Invalid IATA Origins: {$invalidIataOrigin}, Invalid IATA Dests: {$invalidIataDest}"
);

// ── TEST 5: Directional Base Airport Mapping ─────────────────────────────
$arrivalNonBdoDest = Flight::where('flight_type', 'like', 'arrival%')->where('destination', '!=', 'BDO')->count();
$depNonBdoOrigin   = Flight::where('flight_type', 'like', 'departure%')->where('origin', '!=', 'BDO')->count();

testAssert(5, "Directional Integrity (Arrival Dest = BDO, Departure Origin = BDO)", 
    $arrivalNonBdoDest === 0 && $depNonBdoOrigin === 0, 
    "Arrivals with non-BDO destination: {$arrivalNonBdoDest}, Departures with non-BDO origin: {$depNonBdoOrigin}"
);

// ── TEST 6: Nusawiru (CJN) UPT Daerah/Pemda Classification ───────────────
$cjn = Airport::findByIata('CJN');
$cjnValid = $cjn && $cjn->name === 'Nusawiru' && $cjn->management_type === 'UPT Daerah/Pemda' && $cjn->region === null && $cjn->province === 'Jawa Barat';
testAssert(6, "Nusawiru (CJN) UPT Daerah/Pemda Master Classification", 
    $cjnValid, 
    "IATA: " . ($cjn?->iata_code ?? 'NULL') . ", Name: " . ($cjn?->name ?? 'NULL') . ", Management: " . ($cjn?->management_type ?? 'NULL') . ", Region: " . var_export($cjn?->region, true)
);

// ── TEST 7: Airline 2-Letter Code Prefix Mapping ─────────────────────────
$mismatchedAirlines = Flight::whereRaw('UPPER(airline_code) != UPPER(SUBSTRING(flight_number, 1, 2))')->count();
testAssert(7, "Airline Code Mapping from 2-Letter Flight Prefix", 
    $mismatchedAirlines === 0, 
    "Flights with mismatched airline_code: {$mismatchedAirlines}"
);

// ── TEST 8: AirportResolverService & Master Dictionary Lookup ────────────
$resolver = new AirportResolverService();
$resSub = $resolver->getIataCode('SURABAYA');
$resJog = $resolver->getIataCode('JOGYAKARTA');
$resDps = $resolver->getIataCode('DENPASAR');
$resTkg = $resolver->getIataCode('TANJUNG KARANG');
$resCjn = $resolver->getIataCode('NUSAWIRU');
$resSin = $resolver->getIataCode('SINGAPURA');

$resolverPass = ($resSub === 'SUB' && $resJog === 'JOG' && $resDps === 'DPS' && $resTkg === 'TKG' && $resCjn === 'CJN' && $resSin === 'SIN');
testAssert(8, "AirportResolverService Master Resolution", 
    $resolverPass, 
    "SURABAYA->{$resSub}, JOGYAKARTA->{$resJog}, DENPASAR->{$resDps}, TANJUNG KARANG->{$resTkg}, NUSAWIRU->{$resCjn}, SINGAPURA->{$resSin}"
);

// ── TEST 9: Timeline Layout & Tooltip Relational Data ────────────────────
$firstUpload = Upload::first();
if ($firstUpload) {
    $timelineService = new TimelineLayoutService($resolver);
    $layout = $timelineService->getLayout($firstUpload);
    $hasBlocks = count($layout['departureBlocks']) > 0 || count($layout['arrivalBlocks']) > 0;
    $sampleBlock = $layout['departureBlocks'][0] ?? $layout['arrivalBlocks'][0] ?? null;
    $hasRelationalData = $sampleBlock && isset($sampleBlock['flight']['airline_name']) && isset($sampleBlock['flight']['remote_airport_name']);
    
    testAssert(9, "TimelineLayoutService Relational Data Generation", 
        $hasBlocks && $hasRelationalData, 
        "Total blocks: {$layout['totalFlights']}, Sample Airline Name: " . ($sampleBlock['flight']['airline_name'] ?? 'N/A') . ", Remote Airport: " . ($sampleBlock['flight']['remote_airport_name'] ?? 'N/A')
    );
} else {
    testAssert(9, "TimelineLayoutService", false, "No uploads found in database");
}

// ── TEST 10: Idempotency & Database Integrity ────────────────────────────
$initialFlightCount = Flight::count();
$initialAirportCount = Airport::count();
$initialAirlineCount = Airline::count();

// Run seeder again
$seeder = new Database\Seeders\MasterDatabaseSeeder();
$seeder->run();

$afterFlightCount = Flight::count();
$afterAirportCount = Airport::count();
$afterAirlineCount = Airline::count();

$idempotent = ($initialFlightCount === $afterFlightCount && $initialAirportCount === $afterAirportCount && $initialAirlineCount === $afterAirlineCount);
testAssert(10, "Database Idempotency Check (No duplicate records upon re-seeding)", 
    $idempotent, 
    "Airports before/after: {$initialAirportCount}/{$afterAirportCount}, Airlines before/after: {$initialAirlineCount}/{$afterAirlineCount}, Flights before/after: {$initialFlightCount}/{$afterFlightCount}"
);

echo "\n======================================================================\n";
echo "FINAL SUITE RESULT: " . ($allPassed ? "ALL 10 TESTS PASSED (100% SUCCESS)" : "FAILURES DETECTED") . "\n";
echo "======================================================================\n";
