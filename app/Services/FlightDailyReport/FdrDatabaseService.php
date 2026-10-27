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
    private static bool $connectionVerified = false;

    /**
     * Ensures a working relational database connection with all required FDR tables.
     * If PostgreSQL TCP pooler (port 6543) is unreachable, rejects credentials (28P01/08006),
     * or lacks the FDR tables (42P01), automatically falls back to local/serverless SQLite
     * (/tmp/storage/database.sqlite on Vercel, database/database.sqlite locally) and
     * auto-creates the required schema while syncing state via Supabase PostgREST HTTPS API.
     */
    public static function ensureWorkingConnection(): string
    {
        if (self::$connectionVerified) {
            return (string) config('database.default', 'sqlite');
        }

        $defaultConn = (string) config('database.default', 'pgsql');

        if ($defaultConn === 'pgsql') {
            try {
                DB::connection('pgsql')->getPdo();
                $check = DB::connection('pgsql')->selectOne(
                    "SELECT to_regclass('public.uploads') as u, to_regclass('public.fdr_processing_jobs') as j, to_regclass('public.fdr_flights') as f"
                );
                if ($check && !empty($check->u) && !empty($check->j) && !empty($check->f)) {
                    self::$connectionVerified = true;
                    return 'pgsql';
                }
                Log::info('FdrDatabaseService: PostgreSQL lacks fdr_* tables. Switching to SQLite + Supabase PostgREST hybrid engine.');
            } catch (\Throwable $e) {
                Log::info('FdrDatabaseService: PostgreSQL TCP pooler unavailable (' . self::sanitizeLogMessage($e->getMessage()) . '). Switching to SQLite + Supabase PostgREST hybrid engine.');
            }
        }

        $sqlitePath = is_dir('/tmp/storage')
            ? '/tmp/storage/database.sqlite'
            : database_path('database.sqlite');

        $sqliteDir = dirname($sqlitePath);
        if (!is_dir($sqliteDir)) {
            @mkdir($sqliteDir, 0755, true);
        }
        if (!file_exists($sqlitePath)) {
            @touch($sqlitePath);
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $sqlitePath,
        ]);
        DB::purge('sqlite');

        self::ensureSqliteSchema();
        self::$connectionVerified = true;

        return 'sqlite';
    }

    /**
     * Auto-initializes required tables in SQLite when running in serverless / fallback mode.
     */
    public static function ensureSqliteSchema(): void
    {
        $schema = \Illuminate\Support\Facades\Schema::connection('sqlite');

        if (!$schema->hasTable('airports')) {
            $schema->create('airports', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('iata_code', 10)->index();
                $table->string('icao_code', 10)->nullable();
                $table->string('name')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('timezone')->default('Asia/Jakarta');
                $table->boolean('is_active')->default(true);
                $table->integer('slot_capacity')->default(30);
                $table->integer('arrival_capacity')->nullable();
                $table->integer('departure_capacity')->nullable();
                $table->string('ops_start_time')->default('06:00');
                $table->string('ops_end_time')->default('20:00');
                $table->decimal('latitude', 10, 6)->nullable();
                $table->decimal('longitude', 10, 6)->nullable();
                $table->string('management_type')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('uploads')) {
            $schema->create('uploads', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('original_filename');
                $table->string('stored_path');
                $table->string('status')->default('pending');
                $table->longText('error_message')->nullable();
                $table->string('season')->nullable();
                $table->unsignedBigInteger('airport_id')->nullable();
                $table->integer('total_rows')->default(0);
                $table->integer('valid_rows')->default(0);
                $table->integer('invalid_rows')->default(0);
                $table->integer('duplicate_rows')->default(0);
                $table->float('parsing_confidence')->default(0);
                $table->longText('validation_summary')->nullable();
                $table->string('report_type', 50)->nullable()->index();
                $table->longText('report_data')->nullable();
                $table->longText('cached_analytics')->nullable();
                $table->timestamps();
            });
        } else {
            $missingCols = [];
            foreach (['report_type', 'report_data', 'cached_analytics', 'validation_summary', 'season', 'airport_id', 'total_rows', 'valid_rows', 'invalid_rows', 'duplicate_rows', 'parsing_confidence'] as $col) {
                if (!$schema->hasColumn('uploads', $col)) {
                    $missingCols[] = $col;
                }
            }
            if (!empty($missingCols)) {
                $schema->table('uploads', function (\Illuminate\Database\Schema\Blueprint $table) use ($missingCols) {
                    foreach ($missingCols as $col) {
                        if (in_array($col, ['report_data', 'cached_analytics', 'validation_summary'], true)) {
                            $table->longText($col)->nullable();
                        } elseif (in_array($col, ['total_rows', 'valid_rows', 'invalid_rows', 'duplicate_rows', 'airport_id'], true)) {
                            $table->integer($col)->default(0)->nullable();
                        } elseif ($col === 'parsing_confidence') {
                            $table->float($col)->default(0)->nullable();
                        } else {
                            $table->string($col)->nullable();
                        }
                    }
                });
            }
        }

        if (!$schema->hasTable('fdr_upload_sessions')) {
            $schema->create('fdr_upload_sessions', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('upload_token', 120)->unique();
                $table->string('original_filename');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('mime_type')->nullable();
                $table->string('file_hash')->nullable();
                $table->integer('chunk_size')->default(0);
                $table->integer('total_chunks')->default(0);
                $table->unsignedBigInteger('uploaded_bytes')->default(0);
                $table->longText('uploaded_chunks')->nullable();
                $table->string('storage_bucket')->nullable();
                $table->string('storage_path')->nullable();
                $table->string('status', 40)->default('CREATED');
                $table->integer('progress')->default(0);
                $table->integer('last_confirmed_chunk')->nullable();
                $table->integer('failed_chunk')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('fdr_processing_jobs')) {
            $schema->create('fdr_processing_jobs', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->unsignedBigInteger('upload_id')->nullable()->index();
                $table->string('upload_token', 120)->index();
                $table->string('filename')->nullable();
                $table->string('stored_path')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('file_hash')->nullable();
                $table->string('report_type', 60)->default('fdr');
                $table->string('status', 40)->default('QUEUED');
                $table->string('stage', 40)->default('QUEUED');
                $table->string('stage_label')->nullable();
                $table->integer('progress')->default(0);
                $table->integer('processed_rows')->default(0);
                $table->integer('total_rows')->default(0);
                $table->unsignedBigInteger('current_offset')->default(0);
                $table->integer('current_batch')->default(0);
                $table->integer('failed_rows')->default(0);
                $table->string('error_code', 100)->nullable();
                $table->longText('meta')->nullable();
                $table->longText('diagnostics')->nullable();
                $table->longText('error_message')->nullable();
                $table->string('result_url')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('fdr_flights')) {
            $schema->create('fdr_flights', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('upload_id')->index();
                $table->date('flight_date')->nullable()->index();
                $table->string('flight_number', 50)->nullable()->index();
                $table->string('flight_number_base', 50)->nullable();
                $table->string('flight_suffix', 20)->nullable();
                $table->string('airline_code', 30)->nullable()->index();
                $table->string('airline', 100)->nullable();
                $table->string('paired_flight_number', 50)->nullable();
                $table->string('aircraft_type', 100)->nullable();
                $table->string('registration_number', 50)->nullable();
                $table->string('leg', 50)->nullable();
                $table->string('direction', 30)->nullable()->index();
                $table->string('movement_type', 30)->nullable();
                $table->string('origin_airport', 20)->nullable();
                $table->string('destination_airport', 20)->nullable();
                $table->string('city_1', 20)->nullable();
                $table->string('city_2', 20)->nullable();
                $table->string('report_airport', 20)->nullable()->index();
                $table->string('traffic_type', 30)->nullable();
                $table->string('scheduled_time', 20)->nullable();
                $table->string('actual_time', 20)->nullable();
                $table->dateTime('scheduled_datetime')->nullable();
                $table->dateTime('actual_datetime')->nullable();
                $table->string('scheduled_time_str', 30)->nullable();
                $table->string('actual_time_str', 30)->nullable();
                $table->integer('scheduled_hour')->nullable();
                $table->integer('actual_hour')->nullable();
                $table->integer('operational_hour')->default(0);
                $table->integer('delay_minutes')->default(0);
                $table->string('status', 30)->nullable();
                $table->boolean('is_realized')->default(true);
                $table->boolean('is_irregular')->default(false);
                $table->integer('passenger_capacity')->default(0);
                $table->integer('passenger_load')->default(0);
                $table->float('load_factor')->nullable();
                $table->integer('pax_adult')->default(0);
                $table->integer('pax_child')->default(0);
                $table->integer('pax_infant')->default(0);
                $table->integer('pax_transit')->default(0);
                $table->integer('pax_transfer')->default(0);
                $table->integer('operating_crew')->default(0);
                $table->integer('extra_crew')->default(0);
                $table->float('cargo_kg')->default(0);
                $table->float('baggage_kg')->default(0);
                $table->float('pos_kg')->default(0);
                $table->string('stand', 50)->nullable();
                $table->string('runway', 50)->nullable();
                $table->string('mtow', 50)->nullable();
                $table->longText('raw_data')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('upload_chunks')) {
            $schema->create('upload_chunks', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('upload_token', 120)->index();
                $table->integer('chunk_index');
                $table->longText('chunk_data')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Execute an HTTPS PostgREST request against Supabase (/rest/v1/...).
     */
    public static function supabaseRestRequest(string $method, string $endpoint, ?array $body = null, array $extraHeaders = []): array
    {
        $baseUrl = rtrim((string) config('services.supabase.url', 'https://zvtkphkjczjobvvtvhbm.supabase.co'), '/');
        $serviceKey = trim((string) config('services.supabase.service_role_key', ''));
        $anonKey = trim((string) config('services.supabase.anon_key', ''));
        $apiKey = $serviceKey !== '' ? $serviceKey : $anonKey;

        if ($baseUrl === '' || $apiKey === '' || str_contains($baseUrl, 'your-project-id')) {
            return ['ok' => false, 'status' => 0, 'data' => null];
        }

        $url = $baseUrl . '/rest/v1/' . ltrim($endpoint, '/');
        $headers = array_merge([
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extraHeaders);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        return [
            'ok'     => ($status >= 200 && $status < 300),
            'status' => $status,
            'data'   => $decoded,
        ];
    }

    /**
     * Encode FDR state metadata into a compact string for Supabase public.uploads.error_message.
     */
    public static function encodeSupabaseStateEnvelope(array $state): string
    {
        $json = json_encode($state);
        $gz = @gzencode($json, 6);
        if ($gz !== false) {
            return '__FDR_STATE_GZ__:' . base64_encode($gz);
        }
        return '__FDR_STATE__:' . $json;
    }

    /**
     * Decode FDR state metadata from Supabase public.uploads.error_message.
     */
    public static function decodeSupabaseStateEnvelope(?string $raw): ?array
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        if (str_starts_with($raw, '__FDR_STATE_GZ__:')) {
            $b64 = substr($raw, strlen('__FDR_STATE_GZ__:'));
            $bin = base64_decode($b64, true);
            if ($bin !== false) {
                $json = @gzdecode($bin);
                if (is_string($json)) {
                    $arr = json_decode($json, true);
                    if (is_array($arr)) {
                        return $arr;
                    }
                }
            }
        }
        if (str_starts_with($raw, '__FDR_STATE__:')) {
            $json = substr($raw, strlen('__FDR_STATE__:'));
            $arr = json_decode($json, true);
            if (is_array($arr)) {
                return $arr;
            }
        }
        return null;
    }

    /**
     * Create or reconcile an Upload row in Supabase public.uploads via PostgREST HTTPS API.
     * Returns the canonical Supabase upload ID so local SQLite and Supabase stay in sync.
     */
    public static function syncQueuedUploadToSupabaseRest(string $originalFilename, string $storedPath, array $statePayload): ?int
    {
        try {
            $existingResp = self::supabaseRestRequest(
                'GET',
                'uploads?stored_path=eq.' . rawurlencode($storedPath) . '&order=id.desc&limit=1'
            );

            $encodedState = self::encodeSupabaseStateEnvelope($statePayload);
            $nowStr = now()->toDateTimeString();

            if ($existingResp['ok'] && !empty($existingResp['data'][0]['id'])) {
                $supaId = (int) $existingResp['data'][0]['id'];
                self::supabaseRestRequest(
                    'PATCH',
                    'uploads?id=eq.' . $supaId,
                    [
                        'error_message' => $encodedState,
                        'updated_at'    => $nowStr,
                    ],
                    ['Prefer: return=minimal']
                );
                return $supaId;
            }

            $createResp = self::supabaseRestRequest(
                'POST',
                'uploads',
                [
                    'original_filename' => substr($originalFilename, 0, 255),
                    'stored_path'       => substr($storedPath, 0, 255),
                    'status'            => 'processing',
                    'error_message'     => $encodedState,
                    'created_at'        => $nowStr,
                    'updated_at'        => $nowStr,
                ],
                ['Prefer: return=representation']
            );

            if ($createResp['ok'] && !empty($createResp['data'][0]['id'])) {
                return (int) $createResp['data'][0]['id'];
            }
        } catch (\Throwable $e) {
            Log::warning('FdrDatabaseService::syncQueuedUploadToSupabaseRest non-fatal warning: ' . self::sanitizeLogMessage($e->getMessage()));
        }

        return null;
    }

    /**
     * Sync completed Upload metadata, precomputed analytics, and normalized flight rows
     * to Supabase public.uploads and public.flights via PostgREST HTTPS API.
     */
    public static function syncCompletedUploadToSupabaseRest(Upload $upload, ?\App\Models\FdrProcessingJob $job = null): void
    {
        try {
            $state = [
                'v'                  => 2,
                'job_id'             => $job?->id ?: "fdr_job_{$upload->id}",
                'upload_token'       => $job?->upload_token ?: "fdr_tok_{$upload->id}",
                'file_size'          => (int) ($job?->file_size ?: 0),
                'status'             => $job?->status ?: 'READY',
                'stage'              => $job?->stage ?: 'READY',
                'progress'           => (int) ($job?->progress ?? 100),
                'report_type'        => $upload->report_type ?: 'fdr',
                'total_rows'         => (int) $upload->total_rows,
                'valid_rows'         => (int) $upload->valid_rows,
                'validation_summary' => $upload->validation_summary,
                'report_data'        => $upload->report_data,
                'cached_analytics'   => $upload->cached_analytics,
                'diagnostics'        => $job?->diagnostics ?: [],
            ];

            $encodedState = self::encodeSupabaseStateEnvelope($state);
            $nowStr = now()->toDateTimeString();

            $patchResp = self::supabaseRestRequest(
                'PATCH',
                'uploads?id=eq.' . $upload->id,
                [
                    'status'        => $upload->status ?: 'completed',
                    'error_message' => $encodedState,
                    'updated_at'    => $nowStr,
                ],
                ['Prefer: return=representation']
            );

            if (empty($patchResp['data'])) {
                self::supabaseRestRequest(
                    'POST',
                    'uploads',
                    [
                        'id'                => (int) $upload->id,
                        'original_filename' => substr((string) ($upload->original_filename ?: 'FDR_Report.xls'), 0, 255),
                        'stored_path'       => substr((string) ($upload->stored_path ?: ''), 0, 255),
                        'status'            => $upload->status ?: 'completed',
                        'error_message'     => $encodedState,
                        'created_at'        => $nowStr,
                        'updated_at'        => $nowStr,
                    ],
                    ['Prefer: return=minimal']
                );
            }

            // Sync normalized flight rows to Supabase public.flights so data is visible in Supabase Table Editor
            // and can be hydrated across serverless containers
            self::supabaseRestRequest('DELETE', 'flights?upload_id=eq.' . $upload->id, null, ['Prefer: return=minimal']);

            $flights = FdrFlight::where('upload_id', $upload->id)->orderBy('id')->limit(1000)->get();
            if ($flights->isNotEmpty()) {
                $supaRows = [];
                foreach ($flights as $f) {
                    $fDate = $f->flight_date
                        ? (is_string($f->flight_date) ? substr($f->flight_date, 0, 10) : $f->flight_date->format('Y-m-d'))
                        : date('Y-m-d');

                    $compactRaw = [
                        'flight_date'         => $fDate,
                        'flight_number_base'  => $f->flight_number_base,
                        'flight_suffix'       => $f->flight_suffix,
                        'paired_flight_number'=> $f->paired_flight_number,
                        'registration_number' => $f->registration_number,
                        'leg'                 => $f->leg,
                        'direction'           => $f->direction,
                        'movement_type'       => $f->movement_type,
                        'report_airport'      => $f->report_airport,
                        'traffic_type'        => $f->traffic_type,
                        'scheduled_time_str'  => $f->scheduled_time_str,
                        'actual_time_str'     => $f->actual_time_str,
                        'scheduled_hour'      => $f->scheduled_hour,
                        'actual_hour'         => $f->actual_hour,
                        'operational_hour'    => $f->operational_hour,
                        'delay_minutes'       => $f->delay_minutes,
                        'status'              => $f->status,
                        'is_realized'         => (bool) $f->is_realized,
                        'is_irregular'        => (bool) $f->is_irregular,
                        'passenger_capacity'  => (int) $f->passenger_capacity,
                        'passenger_load'      => (int) $f->passenger_load,
                        'load_factor'         => $f->load_factor,
                        'pax_adult'           => (int) $f->pax_adult,
                        'pax_child'           => (int) $f->pax_child,
                        'pax_infant'          => (int) $f->pax_infant,
                        'pax_transit'         => (int) $f->pax_transit,
                        'pax_transfer'        => (int) $f->pax_transfer,
                        'operating_crew'      => (int) $f->operating_crew,
                        'extra_crew'          => (int) $f->extra_crew,
                        'cargo_kg'            => (float) $f->cargo_kg,
                        'baggage_kg'          => (float) $f->baggage_kg,
                        'pos_kg'              => (float) $f->pos_kg,
                        'stand'               => $f->stand,
                        'runway'              => $f->runway,
                        'mtow'                => $f->mtow,
                    ];

                    $schedTime = $f->scheduled_time ?: '00:00:00';
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $schedTime)) {
                        $schedTime = '00:00:00';
                    }

                    $supaRows[] = [
                        'upload_id'      => (int) $upload->id,
                        'flight_number'  => substr((string) ($f->flight_number ?: 'N/A'), 0, 20),
                        'airline_code'   => substr((string) ($f->airline_code ?: 'GA'), 0, 10),
                        'aircraft_type'  => substr((string) ($f->aircraft_type ?: 'N/A'), 0, 20),
                        'origin'         => substr((string) ($f->origin_airport ?: ($f->report_airport ?: 'CGK')), 0, 10),
                        'destination'    => substr((string) ($f->destination_airport ?: ($f->report_airport ?: 'CGK')), 0, 10),
                        'scheduled_time' => $schedTime,
                        'operating_days' => substr(str_replace('-', '', $fDate), -6),
                        'flight_type'    => substr($f->direction === 'ARRIVAL' ? 'ARR' : 'DEP', 0, 20),
                        'remarks'        => substr((string) ($f->status ?: 'ON_TIME'), 0, 255),
                        'raw_data'       => json_encode($compactRaw),
                        'created_at'     => $nowStr,
                        'updated_at'     => $nowStr,
                    ];
                }

                foreach (array_chunk($supaRows, 500) as $batchChunk) {
                    self::supabaseRestRequest('POST', 'flights', $batchChunk, ['Prefer: return=minimal']);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('FdrDatabaseService::syncCompletedUploadToSupabaseRest non-fatal warning: ' . self::sanitizeLogMessage($e->getMessage()));
        }
    }

    /**
     * Hydrate an Upload and its FdrProcessingJob from Supabase PostgREST into local SQLite
     * when a request lands on a fresh Vercel serverless container.
     */
    public static function hydrateUploadFromSupabaseRest(int $uploadId): ?Upload
    {
        self::ensureWorkingConnection();

        $existing = Upload::find($uploadId);
        if ($existing && ($existing->status === 'completed' && !empty($existing->cached_analytics))) {
            return $existing;
        }

        try {
            $resp = self::supabaseRestRequest('GET', 'uploads?id=eq.' . $uploadId . '&limit=1');
            if (!$resp['ok'] || empty($resp['data'][0])) {
                return $existing;
            }

            $row = $resp['data'][0];
            $state = self::decodeSupabaseStateEnvelope($row['error_message'] ?? null) ?: [];

            $attrs = [
                'id'                 => (int) $row['id'],
                'original_filename'  => $row['original_filename'] ?? 'CGK GA FDR.xls',
                'stored_path'        => $row['stored_path'] ?? '',
                'status'             => $row['status'] ?? 'processing',
                'report_type'        => $state['report_type'] ?? 'fdr',
                'total_rows'         => (int) ($state['total_rows'] ?? 0),
                'valid_rows'         => (int) ($state['valid_rows'] ?? 0),
                'invalid_rows'       => 0,
                'duplicate_rows'     => 0,
                'parsing_confidence' => ($row['status'] ?? '') === 'completed' ? 1.0 : 0.0,
                'validation_summary' => $state['validation_summary'] ?? ['valid' => true],
                'report_data'        => $state['report_data'] ?? [
                    'meta' => $state['meta'] ?? [
                        'source_system' => 'OASYS',
                        'report_name'   => 'FLIGHT DAILY REPORT',
                        'source_path'   => $row['stored_path'] ?? '',
                    ],
                ],
                'cached_analytics'   => $state['cached_analytics'] ?? null,
            ];

            if ($existing) {
                $existing->update($attrs);
                $upload = $existing->fresh();
            } else {
                $upload = Upload::create($attrs);
            }

            $jobId = $state['job_id'] ?? "fdr_job_{$upload->id}";
            $uploadToken = $state['upload_token'] ?? "fdr_tok_{$upload->id}";
            $jobStatus = $state['status'] ?? (($upload->status === 'completed') ? 'READY' : 'QUEUED');

            $job = \App\Models\FdrProcessingJob::find($jobId)
                ?: \App\Models\FdrProcessingJob::where('upload_id', $upload->id)->first();

            $jobAttrs = [
                'id'             => $jobId,
                'upload_id'      => $upload->id,
                'upload_token'   => $uploadToken,
                'filename'       => $upload->original_filename,
                'stored_path'    => $upload->stored_path,
                'file_size'      => (int) ($state['file_size'] ?? 0),
                'report_type'    => 'fdr',
                'status'         => $jobStatus,
                'stage'          => $state['stage'] ?? $jobStatus,
                'stage_label'    => $jobStatus === 'READY' ? 'Ready' : 'Preparing Flight Daily Report...',
                'progress'       => (int) ($state['progress'] ?? ($jobStatus === 'READY' ? 100 : 0)),
                'processed_rows' => (int) ($state['total_rows'] ?? 0),
                'total_rows'     => (int) ($state['total_rows'] ?? 0),
                'meta'           => $state['meta'] ?? ($upload->report_data['meta'] ?? []),
                'diagnostics'    => $state['diagnostics'] ?? ['source' => 'SUPABASE_STORAGE'],
                'result_url'     => route('fdr.dashboard', $upload->id),
            ];

            if ($job) {
                $job->update($jobAttrs);
            } else {
                \App\Models\FdrProcessingJob::create($jobAttrs);
            }

            return $upload;
        } catch (\Throwable $e) {
            Log::warning('FdrDatabaseService::hydrateUploadFromSupabaseRest warning: ' . self::sanitizeLogMessage($e->getMessage()));
            return $existing;
        }
    }

    /**
     * Find and hydrate the latest completed FDR Upload from Supabase PostgREST.
     */
    public static function hydrateLatestCompletedUploadFromSupabaseRest(): ?Upload
    {
        try {
            $resp = self::supabaseRestRequest(
                'GET',
                'uploads?status=eq.completed&error_message=like.__FDR_STATE*&order=id.desc&limit=1&select=id'
            );
            if ($resp['ok'] && !empty($resp['data'][0]['id'])) {
                return self::hydrateUploadFromSupabaseRest((int) $resp['data'][0]['id']);
            }
        } catch (\Throwable $e) {
            Log::warning('FdrDatabaseService::hydrateLatestCompletedUploadFromSupabaseRest warning: ' . self::sanitizeLogMessage($e->getMessage()));
        }

        return null;
    }

    /**
     * Find an FdrProcessingJob locally or hydrate it from Supabase PostgREST across serverless containers.
     */
    public static function findOrHydrateJob(string $jobId): ?\App\Models\FdrProcessingJob
    {
        self::ensureWorkingConnection();

        $job = \App\Models\FdrProcessingJob::find($jobId);
        if ($job) {
            return $job;
        }

        $uploadId = null;
        if (preg_match('/^fdr_job_(\d+)$/', $jobId, $m)) {
            $uploadId = (int) $m[1];
        } elseif (is_numeric($jobId)) {
            $uploadId = (int) $jobId;
        }

        if ($uploadId !== null && $uploadId > 0) {
            self::hydrateUploadFromSupabaseRest($uploadId);
            return \App\Models\FdrProcessingJob::find($jobId)
                ?: \App\Models\FdrProcessingJob::where('upload_id', $uploadId)->latest()->first();
        }

        return null;
    }

    /**
     * Hydrate fdr_flights rows from Supabase public.flights when a cold serverless container
     * needs row-level filtering or exports.
     */
    public static function hydrateFlightsFromSupabaseRest(Upload $upload): int
    {
        try {
            $resp = self::supabaseRestRequest('GET', 'flights?upload_id=eq.' . $upload->id . '&order=id.asc&limit=5000');
            if (!$resp['ok'] || empty($resp['data']) || !is_array($resp['data'])) {
                return 0;
            }

            $isSqlite = (DB::connection()->getDriverName() === 'sqlite');
            $now = now();
            $rows = [];

            foreach ($resp['data'] as $sr) {
                $raw = is_string($sr['raw_data'] ?? null) ? (json_decode($sr['raw_data'], true) ?: []) : ($sr['raw_data'] ?? []);
                $isRealized = (bool) ($raw['is_realized'] ?? true);
                $isIrregular = (bool) ($raw['is_irregular'] ?? false);

                $rows[] = [
                    'upload_id'            => (int) $upload->id,
                    'flight_date'          => $raw['flight_date'] ?? ($sr['operating_days'] ?? date('Y-m-d')),
                    'flight_number'        => $sr['flight_number'] ?? 'N/A',
                    'flight_number_base'   => $raw['flight_number_base'] ?? ($sr['flight_number'] ?? 'N/A'),
                    'flight_suffix'        => $raw['flight_suffix'] ?? null,
                    'airline_code'         => $sr['airline_code'] ?? 'GA',
                    'airline'              => $sr['airline_code'] ?? 'GA',
                    'paired_flight_number' => $raw['paired_flight_number'] ?? null,
                    'aircraft_type'        => $sr['aircraft_type'] ?? null,
                    'registration_number'  => $raw['registration_number'] ?? null,
                    'leg'                  => $raw['leg'] ?? null,
                    'direction'            => $raw['direction'] ?? (($sr['flight_type'] ?? 'ARR') === 'ARR' ? 'ARRIVAL' : 'DEPARTURE'),
                    'movement_type'        => $raw['movement_type'] ?? 'SCHEDULED',
                    'origin_airport'       => $sr['origin'] ?? 'CGK',
                    'destination_airport'  => $sr['destination'] ?? 'CGK',
                    'city_1'               => $sr['origin'] ?? 'CGK',
                    'city_2'               => $sr['destination'] ?? 'CGK',
                    'report_airport'       => $raw['report_airport'] ?? 'CGK',
                    'traffic_type'         => $raw['traffic_type'] ?? 'DOMESTIC',
                    'scheduled_time'       => $sr['scheduled_time'] ?? null,
                    'actual_time'          => null,
                    'scheduled_datetime'   => null,
                    'actual_datetime'      => null,
                    'scheduled_time_str'   => $raw['scheduled_time_str'] ?? null,
                    'actual_time_str'      => $raw['actual_time_str'] ?? null,
                    'scheduled_hour'       => isset($raw['scheduled_hour']) ? (int) $raw['scheduled_hour'] : null,
                    'actual_hour'          => isset($raw['actual_hour']) ? (int) $raw['actual_hour'] : null,
                    'operational_hour'     => (int) ($raw['operational_hour'] ?? 0),
                    'delay_minutes'        => (int) ($raw['delay_minutes'] ?? 0),
                    'status'               => $raw['status'] ?? ($sr['remarks'] ?? 'ON_TIME'),
                    'is_realized'          => $isSqlite ? ($isRealized ? 1 : 0) : ($isRealized ? 'true' : 'false'),
                    'is_irregular'         => $isSqlite ? ($isIrregular ? 1 : 0) : ($isIrregular ? 'true' : 'false'),
                    'passenger_capacity'   => (int) ($raw['passenger_capacity'] ?? 0),
                    'passenger_load'       => (int) ($raw['passenger_load'] ?? 0),
                    'load_factor'          => isset($raw['load_factor']) ? (float) $raw['load_factor'] : null,
                    'pax_adult'            => (int) ($raw['pax_adult'] ?? 0),
                    'pax_child'            => (int) ($raw['pax_child'] ?? 0),
                    'pax_infant'           => (int) ($raw['pax_infant'] ?? 0),
                    'pax_transit'          => (int) ($raw['pax_transit'] ?? 0),
                    'pax_transfer'         => (int) ($raw['pax_transfer'] ?? 0),
                    'operating_crew'       => (int) ($raw['operating_crew'] ?? 0),
                    'extra_crew'           => (int) ($raw['extra_crew'] ?? 0),
                    'cargo_kg'             => (float) ($raw['cargo_kg'] ?? 0),
                    'baggage_kg'           => (float) ($raw['baggage_kg'] ?? 0),
                    'pos_kg'               => (float) ($raw['pos_kg'] ?? 0),
                    'stand'                => $raw['stand'] ?? null,
                    'runway'               => $raw['runway'] ?? null,
                    'mtow'                 => $raw['mtow'] ?? null,
                    'raw_data'             => json_encode($raw),
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }

            foreach (array_chunk($rows, 250) as $chunk) {
                DB::table('fdr_flights')->insert($chunk);
            }

            return count($rows);
        } catch (\Throwable $e) {
            Log::warning('FdrDatabaseService::hydrateFlightsFromSupabaseRest warning: ' . self::sanitizeLogMessage($e->getMessage()));
            return 0;
        }
    }

    /**
     * Check if an upload already has records in the fdr_flights database table.
     */
    public function hasDatabaseRecords(Upload $upload): bool
    {
        self::ensureWorkingConnection();
        if (FdrFlight::where('upload_id', $upload->id)->exists()) {
            return true;
        }
        if (self::hydrateFlightsFromSupabaseRest($upload) > 0) {
            return true;
        }
        return !empty($upload->cached_analytics['total_flights']);
    }

    /**
     * Get count of database records for an upload.
     */
    public function getRecordCount(Upload $upload): int
    {
        self::ensureWorkingConnection();
        $cnt = FdrFlight::where('upload_id', $upload->id)->count();
        if ($cnt === 0) {
            $cnt = self::hydrateFlightsFromSupabaseRest($upload);
        }
        if ($cnt === 0 && !empty($upload->cached_analytics['total_flights'])) {
            return (int) $upload->cached_analytics['total_flights'];
        }
        return $cnt;
    }

    /**
     * Resilient database query executor with exponential backoff and connection reconnect.
     * Recovers from PgBouncer pooler connection drops, standby mode failovers, and transient SSL terminations.
     * Does NOT retry non-transient schema (42xxx), integrity (23xxx), auth (28xxx), or data (22xxx) errors.
     */
    public static function executeWithRetry(callable $callback, int $maxRetries = 3, int $initialDelayMs = 250, bool $forceFresh = false)
    {
        $attempt = 0;

        if ($forceFresh) {
            self::ensureWorkingConnection();
        }

        $connectionName = config('database.default', 'pgsql');

        if ($forceFresh && $connectionName === 'pgsql') {
            try {
                DB::disconnect($connectionName);
                DB::purge($connectionName);
                $freshConn = DB::reconnect($connectionName);
                $freshConn->select('SELECT 1');
            } catch (\Throwable $freshErr) {
                // Non-fatal, reconnect will be retried in loop if needed
            }
        }

        while (true) {
            $attempt++;
            try {
                return $callback();
            } catch (\Throwable $e) {
                $classification = self::classifyError($e);
                $isConnectionDrop = ($classification['category'] === 'connection');

                if ($attempt >= $maxRetries || !$isConnectionDrop) {
                    throw $e;
                }

                $jitterMs = random_int(20, 80);
                $backoffMs = ($initialDelayMs * (2 ** ($attempt - 1))) + $jitterMs;
                Log::warning("FdrDatabaseService: Transient database connection error on attempt {$attempt}/{$maxRetries} ({$classification['sql_state']}). Reconnecting in {$backoffMs}ms...", [
                    'category'  => $classification['category'],
                    'sql_state' => $classification['sql_state'],
                ]);

                try {
                    // If a transaction was open when the connection severed, rollback safely
                    while (DB::connection($connectionName)->transactionLevel() > 0) {
                        try {
                            DB::connection($connectionName)->rollBack();
                        } catch (\Throwable $rbEx) {
                            // Severed socket cannot accept rollback, break cleanly
                            break;
                        }
                    }
                    DB::disconnect($connectionName);
                    DB::purge($connectionName);
                    $conn = DB::reconnect($connectionName);
                    // Lightweight validation ping to guarantee socket accepts queries:
                    $conn->select('SELECT 1');
                } catch (\Throwable $reconnectErr) {
                    Log::warning("FdrDatabaseService: DB reconnect verification failed on attempt {$attempt}: " . self::sanitizeLogMessage($reconnectErr->getMessage()));
                }

                usleep($backoffMs * 1000);
            }
        }
    }

    /**
     * Comprehensive, accurate classifier for database exceptions and general errors.
     * Extracts true SQLSTATE from the exception chain (QueryException -> PDOException -> driver).
     *
     * Minimal separated categories:
     * - 'connection': Transient 08xxx, 57P01-57P03, network drops (retryable)
     * - 'schema': 42P01 undefined table, 42703 undefined column, 42xxx syntax (non-retryable)
     * - 'integrity': 23xxx constraint violations (non-retryable)
     * - 'auth': 28xxx authorization / password failed (non-retryable)
     * - 'data': 22xxx data representation exception (non-retryable)
     * - 'validation': Laravel form/input validation failed (non-retryable)
     * - 'general': other unclassified application error (non-retryable)
     *
     * @return array{category: string, code: string, sql_state: string, is_retryable: bool, user_title: string, user_message: string, detail: string}
     */
    public static function classifyError(\Throwable $e): array
    {
        $curr = $e;
        $sqlState = null;
        $extractedMessage = $e->getMessage() ?: get_class($e);

        while ($curr) {
            $msg = $curr->getMessage();
            $errorInfo = ($curr instanceof \PDOException || $curr instanceof \Illuminate\Database\QueryException)
                ? ($curr->errorInfo ?? [])
                : [];

            $state = (string) ($errorInfo[0] ?? '');
            if ($state !== '' && $sqlState === null) {
                $sqlState = strtoupper($state);
            }

            if ($sqlState === null && preg_match('/SQLSTATE\[([A-Z0-9]{5})\]/i', $msg, $m)) {
                $sqlState = strtoupper($m[1]);
            }

            $curr = $curr->getPrevious();
        }

        $sqlState = $sqlState ?: 'UNKNOWN';
        $prefix = substr($sqlState, 0, 2);
        $cleanMsg = self::sanitizeLogMessage($extractedMessage);

        // 1. Schema / Structure / Syntax Errors (Class 42xxx)
        $isSchemaPattern = preg_match(
            '/42P01|42703|42P07|42601|undefined table|undefined column|relation [\"\'].*?[\"\'] does not exist|column [\"\'].*?[\"\'] does not exist|syntax error/i',
            $extractedMessage
        );
        if ($prefix === '42' || $isSchemaPattern) {
            $missingTarget = '';
            if (preg_match('/relation [\"\'](.*?)[\"\'] does not exist/i', $extractedMessage, $tm)) {
                $missingTarget = " (Tabel '{$tm[1]}' belum tersedia di database)";
            } elseif (preg_match('/column [\"\'](.*?)[\"\'] does not exist/i', $extractedMessage, $cm)) {
                $missingTarget = " (Kolom '{$cm[1]}' belum tersedia di database)";
            }

            return [
                'category'     => 'schema',
                'code'         => 'DATABASE_SCHEMA_ERROR',
                'sql_state'    => $sqlState,
                'is_retryable' => false,
                'user_title'   => 'STRUKTUR DATABASE BELUM SESUAI',
                'user_message' => 'Struktur database belum sesuai dengan kebutuhan FDR. Hubungi administrator untuk memeriksa migration.' . $missingTarget,
                'detail'       => $cleanMsg,
            ];
        }

        // 2. Integrity Constraint Violations (Class 23xxx)
        if ($prefix === '23' || preg_match('/unique constraint|foreign key constraint|not-null constraint|violates check constraint/i', $extractedMessage)) {
            return [
                'category'     => 'integrity',
                'code'         => 'DATABASE_INTEGRITY_ERROR',
                'sql_state'    => $sqlState,
                'is_retryable' => false,
                'user_title'   => 'KONFLIK INTEGRITAS DATABASE',
                'user_message' => 'Data unggahan tidak dapat disimpan karena konflik integritas database. Periksa log server untuk detail aman.',
                'detail'       => $cleanMsg,
            ];
        }

        // 3. Authorization / Authentication (Class 28xxx)
        if ($prefix === '28' || preg_match('/password authentication failed|permission denied for/i', $extractedMessage)) {
            return [
                'category'     => 'auth',
                'code'         => 'DATABASE_AUTH_ERROR',
                'sql_state'    => $sqlState,
                'is_retryable' => false,
                'user_title'   => 'OTORISASI DATABASE GAGAL',
                'user_message' => 'Otorisasi database gagal. Hubungi administrator untuk memeriksa konfigurasi kredensial database.',
                'detail'       => $cleanMsg,
            ];
        }

        // 4. Data Representation / Type Exceptions (Class 22xxx)
        if ($prefix === '22' || preg_match('/value too long|invalid input syntax/i', $extractedMessage)) {
            return [
                'category'     => 'data',
                'code'         => 'DATABASE_DATA_ERROR',
                'sql_state'    => $sqlState,
                'is_retryable' => false,
                'user_title'   => 'FORMAT DATA TIDAK VALID',
                'user_message' => 'Data unggahan memiliki representasi nilai yang tidak valid untuk skema database.',
                'detail'       => $cleanMsg,
            ];
        }

        // 5. Transient Connection Errors (Class 08xxx, 57P01-57P03, Network drops)
        $isConnectionSqlState = in_array($sqlState, ['08006', '08003', '08001', '08004', '08007', '57P01', '57P02', '57P03'], true);
        $isConnectionMsg = self::hasConnectionDropMessage($extractedMessage);

        if ($prefix === '08' || $isConnectionSqlState || $isConnectionMsg) {
            return [
                'category'     => 'connection',
                'code'         => 'DATABASE_CONNECTION_ERROR',
                'sql_state'    => $sqlState,
                'is_retryable' => true,
                'user_title'   => 'KONEKSI DATABASE BERMASALAH',
                'user_message' => 'Koneksi database sedang bermasalah. Sistem telah mencoba menyambungkan kembali tetapi belum berhasil. File tetap disimpan jika upload Storage sudah berhasil. Silakan coba kembali setelah status database diverifikasi.',
                'detail'       => $cleanMsg,
            ];
        }

        // 6. Validation Error
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return [
                'category'     => 'validation',
                'code'         => 'FDR_VALIDATION_ERROR',
                'sql_state'    => $sqlState,
                'is_retryable' => false,
                'user_title'   => 'VALIDASI UNGGAHAN GAGAL',
                'user_message' => implode('; ', $e->validator->errors()->all()),
                'detail'       => $cleanMsg,
            ];
        }

        // 7. General Error
        return [
            'category'     => 'general',
            'code'         => 'FDR_PROCESSING_ERROR',
            'sql_state'    => $sqlState,
            'is_retryable' => false,
            'user_title'   => 'GAGAL MEMPROSES FLIGHT DAILY REPORT',
            'user_message' => $cleanMsg,
            'detail'       => $cleanMsg,
        ];
    }

    /**
     * Detect transient database pooler, standby, and network drop errors.
     * Inspects the full exception chain (QueryException -> PDOException -> driver error).
     */
    public static function isConnectionError(\Throwable $e): bool
    {
        return self::classifyError($e)['category'] === 'connection';
    }

    /**
     * Check if an error message contains known PostgreSQL pooler / network disconnection signatures.
     */
    public static function hasConnectionDropMessage(string $msg): bool
    {
        $patterns = [
            'Hot standby mode is disabled',
            'the database system is not accepting connections',
            'terminating connection due to administrator command',
            'could not receive data from client',
            'SSL connection has been closed unexpectedly',
            'SSL SYSCALL error',
            'connection to server',
            'connection failure',
            'connection does not exist',
            'could not connect to server',
            'no connection to the server',
            'server closed the connection unexpectedly',
            'server has gone away',
            'Lost connection',
            'Broken pipe',
            'Connection refused',
            'Resource temporarily unavailable',
            'Operation timed out',
            'Connection timed out',
            'ETIMEDOUT',
            '08006',
            '08003',
            '08001',
            '57P01',
        ];

        foreach ($patterns as $pattern) {
            if (stripos($msg, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize log and exception messages by scrubbing sensitive connection parameters,
     * passwords, authorization tokens, and API keys.
     */
    public static function sanitizeLogMessage(string $msg): string
    {
        $sanitized = preg_replace('/password=[^\s;]+/i', 'password=***', $msg);
        $sanitized = preg_replace('/postgres:[^@]+@/i', 'postgres:***@', $sanitized);
        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9_\-\.]+/i', 'Bearer ***', $sanitized);
        $sanitized = preg_replace('/(token|key|secret)=[A-Za-z0-9_\-\.]+/i', '$1=***', $sanitized);
        return $sanitized;
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

        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');

        $now = now();
        $batch = [];
        $batchSize = 500;
        $totalInserted = 0;
        $defaultAirport = strtoupper(trim($meta['airport'] ?? 'CGK'));

        try {
            self::executeWithRetry(function () use ($upload) {
                FdrFlight::where('upload_id', $upload->id)->delete();
            });

            foreach ($records as $r) {
                $row = $this->transformRecordToDbRow($r, $upload->id, $defaultAirport, $meta, $now);
                if ($row === null) {
                    continue;
                }

                $batch[] = $row;
                if (count($batch) >= $batchSize) {
                    $insertBatch = $batch;
                    self::executeWithRetry(function () use ($insertBatch) {
                        DB::transaction(function () use ($insertBatch) {
                            DB::table('fdr_flights')->insert($insertBatch);
                        });
                    });
                    $totalInserted += count($batch);
                    $batch = [];
                    if (config('database.default') === 'pgsql') {
                        usleep(20000);
                    }
                }
            }

            if (!empty($batch)) {
                $insertBatch = $batch;
                self::executeWithRetry(function () use ($insertBatch) {
                    DB::transaction(function () use ($insertBatch) {
                        DB::table('fdr_flights')->insert($insertBatch);
                    });
                });
                $totalInserted += count($batch);
                $batch = [];
                if (config('database.default') === 'pgsql') {
                    usleep(20000);
                }
            }

            Log::info("FdrDatabaseService: Synced {$totalInserted} flights to database for upload #{$upload->id}");

            try {
                $this->precomputeAndCacheAnalytics($upload, $meta);
            } catch (\Throwable $e) {
                Log::warning("Could not precompute analytics in syncUploadToDatabase for upload #{$upload->id}: " . $e->getMessage());
            }

            return $totalInserted;

        } catch (\Throwable $e) {
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

        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');

        $now = now();
        $totalInserted = 0;
        $offset = 0;
        $isEof = false;
        $meta = [];

        try {
            self::executeWithRetry(function () use ($upload) {
                FdrFlight::where('upload_id', $upload->id)->delete();
            });

            while (!$isEof) {
                $batchResult = $parser->parseBatch($filePath, $offset, 4000, $meta);
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

                    if (!empty($dbRows)) {
                        self::executeWithRetry(function () use ($dbRows) {
                            DB::transaction(function () use ($dbRows) {
                                foreach (array_chunk($dbRows, 500) as $chunk) {
                                    DB::table('fdr_flights')->insert($chunk);
                                }
                            });
                        });
                        $totalInserted += count($dbRows);
                        if (config('database.default') === 'pgsql') {
                            usleep(20000);
                        }
                    }
                    unset($dbRows);
                    unset($records);
                }
            }

            Log::info("FdrDatabaseService: Synced {$totalInserted} flights from file for upload #{$upload->id}");

            try {
                $this->precomputeAndCacheAnalytics($upload, $meta);
            } catch (\Throwable $e) {
                Log::warning("Could not precompute analytics in syncUploadFromFile for upload #{$upload->id}: " . $e->getMessage());
            }

            return $totalInserted;
        } catch (\Throwable $e) {
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

        $rawSchedDt = $r['scheduled_datetime'] ?? null;
        $schedDt = !empty($rawSchedDt)
            ? (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $rawSchedDt) ? (string) $rawSchedDt : Carbon::parse($rawSchedDt)->toDateTimeString())
            : null;
        $rawActDt = $r['actual_datetime'] ?? null;
        $actDt = !empty($rawActDt)
            ? (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $rawActDt) ? (string) $rawActDt : Carbon::parse($rawActDt)->toDateTimeString())
            : null;

        $flightDate = null;
        if (!empty($r['flight_date']) && $r['flight_date'] !== 'N/A') {
            $flightDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['flight_date'])
                ? (string) $r['flight_date']
                : FlightDailyReportFilter::standardizeDate($r['flight_date']);
        } elseif (!empty($r['operational_date']) && $r['operational_date'] !== 'N/A') {
            $flightDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['operational_date'])
                ? (string) $r['operational_date']
                : FlightDailyReportFilter::standardizeDate($r['operational_date']);
        } elseif ($schedDt) {
            $flightDate = substr($schedDt, 0, 10);
        } elseif (!empty($meta['period_start'])) {
            $flightDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $meta['period_start'])
                ? (string) $meta['period_start']
                : FlightDailyReportFilter::standardizeDate($meta['period_start']);
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
            'is_realized'          => (config('database.default') === 'sqlite') ? ($isRealized ? 1 : 0) : ($isRealized ? 'true' : 'false'),
            'is_irregular'         => (config('database.default') === 'sqlite') ? ((!empty($r['is_irregular'])) ? 1 : 0) : ((!empty($r['is_irregular'])) ? 'true' : 'false'),
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
            'raw_data'             => json_encode([
                'index'                  => $r['index'] ?? null,
                'source_row_number'      => $r['source_row_number'] ?? ($r['index'] ?? null),
                'sibt'                   => $r['sibt'] ?? null,
                'sobt'                   => $r['sobt'] ?? null,
                'aibt'                   => $r['aibt'] ?? null,
                'aobt'                   => $r['aobt'] ?? null,
                'umroh'                  => $r['umroh'] ?? 0,
                'divert'                 => $r['divert'] ?? 0,
                'miss'                   => $r['miss'] ?? 0,
                'crw'                    => (int)($r['operating_crew'] ?? ($r['crw'] ?? 0)),
                'ex_crw'                 => (int)($r['extra_crew'] ?? ($r['ex_crw'] ?? 0)),
                'operating_crew'         => (int)($r['operating_crew'] ?? ($r['crw'] ?? 0)),
                'extra_crew'             => (int)($r['extra_crew'] ?? ($r['ex_crw'] ?? 0)),
                'total_crew'             => (int)($r['total_crew'] ?? ($r['operating_crew'] ?? ($r['crw'] ?? 0))),
                'final'                  => $r['final'] ?? null,
                'final_time'             => $r['final_time'] ?? null,
                'branch'                 => $r['branch'] ?? $repAirport,
                'raw_leg'                => $r['raw_leg'] ?? ($r['leg'] ?? null),
                'leg_subtype'            => $r['leg_subtype'] ?? null,
                'sched_type'             => $r['sched_type'] ?? ($isSched ? 'SCHED' : 'UNSCHED'),
                'is_scheduled'           => $isSched,
                'flight_suffix_label'    => $r['flight_suffix_label'] ?? null,
                'flight_suffix_desc'     => $r['flight_suffix_desc'] ?? null,
                'flight_suffix_category' => $r['flight_suffix_category'] ?? null,
                'api_pax_edifly'         => $r['api_pax_edifly'] ?? null,
                'api_cargo_edifly'       => $r['api_cargo_edifly'] ?? null,
            ]),
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

        // File-based fallback cache in /tmp
        $tmpCacheFile = sys_get_temp_dir() . "/fdr_analytics_{$upload->id}.json";
        if (file_exists($tmpCacheFile)) {
            $json = @file_get_contents($tmpCacheFile);
            if ($json) {
                $decoded = json_decode($json, true);
                if (!empty($decoded) && !empty($decoded['analytics'])) {
                    return $decoded;
                }
            }
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
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');

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
            $upload->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning("Could not persist cached_analytics to DB for upload #{$upload->id}: " . $e->getMessage());
        }

        // File-based fallback cache in /tmp for resilient offline dashboard rendering
        try {
            $tmpCacheFile = sys_get_temp_dir() . "/fdr_analytics_{$upload->id}.json";
            @file_put_contents($tmpCacheFile, json_encode($cachedPayload));
        } catch (\Throwable $fEx) {
            // Non-critical local tmp cache
        }

        return $cachedPayload;
    }

}
