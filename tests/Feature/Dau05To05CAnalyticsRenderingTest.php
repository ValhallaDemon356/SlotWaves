<?php

namespace Tests\Feature;

use App\Models\Upload;
use App\Models\Airport;
use App\Services\Dau\Parsers\DAU5Parser;
use App\Services\Dau\Parsers\DAU5AParser;
use App\Services\Dau\Parsers\DAU5BParser;
use App\Services\Dau\Parsers\DAU5CParser;
use App\Services\Dau\Parsers\DAU10AParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dau05To05CAnalyticsRenderingTest
 *
 * Validates that DAU-05, DAU-05A, DAU-05B, and DAU-05C dashboards:
 *  1. Return HTTP 200
 *  2. Embed chart canvas elements in the page
 *  3. Pass records containing required field keys to Alpine/JS
 *  4. Handle metric and airline filter parameters
 *  5. Do not regress DAU-10A behavior
 */
class Dau05To05CAnalyticsRenderingTest extends TestCase
{
    use RefreshDatabase;

    private Airport $airport;
    private Upload $dau5Upload;
    private Upload $dau5aUpload;
    private Upload $dau5bUpload;
    private Upload $dau5cUpload;
    private Upload $dau10aUpload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->airport = Airport::firstOrCreate(
            ['iata_code' => 'CGK'],
            ['name' => 'Soekarno Hatta', 'city' => 'Tangerang', 'country' => 'Indonesia']
        );

