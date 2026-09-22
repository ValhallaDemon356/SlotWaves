<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\DauDashboardController;
use App\Services\Dau\Parsers\DAU1Parser;
use Illuminate\Support\Facades\View;

echo "\n======================================================================\n";
echo "SLOTWAVES — DAU-01 OPERATIONAL INTELLIGENCE & RATIOS VERIFICATION TEST\n";
echo "======================================================================\n\n";

$passed = 0;
$failed = 0;

function assertCondition($cond, $label) {
    global $passed, $failed;
    if ($cond) {
        echo "[PASS] {$label}\n";
        $passed++;
    } else {
        echo "[FAIL] {$label}\n";
        $failed++;
    }
}

// 1. Direct unit test of DAU1Parser::calculateRatios
$sampleSummary = [
    'total_movements'    => 100,
    'aircraft_total'     => 100,
    'aircraft_arrival'   => 75,
    'aircraft_departure' => 25,
    'passenger_total'    => 15000,
    'passenger_adult'    => 12000,
    'passenger_child'    => 2250,
    'passenger_infant'   => 750,
    'baggage_total'      => 225000, // 15 kg per pax
    'cargo_total'        => 50000,  // 50 tons total, 0.5 ton per flight
];

$ratios = DAU1Parser::calculateRatios($sampleSummary);

assertCondition($ratios['pax_per_flight'] === 150.0, "Pax per flight calculated correctly: 150.0 pax/flight (got {$ratios['pax_per_flight']})");
assertCondition($ratios['baggage_per_pax'] === 15.0, "Baggage per pax calculated correctly: 15.0 kg/pax (got {$ratios['baggage_per_pax']})");
assertCondition($ratios['cargo_density_ton'] === 0.5, "Cargo density calculated correctly: 0.5 tons/flight (got {$ratios['cargo_density_ton']})");
assertCondition($ratios['inbound_ratio'] === 75.0, "Inbound ratio calculated correctly: 75.0% (got {$ratios['inbound_ratio']})");
assertCondition($ratios['is_inbound_heavy'] === true, "Inbound Heavy alert flag is TRUE when inbound > 70% (got " . ($ratios['is_inbound_heavy'] ? 'true' : 'false') . ")");
assertCondition($ratios['adult_pct'] === 80.0, "Adult passenger demographic share is 80.0% (got {$ratios['adult_pct']}%)");
assertCondition($ratios['child_pct'] === 15.0, "Child passenger demographic share is 15.0% (got {$ratios['child_pct']}%)");
assertCondition($ratios['infant_pct'] === 5.0, "Infant passenger demographic share is 5.0% (got {$ratios['infant_pct']}%)");

// Test Balanced condition (50/50)
$balancedSummary = [
    'total_movements'    => 100,
    'aircraft_arrival'   => 50,
    'aircraft_departure' => 50,
    'passenger_total'    => 10000,
    'baggage_total'      => 100000,
    'cargo_total'        => 100000,
];
$balRatios = DAU1Parser::calculateRatios($balancedSummary);
assertCondition($balRatios['is_inbound_heavy'] === false, "Inbound Heavy alert flag is FALSE for balanced 50/50 traffic");
assertCondition($balRatios['directional_status'] === 'BALANCED', "Directional status is 'BALANCED' for 50/50 traffic");

// 2. Integration test with authentic DAU-1.xls template
$parser = new DAU1Parser();
$filePath = resource_path('templates/dau/DAU-1.xls');
$parsed = $parser->parse($filePath);
assertCondition(!empty($parsed['records']), "DAU1Parser parses authentic DAU-1.xls (" . count($parsed['records']) . " rows)");
assertCondition(isset($parsed['ratios']['pax_per_flight']), "DAU1Parser parse output includes precomputed 'ratios'");

