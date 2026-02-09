<?php
require_once 'db.php';

try {
    // Add social media columns to companies table
    $columns = [
        'social_facebook' => 'VARCHAR(255) DEFAULT NULL',
        'social_instagram' => 'VARCHAR(255) DEFAULT NULL',
        'social_linkedin' => 'VARCHAR(255) DEFAULT NULL',
        'social_twitter' => 'VARCHAR(255) DEFAULT NULL',
        'social_website' => 'VARCHAR(255) DEFAULT NULL'
    ];

    foreach ($columns as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN $col $def");
            echo "Columna $col agregada.\n";
        } catch (PDOException $e) {
            // Ignore if exists
             echo "Columna $col ya existe o error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Actualización de base de datos completada.";

} catch (PDOException $e) {
    die("Error actualizando BD: " . $e->getMessage());
}
?>
