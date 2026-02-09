<?php
/**
 * Script de Automatización: Notificaciones de Vencimiento
 * Ejecutar vía Cron o manualmente.
 * Ej: php cron_vencimientos.php
 */

require_once 'db.php';
// Nota: No requerimos auth_helper porque es un script CLI/Cron, 
// pero necesitamos manejar las empresas de forma global.

echo "--- Iniciando Proceso de Alertas de Vencimiento ---\n";

try {
    // 1. Buscar documentos que vencen en 7 días o 1 día y que NO hayan sido notificados hoy
    $sql = "
        SELECT d.*, e.nombre as empresa_nombre, e.email as empresa_email, u.nombre as usuario_nombre, u.email as usuario_email, cl.nombre as cliente_nombre, cl.telefono as cliente_telefono
        FROM documentos d
        JOIN empresas e ON d.empresa_id = e.id
        LEFT JOIN usuarios u ON d.usuario_id = u.id
        LEFT JOIN clientes cl ON d.cliente_id = cl.id
        WHERE d.fecha_vencimiento IS NOT NULL 
          AND d.notificado_vencimiento = 0
          AND (
              d.fecha_vencimiento = DATE_ADD(CURDATE(), INTERVAL 7 DAY) OR 
              d.fecha_vencimiento = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
          )
    ";
    
    $stmt = $pdo->query($sql);
    $documentos = $stmt->fetchAll();
    
    if (empty($documentos)) {
        echo "No hay documentos próximos a vencer para notificar hoy.\n";
        exit;
    }

    foreach ($documentos as $doc) {
        echo "Notificando: {$doc['nombre_original']} (Vence: {$doc['fecha_vencimiento']})\n";
        
        $mensaje = "⚠️ *ALERTA DE VENCIMIENTO*\n\n";
        $mensaje .= "El documento *{$doc['nombre_original']}* ({$doc['categoria']}) está próximo a vencer el día *{$doc['fecha_vencimiento']}*.\n\n";
        $mensaje .= "Empresa: {$doc['empresa_nombre']}\n";
        
        // Enviar a Usuario (Admin/Creador)
        if (!empty($doc['usuario_email'])) {
            // Aquí llamaríamos a mail_helper o similar
            echo "- Enviando email a: {$doc['usuario_email']}\n";
            // mail($doc['usuario_email'], "Alerta: Documento por vencer", $mensaje);
        }

        // Enviar a Cliente si está asociado
        if (!empty($doc['cliente_telefono'])) {
            echo "- Programando WhatsApp para: {$doc['cliente_telefono']}\n";
            // Lógica para insertar en una cola de mensajes o enviar directo
            // enviar_whatsapp($doc['cliente_telefono'], $mensaje, $doc['empresa_id']);
        }

        // Marcar como notificado para no repetir hoy
        $pdo->prepare("UPDATE documentos SET notificado_vencimiento = 1 WHERE id = ?")->execute([$doc['id']]);
    }

    echo "--- Proceso completado con éxito ---\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
