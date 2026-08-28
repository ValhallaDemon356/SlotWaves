<?php

namespace App\Console\Commands;

use App\Models\Flight;
use App\Models\Airline;
use App\Models\Airport;
use App\Services\AirportResolverService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeFlightsDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slotwaves:normalize-database {--dry-run : Run normalization without committing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize existing flight records to master IATA codes and relational airline codes';

    public function handle(AirportResolverService $resolver): int
    {
        $this->info("==================================================");
        $this->info("SLOTWAVES — FLIGHT DATABASE NORMALIZATION");
        $this->info("==================================================");

        $flights = Flight::all();
        $totalFlights = $flights->count();
        $this->info("Total flights in database: {$totalFlights}");

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn("RUNNING IN DRY-RUN MODE (No database changes will be saved)");
        }

        $normalizedCount = 0;
        $invalidAirlineCount = 0;
        $invalidAirportCount = 0;
        $nullOriginCount = 0;
        $nullDestinationCount = 0;

        $unresolvedAirlines = [];
        $unresolvedAirports = [];

        DB::beginTransaction();

        try {
            foreach ($flights as $flight) {
                $flNo = trim($flight->flight_number ?? '');
                if (empty($flNo)) {
                    continue;
                }

                // ── 1. Normalize Airline Code from 2-Letter Prefix ────────────
                $airlinePrefix = strtoupper(substr($flNo, 0, 2));
                $flight->airline_code = $airlinePrefix;

                $airline = Airline::findByCode($airlinePrefix);
                if (!$airline) {
                    $invalidAirlineCount++;
                    $unresolvedAirlines[$airlinePrefix] = ($unresolvedAirlines[$airlinePrefix] ?? 0) + 1;
                }

                // ── 2. Determine Directional Origin & Destination ─────────────
                $isArrival = str_contains($flight->flight_type ?? '', 'arrival');
                $isDeparture = str_contains($flight->flight_type ?? '', 'departure');

                // Determine raw station from origin, destination, or raw_data
                $rawStation = '';
                if ($isArrival) {
                    $rawStation = $flight->origin ?: '';
                } elseif ($isDeparture) {
                    $rawStation = $flight->destination ?: '';
                }

                if (empty($rawStation) && !empty($flight->raw_data)) {
                    $rawJson = json_decode($flight->raw_data, true);
                    if (isset($rawJson['raw_station'])) {
                        $rawStation = $rawJson['raw_station'];
                    }
                }

                $resolvedIata = $resolver->getIataCode($rawStation);

                if ($isArrival) {
                    $flight->origin = $resolvedIata;
                    $flight->destination = 'BDO'; // Base airport
                } elseif ($isDeparture) {
                    $flight->origin = 'BDO';      // Base airport
                    $flight->destination = $resolvedIata;
                } else {
                    // Fallback
                    if (!empty($flight->origin)) {
                        $flight->origin = $resolver->getIataCode($flight->origin);
                    }
                    if (!empty($flight->destination)) {
                        $flight->destination = $resolver->getIataCode($flight->destination);
                    }
                }

                // Validate IATA format
                if (empty($flight->origin) || $flight->origin === '—') {
                    $nullOriginCount++;
                } elseif (!preg_match('/^[A-Z]{3}$/', $flight->origin)) {
                    $invalidAirportCount++;
                    $unresolvedAirports[$flight->origin] = ($unresolvedAirports[$flight->origin] ?? 0) + 1;
                }

                if (empty($flight->destination) || $flight->destination === '—') {
                    $nullDestinationCount++;
                } elseif (!preg_match('/^[A-Z]{3}$/', $flight->destination)) {
                    $invalidAirportCount++;
                    $unresolvedAirports[$flight->destination] = ($unresolvedAirports[$flight->destination] ?? 0) + 1;
                }

                if (!$isDryRun) {
                    $flight->save();
                }

                $normalizedCount++;
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->info("Dry run complete. Rolled back changes.");
            } else {
                DB::commit();
                $this->info("Normalization committed successfully to MySQL database.");
            }

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Normalization failed: " . $e->getMessage());
            return 1;
        }

        // ── Validation Summary ────────────────────────────────────────────────
        $this->info("\n==================================================");
        $this->info("DATABASE VALIDATION REPORT");
        $this->info("==================================================");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Flights Processed', $totalFlights],
                ['Successfully Normalized Flights', $normalizedCount],
                ['Flights with Unlisted Airline Prefix (Candidate)', $invalidAirlineCount],
                ['Flights with Invalid Airport Format', $invalidAirportCount],
                ['Flights with NULL Origin', $nullOriginCount],
                ['Flights with NULL Destination', $nullDestinationCount],
            ]
        );

        if (!empty($unresolvedAirlines)) {
            $this->warn("\nCandidate Airlines Found in Flights:");
            foreach ($unresolvedAirlines as $code => $cnt) {
                $this->line("  - {$code}: {$cnt} flights");
            }
        }

        if (!empty($unresolvedAirports)) {
            $this->warn("\nUnresolved Airport Stations:");
            foreach ($unresolvedAirports as $st => $cnt) {
                $this->line("  - {$st}: {$cnt} flights");
            }
        }

        return 0;
    }
}
