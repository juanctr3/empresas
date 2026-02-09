<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$id = $_POST['id'] ?? null;
$html = $_POST['contenido_html'] ?? '';
$empresa_id = getEmpresaId();

if (!$id || !$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID or Session']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE cotizaciones SET contenido_html = ? WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$html, $id, $empresa_id]);

    echo json_encode(['status' => 'success', 'message' => 'Diseño actualizado']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
