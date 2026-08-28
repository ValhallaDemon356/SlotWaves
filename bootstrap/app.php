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
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ], 500);
            }
        });
    })->create();

$storagePath = env('APP_STORAGE_PATH') ?: getenv('APP_STORAGE_PATH') ?: ($_SERVER['APP_STORAGE_PATH'] ?? null);
if ($storagePath) {
    $app->useStoragePath($storagePath);
}

$app->booted(function () {
    try {
        if (config('database.default') === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath && file_exists($dbPath) && !\Illuminate\Support\Facades\Schema::hasTable('airports')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\MasterDatabaseSeeder', '--force' => true]);
            }
        }
    } catch (\Throwable $e) {
        // Fallback gracefully
    }
});

return $app;
