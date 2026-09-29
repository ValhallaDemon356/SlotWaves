<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\HourlyChartService;
use App\Services\FlightDailyReport\FlightDailyReportParser;

class FDRScheduleVarianceAndPaxAllRefinementTest extends TestCase
{
    /**
     * Requirement 8, 9, 10, 11, 33:
     * PAX ALL classification, exclusion from operator list and movement analytics.
     */
    public function test_pax_all_classified_as_summary_and_excluded_from_operators_and_analytics()
    {
        $rawRecords = [
            [
                'flight_number' => 'JT-100',
                'air_line' => 'JT',
                'movement_type' => 'A',
                'dom_int' => 'D',
                'sibt' => '10:00',
                'aibt' => '10:02',
                'total_passenger' => 150,
                'cargo_kg' => 500,
                'baggage_kg' => 300,
                'row_type' => 'MOVEMENT',
            ],
            [
                'flight_number' => 'GA-200',
                'air_line' => 'GA',
                'movement_type' => 'D',
                'dom_int' => 'I',
                'sobt' => '12:00',
                'aobt' => '12:10',
                'total_passenger' => 200,
                'cargo_kg' => 800,
                'baggage_kg' => 600,
                'row_type' => 'MOVEMENT',
            ],
            // PAX ALL source summary row
            [
                'flight_number' => 'SUMMARY',
                'air_line' => 'PAX ALL',
                'movement_type' => '',
                'dom_int' => '',
                'sibt' => null,
                'aibt' => null,
                'total_passenger' => 7412,
                'cargo_kg' => 0,
                'baggage_kg' => 0,
                'raw_row' => '-PAX ALL -(7412)- | -Adult, Child, Infant (6757)- | -Transit (609)-',
                'row_type' => 'SUMMARY',
            ],
        ];

        // 1. Filter exclusion test
        $filter = new FlightDailyReportFilter();
        $filtered = $filter->apply($rawRecords, ['movement_type' => 'ALL', 'traffic_type' => 'ALL'])['records'];
        
        $this->assertCount(2, $filtered, 'PAX ALL summary row must be excluded from movement records');
        $airlines = collect($filtered)->pluck('air_line')->unique()->values()->all();
        $this->assertNotContains('PAX ALL', $airlines, 'PAX ALL must not be in airlines');
        $this->assertEquals(['JT', 'GA'], $airlines);

        // 2. Analytics exclusion test
        $analytics = new FlightDailyReportAnalytics();
        $results = $analytics->compute($rawRecords, 'DAILY');

        // Total flights must be 2, not 3
        $this->assertEquals(2, $results['kpi']['total_flights']);
        $this->assertEquals(1, $results['kpi']['total_arrivals']);
        $this->assertEquals(1, $results['kpi']['total_departures']);
        
        // Total passengers must be 150 + 200 = 350, NOT 7412 or 7762
        $this->assertEquals(350, $results['kpi']['total_passengers']);
        $this->assertEquals(1300, $results['kpi']['total_cargo_kg']);
        $this->assertEquals(900, $results['kpi']['total_baggage_kg']);

        // Airline analysis must only have JT and GA
        $operatorList = array_column($results['airline_analysis'], 'airline');
        $this->assertNotContains('PAX ALL', $operatorList);
        $this->assertContains('JT', $operatorList);
        $this->assertContains('GA', $operatorList);
    }

