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
            error_log(sprintf(
                '[SlotWaves Root Error] %s: %s in %s:%d' . PHP_EOL . '%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));

            if ($request->wantsJson() || $request->is('api/*')) {
                return new \Illuminate\Http\JsonResponse([
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ], 500);
            }

            // Standalone HTML response: directly instantiate Illuminate\Http\Response
            // to bypass ResponseFactory -> redirect -> session.store dependency loop.
            $debug = config('app.debug', false);
            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>SlotWaves — System Notice</title>';
            $html .= '<style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem;}';
            $html .= '.card{background:#1e293b;border:1px solid #334155;border-radius:1rem;padding:2rem;max-width:600px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);}';
            $html .= 'h1{margin:0 0 0.5rem;font-size:1.5rem;color:#f1f5f9;display:flex;align-items:center;gap:0.5rem;}';
            $html .= 'p{color:#94a3b8;font-size:0.875rem;line-height:1.5;margin:0 0 1rem;}';
            $html .= 'pre{background:#0f172a;border:1px solid #334155;border-radius:0.5rem;padding:1rem;font-size:0.75rem;color:#38bdf8;overflow-x:auto;white-space:pre-wrap;margin:1rem 0 0;}';
            $html .= 'a{display:inline-block;background:#0284c7;color:#fff;text-decoration:none;padding:0.5rem 1rem;border-radius:0.5rem;font-weight:600;font-size:0.875rem;margin-top:1rem;}';
            $html .= '</style></head><body><div class="card">';
            $html .= '<h1><span>✈️</span> SlotWaves — System Notice</h1>';
            $html .= '<p>An error occurred while processing your request. Please ensure all required environment variables (including <code>APP_KEY</code> and Supabase credentials) are configured in the Vercel Dashboard.</p>';
            if ($debug) {
                $html .= '<pre>' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n\nFile: " . $e->getFile() . ':' . $e->getLine()) . '</pre>';
            }
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
