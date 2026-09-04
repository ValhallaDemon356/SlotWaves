<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\DauDashboardController;
use App\Models\ReportUpload;
use App\Services\Dau\ReportTemplateRegistry;
use Barryvdh\DomPDF\Facade\Pdf;

echo "\n======================================================================\n";
echo "SLOTWAVES — COMPLETE DAU ANALYTICS DASHBOARD UPGRADE VERIFICATION TEST\n";
echo "======================================================================\n\n";

$registry = app(ReportTemplateRegistry::class);
$allDauTypes = [
    'DAU1'  => 'DAU-1.xls',
    'DAU2'  => 'DAU-2.xls',
    'DAU3'  => 'DAU-3.xls',
    'DAU4'  => 'DAU-4.xls',
    'DAU4A' => 'DAU-4A.xls',
    'DAU4B' => 'DAU-4B.xls',
    'DAU5'  => 'DAU-5.xls',
    'DAU5A' => 'DAU-5A.xls',
    'DAU5B' => 'DAU-5B.xls',
    'DAU5C' => 'DAU-5C.xls',
    'DAU6'  => 'DAU-6.xls',
    'DAU10' => 'DAU-10.xls',
    'DAU10A'=> 'DAU-10A.xls',
    'DAU10B'=> 'DAU-10B.xls',
    'DAU11' => 'DAU-11.xls',
    'DAU12' => 'DAU-12.xls',
];

$passed = 0;
$failed = 0;

$controller = app(DauDashboardController::class);
$reflection = new ReflectionClass($controller);
$filterMethod = $reflection->getMethod('filterReportDataset');
$filterMethod->setAccessible(true);

