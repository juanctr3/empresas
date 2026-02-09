<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=coticefacil-db;charset=utf8mb4', 'cotice-user', 'cotice_temp_123');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$sql = file_get_contents('saas_migration_v1.sql');

try {
    $pdo->exec($sql);
    echo "Migration successful!\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
