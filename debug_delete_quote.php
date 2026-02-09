<?php
// debug_delete_quote.php
// Script de diagnóstico para borrar cotizaciones y ver errores reales
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$id = $_GET['id'] ?? 0;

echo "<h1>Herramienta de Diagnóstico de Borrado</h1>";

if (!$id) {
    echo "<p>Por favor, usa este script poniendo el ID de la cotización en la URL.</p>";
    echo "<p>Ejemplo: <code>debug_delete_quote.php?id=123</code></p>";
    exit;
}

echo "Intentando borrar Cotización ID: <strong>$id</strong><br><hr>";

try {
    // 1. Verificar si existe
    $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ?");
    $stmt->execute([$id]);
    $cot = $stmt->fetch();

    if (!$cot) {
        die("<h3 style='color:red'>Error: La cotización ID $id no existe en la base de datos.</h3>");
    }

    echo "✅ Cotización encontrada.<br>";
    echo "Estado: " . $cot['estado'] . "<br>";
    echo "Empresa ID: " . $cot['empresa_id'] . "<br><hr>";

    $pdo->beginTransaction();

    // 2. Lista de tablas a limpiar
    $tables_to_clean = [
        'cotizacion_adjuntos' => 'DELETE FROM cotizacion_adjuntos WHERE cotizacion_id = ?',
        'cotizacion_detalles' => 'DELETE FROM cotizacion_detalles WHERE cotizacion_id = ?',
        'cotizacion_historial' => 'DELETE FROM cotizacion_historial WHERE cotizacion_id = ?',
        'cotizacion_vistas' => 'DELETE FROM cotizacion_vistas WHERE cotizacion_id = ?',
        'notificaciones' => 'DELETE FROM notificaciones WHERE cotizacion_id = ?',
        'certificados' => 'UPDATE certificados SET cotizacion_id = NULL WHERE cotizacion_id = ?', // Desvincular
        'ordenes_trabajo' => 'UPDATE ordenes_trabajo SET cotizacion_id = NULL WHERE cotizacion_id = ?' // Desvincular
    ];

    foreach ($tables_to_clean as $table => $sql) {
        echo "Procesando tabla: <strong>$table</strong>... ";
        try {
            // Verificar si tabla existe primero
            $check = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($check->rowCount() > 0) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                echo "<span style='color:green'>OK</span> (Filas afectadas: " . $stmt->rowCount() . ")<br>";
            } else {
                echo "<span style='color:orange'>La tabla no existe, saltando.</span><br>";
            }
        } catch (Exception $e) {
            echo "<span style='color:red'>ERROR: " . $e->getMessage() . "</span><br>";
        }
    }

    // 3. Borrar Cotización Final
    echo "<hr>Intentando borrar registro de <strong>cotizaciones</strong>... ";
    $stmt = $pdo->prepare("DELETE FROM cotizaciones WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo "<h2 style='color:green'>✅ ¡BORRADO EXITOSO!</h2>";
        $pdo->commit();
        echo "<p>La cotización ha sido eliminada correctamente.</p>";
    } else {
        echo "<h2 style='color:red'>❌ FALLÓ EL BORRADO FINAL</h2>";
        echo "<p>No se eliminó ninguna fila. Tal vez ya no existía.</p>";
        $pdo->rollBack();
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1 style='color:red'>ERROR CRÍTICO (Transacción Revertida)</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<hr>";
    echo "<h3>Traza:</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
