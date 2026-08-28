<?php

require __DIR__ . '/vendor/autoload.php';

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile('C:/Users/Axioo Pongo/Downloads/Database Bandara & Maskapai.pdf');
$pages = $pdf->getPages();

foreach ($pages as $i => $page) {
    echo "=== PAGE " . ($i + 1) . " ===\n";
    echo $page->getText() . "\n\n";
}
