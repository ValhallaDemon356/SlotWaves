<?php
require __DIR__ . '/../vendor/autoload.php';

$client = new GuzzleHttp\Client(['base_uri' => 'http://127.0.0.1:8000', 'http_errors' => false]);

echo "========================================================\n";
echo "1. TESTING SINGLE DAU-02 UPLOAD & DASHBOARD\n";
echo "========================================================\n";

$singleFile = 'C:/SlotWaves/resources/templates/dau/DAU-2.xls';
$res = $client->post('/upload', [
    'multipart' => [
        [
            'name'     => 'file',
            'contents' => fopen($singleFile, 'r'),
            'filename' => 'DAU-2.xls',
        ],
        [
            'name'     => 'report_type',
            'contents' => 'DAU2',
        ],
    ],
]);

echo "Upload status: " . $res->getStatusCode() . PHP_EOL;
$body = json_decode((string)$res->getBody(), true);
echo "Upload response: " . json_encode($body) . PHP_EOL;

$singleUploadId = $body['uploadId'] ?? ($body['upload_id'] ?? null);
if ($singleUploadId) {
    $dashRes = $client->get("/dau/dashboard/{$singleUploadId}");
    echo "Single Dashboard status: " . $dashRes->getStatusCode() . PHP_EOL;
    $dashHtml = (string)$dashRes->getBody();
    echo "Has SOEKARNO HATTA: " . (str_contains($dashHtml, 'SOEKARNO HATTA') ? 'YES' : 'NO') . PHP_EOL;
    echo "Has DAU-02 or DAU-2: " . (str_contains($dashHtml, 'DAU-02') || str_contains($dashHtml, 'DAU-2') ? 'YES' : 'NO') . PHP_EOL;
}

echo "\n========================================================\n";
echo "2. TESTING COMPARISON UPLOAD (2025 & 2026 FILES)\n";
echo "========================================================\n";

$file2025 = 'C:/Users/Axioo Pongo/OneDrive/Documents/Data ATDP OASYS/DAU/6 Bulan/(01-01-2025) - (30-06-2025)/DAU-02.xls';
$file2026 = 'C:/Users/Axioo Pongo/OneDrive/Documents/Data ATDP OASYS/DAU/6 Bulan/(01-01-2026) - (30-06-2026)/DAU-02.xls';
$file5mo  = 'C:/Users/Axioo Pongo/OneDrive/Documents/Data ATDP OASYS/DAU/5 Bulan/(01-08-2025) - (31-12-2025)/DAU-02.xls';

$res1 = $client->post('/upload/compare-file', [
    'multipart' => [
        ['name' => 'file', 'contents' => fopen($file2025, 'r'), 'filename' => 'DAU-02-2025.xls'],
        ['name' => 'report_type', 'contents' => 'DAU2'],
    ],
]);
$body1 = json_decode((string)$res1->getBody(), true);
echo "File 1 upload (2025): " . ($body1['status'] ?? 'ERROR') . " | ID: " . ($body1['upload_id'] ?? 'N/A') . " | Period: " . ($body1['period_display'] ?? '') . " | Days: " . ($body1['data_days'] ?? '') . PHP_EOL;

$res2 = $client->post('/upload/compare-file', [
    'multipart' => [
        ['name' => 'file', 'contents' => fopen($file2026, 'r'), 'filename' => 'DAU-02-2026.xls'],
        ['name' => 'report_type', 'contents' => 'DAU2'],
    ],
]);
$body2 = json_decode((string)$res2->getBody(), true);
echo "File 2 upload (2026): " . ($body2['status'] ?? 'ERROR') . " | ID: " . ($body2['upload_id'] ?? 'N/A') . " | Period: " . ($body2['period_display'] ?? '') . " | Days: " . ($body2['data_days'] ?? '') . PHP_EOL;

