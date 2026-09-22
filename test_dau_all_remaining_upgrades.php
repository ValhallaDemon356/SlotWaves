<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\DauDashboardController;
use App\Models\Upload;
use App\Services\Dau\ReportTemplateRegistry;
use App\Services\Dau\Parsers\DAU1Parser;
use App\Services\Dau\Parsers\DAU2Parser;
use App\Services\Dau\Parsers\DAU3Parser;
use App\Services\Dau\Parsers\DAU4Parser;
use App\Services\Dau\Parsers\DAU4AParser;
use App\Services\Dau\Parsers\DAU4BParser;
use App\Services\Dau\Parsers\DAU5Parser;
use App\Services\Dau\Parsers\DAU5AParser;
use App\Services\Dau\Parsers\DAU5BParser;
use App\Services\Dau\Parsers\DAU5CParser;
use App\Services\Dau\Parsers\DAU6Parser;
use App\Services\Dau\Parsers\DAU10Parser;
use App\Services\Dau\Parsers\DAU10AParser;
use App\Services\Dau\Parsers\DAU10BParser;
use App\Services\Dau\Parsers\DAU11Parser;
use App\Services\Dau\Parsers\DAU12Parser;
use Illuminate\Support\Facades\View;

echo "\n======================================================================\n";
echo "SLOTWAVES — ALL DAU OPERATIONAL INTELLIGENCE UPGRADE VERIFICATION TEST\n";
echo "======================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest($cond, $label) {
    global $passed, $failed;
    if ($cond) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

// --------------------------------------------------------------------
// SECTION 1: STRICT UNTOUCHABLE GUARDRAILS (DAU-01, DAU-02, DAU-10A)
// --------------------------------------------------------------------
echo "\n--- 1. GUARDRAILS AUDIT (DAU-01, DAU-02, DAU-10A) ---\n";

// 1.1 DAU-01 Integrity
$dau1Path = resource_path('templates/dau/DAU-1.xls');
$dau1Parser = new DAU1Parser();
$dau1Parsed = $dau1Parser->parse($dau1Path);
assertTest(!empty($dau1Parsed['records']), "DAU-01 template parses successfully (" . count($dau1Parsed['records']) . " rows)");
assertTest(isset($dau1Parsed['ratios']['pax_per_flight']), "DAU-01 contains operational ratios (Pax/flight: {$dau1Parsed['ratios']['pax_per_flight']})");
assertTest(isset($dau1Parsed['ratios']['is_inbound_heavy']), "DAU-01 contains directional alert flag (is_inbound_heavy: " . ($dau1Parsed['ratios']['is_inbound_heavy'] ? 'true' : 'false') . ")");

// 1.2 DAU-02 Integrity (DO NOT TOUCH)
$dau2Path = resource_path('templates/dau/DAU-2.xls');
$dau2Parser = new DAU2Parser();
$dau2Parsed = $dau2Parser->parse($dau2Path);
assertTest(!empty($dau2Parsed['records']), "DAU-02 template parses successfully (" . count($dau2Parsed['records']) . " rows)");
assertTest($dau2Parsed['report_type'] === 'DAU2', "DAU-02 report_type is untouched");

// 1.3 DAU-10A Integrity (DO NOT TOUCH)
$dau10aPath = resource_path('templates/dau/DAU-10A.xls');
$dau10aParser = new DAU10AParser();
$dau10aParsed = $dau10aParser->parse($dau10aPath);
assertTest(!empty($dau10aParsed['records']), "DAU-10A template parses successfully (" . count($dau10aParsed['records']) . " rows)");
assertTest($dau10aParsed['report_type'] === 'DAU10A', "DAU-10A report_type is untouched");

// --------------------------------------------------------------------
// SECTION 2: PARSER OPERATIONAL METRICS FOR ALL REMAINING DAUS
// --------------------------------------------------------------------
echo "\n--- 2. PARSER OPERATIONAL METRICS (REMAINING DAUS) ---\n";