        $this->dau5Upload   = $this->createUpload('DAU5',  'DAU-5.xls',  new DAU5Parser());
        $this->dau5aUpload  = $this->createUpload('DAU5A', 'DAU-5A.xls', new DAU5AParser());
        $this->dau5bUpload  = $this->createUpload('DAU5B', 'DAU-5B.xls', new DAU5BParser());
        $this->dau5cUpload  = $this->createUpload('DAU5C', 'DAU-5C.xls', new DAU5CParser());
        $this->dau10aUpload = $this->createUpload('DAU10A', 'DAU-10A.xls', new DAU10AParser());
    }

    private function createUpload(string $reportType, string $filename, object $parser): Upload
    {
        $path   = base_path("resources/templates/dau/{$filename}");
        $parsed = $parser->parse($path);
        return Upload::create([
            'original_filename' => $filename,
            'stored_path'       => "uploads/{$filename}",
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => $reportType,
            'status'            => 'completed',
            'season'            => 'summer',
            'report_data'       => $parsed,
            'source_type'       => 'excel',
            'parser_metadata'   => $parsed,
        ]);
    }

    private function records(Upload $upload): array
    {
        $data = $upload->report_data ?? [];
        if (in_array($upload->report_type, ['DAU10A', 'DAU4B']) && !empty($data['normalized_pairs'])) {
            return $data['normalized_pairs'];
        }
        return $data['records'] ?? [];
    }

    // === DAU-05 ===

    public function test_dau05_dashboard_renders_successfully(): void
    {
        $this->get(route('dau.dashboard', $this->dau5Upload->id))->assertOk();
    }

    public function test_dau05_dashboard_contains_pareto_canvas(): void
    {
        $this->get(route('dau.dashboard', $this->dau5Upload->id))
             ->assertOk()->assertSee('dau5ParetoChart');
    }

    public function test_dau05_records_are_passed_to_blade(): void
    {
        $records = $this->records($this->dau5Upload);
        $this->assertNotEmpty($records, 'DAU-5 parser must return non-empty records array');
        $this->get(route('dau.dashboard', $this->dau5Upload->id))->assertOk()->assertSee('allRecords');
    }

    public function test_dau05_records_have_required_fields(): void
    {
        $records = $this->records($this->dau5Upload);
        $this->assertNotEmpty($records);
        foreach ($records as $i => $r) {
            $this->assertArrayHasKey('airline', $r, "Record #{$i} missing 'airline'");
            $this->assertArrayHasKey('aircraft_arrival', $r, "Record #{$i} missing 'aircraft_arrival'");
            $this->assertArrayHasKey('aircraft_departure', $r, "Record #{$i} missing 'aircraft_departure'");
            $this->assertArrayHasKey('passenger_total', $r, "Record #{$i} missing 'passenger_total'");
        }
    }

    public function test_dau05_records_produce_nonzero_airline_volumes(): void
    {
        $records = $this->records($this->dau5Upload);
        $nonZero = 0;
        foreach ($records as $r) {
            $t = (int)($r['aircraft_total'] ?? 0) + (int)($r['aircraft_arrival'] ?? 0) + (int)($r['aircraft_departure'] ?? 0);
            if ($t > 0) $nonZero++;
        }
        $this->assertGreaterThan(0, $nonZero, 'All DAU-5 aircraft volumes are zero ??? Pareto chart will be blank');
    }

    public function test_dau05_airline_field_is_nonempty_string(): void
    {
        $records = $this->records($this->dau5Upload);
        foreach ($records as $i => $r) {
            $this->assertNotEmpty($r['airline'] ?? '', "Record #{$i} has empty airline field");
        }
    }

    public function test_dau05_dashboard_with_metric_aircraft(): void
    {
        $this->get(route('dau.dashboard', $this->dau5Upload->id) . '?metric=aircraft')
             ->assertOk()->assertSee('dau5ParetoChart');
    }

    public function test_dau05_dashboard_with_metric_passenger(): void
    {
        $this->get(route('dau.dashboard', $this->dau5Upload->id) . '?metric=passenger')
             ->assertOk()->assertSee('dau5ParetoChart');
    }

    public function test_dau05_airline_filter_parameter_is_supported(): void
    {
        $records = $this->records($this->dau5Upload);
        $firstAl = $records[0]['airline'] ?? null;
        if ($firstAl === null) { $this->markTestSkipped('No airline in first record'); }
        $this->get(route('dau.dashboard', $this->dau5Upload->id) . '?airline=' . urlencode($firstAl))->assertOk();
    }

    // === DAU-05A ===

    public function test_dau05a_dashboard_renders_successfully(): void
    {
        $this->get(route('dau.dashboard', $this->dau5aUpload->id))->assertOk();
    }

    public function test_dau05a_dashboard_contains_crew_chart_canvas(): void
    {
        $this->get(route('dau.dashboard', $this->dau5aUpload->id))->assertOk()->assertSee('dau5aCrewChart');
    }

    public function test_dau05a_records_have_crew_fields(): void
    {
        $records  = $this->records($this->dau5aUpload);
        $this->assertNotEmpty($records);
        $hasCrew  = false;
        foreach ($records as $r) {
            if (isset($r['crew']) || isset($r['extra_crew']) || isset($r['arr_extra_crew'])) { $hasCrew = true; break; }
        }
        $this->assertTrue($hasCrew, 'No DAU-5A record has crew fields ??? crew chart will be blank');
    }

    public function test_dau05a_records_produce_nonzero_crew_totals(): void
    {
        $records = $this->records($this->dau5aUpload);
        $nonZero = 0;
        foreach ($records as $r) {
            $c = (int)($r['crew'] ?? 0) + (int)($r['extra_crew'] ?? 0) + (int)($r['crew_total'] ?? 0);
            if ($c > 0) $nonZero++;
        }
        $this->assertGreaterThan(0, $nonZero, 'All DAU-5A crew values are zero ??? crew chart will be blank');
    }

    // === DAU-05B ===

    public function test_dau05b_dashboard_renders_successfully(): void
    {
        $this->get(route('dau.dashboard', $this->dau5bUpload->id))->assertOk();
    }

    public function test_dau05b_dashboard_contains_terminal_chart_canvas(): void
    {
        $this->get(route('dau.dashboard', $this->dau5bUpload->id))->assertOk()->assertSee('dau5bTerminalChart');
    }

    public function test_dau05b_records_have_terminal_and_airline_fields(): void
    {
        $records     = $this->records($this->dau5bUpload);
        $this->assertNotEmpty($records);
        $hasTerminal = false;
        $hasAirline  = false;
        foreach ($records as $r) {
            if (!empty($r['terminal'])) $hasTerminal = true;
            if (!empty($r['airline']) || !empty($r['operator_name'])) $hasAirline = true;
            if ($hasTerminal && $hasAirline) break;
        }
        $this->assertTrue($hasTerminal, 'No DAU-5B record has terminal field');
        $this->assertTrue($hasAirline,  'No DAU-5B record has airline field');
    }

    public function test_dau05b_records_have_at_least_one_terminal(): void
    {
        $records   = $this->records($this->dau5bUpload);
        $terminals = array_unique(array_filter(array_column($records, 'terminal')));
        $this->assertGreaterThanOrEqual(1, count($terminals), 'No distinct terminals found in DAU-5B');
    }

    // === DAU-05C ===

    public function test_dau05c_dashboard_renders_successfully(): void
    {
        $this->get(route('dau.dashboard', $this->dau5cUpload->id))->assertOk();
    }

    public function test_dau05c_dashboard_contains_bar_chart_canvas(): void
    {
        $this->get(route('dau.dashboard', $this->dau5cUpload->id))->assertOk()->assertSee('dau5cBarChart');
    }

    public function test_dau05c_records_share_structure_with_dau05(): void
    {
        $r5c = $this->records($this->dau5cUpload);
        $this->assertNotEmpty($r5c);
        $this->assertArrayHasKey('airline', $r5c[0], 'DAU-5C records must have airline field');
    }

    public function test_dau05c_records_produce_nonzero_airline_volumes(): void
    {
        $records = $this->records($this->dau5cUpload);
        $nonZero = 0;
        foreach ($records as $r) {
            $t = (int)($r['aircraft_total'] ?? 0) + (int)($r['aircraft_arrival'] ?? 0) + (int)($r['aircraft_departure'] ?? 0);
            if ($t > 0) $nonZero++;
        }
        $this->assertGreaterThan(0, $nonZero, 'All DAU-5C records have zero volumes ??? chart will be blank');
    }

    // === DAU-10A Regression Guard ===

    public function test_dau10a_dashboard_regression_guard(): void
    {
        $this->get(route('dau.dashboard', $this->dau10aUpload->id))->assertOk();
    }

    public function test_dau10a_metric_switcher_still_present(): void
    {
        $this->get(route('dau.dashboard', $this->dau10aUpload->id))
             ->assertOk()->assertSee('PESAWAT')->assertSee('PENUMPANG');
    }

    public function test_dau10a_does_not_show_dau5_canvases(): void
    {
        // The JS code contains references to dau5ParetoChart, but the HTML canvas
        // element itself should only be rendered inside the @if ($reportType === 'DAU5') block.
        // We check that the canvas HTML tag is absent (not the JS string reference).
        $response = $this->get(route('dau.dashboard', $this->dau10aUpload->id));
        $response->assertOk();
        // The canvas element (HTML) must not be present for DAU-10A
        $response->assertDontSee('<canvas id="dau5ParetoChart"', false);
        $response->assertDontSee('<canvas id="dau5aCrewChart"', false);
    }
}
