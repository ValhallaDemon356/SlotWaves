<?php

namespace App\Services\Dau\Parsers;

class DAU6Parser extends BaseDauParser
{
    public function parse(string $filePath): array
    {
        $rawRows = $this->extractRawTable($filePath);
        $meta = $this->extractMetadata($filePath, $rawRows);

        $records = [];
        $summary = [
            'total_movements'    => 0,
            'aircraft_arrival'   => 0,
            'aircraft_departure' => 0,
            'passenger_arrival'  => 0,
            'passenger_departure'=> 0,
            'passenger_transit'  => 0,
            'passenger_transfer' => 0,
            'passenger_total'    => 0,
            'crew_total'         => 0,
            'baggage_total'      => 0,
            'cargo_total'        => 0,
            'pos_total'          => 0,
        ];

        $dataStartIndex = 3;
        foreach ($rawRows as $idx => $row) {
            if (isset($row[0]) && is_numeric($row[0]) && (int)$row[0] === 1 && !empty($row[1]) && !is_numeric($row[1])) {
                $dataStartIndex = $idx;
                break;
            }
        }

        for ($i = $dataStartIndex; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if (empty($row) || count($row) < 8) continue;
            $first = $this->toStr($row[0] ?? '');
            $second = $this->toStr($row[1] ?? '');

            if (stripos($first, 'TOTAL') !== false || stripos($second, 'TOTAL') !== false) {
                continue;
            }

            if (!is_numeric($first) || is_numeric($second)) continue;

            $arrAc  = $this->toInt($row[2] ?? 0);
            $depAc  = $this->toInt($row[3] ?? 0);
            $totAc  = $this->toInt($row[4] ?? ($arrAc + $depAc));
            $arrPax = $this->toInt($row[5] ?? 0);
            $depPax = $this->toInt($row[6] ?? 0);
            $transPax = $this->toInt($row[7] ?? 0);
            $trfPax   = $this->toInt($row[8] ?? 0);
            $totPax   = $this->toInt($row[9] ?? ($arrPax + $depPax + $transPax + $trfPax));
            $crew     = $this->toInt($row[10] ?? 0);
            $exCrew   = $this->toInt($row[11] ?? 0);
            $totCrew  = $this->toInt($row[12] ?? ($crew + $exCrew));
            $baggage  = $this->toInt($row[15] ?? 0);
            $cargo    = $this->toInt($row[18] ?? 0);
            $pos      = $this->toInt($row[21] ?? 0);

            // Deterministic classification based on ICAO aircraft standards
            $upperType = strtoupper($second);
            $category = 'Narrow Body';
            $wtc = 'Medium';
            $icaoCode = 'Code C';

            if (preg_match('/(330|340|350|380|747|767|777|787)/', $upperType)) {
                $category = 'Wide Body';
                $wtc = 'Heavy';
                $icaoCode = 'Code D/E/F';
            } elseif (preg_match('/(ATR|CRJ|ERJ|EMB|FOKKER|DASH|DHC|Q400|PROP)/', $upperType)) {
                $category = 'Regional / Turboprop';
                $wtc = 'Medium';
                $icaoCode = 'Code A/B';
            } elseif (preg_match('/(C208|CESSNA|OTTER|CARAVAN|PILATUS|BEECH)/', $upperType)) {
                $category = 'Light Aircraft';
                $wtc = 'Light';
                $icaoCode = 'Code A/B';
            }

            $rec = [
                'no'                  => $this->toInt($first),
                'aircraft_type'       => $second,
                'category'            => $category,
                'icao_code'           => $icaoCode,
                'wtc'                 => $wtc,
                'aircraft_arrival'    => $arrAc,
                'aircraft_departure'  => $depAc,
                'aircraft_total'      => $totAc,
                'passenger_arrival'   => $arrPax,
                'passenger_departure' => $depPax,
                'passenger_transit'   => $transPax,
                'passenger_transfer'  => $trfPax,
                'passenger_total'     => $totPax,
                'crew'                => $crew,
                'extra_crew'          => $exCrew,
                'crew_total'          => $totCrew,
                'baggage'             => $baggage,
                'cargo'               => $cargo,
                'pos'                 => $pos,
            ];
            $records[] = $rec;

            $summary['total_movements']    += $totAc;
            $summary['aircraft_arrival']   += $arrAc;
            $summary['aircraft_departure'] += $depAc;
            $summary['passenger_arrival']  += $arrPax;
            $summary['passenger_departure']+= $depPax;
            $summary['passenger_transit']  += $transPax;
            $summary['passenger_transfer'] += $trfPax;
            $summary['passenger_total']    += $totPax;
            $summary['crew_total']         += $totCrew;
            $summary['baggage_total']      += $baggage;
            $summary['cargo_total']        += $cargo;
            $summary['pos_total']          += $pos;
        }

        $aerodromeIntelligence = self::calculateAerodromeProfile($records, $summary);

        return [
            'report_type'            => 'DAU6',
            'report_title'           => 'Data Angkutan Udara Menurut Tipe Pesawat (DAU-06)',
            'report_code'            => 'DAU-06',
            'meta'                   => $meta,
            'summary'                => $summary,
            'aerodrome_intelligence' => $aerodromeIntelligence,
            'aerodrome_profile'      => $aerodromeIntelligence,
            'records_count'          => count($records),
            'records'                => $records,
            'columns'                => [
                'No', 'Tipe Pesawat', 'Pesawat (DTG/BRK/TOT)', 'Penumpang (DTG/BRK/Transit/Transfer/TOT)',
                'Awak (Crew/Ex Crew/TOT)', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }

    /**
     * Precompute ICAO Aerodrome Code Breakdown and WTC Safety Profile.
     */
    public static function calculateAerodromeProfile(array $records, array $summary): array
    {
        $totalMovements = (int)($summary['total_movements'] ?? 0);
        $totalPax = (int)($summary['passenger_total'] ?? 0);

        $icaoCodes = [
            'Code C' => [
                'label'       => 'Code C (Narrow-Body)',
                'description' => 'A320, B737 family, MD-80 (Standar Runway 45m)',
                'movements'   => 0,
                'passengers'  => 0,
                'share_pct'   => 0.0,
            ],
            'Code D/E/F' => [
                'label'       => 'Code D/E/F (Wide-Body)',
                'description' => 'A330, B777, B787, A350, B747 (Heavy Long-Haul)',
                'movements'   => 0,
                'passengers'  => 0,
                'share_pct'   => 0.0,
            ],
            'Code A/B' => [
                'label'       => 'Code A/B (Turboprop / Regional)',
                'description' => 'ATR-72, Twin Otter, Caravan (Short Feeder)',
                'movements'   => 0,
                'passengers'  => 0,
                'share_pct'   => 0.0,
            ],
        ];

        $wtcProfile = [
            'Heavy' => [
                'label'       => 'Heavy (H)',
                'description' => 'MTOW ≥ 136.000 Kg (Separasi Wake Turbulensi Maksimal)',
                'movements'   => 0,
                'share_pct'   => 0.0,
                'color'       => '#6366f1', // indigo
            ],
            'Medium' => [
                'label'       => 'Medium (M)',
                'description' => '7.000 Kg < MTOW < 136.000 Kg (Separasi Standar 5 NM / 2-3 Menit)',
                'movements'   => 0,
                'share_pct'   => 0.0,
                'color'       => '#0ea5e9', // sky
            ],
            'Light' => [
                'label'       => 'Light (L)',
                'description' => 'MTOW ≤ 7.000 Kg (Separasi Ekstra di Belakang Heavy/Medium)',
                'movements'   => 0,
                'share_pct'   => 0.0,
                'color'       => '#10b981', // emerald
            ],
        ];

        foreach ($records as $r) {
            $code = $r['icao_code'] ?? 'Code C';
            $wtc = $r['wtc'] ?? 'Medium';
            $mv = (int)($r['aircraft_total'] ?? 0);
            $px = (int)($r['passenger_total'] ?? 0);

            if (isset($icaoCodes[$code])) {
                $icaoCodes[$code]['movements'] += $mv;
                $icaoCodes[$code]['passengers'] += $px;
            }

            if (isset($wtcProfile[$wtc])) {
                $wtcProfile[$wtc]['movements'] += $mv;
            }
        }

        foreach ($icaoCodes as &$ic) {
            $ic['share_pct'] = $totalMovements > 0 ? round(($ic['movements'] / $totalMovements) * 100, 1) : 0.0;
        }
        unset($ic);

        foreach ($wtcProfile as &$wp) {
            $wp['share_pct'] = $totalMovements > 0 ? round(($wp['movements'] / $totalMovements) * 100, 1) : 0.0;
        }
        $icaoCodes['code_c']   = $icaoCodes['Code C'];
        $icaoCodes['code_def'] = $icaoCodes['Code D/E/F'];
        $icaoCodes['code_ab']  = $icaoCodes['Code A/B'];

        $wtcProfile['heavy']  = $wtcProfile['Heavy'];
        $wtcProfile['medium'] = $wtcProfile['Medium'];
        $wtcProfile['light']  = $wtcProfile['Light'];

        return [
            'icao_codes'   => $icaoCodes,
            'wtc_profile'  => $wtcProfile,
            'wtc_profiles' => $wtcProfile,
        ];
    }
}

