<?php

namespace App\Services\Dau\Parsers;

class DAU4AParser extends BaseDauParser
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

        // Find data start
        $dataStartIndex = 3;
        foreach ($rawRows as $idx => $row) {
            if (isset($row[0]) && is_numeric($row[0]) && (int)$row[0] === 1 && !empty($row[1])) {
                $dataStartIndex = $idx;
                break;
            }
        }

        for ($i = $dataStartIndex; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if (empty($row) || count($row) < 12) continue;
            $first = $this->toStr($row[0] ?? '');
            $second = $this->toStr($row[1] ?? '');

            if (stripos($first, 'TOTAL') !== false || stripos($second, 'TOTAL') !== false) {
                continue;
            }

            if (!is_numeric($first)) continue;

            $arrAc  = $this->toInt($row[6] ?? 0);
            $depAc  = $this->toInt($row[7] ?? 0);
            $totAc  = $this->toInt($row[8] ?? ($arrAc + $depAc));
            $arrPax = $this->toInt($row[9] ?? 0);
            $depPax = $this->toInt($row[10] ?? 0);
            $transPax = $this->toInt($row[11] ?? 0);
            $trfPax   = $this->toInt($row[12] ?? 0);
            $totPax   = $this->toInt($row[13] ?? ($arrPax + $depPax + $transPax + $trfPax));
            $crew     = $this->toInt($row[14] ?? 0);
            $exCrew   = $this->toInt($row[15] ?? 0);
            $totCrew  = $this->toInt($row[16] ?? ($crew + $exCrew));
            $baggage  = $this->toInt($row[19] ?? 0);
            $cargo    = $this->toInt($row[22] ?? 0);
            $pos      = $this->toInt($row[25] ?? 0);

            $rec = [
                'no'                  => $this->toInt($first),
                'operator_name'       => $second,
                'airline'             => $second,
                'operator_code'       => $this->toStr($row[2] ?? ''),
                'airline_code'        => $this->toStr($row[2] ?? ''),
                'airport'             => $this->toStr($row[3] ?? ''),
                'city_code'           => $this->toStr($row[4] ?? ''),
                'city'                => $this->toStr($row[5] ?? ''),
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

        $routeMarketShare = self::calculateRouteMarketShare($records);

        return [
            'report_type'        => 'DAU4A',
            'report_title'       => 'Data Angkutan Udara Menurut Asal/Tujuan Operator (DAU-04A)',
            'report_code'        => 'DAU-04A',
            'meta'               => $meta,
            'summary'            => $summary,
            'route_market_share' => $routeMarketShare,
            'market_share'       => $routeMarketShare,
            'records_count'      => count($records),
            'records'            => $records,
            'columns'            => [
                'No', 'Operator', 'Kode', 'Airport', 'Kode IATA', 'Kota', 'Pesawat (DTG/BRK/TOT)',
                'Penumpang (DTG/BRK/Transit/Transfer/TOT)', 'Awak', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }

    /**
     * Precompute Route Market Share per Airport/Route across Airlines.
     */
    public static function calculateRouteMarketShare(array $records): array
    {
        $routes = [];

        foreach ($records as $r) {
            $routeKey = trim($r['airport'] ?? $r['city'] ?? 'Unknown');
            if ($routeKey === '') continue;

            $airline = trim($r['airline'] ?? $r['operator_name'] ?? 'Unknown');
            $airlineCode = trim($r['airline_code'] ?? $r['operator_code'] ?? '');
            $flights = (int)($r['aircraft_total'] ?? 0);
            $pax = (int)($r['passenger_total'] ?? 0);

            if (!isset($routes[$routeKey])) {
                $routes[$routeKey] = [
                    'route'         => $routeKey,
                    'city'          => $r['city'] ?? $routeKey,
                    'city_code'     => $r['city_code'] ?? '',
                    'total_flights' => 0,
                    'total_pax'     => 0,
                    'airlines'      => [],
                ];
            }

            $routes[$routeKey]['total_flights'] += $flights;
            $routes[$routeKey]['total_pax']     += $pax;

            if (!isset($routes[$routeKey]['airlines'][$airline])) {
                $routes[$routeKey]['airlines'][$airline] = [
                    'airline'      => $airline,
                    'airline_code' => $airlineCode,
                    'flights'      => 0,
                    'pax'          => 0,
                    'share_pct'    => 0.0,
                ];
            }

            $routes[$routeKey]['airlines'][$airline]['flights'] += $flights;
            $routes[$routeKey]['airlines'][$airline]['pax']     += $pax;
        }

        // Calculate share and sort airlines descending
        foreach ($routes as &$rt) {
            $totF = $rt['total_flights'];
            $alList = array_values($rt['airlines']);
            usort($alList, fn($a, $b) => $b['flights'] <=> $a['flights']);

            foreach ($alList as &$al) {
                $al['share_pct'] = $totF > 0 ? round(($al['flights'] / $totF) * 100, 1) : 0.0;
            }
            unset($al);

            $rt['airlines'] = $alList;
            $rt['dominant_carrier']   = !empty($alList) ? ($alList[0]['airline'] ?? 'N/A') : 'N/A';
            $rt['dominant_share_pct'] = !empty($alList) ? ($alList[0]['share_pct'] ?? 0.0) : 0.0;
        }
        unset($rt);

        // Sort routes by total flight volume
        uasort($routes, fn($a, $b) => $b['total_flights'] <=> $a['total_flights']);

        return [
            'routes'        => $routes,
            'route_keys'    => array_keys($routes),
            'default_route' => array_key_first($routes) ?? '',
        ];
    }
}

