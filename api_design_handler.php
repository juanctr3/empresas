<?php
require_once 'db.php';
header('Content-Type: application/json');

$empresa_id = getEmpresaId();

// POST: Update HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $html = $_POST['contenido_html'] ?? '';

    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'No ID provided']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE cotizaciones SET contenido_html = ? WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$html, $id, $empresa_id]);
        
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// GET: Fetch HTML
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'No ID provided']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT contenido_html, plantilla_id FROM cotizaciones WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        $cot = $stmt->fetch();

        if ($cot) {
            $html = $cot['contenido_html'];
            
            // If empty, try fetching from template if linked
            if (empty($html) && !empty($cot['plantilla_id'])) {
                $stmtP = $pdo->prepare("SELECT contenido_html FROM plantillas WHERE id = ?");
                $stmtP->execute([$cot['plantilla_id']]);
                $tpl = $stmtP->fetch();
                if ($tpl) $html = $tpl['contenido_html'];
            }

            echo json_encode(['status' => 'success', 'html' => $html]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
