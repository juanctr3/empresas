<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Configuración de la base de datos
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}
$db_host = getenv('DB_HOST');
// Forzamos localhost en Windows si no estamos explícitamente en Docker
if (PHP_OS_FAMILY === 'Windows' && ($db_host === 'db' || empty($db_host))) {
    $db_host = 'localhost';
}
$db_name = getenv('DB_NAME') ?: 'coticefacil-db';
$db_user = getenv('DB_USER') ?: 'cotice-user';
$db_pass = getenv('DB_PASSWORD') ?: 'cotice_temp_123';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Auto-migración Robusta (Independiente de Sesión para desarrollo)
    try {
        // 1. Migración Clientes (Token Acceso)
        $colsClientes = $pdo->query("SHOW COLUMNS FROM clientes")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('token_acceso', $colsClientes)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN token_acceso VARCHAR(100) NULL AFTER email, ADD INDEX(token_acceso)");
            // Crear tokens iniciales
            $pdo->exec("UPDATE clientes SET token_acceso = MD5(CONCAT(id, NOW(), RAND())) WHERE token_acceso IS NULL");
        }
        if (!in_array('usuario_id', $colsClientes)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN usuario_id INT NULL AFTER empresa_id, ADD INDEX(usuario_id)");
        }

        // 1. Migración Empresas
        $colsEmpresa = $pdo->query("SHOW COLUMNS FROM empresas")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('timezone', $colsEmpresa)) {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN timezone VARCHAR(50) DEFAULT 'America/Bogota' AFTER color_hex");
        }
        if (!in_array('starting_quote_number', $colsEmpresa)) {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN starting_quote_number INT DEFAULT 1");
        }
        if (!in_array('quote_prefix', $colsEmpresa)) {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN quote_prefix VARCHAR(20) DEFAULT ''");
        }
        if (!in_array('google_maps_api_key', $colsEmpresa)) {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN google_maps_api_key VARCHAR(255) NULL");
        }
        
        $colsFormularios = $pdo->query("SHOW COLUMNS FROM formularios")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('email_template', $colsFormularios)) {
            $pdo->exec("ALTER TABLE formularios ADD COLUMN email_template TEXT NULL, ADD COLUMN whatsapp_template TEXT NULL");
        }
        if (!in_array('email_recipients', $colsFormularios)) {
            $pdo->exec("ALTER TABLE formularios ADD COLUMN email_recipients VARCHAR(255) NULL, ADD COLUMN whatsapp_recipients VARCHAR(255) NULL");
        }
        
        // Evolution API Migration
        if (!in_array('evolution_api_url', $colsEmpresa)) {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN evolution_api_url VARCHAR(255), ADD COLUMN evolution_api_key VARCHAR(255), ADD COLUMN evolution_instance_name VARCHAR(255), ADD COLUMN evolution_instance_token VARCHAR(255)");
        }
        if (!in_array('submit_label', $colsFormularios)) {
            $pdo->exec("ALTER TABLE formularios ADD COLUMN submit_label VARCHAR(100) DEFAULT 'Enviar Formulario'");
        }
        if (!in_array('success_message', $colsFormularios)) {
            $pdo->exec("ALTER TABLE formularios ADD COLUMN success_message TEXT NULL");
        }
        if (!in_array('wa_provider', $colsEmpresa)) {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN wa_provider ENUM('smsenlinea', 'evolution') DEFAULT 'smsenlinea'");
        }
        if (!in_array('quote_suffix', $colsEmpresa)) {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN quote_suffix VARCHAR(20) DEFAULT ''");
        }

        // 2. Migración Cotización Detalles (Imagen)
        $colsDetalles = $pdo->query("SHOW COLUMNS FROM cotizacion_detalles")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('imagen', $colsDetalles)) {
            $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN imagen VARCHAR(255) AFTER nombre_producto");
        }

        // 3. Migración Cotizaciones (updated_at)
        $colsCot = $pdo->query("SHOW COLUMNS FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('updated_at', $colsCot)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
        if (!in_array('vistas_count', $colsCot)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN vistas_count INT DEFAULT 0 AFTER estado");
        }
        if (!in_array('visto_primera_vez_el', $colsCot)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN visto_primera_vez_el DATETIME NULL AFTER vistas_count");
        }
        if (!in_array('visto_ultima_vez_el', $colsCot)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN visto_ultima_vez_el DATETIME NULL AFTER visto_primera_vez_el");
        }

        // 4. Migración Documentos (Categorías y Vencimiento)
        // Columnas adicionales en documentos para categorías y fechas
        $colsDocs = $pdo->query("SHOW COLUMNS FROM documentos")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('categoria', $colsDocs)) {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN categoria VARCHAR(100) DEFAULT 'General' AFTER tipo_mime");
        }
        if (!in_array('fecha_vencimiento', $colsDocs)) {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN fecha_vencimiento DATE NULL AFTER categoria");
        }
        if (!in_array('notificado_vencimiento', $colsDocs)) {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN notificado_vencimiento TINYINT(1) DEFAULT 0 AFTER fecha_vencimiento");
        }
        if (!in_array('share_token', $colsDocs)) {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN share_token VARCHAR(64) NULL AFTER notificado_vencimiento, ADD INDEX(share_token)");
        }
        if (!in_array('share_password', $colsDocs)) {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN share_password VARCHAR(255) NULL AFTER share_token");
        }
        if (!in_array('allow_download', $colsDocs)) {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN allow_download TINYINT(1) DEFAULT 1 AFTER share_password");
        }
        if (!in_array('view_count', $colsDocs)) {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN view_count INT DEFAULT 0 AFTER allow_download");
        }
        if (!in_array('download_count', $colsDocs)) {
            $pdo->exec("ALTER TABLE documentos ADD COLUMN download_count INT DEFAULT 0 AFTER view_count");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS documento_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            documento_id INT NOT NULL,
            tipo_evento ENUM('vista', 'descarga') NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(documento_id)
        )");

        // 4.1 Migración Entradas de Formulario (Integraciones)
        $colsEntradas = $pdo->query("SHOW COLUMNS FROM formulario_entradas")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('cotizacion_id', $colsEntradas)) {
            $pdo->exec("ALTER TABLE formulario_entradas ADD COLUMN cotizacion_id INT NULL AFTER formulario_id, ADD INDEX(cotizacion_id)");
        }
        if (!in_array('orden_trabajo_id', $colsEntradas)) {
            $pdo->exec("ALTER TABLE formulario_entradas ADD COLUMN orden_trabajo_id INT NULL AFTER cotizacion_id, ADD INDEX(orden_trabajo_id)");
        }

        // 5. Cargar Tablas Nuevas
        $pdo->exec("CREATE TABLE IF NOT EXISTS cotizacion_adjuntos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cotizacion_id INT NOT NULL,
            documento_id INT NOT NULL,
            creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(cotizacion_id),
            INDEX(documento_id)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS trusted_clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            nombre VARCHAR(100),
            logo_url VARCHAR(255),
            orden INT DEFAULT 0,
            INDEX(empresa_id)
        )");

        // 6. Migración Unidades Personalizadas (CRITICAL FIX)
        $pdo->exec("CREATE TABLE IF NOT EXISTS unidades_personalizadas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            nombre VARCHAR(50) NOT NULL,
            creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(empresa_id)
        )");

        // Asegurar que cotizacion_detalles tenga unidad_id
        if (!in_array('unidad_id', $colsDetalles)) {
            $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN unidad_id INT NULL AFTER producto_id");
        }
        
        // Asegurar que cotizacion_detalles tenga orden
        if (!in_array('orden', $colsDetalles)) {
             $pdo->exec("ALTER TABLE cotizacion_detalles ADD COLUMN orden INT DEFAULT 0 AFTER cantidad");
        }

        // 7. Migración Tareas Empresa (Post-Firma)
        $pdo->exec("CREATE TABLE IF NOT EXISTS tareas_empresa (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            descripcion TEXT,
            tipo VARCHAR(50) DEFAULT 'post_firma',
            orden INT DEFAULT 0,
            activo TINYINT(1) DEFAULT 1,
            INDEX(empresa_id)
        )");

        // 7.1 Migración Ordenes de Trabajo (Asegurar columnas)
        $pdo->exec("CREATE TABLE IF NOT EXISTS ordenes_trabajo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            cotizacion_id INT NOT NULL,
            cliente_id INT NOT NULL,
            estado VARCHAR(50) DEFAULT 'Pendiente',
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(cotizacion_id), INDEX(cliente_id)
        )");
        
        $colsOT = $pdo->query("SHOW COLUMNS FROM ordenes_trabajo")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('fecha_programada', $colsOT)) {
             $pdo->exec("ALTER TABLE ordenes_trabajo ADD COLUMN fecha_programada DATE NULL AFTER fecha_creacion");
        }
        if (!in_array('datos_recoleccion', $colsOT)) {
             $pdo->exec("ALTER TABLE ordenes_trabajo ADD COLUMN datos_recoleccion JSON NULL AFTER fecha_programada");
        }
        if (!in_array('responsable_id', $colsOT)) {
             $pdo->exec("ALTER TABLE ordenes_trabajo ADD COLUMN responsable_id INT NULL AFTER datos_recoleccion");
        }
        if (!in_array('firma_cliente', $colsOT)) {
             $pdo->exec("ALTER TABLE ordenes_trabajo ADD COLUMN firma_cliente LONGTEXT NULL AFTER responsable_id");
        }

        // 8. Migración Cotizaciones Refactor (Conversion y Tareas Cliente)
        $colsCot = $pdo->query("SHOW COLUMNS FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('conversion_automatica', $colsCot)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN conversion_automatica TINYINT(1) DEFAULT 0"); // Default 0 (Manual) is safer for existing logic unless specified
        }
        if (!in_array('impuesto_total', $colsCot)) {
             if (in_array('impuestos', $colsCot)) {
                 $pdo->exec("ALTER TABLE cotizaciones CHANGE COLUMN impuestos impuesto_total DECIMAL(15,2) DEFAULT 0");
             } else {
                 $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN impuesto_total DECIMAL(15,2) DEFAULT 0 AFTER subtotal");
             }
        }
        if (!in_array('tareas_cliente', $colsCot)) {
             $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN tareas_cliente JSON DEFAULT NULL");
        }
        if (!in_array('firma_digital', $colsCot)) {
             $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN firma_digital LONGTEXT DEFAULT NULL");
        }
        if (!in_array('usuario_id', $colsCot)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN usuario_id INT NULL AFTER empresa_id, ADD INDEX(usuario_id)");
        }
        if (!in_array('notificar_vistas_wa', $colsCot)) {
             $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN notificar_vistas_wa TINYINT(1) DEFAULT 0");
        }
        if (!in_array('formulario_id', $colsCot)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN formulario_id INT NULL AFTER plantilla_id, ADD INDEX(formulario_id)");
        }
        if (!in_array('formulario_config', $colsCot)) {
             $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN formulario_config JSON NULL");
        }
        
        // 9. Tabla de Historial de Envíos y Rastreo
        $pdo->exec("CREATE TABLE IF NOT EXISTS cotizacion_envios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cotizacion_id INT NOT NULL,
            tipo ENUM('Email', 'WhatsApp') NOT NULL,
            destinatario VARCHAR(255) NOT NULL,
            mensaje TEXT,
            fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
            visto TINYINT(1) DEFAULT 0,
            fecha_visto DATETIME NULL,
            uid VARCHAR(64) UNIQUE NOT NULL,
            INDEX(cotizacion_id),
            INDEX(uid)
        )");

        if (!in_array('aceptada_data', $colsCot)) {
             $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN aceptada_data JSON DEFAULT NULL");
        }

        // 10. Migración Tabla Usuarios (Pass y SaaS)
        $colsUser = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('password', $colsUser)) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN password VARCHAR(255) AFTER email");
        }
        if (!in_array('is_super_admin', $colsUser)) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN is_super_admin TINYINT(1) DEFAULT 0 AFTER rol_id");
        }
        if (!in_array('requires_password_setup', $colsUser)) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN requires_password_setup TINYINT(1) DEFAULT 0 AFTER password");
        }
        if (!in_array('password_reset_token', $colsUser)) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN password_reset_token VARCHAR(100) DEFAULT NULL AFTER requires_password_setup");
        }
        // 11. Migración Recordatorios (Columns Missing Fix)
        try {
            $colsRec = $pdo->query("SHOW COLUMNS FROM cotizacion_recordatorios")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('cliente_id', $colsRec)) {
                 $pdo->exec("ALTER TABLE cotizacion_recordatorios ADD COLUMN cliente_id INT NULL AFTER cotizacion_id");
                 $pdo->exec("ALTER TABLE cotizacion_recordatorios MODIFY COLUMN cotizacion_id INT NULL");
                 $pdo->exec("UPDATE cotizacion_recordatorios r JOIN cotizaciones c ON r.cotizacion_id = c.id SET r.cliente_id = c.cliente_id WHERE r.cliente_id IS NULL");
            }
        } catch(Exception $eRec) {} 

    } catch (Exception $e) { 
        error_log("Migration Error: " . $e->getMessage());
    }

    // Configuración Global de Timezone según Empresa
    $empresa_id_ctx = getEmpresaId();
    if ($empresa_id_ctx) {
        $cache_key = "company_timezone_cache_" . $empresa_id_ctx;
        if (!isset($_SESSION[$cache_key])) {
            $stmt_tz = $pdo->prepare("SELECT timezone FROM empresas WHERE id = ?");
            $stmt_tz->execute([$empresa_id_ctx]);
            $_SESSION[$cache_key] = $stmt_tz->fetchColumn() ?: 'America/Bogota';
        }
        setCompanyTimezone($_SESSION[$cache_key]);
    } else {
        date_default_timezone_set('America/Bogota');
    }

} catch (PDOException $e) {
    // Si falla la conexión, manejamos el error según el contexto
    $is_api = (strpos($_SERVER['PHP_SELF'], 'api_') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false));
    
    if ($is_api) {
        if (!headers_sent()) header('Content-Type: application/json', true, 500);
        echo json_encode(['status' => 'error', 'message' => 'Error de conexión a base de datos', 'debug' => $e->getMessage()]);
        exit;
    }
    
    die("Error crítico de conexión: " . $e->getMessage());
}