// 2.1 DAU-03: Flight Status & Regularity
echo "\n[DAU-03 Test]\n";
$dau3Path = resource_path('templates/dau/DAU-3.xls');
$dau3Parser = new DAU3Parser();
$dau3Parsed = $dau3Parser->parse($dau3Path);
assertTest(!empty($dau3Parsed['records']), "DAU-03 parsed (" . count($dau3Parsed['records']) . " rows)");
assertTest(isset($dau3Parsed['regularity']), "DAU-03 parse result contains 'regularity' structure");
$d3Reg = $dau3Parsed['regularity'];
assertTest(isset($d3Reg['scheduled_pct']) && isset($d3Reg['non_scheduled_pct']), "DAU-03 has scheduled ({$d3Reg['scheduled_pct']}%) and non-scheduled ({$d3Reg['non_scheduled_pct']}%) share");
assertTest(isset($d3Reg['extra_flight_impact']), "DAU-03 has extra flight impact metric ({$d3Reg['extra_flight_impact']}%)");
assertTest(isset($d3Reg['cancellation_tracked']) && $d3Reg['cancellation_tracked'] === false, "DAU-03 source-first honesty: cancellation_tracked is false when missing from source");

// 2.2 DAU-04: Route Intelligence (Origin vs Destination & Pareto Clustering)
echo "\n[DAU-04 Test]\n";
$dau4Path = resource_path('templates/dau/DAU-4.xls');
$dau4Parser = new DAU4Parser();
$dau4Parsed = $dau4Parser->parse($dau4Path);
assertTest(!empty($dau4Parsed['records']), "DAU-04 parsed (" . count($dau4Parsed['records']) . " rows)");
assertTest(isset($dau4Parsed['route_intelligence']), "DAU-04 parse result contains 'route_intelligence'");
$d4Intel = $dau4Parsed['route_intelligence'];
assertTest(isset($d4Intel['top_origins']) && count($d4Intel['top_origins']) <= 10, "DAU-04 top_origins has up to 10 entries (" . count($d4Intel['top_origins']) . ")");
assertTest(isset($d4Intel['top_destinations']) && count($d4Intel['top_destinations']) <= 10, "DAU-04 top_destinations has up to 10 entries (" . count($d4Intel['top_destinations']) . ")");
assertTest(isset($d4Intel['pareto_top10_share_pct']) && isset($d4Intel['pareto_others_share_pct']), "DAU-04 Top N + Others cluster calculated (Top 10: {$d4Intel['pareto_top10_share_pct']}%, Others: {$d4Intel['pareto_others_share_pct']}%)");

// 2.3 DAU-04A: Route Market Share Analyzer
echo "\n[DAU-04A Test]\n";
$dau4aPath = resource_path('templates/dau/DAU-4A.xls');
$dau4aParser = new DAU4AParser();
$dau4aParsed = $dau4aParser->parse($dau4aPath);
assertTest(!empty($dau4aParsed['records']), "DAU-04A parsed (" . count($dau4aParsed['records']) . " rows)");
assertTest(isset($dau4aParsed['market_share']), "DAU-04A parse result contains 'market_share'");
$d4aMS = $dau4aParsed['market_share'];
assertTest(!empty($d4aMS['routes']), "DAU-04A has computed route market shares (" . count($d4aMS['routes']) . " routes)");
if (!empty($d4aMS['routes'])) {
    $firstRoute = reset($d4aMS['routes']);
    assertTest(isset($firstRoute['dominant_carrier']) && isset($firstRoute['dominant_share_pct']), "DAU-04A route has dominant carrier ({$firstRoute['dominant_carrier']} @ {$firstRoute['dominant_share_pct']}%)");
}

// 2.4 DAU-04B: Matrix Heatmap
echo "\n[DAU-04B Test]\n";
$dau4bPath = resource_path('templates/dau/DAU-4B.xls');
$dau4bParser = new DAU4BParser();
$dau4bParsed = $dau4bParser->parse($dau4bPath);
assertTest(!empty($dau4bParsed['records']), "DAU-04B parsed (" . count($dau4bParsed['records']) . " rows)");
assertTest(isset($dau4bParsed['heatmap_matrix']), "DAU-04B parse result contains 'heatmap_matrix'");
$d4bHM = $dau4bParsed['heatmap_matrix'];
assertTest(!empty($d4bHM['airlines']) && !empty($d4bHM['routes']), "DAU-04B heatmap has airlines (" . count($d4bHM['airlines']) . ") and routes (" . count($d4bHM['routes']) . ")");
assertTest(isset($d4bHM['matrix']), "DAU-04B matrix grid generated");

