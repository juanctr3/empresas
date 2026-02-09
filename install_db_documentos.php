<?php
require_once 'db.php';

echo "<h1>Actualización de Base de Datos - Bóveda de Documentos</h1>";
echo "<pre>";

try {
    // 1. Tabla: documentos_carpetas
    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_carpetas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nombre VARCHAR(255) NOT NULL,
        parent_id INT DEFAULT NULL,
        color VARCHAR(20) DEFAULT '#6366f1',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(empresa_id),
        INDEX(parent_id)
    )");
    echo "✅ Tabla 'documentos_carpetas' verificada.<br>";

    // 2. Tabla: documentos_categorias
    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#10b981',
        icono VARCHAR(50) DEFAULT 'tag',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE INDEX unq_cat (empresa_id, nombre),
        INDEX(empresa_id)
    )");
    echo "✅ Tabla 'documentos_categorias' verificada.<br>";

    // 3. Tabla: documentos_compartidos
    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_compartidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        documento_id INT NOT NULL,
        tipo_objeto ENUM('archivo', 'carpeta') DEFAULT 'archivo', 
        hash_publico VARCHAR(64) NOT NULL,
        requiere_password TINYINT(1) DEFAULT 0,
        password_hash VARCHAR(255) DEFAULT NULL,
        vistas_max INT DEFAULT 0,
        total_vistas INT DEFAULT 0,
        expira_at DATETIME DEFAULT NULL,
        activo TINYINT(1) DEFAULT 1,
        creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(documento_id),
        UNIQUE INDEX(hash_publico)
    )");
    echo "✅ Tabla 'documentos_compartidos' verificada.<br>";

    // Actualización: Asegurar columna tipo_objeto
    try {
        $pdo->exec("ALTER TABLE documentos_compartidos ADD COLUMN tipo_objeto ENUM('archivo', 'carpeta') DEFAULT 'archivo' AFTER documento_id");
        echo "🔹 Columna 'tipo_objeto' agregada a 'documentos_compartidos'.<br>";
    } catch (Exception $e) { 
        echo "🔹 Columna 'tipo_objeto' ya existía.<br>";
    }

    // 4. Tabla: documentos_logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        compartido_id INT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        referencia VARCHAR(255),
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(compartido_id)
    )");
    echo "✅ Tabla 'documentos_logs' verificada.<br>";

    // 5. Tabla: documentos (Asegurar columnas)
    // Asumimos que la tabla base existe, verificamos columnas clave
    try {
        $pdo->exec("ALTER TABLE documentos ADD COLUMN carpeta_id INT DEFAULT NULL AFTER usuario_id");
        echo "🔹 Columna 'carpeta_id' agregada a 'documentos'.<br>";
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE documentos ADD COLUMN nombre_s3 VARCHAR(255) DEFAULT NULL AFTER nombre_original");
        echo "🔹 Columna 'nombre_s3' agregada a 'documentos'.<br>";
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE documentos ADD COLUMN bucket VARCHAR(50) DEFAULT 'local' AFTER nombre_s3");
        echo "🔹 Columna 'bucket' agregada a 'documentos'.<br>";
    } catch (Exception $e) {}
    
    // 6. Tabla: empresas (Configuración S3)
    try {
        $pdo->exec("ALTER TABLE empresas ADD COLUMN storage_type ENUM('local', 's3') DEFAULT 'local'");
        echo "🔹 Columna 'storage_type' agregada a 'empresas'.<br>";
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE empresas ADD COLUMN s3_bucket VARCHAR(255) DEFAULT NULL");
        echo "🔹 Columna 's3_bucket' agregada a 'empresas'.<br>";
    } catch (Exception $e) {}
    
    try {
        $pdo->exec("ALTER TABLE empresas ADD COLUMN s3_region VARCHAR(50) DEFAULT NULL");
        echo "🔹 Columna 's3_region' agregada a 'empresas'.<br>";
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE empresas ADD COLUMN s3_access_key VARCHAR(255) DEFAULT NULL");
        echo "🔹 Columna 's3_access_key' agregada a 'empresas'.<br>";
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE empresas ADD COLUMN s3_secret_key VARCHAR(255) DEFAULT NULL");
        echo "🔹 Columna 's3_secret_key' agregada a 'empresas'.<br>";
    } catch (Exception $e) {}


    echo "<h3 style='color:green'>¡Actualización Completada con Éxito!</h3>";
    echo "<a href='documentos.php'>Volver a Documentos</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
echo "</pre>";
