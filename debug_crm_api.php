<?php
// debug_crm_api.php
// Simulador CLI del request que hace el frontend para ver qué devuelve realmente

// Mock Session
session_start();
$_SESSION['user_id'] = 1; 
$_SESSION['user_rol'] = 'admin'; 
$_SESSION['user_nombre'] = 'Admin Debug';
$_SESSION['empresa_id'] = 1; // Asumimos ID 1 para debug

require_once 'db.php';
require_once 'includes/auth_helper.php';

// Mock getEmpresaId() helper override if needed, but db.php has it.
// Force manual if session fail
if (!getEmpresaId()) {
    echo "⚠️ Falló la sesión, forzando empresa_id = 1 para prueba.\n";
    $_SESSION['empresa_id'] = 1;
}

echo "🔍 Debugging get_chats action...\n";

try {
    $empresa_id = getEmpresaId();
    echo "Empresa ID: $empresa_id\n";

    $action = 'get_chats';
    $usuario_id = $_SESSION['user_id'];
    
    // Logic from api_crm_whatsapp.php
    $es_admin = ($_SESSION['user_rol'] === 'admin');
    
    // IMPORTANT: auth_helper functions like tienePermiso() need to work in CLI or fallback
    // We define a mock function if auth_helper didn't load correctly or relies on advanced session state
    if (!function_exists('tienePermiso')) {
        function tienePermiso($p) { return true; }
    }

    $seleccionar_chats = "SELECT c.*, cl.nombre as cliente_nombre FROM wa_chats c LEFT JOIN clientes cl ON c.cliente_id = cl.id WHERE c.empresa_id = ?";
    
    // Only if not admin logic... skipped for simplicity since we set admin
    
    $stmt = $pdo->prepare($seleccionar_chats . " ORDER BY c.fecha_ultimo_mensaje DESC");
    $stmt->execute([$empresa_id]);
    $chats = $stmt->fetchAll();
    
    echo "✅ Query executed. Rows found: " . count($chats) . "\n";
    
    // Check JSON encoding
    $json = json_encode(['status' => 'success', 'data' => $chats]);
    
    if ($json === false) {
        echo "❌ JSON Error: " . json_last_error_msg() . "\n";
    } else {
        echo "✅ JSON Output Preview:\n" . substr($json, 0, 200) . "...\n";
    }

} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}
?>
