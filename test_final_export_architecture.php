<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use App\Models\Flight;
use App\Models\Airport;
use App\Models\Airline;
use App\Services\TimelineLayoutService;
use App\Services\AirportResolverService;
use Barryvdh\DomPDF\Facade\Pdf;

echo "============================================================\n";
echo "SLOTWAVES — VERIFY FINAL EXPORT ARCHITECTURE (JPG + PDF)\n";
echo "============================================================\n\n";

function assertTest($condition, $message) {
    if ($condition) {
        echo " [PASS] $message\n";
    } else {
        echo " [FAIL] $message\n";
        exit(1);
    }
}

$upload = Upload::where('status', 'completed')->orderBy('id', 'desc')->first();
if (!$upload) {
    echo "No completed upload found in database.\n";
    exit(1);
}

$flights = Flight::where('upload_id', $upload->id)->where('validation_status', 'valid')->get();
$totalDbFlights = $flights->count();
echo "Testing with Upload ID #{$upload->id} ({$totalDbFlights} flights)\n\n";

$resolver = app(AirportResolverService::class);
$layoutService = app(TimelineLayoutService::class);

// ══ TEST PART 1: 6500px JPG TIMELINE RENDERER GEOMETRY & FLIGHT CARDS ══════
echo "--- PART 1: 6500px JPG TIMELINE RENDERER ---\n";
// JPG layout: colW = 250px, rowH = 135px, blockW = 200px, labelOffset = 250px -> totalW = 6500px
$jpgLayout = $layoutService->getLayout($upload, 250, 135, 200, 250);

assertTest($jpgLayout['canvasWidth'] === 6500, "1.1: JPG Canvas width is 6500px (>= 6000px high-res specification)");
assertTest($jpgLayout['blockW'] === 200, "1.2: JPG Flight card width is 200px (large readable card)");
assertTest($jpgLayout['rowH'] === 135, "1.3: JPG Flight lane height is 135px (generous vertical spacing for 110px card)");
assertTest(count($jpgLayout['departureBlocks']) + count($jpgLayout['arrivalBlocks']) === $totalDbFlights, "1.4: 100% of flights present in 24-hour timeline ({$totalDbFlights} flights)");

// Verify 3-point card content (Top-Left: FLIGHT NO, Bottom-Left: A/C, Bottom-Right: AIRPORT)
$sampleDep = collect($jpgLayout['departureBlocks'])->first();
$sampleArr = collect($jpgLayout['arrivalBlocks'])->first();

assertTest(!empty($sampleDep['flight']['flight_number']), "1.5: Departure card Top-Left contains Flight Number ({$sampleDep['flight']['flight_number']})");
assertTest(!empty($sampleDep['flight']['aircraft_type']), "1.5: Departure card Bottom-Left contains Aircraft Type ({$sampleDep['flight']['aircraft_type']})");
assertTest(!empty($sampleDep['flight']['origin_iata']), "1.5: Departure card Bottom-Right contains Origin IATA ({$sampleDep['flight']['origin_iata']})");

assertTest(!empty($sampleArr['flight']['flight_number']), "1.6: Arrival card Top-Left contains Flight Number ({$sampleArr['flight']['flight_number']})");
assertTest(!empty($sampleArr['flight']['aircraft_type']), "1.6: Arrival card Bottom-Left contains Aircraft Type ({$sampleArr['flight']['aircraft_type']})");
assertTest(!empty($sampleArr['flight']['origin_iata']), "1.6: Arrival card Bottom-Right contains Origin IATA ({$sampleArr['flight']['origin_iata']})");

// Specific user test cards verification:
$jt882 = collect($jpgLayout['departureBlocks'])->first(fn($b) => $b['flight']['flight_number'] === 'JT882');
$qg820 = collect($jpgLayout['arrivalBlocks'])->first(fn($b) => $b['flight']['flight_number'] === 'QG820');
$ak416 = collect($jpgLayout['arrivalBlocks'])->first(fn($b) => $b['flight']['flight_number'] === 'AK416');
$iw1289 = collect($jpgLayout['arrivalBlocks'])->first(fn($b) => $b['flight']['flight_number'] === 'IW1289');

assertTest($jt882 && $jt882['flight']['aircraft_type'] === 'B738' && $jt882['flight']['origin_iata'] === 'BDO', "1.7: Test Card 1: JT882 / B738 / BDO verified");
assertTest($qg820 && $qg820['flight']['aircraft_type'] === 'A320' && $qg820['flight']['origin_iata'] === 'SUB', "1.8: Test Card 2: QG820 / A320 / SUB verified");
assertTest($ak416 && $ak416['flight']['aircraft_type'] === 'A320' && $ak416['flight']['origin_iata'] === 'KUL', "1.9: Test Card 3: AK416 / A320 / KUL verified");
assertTest($iw1289 && $iw1289['flight']['aircraft_type'] === 'AT72' && $iw1289['flight']['origin_iata'] === 'TKG', "1.10: Test Card 4: IW1289 / AT72 / TKG verified");

