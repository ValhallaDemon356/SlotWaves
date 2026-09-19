<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Dau\DauComparisonService;

class Dau02OperationalGrowthCalculationTest extends TestCase
{
    /**
     * Helper to mock periods array with arbitrary metric values.
     */
    private function makeMockPeriods(array $paxValues, array $acValues = [], array $cargoValues = []): array
    {
        $periods = [];
        $letters = range('A', 'Z');
        $n = count($paxValues);

        for ($i = 0; $i < $n; $i++) {
            $key = 'P' . ($i + 1);
            $year = 2020 + $i;
            $periods[$key] = [
                'id'          => $i + 1,
                'key'         => $key,
                'label'       => 'PERIOD ' . ($letters[$i] ?? ($i + 1)),
                'short_label' => (string)$year,
                'raw_totals'  => [
                    'passenger' => $paxValues[$i] ?? 0,
                    'aircraft'  => $acValues[$i] ?? 100,
                    'cargo'     => $cargoValues[$i] ?? 1000,
                ],
                'metrics'     => [
                    'cargo_unit' => 'Kg',
                ]
            ];
        }

        return $periods;
    }

    /**
     * Test sequence: 100 -> 120 -> 110 -> 150
     * Expected: N/A -> +20.00% -> -8.33% -> +36.36%
     */
    public function test_period_over_period_standard_growth_sequence(): void
    {
        $periods = $this->makeMockPeriods([100, 120, 110, 150]);
        $trend = DauComparisonService::buildOperationalTrend($periods, 'P1');

        $pax = $trend['passenger'];
        $this->assertCount(4, $pax);

        // Period 1 (100)
        $this->assertEquals(100, $pax[0]['value']);
        $this->assertNull($pax[0]['previous_value']);
        $this->assertNull($pax[0]['growth_pct']);
        $this->assertEquals('N/A', $pax[0]['growth_fmt']);

        // Period 2 (120)
        $this->assertEquals(120, $pax[1]['value']);
        $this->assertEquals(100, $pax[1]['previous_value']);
        $this->assertEquals(20.0, $pax[1]['growth_pct']);
        $this->assertEquals('+20.00%', $pax[1]['growth_fmt']);

        // Period 3 (110)
        $this->assertEquals(110, $pax[2]['value']);
        $this->assertEquals(120, $pax[2]['previous_value']);
        $this->assertEquals(-8.33, $pax[2]['growth_pct']);
        $this->assertEquals('-8.33%', $pax[2]['growth_fmt']);

        // Period 4 (150)
        $this->assertEquals(150, $pax[3]['value']);
        $this->assertEquals(110, $pax[3]['previous_value']);
        $this->assertEquals(36.36, $pax[3]['growth_pct']);
        $this->assertEquals('+36.36%', $pax[3]['growth_fmt']);
    }

    /**
     * Test zero previous period handling: 100 -> 0 -> 50
     * Expected: N/A -> -100.00% -> N/A (no Infinity, no NaN)
     */
    public function test_zero_previous_period_does_not_produce_infinity_or_nan(): void
    {
        $periods = $this->makeMockPeriods([100, 0, 50]);
        $trend = DauComparisonService::buildOperationalTrend($periods, 'P1');

        $pax = $trend['passenger'];
        $this->assertCount(3, $pax);

        // Period 1: 100 -> N/A
        $this->assertNull($pax[0]['growth_pct']);
        $this->assertEquals('N/A', $pax[0]['growth_fmt']);

        // Period 2: 0 -> -100%
        $this->assertEquals(0, $pax[1]['value']);
        $this->assertEquals(100, $pax[1]['previous_value']);
        $this->assertEquals(-100.0, $pax[1]['growth_pct']);
        $this->assertEquals('-100.00%', $pax[1]['growth_fmt']);

        // Period 3: 50 from previous 0 -> N/A (no division by zero)
        $this->assertEquals(50, $pax[2]['value']);
        $this->assertEquals(0, $pax[2]['previous_value']);
        $this->assertNull($pax[2]['growth_pct']);
        $this->assertEquals('N/A', $pax[2]['growth_fmt']);
    }

    /**
     * Test negative growth: 200 -> 150
     * Expected: -25.00%
     */
    public function test_negative_growth_is_preserved(): void
    {
        $periods = $this->makeMockPeriods([200, 150]);
        $trend = DauComparisonService::buildOperationalTrend($periods, 'P1');

        $pax = $trend['passenger'];
        $this->assertCount(2, $pax);

        $this->assertEquals(200, $pax[0]['value']);
        $this->assertNull($pax[0]['growth_pct']);

        $this->assertEquals(150, $pax[1]['value']);
        $this->assertEquals(200, $pax[1]['previous_value']);
        $this->assertEquals(-25.0, $pax[1]['growth_pct']);
        $this->assertEquals('-25.00%', $pax[1]['growth_fmt']);
    }

    /**
     * Test 2-period, 3-period, and 6-period dynamic scaling without hardcoding 6 periods.
     */
    public function test_supports_arbitrary_number_of_periods(): void
    {
        // 2 periods
        $trend2 = DauComparisonService::buildOperationalTrend($this->makeMockPeriods([100, 120]));
        $this->assertCount(2, $trend2['passenger']);
        $this->assertEquals('+20.00%', $trend2['passenger'][1]['growth_fmt']);

        // 3 periods
        $trend3 = DauComparisonService::buildOperationalTrend($this->makeMockPeriods([100, 120, 150]));
        $this->assertCount(3, $trend3['passenger']);
        $this->assertEquals('+25.00%', $trend3['passenger'][2]['growth_fmt']);

        // 6 periods
        $trend6 = DauComparisonService::buildOperationalTrend($this->makeMockPeriods([100, 110, 121, 133.1, 146.41, 161.05]));
        $this->assertCount(6, $trend6['passenger']);
        $this->assertEquals('+10.00%', $trend6['passenger'][1]['growth_fmt']);
    }

    /**
     * Test all 3 metrics (passenger, aircraft, cargo) computed simultaneously.
     */
    public function test_all_three_metrics_computed_independently(): void
    {
        $periods = $this->makeMockPeriods(
            [1000, 1100], // Pax: +10%
            [200, 180],   // Aircraft: -10%
            [5000, 6000]  // Cargo: +20%
        );

        $trend = DauComparisonService::buildOperationalTrend($periods);

        $this->assertEquals('+10.00%', $trend['passenger'][1]['growth_fmt']);
        $this->assertEquals('-10.00%', $trend['aircraft'][1]['growth_fmt']);
        $this->assertEquals('+20.00%', $trend['cargo'][1]['growth_fmt']);
    }
}
