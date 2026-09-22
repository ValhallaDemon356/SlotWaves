<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Dau\ReportTemplateRegistry;

$types = ['DAU1', 'DAU3', 'DAU4', 'DAU4A', 'DAU4B', 'DAU5'];

foreach ($types as $t) {
    $conf = ReportTemplateRegistry::find($t);
    $parser = app($conf['parser_class']);
    $file = resource_path("templates/dau/{$conf['template_filename']}");
    $res = $parser->parse($file);
    echo "=== {$t} ({$conf['template_filename']}) ===\n";
    echo "Records Count: " . count($res['records'] ?? []) . "\n";
    echo "Columns: " . json_encode($res['columns'] ?? []) . "\n";
    if (!empty($res['records'])) {
        echo "Sample Record [0]: " . json_encode($res['records'][0], JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}
