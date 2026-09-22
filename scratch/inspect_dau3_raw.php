<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = new App\Services\Dau\Parsers\DAU3Parser();
$ref = new ReflectionClass($p);
$method = $ref->getMethod('extractRawTable');
$method->setAccessible(true);
$raw = $method->invoke($p, resource_path('templates/dau/DAU-3.xls'));

foreach ($raw as $idx => $r) {
    echo "Row {$idx}: " . json_encode(array_slice($r, 0, 8)) . "\n";
}
