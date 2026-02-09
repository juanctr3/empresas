<?php
/**
 * f.php - Proxy Seguro de Entrega de Archivos
 * Verifica la propiedad del archivo antes de servirlo.
 */
require_once 'db.php';

$path = $_GET['path'] ?? '';
$public_hash = $_GET['h'] ?? ''; // Para acceso público vía cotizaciones/documentos compartidos

if (empty($path)) {
    header("HTTP/1.1 400 Bad Request");
    exit("Path missing");
}

// Normalizar path (evitar Directory Traversal)
$path = str_replace(['../', '..\\'], '', $path);
$full_path = __DIR__ . '/' . $path;

if (!file_exists($full_path) || is_dir($full_path)) {
    header("HTTP/1.1 404 Not Found");
    exit("File not found");
}

// Extraer empresa_id del path (Estructura: uploads/e{id}/...)
$empresa_id_target = null;
if (preg_match('/uploads\/e(\d+)\//', $path, $matches)) {
    $empresa_id_target = (int)$matches[1];
}

$authorized = false;

// 1. Verificar si el usuario está logueado y pertenece a la empresa
if (getEmpresaId() && getEmpresaId() == $empresa_id_target) {
    $authorized = true;
} 
// 2. Verificar acceso público vía hash (si se proporcionó)
elseif (!empty($public_hash)) {
    // Verificar si es una cotización pública con este hash
    $stmt = $pdo->prepare("SELECT id FROM cotizaciones WHERE hash_publico = ? AND empresa_id = ?");
    $stmt->execute([$public_hash, $empresa_id_target]);
    if ($stmt->fetch()) {
        $authorized = true;
    } else {
        // Verificar si es un documento compartido
        $stmt = $pdo->prepare("SELECT dc.id 
                              FROM documentos_compartidos dc 
                              JOIN documentos d ON dc.documento_id = d.id 
                              WHERE dc.hash_publico = ? AND d.empresa_id = ?");
        $stmt->execute([$public_hash, $empresa_id_target]);
        if ($stmt->fetch()) {
            $authorized = true;
        }
    }
}

if (!$authorized) {
    header("HTTP/1.1 403 Forbidden");
    exit("Unauthorized access to this asset.");
}

// Servir el archivo con el MIME type correcto
$mime = mime_content_type($full_path);
header("Content-Type: $mime");
header("Content-Length: " . filesize($full_path));
header("Cache-Control: private, max-age=86400"); // Cache por 24h para el cliente específico

readfile($full_path);
exit;
