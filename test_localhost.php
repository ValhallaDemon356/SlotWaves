<?php

$s = microtime(true);
try {
    $pdo = new PDO('mysql:host=localhost;dbname=slotwaves;charset=utf8mb4', 'root', '', [
        PDO::ATTR_TIMEOUT => 2,
    ]);
    echo "CONNECTED TO localhost in " . round((microtime(true)-$s)*1000, 1) . "ms\n";
    $count = $pdo->query('SELECT COUNT(*) FROM airports')->fetchColumn();
    echo "Airports count: $count in " . round((microtime(true)-$s)*1000, 1) . "ms\n";
} catch (Exception $e) {
    echo "ERROR with localhost: " . $e->getMessage() . " (" . round((microtime(true)-$s)*1000, 1) . "ms)\n";
}
