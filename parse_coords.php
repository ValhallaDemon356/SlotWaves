<?php

require __DIR__ . '/vendor/autoload.php';

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile('C:/Users/Axioo Pongo/Downloads/Database Bandara & Maskapai.pdf');

foreach ($pdf->getPages() as $i => $page) {
    echo "==================== PAGE " . ($i + 1) . " ====================\n";
    $dataTm = $page->getDataTm();
    foreach ($dataTm as $item) {
        $tm = $item[0];
        $text = trim($item[1]);
        if ($text !== '') {
            printf("x: %6.1f, y: %6.1f | %s\n", $tm[4], $tm[5], $text);
        }
    }
}
