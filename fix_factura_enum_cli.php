<?php
// Hardcoded credentials for CLI fix
$db_host = '127.0.0.1';
$db_name = 'coticefacil-db';
$db_user = 'cotice-user';
$db_pass = 'JC@0020560392jc*-?';

try {
    echo "Connecting to $db_host::$db_name...\n";
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check current state
    $stmt = $pdo->query("SHOW COLUMNS FROM facturas LIKE 'estado'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Current Type for 'estado': " . ($col['Type'] ?? 'Unknown') . "\n";
    
    // Fix it
    echo "Attempting ALERT TABLE...\n";
    $sql = "ALTER TABLE facturas MODIFY COLUMN estado ENUM('Borrador', 'Pendiente', 'Pagada', 'Vencida', 'Anulada', 'Enviada') DEFAULT 'Borrador'";
    $pdo->exec($sql);
    
    echo "Schema Updated Successfully.\n";
    
     // Verify
    $stmt = $pdo->query("SHOW COLUMNS FROM facturas LIKE 'estado'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "New Type for 'estado': " . ($col['Type'] ?? 'Unknown') . "\n";
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
