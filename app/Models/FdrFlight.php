<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class FdrFlight extends Model
{
    protected $table = 'fdr_flights';

    protected $fillable = [
        'upload_id',
        'flight_date',
        'flight_number',
        'flight_number_base',
        'flight_suffix',
        'airline_code',
        'paired_flight_number',
        'aircraft_type',
        'registration_number',
        'leg',
        'direction',
        'movement_type',
        'origin_airport',
        'destination_airport',
        'report_airport',
        'traffic_type',
        'scheduled_time',
        'actual_time',
        'scheduled_datetime',
        'actual_datetime',
        'scheduled_time_str',
        'actual_time_str',
        'scheduled_hour',
        'actual_hour',
        'operational_hour',
        'delay_minutes',
        'status',
        'is_realized',
        'is_irregular',
        'passenger_capacity',
        'passenger_load',
        'load_factor',
        'pax_adult',
        'pax_child',
        'pax_infant',
        'pax_transit',
        'pax_transfer',
        'cargo_kg',
        'baggage_kg',
        'pos_kg',
        'stand',
        'runway',
        'mtow',
        'raw_data',
    ];

    protected $casts = [
        'flight_date'        => 'date',
        'scheduled_datetime' => 'datetime',
        'actual_datetime'    => 'datetime',
        'is_realized'        => 'boolean',
        'is_irregular'       => 'boolean',
        'passenger_capacity' => 'integer',
        'passenger_load'     => 'integer',
        'load_factor'        => 'float',
        'pax_adult'          => 'integer',
        'pax_child'          => 'integer',
        'pax_infant'         => 'integer',
        'pax_transit'        => 'integer',
        'pax_transfer'       => 'integer',
        'cargo_kg'           => 'float',
        'baggage_kg'         => 'float',
        'pos_kg'             => 'float',
        'delay_minutes'      => 'integer',
        'scheduled_hour'     => 'integer',
        'actual_hour'        => 'integer',
        'operational_hour'   => 'integer',
        'raw_data'           => 'array',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    /**
     * Scope to apply all standard FDR dashboard filters conditionally.
     */
    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        // 1. Airport filter
        $airport = strtoupper(trim($filters['airport'] ?? 'ALL'));
        if ($airport !== 'ALL' && $airport !== '') {
            $query->where('report_airport', $airport);
        }

        // 2. Leg / Direction filter
        $leg = strtoupper(trim($filters['leg'] ?? 'ALL'));
        if ($leg === 'ARR' || $leg === 'ARRIVAL') {
            $query->where('direction', 'ARRIVAL');
        } elseif ($leg === 'DEP' || $leg === 'DEPARTURE') {
            $query->where('direction', 'DEPARTURE');
        }

        // 3. Operator / Airline filter (matches either code like 'GA' or full name like 'Garuda Indonesia')
        $operator = trim($filters['operator'] ?? 'ALL');
        if ($operator !== 'ALL' && $operator !== '' && strcasecmp($operator, 'ALL AIRLINE') !== 0) {
            $codes = \App\Services\FlightDailyReport\FlightDailyReportFilter::resolveAirlineCode($operator);
            if (!empty($codes)) {
                $query->where(function (Builder $sub) use ($codes, $operator) {
                    $sub->whereIn('airline_code', $codes);
                    // Also check raw_data air_line or flight_number prefix
                    foreach ($codes as $c) {
                        $sub->orWhere('flight_number', 'LIKE', $c . '%');
                    }
                });
            } else {
                $query->where(function (Builder $sub) use ($operator) {
                    $sub->where('airline_code', strtoupper($operator))
                        ->orWhere('flight_number', 'ILIKE', $operator . '%');
                });
            }
        }

        // 4. Traffic filter
        $traffic = strtoupper(trim($filters['traffic'] ?? 'ALL'));
        if ($traffic === 'DOM' || $traffic === 'DOMESTIC') {
            $query->where('traffic_type', 'DOMESTIC');
        } elseif ($traffic === 'INTL' || $traffic === 'INTERNATIONAL' || $traffic === 'INT') {
            $query->where('traffic_type', 'INTERNATIONAL');
        }

        // 5. Realization filter
        $realization = strtoupper(trim($filters['realization'] ?? 'ALL'));
        if ($realization === 'YES' || $realization === 'REALIZED') {
            $query->whereRaw('is_realized = true');
        } elseif ($realization === 'NO' || $realization === 'UNREALIZED' || $realization === 'CANCELLED' || $realization === 'PLANNED') {
            $query->whereRaw('is_realized = false');
        }

        // 6. Status filter
        $status = strtoupper(trim($filters['status'] ?? 'ALL'));
        if ($status !== 'ALL' && $status !== '') {
            if ($status === 'ON_TIME' || $status === 'ONTIME') {
                $query->where('delay_minutes', '<=', 15)->whereRaw('is_realized = true');
            } elseif ($status === 'DELAYED' || $status === 'DELAY') {
                $query->where('delay_minutes', '>', 15)->whereRaw('is_realized = true');
            } elseif ($status === 'CANCELLED' || $status === 'CANCEL') {
                $query->whereRaw('is_realized = false');
            } else {
                $query->where('status', $status);
            }
        }

        // 7. Date Scope filter
        $dateScope = strtoupper(trim($filters['date_scope'] ?? 'ALL_PERIOD'));
        $analysisLevel = strtoupper(trim($filters['analysis_level'] ?? ''));
        $analysisDate = trim($filters['analysis_date'] ?? '');
        $startDate = trim($filters['start_date'] ?? '');
        $endDate = trim($filters['end_date'] ?? '');
        $analysisMonth = trim($filters['analysis_month'] ?? '');
        $analysisYear = trim($filters['analysis_year'] ?? '');

        if ($dateScope === 'ALL_PERIOD' || $dateScope === 'ALL' || $dateScope === 'FULL_RANGE') {
            // Full dataset view: no date restriction
        } elseif ($dateScope === 'DAY' && !empty($analysisDate) && !in_array(strtoupper($analysisDate), ['ALL', 'ALL_PERIOD', 'FULL', 'FULL_RANGE'], true)) {
            $stdDate = \App\Services\FlightDailyReport\FlightDailyReportFilter::standardizeDate($analysisDate);
            if ($stdDate !== 'N/A') {
                $query->where('flight_date', $stdDate);
            }
        } elseif ($dateScope === 'RANGE' && (!empty($startDate) || !empty($endDate))) {
            if (!empty($startDate)) {
                $stdStart = \App\Services\FlightDailyReport\FlightDailyReportFilter::standardizeDate($startDate);
                if ($stdStart !== 'N/A') $query->where('flight_date', '>=', $stdStart);
            }
            if (!empty($endDate)) {
                $stdEnd = \App\Services\FlightDailyReport\FlightDailyReportFilter::standardizeDate($endDate);
                if ($stdEnd !== 'N/A') $query->where('flight_date', '<=', $stdEnd);
            }
        } elseif (($dateScope === 'MONTH' || $analysisLevel === 'MONTHLY') && !empty($analysisMonth)) {
            $query->whereRaw("TO_CHAR(flight_date, 'YYYY-MM') = ?", [$analysisMonth]);
        } elseif (($dateScope === 'YEAR' || $analysisLevel === 'YEARLY') && !empty($analysisYear)) {
            $query->whereRaw("TO_CHAR(flight_date, 'YYYY') = ?", [$analysisYear]);
        }

        // 8. Flight Number & Suffix
        $flightNo = strtoupper(trim($filters['flight_no'] ?? ''));
        if ($flightNo !== '') {
            $query->where(function (Builder $sub) use ($flightNo) {
                $sub->where('flight_number', 'ILIKE', '%' . $flightNo . '%')
                    ->orWhere('flight_number_base', 'ILIKE', '%' . $flightNo . '%');
            });
        }
        $suffix = strtoupper(trim($filters['suffix'] ?? ''));
        if ($suffix !== '') {
            $query->where('flight_suffix', $suffix);
        }

        // 9. Free-text search
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $s = '%' . strtolower($search) . '%';
            $query->where(function (Builder $sub) use ($s) {
                $sub->whereRaw('LOWER(flight_number) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(airline_code) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(COALESCE(registration_number, \'\')) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(COALESCE(origin_airport, \'\')) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(COALESCE(destination_airport, \'\')) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(COALESCE(stand, \'\')) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(COALESCE(runway, \'\')) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(COALESCE(aircraft_type, \'\')) LIKE ?', [$s]);
            });
        }

        return $query;
    }

    /**
     * Transform database record to the array format expected by blade & JS.
     */
    public function toFdrArray(): array
    {
        $raw = $this->raw_data ?: [];
        return array_merge([
            'id'                  => $this->id,
            'index'               => $raw['index'] ?? $this->id,
            'row_type'            => 'MOVEMENT',
            'air_line'            => $this->airline_code,
            'flight_no'           => $this->flight_number,
            'flight_no_base'      => $this->flight_number_base ?: $this->flight_number,
            'flight_suffix'       => $this->flight_suffix ?: '',
            'paired_no'           => $this->paired_flight_number ?: 'N/A',
            'desc'                => $this->aircraft_type ?: 'N/A',
            'sibt'                => $raw['sibt'] ?? ($this->direction === 'ARRIVAL' ? $this->scheduled_time_str : 'N/A'),
            'sobt'                => $raw['sobt'] ?? ($this->direction === 'DEPARTURE' ? $this->scheduled_time_str : 'N/A'),
            'aibt'                => $raw['aibt'] ?? ($this->direction === 'ARRIVAL' ? $this->actual_time_str : 'N/A'),
            'aobt'                => $raw['aobt'] ?? ($this->direction === 'DEPARTURE' ? $this->actual_time_str : 'N/A'),
            'sched_display'       => $this->scheduled_time_str ?: 'N/A',
            'actual_display'      => $this->actual_time_str ?: 'N/A',
            'scheduled_datetime'  => $this->scheduled_datetime ? $this->scheduled_datetime->format('Y-m-d H:i:s') : null,
            'actual_datetime'     => $this->actual_datetime ? $this->actual_datetime->format('Y-m-d H:i:s') : null,
            'operational_date'    => $this->flight_date ? $this->flight_date->format('Y-m-d') : null,
            'flight_date'         => $this->flight_date ? $this->flight_date->format('Y-m-d') : null,
            'operational_hour'    => $this->operational_hour,
            'hour'                => $this->operational_hour,
            'scheduled_hour'      => $this->scheduled_hour,
            'actual_hour'         => $this->actual_hour,
            'leg'                 => $this->leg ?: ($this->direction === 'ARRIVAL' ? 'A SCHED' : 'D SCHED'),
            'direction'           => $this->direction,
            'city_1'              => $this->origin_airport ?: 'N/A',
            'city_2'              => $this->destination_airport ?: 'N/A',
            'route'               => ($this->origin_airport && $this->destination_airport) ? "{$this->origin_airport} → {$this->destination_airport}" : 'N/A',
            'traffic'             => $this->traffic_type,
            'route_type'          => $this->traffic_type,
            'mtow'                => $this->mtow ?: 'N/A',
            'reg_no'              => $this->registration_number ?: 'N/A',
            'cap'                 => $this->passenger_capacity,
            'load'                => $this->passenger_load,
            'load_factor'         => $this->load_factor !== null ? (float)$this->load_factor : 'N/A',
            'adult'               => $this->pax_adult,
            'child'               => $this->pax_child,
            'infant'              => $this->pax_infant,
            'transit'             => $this->pax_transit,
            'transfer'            => $this->pax_transfer,
            'cargo_kg'            => (float)$this->cargo_kg,
            'baggage_kg'          => (float)$this->baggage_kg,
            'pos_kg'              => (float)$this->pos_kg,
            'stand'               => $this->stand ?: 'N/A',
            'runway'              => $this->runway ?: 'N/A',
            'status'              => $this->status,
            'delay_minutes'       => $this->delay_minutes,
            'is_realized'         => $this->is_realized,
            'is_irregular'        => $this->is_irregular,
            'report_airport'      => $this->report_airport,
        ], $raw);
    }
}
