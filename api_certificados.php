<?php
/**
 * API Certificados - CoticeFacil.com
 */

require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

$empresa_id = getEmpresaId();
if (!$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

switch ($action) {
    case 'listar':
        $stmt = $pdo->prepare("SELECT cer.*, cl.nombre as cliente_nombre 
                              FROM certificados cer
                              JOIN clientes cl ON cer.cliente_id = cl.id
                              WHERE cer.empresa_id = ? 
                              ORDER BY cer.fecha_vencimiento ASC");
        $stmt->execute([$empresa_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'crear':
        $cliente_id = $_POST['cliente_id'] ?? 0;
        $nombre = $_POST['nombre_certificado'] ?? '';
        $fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d');
        $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? null;
        $archivo = $_FILES['archivo'] ?? null;

        $target_file = null;
        if ($archivo && $archivo['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/empresa_' . $empresa_id . '/certificados/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($archivo['name']);
            $target_file = $upload_dir . $filename;
            move_uploaded_file($archivo['tmp_name'], $target_file);
        }

        $stmt = $pdo->prepare("INSERT INTO certificados (empresa_id, cliente_id, nombre_certificado, url_archivo, fecha_emision, fecha_vencimiento) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$empresa_id, $cliente_id, $nombre, $target_file, $fecha_emision, $fecha_vencimiento]);
        
        echo json_encode(['status' => 'success', 'message' => 'Certificado creado']);
        break;

    case 'eliminar':
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT url_archivo FROM certificados WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        $cer = $stmt->fetch();
        if ($cer) {
            if ($cer['url_archivo'] && file_exists($cer['url_archivo'])) unlink($cer['url_archivo']);
            $pdo->prepare("DELETE FROM certificados WHERE id = ?")->execute([$id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No encontrado']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}
