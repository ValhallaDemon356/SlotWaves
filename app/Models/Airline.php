<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Airline extends Model
{
    protected $fillable = [
        'airline_code',
        'airline_name',
        'organization_code',
        'category',
        'country',
        'status',
        'is_active',
        'source',
        'source_url',
        'source_checked_at',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'source_checked_at' => 'datetime',
    ];

    /**
     * Find an airline by 2-letter IATA flight code (case-insensitive).
     */
    public static function findByCode(string $code): ?self
    {
        return static::whereRaw('UPPER(airline_code) = ?', [strtoupper(trim($code))])->first();
    }

    /**
     * Find an airline by Hubud Organization Code (e.g. AOC 121-001).
     */
    public static function findByOrgCode(string $orgCode): ?self
    {
        return static::whereRaw('UPPER(organization_code) = ?', [strtoupper(trim($orgCode))])->first();
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class, 'airline_code', 'airline_code');
    }

    public function scopeDomestic($query)
    {
        return $query->where('category', 'domestic');
    }

    public function scopeInternational($query)
    {
        return $query->where('category', 'international');
    }

    public function scopeCargo($query)
    {
        return $query->where('category', 'cargo');
    }

    public function scopeCharter($query)
    {
        return $query->where('category', 'charter');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }
}
