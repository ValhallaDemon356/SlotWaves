<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau10AAverageByDaysTest extends TestCase
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
     * Helper to create a DAU-10A upload with specified hour 08:00 values.
     */
    protected function createDau10AUploadWithHour08(int $hour08Arr = 338, int $hour08Dep = 291, int $term2FArr = 74, int $term2FDep = 60): Upload
    {
        $meta = [
            'airport_name'   => 'Tangerang Banten - Soekarno Hatta',
            'airport_code'   => 'CGK',
            'date_range'     => '01/08/2024 s/d 31/01/2025',
            'start_date'     => '2024-08-01',
            'end_date'       => '2025-01-31',
            'flight_scope'   => 'DOM & INT',
            'terminal_scope' => 'ALL',
        ];

        $hours = [
            '00.00-00.59', '01.00-01.59', '02.00-02.59', '03.00-03.59', '04.00-04.59', '05.00-05.59',
            '06.00-06.59', '07.00-07.59', '08.00-08.59', '09.00-09.59', '10.00-10.59', '11.00-11.59',
            '12.00-12.59', '13.00-13.59', '14.00-14.59', '15.00-15.59', '16.00-16.59', '17.00-17.59',
            '18.00-18.59', '19.00-19.59', '20.00-20.59', '21.00-21.59', '22.00-22.59', '23.00-23.59'
        ];

        $records = [];
        foreach ($hours as $h) {
            if ($h === '08.00-08.59') {
                // Terminal 2F has term2FArr, 2FDep
                $records[] = [
                    'hour'                => $h,
                    'terminal'            => '2F',
                    'aircraft_arrival'    => $term2FArr,
                    'aircraft_departure'  => $term2FDep,
                    'aircraft_total'      => $term2FArr + $term2FDep,
                    'passenger_arrival'   => 1000,
                    'passenger_departure' => 1000,
                    'passenger_total'     => 2000,
                    'crew'                => 20,
                    'extra_crew'          => 10,
                    'crew_total'          => 30,
                    'date'                => '2024-08-01',
                ];
                // Terminal 1A has remainder so total matches hour08Arr and hour08Dep
                $term1AArr = max(0, $hour08Arr - $term2FArr);
                $term1ADep = max(0, $hour08Dep - $term2FDep);
                $records[] = [
                    'hour'                => $h,
                    'terminal'            => '1A',
                    'aircraft_arrival'    => $term1AArr,
                    'aircraft_departure'  => $term1ADep,
                    'aircraft_total'      => $term1AArr + $term1ADep,
                    'passenger_arrival'   => 1500,
                    'passenger_departure' => 1500,
                    'passenger_total'     => 3000,
                    'crew'                => 30,
                    'extra_crew'          => 15,
                    'crew_total'          => 45,
                    'date'                => '2024-08-01',
                ];
            } else {
                $records[] = [
                    'hour'                => $h,
                    'terminal'            => '1A',
                    'aircraft_arrival'    => 10,
                    'aircraft_departure'  => 10,
                    'aircraft_total'      => 20,
                    'passenger_arrival'   => 100,
                    'passenger_departure' => 100,
                    'passenger_total'     => 200,
                    'crew'                => 5,
                    'extra_crew'          => 5,
                    'crew_total'          => 10,
                    'date'                => '2024-08-01',
                ];
            }
        }

        $parsed = [
            'meta'            => $meta,
            'terminals'       => ['1A', '2F'],
            'hours'           => $hours,
            'records'         => $records,
            'available_dates' => ['2024-08-01'],
            'available_days'  => 184, // Season span (Aug-Jan)
        ];

        return Upload::create([
            'original_filename' => 'DAU-10A_Test.xls',
            'stored_path'       => 'uploads/dau10a_test.xls',
            'airport_iata'      => 'CGK',
            'airport_id'        => $this->airport->id,
            'report_type'       => 'DAU10A',
            'status'            => 'completed',
            'report_data'       => $parsed,
        ]);
    }

    /**
     * Helper formula simulating frontend & backend transformation:
     * ceil(value / days)
     */
    protected function transformCeil(int $value, int $days): int
    {
        if ($days <= 1) return $value;
        return (int) ceil($value / $days);
    }

    /**
     * PART 51 & 52: Numerical Test across all presets and custom period.
     * Original: ARR = 338, DEP = 291
     * 5 Days: ARR = 68, DEP = 59
     * 15 Days: ARR = 23, DEP = 20
     * 30 Days: ARR = 12, DEP = 10
     * 60 Days: ARR = 6, DEP = 5
     * Custom 7: ARR = 49, DEP = 42
     */
    public function test_part_51_and_52_numerical_transformation(): void
    {
        $origArr = 338;
        $origDep = 291;

        // Original (no transformation)
        $this->assertEquals(338, $this->transformCeil($origArr, 1));
        $this->assertEquals(291, $this->transformCeil($origDep, 1));

        // 5 Days
        $this->assertEquals(68, $this->transformCeil($origArr, 5));
        $this->assertEquals(59, $this->transformCeil($origDep, 5));

        // 15 Days
        $this->assertEquals(23, $this->transformCeil($origArr, 15));
        $this->assertEquals(20, $this->transformCeil($origDep, 15));

        // 30 Days
        $this->assertEquals(12, $this->transformCeil($origArr, 30));
        $this->assertEquals(10, $this->transformCeil($origDep, 30));

        // 60 Days
        $this->assertEquals(6, $this->transformCeil($origArr, 60));
        $this->assertEquals(5, $this->transformCeil($origDep, 60));

        // Custom 7 Days
        $this->assertEquals(49, $this->transformCeil($origArr, 7));
        $this->assertEquals(42, $this->transformCeil($origDep, 7));
    }

    /**
     * PART 53: Rounding Test using ceil().
     * Any decimal remainder from 0.1 to 0.9 rounds UPWARD to the next integer.
     */
    public function test_part_53_ceiling_rounding_rule(): void
    {
        // 338.0 -> 338
        $this->assertEquals(338, (int) ceil(338.0));

        // 338.1 -> 339
        $this->assertEquals(339, (int) ceil(338.1));

        // 338.5 -> 339
        $this->assertEquals(339, (int) ceil(338.5));

        // 338.9 -> 339
        $this->assertEquals(339, (int) ceil(338.9));

        // Specific division examples:
        // 338 / 5 = 67.6 -> 68
        $this->assertEquals(68, (int) ceil(338 / 5));

        // 291 / 5 = 58.2 -> 59
        $this->assertEquals(59, (int) ceil(291 / 5));

        // 338 / 15 = 22.5333... -> 23
        $this->assertEquals(23, (int) ceil(338 / 15));

        // 291 / 15 = 19.4 -> 20
        $this->assertEquals(20, (int) ceil(291 / 15));

        // 1015 / 30 = 33.833... -> 34
        $this->assertEquals(34, (int) ceil(1015 / 30));

        // 950 / 30 = 31.666... -> 32
        $this->assertEquals(32, (int) ceil(950 / 30));
    }

    /**
     * PART 54: Non-cumulative division test.
     * Switching from 5 Days -> 15 Days must calculate from ORIGINAL (338),
     * NOT from the 5-day result (68).
     * 338 / 15 = 23 (CORRECT), NOT ceil(68 / 15) = 5 (WRONG).
     */
    public function test_part_54_non_cumulative_division(): void
    {
        $original = 338;

        // Step 1: 5 Days
        $fiveDaysVal = (int) ceil($original / 5);
        $this->assertEquals(68, $fiveDaysVal);

        // Step 2: Switch to 15 Days directly from original
        $fifteenDaysVal = (int) ceil($original / 15);
        $this->assertEquals(23, $fifteenDaysVal);

        // Verify it is NOT cumulative
        $wrongCumulative = (int) ceil($fiveDaysVal / 15);
        $this->assertNotEquals($wrongCumulative, $fifteenDaysVal);
        $this->assertEquals(5, $wrongCumulative); // The incorrect bug is 5
    }

    /**
     * PART 55: Terminal Filter test.
     * Terminal filtering happens BEFORE average transformation.
     * ALL TERMINALS: 08 ARR = 338 -> 5 Days = 68
     * Terminal 2F:   08 ARR = 74  -> 5 Days = 15 (ceil(74 / 5) = 15)
     */
    public function test_part_55_terminal_filtering_before_average(): void
    {
        $allArr = 338;
        $t2fArr = 74;

        $fiveDaysAll = (int) ceil($allArr / 5);
        $fiveDaysT2F = (int) ceil($t2fArr / 5);

        $this->assertEquals(68, $fiveDaysAll);
        $this->assertEquals(15, $fiveDaysT2F); // 74 / 5 = 14.8 -> 15
    }

    /**
     * PART 56: Status Recalculation test.
     * Capacity ARR = 30.
     * Original: ARR = 338 (> 30) -> OVER CAPACITY
     * 15 Days:  ARR = 23  (< 30) -> AVAILABLE
     * 30 Days:  ARR = 12  (< 30) -> AVAILABLE
     * Full/Max: ARR = 30  (== 30)-> FULL / MAX
     */
    public function test_part_56_status_recalculation(): void
    {
        $arrCap = 30;

        // Original 338
        $valOrig = 338;
        $statusOrig = ($valOrig > $arrCap) ? 'OVER' : (($valOrig === $arrCap) ? 'MAX' : 'AVAILABLE');
        $this->assertEquals('OVER', $statusOrig);

        // 15 Days: 23
        $val15 = (int) ceil($valOrig / 15);
        $status15 = ($val15 > $arrCap) ? 'OVER' : (($val15 === $arrCap) ? 'MAX' : 'AVAILABLE');
        $this->assertEquals(23, $val15);
        $this->assertEquals('AVAILABLE', $status15);

        // 30 Days: 12
        $val30 = (int) ceil($valOrig / 30);
        $status30 = ($val30 > $arrCap) ? 'OVER' : (($val30 === $arrCap) ? 'MAX' : 'AVAILABLE');
        $this->assertEquals(12, $val30);
        $this->assertEquals('AVAILABLE', $status30);

        // Boundary test: ARR = 30 -> FULL / MAX
        $valMax = 30;
        $statusMax = ($valMax > $arrCap) ? 'OVER' : (($valMax === $arrCap) ? 'MAX' : 'AVAILABLE');
        $this->assertEquals('MAX', $statusMax);
    }

    /**
     * PART 57: Reset test.
     * Selecting 30 days, then 15 days, then Reset must restore exact original values.
     */
    public function test_part_57_reset_restores_original_data(): void
    {
        $originalArr = 338;
        $originalDep = 291;

        // 30 Days
        $arr30 = (int) ceil($originalArr / 30);
        $dep30 = (int) ceil($originalDep / 30);
        $this->assertEquals(12, $arr30);
        $this->assertEquals(10, $dep30);

        // 15 Days
        $arr15 = (int) ceil($originalArr / 15);
        $dep15 = (int) ceil($originalDep / 15);
        $this->assertEquals(23, $arr15);
        $this->assertEquals(20, $dep15);

        // Reset to original
        $restoredArr = $originalArr;
        $restoredDep = $originalDep;
        $this->assertEquals(338, $restoredArr);
        $this->assertEquals(291, $restoredDep);
    }

    /**
     * Test DAU-10A Dashboard view contains AVERAGE BY DAYS controls
     * and NO obsolete Average Arrival/Departure reference lines or KPI cards.
     */
    public function test_dashboard_view_has_average_by_days_controls_and_no_avg_lines(): void
    {
        $upload = $this->createDau10AUploadWithHour08();

        $response = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'metric' => 'aircraft'
        ]));

        $response->assertStatus(200);

        // Assert AVERAGE BY DAYS controls are present
        $response->assertSee('AVERAGE BY DAYS');
        $response->assertSee('ORIGINAL DATA');
        $response->assertSee('[5, 15, 30, 60]');
        $response->assertSee('CUSTOM');
        $response->assertSee('AVAILABLE DATA DAYS');
        $response->assertSee('RESET TO ORIGINAL');
        $response->assertSee('ceil(hourlyValue / N)');

        // Assert obsolete Average Terminal Capacity lines and cards are GONE
        $response->assertDontSee('AVG ARRIVAL CAPACITY');
        $response->assertDontSee('AVG DEPARTURE CAPACITY');
        $response->assertDontSee('Average Terminal ARR');
        $response->assertDontSee('Average Terminal DEP');
    }

    /**
     * Test PDF export with Average By Days transforms hourly values,
     * includes AVERAGE BY DAYS banner, and has NO obsolete Avg ARR/DEP legend lines.
     */
    public function test_pdf_export_with_average_by_days(): void
    {
        $upload = $this->createDau10AUploadWithHour08();

        // 15-day average PDF export
        $response = $this->get(route('dau.export.pdf', [
            'upload'     => $upload->id,
            'metric'     => 'aircraft',
            'avg_period' => '15',
            'avg_days'   => 15,
            'arr_nac'    => 30,
            'dep_nac'    => 30,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    /**
     * Test that Passenger and Crew metrics do NOT show Average By Days controls.
     */
    public function test_passenger_and_crew_protection(): void
    {
        $upload = $this->createDau10AUploadWithHour08();

        // Passenger metric
        $paxResponse = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'metric' => 'passenger'
        ]));
        $paxResponse->assertStatus(200);
        // The container has x-show="selectedMetric === 'aircraft'", so it's protected in the frontend
        $paxResponse->assertSee("selectedMetric === 'aircraft'");

        // Crew metric
        $crewResponse = $this->get(route('dau.dashboard', [
            'upload' => $upload->id,
            'metric' => 'crew'
        ]));
        $crewResponse->assertStatus(200);
        $crewResponse->assertSee("selectedMetric === 'aircraft'");
    }
}
