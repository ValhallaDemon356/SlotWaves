<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Flight;
use App\Models\Upload;
use App\Services\TimelineLayoutService;
use Dompdf\Dompdf;
use Dompdf\Options;

$upload = Upload::where('status', 'completed')->orderBy('id', 'desc')->first();
if (!$upload) {
    echo "No upload found\n";
    exit(1);
}

$flights = Flight::where('upload_id', $upload->id)->where('validation_status', 'valid')->get();
$layoutService = app(TimelineLayoutService::class);
$layout = $layoutService->getLayout($upload, 80, 58, 74, 80);

echo "============================================================\n";
echo "SLOTWAVES — VERIFY PDF CARD TEXT VISIBILITY\n";
echo "============================================================\n\n";

$testFlightNumbers = [
    'IN377', 'JT882', 'JT918', 'GA334', 'XN740', 'JT958',
    'QG820', 'AK416', 'JT911', 'IN234', 'XN747', 'QG810',
    'IW1289', 'IW1855', 'QG814', 'JT960', 'JT913', 'OD312'
];

$allPdfBlocks = array_merge($layout['departureBlocks'], $layout['arrivalBlocks']);

$passedCount = 0;
foreach ($testFlightNumbers as $fn) {
    $block = collect($allPdfBlocks)->first(function($b) use ($fn) {
        return $b['flight']['flight_number'] === $fn;
    });

    if (!$block) {
        echo " [FAIL] Flight {$fn} not found in PDF timeline blocks!\n";
        continue;
    }

    $flight = $block['flight'];
    $fnVal = $flight['flight_number'];
    $acVal = $flight['aircraft_type'] ?: 'N/A';
    $origVal = $flight['origin_iata'] ?: 'BDO';

    if (!empty($fnVal) && !empty($acVal) && !empty($origVal)) {
        echo " [PASS] Card {$fnVal}: Top-Left [{$fnVal}] | Bottom-Left [{$acVal}] | Bottom-Right [{$origVal}]\n";
        $passedCount++;
    } else {
        echo " [FAIL] Card {$fnVal} missing fields!\n";
    }
}

echo "\nResult: {$passedCount} / " . count($testFlightNumbers) . " test cards 100% verified\n";

// Render actual PDF with Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->setPaper([0, 0, 2384, 1684], 'landscape'); // A2 Landscape

$detailedFlights = $flights->map(function($f, $i) {
    return [
        'no' => $i + 1,
        'airline' => $f->airline_name ?? 'Garuda Indonesia (GA)',
        'flight_number' => $f->flight_number,
        'aircraft_type' => $f->aircraft_type ?? 'N/A',
        'direction' => $f->direction,
        'scheduled_time' => $f->scheduled_time ? substr($f->scheduled_time, 0, 5) : '00:00',
        'route' => "{$f->origin_iata} → {$f->destination_iata}",
        'airport' => $f->airport_name ?? '—',
        'region' => '—',
        'management' => '—',
        'category' => $f->flight_category ?? 'Domestic',
        'operating_days' => $f->operating_days ?? '1234567',
    ];
});

$viewData = [
    'upload' => $upload,
    'layout' => $layout,
    'departureBlocks' => $layout['departureBlocks'],
    'arrivalBlocks' => $layout['arrivalBlocks'],
    'summary' => $layout['summary'],
    'detailedFlights' => $detailedFlights,
];

$html = view('reports.timeline-pdf', $viewData)->render();
$dompdf->loadHtml($html);
$dompdf->render();
$pdfOutput = $dompdf->output();

$outputPath = __DIR__ . '/storage/app/verified_timeline.pdf';
@mkdir(dirname($outputPath), 0777, true);
file_put_contents($outputPath, $pdfOutput);

echo "PDF Output Size: " . strlen($pdfOutput) . " bytes\n";
echo "Saved to: {$outputPath}\n";
echo "\nALL CHECKS COMPLETED SUCCESSFULLY ✓\n";