foreach ($allDauTypes as $type => $fileName) {
    $filePath = resource_path("templates/dau/{$fileName}");
    if (!file_exists($filePath)) {
        echo "[FAIL] File missing: {$filePath}\n";
        $failed++;
        continue;
    }

    $conf = ReportTemplateRegistry::find($type);
    if (!$conf || empty($conf['parser_class'])) {
        echo "[FAIL] No parser registered for {$type}\n";
        $failed++;
        continue;
    }
    $parser = app($conf['parser_class']);

    try {
        $parsed = $parser->parse($filePath);
        $records = (in_array($type, ['DAU10A', 'DAU4B']) && !empty($parsed['normalized_pairs']))
            ? $parsed['normalized_pairs']
            : ($parsed['records'] ?? []);
        $meta = $parsed['meta'] ?? [];
        $summary = $parsed['summary'] ?? [];

        // 1. Verify parser output
        if (empty($records)) {
            echo "[FAIL] {$type}: No records parsed from {$fileName}\n";
            $failed++;
            continue;
        }

        // 2. Test filterReportDataset default filters
        $defaultFilters = [
            'direction'   => 'ALL',
            'terminal'    => 'ALL',
            'airline'     => 'ALL',
            'flight_type' => 'ALL',
            'hour'        => 'ALL',
            'metric'      => 'aircraft',
            'status'      => 'ALL',
            'category'    => 'ALL',
            'search'      => '',
            'top_n'       => 10,
            'threshold'   => 0,
        ];

        $filteredData = $filterMethod->invoke($controller, $records, $defaultFilters, $meta, $type);
        $filteredRecords = $filteredData['filtered_records'];
        $calcSummary = $filteredData['summary'];

        if (count($filteredRecords) !== count($records)) {
            echo "[FAIL] {$type}: Default filtered record count (" . count($filteredRecords) . ") != parsed record count (" . count($records) . ")\n";
            $failed++;
            continue;
        }

        // Check specific analytics keys
        $hasRequiredVisualModel = match ($type) {
            'DAU1'   => !empty($filteredData['dau1_routes']),
            'DAU2'   => isset($filteredData['dau2_distribution']['domestic']),
            'DAU3'   => isset($filteredData['dau3_status']['niaga']),
            'DAU4'   => isset($filteredData['dau4_diverging']['top_arrival']),
            'DAU4B'  => isset($filteredData['dau4b_matrix']['grid']),
            'DAU5'   => !empty($filteredData['dau5_pareto']),
            'DAU5A'  => !empty($filteredData['dau5a_crew']),
            'DAU5B'  => !empty($filteredData['dau5b_terminals']),
            'DAU6'   => !empty($filteredData['dau6_fleet']['types']),
            'DAU10'  => !empty($filteredData['hourly_distribution']),
            'DAU10A' => !empty($filteredData['heatmap_matrix']),
            'DAU10B' => !empty($filteredData['hourly_distribution']),
            'DAU11'  => isset($filteredData['dau11_flow']['dom_arr']),
            'DAU12'  => isset($filteredData['dau12_matrix']['aircraft']),
            default  => true,
        };

        if (!$hasRequiredVisualModel) {
            echo "[FAIL] {$type}: Analytical data model missing for chart visualization\n";
            $failed++;
            continue;
        }

        // 3. Test PDF generation with authentic parser data
        $conf = ReportTemplateRegistry::find($type) ?? ['title' => 'DAU Report', 'code' => $type];
        $peaks = $filteredData['peaks'] ?? [];
        $hourlyData = $filteredData['hourly_distribution'] ?? [];
        $terminalData = $filteredData['terminal_comparison'] ?? [];
        $heatmapMatrix = $filteredData['heatmap_matrix'] ?? [];

        $pdfViewData = [
            'upload'               => null,
            'reportType'           => $type,
            'conf'                 => $conf,
            'data'                 => $parsed,
            'meta'                 => $meta,
            'summary'              => $calcSummary,
            'records'              => count($filteredRecords) > 150 ? array_slice($filteredRecords, 0, 150) : $filteredRecords,
            'totalFilteredCount'   => count($filteredRecords),
            'isTruncatedForPdf'    => count($filteredRecords) > 150,
            'peaks'                => $peaks,
            'hourlyData'           => $hourlyData,
            'terminalData'         => $terminalData,
            'heatmapMatrix'        => $heatmapMatrix,
            'analytics'            => $filteredData,
            'filters'              => $defaultFilters,
            'metric'               => 'aircraft',
            'nac'                  => 12,
            'arrNac'               => 6,
            'depNac'               => 6,
            'opsStart'             => '00:00',
            'opsEnd'               => '24:00',
            'capacitySummary'      => [
                'arr_nac'              => 6,
                'dep_nac'              => 6,
                'available_hours'      => 10,
                'full_hours'           => 2,
                'over_capacity_hours'  => 12,
                'off_hours'            => 0,
                'peak_aircraft'        => $peaks['peak_aircraft'] ?? 0,
                'peak_hour'            => $peaks['peak_hour'] ?? '—',
            ],
            'hourlyCapacityStatus' => [],
        ];

        $pdf = Pdf::loadView('dau.pdf', $pdfViewData)->setPaper('a4', 'landscape');
        $pdfOutput = $pdf->output();

        if (strlen($pdfOutput) < 1000 || substr($pdfOutput, 0, 4) !== '%PDF') {
            echo "[FAIL] {$type}: PDF generation produced invalid or empty output (len: " . strlen($pdfOutput) . ")\n";
            $failed++;
            continue;
        }

        echo "[PASS] {$type}: Parsed " . count($records) . " rows | Movements: " . number_format($calcSummary['aircraft_total'] ?? 0) . " | PDF generated (" . round(strlen($pdfOutput)/1024, 1) . " KB)\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "[FAIL] {$type} EXCEPTION: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

// 4. Test Reactive Filtering Consistency
echo "\n--- TESTING REACTIVE FILTERING CONSISTENCY ---\n";
try {
    // Test DAU-10A Terminal filter
    $p10a = app(ReportTemplateRegistry::find('DAU10A')['parser_class']);
    $dau10aParsed = $p10a->parse(resource_path('templates/dau/DAU-10A.xls'));
    $filterT2F = [
        'direction'   => 'ALL',
        'terminal'    => '2F',
        'airline'     => 'ALL',
        'flight_type' => 'ALL',
        'hour'        => 'ALL',
        'metric'      => 'aircraft',
        'status'      => 'ALL',
        'category'    => 'ALL',
        'search'      => '',
        'top_n'       => 10,
        'threshold'   => 0,
    ];
    $resT2F = $filterMethod->invoke($controller, $dau10aParsed['normalized_pairs'], $filterT2F, $dau10aParsed['meta'] ?? [], 'DAU10A');
    $allAreT2F = true;
    foreach ($resT2F['filtered_records'] as $r) {
        if (($r['terminal'] ?? '') !== '2F') {
            $allAreT2F = false;
            break;
        }
    }
    if ($allAreT2F && count($resT2F['filtered_records']) > 0) {
        echo "[PASS] DAU10A Terminal '2F' filter strictly isolated Terminal 2F records (" . count($resT2F['filtered_records']) . " rows)\n";
        $passed++;
    } else {
        echo "[FAIL] DAU10A Terminal filter did not isolate Terminal 2F properly\n";
        $failed++;
    }

    // Test DAU1 Direction filter
    $p1 = app(ReportTemplateRegistry::find('DAU1')['parser_class']);
    $dau1Parsed = $p1->parse(resource_path('templates/dau/DAU-1.xls'));
    $filterArr = [
        'direction'   => 'ARRIVAL',
        'terminal'    => 'ALL',
        'airline'     => 'ALL',
        'flight_type' => 'ALL',
        'hour'        => 'ALL',
        'metric'      => 'aircraft',
        'status'      => 'ALL',
        'category'    => 'ALL',
        'search'      => '',
        'top_n'       => 10,
        'threshold'   => 0,
    ];
    $resArr = $filterMethod->invoke($controller, $dau1Parsed['records'], $filterArr, $dau1Parsed['meta'] ?? [], 'DAU1');
    $allAreArr = true;
    foreach ($resArr['filtered_records'] as $r) {
        if (($r['aircraft_arrival'] ?? 0) <= 0) {
            $allAreArr = false;
            break;
        }
    }
    if ($allAreArr && count($resArr['filtered_records']) > 0) {
        echo "[PASS] DAU1 Direction 'ARRIVAL' filter strictly isolated arrival records (" . count($resArr['filtered_records']) . " rows)\n";
        $passed++;
    } else {
        echo "[FAIL] DAU1 Direction filter failed\n";
        $failed++;
    }
} catch (\Throwable $e) {
    echo "[FAIL] Filter test exception: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n======================================================================\n";
echo "FINAL TEST RESULT: {$passed} PASSED, {$failed} FAILED\n";
echo "======================================================================\n";
exit($failed > 0 ? 1 : 0);
