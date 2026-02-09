<?php
/**
 * Webhook Evolucionado para CoticeFacil.com
 * Atiende las notificaciones de Evolution API v2
 */

require_once 'db.php';

// Limpieza de inputs
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// LOCAL DEBUG: Log raw input
file_put_contents('log_whatsapp.txt', date('[Y-m-d H:i:s] ') . $input . PHP_EOL, FILE_APPEND);

if (!$data) exit;

$event = $data['event'] ?? '';
$instance = $data['instance'] ?? '';

// Identificar empresa por la instancia
$stmt = $pdo->prepare("SELECT id FROM empresas WHERE evolution_instance_name = ?");
$stmt->execute([$instance]);
$empresa = $stmt->fetch();

// Si no se encuentra por nombre de instancia, intentar por parámetro GET (fallback opcional)
$empresa_id = $empresa ? $empresa['id'] : ($_GET['empresa_id'] ?? null);

if (!$empresa_id) {
    file_put_contents('log_whatsapp_errors.txt', date('[Y-m-d H:i:s] ') . "No se encontró empresa para la instancia: $instance" . PHP_EOL, FILE_APPEND);
    exit;
}

if ($event === 'messages.upsert') {
    $msg = $data['data'] ?? null;
    if (!$msg) exit;

    $remoteJid = $msg['key']['remoteJid'] ?? '';
    $fromMe = $msg['key']['fromMe'] ?? false;
    $content = "";
    $pushName = $msg['pushName'] ?? 'Cliente';
    
    // Obviar grupos y estados
    if (strpos($remoteJid, '@g.us') !== false || strpos($remoteJid, 'status@broadcast') !== false) exit;

    // 1. Gestionar Chat
    $stmt_chat = $pdo->prepare("SELECT id FROM wa_chats WHERE empresa_id = ? AND whatsapp_id = ?");
    $stmt_chat->execute([$empresa_id, $remoteJid]);
    $chat = $stmt_chat->fetch();

    if (!$chat) {
        $numero = explode('@', $remoteJid)[0];
        $stmt_c = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id = ? AND (celular_contacto LIKE ? OR telefono LIKE ?)");
        $stmt_c->execute([$empresa_id, "%$numero%", "%$numero%"]);
        $cliente = $stmt_c->fetch();
        
        $stmt_ins = $pdo->prepare("INSERT INTO wa_chats (empresa_id, cliente_id, whatsapp_id, fecha_ultimo_mensaje) VALUES (?, ?, ?, NOW())");
        $stmt_ins->execute([$empresa_id, $cliente ? $cliente['id'] : null, $remoteJid]);
        $chat_id = $pdo->lastInsertId();
    } else {
        $chat_id = $chat['id'];
    }

    // 2. Extraer contenido (v2 OS structure)
    $messageBody = $msg['message'] ?? [];
    
    // Si es un mensaje editado o con contexto, el contenido real puede estar anidado
    if (isset($messageBody['conversation'])) {
        $content = $messageBody['conversation'];
    } elseif (isset($messageBody['extendedTextMessage']['text'])) {
        $content = $messageBody['extendedTextMessage']['text'];
    } elseif (isset($messageBody['imageMessage'])) {
        $content = "[Imagen]" . (isset($messageBody['imageMessage']['caption']) ? ": " . $messageBody['imageMessage']['caption'] : "");
    } elseif (isset($messageBody['videoMessage'])) {
        $content = "[Video]" . (isset($messageBody['videoMessage']['caption']) ? ": " . $messageBody['videoMessage']['caption'] : "");
    } elseif (isset($messageBody['documentMessage'])) {
        $content = "[Documento]" . (isset($messageBody['documentMessage']['caption']) ? ": " . $messageBody['documentMessage']['caption'] : "");
    } elseif (isset($messageBody['audioMessage'])) {
        $content = "[Audio]";
    } else {
        $content = "[Mensaje Multimedia/Sistema]";
    }

    // 3. Guardar Mensaje
    $stmt_msg = $pdo->prepare("
        INSERT INTO wa_mensajes (chat_id, direccion, contenido, tipo_mensaje, fecha_envio) 
        VALUES (?, ?, ?, 'texto', NOW())
    ");
    $stmt_msg->execute([$chat_id, $fromMe ? 'saliente' : 'entrante', $content]);

    // 4. Actualizar metadata del chat
    $pdo->prepare("UPDATE wa_chats SET ultimo_mensaje = ?, fecha_ultimo_mensaje = NOW(), visto_por_admin = ? WHERE id = ?")
        ->execute([$content, $fromMe ? 1 : 0, $chat_id]);
}
