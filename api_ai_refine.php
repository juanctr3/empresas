<?php
require_once 'db.php';
require_once 'includes/ai_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nombre = $input['nombre'] ?? '';
$detalles = $input['detalles'] ?? '';

if (empty($nombre)) {
    echo json_encode(['status' => 'error', 'message' => 'Nombre del producto requerido']);
    exit;
}

try {
    $ai = new AIHelper($pdo, getEmpresaId());
    
    // Prompt específico para mejorar descripciones de productos en cotizaciones
    $prompt = "Actúa como un experto en ventas y copywriting. ";
    $prompt .= "Tengo un producto/servicio llamado '$nombre'. ";
    if (!empty($detalles)) {
        $prompt .= "Actualmente tiene esta descripción: '$detalles'. ";
    }
    $prompt .= "Genera una descripción profesional, persuasiva y técnica (si aplica) de máximo 200 caracteres para incluir en una cotización formal. ";
    $prompt .= "Solo devuelve la descripción, sin introducciones ni comillas.";

    // Usar el proveedor configurado o por defecto
    $respuesta = $ai->generateTemplate('openai', $prompt); // Por defecto usamos openai, ajustado por AIHelper
    
    if ($respuesta) {
        $respuesta = trim(preg_replace('/^"|"$/', '', $respuesta));
        echo json_encode(['status' => 'success', 'description' => $respuesta]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'La IA no pudo generar el texto']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
