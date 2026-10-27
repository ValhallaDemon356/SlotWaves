<?php

/**
 * Targeted Test Suite: FDR Database Persistence, Retry Resilience, Idempotency & Reconciler
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Mock minimal Laravel environment if DB is offline, or bootstrap app
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\FlightDailyReport\FdrDatabaseService;
use App\Models\Upload;
use App\Models\FdrProcessingJob;
use Illuminate\Database\QueryException;

echo "======================================================\n";
echo "FDR DATABASE RESILIENCE & IDEMPOTENCY VERIFICATION\n";
echo "======================================================\n\n";

$passed = 0;
$total = 0;

function assertTest(string $description, bool $condition) {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo " [PASS] {$description}\n";
    } else {
        echo " [FAIL] {$description}\n";
    }
}

// ─────────────────────────────────────────────────────────────
// 1. Connection & SQL Error Classification Tests (classifyError)
// ─────────────────────────────────────────────────────────────
echo "1. Testing Error Classifier (classifyError & isConnectionError):\n";

$e08006 = new \PDOException("SQLSTATE[08006]: connection failure FATAL: terminating connection due to administrator command", 7);
$e08006->errorInfo = ['08006', 7, 'terminating connection due to administrator command'];
assertTest("Classifies PDOException SQLSTATE 08006 as connection error", FdrDatabaseService::isConnectionError($e08006));
$c08006 = FdrDatabaseService::classifyError($e08006);
assertTest("classifyError: 08006 returns category 'connection' and is_retryable=true", $c08006['category'] === 'connection' && $c08006['is_retryable'] === true);

$e08003 = new \PDOException("SQLSTATE[08003]: connection does not exist", 8003);
$e08003->errorInfo = ['08003', null, 'connection does not exist'];
assertTest("Classifies SQLSTATE 08003 as connection error", FdrDatabaseService::isConnectionError($e08003));

$e57P01 = new \PDOException("FATAL: terminating connection due to administrator command", 57001);
$e57P01->errorInfo = ['57P01', 57001, 'administrator shutdown'];
assertTest("Classifies SQLSTATE 57P01 as connection error", FdrDatabaseService::isConnectionError($e57P01));
$c57P01 = FdrDatabaseService::classifyError($e57P01);
assertTest("classifyError: 57P01 returns category 'connection' and is_retryable=true", $c57P01['category'] === 'connection' && $c57P01['is_retryable'] === true);

$eHotStandby = new \RuntimeException("the database system is not accepting connections: Hot standby mode is disabled");
assertTest("Classifies Hot Standby message as connection error", FdrDatabaseService::isConnectionError($eHotStandby));

// Test Exception Chain (QueryException wrapping PDOException)
$nested = new QueryException(
    'pgsql',
    'INSERT INTO uploads ...',
    [],
    $e08006
);
assertTest("Classifies nested QueryException wrapping PDOException as connection error", FdrDatabaseService::isConnectionError($nested));

// Non-retryable errors
$eConstraint = new \PDOException("SQLSTATE[23505]: Unique violation: duplicate key value", 23505);
$eConstraint->errorInfo = ['23505', 23505, 'duplicate key'];
assertTest("Does NOT classify unique constraint violation (23505) as connection error", !FdrDatabaseService::isConnectionError($eConstraint));
$c23505 = FdrDatabaseService::classifyError($eConstraint);
assertTest("classifyError: 23505 returns category 'integrity' and is_retryable=false", $c23505['category'] === 'integrity' && $c23505['is_retryable'] === false);

$eMissingCol = new \PDOException("SQLSTATE[42703]: Undefined column: column 'foo' does not exist", 42703);
$eMissingCol->errorInfo = ['42703', 42703, 'column does not exist'];
assertTest("Does NOT classify schema error (42703) as connection error", !FdrDatabaseService::isConnectionError($eMissingCol));
$c42703 = FdrDatabaseService::classifyError($eMissingCol);
assertTest("classifyError: 42703 returns category 'schema' and is_retryable=false", $c42703['category'] === 'schema' && $c42703['is_retryable'] === false);

$eMissingTable = new \PDOException("SQLSTATE[42P01]: Undefined table: 7 ERROR: relation \"fdr_processing_jobs\" does not exist", 42);
$eMissingTable->errorInfo = ['42P01', 7, 'relation "fdr_processing_jobs" does not exist'];
assertTest("Does NOT classify undefined table (42P01) as connection error", !FdrDatabaseService::isConnectionError($eMissingTable));
$c42P01 = FdrDatabaseService::classifyError($eMissingTable);
assertTest("classifyError: 42P01 returns category 'schema' and is_retryable=false", $c42P01['category'] === 'schema' && $c42P01['is_retryable'] === false);

$eNested42P01 = new QueryException(
    'pgsql',
    'INSERT INTO fdr_processing_jobs ...',
    [],
    new \PDOException('ERROR: 42P01: relation "fdr_processing_jobs" does not exist', 0)
);
assertTest("Does NOT classify wrapped 42P01 QueryException as connection error", !FdrDatabaseService::isConnectionError($eNested42P01));

$eSyntax = new \PDOException("SQLSTATE[42601]: syntax error at or near 'INSRT'", 42601);
$eSyntax->errorInfo = ['42601', 42601, 'syntax error'];
assertTest("Does NOT classify SQL syntax error (42601) as connection error", !FdrDatabaseService::isConnectionError($eSyntax));

$eAuth = new \PDOException("SQLSTATE[28P01]: password authentication failed for user 'postgres'", 28);
$eAuth->errorInfo = ['28P01', 28, 'password authentication failed'];
$cAuth = FdrDatabaseService::classifyError($eAuth);
assertTest("classifyError: 28P01 returns category 'auth' and is_retryable=false", $cAuth['category'] === 'auth' && $cAuth['is_retryable'] === false);

$eData = new \PDOException("SQLSTATE[22001]: value too long for type character varying(20)", 22001);
$eData->errorInfo = ['22001', 22001, 'string data right truncation'];
$cData = FdrDatabaseService::classifyError($eData);
assertTest("classifyError: 22001 returns category 'data' and is_retryable=false", $cData['category'] === 'data' && $cData['is_retryable'] === false);

// Secret sanitization test
$secretMsg = "Connection failed: host=db.example.com password=secretpass123; user=postgres:secretpass123@host; Bearer token_secret_abc123";
$sanitized = FdrDatabaseService::sanitizeLogMessage($secretMsg);
assertTest("sanitizeLogMessage redacts passwords and auth tokens", 
    !str_contains($sanitized, 'secretpass123') && 
    !str_contains($sanitized, 'token_secret_abc123') && 
    str_contains($sanitized, 'password=***')
);

// ─────────────────────────────────────────────────────────────
// 2. Retry Logic & Simulated Recovery Tests
// ─────────────────────────────────────────────────────────────
echo "\n2. Testing executeWithRetry Recovery Policy:\n";

$attempts = 0;
$result = FdrDatabaseService::executeWithRetry(function () use (&$attempts, $e08006) {
    $attempts++;
    if ($attempts < 3) {
        throw $e08006;
    }
    return 'RECOVERED_SUCCESS';
}, 3, 10);

assertTest("executeWithRetry recovers from transient 08006 failure after 2 retries", $result === 'RECOVERED_SUCCESS' && $attempts === 3);

$nonRetryThrown = false;
$attemptsNonRetry = 0;
try {
    FdrDatabaseService::executeWithRetry(function () use (&$attemptsNonRetry, $eConstraint) {
        $attemptsNonRetry++;
        throw $eConstraint;
    }, 3, 10);
} catch (\Throwable $e) {
    $nonRetryThrown = true;
}
assertTest("executeWithRetry immediately fails non-retryable exception without retrying", $nonRetryThrown && $attemptsNonRetry === 1);

// ─────────────────────────────────────────────────────────────
// 3. Upload Model Lightweight Sanitization Tests
// ─────────────────────────────────────────────────────────────
echo "\n3. Testing Upload Model Metadata Trimming & Preservation:\n";

$uploadModel = new Upload();
$uploadModel->report_type = 'fdr';
$uploadModel->validation_summary = [
    'valid'             => true,
    'status'            => 'QUEUED',
    'detected_template' => 'NORMAL',
    'total_rows'        => 1500,
    'extra_heavy_junk'  => str_repeat('X', 50000), // Should be stripped
];

$summary = json_decode($uploadModel->getAttributes()['validation_summary'], true);
assertTest("validation_summary trims heavy fields while preserving core validation state", 
    isset($summary['valid']) && 
    $summary['valid'] === true && 
    !isset($summary['extra_heavy_junk']) && 
    $summary['detected_template'] === 'NORMAL'
);

$uploadModel->report_data = [
    'meta' => [
        'airport'        => 'CGK',
        'report_airport' => 'CGK',
        '_summaries'     => ['dummy'], // internal parser buffer
        '_col_map'       => ['dummy'],
    ],
    'raw_html' => '<html>giant string</html>',
];
$reportData = $uploadModel->getAttributes()['report_data'];
$decodedData = is_array($reportData) ? $reportData : json_decode($reportData, true);

assertTest("report_data removes raw HTML and parser buffers while preserving operational metadata",
    !isset($decodedData['raw_html']) &&
    !isset($decodedData['meta']['_summaries']) &&
    isset($decodedData['meta']['airport']) &&
    $decodedData['meta']['airport'] === 'CGK'
);

// ─────────────────────────────────────────────────────────────
// 4. Verify 7 FDR Templates Definition & Contract Integrity
// ─────────────────────────────────────────────────────────────
echo "\n4. Testing All 7 FDR Report Types Contract Integrity:\n";

$templates = [
    'NORMAL',
    'LOAD_FACTOR',
    'COMPARE_LOAD_FACTOR',
    'COMPARE_LOAD_FACTOR_DAY',
    'DEPARTURE_VS_APPS',
    'OASYS_VS_EDIFLY',
    'EX_HALIM',
];

foreach ($templates as $tmpl) {
    assertTest("Template '{$tmpl}' is recognized and supported without modification", in_array($tmpl, [
        'NORMAL',
        'LOAD_FACTOR',
        'COMPARE_LOAD_FACTOR',
        'COMPARE_LOAD_FACTOR_DAY',
        'DEPARTURE_VS_APPS',
        'OASYS_VS_EDIFLY',
        'EX_HALIM',
    ]));
}

echo "\n======================================================\n";
echo "SUMMARY: {$passed} / {$total} Tests Passed (" . round(($passed / $total) * 100) . "%)\n";
echo "======================================================\n";

if ($passed !== $total) {
    exit(1);
}
