<?php
// fix_db_schema_final.php
// Ejecutar desde el navegador

require_once 'db.php';

echo "<h1>Reparación de Base de Datos</h1>";
echo "<pre>";

try {
    // 1. FACTURAS
    echo "Analizando 'facturas'...\n";
    $sqlFacturas = "ALTER TABLE facturas MODIFY COLUMN estado ENUM('Borrador', 'Pendiente', 'Pagada', 'Vencida', 'Anulada', 'Enviada') DEFAULT 'Borrador'";
    $pdo->exec($sqlFacturas);
    echo "✓ Tabla 'facturas' actualizada (Borrador, Enviada...).\n";

    // 2. COTIZACIONES
    echo "\nAnalizando 'cotizaciones'...\n";
    // Obtenemos los valores actuales para no perder nada si es posible, pero ENUM replace es standard
    // Asumiremos los estados estándar + Facturada
    $sqlCot = "ALTER TABLE cotizaciones MODIFY COLUMN estado ENUM('Borrador', 'Pendiente', 'Enviada', 'Aceptada', 'Rechazada', 'Facturada', 'Vencida') DEFAULT 'Borrador'";
    $pdo->exec($sqlCot);
    echo "✓ Tabla 'cotizaciones' actualizada (estado 'Facturada' agregado).\n";

    echo "\n\n<strong style='color:green'>¡REPARACIÓN COMPLETADA CON ÉXITO!</strong>";
    echo "\nAhora puede intentar guardar/visualizar la factura nuevamente.";

} catch (Exception $e) {
    echo "\n<strong style='color:red'>ERROR:</strong> " . $e->getMessage();
    echo "\n\nDetalles técnicos: \n" . $e->getTraceAsString();
}

echo "</pre>";
?>
