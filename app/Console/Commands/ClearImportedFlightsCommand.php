<?php

namespace App\Console\Commands;

use App\Models\Flight;
use App\Models\TimelinePosition;
use App\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearImportedFlightsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slotwaves:clear-imported-flights 
                            {--upload= : Clear flights and timeline positions for a specific Upload ID}
                            {--all : Clear all imported flights and timeline positions across all uploads}
                            {--force : Bypass confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely clear imported flight records and generated timeline positions (Master database airports and airlines are preserved)';

    public function handle(): int
    {
        $uploadId = $this->option('upload');
        $all = $this->option('all');
        $force = $this->option('force');

        if (!$uploadId && !$all) {
            $this->error("Please specify either --upload=ID or --all.");
            $this->line("Example: php artisan slotwaves:clear-imported-flights --upload=1");
            $this->line("Example: php artisan slotwaves:clear-imported-flights --all");
            return 1;
        }

        $this->info("==================================================");
        $this->info("SLOTWAVES — CLEAR IMPORTED FLIGHT RECORDS");
        $this->info("==================================================");

        if ($uploadId) {
            $upload = Upload::find($uploadId);
            if (!$upload) {
                $this->error("Upload record with ID {$uploadId} not found.");
                return 1;
            }

            $flightCount = $upload->flights()->count();
            $posCount    = $upload->timelinePositions()->count();

            $this->warn("Target Upload: ID {$upload->id} ({$upload->original_filename})");
            $this->line("Flights to remove: {$flightCount}");
            $this->line("Timeline positions to remove: {$posCount}");

            if (!$force && !$this->confirm("Are you sure you want to delete flight records for upload ID {$uploadId}?")) {
                $this->info("Operation cancelled.");
                return 0;
            }

            DB::transaction(function () use ($upload) {
                if (Schema::hasTable('flight_pairings')) {
                    DB::table('flight_pairings')->where('upload_id', $upload->id)->delete();
                }
                $upload->timelinePositions()->delete();
                $upload->flights()->delete();
                $upload->update(['status' => 'pending']);
            });

            $this->info("Successfully cleared imported flights and positions for Upload ID {$uploadId}.");
            return 0;
        }

        if ($all) {
            $totalFlights = Flight::count();
            $totalPositions = TimelinePosition::count();

            $this->warn("DANGER: This will delete ALL {$totalFlights} imported flights and {$totalPositions} timeline positions across all uploads!");
            $this->line("Master Data (`airports` & `airlines`) will NOT be touched.");

            if (!$force && !$this->confirm("Type 'yes' to proceed with clearing all imported flight data?", false)) {
                $this->info("Operation cancelled.");
                return 0;
            }

            DB::transaction(function () {
                if (Schema::hasTable('flight_pairings')) {
                    DB::table('flight_pairings')->delete();
                }
                TimelinePosition::query()->delete();
                Flight::query()->delete();
                Upload::query()->update(['status' => 'pending']);
            });

            $this->info("Successfully cleared all imported flights and timeline positions.");
            $this->info("Master airports and airlines remain completely intact.");
            return 0;
        }

        return 0;
    }
}
