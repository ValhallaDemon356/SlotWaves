<?php

require __DIR__ . '/vendor/autoload.php';

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile('C:/Users/Axioo Pongo/Downloads/Database Bandara & Maskapai.pdf');

$pages = $pdf->getPages();

foreach ([1, 2] as $pageIndex) {
    $page = $pages[$pageIndex];
    echo "\n==================== PAGE " . ($pageIndex + 1) . " ====================\n";
    $dataTm = $page->getDataTm();
    
    // Group text items by y-coordinate (or close y within 3-4 units)
    $lines = [];
    foreach ($dataTm as $item) {
        $tm = $item[0];
        $text = trim($item[1]);
        if ($text === '') continue;
        $x = round($tm[4], 1);
        $y = round($tm[5], 1);
        
        $matchedY = null;
        foreach (array_keys($lines) as $existingY) {
            if (abs($existingY - $y) <= 4) {
                $matchedY = $existingY;
                break;
            }
        }
        if ($matchedY === null) {
            $matchedY = $y;
            $lines[$matchedY] = [];
        }
        $lines[$matchedY][] = ['x' => $x, 'text' => $text];
    }
    
    ksort($lines); // sort by y
    foreach ($lines as $y => $items) {
        usort($items, fn($a, $b) => $a['x'] <=> $b['x']);
        $str = "";
        foreach ($items as $it) {
            $str .= sprintf("[x=%0.1f: %s] ", $it['x'], $it['text']);
        }
        printf("y=%6.1f | %s\n", $y, $str);
    }
}
