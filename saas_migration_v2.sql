-- Migración v2: Módulo de Archivos y Documentos

-- 1. Agregar límite de almacenamiento a los planes (en bytes)
ALTER TABLE planes ADD COLUMN limite_almacenamiento BIGINT DEFAULT 104857600 AFTER limite_usuarios; -- 100MB por defecto

-- Actualizar planes existentes con límites generosos para el MVP
UPDATE planes SET limite_almacenamiento = 524288000 WHERE id = 1; -- 500MB
UPDATE planes SET limite_almacenamiento = 2147483648 WHERE id = 2; -- 2GB
UPDATE planes SET limite_almacenamiento = 0 WHERE id = 3; -- Ilimitado

-- 2. Tabla de Documentos
CREATE TABLE IF NOT EXISTS documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_s3 VARCHAR(255) NOT NULL,
    bucket VARCHAR(100) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    tamano BIGINT NOT NULL,
    tipo_mime VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    folder_path VARCHAR(255) DEFAULT '/',
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX (empresa_id),
    INDEX (folder_path)
) ENGINE=InnoDB;

-- 3. Tabla de Documentos Compartidos
CREATE TABLE IF NOT EXISTS documentos_compartidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    hash_publico VARCHAR(64) UNIQUE NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    requiere_password TINYINT(1) DEFAULT 0,
    expira_at DATETIME DEFAULT NULL,
    vistas_max INT DEFAULT 0, -- 0 = Ilimitado
    total_vistas INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE CASCADE,
    INDEX (hash_publico)
) ENGINE=InnoDB;

-- 4. Tabla de Logs de Acceso a Documentos
CREATE TABLE IF NOT EXISTS documentos_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compartido_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referencia VARCHAR(255), -- Ej: 'WhatsApp', 'Email', 'Link'
    visto_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compartido_id) REFERENCES documentos_compartidos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Agregar campos de configuración global de S3 a la tabla empresas (opcional si es por empresa)
-- En este caso, usaremos variables de entorno para lo global, 
-- pero permitiremos que el super admin vea/edite la cuota de la empresa si es necesario.
ALTER TABLE empresas ADD COLUMN almacenamiento_usado BIGINT DEFAULT 0 AFTER plan_id;
