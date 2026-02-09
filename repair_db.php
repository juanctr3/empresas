<?php
require_once 'db.php';

try {
    echo "--- Reparando base de datos ---\n";
    
    // 1. Cotizaciones
    $stmt = $pdo->query("DESCRIBE cotizaciones");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('updated_at', $cols)) {
        $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "✅ Columna 'updated_at' añadida a 'cotizaciones'.\n";
    }
    
    if (!in_array('mostrar_cantidad_como', $cols)) {
        $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN mostrar_cantidad_como VARCHAR(20) DEFAULT 'unidad'");
        echo "✅ Columna 'mostrar_cantidad_como' añadida a 'cotizaciones'.\n";
    }

    if (!in_array('impuesto_total', $cols)) {
        // Renombrar impuestos a impuesto_total si existe impuestos
        if (in_array('impuestos', $cols)) {
            $pdo->exec("ALTER TABLE cotizaciones CHANGE COLUMN impuestos impuesto_total DECIMAL(15,2) DEFAULT 0");
            echo "✅ Columna 'impuestos' renombrada a 'impuesto_total'.\n";
        } else {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN impuesto_total DECIMAL(15,2) DEFAULT 0 AFTER subtotal");
            echo "✅ Columna 'impuesto_total' añadida.\n";
        }
    }

    // 2. Cotizacion Detalles
    $stmt = $pdo->query("DESCRIBE cotizacion_detalles");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('unidad_nombre', $cols)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN unidad_nombre VARCHAR(50) AFTER cantidad");
        echo "✅ Columna 'unidad_nombre' añadida a 'cotizacion_detalles'.\n";
    }
    if (!in_array('imagen', $cols)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN imagen VARCHAR(255) AFTER nombre_producto");
        echo "✅ Columna 'imagen' añadida.\n";
    }
    if (!in_array('descripcion', $cols)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN descripcion TEXT AFTER imagen");
        echo "✅ Columna 'descripcion' añadida.\n";
    }
    if (!in_array('es_opcional', $cols)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN es_opcional TINYINT(1) DEFAULT 0");
        echo "✅ Columna 'es_opcional' añadida.\n";
    }
    if (!in_array('seleccionado', $cols)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN seleccionado TINYINT(1) DEFAULT 1");
        echo "✅ Columna 'seleccionado' añadida.\n";
    }

    echo "--- Sincronización completa ---\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
