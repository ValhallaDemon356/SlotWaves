<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = new App\Services\Dau\Parsers\DAU3Parser();
$res = $p->parse(resource_path('templates/dau/DAU-3.xls'));
print_r($res['records']);
