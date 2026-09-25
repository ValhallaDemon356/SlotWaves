<?php

namespace App\Services\Dau;

use Illuminate\Http\UploadedFile;
use App\Services\Dau\ReportTemplateRegistry;

class TemplateValidator
{
    /**
     * Validate an uploaded file against the selected report type strictly by content.
     *
     * @param string $selectedReportType e.g. 'slot_schedule', 'DAU1', 'DAU5'
     * @param UploadedFile|string $file Uploaded file instance or absolute file path
     * @return array
     */
    public function validate(string $selectedReportType, $file, bool $isProbe = false): array
    {
        $conf = ReportTemplateRegistry::find($selectedReportType);
        if (!$conf) {
            return [
                'valid'            => false,
                'category'         => 'UNSUPPORTED_DAU_TYPE',
                'category_title'   => 'UNSUPPORTED REPORT TYPE',
                'detectedTemplate' => 'Unknown',
                'expectedTemplate' => 'Valid Report Type',
                'errors'           => ["Unknown or unsupported report type: {$selectedReportType}"],
                'error'            => "Unknown or unsupported report type: {$selectedReportType}",
                'warnings'         => [],
            ];
        }

        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $originalFilename = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);

        if (!file_exists($filePath)) {
            return [
                'valid'            => false,
                'category'         => 'CORRUPTED_FILE',
                'category_title'   => 'FILE NOT FOUND OR UNREADABLE',
                'detectedTemplate' => 'None',
                'expectedTemplate' => $conf['template_label'],
                'errors'           => ["Uploaded file does not exist on disk."],
                'error'            => "Uploaded file does not exist on disk.",
                'warnings'         => [],
            ];
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            return [
                'valid'            => false,
                'category'         => 'CORRUPTED_FILE',
                'category_title'   => 'EMPTY FILE (0 BYTES)',
                'detectedTemplate' => 'Empty File',
                'expectedTemplate' => $conf['template_label'],
                'errors'           => ["Uploaded file is empty (0 bytes)."],
                'error'            => "Uploaded file is empty (0 bytes).",
                'warnings'         => [],
            ];
        }

        // Determine basic file format
        $isPdf = $this->isPdfFile($filePath);

        // ── Check 1: Airport Slot Schedule expects PDF ────────────────────────
        if ($selectedReportType === 'slot_schedule') {
            if (!$isPdf) {
                $detected = $this->detectDauTemplateType($filePath) ?: 'Excel / Spreadsheet';
                return [
                    'valid'            => false,
                    'category'         => 'INVALID_EXTENSION',
                    'category_title'   => 'INVALID FILE FORMAT',
                    'detectedTemplate' => $detected,
                    'expectedTemplate' => 'Airport Slot Schedule PDF',
                    'errors'           => [
                        "INVALID FILE FORMAT: Airport Slot Schedule requires Airport Slot Schedule PDF (.pdf). Uploaded file is {$detected}."
                    ],
                    'error'            => "INVALID FILE FORMAT: Airport Slot Schedule requires Airport Slot Schedule PDF (.pdf). Uploaded file is {$detected}.",
                    'warnings'         => [],
                ];
            }

            // Inspect PDF content for flight schedule signatures
            $pdfValidation = $this->validateSlotSchedulePdf($filePath);
            if (!$pdfValidation['valid']) {
                return [
                    'valid'            => false,
                    'category'         => 'INVALID_TEMPLATE',
                    'category_title'   => 'INVALID SLOT SCHEDULE TEMPLATE',
                    'detectedTemplate' => 'Generic PDF',
                    'expectedTemplate' => 'Airport Slot Schedule PDF',
                    'errors'           => $pdfValidation['errors'],
                    'error'            => implode('; ', $pdfValidation['errors']),
                    'warnings'         => [],
                ];
            }

            return [
                'valid'            => true,
                'category'         => null,
                'category_title'   => null,
                'detectedTemplate' => 'slot_schedule',
                'expectedTemplate' => 'Airport Slot Schedule PDF',
                'records_count'    => $pdfValidation['estimated_records'] ?? 0,
                'detected_columns' => $conf['detected_columns'],
                'errors'           => [],
                'warnings'         => [],
            ];
        }

