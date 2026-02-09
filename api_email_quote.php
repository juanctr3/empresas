<?php
require_once 'db.php';
require_once 'includes/mail_helper.php';

$cot_id = $_POST['cotizacion_id'] ?? $_GET['id'] ?? 0;

if (!$cot_id) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cotización no válido']);
    exit;
}

// Obtener datos para el email
$stmt = $pdo->prepare("
    SELECT c.*, cl.nombre as cliente_nombre, cl.email as cliente_email, 
           e.nombre as empresa_nombre, e.smtp_host, e.smtp_port, e.smtp_user, e.smtp_pass, e.smtp_encryption, e.smtp_from_email 
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

if (empty($cot['cliente_email'])) {
    echo json_encode(['status' => 'error', 'message' => 'El cliente no tiene un correo electrónico registrado']);
    exit;
}

$asunto = "Propuesta Comercial #" . ($cot['numero_cotizacion'] ?: $cot['id']) . " - " . $cot['empresa_nombre'];

$destinatarios_raw = $_POST['destinatarios'] ?? $cot['cliente_email'];
$destinatarios = array_map('trim', explode(',', $destinatarios_raw));
$mensaje_texto = $_POST['mensaje'] ?? ("Hola " . $cot['cliente_nombre'] . ", te enviamos la propuesta comercial que solicitaste. Puedes ver todos los detalles en el enlace adjunto.");

$enviados = 0;
$errores = 0;

foreach ($destinatarios as $email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores++;
        continue;
    }
    
    $uid = bin2hex(random_bytes(16));
    $cot_actual = $cot;
    $cot_actual['cliente_email'] = $email;
    $cot_actual['tracking_uid'] = $uid;
    
    if (enviarEmailPropuesta($cot_actual, $asunto, $mensaje_texto)) {
        $enviados++;
        // Registrar en historial
        $stmtHist = $pdo->prepare("INSERT INTO cotizacion_envios (cotizacion_id, tipo, destinatario, mensaje, uid) VALUES (?, 'Email', ?, ?, ?)");
        $stmtHist->execute([$cot_id, $email, $mensaje_texto, $uid]);
    } else {
        $errores++;
    }
}

if ($enviados > 0) {
    if ($cot['estado'] === 'Borrador') {
        $pdo->prepare("UPDATE cotizaciones SET estado = 'Enviada' WHERE id = ?")->execute([$cot_id]);
    }
    $msg = ($enviados > 1) ? "$enviados correos enviados correctamente." : "Correo enviado correctamente.";
    if ($errores > 0) $msg .= " Hubo $errores errores.";
    echo json_encode(['status' => 'success', 'message' => $msg]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar ningún correo. Verifica la configuración SMTP o los destinatarios.']);
}
?>
