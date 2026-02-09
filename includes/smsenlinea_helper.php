<?php
/**
 * Clase para manejar la integración con SMSenlinea.com
 */
class SMSEnLinea {
    private $secret;
    private $account;
    private $baseUrl = "https://whatsapp.smsenlinea.com/api";

    public function __construct($secret, $account) {
        $this->secret = $secret;
        $this->account = $account;
    }

    private function request($endpoint, $params) {
        if (empty($this->secret) || empty($this->account)) {
            return ['status' => 'error', 'message' => 'Credenciales API no configuradas'];
        }

        $params['secret'] = $this->secret;
        $params['account'] = $this->account;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }

        $json = json_decode($response, true);
        return $json ?: ['status' => 'error', 'message' => 'Respuesta inválida API', 'raw' => $response];
    }

    public function enviarMensaje($telefono, $mensaje, $pais_codigo = '') {
        // Limpieza básica de teléfono (E.164 o local)
        $telefono = preg_replace('/[^0-9+]/', '', $telefono);
        
        // Si no empieza con +, procesamos con el código de país
        if (strpos($telefono, '+') !== 0) {
            // Si el teléfono tiene 10 dígitos y tenemos código de país, los unimos
            if (strlen($telefono) === 10 && !empty($pais_codigo)) {
                $telefono = '+' . preg_replace('/[^0-9]/', '', $pais_codigo) . $telefono;
            } elseif (strlen($telefono) >= 10) {
                // Si ya parece tener código de país o es largo, solo ponemos el +
                $telefono = '+' . $telefono;
            }
        }
        
        return $this->request('/send/whatsapp', [
            'recipient' => $telefono,
            'type' => 'text',
            'message' => $mensaje,
            'priority' => 1
        ]);
    }

    public function enviarMedia($telefono, $mensaje, $url_archivo, $tipo_archivo = 'image', $pais_codigo = '') {
        $telefono = preg_replace('/[^0-9+]/', '', $telefono);
        
        if (strpos($telefono, '+') !== 0) {
            if (strlen($telefono) === 10 && !empty($pais_codigo)) {
                $telefono = '+' . preg_replace('/[^0-9]/', '', $pais_codigo) . $telefono;
            } elseif (strlen($telefono) >= 10) {
                $telefono = '+' . $telefono;
            }
        }

        return $this->request('/send/whatsapp', [
            'recipient' => $telefono,
            'type' => 'media',
            'message' => $mensaje,
            'media_url' => $url_archivo,
            'media_type' => $tipo_archivo,
            'priority' => 1
        ]);
    }
}
?>