        // ── Check 2: DAU expects Excel/HTML, rejects PDF ───────────────────────
        if ($isPdf) {
            return [
                'valid'            => false,
                'category'         => 'INVALID_EXTENSION',
                'category_title'   => 'INVALID FILE FORMAT (PDF DETECTED)',
                'detectedTemplate' => 'PDF Document',
                'expectedTemplate' => $conf['template_filename'],
                'errors'           => [
                    "INVALID FILE FORMAT: {$conf['name']} requires Excel (.xls/.xlsx). Expected template: {$conf['template_filename']}."
                ],
                'error'            => "INVALID FILE FORMAT: {$conf['name']} requires Excel (.xls/.xlsx). Expected template: {$conf['template_filename']}.",
                'warnings'         => [],
            ];
        }

        // ── Check 2.5: Flight Daily Report (FDR) Module Validation ────────────
        if (strcasecmp($selectedReportType, 'fdr') === 0) {
            $fdrValidator = new \App\Services\FlightDailyReport\FlightDailyReportValidator();
            $fdrResult = $fdrValidator->validate($filePath);

            if (!$fdrResult['valid']) {
                return [
                    'valid'            => false,
                    'category'         => $fdrResult['category'] ?? 'INVALID_TEMPLATE',
                    'category_title'   => $fdrResult['category_title'] ?? 'INVALID FDR TEMPLATE',
                    'detectedTemplate' => 'Invalid FDR File',
                    'expectedTemplate' => $conf['template_label'],
                    'errors'           => $fdrResult['errors'] ?? ['The uploaded file does not match the Flight Daily Report format.'],
                    'error'            => implode('; ', $fdrResult['errors'] ?? ['The uploaded file does not match the Flight Daily Report format.']),
                    'warnings'         => [],
                ];
            }

            return [
                'valid'            => true,
                'category'         => null,
                'category_title'   => null,
                'detectedTemplate' => 'fdr',
                'expectedTemplate' => $conf['template_filename'],
                'records_count'    => $fdrResult['records_count'] ?? 0,
                'detected_columns' => $conf['detected_columns'],
                'summary'          => $fdrResult['summary'] ?? [],
                'meta'             => $fdrResult['meta'] ?? [],
                'errors'           => [],
                'warnings'         => [],
            ];
        }

        // ── Check 3: Content Fingerprinting across DAU types ───────────────────
        $detectedDau = $this->detectDauTemplateType($filePath);

        if (!$detectedDau) {
            return [
                'valid'            => false,
                'category'         => 'INVALID_TEMPLATE',
                'category_title'   => "INVALID {$conf['code']} TEMPLATE",
                'detectedTemplate' => 'Unknown Document',
                'expectedTemplate' => $conf['template_label'],
                'errors'           => [
                    "INVALID FILE TEMPLATE: Could not recognize a valid OASYS DAU structure in the uploaded file.",
                    "Expected template: {$conf['template_filename']}."
                ],
                'error'            => "INVALID FILE TEMPLATE: Could not recognize a valid OASYS DAU structure. Expected template: {$conf['template_filename']}.",
                'warnings'         => [],
            ];
        }

        // Check if detected matches selected (DAU5 and DAU5C share identical schema)
        $isMatch = ($detectedDau === $selectedReportType) ||
                   ($selectedReportType === 'DAU5C' && in_array($detectedDau, ['DAU5', 'DAU5C'])) ||
                   ($selectedReportType === 'DAU5' && in_array($detectedDau, ['DAU5', 'DAU5C']));

