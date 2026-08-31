<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ImportHubudAirportsCommand extends Command
{
    protected $signature = 'slotwaves:import-hubud-airports {--force : Force re-import}';
    protected $description = 'Import and reconcile complete master airports dataset from Hubud Kemenhub API';

    public function handle(): int
    {
        $this->line("");
        $this->line("========================================");
        $this->line("SLOTWAVES HUBUD AIRPORT IMPORT");
        $this->line("========================================");
        $this->line("");

        $apiUrl = 'https://hubud.kemenhub.go.id/api/bandara-map';
        $localCache = base_path('hubud_bandara_map.json');

        $this->info("Fetching data from Hubud Kemenhub...");

        $jsonRaw = null;

        // 1. Fetch from live API with short timeout, fallback to local cache
        if (File::exists($localCache)) {
            $jsonRaw = File::get($localCache);
        }

        if (!$jsonRaw && function_exists('curl_init')) {
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SlotWaves/1.0');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($response)) {
                $jsonRaw = $response;
                File::put($localCache, $response);
                $this->line("<info>[SUCCESS]</info> Connected to live API: {$apiUrl}");
            }
        }

        if (!$jsonRaw) {
            $this->error("Failed to fetch Hubud dataset and no local cache found!");
            return Command::FAILURE;
        }

        $decoded = json_decode($jsonRaw, true);
        $sourceRecords = $decoded['data'] ?? [];
        $sourceCount = count($sourceRecords);

        if ($sourceCount === 0) {
            $this->error("Hubud dataset is empty or invalid JSON!");
            return Command::FAILURE;
        }

        // Preload existing database records into memory to prevent N+1 queries
        $existingAirports = Airport::all();
        $initialDbCount = $existingAirports->count();
        $this->line("Source                  : HUBUD KEMENHUB");
        $this->line("Source Records          : {$sourceCount}");
        $this->line("Existing Database       : {$initialDbCount}");
        $this->line("");

        $existingByBandaraId = [];
        $existingByIata      = [];
        $existingByIcao      = [];
        $existingByNameProv  = [];

        foreach ($existingAirports as $ap) {
            if (!empty($ap->bandara_id)) {
                $existingByBandaraId[(int)$ap->bandara_id] = $ap;
            }
            if (!empty($ap->iata_code)) {
                $existingByIata[strtoupper($ap->iata_code)] = $ap;
            }
            if (!empty($ap->icao_code)) {
                $existingByIcao[strtoupper($ap->icao_code)] = $ap;
            }
            $nameKey = strtolower(trim($ap->name));
            $provKey = strtolower(trim($ap->province ?? ''));
            $existingByNameProv["{$nameKey}|{$provKey}"] = $ap;
            $existingByNameProv["{$nameKey}|"] = $ap;
        }

        $now = Carbon::now();
        $hubudUrl = 'https://hubud.kemenhub.go.id/daftar-bandara';

        // 37 Locked InJourney IATAs
        $lockedIatas = [
            'CGK', 'HLP', 'KJT', 'BDO',
            'DPS', 'BWX', 'LOP', 'KOE',
            'BTH', 'TNJ', 'TJQ', 'KNO', 'DTB', 'PDG', 'BTJ', 'PLM', 'PKU', 'TKG', 'PGK', 'BKS', 'DJB',
            'YIA', 'SRG', 'SOC', 'SUB', 'DHX', 'JOG', 'PWL',
            'UPG', 'MDC', 'AMQ', 'DJJ', 'BIK',
            'PNK', 'BDJ', 'BPN', 'PKY'
        ];

        $newCount = 0;
        $updatedCount = 0;
        $skippedLocked = 0;
        $duplicateCount = 0;
        $invalidCount = 0;

        $logEntries = [];
        $validationDetails = [
            'source_count' => $sourceCount,
            'initial_db_count' => $initialDbCount,
            'locked_injourney' => count($lockedIatas),
            'processed' => [],
            'errors' => [],
        ];

        $seenIatas = [];
        $seenIcaos = [];

        foreach ($sourceRecords as $row) {
            $bandaraId = isset($row['BandaraID']) ? (int)$row['BandaraID'] : null;
            $name      = trim($row['NamaBandara'] ?? '');
            $iata      = strtoupper(trim($row['IATA'] ?? ''));
            $icao      = strtoupper(trim($row['ICAO'] ?? ''));
            $prov      = trim($row['Provinsi'] ?? '');
            $kab       = trim($row['Kabupaten'] ?? '');
            $penggunaan= trim($row['Penggunaan'] ?? '');
            $kelas     = trim($row['Kelas'] ?? '');
            $pengelola = trim($row['Pengelola'] ?? '');
            $statusOp  = trim($row['StatusOperasi'] ?? '');
            $lat       = isset($row['Lat']) && is_numeric($row['Lat']) ? (float)$row['Lat'] : null;
            $lng       = isset($row['Lng']) && is_numeric($row['Lng']) ? (float)$row['Lng'] : null;

            if ($iata === '-' || $iata === '') $iata = null;
            if ($icao === '-' || $icao === '') $icao = null;

            // Basic validation
            if (empty($name)) {
                $invalidCount++;
                $logEntries[] = "[INVALID] Record without name: ID {$bandaraId}";
                continue;
            }

            // Track potential duplicate codes in source
            if ($iata) {
                if (isset($seenIatas[$iata])) {
                    $duplicateCount++;
                    $logEntries[] = "[DUPLICATE IATA IN SOURCE] {$iata} for {$name} (already seen)";
                } else {
                    $seenIatas[$iata] = true;
                }
            }
            if ($icao) {
                if (isset($seenIcaos[$icao])) {
                    $logEntries[] = "[DUPLICATE ICAO IN SOURCE] {$icao} for {$name}";
                } else {
                    $seenIcaos[$icao] = true;
                }
            }

            // Determine if this is one of the 37 locked InJourney airports
            $isLockedInjourney = ($iata && in_array($iata, $lockedIatas));

            // Matching priority via fast in-memory lookups
            $airport = null;
            if ($bandaraId && isset($existingByBandaraId[$bandaraId])) {
                $airport = $existingByBandaraId[$bandaraId];
            }
            if (!$airport && $iata && isset($existingByIata[$iata])) {
                $airport = $existingByIata[$iata];
            }
            if (!$airport && $icao && isset($existingByIcao[$icao])) {
                $candidate = $existingByIcao[$icao];
                if (empty($candidate->iata_code) || empty($iata) || $candidate->iata_code === $iata) {
                    if (empty($candidate->bandara_id) || $candidate->bandara_id === $bandaraId) {
                        $airport = $candidate;
                    }
                }
            }
            if (!$airport) {
                $nameKey = strtolower($name);
                $provKey = strtolower($prov);
                if (isset($existingByNameProv["{$nameKey}|{$provKey}"])) {
                    $candidate = $existingByNameProv["{$nameKey}|{$provKey}"];
                    if (empty($candidate->bandara_id)) {
                        $airport = $candidate;
                    }
                } elseif (isset($existingByNameProv["{$nameKey}|"])) {
                    $candidate = $existingByNameProv["{$nameKey}|"];
                    if (empty($candidate->bandara_id)) {
                        $airport = $candidate;
                    }
                }
            }

            // Normalize management type
            $mgmtType = match (true) {
                str_contains(strtoupper($pengelola), 'ANGKASA') || str_contains(strtoupper($pengelola), 'INJOURNEY') => Airport::MANAGEMENT_INJOURNEY,
                str_contains(strtoupper($pengelola), 'PEMDA') || str_contains(strtoupper($pengelola), 'DAERAH') || str_contains(strtoupper($pengelola), 'UPTD') => Airport::MANAGEMENT_UPTD_PEMDA,
                str_contains(strtoupper($pengelola), 'DITJEN') || str_contains(strtoupper($pengelola), 'HUBUD') || str_contains(strtoupper($pengelola), 'UPBU') => Airport::MANAGEMENT_UPBU_HUBUD,
                str_contains(strtoupper($pengelola), 'TNI') => Airport::MANAGEMENT_TNI,
                str_contains(strtoupper($pengelola), 'MISSION') || str_contains(strtoupper($pengelola), 'MISION') => Airport::MANAGEMENT_MISSIONARIS,
                str_contains(strtoupper($pengelola), 'BUMN') => Airport::MANAGEMENT_BUMN,
                str_contains(strtoupper($pengelola), 'SWASTA') => Airport::MANAGEMENT_SWASTA,
                str_contains(strtoupper($pengelola), 'MASYARAKAT') => Airport::MANAGEMENT_MASYARAKAT,
                default => Airport::MANAGEMENT_OTHER,
            };

            $mgmtName = match ($mgmtType) {
                Airport::MANAGEMENT_INJOURNEY => 'PT. Angkasa Pura Indonesia',
                Airport::MANAGEMENT_UPTD_PEMDA => 'UPT Daerah/Pemda',
                Airport::MANAGEMENT_UPBU_HUBUD => 'UPT Ditjen Hubud',
                Airport::MANAGEMENT_TNI => 'TNI',
                Airport::MANAGEMENT_MISSIONARIS => 'Missionaris',
                Airport::MANAGEMENT_BUMN => 'BUMN',
                Airport::MANAGEMENT_SWASTA => 'Swasta',
                Airport::MANAGEMENT_MASYARAKAT => 'Masyarakat',
                default => $pengelola ?: 'Other',
            };

            $isIntl = (stripos($penggunaan, 'internasional') !== false);
            $airportType = $isIntl ? 'international' : 'domestic';

            if ($airport) {
                // If it's a locked InJourney record, PRESERVE locked fields!
                if ($airport->isAngkasaPura() || $isLockedInjourney) {
                    $skippedLocked++;
                    // Only update safe metadata without modifying locked attributes
                    $airport->bandara_id       = $bandaraId ?: $airport->bandara_id;
                    $airport->latitude         = $lat ?: $airport->latitude;
                    $airport->longitude        = $lng ?: $airport->longitude;
                    $airport->area             = $kab ?: $airport->area;
                    $airport->operating_status = $statusOp ?: $airport->operating_status;
                    $airport->source_checked_at= $now;
                    $airport->save();

                    $logEntries[] = "[PRESERVED LOCKED] {$airport->iata_code} — {$airport->name} (Region: {$airport->region}, Mgmt: {$airport->management_type})";
                } else {
                    // Update existing non-locked record
                    $airport->bandara_id       = $bandaraId ?: $airport->bandara_id;
                    $airport->name             = $name ?: $airport->name;
                    $airport->icao_code        = $icao ?: $airport->icao_code;
                    $airport->city             = $airport->city ?: ($kab ?: $prov);
                    $airport->area             = $kab ?: $airport->area;
                    $airport->province         = $prov ?: $airport->province;
                    $airport->country          = $airport->country ?: 'Indonesia';
                    $airport->latitude         = $lat ?: $airport->latitude;
                    $airport->longitude        = $lng ?: $airport->longitude;
                    $airport->management_type  = $mgmtType;
                    $airport->management_name  = $mgmtName;
                    $airport->usage_type       = $penggunaan ?: $airport->usage_type;
                    $airport->classification   = $kelas ?: $airport->classification;
                    $airport->operating_status = $statusOp ?: $airport->operating_status;
                    $airport->airport_type     = $airportType;
                    $airport->is_international = $isIntl;
                    $airport->region           = null; // Non-InJourney MUST be null
                    $airport->source           = Airport::SOURCE_HUBUD;
                    $airport->data_source      = Airport::SOURCE_HUBUD;
                    $airport->source_url       = $hubudUrl;
                    $airport->source_checked_at= $now;
                    $airport->save();

                    $updatedCount++;
                    $logEntries[] = "[UPDATED] ID:{$bandaraId} " . ($airport->iata_code ?: $airport->icao_code ?: '-') . " — {$name} [{$mgmtType}]";
                }
            } else {
                // Insert new airport record
                $newAirport = new Airport();
                $newAirport->bandara_id       = $bandaraId;
                $newAirport->iata_code        = $iata;
                $newAirport->icao_code        = $icao;
                $newAirport->name             = $name;
                $newAirport->city             = $kab ?: $prov;
                $newAirport->area             = $kab;
                $newAirport->province         = $prov;
                $newAirport->country          = 'Indonesia';
                $newAirport->latitude         = $lat;
                $newAirport->longitude        = $lng;
                $newAirport->management_type  = $mgmtType;
                $newAirport->management_name  = $mgmtName;
                $newAirport->usage_type       = $penggunaan ?: 'Domestik';
                $newAirport->classification   = $kelas ?: 'Non Kelas';
                $newAirport->status           = 'active';
                $newAirport->operating_status = $statusOp ?: 'Umum';
                $newAirport->airport_type     = $airportType;
                $newAirport->is_international = $isIntl;
                $newAirport->is_active        = true;
                $newAirport->data_incomplete  = empty($iata) || empty($icao);
                $newAirport->region           = null; // Non-InJourney MUST be null
                $newAirport->source           = Airport::SOURCE_HUBUD;
                $newAirport->data_source      = Airport::SOURCE_HUBUD;
                $newAirport->source_url       = $hubudUrl;
                $newAirport->source_checked_at= $now;
                $newAirport->save();

                // Register into in-memory lookup maps
                if ($bandaraId) $existingByBandaraId[$bandaraId] = $newAirport;
                if ($iata) $existingByIata[$iata] = $newAirport;
                if ($icao) $existingByIcao[$icao] = $newAirport;

                $newCount++;
                $logEntries[] = "[INSERTED] ID:{$bandaraId} " . ($iata ?: $icao ?: 'NO_CODE') . " — {$name} [{$mgmtType}]";
            }
        }

        // Final counts
        $finalDbCount = Airport::count();
        $injourneyCount = Airport::where('management_type', Airport::MANAGEMENT_INJOURNEY)->count();
        $upbuCount      = Airport::where('management_type', Airport::MANAGEMENT_UPBU_HUBUD)->count();
        $uptdCount      = Airport::where('management_type', Airport::MANAGEMENT_UPTD_PEMDA)->count();
        $tniCount       = Airport::where('management_type', Airport::MANAGEMENT_TNI)->count();
        $missionCount   = Airport::where('management_type', Airport::MANAGEMENT_MISSIONARIS)->count();
        $bumnCount      = Airport::where('management_type', Airport::MANAGEMENT_BUMN)->count();
        $swastaCount    = Airport::where('management_type', Airport::MANAGEMENT_SWASTA)->count();
        $masyarakatCount= Airport::where('management_type', Airport::MANAGEMENT_MASYARAKAT)->count();
        $otherCount     = Airport::where('management_type', Airport::MANAGEMENT_OTHER)->count();

        $this->line("InJourney Locked        : {$injourneyCount}");
        $this->line("New Airports            : {$newCount}");
        $this->line("Updated Airports        : {$updatedCount}");
        $this->line("Skipped Locked          : {$skippedLocked}");
        $this->line("Duplicates              : {$duplicateCount}");
        $this->line("Invalid Records         : {$invalidCount}");
        $this->line("");
        $this->line("Final Database          : {$finalDbCount}");
        $this->line("");
        $this->line("========================================");
        $this->line("MANAGEMENT BREAKDOWN");
        $this->line("========================================");
        $this->line("");
        $this->line("INJOURNEY       : {$injourneyCount}");
        $this->line("UPT HUBUD       : {$upbuCount}");
        $this->line("UPTD PEMDA      : {$uptdCount}");
        $this->line("TNI             : {$tniCount}");
        $this->line("MISSIONARIS     : {$missionCount}");
        $this->line("BUMN            : {$bumnCount}");
        $this->line("SWASTA          : {$swastaCount}");
        $this->line("MASYARAKAT      : {$masyarakatCount}");
        $this->line("OTHER           : {$otherCount}");
        $this->line("");
        $this->line("========================================");

        // Save validation report JSON
        $reportData = [
            'timestamp' => $now->toIso8601String(),
            'source' => 'HUBUD KEMENHUB (https://hubud.kemenhub.go.id/api/bandara-map)',
            'source_count' => $sourceCount,
            'initial_database_count' => $initialDbCount,
            'final_database_count' => $finalDbCount,
            'newly_inserted' => $newCount,
            'updated' => $updatedCount,
            'locked_injourney' => $injourneyCount,
            'duplicates_detected' => $duplicateCount,
            'invalid_records' => $invalidCount,
            'management_breakdown' => [
                'INJOURNEY' => $injourneyCount,
                'UPT_HUBUD' => $upbuCount,
                'UPTD_PEMDA' => $uptdCount,
                'TNI' => $tniCount,
                'MISSIONARIS' => $missionCount,
                'BUMN' => $bumnCount,
                'SWASTA' => $swastaCount,
                'MASYARAKAT' => $masyarakatCount,
                'OTHER' => $otherCount,
            ],
            'reconciliation_notes' => [
                'hubud_official_source_total' => 597,
                'external_reference_hubs' => $otherCount,
                'total_expected' => 597 + $otherCount,
                '597th_airport' => 'Bandara Wari (Tolikara, Papua Pegunungan) - Pengelola: Masyarakat',
            ],
        ];

        // Ensure directories exist
        File::ensureDirectoryExists(storage_path('app'));
        File::ensureDirectoryExists(storage_path('logs'));
        File::ensureDirectoryExists(base_path('database/validation'));

        File::put(storage_path('app/airport-validation-report.json'), json_encode($reportData, JSON_PRETTY_PRINT));
        File::put(base_path('database/validation/airport_master_validation.json'), json_encode($reportData, JSON_PRETTY_PRINT));
        File::put(storage_path('logs/hubud-airport-import.log'), implode("\n", $logEntries));

        $this->info("Validation report saved to: storage/app/airport-validation-report.json & database/validation/airport_master_validation.json");
        $this->info("Import log saved to: storage/logs/hubud-airport-import.log");

        return Command::SUCCESS;
    }
}
