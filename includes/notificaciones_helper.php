<?php
/**
 * Sistema de Notificaciones - Funciones Helper
 * Permite crear notificaciones desde cualquier parte del sistema
 */

/**
 * Crea una notificación para un usuario específico
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $usuario_id ID del usuario receptor
 * @param string $tipo Tipo de notificación (cotizacion_aceptada, cotizacion_rechazada, documento_compartido, etc.)
 * @param string $titulo Título breve de la notificación
 * @param string $mensaje Mensaje descriptivo
 * @param string|null $url URL opcional a la que redirigir al hacer clic
 * @param int|null $cotizacion_id ID de cotización relacionada (opcional)
 * @param int|null $documento_id ID de documento relacionado (opcional)
 * @return int ID de la notificación creada
 */
function crearNotificacion($pdo, $usuario_id, $tipo, $titulo, $mensaje, $url = null, $cotizacion_id = null, $documento_id = null) {
    // Crear tabla si no existe (lazy migration)
    $pdo->exec("CREATE TABLE IF NOT EXISTS notificaciones (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT NOT NULL,
        tipo VARCHAR(50) NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        mensaje TEXT,
        url VARCHAR(500),
        cotizacion_id INT NULL,
        documento_id INT NULL,
        leida TINYINT DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_lectura TIMESTAMP NULL,
        INDEX(usuario_id),
        INDEX(leida),
        INDEX(fecha_creacion),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    )");

    $stmt = $pdo->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, url, cotizacion_id, documento_id) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$usuario_id, $tipo, $titulo, $mensaje, $url, $cotizacion_id, $documento_id]);
    
    return $pdo->lastInsertId();
}

/**
 * Notifica a todos los usuarios de una empresa
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $empresa_id ID de la empresa
 * @param string $tipo Tipo de notificación
 * @param string $titulo Título breve
 * @param string $mensaje Mensaje descriptivo
 * @param string|null $url URL opcional
 * @param array $excluir_usuarios Array de IDs de usuarios a excluir (opcional)
 */
function notificarEmpresa($pdo, $empresa_id, $tipo, $titulo, $mensaje, $url = null, $excluir_usuarios = []) {
    $placeholders = str_repeat('?,', count($excluir_usuarios) - 1) . '?';
    $where_exclude = count($excluir_usuarios) > 0 ? " AND id NOT IN ($placeholders)" : "";
    
    $params = array_merge([$empresa_id], $excluir_usuarios);
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE empresa_id = ? $where_exclude");
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($usuarios as $usuario_id) {
        crearNotificacion($pdo, $usuario_id, $tipo, $titulo, $mensaje, $url);
    }
}

/**
 * Marca una notificación como leída
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $notificacion_id ID de la notificación
 * @param int $usuario_id ID del usuario (para verificar permisos)
 * @return bool True si se marcó correctamente
 */
function marcarNotificacionLeida($pdo, $notificacion_id, $usuario_id) {
    $stmt = $pdo->prepare("UPDATE notificaciones SET leida = 1, fecha_lectura = NOW() 
                          WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$notificacion_id, $usuario_id]);
    return $stmt->rowCount() > 0;
}

/**
 * Obtiene el contador de notificaciones no leídas
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @return int Número de notificaciones no leídas
 */
function contarNotificacionesNoLeidas($pdo, $usuario_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
    $stmt->execute([$usuario_id]);
    return (int)$stmt->fetchColumn();
}
?>
