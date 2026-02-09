<?php
// cron_alerta_vencimiento.php
// Ejecutar este script una vez al día via CRON job (ej: 9:00 AM)
// php /path/to/cron_alerta_vencimiento.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/client_notifications.php';

// 1. Auto-migración columna de control (Si no existe)
try {
    $cols = $pdo->query("SHOW COLUMNS FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('vencimiento_notificado', $cols)) {
        $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN vencimiento_notificado TINYINT(1) DEFAULT 0 AFTER estado");
        echo "[Setup] Columna vencimiento_notificado creada.\n";
    }
} catch (Exception $e) {
    echo "[Error Setup] " . $e->getMessage() . "\n";
}

// 2. Buscar cotizaciones por vencer en 2 días
// Condición: Fecha vencimiento = Hoy + 2 dias, Estado no final, No notificado aun
$hoy = date('Y-m-d');
$target_date = date('Y-m-d', strtotime('+2 days'));

echo "--------------------------------------------------\n";
echo "Ejecutando Alertas de Vencimiento: $hoy\n";
echo "Buscando cotizaciones que vencen el: $target_date\n";

try {
    $sql = "
        SELECT c.*, 
               cl.nombre as cliente_nombre, cl.email as cliente_email, cl.telefono as cliente_telefono, cl.celular_contacto,
               e.nombre as empresa_nombre, e.smsenlinea_secret, e.smsenlinea_wa_account,
               e.smtp_host, e.smtp_user, e.smtp_pass, e.smtp_port, e.smtp_from_email
        FROM cotizaciones c
        JOIN clientes cl ON c.cliente_id = cl.id
        JOIN empresas e ON c.empresa_id = e.id
        WHERE c.fecha_vencimiento = ? 
          AND c.estado NOT IN ('Aceptada', 'Rechazada') 
          AND c.vencimiento_notificado = 0
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$target_date]);
    $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Encontradas: " . count($pendientes) . "\n";
    
    $count_ok = 0;
    
    foreach ($pendientes as $cot) {
        echo "Procesando Cot #{$cot['numero_cotizacion']} (ID: {$cot['id']})... ";
        
        // Preparar array cliente con datos SMTP de empresa (que ya vienen en el JOIN)
        $datos_cliente = [
            'nombre' => $cot['cliente_nombre'],
            'email' => $cot['cliente_email'],
            'telefono' => $cot['cliente_telefono'],
            'celular_contacto' => $cot['celular_contacto'],
            'smtp_host' => $cot['smtp_host'],
            'smtp_user' => $cot['smtp_user'],
            'smtp_pass' => $cot['smtp_pass'],
            'smtp_port' => $cot['smtp_port'],
            'smtp_from_email' => $cot['smtp_from_email'],
            'empresa_nombre' => $cot['empresa_nombre'],
            'smsenlinea_secret' => $cot['smsenlinea_secret'],
            'smsenlinea_wa_account' => $cot['smsenlinea_wa_account']
        ];
        
        $res = enviarAlertaVencimiento($pdo, $cot, $datos_cliente);
        
        // Marcar como notificado
        $pdo->prepare("UPDATE cotizaciones SET vencimiento_notificado = 1 WHERE id = ?")->execute([$cot['id']]);
        
        echo "OK (Email: " . ($res['email']?'Si':'No') . ", WA: " . ($res['whatsapp']?'Si':'No') . ")\n";
        $count_ok++;
    }
    
    echo "Finalizado. $count_ok alertas enviadas.\n";
    
} catch (Exception $e) {
    echo "Error General: " . $e->getMessage() . "\n";
}
