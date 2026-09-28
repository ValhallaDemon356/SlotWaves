<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;

class FdrTimestampNormalizationTest extends TestCase
{
    protected string $filePath;
    protected FlightDailyReportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->filePath = storage_path('app/templates/CGK FDR.xls');
    }

    public function test_direction_aware_timestamp_mapping_and_no_hour_12_fallback()
    {
        $result = $this->parser->parse($this->filePath);
        $records = $result['records'];

        $distinctScheduledHours = [];
        $distinctActualHours = [];

        foreach ($records as $r) {
            $isArr = ($r['direction'] === 'ARRIVAL');
            $isDep = ($r['direction'] === 'DEPARTURE');

            if ($isArr) {
                $this->assertNotNull($r['scheduled_arrival_datetime']);
                $this->assertNull($r['scheduled_departure_datetime']);
                $this->assertEquals($r['sibt'], $r['sched_display']);
                $this->assertEquals('N/A', $r['sobt']);
                if ($r['is_realized']) {
                    $this->assertNotNull($r['actual_arrival_datetime']);
                    $this->assertNull($r['actual_departure_datetime']);
                    $this->assertEquals($r['aibt'], $r['actual_display']);
                    $this->assertEquals('N/A', $r['aobt']);
                }
            } elseif ($isDep) {
                $this->assertNotNull($r['scheduled_departure_datetime']);
                $this->assertNull($r['scheduled_arrival_datetime']);
                $this->assertEquals($r['sobt'], $r['sched_display']);
                $this->assertEquals('N/A', $r['sibt']);
                if ($r['is_realized']) {
                    $this->assertNotNull($r['actual_departure_datetime']);
                    $this->assertNull($r['actual_arrival_datetime']);
                    $this->assertEquals($r['aobt'], $r['actual_display']);
                    $this->assertEquals('N/A', $r['aibt']);
                }
            }

            if ($r['scheduled_hour'] !== null) {
                $distinctScheduledHours[$r['scheduled_hour']] = true;
            }
            if ($r['actual_hour'] !== null) {
                $distinctActualHours[$r['actual_hour']] = true;
            }

            // Verify operational date derived from scheduled date
            $this->assertEquals('2026-07-01', $r['operational_date']);
        }

        // Must NOT fallback all records to hour 12! There should be operations across many hours.
        $this->assertGreaterThan(15, count($distinctScheduledHours), "Scheduled movements should span multiple hours across the day, not collapse to hour 12");
        $this->assertGreaterThan(15, count($distinctActualHours), "Actual movements should span multiple hours across the day, not collapse to hour 12");
    }

    public function test_missing_actual_timestamps_are_null_not_manufactured()
    {
        $result = $this->parser->parse($this->filePath);
        $records = $result['records'];

        $unrealized = array_filter($records, fn($r) => !$r['is_realized']);
        $this->assertCount(25, $unrealized);

        foreach ($unrealized as $r) {
            $this->assertNull($r['actual_datetime'], "Unrealized flight must have actual_datetime = null");
            $this->assertNull($r['actual_arrival_datetime']);
            $this->assertNull($r['actual_departure_datetime']);
            $this->assertNull($r['actual_hour']);
            $this->assertEquals('N/A', $r['actual_display']);
        }
    }

    public function test_robust_timestamp_parser_for_various_formats()
    {
        $p1 = $this->parser->parseDateTimeString('01-07-2026 00:10');
        $this->assertEquals('2026-07-01', $p1['date']);
        $this->assertEquals(0, $p1['hour']);
        $this->assertEquals('2026-07-01 00:10:00', $p1['datetime']);

        $p2 = $this->parser->parseDateTimeString('30-06-2026 23:45');
        $this->assertEquals('2026-06-30', $p2['date']);
        $this->assertEquals(23, $p2['hour']);
        $this->assertEquals('2026-06-30 23:45:00', $p2['datetime']);

        // Empty or N/A should return null, not hour 12
        $p3 = $this->parser->parseDateTimeString('N/A');
        $this->assertNull($p3['date']);
        $this->assertNull($p3['hour']);
        $this->assertNull($p3['datetime']);

        $p4 = $this->parser->parseDateTimeString('-');
        $this->assertNull($p4['hour']);
    }
}
