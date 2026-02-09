<?php

class AIHelper {
    private $pdo;
    private $empresa_id;
    private $config;

    public function __construct($pdo, $empresa_id) {
        $this->pdo = $pdo;
        $this->empresa_id = $empresa_id;
        $this->loadConfig();
    }

    private function loadConfig() {
        if (!$this->empresa_id) {
            $this->config = false;
            return;
        }
        $stmt = $this->pdo->prepare("SELECT openai_api_key, gemini_api_key, claude_api_key FROM empresas WHERE id = ?");
        $stmt->execute([$this->empresa_id]);
        $this->config = $stmt->fetch();
    }

    public function generateTemplate($provider, $prompt) {
        switch ($provider) {
            case 'openai':
                return $this->callOpenAI($prompt);
            case 'gemini':
                return $this->callGemini($prompt);
            case 'claude':
                return $this->callClaude($prompt);
            default:
                throw new Exception("Proveedor de IA no válido");
        }
    }

    private function callOpenAI($prompt) {
        if (!isset($this->config['openai_api_key']) || !$this->config['openai_api_key']) {
            throw new Exception("Clave de OpenAI no configurada en el perfil de empresa");
        }
        $api_key = $this->config['openai_api_key'];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'Eres un diseñador experto en UI/UX. Generas solo código HTML compatible con Tailwind CSS para plantillas de cotización.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7
        ]));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error de conexión (CURL): " . $error_msg);
        }
        $data = json_decode($response, true);
        curl_close($ch);

        if (isset($data['error'])) {
            throw new Exception("Error de OpenAI: " . ($data['error']['message'] ?? 'Error desconocido'));
        }

        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function callGemini($prompt) {
        if (!isset($this->config['gemini_api_key']) || !$this->config['gemini_api_key']) {
            throw new Exception("Clave de Gemini no configurada");
        }
        $api_key = $this->config['gemini_api_key'];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'contents' => [
                ['parts' => [['text' => "Actúa como diseñador experto. Solo responde con el código HTML/Tailwind solicitado. Prompt: " . $prompt]]]
            ]
        ]));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error de conexión (Gemini): " . $error_msg);
        }
        $data = json_decode($response, true);
        curl_close($ch);

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    private function callClaude($prompt) {
        if (!isset($this->config['claude_api_key']) || !$this->config['claude_api_key']) {
            throw new Exception("Clave de Claude no configurada");
        }
        $api_key = $this->config['claude_api_key'];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'claude-3-5-sonnet-20240620',
            'max_tokens' => 4000,
            'messages' => [
                ['role' => 'user', 'content' => "Eres un diseñador experto. Solo responde con el código HTML/Tailwind solicitado. Prompt: " . $prompt]
            ]
        ]));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error de conexión (Claude): " . $error_msg);
        }
        $data = json_decode($response, true);
        curl_close($ch);

        return $data['content'][0]['text'] ?? null;
    }

    public static function getPremiumTemplatePrompt($context, $style = 'Moderno, Minimalista, Premium') {
        return "Actúa como un Director de Arte y Diseñador Web Senior experto en UI/UX, especializado en el sector: {$context}.
Tu objetivo es generar el código HTML crudo para una PLANTILLA DE COTIZACIÓN COMERCIAL sofisticada, usando Tailwind CSS.

--- GUÍA DE ESTILO VISUAL ({$style}) ---
1. ESTÉTICA: Usa Glassmorphism (bg-white/70 backdrop-blur-xl), bordes suaves (rounded-[3rem]), sombras profundas y gradientes elegantes.
2. TIPOGRAFÍA: Importa y usa fuentes como 'Outfit' o 'Plus Jakarta Sans'.
3. PRINT: El diseño debe ser impecable en papel (evita fondos negros masivos).

--- DICCIONARIO DE SHORTCODES (Úsalos exactamente así) ---
Estos tags serán reemplazados por datos reales. NO inventes el contenido de estos tags:

1. IDENTIDAD (Nuestra Empresa):
   - {LOGO} : Espacio para nuestro logotipo corporativo (ubícalo en un lugar destacado).
   - {EMPRESA} : El nombre legal de nuestra empresa.
   - {TEL} : Nuestro teléfono de contacto comercial.

2. ENCABEZADO DE PROPUESTA:
   - {NUMERO} o {NUMERO_COT} : El identificador único de la cotización (ej: #1001).
   - {TITULO} : El nombre del proyecto o servicio que se está cotizando.
   - {FECHA_VENCIMIENTO} : Fecha en la que expira la oferta.

3. INFORMACIÓN DEL CLIENTE:
   - {CLIENTE} : Nombre de la empresa o entidad que recibirá la propuesta.
   - {CONTACTO_NOMBRE} : Nombre de la persona específica a la que nos dirigimos.

4. DATOS ECONÓMICOS:
   - {SUB} : Subtotal antes de impuestos.
   - {IVA} : Valor total de los impuestos.
   - {TOTAL} : Monto final de la inversión (resáltalo visualmente).

5. BLOQUES DINÁMICOS:
   - {TABLA} o {TABLA_PRECIOS} : Inserta la tabla de productos/servicios (Solo pon el tag, el sistema la dibuja).
   - {NOTAS} : Inyecta los términos, condiciones y observaciones legales.
   - {REDES} o {REDES_SOCIALES} : Iconos de nuestras redes sociales (Facebook, Instagram, etc).
   - {CONFIAN} o {LOGOS_CLIENTES} : Grid de logos de empresas que nos recomiendan.

--- ESTRUCTURA SUGERIDA ---
- Encabezado: Logo + Datos de Empresa + Info de la Cotización.
- Cuerpo: Saludo personalizado ({CONTACTO_NOMBRE}), Contexto de la Propuesta ({TITULO}).
- Bloque Central: El tag {TABLA} para los precios.
- Cierre: Resumen de Valores ({TOTAL} destacado), {NOTAS}, {REDES} y el bloque de confianza {CONFIAN}.

REQUISITO TÉCNICO:
Responde SOLO con el código HTML dentro de un div: <div class='max-w-5xl mx-auto p-4 md:p-12 ...'>. 
Sin etiquetas <html>, <head> o <body>. Sin explicaciones adicionales.";
    }
}
