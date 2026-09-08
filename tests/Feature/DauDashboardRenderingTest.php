<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DauDashboardRenderingTest extends TestCase
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
     * Helper to create a completed upload with given report_data.
     */
    private function createCompletedUpload(string $reportType, array $reportData): Upload
    {
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
     * TEST 1: DAU-01 renders non-empty dataset, KPI values, and detail table.
     */
    public function test_dau01_dashboard_renders_non_empty_dataset_and_valid_js(): void
    {
        $reportData = [
            'meta' => [
                'airport_code' => 'CGK',
                'airport_name' => 'Soekarno Hatta',
                'flight_scope' => 'DOMESTIK & INTERNASIONAL',
            ],
            'summary' => [
                'total_movements'    => 1019,
                'passenger_total'    => 149298,
                'passenger_arrival'  => 75421,
                'passenger_departure'=> 67581,
                'passenger_adult'    => 138690,
                'passenger_child'    => 3525,
                'passenger_infant'   => 787,
            ],
            'records' => [
                [
                    'no'                 => 1,
                    'airline'            => 'Garuda Indonesia',
                    'airline_code'       => 'GA',
                    'airport_route'      => 'DPS',
                    'flight_number'      => 'GA 401',
                    'aircraft_type'      => 'B737-800',
                    'aircraft_arrival'   => 1,
                    'aircraft_departure' => 0,
                    'aircraft_total'     => 1,
                    'passenger_arrival'  => 150,
                    'passenger_departure'=> 0,
                    'passenger_total'    => 150,
                    'passenger_adult'    => 130,
                    'passenger_child'    => 15,
                    'passenger_infant'   => 5,
                ],
                [
                    'no'                 => 2,
                    'airline'            => 'Lion Air',
                    'airline_code'       => 'JT',
                    'airport_route'      => 'SUB',
                    'flight_number'      => 'JT 610',
                    'aircraft_type'      => 'B737-900',
                    'aircraft_arrival'   => 0,
                    'aircraft_departure' => 1,
                    'aircraft_total'     => 1,
                    'passenger_arrival'  => 0,
                    'passenger_departure'=> 180,
                    'passenger_total'    => 180,
                    'passenger_adult'    => 160,
                    'passenger_child'    => 18,
                    'passenger_infant'   => 2,
                ],
            ],
        ];

        $upload = $this->createCompletedUpload('DAU1', $reportData);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);

        // 1. Blade contains the Alpine root component
        $response->assertSee('x-data="dauEnhancedDashboard()"', false);
        $response->assertSee('x-init="initDashboard()"', false);

        // 2. Pre-rendered summary values from records are present
        $response->assertSee('DETAILED OPERATIONAL RECORDS', false);
        $response->assertSee('330');

        // 3. Extract script and evaluate in Node to ensure 0 syntax errors and proper execution
        $content = $response->getContent();
        preg_match('/<script>\s*function dauEnhancedDashboard\(\)\s*\{(.*?)<\/script>/s', $content, $m);
        $this->assertNotEmpty($m, 'dauEnhancedDashboard script block must be present');

        $js = preg_replace('/^<script>|<\/script>$/', '', trim($m[0]));
        $tmpFile = tempnam(sys_get_temp_dir(), 'dau_test_') . '.js';
        file_put_contents($tmpFile, "
            const localStorage = { getItem: () => null, setItem: () => null };
            const window = { Chart: class {} };
            const document = { getElementById: () => null };
            {$js}
            const d = dauEnhancedDashboard();
            d.\$nextTick = (fn) => { if (fn) fn(); };
            d.initDashboard();
            if (!d.filteredRecords || d.filteredRecords.length !== 2) process.exit(1);
            if (!d.paginatedRecords || d.paginatedRecords.length !== 2) process.exit(2);
            if (d.activeSummary.total_movements !== 2) process.exit(3);
            if (d.activeSummary.passenger_total !== 330) process.exit(4);
        ");

        $out = [];
        $ret = 0;
        exec('node "' . $tmpFile . '"', $out, $ret);
        unlink($tmpFile);

        $this->assertEquals(0, $ret, 'Node.js execution of DAU1 Alpine state must pass with return code 0');
    }

    /**
     * TEST 2: DAU-02 renders Comparative Breakdown with non-empty values.
     */
    public function test_dau02_dashboard_renders_comparative_breakdown_and_valid_js(): void
    {
        $reportData = [
            'meta' => [
                'airport_code' => 'CGK',
                'airport_name' => 'Soekarno Hatta',
                'flight_scope' => 'DOMESTIK & INTERNASIONAL',
            ],
            'summary' => [
                'total_movements'    => 1019,
                'passenger_total'    => 149298,
            ],
            'records' => [
                [
                    'category'           => 'DOMESTIK',
                    'aircraft_arrival'   => 371,
                    'aircraft_departure' => 364,
                    'aircraft_total'     => 735,
                    'passenger_arrival'  => 49031,
                    'passenger_departure'=> 41105,
                    'passenger_total'    => 96381,
                    'baggage'            => 891752,
                    'cargo'              => 902177,
                    'pos'                => 0,
                ],
                [
                    'category'           => 'INTERNASIONAL',
                    'aircraft_arrival'   => 139,
                    'aircraft_departure' => 145,
                    'aircraft_total'     => 284,
                    'passenger_arrival'  => 26390,
                    'passenger_departure'=> 26476,
                    'passenger_total'    => 52917,
                    'baggage'            => 828169,
                    'cargo'              => 870825,
                    'pos'                => 0,
                ],
            ],
        ];

        $upload = $this->createCompletedUpload('DAU2', $reportData);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);

        // Comparative Breakdown section must be in HTML
        $response->assertSee('COMPARATIVE BREAKDOWN', false);

        // Extract script and verify Node execution
        $content = $response->getContent();
        preg_match('/<script>\s*function dauEnhancedDashboard\(\)\s*\{(.*?)<\/script>/s', $content, $m);
        $this->assertNotEmpty($m, 'dauEnhancedDashboard script block must be present');

        $js = preg_replace('/^<script>|<\/script>$/', '', trim($m[0]));
        $tmpFile = tempnam(sys_get_temp_dir(), 'dau_test_') . '.js';
        file_put_contents($tmpFile, "
            const localStorage = { getItem: () => null, setItem: () => null };
            const window = { Chart: class {} };
            const document = { getElementById: () => null };
            {$js}
            const d = dauEnhancedDashboard();
            d.\$nextTick = (fn) => { if (fn) fn(); };
            d.initDashboard();
            if (!d.dau2Comparative || !d.dau2Comparative.aircraft) process.exit(1);
            if (d.dau2Comparative.aircraft.domestic !== 735) process.exit(2);
            if (d.dau2Comparative.aircraft.international !== 284) process.exit(3);
            if (d.dau2Comparative.aircraft.total !== 1019) process.exit(4);
            if (d.dau2Comparative.passenger.total !== 149298) process.exit(5);
        ");

        $out = [];
        $ret = 0;
        exec('node "' . $tmpFile . '"', $out, $ret);
        unlink($tmpFile);

        $this->assertEquals(0, $ret, 'Node.js execution of DAU2 Alpine comparative breakdown must pass');
    }

    /**
     * TEST 3: Deterministic filter interaction and state synchronization test.
     * Raw: 3 records (Garuda 100, Lion 200, Garuda 300). Total: 600.
     * Filter GA -> 2 records, 400 pax.
     * Reset -> 3 records, 600 pax.
     */
    public function test_dau01_filter_state_interaction_and_synchronization(): void
    {
        $reportData = [
            'meta' => [
                'airport_code' => 'CGK',
                'airport_name' => 'Soekarno Hatta',
                'flight_scope' => 'DOMESTIK & INTERNASIONAL',
            ],
            'summary' => [
                'total_movements'    => 3,
                'passenger_total'    => 600,
            ],
            'records' => [
                [
                    'no'                 => 1,
                    'airline'            => 'Garuda Indonesia',
                    'airline_code'       => 'GA',
                    'airport_route'      => 'DPS',
                    'aircraft_arrival'   => 1,
                    'aircraft_departure' => 0,
                    'aircraft_total'     => 1,
                    'passenger_arrival'  => 100,
                    'passenger_departure'=> 0,
                    'passenger_total'    => 100,
                ],
                [
                    'no'                 => 2,
                    'airline'            => 'Lion Air',
                    'airline_code'       => 'JT',
                    'airport_route'      => 'SUB',
                    'aircraft_arrival'   => 0,
                    'aircraft_departure' => 1,
                    'aircraft_total'     => 1,
                    'passenger_arrival'  => 0,
                    'passenger_departure'=> 200,
                    'passenger_total'    => 200,
                ],
                [
                    'no'                 => 3,
                    'airline'            => 'Garuda Indonesia',
                    'airline_code'       => 'GA',
                    'airport_route'      => 'DPS',
                    'aircraft_arrival'   => 0,
                    'aircraft_departure' => 1,
                    'aircraft_total'     => 1,
                    'passenger_arrival'  => 0,
                    'passenger_departure'=> 300,
                    'passenger_total'    => 300,
                ],
            ],
        ];

        $upload = $this->createCompletedUpload('DAU1', $reportData);

        $response = $this->get(route('dau.dashboard', $upload->id));
        $response->assertStatus(200);

        $content = $response->getContent();
        preg_match('/<script>\s*function dauEnhancedDashboard\(\)\s*\{(.*?)<\/script>/s', $content, $m);
        $this->assertNotEmpty($m);

        $js = preg_replace('/^<script>|<\/script>$/', '', trim($m[0]));
        $tmpFile = tempnam(sys_get_temp_dir(), 'dau_test_') . '.js';
        file_put_contents($tmpFile, "
            const localStorage = { getItem: () => null, setItem: () => null };
            const window = { Chart: class {} };
            const document = { getElementById: () => null };
            {$js}
            const d = dauEnhancedDashboard();
            d.\$nextTick = (fn) => { if (fn) fn(); };
            d.initDashboard();

            // 1. Initial State: 3 records, 600 pax
            if (d.filteredRecords.length !== 3) process.exit(1);
            if (d.activeSummary.passenger_total !== 600) process.exit(2);
            if (d.paginatedRecords.length !== 3) process.exit(3);

            // 2. Filter Airline = Garuda Indonesia
            d.filterAirline = 'Garuda Indonesia';
            d.applyFilters();
            if (d.filteredRecords.length !== 2) process.exit(4);
            if (d.activeSummary.passenger_total !== 400) process.exit(5);
            if (d.paginatedRecords.length !== 2) process.exit(6);

            // 3. Reset Filters -> Returns to initial 3 records, 600 pax
            d.resetFilters();
            if (d.filteredRecords.length !== 3) process.exit(7);
            if (d.activeSummary.passenger_total !== 600) process.exit(8);
        ");

        $out = [];
        $ret = 0;
        exec('node "' . $tmpFile . '"', $out, $ret);
        unlink($tmpFile);

        $this->assertEquals(0, $ret, 'Node.js execution of filter interaction and reset must pass with code 0');
    }
}
