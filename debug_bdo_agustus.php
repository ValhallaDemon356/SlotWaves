<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use App\Services\PdfParser;
use Smalot\PdfParser\Parser as SmalotParser;
use Illuminate\Support\Facades\Storage;

$u = Upload::find(47);
$path = Storage::disk('local')->path($u->stored_path);

echo "=== RAW TEXT IN PDF ID 47 ({$u->original_filename}) ===\n";
$smalot = new SmalotParser();
$pdf = $smalot->parseFile($path);
$pages = $pdf->getPages();
echo "Total Pages: " . count($pages) . "\n";

foreach ($pages as $pIndex => $page) {
    echo "\n--- PAGE " . ($pIndex + 1) . " ---\n";
    $text = $page->getText();
    $lines = preg_split('/\r?\n/', $text);
    foreach (array_slice($lines, 0, 30) as $lIndex => $line) {
        echo "Line {$lIndex}: " . trim($line) . "\n";
    }
}

echo "\n=== CURRENT PARSER OUTPUT FOR ID 47 ===\n";
$parser = new PdfParser();
$flights = $parser->parse($path);
echo "Total flights parsed: " . count($flights) . "\n";
foreach (array_slice($flights, 0, 15) as $f) {
    echo json_encode($f) . "\n";
}

echo "\n=== FLIGHTS SAVED IN DB FOR ID 47 ===\n";
$dbFlights = $u->flights()->get();
echo "Total in DB: " . $dbFlights->count() . "\n";
foreach ($dbFlights->take(15) as $df) {
    echo "ID: {$df->id} | {$df->flight_number} | {$df->airline_code} | {$df->aircraft_type} | {$df->flight_type} | STA/STD: {$df->scheduled_time} | Orig: {$df->origin} | Dest: {$df->destination} | DOS: {$df->operating_days}\n";
}

echo "\n=== CAPACITY STATS FOR ID 47 ===\n";
$capService = app(\App\Services\CapacityService::class);
$cap = $capService->calculate($dbFlights);
echo "Peak Hour: {$cap['peak_hour']} | Peak Demand: {$cap['peak_demand']}\n";
foreach ($cap['hourly'] as $h => $hd) {
    if ($hd['demand'] > 0 || $hd['occupied'] > 0) {
        echo "Hour {$h} ({$hd['label']}): Demand={$hd['demand']} (Arr={$hd['arrivals_count']}, Dep={$hd['departures_count']}) | Occupied={$hd['occupied']} | Remaining={$hd['remaining']} | Status={$hd['status']}\n";
    }
}
