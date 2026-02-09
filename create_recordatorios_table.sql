CREATE TABLE IF NOT EXISTS cotizacion_recordatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cotizacion_id INT NOT NULL,
    usuario_id INT NOT NULL,
    asunto VARCHAR(255) NOT NULL,
    mensaje TEXT,
    fecha_programada DATETIME NOT NULL,
    emails_adicionales TEXT, -- JSON array or comma separated
    telefonos_adicionales TEXT, -- JSON array or comma separated
    notificar_cliente TINYINT(1) DEFAULT 1,
    estado ENUM('Pendiente', 'Enviado', 'Fallido', 'Cancelado') DEFAULT 'Pendiente',
    log_envio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
