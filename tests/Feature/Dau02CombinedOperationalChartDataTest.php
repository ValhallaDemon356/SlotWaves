<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use Database\Seeders\MasterDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class Dau02CombinedOperationalChartDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDatabaseSeeder::class);
        Storage::fake('local');
    }

    private function createReport(int $year, ?int $pax, ?int $ac, ?int $cargo): Upload
    {
        $records = [];
        if ($pax !== null || $ac !== null || $cargo !== null) {
            $records[] = [
                'category' => 'DOMESTIK',
                'aircraft_arrival' => (int)(($ac ?? 0) / 2), 'aircraft_departure' => (int)(($ac ?? 0) / 2), 'aircraft_total' => $ac ?? 0,
                'passenger_arrival' => (int)(($pax ?? 0) / 2), 'passenger_departure' => (int)(($pax ?? 0) / 2), 'passenger_total' => $pax ?? 0,
                'cargo_arrival' => (int)(($cargo ?? 0) / 2), 'cargo_departure' => (int)(($cargo ?? 0) / 2), 'cargo' => $cargo ?? 0,
            ];
        }

        return Upload::create([
            'report_type'       => 'DAU2',
            'status'            => 'completed',
            'original_filename' => "dau_{$year}.xls",
            'stored_path'       => "uploads/dau_{$year}.xls",
            'report_data'       => [
                'records' => $records,
                'meta' => [
                    'airport_code' => 'CGK', 'airport_name' => 'Soekarno Hatta',
                    'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'cargo_unit' => 'Kg',
                ]
            ]
        ]);
    }

    /**
     * Test chronological sorting of periods regardless of insertion order.
     */
    public function test_periods_are_sorted_chronologically(): void
    {
        $u2022 = $this->createReport(2022, 3000, 50, 1000);
        $u2020 = $this->createReport(2020, 1000, 20, 500);
        $u2021 = $this->createReport(2021, 2000, 35, 800);

        // Upload IDs in mixed order: 2022, 2020, 2021
        $res = $this->get("/dau/compare?reports={$u2022->id},{$u2020->id},{$u2021->id}");
        $res->assertStatus(200);

        $comp = $res->viewData('comparison');
        $labels = array_map(fn($p) => $p['short_label'], array_values($comp['periods']));

        $this->assertEquals(['2020', '2021', '2022'], $labels);
    }

    /**
     * Test valid zero values are preserved as 0, not hidden.
     */
    public function test_zero_values_are_preserved_correctly(): void
    {
        $u1 = $this->createReport(2020, 0, 0, 0);
        $u2 = $this->createReport(2021, 5000, 100, 2000);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $res->assertStatus(200);

        $comp = $res->viewData('comparison');
        $trend = $comp['operational_trend'];

        $this->assertEquals(0, $trend['passenger'][0]['value']);
        $this->assertEquals(0, $trend['aircraft'][0]['value']);
        $this->assertEquals(0, $trend['cargo'][0]['value']);

        // Check values are not NaN or undefined
        $this->assertFalse(is_nan($trend['passenger'][0]['value']));
        $this->assertFalse(is_infinite($trend['passenger'][0]['value']));
    }

    /**
     * Test data contract consistency across metrics, operational_trend, and view.
     */
    public function test_data_contract_consistency_across_layers(): void
    {
        $u1 = $this->createReport(2020, 10500, 210, 5500);
        $u2 = $this->createReport(2021, 12600, 230, 6100);

        $res = $this->get("/dau/compare?reports={$u1->id},{$u2->id}");
        $res->assertStatus(200);

        $comp = $res->viewData('comparison');
        $periods = array_values($comp['periods']);
        $trend = $comp['operational_trend'];

        foreach ($periods as $idx => $p) {
            $this->assertEquals($p['metrics']['passenger'], $trend['passenger'][$idx]['value']);
            $this->assertEquals($p['metrics']['aircraft'],  $trend['aircraft'][$idx]['value']);
            $this->assertEquals($p['metrics']['cargo'],     $trend['cargo'][$idx]['value']);

            $this->assertIsNumeric($trend['passenger'][$idx]['value']);
            $this->assertIsNumeric($trend['aircraft'][$idx]['value']);
            $this->assertIsNumeric($trend['cargo'][$idx]['value']);

            $this->assertFalse(is_nan($trend['passenger'][$idx]['value']));
            $this->assertFalse(is_infinite($trend['passenger'][$idx]['value']));
        }
    }
}
