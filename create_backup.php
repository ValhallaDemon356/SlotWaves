<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$backupDir = __DIR__ . '/database/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$backupFile = $backupDir . '/slotwaves_backup_pre_normalization.sql';
$handle = fopen($backupFile, 'w');

fwrite($handle, "-- SlotWaves Database Backup Pre-Normalization\n");
fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = DB::select("SHOW TABLES");
$dbName = DB::connection()->getDatabaseName();
$colName = "Tables_in_" . $dbName;

foreach ($tables as $t) {
    $table = $t->$colName;
    
    // Structure
    $createTable = DB::select("SHOW CREATE TABLE `{$table}`");
    $createSql = $createTable[0]->{'Create Table'};
    fwrite($handle, "-- ----------------------------\n");
    fwrite($handle, "-- Table structure for {$table}\n");
    fwrite($handle, "-- ----------------------------\n");
    fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($handle, $createSql . ";\n\n");
    
    // Data
    $rows = DB::table($table)->get();
    if ($rows->count() > 0) {
        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "-- Records of {$table} (count: {$rows->count()})\n");
        fwrite($handle, "-- ----------------------------\n");
        
        $chunks = $rows->chunk(100);
        foreach ($chunks as $chunk) {
            $insertParts = [];
            foreach ($chunk as $row) {
                $values = [];
                foreach ((array)$row as $val) {
                    if ($val === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . addslashes((string)$val) . "'";
                    }
                }
                $insertParts[] = "(" . implode(", ", $values) . ")";
            }
            if (!empty($insertParts)) {
                $colNames = array_map(fn($c) => "`{$c}`", array_keys((array)$chunk->first()));
                $sql = "INSERT INTO `{$table}` (" . implode(", ", $colNames) . ") VALUES \n" . implode(",\n", $insertParts) . ";\n";
                fwrite($handle, $sql);
            }
        }
        fwrite($handle, "\n");
    }
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);

echo "Backup created successfully at: " . $backupFile . "\n";
echo "File size: " . round(filesize($backupFile) / 1024, 2) . " KB\n";
