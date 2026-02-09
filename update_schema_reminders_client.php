<?php
require_once 'db.php';

try {
    echo "Actualizando tabla cotizacion_recordatorios para soporte de Clientes...\n";
    
    // 1. Add cliente_id column
    try {
        $pdo->exec("ALTER TABLE cotizacion_recordatorios ADD COLUMN cliente_id INT NULL AFTER cotizacion_id");
        echo "- Columna cliente_id agregada.\n";
    } catch (PDOException $e) { echo "- Columna cliente_id ya existe o error: " . $e->getMessage() . "\n"; }

    // 2. Modify cotizacion_id to be nullable
    try {
        $pdo->exec("ALTER TABLE cotizacion_recordatorios MODIFY COLUMN cotizacion_id INT NULL");
        echo "- Columna cotizacion_id ahora permite NULL.\n";
    } catch (PDOException $e) { echo "- Error modificando cotizacion_id: " . $e->getMessage() . "\n"; }

    // 3. Populate cliente_id for existing records (based on cotizacion_id)
    try {
        $pdo->exec("UPDATE cotizacion_recordatorios r 
                    JOIN cotizaciones c ON r.cotizacion_id = c.id 
                    SET r.cliente_id = c.cliente_id 
                    WHERE r.cliente_id IS NULL AND r.cotizacion_id IS NOT NULL");
        echo "- Registros existentes actualizados con cliente_id.\n";
    } catch (PDOException $e) { echo "- Error actualizando datos: " . $e->getMessage() . "\n"; }

    echo "Actualización completada.\n";

} catch (PDOException $e) {
    echo "Error General: " . $e->getMessage() . "\n";
}
?>
