<?php
$html = file_get_contents('http://127.0.0.1:8000/dau/compare?reports[]=195&reports[]=196');
echo "HTML Length: " . strlen($html) . PHP_EOL;
echo "Has BASELINE COMPARISON: " . (str_contains($html, 'BASELINE COMPARISON') ? 'YES' : 'NO') . PHP_EOL;
echo "Has chart-trend-passenger: " . (str_contains($html, 'chart-trend-passenger') ? 'YES' : 'NO') . PHP_EOL;
echo "Has chart-hist-passenger: " . (str_contains($html, 'chart-hist-passenger') ? 'YES' : 'NO') . PHP_EOL;

$pdf = file_get_contents('http://127.0.0.1:8000/dau/compare/export/pdf?reports[]=195&reports[]=196');
echo "PDF Size: " . strlen($pdf) . " bytes | Starts with %PDF: " . (str_starts_with($pdf, '%PDF') ? 'YES' : 'NO') . PHP_EOL;

$csv = file_get_contents('http://127.0.0.1:8000/dau/compare/export/csv?reports[]=195&reports[]=196');
echo "CSV Size: " . strlen($csv) . " bytes | Starts with # SLOTWAVES: " . (str_contains($csv, 'SLOTWAVES') ? 'YES' : 'NO') . PHP_EOL;