function setCompanyTimezone($tz) {
    global $pdo;
    if (!$tz) $tz = 'America/Bogota';
    date_default_timezone_set($tz);
    
    $now = new DateTime();
    $mins = $now->getOffset() / 60;
    $sgn = ($mins < 0 ? -1 : 1);
    $mins_abs = abs($mins);
    $hrs = floor($mins_abs / 60);
    $mins_rem = $mins_abs - ($hrs * 60);
    $offset = sprintf('%+03d:%02d', $hrs*$sgn, $mins_rem);
    $pdo->exec("SET time_zone='$offset'");
}
// Fallback handled above

function getEmpresaId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Si el Super Admin está imitando a otra empresa, devolvemos esa
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true && isset($_SESSION['simulated_empresa_id'])) {
        return $_SESSION['simulated_empresa_id'];
    }
    return $_SESSION['empresa_id'] ?? null;
}

function getBaseUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    // Normalizar path para que no termine en \ o /
    $path = rtrim($path, '/\\');
    return $protocol . "://" . $host . $path . '/';
}

/**
 * Obtiene la ruta de subida aislada para la empresa actual
 * @param string $modulo Opcional: 'productos', 'logos', 'documentos', etc.
 * @param bool $absolute Si true, retorna la ruta absoluta del servidor.
 * @return string
 */
