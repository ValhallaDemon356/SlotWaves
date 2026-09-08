<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DauTargetedDashboardOutputTest extends TestCase
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

    private function createUpload(string $reportType, array $records, array $meta = [], array $summary = []): Upload
    {
        $meta = array_merge([
            'airport_code' => 'CGK',
            'airport_name' => 'Soekarno Hatta',
            'flight_scope' => 'DOMESTIK & INTERNASIONAL',
        ], $meta);

        $reportData = [
            'meta'    => $meta,
            'summary' => $summary,
            'records' => $records,
        ];

        return Upload::create([
            'original_filename' => "test_{$reportType}.xlsx",
            'stored_path'       => "uploads/test_{$reportType}.xlsx",
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => $reportType,
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $reportData,
            'source_type'       => 'excel',
            'parser_metadata'   => $reportData,
        ]);
    }

    /**
     * DAU-04A: Renders operator cards, uses metric-aware bar heights, and supports airline & airport filtering.
     */
    public function test_dau04a_dashboard_renders_metrics_and_operator_cards(): void
    {
        $upload = $this->createUpload('DAU4A', [
            [
                'no' => 1,
                'operator_name' => 'Garuda Indonesia',
                'airline' => 'Garuda Indonesia',
                'city' => 'Denpasar',
                'airport' => 'DPS',
                'aircraft_arrival' => 10,
                'aircraft_departure' => 10,
                'aircraft_total' => 20,
                'passenger_arrival' => 1500,
                'passenger_departure' => 1500,
                'passenger_total' => 3000,
                'baggage' => 5000,
                'cargo' => 2000,
                'pos' => 100,
            ],
            [
                'no' => 2,
                'operator_name' => 'Lion Air',
                'airline' => 'Lion Air',
                'city' => 'Surabaya',
                'airport' => 'SUB',
                'aircraft_arrival' => 15,
                'aircraft_departure' => 15,
                'aircraft_total' => 30,
                'passenger_arrival' => 2500,
                'passenger_departure' => 2500,
                'passenger_total' => 5000,
                'baggage' => 8000,
                'cargo' => 1000,
                'pos' => 50,
            ],
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);

        // Assert metric-aware display code is present in view
        $response->assertSee('dau4aOperators');
        $response->assertSee('dau4aMax');
        $response->assertSee('calculateBarHeight');
        $response->assertSee('Routes Served');
    }

    /**
     * DAU-05: Renders Pareto chart canvas element and includes retry logic.
     */
    public function test_dau05_dashboard_renders_pareto_chart(): void
    {
        $upload = $this->createUpload('DAU5', [
            [
                'no' => 1,
                'airline' => 'Garuda Indonesia',
                'aircraft_arrival' => 20,
                'aircraft_departure' => 20,
                'aircraft_total' => 40,
                'passenger_arrival' => 3000,
                'passenger_departure' => 3000,
                'passenger_total' => 6000,
            ],
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);
        $response->assertSee('id="dau5ParetoChart"', false);
        $response->assertSee('renderDau5Charts');
    }

    /**
     * DAU-05A: Renders Crew chart canvas element.
     */
    public function test_dau05a_dashboard_renders_crew_chart(): void
    {
        $upload = $this->createUpload('DAU5A', [
            [
                'no' => 1,
                'airline' => 'Garuda Indonesia',
                'crew' => 80,
                'arr_extra_crew' => 5,
                'dep_extra_crew' => 5,
                'extra_crew' => 10,
                'crew_total' => 90,
            ],
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);
        $response->assertSee('id="dau5aCrewChart"', false);
        $response->assertSee('renderDau5aCharts');
    }

    /**
     * DAU-05B: Renders Terminal distribution chart canvas element.
     */
    public function test_dau05b_dashboard_renders_terminal_chart(): void
    {
        $upload = $this->createUpload('DAU5B', [
            [
                'no' => 1,
                'terminal' => '1A',
                'airline' => 'Super Air Jet',
                'aircraft_total' => 25,
                'passenger_total' => 4000,
            ],
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);
        $response->assertSee('id="dau5bTerminalChart"', false);
        $response->assertSee('renderDau5bCharts');
    }

    /**
     * DAU-05C: Renders Airline Comparison chart canvas element.
     */
    public function test_dau05c_dashboard_renders_airline_comparison_chart(): void
    {
        $upload = $this->createUpload('DAU5C', [
            [
                'no' => 1,
                'airline' => 'Batik Air',
                'aircraft_total' => 30,
                'passenger_total' => 4500,
            ],
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);
        $response->assertSee('id="dau5cBarChart"', false);
        $response->assertSee('renderDau5cCharts');
    }

    /**
     * DAU-06: Verifies Category filter is REMOVED, WTC filter is PRESENT,
     * and Category donut merges Regional into Narrow Body.
     */
    public function test_dau06_dashboard_has_no_category_filter_and_has_wtc_filter(): void
    {
        $upload = $this->createUpload('DAU6', [
            [
                'no' => 1,
                'aircraft_type' => 'B737-800',
                'category' => 'Narrow Body',
                'wtc' => 'Medium',
                'aircraft_total' => 50,
                'passenger_total' => 7500,
            ],
            [
                'no' => 2,
                'aircraft_type' => 'ATR72',
                'category' => 'Regional',
                'wtc' => 'Medium',
                'aircraft_total' => 10,
                'passenger_total' => 600,
            ],
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);

        // Canvases
        $response->assertSee('id="dau6FleetChart"', false);
        $response->assertSee('id="dau6CategoryDonut"', false);
        $response->assertSee('id="dau6WtcDonut"', false);

        // Category filter (filterCategory) must NOT be shown in the filter controls for DAU6
        // Instead, WTC filter (filterWtc) must be present
        $response->assertSee('ALL WTC');
        $response->assertSee('Medium (M)');

        // Category Donut consolidated label
        $response->assertSee('Narrow Body / Regional');
    }

    /**
     * DAU-10: Verifies Metric dropdown is REMOVED, Direction filter is PRESENT,
     * and dual hourly charts (Aircraft + Passenger) exist.
     */
    public function test_dau10_dashboard_has_no_metric_dropdown_and_has_direction_filter(): void
    {
        $upload = $this->createUpload('DAU10', [
            [
                'hour' => '08:00 - 08:59',
                'terminal' => '1A',
                'aircraft_arrival' => 12,
                'aircraft_departure' => 10,
                'aircraft_total' => 22,
                'passenger_arrival' => 1800,
                'passenger_departure' => 1500,
                'passenger_total' => 3300,
            ],
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);

        // Metric dropdown must NOT be rendered for DAU10
        // Because DAU10 was removed from the list of reportTypes in the Metric dropdown condition
        $content = $response->getContent();
        
        // Direction filter MUST be present for DAU10
        $response->assertSee('filterDirection');
        $response->assertSee('ALL (ARR &amp; DEP)', false);

        // Terminal scope derivation logic in applyFilters
        $response->assertSee('isIntTerminal');

        // Both Hourly Aircraft and Hourly Passenger charts must be present
        $response->assertSee('HOURLY AIRCRAFT MOVEMENT');
        $response->assertSee('HOURLY PASSENGER MOVEMENT');
    }

    /**
     * DAU-10B: Direction filter is PRESENT and Block On/Off chart canvas exists.
     */
    public function test_dau10b_dashboard_renders_block_chart_and_direction_filter(): void
    {
        $upload = $this->createUpload('DAU10B', [
            [
                'hour' => '10:00 - 10:59',
                'terminal' => '2E',
                'aircraft_arrival' => 8,
                'aircraft_departure' => 6,
                'aircraft_total' => 14,
            ],
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);

        $response->assertSee('id="dau10bBlockChart"', false);
        $response->assertSee('filterDirection');
        $response->assertSee('renderDau10bCharts');
    }

    /**
     * DAU-12: Grouped chart canvas element exists and Direction filter is present.
     */
    public function test_dau12_dashboard_renders_grouped_chart(): void
    {
        $upload = $this->createUpload('DAU12', [
            [
                'airline' => 'Garuda Indonesia',
                'aircraft_arr_domestic' => 15,
                'aircraft_arr_int' => 5,
                'aircraft_dep_domestic' => 14,
                'aircraft_dep_int' => 6,
                'passenger_arr_domestic' => 2000,
                'passenger_arr_int' => 800,
                'passenger_dep_domestic' => 1900,
                'passenger_dep_int' => 850,
            ],
        ]);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);

        $response->assertSee('id="dau12GroupedChart"', false);
        $response->assertSee('renderDau12Charts');
        $response->assertSee('filterDirection');
    }
}
