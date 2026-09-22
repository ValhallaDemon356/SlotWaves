<?php

namespace App\Services\Dau\Parsers;

class DAU12Parser extends BaseDauParser
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
            'passenger_total'    => 0,
        ];

        $dataStartIndex = 3;
        foreach ($rawRows as $idx => $row) {
            if (isset($row[0]) && is_numeric($row[0]) && (int)$row[0] === 1 && !empty($row[1])) {
                $dataStartIndex = $idx;
                break;
            }
        }

        for ($i = $dataStartIndex; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if (empty($row) || count($row) < 7) continue;
            $first = $this->toStr($row[0] ?? '');
            $date  = $this->toStr($row[1] ?? '');

            if (stripos($first, 'TOTAL') !== false || stripos($date, 'TOTAL') !== false) {
                continue;
            }

            if (!is_numeric($first)) continue;

            $acArrDom = $this->toInt($row[2] ?? 0);
            $acArrInt = $this->toInt($row[3] ?? 0);
            $acArrTot = $this->toInt($row[4] ?? ($acArrDom + $acArrInt));

            $acDepDom = $this->toInt($row[5] ?? 0);
            $acDepInt = $this->toInt($row[6] ?? 0);
            $acDepTot = $this->toInt($row[7] ?? ($acDepDom + $acDepInt));

            $acTot    = $this->toInt($row[8] ?? ($acArrTot + $acDepTot));

            $pArrDom  = $this->toInt($row[9] ?? 0);
            $pArrInt  = $this->toInt($row[10] ?? 0);
            $pArrTot  = $this->toInt($row[11] ?? ($pArrDom + $pArrInt));

            $pDepDom  = $this->toInt($row[12] ?? 0);
            $pDepInt  = $this->toInt($row[13] ?? 0);
            $pDepTot  = $this->toInt($row[14] ?? ($pDepDom + $pDepInt));

            $pTot     = $this->toInt($row[15] ?? ($pArrTot + $pDepTot));

            $rec = [
                'no'                    => $this->toInt($first),
                'date'                  => $date,
                'aircraft_arr_domestic' => $acArrDom,
                'aircraft_arr_int'      => $acArrInt,
                'aircraft_arrival_tot'  => $acArrTot,
                'aircraft_dep_domestic' => $acDepDom,
                'aircraft_dep_int'      => $acDepInt,
                'aircraft_departure_tot'=> $acDepTot,
                'aircraft_total'        => $acTot,
                'passenger_arr_domestic'=> $pArrDom,
                'passenger_arr_int'     => $pArrInt,
                'passenger_arrival_tot' => $pArrTot,
                'passenger_dep_domestic'=> $pDepDom,
                'passenger_dep_int'     => $pDepInt,
                'passenger_departure_tot'=> $pDepTot,
                'passenger_total'       => $pTot,
                // Normalized aliases required by filterReportDataset + Alpine.js applyFilters / recalculateAnalytics
                'aircraft_arrival'      => $acArrTot,
                'aircraft_departure'    => $acDepTot,
                'passenger_arrival'     => $pArrTot,
                'passenger_departure'   => $pDepTot,
            ];
            $records[] = $rec;

            $summary['total_movements']    += $acTot;
            $summary['aircraft_arrival']   += $acArrTot;
            $summary['aircraft_departure'] += $acDepTot;
            $summary['passenger_arrival']  += $pArrTot;
            $summary['passenger_departure']+= $pDepTot;
            $summary['passenger_total']    += $pTot;
        }

        $trafficMatrix = self::calculateTrafficMatrix($records, $summary);

        return [
            'report_type'      => 'DAU12',
            'report_title'     => 'Data Statistik Angkutan Udara 2 (DAU-12)',
            'report_code'      => 'DAU-12',
            'meta'             => $meta,
            'summary'          => $summary,
            'traffic_matrix'   => $trafficMatrix,
            'records_count'    => count($records),
            'records'          => $records,
            'columns'          => [
                'No', 'Tanggal', 'Pesawat Arrival (DOM/INT/TOT)', 'Pesawat Departure (DOM/INT/TOT)',
                'Total Pesawat', 'Penumpang Arrival (DOM/INT/TOT)', 'Penumpang Departure (DOM/INT/TOT)', 'Total Penumpang'
            ],
        ];
    }

    /**
     * Precompute 2x2 Operational Traffic Matrix (Dom/Int x Arr/Dep) and CIQ Facility Demand.
     */
    public static function calculateTrafficMatrix(array $records, array $summary): array
    {
        $domArrAcft = 0; $domArrPax = 0;
        $domDepAcft = 0; $domDepPax = 0;
        $intArrAcft = 0; $intArrPax = 0;
        $intDepAcft = 0; $intDepPax = 0;

        foreach ($records as $r) {
            $domArrAcft += (int)($r['aircraft_arr_domestic'] ?? 0);
            $domDepAcft += (int)($r['aircraft_dep_domestic'] ?? 0);
            $intArrAcft += (int)($r['aircraft_arr_int'] ?? 0);
            $intDepAcft += (int)($r['aircraft_dep_int'] ?? 0);

            $domArrPax  += (int)($r['passenger_arr_domestic'] ?? 0);
            $domDepPax  += (int)($r['passenger_dep_domestic'] ?? 0);
            $intArrPax  += (int)($r['passenger_arr_int'] ?? 0);
            $intDepPax  += (int)($r['passenger_dep_int'] ?? 0);
        }

        $totAcft = $domArrAcft + $domDepAcft + $intArrAcft + $intDepAcft;
        $totPax  = $domArrPax + $domDepPax + $intArrPax + $intDepPax;

        $intAcftTot = $intArrAcft + $intDepAcft;
        $intPaxTot  = $intArrPax + $intDepPax;
        $domAcftTot = $domArrAcft + $domDepAcft;
        $domPaxTot  = $domArrPax + $domDepPax;

        $ciqPaxPct = $totPax > 0 ? round(($intPaxTot / $totPax) * 100, 1) : 0.0;
        $ciqAcftPct = $totAcft > 0 ? round(($intAcftTot / $totAcft) * 100, 1) : 0.0;

        if ($ciqPaxPct < 15.0) {
            $ciqLevel = 'Rendah (Normal Standar)';
            $ciqClass = 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
            $ciqDesc = 'Kebutuhan loket Bea Cukai, Imigrasi, dan Karantina (CIQ) berada pada tingkat beban dasar.';
        } elseif ($ciqPaxPct <= 30.0) {
            $ciqLevel = 'Moderat (Shift Standar)';
            $ciqClass = 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300 border-sky-200 dark:border-sky-800';
            $ciqDesc = 'Beban operasional CIQ moderat. Disarankan membuka 60-75% loket paspor.';
        } elseif ($ciqPaxPct <= 50.0) {
            $ciqLevel = 'Tinggi (High Demand CIQ)';
            $ciqClass = 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border-amber-200 dark:border-amber-800';
            $ciqDesc = 'Beban CIQ tinggi. Seluruh gerbang autogate & konter manual imigrasi harus siaga.';
        } else {
            $ciqLevel = 'Kritis (Peak CIQ Saturation)';
            $ciqClass = 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border-rose-200 dark:border-rose-800';
            $ciqDesc = 'Arus internasional mendominasi. Potensi antrean panjang verifikasi paspor & custom clearance.';
        }

        return [
            'quadrants' => [
                'dom_arr' => [
                    'title'       => 'Domestik Arrival (DTG)',
                    'scope'       => 'Domestic',
                    'direction'   => 'Arrival',
                    'aircraft'    => $domArrAcft,
                    'passengers'  => $domArrPax,
                    'acft_share'  => $totAcft > 0 ? round(($domArrAcft / $totAcft) * 100, 1) : 0,
                    'pax_share'   => $totPax > 0 ? round(($domArrPax / $totPax) * 100, 1) : 0,
                ],
                'dom_dep' => [
                    'title'       => 'Domestik Departure (BRK)',
                    'scope'       => 'Domestic',
                    'direction'   => 'Departure',
                    'aircraft'    => $domDepAcft,
                    'passengers'  => $domDepPax,
                    'acft_share'  => $totAcft > 0 ? round(($domDepAcft / $totAcft) * 100, 1) : 0,
                    'pax_share'   => $totPax > 0 ? round(($domDepPax / $totPax) * 100, 1) : 0,
                ],
                'int_arr' => [
                    'title'       => 'Internasional Arrival (DTG)',
                    'scope'       => 'International',
                    'direction'   => 'Arrival',
                    'aircraft'    => $intArrAcft,
                    'passengers'  => $intArrPax,
                    'acft_share'  => $totAcft > 0 ? round(($intArrAcft / $totAcft) * 100, 1) : 0,
                    'pax_share'   => $totPax > 0 ? round(($intArrPax / $totPax) * 100, 1) : 0,
                ],
                'int_dep' => [
                    'title'       => 'Internasional Departure (BRK)',
                    'scope'       => 'International',
                    'direction'   => 'Departure',
                    'aircraft'    => $intDepAcft,
                    'passengers'  => $intDepPax,
                    'acft_share'  => $totAcft > 0 ? round(($intDepAcft / $totAcft) * 100, 1) : 0,
                    'pax_share'   => $totPax > 0 ? round(($intDepPax / $totPax) * 100, 1) : 0,
                ],
            ],
            'ciq_demand' => [
                'ciq_pax_share_pct'  => $ciqPaxPct,
                'ciq_acft_share_pct' => $ciqAcftPct,
                'int_passengers'     => $intPaxTot,
                'dom_passengers'     => $domPaxTot,
                'int_movements'      => $intAcftTot,
                'dom_movements'      => $domAcftTot,
                'level'              => $ciqLevel,
                'demand_status'      => $ciqLevel,
                'class'              => $ciqClass,
                'description'        => $ciqDesc,
            ],
            'ciq' => [
                'ciq_pax_share_pct'  => $ciqPaxPct,
                'ciq_acft_share_pct' => $ciqAcftPct,
                'int_passengers'     => $intPaxTot,
                'dom_passengers'     => $domPaxTot,
                'int_movements'      => $intAcftTot,
                'dom_movements'      => $domAcftTot,
                'level'              => $ciqLevel,
                'demand_status'      => $ciqLevel,
                'class'              => $ciqClass,
                'description'        => $ciqDesc,
            ],
        ];
    }
}

