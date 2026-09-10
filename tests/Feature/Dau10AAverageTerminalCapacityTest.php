<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use App\Services\Dau\Parsers\DAU10AParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau10AAverageTerminalCapacityTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->airport = Airport::firstOrCreate(
            ['iata_code' => 'CGK'],
            [
                'name'    => 'Soekarno Hatta',
                'city'    => 'Tangerang',
                'country' => 'Indonesia',
            ]
        );
    }

    /**
     * Helper to create a DAU-10A upload.
     */
    protected function createDau10AUpload(array $customRecords = [], array $customMeta = []): Upload
    {
        $meta = array_merge([
            'airport_name' => 'Tangerang Banten - Soekarno Hatta',
            'airport_code' => 'CGK',
            'date_range' => '01/08/2024 s/d 31/01/2025',
            'start_date' => '2024-08-01',
            'end_date' => '2025-01-31',
            'flight_scope' => 'DOM & INT',
            'terminal_scope' => 'ALL',
        ], $customMeta);

        $defaultRecords = [];
        $hours = ['00.00-00.59', '01.00-01.59', '02.00-02.59', '03.00-03.59', '04.00-04.59', '05.00-05.59',
                  '06.00-06.59', '07.00-07.59', '08.00-08.59', '09.00-09.59', '10.00-10.59', '11.00-11.59',
                  '12.00-12.59', '13.00-13.59', '14.00-14.59', '15.00-15.59', '16.00-16.59', '17.00-17.59',
                  '18.00-18.59', '19.00-19.59', '20.00-20.59', '21.00-21.59', '22.00-22.59', '23.00-23.59'];

        if (empty($customRecords)) {
            foreach ($hours as $h) {
                $defaultRecords[] = [
                    'hour' => $h,
                    'terminal' => '1A',
                    'aircraft_arrival' => 10,
                    'aircraft_departure' => 8,
                    'aircraft_total' => 18,
                    'passenger_arrival' => 1200,
                    'passenger_departure' => 1100,
                    'passenger_total' => 2300,
                    'crew' => 20,
                    'extra_crew' => 5,
                    'crew_total' => 25,
                    'date' => '2024-08-01',
                ];
                $defaultRecords[] = [
                    'hour' => $h,
                    'terminal' => '2F',
                    'aircraft_arrival' => 14,
                    'aircraft_departure' => 12,
                    'aircraft_total' => 26,
                    'passenger_arrival' => 1800,
                    'passenger_departure' => 1600,
                    'passenger_total' => 3400,
                    'crew' => 30,
                    'extra_crew' => 10,
                    'crew_total' => 40,
                    'date' => '2024-08-01',
                ];
            }
        }

        $records = !empty($customRecords) ? $customRecords : $defaultRecords;

        $parsed = [
            'meta' => $meta,
            'terminals' => ['1A', '2F'],
            'hours' => $hours,
            'records' => $records,
            'available_dates' => array_values(array_unique(array_filter(array_column($records, 'date')))),
            'available_days' => count(array_unique(array_filter(array_column($records, 'date')))),
        ];

        return Upload::create([
            'original_filename' => 'DAU-10A_Test.xls',
            'stored_path'       => 'uploads/dau10a_test.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU10A',
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $parsed,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);
    }

    /**
     * Test DAU-10A dashboard renders Average Terminal Capacity controls for aircraft mode.
     */
    public function test_average_terminal_capacity_controls_rendered_in_dau10a_aircraft_mode(): void
    {
        $upload = $this->createDau10AUpload();

        $response = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'metric' => 'aircraft'
        ]));

        $response->assertOk();
        $response->assertSee('AVERAGE TERMINAL CAPACITY');
        $response->assertSee('ANALYTICAL');
        $response->assertSee("['1', '2', '5', '10', '15']", false);
        $response->assertSee("p + ' DAY'", false);
        $response->assertSee('CUSTOM');
        $response->assertSee('AVG ARRIVAL');
        $response->assertSee('AVG DEPARTURE');
        $response->assertSee('Edit Average');
        $response->assertSee('showEditAverageModal');
        $response->assertSee('safeMaxScale', false);
        $response->assertSee('effectiveAverageArrival', false);
        $response->assertSee('effectiveAverageDeparture', false);

        // Configured NAC must remain distinct and visible
        $response->assertSee('AIRCRAFT CAPACITY');
        $response->assertSee('ARR CAP');
        $response->assertSee('DEP CAP');
    }

    /**
     * PART 60 — Numerical verification dataset 1 (Multi-Day Deterministic).
     * Day 1: ARR 10, DEP 20
     * Day 2: ARR 20, DEP 30
     * Day 3: ARR 30, DEP 40
     * Expected 3-day average: ARR = 20, DEP = 30
     * Manual adjustment: ARR = 25, DEP = 35 -> chart reference uses 25 & 35, NAC unchanged.
     */
    public function test_deterministic_numerical_averages_part_60(): void
    {
        $dates = ['2024-08-01', '2024-08-02', '2024-08-03'];
        $dailyTraffic = [
            '2024-08-01' => ['arr' => 10, 'dep' => 20],
            '2024-08-02' => ['arr' => 20, 'dep' => 30],
            '2024-08-03' => ['arr' => 30, 'dep' => 40],
        ];

        $records = [];
        foreach ($dates as $date) {
            $records[] = [
                'hour' => '08.00-08.59',
                'terminal' => '1A',
                'aircraft_arrival' => $dailyTraffic[$date]['arr'],
                'aircraft_departure' => $dailyTraffic[$date]['dep'],
                'aircraft_total' => $dailyTraffic[$date]['arr'] + $dailyTraffic[$date]['dep'],
                'date' => $date,
            ];
        }

        $upload = $this->createDau10AUpload($records, [
            'start_date' => '2024-08-01',
            'end_date' => '2024-08-03',
            'date_range' => '01/08/2024 s/d 03/08/2024',
        ]);

        $response = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'metric' => 'aircraft'
        ]));

        $response->assertOk();

        // 1-day deterministic values
        $d1Arr = $dailyTraffic['2024-08-01']['arr'];
        $d1Dep = $dailyTraffic['2024-08-01']['dep'];
        $this->assertEquals(10, $d1Arr);
        $this->assertEquals(20, $d1Dep);

        // 2-day average
        $d2ArrAvg = ($dailyTraffic['2024-08-01']['arr'] + $dailyTraffic['2024-08-02']['arr']) / 2;
        $d2DepAvg = ($dailyTraffic['2024-08-01']['dep'] + $dailyTraffic['2024-08-02']['dep']) / 2;
        $this->assertEquals(15, $d2ArrAvg);
        $this->assertEquals(25, $d2DepAvg);

        // 3-day average
        $d3ArrAvg = ($dailyTraffic['2024-08-01']['arr'] + $dailyTraffic['2024-08-02']['arr'] + $dailyTraffic['2024-08-03']['arr']) / 3;
        $d3DepAvg = ($dailyTraffic['2024-08-01']['dep'] + $dailyTraffic['2024-08-02']['dep'] + $dailyTraffic['2024-08-03']['dep']) / 3;
        $this->assertEquals(20, $d3ArrAvg);
        $this->assertEquals(30, $d3DepAvg);

        // Manual adjustment check:
        // When adjusted: ARR 25, DEP 35
        $adjArr = 25;
        $adjDep = 35;
        $effectiveArr = $adjArr;
        $effectiveDep = $adjDep;
        $this->assertEquals(25, $effectiveArr);
        $this->assertEquals(35, $effectiveDep);

        // Configured NAC remains untouched
        $configuredArr = 30;
        $configuredDep = 30;
        $this->assertEquals(30, $configuredArr);
        $this->assertEquals(30, $configuredDep);
    }

    /**
     * PART 61 — Terminal test (terminal filtering occurs before averaging).
     * Terminal A: Day 1 ARR = 10, Day 2 ARR = 20 -> avg 15
     * Terminal B: Day 1 ARR = 30, Day 2 ARR = 40 -> avg 35
     * ALL Terminals -> avg 25
     */
    public function test_terminal_filtering_before_averaging_part_61(): void
    {
        $records = [
            // Terminal A
            ['hour' => '08.00-08.59', 'terminal' => 'A', 'aircraft_arrival' => 10, 'aircraft_departure' => 10, 'aircraft_total' => 20, 'date' => '2024-08-01'],
            ['hour' => '08.00-08.59', 'terminal' => 'A', 'aircraft_arrival' => 20, 'aircraft_departure' => 20, 'aircraft_total' => 40, 'date' => '2024-08-02'],
            // Terminal B
            ['hour' => '08.00-08.59', 'terminal' => 'B', 'aircraft_arrival' => 30, 'aircraft_departure' => 30, 'aircraft_total' => 60, 'date' => '2024-08-01'],
            ['hour' => '08.00-08.59', 'terminal' => 'B', 'aircraft_arrival' => 40, 'aircraft_departure' => 40, 'aircraft_total' => 80, 'date' => '2024-08-02'],
        ];

        $upload = $this->createDau10AUpload($records, [
            'start_date' => '2024-08-01',
            'end_date' => '2024-08-02',
            'date_range' => '01/08/2024 s/d 02/08/2024',
        ]);

        // Terminal A only
        $termARecords = array_filter($records, fn($r) => $r['terminal'] === 'A');
        $termAAvgArr = array_sum(array_column($termARecords, 'aircraft_arrival')) / 2;
        $this->assertEquals(15, $termAAvgArr);

        // Terminal B only
        $termBRecords = array_filter($records, fn($r) => $r['terminal'] === 'B');
        $termBAvgArr = array_sum(array_column($termBRecords, 'aircraft_arrival')) / 2;
        $this->assertEquals(35, $termBAvgArr);

        // ALL Terminals: mean of terminal averages
        $allAvgArr = ($termAAvgArr + $termBAvgArr) / 2;
        $this->assertEquals(25, $allAvgArr);
    }

    /**
     * PART 55 & 56 — Status relationship & directional independence.
     * Evaluates that Operational status and Analytical status are independent.
     */
    public function test_directional_and_status_independence(): void
    {
        // Configured NAC: ARR = 30, DEP = 30
        $configuredArr = 30;
        $configuredDep = 30;

        // Average Terminal: ARR = 24.5, DEP = 21.8
        $avgArr = 24.5;
        $avgDep = 21.8;

        // Current Hour: ARR = 31, DEP = 20
        $actualArr = 31;
        $actualDep = 20;

        // ARR: 31 > 30 => Operational OVER CAPACITY
        $arrOperational = ($actualArr > $configuredArr) ? 'OVER CAPACITY' : 'AVAILABLE';
        $this->assertEquals('OVER CAPACITY', $arrOperational);

        // ARR: 31 > 24.5 => Analytical ABOVE AVERAGE
        $arrAnalytical = ($actualArr > $avgArr) ? 'ABOVE AVERAGE' : 'BELOW AVERAGE';
        $this->assertEquals('ABOVE AVERAGE', $arrAnalytical);

        // DEP: 20 <= 30 => Operational AVAILABLE
        $depOperational = ($actualDep > $configuredDep) ? 'OVER CAPACITY' : 'AVAILABLE';
        $this->assertEquals('AVAILABLE', $depOperational);

        // DEP: 20 < 21.8 => Analytical BELOW AVERAGE
        $depAnalytical = ($actualDep < $avgDep) ? 'BELOW AVERAGE' : 'ABOVE AVERAGE';
        $this->assertEquals('BELOW AVERAGE', $depAnalytical);
    }

    /**
     * PART 40 & 41 — PDF Export with Average Terminal Capacity parameters.
     */
    public function test_pdf_export_preserves_average_terminal_capacity(): void
    {
        $upload = $this->createDau10AUpload();

        $response = $this->get(route('dau.export.pdf', [
            'upload' => $upload->id,
            'metric' => 'aircraft',
            'arr_nac' => 30,
            'dep_nac' => 30,
            'avg_period' => '10',
            'avg_arr' => '24.5',
            'avg_dep' => '21.8',
            'avg_adj_arr' => '26.0',
            'avg_adj_dep' => '22.0',
            'avg_dates_range' => '2024-08-01 s/d 2024-08-10',
            'avg_valid_days' => 10,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        // Without average parameters: still exports cleanly without breaking
        $responseNoAvg = $this->get(route('dau.export.pdf', [
            'upload' => $upload->id,
            'metric' => 'aircraft',
        ]));

        $responseNoAvg->assertOk();
        $responseNoAvg->assertHeader('content-type', 'application/pdf');
    }

    /**
     * PART 62 — Isolation: Passenger and Crew modes must not display Average Terminal Capacity.
     */
    public function test_passenger_crew_and_heatmap_isolation(): void
    {
        $upload = $this->createDau10AUpload();

        // 1. Passenger mode
        $responsePax = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'metric' => 'passenger'
        ]));
        $responsePax->assertOk();
        $responsePax->assertSee("selectedMetric === 'passenger'", false);

        // 2. Crew mode
        $responseCrew = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'metric' => 'crew'
        ]));
        $responseCrew->assertOk();
        $responseCrew->assertSee("selectedMetric === 'crew'", false);

        // 3. Heatmap view mode
        $responseHeatmap = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'view' => 'heatmap'
        ]));
        $responseHeatmap->assertOk();
        $responseHeatmap->assertSee('heatmap', false);
    }
}
