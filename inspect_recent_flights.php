<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== FLIGHTS IN UPLOAD 43 (BDO Agustus 2026 Baru) ===\n";
$f43 = DB::table('flights')->where('upload_id', 43)->get();
foreach ($f43 as $f) {
    echo "ID: {$f->id} | Flight: {$f->flight_number} | Airline: {$f->airline_code} | AC: {$f->aircraft_type} | STA/STD: {$f->scheduled_time} | Orig: {$f->origin} | Dest: {$f->destination} | Type: {$f->flight_type}\n";
}

echo "\n=== FLIGHTS IN UPLOAD 44 (FLIGHT SCHEDULE SUMMER 2018) ===\n";
$f44 = DB::table('flights')->where('upload_id', 44)->take(15)->get();
foreach ($f44 as $f) {
    echo "ID: {$f->id} | Flight: {$f->flight_number} | Airline: {$f->airline_code} | AC: {$f->aircraft_type} | STA/STD: {$f->scheduled_time} | Orig: {$f->origin} | Dest: {$f->destination} | Type: {$f->flight_type}\n";
}
