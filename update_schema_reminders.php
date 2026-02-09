<?php
require_once 'db.php';

try {
    echo "Actualizando tabla cotizacion_recordatorios...\n";
    $sql = "ALTER TABLE cotizacion_recordatorios 
            ADD COLUMN tiene_prerecordatorio TINYINT(1) DEFAULT 0,
            ADD COLUMN dias_antes INT DEFAULT 1,
            ADD COLUMN mensaje_prerecordatorio TEXT NULL,
            ADD COLUMN prerecordatorio_enviado TINYINT(1) DEFAULT 0";
    $pdo->exec($sql);
    echo "Tabla actualizada correctamente.\n";
} catch (PDOException $e) {
    echo "Nota: " . $e->getMessage() . "\n";
}
?>
