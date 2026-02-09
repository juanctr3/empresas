<?php
// Simulate session for debugging
session_start();
require_once 'db.php';
require_once 'includes/auth_helper.php';

// Force session variables if missing (for testing this script directly in browser)
if (!isset($_SESSION['user_id'])) {
    echo "<h1>No session active</h1>";
    // Try to find an admin to impersonate
    $stmt = $pdo->query("SELECT id, nombre, rol_id, empresa_id, is_super_admin FROM usuarios WHERE email LIKE '%admin%' LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_rol'] = 'Administrador'; // Simulate the problem role
        $_SESSION['is_super_admin'] = $user['is_super_admin'];
        $_SESSION['empresa_id'] = $user['empresa_id'];
        echo "Impersonating User: " . print_r($user, true) . "<br>";
    }
}

// Logic copied from api_crm_whatsapp.php (simplified for debug)
$empresa_id = getEmpresaId();
echo "Empresa ID: $empresa_id<br>";

$is_super_admin = $_SESSION['is_super_admin'] ?? false;
$role = strtolower($_SESSION['user_rol'] ?? '');
$is_admin = $is_super_admin || strpos($role, 'admin') !== false || $role === 'asesor';

echo "Role: $role, SuperAdmin: " . ($is_super_admin?1:0) . ", Is Admin Calculated: " . ($is_admin?1:0) . "<br>";

$permiso_ver_todos = tienePermiso('ver_todos_chats');
echo "Permiso ver todos: " . ($permiso_ver_todos?1:0) . "<br>";

$where = "WHERE c.empresa_id = ?";
$params = [$empresa_id];

if (!$is_admin && !$permiso_ver_todos) {
    echo "<b>Applying filtering (User assignments only)</b><br>";
    $usuario_actual = $_SESSION['user_id'];
    $where .= " AND (EXISTS (SELECT 1 FROM wa_chat_asignaciones wca WHERE wca.chat_id = c.id AND wca.usuario_id = ?) 
                OR NOT EXISTS (SELECT 1 FROM wa_chat_asignaciones wca2 WHERE wca2.chat_id = c.id))";
    $params[] = $usuario_actual;
} else {
    echo "<b>Showing ALL chats (Admin/Permitted)</b><br>";
}

$sql = "
    SELECT c.id, c.whatsapp_id, cl.nombre as cliente_nombre,
    (SELECT GROUP_CONCAT(CONCAT(u.id, '{SEP}', u.nombre) SEPARATOR '|') 
        FROM wa_chat_asignaciones wca 
        JOIN usuarios u ON wca.usuario_id = u.id 
        WHERE wca.chat_id = c.id) as asignados_raw
    FROM wa_chats c
    LEFT JOIN clientes cl ON c.cliente_id = cl.id 
    $where 
    ORDER BY c.fecha_ultimo_mensaje DESC
";

echo "<pre>$sql</pre>";
echo "Params: " . print_r($params, true) . "<br>";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Result Count: " . count($chats) . "</h2>";
    echo "<pre>";
    print_r($chats);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