// 3. Test Controller filterReportDataset with DAU1
$controller = app(DauDashboardController::class);
$defaultFilters = [
    'direction'      => 'ALL',
    'terminal'       => 'ALL',
    'airline'        => 'ALL',
    'flight_type'    => 'ALL',
    'hour'           => 'ALL',
    'metric'         => 'aircraft',
    'status'         => 'ALL',
    'category'       => 'ALL',
    'search'         => '',
    'top_n'          => 10,
    'threshold'      => 0,
    'passenger_type' => 'ALL',
];
$analytics = $controller->filterReportDataset($parsed['records'], $defaultFilters, $parsed['meta'] ?? [], 'DAU1');
assertCondition(isset($analytics['dau1_ratios']), "filterReportDataset returns 'dau1_ratios' structure");
assertCondition($analytics['dau1_ratios']['total_movements'] > 0, "filterReportDataset dau1_ratios has positive movements ({$analytics['dau1_ratios']['total_movements']})");
assertCondition($analytics['dau1_ratios']['pax_per_flight'] > 0, "filterReportDataset dau1_ratios has positive pax per flight ({$analytics['dau1_ratios']['pax_per_flight']})");

// 4. Test Blade compilation for DAU-1 view
try {
    $upload = new \App\Models\Upload([
        'report_type' => 'DAU1',
        'status'      => 'completed',
        'report_data' => $parsed,
    ]);
    $upload->id = 9999;
    $upload->exists = true;

    $viewHtml = View::make('dau.dashboard', [
        'upload'                  => $upload,
        'reportType'              => 'DAU1',
        'conf'                    => \App\Services\Dau\ReportTemplateRegistry::find('DAU1'),
        'data'                    => $parsed,
        'meta'                    => $parsed['meta'] ?? [],
        'summary'                 => $analytics['summary'],
        'records'                 => $parsed['records'],
        'columns'                 => $parsed['columns'] ?? [],
        'terminals'               => [],
        'hours'                   => [],
        'airlines'                => [],
        'airports'                => [],
        'aircraftTypes'           => [],
        'categories'              => [],
        'peaks'                   => $analytics['peaks'],
        'hourlyDistribution'      => $analytics['hourly_distribution'],
        'terminalComparison'      => $analytics['terminal_comparison'],
        'matrixRecords'           => [],
        'analytics'               => $analytics,
        'dau1Ratios'              => $analytics['dau1_ratios'],
        'dau2Comparative'         => [],
        'filters'                 => $defaultFilters,
        'initialNac'              => 6,
        'initialArrivalCapacity'  => 6,
        'initialDepartureCapacity'=> 6,
        'opsStartTime'            => '06:00',
        'opsEndTime'              => '20:00',
        'tzAbbr'                  => 'WIB',
        'tzOffset'                => 7,
        'availableDates'          => [],
        'totalAvailableDays'      => 1,
    ])->render();

    assertCondition(strlen($viewHtml) > 5000, "Blade view compiled successfully (" . strlen($viewHtml) . " bytes)");
    assertCondition(strpos($viewHtml, 'Pax per Flight Ratio') !== false, "Compiled Blade view contains 'Pax per Flight Ratio'");
    assertCondition(strpos($viewHtml, 'Baggage Load per Pax') !== false, "Compiled Blade view contains 'Baggage Load per Pax'");
    assertCondition(strpos($viewHtml, 'Cargo to Flight Density') !== false, "Compiled Blade view contains 'Cargo to Flight Density'");
    assertCondition(strpos($viewHtml, 'Directional Balance') !== false, "Compiled Blade view contains 'Directional Balance'");
    assertCondition(strpos($viewHtml, 'Inbound Heavy — Apron Congestion Alert') !== false, "Compiled Blade view contains 'Inbound Heavy — Apron Congestion Alert' banner markup");
    assertCondition(strpos($viewHtml, 'PASSENGER DEMOGRAPHIC BREAKDOWN') !== false, "Compiled Blade view contains 'PASSENGER DEMOGRAPHIC BREAKDOWN'");
} catch (\Throwable $e) {
    assertCondition(false, "Blade compilation threw exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
}

echo "\n======================================================================\n";
echo "FINAL TEST RESULT: {$passed} PASSED, {$failed} FAILED\n";
echo "======================================================================\n";
exit($failed > 0 ? 1 : 0);
