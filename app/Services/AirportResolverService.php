<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\Airline;
use Illuminate\Support\Facades\Log;

/**
 * AirportResolverService — Centralized IATA code and Airline lookup service.
 *
 * Resolves station and airport names ("SURABAYA", "JOGYAKARTA", "JOGJAKARTA", "DENPASAR", "TANJUNG KARANG", "NUSAWIRU")
 * to canonical 3-letter IATA codes ("SUB", "JOG", "DPS", "TKG", "CJN") based on master reference data.
 */
class AirportResolverService
{
    /**
     * Map of common station names, cities, and airport aliases to 3-letter IATA codes.
     */
    private array $nameToIataMap = [
        'SURABAYA'                => 'SUB',
        'JUANDA'                  => 'SUB',
        'JAKARTA'                 => 'CGK',
        'SOEKARNO HATTA'          => 'CGK',
        'SOEKARNO-HATTA'          => 'CGK',
        'HALIM'                   => 'HLP',
        'HALIM PERDANAKUSUMA'     => 'HLP',
        'HALIM PERDANA KUSUMA'    => 'HLP',
        'BANDUNG'                 => 'BDO',
        'HUSEIN SASTRANEGARA'     => 'BDO',
        'KERTAJATI'               => 'KJT',
        'MAJALENGKA'              => 'KJT',
        'TANJUNG KARANG'          => 'TKG',
        'TANJUNGKARANG'           => 'TKG',
        'LAMPUNG'                 => 'TKG',
        'BANDAR LAMPUNG'          => 'TKG',
        'RADEN INTEN II'          => 'TKG',
        'RADEN INTEN'             => 'TKG',
        'RADIN INTEN'             => 'TKG',
        'SINGAPURA'               => 'SIN',
        'SINGAPORE'               => 'SIN',
        'CHANGI'                  => 'SIN',
        'MALANG'                  => 'MLG',
        'ABDUL RACHMAN SALEH'     => 'MLG',
        'PANGKALPINANG'           => 'PGK',
        'PANGKAL PINANG'          => 'PGK',
        'DEPATI AMIR'             => 'PGK',
        'SOLO'                    => 'SOC',
        'SURAKARTA'               => 'SOC',
        'ADI SOEMARMO'            => 'SOC',
        'DENPASAR'                => 'DPS',
        'BALI'                    => 'DPS',
        'NGURAH RAI'              => 'DPS',
        'I GUSTI NGURAH RAI'      => 'DPS',
        'MEDAN'                   => 'KNO',
        'KUALANAMU'               => 'KNO',
        'MAKASSAR'                => 'UPG',
        'UJUNGPANDANG'            => 'UPG',
        'UJUNG PANDANG'           => 'UPG',
        'SULTAN HASANUDDIN'       => 'UPG',
        'MANADO'                  => 'MDC',
        'SAM RATULANGI'           => 'MDC',
        'LOMBOK'                  => 'LOP',
        'PRAYA'                   => 'LOP',
        'JOGYAKARTA'              => 'JOG',
        'YOGYAKARTA'              => 'JOG',
        'JOGJAKARTA'              => 'JOG',
        'ADISUTJIPTO'             => 'JOG',
        'YOGYAKARTA INTL'         => 'YIA',
        'YIA'                     => 'YIA',
        'KULON PROGO'             => 'YIA',
        'SEMARANG'                => 'SRG',
        'AHMAD YANI'              => 'SRG',
        'JENDERAL AHMAD YANI'     => 'SRG',
        'PEKANBARU'               => 'PKU',
        'SULTAN SYARIF KASIM'     => 'PKU',
        'SULTAN SYARIF KASIM II'  => 'PKU',
        'PALEMBANG'               => 'PLM',
        'SULTAN MAHMUD BADARUDDIN'=> 'PLM',
        'SULTAN MAHMUD BADARUDDIN II' => 'PLM',
        'BATAM'                   => 'BTH',
        'HANG NADIM'              => 'BTH',
        'BALIKPAPAN'              => 'BPN',
        'SEPINGGAN'               => 'BPN',
        'SULTAN AJI MUHAMMAD SULAIMAN' => 'BPN',
        'BANJARMASIN'             => 'BDJ',
        'SYAMSUDIN NOOR'          => 'BDJ',
        'PONTIANAK'               => 'PNK',
        'SUPADIO'                 => 'PNK',
        'PADANG'                  => 'PDG',
        'MINANGKABAU'             => 'PDG',
        'PALANGKARAYA'            => 'PKY',
        'PALANGKA RAYA'           => 'PKY',
        'TJILIK RIWUT'            => 'PKY',
        'JAMBI'                   => 'DJB',
        'SULTAN THAHA'            => 'DJB',
        'AMBON'                   => 'AMQ',
        'PATTIMURA'               => 'AMQ',
        'JAYAPURA'                => 'DJJ',
        'SENTANI'                 => 'DJJ',
        'BIAK'                    => 'BIK',
        'FRANS KAISIEPO'          => 'BIK',
        'BENGKULU'                => 'BKS',
        'FATMAWATI SOEKARNO'      => 'BKS',
        'KUPANG'                  => 'KOE',
        'EL TARI'                 => 'KOE',
        'BANYUWANGI'              => 'BWX',
        'SILANGIT'                => 'DTB',
        'RAJA SISINGAMANGARAJA XII'=> 'DTB',
        'BELITUNG'                => 'TJQ',
        'TANJUNG PANDAN'          => 'TJQ',
        'H.A.S HANANDJOEDDIN'     => 'TJQ',
        'TANJUNG PINANG'          => 'TNJ',
        'TANJUNGPINANG'           => 'TNJ',
        'RAJA HAJI FISABILILLAH'  => 'TNJ',
        'BANDA ACEH'              => 'BTJ',
        'ACEH'                    => 'BTJ',
        'SULTAN ISKANDAR MUDA'    => 'BTJ',
        'KEDIRI'                  => 'DHX',
        'DHOHO'                   => 'DHX',
        'PURBALINGGA'             => 'PWL',
        'JENDERAL BESAR SOEDIRMAN'=> 'PWL',
        'NUSAWIRU'                => 'CJN',
        'PANGANDARAN'             => 'CJN',
        'KUALA LUMPUR'            => 'KUL',
        'KUALALUMPUR'             => 'KUL',
        'JOHOR BAHRU'             => 'JHB',
        'JOHORBAHRU'              => 'JHB',
        'SENAI'                   => 'JHB',
        'BANGKOK'                 => 'DMK',
        'DON MUEANG'              => 'DMK',
        'SUVARNABHUMI'            => 'BKK',
    ];