// 2.5 DAU-05: Pareto Analysis & HHI
echo "\n[DAU-05 Test]\n";
$dau5Path = resource_path('templates/dau/DAU-5.xls');
$dau5Parser = new DAU5Parser();
$dau5Parsed = $dau5Parser->parse($dau5Path);
assertTest(!empty($dau5Parsed['records']), "DAU-05 parsed (" . count($dau5Parsed['records']) . " rows)");
assertTest(isset($dau5Parsed['pareto']), "DAU-05 parse result contains 'pareto'");
$d5Pareto = $dau5Parsed['pareto'];
assertTest(isset($d5Pareto['hhi']) && isset($d5Pareto['hhi_category']), "DAU-05 HHI calculated: {$d5Pareto['hhi']} ({$d5Pareto['hhi_category']})");
assertTest(isset($d5Pareto['tier1_anchor_airlines']), "DAU-05 Tier-1 Anchor Airlines identified (" . count($d5Pareto['tier1_anchor_airlines']) . " carriers)");

// 2.6 DAU-05A: Operator Operations & Crew Ratios
echo "\n[DAU-05A Test]\n";
$dau5aPath = resource_path('templates/dau/DAU-5A.xls');
$dau5aParser = new DAU5AParser();
$dau5aParsed = $dau5aParser->parse($dau5aPath);
assertTest(!empty($dau5aParsed['records']), "DAU-05A parsed (" . count($dau5aParsed['records']) . " rows)");
assertTest(isset($dau5aParsed['operator_ops']), "DAU-05A parse result contains 'operator_ops'");
$d5aOps = $dau5aParsed['operator_ops'];
assertTest(isset($d5aOps['operating_crew_ratio']) && isset($d5aOps['extra_crew_ratio']), "DAU-05A crew ratios calculated (Operating: {$d5aOps['operating_crew_ratio']}, Extra: {$d5aOps['extra_crew_ratio']})");

// 2.7 DAU-05B: Terminal Workload Allocation
echo "\n[DAU-05B Test]\n";
$dau5bPath = resource_path('templates/dau/DAU-5B.xls');
$dau5bParser = new DAU5BParser();
$dau5bParsed = $dau5bParser->parse($dau5bPath);
assertTest(!empty($dau5bParsed['records']), "DAU-05B parsed (" . count($dau5bParsed['records']) . " rows)");
assertTest(isset($dau5bParsed['terminal_allocation']), "DAU-05B parse result contains 'terminal_allocation'");
$d5bAlloc = $dau5bParsed['terminal_allocation'];
assertTest(isset($d5bAlloc['terminals']), "DAU-05B terminal workload allocation segmented");

// 2.8 DAU-05C: Carrier Efficiency & Fallback
echo "\n[DAU-05C Test]\n";
$dau5cPath = resource_path('templates/dau/DAU-5C.xls');
$dau5cParser = new DAU5CParser();
$dau5cParsed = $dau5cParser->parse($dau5cPath);
assertTest(!empty($dau5cParsed['records']), "DAU-05C parsed (" . count($dau5cParsed['records']) . " rows)");
assertTest(isset($dau5cParsed['carrier_efficiency']), "DAU-05C parse result contains 'carrier_efficiency'");
$d5cEff = $dau5cParsed['carrier_efficiency'];
assertTest(isset($d5cEff['has_seat_capacity']), "DAU-05C has strict source-first flag 'has_seat_capacity' (" . ($d5cEff['has_seat_capacity'] ? 'true' : 'false') . ")");

// 2.9 DAU-06: Aircraft Fleet & Aerodrome Standards
echo "\n[DAU-06 Test]\n";
$dau6Path = resource_path('templates/dau/DAU-6.xls');
$dau6Parser = new DAU6Parser();
$dau6Parsed = $dau6Parser->parse($dau6Path);
assertTest(!empty($dau6Parsed['records']), "DAU-06 parsed (" . count($dau6Parsed['records']) . " rows)");
assertTest(isset($dau6Parsed['aerodrome_profile']), "DAU-06 parse result contains 'aerodrome_profile'");
$d6Aero = $dau6Parsed['aerodrome_profile'];
assertTest(isset($d6Aero['icao_codes']['code_c']) && isset($d6Aero['icao_codes']['code_def']), "DAU-06 ICAO Aerodrome Code C/D/E/F mapped");
assertTest(isset($d6Aero['wtc_profiles']['medium']) && isset($d6Aero['wtc_profiles']['heavy']), "DAU-06 WTC safety profile mapped");

