<?php
require_once 'db.php';

try {
    echo "Iniciando migración de base de datos...\n";

    // 1. Agregar titulo_cotizacion a cotizaciones
    try {
        $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN titulo_cotizacion VARCHAR(255) NULL AFTER numero_cotizacion");
        echo "✅ Columna 'titulo_cotizacion' agregada a tabla 'cotizaciones'.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ La columna 'titulo_cotizacion' ya existe.\n";
        } else {
            echo "❌ Error agregando 'titulo_cotizacion': " . $e->getMessage() . "\n";
        }
    }

    // 2. Agregar seccion a cotizacion_detalles
    try {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN seccion VARCHAR(100) DEFAULT 'General' AFTER cotizacion_id");
        echo "✅ Columna 'seccion' agregada a tabla 'cotizacion_detalles'.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ La columna 'seccion' ya existe.\n";
        } else {
            echo "❌ Error agregando 'seccion': " . $e->getMessage() . "\n";
        }
    }

    echo "Migración completada.";

} catch (Exception $e) {
    echo "Error general: " . $e->getMessage();
}
?>
