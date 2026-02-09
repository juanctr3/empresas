<?php
require_once 'db.php';
try {
    // Check current state
    $stmt = $pdo->query("SHOW COLUMNS FROM facturas LIKE 'estado'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Current Type: " . $col['Type'] . "\n";
    
    // Fix it
    $sql = "ALTER TABLE facturas MODIFY COLUMN estado ENUM('Borrador', 'Pendiente', 'Pagada', 'Vencida', 'Anulada', 'Enviada') DEFAULT 'Borrador'";
    $pdo->exec($sql);
    
    echo "Schema Updated Successfully.\n";
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
