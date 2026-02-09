<?php
// Hardcoded credentials for CLI debug
$db_host = '127.0.0.1';
$db_name = 'coticefacil-db';
$db_user = 'cotice-user';
$db_pass = 'JC@0020560392jc*-?';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "--- FACTURAS ---\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM facturas LIKE 'estado'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Type: " . ($col['Type'] ?? 'Unknown') . "\n";

    echo "\n--- COTIZACIONES ---\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM cotizaciones LIKE 'estado'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Type: " . ($col['Type'] ?? 'Unknown') . "\n";

} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
