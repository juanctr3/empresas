<?php
require_once 'db.php';
require_once 'includes/whatsapp_helper.php';
require_once 'includes/template_helper.php';

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$cot_id = $_POST['cotizacion_id'] ?? $_GET['id'] ?? 0;

if (!$cot_id) {
    header("Location: cotizaciones.php?error=ID de cotización no válido");
    exit;
}

// Obtener datos base
$stmt = $pdo->prepare("
    SELECT c.*, cl.nombre as cliente_nombre, cl.telefono as cliente_tel, cl.pais_codigo, e.*
    FROM cotizaciones c 
    JOIN clientes cl ON c.cliente_id = cl.id 
    JOIN empresas e ON c.empresa_id = e.id
    WHERE c.id = ? AND c.empresa_id = ?
");
$stmt->execute([$cot_id, getEmpresaId()]);
$cot = $stmt->fetch();

if (!$cot) {
    header("Location: cotizaciones.php?error=Cotización no encontrada");
    exit;
}

$link_publico = getBaseUrl() . "propuesta.php?h=" . $cot['hash_publico'];

switch ($accion) {
    case 'enviar_wa':
        // Envío manual o multi-recipiente
        $telefono_final = $_POST['telefono_pro'] ?? $cot['cliente_tel'];
        $pais_final = isset($_POST['telefono_pro']) ? '' : ($cot['pais_codigo'] ?: '57');
        
        $uid = bin2hex(random_bytes(16));
        $link_tracking = $link_publico . "&sid=" . $uid;
        
        $mensaje = $_POST['mensaje_pro'] ?? '';
        if (empty($mensaje)) {
            $mensaje = "Hola *" . $cot['cliente_nombre'] . "*, te saluda *" . $cot['empresa_nombre'] . "*. \n\n";
            $mensaje .= "Te adjuntamos la cotización *#" . ($cot['numero_cotizacion'] ?: $cot['id']) . "*.\n";
            $mensaje .= "Total: *$" . number_format($cot['total'], 2) . "*\n\n";
            $mensaje .= "Puedes revisarla y aceptarla en línea aquí:\n" . $link_tracking;
        } else {
            $mensaje = parsearShortcodesCotizacion($pdo, $cot_id, $mensaje);
            // Si el mensaje es personalizado, verificamos si ya incluye el link para no duplicarlo
            if (strpos($mensaje, 'propuesta.php') === false) {
                 $mensaje .= "\n\nVer propuesta: " . $link_tracking;
            }
        }

        $resultado = enviarWhatsApp($pdo, $cot['empresa_id'], $telefono_final, $mensaje, null, 'text', $pais_final);

        $success = (($resultado['status'] ?? 0) == 200 || ($resultado['status'] ?? '') == 'success' || isset($resultado['key'])); 
        if ($success) {
            // Registrar en historial
            $stmtHist = $pdo->prepare("INSERT INTO cotizacion_envios (cotizacion_id, tipo, destinatario, mensaje, uid) VALUES (?, 'WhatsApp', ?, ?, ?)");
            $stmtHist->execute([$cot_id, $telefono_final, $mensaje, $uid]);

            if ($cot['estado'] === 'Borrador') {
                $pdo->prepare("UPDATE cotizaciones SET estado = 'Enviada' WHERE id = ?")->execute([$cot_id]);
            }
        }

        if (isset($_REQUEST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => $success ? 'success' : 'error',
                'message' => $success ? 'Mensaje enviado correctamente' : ($resultado['message'] ?? 'Error desconocido')
            ]);
            exit;
        }

        if ($success) {
            header("Location: cotizaciones.php?msg=enviado");
        } else {
            $error = $resultado['message'] ?? 'Error desconocido';
            header("Location: cotizaciones.php?error=" . urlencode($error));
        }
        break;

    case 'cambiar_estado':
        $nuevo_estado = $_POST['nuevo_estado'];
        $enviar_notificacion = isset($_POST['notificar']);

        // Actualizar en base de datos
        $stmt = $pdo->prepare("UPDATE cotizaciones SET estado = ? WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$nuevo_estado, $cot_id, getEmpresaId()]);

        if ($nuevo_estado === 'Aprobada') {
            // 1. Convertir Prospecto en Cliente si es necesario
            $pdo->prepare("UPDATE clientes SET es_cliente = 1 WHERE id = ? AND empresa_id = ?")->execute([$cot['cliente_id'], getEmpresaId()]);

            // 2. Crear Orden de Trabajo automática (en estado Borrador)
            $stmt_ot = $pdo->prepare("INSERT INTO ordenes_trabajo (empresa_id, cliente_id, cotizacion_id, numero_ot, estado, fecha_inicio, notas) VALUES (?, ?, ?, ?, 'Borrador', CURDATE(), ?)");
            $numero_ot = 'OT-' . ($cot['numero_cotizacion'] ?: $cot['id']);
            $notas_ot = "Generada automáticamente desde Cotización #" . ($cot['numero_cotizacion'] ?: $cot['id']);
            $stmt_ot->execute([getEmpresaId(), $cot['cliente_id'], $cot_id, $numero_ot, $notas_ot]);
        }

        if ($enviar_notificacion) {
            $msg_notif = "📢 *Actualización de tu Cotización*\n\n";
            $msg_notif .= "Hola *" . $cot['cliente_nombre'] . "*, el estado de tu cotización *#" . ($cot['numero_cotizacion'] ?: $cot['id']) . "* ha cambiado a: *" . strtoupper($nuevo_estado) . "*.\n\n";
            
            if ($nuevo_estado === 'Aprobada') {
                $msg_notif .= "¡Gracias por confiar en nosotros! Pronto nos pondremos en contacto para el siguiente paso. 🎉";
            } elseif ($nuevo_estado === 'Rechazada') {
                $msg_notif .= "Lamentamos que no podamos avanzar esta vez. Quedamos a tu disposición para futuros proyectos.";
            }

            enviarWhatsApp($pdo, $cot['empresa_id'], $cot['cliente_tel'], $msg_notif);
        }

        header("Location: cotizaciones.php?msg=estado_actualizado");
        break;

    default:
        header("Location: cotizaciones.php");
        break;
}
exit;
?>
