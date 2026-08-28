<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Flight extends Model
{
    protected $fillable = [
        'upload_id',
        'flight_number',
        'airline_code',
        'aircraft_type',
        'origin',
        'destination',
        'scheduled_time',
        'operating_days',
        'direction',
        'traffic_type',
        'flight_type',
        'slot_status',
        'parse_status',
        'validation_status',
        'validation_errors',
        'paired_flight_id',
        'remarks',
        'raw_data',
    ];

    protected $casts = [
        'validation_errors' => 'array',
    ];

    public function scopeValidated($query)
    {
        return $query->where('validation_status', 'valid')->where('parse_status', 'valid');
    }

    public function scopeArrivals($query)
    {
        return $query->where('direction', 'arrival');
    }

    public function scopeDepartures($query)
    {
        return $query->where('direction', 'departure');
    }

    public function scopeDomestic($query)
    {
        return $query->where('traffic_type', 'domestic');
    }

    public function scopeInternational($query)
    {
        return $query->where('traffic_type', 'international');
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function timelinePosition(): HasOne
    {
        return $this->hasOne(TimelinePosition::class);
    }

    public function pairedFlight(): BelongsTo
    {
        return $this->belongsTo(Flight::class, 'paired_flight_id');
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_code', 'airline_code');
    }

    public function originAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'origin', 'iata_code');
    }

    public function destinationAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'destination', 'iata_code');
    }

    /**
     * Resolve airline name from relational Airline model or fallback.
     */
    public function getAirlineNameAttribute(): string
    {
        if ($this->airline) {
            return $this->airline->airline_name;
        }
        
        $prefix = strtoupper(substr($this->flight_number ?? '', 0, 2));
        $airline = Airline::findByCode($prefix);
        if ($airline) {
            return $airline->airline_name;
        }

        return $this->airline_code ?: 'Unknown Airline';
    }
}
