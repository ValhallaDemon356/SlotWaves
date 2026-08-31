<?php

/**
 * SlotWaves — Vercel Serverless PHP Entry Point
 *
 * Bootstraps the serverless environment, creates writable /tmp/storage
 * directories, sets non-secret env defaults, then delegates to Laravel's
 * public/index.php.
 *
 * SECURITY: Secrets (APP_KEY, DB_PASSWORD, etc.) MUST be set in Vercel
 * Project → Settings → Environment Variables. Never hardcode them here.
 */

// Register an error handler that logs to stderr before anything else runs.
// This catches fatal errors that happen before the try/catch below.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log(sprintf(
            '[SlotWaves FATAL] %s in %s:%d',
            $err['message'],
            $err['file'],
            $err['line']
        ));
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>500</title></head><body>';
            echo '<h1>500 — Bootstrap Error</h1>';
            if (getenv('APP_ENV') !== 'production') {
                echo '<pre>' . htmlspecialchars($err['message']) . "\n" . $err['file'] . ':' . $err['line'] . '</pre>';
            }
            echo '</body></html>';
        }
    }
});

// ── 1. Working directory ──────────────────────────────────────────────────────
chdir(__DIR__ . '/..');

// ── 2. Normalise Vercel routing vars for Laravel ──────────────────────────────
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ . '/../public/index.php')
    ?: (__DIR__ . '/../public/index.php');

// ── 3. Create writable /tmp/storage directories ───────────────────────────────
$storageDir = '/tmp/storage';
foreach ([
    $storageDir . '/app/public',
    $storageDir . '/app/private/uploads',
    $storageDir . '/framework/cache/data',
    $storageDir . '/framework/sessions',
    $storageDir . '/framework/testing',
    $storageDir . '/framework/views',
    $storageDir . '/logs',
    $storageDir . '/fonts',
] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ── 4. Env helpers ────────────────────────────────────────────────────────────
function _sw_setenv(string $key, string $value): void {
    putenv("{$key}={$value}");
    $_ENV[$key]    = $value;
    $_SERVER[$key] = $value;
}

function _sw_setenv_default(string $key, string $value): void {
    if (getenv($key) === false && empty($_ENV[$key]) && empty($_SERVER[$key])) {
        _sw_setenv($key, $value);
    }
}

// ── 5. Storage path (always set — structural, not a secret) ──────────────────
_sw_setenv('APP_STORAGE_PATH', $storageDir);
_sw_setenv_default('VIEW_COMPILED_PATH', $storageDir . '/framework/views');

// ── 6. Serverless cache-file paths (vercel.json env block sets these too) ────
_sw_setenv_default('APP_CONFIG_CACHE',   '/tmp/config.php');
_sw_setenv_default('APP_EVENTS_CACHE',   '/tmp/events.php');
_sw_setenv_default('APP_PACKAGES_CACHE', '/tmp/packages.php');
_sw_setenv_default('APP_ROUTES_CACHE',   '/tmp/routes.php');
_sw_setenv_default('APP_SERVICES_CACHE', '/tmp/services.php');

// ── 7. Non-secret runtime defaults ───────────────────────────────────────────
_sw_setenv_default('APP_ENV',                'production');
_sw_setenv_default('APP_DEBUG',              'false');
_sw_setenv_default('APP_MAINTENANCE_DRIVER', 'file');
_sw_setenv_default('LOG_CHANNEL',            'stderr');
_sw_setenv_default('SESSION_DRIVER',         'database');
_sw_setenv_default('SESSION_SECURE_COOKIE',  'true');
_sw_setenv_default('CACHE_STORE',            'database');
_sw_setenv_default('QUEUE_CONNECTION',       'sync');
_sw_setenv_default('DB_CONNECTION',          'pgsql');

// ── 8. PostgreSQL SSL and Port Defaults for Supabase ─────────────────────────
_sw_setenv_default('DB_SSLMODE', 'require');
_sw_setenv_default('DB_PORT',    '6543');

// ── 9. Bootstrap Laravel ──────────────────────────────────────────────────────
try {
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    error_log(sprintf(
        '[SlotWaves] %s: %s in %s:%d' . PHP_EOL . '%s',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>SlotWaves — Error</title></head><body>';
        echo '<h1>500 — Server Error</h1>';
        echo '<p>Check Vercel function logs for the full error trace.</p>';
        if (getenv('APP_ENV') !== 'production') {
            echo '<hr><pre>' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage())
                . "\n" . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</pre>';
        }
        echo '</body></html>';
    }
}
