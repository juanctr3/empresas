<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

// Auth check
$empresa_id = getEmpresaId();
if (!$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_s3_config') {
        $bucket = $_POST['bucket'] ?? '';
        $region = $_POST['region'] ?? '';
        $key = $_POST['key'] ?? '';
        $secret = $_POST['secret'] ?? '';
        
        if (!$bucket || !$region || !$key || !$secret) {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios']);
            exit;
        }

        try {
            // Update Database
            $stmt = $pdo->prepare("UPDATE empresas SET storage_type = 's3', s3_bucket = ?, s3_region = ?, s3_access_key = ?, s3_secret_key = ? WHERE id = ?");
            $stmt->execute([$bucket, $region, $key, $secret, $empresa_id]);

            echo json_encode(['status' => 'success', 'message' => 'Configuración S3 guardada y activada exitosamente']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar en base de datos: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Acción desconocida']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
