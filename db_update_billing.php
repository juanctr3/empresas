<?php
// db_update_billing.php
// Script para actualizar la estructura de la base de datos para el módulo de facturación

require_once 'db.php';

echo "Iniciando actualización de base de datos para Módulo de Facturación...\n\n";

try {
    // 1. Alterar tabla EMPRESAS
    echo "[1/4] Verificando tabla 'empresas'...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM empresas LIKE 'starting_invoice_number'")->fetchAll();
    if (empty($columns)) {
        $sql = "ALTER TABLE empresas 
                ADD COLUMN starting_invoice_number INT DEFAULT 1,
                ADD COLUMN invoice_prefix VARCHAR(20) DEFAULT 'FAC-',
                ADD COLUMN invoice_suffix VARCHAR(20) DEFAULT ''";
        $pdo->exec($sql);
        echo " -> Columnas de configuración de facturación agregadas.\n";
    } else {
        echo " -> Columnas ya existen. Omitiendo.\n";
    }

    // 2. Crear tabla FACTURAS
    echo "[2/4] Creando tabla 'facturas'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS facturas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        cliente_id INT NOT NULL,
        cotizacion_id INT,
        numero_factura VARCHAR(50) NOT NULL,
        fecha_emision DATE NOT NULL,
        fecha_vencimiento DATE,
        estado ENUM('Borrador', 'Enviada', 'Pagada', 'Anulada') DEFAULT 'Borrador',
        subtotal DECIMAL(15,2) DEFAULT 0,
        impuestos DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(15,2) DEFAULT 0,
        notas TEXT,
        notas_internas TEXT,
        contenido_html LONGTEXT,
        hash_publico VARCHAR(64) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE SET NULL,
        INDEX (numero_factura),
        INDEX (estado)
    ) ENGINE=InnoDB;";
    $pdo->exec($sql);
    echo " -> Tabla 'facturas' verificada/creada.\n";

    // 3. Crear tabla FACTURA_DETALLES
    echo "[3/4] Creando tabla 'factura_detalles'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS factura_detalles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        factura_id INT NOT NULL,
        producto_id INT,
        nombre_producto VARCHAR(255),
        descripcion TEXT,
        cantidad DECIMAL(10,2) NOT NULL,
        precio_unitario DECIMAL(15,2) NOT NULL,
        impuesto_porcentaje DECIMAL(5,2) DEFAULT 0,
        subtotal DECIMAL(15,2) NOT NULL,
        FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;";
    $pdo->exec($sql);
    echo " -> Tabla 'factura_detalles' verificada/creada.\n";

    // 4. Crear tabla FACTURA_ARCHIVOS
    echo "[4/4] Creando tabla 'factura_archivos'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS factura_archivos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        factura_id INT NOT NULL,
        nombre_original VARCHAR(255) NOT NULL,
        ruta_archivo VARCHAR(255) NOT NULL,
        tipo_mime VARCHAR(100),
        tamano BIGINT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    $pdo->exec($sql);
    echo " -> Tabla 'factura_archivos' verificada/creada.\n";
    
    // Extra: Permisos
    echo "[EXTRA] Insertando permisos...\n";
    $sql = "INSERT IGNORE INTO permisos (clave, nombre, descripcion) VALUES 
            ('ver_facturas', 'Ver Facturas', 'Permite ver el listado de facturas'),
            ('crear_facturas', 'Crear Facturas', 'Permite crear nuevas facturas'),
            ('editar_facturas', 'Editar Facturas', 'Permite modificar facturas'),
            ('eliminar_facturas', 'Eliminar Facturas', 'Permite eliminar facturas');";
    $pdo->exec($sql);
    echo " -> Permisos insertados.\n";

    echo "\n¡Actualización completada con éxito! ✅\n";

} catch (PDOException $e) {
    echo "\n❌ Error en la base de datos: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "\n❌ Error general: " . $e->getMessage() . "\n";
}
?>