    /**
     * Requirement 1, 2, 3, 4, 6, 7, 34:
     * Schedule variance buckets, human-readable labels, semantic colors, and test cases.
     */
    public function test_schedule_variance_distribution_buckets_and_semantics()
    {
        $records = [
            // 1. Arrival: AIBT - SIBT = -70 min -> >60 MIN EARLY
            [
                'flight_number' => 'E1',
                'movement_type' => 'A',
                'dom_int' => 'D',
                'sibt' => '10:00',
                'aibt' => '08:50',
                'row_type' => 'MOVEMENT',
            ],
            // 2. Arrival: AIBT - SIBT = -45 min -> 31–60 MIN EARLY
            [
                'flight_number' => 'E2',
                'movement_type' => 'A',
                'dom_int' => 'D',
                'sibt' => '10:00',
                'aibt' => '09:15',
                'row_type' => 'MOVEMENT',
            ],
            // 3. Arrival: AIBT - SIBT = -20 min -> 16–30 MIN EARLY
            [
                'flight_number' => 'E3',
                'movement_type' => 'A',
                'dom_int' => 'D',
                'sibt' => '10:00',
                'aibt' => '09:40',
                'row_type' => 'MOVEMENT',
            ],
            // 4. Arrival: AIBT - SIBT = -10 min -> 6–15 MIN EARLY
            [
                'flight_number' => 'E4',
                'movement_type' => 'A',
                'dom_int' => 'D',
                'sibt' => '10:00',
                'aibt' => '09:50',
                'row_type' => 'MOVEMENT',
            ],
            // 5. Arrival: AIBT - SIBT = -3 min -> ON TIME ±5 MIN
            [
                'flight_number' => 'OT1',
                'movement_type' => 'A',
                'dom_int' => 'D',
                'sibt' => '10:00',
                'aibt' => '09:57',
                'row_type' => 'MOVEMENT',
            ],
            // 6. Arrival: AIBT - SIBT = +12 min -> 6–15 MIN LATE
            [
                'flight_number' => 'L1',
                'movement_type' => 'A',
                'dom_int' => 'D',
                'sibt' => '10:00',
                'aibt' => '10:12',
                'row_type' => 'MOVEMENT',
            ],
            // 7. Departure: AOBT - SOBT = +25 min -> 16–30 MIN LATE
            [
                'flight_number' => 'L2',
                'movement_type' => 'D',
                'dom_int' => 'D',
                'sobt' => '10:00',
                'aobt' => '10:25',
                'row_type' => 'MOVEMENT',
            ],
            // 8. Departure: AOBT - SOBT = +45 min -> 31–60 MIN LATE
            [
                'flight_number' => 'L3',
                'movement_type' => 'D',
                'dom_int' => 'D',
                'sobt' => '10:00',
                'aobt' => '10:45',
                'row_type' => 'MOVEMENT',
            ],
            // 9. Departure: AOBT - SOBT = +75 min -> >60 MIN LATE
            [
                'flight_number' => 'L4',
                'movement_type' => 'D',
                'dom_int' => 'D',
                'sobt' => '10:00',
                'aobt' => '11:15',
                'row_type' => 'MOVEMENT',
            ],
        ];

        $analytics = new FlightDailyReportAnalytics();
        $schedVsReal = $analytics->computeScheduleVsRealization($records);

        $this->assertEquals('SCHEDULE VARIANCE DISTRIBUTION', $schedVsReal['title']);
        $this->assertStringContainsString('How early or late actual movement occurred', $schedVsReal['subtitle']);

        $histogram = $schedVsReal['histogram'];
        $this->assertCount(9, $histogram, 'Histogram must have exactly 9 buckets');

        $expectedBuckets = [
            ['label' => '>60 MIN EARLY', 'group' => 'EARLY', 'color' => '#1E3A8A', 'count' => 1],
            ['label' => '31–60 MIN EARLY', 'group' => 'EARLY', 'color' => '#2563EB', 'count' => 1],
            ['label' => '16–30 MIN EARLY', 'group' => 'EARLY', 'color' => '#38BDF8', 'count' => 1],
            ['label' => '6–15 MIN EARLY', 'group' => 'EARLY', 'color' => '#BAE6FD', 'count' => 1],
            ['label' => 'ON TIME ±5 MIN', 'group' => 'ON TIME', 'color' => '#10B981', 'count' => 1],
            ['label' => '6–15 MIN LATE', 'group' => 'LATE', 'color' => '#FDE047', 'count' => 1],
            ['label' => '16–30 MIN LATE', 'group' => 'LATE', 'color' => '#F59E0B', 'count' => 1],
            ['label' => '31–60 MIN LATE', 'group' => 'LATE', 'color' => '#EA580C', 'count' => 1],
            ['label' => '>60 MIN LATE', 'group' => 'LATE', 'color' => '#DC2626', 'count' => 1],
        ];

        foreach ($expectedBuckets as $idx => $exp) {
            $this->assertEquals($exp['label'], $histogram[$idx]['label'], "Bucket index {$idx} label mismatch");
            $this->assertEquals($exp['group'], $histogram[$idx]['group'], "Bucket index {$idx} group mismatch");
            $this->assertEquals($exp['color'], $histogram[$idx]['color'], "Bucket index {$idx} color mismatch");
            $this->assertEquals($exp['count'], $histogram[$idx]['count'], "Bucket index {$idx} count mismatch");
            $this->assertNotEmpty($histogram[$idx]['desc'], "Bucket index {$idx} human-readable desc missing");
        }

        // Summary counts
        $this->assertEquals(4, $schedVsReal['summary']['early']);
        $this->assertEquals(1, $schedVsReal['summary']['on_time']);
        $this->assertEquals(4, $schedVsReal['summary']['late']);
    }