    /**
     * Resolve a station string into a clean 3-letter IATA code.
     */
    public function getIataCode(?string $station): string
    {
        if ($station === null || trim($station) === '' || $station === '—') {
            return '—';
        }

        $clean = strtoupper(trim($station));

        // 1. If already 3-letter uppercase IATA code (e.g. 'SUB', 'CGK', 'JOG', 'CJN')
        if (preg_match('/^[A-Z]{3}$/', $clean)) {
            return $clean;
        }

        // 2. Exact match against normalized dictionary
        if (isset($this->nameToIataMap[$clean])) {
            return $this->nameToIataMap[$clean];
        }

        // 3. Database lookup from `airports` table
        try {
            $airport = Airport::whereRaw('UPPER(iata_code) = ?', [$clean])
                ->orWhereRaw('UPPER(icao_code) = ?', [$clean])
                ->orWhereRaw('UPPER(city) = ?', [$clean])
                ->orWhereRaw('UPPER(name) = ?', [$clean])
                ->orWhereRaw('UPPER(province) = ?', [$clean])
                ->first();

            if ($airport && !empty($airport->iata_code)) {
                return strtoupper($airport->iata_code);
            }
        } catch (\Throwable $e) {
            // Ignore DB errors in fallback
        }

        // 4. Partial match against dictionary keys
        foreach ($this->nameToIataMap as $nameKey => $iata) {
            if (str_contains($clean, $nameKey) || str_contains($nameKey, $clean)) {
                return $iata;
            }
        }

        // 5. Unmapped airport fallback
        Log::warning("AirportResolverService: Unmapped station name '{$station}'");
        return $clean;
    }

    private static array $airportCache = [];
    private static array $airlineCache = [];

