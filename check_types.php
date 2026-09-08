<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$types = App\Models\Upload::where('status', 'completed')
    ->whereNotNull('report_type')
    ->get()
    ->groupBy('report_type')
    ->map(fn($g) => $g->last()->id);

echo json_encode($types, JSON_PRETTY_PRINT) . PHP_EOL;
