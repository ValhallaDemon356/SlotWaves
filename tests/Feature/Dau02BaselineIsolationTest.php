<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Dau\DauComparisonService;

class Dau02BaselineIsolationTest extends TestCase
{
    private function makeMockReports(): array
    {
        return [
            [
                'id' => 2,
                'airport_code' => 'CGK',
                'airport_name' => 'Soekarno Hatta',
                'start_date' => '2020-01-01',
                'end_date' => '2020-12-31',
                'report_data' => [
                    'records' => [
                        [
                            'category' => 'DOMESTIK',
                            'aircraft_arrival' => 500, 'aircraft_departure' => 500, 'aircraft_total' => 1000,
                            'passenger_arrival' => 10000, 'passenger_departure' => 10000, 'passenger_total' => 20000,
                            'cargo_arrival' => 2000, 'cargo_departure' => 2000, 'cargo' => 4000,
                        ],
                        [
                            'category' => 'INTERNASIONAL',
                            'aircraft_arrival' => 200, 'aircraft_departure' => 200, 'aircraft_total' => 400,
                            'passenger_arrival' => 5000, 'passenger_departure' => 5000, 'passenger_total' => 10000,
                            'cargo_arrival' => 1000, 'cargo_departure' => 1000, 'cargo' => 2000,
                        ],
                    ],
                    'meta' => [
                        'airport_code' => 'CGK',
                        'airport_name' => 'Soekarno Hatta',
                        'start_date' => '2020-01-01',
                        'end_date' => '2020-12-31',
                        'cargo_unit' => 'Kg',
                    ]
                ]
            ],
            [
                'id' => 3,
                'airport_code' => 'CGK',
                'airport_name' => 'Soekarno Hatta',
                'start_date' => '2021-01-01',
                'end_date' => '2021-12-31',
                'report_data' => [
                    'records' => [
                        [
                            'category' => 'DOMESTIK',
                            'aircraft_arrival' => 600, 'aircraft_departure' => 600, 'aircraft_total' => 1200,
                            'passenger_arrival' => 12000, 'passenger_departure' => 12000, 'passenger_total' => 24000,
                            'cargo_arrival' => 2500, 'cargo_departure' => 2500, 'cargo' => 5000,
                        ],
                        [
                            'category' => 'INTERNASIONAL',
                            'aircraft_arrival' => 250, 'aircraft_departure' => 250, 'aircraft_total' => 500,
                            'passenger_arrival' => 6000, 'passenger_departure' => 6000, 'passenger_total' => 12000,
                            'cargo_arrival' => 1200, 'cargo_departure' => 1200, 'cargo' => 2400,
                        ],
                    ],
                    'meta' => [
                        'airport_code' => 'CGK',
                        'airport_name' => 'Soekarno Hatta',
                        'start_date' => '2021-01-01',
                        'end_date' => '2021-12-31',
                        'cargo_unit' => 'Kg',
                    ]
                ]
            ],
            [
                'id' => 4,
                'airport_code' => 'CGK',
                'airport_name' => 'Soekarno Hatta',
                'start_date' => '2022-01-01',
                'end_date' => '2022-12-31',
                'report_data' => [
                    'records' => [
                        [
                            'category' => 'DOMESTIK',
                            'aircraft_arrival' => 700, 'aircraft_departure' => 700, 'aircraft_total' => 1400,
                            'passenger_arrival' => 15000, 'passenger_departure' => 15000, 'passenger_total' => 30000,
                            'cargo_arrival' => 3000, 'cargo_departure' => 3000, 'cargo' => 6000,
                        ],
                        [
                            'category' => 'INTERNASIONAL',
                            'aircraft_arrival' => 300, 'aircraft_departure' => 300, 'aircraft_total' => 600,
                            'passenger_arrival' => 8000, 'passenger_departure' => 8000, 'passenger_total' => 16000,
                            'cargo_arrival' => 1500, 'cargo_departure' => 1500, 'cargo' => 3000,
                        ],
                    ],
                    'meta' => [
                        'airport_code' => 'CGK',
                        'airport_name' => 'Soekarno Hatta',
                        'start_date' => '2022-01-01',
                        'end_date' => '2022-12-31',
                        'cargo_unit' => 'Kg',
                    ]
                ]
            ],
        ];
    }

