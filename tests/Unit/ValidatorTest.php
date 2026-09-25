<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FlightDailyReport\FlightDailyReportValidator;

class ValidatorTest extends TestCase
{
    protected FlightDailyReportValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FlightDailyReportValidator();
    }

    public function test_valid_template_passes_validation(): void
    {
        $path = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        $res = $this->validator->validate($path);

        $this->assertTrue($res['valid']);
        $this->assertGreaterThan(0, $res['records_count']);
        $this->assertEmpty($res['errors']);
    }

    public function test_non_existent_file_fails(): void
    {
        $res = $this->validator->validate('/path/to/non_existent_file.xls');

        $this->assertFalse($res['valid']);
        $this->assertEquals('FILE_NOT_FOUND', $res['category']);
    }

    public function test_unsupported_extension_fails(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fdr_') . '.txt';
        file_put_contents($tempFile, 'dummy content');

        $res = $this->validator->validate($tempFile);
        @unlink($tempFile);

        $this->assertFalse($res['valid']);
        $this->assertEquals('INVALID_EXTENSION', $res['category']);
    }

    public function test_empty_table_fails_with_no_records(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fdr_') . '.xls';
        file_put_contents($tempFile, '<table><tr><td>No records here</td></tr></table>');

        $res = $this->validator->validate($tempFile);
        @unlink($tempFile);

        $this->assertFalse($res['valid']);
        $this->assertContains($res['category'], ['NO_RECORDS', 'MISSING_CRITICAL_COLUMNS']);
    }

    public function test_btj_cgk_bdo_fdr_workbooks_pass_validation(): void
    {
        $files = [
            'storage/app/templates/BTJ FDR.xls',
            'storage/app/templates/CGK FDR.xls',
            'storage/app/templates/BDO FDR.xls',
        ];

        foreach ($files as $relPath) {
            $path = base_path($relPath);
            if (file_exists($path)) {
                $res = $this->validator->validate($path);
                $this->assertTrue($res['valid'], "Failed validating {$relPath}: " . json_encode($res['errors'] ?? []));
                $this->assertGreaterThan(0, $res['records_count']);
            }
        }
    }

    public function test_validation_with_temp_upload_file_path_and_client_filename(): void
    {
        $template = storage_path('app/templates/OASYS-FDR-TEMPLATE.xls');
        $tempPath = tempnam(sys_get_temp_dir(), 'php_upload_') . '.tmp';
        copy($template, $tempPath);

        // When browser uploads, path is tempnam .tmp and original filename is BTJ FDR.xls
        $res = $this->validator->validate($tempPath, 'BTJ FDR.xls');
        @unlink($tempPath);

        $this->assertTrue($res['valid']);
        $this->assertGreaterThan(0, $res['records_count']);
    }

    public function test_unsupported_extension_error_never_renders_empty_dots(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'fdr_');
        file_put_contents($tempFile, 'raw binary data with no header');

        $res = $this->validator->validate($tempFile, '');
        @unlink($tempFile);

        $this->assertFalse($res['valid']);
        $this->assertEquals('INVALID_EXTENSION', $res['category']);
        $this->assertNotEmpty($res['errors']);
        foreach ($res['errors'] as $err) {
            $this->assertStringNotContainsString('..', $err);
            $this->assertStringContainsString('(unknown)', $err);
        }
    }
}
