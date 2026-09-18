<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Dau\Parsers\DAU2Parser;
use App\Services\Dau\DauComparisonService;

$file2025 = 'C:/Users/Axioo Pongo/OneDrive/Documents/Data ATDP OASYS/DAU/6 Bulan/(01-01-2025) - (30-06-2025)/DAU-02.xls';
$file2026 = 'C:/Users/Axioo Pongo/OneDrive/Documents/Data ATDP OASYS/DAU/6 Bulan/(01-01-2026) - (30-06-2026)/DAU-02.xls';
$file5mo  = 'C:/Users/Axioo Pongo/OneDrive/Documents/Data ATDP OASYS/DAU/5 Bulan/(01-08-2025) - (31-12-2025)/DAU-02.xls';

$parser = new DAU2Parser();
$parsed2025 = $parser->parse($file2025);
$parsed2026 = $parser->parse($file2026);
$parsed5mo  = $parser->parse($file5mo);

$compService = new DauComparisonService();

echo "=== FILE 2025 ===" . PHP_EOL;
echo "Airport: " . ($parsed2025['meta']['airport_name'] ?? '') . " (" . ($parsed2025['meta']['airport_code'] ?? '') . ")" . PHP_EOL;
echo "Period: " . ($parsed2025['meta']['start_date'] ?? '') . " -> " . ($parsed2025['meta']['end_date'] ?? '') . PHP_EOL;

echo "=== FILE 2026 ===" . PHP_EOL;
echo "Airport: " . ($parsed2026['meta']['airport_name'] ?? '') . " (" . ($parsed2026['meta']['airport_code'] ?? '') . ")" . PHP_EOL;
echo "Period: " . ($parsed2026['meta']['start_date'] ?? '') . " -> " . ($parsed2026['meta']['end_date'] ?? '') . PHP_EOL;

$rep1 = [
    'id' => 1,
    'filename' => 'DAU-02-2025.xls',
    'dau_type' => 'DAU2',
    'airport_code' => $parsed2025['meta']['airport_code'],
    'airport_name' => $parsed2025['meta']['airport_name'],
    'start_date' => $parsed2025['meta']['start_date'],
    'end_date' => $parsed2025['meta']['end_date'],
    'report_data' => $parsed2025,
];

$rep2 = [
    'id' => 2,
    'filename' => 'DAU-02-2026.xls',
    'dau_type' => 'DAU2',
    'airport_code' => $parsed2026['meta']['airport_code'],
    'airport_name' => $parsed2026['meta']['airport_name'],
    'start_date' => $parsed2026['meta']['start_date'],
    'end_date' => $parsed2026['meta']['end_date'],
    'report_data' => $parsed2026,
];

$rep5mo = [
    'id' => 3,
    'filename' => 'DAU-02-5mo.xls',
    'dau_type' => 'DAU2',
    'airport_code' => $parsed5mo['meta']['airport_code'],
    'airport_name' => $parsed5mo['meta']['airport_name'],
    'start_date' => $parsed5mo['meta']['start_date'],
    'end_date' => $parsed5mo['meta']['end_date'],
    'report_data' => $parsed5mo,
];

echo "=== VALIDATION 2025 vs 2026 (Expected: VALID) ===" . PHP_EOL;
$valResult = $compService->validateComparisonReports([$rep1, $rep2]);
echo "Valid: " . ($valResult['valid'] ? 'YES' : 'NO') . PHP_EOL;
echo "Errors: " . json_encode($valResult['errors']) . PHP_EOL;
echo "Warnings: " . json_encode($valResult['warnings']) . PHP_EOL;

echo "=== VALIDATION 2025 vs 5mo (Expected: INVALID - Different Duration) ===" . PHP_EOL;
$valDiff = $compService->validateComparisonReports([$rep1, $rep5mo]);
echo "Valid: " . ($valDiff['valid'] ? 'YES' : 'NO') . PHP_EOL;
echo "Errors: " . json_encode($valDiff['errors']) . PHP_EOL;

echo "=== BUILD COMPARISON MODEL 2025 vs 2026 ===" . PHP_EOL;
$model = $compService->buildComparisonModel([$rep1, $rep2], ['flight_type' => 'ALL', 'direction' => 'ALL']);
echo "Periods count: " . count($model['periods']) . PHP_EOL;
foreach ($model['periods'] as $p) {
    echo "Period: " . $p['label'] . " (" . $p['short_label'] . ") | " . $p['display_range'] . PHP_EOL;
    echo "  Pax: " . number_format($p['metrics']['passenger']) . PHP_EOL;
    echo "  Aircraft: " . number_format($p['metrics']['aircraft']) . PHP_EOL;
    echo "  Cargo: " . number_format($p['metrics']['cargo']) . " " . ($p['metrics']['cargo_unit'] ?? 'Kg') . PHP_EOL;
}
echo "Summary rows: " . count($model['summary_table']) . PHP_EOL;
foreach ($model['summary_table'] as $row) {
    echo "Metric: " . $row['metric'] . PHP_EOL;
    foreach ($row['periods'] as $pk => $pv) {
        echo "  $pk: " . number_format($pv) . PHP_EOL;
    }
    foreach ($row['consecutive_growth'] as $cg) {
        echo "  Growth " . $cg['from'] . "->" . $cg['to'] . ": " . $cg['growth_display'] . " (diff: " . number_format($cg['diff']) . ")" . PHP_EOL;
    }
}
