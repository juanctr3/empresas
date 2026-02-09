<?php
require_once 'db.php';
require_once 'includes/mail_helper.php';
require_once 'includes/whatsapp_helper.php';
require_once 'includes/template_helper.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$cot_id = $input['id'] ?? 0;
$emails = $input['emails'] ?? [];
$whatsapps = $input['whatsapps'] ?? [];
$custom_message = $input['message'] ?? '';

if (!$cot_id) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cotización no válido']);
    exit;
}

// Obtener datos detallados
$stmt = $pdo->prepare("
    SELECT c.*, cl.nombre as cliente_nombre, cl.email as cliente_email, cl.telefono as cliente_tel, cl.pais_codigo,
           e.nombre as empresa_nombre, e.smtp_host, e.smtp_port, e.smtp_user, e.smtp_pass, e.smtp_encryption, e.smtp_from_email,
           e.smsenlinea_secret, e.smsenlinea_wa_account 
    FROM cotizaciones c 
    JOIN clientes cl ON c.cliente_id = cl.id 
    JOIN empresas e ON c.empresa_id = e.id
    WHERE c.id = ? AND c.empresa_id = ?
");
$stmt->execute([$cot_id, getEmpresaId()]);
$cot = $stmt->fetch();

if (!$cot) {
    echo json_encode(['status' => 'error', 'message' => 'Cotización no encontrada']);
    exit;
}

$enviados_email = 0;
$enviados_wa = 0;
$log_errores = [];

$link_publico = getBaseUrl() . "propuesta.php?h=" . $cot['hash_publico'];

// --- PROCESAR EMAILS ---
if (!empty($emails)) {
    $asunto = "Propuesta Comercial #" . ($cot['numero_cotizacion'] ?: $cot['id']) . " - " . $cot['empresa_nombre'];
    foreach ($emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        
        $uid = bin2hex(random_bytes(16));
        $info_email = $cot;
        $info_email['cliente_email'] = $email;
        $info_email['tracking_uid'] = $uid;
        
        $mensaje_final = !empty($custom_message) ? parsearShortcodesCotizacion($pdo, $cot_id, $custom_message) : ("Hola " . $cot['cliente_nombre'] . ", te enviamos la propuesta comercial que solicitaste. Puedes ver todos los detalles en el enlace adjunto.");

        if (enviarEmailPropuesta($info_email, $asunto, $mensaje_final)) {
            $enviados_email++;
            // Log Envío
            $stmtHist = $pdo->prepare("INSERT INTO cotizacion_envios (cotizacion_id, tipo, destinatario, mensaje, uid) VALUES (?, 'Email', ?, ?, ?)");
            $stmtHist->execute([$cot_id, $email, $mensaje_final, $uid]);
        } else {
            $log_errores[] = "Error enviando email a $email";
        }
    }
}

// --- PROCESAR WHATSAPPS ---
if (!empty($whatsapps)) {
    foreach ($whatsapps as $wa) {
        $num = preg_replace('/\D/', '', $wa['number']);
        if (empty($num)) continue;
        
        $pais = preg_replace('/\D/', '', $wa['country'] ?: '57');
        $uid = bin2hex(random_bytes(16));
        $link_tracking = $link_publico . "&sid=" . $uid;
        
        $mensaje_wa = !empty($custom_message) ? parsearShortcodesCotizacion($pdo, $cot_id, $custom_message . "\n\nVer propuesta: " . $link_tracking) : null;
        if (!$mensaje_wa) {
            $mensaje_wa = "Hola *" . $cot['cliente_nombre'] . "*, te saluda *" . $cot['empresa_nombre'] . "*. \n\n";
            $mensaje_wa .= "Te adjuntamos la cotización *#" . ($cot['numero_cotizacion'] ?: $cot['id']) . "*.\n";
            $mensaje_wa .= "Total: *$" . number_format($cot['total'], 2) . "*\n\n";
            $mensaje_wa .= "Puedes revisarla y aceptarla en línea aquí:\n" . $link_tracking;
        }

        $resultado = enviarWhatsApp($pdo, $cot['empresa_id'], $num, $mensaje_wa, null, 'image', $pais);
        $success = (($resultado['status'] ?? '') === 'success' || ($resultado['status'] ?? 0) === 200);
        
        if ($success) {
            $enviados_wa++;
            // Log Envío
            $stmtHist = $pdo->prepare("INSERT INTO cotizacion_envios (cotizacion_id, tipo, destinatario, mensaje, uid) VALUES (?, 'WhatsApp', ?, ?, ?)");
            $stmtHist->execute([$cot_id, $pais . $num, $mensaje_wa, $uid]);
        } else {
            $log_errores[] = "Error enviando WhatsApp a $num: " . ($resultado['message'] ?? 'Desconocido');
        }
    }
}

// Actualizar estado si es necesario
if (($enviados_email > 0 || $enviados_wa > 0) && $cot['estado'] === 'Borrador') {
    $pdo->prepare("UPDATE cotizaciones SET estado = 'Enviada' WHERE id = ?")->execute([$cot_id]);
}

$total_enviados = $enviados_email + $enviados_wa;
if ($total_enviados > 0) {
    $response = [
        'status' => 'success',
        'message' => "Se enviaron $enviados_email correos y $enviados_wa WhatsApps correctamente."
    ];
    if (!empty($log_errores)) $response['details'] = $log_errores;
    echo json_encode($response);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar la cotización por ningún medio.', 'details' => $log_errores]);
}
