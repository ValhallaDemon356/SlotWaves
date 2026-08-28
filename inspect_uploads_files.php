<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use Illuminate\Support\Facades\Storage;

$uploads = Upload::orderBy('id', 'desc')->get();
echo "Total Uploads: " . $uploads->count() . "\n";
foreach ($uploads->take(10) as $u) {
    $path = Storage::disk('local')->path($u->stored_path);
    $exists = file_exists($path) ? "EXISTS (" . round(filesize($path)/1024, 1) . " KB)" : "MISSING";
    echo "ID: {$u->id} | {$u->original_filename} | {$u->stored_path} | {$exists} | Status: {$u->status}\n";
}
