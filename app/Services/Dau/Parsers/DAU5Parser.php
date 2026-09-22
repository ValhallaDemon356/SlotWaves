<?php

namespace App\Services\Dau\Parsers;

class DAU5Parser extends BaseDauParser
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

            $rec = [
                'no'                  => $this->toInt($first),
                'airline'             => $second,
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

        $paretoIntelligence = self::calculatePareto($records, $summary);

        return [
            'report_type'         => 'DAU5',
            'report_title'        => 'Data Angkutan Udara Menurut Airline/Operator (DAU-05)',
            'report_code'         => 'DAU-05',
            'meta'                => $meta,
            'summary'             => $summary,
            'pareto_intelligence'=> $paretoIntelligence,
            'pareto'              => $paretoIntelligence,
            'records_count'       => count($records),
            'records'             => $records,
            'columns'             => [
                'No', 'Airline / Operator', 'Pesawat (DTG/BRK/TOT)', 'Penumpang (DTG/BRK/Transit/Transfer/TOT)',
                'Awak (Crew/Ex Crew/TOT)', 'Bagasi (Kg)', 'Kargo (Kg)', 'POS (Kg)'
            ],
        ];
    }

    /**
     * Precompute 80/20 Pareto distribution, Tier-1 Anchor Carriers, and HHI Concentration.
     */
    public static function calculatePareto(array $records, array $summary): array
    {
        $totalMovements = (int)($summary['total_movements'] ?? 0);
        if ($totalMovements <= 0) {
            foreach ($records as $r) {
                $totalMovements += (int)($r['aircraft_total'] ?? 0);
            }
        }

        $sorted = $records;
        usort($sorted, fn($a, $b) => ($b['aircraft_total'] ?? 0) <=> ($a['aircraft_total'] ?? 0));

        $cumVal = 0;
        $paretoItems = [];
        $anchorAirlines = [];
        $hhiSum = 0.0;

        foreach ($sorted as $r) {
            $val = (int)($r['aircraft_total'] ?? 0);
            $cumVal += $val;
            $sharePct = $totalMovements > 0 ? round(($val / $totalMovements) * 100, 2) : 0.0;
            $cumPct = $totalMovements > 0 ? round(($cumVal / $totalMovements) * 100, 1) : 0.0;
            $hhiSum += pow($sharePct, 2);

            // Anchor carrier is any airline contributing to the first 80% cumulative threshold
            $isAnchor = ($cumVal - $val) < ($totalMovements * 0.80);

            $item = [
                'airline'        => $r['airline'] ?? ($r['operator_name'] ?? 'Unknown'),
                'movements'      => $val,
                'passengers'     => (int)($r['passenger_total'] ?? 0),
                'share_pct'      => $sharePct,
                'cumulative_pct' => $cumPct,
                'is_anchor'      => $isAnchor,
            ];
            $paretoItems[] = $item;

            if ($isAnchor) {
                $anchorAirlines[] = $item;
            }
        }

        $hhi = (int) round($hhiSum);
        if ($hhi < 1500) {
            $hhiCat = 'Kompetitif / Diversified';
            $hhiClass = 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
            $hhiDesc = 'Struktur maskapai terdistribusi sehat dengan diversifikasi risiko operasional yang merata.';
        } elseif ($hhi <= 2500) {
            $hhiCat = 'Konsentrasi Sedang';
            $hhiClass = 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border-amber-200 dark:border-amber-800';
            $hhiDesc = 'Konsentrasi maskapai tingkat moderat. Terdapat beberapa operator jangkar dominan.';
        } else {
            $hhiCat = 'Konsentrasi Tinggi (Dominant Carrier Risk)';
            $hhiClass = 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border-rose-200 dark:border-rose-800';
            $hhiDesc = 'Bandara sangat bergantung pada 1-2 maskapai dominan. Sensitif terhadap disrupsi operasional.';
        }

        return [
            'pareto_airlines'       => $paretoItems,
            'anchor_airlines'       => $anchorAirlines,
            'tier1_anchor_airlines' => $anchorAirlines,
            'anchor_count'          => count($anchorAirlines),
            'hhi_index'             => $hhi,
            'hhi'                   => $hhi,
            'hhi_category'          => $hhiCat,
            'hhi_class'             => $hhiClass,
            'hhi_description'       => $hhiDesc,
        ];
    }
}

