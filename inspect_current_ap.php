<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Airport;

$aps = Airport::where('management_type', Airport::MANAGEMENT_ANGKASA_PURA)->get(['iata_code', 'icao_code', 'name', 'city', 'province', 'region']);
echo "Total current AP airports: " . $aps->count() . "\n\n";
foreach ($aps as $a) {
    echo "{$a->iata_code} | {$a->icao_code} | {$a->name} | {$a->city} | {$a->region}\n";
}
