<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

$controller = app(\App\Http\Controllers\Api\MasterDataController::class);

echo "=== 1. API: /api/airports?management_type=UPT_DAERAH_PEMDA ===\n";
$req1 = Request::create('/api/airports', 'GET', ['management_type' => 'UPT_DAERAH_PEMDA']);
$res1 = $controller->airports($req1);
echo $res1->getContent() . "\n\n";

echo "=== 2. API: /api/airports?region=IV ===\n";
$req2 = Request::create('/api/airports', 'GET', ['region' => 'IV']);
$res2 = $controller->airports($req2);
echo $res2->getContent() . "\n\n";

echo "=== 3. API: /api/airlines?category=domestic ===\n";
$req3 = Request::create('/api/airlines', 'GET', ['category' => 'domestic']);
$res3 = $controller->airlines($req3);
echo $res3->getContent() . "\n\n";

echo "=== 4. API: /api/flights?airline_code=JT ===\n";
$req4 = Request::create('/api/flights', 'GET', ['airline_code' => 'JT']);
$res4 = $controller->flights($req4);
$json4 = json_decode($res4->getContent(), true);
echo "Count for JT flights: " . $json4['count'] . "\n";
echo "First JT flight sample: " . json_encode($json4['data'][0] ?? [], JSON_PRETTY_PRINT) . "\n";
