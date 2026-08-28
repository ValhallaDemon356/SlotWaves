<?php

// Forward Vercel Serverless Function requests to Laravel entrypoint
$storageDir = '/tmp/storage';

$dirs = [
    $storageDir . '/app/public',
    $storageDir . '/app/private/uploads',
    $storageDir . '/framework/cache/data',
    $storageDir . '/framework/sessions',
    $storageDir . '/framework/testing',
    $storageDir . '/framework/views',
    $storageDir . '/logs',
    $storageDir . '/fonts',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

putenv("APP_STORAGE_PATH={$storageDir}");
$_ENV['APP_STORAGE_PATH'] = $storageDir;
$_SERVER['APP_STORAGE_PATH'] = $storageDir;

if (!getenv('VIEW_COMPILED_PATH')) {
    putenv("VIEW_COMPILED_PATH={$storageDir}/framework/views");
    $_ENV['VIEW_COMPILED_PATH'] = "{$storageDir}/framework/views";
    $_SERVER['VIEW_COMPILED_PATH'] = "{$storageDir}/framework/views";
}

require __DIR__ . '/../public/index.php';