    /**
     * Resolve full airport label e.g., "SUB — Juanda" or "JOG — Adisutjipto".
     */
    public function getFullLabel(?string $station): string
    {
        if ($station === null || trim($station) === '' || $station === '—') {
            return '—';
        }

        $clean = strtoupper(trim($station));
        $iata = $this->getIataCode($clean);

        if (preg_match('/^[A-Z]{3}$/', $iata)) {
            if (!array_key_exists($iata, self::$airportCache)) {
                self::$airportCache[$iata] = Airport::findByIata($iata);
            }
            $airport = self::$airportCache[$iata];
            if ($airport) {
                return "{$iata} — {$airport->name}";
            }
            return "{$iata} — {$clean}";
        }

        return $clean;
    }

    /**
     * Resolve airline name from airline code or flight number prefix using Master Database.
     */
    public function getAirlineName(?string $airlineCode, ?string $flightNumber = null): string
    {
        $code = strtoupper(trim($airlineCode ?? ''));
        if (empty($code) && !empty($flightNumber)) {
            $code = strtoupper(substr(trim($flightNumber), 0, 2));
        }

        if (empty($code)) {
            return '—';
        }

        if (!array_key_exists($code, self::$airlineCache)) {
            self::$airlineCache[$code] = Airline::findByCode($code);
        }
        $airline = self::$airlineCache[$code];
        if ($airline) {
            return $airline->airline_name;
        }

        return $code;
    }

    /**
     * Normalize raw aircraft type strings to IATA Doc 8643 designators.
     */
    public function normalizeAircraftType(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return 'N/A';
        }

        $clean = strtoupper(trim($raw));

        // Static IATA Doc 8643 normalization map
        $map = [
            // Airbus
            'A 318'  => 'A318',
            'A 319'  => 'A319',
            'A 320'  => 'A320',
            'A 321'  => 'A321',
            'A 330'  => 'A330',
            'A 332'  => 'A332',
            'A 333'  => 'A333',
            'A 340'  => 'A340',
            'A 350'  => 'A350',
            'A 380'  => 'A388',
            'A380'   => 'A388',
            // Boeing
            'B 717'  => 'B717',
            'B 720'  => 'B720',
            'B 727'  => 'B727',
            'B 733'  => 'B733',
            'B 734'  => 'B734',
            'B 735'  => 'B735',
            'B 736'  => 'B736',
            'B 737'  => 'B737',
            'B 738'  => 'B738',
            'B 739'  => 'B739',
            'B 744'  => 'B744',
            'B 747'  => 'B747',
            'B 752'  => 'B752',
            'B 753'  => 'B753',
            'B 757'  => 'B757',
            'B 762'  => 'B762',
            'B 763'  => 'B763',
            'B 764'  => 'B764',
            'B 767'  => 'B767',
            'B 772'  => 'B772',
            'B 773'  => 'B773',
            'B 777'  => 'B777',
            'B 788'  => 'B788',
            'B 789'  => 'B789',
            'B 78X'  => 'B78X',
            // ATR
            'ATR 42' => 'AT43',
            'ATR 72' => 'AT72',
            'ATR-42' => 'AT43',
            'ATR-72' => 'AT72',
            'ATR42'  => 'AT43',
            'ATR72'  => 'AT72',
            // Bombardier / Canadair
            'CRJ'    => 'CRJ2',
            'CRJ 1'  => 'CRJ1',
            'CRJ 2'  => 'CRJ2',
            'CRJ 7'  => 'CRJ7',
            'CRJ 9'  => 'CRJ9',
            'Q 300'  => 'DH8C',
            'Q 400'  => 'DH8D',
            'DASH 8' => 'DH8A',
            // Embraer
            'E 170'  => 'E170',
            'E 175'  => 'E175',
            'E 190'  => 'E190',
            'E 195'  => 'E195',
            'ERJ'    => 'E135',
            // Dornier
            'DO 228' => 'D228',
            'DO228'  => 'D228',
            'D 228'  => 'D228',
            'D 328'  => 'D328',
            'D320'   => 'D328',
        ];

        if (isset($map[$clean])) {
            return $map[$clean];
        }

        $noSpace = str_replace(' ', '', $clean);
        if (preg_match('/^[A-Z]{1,3}\d{2,4}[A-Z]?$/', $noSpace)) {
            return $noSpace;
        }

