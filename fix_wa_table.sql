CREATE TABLE IF NOT EXISTS wa_configuracion_general (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT,
    api_url VARCHAR(255),
    api_key VARCHAR(255),
    webhook_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
