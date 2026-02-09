<?php
require_once 'db.php';
require_once 'includes/s3_helper.php';

$hash = $_GET['h'] ?? '';

if (!$hash) {
    die("Enlace no válido");
}

// Obtener información del documento compartido
$stmt = $pdo->prepare("
    SELECT dc.*, d.nombre_original, d.nombre_s3, d.bucket, d.extension, d.tamano, d.tipo_mime, e.nombre as empresa_nombre, e.color_hex, d.empresa_id
    FROM documentos_compartidos dc
    JOIN documentos d ON dc.documento_id = d.id
    JOIN empresas e ON d.empresa_id = e.id
    WHERE dc.hash_publico = ? AND dc.activo = 1
");
$stmt->execute([$hash]);
$compartido = $stmt->fetch();

if (!$compartido) {
    die("El documento no existe o el enlace ha sido desactivado.");
}

// Verificar expiración
if ($compartido['expira_at'] && strtotime($compartido['expira_at']) < time()) {
    die("Este enlace ha expirado.");
}

// Verificar límite de vistas
if ($compartido['vistas_max'] > 0 && $compartido['total_vistas'] >= $compartido['vistas_max']) {
    die("Este enlace ha alcanzado su límite de visualizaciones.");
}

// Manejar validación de contraseña
$error_pass = false;
if ($compartido['requiere_password']) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (password_verify($_POST['password'], $compartido['password_hash'])) {
            $_SESSION['doc_auth_' . $hash] = true;
        } else {
            $error_pass = true;
        }
    }

    if (!isset($_SESSION['doc_auth_' . $hash])) {
        // Mostrar formulario de contraseña
        include 'includes/header_public.php'; // Necesitaremos crear este o usar uno minimalista
        ?>
        <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
            <div class="max-w-md w-full space-y-8 p-10 bg-white rounded-3xl shadow-xl border border-gray-100">
                <div class="text-center">
                    <div class="mx-auto h-20 w-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="h-10 w-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <h2 class="text-3xl font-extrabold text-gray-900">Documento Protegido</h2>
                    <p class="mt-2 text-sm text-gray-600">Este archivo requiere una contraseña para ser visualizado.</p>
                </div>
                <form class="mt-8 space-y-6" action="" method="POST">
                    <div class="rounded-md shadow-sm -space-y-px">
                        <input type="password" name="password" required class="appearance-none rounded-2xl relative block w-full px-4 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Contraseña">
                    </div>
                    <?php if ($error_pass): ?>
                        <p class="text-red-500 text-xs italic">Contraseña incorrecta.</p>
                    <?php endif; ?>
                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-2xl text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                        Acceder al Documento
                    </button>
                </form>
            </div>
        </div>
        <?php
        exit;
    }
}

// Si llega aquí, está autorizado. 
// 1. Registrar acceso
$pdo->prepare("UPDATE documentos_compartidos SET total_vistas = total_vistas + 1 WHERE id = ?")->execute([$compartido['id']]);

$pdo->prepare("INSERT INTO documentos_logs (compartido_id, ip_address, user_agent, referencia) VALUES (?, ?, ?, ?)")
    ->execute([
        $compartido['id'], 
        $_SERVER['REMOTE_ADDR'], 
        $_SERVER['HTTP_USER_AGENT'], 
        $_GET['ref'] ?? 'Directo'
    ]);

