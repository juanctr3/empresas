<?php
require_once 'db.php';

try {
    $stmt = $pdo->query("DESCRIBE cotizacion_detalles");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('descripcion', $columns)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN descripcion TEXT AFTER imagen");
        echo "✅ Columna 'descripcion' añadida.\n";
    }
    if (!in_array('es_opcional', $columns)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN es_opcional TINYINT(1) DEFAULT 0");
        echo "✅ Columna 'es_opcional' añadida.\n";
    }
    if (!in_array('seleccionado', $columns)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN seleccionado TINYINT(1) DEFAULT 1");
        echo "✅ Columna 'seleccionado' añadida.\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
