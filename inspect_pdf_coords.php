<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use Smalot\PdfParser\Parser as SmalotParser;
use Illuminate\Support\Facades\Storage;

function inspectPdfCoordinates($uploadId) {
    $u = Upload::find($uploadId);
    $path = Storage::disk('local')->path($u->stored_path);
    echo "============================================================\n";
    echo "INSPECTING UPLOAD ID {$u->id}: {$u->original_filename}\n";
    echo "============================================================\n";

    $parser = new SmalotParser();
    $pdf = $parser->parseFile($path);
    $pages = $pdf->getPages();

    foreach ($pages as $pIdx => $page) {
        echo "PAGE " . ($pIdx + 1) . ":\n";
        $dataTm = $page->getDataTm();
        echo "Total Text Elements: " . count($dataTm) . "\n";
        // Print first 40 text elements with X, Y coordinates
        foreach (array_slice($dataTm, 0, 40) as $tm) {
            $text = trim($tm[1]);
            if ($text !== '') {
                $matrix = $tm[0];
                $x = round($matrix[4], 1);
                $y = round($matrix[5], 1);
                echo "  [X: {$x}, Y: {$y}] '{$text}'\n";
            }
        }
    }
}

inspectPdfCoordinates(47); // Fligt Sched BDO Agustus 2026 Baru.pdf
inspectPdfCoordinates(46); // FLIGHT SCHEDULE SUMMER 2018.pdf
