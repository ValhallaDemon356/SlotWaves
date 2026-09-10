<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;

$uploads = Upload::where('report_type', 'DAU10A')
    ->orderByDesc('id')
    ->take(5)
    ->get();

foreach ($uploads as $u) {
    echo "=== Upload ID: {$u->id} ===\n";
    echo "Filename: {$u->original_filename}\n";
    $meta = $u->report_data['meta'] ?? [];
    echo "Meta start_date: " . ($meta['start_date'] ?? 'NULL') . "\n";
    echo "Meta end_date:   " . ($meta['end_date'] ?? 'NULL') . "\n";
    echo "Meta date_range: " . ($meta['date_range'] ?? 'NULL') . "\n";

    // Check available_dates in report_data
    $availDates = $u->report_data['available_dates'] ?? null;
    $availDays = $u->report_data['available_days'] ?? null;
    echo "report_data.available_dates count: " . (is_array($availDates) ? count($availDates) : 'NULL') . "\n";
    echo "report_data.available_days: " . ($availDays ?? 'NULL') . "\n";
    if (is_array($availDates) && count($availDates) > 0) {
        echo "  First date: " . $availDates[0] . "\n";
        echo "  Last date:  " . $availDates[count($availDates) - 1] . "\n";
    }

    // Check normalized_pairs for date field
    $pairs = $u->report_data['normalized_pairs'] ?? [];
    echo "normalized_pairs count: " . count($pairs) . "\n";
    $uniqueDates = [];
    foreach ($pairs as $p) {
        $d = $p['date'] ?? null;
        if ($d && !in_array($d, $uniqueDates)) {
            $uniqueDates[] = $d;
        }
    }
    sort($uniqueDates);
    echo "Unique dates from normalized_pairs: " . count($uniqueDates) . "\n";
    if (count($uniqueDates) > 0) {
        echo "  Min: " . $uniqueDates[0] . "\n";
        echo "  Max: " . $uniqueDates[count($uniqueDates) - 1] . "\n";
    }
    if (count($uniqueDates) > 0) {
        $sample = $pairs[0] ?? [];
        echo "  Sample pair keys: " . implode(', ', array_keys($sample)) . "\n";
    }
    echo "\n";
}