        return $clean;
    }

    /**
     * Resolve timezone string for an airport object or IATA code.
     */
    public function getTimezoneForAirport(?Airport $airport, ?string $iata = null): string
    {
        if ($airport) {
            return $airport->getTimezone();
        }

        $iataCode = strtoupper(trim($iata ?? ''));
        if (!empty($iataCode)) {
            $ap = Airport::findByIata($iataCode);
            if ($ap) {
                return $ap->getTimezone();
            }

            $witaAirports = ['DPS', 'LOP', 'UPG', 'MDC', 'BPN', 'AAP', 'KOE', 'MOF', 'TMC', 'LBJ', 'TRK', 'PLW', 'KDI'];
            $witAirports  = ['DJJ', 'SOQ', 'AMQ', 'TIM', 'MKQ', 'BIK', 'MKW', 'NBX'];

            if (in_array($iataCode, $witaAirports, true)) {
                return 'Asia/Makassar';
            }
            if (in_array($iataCode, $witAirports, true)) {
                return 'Asia/Jayapura';
            }
        }

        return 'Asia/Jakarta';
    }

    /**
     * Convert airport-local time ("HH:MM" or "HH:MM:SS") to UTC time string ("HH:MM").
     *
     * Example: "06:55" in Asia/Jakarta (UTC+7) -> "23:55" (day before)
     */
    public function convertTimeToUtc(string $localTime, string $airportTimezone = 'Asia/Jakarta'): string
    {
        $time = trim($localTime);
        if (empty($time) || $time === '—' || !str_contains($time, ':')) {
            return $localTime;
        }

        try {
            $parts = explode(':', $time);
            $h = (int) ($parts[0] ?? 0);
            $m = (int) ($parts[1] ?? 0);

            $tz = new \DateTimeZone($airportTimezone);
            $dt = new \DateTime('2026-01-01 00:00:00', $tz);
            $offsetMinutes = (int) round($tz->getOffset($dt) / 60);

            $totalMinutes = ($h * 60 + $m) - $offsetMinutes;
            while ($totalMinutes < 0) {
                $totalMinutes += 1440;
            }
            $totalMinutes %= 1440;

            $utcH = (int) floor($totalMinutes / 60);
            $utcM = $totalMinutes % 60;

            return sprintf('%02d:%02d', $utcH, $utcM);
        } catch (\Throwable $e) {
            return substr($time, 0, 5);
        }
    }

    /**
     * Convert local hour (0..23) to UTC hour (0..23).
     */
    public function convertHourToUtc(int $localHour, string $airportTimezone = 'Asia/Jakarta'): int
    {
        try {
            $tz = new \DateTimeZone($airportTimezone);
            $dt = new \DateTime('2026-01-01 00:00:00', $tz);
            $offsetHours = (int) round($tz->getOffset($dt) / 3600);

            $utcHour = ($localHour - $offsetHours) % 24;
            if ($utcHour < 0) {
                $utcHour += 24;
            }
            return $utcHour;
        } catch (\Throwable $e) {
            return ($localHour - 7 + 24) % 24;
        }
    }

    /**
     * Convert UTC time ("HH:MM") to local time string ("HH:MM").
     */
    public function convertUtcToLocal(string $utcTime, string $airportTimezone = 'Asia/Jakarta'): string
    {
        $time = trim($utcTime);
        if (empty($time) || $time === '—' || !str_contains($time, ':')) {
            return $utcTime;
        }

        try {
            $parts = explode(':', $time);
            $h = (int) ($parts[0] ?? 0);
            $m = (int) ($parts[1] ?? 0);

            $tz = new \DateTimeZone($airportTimezone);
            $dt = new \DateTime('2026-01-01 00:00:00', $tz);
            $offsetMinutes = (int) round($tz->getOffset($dt) / 60);

            $totalMinutes = ($h * 60 + $m) + $offsetMinutes;
            while ($totalMinutes < 0) {
                $totalMinutes += 1440;
            }
            $totalMinutes %= 1440;

            $locH = (int) floor($totalMinutes / 60);
            $locM = $totalMinutes % 60;

            return sprintf('%02d:%02d', $locH, $locM);
        } catch (\Throwable $e) {
            return substr($time, 0, 5);
        }
    }
}
