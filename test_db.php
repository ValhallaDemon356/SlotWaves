<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=slotwaves', 'root', '', [
        PDO::ATTR_TIMEOUT => 3,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "MYSQL CONNECTED!\n";
    echo "Uploads: " . $pdo->query('SELECT count(*) FROM uploads')->fetchColumn() . "\n";
    echo "Airports: " . $pdo->query('SELECT count(*) FROM airports')->fetchColumn() . "\n";
    echo "Airlines: " . $pdo->query('SELECT count(*) FROM airlines')->fetchColumn() . "\n";
    echo "Flights: " . $pdo->query('SELECT count(*) FROM flights')->fetchColumn() . "\n";
    echo "Timeline Positions: " . $pdo->query('SELECT count(*) FROM timeline_positions')->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "MYSQL ERROR: " . $e->getMessage() . "\n";
}