function getCompanyUploadPath($modulo = '', $absolute = false) {
    $empresa_id = getEmpresaId();
    if (!$empresa_id) return 'uploads/global/';
    
    $rel_path = "uploads/e{$empresa_id}/" . ($modulo ? trim($modulo, '/') . '/' : '');
    
    if ($absolute) {
        $full_path = __DIR__ . '/' . $rel_path;
        if (!is_dir($full_path)) {
            mkdir($full_path, 0755, true);
        }
        return $full_path;
    }
    
    return $rel_path;
}

/**
 * Convierte un path de archivo en una URL segura vía f.php proxy
 */
function getSecureUrl($path, $public_hash = '') {
    if (empty($path)) return '';
    if (strpos($path, 'http') === 0) return $path; // Ya es una URL externa
    
    $url = "f.php?path=" . urlencode($path);
    if ($public_hash) {
        $url .= "&h=" . urlencode($public_hash);
    }
    return $url;
}

/**
 * Verifica si la empresa ha alcanzado el límite de su plan
 * @param string $tipo 'cotizaciones' o 'usuarios'
 * @return bool true si alcanzó o superó el límite, false de lo contrario
 */
function haAlcanzadoLimite($tipo) {
    global $pdo;
    $empresa_id = getEmpresaId();
    if (!$empresa_id) return false;

    // Obtener los límites del plan de la empresa
    $stmt = $pdo->prepare("
        SELECT p.limite_cotizaciones, p.limite_usuarios 
        FROM empresas e 
        JOIN planes p ON e.plan_id = p.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$empresa_id]);
    $plan = $stmt->fetch();

    if (!$plan) return false;

    if ($tipo === 'cotizaciones') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cotizaciones WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $actual = $stmt->fetchColumn();
        return $actual >= $plan['limite_cotizaciones'];
    }

    if ($tipo === 'usuarios') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $actual = $stmt->fetchColumn();
        return $actual >= $plan['limite_usuarios'];
    }

    return false;
}
