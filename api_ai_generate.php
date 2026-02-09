<?php
require_once 'db.php';
require_once 'includes/ai_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$prompt_text = $_POST['prompt'] ?? '';
$provider = $_POST['provider'] ?? 'openai';
$context = $_POST['context'] ?? 'General';

if (empty($prompt_text)) {
    $style = $_POST['style'] ?? 'Moderno, Minimalista, Premium';
    $prompt_text = AIHelper::getPremiumTemplatePrompt($context, $style);
}

$empresa_id = getEmpresaId();

if (!$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada o inválida. Por favor, reingresa al sistema.']);
    exit;
}

try {
    $ai = new AIHelper($pdo, $empresa_id);
    $html = $ai->generateTemplate($provider, $prompt_text);
    
    if ($html) {
        // Limpiar el código si viene con bloques de markdown
        $html = preg_replace('/^```html\s*|\s*```$/i', '', trim($html));
        echo json_encode(['status' => 'success', 'html' => $html]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'La IA no devolvió contenido. Intenta ajustar el prompt.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de IA: ' . $e->getMessage()]);
}
