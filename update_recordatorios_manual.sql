-- INSTRUCCIONES MANUALES --
-- Ejecuta estos comandos en tu gestor de base de datos (phpMyAdmin, Workbench, etc.)

-- 1. Agregar columna cliente_id
ALTER TABLE cotizacion_recordatorios ADD COLUMN cliente_id INT NULL AFTER cotizacion_id;

-- 2. Hacer opcional la cotización
ALTER TABLE cotizacion_recordatorios MODIFY COLUMN cotizacion_id INT NULL;

-- 3. Migrar datos existentes (vincular recordatorios antiguos a sus clientes)
UPDATE cotizacion_recordatorios r 
JOIN cotizaciones c ON r.cotizacion_id = c.id 
SET r.cliente_id = c.cliente_id 
WHERE r.cliente_id IS NULL AND r.cotizacion_id IS NOT NULL;
