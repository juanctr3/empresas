<?php
// fix_db_update_v2.php
// Ejecutar desde el navegador

require_once 'db.php';

echo "<h1>Actualización de Base de Datos v2</h1>";
echo "<pre>";

try {
    // 1. Corregir DOCUMENTOS (Agregar cliente_id y usuario_id)
    echo "Analizando tabla 'documentos'...\n";
    $stm = $pdo->query("SHOW COLUMNS FROM documentos LIKE 'cliente_id'");
    if (!$stm->fetch()) {
        $pdo->exec("ALTER TABLE documentos ADD COLUMN cliente_id INT NULL AFTER empresa_id, ADD INDEX(cliente_id)");
        echo "✓ Columna 'cliente_id' agregada.\n";
    }

    $stmU = $pdo->query("SHOW COLUMNS FROM documentos LIKE 'usuario_id'");
    if (!$stmU->fetch()) {
        $pdo->exec("ALTER TABLE documentos ADD COLUMN usuario_id INT NULL AFTER cliente_id, ADD INDEX(usuario_id)");
        echo "✓ Columna 'usuario_id' agregada.\n";
    } else {
        // Asegurar que sea NULLABLE
        $pdo->exec("ALTER TABLE documentos MODIFY COLUMN usuario_id INT NULL");
        echo "✓ Columna 'usuario_id' actualizada a NULLABLE.\n";
    }

    // 2. Corregir FACTURAS (Estados solicitados)
    echo "\nActualizando estados de 'facturas'...\n";
    // Estados: Creada, Enviada, Pendiente de pago, Borrador, Pagada, Anulada, Vencida
    $sqlFacturas = "ALTER TABLE facturas MODIFY COLUMN estado ENUM('Borrador', 'Creada', 'Enviada', 'Pendiente de pago', 'Pagada', 'Anulada', 'Vencida') DEFAULT 'Borrador'";
    $pdo->exec($sqlFacturas);
    echo "✓ Estados de facturas actualizados.\n";

    echo "\n\n<strong style='color:green'>¡ACTUALIZACIÓN COMPLETADA!</strong>";
    echo "\n1. Intente subir el documento nuevamente.";
    echo "\n2. Verifique los estados de la factura.";

} catch (Exception $e) {
    echo "\n<strong style='color:red'>ERROR:</strong> " . $e->getMessage();
}
echo "</pre>";
?>
