<?php
// Standalone migration script to avoid db.php inclusion issues in CLI
$host = '127.0.0.1';
$db   = 'coticefacil-db';
$user = 'cotice-user';
$pass = 'JC@0020560392jc*-?';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conexión exitosa. Verificando columnas...\n";

    // 1. Add tareas_cliente (JSON)
    try {
        $pdo->query("SELECT tareas_cliente FROM cotizaciones LIMIT 1");
        echo " - Columna 'tareas_cliente' ya existe.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN tareas_cliente JSON DEFAULT NULL");
        echo " - Columna 'tareas_cliente' creada exitosamente.\n";
    }

    // 2. Add conversion_automatica (TINYINT)
    try {
        $pdo->query("SELECT conversion_automatica FROM cotizaciones LIMIT 1");
        echo " - Columna 'conversion_automatica' ya existe.\n";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN conversion_automatica TINYINT(1) DEFAULT 1");
        echo " - Columna 'conversion_automatica' creada exitosamente.\n";
    }

} catch (PDOException $e) {
    die("Error de conexión/ejecución: " . $e->getMessage());
}
?>