// 2.10 DAU-10: Peak Hours
echo "\n[DAU-10 Test]\n";
$dau10Path = resource_path('templates/dau/DAU-10.xls');
$dau10Parser = new DAU10Parser();
$dau10Parsed = $dau10Parser->parse($dau10Path);
assertTest(!empty($dau10Parsed['records']), "DAU-10 parsed (" . count($dau10Parsed['records']) . " rows)");
assertTest(isset($dau10Parsed['peak_hours']), "DAU-10 parse result contains 'peak_hours'");
$d10Peaks = $dau10Parsed['peak_hours'];
assertTest(isset($d10Peaks['top3_aircraft']) && count($d10Peaks['top3_aircraft']) > 0, "DAU-10 Top 3 Aircraft peak hours identified (Rank 1: {$d10Peaks['top3_aircraft'][0]['hour']} @ {$d10Peaks['top3_aircraft'][0]['volume']} A/C)");
assertTest(isset($d10Peaks['top3_passengers']) && count($d10Peaks['top3_passengers']) > 0, "DAU-10 Top 3 Passenger peak hours identified");

// 2.11 DAU-10B: Net Apron Delta & Dwell
echo "\n[DAU-10B Test]\n";
$dau10bPath = resource_path('templates/dau/DAU-10B.xls');
$dau10bParser = new DAU10BParser();
$dau10bParsed = $dau10bParser->parse($dau10bPath);
assertTest(!empty($dau10bParsed['records']), "DAU-10B parsed (" . count($dau10bParsed['records']) . " rows)");
assertTest(isset($dau10bParsed['dwell_intelligence']), "DAU-10B parse result contains 'dwell_intelligence'");
$d10bDwell = $dau10bParsed['dwell_intelligence'];
assertTest(isset($d10bDwell['hourly_deltas']) && count($d10bDwell['hourly_deltas']) === 24, "DAU-10B calculates 24 hourly Net Apron Deltas (DTG - BRK)");
assertTest(isset($d10bDwell['peak_accumulation_hour']), "DAU-10B tracks peak accumulation hour ({$d10bDwell['peak_accumulation_hour']} with delta {$d10bDwell['peak_accumulation_delta']})");

// 2.12 DAU-11 & DAU-12: Operational Traffic Matrix & CIQ
echo "\n[DAU-11 & DAU-12 Test]\n";
$dau11Path = resource_path('templates/dau/DAU-11.xls');
$dau11Parser = new DAU11Parser();
$dau11Parsed = $dau11Parser->parse($dau11Path);
assertTest(!empty($dau11Parsed['records']), "DAU-11 parsed (" . count($dau11Parsed['records']) . " rows)");
assertTest(isset($dau11Parsed['traffic_matrix']), "DAU-11 parse result contains 'traffic_matrix'");
$d11Mat = $dau11Parsed['traffic_matrix'];
assertTest(isset($d11Mat['quadrants']['dom_arr']) && isset($d11Mat['quadrants']['int_dep']), "DAU-11 2x2 operational quadrants calculated");
assertTest(isset($d11Mat['ciq']['demand_status']), "DAU-11 CIQ facility demand calculated (Status: {$d11Mat['ciq']['demand_status']})");

$dau12Path = resource_path('templates/dau/DAU-12.xls');
$dau12Parser = new DAU12Parser();
$dau12Parsed = $dau12Parser->parse($dau12Path);
assertTest(!empty($dau12Parsed['records']), "DAU-12 parsed (" . count($dau12Parsed['records']) . " rows)");
assertTest(isset($dau12Parsed['traffic_matrix']), "DAU-12 parse result contains 'traffic_matrix'");

// --------------------------------------------------------------------
// SECTION 3: CONTROLLER FILTER PIPELINE FOR ALL 16 DAUS
// --------------------------------------------------------------------
echo "\n--- 3. CONTROLLER FILTER PIPELINE FOR ALL DAUS ---\n";
$controller = app(DauDashboardController::class);

$allReportsToTest = [
    'DAU1'   => $dau1Parsed,
    'DAU2'   => $dau2Parsed,
    'DAU3'   => $dau3Parsed,
    'DAU4'   => $dau4Parsed,
    'DAU4A'  => $dau4aParsed,
    'DAU4B'  => $dau4bParsed,
    'DAU5'   => $dau5Parsed,
    'DAU5A'  => $dau5aParsed,
    'DAU5B'  => $dau5bParsed,
    'DAU5C'  => $dau5cParsed,
    'DAU6'   => $dau6Parsed,
    'DAU10'  => $dau10Parsed,
    'DAU10A' => $dau10aParsed,
    'DAU10B' => $dau10bParsed,
    'DAU11'  => $dau11Parsed,
    'DAU12'  => $dau12Parsed,
];

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

