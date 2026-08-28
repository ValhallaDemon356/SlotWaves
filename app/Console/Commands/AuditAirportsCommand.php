<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditAirportsCommand extends Command
{
    protected $signature = 'slotwaves:audit-airports';
    protected $description = 'Audit Master Airport database integrity, InJourney regions, IATA/ICAO consistency, and data sources';

    public function handle(): int
    {
        $this->line("");
        $this->line("Airport Master Audit");
        $this->line("--------------------");

        $totalAirports    = Airport::count();
        $apAirports       = Airport::where('management_type', Airport::MANAGEMENT_INJOURNEY)->count();
        $uptHubudAirports = Airport::where('management_type', Airport::MANAGEMENT_UPBU_HUBUD)->count();
        $uptPemdaAirports = Airport::where('management_type', Airport::MANAGEMENT_UPTD_PEMDA)->count();
        $tniAirports      = Airport::where('management_type', Airport::MANAGEMENT_TNI)->count();
        $missionAirports  = Airport::where('management_type', Airport::MANAGEMENT_MISSIONARIS)->count();
        $bumnAirports     = Airport::where('management_type', Airport::MANAGEMENT_BUMN)->count();
        $swastaAirports   = Airport::where('management_type', Airport::MANAGEMENT_SWASTA)->count();
        $masyarakatAirports = Airport::where('management_type', Airport::MANAGEMENT_MASYARAKAT)->count();
        $otherAirports    = Airport::where('management_type', Airport::MANAGEMENT_OTHER)->count();

        // Check missing IATA / ICAO
        $missingIata = Airport::whereNull('iata_code')->orWhere('iata_code', '')->count();
        $missingIcao = Airport::whereNull('icao_code')->orWhere('icao_code', '')->count();

        // Check duplicate IATA
        $duplicateIata = DB::table('airports')
            ->select('iata_code', DB::raw('COUNT(*) as c'))
            ->whereNotNull('iata_code')
            ->where('iata_code', '!=', '')
            ->where('iata_code', '!=', '-')
            ->groupBy('iata_code')
            ->having('c', '>', 1)
            ->count();

        // Check duplicate ICAO
        $duplicateIcao = DB::table('airports')
            ->select('icao_code', DB::raw('COUNT(*) as c'))
            ->whereNotNull('icao_code')
            ->where('icao_code', '!=', '')
            ->where('icao_code', '!=', '-')
            ->groupBy('icao_code')
            ->having('c', '>', 1)
            ->count();

        // Region Invariant checks:
        // 1. AP Indonesia MUST NOT have null region
        $missingRegionAp = Airport::where('management_type', Airport::MANAGEMENT_INJOURNEY)
            ->whereNull('region')
            ->count();

        // 2. Non-AP MUST NOT have region assigned
        $invalidRegionNonAp = Airport::where('management_type', '!=', Airport::MANAGEMENT_INJOURNEY)
            ->whereNotNull('region')
            ->count();

        // Check missing or unverified data source
        $missingSource = Airport::whereNull('data_source')->orWhere('data_source', '')->count();
        $unverifiedAirports = Airport::where('data_source', Airport::SOURCE_UNVERIFIED)->count();

        $this->line("");
        $this->line("Total Airports: {$totalAirports}");
        $this->line("");
        $this->line("PT Angkasa Pura Indonesia: {$apAirports}");
        $this->line("UPT Ditjen Hubud: {$uptHubudAirports}");
        $this->line("UPT Daerah/Pemda: {$uptPemdaAirports}");
        $this->line("TNI: {$tniAirports}");
        $this->line("Missionaris: {$missionAirports}");
        $this->line("BUMN: {$bumnAirports}");
        $this->line("Swasta: {$swastaAirports}");
        $this->line("Masyarakat: {$masyarakatAirports}");
        $this->line("Other: {$otherAirports}");
        $this->line("");
        $this->line("Airports without IATA: {$missingIata}");
        $this->line("Airports without ICAO: {$missingIcao}");
        $this->line("Duplicate IATA: {$duplicateIata}");
        $this->line("Duplicate ICAO: {$duplicateIcao}");
        $this->line("Missing Region (AP without Region): {$missingRegionAp}");
        $this->line("Invalid Region (Non-AP with Region): {$invalidRegionNonAp}");
        $this->line("Missing Source: {$missingSource}");
        $this->line("Unverified Airport: {$unverifiedAirports}");
        $this->line("");

        // Check if any critical anomalies exist
        $hasErrors = ($duplicateIata > 0 || $missingRegionAp > 0 || $invalidRegionNonAp > 0);

        if ($hasErrors) {
            $this->error("Audit completed with anomalies!");
            return Command::FAILURE;
        }

        $this->info("Audit completed successfully with ZERO anomalies!");
        return Command::SUCCESS;
    }
}
