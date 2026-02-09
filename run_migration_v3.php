<?php
require_once 'db.php';

echo "<h1>Migración v3: Configuración de Almacenamiento Dinámico</h1>";

try {
    $sql = file_get_contents('saas_migration_v3.sql');
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    echo "✅ Migración de base de datos v3 completada con éxito.<br>";
    echo "✅ Columnas de almacenamiento agregadas a 'empresas'.<br>";
    echo "<br><a href='configuracion.php' style='display:inline-block; margin-top:20px; padding:12px 24px; background:#4f46e5; color:white; border-radius:15px; text-decoration:none; font-weight:bold;'>Ir a Configuración</a>";

} catch (PDOException $e) {
    echo "❌ Error en la migración: " . $e->getMessage();
}
?>
