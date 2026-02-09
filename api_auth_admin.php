<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

$identifier = $data['identifier'] ?? '';
$password = $data['password'] ?? '';

try {
    $stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre, e.nombre as empresa_nombre 
                           FROM usuarios u 
                           LEFT JOIN roles r ON u.rol_id = r.id 
                           LEFT JOIN empresas e ON u.empresa_id = e.id
                           WHERE u.email = ? AND u.is_super_admin = 1 AND u.activo = 1");
    $stmt->execute([$identifier]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Credenciales inválidas o acceso no autorizado']);
        exit;
    }

    // Login exitoso
    session_regenerate_id(true);
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_nombre'] = $usuario['nombre'];
    $_SESSION['user_email'] = $usuario['email'];
    $_SESSION['is_super_admin'] = true;
    $_SESSION['user_rol'] = $usuario['rol_nombre'] ?: 'Super Admin';
    $_SESSION['user_rol_id'] = $usuario['rol_id'];
    $_SESSION['empresa_id'] = $usuario['empresa_id'];
    $_SESSION['empresa_nombre'] = $usuario['empresa_nombre'] ?: 'CoticeFacil Global';

    // Cargar permisos de superadmin (Todos los del sistema)
    $stmt = $pdo->query("SELECT clave FROM permisos");
    $_SESSION['permisos'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    echo json_encode(['status' => 'success', 'message' => 'Bienvenido al Centro de Mando']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
