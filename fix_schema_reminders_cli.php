<?php
$attempts = [
    ['host' => '127.0.0.1', 'user' => 'cotice-user', 'pass' => 'JC@0020560392jc*-?', 'db' => 'coticefacil-db'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'root_secure_password', 'db' => 'coticefacil-db'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'db' => 'coticefacil-db'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'root', 'db' => 'coticefacil-db']
];

$pdo = null;

echo "Iniciando intentos de conexión...\n";

foreach ($attempts as $creds) {
    try {
        echo "Intentando {$creds['user']}@{$creds['host']}... ";
        $dsn = "mysql:host={$creds['host']};dbname={$creds['db']};charset=utf8mb4";
        $pdo = new PDO($dsn, $creds['user'], $creds['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "CONECTADO ACEPTADO.\n";
        break;
    } catch (PDOException $e) {
        echo "Fallo: " . $e->getMessage() . "\n";
    }
}

if (!$pdo) {
    die("CRITICAL: No se pudo conectar con ninguna credencial.\n");
}

echo "Iniciando Migración...\n";

// 1. Add cliente_id column
try {
    $pdo->exec("ALTER TABLE cotizacion_recordatorios ADD COLUMN cliente_id INT NULL AFTER cotizacion_id");
    echo "- Columna cliente_id agregada.\n";
} catch (PDOException $e) { 
    echo "- Info cliente_id: " . $e->getMessage() . "\n"; 
}

// 2. Modify cotizacion_id to be nullable
try {
    $pdo->exec("ALTER TABLE cotizacion_recordatorios MODIFY COLUMN cotizacion_id INT NULL");
    echo "- Columna cotizacion_id ahora permite NULL.\n";
} catch (PDOException $e) { 
    echo "- Info cotizacion_id: " . $e->getMessage() . "\n"; 
}

// 3. Populate cliente_id for existing records
try {
    $pdo->exec("UPDATE cotizacion_recordatorios r 
                JOIN cotizaciones c ON r.cotizacion_id = c.id 
                SET r.cliente_id = c.cliente_id 
                WHERE r.cliente_id IS NULL AND r.cotizacion_id IS NOT NULL");
    echo "- Registros existentes actualizados con cliente_id.\n";
} catch (PDOException $e) { 
    echo "- Error actualizando datos: " . $e->getMessage() . "\n"; 
}

echo "Proceso finalizado.\n";
?>
