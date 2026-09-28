<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;

class FdrDetailedScheduleActualTest extends TestCase
{
    protected string $filePath;
    protected FlightDailyReportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->filePath = storage_path('app/templates/CGK FDR.xls');
    }

    public function test_detailed_schedule_and_actual_values_are_correctly_populated()
    {
        $result = $this->parser->parse($this->filePath);
        $records = $result['records'];

        // Find GA 894 from Part 19
        $ga894 = null;
        foreach ($records as $r) {
            if ($r['flight_no'] === 'GA 894') {
                $ga894 = $r;
                break;
            }
        }

        $this->assertNotNull($ga894, "Flight GA 894 must exist in dataset");
        $this->assertEquals('D SCHED', $ga894['leg']);
        $this->assertEquals('DEPARTURE', $ga894['direction']);
        $this->assertEquals('CGK → SOQ', $ga894['route']);
        $this->assertEquals('01-07-2026 00:10', $ga894['sobt']);
        $this->assertEquals('01-07-2026 00:10', $ga894['sched_display']);
        $this->assertEquals('01-07-2026 00:09', $ga894['aobt']);
        $this->assertEquals('01-07-2026 00:09', $ga894['actual_display']);
        $this->assertEquals('N/A', $ga894['sibt']);
        $this->assertEquals('N/A', $ga894['aibt']);
    }

    public function test_arrival_record_maps_sibt_and_aibt_properly()
    {
        $result = $this->parser->parse($this->filePath);
        $records = $result['records'];

        // Find an arrival record
        $arrival = null;
        foreach ($records as $r) {
            if ($r['direction'] === 'ARRIVAL' && $r['is_realized']) {
                $arrival = $r;
                break;
            }
        }

        $this->assertNotNull($arrival, "An arrival record must exist");
        $this->assertEquals('A SCHED', $arrival['leg']);
        $this->assertNotEquals('N/A', $arrival['sibt']);
        $this->assertNotEquals('N/A', $arrival['aibt']);
        $this->assertEquals($arrival['sibt'], $arrival['sched_display']);
        $this->assertEquals($arrival['aibt'], $arrival['actual_display']);
        $this->assertEquals('N/A', $arrival['sobt']);
        $this->assertEquals('N/A', $arrival['aobt']);
    }
}
