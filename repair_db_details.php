<?php
require_once 'db.php';
try {
    $stmt = $pdo->query("DESCRIBE cotizacion_detalles");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('unidad_nombre', $cols)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN unidad_nombre VARCHAR(50) AFTER cantidad");
        echo "✅ Column 'unidad_nombre' added.\n";
    } else {
        echo "ℹ️ Column 'unidad_nombre' already exists.\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
