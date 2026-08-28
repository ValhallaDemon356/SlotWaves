<?php

require 'c:/SlotWaves/vendor/autoload.php';
$app = require_once 'c:/SlotWaves/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$routes = [
    '/' => 'Home / Upload',
    '/master-data' => 'Master Data View',
    '/schedule/1/dashboard' => 'Dashboard',
    '/timeline/1' => '24-Hour Timeline',
    '/schedule/1/preview/time' => 'Preview Time Report',
    '/schedule/1/preview/dos' => 'Preview DOS Report',
    '/api/airports' => 'API Airports',
    '/api/airlines' => 'API Airlines',
    '/api/flights' => 'API Flights',
];

echo "=== TESTING LARAVEL HTTP KERNEL DISPATCH ===\n";

foreach ($routes as $uri => $label) {
    $start = microtime(true);
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $response = $kernel->handle($request);
    $duration = round((microtime(true) - $start) * 1000, 1);
    $status = $response->getStatusCode();
    $length = strlen($response->getContent());
    
    echo sprintf("%-30s [%-22s] => Status %d (%d bytes, %.1f ms)\n", $uri, $label, $status, $length, $duration);
    $kernel->terminate($request, $response);
}

echo "=== ALL ROUTES TESTED VIA KERNEL ===\n";
