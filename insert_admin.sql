-- Script para crear el usuario Administrador
-- Contraseña por defecto: admin123

INSERT INTO usuarios (empresa_id, rol_id, nombre, email, password, telefono, cargo, activo, is_super_admin) VALUES 
(
    1, -- Asume que la empresa ID 1 existe (creada en full_schema.sql)
    1, -- Asume que el rol ID 1 es Super Admin (o el primero creado)
    'Administrador Principal', 
    'admin@demo.com', 
    '$2y$10$R9h/cIPz0gi.URNNX3kh2OPST9/PgBkqquii.V3ilJppDcv.fTkJK', -- Hash de 'admin123'
    '+573000000000', 
    'Gerente General', 
    1, 
    1
);
