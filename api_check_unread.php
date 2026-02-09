<?php
require_once 'db.php';
header('Content-Type: application/json');

// Este script cuenta los mensajes no leídos para el usuario/empresa actual
// Se asume que existe una tabla o lógica de chats/mensajes.
// Si no existe, devolveremos 0 por ahora para no romper el frontend.

$empresa_id = getEmpresaId();
$unread = 0;

try {
    // Verificar si existe la tabla de chats de whatsapp
    // Ajustar según esquema real (wa_chats, wa_messages, etc.)
    // Asumimos 'wa_chats' con columna 'unread_count' o similar
    
    // Primero, verificar si la tabla existe para evitar 500
    $stmtCheck = $pdo->prepare("SHOW TABLES LIKE 'wa_chats'");
    $stmtCheck->execute();
    
    if ($stmtCheck->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM wa_chats WHERE empresa_id = ? AND visto_por_admin = 0");
        $stmt->execute([$empresa_id]);
        $row = $stmt->fetch();
        $unread = (int)($row['total'] ?? 0);
    }
    
    echo json_encode([
        'status' => 'success',
        'unread_count' => $unread
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'unread_count' => 0,
        'message' => $e->getMessage()
    ]);
}
