<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportFilter;
use App\Services\FlightDailyReport\FlightDailyReportParser;

class FilterTest extends TestCase
{
    protected FlightDailyReportFilter $filter;
    protected array $records;
    protected array $meta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new FlightDailyReportFilter();
        $parser = new FlightDailyReportParser();
        $path = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        $parsed = $parser->parse($path);
        $this->records = $parsed['records'];
        $this->meta = $parsed['meta'];
    }

    public function test_filter_returns_all_records_by_default(): void
    {
        $res = $this->filter->apply($this->records, []);

        $this->assertEquals(count($this->records), $res['total_count']);
        $this->assertEquals(count($this->records), $res['filtered_count']);
        $this->assertEquals("Showing " . count($this->records) . " of " . count($this->records) . " records", $res['counter_text']);
        $this->assertEmpty($res['active_chips']);
    }

    public function test_filter_by_leg_arrival_and_departure(): void
    {
        // Filter Arrivals
        $resArr = $this->filter->apply($this->records, ['leg' => 'ARRIVAL']);
        $this->assertGreaterThan(0, $resArr['filtered_count']);
        $this->assertLessThan(count($this->records), $resArr['filtered_count']);
        foreach ($resArr['records'] as $r) {
            $this->assertEquals('ARRIVAL', $r['direction']);
        }

        // Filter Departures
        $resDep = $this->filter->apply($this->records, ['leg' => 'DEPARTURE']);
        $this->assertGreaterThan(0, $resDep['filtered_count']);
        foreach ($resDep['records'] as $r) {
            $this->assertEquals('DEPARTURE', $r['direction']);
        }

        // Arrival + Departure should equal total
        $this->assertEquals(count($this->records), $resArr['filtered_count'] + $resDep['filtered_count']);
    }

    public function test_filter_by_airline_operator(): void
    {
        $res = $this->filter->apply($this->records, ['operator' => 'Garuda Indonesia']);
        $this->assertGreaterThan(0, $res['filtered_count']);
        foreach ($res['records'] as $r) {
            $this->assertEquals('Garuda Indonesia', $r['air_line']);
        }
    }

    public function test_filter_by_traffic_domestic_and_international(): void
    {
        $resDom = $this->filter->apply($this->records, ['traffic' => 'DOMESTIC']);
        $this->assertGreaterThan(0, $resDom['filtered_count']);
        foreach ($resDom['records'] as $r) {
            $this->assertEquals('DOMESTIC', $r['traffic']);
        }

        $resInt = $this->filter->apply($this->records, ['traffic' => 'INTERNATIONAL']);
        $this->assertGreaterThan(0, $resInt['filtered_count']);
        foreach ($resInt['records'] as $r) {
            $this->assertEquals('INTERNATIONAL', $r['traffic']);
        }
    }

    public function test_filter_by_flight_no_and_suffix(): void
    {
        $targetFlt = $this->records[0]['flight_no_base'];
        $res = $this->filter->apply($this->records, ['flight_no' => $targetFlt]);

        $this->assertGreaterThan(0, $res['filtered_count']);
        foreach ($res['records'] as $r) {
            $this->assertStringContainsString($targetFlt, $r['flight_no']);
        }

        // Filter suffix 'A'
        $resSuffix = $this->filter->apply($this->records, ['suffix' => 'A']);
        foreach ($resSuffix['records'] as $r) {
            $this->assertEquals('A', $r['flight_suffix']);
        }
    }

    public function test_filter_by_date_range(): void
    {
        $res = $this->filter->apply($this->records, [
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-10',
        ]);

        $this->assertGreaterThan(0, $res['filtered_count']);
        $this->assertLessThan(count($this->records), $res['filtered_count']);

        foreach ($res['records'] as $r) {
            $this->assertGreaterThanOrEqual('2026-08-01', $r['flight_date']);
            $this->assertLessThanOrEqual('2026-08-10', $r['flight_date']);
        }
    }

    public function test_filter_by_search_query(): void
    {
        $sample = $this->records[0];
        $searchKey = $sample['reg_no']; // e.g. 'PK-xxx'

        $res = $this->filter->apply($this->records, ['search' => $searchKey]);
        $this->assertGreaterThan(0, $res['filtered_count']);

        foreach ($res['records'] as $r) {
            $haystack = strtolower($r['flight_no'] . ' ' . $r['air_line'] . ' ' . $r['reg_no'] . ' ' . $r['route'] . ' ' . $r['stand'] . ' ' . $r['runway']);
            $this->assertStringContainsString(strtolower($searchKey), $haystack);
        }
    }

    public function test_active_filter_chips_generation(): void
    {
        $res = $this->filter->apply($this->records, [
            'airport'  => 'CGK',
            'leg'      => 'ARRIVAL',
            'operator' => 'Citilink',
        ], $this->meta);

        $chips = $res['active_chips'];
        $this->assertCount(3, $chips);

        $keys = array_column($chips, 'key');
        $this->assertContains('airport', $keys);
        $this->assertContains('leg', $keys);
        $this->assertContains('operator', $keys);
    }

    public function test_race_condition_version_token_preserved(): void
    {
        $res = $this->filter->apply($this->records, ['v' => 1234567]);
        $this->assertEquals(1234567, $res['version']);
    }
}
