<?php

namespace App\Services\Dau\Parsers;

class DAU10BParser extends DAU10Parser
{
    public function parse(string $filePath): array
    {
        $res = parent::parse($filePath);
        $res['report_type']  = 'DAU10B';
        $res['report_title'] = 'Data Angkutan Udara Jam Puncak Block On/Off (DAU-10B)';
        $res['report_code']  = 'DAU-10B';
        $res['is_block_on_off'] = true;

        // Enhance records with explicit Block On / Block Off aliases
        foreach ($res['records'] as &$rec) {
            $rec['block_on_aircraft']   = $rec['aircraft_arrival'];
            $rec['block_off_aircraft']  = $rec['aircraft_departure'];
            $rec['block_on_passenger']  = $rec['passenger_arrival'];
            $rec['block_off_passenger'] = $rec['passenger_departure'];
        }
        unset($rec);

        $res['dwell_intelligence'] = self::calculateApronDwell($res['records'] ?? []);

        return $res;
    }

    /**
     * Precompute Hourly Net Apron Delta (Block On - Block Off) and Accumulation Alert.
     */
    public static function calculateApronDwell(array $records): array
    {
        $hourlyMap = [];

        foreach ($records as $r) {
            $h = trim($r['hour'] ?? $r['period'] ?? '');
            if ($h === '') continue;

            if (!isset($hourlyMap[$h])) {
                $hourlyMap[$h] = [
                    'hour'      => $h,
                    'block_on'  => 0,
                    'block_off' => 0,
                    'pax_on'    => 0,
                    'pax_off'   => 0,
                ];
            }

            $hourlyMap[$h]['block_on']  += (int)($r['aircraft_arrival'] ?? $r['block_on_aircraft'] ?? 0);
            $hourlyMap[$h]['block_off'] += (int)($r['aircraft_departure'] ?? $r['block_off_aircraft'] ?? 0);
            $hourlyMap[$h]['pax_on']    += (int)($r['passenger_arrival'] ?? $r['block_on_passenger'] ?? 0);
            $hourlyMap[$h]['pax_off']   += (int)($r['passenger_departure'] ?? $r['block_off_passenger'] ?? 0);
        }

        $dwellHours = [];
        $runningCumulative = 0;
        $maxDelta = 0;
        $maxDeltaHour = '—';
        $alertCount = 0;

        foreach ($hourlyMap as $h => $d) {
            $on = $d['block_on'];
            $off = $d['block_off'];
            $delta = $on - $off;
            $runningCumulative += $delta;

            if ($delta > $maxDelta) {
                $maxDelta = $delta;
                $maxDeltaHour = $h;
            }

            $isAlert = ($delta >= 3);
            if ($isAlert) $alertCount++;

            $dwellHours[] = [
                'hour'       => $h,
                'block_on'   => $on,
                'block_off'  => $off,
                'net_delta'  => $delta,
                'cumulative' => $runningCumulative,
                'is_alert'   => $isAlert,
                'status'     => $delta > 0 ? 'ACCUMULATION' : ($delta < 0 ? 'CLEARANCE' : 'BALANCED'),
                'color'      => $delta > 0 ? '#f59e0b' : ($delta < 0 ? '#0ea5e9' : '#94a3b8'),
            ];
        }

        return [
            'hourly_deltas'            => $dwellHours,
            'max_accumulation_delta'   => $maxDelta,
            'peak_accumulation_delta'  => $maxDelta,
            'max_accumulation_hour'    => $maxDeltaHour,
            'peak_accumulation_hour'   => $maxDeltaHour,
            'has_accumulation_alert'   => $maxDelta >= 3,
            'alert_hours_count'        => $alertCount,
            'total_net_apron_balance'  => $runningCumulative,
        ];
    }
}

