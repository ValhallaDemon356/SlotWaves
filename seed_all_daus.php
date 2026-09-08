<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Airport;
use App\Models\Upload;
use App\Services\Dau\Parsers\DAU1Parser;
use App\Services\Dau\Parsers\DAU2Parser;
use App\Services\Dau\Parsers\DAU3Parser;
use App\Services\Dau\Parsers\DAU4Parser;
use App\Services\Dau\Parsers\DAU4AParser;
use App\Services\Dau\Parsers\DAU4BParser;
use App\Services\Dau\Parsers\DAU5Parser;
use App\Services\Dau\Parsers\DAU5AParser;
use App\Services\Dau\Parsers\DAU5BParser;
use App\Services\Dau\Parsers\DAU5CParser;
use App\Services\Dau\Parsers\DAU6Parser;
use App\Services\Dau\Parsers\DAU10Parser;
use App\Services\Dau\Parsers\DAU10AParser;
use App\Services\Dau\Parsers\DAU10BParser;
use App\Services\Dau\Parsers\DAU11Parser;
use App\Services\Dau\Parsers\DAU12Parser;

$airport = Airport::firstOrCreate(
    ['iata_code' => 'CGK'],
    [
        'name'    => 'Soekarno Hatta',
        'city'    => 'Tangerang',
        'country' => 'Indonesia',
    ]
);

$parsers = [
    'DAU1'   => [new DAU1Parser(), 'DAU-1.xls'],
    'DAU2'   => [new DAU2Parser(), 'DAU-2.xls'],
    'DAU3'   => [new DAU3Parser(), 'DAU-3.xls'],
    'DAU4'   => [new DAU4Parser(), 'DAU-4.xls'],
    'DAU4A'  => [new DAU4AParser(), 'DAU-4A.xls'],
    'DAU4B'  => [new DAU4BParser(), 'DAU-4B.xls'],
    'DAU5'   => [new DAU5Parser(), 'DAU-5.xls'],
    'DAU5A'  => [new DAU5AParser(), 'DAU-5A.xls'],
    'DAU5B'  => [new DAU5BParser(), 'DAU-5B.xls'],
    'DAU5C'  => [new DAU5CParser(), 'DAU-5C.xls'],
    'DAU6'   => [new DAU6Parser(), 'DAU-6.xls'],
    'DAU10'  => [new DAU10Parser(), 'DAU-10.xls'],
    'DAU10A' => [new DAU10AParser(), 'DAU-10A.xls'],
    'DAU10B' => [new DAU10BParser(), 'DAU-10B.xls'],
    'DAU11'  => [new DAU11Parser(), 'DAU-11.xls'],
    'DAU12'  => [new DAU12Parser(), 'DAU-12.xls'],
];

$results = [];

foreach ($parsers as $type => [$parser, $file]) {
    $existing = Upload::where('report_type', $type)->where('status', 'completed')->latest()->first();
    if ($existing) {
        $results[$type] = $existing->id;
        continue;
    }

    $filePath = base_path("resources/templates/dau/{$file}");
    if (!file_exists($filePath)) {
        echo "File not found: {$filePath}\n";
        continue;
    }

    $parsed = $parser->parse($filePath);
    $upload = Upload::create([
        'original_filename' => $file,
        'stored_path'       => "uploads/{$file}",
        'airport_iata'      => 'CGK',
        'airport_id'        => $airport->id,
        'report_type'       => $type,
        'status'            => 'completed',
        'season'            => 'summer',
        'report_data'       => $parsed,
        'source_type'       => 'excel',
        'parser_metadata'   => $parsed,
    ]);

    $results[$type] = $upload->id;
}

echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
