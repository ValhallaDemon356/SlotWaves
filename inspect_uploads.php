<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$uploads = DB::table('uploads')->get();
echo "Total Uploads: " . $uploads->count() . "\n";
foreach ($uploads as $u) {
    echo "ID: {$u->id} | File: {$u->original_filename} | Status: {$u->status} | Airport ID: {$u->airport_id} | Flights: " . DB::table('flights')->where('upload_id', $u->id)->count() . "\n";
}
