<?php
require_once 'db.php';
require_once 'includes/whatsapp_helper.php';
require_once 'includes/mail_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cotizacion_id = $_POST['cotizacion_id'] ?? 0;
    $tipo = $_POST['tipo'] ?? 'Interno'; // Interno o Publico
    $mensaje = $_POST['mensaje'] ?? '';

    if (!$cotizacion_id || !$mensaje) {
        echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Guardar en Historial
        $stmt = $pdo->prepare("INSERT INTO cotizacion_historial (cotizacion_id, tipo, mensaje) VALUES (?, ?, ?)");
        $stmt->execute([$cotizacion_id, $tipo, $mensaje]);
        $historial_id = $pdo->lastInsertId();

        $notificado_email = 0;
        $notificado_wa = 0;

        // 2. Si es Público, disparar notificaciones
        if ($tipo === 'Publico') {
            // Obtener datos del cliente y empresa
            $stmtData = $pdo->prepare("
                SELECT c.numero_cotizacion, c.hash_publico, cl.nombre as cliente_nombre, cl.email as cliente_email, cl.telefono as cliente_tel, cl.pais_codigo,
                       e.*
                FROM cotizaciones c
                JOIN clientes cl ON c.cliente_id = cl.id
                JOIN empresas e ON c.empresa_id = e.id
                WHERE c.id = ?
            ");
            $stmtData->execute([$cotizacion_id]);
            $info = $stmtData->fetch();

            if ($info) {
                // Enviar Email profesional (SMTP/Mail)
                $asunto = "Actualización en tu cotización #" . ($info['numero_cotizacion'] ?: $cotizacion_id);
                if (enviarEmailPropuesta($info, $asunto, $mensaje)) {
                    $notificado_email = 1;
                }

                // Enviar WhatsApp (SMSenlinea)
                $link_propuesta = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/propuesta.php?h=" . $info['hash_publico'] . "#chat";
                
                $wa_msg = "👋 Hola *" . $info['cliente_nombre'] . "*,\n\n";
                $wa_msg .= "📝 Hay una nueva actualización en tu cotización *#" . ($info['numero_cotizacion'] ?: $cotizacion_id) . "*:\n\n";
                $wa_msg .= "💬 _\"" . $mensaje . "\"_\n\n";
                $wa_msg .= "🔗 *Ver propuesta y responder aquí:*\n" . $link_propuesta;
                
                if (!empty($info['cliente_tel'])) {
                    $pais = $info['pais_codigo'] ?: ''; 
                    $resWa = enviarWhatsApp($pdo, $info['empresa_id'] ?? $cotizacion_id, $info['cliente_tel'], $wa_msg, null, 'text', $pais);
                    if ($resWa && (($resWa['status'] ?? 0) == 200 || ($resWa['status'] ?? '') == 'success' || isset($resWa['key']))) {
                        $notificado_wa = 1;
                    }
                }
            }
        }

        // Actualizar estados de notificación en la DB
        $stmtUpd = $pdo->prepare("UPDATE cotizacion_historial SET notificado_wa = ?, notificado_email = ? WHERE id = ?");
        $stmtUpd->execute([$notificado_wa, $notificado_email, $historial_id]);

        $pdo->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Nota guardada', 
            'wa' => $notificado_wa ? 'Enviado' : 'No enviado',
            'email' => $notificado_email ? 'Enviado' : 'No enviado'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}