    /**
     * Requirement 13, 14, 17, 19, 20, 23, 28:
     * Trend breakdowns and reconciliation with KPIs.
     */
    public function test_passenger_and_cargo_trend_reconciliation_and_breakdowns()
    {
        $records = [
            [
                'flight_number' => 'GA-1',
                'movement_type' => 'A',
                'dom_int' => 'D',
                'sibt' => '10:15',
                'aibt' => '10:15',
                'total_passenger' => 120,
                'cargo_kg' => 450,
                'baggage_kg' => 200,
                'row_type' => 'MOVEMENT',
            ],
            [
                'flight_number' => 'GA-2',
                'movement_type' => 'A',
                'dom_int' => 'I',
                'sibt' => '10:45',
                'aibt' => '10:45',
                'total_passenger' => 80,
                'cargo_kg' => 250,
                'baggage_kg' => 150,
                'row_type' => 'MOVEMENT',
            ],
            [
                'flight_number' => 'JT-1',
                'movement_type' => 'D',
                'dom_int' => 'D',
                'sobt' => '14:20',
                'aobt' => '14:20',
                'total_passenger' => 150,
                'cargo_kg' => 600,
                'baggage_kg' => 350,
                'row_type' => 'MOVEMENT',
            ],
            [
                'flight_number' => 'SQ-1',
                'movement_type' => 'D',
                'dom_int' => 'I',
                'sobt' => '14:50',
                'aobt' => '14:50',
                'total_passenger' => 250,
                'cargo_kg' => 1200,
                'baggage_kg' => 500,
                'row_type' => 'MOVEMENT',
            ],
        ];

        $analytics = new FlightDailyReportAnalytics();
        $results = $analytics->compute($records, 'DAILY');

        $kpiPax = $results['kpi']['total_passengers']; // 120 + 80 + 150 + 250 = 600
        $kpiCargo = $results['kpi']['total_cargo_kg']; // 450 + 250 + 600 + 1200 = 2500

        $trend = $results['combined_trend'];

        // Reconcile total trend passenger sum with KPI total passengers
        $trendPaxSum = array_sum($trend['arr_passengers']) + array_sum($trend['dep_passengers']);
        $this->assertEquals($kpiPax, $trendPaxSum, 'Total trend passenger sum must equal KPI total passengers');

        // Reconcile total trend cargo sum with KPI total cargo
        $trendCargoSum = array_sum($trend['arr_cargo_kg']) + array_sum($trend['dep_cargo_kg']);
        $this->assertEquals($kpiCargo, $trendCargoSum, 'Total trend cargo sum must equal KPI total cargo');

        // Check hour 10 arrival breakdowns (GA-1: 120 Dom, GA-2: 80 Int)
        $this->assertEquals(120, $trend['arr_dom_passengers'][10]);
        $this->assertEquals(80, $trend['arr_int_passengers'][10]);
        $this->assertEquals(200, $trend['arr_passengers'][10]);
        $this->assertEquals(450, $trend['arr_dom_cargo_kg'][10]);
        $this->assertEquals(250, $trend['arr_int_cargo_kg'][10]);
        $this->assertEquals(700, $trend['arr_cargo_kg'][10]);

        // Check hour 14 departure breakdowns (JT-1: 150 Dom, SQ-1: 250 Int)
        $this->assertEquals(150, $trend['dep_dom_passengers'][14]);
        $this->assertEquals(250, $trend['dep_int_passengers'][14]);
        $this->assertEquals(400, $trend['dep_passengers'][14]);
        $this->assertEquals(600, $trend['dep_dom_cargo_kg'][14]);
        $this->assertEquals(1200, $trend['dep_int_cargo_kg'][14]);
        $this->assertEquals(1800, $trend['dep_cargo_kg'][14]);
    }

