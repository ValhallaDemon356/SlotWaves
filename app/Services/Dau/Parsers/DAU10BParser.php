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

        return $res;
    }
}
