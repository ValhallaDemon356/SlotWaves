<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Upload;
use App\Services\Dau\Parsers\BaseDauParser;
use App\Services\Dau\Parsers\DAU10AParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dau10ADateRangeTest extends TestCase
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
     * 1. DD-MM-YYYY normalization:
     * 01-08-2026 must be parsed as 2026-08-01, NOT 2026-01-08.
     */
    public function test_1_dd_mm_yyyy_parsed_correctly(): void
    {
        $this->assertEquals('2026-08-01', BaseDauParser::normalizeOperationalDate('01-08-2026'));
        $this->assertEquals('2026-08-02', BaseDauParser::normalizeOperationalDate('02-08-2026'));
        $this->assertEquals('2026-08-15', BaseDauParser::normalizeOperationalDate('15-08-2026'));
    }

    /**
     * 2. DD/MM/YYYY supported:
     * 01/08/2026 must be parsed as 2026-08-01.
     */
    public function test_2_dd_slash_mm_slash_yyyy_parsed_correctly(): void
    {
        $this->assertEquals('2026-08-01', BaseDauParser::normalizeOperationalDate('01/08/2026'));
        $this->assertEquals('2027-01-31', BaseDauParser::normalizeOperationalDate('31/01/2027'));
    }

    /**
     * 3. YYYY-MM-DD supported:
     * 2026-08-01 normalized to 2026-08-01.
     */
    public function test_3_yyyy_mm_dd_parsed_correctly(): void
    {
        $this->assertEquals('2026-08-01', BaseDauParser::normalizeOperationalDate('2026-08-01'));
        $this->assertEquals('2027-01-31', BaseDauParser::normalizeOperationalDate('2027-01-31'));
    }

    /**
     * PART 28: Date parsing test for 31-08-2026.
     * 31 cannot be interpreted as month. Must produce 2026-08-31.
     */
    public function test_part_28_day_31_cannot_be_month(): void
    {
        $this->assertEquals('2026-08-31', BaseDauParser::normalizeOperationalDate('31-08-2026'));
        $this->assertEquals('31-08-2026', BaseDauParser::formatDisplayDate('2026-08-31'));
    }

    /**
     * 4. One-day dataset:
     * Expected: availableDataDays = 1, start: 2026-08-01, end: 2026-08-01, display: 01-08-2026 - 01-08-2026.
     */
    public function test_4_one_day_dataset(): void
    {
        $parser = new DAU10AParser();
        $html = "<html><body>";
        $html .= "<CENTER><B>TANGGAL 01-08-2026 s/d 01-08-2026</B></CENTER>";
        $html .= $this->createTableHtml('01-08-2026');
        $html .= "</body></html>";

        $tmp = tempnam(sys_get_temp_dir(), 'dau_1d_') . '.xls';
        file_put_contents($tmp, $html);
        $res = $parser->parse($tmp);
        unlink($tmp);

        $this->assertEquals('2026-08-01', $res['meta']['start_date']);
        $this->assertEquals('2026-08-01', $res['meta']['end_date']);
        $this->assertEquals(1, $res['available_days']);
        $this->assertEquals(['2026-08-01'], $res['available_dates']);

        $display = BaseDauParser::formatDisplayDate($res['meta']['start_date']) . ' - ' . BaseDauParser::formatDisplayDate($res['meta']['end_date']);
        $this->assertEquals('01-08-2026 - 01-08-2026', $display);
    }

    /**
     * 5 & PART 27: Multi-day dataset:
     * Input: 01-08-2026, 02-08-2026, 03-08-2026, 15-08-2026, 31-08-2026
     * Expected:
     *   start: 2026-08-01
     *   end:   2026-08-31
     *   display: 01-08-2026 - 31-08-2026
     *   availableDataDays: 5
     */
    public function test_5_and_part_27_multi_day_dataset(): void
    {
        $parser = new DAU10AParser();
        $dates = ['01-08-2026', '02-08-2026', '03-08-2026', '15-08-2026', '31-08-2026'];

        $html = "<html><body>";
        $html .= "<CENTER><B>TANGGAL 01-08-2026 s/d 31-08-2026</B></CENTER>";
        foreach ($dates as $d) {
            $html .= $this->createTableHtml($d);
        }
        $html .= "</body></html>";

        $tmp = tempnam(sys_get_temp_dir(), 'dau_5d_') . '.xls';
        file_put_contents($tmp, $html);
        $res = $parser->parse($tmp);
        unlink($tmp);

        $this->assertEquals('2026-08-01', $res['meta']['start_date']);
        $this->assertEquals('2026-08-31', $res['meta']['end_date']);
        $this->assertEquals(5, $res['available_days']);
        $this->assertCount(5, $res['available_dates']);

        $display = BaseDauParser::formatDisplayDate($res['meta']['start_date']) . ' - ' . BaseDauParser::formatDisplayDate($res['meta']['end_date']);
        $this->assertEquals('01-08-2026 - 31-08-2026', $display);
    }

    /**
     * 6. Multi-month dataset:
     * Range: 01-08-2026 to 31-10-2026 (92 days).
     * Expected: availableDataDays = 92, start: 2026-08-01, end: 2026-10-31.
     */
    public function test_6_multi_month_dataset(): void
    {
        $parser = new DAU10AParser();
        $current = new \DateTime('2026-08-01');
        $end = new \DateTime('2026-10-31');

        $html = "<html><body>";
        $html .= "<CENTER><B>TANGGAL 01-08-2026 s/d 31-10-2026</B></CENTER>";
        $expectedCount = 0;
        while ($current <= $end) {
            $expectedCount++;
            $html .= $this->createTableHtml($current->format('d-m-Y'));
            $current->modify('+1 day');
        }
        $html .= "</body></html>";

        $tmp = tempnam(sys_get_temp_dir(), 'dau_92d_') . '.xls';
        file_put_contents($tmp, $html);
        $res = $parser->parse($tmp);
        unlink($tmp);

        $this->assertEquals(92, $expectedCount);
        $this->assertEquals(92, $res['available_days']);
        $this->assertEquals('2026-08-01', $res['meta']['start_date']);
        $this->assertEquals('2026-10-31', $res['meta']['end_date']);

        $display = BaseDauParser::formatDisplayDate($res['meta']['start_date']) . ' - ' . BaseDauParser::formatDisplayDate($res['meta']['end_date']);
        $this->assertEquals('01-08-2026 - 31-10-2026', $display);
    }

    /**
     * 7. Invalid dates handling:
     * Invalid dates must return null and never fall back to 2026-01-01.
     */
    public function test_7_invalid_dates_return_null_no_fake_fallback(): void
    {
        $this->assertNull(BaseDauParser::normalizeOperationalDate('invalid-date'));
        $this->assertNull(BaseDauParser::normalizeOperationalDate('32-08-2026'));
        $this->assertNull(BaseDauParser::normalizeOperationalDate('00-00-0000'));
        $this->assertNull(BaseDauParser::normalizeOperationalDate(''));
        $this->assertNull(BaseDauParser::normalizeOperationalDate(null));
        // Verify it never turns into 2026-01-01
        $this->assertNotEquals('2026-01-01', BaseDauParser::normalizeOperationalDate('invalid-date'));
    }

    /**
     * 8 & PART 19: Missing dates:
     * Dates: 01-08-2026, 05-08-2026, 06-08-2026.
     * Calendar span is 6, but valid data days is 3.
     * System must NOT invent 02, 03, 04.
     */
    public function test_8_and_part_19_missing_dates_not_invented(): void
    {
        $parser = new DAU10AParser();
        $dates = ['01-08-2026', '05-08-2026', '06-08-2026'];

        $html = "<html><body>";
        foreach ($dates as $d) {
            $html .= $this->createTableHtml($d);
        }
        $html .= "</body></html>";

        $tmp = tempnam(sys_get_temp_dir(), 'dau_sparse_') . '.xls';
        file_put_contents($tmp, $html);
        $res = $parser->parse($tmp);
        unlink($tmp);

        $this->assertEquals('2026-08-01', $res['meta']['start_date']);
        $this->assertEquals('2026-08-06', $res['meta']['end_date']);
        // Must be 3, NOT 6!
        $this->assertEquals(3, $res['available_days']);
        $this->assertEquals(['2026-08-01', '2026-08-05', '2026-08-06'], $res['available_dates']);

        $display = BaseDauParser::formatDisplayDate($res['meta']['start_date']) . ' - ' . BaseDauParser::formatDisplayDate($res['meta']['end_date']);
        $this->assertEquals('01-08-2026 - 06-08-2026', $display);
    }

    /**
     * 9. Unique dates:
     * Duplicate tables or records for the same operational date must be deduplicated.
     */
    public function test_9_duplicate_dates_deduplicated(): void
    {
        $parser = new DAU10AParser();
        $dates = ['01-08-2026', '01-08-2026', '02-08-2026', '02-08-2026'];

        $html = "<html><body>";
        foreach ($dates as $d) {
            $html .= $this->createTableHtml($d);
        }
        $html .= "</body></html>";

        $tmp = tempnam(sys_get_temp_dir(), 'dau_dup_') . '.xls';
        file_put_contents($tmp, $html);
        $res = $parser->parse($tmp);
        unlink($tmp);

        $this->assertEquals(2, $res['available_days']);
        $this->assertEquals(['2026-08-01', '2026-08-02'], $res['available_dates']);
    }

    /**
     * 10 & 11. Start date & End date correctness:
     * Minimum and maximum dates correctly identified regardless of table encounter order.
     */
    public function test_10_and_11_start_and_end_date_correctness(): void
    {
        $parser = new DAU10AParser();
        // Unordered input
        $dates = ['15-08-2026', '31-08-2026', '01-08-2026', '10-08-2026'];

        $html = "<html><body>";
        foreach ($dates as $d) {
            $html .= $this->createTableHtml($d);
        }
        $html .= "</body></html>";

        $tmp = tempnam(sys_get_temp_dir(), 'dau_unordered_') . '.xls';
        file_put_contents($tmp, $html);
        $res = $parser->parse($tmp);
        unlink($tmp);

        $this->assertEquals('2026-08-01', $res['meta']['start_date']);
        $this->assertEquals('2026-08-31', $res['meta']['end_date']);
        $this->assertEquals(4, $res['available_days']);
        $this->assertEquals(['2026-08-01', '2026-08-10', '2026-08-15', '2026-08-31'], $res['available_dates']);
    }

    /**
     * Helper to create HTML table block for a given date.
     */
    private function createTableHtml(string $dateStr): string
    {
        $html = "<center><b>TANGGAL {$dateStr}</b></center><br>";
        $html .= '<table border="1">';
        $html .= '<tr><th>No</th><th>Periode Jam</th><th colspan="10">1</th><th colspan="10">2F</th></tr>';
        $html .= '<tr><th></th><th></th><th>Arr</th><th>Dep</th><th>PArr</th><th>PDep</th><th>PTrn</th><th>PTrf</th><th>Crw</th><th>ExCrw</th><th>TotF</th><th>TotP</th><th>Arr</th><th>Dep</th><th>PArr</th><th>PDep</th><th>PTrn</th><th>PTrf</th><th>Crw</th><th>ExCrw</th><th>TotF</th><th>TotP</th></tr>';
        $html .= '<tr><td>1</td><td>08.01 - 09.00</td><td>10</td><td>10</td><td>200</td><td>200</td><td>0</td><td>0</td><td>4</td><td>0</td><td>20</td><td>400</td><td>5</td><td>5</td><td>100</td><td>100</td><td>0</td><td>0</td><td>2</td><td>0</td><td>10</td><td>200</td></tr>';
        $html .= '</table>';
        return $html;
    }
}
