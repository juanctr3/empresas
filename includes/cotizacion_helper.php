<?php
/**
 * Cotización Helper - Centralized Acceptance Logic
 */

require_once __DIR__ . '/whatsapp_helper.php';
require_once __DIR__ . '/mail_helper.php';

/**
 * Procesa la aceptación de una cotización, genera OT y envía notificaciones.
 */
function procesarAceptacionCotizacion($pdo, $cot_id, $firma_base64, $metadata = []) {
    // 1. Obtener datos completos de la cotización y empresa
    $stmt = $pdo->prepare("
        SELECT c.*, cl.nombre as cliente_nombre, cl.celular_contacto, cl.email as cliente_email,
               e.*
        FROM cotizaciones c 
        JOIN clientes cl ON c.cliente_id = cl.id 
        JOIN empresas e ON c.empresa_id = e.id
        WHERE c.id = ?
    ");
    $stmt->execute([$cot_id]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cot) return ['status' => 'error', 'message' => 'Cotización no encontrada'];
    if ($cot['estado'] === 'Aprobada') return ['status' => 'success', 'message' => 'La cotización ya estaba aprobada'];

    $pdo->beginTransaction();
    try {
        // 2. Convertir Prospecto -> Cliente (Condicional)
        $auto_convert = isset($cot['conversion_automatica']) ? (bool)$cot['conversion_automatica'] : true;
        if ($cot['cliente_id'] && $auto_convert) {
            $stmtCl = $pdo->prepare("UPDATE clientes SET es_cliente = 1 WHERE id = ? AND es_cliente = 0");
            $stmtCl->execute([$cot['cliente_id']]);
        }

        // 3. Actualizar Cotización
        $stmtUpd = $pdo->prepare("UPDATE cotizaciones SET estado = 'Aprobada', aceptada_data = ?, firma_digital = ? WHERE id = ?");
        $stmtUpd->execute([json_encode($metadata), $firma_base64, $cot_id]);

        // 4. Generar Orden de Trabajo (OT) - DESHABILITADO POR REQUERIMIENTO (Solo Aceptación)
        /*
        $logistica = $metadata['logistica'] ?? null;
        $fecha_prog = $logistica['fecha'] ?? null;
        $datos_reco = $logistica ? json_encode($logistica) : null;
        
        $stmtOT = $pdo->prepare("INSERT INTO ordenes_trabajo (empresa_id, cotizacion_id, cliente_id, estado, fecha_programada, datos_recoleccion) VALUES (?, ?, ?, 'Pendiente', ?, ?)");
        $stmtOT->execute([$cot['empresa_id'], $cot['id'], $cot['cliente_id'], $fecha_prog, $datos_reco]);
        $ot_id = $pdo->lastInsertId();

        // 5. Copiar items a la OT
        $stmtItems = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ?");
        $stmtItems->execute([$cot['id']]);
        
        $stmtInsItem = $pdo->prepare("INSERT INTO ordenes_items (orden_id, nombre_item, cantidad, estado_item) VALUES (?, ?, ?, 'Pendiente')");
        while ($item = $stmtItems->fetch(PDO::FETCH_ASSOC)) {
             $stmtInsItem->execute([$ot_id, $item['nombre_producto'], $item['cantidad']]);
        }
        */
        $ot_id = 0; // Legacy placeholder

        $pdo->commit();

        // 6. Notificaciones
        try {
            // Notificar a la empresa
            if (function_exists('notificarEmpresa')) {
                notificarEmpresa(
                    $pdo,
                    $cot['empresa_id'],
                    'cotizacion_aceptada',
                    '¡Cotización Aceptada!',
                    "Cliente: {$cot['cliente_nombre']}. La cotización ha sido aprobada.",
                    "ver-cotizacion.php?id={$cot['id']}"
                );
            }
            
            // WhatsApp al Cliente
            if (!empty($cot['celular_contacto'])) {
                $msg = "¡Hola {$cot['cliente_nombre']}! Gracias por aprobar la cotización #{$cot['numero_cotizacion']}. Tu aceptación ha sido registrada exitosamente.";
                enviarWhatsApp($pdo, $cot['empresa_id'], $cot['celular_contacto'], $msg, null, 'image', '57');
            }
        } catch (Exception $eN) {
            error_log("Warning: Fallo en notificaciones en helper: " . $eN->getMessage());
        }

        // return ['status' => 'success', 'message' => 'Cotización aceptada y Orden de Trabajo generada correctamente.', 'ot_id' => $ot_id];
        return ['status' => 'success', 'message' => 'Cotización aceptada correctamente.', 'ot_id' => 0];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
