<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightPairing extends Model
{
    protected $fillable = [
        'upload_id',
        'arrival_flight_id',
        'departure_flight_id',
        'rotation_id',
        'operating_day',
        'rotation_status',
        'turnaround_minutes',
        'confidence',
        'remarks',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function arrivalFlight(): BelongsTo
    {
        return $this->belongsTo(Flight::class, 'arrival_flight_id');
    }

    public function departureFlight(): BelongsTo
    {
        return $this->belongsTo(Flight::class, 'departure_flight_id');
    }
}
