<?php
/**
 * API SaaS Metrics - CoticeFacil.com
 * Obtiene estadísticas detalladas de una empresa para el Super Admin.
 */

require_once 'db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID de empresa requerido']);
    exit;
}

// Métricas de Uso
$metrics = [];

// 1. Usuarios
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = ?");
$stmt->execute([$id]);
$metrics['usuarios'] = $stmt->fetchColumn();

// 2. Cotizaciones (Por estado)
$stmt = $pdo->prepare("SELECT estado, COUNT(*) as total FROM cotizaciones WHERE empresa_id = ? GROUP BY estado");
$stmt->execute([$id]);
$metrics['cotizaciones'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Órdenes de Trabajo
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ordenes_trabajo WHERE empresa_id = ?");
$stmt->execute([$id]);
$metrics['ordenes'] = $stmt->fetchColumn();

// 4. Clientes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE empresa_id = ?");
$stmt->execute([$id]);
$metrics['clientes'] = $stmt->fetchColumn();

// 5. Almacenamiento
$stmt = $pdo->prepare("SELECT almacenamiento_usado FROM empresas WHERE id = ?");
$stmt->execute([$id]);
$metrics['storage'] = $stmt->fetchColumn();

// 6. Última actividad (Basado en cotizaciones)
$stmt = $pdo->prepare("SELECT MAX(created_at) FROM cotizaciones WHERE empresa_id = ?");
$stmt->execute([$id]);
$metrics['ultima_actividad'] = $stmt->fetchColumn();

echo json_encode([
    'status' => 'success',
    'data' => $metrics
]);
