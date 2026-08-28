<?php

// Set working directory to project root for consistent relative paths
chdir(__DIR__ . '/..');

// Normalize SCRIPT_NAME and SCRIPT_FILENAME for Laravel routing in Vercel Serverless
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ . '/../public/index.php') ?: (__DIR__ . '/../public/index.php');

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

if (!getenv('APP_CONFIG_CACHE')) {
    putenv("APP_CONFIG_CACHE={$storageDir}/config.php");
    $_ENV['APP_CONFIG_CACHE'] = "{$storageDir}/config.php";
    $_SERVER['APP_CONFIG_CACHE'] = "{$storageDir}/config.php";
}

if (!getenv('APP_EVENTS_CACHE')) {
    putenv("APP_EVENTS_CACHE={$storageDir}/events.php");
    $_ENV['APP_EVENTS_CACHE'] = "{$storageDir}/events.php";
    $_SERVER['APP_EVENTS_CACHE'] = "{$storageDir}/events.php";
}

if (!getenv('APP_PACKAGES_CACHE')) {
    putenv("APP_PACKAGES_CACHE={$storageDir}/packages.php");
    $_ENV['APP_PACKAGES_CACHE'] = "{$storageDir}/packages.php";
    $_SERVER['APP_PACKAGES_CACHE'] = "{$storageDir}/packages.php";
}

if (!getenv('APP_ROUTES_CACHE')) {
    putenv("APP_ROUTES_CACHE={$storageDir}/routes.php");
    $_ENV['APP_ROUTES_CACHE'] = "{$storageDir}/routes.php";
    $_SERVER['APP_ROUTES_CACHE'] = "{$storageDir}/routes.php";
}

if (!getenv('APP_SERVICES_CACHE')) {
    putenv("APP_SERVICES_CACHE={$storageDir}/services.php");
    $_ENV['APP_SERVICES_CACHE'] = "{$storageDir}/services.php";
    $_SERVER['APP_SERVICES_CACHE'] = "{$storageDir}/services.php";
}

// Enable APP_DEBUG and APP_ENV=local to see exact exception on production
putenv('APP_ENV=local');
$_ENV['APP_ENV'] = 'local';
$_SERVER['APP_ENV'] = 'local';

putenv('APP_DEBUG=true');
$_ENV['APP_DEBUG'] = 'true';
$_SERVER['APP_DEBUG'] = 'true';

// Fallback APP_KEY if not yet configured in Vercel dashboard
if (!getenv('APP_KEY')) {
    putenv('APP_KEY=base64:5Zmfg8R9HutjOLTPOWXKrT6XP2ZAPlufqDq8VCY/9Fs=');
    $_ENV['APP_KEY'] = 'base64:5Zmfg8R9HutjOLTPOWXKrT6XP2ZAPlufqDq8VCY/9Fs=';
    $_SERVER['APP_KEY'] = 'base64:5Zmfg8R9HutjOLTPOWXKrT6XP2ZAPlufqDq8VCY/9Fs=';
}

// Fallback session driver to cookie if not explicitly configured in serverless
if (!getenv('SESSION_DRIVER')) {
    putenv('SESSION_DRIVER=cookie');
    $_ENV['SESSION_DRIVER'] = 'cookie';
    $_SERVER['SESSION_DRIVER'] = 'cookie';
}

// Fallback cache driver to array if not explicitly configured in serverless
if (!getenv('CACHE_STORE')) {
    putenv('CACHE_STORE=array');
    $_ENV['CACHE_STORE'] = 'array';
    $_SERVER['CACHE_STORE'] = 'array';
}

// Fallback log channel to stderr in serverless
if (!getenv('LOG_CHANNEL')) {
    putenv('LOG_CHANNEL=stderr');
    $_ENV['LOG_CHANNEL'] = 'stderr';
    $_SERVER['LOG_CHANNEL'] = 'stderr';
}

// Fallback SQLite file in /tmp if remote database is not configured
if (!getenv('DB_CONNECTION') && !getenv('DB_HOST')) {
    $sqliteFile = $storageDir . '/database.sqlite';
    if (!file_exists($sqliteFile)) {
        @touch($sqliteFile);
    }
    if (!getenv('DB_DATABASE')) {
        putenv("DB_DATABASE={$sqliteFile}");
        $_ENV['DB_DATABASE'] = $sqliteFile;
        $_SERVER['DB_DATABASE'] = $sqliteFile;
    }
}

try {
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<h1>SlotWaves Serverless Runtime Exception</h1>";
    echo "<p><strong>Type:</strong> " . htmlspecialchars(get_class($e)) . "</p>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
