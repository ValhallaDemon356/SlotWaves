<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SlotWaves Airport Operational Capacity Configuration
    |--------------------------------------------------------------------------
    |
    | Defines operational thresholds for airport runway, apron, and hourly NAC
    | demand calculations. Used on the Generated Schedule Dashboard.
    |
    */

    'nac' => env('SLOTWAVES_NAC', 6),

    // Aircraft Category Capacity Units (Narrow Body = 1, Wide Body = 2, Cargo Narrow = 1, Cargo Wide = 2)
    'aircraft_capacity_units' => [
        'NB'      => 1,
        'WB'      => 2,
        'CNB'     => 1,
        'CWB'     => 2,
        'UNKNOWN' => 1,
    ],

    'hourly_slot_capacity' => env('SLOTWAVES_HOURLY_SLOT_CAPACITY', 6),

    'runway_regular_capacity' => env('SLOTWAVES_RUNWAY_REGULAR_CAPACITY', 8),

    'runway_irregular_capacity' => env('SLOTWAVES_RUNWAY_IRREGULAR_CAPACITY', 2),

    'apron_narrow_body_capacity' => env('SLOTWAVES_APRON_NARROW_BODY_CAPACITY', 6),

    // Fractional threshold to trigger 'NEAR CAPACITY' status (0.75 = 75%)
    'warning_threshold' => env('SLOTWAVES_WARNING_THRESHOLD', 0.75),

];
