<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$empresa_id = getEmpresaId();
if (!$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada']);
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$identificacion = trim($_POST['identificacion'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$pais_codigo = trim($_POST['pais_codigo'] ?? '57'); // Default to 57 if not provided
$direccion = trim($_POST['direccion'] ?? ''); // Optional

if (empty($nombre)) {
    echo json_encode(['status' => 'error', 'message' => 'El nombre es obligatorio']);
    exit;
}

try {
    // Check duplication (by ID if provided)
    if (!empty($identificacion)) {
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id = ? AND identificacion = ?");
        $stmt->execute([$empresa_id, $identificacion]);
        if ($stmt->fetch()) {
             echo json_encode(['status' => 'error', 'message' => 'Ya existe un cliente con esa identificación']);
             exit;
        }
    }

    $es_cliente = isset($_POST['es_cliente']) ? (int)$_POST['es_cliente'] : 1; // Default 1 (Client)

    $token = md5(uniqid(rand(), true));
    $stmt = $pdo->prepare("INSERT INTO clientes (empresa_id, usuario_id, nombre, identificacion, email, telefono, pais_codigo, direccion, token_acceso, es_cliente) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$empresa_id, $_SESSION['user_id'], $nombre, $identificacion, $email, $telefono, $pais_codigo, $direccion, $token, $es_cliente]);
    $id = $pdo->lastInsertId();

    // Enviar Notificación de Bienvenida (Solo si hay email o teléfono)
    if (!empty($email) || !empty($telefono)) {
        require_once 'includes/client_notifications.php';
        enviarNotificacionBienvenida($pdo, $id);
    }

    echo json_encode([
        'status' => 'success', 
        'data' => [
            'id' => $id, 
            'nombre' => $nombre,
            'identificacion' => $identificacion,
            'token' => $token
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
