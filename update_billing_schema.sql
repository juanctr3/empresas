-- Actualización para Módulo de Facturación

-- 1. Actualizar tabla EMPRESAS para configuración de facturación
ALTER TABLE empresas 
ADD COLUMN starting_invoice_number INT DEFAULT 1,
ADD COLUMN invoice_prefix VARCHAR(20) DEFAULT 'FAC-',
ADD COLUMN invoice_suffix VARCHAR(20) DEFAULT '';

-- 2. Crear tabla FACTURAS
CREATE TABLE IF NOT EXISTS facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cliente_id INT NOT NULL,
    cotizacion_id INT, -- Referencia opcional a la cotización origen
    numero_factura VARCHAR(50) NOT NULL,
    fecha_emision DATE NOT NULL,
    fecha_vencimiento DATE,
    estado ENUM('Borrador', 'Enviada', 'Pagada', 'Anulada') DEFAULT 'Borrador',
    subtotal DECIMAL(15,2) DEFAULT 0,
    impuestos DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    notas TEXT,
    notas_internas TEXT,
    contenido_html LONGTEXT, -- Para guardar el diseño específico de la factura
    hash_publico VARCHAR(64) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE SET NULL,
    INDEX (numero_factura),
    INDEX (estado)
) ENGINE=InnoDB;

-- 3. Crear tabla FACTURA_DETALLES
CREATE TABLE IF NOT EXISTS factura_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    producto_id INT,
    nombre_producto VARCHAR(255),
    descripcion TEXT,
    cantidad DECIMAL(10,2) NOT NULL,
    precio_unitario DECIMAL(15,2) NOT NULL,
    impuesto_porcentaje DECIMAL(5,2) DEFAULT 0,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Crear tabla FACTURA_ARCHIVOS (Adjuntos)
CREATE TABLE IF NOT EXISTS factura_archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100),
    tamano BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
