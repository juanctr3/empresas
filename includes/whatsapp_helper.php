<?php
/**
 * Helper unificado para envío de WhatsApp
 * Centraliza la lógica para smsenlinea.com y Evolution API
 */

require_once __DIR__ . '/smsenlinea_helper.php';
require_once __DIR__ . '/evolution_api_helper.php';

/**
 * Envía un mensaje de WhatsApp detectando el proveedor configurado para la empresa
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $empresa_id ID de la empresa enviadora
 * @param string $telefono Número de destino
 * @param string $mensaje Contenido del mensaje
 * @param string|null $media_url URL opcional de archivo adjunto
 * @param string $media_type Tipo de archivo (image, video, document, audio)
 * @param string $pais_codigo Código de país opcional
 * @return array ['status' => 'success/error', 'message' => '...']
 */
function enviarWhatsApp($pdo, $empresa_id, $telefono, $mensaje, $media_url = null, $media_type = 'image', $pais_codigo = '') {
    try {
        // Obtener configuración de la empresa
        $stmt = $pdo->prepare("SELECT wa_provider, smsenlinea_secret, smsenlinea_wa_account, 
                                     evolution_api_url, evolution_api_key, evolution_instance_name, evolution_instance_token 
                              FROM empresas WHERE id = ?");
        $stmt->execute([$empresa_id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emp) {
            return ['status' => 'error', 'message' => 'Empresa no encontrada'];
        }

        $provider = $emp['wa_provider'] ?? 'smsenlinea';

        if ($provider === 'evolution') {
            $wa = new EvolutionAPI(
                $emp['evolution_api_url'], 
                $emp['evolution_api_key'], 
                $emp['evolution_instance_name'], 
                $emp['evolution_instance_token']
            );
            
            if ($media_url) {
                return $wa->enviarMedia($telefono, $mensaje, $media_url, $media_type);
            } else {
                return $wa->enviarMensaje($telefono, $mensaje);
            }
        } else {
            // Predeterminado: smsenlinea
            $wa = new SMSEnLinea(
                $emp['smsenlinea_secret'], 
                $emp['smsenlinea_wa_account']
            );

            if ($media_url) {
                return $wa->enviarMedia($telefono, $mensaje, $media_url, $media_type, $pais_codigo);
            } else {
                return $wa->enviarMensaje($telefono, $mensaje, $pais_codigo);
            }
        }

    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Error en helper WhatsApp: ' . $e->getMessage()];
    }
}
