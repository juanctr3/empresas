-- El script comienza directamente con las tablas ya que la BD se crea desde el panel.

-- Tabla de Empresas
CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    logo VARCHAR(255),
    color_hex VARCHAR(7) DEFAULT '#3b82f6',
    nit VARCHAR(50),
    moneda VARCHAR(10) DEFAULT 'USD',
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de Impuestos
CREATE TABLE IF NOT EXISTS impuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre_impuesto VARCHAR(100) NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Unidades de Medida
CREATE TABLE IF NOT EXISTS unidades_medida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre_unidad VARCHAR(50) NOT NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Productos
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    precio_base DECIMAL(15,2) NOT NULL,
    unidad_id INT,
    impuesto_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (unidad_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    FOREIGN KEY (impuesto_id) REFERENCES impuestos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Insertar una empresa por defecto para la Parte 1
INSERT INTO empresas (nombre, nit, moneda) VALUES ('Mi Empresa Demo', '123456789-0', 'USD');

-- Tabla de Clientes
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Cotizaciones
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Detalles de la Cotización (Los productos incluidos)
CREATE TABLE IF NOT EXISTS cotizacion_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cotizacion_id INT NOT NULL,
    producto_id INT,
    nombre_producto VARCHAR(255),
    cantidad DECIMAL(10,2) NOT NULL,
    precio_unitario DECIMAL(15,2) NOT NULL,
    impuesto_porcentaje DECIMAL(5,2),
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de Plantillas de Propuesta
CREATE TABLE IF NOT EXISTS plantillas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    producto_id INT,
    nombre VARCHAR(255) NOT NULL,
    contenido_html TEXT,
    tipo VARCHAR(50) DEFAULT 'Servicios',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de Roles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Permisos
CREATE TABLE IF NOT EXISTS permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT
) ENGINE=InnoDB;

-- Relación Roles-Permisos
CREATE TABLE IF NOT EXISTS rol_permisos (
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    PRIMARY KEY (rol_id, permiso_id),
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de Usuarios (Empleados)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    rol_id INT,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(50),
    avatar VARCHAR(255),
    cargo VARCHAR(100),
    biografia TEXT,
    activo TINYINT(1) DEFAULT 1,
    ultimo_acceso DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla para códigos OTP
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    tipo ENUM('email', 'whatsapp') NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insertar algunos permisos básicos
INSERT IGNORE INTO permisos (clave, nombre, descripcion) VALUES 
('ver_dashboard', 'Ver Dashboard', 'Permite ver las estadísticas generales'),
('gestionar_empleados', 'Gestionar Empleados', 'Permite crear, editar y eliminar empleados'),
('gestionar_roles', 'Gestionar Roles', 'Permite configurar los roles y sus permisos'),
('ver_cotizaciones', 'Ver Cotizaciones', 'Permite ver el listado de cotizaciones'),
('crear_cotizaciones', 'Crear Cotizaciones', 'Permite crear nuevas cotizaciones'),
('editar_cotizaciones', 'Editar Cotizaciones', 'Permite modificar cotizaciones'),
('eliminar_cotizaciones', 'Eliminar Cotizaciones', 'Permite borrar cotizaciones'),
('configuracion_general', 'Configuración General', 'Acceso a los ajustes de la empresa');

