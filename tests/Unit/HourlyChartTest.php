<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\HourlyChartService;
use App\Services\FlightDailyReport\FlightDailyReportParser;

class HourlyChartTest extends TestCase
{
    protected HourlyChartService $hourlyChartService;
    protected array $records;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hourlyChartService = new HourlyChartService();
        $parser = new FlightDailyReportParser();
        $path = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        $parsed = $parser->parse($path);
        $this->records = $parsed['records'];
    }

    public function test_mandatory_twenty_four_hours_always_visible(): void
    {
        // Even with empty records, all 24 hours (00 to 23) must remain visible
        $chartsEmpty = $this->hourlyChartService->buildHourlyCharts([], 'CGK');

        $this->assertCount(24, $chartsEmpty['hours']);
        $this->assertCount(24, $chartsEmpty['hourly_data']);
        $this->assertEquals('00:00', $chartsEmpty['hours'][0]);
        $this->assertEquals('23:00', $chartsEmpty['hours'][23]);

        // With full records
        $chartsFull = $this->hourlyChartService->buildHourlyCharts($this->records, 'CGK');
        $this->assertCount(24, $chartsFull['hours']);
        $this->assertCount(24, $chartsFull['hourly_data']);
    }

    public function test_chart_one_contains_plan_irregular_and_variable_capacity_line(): void
    {
        $charts = $this->hourlyChartService->buildHourlyCharts($this->records, 'CGK');
        $c1 = $charts['chart1_movement'];

        $this->assertEquals('ARRIVAL–DEPARTURE MOVEMENT (24 HOURS)', $c1['title']);
        $this->assertCount(3, $c1['datasets']);

        // Dataset 0: Runway Capacity line
        $capDataset = $c1['datasets'][0];
        $this->assertEquals('line', $capDataset['type']);
        $this->assertEquals('#EF4444', $capDataset['borderColor']);
        $this->assertCount(24, $capDataset['data']);

        // Verify runway capacity line varies across 24 hours (never a static flat line)
        $uniqueCapacities = array_unique($capDataset['data']);
        $this->assertGreaterThan(1, count($uniqueCapacities), "Runway capacity line must vary by hour and not be flat.");

        // Dataset 1: Plan bar
        $planDataset = $c1['datasets'][1];
        $this->assertEquals('bar', $planDataset['type']);
        $this->assertEquals('#FDBA74', $planDataset['backgroundColor']);

        // Dataset 2: Irregular bar
        $irregDataset = $c1['datasets'][2];
        $this->assertEquals('bar', $irregDataset['type']);
        $this->assertEquals('#D97706', $irregDataset['backgroundColor']);
    }

    public function test_chart_two_departure_uses_blue_semantic_palette(): void
    {
        $charts = $this->hourlyChartService->buildHourlyCharts($this->records, 'CGK');
        $c2 = $charts['chart2_departure'];

        $this->assertEquals('DEPARTURE MOVEMENT (24 HOURS)', $c2['title']);
        $this->assertCount(2, $c2['datasets']);

        // Plan: Light blue
        $this->assertEquals('#93C5FD', $c2['datasets'][0]['backgroundColor']);
        // Irregular: Darker blue
        $this->assertEquals('#1D4ED8', $c2['datasets'][1]['backgroundColor']);
    }

    public function test_chart_three_arrival_uses_salmon_magenta_palette(): void
    {
        $charts = $this->hourlyChartService->buildHourlyCharts($this->records, 'CGK');
        $c3 = $charts['chart3_arrival'];

        $this->assertEquals('ARRIVAL MOVEMENT (24 HOURS)', $c3['title']);
        $this->assertCount(2, $c3['datasets']);

        // Plan: Light salmon
        $this->assertEquals('#FDA4AF', $c3['datasets'][0]['backgroundColor']);
        // Irregular: Dark magenta
        $this->assertEquals('#BE185D', $c3['datasets'][1]['backgroundColor']);
    }

    public function test_hourly_tooltip_format_and_status(): void
    {
        $charts = $this->hourlyChartService->buildHourlyCharts($this->records, 'CGK');
        $h13 = $charts['hourly_data'][13];

        $this->assertArrayHasKey('tooltip', $h13);
        $tooltip = $h13['tooltip'];

        // Format: HH:00–HH:59 | Plan: XX | Irregular: XX | Capacity: XX | Delta: XX | Status: AVAILABLE / FULL / OVER
        $this->assertStringContainsString('13:00–13:59', $tooltip);
        $this->assertStringContainsString('Plan:', $tooltip);
        $this->assertStringContainsString('Irregular:', $tooltip);
        $this->assertStringContainsString('Capacity:', $tooltip);
        $this->assertStringContainsString('Delta:', $tooltip);
        $this->assertMatchesRegularExpression('/Status:\s*(AVAILABLE|FULL|OVER)/', $tooltip);
    }

    public function test_render_chart_svg_generates_valid_vector(): void
    {
        $charts = $this->hourlyChartService->buildHourlyCharts($this->records, 'CGK');
        $svg1 = $this->hourlyChartService->renderChartSvg('movement', $charts['hourly_data']);

        $this->assertStringStartsWith('<svg', $svg1);
        $this->assertStringEndsWith("</svg>\n", $svg1);
        $this->assertStringContainsString('stroke="#EF4444"', $svg1); // Red capacity line
        $this->assertStringContainsString('#FDBA74', $svg1); // Plan bar
        $this->assertStringContainsString('#D97706', $svg1); // Irregular bar
    }
}
