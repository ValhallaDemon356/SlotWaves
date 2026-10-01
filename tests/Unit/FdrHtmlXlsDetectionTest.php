<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportParser;

class FdrHtmlXlsDetectionTest extends TestCase
{
    protected FlightDailyReportParser $parser;
    protected string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FlightDailyReportParser();
        $this->filePath = storage_path('app/templates/CGK IP YES.xls');
        if (!file_exists($this->filePath)) {
            $this->filePath = 'C:/Users/Axioo Pongo/Downloads/CGK IP YES.xls';
        }
    }

    public function test_it_detects_html_markup_disguised_as_xls(): void
    {
        $this->assertFileExists($this->filePath);
        $handle = fopen($this->filePath, 'rb');
        $headerBytes = fread($handle, 4096);
        fclose($handle);

        $detected = $this->parser->detectActualFormat($headerBytes, 'CGK IP YES.xls');
        $this->assertEquals('HTML_XLS', $detected);
    }
}
