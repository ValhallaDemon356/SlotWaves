<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$uploads = App\Models\Upload::all();
foreach ($uploads as $u) {
    echo "Upload ID: {$u->id}, Filename: {$u->original_filename}, Flights: " . $u->flights()->count() . "\n";
}
if ($uploads->isEmpty()) {
    echo "No uploads in DB! Creating one from sample...\n";
    $samplePath = base_path('tests/fixtures/sample_schedule.pdf');
    if (!file_exists($samplePath)) {
        // Find existing pdfs
        $files = glob(storage_path('app/uploads/*.pdf')) ?: glob(base_path('*.pdf'));
        echo "Found files: " . json_encode($files) . "\n";
    }
}
