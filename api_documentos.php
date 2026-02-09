<?php
ob_start();
require_once 'db.php';
require_once 'includes/auth_helper.php';
require_once 'includes/s3_helper.php';
require_once 'includes/mail_helper.php';
require_once 'includes/whatsapp_helper.php';

header('Content-Type: application/json; charset=utf-8');


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Asegurar que el usuario esté autenticado
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('No autorizado - Sesión no iniciada');
    }
    
    $user_id = $_SESSION['user_id'];
    $empresa_id = getEmpresaId();

    if (!$empresa_id) {
        throw new Exception('No autorizado - Empresa no identificada');
    }

    // --- AUTO-MIGRACIÓN FLASH (Asegurar todas las tablas necesarias) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_carpetas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nombre VARCHAR(255) NOT NULL,
        parent_id INT DEFAULT NULL,
        color VARCHAR(20) DEFAULT '#6366f1',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(empresa_id),
        INDEX(parent_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#10b981',
        icono VARCHAR(50) DEFAULT 'tag',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE INDEX unq_cat (empresa_id, nombre),
        INDEX(empresa_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_compartidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        documento_id INT NOT NULL,
        tipo_objeto ENUM('archivo', 'carpeta') DEFAULT 'archivo', 
        hash_publico VARCHAR(64) NOT NULL,
        requiere_password TINYINT(1) DEFAULT 0,
        password_hash VARCHAR(255) DEFAULT NULL,
        vistas_max INT DEFAULT 0,
        total_vistas INT DEFAULT 0,
        expira_at DATETIME DEFAULT NULL,
        activo TINYINT(1) DEFAULT 1,
        creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(documento_id),
        UNIQUE INDEX(hash_publico)
    )");
    
    // Ensure column exists for existing tables
    try {
        $pdo->exec("ALTER TABLE documentos_compartidos ADD COLUMN tipo_objeto ENUM('archivo', 'carpeta') DEFAULT 'archivo' AFTER documento_id");
    } catch (Exception $e) { /* Ignore if exists */ }

    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        compartido_id INT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        referencia VARCHAR(255),
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(compartido_id)
    )");

    // Ensure documents table columns (in case install_db was missed)
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM documentos")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('nombre_s3', $cols)) $pdo->exec("ALTER TABLE documentos ADD COLUMN nombre_s3 VARCHAR(255) DEFAULT NULL");
        if (!in_array('bucket', $cols)) $pdo->exec("ALTER TABLE documentos ADD COLUMN bucket VARCHAR(50) DEFAULT 'local'");
        if (!in_array('carpeta_id', $cols)) $pdo->exec("ALTER TABLE documentos ADD COLUMN carpeta_id INT DEFAULT NULL");
        if (!in_array('categoria', $cols)) $pdo->exec("ALTER TABLE documentos ADD COLUMN categoria VARCHAR(100) DEFAULT 'General'");
        if (!in_array('fecha_vencimiento', $cols)) $pdo->exec("ALTER TABLE documentos ADD COLUMN fecha_vencimiento DATE DEFAULT NULL");
    } catch (Exception $e) { /* Table might not exist yet if very first run */ }

    // Insertar categorías por defecto si no existen
    $pdo->exec("INSERT IGNORE INTO documentos_categorias (empresa_id, nombre, color) VALUES 
        ($empresa_id, 'Factura', '#ef4444'),
        ($empresa_id, 'Contrato', '#3b82f6'),
        ($empresa_id, 'Identidad', '#8b5cf6'),
        ($empresa_id, 'Certificado', '#10b981'),
        ($empresa_id, 'General', '#6b7280')");
    // ---------------------------------------------------------

    // Obtener configuración de almacenamiento de la empresa
    $stmt_config = $pdo->prepare("SELECT storage_type, s3_bucket, s3_region, s3_access_key, s3_secret_key FROM empresas WHERE id = ?");
    $stmt_config->execute([$empresa_id]);
    $config = $stmt_config->fetch();

    $storage_type = $config['storage_type'] ?? 'local';
    
    // Configuración de S3 dinámica
    $s3_key = !empty($config['s3_access_key']) ? $config['s3_access_key'] : (getenv('AWS_ACCESS_KEY_ID') ?: '');
    $s3_secret = !empty($config['s3_secret_key']) ? $config['s3_secret_key'] : (getenv('AWS_SECRET_ACCESS_KEY') ?: '');
    $s3_region = !empty($config['s3_region']) ? $config['s3_region'] : (getenv('AWS_REGION') ?: 'us-east-1');
    $s3_bucket = !empty($config['s3_bucket']) ? $config['s3_bucket'] : (getenv('AWS_S3_BUCKET') ?: 'coticefacil-docs');

    $s3 = ($storage_type === 's3' || (isset($_REQUEST['storage_type']) && $_REQUEST['storage_type'] === 's3')) 
           ? new S3Helper($s3_key, $s3_secret, $s3_region, $s3_bucket) 
           : null;

    $accion = $_REQUEST['accion'] ?? '';
    $response = ['status' => 'error', 'message' => 'Acción no válida: ' . $accion];

    switch ($accion) {
        case 'listar':
            $carpeta_id = $_GET['carpeta_id'] ?? null;
            if ($carpeta_id === 'root') $carpeta_id = null;
            
            // Documentos
            $sql1 = "SELECT d.*, u.nombre as usuario_nombre FROM documentos d 
                     JOIN usuarios u ON d.usuario_id = u.id
                     WHERE d.empresa_id = ?";
            if ($carpeta_id === null) $sql1 .= " AND d.carpeta_id IS NULL";
            else $sql1 .= " AND d.carpeta_id = " . intval($carpeta_id);
            
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([$empresa_id]);
            $docs1 = $stmt1->fetchAll();

            foreach ($docs1 as &$doc) {
                if ($doc['bucket'] !== 'local') {
                    if (!$s3) $s3 = new S3Helper($s3_key, $s3_secret, $s3_region, $s3_bucket);
                    $doc['nombre_s3'] = $s3->getPresignedUrl($doc['nombre_s3']);
                }
            }

            // Legacy docs (Clientes) only in root
            $docs2 = [];
            if ($carpeta_id === null) {
                $stmt2 = $pdo->prepare("SELECT cd.id, cd.empresa_id, cd.nombre_archivo as nombre_original, cd.url_archivo as nombre_s3, 'local' as bucket, 'System' as usuario_nombre, c.nombre as cliente_nombre
                                       FROM clientes_documentos cd JOIN clientes c ON cd.cliente_id = c.id WHERE cd.empresa_id = ?");
                $stmt2->execute([$empresa_id]);
                $docs2 = $stmt2->fetchAll();
            }

            // Carpetas
            $sqlC = "SELECT * FROM documentos_carpetas WHERE empresa_id = ? AND parent_id " . ($carpeta_id === null ? "IS NULL" : "= ".intval($carpeta_id));
            $stmtC = $pdo->prepare($sqlC);
            $stmtC->execute([$empresa_id]);
            
            // Categorías
            $stmtCat = $pdo->prepare("SELECT * FROM documentos_categorias WHERE empresa_id = ? ORDER BY nombre ASC");
            $stmtCat->execute([$empresa_id]);

            // Quota
            $stmt_q = $pdo->prepare("SELECT e.almacenamiento_usado, p.limite_almacenamiento FROM empresas e JOIN planes p ON e.plan_id = p.id WHERE e.id = ?");
            $stmt_q->execute([$empresa_id]);

            $response = [
                'status' => 'success',
                'data' => [
                    'documentos' => array_merge($docs1, $docs2),
                    'carpetas' => $stmtC->fetchAll(),
                    'categorias' => $stmtCat->fetchAll()
                ],
                'quota' => $stmt_q->fetch()
            ];
            break;

        case 'subir':
            $file = $_FILES['archivo'] ?? null;
            if (!$file) throw new Exception('No se recibió ningún archivo');
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE: throw new Exception('El archivo excede upload_max_filesize en php.ini');
                    case UPLOAD_ERR_FORM_SIZE: throw new Exception('El archivo excede MAX_FILE_SIZE del formulario');
                    case UPLOAD_ERR_PARTIAL: throw new Exception('El archivo se subió parcialmente');
                    case UPLOAD_ERR_NO_FILE: throw new Exception('No se subió ningún archivo');
                    case UPLOAD_ERR_NO_TMP_DIR: throw new Exception('Falta la carpeta temporal');
                    case UPLOAD_ERR_CANT_WRITE: throw new Exception('No se pudo escribir el archivo en disco');
                    case UPLOAD_ERR_EXTENSION: throw new Exception('Una extensión de PHP detuvo la subida');
                    default: throw new Exception('Error desconocido al subir archivo: ' . $file['error']);
                }
            }

            $upload_storage = $_POST['storage_type'] ?? $storage_type;
            $carpeta_id = !empty($_POST['carpeta_id']) ? intval($_POST['carpeta_id']) : null;
            
            $size = $file['size'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_name_db = "";

            if ($upload_storage === 's3') {
                if (empty($s3_key) || empty($s3_secret) || empty($s3_bucket)) {
                    throw new Exception('Configuración de S3 incompleta. Verifique sus credenciales.');
                }
                
                $s3_instance = new S3Helper($s3_key, $s3_secret, $s3_region, $s3_bucket);
                $s3_key_name = "e{$empresa_id}/docs/" . uniqid() . "." . $ext;
                
                if ($s3_instance->uploadFile($file['tmp_name'], $s3_key_name, $file['type'])) {
                    $file_name_db = $s3_key_name;
                } else {
                    $details = $s3_instance->lastError ? json_encode($s3_instance->lastError) : 'Desconocido';
                    throw new Exception('Error al subir a Amazon S3. Detalles: ' . $details);
                }
            } else {
                $target_dir = getCompanyUploadPath('documentos', true);
                if (!is_writable($target_dir)) {
                     throw new Exception('El directorio de destino no tiene permisos de escritura: ' . $target_dir);
                }
                
                $pure_name = uniqid('doc_') . "." . $ext;
                $dest_path = $target_dir . $pure_name;
                
                if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                    $file_name_db = getCompanyUploadPath('documentos') . $pure_name;
                } else {
                    $err = error_get_last();
                    throw new Exception('Error al guardar localmente: ' . ($err['message'] ?? ''));
                }
            }

            $stmt = $pdo->prepare("INSERT INTO documentos (empresa_id, usuario_id, carpeta_id, nombre_original, nombre_s3, bucket, extension, tamano, tipo_mime, categoria, fecha_vencimiento) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empresa_id, $user_id, $carpeta_id, $file['name'], $file_name_db, ($upload_storage === 's3' ? $s3_bucket : 'local'), $ext, $size, $file['type'], $_POST['categoria'] ?? 'General', !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null]);

            if ($stmt->rowCount() > 0) {
                 $pdo->prepare("UPDATE empresas SET almacenamiento_usado = almacenamiento_usado + ? WHERE id = ?")->execute([$size, $empresa_id]);
                 $response = ['status' => 'success', 'message' => 'Archivo guardado correctamente'];
            } else {
                throw new Exception("Error al registrar el archivo en la base de datos");
            }
            break;

        case 'crear_carpeta':
            $nombre = $_POST['nombre'] ?? '';
            $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
            if (!$nombre) throw new Exception('El nombre es requerido');

            $stmt = $pdo->prepare("INSERT INTO documentos_carpetas (empresa_id, nombre, parent_id, color) VALUES (?, ?, ?, ?)");
            $stmt->execute([$empresa_id, $nombre, $parent_id, $_POST['color'] ?? '#6366f1']);
            $response = ['status' => 'success', 'message' => 'Carpeta creada'];
            break;

        case 'compartir':
            $id = $_POST['id'] ?? 0;
            if (!$id) throw new Exception('ID de documento no válido');
            
            // Check ownership
            $stmt = $pdo->prepare("SELECT id FROM documentos WHERE id = ? AND (empresa_id = ? OR cliente_id IN (SELECT id FROM clientes WHERE empresa_id = ?))");
            $stmt->execute([$id, $empresa_id, $empresa_id]);
            if (!$stmt->fetch()) throw new Exception('Documento no encontrado o sin permiso');
            
            $pass = $_POST['password'] ?? '';
            $allow_download = isset($_POST['allow_download']) && ($_POST['allow_download'] === 'true' || $_POST['allow_download'] === '1') ? 1 : 0;
            
            // Token Generation
            $token = bin2hex(random_bytes(16));
            
            // Update Document
            $sql = "UPDATE documentos SET share_token = ?, allow_download = ?";
            $params = [$token, $allow_download];
            
            if (!empty($pass)) {
                $sql .= ", share_password = ?";
                $params[] = $pass; 
            } else {
                $sql .= ", share_password = NULL";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $response = [
                'status' => 'success',
                'link' => getBaseUrl() . "/ver-documento.php?t=" . $token
            ];
            break;

        case 'crear_categoria':
            $nombre = $_POST['nombre'] ?? '';
            if (!$nombre) throw new Exception('Nombre requerido');
            $stmt = $pdo->prepare("INSERT IGNORE INTO documentos_categorias (empresa_id, nombre, color) VALUES (?, ?, ?)");
            $stmt->execute([$empresa_id, $nombre, $_POST['color'] ?? '#10b981']);
            $response = ['status' => 'success', 'message' => 'Categoría guardada'];
            break;

        case 'eliminar':
            $id = $_POST['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM documentos WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresa_id]);
            $doc = $stmt->fetch();
            if ($doc) {
                if ($doc['bucket'] === 'local') {
                    $lp = __DIR__ . "/" . $doc['nombre_s3'];
                    if (file_exists($lp)) unlink($lp);
                } else if ($s3) $s3->deleteFile($doc['nombre_s3']);
                
                $pdo->prepare("DELETE FROM documentos WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE empresas SET almacenamiento_usado = GREATEST(0, almacenamiento_usado - ?) WHERE id = ?")->execute([$doc['tamano'], $empresa_id]);
                $response = ['status' => 'success', 'message' => 'Eliminado'];
            } else throw new Exception('No encontrado');
            break;

        case 'renombrar':
            $type = $_POST['type'] ?? '';
            $id = $_POST['id'] ?? 0;
            $nombre = $_POST['nombre'] ?? '';
            
            if (!$id || !$nombre) throw new Exception('Datos incompletos');
            
            if ($type === 'archivo') {
                $stmt = $pdo->prepare("UPDATE documentos SET nombre_original = ? WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$nombre, $id, $empresa_id]);
            } elseif ($type === 'carpeta') {
                $stmt = $pdo->prepare("UPDATE documentos_carpetas SET nombre = ? WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$nombre, $id, $empresa_id]);
            } else {
                throw new Exception('Tipo no válido');
            }
            $response = ['status' => 'success', 'message' => 'Renombrado'];
            break;

        case 'mover':
            $type = $_POST['type'] ?? '';
            $id = $_POST['id'] ?? 0;
            $target_id = !empty($_POST['target_id']) ? intval($_POST['target_id']) : null;
            
            if (!$id) throw new Exception('ID inválido');

            if ($type === 'archivo') {
                $stmt = $pdo->prepare("UPDATE documentos SET carpeta_id = ? WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$target_id, $id, $empresa_id]);
            } elseif ($type === 'carpeta') {
                if ($id === $target_id) throw new Exception('No se puede mover a sí misma');
                // Evitar ciclos (a -> b -> a) sería ideal pero por ahora básico
                $stmt = $pdo->prepare("UPDATE documentos_carpetas SET parent_id = ? WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$target_id, $id, $empresa_id]);
            } else {
                 throw new Exception('Tipo no válido');
            }
            $response = ['status' => 'success', 'message' => 'Movido con éxito'];
            break;

        case 'eliminar_carpeta':
            $id = $_POST['id'] ?? 0;
            $force = isset($_POST['force']) && $_POST['force'] === 'true';

            // 1. Scan content
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos WHERE carpeta_id = ?");
            $stmt->execute([$id]);
            $filesCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM documentos_carpetas WHERE parent_id = ?");
            $stmt->execute([$id]);
            $subfoldersCount = $stmt->fetchColumn();

            if (($filesCount > 0 || $subfoldersCount > 0) && !$force) {
                // Return a specific status so frontend knows to ask for confirmation
                echo json_encode(['status' => 'confirm_required', 'message' => 'La carpeta no está vacía. ¿Desea eliminar todo su contenido?']);
                exit; // Stop execution here
            }

            // 2. Recursive Deletion
            if ($filesCount > 0 || $subfoldersCount > 0) {
                 // Get all descendants using BFS
                $all_folders = [$id];
                $current_level = [$id];
                
                // Limit depth to avoid infinite loops in case of bad data, though tree should be DAG
                $depth = 0;
                while(!empty($current_level) && $depth < 20) {
                    $placeholders = implode(',', array_map('intval', $current_level));
                    if (!$placeholders) break; 
                    
                    $stmt = $pdo->prepare("SELECT id FROM documentos_carpetas WHERE parent_id IN ($placeholders) AND empresa_id = ?");
                    $stmt->execute([$empresa_id]);
                    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if(empty($children)) break;
                    
                    $all_folders = array_merge($all_folders, $children);
                    $current_level = $children;
                    $depth++;
                }
                
                // Get all files in these folders to delete physical files
                $folder_placeholders = implode(',', array_map('intval', $all_folders));
                $stmt = $pdo->prepare("SELECT * FROM documentos WHERE carpeta_id IN ($folder_placeholders) AND empresa_id = ?");
                $stmt->execute([$empresa_id]);
                $filesToDelete = $stmt->fetchAll();
                
                $total_size_freed = 0;
                foreach($filesToDelete as $doc) {
                     $total_size_freed += $doc['tamano'];
                     if ($doc['bucket'] === 'local') {
                        $lp = __DIR__ . "/" . $doc['nombre_s3'];
                        if (file_exists($lp)) unlink($lp);
                    } else if ($s3) {
                        $s3->deleteFile($doc['nombre_s3']);
                    }
                }
                
                // Bulk delete DB records
                // 1. Files
                $pdo->prepare("DELETE FROM documentos WHERE carpeta_id IN ($folder_placeholders) AND empresa_id = ?")->execute([$empresa_id]);
                
                // 2. Folders
                $pdo->prepare("DELETE FROM documentos_carpetas WHERE id IN ($folder_placeholders) AND empresa_id = ?")->execute([$empresa_id]);
                
                // Update quota
                if ($total_size_freed > 0) {
                    $pdo->prepare("UPDATE empresas SET almacenamiento_usado = GREATEST(0, almacenamiento_usado - ?) WHERE id = ?")->execute([$total_size_freed, $empresa_id]);
                }

            } else {
                // Simple delete for empty folder
                $stmt = $pdo->prepare("DELETE FROM documentos_carpetas WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$id, $empresa_id]);
            }
            
        case 'eliminar_carpeta':
             // ... existing code ...
             $response = ['status' => 'success', 'message' => 'Carpeta eliminada'];
             break;

        case 'enviar_whatsapp':
            $destinatario = $_POST['destinatario'] ?? '';
            $link = $_POST['link'] ?? '';
            $nombre_doc = $_POST['nombre_doc'] ?? 'un documento';
            
            if (!$destinatario || !$link) throw new Exception('Faltan datos');

            $mensaje = "Hola! Te han compartido el documento *{$nombre_doc}* a través de CoticeFacil.\n\nAccede aquí: {$link}\n\nEste enlace es seguro y privado.";
            
            $res = enviarWhatsApp($pdo, $empresa_id, $destinatario, $mensaje);
            
            if (($res['status'] ?? 0) === 200 || ($res['status'] ?? '') === 'success' || isset($res['key'])) {
                $response = ['status' => 'success', 'message' => 'WhatsApp enviado exitosamente'];
            } else {
                throw new Exception('Error enviando WhatsApp: ' . json_encode($res));
            }
            break;

        case 'enviar_email':
            $destinatario = $_POST['destinatario'] ?? '';
            $link = $_POST['link'] ?? '';
            $nombre_doc = $_POST['nombre_doc'] ?? 'un documento';

            if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido');

            // Fetch SMTP Config
            $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
            $stmt->execute([$empresa_id]);
            $conf = $stmt->fetch();

            if (empty($conf['smtp_host'])) throw new Exception('SMTP no configurado en la empresa');

            // Config array for helper
            $smtpConf = [
                'smtp_host' => $conf['smtp_host'],
                'smtp_port' => $conf['smtp_port'],
                'smtp_user' => $conf['smtp_user'],
                'smtp_pass' => $conf['smtp_pass'],
                'smtp_encryption' => $conf['smtp_encryption'],
                'smtp_from_email' => $conf['smtp_from_email']
            ];

            $mail = getMailer($smtpConf);
            $mail->setFrom($conf['smtp_from_email'], $conf['nombre']);
            $mail->addAddress($destinatario);
            $mail->Subject = "Te compartieron un documento: " . $nombre_doc;
            
            // HTML Template
            $html = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                <h2 style='color: #0d47a1;'>Hola!</h2>
                <p style='color: #555;'>Te han compartido acceso seguro al documento <strong>{$nombre_doc}</strong>.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$link}' style='background-color: #2563eb; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                        Ver Documento Ahora
                    </a>
                </div>
                <p style='font-size: 12px; color: #999; text-align: center;'>Este enlace puede tener expiración o límite de vistas.</p>
            </div>
            ";
            
            $mail->Body = $html;
            $mail->AltBody = "Te han compartido el documento {$nombre_doc}. Accede aquí: {$link}";

            if ($mail->send()) {
                $response = ['status' => 'success', 'message' => 'Email enviado exitosamente'];
            } else {
                throw new Exception('Error enviando Email: ' . $mail->ErrorInfo);
            }
            break;
    }

} catch (Throwable $e) {
    ob_clean();
    $response = [
        'status' => 'error', 
        'message' => $e->getMessage(),
        'debug' => (getenv('APP_DEBUG') === 'true' || isset($_SESSION['is_super_admin'])) ? $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getTraceAsString() : null
    ];
}

// Final cleanup and output
if (ob_get_length()) ob_clean();
echo json_encode($response);
exit;
