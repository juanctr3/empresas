<?php
/**
 * Webhook para SMSEnLinea.com - CRM CoticeFacil
 */

require_once 'db.php';

// Limpieza y log
$input = file_get_contents('php://input');
$payload = json_decode($input, true) ?: $_REQUEST;
// Log all attempts to help debug
$headers = getallheaders();
$debug_info = [
    'time' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'get' => $_GET,
    'headers' => $headers,
    'input' => $input
];
file_put_contents('log_smsenlinea.txt', json_encode($debug_info, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

$empresa_id = $_GET['empresa_id'] ?? null;
if (!$empresa_id) {
    echo "Falta empresa_id. Por favor configura el webhook como: wa_webhook_sms.php?empresa_id=TU_ID";
    exit;
}

if (isset($payload['type']) && $payload['type'] === 'whatsapp') {
    $data = $payload['data'] ?? [];
    $remitente = $data['phone'] ?? null;
    $mensaje = $data['message'] ?? '';
    $adjunto = $data['attachment'] ?? null;
    
    if ($remitente) {
        // Normalizar número a solo dígitos para comparación interna
        $whatsapp_id = preg_replace('/[^0-9+]/', '', $remitente);
        
        // 1. Obtener o crear Chat
        $stmt_chat = $pdo->prepare("SELECT id FROM wa_chats WHERE empresa_id = ? AND (whatsapp_id = ? OR whatsapp_id LIKE ?)");
        $stmt_chat->execute([$empresa_id, $whatsapp_id, "%$whatsapp_id"]);
        $chat = $stmt_chat->fetch();

        if (!$chat) {
            $stmt_cli = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id = ? AND (celular_contacto LIKE ? OR telefono LIKE ?)");
            $stmt_cli->execute([$empresa_id, "%$whatsapp_id%", "%$whatsapp_id%"]);
            $cliente = $stmt_cli->fetch();
            
            $stmt_ins = $pdo->prepare("INSERT INTO wa_chats (empresa_id, cliente_id, whatsapp_id, tipo_asignacion, fecha_ultimo_mensaje) VALUES (?, ?, ?, 'aleatoria', NOW())");
            $stmt_ins->execute([$empresa_id, $cliente ? $cliente['id'] : null, $whatsapp_id]);
            $chat_id = $pdo->lastInsertId();
        } else {
            $chat_id = $chat['id'];
        }

        // 2. Guardar Mensaje
        $contenido = $mensaje . ($adjunto ? " [Adjunto: $adjunto]" : "");
        $stmt_msg = $pdo->prepare("INSERT INTO wa_mensajes (chat_id, direccion, contenido, tipo_mensaje, fecha_envio) VALUES (?, 'entrante', ?, 'texto', NOW())");
        $stmt_msg->execute([$chat_id, $contenido]);

        $pdo->prepare("UPDATE wa_chats SET ultimo_mensaje = ?, fecha_ultimo_mensaje = NOW(), visto_por_admin = 0 WHERE id = ?")
            ->execute([$contenido, $chat_id]);
            
        echo "OK";
    }
}
echo "Heartbeat SMSEnLinea";