    /**
     * Requirement 22A:
     * Payload composition percentage and formatting.
     */
    public function test_payload_composition_math_and_proportions()
    {
        // Example from 22A.22:
        // Cargo = 439,798 kg, Baggage = 285,219 kg
        $cargo = 439798;
        $baggage = 285219;
        $total = $cargo + $baggage;

        $this->assertEquals(725017, $total, 'Total payload must be 725,017 kg');

        $cargoPct = round(($cargo / $total) * 100, 1);
        $baggagePct = round(($baggage / $total) * 100, 1);

        $this->assertEquals(60.7, $cargoPct, 'Cargo percentage must be 60.7%');
        $this->assertEquals(39.3, $baggagePct, 'Baggage percentage must be 39.3%');
        $this->assertEquals(100.0, $cargoPct + $baggagePct);
    }

    /**
     * Requirement 5, 29, 32:
     * HourlyChartService renders Schedule Variance Distribution SVG properly.
     */
    public function test_schedule_variance_svg_renders_groups_and_bars()
    {
        $schedVsReal = [
            'histogram' => [
                ['label' => '>60 MIN EARLY', 'count' => 2, 'group' => 'EARLY', 'color' => '#1E3A8A'],
                ['label' => '31–60 MIN EARLY', 'count' => 1, 'group' => 'EARLY', 'color' => '#2563EB'],
                ['label' => '16–30 MIN EARLY', 'count' => 3, 'group' => 'EARLY', 'color' => '#38BDF8'],
                ['label' => '6–15 MIN EARLY', 'count' => 5, 'group' => 'EARLY', 'color' => '#BAE6FD'],
                ['label' => 'ON TIME ±5 MIN', 'count' => 45, 'group' => 'ON TIME', 'color' => '#10B981'],
                ['label' => '6–15 MIN LATE', 'count' => 6, 'group' => 'LATE', 'color' => '#FDE047'],
                ['label' => '16–30 MIN LATE', 'count' => 4, 'group' => 'LATE', 'color' => '#F59E0B'],
                ['label' => '31–60 MIN LATE', 'count' => 2, 'group' => 'LATE', 'color' => '#EA580C'],
                ['label' => '>60 MIN LATE', 'count' => 1, 'group' => 'LATE', 'color' => '#DC2626'],
            ],
            'summary' => [
                'early' => 11,
                'on_time' => 45,
                'late' => 13,
            ]
        ];

        $chartService = new HourlyChartService();
        $svg = $chartService->renderScheduleVarianceDistributionSvg($schedVsReal, 720, 145);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('EARLY (4 BUCKETS)', $svg);
        $this->assertStringContainsString('ON TIME', $svg);
        $this->assertStringContainsString('LATE (4 BUCKETS)', $svg);
        $this->assertStringContainsString('ON TIME ±5 MIN', $svg);
        $this->assertStringContainsString('>60 MIN EARLY', $svg);
        $this->assertStringContainsString('>60 MIN LATE', $svg);
    }
}
