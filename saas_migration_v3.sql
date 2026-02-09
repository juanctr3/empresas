-- Migración v3: Configuración de Almacenamiento Dinámico
ALTER TABLE empresas 
ADD COLUMN storage_type ENUM('local', 's3') DEFAULT 'local' AFTER almacenamiento_usado,
ADD COLUMN s3_bucket VARCHAR(255) DEFAULT NULL AFTER storage_type,
ADD COLUMN s3_region VARCHAR(50) DEFAULT NULL AFTER s3_bucket,
ADD COLUMN s3_access_key VARCHAR(255) DEFAULT NULL AFTER s3_region,
ADD COLUMN s3_secret_key VARCHAR(255) DEFAULT NULL AFTER s3_access_key;
