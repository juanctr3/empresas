<?php
require_once 'db.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? null;
$empresa_id = getEmpresaId();

if (!$id || !$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT contenido_html, plantilla_id FROM cotizaciones WHERE id = ? AND empresa_id = ?");
$stmt->execute([$id, $empresa_id]);
$cot = $stmt->fetch();

$html = $cot['contenido_html'];

// Si está vacío, cargar plantilla por defecto
if (empty($html) && $cot['plantilla_id']) {
    $stmtP = $pdo->prepare("SELECT contenido_html FROM plantillas WHERE id = ?");
    $stmtP->execute([$cot['plantilla_id']]);
    $html = $stmtP->fetchColumn();
    // Auto-update to link content immediately? Optional.
}

echo json_encode(['status' => 'success', 'html' => $html]);
?>
