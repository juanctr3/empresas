CREATE TABLE IF NOT EXISTS wa_instancias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT, 
    instancia_nombre VARCHAR(100), 
    estado_conexion ENUM('CONNECTED', 'DISCONNECTED', 'CONNECTING') DEFAULT 'DISCONNECTED',
    token_instancia VARCHAR(255), 
    numero_vinculado VARCHAR(20), 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wa_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT,
    cliente_id INT, 
    whatsapp_id VARCHAR(50), 
    visto_por_admin TINYINT(1) DEFAULT 0,
    asignado_a INT NULL, 
    tipo_asignacion ENUM('manual', 'aleatoria') DEFAULT 'manual',
    ultimo_mensaje TEXT,
    fecha_ultimo_mensaje TIMESTAMP NULL,
    INDEX (whatsapp_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (asignado_a) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wa_mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT,
    empleado_id INT NULL, 
    direccion ENUM('entrante', 'saliente'),
    contenido TEXT,
    tipo_mensaje ENUM('texto', 'imagen', 'pdf', 'audio') DEFAULT 'texto',
    url_archivo VARCHAR(255), 
    nombre_empleado_copia VARCHAR(100), 
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES wa_chats(id) ON DELETE CASCADE,
    FOREIGN KEY (empleado_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