    /**
     * Test baseline switch does NOT change Section 1 bar values or period-over-period growth.
     */
    public function test_baseline_switching_does_not_alter_operational_combo_values(): void
    {
        $reports = $this->makeMockReports();

        $compBaseA = DauComparisonService::buildComparisonModel($reports, [], 'P1');
        $compBaseB = DauComparisonService::buildComparisonModel($reports, [], 'P2');
        $compBaseC = DauComparisonService::buildComparisonModel($reports, [], 'P3');

        $metrics = ['passenger', 'aircraft', 'cargo'];

        foreach ($metrics as $mKey) {
            $trendA = $compBaseA['operational_trend'][$mKey];
            $trendB = $compBaseB['operational_trend'][$mKey];
            $trendC = $compBaseC['operational_trend'][$mKey];

            for ($i = 0; $i < count($trendA); $i++) {
                // Exact bar values must be identical
                $this->assertEquals($trendA[$i]['value'], $trendB[$i]['value']);
                $this->assertEquals($trendA[$i]['value'], $trendC[$i]['value']);

                // Exact previous values must be identical
                $this->assertEquals($trendA[$i]['previous_value'], $trendB[$i]['previous_value']);
                $this->assertEquals($trendA[$i]['previous_value'], $trendC[$i]['previous_value']);

                // Exact growth % must be identical
                $this->assertEquals($trendA[$i]['growth_pct'], $trendB[$i]['growth_pct']);
                $this->assertEquals($trendA[$i]['growth_pct'], $trendC[$i]['growth_pct']);

                // Exact formatted growth must be identical
                $this->assertEquals($trendA[$i]['growth_fmt'], $trendB[$i]['growth_fmt']);
                $this->assertEquals($trendA[$i]['growth_fmt'], $trendC[$i]['growth_fmt']);
            }
        }

        // Only baseline visual indicator flag changes
        $this->assertTrue($compBaseA['operational_trend']['passenger'][0]['is_baseline']);
        $this->assertFalse($compBaseA['operational_trend']['passenger'][1]['is_baseline']);

        $this->assertFalse($compBaseB['operational_trend']['passenger'][0]['is_baseline']);
        $this->assertTrue($compBaseB['operational_trend']['passenger'][1]['is_baseline']);

        $this->assertFalse($compBaseC['operational_trend']['passenger'][0]['is_baseline']);
        $this->assertTrue($compBaseC['operational_trend']['passenger'][2]['is_baseline']);
    }

    /**
     * Test Historical filters (DOM/INT, ARRIVAL/DEPARTURE) do NOT leak into Kinerja Operasional Bandara.
     */
    public function test_historical_traffic_filters_do_not_leak_into_section_1(): void
    {
        $reports = $this->makeMockReports();

        $compAll = DauComparisonService::buildComparisonModel($reports, [
            'hist_scope' => 'ALL',
            'hist_direction' => 'ALL'
        ]);

        $compDomArrival = DauComparisonService::buildComparisonModel($reports, [
            'hist_scope' => 'DOM',
            'hist_direction' => 'ARRIVAL'
        ]);

        $compIntDeparture = DauComparisonService::buildComparisonModel($reports, [
            'hist_scope' => 'INT',
            'hist_direction' => 'DEPARTURE'
        ]);

        // Section 1 Kinerja Operasional Bandara must remain identical across all filter variations
        $this->assertEquals(
            $compAll['operational_trend']['passenger'],
            $compDomArrival['operational_trend']['passenger']
        );
        $this->assertEquals(
            $compAll['operational_trend']['aircraft'],
            $compIntDeparture['operational_trend']['aircraft']
        );
        $this->assertEquals(
            $compAll['operational_trend']['cargo'],
            $compDomArrival['operational_trend']['cargo']
        );

        // However, Section 2 Historical Bar Model SHOULD change with filters
        $this->assertNotEquals(
            $compAll['historical_model']['metrics']['passenger']['datasets'],
            $compDomArrival['historical_model']['metrics']['passenger']['datasets']
        );
    }
}
