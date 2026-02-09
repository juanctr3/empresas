<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'includes/auth_helper.php';

// session_start(); // Handled by db.php

header('Content-Type: text/plain');

echo "Debug Info:\n\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NULL') . "\n";
echo "Empresa ID: " . ($_SESSION['empresa_id'] ?? 'NULL') . "\n";
echo "Rol: " . ($_SESSION['user_rol'] ?? 'NULL') . "\n";

echo "Has ver_todos_chats? " . (tienePermiso('ver_todos_chats') ? 'YES' : 'NO') . "\n";
echo "Permissions Count: " . count($_SESSION['permisos'] ?? []) . "\n";
echo "Permissions: " . implode(', ', $_SESSION['permisos'] ?? []) . "\n\n";

$empresa_id = getEmpresaId();
$usuario_actual = $_SESSION['user_id'] ?? 0;
$permiso_ver_todos = tienePermiso('ver_todos_chats');
$is_admin = ($_SESSION['user_rol'] ?? '') === 'admin';

echo "Query logic check:\n";
$where = "WHERE c.empresa_id = ?";
$params = [$empresa_id];

if (!$is_admin && !$permiso_ver_todos) {
    echo "Applying FILTER: Only assigned chats.\n";
    $where .= " AND EXISTS (SELECT 1 FROM wa_chat_asignaciones wca WHERE wca.chat_id = c.id AND wca.usuario_id = ?)";
    $params[] = $usuario_actual;
} else {
    echo "Showing ALL chats (Admin or Permission).\n";
}

$sql = "
    SELECT c.id, c.whatsapp_id, cl.nombre as cliente_nombre
    FROM wa_chats c
    LEFT JOIN clientes cl ON c.cliente_id = cl.id 
    $where 
    ORDER BY c.fecha_ultimo_mensaje DESC
    LIMIT 5
";

echo "SQL: $sql\n";
echo "Params: " . json_encode($params) . "\n\n";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($chats) . " chats.\n";
    print_r($chats);
} catch (Exception $e) {
    echo "SQL Error: " . $e->getMessage();
}
