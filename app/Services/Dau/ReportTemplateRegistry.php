<?php

namespace App\Services\Dau;

class ReportTemplateRegistry
{
    /**
     * All supported report types in SlotWaves.
     */
    public const REPORT_TYPES = [
        'slot_schedule' => [
            'id'                 => 'slot_schedule',
            'code'               => 'SLOT',
            'number'             => 1,
            'name'               => 'Airport Slot Schedule',
            'title'              => 'Airport Flight Schedule & Slot Operations',
            'category'           => 'Airport Slot Operations',
            'extensions'         => ['pdf'],
            'mime_types'         => ['application/pdf'],
            'template_filename'  => 'schedule.pdf',
            'template_label'     => 'Airport Slot Schedule PDF',
            'parser_class'       => \App\Services\PdfParser::class,
            'is_pdf'             => true,
            'description'        => 'Standard airport slot schedule PDF containing arrival, departure, operational capacity and timeline data.',
            'detected_columns'   => ['Flight No', 'Airline', 'Aircraft Type', 'STA/STD', 'Origin/Dest', 'Operating Days', 'Movement Type'],
        ],
        'DAU1' => [
            'id'                 => 'DAU1',
            'code'               => 'DAU-01',
            'number'             => 2,
            'name'               => 'DAU1 (Arus Lalu Lintas)',
            'title'              => 'Data Lalu Lintas Angkutan Udara (DAU-01)',
            'category'           => 'Traffic Flow',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-1.xls',
            'template_label'     => 'OASYS DAU-1 Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU1Parser::class,
            'is_pdf'             => false,
            'description'        => 'Detailed individual flight movement records with seat capacity, adult/child/infant passenger breakdowns, baggage, cargo, and POS.',
            'detected_columns'   => ['Bandara Asal/Tujuan', 'Flight No', 'Berjadwal', 'Tipe Pesawat', 'Kapasitas Kursi', 'Pesawat', 'Penumpang (Dewasa/Anak/Bayi)', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU2' => [
            'id'                 => 'DAU2',
            'code'               => 'DAU-02',
            'number'             => 3,
            'name'               => 'DAU2 (Secara Total)',
            'title'              => 'Data Angkutan Udara Secara Total (DAU-02)',
            'category'           => 'Traffic Flow',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-2.xls',
            'template_label'     => 'OASYS DAU-2 Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU2Parser::class,
            'is_pdf'             => false,
            'description'        => 'Airport traffic totals aggregated strictly by flight category: Domestik, Internasional, and Grand Total.',
            'detected_columns'   => ['Jenis Penerbangan', 'Pesawat (DTG/BRK/JML)', 'Penumpang (DTG/BRK/Transit/Transfer)', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU3' => [
            'id'                 => 'DAU3',
            'code'               => 'DAU-03',
            'number'             => 4,
            'name'               => 'DAU3 (Status Penerbangan)',
            'title'              => 'Data Angkutan Udara Menurut Status Penerbangan (DAU-03)',
            'category'           => 'Traffic Flow',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-3.xls',
            'template_label'     => 'OASYS DAU-3 Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU3Parser::class,
            'is_pdf'             => false,
            'description'        => 'Air transport data grouped by flight operational status: Niaga (Commercial) vs Bukan Niaga (Non-Commercial).',
            'detected_columns'   => ['Status Penerbangan (Niaga/Bukan Niaga)', 'Pesawat', 'Penumpang', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU4' => [
            'id'                 => 'DAU4',
            'code'               => 'DAU-04',
            'number'             => 5,
            'name'               => 'DAU4 (Asal/Tujuan)',
            'title'              => 'Data Angkutan Udara Menurut Asal/Tujuan (DAU-04)',
            'category'           => 'Origin & Destination',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-4.xls',
            'template_label'     => 'OASYS DAU-4 Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU4Parser::class,
            'is_pdf'             => false,
            'description'        => 'Traffic volumes categorized by Origin/Destination Airport, City Code (IATA), and City Name.',
            'detected_columns'   => ['Airport', 'City Code (IATA)', 'City', 'Pesawat', 'Penumpang', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU4A' => [
            'id'                 => 'DAU4A',
            'code'               => 'DAU-04A',
            'number'             => 6,
            'name'               => 'DAU4A (Asal/Tujuan)',
            'title'              => 'Data Angkutan Udara Menurut Asal/Tujuan Operator (DAU-04A)',
            'category'           => 'Origin & Destination',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-4A.xls',
            'template_label'     => 'OASYS DAU-4A Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU4AParser::class,
            'is_pdf'             => false,
            'description'        => 'Origin/Destination statistics paired with individual Airline Operators and City codes.',
            'detected_columns'   => ['Nama Operator', 'Operator Code', 'Airport', 'City Code', 'Pesawat', 'Penumpang', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU4B' => [
            'id'                 => 'DAU4B',
            'code'               => 'DAU-04B',
            'number'             => 7,
            'name'               => 'DAU4B (Asal/Tujuan (Airline/Operator))',
            'title'              => 'Asal/Tujuan Menurut Airline/Operator Matrix (DAU-04B)',
            'category'           => 'Origin & Destination',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-4B.xls',
            'template_label'     => 'OASYS DAU-4B Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU4BParser::class,
            'is_pdf'             => false,
            'description'        => 'Comprehensive wide matrix cross-referencing Cities/IATA against multiple Airline Operators with 10 sub-metrics per operator.',
            'detected_columns'   => ['Kota Asal/Tujuan', 'Kode IATA', 'Airline Columns Matrix (Flight Arr/Dep, Pass Arr/Dep, Transit, Transfer, Crew, Total)'],
        ],
        'DAU5' => [
            'id'                 => 'DAU5',
            'code'               => 'DAU-05',
            'number'             => 8,
            'name'               => 'DAU5 (Airline Operator)',
            'title'              => 'Data Angkutan Udara Menurut Airline/Operator (DAU-05)',
            'category'           => 'Airlines & Fleets',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-5.xls',
            'template_label'     => 'OASYS DAU-5 Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU5Parser::class,
            'is_pdf'             => false,
            'description'        => 'Airline operator volume rankings with aircraft movements, passenger traffic, crew, baggage, cargo, and POS.',
            'detected_columns'   => ['Airline', 'Pesawat (DTG/BRK/JML)', 'Penumpang (DTG/BRK/Transit/Transfer)', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU5A' => [
            'id'                 => 'DAU5A',
            'code'               => 'DAU-05A',
            'number'             => 9,
            'name'               => 'DAU5A (Airline Operator (A))',
            'title'              => 'Data Angkutan Udara Menurut Airline/Operator Extended Crew (DAU-05A)',
            'category'           => 'Airlines & Fleets',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-5A.xls',
            'template_label'     => 'OASYS DAU-5A Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU5AParser::class,
            'is_pdf'             => false,
            'description'        => 'Airline operator statistics with granular Extra Crew breakdown (Arrival Extra Crew, Departure Extra Crew, Total Extra Crew).',
            'detected_columns'   => ['Airline', 'Pesawat', 'Penumpang', 'Awak (Crew, Arr E.Crew, Dep E.Crew, Ex Crew, Total)', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU5B' => [
            'id'                 => 'DAU5B',
            'code'               => 'DAU-05B',
            'number'             => 10,
            'name'               => 'DAU5B (Airline Operator (B))',
            'title'              => 'Data Angkutan Udara Menurut Terminal & Airline (DAU-05B)',
            'category'           => 'Airlines & Fleets',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-5B.xls',
            'template_label'     => 'OASYS DAU-5B Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU5BParser::class,
            'is_pdf'             => false,
            'description'        => 'Airline operator data classified per Airport Terminal (e.g. Terminal 1, 2, 3) with flight and passenger breakdowns.',
            'detected_columns'   => ['Terminal', 'Airline', 'Pesawat', 'Penumpang', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU5C' => [
            'id'                 => 'DAU5C',
            'code'               => 'DAU-05C',
            'number'             => 11,
            'name'               => 'DAU5C (Airline Operator (C))',
            'title'              => 'Data Angkutan Udara Menurut Airline/Operator (DAU-05C)',
            'category'           => 'Airlines & Fleets',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-5C.xls',
            'template_label'     => 'OASYS DAU-5C Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU5CParser::class,
            'is_pdf'             => false,
            'description'        => 'Airline operator analytical report variant (DAU-05C format).',
            'detected_columns'   => ['Airline', 'Pesawat', 'Penumpang', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU6' => [
            'id'                 => 'DAU6',
            'code'               => 'DAU-06',
            'number'             => 12,
            'name'               => 'DAU6 (Tipe Pesawat)',
            'title'              => 'Data Angkutan Udara Menurut Tipe Pesawat (DAU-06)',
            'category'           => 'Airlines & Fleets',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-6.xls',
            'template_label'     => 'OASYS DAU-6 Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU6Parser::class,
            'is_pdf'             => false,
            'description'        => 'Traffic statistics categorized by Aircraft Type (Airbus A320, Boeing 737, ATR, etc.).',
            'detected_columns'   => ['Tipe Pesawat', 'Pesawat (DTG/BRK/JML)', 'Penumpang', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU10' => [
            'id'                 => 'DAU10',
            'code'               => 'DAU-10',
            'number'             => 13,
            'name'               => 'DAU10 (Jam Puncak Pesawat/Penumpang)',
            'title'              => 'Data Angkutan Udara Jam Puncak Pesawat/Penumpang (DAU-10)',
            'category'           => 'Peak Hours & Terminals',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-10.xls',
            'template_label'     => 'OASYS DAU-10 Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU10Parser::class,
            'is_pdf'             => false,
            'description'        => 'Hourly operational demand distribution (00:01 - 24:00) per terminal for flight and passenger capacity analysis.',
            'detected_columns'   => ['Jam (Period)', 'Terminal', 'Pesawat', 'Penumpang', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU10A' => [
            'id'                 => 'DAU10A',
            'code'               => 'DAU-10A',
            'number'             => 14,
            'name'               => 'DAU10A (Jam Puncak Pesawat/Penumpang (Terminal))',
            'title'              => 'Jam Puncak Menurut Terminal Matrix (DAU-10A)',
            'category'           => 'Peak Hours & Terminals',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-10A.xls',
            'template_label'     => 'OASYS DAU-10A Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU10AParser::class,
            'is_pdf'             => false,
            'description'        => 'Wide matrix representing hourly periods across all terminals (1, 2F, 3U, 1B, 2D, 2E, 1C) with 10 metrics each.',
            'detected_columns'   => ['Periode Jam', 'Terminal Matrix (1, 2F, 3U, 1B, 2D, 2E, 1C: Flight Arr/Dep, Pass Arr/Dep, Transit, Transfer, Crew, Ex Crew, Total)'],
        ],
        'DAU10B' => [
            'id'                 => 'DAU10B',
            'code'               => 'DAU-10B',
            'number'             => 15,
            'name'               => 'DAU10B (Jam Puncak Pesawat/Penumpang (Block On/Off))',
            'title'              => 'Data Angkutan Udara Jam Puncak Block On/Off (DAU-10B)',
            'category'           => 'Peak Hours & Terminals',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-10B.xls',
            'template_label'     => 'OASYS DAU-10B Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU10BParser::class,
            'is_pdf'             => false,
            'description'        => 'Hourly peak distribution measured strictly by aircraft Block On and Block Off gate timestamps.',
            'detected_columns'   => ['Jam (Block On/Off)', 'Terminal', 'Pesawat', 'Penumpang', 'Awak', 'Bagasi', 'Kargo', 'POS'],
        ],
        'DAU11' => [
            'id'                 => 'DAU11',
            'code'               => 'DAU-11',
            'number'             => 16,
            'name'               => 'DAU11 (Data Statistik 1 (ARR/DEP/DOM/INT))',
            'title'              => 'Data Statistik Angkutan Udara 1 (DAU-11)',
            'category'           => 'Statistics',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-11.xls',
            'template_label'     => 'OASYS DAU-11 Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU11Parser::class,
            'is_pdf'             => false,
            'description'        => 'Daily time-series statistics tracking Aircraft & Passenger totals split by Domestic vs International.',
            'detected_columns'   => ['Tanggal', 'Pesawat (Int DTG/BKT, Dom DTG/BKT, Total)', 'Penumpang (Int & Dom Breakdowns)', 'Total Penumpang'],
        ],
        'DAU12' => [
            'id'                 => 'DAU12',
            'code'               => 'DAU-12',
            'number'             => 17,
            'name'               => 'DAU12 (Data Statistik 2 (ARR/DEP/DOM/INT))',
            'title'              => 'Data Statistik Angkutan Udara 2 (DAU-12)',
            'category'           => 'Statistics',
            'extensions'         => ['xls', 'xlsx'],
            'mime_types'         => ['application/vnd.ms-excel', 'text/html', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'template_filename'  => 'DAU-12.xls',
            'template_label'     => 'OASYS DAU-12 Excel Template',
            'parser_class'       => \App\Services\Dau\Parsers\DAU12Parser::class,
            'is_pdf'             => false,
            'description'        => 'Daily time-series matrix organized by Arrival (Dom/Int/Tot) and Departure (Dom/Int/Tot) for aircraft and passengers.',
            'detected_columns'   => ['Tanggal', 'Pesawat Arrival (Dom/Int/Tot)', 'Pesawat Departure (Dom/Int/Tot)', 'Total Pesawat', 'Penumpang Arrival & Departure', 'Total Penumpang'],
        ],
    ];

    /**
     * Get list of all report types.
     */
    public static function all(): array
    {
        return self::REPORT_TYPES;
    }

    /**
     * Find a report type by key/ID (case-insensitive).
     */
    public static function find(string $id): ?array
    {
        foreach (self::REPORT_TYPES as $key => $conf) {
            if (strcasecmp($key, $id) === 0 || strcasecmp($conf['code'], $id) === 0) {
                return $conf;
            }
        }
        return null;
    }

    /**
     * Check if report type ID exists.
     */
    public static function exists(string $id): bool
    {
        return self::find($id) !== null;
    }

    /**
     * Get grouped report types for UI presentation.
     */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::REPORT_TYPES as $conf) {
            $cat = $conf['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $conf;
        }
        return $grouped;
    }

    /**
     * Resolve template file path.
     */
    public static function getTemplatePath(string $reportType): ?string
    {
        $conf = self::find($reportType);
        if (!$conf || $conf['is_pdf']) {
            return null;
        }

        $filename = $conf['template_filename'];

        // 1. Check storage/app/templates
        $p1 = storage_path('app/templates/' . $filename);
        if (file_exists($p1)) return $p1;

        // 2. Check resources/templates/dau
        $p2 = resource_path('templates/dau/' . $filename);
        if (file_exists($p2)) return $p2;

        return null;
    }
}
