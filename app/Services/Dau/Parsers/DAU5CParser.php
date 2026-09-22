<?php

namespace App\Services\Dau\Parsers;

class DAU5CParser extends DAU5Parser
{
    public function parse(string $filePath): array
    {
        $res = parent::parse($filePath);
        $res['report_type']  = 'DAU5C';
        $res['report_title'] = 'Data Angkutan Udara Menurut Airline/Operator (DAU-05C)';
        $res['report_code']  = 'DAU-05C';

        $res['carrier_efficiency'] = self::calculateCarrierEfficiency($res['records'] ?? []);

        return $res;
    }

    /**
     * Precompute 4-Quadrant Carrier Efficiency (Capacity vs Load Factor vs Movements)
     * with Strict Fallback when Seat Capacity is not present in OASYS export.
     */
    public static function calculateCarrierEfficiency(array $records): array
    {
        $hasSeatCap = false;
        $carriers = [];

        foreach ($records as $r) {
            $seatCap = (int)($r['seat_capacity'] ?? 0);
            if ($seatCap > 0) {
                $hasSeatCap = true;
            }
        }

        if (!$hasSeatCap) {
            return [
                'has_seat_capacity' => false,
                'message'           => 'Data Kapasitas Tempat Duduk (Seat Capacity) tidak tercatat dalam arsip DAU-05C.',
                'reason'            => 'Kolom kapasitas tempat duduk / seat capacity tidak tersedia pada format laporan DAU-05C OASYS bandara.',
                'carriers'          => [],
            ];
        }

        foreach ($records as $r) {
            $seatCap = (int)($r['seat_capacity'] ?? 0);
            $pax = (int)($r['passenger_total'] ?? 0);
            $mv = (int)($r['aircraft_total'] ?? 0);
            $lf = $seatCap > 0 ? round(($pax / $seatCap) * 100, 1) : 0.0;

            // Quadrant determination (threshold: 150 seats, 70% LF)
            if ($seatCap >= 150 && $lf >= 70) {
                $quadrant = 'Q1 (High Cap / High LF)';
            } elseif ($seatCap < 150 && $lf >= 70) {
                $quadrant = 'Q2 (Low Cap / High LF)';
            } elseif ($seatCap >= 150 && $lf < 70) {
                $quadrant = 'Q3 (High Cap / Low LF - Slot Waste)';
            } else {
                $quadrant = 'Q4 (Underperforming)';
            }

            $carriers[] = [
                'airline'       => $r['airline'],
                'seat_capacity' => $seatCap,
                'passengers'    => $pax,
                'movements'     => $mv,
                'load_factor'   => $lf,
                'quadrant'      => $quadrant,
            ];
        }

        return [
            'has_seat_capacity' => true,
            'carriers'          => $carriers,
        ];
    }
}

