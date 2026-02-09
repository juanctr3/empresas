-- Migración para soporte SAAS

-- 1. Tabla de Planes
CREATE TABLE IF NOT EXISTS planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(15,2) DEFAULT 0,
    limite_cotizaciones INT DEFAULT 0, -- 0 = Ilimitado
    limite_usuarios INT DEFAULT 0,      -- 0 = Ilimitado
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Insertar Planes por defecto
INSERT IGNORE INTO planes (id, nombre, descripcion, precio, limite_cotizaciones, limite_usuarios) VALUES 
(1, 'Básico', 'Plan para pequeñas empresas', 29.99, 50, 2),
(2, 'Profesional', 'Plan para empresas en crecimiento', 59.99, 200, 5),
(3, 'Enterprise', 'Control total sin límites', 99.99, 0, 0);

-- 3. Modificar Tabla de Empresas para incluir el Plan
ALTER TABLE empresas ADD COLUMN plan_id INT AFTER nit;
ALTER TABLE empresas ADD FOREIGN KEY (plan_id) REFERENCES planes(id) ON DELETE SET NULL;

-- Asignar plan profesional por defecto a las empresas existentes
UPDATE empresas SET plan_id = 2 WHERE plan_id IS NULL;

-- 4. Modificar Tabla de Usuarios para rol Super Admin
ALTER TABLE usuarios ADD COLUMN is_super_admin TINYINT(1) DEFAULT 0 AFTER rol_id;

-- Marcar al primer usuario de la base de datos (si existe) como super admin para pruebas iniciales
-- O puedes usar un ID específico si lo conoces.
UPDATE usuarios SET is_super_admin = 1 WHERE id = 1;
