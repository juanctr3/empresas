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

$id = $_POST['id'] ?? 0;
$nombre = trim($_POST['nombre'] ?? '');
$identificacion = trim($_POST['identificacion'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$celular = trim($_POST['celular_contacto'] ?? '');
$pais_codigo = trim($_POST['pais_codigo'] ?? '57');
$direccion = trim($_POST['direccion'] ?? '');
$contacto = trim($_POST['nombre_contacto'] ?? '');
$cargo = trim($_POST['cargo_contacto'] ?? '');

if (empty($id) || empty($nombre)) {
    echo json_encode(['status' => 'error', 'message' => 'ID y Nombre son obligatorios']);
    exit;
}

try {
    // 1. Verificar Duplicidad de Identificación (Excluyendo al actual)
    if (!empty($identificacion)) {
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id = ? AND identificacion = ? AND id != ?");
        $stmt->execute([$empresa_id, $identificacion, $id]);
        if ($stmt->fetch()) {
             echo json_encode(['status' => 'error', 'message' => 'Ya existe otro cliente con esa identificación']);
             exit;
        }
    }

    // 2. Actualizar
    $sql = "UPDATE clientes SET 
            nombre = ?, 
            identificacion = ?, 
            email = ?, 
            telefono = ?, 
            celular_contacto = ?, 
            pais_codigo = ?, 
            direccion = ?, 
            nombre_contacto = ?, 
            cargo_contacto = ? 
            WHERE id = ? AND empresa_id = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $nombre, 
        $identificacion, 
        $email, 
        $telefono, 
        $celular, 
        $pais_codigo, 
        $direccion, 
        $contacto, 
        $cargo, 
        $id, 
        $empresa_id
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Información actualizada correctamente']);

} catch (Exception $e) {
    error_log("Error updating client: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}
