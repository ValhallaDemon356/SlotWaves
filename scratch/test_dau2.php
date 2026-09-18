<?php
require __DIR__ . '/../vendor/autoload.php';

$p = new App\Services\Dau\Parsers\DAU2Parser();
$res = $p->parse(__DIR__ . '/../resources/templates/dau/DAU-2.xls');
echo json_encode($res['meta'], JSON_PRETTY_PRINT) . PHP_EOL;
