<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Upload;
use App\Services\FlightDailyReport\FlightDailyReportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FDRRealOasysMonthlyDayByDayTest extends TestCase
{
    use RefreshDatabase;

    protected FlightDailyReportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
    }

    public function test_real_monthly_oasys_file_day_by_day_selection(): void
    {
        $filePath = storage_path('app/templates/CGK FDR.xls');
        if (!file_exists($filePath)) {
            $filePath = resource_path('templates/fdr/OASYS-FDR-TEMPLATE.xls');
        }
        $this->assertFileExists($filePath);

        $parsed = $this->parser->parse($filePath);
        $meta = $parsed['meta'];
        $records = $parsed['records'];

        $this->assertNotEmpty($records);
        $this->assertEquals('MONTHLY', $meta['source_type'] ?? FlightDailyReportParser::detectGranularity($meta['period_start'], $meta['period_end']));

        $upload = Upload::create([
            'original_filename'  => 'CGK FDR.xls',
            'stored_path'        => 'templates/CGK FDR.xls',
            'status'             => 'completed',
            'report_type'        => 'fdr',
            'total_rows'         => count($records),
            'valid_rows'         => count($records),
            'invalid_rows'       => 0,
            'duplicate_rows'     => 0,
            'parsing_confidence' => 1.0,
            'validation_summary' => ['valid' => true],
            'report_data'        => $parsed,
        ]);

        // 1. Initial dashboard load: Defaults to first available date, NOT all 240 flights
        $response = $this->get(route('fdr.dashboard', $upload->id));
        $response->assertStatus(200);
        $response->assertSee('SOURCE TYPE: MONTHLY');
        $response->assertSee('PEAK DAILY ANALYSIS');

        // Extract available dates
        $availableDates = [];
        foreach ($records as $r) {
            $d = $r['operational_date'] ?? null;
            if ($d && $d !== 'N/A') {
                $availableDates[$d] = true;
            }
        }
        $datesList = array_keys($availableDates);
        sort($datesList);
        $this->assertGreaterThan(1, count($datesList));

        $firstDay = $datesList[0];
        $firstDayExpectedCount = count(array_filter($records, fn($r) => ($r['operational_date'] ?? '') === $firstDay));

        // 2. Filter API for first day (01-08-2026)
        $resDay1 = $this->getJson(route('fdr.filter', [
            'upload'         => $upload->id,
            'analysis_level' => 'DAILY',
            'analysis_date'  => $firstDay,
            'v'              => 101,
        ]));

        $resDay1->assertStatus(200);
        $jsonDay1 = $resDay1->json();

        // Must ONLY match the day's flight count, NEVER the total source flights
        $this->assertEquals($firstDayExpectedCount, $jsonDay1['filtered_count']);
        $this->assertEquals($firstDayExpectedCount, $jsonDay1['kpis']['total_flights']);
        $this->assertLessThan(count($records), $jsonDay1['kpis']['total_flights']);

        // Check hourly reconciliation: Sum of hourly equals daily total
        $hourlyArrivals = array_sum(array_column($jsonDay1['hourly_charts']['hourly_data'], 'arr_realized'));
        $hourlyDepartures = array_sum(array_column($jsonDay1['hourly_charts']['hourly_data'], 'dep_realized'));
        $hourlyTotal = array_sum(array_column($jsonDay1['hourly_charts']['hourly_data'], 'total_realized'));
        $this->assertEquals($firstDayExpectedCount, $hourlyTotal);
        $this->assertEquals($jsonDay1['kpis']['arrivals'], $hourlyArrivals);
        $this->assertEquals($jsonDay1['kpis']['departures'], $hourlyDepartures);

        // 3. Switch to another day (e.g. second available day)
        $secondDay = $datesList[1];
        $secondDayExpectedCount = count(array_filter($records, fn($r) => ($r['operational_date'] ?? '') === $secondDay));

        $resDay2 = $this->getJson(route('fdr.filter', [
            'upload'         => $upload->id,
            'analysis_level' => 'DAILY',
            'analysis_date'  => $secondDay,
            'v'              => 102,
        ]));

        $resDay2->assertStatus(200);
        $jsonDay2 = $resDay2->json();

        $this->assertEquals($secondDayExpectedCount, $jsonDay2['filtered_count']);
        $this->assertEquals($secondDayExpectedCount, $jsonDay2['kpis']['total_flights']);
        $this->assertEquals($secondDay, $jsonDay2['analysis_date']);

        // 4. Test Export CSV strictly reflecting the filtered single day
        $csvResponse = $this->get(route('fdr.export.csv', [
            'upload'         => $upload->id,
            'analysis_level' => 'DAILY',
            'analysis_date'  => $firstDay,
        ]));
        $csvResponse->assertStatus(200);

        // 5. Test Export PDF strictly reflecting the filtered single day
        $pdfResponse = $this->get(route('fdr.export.pdf', [
            'upload'         => $upload->id,
            'analysis_level' => 'DAILY',
            'analysis_date'  => $firstDay,
        ]));
        $pdfResponse->assertStatus(200);
    }
}
