<?php
// Script de Instalación del Módulo de Recordatorios
// Ejecutar esto una vez para crear la tabla y datos de prueba.

require_once 'db.php';

echo "<h1>Instalación Módulo Recordatorios</h1>";
echo "<pre>";

try {
    // 1. Crear Tabla
    echo "1. Verificando tabla 'cotizacion_recordatorios'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS cotizacion_recordatorios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        cotizacion_id INT NOT NULL,
        usuario_id INT NOT NULL,
        asunto VARCHAR(255) NOT NULL,
        mensaje TEXT,
        fecha_programada DATETIME NOT NULL,
        emails_adicionales TEXT,
        telefonos_adicionales TEXT,
        notificar_cliente TINYINT(1) DEFAULT 1,
        estado ENUM('Pendiente', 'Enviado', 'Fallido', 'Cancelado') DEFAULT 'Pendiente',
        log_envio TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
        FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    
    $pdo->exec($sql);
    echo "✅ Tabla creada o ya existía.\n";

    // 2. Insertar Dato de Prueba
    echo "\n2. Intentando insertar dato de prueba...\n";
    
    // Buscar una cotización existente para asociar
    $stmt = $pdo->query("SELECT id, empresa_id, usuario_id FROM cotizaciones ORDER BY id DESC LIMIT 1");
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cot) {
        $empresa_id = $cot['empresa_id'];
        $cot_id = $cot['id'];
        $user_id = $cot['usuario_id'] ?? 1; // Fallback audit

        // Verificar si ya existe el de prueba
        $stmtCheck = $pdo->prepare("SELECT id FROM cotizacion_recordatorios WHERE cotizacion_id = ? AND asunto = 'Prueba de Sistema'");
        $stmtCheck->execute([$cot_id]);
        
        if (!$stmtCheck->fetch()) {
            $stmtInsert = $pdo->prepare("INSERT INTO cotizacion_recordatorios 
                (empresa_id, cotizacion_id, usuario_id, asunto, mensaje, fecha_programada, emails_adicionales, telefonos_adicionales, notificar_cliente, estado) 
                VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), '[]', '[]', 1, 'Pendiente')");
            
            $stmtInsert->execute([
                $empresa_id,
                $cot_id,
                $user_id,
                'Prueba de Sistema',
                'Este es un recordatorio de prueba generado automáticamente.',
            ]);
            echo "✅ Dato de prueba insertado correctamente para la Cotización #$cot_id.\n";
        } else {
            echo "ℹ️ Dato de prueba ya existía.\n";
        }
    } else {
        echo "⚠️ No se encontraron cotizaciones para crear un dato de prueba. Crea una cotización primero.\n";
    }

    echo "\n✅ PROCESO COMPLETADO EXITOSAMENTE.";
    echo "\nPuedes borrar este archivo (install_recordatorios.php) si lo deseas.";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage();
}

echo "</pre>";
?>
