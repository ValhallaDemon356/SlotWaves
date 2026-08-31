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
                '[SlotWaves Exception Caught] %s: %s in %s:%d' . PHP_EOL . '%s',
                $errClass,
                $sanitizedMsg,
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));

            // Categorize the error
            $statusCode = 500;
            $category = 'Application Error';

            if ($e instanceof \Illuminate\Session\TokenMismatchException) {
                $statusCode = 419;
                $category = 'CSRF Token Expired (419)';
                $sanitizedMsg = 'Your session or CSRF token has expired. Please refresh the page and try again.';
            } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                $statusCode = 422;
                $category = 'Validation Error';
            } elseif ($e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException) {
                $statusCode = 500;
                $category = 'Database / Supabase Connection Error';
            } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                $statusCode = 404;
                $category = 'Page Not Found (404)';
            } elseif (str_contains($errClass, 'Pdf') || str_contains($errMsg, 'PDF') || str_contains($errMsg, 'parser')) {
                $category = 'PDF Parsing / Schedule Extraction Error';
            }

            if ($request->wantsJson() || $request->is('api/*') || $request->ajax()) {
                return new \Illuminate\Http\JsonResponse([
                    'success'  => false,
                    'category' => $category,
                    'error'    => $sanitizedMsg,
                    'class'    => $errClass,
                ], $statusCode);
            }

            // For token mismatch, redirect back with error if possible
            if ($e instanceof \Illuminate\Session\TokenMismatchException) {
                return redirect()->back()->withErrors(['csrf' => 'Session expired. Please try again.']);
            }

            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>SlotWaves — Error</title>';
            $html .= '<style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem;}';
            $html .= '.card{background:#1e293b;border:1px solid #334155;border-radius:1rem;padding:2rem;max-width:640px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);}';
            $html .= 'h1{margin:0 0 0.5rem;font-size:1.5rem;color:#f1f5f9;display:flex;align-items:center;gap:0.5rem;}';
            $html .= 'p{color:#94a3b8;font-size:0.875rem;line-height:1.5;margin:0 0 1rem;}';
            $html .= '.notice-box{background:#0f172a;border:1px solid #ef4444;border-radius:0.5rem;padding:1rem;margin:1rem 0;}';
            $html .= '.notice-title{color:#f87171;font-weight:700;font-size:0.8125rem;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.375rem;}';
            $html .= '.notice-code{font-family:monospace;font-size:0.75rem;color:#fca5a5;word-break:break-all;white-space:pre-wrap;}';
            $html .= 'a{display:inline-block;background:#0284c7;color:#fff;text-decoration:none;padding:0.5rem 1rem;border-radius:0.5rem;font-weight:600;font-size:0.875rem;margin-top:0.5rem;margin-right:0.5rem;}';
            $html .= '</style></head><body><div class="card">';
            $html .= '<h1><span>✈️</span> SlotWaves — System Notice</h1>';
            $html .= '<p>The application encountered an error while processing your request:</p>';
            $html .= '<div class="notice-box">';
            $html .= '<div class="notice-title">' . htmlspecialchars($category) . ' (' . htmlspecialchars($errClass) . ')</div>';
            $html .= '<div class="notice-code">' . htmlspecialchars($sanitizedMsg) . ' (' . htmlspecialchars($errFile) . ')</div>';
            $html .= '</div>';
            $html .= '<a href="/">Return to Dashboard</a>';
            $html .= '<a href="javascript:location.reload()" style="background:#334155;">Retry</a>';
            $html .= '</div></body></html>';

            return new \Illuminate\Http\Response($html, $statusCode, ['Content-Type' => 'text/html; charset=utf-8']);
        });
    })->create();

// Redirect storage path to /tmp in serverless environments (Vercel)
$storagePath = env('APP_STORAGE_PATH') ?: getenv('APP_STORAGE_PATH') ?: ($_SERVER['APP_STORAGE_PATH'] ?? null);
if ($storagePath) {
    $app->useStoragePath($storagePath);
}

return $app;