$res3 = $client->post('/upload/compare-file', [
    'multipart' => [
        ['name' => 'file', 'contents' => fopen($file5mo, 'r'), 'filename' => 'DAU-02-5mo.xls'],
        ['name' => 'report_type', 'contents' => 'DAU2'],
    ],
]);
$body3 = json_decode((string)$res3->getBody(), true);
echo "File 3 upload (5mo): " . ($body3['status'] ?? 'ERROR') . " | ID: " . ($body3['upload_id'] ?? 'N/A') . " | Period: " . ($body3['period_display'] ?? '') . " | Days: " . ($body3['data_days'] ?? '') . PHP_EOL;

echo "\n========================================================\n";
echo "3. TESTING VALIDATION ENDPOINT\n";
echo "========================================================\n";

// Test invalid duration (2025 vs 5mo)
$valResInvalid = $client->post('/dau/compare/validate', [
    'json' => ['report_ids' => [$body1['upload_id'], $body3['upload_id']]]
]);
$valBodyInv = json_decode((string)$valResInvalid->getBody(), true);
echo "Mismatch Duration Valid? " . ($valBodyInv['valid'] ? 'YES' : 'NO') . " | Errors: " . implode('; ', $valBodyInv['errors'] ?? []) . PHP_EOL;

// Test valid pair (2025 vs 2026)
$valResValid = $client->post('/dau/compare/validate', [
    'json' => ['report_ids' => [$body1['upload_id'], $body2['upload_id']]]
]);
$valBodyVal = json_decode((string)$valResValid->getBody(), true);
echo "Matching Pair Valid? " . ($valBodyVal['valid'] ? 'YES' : 'NO') . " | Errors: " . implode('; ', $valBodyVal['errors'] ?? []) . PHP_EOL;
echo "Checklist: " . json_encode($valBodyVal['checklist'] ?? []) . PHP_EOL;

echo "\n========================================================\n";
echo "4. TESTING COMPARISON DASHBOARD VIEW\n";
echo "========================================================\n";

$compDashRes = $client->get("/dau/compare?reports[]={$body1['upload_id']}&reports[]={$body2['upload_id']}");
echo "Comparison Dashboard HTTP status: " . $compDashRes->getStatusCode() . PHP_EOL;
$compHtml = (string)$compDashRes->getBody();
echo "Contains 'KINERJA OPERASIONAL BANDARA': " . (str_contains($compHtml, 'KINERJA OPERASIONAL BANDARA') ? 'YES' : 'NO') . PHP_EOL;
echo "Contains 'DATA PERGERAKAN HISTORIS': " . (str_contains($compHtml, 'DATA PERGERAKAN HISTORIS') ? 'YES' : 'NO') . PHP_EOL;
echo "Contains 'SOEKARNO HATTA': " . (str_contains($compHtml, 'SOEKARNO HATTA') ? 'YES' : 'NO') . PHP_EOL;
echo "Contains 'CGK': " . (str_contains($compHtml, 'CGK') ? 'YES' : 'NO') . PHP_EOL;
echo "Contains 'chart-section1-passenger': " . (str_contains($compHtml, 'chart-section1-passenger') ? 'YES' : 'NO') . PHP_EOL;
echo "Contains 'chart-hist-passenger': " . (str_contains($compHtml, 'chart-hist-passenger') ? 'YES' : 'NO') . PHP_EOL;

echo "\n========================================================\n";
echo "5. TESTING COMPARISON CSV & PDF EXPORT\n";
echo "========================================================\n";

$csvRes = $client->get("/dau/compare/export/csv?reports[]={$body1['upload_id']}&reports[]={$body2['upload_id']}");
echo "CSV Export HTTP status: " . $csvRes->getStatusCode() . " | Content-Type: " . $csvRes->getHeaderLine('Content-Type') . PHP_EOL;
echo "CSV first 200 chars:\n" . substr((string)$csvRes->getBody(), 0, 200) . PHP_EOL;

$pdfRes = $client->get("/dau/compare/export/pdf?reports[]={$body1['upload_id']}&reports[]={$body2['upload_id']}");
echo "PDF Export HTTP status: " . $pdfRes->getStatusCode() . " | Content-Type: " . $pdfRes->getHeaderLine('Content-Type') . " | Size: " . strlen((string)$pdfRes->getBody()) . " bytes" . PHP_EOL;