        if (!$isMatch) {
            $detectedConf = ReportTemplateRegistry::find($detectedDau);
            $detectedLabel = $detectedConf ? $detectedConf['name'] : $detectedDau;

            return [
                'valid'            => false,
                'category'         => 'INVALID_TEMPLATE',
                'category_title'   => "INVALID {$conf['code']} TEMPLATE",
                'detectedTemplate' => $detectedDau,
                'expectedTemplate' => $selectedReportType,
                'errors'           => [
                    "❌ Template Tidak Sesuai",
                    "Selected: {$conf['name']}",
                    "Uploaded structure: {$detectedLabel}",
                    "Expected: {$conf['template_filename']} structure",
                    "Please upload the correct {$conf['name']} source file."
                ],
                'error'            => "Template mismatch: Uploaded file matches {$detectedLabel}, but selected report is {$conf['name']}.",
                'warnings'         => [],
            ];
        }

        // ── Check 4: Deep Parse or Probe Verification ─────────────────────────
        try {
            $parserClass = $conf['parser_class'];
            /** @var \App\Services\Dau\Parsers\BaseDauParser $parser */
            $parser = new $parserClass();

            // Probe mode: extract structural metadata without requiring full dataset parsing
            if ($isProbe) {
                $meta = (new class extends \App\Services\Dau\Parsers\BaseDauParser {
                    public function parse(string $p): array { return []; }
                    public function getMeta(string $p): array { return $this->extractMetadata($p, []); }
                })->getMeta($filePath);

                return [
                    'valid'            => true,
                    'category'         => null,
                    'category_title'   => null,
                    'is_probe'         => true,
                    'detectedTemplate' => $selectedReportType,
                    'expectedTemplate' => $conf['template_filename'],
                    'records_count'    => 'Verified (Ready to Ingest)',
                    'detected_columns' => $conf['detected_columns'],
                    'summary'          => [],
                    'meta'             => $meta,
                    'errors'           => [],
                    'warnings'         => [],
                ];
            }

            $parsedData = $parser->parse($filePath);

            $recordCount = $parsedData['records_count'] ?? 0;
            if ($recordCount === 0) {
                return [
                    'valid'            => false,
                    'category'         => 'INVALID_TEMPLATE',
                    'category_title'   => "INVALID {$conf['code']} TEMPLATE",
                    'detectedTemplate' => $detectedDau,
                    'expectedTemplate' => $conf['template_filename'],
                    'errors'           => [
                        "File matches {$conf['name']} structure but contains no data rows."
                    ],
                    'error'            => "File matches {$conf['name']} structure but contains no data rows.",
                    'warnings'         => [],
                ];
            }

            return [
                'valid'            => true,
                'category'         => null,
                'category_title'   => null,
                'detectedTemplate' => $selectedReportType,
                'expectedTemplate' => $conf['template_filename'],
                'records_count'    => $recordCount,
                'detected_columns' => $conf['detected_columns'],
                'summary'          => $parsedData['summary'] ?? [],
                'meta'             => $parsedData['meta'] ?? [],
                'errors'           => [],
                'warnings'         => [],
            ];
        } catch (\Throwable $e) {
            return [
                'valid'            => false,
                'category'         => 'CORRUPTED_FILE',
                'category_title'   => 'CORRUPTED / UNREADABLE EXCEL',
                'detectedTemplate' => $detectedDau,
                'expectedTemplate' => $conf['template_filename'],
                'errors'           => [
                    "Error parsing {$conf['name']} content: " . $e->getMessage()
                ],
                'error'            => "Error parsing {$conf['name']} content: " . $e->getMessage(),
                'warnings'         => [],
            ];
        }
    }

    /**
     * Check if a file is PDF by header magic bytes.
     */
    protected function isPdfFile(string $filePath): bool
    {
        $handle = @fopen($filePath, 'rb');
        if (!$handle) return false;
        $bytes = fread($handle, 5);
        fclose($handle);
        return $bytes === '%PDF-';
    }

    /**
     * Inspect PDF content for Airport Slot Schedule markers.
     */
    protected function validateSlotSchedulePdf(string $filePath): array
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            // Look for standard airport schedule keywords
            $hasKeywords = (
                (preg_match('/\b(FLIGHT|FLT|ARV|DEP|STA|STD|ORIG|DEST|A\/C|ACFT)\b/i', $text)) ||
                (preg_match('/(DEPARTURE|ARRIVAL|PENERBANGAN|BERJADWAL)/i', $text)) ||
                (preg_match('/[A-Z0-9]{2,3}\s*\d{3,4}/', $text)) // Flight numbers like GA123
            );

            if (!$hasKeywords) {
                return [
                    'valid'  => false,
                    'errors' => ['Uploaded PDF does not contain recognized Airport Slot Schedule tables or flight data.'],
                ];
            }

            // Estimate records by flight number regex
            preg_match_all('/[A-Z0-9]{2,3}\s*\d{3,4}/', $text, $matches);
            $count = count(array_unique($matches[0] ?? []));

            return [
                'valid'             => true,
                'estimated_records' => max($count, 1),
            ];
        } catch (\Throwable $e) {
            return [
                'valid'  => false,
                'errors' => ['Failed to read PDF schedule: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Detect specific DAU template type by inspecting HTML/XML/Spreadsheet structure.
     */
    public function detectDauTemplateType(string $filePath): ?string
    {
        // Read header section (first 256 KB) — all OASYS template titles and columns are within the first 64 KB
        $content = file_get_contents($filePath, false, null, 0, 262144);
        $upper = strtoupper($content);

        // 0. Flight Daily Report (FDR) OASYS Template
        if (str_contains($upper, 'FLIGHT DAILY REPORT') ||
            (str_contains($upper, 'AIR LINE') && str_contains($upper, 'PAIRED NO') && (str_contains($upper, 'SIBT') || str_contains($upper, 'SOBT')))) {
            return 'fdr';
        }

        // 1. Exact OASYS report titles in <title> or <CENTER><B>
        if (preg_match('/(?:OASYS\s+REPORT-01|DAU-01\)|DAU-1\b|DATA\s+LALU\s+LINTAS\s+ANGKUTAN\s+UDARA)/i', $upper) &&
            str_contains($upper, 'BANDARA ASAL/TUJUAN') && str_contains($upper, 'KAPASITAS KURSI')) {
            return 'DAU1';
        }

        if (preg_match('/(?:OASYS\s+REPORT-10B|DAU-10B\)|BLOCK\s+ON\/OFF)/i', $upper)) {
            return 'DAU10B';
        }

        if (preg_match('/(?:OASYS\s+REPORT-10A|DAU-10A\b)/i', $upper) ||
            (str_contains($upper, 'JAM PUNCAK') && str_contains($upper, 'PERIODE') && (str_contains($upper, '2F') || str_contains($upper, '3U') || str_contains($upper, '1B')))) {
            return 'DAU10A';
        }

        if (preg_match('/(?:OASYS\s+REPORT-10\b|DAU-10\))/i', $upper) && str_contains($upper, 'JAM PUNCAK')) {
            return 'DAU10';
        }

        if (preg_match('/(?:OASYS\s+REPORT-11|DAU-11\))/i', $upper) ||
            (str_contains($upper, 'DATA STATISTIK') && str_contains($upper, 'INTERNASIONAL') && str_contains($upper, 'DOMESTIK') && str_contains($upper, 'BKT'))) {
            return 'DAU11';
        }

        if (preg_match('/(?:OASYS\s+REPORT-12|DAU-12\))/i', $upper) ||
            (str_contains($upper, 'DATA STATISTIK') && str_contains($upper, 'ARRIVAL') && str_contains($upper, 'DEPARTURE') && str_contains($upper, 'TOT'))) {
            return 'DAU12';
        }

        if (preg_match('/(?:OASYS\s+REPORT-04B|DAU-04B\))/i', $upper) ||
            (str_contains($upper, 'KOTA ASAL/TUJUAN') && str_contains($upper, 'KODE IATA') && (str_contains($upper, 'MY INDO') || str_contains($upper, 'CEBU') || str_contains($upper, 'GARUDA')))) {
            return 'DAU4B';
        }

        if (preg_match('/(?:OASYS\s+REPORT-04A|DAU-04A\b|ASAL\/TUJUAN\s+OPERATOR)/i', $upper) ||
            (str_contains($upper, 'NAMAOPERATOR') && str_contains($upper, 'OPERATOR') && str_contains($upper, 'AIRPORT'))) {
            return 'DAU4A';
        }

        if (preg_match('/(?:OASYS\s+REPORT-04\b|DAU-04\))/i', $upper) ||
            (str_contains($upper, 'ASAL/TUJUAN') && str_contains($upper, 'CITY CODE') && str_contains($upper, 'AIRPORT'))) {
            return 'DAU4';
        }

        // DAU-5B: Must have TERMINAL column in table or Report-05B in title/heading
        if (preg_match('/(?:OASYS\s+REPORT-05B|DAU-05B\b|\(DAU-05\)\s*B\b)/i', $upper) ||
            (str_contains($upper, 'AIRLINE/OPERATOR') && preg_match('/<TD[^>]*>\s*TERMINAL\s*<\/TD>/i', $content))) {
            return 'DAU5B';
        }

        // DAU-5A: Has Report-05A or (DAU-05) A or ARR E.CREW column
        if (preg_match('/(?:OASYS\s+REPORT-05A|DAU-05A\b|\(DAU-05\)\s*A\b)/i', $upper) ||
            (str_contains($upper, 'AIRLINE/OPERATOR') && (str_contains($upper, 'ARR E.CREW') || str_contains($upper, 'ARRE.CREW')))) {
            return 'DAU5A';
        }

        if (preg_match('/(?:OASYS\s+REPORT-05C|DAU-05C\b|\(DAU-05\)\s*C\b)/i', $upper)) {
            return 'DAU5C';
        }

        if (preg_match('/(?:OASYS\s+REPORT-05\b|DAU-05\))/i', $upper) &&
            str_contains($upper, 'AIRLINE/OPERATOR') &&
            !preg_match('/<TD[^>]*>\s*TERMINAL\s*<\/TD>/i', $content)) {
            return 'DAU5';
        }

        if (preg_match('/(?:OASYS\s+REPORT-06|DAU-06\))/i', $upper) ||
            (str_contains($upper, 'TIPE PESAWAT') && str_contains($upper, 'PESAWAT') && str_contains($upper, 'PENUMPANG'))) {
            return 'DAU6';
        }

        if (preg_match('/(?:OASYS\s+REPORT-03|DAU-03\))/i', $upper) ||
            (str_contains($upper, 'STATUS PENERBANGAN') || (str_contains($upper, 'NIAGA') && str_contains($upper, 'BUKAN NIAGA')))) {
            return 'DAU3';
        }

        if (preg_match('/(?:OASYS\s+REPORT-02|DAU-02\))/i', $upper) ||
            (str_contains($upper, 'SECARA TOTAL') && str_contains($upper, 'DOMESTIK') && str_contains($upper, 'INTERNASIONAL'))) {
            return 'DAU2';
        }

        // Secondary fallback checks based on unique column combinations
        if (str_contains($upper, 'BANDARA ASAL/TUJUAN') && str_contains($upper, 'FLIGHT NO')) {
            return 'DAU1';
        }
        if (str_contains($upper, 'KOTA ASAL/TUJUAN') && str_contains($upper, 'KODE IATA')) {
            return 'DAU4B';
        }
        if (str_contains($upper, 'NAMAOPERATOR') && str_contains($upper, 'AIRPORT')) {
            return 'DAU4A';
        }
        if (str_contains($upper, 'CITY CODE') && str_contains($upper, 'AIRPORT')) {
            return 'DAU4';
        }
        if (str_contains($upper, 'TIPE PESAWAT')) {
            return 'DAU6';
        }
        if (str_contains($upper, 'BLOCK ON/OFF')) {
            return 'DAU10B';
        }
        if (str_contains($upper, 'JAM PUNCAK') && str_contains($upper, 'PERIODE')) {
            return 'DAU10A';
        }
        if (str_contains($upper, 'JAM PUNCAK')) {
            return 'DAU10';
        }

        return null;
    }
}
