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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (env('APP_DEBUG') || getenv('APP_DEBUG') === 'true') {
                return response("<h1>Exception Details (SlotWaves Production Debug)</h1><p><strong>Class:</strong> " . htmlspecialchars(get_class($e)) . "</p><p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p><p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", 500);
            }
        });
    })->create();

$storagePath = env('APP_STORAGE_PATH') ?: getenv('APP_STORAGE_PATH') ?: ($_SERVER['APP_STORAGE_PATH'] ?? null);
if ($storagePath) {
    $app->useStoragePath($storagePath);
}

return $app;
