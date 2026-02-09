<?php
/**
 * Clase para manejar la integración con Evolution API
 */
class EvolutionAPI {
    private $apiUrl;
    private $apiKey;
    private $instanceName;
    private $instanceToken;

    public function __construct($apiUrl, $apiKey, $instanceName = null, $instanceToken = null) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->instanceName = $instanceName;
        $this->instanceToken = $instanceToken;
    }

    private function request($endpoint, $method = 'GET', $params = [], $isInstanceRequest = true) {
        if (empty($this->apiUrl) || empty($this->apiKey)) {
            return ['status' => 'error', 'message' => 'Configuración de API incompleta'];
        }

        $url = $this->apiUrl . $endpoint;
        $headers = [
            'apikey: ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }

        $json = json_decode($response, true);
        return $json ?: ['status' => 'error', 'message' => 'Respuesta inválida', 'raw' => $response, 'http_code' => $httpCode];
    }

    /**
     * Gestión de Instancias
     */
    public function createInstance($name) {
        return $this->request('/instance/create', 'POST', [
            'instanceName' => $name,
            'token' => bin2hex(random_bytes(16)),
            'qrcode' => true
        ]);
    }

    public function getInstanceStatus() {
        if (!$this->instanceName) return ['status' => 'error', 'message' => 'Nombre de instancia no definido'];
        return $this->request("/instance/connectionState/{$this->instanceName}");
    }

    public function getQR() {
        if (!$this->instanceName) return ['status' => 'error', 'message' => 'Nombre de instancia no definido'];
        return $this->request("/instance/connect/{$this->instanceName}");
    }

    public function logout() {
        if (!$this->instanceName) return ['status' => 'error', 'message' => 'Nombre de instancia no definido'];
        return $this->request("/instance/logout/{$this->instanceName}", 'DELETE');
    }

    /**
     * Envío de Mensajes
     */
    public function enviarMensaje($telefono, $mensaje) {
        if (!$this->instanceName) return ['status' => 'error', 'message' => 'Nombre de instancia no definido'];
        
        $telefono = $this->formatNumber($telefono);
        
        return $this->request("/message/sendText/{$this->instanceName}", 'POST', [
            'number' => $telefono,
            'options' => ['delay' => 1200, 'presence' => 'composing', 'linkPreview' => true],
            'textMessage' => ['text' => $mensaje]
        ]);
    }

    public function enviarMedia($telefono, $caption, $url, $tipo = 'image') {
        if (!$this->instanceName) return ['status' => 'error', 'message' => 'Nombre de instancia no definido'];
        
        $telefono = $this->formatNumber($telefono);
        
        // El formato de Evolution API v2 OS para mediaMessage
        $params = [
            'number' => $telefono,
            'mediatype' => $tipo, // 'image', 'video', 'document', 'audio'
            'media' => $url,
            'caption' => $caption
        ];

        // Si es documento, podemos añadir fileName
        if ($tipo === 'document') {
            $params['fileName'] = basename($url);
        }

        return $this->request("/message/sendMedia/{$this->instanceName}", 'POST', $params);
    }

    private function formatNumber($number) {
        $number = preg_replace('/[^0-9]/', '', $number);
        // Evolution API suele preferir el número sin el + pero con código de país
        return $number;
    }
}
