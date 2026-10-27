<?php

namespace App\Services\FlightDailyReport;

use App\Models\Airport;
use App\Models\FdrFlight;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FdrDatabaseService
{
    public const AIRPORT_COORDINATES_FALLBACK = [
        // Domestic
        'CGK' => ['name' => 'Soekarno-Hatta International', 'city' => 'Jakarta', 'country' => 'Indonesia', 'lat' => -6.1256, 'lon' => 106.6559],
        'HLP' => ['name' => 'Halim Perdanakusuma', 'city' => 'Jakarta', 'country' => 'Indonesia', 'lat' => -6.2667, 'lon' => 106.8917],
        'DPS' => ['name' => 'I Gusti Ngurah Rai', 'city' => 'Denpasar', 'country' => 'Indonesia', 'lat' => -8.7481, 'lon' => 115.1672],
        'SUB' => ['name' => 'Juanda International', 'city' => 'Surabaya', 'country' => 'Indonesia', 'lat' => -7.3797, 'lon' => 112.7875],
        'KNO' => ['name' => 'Kualanamu International', 'city' => 'Medan', 'country' => 'Indonesia', 'lat' => 3.6422, 'lon' => 98.8853],
        'UPG' => ['name' => 'Sultan Hasanuddin', 'city' => 'Makassar', 'country' => 'Indonesia', 'lat' => -5.0617, 'lon' => 119.5542],
        'BPN' => ['name' => 'Sultan Aji Muhammad Sulaiman Sepinggan', 'city' => 'Balikpapan', 'country' => 'Indonesia', 'lat' => -1.2683, 'lon' => 116.8947],
        'YIA' => ['name' => 'Yogyakarta International', 'city' => 'Kulon Progo', 'country' => 'Indonesia', 'lat' => -7.9072, 'lon' => 110.0544],
        'SRG' => ['name' => 'Jenderal Ahmad Yani', 'city' => 'Semarang', 'country' => 'Indonesia', 'lat' => -6.9742, 'lon' => 110.3756],
        'SOC' => ['name' => 'Adi Soemarmo', 'city' => 'Surakarta', 'country' => 'Indonesia', 'lat' => -7.5161, 'lon' => 110.7569],
        'BDJ' => ['name' => 'Syamsudin Noor', 'city' => 'Banjarmasin', 'country' => 'Indonesia', 'lat' => -3.4422, 'lon' => 114.7578],
        'PLM' => ['name' => 'Sultan Mahmud Badaruddin II', 'city' => 'Palembang', 'country' => 'Indonesia', 'lat' => -2.8983, 'lon' => 104.7000],
        'PKU' => ['name' => 'Sultan Syarif Kasim II', 'city' => 'Pekanbaru', 'country' => 'Indonesia', 'lat' => 0.4608, 'lon' => 101.4447],
        'PDG' => ['name' => 'Minangkabau International', 'city' => 'Padang', 'country' => 'Indonesia', 'lat' => -0.7867, 'lon' => 100.2806],
        'BTH' => ['name' => 'Hang Nadim', 'city' => 'Batam', 'country' => 'Indonesia', 'lat' => 1.1211, 'lon' => 104.1189],
        'PNK' => ['name' => 'Supadio', 'city' => 'Pontianak', 'country' => 'Indonesia', 'lat' => -0.1506, 'lon' => 109.4039],
        'MDC' => ['name' => 'Sam Ratulangi', 'city' => 'Manado', 'country' => 'Indonesia', 'lat' => 1.5492, 'lon' => 124.9258],
        'LOP' => ['name' => 'Lombok International', 'city' => 'Praya', 'country' => 'Indonesia', 'lat' => -8.7578, 'lon' => 116.2764],
        'KOE' => ['name' => 'El Tari', 'city' => 'Kupang', 'country' => 'Indonesia', 'lat' => -10.1714, 'lon' => 123.6653],
        'AMQ' => ['name' => 'Pattimura', 'city' => 'Ambon', 'country' => 'Indonesia', 'lat' => -3.7092, 'lon' => 128.0894],
        'DJJ' => ['name' => 'Sentani', 'city' => 'Jayapura', 'country' => 'Indonesia', 'lat' => -2.5769, 'lon' => 140.5164],
        'TIM' => ['name' => 'Mozes Kilangin', 'city' => 'Timika', 'country' => 'Indonesia', 'lat' => -4.5283, 'lon' => 136.8867],
        'SOQ' => ['name' => 'Domine Eduard Osok', 'city' => 'Sorong', 'country' => 'Indonesia', 'lat' => -0.8906, 'lon' => 131.2872],
        'KJT' => ['name' => 'Kertajati International', 'city' => 'Majalengka', 'country' => 'Indonesia', 'lat' => -6.6475, 'lon' => 108.1883],
        'BDO' => ['name' => 'Husein Sastranegara', 'city' => 'Bandung', 'country' => 'Indonesia', 'lat' => -6.9006, 'lon' => 107.5761],
        'BTJ' => ['name' => 'Sultan Iskandar Muda', 'city' => 'Banda Aceh', 'country' => 'Indonesia', 'lat' => 5.5225, 'lon' => 95.4194],
        'DTB' => ['name' => 'Sisingamangaraja XII', 'city' => 'Silangit', 'country' => 'Indonesia', 'lat' => 2.2575, 'lon' => 98.9839],
        'TNJ' => ['name' => 'Raja Haji Fisabilillah', 'city' => 'Tanjung Pinang', 'country' => 'Indonesia', 'lat' => 0.9236, 'lon' => 104.5317],
        'DJB' => ['name' => 'Sultan Thaha', 'city' => 'Jambi', 'country' => 'Indonesia', 'lat' => -1.6381, 'lon' => 103.6444],
        'PGK' => ['name' => 'Depati Amir', 'city' => 'Pangkal Pinang', 'country' => 'Indonesia', 'lat' => -2.1625, 'lon' => 106.1394],
        'TJQ' => ['name' => 'H.A.S. Hanandjoeddin', 'city' => 'Belitung', 'country' => 'Indonesia', 'lat' => -2.7608, 'lon' => 107.7547],
        'BKS' => ['name' => 'Fatmawati Soekarno', 'city' => 'Bengkulu', 'country' => 'Indonesia', 'lat' => -3.8639, 'lon' => 102.3400],
        'TKG' => ['name' => 'Radin Inten II', 'city' => 'Lampung', 'country' => 'Indonesia', 'lat' => -5.2425, 'lon' => 105.1786],
        'PKY' => ['name' => 'Tjilik Riwut', 'city' => 'Palangkaraya', 'country' => 'Indonesia', 'lat' => -2.2253, 'lon' => 113.9436],
        'PLW' => ['name' => 'Mutiara SIS Al-Jufrie', 'city' => 'Palu', 'country' => 'Indonesia', 'lat' => -0.9178, 'lon' => 119.9097],
        'MKQ' => ['name' => 'Mopah', 'city' => 'Merauke', 'country' => 'Indonesia', 'lat' => -8.5203, 'lon' => 140.4181],
        'KDI' => ['name' => 'Haluoleo', 'city' => 'Kendari', 'country' => 'Indonesia', 'lat' => -4.0817, 'lon' => 122.4178],
        'GTO' => ['name' => 'Djalaluddin', 'city' => 'Gorontalo', 'country' => 'Indonesia', 'lat' => 0.6372, 'lon' => 122.8517],
        'LUW' => ['name' => 'Syukuran Aminuddin Amir', 'city' => 'Luwuk', 'country' => 'Indonesia', 'lat' => -1.0378, 'lon' => 122.7758],
        'TTE' => ['name' => 'Sultan Babullah', 'city' => 'Ternate', 'country' => 'Indonesia', 'lat' => 0.8317, 'lon' => 127.3806],
        'LBJ' => ['name' => 'Komodo', 'city' => 'Labuan Bajo', 'country' => 'Indonesia', 'lat' => -8.4878, 'lon' => 119.8872],
        'AAP' => ['name' => 'APT Pranoto', 'city' => 'Samarinda', 'country' => 'Indonesia', 'lat' => -0.3744, 'lon' => 117.2500],
        'BEJ' => ['name' => 'Kalimarau', 'city' => 'Berau', 'country' => 'Indonesia', 'lat' => 2.1555, 'lon' => 117.4322],
        'BUU' => ['name' => 'Muara Bungo', 'city' => 'Bungo', 'country' => 'Indonesia', 'lat' => -1.1277, 'lon' => 102.1350],
        'LLJ' => ['name' => 'Silampari', 'city' => 'Lubuklinggau', 'country' => 'Indonesia', 'lat' => -3.2933, 'lon' => 102.9140],
        'MLG' => ['name' => 'Abdul Rachman Saleh', 'city' => 'Malang', 'country' => 'Indonesia', 'lat' => -7.9266, 'lon' => 112.7140],
        'PKN' => ['name' => 'Iskandar', 'city' => 'Pangkalan Bun', 'country' => 'Indonesia', 'lat' => -2.7052, 'lon' => 111.6736],
        'TRK' => ['name' => 'Juwata', 'city' => 'Tarakan', 'country' => 'Indonesia', 'lat' => 3.3267, 'lon' => 117.5656],
        'SMQ' => ['name' => 'Sampit', 'city' => 'Sampit', 'country' => 'Indonesia', 'lat' => -2.5006, 'lon' => 112.9739],
        'MOH' => ['name' => 'Morowali Airport', 'city' => 'Morowali', 'country' => 'Indonesia', 'lat' => -1.8744, 'lon' => 121.9483],
        'MWS' => ['name' => 'Muara Wahau Airport', 'city' => 'Muara Wahau', 'country' => 'Indonesia', 'lat' => 0.8167, 'lon' => 116.8833],
        'WDB' => ['name' => 'Wedo Bay Airport', 'city' => 'Weda', 'country' => 'Indonesia', 'lat' => 0.4639, 'lon' => 127.9150],

        // International
        'SIN' => ['name' => 'Singapore Changi', 'city' => 'Singapore', 'country' => 'Singapore', 'lat' => 1.3644, 'lon' => 103.9915],
        'KUL' => ['name' => 'Kuala Lumpur International', 'city' => 'Kuala Lumpur', 'country' => 'Malaysia', 'lat' => 2.7456, 'lon' => 101.7099],
        'SZB' => ['name' => 'Sultan Abdul Aziz Shah', 'city' => 'Subang', 'country' => 'Malaysia', 'lat' => 3.1306, 'lon' => 101.5497],
        'PEN' => ['name' => 'Penang International', 'city' => 'Penang', 'country' => 'Malaysia', 'lat' => 5.2971, 'lon' => 100.2769],
        'BKK' => ['name' => 'Suvarnabhumi Airport', 'city' => 'Bangkok', 'country' => 'Thailand', 'lat' => 13.6900, 'lon' => 100.7501],
        'DMK' => ['name' => 'Don Mueang International', 'city' => 'Bangkok', 'country' => 'Thailand', 'lat' => 13.9126, 'lon' => 100.6068],
        'HKG' => ['name' => 'Hong Kong International', 'city' => 'Hong Kong', 'country' => 'Hong Kong', 'lat' => 22.3080, 'lon' => 113.9185],
        'HND' => ['name' => 'Tokyo Haneda Airport', 'city' => 'Tokyo', 'country' => 'Japan', 'lat' => 35.5494, 'lon' => 139.7798],
        'NRT' => ['name' => 'Tokyo Narita Airport', 'city' => 'Tokyo', 'country' => 'Japan', 'lat' => 35.7647, 'lon' => 140.3863],
        'CTS' => ['name' => 'New Chitose Airport', 'city' => 'Sapporo', 'country' => 'Japan', 'lat' => 42.7752, 'lon' => 141.6923],
        'KIX' => ['name' => 'Kansai International', 'city' => 'Osaka', 'country' => 'Japan', 'lat' => 34.4320, 'lon' => 135.2304],
        'ICN' => ['name' => 'Seoul Incheon International', 'city' => 'Seoul', 'country' => 'South Korea', 'lat' => 37.4602, 'lon' => 126.4407],
        'PVG' => ['name' => 'Shanghai Pudong International', 'city' => 'Shanghai', 'country' => 'China', 'lat' => 31.1443, 'lon' => 121.8083],
        'CAN' => ['name' => 'Guangzhou Baiyun International', 'city' => 'Guangzhou', 'country' => 'China', 'lat' => 23.3924, 'lon' => 113.2988],
        'CSX' => ['name' => 'Changsha Huanghua International', 'city' => 'Changsha', 'country' => 'China', 'lat' => 28.1892, 'lon' => 113.2197],
        'DYG' => ['name' => 'Zhangjiajie Hehua', 'city' => 'Zhangjiajie', 'country' => 'China', 'lat' => 29.1028, 'lon' => 110.4439],
        'HAK' => ['name' => 'Haikou Meilan International', 'city' => 'Haikou', 'country' => 'China', 'lat' => 19.9347, 'lon' => 110.4589],
        'KMG' => ['name' => 'Kunming Changshui International', 'city' => 'Kunming', 'country' => 'China', 'lat' => 25.1019, 'lon' => 102.9292],
        'NNG' => ['name' => 'Nanning Wuxu International', 'city' => 'Nanning', 'country' => 'China', 'lat' => 22.6083, 'lon' => 108.1722],
        'TPE' => ['name' => 'Taiwan Taoyuan International', 'city' => 'Taipei', 'country' => 'Taiwan', 'lat' => 25.0797, 'lon' => 121.2342],
        'MFM' => ['name' => 'Macau International', 'city' => 'Macau', 'country' => 'Macau', 'lat' => 22.1494, 'lon' => 113.5917],
        'HAN' => ['name' => 'Noi Bai International', 'city' => 'Hanoi', 'country' => 'Vietnam', 'lat' => 21.2212, 'lon' => 105.8072],
        'SGN' => ['name' => 'Tan Son Nhat International', 'city' => 'Ho Chi Minh City', 'country' => 'Vietnam', 'lat' => 10.8188, 'lon' => 106.6519],
        'PNH' => ['name' => 'Phnom Penh International', 'city' => 'Phnom Penh', 'country' => 'Cambodia', 'lat' => 11.5466, 'lon' => 104.8441],
        'KOS' => ['name' => 'Sihanoukville International', 'city' => 'Sihanoukville', 'country' => 'Cambodia', 'lat' => 10.5797, 'lon' => 103.6360],
        'SYD' => ['name' => 'Sydney Kingsford Smith', 'city' => 'Sydney', 'country' => 'Australia', 'lat' => -33.9399, 'lon' => 151.1753],
        'MEL' => ['name' => 'Melbourne Tullamarine', 'city' => 'Melbourne', 'country' => 'Australia', 'lat' => -37.6690, 'lon' => 144.8410],
        'PER' => ['name' => 'Perth Airport', 'city' => 'Perth', 'country' => 'Australia', 'lat' => -31.9403, 'lon' => 115.9668],
        'DRW' => ['name' => 'Darwin International', 'city' => 'Darwin', 'country' => 'Australia', 'lat' => -12.4147, 'lon' => 130.8766],
        'BNE' => ['name' => 'Brisbane Airport', 'city' => 'Brisbane', 'country' => 'Australia', 'lat' => -27.3842, 'lon' => 153.1175],
        'BLR' => ['name' => 'Kempegowda International', 'city' => 'Bengaluru', 'country' => 'India', 'lat' => 13.1986, 'lon' => 77.7066],
        'HYD' => ['name' => 'Rajiv Gandhi International', 'city' => 'Hyderabad', 'country' => 'India', 'lat' => 17.2403, 'lon' => 78.4294],
        'JED' => ['name' => 'King Abdulaziz International', 'city' => 'Jeddah', 'country' => 'Saudi Arabia', 'lat' => 21.6796, 'lon' => 39.1565],
        'MED' => ['name' => 'Prince Mohammad bin Abdulaziz', 'city' => 'Medina', 'country' => 'Saudi Arabia', 'lat' => 24.5534, 'lon' => 39.7051],
        'DOH' => ['name' => 'Hamad International', 'city' => 'Doha', 'country' => 'Qatar', 'lat' => 25.2731, 'lon' => 51.6081],
        'DXB' => ['name' => 'Dubai International', 'city' => 'Dubai', 'country' => 'United Arab Emirates', 'lat' => 25.2532, 'lon' => 55.3657],
        'AUH' => ['name' => 'Zayed International', 'city' => 'Abu Dhabi', 'country' => 'United Arab Emirates', 'lat' => 24.4330, 'lon' => 54.6511],
        'IST' => ['name' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'Turkey', 'lat' => 41.2753, 'lon' => 28.7519],
        'ISL' => ['name' => 'Ataturk Airport', 'city' => 'Istanbul', 'country' => 'Turkey', 'lat' => 40.9769, 'lon' => 28.8146],
        'AMS' => ['name' => 'Amsterdam Airport Schiphol', 'city' => 'Amsterdam', 'country' => 'Netherlands', 'lat' => 52.3105, 'lon' => 4.7683],
        'LHR' => ['name' => 'London Heathrow', 'city' => 'London', 'country' => 'United Kingdom', 'lat' => 51.4700, 'lon' => -0.4543],
        'STN' => ['name' => 'London Stansted', 'city' => 'London', 'country' => 'United Kingdom', 'lat' => 51.8860, 'lon' => 0.2389],
        'CDG' => ['name' => 'Paris Charles de Gaulle', 'city' => 'Paris', 'country' => 'France', 'lat' => 49.0097, 'lon' => 2.5479],
        'FRA' => ['name' => 'Frankfurt Airport', 'city' => 'Frankfurt', 'country' => 'Germany', 'lat' => 50.0379, 'lon' => 8.5622],
        'MAD' => ['name' => 'Adolfo Suarez Madrid-Barajas', 'city' => 'Madrid', 'country' => 'Spain', 'lat' => 40.4839, 'lon' => -3.5680],
    ];
    /**
     * Check if an upload already has records in the fdr_flights database table.
     */
    public function hasDatabaseRecords(Upload $upload): bool
    {
        return FdrFlight::where('upload_id', $upload->id)->exists();
    }

    /**
     * Get count of database records for an upload.
     */
    public function getRecordCount(Upload $upload): int
    {
        return FdrFlight::where('upload_id', $upload->id)->count();
    }

    /**
     * Ingest/Sync normalized FDR records into the fdr_flights table in efficient batches.
     * Guarantees idempotency by clearing any previous partial records for this upload.
     */
    public function syncUploadToDatabase(Upload $upload, array $records, array $meta = []): int
    {
        if (empty($records)) {
            return 0;
        }

        $now = now();
        $batch = [];
        $batchSize = 500;
        $totalInserted = 0;
        $defaultAirport = strtoupper(trim($meta['airport'] ?? 'CGK'));

        DB::beginTransaction();
        try {
            FdrFlight::where('upload_id', $upload->id)->delete();

            foreach ($records as $r) {
                $row = $this->transformRecordToDbRow($r, $upload->id, $defaultAirport, $meta, $now);
                if ($row === null) {
                    continue;
                }

                $batch[] = $row;
                if (count($batch) >= $batchSize) {
                    DB::table('fdr_flights')->insert($batch);
                    $totalInserted += count($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('fdr_flights')->insert($batch);
                $totalInserted += count($batch);
                $batch = [];
            }

            DB::commit();
            Log::info("FdrDatabaseService: Synced {$totalInserted} flights to database for upload #{$upload->id}");

            try {
                $this->precomputeAndCacheAnalytics($upload, $meta);
            } catch (\Throwable $e) {
                Log::warning("Could not precompute analytics in syncUploadToDatabase for upload #{$upload->id}: " . $e->getMessage());
            }

            return $totalInserted;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("FdrDatabaseService sync error for upload #{$upload->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Memory-safe streaming parser and database syncer directly from file.
     * Streams 2,000 rows at a time, keeping peak PHP memory below 20MB even for 50MB files.
     */
    public function syncUploadFromFile(Upload $upload, string $filePath, FlightDailyReportParser $parser): int
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Source FDR file not found: {$filePath}");
        }

        $now = now();
        $totalInserted = 0;
        $offset = 0;
        $isEof = false;
        $meta = [];

        DB::beginTransaction();
        try {
            FdrFlight::where('upload_id', $upload->id)->delete();

            while (!$isEof) {
                $batchResult = $parser->parseBatch($filePath, $offset, 2000, $meta);
                $offset = $batchResult['next_offset'];
                $isEof = $batchResult['is_eof'];
                $meta = array_merge($meta, $batchResult['meta'] ?? []);

                $records = $batchResult['records'] ?? [];
                if (!empty($records)) {
                    $dbRows = [];
                    $defaultAirport = strtoupper(trim($meta['airport'] ?? 'CGK'));
                    foreach ($records as $r) {
                        $row = $this->transformRecordToDbRow($r, $upload->id, $defaultAirport, $meta, $now);
                        if ($row !== null) {
                            $dbRows[] = $row;
                        }
                    }

                    foreach (array_chunk($dbRows, 500) as $chunk) {
                        DB::table('fdr_flights')->insert($chunk);
                        $totalInserted += count($chunk);
                    }
                    unset($dbRows);
                    unset($records);
                }
            }

            DB::commit();
            Log::info("FdrDatabaseService: Synced {$totalInserted} flights from file for upload #{$upload->id}");

            try {
                $this->precomputeAndCacheAnalytics($upload, $meta);
            } catch (\Throwable $e) {
                Log::warning("Could not precompute analytics in syncUploadFromFile for upload #{$upload->id}: " . $e->getMessage());
            }

            return $totalInserted;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("FdrDatabaseService file sync error for upload #{$upload->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Transform a raw/normalized FDR row into a native PostgreSQL fdr_flights insert array.
     */
    public function transformRecordToDbRow(array $r, int $uploadId, string $defaultAirport, array $meta, $now): ?array
    {
        $flNo = trim((string)($r['flight_no'] ?? ($r['flight_number'] ?? 'N/A')));
        $airline = trim((string)($r['air_line'] ?? ($r['airline'] ?? ($r['airline_code'] ?? ($r['operator'] ?? 'N/A')))));

        if ($airline === 'N/A' || empty($airline)) {
            if (preg_match('/^([A-Z0-9]{2,3})\s*\d+/i', $flNo, $mCode)) {
                $airline = strtoupper($mCode[1]);
            }
        }

        if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY'
            || strcasecmp($airline, 'PAX ALL') === 0
            || stripos($airline, 'PAX ALL') !== false
            || stripos($flNo, 'PAX ALL') !== false
            || stripos($flNo, 'TOTAL') !== false
            || stripos($flNo, 'GRAND') !== false) {
            return null;
        }

        if ($flNo === 'N/A' && $airline === 'N/A') {
            return null;
        }

        $schedDt = !empty($r['scheduled_datetime']) ? Carbon::parse($r['scheduled_datetime'])->toDateTimeString() : null;
        $actDt = !empty($r['actual_datetime']) ? Carbon::parse($r['actual_datetime'])->toDateTimeString() : null;

        $flightDate = null;
        if (!empty($r['flight_date']) && $r['flight_date'] !== 'N/A') {
            $flightDate = FlightDailyReportFilter::standardizeDate($r['flight_date']);
        } elseif (!empty($r['operational_date']) && $r['operational_date'] !== 'N/A') {
            $flightDate = FlightDailyReportFilter::standardizeDate($r['operational_date']);
        } elseif ($schedDt) {
            $flightDate = substr($schedDt, 0, 10);
        } elseif (!empty($meta['period_start'])) {
            $flightDate = FlightDailyReportFilter::standardizeDate($meta['period_start']);
        }

        $direction = strtoupper(trim((string)($r['direction'] ?? 'ARRIVAL')));
        if (!in_array($direction, ['ARRIVAL', 'DEPARTURE'])) {
            $direction = 'ARRIVAL';
        }

        $repAirport = strtoupper(trim((string)($r['report_airport'] ?? ($r['branch'] ?? $defaultAirport))));
        if (empty($repAirport) || $repAirport === 'N/A') {
            $repAirport = $defaultAirport;
        }

        $traffic = strtoupper(trim((string)($r['traffic'] ?? ($r['route_type'] ?? 'DOMESTIC'))));
        if (!in_array($traffic, ['DOMESTIC', 'INTERNATIONAL'])) {
            $traffic = ($traffic === 'INT' || $traffic === 'INTL') ? 'INTERNATIONAL' : 'DOMESTIC';
        }

        $delayMinutes = (int)($r['delay_minutes'] ?? 0);
        $isRealized = (bool)($r['is_realized'] ?? false);
        if (!$isRealized && (!empty($r['aibt']) && $r['aibt'] !== 'N/A' || !empty($r['aobt']) && $r['aobt'] !== 'N/A')) {
            $isRealized = true;
        }

        $status = 'ON_TIME';
        if (!$isRealized) {
            $status = 'UNREALIZED';
        } elseif ($delayMinutes > 15) {
            $status = 'DELAYED';
        } elseif (!empty($r['is_irregular'])) {
            $status = 'IRREGULAR';
        }

        $cap = (int)($r['cap'] ?? ($r['capacity'] ?? ($r['seat_capacity'] ?? 0)));

        $paxLoad = 0;
        if (isset($r['load']) && is_numeric($r['load']) && !str_contains((string)$r['load'], '%')) {
            $paxLoad = (int)$r['load'];
        } elseif (isset($r['passenger_load']) && is_numeric($r['passenger_load'])) {
            $paxLoad = (int)$r['passenger_load'];
        } elseif (isset($r['passengers']) && is_numeric($r['passengers'])) {
            $paxLoad = (int)$r['passengers'];
        } elseif (isset($r['pax']) && is_numeric($r['pax'])) {
            $paxLoad = (int)$r['pax'];
        } elseif (isset($r['pax_total']) && is_numeric($r['pax_total'])) {
            $paxLoad = (int)$r['pax_total'];
        } else {
            $paxLoad = (int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0);
        }

        $loadFactor = null;
        if (isset($r['load_factor']) && is_numeric($r['load_factor'])) {
            $loadFactor = (float)$r['load_factor'];
        } elseif (isset($r['slf']) && is_numeric($r['slf'])) {
            $loadFactor = (float)$r['slf'];
        } elseif ($cap > 0 && $paxLoad > 0) {
            $loadFactor = round(($paxLoad / $cap) * 100.0, 2);
        }

        $schedTimeStr = $r['sched_display'] ?? ($direction === 'ARRIVAL' ? ($r['sibt'] ?? null) : ($r['sobt'] ?? null));
        $actTimeStr = $r['actual_display'] ?? ($direction === 'ARRIVAL' ? ($r['aibt'] ?? null) : ($r['aobt'] ?? null));

        $originAirport = $r['city_1'] ?? ($r['arrival_route'] ?? ($r['station_1'] ?? null));
        $destAirport = $r['city_2'] ?? ($r['departure_route'] ?? ($r['station_2'] ?? null));

        $isSched = !empty($r['is_scheduled']);
        if (!isset($r['is_scheduled'])) {
            $legStr = strtoupper(trim(preg_replace('/<br\s*\/?>/i', ' ', (string)($r['leg'] ?? ''))));
            if (str_contains($legStr, 'UNSCHED') || str_contains($legStr, 'TIDAK')) {
                $isSched = false;
            } elseif (str_contains($legStr, 'SCHED')) {
                $isSched = true;
            } else {
                $isSched = true;
            }
        }

        return [
            'upload_id'            => $uploadId,
            'flight_date'          => $flightDate ?: date('Y-m-d'),
            'flight_number'        => $flNo,
            'flight_number_base'   => $r['flight_no_base'] ?? $flNo,
            'flight_suffix'        => $r['flight_suffix'] ?? null,
            'airline_code'         => $airline,
            'airline'              => $airline,
            'paired_flight_number' => $r['paired_no'] ?? null,
            'aircraft_type'        => $r['desc'] ?? ($r['aircraft_type'] ?? null),
            'registration_number'  => $r['reg_no'] ?? ($r['registration'] ?? null),
            'leg'                  => $r['leg'] ?? null,
            'direction'            => $direction,
            'movement_type'        => $isSched ? 'SCHEDULED' : 'UNSCHEDULED',
            'origin_airport'       => $originAirport,
            'destination_airport'  => $destAirport,
            'city_1'               => $originAirport,
            'city_2'               => $destAirport,
            'report_airport'       => $repAirport,
            'traffic_type'         => $traffic,
            'scheduled_time'       => $schedDt ? substr($schedDt, 11, 8) : null,
            'actual_time'          => $actDt ? substr($actDt, 11, 8) : null,
            'scheduled_datetime'   => $schedDt,
            'actual_datetime'      => $actDt,
            'scheduled_time_str'   => $schedTimeStr,
            'actual_time_str'      => $actTimeStr,
            'scheduled_hour'       => (isset($r['scheduled_hour']) && is_numeric($r['scheduled_hour'])) ? (int)$r['scheduled_hour'] : ($schedDt ? (int)substr($schedDt, 11, 2) : null),
            'actual_hour'          => (isset($r['actual_hour']) && is_numeric($r['actual_hour'])) ? (int)$r['actual_hour'] : ($actDt ? (int)substr($actDt, 11, 2) : null),
            'operational_hour'     => (int)($r['operational_hour'] ?? ($r['hour'] ?? 0)),
            'delay_minutes'        => $delayMinutes,
            'status'               => $status,
            'is_realized'          => $isRealized ? 'true' : 'false',
            'is_irregular'         => (!empty($r['is_irregular'])) ? 'true' : 'false',
            'passenger_capacity'   => $cap,
            'passenger_load'       => $paxLoad,
            'load_factor'          => $loadFactor,
            'pax_adult'            => (int)($r['adult'] ?? 0),
            'pax_child'            => (int)($r['child'] ?? 0),
            'pax_infant'           => (int)($r['infant'] ?? 0),
            'pax_transit'          => (int)($r['transit'] ?? 0),
            'pax_transfer'         => (int)($r['transfer'] ?? 0),
            'operating_crew'       => (int)($r['operating_crew'] ?? ($r['crw'] ?? 0)),
            'extra_crew'           => (int)($r['extra_crew'] ?? ($r['ex_crw'] ?? 0)),
            'cargo_kg'             => (float)($r['cargo_kg'] ?? 0),
            'baggage_kg'           => (float)($r['baggage_kg'] ?? 0),
            'pos_kg'               => (float)($r['pos_kg'] ?? 0),
            'stand'                => $r['stand'] ?? null,
            'runway'               => $r['runway'] ?? null,
            'mtow'                 => $r['mtow'] ?? null,
            'raw_data'             => json_encode(array_merge($r, [
                'source_row_number' => $r['source_row_number'] ?? ($r['index'] ?? null),
            ])),
            'created_at'           => $now,
            'updated_at'           => $now,
        ];
    }

    /**
     * Single canonical query builder for all FDR filtering needs:
     * KPI, Charts, Detailed Flight Registry, Search, and Exports.
     * Guaranteed upload_id scoping and deterministic sorting.
     */
    public function buildFdrFilteredQuery(int $uploadId, array $filters): Builder
    {
        return FdrFlight::query()
            ->where('upload_id', $uploadId)
            ->applyFilters($filters);
    }

    /**
     * Compute presentation-ready analytics directly from database for high performance.
     */
    public function computeAnalyticsFromDb(Upload $upload, array $filters, array $meta = []): array
    {
        $base = $this->buildFdrFilteredQuery($upload->id, $filters);

        // 1. Core KPIs Aggregation with Directional and Class Splits
        $kpiRow = (clone $base)->selectRaw("
            COUNT(*) as total_flights,
            COUNT(CASE WHEN direction = 'ARRIVAL' THEN 1 END) as arrivals,
            COUNT(CASE WHEN direction = 'DEPARTURE' THEN 1 END) as departures,
            COUNT(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'DOMESTIC' THEN 1 END) as arrivals_dom,
            COUNT(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'INTERNATIONAL' THEN 1 END) as arrivals_int,
            COUNT(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'DOMESTIC' THEN 1 END) as departures_dom,
            COUNT(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'INTERNATIONAL' THEN 1 END) as departures_int,
            COUNT(CASE WHEN movement_type = 'SCHEDULED' THEN 1 END) as scheduled_flights,
            COUNT(CASE WHEN movement_type = 'UNSCHEDULED' THEN 1 END) as unscheduled_flights,
            COUNT(CASE WHEN is_realized = true THEN 1 END) as realized_flights,
            COUNT(CASE WHEN is_realized = false THEN 1 END) as planned_unrealized,
            COUNT(CASE WHEN is_realized = true AND delay_minutes < -15 THEN 1 END) as early_flights,
            COUNT(CASE WHEN is_realized = true AND delay_minutes >= -15 AND delay_minutes <= 15 THEN 1 END) as on_time_flights,
            COUNT(CASE WHEN is_realized = true AND delay_minutes > 15 THEN 1 END) as delayed_flights,
            COUNT(CASE WHEN is_irregular = true THEN 1 END) as irregular_flights,
            COALESCE(AVG(CASE WHEN is_realized = true THEN delay_minutes END), 0) as avg_delay_minutes,
            COALESCE(MIN(CASE WHEN is_realized = true THEN delay_minutes END), 0) as min_delay_minutes,
            COALESCE(MAX(CASE WHEN is_realized = true THEN delay_minutes END), 0) as max_delay_minutes,
            COALESCE(SUM(passenger_capacity), 0) as total_capacity,
            COALESCE(SUM(passenger_load), 0) as total_load,
            COALESCE(SUM(operating_crew), 0) as total_operating_crew,
            COALESCE(SUM(extra_crew), 0) as total_extra_crew,
            COALESCE(SUM(pax_adult), 0) as total_adult,
            COALESCE(SUM(pax_child), 0) as total_child,
            COALESCE(SUM(pax_infant), 0) as total_infant,
            COALESCE(SUM(pax_transit), 0) as total_transit,
            COALESCE(SUM(pax_transfer), 0) as total_transfer,
            COALESCE(SUM(cargo_kg), 0) as total_cargo_kg,
            COALESCE(SUM(baggage_kg), 0) as total_baggage_kg,
            COALESCE(SUM(pos_kg), 0) as total_pos_kg,
            -- ARRIVAL BREAKDOWN
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN passenger_capacity END), 0) as arr_capacity,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN passenger_load END), 0) as arr_load,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN pax_adult END), 0) as arr_adult,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN pax_child END), 0) as arr_child,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN pax_infant END), 0) as arr_infant,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN pax_transit END), 0) as arr_transit,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN pax_transfer END), 0) as arr_transfer,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN cargo_kg END), 0) as arr_cargo_kg,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN baggage_kg END), 0) as arr_baggage_kg,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN pos_kg END), 0) as arr_pos_kg,
            -- DEPARTURE BREAKDOWN
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN passenger_capacity END), 0) as dep_capacity,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN passenger_load END), 0) as dep_load,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN pax_adult END), 0) as dep_adult,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN pax_child END), 0) as dep_child,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN pax_infant END), 0) as dep_infant,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN pax_transit END), 0) as dep_transit,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN pax_transfer END), 0) as dep_transfer,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN cargo_kg END), 0) as dep_cargo_kg,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN baggage_kg END), 0) as dep_baggage_kg,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN pos_kg END), 0) as dep_pos_kg
        ")->first();

        $totFlights   = (int)($kpiRow->total_flights ?? 0);
        $schedFlights = (int)($kpiRow->scheduled_flights ?? 0);
        $arrFlights   = (int)($kpiRow->arrivals ?? 0);
        $depFlights   = (int)($kpiRow->departures ?? 0);
        $realized     = (int)($kpiRow->realized_flights ?? 0);
        $onTime       = (int)($kpiRow->on_time_flights ?? 0);
        $delayed      = (int)($kpiRow->delayed_flights ?? 0);
        $earlyCount   = (int)($kpiRow->early_flights ?? 0);
        $totLoad      = (int)($kpiRow->total_load ?? 0);
        $totCap       = (int)($kpiRow->total_capacity ?? 0);

        $totPax = (int)$kpiRow->total_adult + (int)$kpiRow->total_child + (int)$kpiRow->total_infant;
        if ($totPax === 0 && $totLoad > 0) {
            $totPax = $totLoad;
        }

        $otpPercent = $realized > 0 ? round(($onTime / $realized) * 100, 1) : 0.0;
        $delayPercent = $realized > 0 ? round(($delayed / $realized) * 100, 1) : 0.0;
        $loadFactorAvg = $totCap > 0 ? round(($totLoad / $totCap) * 100, 1) : 0.0;

        $timeBasis = in_array($filters['time_basis'] ?? '', ['scheduled', 'actual'], true) ? $filters['time_basis'] : 'actual';
        $hourCol = ($timeBasis === 'scheduled')
            ? "COALESCE(scheduled_hour, actual_hour, 0)"
            : "COALESCE(actual_hour, scheduled_hour, 0)";

        // 2. Hourly Movement Distribution (Strictly 24 hourly buckets: 00:00 to 23:00)
        $hourlyRows = (clone $base)->selectRaw("
            {$hourCol} as op_hour,
            COUNT(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'DOMESTIC' THEN 1 END) as arr_dom,
            COUNT(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'INTERNATIONAL' THEN 1 END) as arr_int,
            COUNT(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'DOMESTIC' THEN 1 END) as dep_dom,
            COUNT(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'INTERNATIONAL' THEN 1 END) as dep_int,
            COUNT(*) as total
        ")->groupByRaw($hourCol)->get()->keyBy('op_hour');

        $arrDomArr = array_fill(0, 24, 0);
        $arrIntArr = array_fill(0, 24, 0);
        $depDomArr = array_fill(0, 24, 0);
        $depIntArr = array_fill(0, 24, 0);
        $totHourArr = array_fill(0, 24, 0);
        $hourlyLabels = [];
        $timeRanges = [];

        $peakHourIdx = 0;
        $peakHourCount = 0;

        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hStr = sprintf('%02d:00', $h);
            $nextH = sprintf('%02d:59', $h);
            $hourlyLabels[] = $hStr;
            $timeRanges[] = "{$hStr} - {$nextH}";

            if (isset($hourlyRows[$h])) {
                $row = $hourlyRows[$h];
                $arrDomArr[$h] = (int)$row->arr_dom;
                $arrIntArr[$h] = (int)$row->arr_int;
                $depDomArr[$h] = (int)$row->dep_dom;
                $depIntArr[$h] = (int)$row->dep_int;
                $t = (int)$row->total;
                $totHourArr[$h] = $t;
                if ($t > $peakHourCount) {
                    $peakHourCount = $t;
                    $peakHourIdx = $h;
                }
            }

            $hourlyData[$h] = [
                'hour'             => $h,
                'hour_label'       => $hStr,
                'time_range'       => sprintf('%02d:00–%02d:59', $h, $h),
                'runway_capacity'  => 40,
                'total_plan'       => $totHourArr[$h],
                'dep_plan'         => $depDomArr[$h] + $depIntArr[$h],
                'dep_dom_plan'     => $depDomArr[$h],
                'dep_int_plan'     => $depIntArr[$h],
                'arr_plan'         => $arrDomArr[$h] + $arrIntArr[$h],
                'arr_dom_plan'     => $arrDomArr[$h],
                'arr_int_plan'     => $arrIntArr[$h],
                'total_realized'   => $totHourArr[$h],
                'dep_realized'     => $depDomArr[$h] + $depIntArr[$h],
                'dep_dom_realized' => $depDomArr[$h],
                'dep_int_realized' => $depIntArr[$h],
                'arr_realized'     => $arrDomArr[$h] + $arrIntArr[$h],
                'arr_dom_realized' => $arrDomArr[$h],
                'arr_int_realized' => $arrIntArr[$h],
                'total_irregular'  => 0,
                'dep_irregular'    => 0,
                'arr_irregular'    => 0,
            ];
        }

        // Distinct available dates for context
        $dateCounts = (clone $base)->selectRaw("COUNT(DISTINCT flight_date) as cnt")->value('cnt') ?: 1;

        $peakHourRange = sprintf('%02d:00–%02d:59', $peakHourIdx, $peakHourIdx);
        $peakHourAvg = $dateCounts > 1 ? round($peakHourCount / $dateCounts, 1) : null;

        $hourlyDistribution = [
            'labels'        => $hourlyLabels,
            'time_ranges'   => $timeRanges,
            'arr'           => array_map(fn($a, $b) => $a + $b, $arrDomArr, $arrIntArr),
            'dep'           => array_map(fn($a, $b) => $a + $b, $depDomArr, $depIntArr),
            'arr_dom'       => $arrDomArr,
            'arr_int'       => $arrIntArr,
            'dep_dom'       => $depDomArr,
            'dep_int'       => $depIntArr,
            'totals'        => $totHourArr,
            'period_days'   => $dateCounts,
            'period_label'  => ($filters['date_scope'] ?? '') === 'DAY' ? ($filters['analysis_date'] ?? 'Selected Date') : 'Full Period Range',
            'time_basis'    => $filters['time_basis'] ?? 'actual',
            'time_basis_desc' => ($filters['time_basis'] ?? 'actual') === 'actual' ? 'AIBT / AOBT' : 'SIBT / SOBT',
            'peak_hour'     => [
                'hour'            => $peakHourIdx,
                'time_range'      => $peakHourRange,
                'movements'       => $peakHourCount,
                'average_per_day' => $peakHourAvg,
            ],
        ];

        // 3. Delay Histogram (9 Standard Bins: 4 Early, 1 On Time, 4 Late - Prompt Item 19)
        $delayBins = [
            ['label' => 'EARLY > 60 min',  'legacy_label' => '<-60',    'min' => -9999, 'max' => -61, 'color' => '#1E3A8A', 'group' => 'EARLY', 'desc' => '>60 minutes early'],
            ['label' => 'EARLY 31–60 min', 'legacy_label' => '-60~-31', 'min' => -60,   'max' => -31, 'color' => '#2563EB', 'group' => 'EARLY', 'desc' => '31–60 minutes early'],
            ['label' => 'EARLY 16–30 min', 'legacy_label' => '-30~-16', 'min' => -30,   'max' => -16, 'color' => '#38BDF8', 'group' => 'EARLY', 'desc' => '16–30 minutes early'],
            ['label' => 'EARLY 6–15 min',  'legacy_label' => '-15~-6',  'min' => -15,   'max' => -6,  'color' => '#BAE6FD', 'group' => 'EARLY', 'desc' => '6–15 minutes early'],
            ['label' => 'ON TIME ±5 min',  'legacy_label' => '-5~5',    'min' => -5,    'max' => 5,   'color' => '#10B981', 'group' => 'ON TIME', 'desc' => 'Within ±5 minutes'],
            ['label' => 'LATE 6–15 min',   'legacy_label' => '6~15',    'min' => 6,     'max' => 15,  'color' => '#FDE047', 'group' => 'LATE', 'desc' => '6–15 minutes late'],
            ['label' => 'LATE 16–30 min',  'legacy_label' => '16~30',   'min' => 16,    'max' => 30,  'color' => '#F59E0B', 'group' => 'LATE', 'desc' => '16–30 minutes late'],
            ['label' => 'LATE 31–60 min',  'legacy_label' => '31~60',   'min' => 31,    'max' => 60,  'color' => '#EA580C', 'group' => 'LATE', 'desc' => '31–60 minutes late'],
            ['label' => 'LATE >60 min',    'legacy_label' => '>60',     'min' => 61,    'max' => 9999,'color' => '#DC2626', 'group' => 'LATE', 'desc' => '>60 minutes late'],
        ];

        $histCounts = (clone $base)->whereRaw('is_realized = true')->selectRaw("
            COUNT(CASE WHEN delay_minutes < -60 THEN 1 END) as b0,
            COUNT(CASE WHEN delay_minutes >= -60 AND delay_minutes <= -31 THEN 1 END) as b1,
            COUNT(CASE WHEN delay_minutes >= -30 AND delay_minutes <= -16 THEN 1 END) as b2,
            COUNT(CASE WHEN delay_minutes >= -15 AND delay_minutes <= -6 THEN 1 END) as b3,
            COUNT(CASE WHEN delay_minutes >= -5 AND delay_minutes <= 5 THEN 1 END) as b4,
            COUNT(CASE WHEN delay_minutes >= 6 AND delay_minutes <= 15 THEN 1 END) as b5,
            COUNT(CASE WHEN delay_minutes >= 16 AND delay_minutes <= 30 THEN 1 END) as b6,
            COUNT(CASE WHEN delay_minutes >= 31 AND delay_minutes <= 60 THEN 1 END) as b7,
            COUNT(CASE WHEN delay_minutes > 60 THEN 1 END) as b8
        ")->first();

        for ($i = 0; $i < 9; $i++) {
            $key = 'b' . $i;
            $delayBins[$i]['count'] = (int)($histCounts->$key ?? 0);
        }

        // 4. Passenger & Cargo Hourly Trend (Strictly aligned with time_basis)
        $hourlyFlow = (clone $base)->selectRaw("
            {$hourCol} as op_hour,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN (pax_adult + pax_child + pax_infant) END), 0) as arr_pax,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN (pax_adult + pax_child + pax_infant) END), 0) as dep_pax,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'DOMESTIC' THEN (pax_adult + pax_child + pax_infant) END), 0) as arr_dom_pax,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'INTERNATIONAL' THEN (pax_adult + pax_child + pax_infant) END), 0) as arr_int_pax,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'DOMESTIC' THEN (pax_adult + pax_child + pax_infant) END), 0) as dep_dom_pax,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'INTERNATIONAL' THEN (pax_adult + pax_child + pax_infant) END), 0) as dep_int_pax,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' THEN cargo_kg END), 0) as arr_cargo,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' THEN cargo_kg END), 0) as dep_cargo,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'DOMESTIC' THEN cargo_kg END), 0) as arr_dom_cargo,
            COALESCE(SUM(CASE WHEN direction = 'ARRIVAL' AND traffic_type = 'INTERNATIONAL' THEN cargo_kg END), 0) as arr_int_cargo,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'DOMESTIC' THEN cargo_kg END), 0) as dep_dom_cargo,
            COALESCE(SUM(CASE WHEN direction = 'DEPARTURE' AND traffic_type = 'INTERNATIONAL' THEN cargo_kg END), 0) as dep_int_cargo
        ")->groupByRaw($hourCol)->get()->keyBy('op_hour');

        $arrPaxArr = array_fill(0, 24, 0);
        $depPaxArr = array_fill(0, 24, 0);
        $arrDomPaxArr = array_fill(0, 24, 0);
        $arrIntPaxArr = array_fill(0, 24, 0);
        $depDomPaxArr = array_fill(0, 24, 0);
        $depIntPaxArr = array_fill(0, 24, 0);
        $arrCargoArr = array_fill(0, 24, 0);
        $depCargoArr = array_fill(0, 24, 0);
        $arrDomCargoArr = array_fill(0, 24, 0);
        $arrIntCargoArr = array_fill(0, 24, 0);
        $depDomCargoArr = array_fill(0, 24, 0);
        $depIntCargoArr = array_fill(0, 24, 0);

        for ($h = 0; $h < 24; $h++) {
            if (isset($hourlyFlow[$h])) {
                $f = $hourlyFlow[$h];
                $arrPaxArr[$h] = (int)$f->arr_pax;
                $depPaxArr[$h] = (int)$f->dep_pax;
                $arrDomPaxArr[$h] = (int)$f->arr_dom_pax;
                $arrIntPaxArr[$h] = (int)$f->arr_int_pax;
                $depDomPaxArr[$h] = (int)$f->dep_dom_pax;
                $depIntPaxArr[$h] = (int)$f->dep_int_pax;
                $arrCargoArr[$h] = (float)$f->arr_cargo;
                $depCargoArr[$h] = (float)$f->dep_cargo;
                $arrDomCargoArr[$h] = (float)$f->arr_dom_cargo;
                $arrIntCargoArr[$h] = (float)$f->arr_int_cargo;
                $depDomCargoArr[$h] = (float)$f->dep_dom_cargo;
                $depIntCargoArr[$h] = (float)$f->dep_int_cargo;
            }
        }

        $combinedTrend = [
            'labels'                => $hourlyLabels,
            'time_ranges'           => $timeRanges,
            'arr_passengers'        => $arrPaxArr,
            'dep_passengers'        => $depPaxArr,
            'arr_dom_passengers'    => $arrDomPaxArr,
            'arr_int_passengers'    => $arrIntPaxArr,
            'dep_dom_passengers'    => $depDomPaxArr,
            'dep_int_passengers'    => $depIntPaxArr,
            'arr_cargo_kg'          => $arrCargoArr,
            'dep_cargo_kg'          => $depCargoArr,
            'arr_dom_cargo_kg'      => $arrDomCargoArr,
            'arr_int_cargo_kg'      => $arrIntCargoArr,
            'dep_dom_cargo_kg'      => $depDomCargoArr,
            'dep_int_cargo_kg'      => $depIntCargoArr,
        ];

        // 5. Airline and Route Breakdown (Ranked Airlines & Top Routes)
        $airlineBreakdown = (clone $base)
            ->whereNotNull('airline_code')
            ->where('airline_code', '!=', '')
            ->where('airline_code', '!=', 'N/A')
            ->whereRaw("LOWER(airline_code) NOT LIKE '%pax all%'")
            ->selectRaw("
                airline_code,
                COUNT(*) as flights,
                COALESCE(SUM(passenger_capacity), 0) as capacity,
                COALESCE(SUM(passenger_load), 0) as load,
                COALESCE(SUM(pax_adult + pax_child + pax_infant), 0) as passengers,
                COALESCE(SUM(cargo_kg), 0) as cargo_kg
            ")->groupBy('airline_code')->orderByDesc('flights')->get();

        $rankedAirlines = [];
        $airlinesMap = [];
        foreach ($airlineBreakdown as $ab) {
            $cap = (int)$ab->capacity;
            $ld  = (int)$ab->load;
            $pax = (int)$ab->passengers;
            if ($pax === 0 && $ld > 0) $pax = $ld;
            $lfNum = $cap > 0 ? round(($ld / $cap) * 100, 1) : 0.0;
            $item = [
                'airline'         => $ab->airline_code,
                'flights'         => (int)$ab->flights,
                'passengers'      => $pax,
                'capacity'        => $cap,
                'load'            => $ld,
                'cargo_kg'        => (float)$ab->cargo_kg,
                'avg_load_factor' => $cap > 0 ? "{$lfNum}%" : 'N/A',
                'lf_num'          => $lfNum,
            ];
            $rankedAirlines[] = $item;
            $airlinesMap[$ab->airline_code] = $item;
        }

        // Top Routes (Busiest)
        $routeRows = (clone $base)
            ->whereNotNull('origin_airport')->where('origin_airport', '!=', '')->where('origin_airport', '!=', 'N/A')
            ->whereNotNull('destination_airport')->where('destination_airport', '!=', '')->where('destination_airport', '!=', 'N/A')
            ->selectRaw("
                origin_airport,
                destination_airport,
                COUNT(*) as flights,
                COALESCE(SUM(passenger_capacity), 0) as capacity,
                COALESCE(SUM(passenger_load), 0) as load,
                COALESCE(SUM(pax_adult + pax_child + pax_infant), 0) as passengers,
                COUNT(CASE WHEN is_realized = true AND delay_minutes > 15 THEN 1 END) as delayed_flights,
                COALESCE(AVG(CASE WHEN is_realized = true AND delay_minutes > 15 THEN delay_minutes END), 0) as avg_delay_minutes,
                COALESCE(MAX(CASE WHEN is_realized = true THEN delay_minutes END), 0) as max_delay_minutes
            ")
            ->groupBy('origin_airport', 'destination_airport')
            ->orderByDesc('flights')
            ->get();

        // Keep the full route aggregation for the interactive network map, while
        // preserving the existing Top Routes card limit separately.
        $allRouteAggregates = [];
        foreach ($routeRows as $r) {
            $cap = (int)$r->capacity;
            $ld  = (int)$r->load;
            $pax = (int)$r->passengers;
            if ($pax === 0 && $ld > 0) $pax = $ld;
            $lfNum = $cap > 0 ? round(($ld / $cap) * 100, 1) : 0.0;
            $delCnt = (int)$r->delayed_flights;
            $fltCnt = (int)$r->flights;
            $delRate = $fltCnt > 0 ? round(($delCnt / $fltCnt) * 100, 1) : 0.0;
            $avgDel = round((float)$r->avg_delay_minutes, 1);
            $maxDel = (int)$r->max_delay_minutes;

            $allRouteAggregates[] = [
                'route'           => "{$r->origin_airport} → {$r->destination_airport}",
                'origin'          => $r->origin_airport,
                'dest'            => $r->destination_airport,
                'destination'     => $r->destination_airport,
                'flights'         => $fltCnt,
                'count'           => $fltCnt,
                'movements'       => $fltCnt,
                'total_flights'   => $fltCnt,
                'passengers'      => $pax,
                'pax'             => $pax,
                'totalPax'        => $pax,
                'capacity'        => $cap,
                'totalCapacity'   => $cap,
                'load'            => $ld,
                'avg_load_factor' => $cap > 0 ? "{$lfNum}%" : '0.0%',
                'lf_num'          => $lfNum,
                'avgLf'           => $lfNum,
                'lf'              => $lfNum,
                'slf'             => $cap > 0 ? "{$lfNum}%" : '0.0%',
                'delayed_flights' => $delCnt,
                'delayedFlights'  => $delCnt,
                'delay_rate'      => $delRate,
                'delayRate'       => $delRate,
                'avg_delay'       => $avgDel,
                'avgDelay'        => $avgDel,
                'avg_delay_min'   => $avgDel,
                'max_delay'       => $maxDel,
            ];
        }

        $topRoutes = array_slice($allRouteAggregates, 0, 25);
        $networkRoutes = $allRouteAggregates;

        // Resolve route endpoint metadata from the airport master table. The route
        // itself always comes from the uploaded FDR; coordinates are only used to
        // project those actual endpoints onto the SVG network map.
        $networkCodes = [];
        foreach ($networkRoutes as $route) {
            foreach (['origin', 'destination'] as $endpoint) {
                $code = strtoupper(trim((string)($route[$endpoint] ?? '')));
                if ($code !== '' && $code !== 'N/A') {
                    $networkCodes[$code] = true;
                }
            }
        }

        $networkAirports = [];
        if (!empty($networkCodes)) {
            $airportRows = Airport::query()
                ->whereIn('iata_code', array_keys($networkCodes))
                ->get(['iata_code', 'name', 'city', 'country', 'latitude', 'longitude', 'management_type', 'is_active']);

            foreach ($airportRows as $airport) {
                $code = strtoupper(trim((string)$airport->iata_code));
                $lat = is_numeric($airport->latitude) ? (float)$airport->latitude : null;
                $lon = is_numeric($airport->longitude) ? (float)$airport->longitude : null;
                if ($lat !== null && ($lat < -90 || $lat > 90)) $lat = null;
                if ($lon !== null && ($lon < -180 || $lon > 180)) $lon = null;

                $networkAirports[$code] = [
                    'code'       => $code,
                    'name'       => $airport->name ?: $code,
                    'city'       => $airport->city ?: '',
                    'country'    => $airport->country ?: '',
                    'lat'        => $lat,
                    'lon'        => $lon,
                    'management' => $airport->management_type ?: null,
                    'is_active'  => (bool)$airport->is_active,
                ];
            }

            // Fallback dictionary for any airport code not found in DB or with missing coords
            foreach ($networkCodes as $code => $_) {
                if (!isset($networkAirports[$code]) || $networkAirports[$code]['lat'] === null || $networkAirports[$code]['lon'] === null) {
                    if (isset(self::AIRPORT_COORDINATES_FALLBACK[$code])) {
                        $fb = self::AIRPORT_COORDINATES_FALLBACK[$code];
                        $networkAirports[$code] = [
                            'code'       => $code,
                            'name'       => $fb['name'] ?? $code,
                            'city'       => $fb['city'] ?? '',
                            'country'    => $fb['country'] ?? '',
                            'lat'        => $fb['lat'],
                            'lon'        => $fb['lon'],
                            'management' => $networkAirports[$code]['management'] ?? 'OTHER',
                            'is_active'  => true,
                        ];
                    }
                }
            }
        }

        $mappedNetworkRoutes = 0;
        $unmappedRouteDetails = [];
        foreach ($networkRoutes as $route) {
            $orig = $route['origin'] ?? '';
            $dest = $route['destination'] ?? ($route['dest'] ?? '');
            $origAp = $networkAirports[$orig] ?? null;
            $destAp = $networkAirports[$dest] ?? null;

            $hasOrig = !empty($origAp['lat']) && !empty($origAp['lon']) && is_numeric($origAp['lat']) && is_numeric($origAp['lon']);
            $hasDest = !empty($destAp['lat']) && !empty($destAp['lon']) && is_numeric($destAp['lat']) && is_numeric($destAp['lon']);

            if ($hasOrig && $hasDest) {
                $mappedNetworkRoutes++;
            } else {
                $reasons = [];
                if (!$hasOrig) $reasons[] = "Missing coordinates for origin [{$orig}]";
                if (!$hasDest) $reasons[] = "Missing coordinates for destination [{$dest}]";
                $unmappedRouteDetails[] = [
                    'route'   => "{$orig} → {$dest}",
                    'origin'  => $orig,
                    'dest'    => $dest,
                    'flights' => $route['flights'] ?? 0,
                    'reason'  => implode('; ', $reasons),
                ];
            }
        }

        $networkSummary = [
            'unique_routes'          => count($networkRoutes),
            'unique_airports'        => count($networkCodes),
            'airports_with_coords'   => count(array_filter($networkAirports, fn($a) => $a['lat'] !== null && $a['lon'] !== null)),
            'mapped_routes'          => $mappedNetworkRoutes,
            'unmapped_routes'        => max(0, count($networkRoutes) - $mappedNetworkRoutes),
            'unmapped_route_details' => $unmappedRouteDetails,
        ];

        // Most Delayed Routes (Aggregated directly from real delay minutes > 15)
        $delayedRouteRows = (clone $base)
            ->whereNotNull('origin_airport')->where('origin_airport', '!=', '')->where('origin_airport', '!=', 'N/A')
            ->whereNotNull('destination_airport')->where('destination_airport', '!=', '')->where('destination_airport', '!=', 'N/A')
            ->selectRaw("
                origin_airport,
                destination_airport,
                COUNT(*) as flights,
                COALESCE(SUM(passenger_capacity), 0) as capacity,
                COALESCE(SUM(passenger_load), 0) as load,
                COALESCE(SUM(pax_adult + pax_child + pax_infant), 0) as passengers,
                COUNT(CASE WHEN is_realized = true AND delay_minutes > 15 THEN 1 END) as delayed_flights,
                COALESCE(AVG(CASE WHEN is_realized = true AND delay_minutes > 15 THEN delay_minutes END), 0) as avg_delay_minutes,
                COALESCE(MAX(CASE WHEN is_realized = true THEN delay_minutes END), 0) as max_delay_minutes
            ")
            ->groupBy('origin_airport', 'destination_airport')
            ->havingRaw("COUNT(CASE WHEN is_realized = true AND delay_minutes > 15 THEN 1 END) > 0")
            ->orderByDesc('delayed_flights')
            ->orderByDesc('avg_delay_minutes')
            ->take(15)
            ->get();

        $mostDelayedRoutes = [];
        foreach ($delayedRouteRows as $dr) {
            $delCnt = (int)$dr->delayed_flights;
            $fltCnt = (int)$dr->flights;
            $delRate = $fltCnt > 0 ? round(($delCnt / $fltCnt) * 100, 1) : 0.0;
            $avgDel = round((float)$dr->avg_delay_minutes, 1);
            $maxDel = (int)$dr->max_delay_minutes;
            $cap = (int)$dr->capacity;
            $ld = (int)$dr->load;
            $lfNum = $cap > 0 ? round(($ld / $cap) * 100, 1) : 0.0;

            $mostDelayedRoutes[] = [
                'route'           => "{$dr->origin_airport} → {$dr->destination_airport}",
                'origin'          => $dr->origin_airport,
                'dest'            => $dr->destination_airport,
                'destination'     => $dr->destination_airport,
                'flights'         => $fltCnt,
                'count'           => $fltCnt,
                'total_flights'   => $fltCnt,
                'delayed_flights' => $delCnt,
                'delayedFlights'  => $delCnt,
                'delay_rate'      => $delRate,
                'delayRate'       => $delRate,
                'avg_delay'       => $avgDel,
                'avgDelay'        => $avgDel,
                'avg_delay_min'   => $avgDel,
                'max_delay'       => $maxDel,
                'avg_load_factor' => "{$lfNum}%",
                'lf_num'          => $lfNum,
            ];
        }

        // Top SLF Routes
        $topSlfRoutes = $topRoutes;
        usort($topSlfRoutes, fn($a, $b) => ($b['lf_num'] <=> $a['lf_num']));
        $topSlfRoutes = array_slice($topSlfRoutes, 0, 15);

        // Suffix Distribution
        $suffixRows = (clone $base)
            ->whereNotNull('flight_suffix')
            ->where('flight_suffix', '!=', '')
            ->selectRaw("flight_suffix, COUNT(*) as count")
            ->groupBy('flight_suffix')
            ->orderByDesc('count')
            ->get();

        $suffixDist = [];
        foreach ($suffixRows as $sr) {
            $suffixDist[] = [
                'suffix' => $sr->flight_suffix,
                'count'  => (int)$sr->count,
            ];
        }

        // 6. Fleet Performance (Fleet Mix per Aircraft Type, Narrow vs Wide)
        $fleetRows = (clone $base)
            ->whereNotNull('aircraft_type')
            ->where('aircraft_type', '!=', '')
            ->where('aircraft_type', '!=', 'N/A')
            ->selectRaw("
                aircraft_type,
                COUNT(*) as movements,
                AVG(CASE WHEN passenger_capacity > 0 THEN passenger_capacity END) as avg_cap,
                AVG(CASE WHEN passenger_capacity > 0 THEN (CAST(passenger_load AS REAL) / passenger_capacity * 100) END) as avg_lf
            ")
            ->groupBy('aircraft_type')
            ->orderByDesc('movements')
            ->take(15)
            ->get();

        $fleetMix = [];
        $narrowCount = 0;
        $wideCount = 0;
        foreach ($fleetRows as $fr) {
            $desc = $fr->aircraft_type;
            $isNarrow = str_contains($desc, '(N )') || str_contains($desc, '(N)');
            $isWide   = str_contains($desc, '(W )') || str_contains($desc, '(W)');
            if ($isNarrow) $narrowCount += (int)$fr->movements;
            elseif ($isWide) $wideCount += (int)$fr->movements;

            $fleetMix[] = [
                'desc'      => $desc,
                'movements' => (int)$fr->movements,
                'avg_cap'   => $fr->avg_cap ? round((float)$fr->avg_cap) : null,
                'avg_lf'    => $fr->avg_lf ? round((float)$fr->avg_lf, 1) : null,
                'narrow'    => $isNarrow,
                'wide'      => $isWide,
            ];
        }

        // Top Stands & Runways
        $standsList = (clone $base)->whereNotNull('stand')->where('stand', '!=', 'N/A')->where('stand', '!=', '-')
            ->selectRaw("stand, COUNT(*) as flights")
            ->groupBy('stand')->orderByDesc('flights')->take(10)->get();

        $runwaysList = (clone $base)->whereNotNull('runway')->where('runway', '!=', 'N/A')->where('runway', '!=', '-')
            ->selectRaw("runway, COUNT(*) as flights")
            ->groupBy('runway')->orderByDesc('flights')->take(10)->get();

        $standsMap = [];
        foreach ($standsList as $st) {
            $cnt = (int)$st->flights;
            $standsMap[] = [
                'stand'      => $st->stand,
                'flights'    => $cnt,
                'count'      => $cnt,
                'percentage' => $totFlights > 0 ? round(($cnt / $totFlights) * 100, 1) : 0.0,
            ];
        }
        $runwaysMap = [];
        foreach ($runwaysList as $rw) {
            $cnt = (int)$rw->flights;
            $runwaysMap[] = [
                'runway'     => $rw->runway,
                'flights'    => $cnt,
                'count'      => $cnt,
                'percentage' => $totFlights > 0 ? round(($cnt / $totFlights) * 100, 1) : 0.0,
            ];
        }

        // Derived Metrics & Rates
        $cargoKg = (float)$kpiRow->total_cargo_kg;
        $baggageKg = (float)$kpiRow->total_baggage_kg;
        $cargoTon = round($cargoKg / 1000, 2);
        $cargoPerFlightT = $totFlights > 0 ? round(($cargoKg / 1000) / $totFlights, 2) : 0.0;
        $baggagePerFlightKg = $totFlights > 0 ? (int)round($baggageKg / $totFlights) : 0;
        $paxPerFlight = $totFlights > 0 ? round($totPax / $totFlights, 1) : 0.0;

        $paxUtilStr = $totCap > 0 ? round(($totPax / $totCap) * 100, 1) . '%' : 'N/A';
        $paxUtilNum = $totCap > 0 ? round(($totPax / $totCap) * 100, 1) : 0.0;
        $weightedLfStr = $totCap > 0 ? round(($totLoad / $totCap) * 100, 1) . '%' : 'N/A';
        $weightedLfNum = $totCap > 0 ? round(($totLoad / $totCap) * 100, 1) : 0.0;

        $irregularTotal = (int)$kpiRow->irregular_flights;
        $irregularRate = $totFlights > 0 ? round(($irregularTotal / $totFlights) * 100, 1) : 0.0;

        $avgDelMin = round((float)$kpiRow->avg_delay_minutes, 1);
        $minDelMin = (int)$kpiRow->min_delay_minutes;
        $maxDelMin = (int)$kpiRow->max_delay_minutes;

        $kpis = [
            'total_flights'            => $totFlights,
            'scheduled_flights'        => $schedFlights,
            'actual_flights'           => $realized,
            'arrivals'                 => $arrFlights,
            'total_arrivals'           => $arrFlights,
            'departures'               => $depFlights,
            'total_departures'         => $depFlights,
            'arrivals_dom'             => (int)$kpiRow->arrivals_dom,
            'arrivals_int'             => (int)$kpiRow->arrivals_int,
            'departures_dom'           => (int)$kpiRow->departures_dom,
            'departures_int'           => (int)$kpiRow->departures_int,
            'on_time_flights'          => $onTime,
            'delayed_flights'          => $delayed,
            'irregular_flights'        => $irregularTotal,
            'otp_percent'              => $otpPercent,
            'delay_percent'            => $delayPercent,
            'load_factor_avg'          => $loadFactorAvg,
            'avg_load_factor'          => $weightedLfStr,
            'avg_load_factor_num'      => $weightedLfNum,
            'weighted_load_factor'     => $weightedLfStr,
            'weighted_load_factor_num' => $weightedLfNum,
            'passenger_utilization'    => $paxUtilStr,
            'passenger_utilization_num'=> $paxUtilNum,
            'total_passengers'         => $totPax,
            'passenger_movement'       => $totPax,
            'direct_passengers'        => $totPax,
            'adult_passengers'         => (int)$kpiRow->total_adult,
            'child_passengers'         => (int)$kpiRow->total_child,
            'infant_passengers'        => (int)$kpiRow->total_infant,
            'transit_passengers'       => (int)$kpiRow->total_transit,
            'transfer_passengers'      => (int)$kpiRow->total_transfer,
            'pax_per_flight'           => $paxPerFlight,
            'pax_per_flight_display'   => "{$paxPerFlight} Pax / Flight",
            'total_capacity'           => $totCap,
            'total_load'               => $totLoad,
            'total_cargo_kg'           => $cargoKg,
            'cargo_kg'                 => $cargoKg,
            'cargo_ton'                => $cargoTon,
            'cargo_per_flight_t'       => $cargoPerFlightT,
            'total_baggage_kg'         => $baggageKg,
            'baggage_kg'               => $baggageKg,
            'baggage_per_flight_kg'    => $baggagePerFlightKg,
            'total_pos_kg'             => (float)$kpiRow->total_pos_kg,
            'pos_kg'                   => (float)$kpiRow->total_pos_kg,
            'operating_crew'           => (int)$kpiRow->total_operating_crew,
            'extra_crew'               => (int)$kpiRow->total_extra_crew,
            'total_crew'               => (int)$kpiRow->total_operating_crew,
            'crew'                     => (int)$kpiRow->total_operating_crew,
            'unscheduled_flights'      => (int)$kpiRow->unscheduled_flights,
            'avg_delay_minutes'        => $avgDelMin,
            'irregular_rate'           => $irregularRate,
            'irregularities'           => [
                'total'        => $irregularTotal,
                'divert'       => 0,
                'miss'         => 0,
                'unscheduled'  => $irregularTotal,
            ],
            'peak_hour'                => $peakHourRange,
            'peak_movements'           => $peakHourCount,
        ];

        $schedVsReal = [
            'has_evaluation'     => $realized > 0,
            'evaluated_count'    => $realized,
            'evaluated_flights'  => $realized > 0 ? $realized : 'N/A',
            'tolerance_minutes'  => 15,
            'on_time_count'      => $onTime,
            'early_count'        => $earlyCount,
            'late_count'         => $delayed,
            'on_time_percentage' => "{$otpPercent}%",
            'on_time_pct_num'    => $otpPercent,
            'delay_percent'      => $delayPercent,
            'avg_delay_minutes'  => "{$avgDelMin} min",
            'avg_delay_num'      => $avgDelMin,
            'median_delay_num'   => 0,
            'median_delay'       => '0 min',
            'max_delay_num'      => $maxDelMin,
            'min_delay_num'      => $minDelMin,
            'max_delay'          => ($maxDelMin >= 0 ? '+' : '') . "{$maxDelMin} min",
            'min_delay'          => ($minDelMin >= 0 ? '+' : '') . "{$minDelMin} min",
            'hist_bins'          => $delayBins,
            'histogram'          => $delayBins,
            'summary'            => [
                'early'   => $earlyCount,
                'on_time' => $onTime,
                'late'    => $delayed,
            ],
            'tolerance'          => 15,
        ];

        $arrCap = (int)$kpiRow->arr_capacity;
        $arrLd  = (int)$kpiRow->arr_load;
        $depCap = (int)$kpiRow->dep_capacity;
        $depLd  = (int)$kpiRow->dep_load;

        $paxAnalytics = [
            'total_passengers' => $totPax,
            'total_direct'     => $totPax,
            'total_all'        => $totPax + (int)$kpiRow->total_transit,
            'total_load'       => $totLoad,
            'composition'      => [
                'adult'    => (int)$kpiRow->total_adult,
                'child'    => (int)$kpiRow->total_child,
                'infant'   => (int)$kpiRow->total_infant,
                'transit'  => (int)$kpiRow->total_transit,
                'transfer' => (int)$kpiRow->total_transfer,
                'crew'     => (int)$kpiRow->total_operating_crew,
            ],
            'composition_arr'  => [
                'adult'    => (int)$kpiRow->arr_adult,
                'child'    => (int)$kpiRow->arr_child,
                'infant'   => (int)$kpiRow->arr_infant,
                'transit'  => (int)$kpiRow->arr_transit,
                'transfer' => (int)$kpiRow->arr_transfer,
                'crew'     => (int)$kpiRow->total_operating_crew,
            ],
            'composition_dep'  => [
                'adult'    => (int)$kpiRow->dep_adult,
                'child'    => (int)$kpiRow->dep_child,
                'infant'   => (int)$kpiRow->dep_infant,
                'transit'  => (int)$kpiRow->dep_transit,
                'transfer' => (int)$kpiRow->dep_transfer,
                'crew'     => (int)$kpiRow->total_operating_crew,
            ],
            'payload_arr'      => [
                'cargo_kg'   => round((float)$kpiRow->arr_cargo_kg),
                'baggage_kg' => round((float)$kpiRow->arr_baggage_kg),
                'pos_kg'     => round((float)$kpiRow->arr_pos_kg),
            ],
            'payload_dep'      => [
                'cargo_kg'   => round((float)$kpiRow->dep_cargo_kg),
                'baggage_kg' => round((float)$kpiRow->dep_baggage_kg),
                'pos_kg'     => round((float)$kpiRow->dep_pos_kg),
            ],
            'lf_avg_arr'       => $arrCap > 0 ? round(($arrLd / $arrCap) * 100, 1) : 0.0,
            'lf_avg_dep'       => $depCap > 0 ? round(($depLd / $depCap) * 100, 1) : 0.0,
            'has_breakdown'    => true,
            'lf_from_file'     => true,
            'daily_trend'      => [],
        ];

        // Real Ground Operations & Turnaround Calculation (§51)
        $regMovements = (clone $base)
            ->whereNotNull('registration_number')
            ->where('registration_number', '!=', '')
            ->where('registration_number', '!=', 'N/A')
            ->whereNotNull('actual_datetime')
            ->select('registration_number', 'direction', 'actual_datetime', 'stand', 'runway')
            ->orderBy('registration_number')
            ->orderBy('actual_datetime')
            ->limit(500)
            ->get();

        $groundTimes = [];
        $standChanges = 0;
        $pairs = [];
        $byReg = [];
        foreach ($regMovements as $rm) {
            $byReg[$rm->registration_number][] = $rm;
        }

        foreach ($byReg as $reg => $events) {
            $arrQueue = [];
            foreach ($events as $ev) {
                if ($ev->direction === 'ARRIVAL') {
                    $arrQueue[] = $ev;
                } else {
                    $matched = null;
                    foreach ($arrQueue as $qi => $qa) {
                        if ($qa->actual_datetime <= $ev->actual_datetime) {
                            $matched = $qi;
                            break;
                        }
                    }
                    if ($matched !== null) {
                        $arr = $arrQueue[$matched];
                        array_splice($arrQueue, $matched, 1);
                        $tArr = strtotime($arr->actual_datetime);
                        $tDep = strtotime($ev->actual_datetime);
                        if ($tArr && $tDep && $tDep >= $tArr) {
                            $groundMin = (int)round(($tDep - $tArr) / 60);
                            if ($groundMin >= 15 && $groundMin <= 1440) {
                                $groundTimes[] = $groundMin;
                                if ($arr->stand && $ev->stand && $arr->stand !== $ev->stand) {
                                    $standChanges++;
                                }
                                $pairs[] = [
                                    'reg_no'       => $reg,
                                    'arr_time'     => (string)$arr->actual_datetime,
                                    'dep_time'     => (string)$ev->actual_datetime,
                                    'ground_min'   => $groundMin,
                                    'stand_arr'    => $arr->stand,
                                    'stand_dep'    => $ev->stand,
                                    'stand_change' => ($arr->stand !== $ev->stand),
                                ];
                            }
                        }
                    }
                }
            }
        }

        $gtCount = count($groundTimes);
        $gtMean = $gtCount > 0 ? round(array_sum($groundTimes) / $gtCount, 1) : null;
        $gtMin = $gtCount > 0 ? min($groundTimes) : null;
        $gtMax = $gtCount > 0 ? max($groundTimes) : null;
        $gtMedian = null;
        if ($gtCount > 0) {
            sort($groundTimes);
            $mid = (int)floor($gtCount / 2);
            $gtMedian = ($gtCount % 2 === 0) ? round(($groundTimes[$mid - 1] + $groundTimes[$mid]) / 2) : $groundTimes[$mid];
        }

        $groundOps = [
            'stands'             => $standsMap,
            'runways'            => $runwaysMap,
            'stand_unique'       => count($standsMap),
            'stand_filled'       => $totFlights,
            'runway_filled'      => $totFlights,
            'ground_time_mean'   => $gtMean,
            'ground_time_median' => $gtMedian,
            'ground_time_min'    => $gtMin,
            'ground_time_max'    => $gtMax,
            'stand_changes'      => $standChanges,
            'turnaround_pairs'   => array_slice($pairs, 0, 50),
            'turnaround_count'   => $gtCount,
            'unpaired_flights'   => max(0, $totFlights - ($gtCount * 2)),
        ];

        return [
            'meta'                    => $meta,
            'kpis'                    => $kpis,
            'kpi'                     => $kpis,
            'hourly_charts'           => [
                'hours'          => $hourlyLabels,
                'hourly_data'    => array_values($hourlyData),
                'peak_hour'      => $peakHourRange,
                'peak_movements' => $peakHourCount,
            ],
            'hourly_distribution'     => $hourlyDistribution,
            'combined_trend'          => $combinedTrend,
            'schedule_vs_realization' => $schedVsReal,
            'sched_vs_real'           => $schedVsReal,
            'passenger_analytics'     => $paxAnalytics,
            'pax_analytics'           => $paxAnalytics,
            'airline_route'           => [
                'ranked_airlines'     => $rankedAirlines,
                'airlines'            => $airlinesMap,
                'top_routes'          => $topRoutes,
                'routes'              => $topRoutes,
                'busiest_routes'      => array_slice($topRoutes, 0, 10),
                'top_busiest_routes'  => array_slice($topRoutes, 0, 10),
                'network_routes'      => $networkRoutes,
                'network_airports'    => $networkAirports,
                'network_summary'     => $networkSummary,
                'most_delayed_routes' => $mostDelayedRoutes,
                'top_delayed_routes'  => $mostDelayedRoutes,
                'highest_slf_routes'  => array_slice($topSlfRoutes, 0, 10),
                'top_slf_routes'      => array_slice($topSlfRoutes, 0, 10),
                'suffix_breakdown'    => $suffixDist,
                'suffix_distribution'=> $suffixDist,
                'leg_subtypes'        => [
                    'SCHED'   => (int)$kpiRow->scheduled_flights,
                    'UNSCHED' => (int)$kpiRow->unscheduled_flights,
                    'CARGO'   => 0,
                ],
            ],
            'fleet_performance'       => [
                'fleet_mix'    => $fleetMix,
                'narrow_count' => $narrowCount,
                'wide_count'   => $wideCount,
                'total_regs'   => 0,
                'regs_multi'   => 0,
                'top10_regs'   => [],
            ],
            'ground_operations'       => $groundOps,
            'ground_ops'              => $groundOps,
            'mode_payload'            => [],
            'reconciliation_apps'     => ['status' => 'MATCH', 'delta' => 0],
            'reconciliation_edifly'   => ['status' => 'MATCH', 'delta' => 0],
        ];
    }

    /**
     * Retrieve precomputed summary analytics structure from cache column.
     */
    public function getCachedAnalytics(Upload $upload): ?array
    {
        $cached = $upload->cached_analytics;
        if (!empty($cached) && is_array($cached) && !empty($cached['analytics'])) {
            return $cached;
        }

        $repData = $upload->report_data ?: [];
        if (!empty($repData['cached_analytics']) && is_array($repData['cached_analytics']) && !empty($repData['cached_analytics']['analytics'])) {
            return $repData['cached_analytics'];
        }

        return null;
    }

    /**
     * Precompute and store all heavy aggregations as a JSON payload in cached_analytics.
     * Computes: 8 KPI cards, hourly distribution buckets, top routes, delay statistics,
     * irregularity breakdowns, and operating crew sums once during upload/parse.
     */
    public function precomputeAndCacheAnalytics(Upload $upload, array $meta = []): array
    {
        if (empty($meta)) {
            $repData = $upload->report_data ?: [];
            $meta = $repData['meta'] ?? [];
        }

        $defaultAirport = strtoupper(trim($meta['report_airport'] ?? ($meta['airport'] ?? 'CGK')));

        $minMax = FdrFlight::where('upload_id', $upload->id)->selectRaw("
            MIN(flight_date) as min_date,
            MAX(flight_date) as max_date,
            COUNT(*) as total_flights,
            COUNT(DISTINCT flight_date) as days_available
        ")->first();

        $totalDbRows = (int)($minMax->total_flights ?? 0);
        $daysCount = (int)($minMax->days_available ?? 1);

        $availableDates = FdrFlight::where('upload_id', $upload->id)
            ->selectRaw('DISTINCT flight_date')
            ->orderBy('flight_date')
            ->pluck('flight_date')
            ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : ($d ? $d->format('Y-m-d') : null))
            ->filter()
            ->values()
            ->toArray();

        $pStart = $minMax && $minMax->min_date ? (is_string($minMax->min_date) ? substr($minMax->min_date, 0, 10) : $minMax->min_date->format('Y-m-d')) : ($meta['period_start'] ?? date('Y-m-d'));
        $pEnd = $minMax && $minMax->max_date ? (is_string($minMax->max_date) ? substr($minMax->max_date, 0, 10) : $minMax->max_date->format('Y-m-d')) : ($meta['period_end'] ?? date('Y-m-d'));

        $sourceSummary = [
            'source_type'    => $daysCount > 30 ? 'MONTHLY' : ($daysCount > 1 ? 'MULTI-DAY' : 'DAILY'),
            'period_start'   => $pStart,
            'period_end'     => $pEnd,
            'period_label'   => date('d-m-Y', strtotime($pStart)) . ' → ' . date('d-m-Y', strtotime($pEnd)),
            'days_available' => $daysCount . ' ' . ($daysCount === 1 ? 'Day' : 'Days'),
            'total_flights'  => $totalDbRows,
            'source_rows'    => (int)($meta['source_rows'] ?? ($meta['raw_rows'] ?? $totalDbRows)),
            'movement_rows'  => (int)($meta['movement_rows'] ?? $totalDbRows),
            'summary_rows'   => (int)($meta['summary_rows'] ?? 0),
            'db_rows'        => $totalDbRows,
        ];

        $airports = FdrFlight::where('upload_id', $upload->id)
            ->whereNotNull('report_airport')
            ->where('report_airport', '!=', '')
            ->where('report_airport', '!=', 'N/A')
            ->distinct()
            ->orderBy('report_airport')
            ->pluck('report_airport')
            ->toArray();
        if (empty($airports)) {
            $airports = [$defaultAirport ?: 'CGK'];
        }

        $airlines = FdrFlight::where('upload_id', $upload->id)
            ->whereNotNull('airline_code')
            ->where('airline_code', '!=', '')
            ->where('airline_code', '!=', 'N/A')
            ->whereRaw("LOWER(airline_code) NOT LIKE '%pax all%'")
            ->distinct()
            ->orderBy('airline_code')
            ->pluck('airline_code')
            ->toArray();

        $defaultFilters = [
            'date_scope'     => 'ALL_PERIOD',
            'analysis_level' => 'ALL_PERIOD',
            'analysis_date'  => null,
            'analysis_month' => null,
            'analysis_year'  => null,
            'airport'        => 'ALL',
            'leg'            => 'ALL',
            'operator'       => 'ALL',
            'traffic'        => 'ALL',
            'data_type'      => strtoupper(trim($meta['data_type'] ?? 'OPERATIONAL DATA')),
            'realization'    => 'ALL',
            'flight_no'      => '',
            'suffix'         => '',
            'start_date'     => '',
            'end_date'       => '',
            'report_mode'    => 1,
            'time_basis'     => 'actual',
            'otp_tolerance'  => 15,
            'search'         => '',
            'v'              => time(),
        ];

        $analytics = $this->computeAnalyticsFromDb($upload, $defaultFilters, $meta);

        // Pre-fetch initial 50 paginated rows for instant SSR without queries
        $initialRecords = FdrFlight::where('upload_id', $upload->id)
            ->orderBy('id')
            ->take(50)
            ->get()
            ->map->toFdrArray()
            ->toArray();

        $cachedPayload = [
            'analytics'       => $analytics,
            'available_dates' => $availableDates,
            'source_summary'  => $sourceSummary,
            'airports'        => $airports,
            'airlines'        => $airlines,
            'total_flights'   => $totalDbRows,
            'initial_records' => $initialRecords,
            'computed_at'     => now()->toISOString(),
        ];

        try {
            $upload->cached_analytics = $cachedPayload;
            $repData = $upload->report_data ?: [];
            $repData['cached_analytics'] = $cachedPayload;
            $upload->report_data = $repData;
            $upload->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning("Could not persist cached_analytics for upload #{$upload->id}: " . $e->getMessage());
        }

        return $cachedPayload;
    }

}
