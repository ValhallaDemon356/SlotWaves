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
        return $res;
    }
}
