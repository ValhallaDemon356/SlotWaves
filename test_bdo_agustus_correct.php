<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use App\Services\CapacityService;
use App\Services\AirportResolverService;
use Smalot\PdfParser\Parser as SmalotParser;
use Illuminate\Support\Facades\Storage;

$u = Upload::find(47);
$path = Storage::disk('local')->path($u->stored_path);

$smalot = new SmalotParser();
$pdf = $smalot->parseFile($path);
$text = $pdf->getText();
$lines = preg_split('/\r?\n/', $text);

$validLines = [];
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;
    if (preg_match('/^\d+/', $line)) {
        if (preg_match('/^\d+(\s+\d+){6}$/', $line)) continue;
        $validLines[] = $line;
    }
}

$blocks = [];
$currentBlockIndex = -1;
foreach ($validLines as $line) {
    if (preg_match('/^1(\s+|[A-Za-z])/', $line)) {
        $currentBlockIndex++;
    }
    if ($currentBlockIndex >= 0 && $currentBlockIndex < 4) {
        $blocks[$currentBlockIndex][] = $line;
    }
}

echo "Detected Blocks count: " . count($blocks) . "\n";
foreach ($blocks as $i => $bl) {
    echo "Block {$i}: " . count($bl) . " lines (First: " . substr($bl[0], 0, 40) . "...)\n";
}

// In BDO Agustus 2026:
// Block 0: ARRIVAL DOMESTIC (21)
// Block 1: DEPARTURE DOMESTIC (22)
// Block 2: ARRIVAL INTERNATIONAL (6)
// Block 3: DEPARTURE INTERNATIONAL (5)
$sectionsBDO2026 = [
    0 => 'arrival_domestic',
    1 => 'departure_domestic',
    2 => 'arrival_international',
    3 => 'departure_international'
];

$resolver = new AirportResolverService();
$parser = new App\Services\PdfParser($resolver);

$parsedFlights = [];
foreach ($blocks as $bIdx => $bLines) {
    $sec = $sectionsBDO2026[$bIdx];
    foreach ($bLines as $line) {
        $f = $parser->parseLineForTesting($line, $sec);
        if ($f) {
            $parsedFlights[] = (object) $f;
        }
    }
}

echo "\nTotal parsed flights: " . count($parsedFlights) . "\n";
$arrDom = count(array_filter($parsedFlights, fn($f) => $f->flight_type === 'arrival_domestic'));
$depDom = count(array_filter($parsedFlights, fn($f) => $f->flight_type === 'departure_domestic'));
$arrInt = count(array_filter($parsedFlights, fn($f) => $f->flight_type === 'arrival_international'));
$depInt = count(array_filter($parsedFlights, fn($f) => $f->flight_type === 'departure_international'));

echo "Arrival Domestic: {$arrDom}\n";
echo "Departure Domestic: {$depDom}\n";
echo "Arrival International: {$arrInt}\n";
echo "Departure International: {$depInt}\n";
echo "Total: " . ($arrDom + $depDom + $arrInt + $depInt) . "\n";

$capService = app(CapacityService::class);
$cap = $capService->calculate(collect($parsedFlights));
echo "\n=== CAPACITY ENGINE RESULT WITH CORRECT SECTION DETECTION ===\n";
echo "Peak Hour: {$cap['peak_hour']} | Peak Demand: {$cap['peak_demand']}\n";
foreach ($cap['hourly'] as $h => $hd) {
    if ($hd['demand'] > 0 || $hd['occupied'] > 0) {
        echo "Hour {$h} ({$hd['label']}): Demand={$hd['demand']} (Arr={$hd['arrivals_count']}, Dep={$hd['departures_count']}) | Occupied={$hd['occupied']} | Remaining={$hd['remaining']} | Status={$hd['status']}\n";
    }
}
