<?php
require_once 'db.php';
require_once 'includes/notificaciones_helper.php';
require_once 'includes/whatsapp_helper.php';

// Esta API permite al cliente dejar notas en la propuesta pública
$hash = $_POST['hash'] ?? '';
$mensaje = $_POST['mensaje'] ?? '';

if (empty($hash) || empty($mensaje)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
    exit;
}

// Buscar cotización y datos de empresa
$stmt = $pdo->prepare("
    SELECT c.id, c.numero_cotizacion, c.empresa_id, c.cliente_id, 
           e.*,
           cl.nombre as cliente_nombre
    FROM cotizaciones c
    JOIN empresas e ON c.empresa_id = e.id
    JOIN clientes cl ON c.cliente_id = cl.id
    WHERE c.hash_publico = ?
");
$stmt->execute([$hash]);
$cot = $stmt->fetch();

if (!$cot) {
    echo json_encode(['status' => 'error', 'message' => 'Propuesta no encontrada']);
    exit;
}

// Insertar en historial como "Publico"
$stmt = $pdo->prepare("INSERT INTO cotizacion_historial (cotizacion_id, tipo, mensaje) VALUES (?, 'Publico', ?)");
$stmt->execute([$cot['id'], "COMENTARIO DEL CLIENTE: " . $mensaje]);

// ---------------------------------------------------------
// NOTIFICAR A ADMINISTRADORES/ASESORES
// ---------------------------------------------------------

// 1. Obtener usuarios administradores o con permisos de ver cotizaciones de la empresa
$stmtUsers = $pdo->prepare("
    SELECT id, nombre, telefono 
    FROM usuarios 
    WHERE empresa_id = ? AND (is_super_admin = 1 OR rol_id IN (SELECT rol_id FROM rol_permisos rp JOIN permisos p ON rp.permiso_id = p.id WHERE p.clave = 'ver_cotizaciones'))
");
$stmtUsers->execute([$cot['empresa_id']]);
$usuarios = $stmtUsers->fetchAll();

$num_cot = $cot['numero_cotizacion'] ?: $cot['id'];

foreach ($usuarios as $usu) {
    // A. Notificación en Panel
    crearNotificacion(
        $pdo, 
        $usu['id'], 
        'comentario_cliente', 
        "Nuevo comentario en Cotización #$num_cot", 
        "El cliente {$cot['cliente_nombre']} ha comentado: " . substr($mensaje, 0, 50) . "...",
        "ver-cotizacion.php?id=" . $cot['id'] . "#chat",
        $cot['id']
    );

    // B. Notificación WhatsApp (Si tiene teléfono configurado)
    if (!empty($usu['telefono'])) {
        $msg_wa = "🔔 *Nuevo Comentario de Cliente*\n\n";
        $msg_wa .= "Cotización: *#{$num_cot}*\n";
        $msg_wa .= "Cliente: *{$cot['cliente_nombre']}*\n\n";
        $msg_wa .= "💬 \"{$mensaje}\"\n\n";
        $msg_wa .= "👀 Ver: " . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . str_replace("api_cliente_nota.php", "ver-cotizacion.php", $_SERVER['PHP_SELF']) . "?id=" . $cot['id'] . "#chat";
        
        enviarWhatsApp($pdo, $cot['empresa_id'], $usu['telefono'], $msg_wa);
    }
}

echo json_encode(['status' => 'success']);

