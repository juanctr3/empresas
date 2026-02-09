<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$configs = [
    ['host' => '127.0.0.1', 'user' => 'cotice-user', 'pass' => 'JC@0020560392jc*-?'],
    ['host' => 'localhost', 'user' => 'cotice-user', 'pass' => 'JC@0020560392jc*-?'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'root_secure_password'],
];

foreach ($configs as $cfg) {
    echo "Intentando conectar a {$cfg['host']} con usuario {$cfg['user']}...\n";
    try {
        $pdo = new PDO("mysql:host={$cfg['host']};dbname=coticefacil-db;charset=utf8mb4", $cfg['user'], $cfg['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "¡ÉXITO! Conexión establecida.\n";
        
        // Execute schema changes here since we have a connection
        try {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN tareas_cliente JSON DEFAULT NULL");
            echo " - Columna 'tareas_cliente' creada.\n";
        } catch (Exception $e) { echo " - " . $e->getMessage() . "\n"; }

        try {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN conversion_automatica TINYINT(1) DEFAULT 1");
            echo " - Columna 'conversion_automatica' creada.\n";
        } catch (Exception $e) { echo " - " . $e->getMessage() . "\n"; }

        exit(0); // Stop after success
    } catch (PDOException $e) {
        echo "FALLÓ: " . $e->getMessage() . "\n";
    }
    echo "-------------------\n";
}
?>
