-- ESCALA COMPLETO DB SCHEMA (v9)
-- Generado para migración a CloudPanel

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    logo VARCHAR(255),
    color_hex VARCHAR(7) DEFAULT '#3b82f6',
    nit VARCHAR(50),
    moneda VARCHAR(10) DEFAULT 'USD',
    plan_id INT, 
    smsenlinea_secret VARCHAR(255),
    smsenlinea_wa_account VARCHAR(255),
    openai_api_key VARCHAR(255),
    gemini_api_key VARCHAR(255),
    claude_api_key VARCHAR(255),
    smtp_host VARCHAR(255),
    smtp_port INT,
    smtp_user VARCHAR(255),
    smtp_from_email VARCHAR(255),
    smtp_pass VARCHAR(255),
    smtp_encryption VARCHAR(20),
    almacenamiento_usado BIGINT DEFAULT 0,
    storage_type ENUM('local', 's3') DEFAULT 'local',
    s3_bucket VARCHAR(255) DEFAULT NULL,
    s3_region VARCHAR(50) DEFAULT NULL,
    s3_access_key VARCHAR(255),
    s3_secret_key VARCHAR(255),
    starting_quote_number INT DEFAULT 1,
    quote_prefix VARCHAR(20) DEFAULT '',
    quote_suffix VARCHAR(20) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS impuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre_impuesto VARCHAR(100) NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS unidades_medida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre_unidad VARCHAR(50) NOT NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    precio_base DECIMAL(15,2) NOT NULL,
    unidad_id INT,
    impuesto_id INT,
    imagen VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (unidad_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    FOREIGN KEY (impuesto_id) REFERENCES impuestos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    identificacion VARCHAR(50),
    pais_codigo VARCHAR(10),
    nombre_contacto VARCHAR(255),
    cargo_contacto VARCHAR(100),
    celular_contacto VARCHAR(50),
    direccion TEXT,
    telefono VARCHAR(50),
    email VARCHAR(150),
    es_cliente TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cotizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT NOT NULL,
    numero_cotizacion VARCHAR(20),
    fecha DATE NOT NULL,
    subtotal DECIMAL(15,2) DEFAULT 0,
    impuestos DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    estado ENUM('Borrador', 'Enviada', 'Aprobada', 'Rechazada') DEFAULT 'Borrador',
    notas TEXT,
    contenido_html LONGTEXT,
    config_pasos JSON, 
    mostrar_cantidad_como VARCHAR(50) DEFAULT 'unidad',
    hash_publico VARCHAR(64) UNIQUE, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cotizacion_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cotizacion_id INT NOT NULL,
    producto_id INT,
    nombre_producto VARCHAR(255),
    imagen VARCHAR(255),
    descripcion TEXT, 
    cantidad DECIMAL(10,2) NOT NULL,
    unidad_nombre VARCHAR(50), 
    precio_unitario DECIMAL(15,2) NOT NULL,
    impuesto_porcentaje DECIMAL(5,2),
    subtotal DECIMAL(15,2) NOT NULL,
    es_opcional TINYINT(1) DEFAULT 0, 
    seleccionado TINYINT(1) DEFAULT 1, 
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plantillas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    producto_id INT,
    nombre VARCHAR(255) NOT NULL,
    contenido_html TEXT,
    tipo VARCHAR(50) DEFAULT 'Servicios',
    producto_id INT,
    logo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rol_permisos (
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    PRIMARY KEY (rol_id, permiso_id),
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    rol_id INT,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255), 
    telefono VARCHAR(50),
    avatar VARCHAR(255),
    cargo VARCHAR(100),
    biografia TEXT,
    activo TINYINT(1) DEFAULT 1,
    is_super_admin TINYINT(1) DEFAULT 0, 
    ultimo_acceso DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    tipo ENUM('email', 'whatsapp') NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(15,2) DEFAULT 0,
    limite_cotizaciones INT DEFAULT 0,
    limite_usuarios INT DEFAULT 0,
    limite_almacenamiento BIGINT DEFAULT 104857600, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO planes (id, nombre, descripcion, precio, limite_cotizaciones, limite_usuarios, limite_almacenamiento) VALUES 
(1, 'Básico', 'Plan para pequeñas empresas', 29.99, 50, 2, 524288000),
(2, 'Profesional', 'Plan para empresas en crecimiento', 59.99, 200, 5, 2147483648),
(3, 'Enterprise', 'Control total sin límites', 99.99, 0, 0, 0);

ALTER TABLE empresas ADD CONSTRAINT fk_empresa_plan FOREIGN KEY (plan_id) REFERENCES planes(id) ON DELETE SET NULL;


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

CREATE TABLE IF NOT EXISTS documentos_compartidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    hash_publico VARCHAR(64) UNIQUE NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    requiere_password TINYINT(1) DEFAULT 0,
    expira_at DATETIME DEFAULT NULL,
    vistas_max INT DEFAULT 0,
    total_vistas INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE CASCADE,
    INDEX (hash_publico)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documentos_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compartido_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referencia VARCHAR(255),
    visto_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compartido_id) REFERENCES documentos_compartidos(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS ordenes_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT NOT NULL,
    cotizacion_id INT,
    numero_ot VARCHAR(20),
    estado ENUM('Borrador', 'Pendiente', 'En Proceso', 'Completada', 'Cancelada') DEFAULT 'Borrador',
    fecha_inicio DATE,
    fecha_entrega DATE,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wa_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT,
    whatsapp_id VARCHAR(50),
    asignado_a INT,
    tipo_asignacion VARCHAR(50),
    ultimo_mensaje TEXT,
    fecha_ultimo_mensaje DATETIME,
    visto_por_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wa_mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    empleado_id INT,
    direccion VARCHAR(20),
    contenido TEXT,
    nombre_empleado_copia VARCHAR(100),
    fecha_envio DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES wa_chats(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS certificados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT NOT NULL,
    cotizacion_id INT,
    nombre_certificado VARCHAR(255) NOT NULL,
    url_archivo VARCHAR(255),
    fecha_emision DATE,
    fecha_vencimiento DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT,
    tipo ENUM('Recordatorio', 'Vencimiento', 'Seguimiento', 'Sistema') DEFAULT 'Seguimiento',
    estado ENUM('Pendiente', 'Leida', 'Resuelta') DEFAULT 'Pendiente',
    fecha_alerta TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clientes_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    url_archivo VARCHAR(255) NOT NULL,
    tipo_documento VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;


INSERT IGNORE INTO permisos (clave, nombre, descripcion) VALUES 
('ver_dashboard', 'Ver Dashboard', 'Permite ver las estadísticas generales'),
('gestionar_empleados', 'Gestionar Empleados', 'Permite crear, editar y eliminar empleados'),
('gestionar_roles', 'Gestionar Roles', 'Permite configurar los roles y sus permisos'),
('ver_cotizaciones', 'Ver Cotizaciones', 'Permite ver el listado de cotizaciones'),
('crear_cotizaciones', 'Crear Cotizaciones', 'Permite crear nuevas cotizaciones'),
('editar_cotizaciones', 'Editar Cotizaciones', 'Permite modificar cotizaciones'),
('eliminar_cotizaciones', 'Eliminar Cotizaciones', 'Permite borrar cotizaciones'),
('configuracion_general', 'Configuración General', 'Acceso a los ajustes de la empresa');

COMMIT;
