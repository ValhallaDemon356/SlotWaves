<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use Smalot\PdfParser\Parser as SmalotParser;
use Illuminate\Support\Facades\Storage;

$u = Upload::find(47);
$path = Storage::disk('local')->path($u->stored_path);

$smalot = new SmalotParser();
$pdf = $smalot->parseFile($path);
$pages = $pdf->getPages();

echo "PDF: {$u->original_filename}\n";
echo "Total Pages: " . count($pages) . "\n";

foreach ($pages as $pIndex => $page) {
    echo "==================================================\n";
    echo "PAGE " . ($pIndex + 1) . "\n";
    echo "==================================================\n";
    $text = $page->getText();
    $lines = preg_split('/\r?\n/', $text);
    foreach ($lines as $lIndex => $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') {
            echo "[L{$lIndex}] {$trimmed}\n";
        }
    }
}