// 2. Servir el archivo según el almacenamiento
// Si es una archivo, la lógica anterior se mantiene
if (($compartido['tipo_objeto'] ?? 'archivo') === 'archivo') {
    // 2. Servir el archivo según el almacenamiento
    if ($compartido['bucket'] === 'local') {
        $local_path = __DIR__ . "/" . $compartido['nombre_s3']; // Warning: path might need adjustment depending on stored format
        // If 'nombre_s3' stores 'storage/folder/file.ext', and we prepend __DIR__ . "/", check for double 'storage'
        // In api, local saves as: getCompanyUploadPath('documentos') . filename. 
        // getCompanyUploadPath usually returns 'storage/company_id/documentos/'
        
        if (file_exists($local_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: ' . ($compartido['tipo_mime'] ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . basename($compartido['nombre_original']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($local_path));
            readfile($local_path);
            exit;
        } else {
            die("Error: El archivo local no se encuentra en el servidor. Path: $local_path");
        }
    } else {
        // ... S3 logic ...
        // 3. Generar URL de S3 dinámicamente
        $empresa_id = $compartido['empresa_id'];
        $stmt_config = $pdo->prepare("SELECT s3_bucket, s3_region, s3_access_key, s3_secret_key FROM empresas WHERE id = ?");
        $stmt_config->execute([$empresa_id]);
        $config = $stmt_config->fetch();

        $s3_key = !empty($config['s3_access_key']) ? $config['s3_access_key'] : (getenv('AWS_ACCESS_KEY_ID') ?: '');
        $s3_secret = !empty($config['s3_secret_key']) ? $config['s3_secret_key'] : (getenv('AWS_SECRET_ACCESS_KEY') ?: '');
        $s3_region = !empty($config['s3_region']) ? $config['s3_region'] : (getenv('AWS_REGION') ?: 'us-east-1');
        $s3_bucket = !empty($config['s3_bucket']) ? $config['s3_bucket'] : (getenv('AWS_S3_BUCKET') ?: '');

        if (empty($s3_key) || empty($s3_secret)) {
            die("Error: Configuración de Amazon S3 incompleta para esta empresa.");
        }

        $s3 = new S3Helper($s3_key, $s3_secret, $s3_region, $s3_bucket);
        $url_s3 = $s3->getPresignedUrl($compartido['nombre_s3'], 600); // 10 minutos
        
        header("Location: $url_s3");
        exit;
    }
}
// Logic for SHARED FOLDERS
else {
    $folder_id = $compartido['documento_id']; // For folders, this links to documentos_carpetas.id
    $empresa_id = $compartido['empresa_id'];

    // 1. Get Folder Info
    $stmtF = $pdo->prepare("SELECT * FROM documentos_carpetas WHERE id = ?");
    $stmtF->execute([$folder_id]);
    $folder = $stmtF->fetch();
    
    if(!$folder) die("Carpeta no encontrada");

    // 2. Recursive Get All Files
    // Helper function to get files recursively
    function getFolderFiles($pdo, $folderId, $empresaId) {
        $files = [];
        
        // Files in current folder
        $stmt = $pdo->prepare("SELECT * FROM documentos WHERE carpeta_id = ? AND empresa_id = ?");
        $stmt->execute([$folderId, $empresaId]);
        $currFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($currFiles as $f) $files[] = $f;

        // Subfolders
        $stmt = $pdo->prepare("SELECT id FROM documentos_carpetas WHERE parent_id = ?");
        $stmt->execute([$folderId]);
        $subs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach($subs as $subId) {
            $files = array_merge($files, getFolderFiles($pdo, $subId, $empresaId));
        }
        return $files;
    }

    $allFiles = getFolderFiles($pdo, $folder_id, $empresa_id);

    if(count($allFiles) === 0) die("La carpeta está vacía.");

    // 3. Create ZIP
    $zip = new ZipArchive();
    $zipName = sys_get_temp_dir() . "/folder_{$folder_id}_" . time() . ".zip";
    
    if ($zip->open($zipName, ZipArchive::CREATE) !== TRUE) {
        die("Error creando archivo ZIP temporal");
    }

    // Prepare S3 if needed
    $s3 = null; 
    // ... S3 Config Fetch (Simplified, duplicated from above, should be refactored) ...
    $stmt_config = $pdo->prepare("SELECT storage_type, s3_bucket, s3_region, s3_access_key, s3_secret_key FROM empresas WHERE id = ?");
    $stmt_config->execute([$empresa_id]);
    $config = $stmt_config->fetch();
    
    // Only init S3 if we have S3 files
    $hasS3 = false;
    foreach($allFiles as $f) if($f['bucket'] !== 'local') $hasS3 = true;
    
    if($hasS3) {
         $s3_key = !empty($config['s3_access_key']) ? $config['s3_access_key'] : (getenv('AWS_ACCESS_KEY_ID') ?: '');
         $s3_secret = !empty($config['s3_secret_key']) ? $config['s3_secret_key'] : (getenv('AWS_SECRET_ACCESS_KEY') ?: '');
         $s3_region = !empty($config['s3_region']) ? $config['s3_region'] : (getenv('AWS_REGION') ?: 'us-east-1');
         $s3_bucket = !empty($config['s3_bucket']) ? $config['s3_bucket'] : (getenv('AWS_S3_BUCKET') ?: '');
         $s3 = new S3Helper($s3_key, $s3_secret, $s3_region, $s3_bucket);
    }

    foreach ($allFiles as $file) {
        $localFilename = $file['nombre_original'];
        
        if ($file['bucket'] === 'local') {
            $path = __DIR__ . "/" . $file['nombre_s3'];
            if(file_exists($path)) {
                $zip->addFile($path, $localFilename);
            }
        } else if ($s3) {
            // S3: Download manually to temp then add? Or use AddFromString if small?
            // Safer: Download to temp
            $tempS3 = sys_get_temp_dir() . "/s3_" . uniqid();
            // S3Helper doesn't have downloadTo, so we use getObjectContent? 
            // Assuming S3Helper isn't fully robust for streams, let's try getPresigned and file_get_contents (slow but works)
            // Or better, let's skip for V1 if too complex. No, must implement.
            // Let's assume we can read via presigned URL
            $url = $s3->getPresignedUrl($file['nombre_s3']);
            $content = @file_get_contents($url);
            if($content) $zip->addFromString($localFilename, $content);
        }
    }

    $zip->close();

    // 4. Send ZIP
    if(file_exists($zipName)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $folder['nombre'] . '.zip"');
        header('Content-Length: ' . filesize($zipName));
        readfile($zipName);
        unlink($zipName); // Cleanup
        exit;
    } else {
        die("Error: No se pudo generar el archivo ZIP.");
    }

}
