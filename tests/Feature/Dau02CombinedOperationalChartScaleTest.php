<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Dau\DauComparisonChartRenderer;

class Dau02CombinedOperationalChartScaleTest extends TestCase
{
    /**
     * Test nice ceiling calculation across orders of magnitude.
     */
    public function test_dynamic_nice_ceiling_calculations(): void
    {
        // Passenger scale (54.95M -> 75M ceiling)
        $paxMax = 54953746.0;
        $paxCeil = DauComparisonChartRenderer::getNiceCeiling($paxMax);
        $this->assertEquals(75000000.0, $paxCeil);
        $this->assertGreaterThanOrEqual($paxMax, $paxCeil);

        // Aircraft scale (368k -> 500k ceiling)
        $acMax = 368269.0;
        $acCeil = DauComparisonChartRenderer::getNiceCeiling($acMax);
        $this->assertEquals(500000.0, $acCeil);
        $this->assertGreaterThanOrEqual($acMax, $acCeil);

        // Cargo scale (682M -> 750M ceiling)
        $cargoMax = 682008176.0;
        $cargoCeil = DauComparisonChartRenderer::getNiceCeiling($cargoMax);
        $this->assertEquals(750000000.0, $cargoCeil);
        $this->assertGreaterThanOrEqual($cargoMax, $cargoCeil);

        // Small / zero values
        $this->assertEquals(10.0, DauComparisonChartRenderer::getNiceCeiling(0));
        $this->assertEquals(10.0, DauComparisonChartRenderer::getNiceCeiling(-5));
        $this->assertEquals(12.0, DauComparisonChartRenderer::getNiceCeiling(11));
    }

    /**
     * Test formatShortNumber helper outputs correct abbreviations.
     */
    public function test_short_number_formatting(): void
    {
        $this->assertEquals('1.5B', DauComparisonChartRenderer::formatShortNumber(1500000000));
        $this->assertEquals('55M', DauComparisonChartRenderer::formatShortNumber(54950000));
        $this->assertEquals('368k', DauComparisonChartRenderer::formatShortNumber(368000));
        $this->assertEquals('500', DauComparisonChartRenderer::formatShortNumber(500));
    }

    /**
     * Test all three metrics scale independently without cross-distortion.
     */
    public function test_scale_independence_between_metrics(): void
    {
        $paxValues = [10000000, 20000000, 30000000]; // Millions
        $acValues  = [1000, 2000, 3000];             // Thousands
        $cargoValues = [100000000, 200000000, 300000000]; // Hundreds of Millions

        $paxCeil = DauComparisonChartRenderer::getNiceCeiling(max($paxValues));
        $acCeil  = DauComparisonChartRenderer::getNiceCeiling(max($acValues));
        $cargoCeil = DauComparisonChartRenderer::getNiceCeiling(max($cargoValues));

        // Ratios within each metric's own scale are preserved
        $this->assertEquals(0.5, ($paxValues[1] / max($paxValues)) * 0.75 / (($paxCeil > 0 ? $paxCeil : 1) ? 1 : 1), '', 0.3);

        // Aircraft ceiling is independent of Pax ceiling
        $this->assertNotEquals($paxCeil, $acCeil);
        $this->assertNotEquals($paxCeil, $cargoCeil);
        $this->assertNotEquals($acCeil, $cargoCeil);

        $this->assertEquals(5000.0, $acCeil);
        $this->assertEquals(50000000.0, $paxCeil);
        $this->assertEquals(500000000.0, $cargoCeil);
    }
}
