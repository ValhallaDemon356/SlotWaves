<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\AuditAirportsCommand::class,
                \App\Console\Commands\CheckDatabaseConnectionCommand::class,
                \App\Console\Commands\ClearImportedFlightsCommand::class,
                \App\Console\Commands\ImportHubudAirportsCommand::class,
                \App\Console\Commands\InitSupabaseDatabaseCommand::class,
                \App\Console\Commands\NormalizeFlightsDataCommand::class,
                \App\Console\Commands\SyncHubudMasterDataCommand::class,
                \App\Console\Commands\ValidateAirportsCommand::class,
            ]);
        }
    }
}
