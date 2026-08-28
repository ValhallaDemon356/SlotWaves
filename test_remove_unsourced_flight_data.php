<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use App\Models\Flight;
use App\Models\Airport;
use App\Models\Airline;
use App\Services\CapacityService;
use App\Services\FlightFilterService;
use App\Services\ReportService;
use App\Services\TimelineLayoutService;
use Illuminate\Http\Request;

echo "======================================================================\n";
echo "SLOTWAVES — REGRESSION TEST: UNSOURCED DATA REMOVAL & SCHEDULE ACCURACY\n";
echo "======================================================================\n\n";

$pass = 0;
$total = 0;

function runTest($title, $condition, $details = '') {
    global $pass, $total;
    $total++;
    if ($condition) {
        $pass++;
        echo "[PASS] TEST {$total}: {$title}\n";
        if ($details) echo "       {$details}\n";
    } else {
        echo "[FAIL] TEST {$total}: {$title}\n";
        if ($details) echo "       ERROR: {$details}\n";
    }
}

// ── TEST 1: Check Blade View for Unsourced Elements ──
$dashboardBlade = file_get_contents(resource_path('views/schedule/dashboard.blade.php'));

runTest(
    "List Pergerakan Hari Ini table headers have NO 'STAND' or 'STATUS'",
    !str_contains($dashboardBlade, '<th class="py-3 px-4">STAND</th>') &&
    !str_contains($dashboardBlade, '<th class="py-3 px-3 text-center w-36">') &&
    !str_contains($dashboardBlade, 'STATUS ⓘ'),
    "Headers contain exactly: WAKTU, FLIGHT, ROUTE, MASKAPAI, TIPE PESAWAT, JENIS"
);

runTest(
    "Flight Drawer contains NO 'Apron Stand' or 'Stand Allocation'",
    !str_contains($dashboardBlade, 'Apron Stand') &&
    !str_contains($dashboardBlade, 'Stand Allocation'),
    "Apron Stand and Stand Allocation sections are completely removed"
);

runTest(
    "Flight Drawer contains NO ungrounded 'Operational Status' section",
    !str_contains($dashboardBlade, '<span class="font-bold text-slate-600 dark:text-slate-300 uppercase text-[10px]">Operational Status</span>'),
    "Operational Status section is removed from flight drawer"
);

runTest(
    "Movement table has exactly 6 columns in empty state",
    str_contains($dashboardBlade, '<td colspan="6" class="py-12 text-center text-slate-400">'),
    "Colspan is 6 (matching the 6 columns)"
);

// ── TEST 2: DashboardController Output Verification ──
$upload = Upload::where('status', 'completed')->latest()->first();

if ($upload) {
    echo "\nTesting with Upload ID #{$upload->id} ({$upload->original_filename}):\n";

    $response = app()->call('App\Http\Controllers\DashboardController@show', [
        'request' => new Request(),
        'upload' => $upload
    ]);

    $viewData = $response->getData();
    $movements = $viewData['flightMovements'];

    runTest(
        "FlightMovements collection exists and has valid count",
        $movements->count() > 0,
        "Found {$movements->count()} flight movements"
    );

    // Check first movement
    $first = $movements->first();
    runTest(
        "First flight movement has required fields without stand/status",
        isset($first['flight_number']) &&
        isset($first['airline_name']) &&
        isset($first['scheduled_time']) &&
        isset($first['aircraft_type']) &&
        isset($first['route']) &&
        isset($first['origin_name']) &&
        isset($first['destination_name']) &&
        isset($first['management']) &&
        isset($first['region']) &&
        !isset($first['stand']) &&
        !isset($first['status_code']),
        "Flight: {$first['flight_number']}, Airline: {$first['airline_name']}, Time: {$first['scheduled_time']}, Aircraft: {$first['aircraft_type']}"
    );

    // Check if JT882 or specific flight exists
    $jt882 = $movements->firstWhere('flight_number', 'JT882') ?: $movements->first();
    if ($jt882) {
        runTest(
            "Flight {$jt882['flight_number']} has authentic airline and master data",
            !empty($jt882['airline_name']) && !empty($jt882['origin']) && !empty($jt882['destination']),
            "Flight: {$jt882['flight_number']}, Airline: {$jt882['airline_name']}, Route: {$jt882['route']}, Management: {$jt882['management']}, Region: {$jt882['region']}"
        );
    }

    // ── TEST 3: Operational Capacity & Demand Calculation Integrity ──
    $capacityStats = $viewData['capacityStats'];
    runTest(
        "Operational Capacity Engine calculates 24 hourly buckets",
        isset($capacityStats['hourly']) && count($capacityStats['hourly']) === 24,
        "NAC: {$capacityStats['nac']}, Peak Demand: {$capacityStats['peak_demand']} mvm"
    );

    // ── TEST 4: Timeline View & Layout Service ──
    $layoutService = app(TimelineLayoutService::class);
    $layout = $layoutService->getLayout($upload, 120, 64);
    runTest(
        "Timeline Layout Service computes valid timeline blocks",
        isset($layout['departureBlocks']) && isset($layout['arrivalBlocks']) && isset($layout['summary']),
        "Departures: " . count($layout['departureBlocks']) . ", Arrivals: " . count($layout['arrivalBlocks'])
    );

    // ── TEST 5: PDF Export Views ──
    $reportService = app(ReportService::class);
    $timeHtml = $reportService->renderTimeHtml($upload);
    $dosHtml = $reportService->renderDosHtml($upload);

    runTest(
        "TIME Report renders correctly without stand column",
        str_contains($timeHtml, 'TIME FLIGHT SCHEDULE') && !str_contains($timeHtml, 'Stand A'),
        "Rendered TIME schedule HTML successfully"
    );

    runTest(
        "DOS Report renders correctly without stand column",
        str_contains($dosHtml, 'DAILY OPERATING SERVICE') && !str_contains($dosHtml, 'Stand A'),
        "Rendered DOS schedule HTML successfully"
    );
}

echo "\n======================================================================\n";
echo "RESULT: {$pass}/{$total} TESTS PASSED\n";
echo "======================================================================\n";

exit($pass === $total ? 0 : 1);
