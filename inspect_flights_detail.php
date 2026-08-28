<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$flights = DB::table('flights')->get();

$origins = [];
$destinations = [];
$airlines = [];
$flightNumbers = [];

foreach ($flights as $f) {
    if ($f->origin) {
        $origins[$f->origin] = ($origins[$f->origin] ?? 0) + 1;
    }
    if ($f->destination) {
        $destinations[$f->destination] = ($destinations[$f->destination] ?? 0) + 1;
    }
    $airlinePrefix = substr($f->flight_number, 0, 2);
    $airlines[$airlinePrefix] = ($airlines[$airlinePrefix] ?? 0) + 1;
    $flightAirlines[$f->airline_code ?? 'NULL'] = ($flightAirlines[$f->airline_code ?? 'NULL'] ?? 0) + 1;
}

echo "=== ORIGINS IN FLIGHTS ===\n";
foreach ($origins as $o => $c) {
    echo "  '{$o}' => {$c}\n";
}

echo "\n=== DESTINATIONS IN FLIGHTS ===\n";
foreach ($destinations as $d => $c) {
    echo "  '{$d}' => {$c}\n";
}

echo "\n=== AIRLINE PREFIXES (first 2 chars of flight_number) ===\n";
foreach ($airlines as $a => $c) {
    echo "  '{$a}' => {$c}\n";
}
