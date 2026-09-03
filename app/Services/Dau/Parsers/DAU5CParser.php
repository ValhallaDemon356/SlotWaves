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
        return $res;
    }
}
