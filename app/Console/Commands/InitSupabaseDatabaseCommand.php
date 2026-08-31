<?php

namespace App\Console\Commands;

use App\Models\Airline;
use App\Models\Airport;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InitSupabaseDatabaseCommand extends Command
{
    protected $signature = 'slotwaves:init-supabase {--seed : Seed official master data after migration} {--force : Force execution without confirmation}';
    protected $description = 'Initialize and migrate the production Supabase PostgreSQL database safely with idempotent master data seeding';

    public function handle(): int
    {
        $this->line("");
        $this->info("================================================================");
        $this->info("SLOTWAVES — SUPABASE DATABASE INITIALIZATION & HEALTH CHECK");
        $this->info("================================================================");
        $this->line("");

        $driver = config('database.default');
        $this->line("Active Connection Driver: <comment>{$driver}</comment>");

        // Step 1: Verify PDO Connection
        try {
            $pdo = DB::connection()->getPdo();
            $serverVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $this->line("Database Connection:     <info>[SUCCESS]</info>");
            $this->line("Server Version:          <info>{$serverVersion}</info>");
        } catch (\Throwable $e) {
            $this->error("Database Connection:     [FAILED]");
            $this->error("Reason: " . $e->getMessage());
            $this->warn("\nPlease verify your DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD environment variables.");
            return Command::FAILURE;
        }

        // Step 2: Run Pending Migrations
        $this->line("");
        $this->info("Step 1/3: Applying Database Migrations...");
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            if ($exitCode === 0) {
                $this->line("<info>[SUCCESS]</info> Migrations applied successfully.");
                if (trim($output)) {
                    $this->line($output);
                }
            } else {
                $this->error("[FAILED] Migration failed with exit code: {$exitCode}");
                $this->line($output);
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error("[EXCEPTION] Failed to run migrations: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Step 3: Seed Master Data (Idempotent)
        $this->line("");
        $this->info("Step 2/3: Checking Master Data...");
        $airportCount = 0;
        try {
            if (Schema::hasTable('airports')) {
                $airportCount = Airport::count();
            }
        } catch (\Throwable $e) {
            $airportCount = 0;
        }

        $shouldSeed = $this->option('seed') || $airportCount === 0;

        if ($shouldSeed) {
            $this->line("Seeding official master data (Hubud Kemenhub + PT Angkasa Pura Indonesia)...");
            try {
                $exitCode = Artisan::call('db:seed', [
                    '--class' => MasterDatabaseSeeder::class,
                    '--force' => true,
                ]);
                $output = Artisan::output();
                if ($exitCode === 0) {
                    $this->line("<info>[SUCCESS]</info> Master data seeded successfully.");
                } else {
                    $this->warn("[WARNING] Seed returned non-zero code: {$exitCode}");
                    $this->line($output);
                }
            } catch (\Throwable $e) {
                $this->error("[EXCEPTION] Seeding encountered an error: " . $e->getMessage());
            }
        } else {
            $this->line("<info>[OK]</info> Master data already present ({$airportCount} airports found). Skipping re-seed.");
        }

        // Step 4: Verification & Table Summary
        $this->line("");
        $this->info("Step 3/3: Database Schema Inventory & Verification...");

        $requiredTables = [
            'airports'            => 'Master Airports (Hubud & InJourney)',
            'airlines'            => 'Master Airlines Registry',
            'uploads'             => 'Flight Schedule Uploads',
            'flights'             => 'Extracted Flights Data',
            'timeline_positions'  => 'Visual Gantt / Timeline Positions',
            'timeline_settings'   => 'Operational Hours Viewport Settings',
            'flight_pairings'     => 'Turnaround Pairings & RON Tracking',
            'exports'             => 'Exported Reports (PDF/JPG/Excel)',
            'sessions'            => 'Persistent Serverless Sessions',
            'cache'               => 'Database Cache Store',
            'cache_locks'         => 'Database Cache Atomic Locks',
        ];

        $missingCount = 0;
        foreach ($requiredTables as $table => $description) {
            if (Schema::hasTable($table)) {
                $rowCount = DB::table($table)->count();
                $this->line("  ✓ <info>[TABLE OK]</info> <comment>{$table}</comment> ({$rowCount} rows) — {$description}");
            } else {
                $this->line("  ✗ <error>[MISSING]</error> <comment>{$table}</comment> — {$description}");
                $missingCount++;
            }
        }

        $this->line("");
        if ($missingCount === 0) {
            $this->info("================================================================");
            $this->info("✓ SUPABASE DATABASE INITIALIZATION COMPLETE & VERIFIED");
            $this->info("================================================================");
            return Command::SUCCESS;
        } else {
            $this->error("================================================================");
            $this->error("✗ {$missingCount} TABLE(S) MISSING. Please review migration logs.");
            $this->error("================================================================");
            return Command::FAILURE;
        }
    }
}
