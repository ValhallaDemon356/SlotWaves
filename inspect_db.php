<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== DATABASE CONNECTION ===\n";
echo "Connection: " . config('database.default') . "\n";
echo "Database: " . DB::connection()->getDatabaseName() . "\n";

echo "\n=== TABLES AND COLUMNS ===\n";
$tables = DB::select("SHOW TABLES");
$dbName = DB::connection()->getDatabaseName();
$colName = "Tables_in_" . $dbName;

foreach ($tables as $t) {
    $tableName = $t->$colName;
    if ($tableName === 'migrations') continue;
    $count = DB::table($tableName)->count();
    echo "- Table: {$tableName} (count: {$count})\n";
    $columns = DB::select("DESCRIBE `{$tableName}`");
    foreach ($columns as $c) {
        echo "    {$c->Field} ({$c->Type}, " . ($c->Null === 'YES' ? 'NULL' : 'NOT NULL') . ", Key: {$c->Key}, Default: {$c->Default})\n";
    }
}

echo "\n=== AIRPORTS TABLE (ALL ROWS) ===\n";
if (Schema::hasTable('airports')) {
    $airports = DB::table('airports')->get();
    echo "Total airports: " . $airports->count() . "\n";
    foreach ($airports as $a) {
        echo json_encode($a) . "\n";
    }
} else {
    echo "Table airports does not exist.\n";
}

echo "\n=== FLIGHTS SAMPLE (TOP 20) ===\n";
if (Schema::hasTable('flights')) {
    $flights = DB::table('flights')->take(20)->get();
    echo "Total flights: " . DB::table('flights')->count() . "\n";
    foreach ($flights as $f) {
        echo json_encode($f) . "\n";
    }
}

echo "\n=== DISTINCT AIRLINE CODES IN FLIGHTS ===\n";
if (Schema::hasTable('flights')) {
    $distinctAirlines = DB::table('flights')->select('airline_code', DB::raw('count(*) as count'))->groupBy('airline_code')->get();
    foreach ($distinctAirlines as $da) {
        echo "  airline_code: " . ($da->airline_code ?? 'NULL') . " -> count: {$da->count}\n";
    }
}

echo "\n=== DISTINCT ORIGINS IN FLIGHTS ===\n";
if (Schema::hasTable('flights')) {
    $distinctOrigins = DB::table('flights')->select('origin', DB::raw('count(*) as count'))->groupBy('origin')->get();
    foreach ($distinctOrigins as $do) {
        echo "  origin: " . ($do->origin ?? 'NULL') . " -> count: {$do->count}\n";
    }
}

echo "\n=== DISTINCT DESTINATIONS IN FLIGHTS ===\n";
if (Schema::hasTable('flights')) {
    $distinctDests = DB::table('flights')->select('destination', DB::raw('count(*) as count'))->groupBy('destination')->get();
    foreach ($distinctDests as $dd) {
        echo "  destination: " . ($dd->destination ?? 'NULL') . " -> count: {$dd->count}\n";
    }
}
