<?php
require_once 'db.php';
require_once 'includes/ai_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$prompt_original = $_POST['prompt'] ?? '';
$provider = $_POST['provider'] ?? 'openai';
$context = $_POST['context'] ?? '';
$style = $_POST['style'] ?? '';

if (empty($prompt_original)) {
    echo json_encode(['status' => 'error', 'message' => 'El prompt es requerido']);
    exit;
}

try {
    $ai = new AIHelper($pdo, getEmpresaId());
    
    // Enriquecer el prompt para asegurar calidad HTML
    $final_prompt = "Eres un experto diseñador web Frontend y especialista en TailwindCSS. \n";
    $final_prompt .= "TU OBJETIVO: Generar el código HTML crudo para una plantilla de cotización profesional y hermosa.\n\n";
    $final_prompt .= "CONTEXTO DEL NEGOCIO: $context\n";
    $final_prompt .= "ESTILO VISUAL: $style\n\n";
    $final_prompt .= "INSTRUCCIONES TÉCNICAS:\n";
    $final_prompt .= "1. Usa EXCLUSIVAMENTE TailwindCSS para los estilos via CDN.\n";
    $final_prompt .= "2. El diseño debe ser responsivo y optimizado para impresión (A4/Letter).\n";
    $final_prompt .= "3. NO incluyas etiquetas <html>, <head> o <body>. Solo el contenido dentro del body.\n";
    $final_prompt .= "4. Usa fuentes modernas (Outfit, Inter, etc).\n\n";
    
    $final_prompt .= "DICCIONARIO DE SHORTCODES (Úsalos tal cual):\n";
    $final_prompt .= "--- Empresa: ---\n";
    $final_prompt .= "- {LOGO} : Logotipo corporativo de nuestra empresa\n";
    $final_prompt .= "- {EMPRESA} : Nombre legal de nuestra empresa\n";
    $final_prompt .= "- {TEL} : Teléfono de contacto de nuestra empresa\n\n";
    
    $final_prompt .= "--- Cotización: ---\n";
    $final_prompt .= "- {NUMERO} o {NUMERO_COT} : Número identificador de la cotización\n";
    $final_prompt .= "- {TITULO} : Título o nombre del proyecto\n";
    $final_prompt .= "- {CLIENTE} : Nombre de la empresa del cliente\n";
    $final_prompt .= "- {CONTACTO_NOMBRE} : Nombre de contacto del cliente\n";
    $final_prompt .= "- {FECHA_VENCIMIENTO} : Fecha de vencimiento de la oferta\n\n";

    $final_prompt .= "--- Valores (Moneatarios): ---\n";
    $final_prompt .= "- {SUB} : Valor subtotal (sin impuestos)\n";
    $final_prompt .= "- {IVA} : Valor de los impuestos\n";
    $final_prompt .= "- {TOTAL} : Monto total de la inversión (resáltalo)\n\n";

    $final_prompt .= "--- Bloques Especiales: ---\n";
    $final_prompt .= "- {TABLA} o {TABLA_PRECIOS} : Inyecta la tabla de productos (Solo pon el tag)\n";
    $final_prompt .= "- {NOTAS} : Bloque de términos y condiciones legales\n";
    $final_prompt .= "- {REDES} o {REDES_SOCIALES} : Nuestras redes sociales\n";
    $final_prompt .= "- {CONFIAN} o {LOGOS_CLIENTES} : Logos de clientes recomendados\n\n";

    $final_prompt .= "PROMPT DEL USUARIO:\n $prompt_original \n";
    $final_prompt .= "\nResponde ÚNICAMENTE con el código HTML. Sin markdown, sin explicaciones.";

    $respuesta = $ai->generateTemplate($provider, $final_prompt);
    
    if ($respuesta) {
        // Limpiar markdown si la IA lo añade
        $html = trim($respuesta);
        $html = str_replace('```html', '', $html);
        $html = str_replace('```', '', $html);
        
        echo json_encode(['status' => 'success', 'html' => $html]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'La IA no devolvió contenido válido.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