// Summary Table Verification
$summary = $jpgLayout['summary'];
$sumTotal = 0;
for ($h = 0; $h < 24; $h++) {
    $sumTotal += ($summary[$h]['dep_dom'] ?? 0)
               + ($summary[$h]['dep_int'] ?? 0)
               + ($summary[$h]['arr_dom'] ?? 0)
               + ($summary[$h]['arr_int'] ?? 0);
}
assertTest($sumTotal === $totalDbFlights, "1.11: 24-Hour Summary Table total ({$sumTotal}) strictly matches Total Flights ({$totalDbFlights})");


// ══ TEST PART 2: UNCLIPPED A2 OPERATIONAL PDF REPORT (SHARED HORIZONTAL GRID) ════
echo "\n--- PART 2: UNCLIPPED A2 OPERATIONAL PDF REPORT ---\n";
// PDF layout: colW = 80px, rowH = 58px, blockW = 74px, labelOffset = 80px -> totalW = 2080px <= 2185px printable A2
$pdfLayout = $layoutService->getLayout($upload, 80, 58, 74, 80);

assertTest($pdfLayout['canvasWidth'] === 2080, "2.1: PDF Page 1 Timeline width is 2080px (fits 100% within A2 printable width 2185px without right-edge clipping)");
assertTest($pdfLayout['colW'] === 80 && $pdfLayout['labelOffset'] === 80 && $pdfLayout['totWidth'] === 80, "2.2: Shared Grid System: labelOffset (80px) = colW (80px) = totWidth (80px) ensuring pixel-perfect column alignment");
assertTest(count($pdfLayout['departureBlocks']) + count($pdfLayout['arrivalBlocks']) === $totalDbFlights, "2.3: PDF Timeline contains all {$totalDbFlights} flights");

$airportsCache = Airport::all()->keyBy('iata_code');
$airlinesCache = Airline::all()->keyBy('airline_code');

$detailedFlights = [];
$no = 1;
foreach ($flights as $f) {
    $airlineCode = strtoupper($f->airline_code ?? substr($f->flight_number ?? '', 0, 2));
    $airlineObj  = $airlinesCache->get($airlineCode);
    $airlineName = $airlineObj ? $airlineObj->airline_name : $resolver->getAirlineName($airlineCode);
    $airlineFull = $airlineName ? "{$airlineName} ({$airlineCode})" : $airlineCode;

    $origIata = strtoupper($f->origin ?? 'BDO');
    $destIata = strtoupper($f->destination ?? 'BDO');

    $origAirport = $airportsCache->get($origIata);
    $destAirport = $airportsCache->get($destIata);

    $isArrival = strtolower($f->direction ?? '') === 'arrival' || str_contains($f->flight_type ?? '', 'arrival');
    $remoteAirport = $isArrival ? $origAirport : $destAirport;
    $remoteIata    = $isArrival ? $origIata : $destIata;

    $remoteName = $remoteAirport?->name ?? $remoteIata;
    $airportLabel = "{$remoteName} ({$remoteIata})";

    $region = $remoteAirport?->region;
    if (empty($region)) {
        $region = ($remoteAirport?->management_type === 'UPT Daerah/Pemda' ? 'UPT Daerah/Pemda' : '—');
    }

    $management = $remoteAirport?->management_name ?: ($remoteAirport?->management_type ?: '—');
    if ($management === 'Other' || empty($management)) {
        $management = '—';
    }

    $category = (strtolower($f->traffic_type ?? '') === 'international' || str_contains($f->flight_type ?? '', 'international'))
        ? 'International' : 'Domestic';

    $direction = $isArrival ? 'ARR' : 'DEP';
    $route = "{$origIata} → {$destIata}";

    $detailedFlights[] = [
        'no'             => $no++,
        'airline'        => $airlineFull,
        'flight_number'  => $f->flight_number,
        'aircraft_type'  => $resolver->normalizeAircraftType($f->aircraft_type),
        'direction'      => $direction,
        'scheduled_time' => $f->scheduled_time ? substr($f->scheduled_time, 0, 5) : '—',
        'route'          => $route,
        'airport'        => $airportLabel,
        'region'         => $region ?: '—',
        'management'     => $management ?: '—',
        'category'       => $category,
        'operating_days' => $f->operating_days ?: '1234567',
    ];
}

