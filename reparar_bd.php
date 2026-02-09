<?php
require_once 'db.php';

echo "<h1>Reparación de Base de Datos</h1>";

try {
    // Verificar si la columna es_cliente existe
    $stmt = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'es_cliente'");
    $exists = $stmt->fetch();

    if (!$exists) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN es_cliente TINYINT(1) DEFAULT 0 AFTER email");
        echo "<p style='color:green'>✅ Columna 'es_cliente' agregada exitosamente.</p>";
        
        // Opcional: Actualizar registros existentes si hay lógica para determinarlo?
        // Por defecto todos serán 0 (Prospectos). El usuario tendrá que actualizarlos manualmente si eran Clientes.
        echo "<p>Nota: Todos los contactos actuales se han marcado como 'Prospectos' por defecto.</p>";
    } else {
        echo "<p style='color:blue'>ℹ️ La columna 'es_cliente' ya existe. No se requieren cambios.</p>";
    }

    // Verificar si la columna imagen existe en productos
    $stmt = $pdo->query("SHOW COLUMNS FROM productos LIKE 'imagen'");
    $exists_img = $stmt->fetch();

    if (!$exists_img) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN imagen VARCHAR(255) AFTER impuesto_id");
        echo "<p style='color:green'>✅ Columna 'imagen' agregada a productos exitosamente.</p>";
    } else {
        echo "<p style='color:blue'>ℹ️ La columna 'imagen' en productos ya existe.</p>";
    }

    // Verificar si la columna logo_url existe en plantillas
    $stmt = $pdo->query("SHOW COLUMNS FROM plantillas LIKE 'logo_url'");
    $exists_logo = $stmt->fetch();

    if (!$exists_logo) {
        $pdo->exec("ALTER TABLE plantillas ADD COLUMN logo_url VARCHAR(255) AFTER producto_id");
        echo "<p style='color:green'>✅ Columna 'logo_url' agregada a plantillas exitosamente.</p>";
    } else {
        echo "<p style='color:blue'>ℹ️ La columna 'logo_url' en plantillas ya existe.</p>";
    }

    // Verificar Tablas WhatsApp
    $pdo->exec("CREATE TABLE IF NOT EXISTS wa_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        cliente_id INT,
        whatsapp_id VARCHAR(50),
        asignado_a INT,
        tipo_asignacion VARCHAR(50),
        ultimo_mensaje TEXT,
        fecha_ultimo_mensaje DATETIME,
        visto_por_admin TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
    echo "<p style='color:green'>✅ Tabla 'wa_chats' verificada/creada.</p>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS wa_mensajes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NOT NULL,
        empleado_id INT,
        direccion VARCHAR(20),
        contenido TEXT,
        nombre_empleado_copia VARCHAR(100),
        fecha_envio DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chat_id) REFERENCES wa_chats(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    echo "<p style='color:green'>✅ Tabla 'wa_mensajes' verificada/creada.</p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='index.php'>Volver al Inicio</a>";
?>
