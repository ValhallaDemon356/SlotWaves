<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use App\Services\FlightDailyReport\FlightDailyReportAnalytics;

class FdrPassengerAggregationTest extends TestCase
{
    protected string $filePath;
    protected FlightDailyReportParser $parser;
    protected FlightDailyReportAnalytics $analytics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->analytics = new FlightDailyReportAnalytics();
        $this->filePath = storage_path('app/templates/CGK FDR.xls');
    }

    public function test_passenger_movement_strictly_equals_adult_plus_child_plus_infant()
    {
        $result = $this->parser->parse($this->filePath);
        $records = $result['records'];

        $kpis = $this->analytics->computeTopKpis($records);

        // Required assertions from Part 22 & Part 27:
        $this->assertEquals(24774, $kpis['adult_passengers'], "Adult count must equal 24,774");
        $this->assertEquals(757, $kpis['child_passengers'], "Child count must equal 757");
        $this->assertEquals(96, $kpis['infant_passengers'], "Infant count must equal 96");

        // Passenger Movement = 24,774 + 757 + 96 = 25,627
        $this->assertEquals(25627, $kpis['total_passengers'], "Passenger Movement must strictly equal 25,627");
        $this->assertEquals(25627, $kpis['passenger_movement']);
        $this->assertNotEquals(37080, $kpis['total_passengers'], "Must not display 37,080 passengers");

        // Transit and Transfer kept separate
        $this->assertEquals(825, $kpis['transit_passengers']);
        $this->assertEquals(0, $kpis['transfer_passengers']);

        // Capacity and weight checks
        $this->assertEquals(35763, $kpis['total_capacity']);
        $this->assertEquals(439798.0, $kpis['cargo_kg']);
        $this->assertEquals(285219.0, $kpis['baggage_kg']);
    }
}
