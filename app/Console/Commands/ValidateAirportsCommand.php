<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ValidateAirportsCommand extends Command
{
    protected $signature = 'slotwaves:validate-airports';
    protected $description = 'Strict validation of full Hubud master airport registry and InJourney 37 locked airports';

    public function handle(): int
    {
        $this->line("");
        $this->line("========================================");
        $this->line("SLOTWAVES AIRPORT MASTER VALIDATION");
        $this->line("========================================");

        $hasFailures = false;

        $totalDbCount    = Airport::count();
        $totalInjourney  = Airport::where('management_type', Airport::MANAGEMENT_INJOURNEY)->count();
        $totalUpbu       = Airport::where('management_type', Airport::MANAGEMENT_UPBU_HUBUD)->count();
        $totalUptd       = Airport::where('management_type', Airport::MANAGEMENT_UPTD_PEMDA)->count();
        $totalTni        = Airport::where('management_type', Airport::MANAGEMENT_TNI)->count();
        $totalMission    = Airport::where('management_type', Airport::MANAGEMENT_MISSIONARIS)->count();
        $totalBumn       = Airport::where('management_type', Airport::MANAGEMENT_BUMN)->count();
        $totalSwasta     = Airport::where('management_type', Airport::MANAGEMENT_SWASTA)->count();
        $totalMasyarakat = Airport::where('management_type', Airport::MANAGEMENT_MASYARAKAT)->count();
        $totalOther      = Airport::where('management_type', Airport::MANAGEMENT_OTHER)->count();

        $countReg1 = Airport::where('is_active', true)->byRegion('Region 1')->count();
        $countReg2 = Airport::where('is_active', true)->byRegion('Region 2')->count();
        $countReg3 = Airport::where('is_active', true)->byRegion('Region 3')->count();
        $countReg4 = Airport::where('is_active', true)->byRegion('Region 4')->count();
        $countReg5 = Airport::where('is_active', true)->byRegion('Region 5')->count();
        $countReg6 = Airport::where('is_active', true)->byRegion('Region 6')->count();

        $this->line("");
        $this->line("PT API / INJOURNEY:");
        $this->line("{$totalInjourney}");
        $this->line("");
        $this->line("REGION 1:");
        $this->line("{$countReg1}");
        $this->line("");
        $this->line("REGION 2:");
        $this->line("{$countReg2}");
        $this->line("");
        $this->line("REGION 3:");
        $this->line("{$countReg3}");
        $this->line("");
        $this->line("REGION 4:");
        $this->line("{$countReg4}");
        $this->line("");
        $this->line("REGION 5:");
        $this->line("{$countReg5}");
        $this->line("");
        $this->line("REGION 6:");
        $this->line("{$countReg6}");
        $this->line("");
        $this->line("========================================");
        $this->line("");
        $this->line("REGION VALIDATION:");
        $this->line("");

        $expectedRegions = [
            'Region 1' => ['CGK', 'HLP', 'KJT', 'BDO'],
            'Region 2' => ['DPS', 'BWX', 'LOP', 'KOE'],
            'Region 3' => ['BTH', 'TNJ', 'TJQ', 'KNO', 'DTB', 'PDG', 'BTJ', 'PLM', 'PKU', 'TKG', 'PGK', 'BKS', 'DJB'],
            'Region 4' => ['YIA', 'SRG', 'SOC', 'SUB', 'DHX', 'JOG', 'PWL'],
            'Region 5' => ['UPG', 'MDC', 'AMQ', 'DJJ', 'BIK'],
            'Region 6' => ['PNK', 'BDJ', 'BPN', 'PKY'],
        ];

        foreach ($expectedRegions as $reg => $iatas) {
            $regPass = true;
            foreach ($iatas as $code) {
                $ap = Airport::findByIata($code);
                if (!$ap || !in_array($ap->region, [$reg, str_replace(['1','2','3','4','5','6'], ['I','II','III','IV','V','VI'], $reg)])) {
                    $regPass = false;
                    $hasFailures = true;
                }
            }
            if ($regPass) {
                $this->line("<info>[PASS]</info> {$reg}");
            } else {
                $this->line("<error>[FAIL]</error> {$reg} has missing or misassigned airports");
            }
        }

        $this->line("");
        $this->line("========================================");
        $this->line("");
        $this->line("OPERATOR & MANAGEMENT VALIDATION:");
        $this->line("");

        // Check PT API count
        if ($totalInjourney === 37) {
            $this->line("<info>[PASS]</info> PT API count = 37");
        } else {
            $this->line("<error>[FAIL]</error> PT API count is {$totalInjourney} (expected 37)");
            $hasFailures = true;
        }

        // Check UPT Hubud count
        if ($totalUpbu === 197) {
            $this->line("<info>[PASS]</info> UPT Ditjen Hubud count = 197");
        } else {
            $this->line("<error>[FAIL]</error> UPT Ditjen Hubud count is {$totalUpbu} (expected 197)");
            $hasFailures = true;
        }

        // Check UPTD Pemda count
        if ($totalUptd === 107) {
            $this->line("<info>[PASS]</info> UPT Daerah / Pemda count = 107");
        } else {
            $this->line("<error>[FAIL]</error> UPT Daerah / Pemda count is {$totalUptd} (expected 107)");
            $hasFailures = true;
        }

        // Check TNI count
        if ($totalTni === 6) {
            $this->line("<info>[PASS]</info> TNI count = 6");
        } else {
            $this->line("<error>[FAIL]</error> TNI count is {$totalTni} (expected 6)");
            $hasFailures = true;
        }

        // Check Missionaris count
        if ($totalMission === 188) {
            $this->line("<info>[PASS]</info> Missionaris count = 188");
        } else {
            $this->line("<error>[FAIL]</error> Missionaris count is {$totalMission} (expected 188)");
            $hasFailures = true;
        }

        // Check BUMN count
        if ($totalBumn === 9) {
            $this->line("<info>[PASS]</info> BUMN count = 9");
        } else {
            $this->line("<error>[FAIL]</error> BUMN count is {$totalBumn} (expected 9)");
            $hasFailures = true;
        }

        // Check Swasta count
        if ($totalSwasta === 52) {
            $this->line("<info>[PASS]</info> Swasta count = 52");
        } else {
            $this->line("<error>[FAIL]</error> Swasta count is {$totalSwasta} (expected 52)");
            $hasFailures = true;
        }

        // Check Masyarakat count
        if ($totalMasyarakat === 1) {
            $this->line("<info>[PASS]</info> Masyarakat count = 1");
        } else {
            $this->line("<error>[FAIL]</error> Masyarakat count is {$totalMasyarakat} (expected 1)");
            $hasFailures = true;
        }

        // Check Other count
        if ($totalOther === 5) {
            $this->line("<info>[PASS]</info> Other / Reference Hubs count = 5");
        } else {
            $this->line("<error>[FAIL]</error> Other count is {$totalOther} (expected 5)");
            $hasFailures = true;
        }

        // Check Total Database count
        if ($totalDbCount === 602) {
            $this->line("<info>[PASS]</info> Total Database count = 602 (597 Hubud + 5 Reference Hubs)");
        } else {
            $this->line("<error>[FAIL]</error> Total Database count is {$totalDbCount} (expected 602)");
            $hasFailures = true;
        }

        $this->line("");
        $this->line("========================================");
        $this->line("");
        $this->line("DUPLICATE VALIDATION:");
        $this->line("");

        // Check Duplicate IATA
        $dupIata = DB::table('airports')
            ->select('iata_code', DB::raw('COUNT(*) as c'))
            ->whereNotNull('iata_code')
            ->where('iata_code', '!=', '')
            ->where('iata_code', '!=', '-')
            ->groupBy('iata_code')
            ->having('c', '>', 1)
            ->count();
        if ($dupIata === 0) {
            $this->line("<info>[PASS]</info> No duplicate IATA");
        } else {
            $this->line("<error>[FAIL]</error> Found {$dupIata} duplicate IATA codes");
            $hasFailures = true;
        }

        $this->line("");
        $this->line("========================================");
        $this->line("");
        $this->line("DATA INTEGRITY:");
        $this->line("");

        // Check Region data preserved for 37 AP
        $allApHasRegion = Airport::where('management_type', Airport::MANAGEMENT_INJOURNEY)->whereNull('region')->count() === 0;
        if ($allApHasRegion) {
            $this->line("<info>[PASS]</info> Region data preserved for PT API (37/37)");
        } else {
            $this->line("<error>[FAIL]</error> Some PT API airports lost their region data");
            $hasFailures = true;
        }

        // Check Non-InJourney strictly have region = null
        $invalidRegions = Airport::where('management_type', '!=', Airport::MANAGEMENT_INJOURNEY)->whereNotNull('region')->count();
        if ($invalidRegions === 0) {
            $this->line("<info>[PASS]</info> Non-PT API region strictly NULL ({$invalidRegions} invalid)");
        } else {
            $this->line("<error>[FAIL]</error> Found {$invalidRegions} non-PT API airports with region assigned!");
            $hasFailures = true;
        }

        // Domestic / International independence & exact counts (27 INTL, 10 DOM)
        $ptIntlCount = Airport::where('is_active', true)->where('management_type', Airport::MANAGEMENT_INJOURNEY)->where('is_international', true)->count();
        $ptDomCount = Airport::where('is_active', true)->where('management_type', Airport::MANAGEMENT_INJOURNEY)->where('is_international', false)->count();
        if ($ptIntlCount === 27 && $ptDomCount === 10) {
            $this->line("<info>[PASS]</info> Domestic/International independent from Region (INTL: {$ptIntlCount}, DOM: {$ptDomCount})");
        } else {
            $this->line("<error>[FAIL]</error> PT API DOM/INTL counts incorrect (INTL: {$ptIntlCount}/27, DOM: {$ptDomCount}/10)");
            $hasFailures = true;
        }

        // Non-PT API not counted as PT API & has no region
        $nonPtApiList = ['LBJ', 'TRK', 'AAP', 'GTO', 'KDI', 'PLW', 'MKQ', 'MKW', 'SOQ', 'TTE'];
        $nonPtOk = true;
        foreach ($nonPtApiList as $code) {
            $ap = Airport::findByIata($code);
            if (!$ap || $ap->isAngkasaPura() || $ap->region !== null) {
                $nonPtOk = false;
                $hasFailures = true;
            }
        }
        if ($nonPtOk) {
            $this->line("<info>[PASS]</info> Non-PT API not counted as PT API");
        } else {
            $this->line("<error>[FAIL]</error> Non-PT API airport incorrectly assigned to PT API or has region");
        }

        $this->line("");
        $this->line("========================================");
        $this->line("");

        if ($hasFailures) {
            $this->error("MASTER AIRPORT DATABASE: INVALID (Validation finished with ERRORS!)");
            return Command::FAILURE;
        }

        $this->info("MASTER AIRPORT DATABASE: VALID");
        return Command::SUCCESS;
    }
}
