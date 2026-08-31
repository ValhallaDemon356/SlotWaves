<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\Airline;
use Database\Seeders\HubudAirportSeeder;
use Database\Seeders\HubudAirlineSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncHubudMasterDataCommand extends Command
{
    protected $signature = 'slotwaves:sync-hubud {--force : Force sync even if unchanged}';
    protected $description = 'Synchronize and audit Master Reference Database with Hubud Kemenhub and project references';

    public function handle(): int
    {
        $this->info("Connecting to Hubud Kemenhub Master Reference data repository...");

        // We run seeders to capture metrics
        $airportMetrics = $this->syncAirports();
        $airlineMetrics = $this->syncAirlines();

        $this->line("");
        $this->line("================================================");
        $this->line("HUBUD MASTER DATA SYNC REPORT");
        $this->line("================================================");
        $this->line("");
        $this->line("Airports checked:\n" . $airportMetrics['checked']);
        $this->line("");
        $this->line("Airports inserted:\n" . $airportMetrics['inserted']);
        $this->line("");
        $this->line("Airports updated:\n" . $airportMetrics['updated']);
        $this->line("");
        $this->line("Airports unchanged:\n" . $airportMetrics['unchanged']);
        $this->line("");
        $this->line("Airports duplicate:\n" . $airportMetrics['duplicate']);
        $this->line("");
        $this->line("Airlines checked:\n" . $airlineMetrics['checked']);
        $this->line("");
        $this->line("Airlines inserted:\n" . $airlineMetrics['inserted']);
        $this->line("");
        $this->line("Airlines updated:\n" . $airlineMetrics['updated']);
        $this->line("");
        $this->line("Airlines unchanged:\n" . $airlineMetrics['unchanged']);
        $this->line("");
        $this->line("================================================");
        $this->line("");

        return Command::SUCCESS;
    }

    private function syncAirports(): array
    {
        $checked = 0;
        $inserted = 0;
        $updated = 0;
        $unchanged = 0;
        $duplicate = 0;

        $beforeAirports = Airport::all()->keyBy('id');
        $beforeCount    = $beforeAirports->count();

        // 1. Seed core InJourney & reference airports
        $seeder = new HubudAirportSeeder();
        $seeder->run();

        // 2. Import complete Hubud master dataset (597 airports)
        \Illuminate\Support\Facades\Artisan::call('slotwaves:import-hubud-airports');

        $afterAirports = Airport::all();
        $afterCount    = $afterAirports->count();

        $seenIds = [];
        foreach ($afterAirports as $ap) {
            $checked++;
            if (isset($seenIds[$ap->id])) {
                $duplicate++;
                continue;
            }
            $seenIds[$ap->id] = true;

            $before = $beforeAirports->get($ap->id);
            if (!$before) {
                $inserted++;
            } elseif ($before->updated_at != $ap->updated_at) {
                $updated++;
            } else {
                $unchanged++;
            }
        }

        return compact('checked', 'inserted', 'updated', 'unchanged', 'duplicate');
    }

    private function syncAirlines(): array
    {
        $checked = 0;
        $inserted = 0;
        $updated = 0;
        $unchanged = 0;

        $seeder = new HubudAirlineSeeder();
        $beforeAirlines = Airline::all()->keyBy('airline_code');
        $seeder->run();
        $afterAirlines = Airline::all()->keyBy('airline_code');

        foreach ($afterAirlines as $code => $al) {
            $checked++;
            $before = $beforeAirlines->get($code);
            if (!$before) {
                $inserted++;
            } elseif ($before->updated_at != $al->updated_at) {
                $updated++;
            } else {
                $unchanged++;
            }
        }

        return compact('checked', 'inserted', 'updated', 'unchanged');
    }
}