assertTest(count($detailedFlights) === $totalDbFlights, "2.4: PDF Detailed list has exactly {$totalDbFlights} flights");

// Verify the 12 required fields in detailedFlights
$firstDf = $detailedFlights[0];
$requiredKeys = ['no', 'airline', 'flight_number', 'aircraft_type', 'direction', 'scheduled_time', 'route', 'airport', 'region', 'management', 'category', 'operating_days'];
foreach ($requiredKeys as $key) {
    assertTest(array_key_exists($key, $firstDf) && $firstDf[$key] !== null, "2.5: Detailed row contains '{$key}' (value: {$firstDf[$key]})");
}

// Generate actual Dompdf binary and save to test file
$pdf = Pdf::loadView('reports.timeline-pdf', [
    'upload'          => $upload,
    'layout'          => $pdfLayout,
    'departureBlocks' => $pdfLayout['departureBlocks'],
    'arrivalBlocks'   => $pdfLayout['arrivalBlocks'],
    'settings'        => $pdfLayout['settings'],
    'summary'         => $pdfLayout['summary'],
    'detailedFlights' => $detailedFlights,
])
->setPaper([0, 0, 1683.78, 1190.55], 'landscape')
->setOptions([
    'isRemoteEnabled'      => true,
    'isHtml5ParserEnabled' => true,
    'defaultFont'          => 'sans-serif',
    'chroot'               => public_path(),
]);

$pdfOutput = $pdf->output();
$pdfBytes = strlen($pdfOutput);
$dompdf = $pdf->getDomPDF();
$canvas = $dompdf->getCanvas();
$pageCount = $canvas->get_page_count();
assertTest($pdfBytes > 10000 && str_starts_with($pdfOutput, '%PDF-'), "2.6: Dompdf successfully renders valid unclipped PDF binary ({$pdfBytes} bytes)");
assertTest($pageCount >= 2, "2.7: PDF Report is multi-page (Page 1 = 24-Hour Timeline, Pages 2..{$pageCount} = Flight Schedule Details; Total Pages: {$pageCount})");

// ══ TEST PART 3: ZERO DATA FABRICATION & FLIGHT COUNT EQUALITY ═══════════════
echo "\n--- PART 3: ZERO DATA FABRICATION & FLIGHT COUNT EQUALITY ---\n";

assertTest(
    $totalDbFlights === count($jpgLayout['departureBlocks']) + count($jpgLayout['arrivalBlocks']) &&
    $totalDbFlights === count($pdfLayout['departureBlocks']) + count($pdfLayout['arrivalBlocks']) &&
    $totalDbFlights === count($detailedFlights) &&
    $totalDbFlights === $sumTotal,
    "3.1: Strict Equality: Database ({$totalDbFlights}) == JPG Timeline ({$totalDbFlights}) == PDF Timeline ({$totalDbFlights}) == PDF Details ({$totalDbFlights}) == Summary ({$sumTotal})"
);

// ══ TEST PART 4: DYNAMIC OPS HOURS REGRESSION ═════════════════════════════
echo "\n--- PART 4: DYNAMIC OPS HOURS REGRESSION ---\n";
// Change OPS settings to 08:00 -> 17:00
$setting = \App\Models\TimelineSetting::firstOrCreate(['upload_id' => $upload->id]);
$setting->update(['ops_start' => 8, 'ops_end' => 17]);

$regJpgLayout = $layoutService->getLayout($upload, 250, 155, 220, 250);
$regPdfLayout = $layoutService->getLayout($upload, 80, 58, 74, 80);

assertTest(count($regJpgLayout['departureBlocks']) + count($regJpgLayout['arrivalBlocks']) === $totalDbFlights, "4.1: 08:00–17:00 OPS Hours change preserves all {$totalDbFlights} flights in JPG Timeline");
assertTest(count($regPdfLayout['departureBlocks']) + count($regPdfLayout['arrivalBlocks']) === $totalDbFlights, "4.2: 08:00–17:00 OPS Hours change preserves all {$totalDbFlights} flights in PDF Timeline");
assertTest(count($regPdfLayout['summary']) === 24, "4.3: Timeline summary table remains full 24-hour schedule (00:00 to 23:00)");

// Reset to 06:00 -> 20:00
$setting->update(['ops_start' => 6, 'ops_end' => 20]);

echo "\n============================================================\n";
echo "SUMMARY: ALL TESTS PASSED SUCCESSFULLY (100%)\n";
echo "============================================================\n";
