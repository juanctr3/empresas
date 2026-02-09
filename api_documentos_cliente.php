<?php
/**
 * API Documentos Cliente - CoticeFacil.com
 */

require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

$empresa_id = getEmpresaId();
if (!$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?? 0;
    $tipo_doc = $_POST['tipo_documento'] ?? 'Otro';
    $archivo = $_FILES['archivo'] ?? null;

    if ($archivo && $archivo['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/empresa_' . $empresa_id . '/clientes/' . $cliente_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = time() . '_' . basename($archivo['name']);
        $target_file = $upload_dir . $filename;

        if (move_uploaded_file($archivo['tmp_name'], $target_file)) {
            $stmt = $pdo->prepare("INSERT INTO clientes_documentos (empresa_id, cliente_id, nombre_archivo, tipo_documento, url_archivo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$empresa_id, $cliente_id, $archivo['name'], $tipo_doc, $target_file]);
            
            echo json_encode(['status' => 'success', 'message' => 'Documento subido']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al mover archivo']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se recibió archivo']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
