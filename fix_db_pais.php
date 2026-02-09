<?php
require_once 'db.php';

try {
    // Verificar si 'pais_codigo' existe en 'clientes'
    $columnas = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'pais_codigo'")->fetchAll();
    if (empty($columnas)) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN pais_codigo VARCHAR(10) DEFAULT '57' AFTER identificacion");
        echo "✅ Columna 'pais_codigo' agregada con éxito.<br>";
    } else {
        echo "ℹ️ La columna 'pais_codigo' ya existe.<br>";
    }
    
    echo "<br><a href='clientes.php'>Volver a Clientes</a>";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