foreach ($allReportsToTest as $rType => $parsedData) {
    try {
        $analytics = $controller->filterReportDataset($parsedData['records'], $defaultFilters, $parsedData['meta'] ?? [], $rType);
        assertTest(isset($analytics['summary']), "Controller filterReportDataset for {$rType} returned valid summary");
    } catch (\Throwable $e) {
        assertTest(false, "Controller filterReportDataset for {$rType} threw exception: " . $e->getMessage());
    }
}

// --------------------------------------------------------------------
// SECTION 4: BLADE VIEW COMPILATION TEST FOR ALL DAUS
// --------------------------------------------------------------------
echo "\n--- 4. BLADE VIEW COMPILATION TEST FOR ALL DAUS ---\n";

foreach ($allReportsToTest as $rType => $parsedData) {
    try {
        $analytics = $controller->filterReportDataset($parsedData['records'], $defaultFilters, $parsedData['meta'] ?? [], $rType);

        $upload = new Upload([
            'report_type' => $rType,
            'status'      => 'completed',
            'report_data' => $parsedData,
        ]);
        $upload->id = 1000 + (crc32($rType) % 1000);
        $upload->exists = true;

        $viewData = [
            'upload'                  => $upload,
            'reportType'              => $rType,
            'conf'                    => ReportTemplateRegistry::find($rType) ?? [
                'code' => $rType,
                'name' => "Report {$rType}",
                'icon' => 'chart-bar',
                'description' => "Test description for {$rType}",
            ],
            'data'                    => $parsedData,
            'meta'                    => $parsedData['meta'] ?? [],
            'summary'                 => $analytics['summary'] ?? [],
            'records'                 => $parsedData['records'] ?? [],
            'columns'                 => $parsedData['columns'] ?? [],
            'terminals'               => [],
            'hours'                   => [],
            'airlines'                => [],
            'airports'                => [],
            'aircraftTypes'           => [],
            'categories'              => [],
            'peaks'                   => $analytics['peaks'] ?? [],
            'hourlyDistribution'      => $analytics['hourly_distribution'] ?? [],
            'terminalComparison'      => $analytics['terminal_comparison'] ?? [],
            'matrixRecords'           => $parsedData['records'] ?? [],
            'analytics'               => $analytics,
            'dau1Ratios'              => $analytics['dau1_ratios'] ?? [],
            'dau2Comparative'         => $analytics['dau2_comparative'] ?? [],
            'dau3Regularity'          => $analytics['dau3_regularity'] ?? [],
            'dau4Intelligence'        => $analytics['dau4_intelligence'] ?? [],
            'dau4aMarketShare'        => $analytics['dau4a_market_share'] ?? [],
            'dau4bIntelligence'       => $analytics['dau4b_intelligence'] ?? [],
            'dau5ParetoIntel'         => $analytics['dau5_pareto_intel'] ?? [],
            'dau5aOps'                => $analytics['dau5a_ops'] ?? [],
            'dau5bAllocation'         => $analytics['dau5b_allocation'] ?? [],
            'dau5cEfficiency'         => $analytics['dau5c_efficiency'] ?? [],
            'dau6Aerodrome'           => $analytics['dau6_aerodrome'] ?? [],
            'dau10PeakIntel'          => $analytics['dau10_peak_intel'] ?? [],
            'dau10bDwell'             => $analytics['dau10b_dwell'] ?? [],
            'dau11TrafficMatrix'      => $analytics['dau11_matrix'] ?? [],
            'dau12TrafficMatrix'      => $analytics['dau12_matrix'] ?? [],
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
        ];

        $html = View::make('dau.dashboard', $viewData)->render();
        assertTest(strlen($html) > 5000, "Blade view for {$rType} compiled cleanly (" . strlen($html) . " bytes)");
    } catch (\Throwable $e) {
        assertTest(false, "Blade view for {$rType} failed compilation: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    }
}

echo "\n======================================================================\n";
echo "FINAL TEST RESULT: {$passed} PASSED, {$failed} FAILED\n";
echo "======================================================================\n";

exit($failed > 0 ? 1 : 0);
