<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $e) {
            error_log(sprintf(
                '[SlotWaves Exception] %s: %s in %s:%d' . PHP_EOL . '%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));
        });

        $exceptions->render(function (\Throwable $e, $request) {
            $errClass = get_class($e);
            $errMsg   = $e->getMessage();
            $errFile  = basename($e->getFile()) . ':' . $e->getLine();

            // Mask any potential passwords or keys in error messages
            $sanitizedMsg = preg_replace('/password=[^\s&]+/i', 'password=***', $errMsg);
            $sanitizedMsg = preg_replace('/:[^\s@]+@/i', ':***@', $sanitizedMsg);

            error_log(sprintf(
                '[SlotWaves Root Error] %s: %s in %s:%d' . PHP_EOL . '%s',
                $errClass,
                $sanitizedMsg,
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));

            if ($request->wantsJson() || $request->is('api/*')) {
                return new \Illuminate\Http\JsonResponse([
                    'error' => $sanitizedMsg,
                    'class' => $errClass,
                ], 500);
            }

            // Standalone HTML response: directly instantiate Illuminate\Http\Response
            // to bypass ResponseFactory -> redirect -> session.store dependency loop.
            $appKeyStatus   = (getenv('APP_KEY') || !empty($_ENV['APP_KEY']) || !empty($_SERVER['APP_KEY'])) ? '✓ Set' : '✗ MISSING';
            $dbHostStatus   = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? '✗ MISSING (defaults to 127.0.0.1)'));
            $dbUserStatus   = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? ($_SERVER['DB_USERNAME'] ?? '✗ MISSING'));
            $dbPassStatus   = (getenv('DB_PASSWORD') || !empty($_ENV['DB_PASSWORD']) || !empty($_SERVER['DB_PASSWORD'])) ? '✓ Set' : '✗ MISSING';
            $sessDriver     = getenv('SESSION_DRIVER') ?: ($_ENV['SESSION_DRIVER'] ?? ($_SERVER['SESSION_DRIVER'] ?? 'database'));

            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>SlotWaves — System Notice</title>';
            $html .= '<style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem;}';
            $html .= '.card{background:#1e293b;border:1px solid #334155;border-radius:1rem;padding:2rem;max-width:640px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);}';
            $html .= 'h1{margin:0 0 0.5rem;font-size:1.5rem;color:#f1f5f9;display:flex;align-items:center;gap:0.5rem;}';
            $html .= 'p{color:#94a3b8;font-size:0.875rem;line-height:1.5;margin:0 0 1rem;}';
            $html .= '.notice-box{background:#0f172a;border:1px solid #dc2626;border-radius:0.5rem;padding:1rem;margin:1rem 0;}';
            $html .= '.notice-title{color:#f87171;font-weight:700;font-size:0.8125rem;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.375rem;}';
            $html .= '.notice-code{font-family:monospace;font-size:0.75rem;color:#fca5a5;word-break:break-all;white-space:pre-wrap;}';
            $html .= '.env-list{background:#0f172a;border:1px solid #334155;border-radius:0.5rem;padding:0.75rem 1rem;font-family:monospace;font-size:0.75rem;color:#38bdf8;margin:1rem 0;line-height:1.6;}';
            $html .= 'a{display:inline-block;background:#0284c7;color:#fff;text-decoration:none;padding:0.5rem 1rem;border-radius:0.5rem;font-weight:600;font-size:0.875rem;margin-top:0.5rem;}';
            $html .= '</style></head><body><div class="card">';
            $html .= '<h1><span>✈️</span> SlotWaves — System Notice</h1>';
            $html .= '<p>The application encountered a configuration or connection issue during startup:</p>';
            $html .= '<div class="notice-box">';
            $html .= '<div class="notice-title">' . htmlspecialchars($errClass) . '</div>';
            $html .= '<div class="notice-code">' . htmlspecialchars($sanitizedMsg) . ' (' . htmlspecialchars($errFile) . ')</div>';
            $html .= '</div>';
            $html .= '<p style="margin-top:1rem;font-weight:600;color:#cbd5e1;">Vercel Environment Variables Status:</p>';
            $html .= '<div class="env-list">';
            $html .= 'APP_KEY: ' . $appKeyStatus . '<br>';
            $html .= 'DB_HOST: ' . htmlspecialchars($dbHostStatus) . '<br>';
            $html .= 'DB_USERNAME: ' . htmlspecialchars($dbUserStatus) . '<br>';
            $html .= 'DB_PASSWORD: ' . $dbPassStatus . '<br>';
            $html .= 'SESSION_DRIVER: ' . htmlspecialchars($sessDriver) . '<br>';
            $html .= '</div>';
            $html .= '<p style="font-size:0.75rem;color:#64748b;">Set any missing variables in <b>Vercel Project → Settings → Environment Variables</b> (under the Production Environment), then trigger a redeploy.</p>';
            $html .= '<a href="/">Reload Application</a>';
            $html .= '</div></body></html>';

            return new \Illuminate\Http\Response($html, 500, ['Content-Type' => 'text/html; charset=utf-8']);
        });
    })->create();

// Redirect storage path to /tmp in serverless environments (Vercel)
$storagePath = env('APP_STORAGE_PATH') ?: getenv('APP_STORAGE_PATH') ?: ($_SERVER['APP_STORAGE_PATH'] ?? null);
if ($storagePath) {
    $app->useStoragePath($storagePath);
}

return $app;
