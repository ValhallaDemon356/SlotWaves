<?php

require __DIR__ . '/vendor/autoload.php';

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile('C:/Users/Axioo Pongo/Downloads/Database Bandara & Maskapai.pdf');
$pages = $pdf->getPages();

foreach ([1, 2] as $pIdx) {
    echo "==================== PAGE " . ($pIdx + 1) . " ====================\n";
    $page = $pages[$pIdx];
    $dataTm = $page->getDataTm();
    
    // Group characters into words based on distance
    $words = [];
    $currentWord = null;
    
    foreach ($dataTm as $item) {
        $tm = $item[0];
        $text = $item[1];
        if (trim($text) === '' || $text === "​") continue;
        $x = round($tm[4], 1);
        $y = round($tm[5], 1);
        
        $words[] = ['x' => $x, 'y' => $y, 'text' => $text];
    }
    
    // Group by lines (y coordinate)
    $lines = [];
    foreach ($words as $w) {
        $foundY = null;
        foreach (array_keys($lines) as $ly) {
            if (abs($ly - $w['y']) <= 5) {
                $foundY = $ly;
                break;
            }
        }
        if ($foundY === null) {
            $foundY = $w['y'];
            $lines[$foundY] = [];
        }
        $lines[$foundY][] = $w;
    }
    ksort($lines);
    
    foreach ($lines as $y => $wList) {
        usort($wList, fn($a, $b) => $a['x'] <=> $b['x']);
        // Separate columns: Column 1 (Region / Management): x < 130
        // Column 2 (Internasional / Main column 1): 130 <= x < 340
        // Column 3 (Domestik / Main column 2): x >= 340
        $col1 = [];
        $col2 = [];
        $col3 = [];
        foreach ($wList as $w) {
            if ($w['x'] < 130) {
                $col1[] = $w['text'];
            } elseif ($w['x'] < 340) {
                $col2[] = $w['text'];
            } else {
                $col3[] = $w['text'];
            }
        }
        $t1 = implode('', $col1);
        $t2 = implode('', $col2);
        $t3 = implode('', $col3);
        printf("y=%4.0f | Col1: %-15s | Col2 (Intl): %-35s | Col3 (Dom): %-35s\n", $y, $t1, $t2, $t3);
    }
}
