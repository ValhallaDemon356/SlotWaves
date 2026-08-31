<?php

namespace App\Console\Commands;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckDatabaseConnectionCommand extends Command
{
    protected $signature = 'slotwaves:check-db';
    protected $description = 'Check database connection status, active driver, and table integrity without exposing credentials';

    public function handle(): int
    {
        $this->info("========================================");
        $this->info("SLOTWAVES DATABASE INTEGRITY CHECK");
        $this->info("========================================");

        $driver = config('database.default');
        $this->line("Default Connection: <comment>{$driver}</comment>");

        try {
            $pdo = DB::connection()->getPdo();
            $serverVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $this->line("Connection Status: <info>[SUCCESS]</info>");
            $this->line("Server Version:    <info>{$serverVersion}</info>");
        } catch (\Throwable $e) {
            $this->error("Connection Status: [FAILED]");
            $this->error("Error: " . $e->getMessage());
            return 1;
        }

        // Test basic query
        try {
            $result = DB::select('SELECT 1 as ping');
            $this->line("Query Ping Test:   <info>[SUCCESS]</info>");
        } catch (\Throwable $e) {
            $this->error("Query Ping Test:   [FAILED] - " . $e->getMessage());
            return 1;
        }

        // Check required tables
        $tables = [
            'airports',
            'airlines',
            'uploads',
            'flights',
            'timeline_positions',
            'timeline_settings',
            'flight_pairings',
            'exports',
        ];

        $this->line("");
        $this->line("Table Health Status:");
        $missingTables = 0;
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("  - <info>[OK]</info> {$table} ({$count} rows)");
            } else {
                $this->line("  - <error>[MISSING]</error> {$table}");
                $missingTables++;
            }
        }

        if ($missingTables > 0) {
            $this->warn("\nSome tables are missing. Please run `php artisan migrate`.");
            return 1;
        }

        // Master data check
        $this->line("");
        $this->line("Master Data Health Check:");
        $airportCount = Airport::count();
        $airlineCount = Airline::count();
        $uploadCount  = Upload::count();
        $flightCount  = Flight::count();

        $this->line("  - Airports:       {$airportCount} (expected: 602)");
        $this->line("  - Airlines:       {$airlineCount} (expected: 62)");
        $this->line("  - Total Uploads:  {$uploadCount}");
        $this->line("  - Total Flights:  {$flightCount}");

        // Station capacity verification
        $bdo = Airport::findByIata('BDO');
        $cgk = Airport::findByIata('CGK');
        $dps = Airport::findByIata('DPS');

        $bdoCap = $bdo ? $bdo->getEffectiveCapacity() : 'N/A';
        $cgkCap = $cgk ? $cgk->getEffectiveCapacity() : 'N/A';
        $dpsCap = $dps ? $dps->getEffectiveCapacity() : 'N/A';

        $this->line("  - BDO Capacity:   {$bdoCap} A/C (NAC)");
        $this->line("  - CGK Capacity:   {$cgkCap} A/C (NAC)");
        $this->line("  - DPS Capacity:   {$dpsCap} A/C (NAC)");

        $this->line("");
        $this->info("Database configuration and schema validation complete.");
        return 0;
    }
}
