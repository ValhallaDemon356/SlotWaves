<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Uploads columns:\n";
$cols = DB::select("DESCRIBE uploads");
foreach ($cols as $c) {
    echo "  {$c->Field} ({$c->Type})\n";
}

