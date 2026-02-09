<?php
// fix_migration_local.php
require_once 'db.php';

echo "Intentando crear tablas...\n";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS wa_configuracion_general (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT,
        api_url VARCHAR(255),
        api_key VARCHAR(255),
        webhook_url VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    
    echo "✅ Tabla wa_configuracion_general creada/verificada.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
