<?php

/**
 * SlotWaves — Vercel Serverless PHP Entry Point
 *
 * This file bootstraps the serverless environment before handing off to
 * Laravel's public/index.php. It:
 *  - Sets the working directory to the project root
 *  - Creates required /tmp/storage directories (writable in Vercel)
 *  - Establishes sensible env-var defaults for serverless (non-secret only)
 *  - Forwards the request to Laravel
 *
 * IMPORTANT: All secrets (APP_KEY, DB_PASSWORD, etc.) MUST be set in the
 * Vercel Project Dashboard → Settings → Environment Variables.
 * DO NOT hardcode secrets in this file.
 */

// ── 1. Working directory ─────────────────────────────────────────────────────
chdir(__DIR__ . '/..');

// ── 2. Normalize routing vars for Laravel in Vercel Serverless ───────────────
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ . '/../public/index.php')
    ?: (__DIR__ . '/../public/index.php');

// ── 3. Create writable /tmp/storage directories ───────────────────────────────
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

// ── 4. Set storage path env vars ─────────────────────────────────────────────
$_envSet = static function (string $key, string $value): void {
    putenv("{$key}={$value}");
    $_ENV[$key]    = $value;
    $_SERVER[$key] = $value;
};

$_envSetIfMissing = static function (string $key, string $value) use ($_envSet): void {
    if (!getenv($key) && empty($_ENV[$key]) && empty($_SERVER[$key])) {
        $_envSet($key, $value);
    }
};

// Storage path (always override — these are structural, not secrets)
$_envSet('APP_STORAGE_PATH', $storageDir);

if (!getenv('VIEW_COMPILED_PATH')) {
    $_envSet('VIEW_COMPILED_PATH', "{$storageDir}/framework/views");
}

// Cache file paths: override to /tmp so config:cache works in serverless
// vercel.json env block already sets these; the if-missing check is a safety net
$_envSetIfMissing('APP_CONFIG_CACHE',   '/tmp/config.php');
$_envSetIfMissing('APP_EVENTS_CACHE',   '/tmp/events.php');
$_envSetIfMissing('APP_PACKAGES_CACHE', '/tmp/packages.php');
$_envSetIfMissing('APP_ROUTES_CACHE',   '/tmp/routes.php');
$_envSetIfMissing('APP_SERVICES_CACHE', '/tmp/services.php');

// ── 5. Non-secret structural defaults ────────────────────────────────────────
// These are safe to set here because they are non-secret operational values.
// Secrets (APP_KEY, DB_PASSWORD, etc.) MUST be in the Vercel dashboard.

$_envSetIfMissing('APP_ENV',   'production');
$_envSetIfMissing('APP_DEBUG', 'false');

// Maintenance driver: use file (reads from /tmp/storage/framework/maintenance.php)
$_envSetIfMissing('APP_MAINTENANCE_DRIVER', 'file');

// Logging: always use stderr in serverless (visible in Vercel function logs)
$_envSetIfMissing('LOG_CHANNEL', 'stderr');

// Session: database driver backed by Supabase `sessions` table (persistent)
// This is set in vercel.json env block; this is a safety net only.
$_envSetIfMissing('SESSION_DRIVER', 'database');

// Session cookie must be Secure on HTTPS production — prevents 419 CSRF errors
// This is set in vercel.json env block; this is a safety net only.
$_envSetIfMissing('SESSION_SECURE_COOKIE', 'true');

// Cache: database driver backed by Supabase `cache` / `cache_locks` tables
$_envSetIfMissing('CACHE_STORE', 'database');

// Queue: synchronous (no worker processes in serverless)
$_envSetIfMissing('QUEUE_CONNECTION', 'sync');

// ── 6. Database: default to Supabase PostgreSQL ───────────────────────────────
$dbConn = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? ($_SERVER['DB_CONNECTION'] ?? null));
$dbUrl  = getenv('DB_URL') ?: (getenv('DATABASE_URL') ?: (getenv('POSTGRES_URL') ?: null));

if ($dbUrl && !getenv('DB_URL')) {
    $_envSet('DB_URL', $dbUrl);
}

// If no connection explicitly set and we have a postgres URL, default to pgsql
if (empty($dbConn)) {
    $_envSetIfMissing('DB_CONNECTION', 'pgsql');
}

// ── 7. Bootstrap Laravel ──────────────────────────────────────────────────────
try {
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    // Log full error to Vercel stderr (visible in function logs)
    error_log(sprintf(
        '[SlotWaves] %s: %s in %s:%d%s%s',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        $e->getTraceAsString()
    ));

    // Return a minimal error response — do NOT expose stack traces or secrets
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>SlotWaves — Server Error</title></head><body>';
    echo '<h1>500 — Server Error</h1>';
    echo '<p>An internal error occurred. Please check the Vercel function logs for details.</p>';

    // In non-production (preview/dev), show the exception class and message for debugging
    if (getenv('APP_ENV') !== 'production') {
        echo '<hr><pre style="background:#f4f4f4;padding:1rem">';
        echo htmlspecialchars(get_class($e) . ': ' . $e->getMessage());
        echo "\n\nFile: " . htmlspecialchars($e->getFile()) . ':' . $e->getLine();
        echo '</pre>';
    }

    echo '</body></html>';
}
