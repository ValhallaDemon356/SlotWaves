<?php

require 'c:/SlotWaves/vendor/autoload.php';
$app = require_once 'c:/SlotWaves/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$latestUpload = App\Models\Upload::where('status', 'completed')->has('flights')->latest('id')->first();
$uploadId = $latestUpload ? $latestUpload->id : 1;

$routes = [
    '/' => 'Home / Upload',
    '/master-data' => 'Master Data View',
    "/schedule/{$uploadId}/dashboard" => 'Dashboard',
    "/timeline/{$uploadId}" => '24-Hour Timeline',
    "/schedule/{$uploadId}/preview/time" => 'Preview Time Report',
    "/schedule/{$uploadId}/preview/dos" => 'Preview DOS Report',
    '/api/airports' => 'API Airports',
    '/api/airlines' => 'API Airlines',
    '/api/flights' => 'API Flights',
    "/api/upload/{$uploadId}/status" => 'API Upload Status',
];

echo "=== TESTING LARAVEL HTTP KERNEL DISPATCH ===\n";
echo "Testing with Upload ID: {$uploadId}\n\n";

foreach ($routes as $uri => $label) {
    $start = microtime(true);
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $response = $kernel->handle($request);
    $duration = round((microtime(true) - $start) * 1000, 1);
    $status = $response->getStatusCode();
    $length = strlen($response->getContent());
    
    echo sprintf("%-35s [%-22s] => Status %d (%d bytes, %.1f ms)\n", $uri, $label, $status, $length, $duration);
    $kernel->terminate($request, $response);
}

echo "\n=== ALL ROUTES TESTED VIA KERNEL ===\n";
