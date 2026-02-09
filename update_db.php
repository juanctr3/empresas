<?php
/**
 * Script de Actualización de Base de Datos - CoticeFacil
 * Este script asegura que todas las columnas necesarias para las nuevas funcionalidades existan.
 */

// Desactivar el reporte de errores de sesión por si acaso
ini_set('session.use_cookies', 0);
ini_set('session.use_only_cookies', 0);
ini_set('session.use_trans_sid', 0);

require_once 'db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Actualizador de Base de Datos</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; max-width: 800px; margin: 40px auto; padding: 20px; background: #f4f7f6; }
        .card { background: white; padding: 30px; border-radius: 12px; shadow: 0 4px 6px rgba(0,0,0,0.1); border: 1px solid #ddd; }
        h1 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .success { color: #27ae60; font-weight: bold; }
        .info { color: #2980b9; }
        .error { color: #c0392b; font-weight: bold; background: #f9ebea; padding: 10px; border-radius: 4px; }
        .log-box { background: #2c3e50; color: #ecf0f1; padding: 20px; border-radius: 8px; font-family: monospace; overflow-x: auto; margin-top: 20px; }
        .btn { display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='card'>
        <h1>Actualizador de Estructura SQL</h1>
        <div class='log-box'>";

function log_msg($msg, $type = 'info') {
    $symbol = ($type === 'success') ? '[✓]' : (($type === 'error') ? '[✗]' : '[i]');
    echo "<div>$symbol $msg</div>";
}

try {
    log_msg("Iniciando proceso de actualización...");

    // 1. Verificar Tabla EMPRESAS
    log_msg("Verificando tabla 'empresas'...");
    $colsEmpresa = $pdo->query("SHOW COLUMNS FROM empresas")->fetchAll(PDO::FETCH_COLUMN);
    
    $updatesEmpresa = [
        'starting_quote_number' => "ALTER TABLE empresas ADD COLUMN starting_quote_number INT DEFAULT 1",
        'quote_prefix' => "ALTER TABLE empresas ADD COLUMN quote_prefix VARCHAR(20) DEFAULT ''",
        'quote_suffix' => "ALTER TABLE empresas ADD COLUMN quote_suffix VARCHAR(20) DEFAULT ''",
        'timezone' => "ALTER TABLE empresas ADD COLUMN timezone VARCHAR(50) DEFAULT 'America/Bogota' AFTER color_hex"
    ];

    foreach ($updatesEmpresa as $col => $sql) {
        if (!in_array($col, $colsEmpresa)) {
            $pdo->exec($sql);
            log_msg("Columna '$col' creada en 'empresas'.", 'success');
        } else {
            log_msg("Columna '$col' ya existe en 'empresas'.", 'info');
        }
    }

    // 2. Verificar Tabla COTIZACION_DETALLES
    log_msg("Verificando tabla 'cotizacion_detalles'...");
    $colsDetalles = $pdo->query("SHOW COLUMNS FROM cotizacion_detalles")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('imagen', $colsDetalles)) {
        $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN imagen VARCHAR(255) AFTER nombre_producto");
        log_msg("Columna 'imagen' creada en 'cotizacion_detalles'.", 'success');
    } else {
        log_msg("Columna 'imagen' ya existe en 'cotizacion_detalles'.", 'info');
    }

    // 3. Verificar Tabla COTIZACIONES
    log_msg("Verificando tabla 'cotizaciones'...");
    $colsCot = $pdo->query("SHOW COLUMNS FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('updated_at', $colsCot)) {
        $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        log_msg("Columna 'updated_at' creada en 'cotizaciones'.", 'success');
    } else {
        log_msg("Columna 'updated_at' ya existe en 'cotizaciones'.", 'info');
    }

    log_msg("Proceso finalizado exitosamente.", 'success');

} catch (Exception $e) {
    log_msg("ERROR CRÍTICO: " . $e->getMessage(), 'error');
}

echo "        </div>
        <p>Ya puedes cerrar este script y continuar usando la aplicación.</p>
        <a href='index.php' class='btn'>Ir al Panel</a>
    </div>
</body>
</html>";
