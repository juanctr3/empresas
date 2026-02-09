<?php
require_once 'db.php';
header('Content-Type: application/json');

$empresa_id = getEmpresaId();
$email = $_GET['email'] ?? '';
$telefono = $_GET['telefono'] ?? '';
$tipo = $_GET['tipo'] ?? 'cliente'; // 'cliente' or 'prospecto'
$exclude_id = $_GET['exclude_id'] ?? 0;

$response = [
    'exists' => false,
    'message' => ''
];

if (empty($email) && empty($telefono)) {
    echo json_encode($response);
    exit;
}

try {
    // Check Email
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id, nombre, es_cliente FROM clientes WHERE email = ? AND empresa_id = ? AND id != ?");
        $stmt->execute([$email, $empresa_id, $exclude_id]);
        $match = $stmt->fetch();
        
        if ($match) {
            $response['exists'] = true;
            $tipoStr = $match['es_cliente'] ? 'Cliente' : 'Prospecto';
            $response['message'] = "El email ya está registrado para el $tipoStr: " . $match['nombre'];
            echo json_encode($response);
            exit;
        }
    }

    // Check Phone (Celular/Telefono)
    if (!empty($telefono)) {
        // Check both celular_contacto and telefono fields
        $stmt = $pdo->prepare("SELECT id, nombre, es_cliente FROM clientes WHERE (celular_contacto = ? OR telefono = ?) AND empresa_id = ? AND id != ?");
        $stmt->execute([$telefono, $telefono, $empresa_id, $exclude_id]);
        $match = $stmt->fetch();
        
        if ($match) {
            $response['exists'] = true;
            $tipoStr = $match['es_cliente'] ? 'Cliente' : 'Prospecto';
            $response['message'] = "El teléfono ya está registrado para el $tipoStr: " . $match['nombre'];
            echo json_encode($response);
            exit;
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
