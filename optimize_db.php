<?php
/**
 * Database Index Optimizer - CoticeFacil.com
 */

require_once 'db.php';

$tables = [
    'cotizaciones', 'clientes', 'productos', 'usuarios', 
    'ordenes_trabajo', 'certificados', 'alertas', 
    'wa_chats', 'plantillas', 'roles'
];

echo "Iniciando optimización de índices...\n";

foreach ($tables as $table) {
    try {
        // Verificar si el índice ya existe
        $stmt = $pdo->prepare("SHOW INDEX FROM $table WHERE Key_name = 'idx_empresa_id'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            echo "Agregando índice idx_empresa_id a $table...\n";
            $pdo->exec("ALTER TABLE $table ADD INDEX idx_empresa_id (empresa_id)");
            echo "Listo.\n";
        } else {
            echo "Índice ya existe en $table.\n";
        }
    } catch (Exception $e) {
        echo "Error en $table: " . $e->getMessage() . "\n";
    }
}

echo "Optimización completada.\n";
unlink(__FILE__); // Auto-eliminar script
