<?php
require_once 'db.php';

echo "<h1>Migración v2: Módulo de Archivos y Documentos</h1>";

try {
    $sql = file_get_contents('saas_migration_v2.sql');
    
    // Ejecutar por partes ya que PDO->exec no maneja múltiples sentencias de forma confiable en todos los entornos
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    echo "✅ Migración de base de datos completada con éxito.<br>";
    echo "✅ Tablas: 'documentos', 'documentos_compartidos', 'documentos_logs' creadas.<br>";
    echo "✅ Límites de almacenamiento agregados a los planes.<br>";
    
    echo "<br><a href='index.php' style='display:inline-block; margin-top:20px; padding:12px 24px; background:#4f46e5; color:white; border-radius:15px; text-decoration:none; font-weight:bold;'>Volver al Panel</a>";

} catch (PDOException $e) {
    echo "❌ Error en la migración: " . $e->getMessage();
}
?>
