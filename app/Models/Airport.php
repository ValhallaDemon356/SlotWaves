<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Airport extends Model
{
    public const MANAGEMENT_INJOURNEY    = 'INJOURNEY';
    public const MANAGEMENT_ANGKASA_PURA = 'INJOURNEY'; // Alias for compatibility
    public const MANAGEMENT_UPBU_HUBUD   = 'UPBU_HUBUD';
    public const MANAGEMENT_UPT_HUBUD    = 'UPBU_HUBUD'; // Alias
    public const MANAGEMENT_UPTD_PEMDA   = 'UPTD_PEMDA';
    public const MANAGEMENT_UPT_PEMDA    = 'UPTD_PEMDA'; // Alias
    public const MANAGEMENT_TNI          = 'TNI';
    public const MANAGEMENT_MISSIONARIS  = 'MISSIONARIS';
    public const MANAGEMENT_BUMN         = 'BUMN';
    public const MANAGEMENT_SWASTA       = 'SWASTA';
    public const MANAGEMENT_MASYARAKAT   = 'MASYARAKAT';
    public const MANAGEMENT_OTHER        = 'OTHER';

    public const SOURCE_INJOURNEY  = 'INJOURNEY';
    public const SOURCE_HUBUD      = 'HUBUD';
    public const SOURCE_REFERENCE  = 'REFERENCE';
    public const SOURCE_UNVERIFIED = 'UNVERIFIED';

    protected $fillable = [
        'bandara_id',
        'iata_code',
        'icao_code',
        'name',
        'city',
        'area',
        'province',
        'region',
        'country',
        'latitude',
        'longitude',
        'airport_type',
        'management_type',
        'management_name',
        'usage_type',
        'classification',
        'status',
        'operating_status',
        'aircraft_capacity',
        'arrival_capacity',
        'departure_capacity',
        'timezone',
        'ops_start_time',
        'ops_end_time',
        'is_international',
        'is_active',
        'data_incomplete',
        'data_source',
        'source',
        'source_url',
        'source_checked_at',
    ];

    protected $casts = [
        'bandara_id'         => 'integer',
        'aircraft_capacity'  => 'integer',
        'arrival_capacity'   => 'integer',
        'departure_capacity' => 'integer',
        'latitude'          => 'float',
        'longitude'         => 'float',
        'is_international'  => 'boolean',
        'is_active'         => 'boolean',
        'data_incomplete'   => 'boolean',
        'source_checked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Airport $airport) {
            // Normalize IATA & ICAO to uppercase
            if (!empty($airport->iata_code)) {
                $airport->iata_code = strtoupper(trim($airport->iata_code));
                if ($airport->iata_code === '-' || $airport->iata_code === '') {
                    $airport->iata_code = null;
                }
            } else {
                $airport->iata_code = null;
            }

            if (!empty($airport->icao_code)) {
                $airport->icao_code = strtoupper(trim($airport->icao_code));
                if ($airport->icao_code === '-' || $airport->icao_code === '') {
                    $airport->icao_code = null;
                }
            } else {
                $airport->icao_code = null;
            }

            // Normalize management_type
            $rawMgmt = strtoupper(str_replace(['.', ' ', '/'], '_', $airport->management_type ?? ''));
            $rawName = strtoupper(trim($airport->management_name ?? ''));

            if (str_contains($rawMgmt, 'INJOURNEY') || str_contains($rawMgmt, 'ANGKASA') || str_contains($rawName, 'ANGKASA PURA')) {
                $airport->management_type = self::MANAGEMENT_INJOURNEY;
                $airport->management_name = 'PT. Angkasa Pura Indonesia';
            } elseif (str_contains($rawMgmt, 'PEMDA') || str_contains($rawMgmt, 'DAERAH') || str_contains($rawMgmt, 'UPTD')) {
                $airport->management_type = self::MANAGEMENT_UPTD_PEMDA;
                $airport->management_name = 'UPT Daerah/Pemda';
            } elseif (str_contains($rawMgmt, 'DITJEN') || str_contains($rawMgmt, 'HUBUD') || str_contains($rawMgmt, 'UPBU')) {
                $airport->management_type = self::MANAGEMENT_UPBU_HUBUD;
                $airport->management_name = 'UPT Ditjen Hubud';
            } elseif (str_contains($rawMgmt, 'TNI') || str_contains($rawName, 'TNI')) {
                $airport->management_type = self::MANAGEMENT_TNI;
                $airport->management_name = 'TNI';
            } elseif (str_contains($rawMgmt, 'MISSION') || str_contains($rawMgmt, 'MISION') || str_contains($rawName, 'MISSION') || str_contains($rawName, 'MISION')) {
                $airport->management_type = self::MANAGEMENT_MISSIONARIS;
                $airport->management_name = 'Missionaris';
            } elseif (str_contains($rawMgmt, 'BUMN') || str_contains($rawName, 'BUMN')) {
                $airport->management_type = self::MANAGEMENT_BUMN;
                $airport->management_name = 'BUMN';
            } elseif (str_contains($rawMgmt, 'SWASTA') || str_contains($rawName, 'SWASTA')) {
                $airport->management_type = self::MANAGEMENT_SWASTA;
                $airport->management_name = 'Swasta';
            } elseif (str_contains($rawMgmt, 'MASYARAKAT') || str_contains($rawName, 'MASYARAKAT')) {
                $airport->management_type = self::MANAGEMENT_MASYARAKAT;
                $airport->management_name = 'Masyarakat';
            } else {
                $airport->management_type = self::MANAGEMENT_OTHER;
                if (empty($airport->management_name)) {
                    $airport->management_name = 'Other';
                }
            }

            // Strict Region Invariant:
            // Non-InJourney airports MUST NOT have a region assigned!
            if ($airport->management_type !== self::MANAGEMENT_INJOURNEY) {
                $airport->region = null;
            }

            // Sync data_source if not set
            if (empty($airport->data_source)) {
                $airport->data_source = $airport->source ?: self::SOURCE_UNVERIFIED;
            }
        });
    }

    /**
     * Find an airport by IATA code (case-insensitive).
     */
    public static function findByIata(?string $iata): ?self
    {
        if (empty($iata) || trim($iata) === '-' || trim($iata) === '') {
            return null;
        }
        return static::whereRaw('UPPER(iata_code) = ?', [strtoupper(trim($iata))])->first();
    }

    /**
     * Find an airport by ICAO code (case-insensitive).
     */
    public static function findByIcao(?string $icao): ?self
    {
        if (empty($icao) || trim($icao) === '-' || trim($icao) === '') {
            return null;
        }
        return static::whereRaw('UPPER(icao_code) = ?', [strtoupper(trim($icao))])->first();
    }

    /**
     * Find an airport by Hubud BandaraID.
     */
    public static function findByBandaraId(?int $bandaraId): ?self
    {
        if (empty($bandaraId)) {
            return null;
        }
        return static::where('bandara_id', $bandaraId)->first();
    }

    public function isInJourney(): bool
    {
        return $this->management_type === self::MANAGEMENT_INJOURNEY;
    }

    public function isAngkasaPura(): bool
    {
        return $this->management_type === self::MANAGEMENT_INJOURNEY;
    }

    public function isUpbuHubud(): bool
    {
        return $this->management_type === self::MANAGEMENT_UPBU_HUBUD;
    }

    public function isUptDitjenHubud(): bool
    {
        return $this->management_type === self::MANAGEMENT_UPBU_HUBUD;
    }

    public function isUptdPemda(): bool
    {
        return $this->management_type === self::MANAGEMENT_UPTD_PEMDA;
    }

    public function isUptPemda(): bool
    {
        return $this->management_type === self::MANAGEMENT_UPTD_PEMDA;
    }

    public function isTni(): bool
    {
        return $this->management_type === self::MANAGEMENT_TNI;
    }

    public function isMissionaris(): bool
    {
        return $this->management_type === self::MANAGEMENT_MISSIONARIS;
    }

    public function isBumn(): bool
    {
        return $this->management_type === self::MANAGEMENT_BUMN;
    }

    public function isSwasta(): bool
    {
        return $this->management_type === self::MANAGEMENT_SWASTA;
    }

    public function isMasyarakat(): bool
    {
        return $this->management_type === self::MANAGEMENT_MASYARAKAT;
    }

    /**
     * Return the display label in the format "Bandar Udara [Name] — [IATA/ICAO]"
     */
    public function getDisplayLabel(): string
    {
        $code = $this->iata_code ?: ($this->icao_code ?: "ID:{$this->bandara_id}");
        return "Bandar Udara {$this->name} — {$code}";
    }

    /**
     * Return configured arrival aircraft capacity or default 6.
     */
    public function getEffectiveArrivalCapacity(): int
    {
        return $this->arrival_capacity ?: ($this->aircraft_capacity ?: (int) config('slotwaves.nac', 6));
    }

    /**
     * Return configured departure aircraft capacity or default 6.
     */
    public function getEffectiveDepartureCapacity(): int
    {
        return $this->departure_capacity ?: ($this->aircraft_capacity ?: (int) config('slotwaves.nac', 6));
    }

    /**
     * Return configured aircraft capacity or default 6.
     */
    public function getEffectiveCapacity(): int
    {
        return max($this->getEffectiveArrivalCapacity(), $this->getEffectiveDepartureCapacity());
    }

    /**
     * Return airport's canonical local timezone identifier.
     */
    public function getTimezone(): string
    {
        if (!empty($this->timezone)) {
            return $this->timezone;
        }

        // Automatic fallback based on province / IATA
        $iata = strtoupper($this->iata_code ?? '');
        $witaAirports = ['DPS', 'LOP', 'UPG', 'MDC', 'BPN', 'AAP', 'KOE', 'MOF', 'TMC', 'LBJ', 'TRK', 'PLW', 'KDI'];
        $witAirports  = ['DJJ', 'SOQ', 'AMQ', 'TIM', 'MKQ', 'BIK', 'MKW', 'NBX'];

        if (in_array($iata, $witaAirports, true)) {
            return 'Asia/Makassar';
        }
        if (in_array($iata, $witAirports, true)) {
            return 'Asia/Jayapura';
        }

        return 'Asia/Jakarta';
    }

    /**
     * Return minutes offset from UTC (e.g. +420 for Asia/Jakarta, +480 for Asia/Makassar, +540 for Asia/Jayapura).
     */
    public function getTimezoneOffsetMinutes(): int
    {
        try {
            $tz = new \DateTimeZone($this->getTimezone());
            $dt = new \DateTime('now', $tz);
            return (int) round($tz->getOffset($dt) / 60);
        } catch (\Throwable $e) {
            return 420; // Default WIB (+7 hours)
        }
    }

    /**
     * Return timezone code abbreviation (WIB, WITA, WIT, UTC).
     */
    public function getTimezoneAbbreviation(): string
    {
        $tz = $this->getTimezone();
        return match ($tz) {
            'Asia/Jakarta', 'Asia/Pontianak' => 'WIB',
            'Asia/Makassar', 'Asia/Ujung_Pandang' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            'UTC' => 'UTC',
            default => 'WIB',
        };
    }

    public function getOpsStartHour(): int
    {
        if (!empty($this->ops_start_time) && str_contains($this->ops_start_time, ':')) {
            return (int) explode(':', $this->ops_start_time)[0];
        }
        return 6;
    }

    public function getOpsEndHour(): int
    {
        if (!empty($this->ops_end_time) && str_contains($this->ops_end_time, ':')) {
            $h = (int) explode(':', $this->ops_end_time)[0];
            return $h === 0 ? 24 : $h;
        }
        return 20;
    }

    public function departureFlights(): HasMany
    {
        return $this->hasMany(Flight::class, 'origin', 'iata_code');
    }

    public function arrivalFlights(): HasMany
    {
        return $this->hasMany(Flight::class, 'destination', 'iata_code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInternational($query)
    {
        return $query->where('is_international', true);
    }

    public function scopeDomestic($query)
    {
        return $query->where('is_international', false);
    }

    public function scopeInjourney($query)
    {
        return $query->where('management_type', self::MANAGEMENT_INJOURNEY);
    }

    public function scopeAngkasaPura($query)
    {
        return $query->where('management_type', self::MANAGEMENT_INJOURNEY);
    }

    public function scopeUpbuHubud($query)
    {
        return $query->where('management_type', self::MANAGEMENT_UPBU_HUBUD);
    }

    public function scopeUptDitjenHubud($query)
    {
        return $query->where('management_type', self::MANAGEMENT_UPBU_HUBUD);
    }

    public function scopeUptdPemda($query)
    {
        return $query->where('management_type', self::MANAGEMENT_UPTD_PEMDA);
    }

    public function scopeUptPemda($query)
    {
        return $query->where('management_type', self::MANAGEMENT_UPTD_PEMDA);
    }

    public function scopeTni($query)
    {
        return $query->where('management_type', self::MANAGEMENT_TNI);
    }

    public function scopeMissionaris($query)
    {
        return $query->where('management_type', self::MANAGEMENT_MISSIONARIS);
    }

    public function scopeBumn($query)
    {
        return $query->where('management_type', self::MANAGEMENT_BUMN);
    }

    public function scopeSwasta($query)
    {
        return $query->where('management_type', self::MANAGEMENT_SWASTA);
    }

    public function scopeMasyarakat($query)
    {
        return $query->where('management_type', self::MANAGEMENT_MASYARAKAT);
    }

    public function scopeByRegion($query, string $region)
    {
        $reg = trim($region);
        $romanMap = [
            '1' => 'Region 1', 'I' => 'Region 1', 'REGION 1' => 'Region 1', 'REGION I' => 'Region 1',
            '2' => 'Region 2', 'II' => 'Region 2', 'REGION 2' => 'Region 2', 'REGION II' => 'Region 2',
            '3' => 'Region 3', 'III' => 'Region 3', 'REGION 3' => 'Region 3', 'REGION III' => 'Region 3',
            '4' => 'Region 4', 'IV' => 'Region 4', 'REGION 4' => 'Region 4', 'REGION IV' => 'Region 4',
            '5' => 'Region 5', 'V' => 'Region 5', 'REGION 5' => 'Region 5', 'REGION V' => 'Region 5',
            '6' => 'Region 6', 'VI' => 'Region 6', 'REGION 6' => 'Region 6', 'REGION VI' => 'Region 6',
        ];
        $upper = strtoupper($reg);
        $canonical = $romanMap[$upper] ?? $reg;
        $alt = match ($canonical) {
            'Region 1' => 'Region I',
            'Region 2' => 'Region II',
            'Region 3' => 'Region III',
            'Region 4' => 'Region IV',
            'Region 5' => 'Region V',
            'Region 6' => 'Region VI',
            default => $canonical,
        };

        return $query->where(function ($q) use ($canonical, $alt, $reg) {
            $q->where('region', $canonical)
              ->orWhere('region', $alt)
              ->orWhere('region', $reg);
        });
    }

    public function scopeByManagement($query, string $type)
    {
        $normalized = strtoupper(str_replace(['.', ' ', '/'], '_', trim($type)));
        if (str_contains($normalized, 'INJOURNEY') || str_contains($normalized, 'ANGKASA')) {
            return $query->where('management_type', self::MANAGEMENT_INJOURNEY);
        } elseif (str_contains($normalized, 'PEMDA') || str_contains($normalized, 'DAERAH') || str_contains($normalized, 'UPTD')) {
            return $query->where('management_type', self::MANAGEMENT_UPTD_PEMDA);
        } elseif (str_contains($normalized, 'DITJEN') || str_contains($normalized, 'HUBUD') || str_contains($normalized, 'UPBU')) {
            return $query->where('management_type', self::MANAGEMENT_UPBU_HUBUD);
        } elseif (str_contains($normalized, 'TNI')) {
            return $query->where('management_type', self::MANAGEMENT_TNI);
        } elseif (str_contains($normalized, 'MISSION') || str_contains($normalized, 'MISION')) {
            return $query->where('management_type', self::MANAGEMENT_MISSIONARIS);
        } elseif (str_contains($normalized, 'BUMN')) {
            return $query->where('management_type', self::MANAGEMENT_BUMN);
        } elseif (str_contains($normalized, 'SWASTA')) {
            return $query->where('management_type', self::MANAGEMENT_SWASTA);
        } elseif (str_contains($normalized, 'MASYARAKAT')) {
            return $query->where('management_type', self::MANAGEMENT_MASYARAKAT);
        } elseif (str_contains($normalized, 'OTHER')) {
            return $query->where('management_type', self::MANAGEMENT_OTHER);
        }
        return $query->where('management_type', $type);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where(function ($q) use ($source) {
            $q->where('data_source', $source)
              ->orWhere('source', $source);
        });
    }
}
