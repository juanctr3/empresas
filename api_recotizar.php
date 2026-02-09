<?php
// api_recotizar.php
require_once 'db.php';
require_once 'includes/client_notifications.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Obtener input JSON
$input = json_decode(file_get_contents('php://input'), true);
$cotizacion_id = $input['cotizacion_id'] ?? null;
$mensaje = $input['mensaje'] ?? '(Sin mensaje adjunto)';
$hash = $input['hash'] ?? '';

if (!$cotizacion_id || !$hash) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

try {
    // Verificar que coincida ID y Hash
    $stmt = $pdo->prepare("
        SELECT c.*, 
               cl.nombre as cliente_nombre, cl.email as cliente_email, cl.telefono as cliente_telefono,
               e.nombre as empresa_nombre, e.smtp_host, e.smtp_user, e.smtp_pass, e.smtp_port, e.smtp_from_email
        FROM cotizaciones c
        JOIN clientes cl ON c.cliente_id = cl.id
        JOIN empresas e ON c.empresa_id = e.id
        WHERE c.id = ? AND c.hash_publico = ?
    ");
    $stmt->execute([$cotizacion_id, $hash]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cot) {
        echo json_encode(['error' => 'Cotización no encontrada o acceso inválido']);
        exit;
    }

    // Opcional: Cambiar estado o marcar flag 'solicitud_pendiente'? 
    // Por ahora solo notificamos, sin cambiar estado crítico, quizás 'Borrador' o un estado nuevo 'En Revisión'.
    // El usuario pidió "Recotizar", lo cual implica que el admin debe editarla.
    // Cambiemos el estado a 'Borrador' (para que sea editable de nuevo si estaba bloqueada) 
    // O mejor no tocamos estado para no romper flujos, solo enviamos mail.
    // Decisión: No cambiar estado A MENOS que sea estrictamente necesario. Solo notificar.

    // Preparar datos cliente
    $datos_cliente = [
        'nombre' => $cot['cliente_nombre'],
        'email' => $cot['cliente_email'],
        'smtp_host' => $cot['smtp_host'],
        'smtp_user' => $cot['smtp_user'],
        'smtp_pass' => $cot['smtp_pass'],
        'smtp_port' => $cot['smtp_port'],
        'smtp_from_email' => $cot['smtp_from_email'],
        'empresa_nombre' => $cot['empresa_nombre']
    ];

    enviarSolicitudRecotizacion($pdo, $cot, $datos_cliente, $mensaje);

    // Registrar en historial
    $historia_msg = "Cliente solicitó recotización: " . substr($mensaje, 0, 50) . "...";
    $pdo->prepare("INSERT INTO cotizacion_historial (cotizacion_id, usuario_id, accion, detalles) VALUES (?, 0, 'Solicitud Recotización', ?)")
        ->execute([$cot['id'], $historia_msg]);

    echo json_encode(['status' => 'success', 'message' => 'Solicitud enviada correctamente']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}
