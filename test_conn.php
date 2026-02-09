<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
try {
    require_once 'db.php';
    echo "Conexión exitosa a la base de datos: " . getenv('DB_NAME') . "\n";
} catch (Exception $e) {
    echo "Error en el script: " . $e->getMessage() . "\n";
}
