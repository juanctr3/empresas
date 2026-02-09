<?php
require_once 'db.php';

$token = $_GET['t'] ?? '';
$password_input = $_POST['password'] ?? '';
$action = $_GET['action'] ?? 'view';

if (!$token) die("Enlace inválido.");

// 1. Buscar Documento
$stmt = $pdo->prepare("
    SELECT d.*, c.nombre as cliente_nombre, e.nombre as empresa_nombre, e.logo as empresa_logo 
    FROM documentos d
    JOIN clientes c ON d.cliente_id = c.id
    JOIN empresas e ON c.empresa_id = e.id
    WHERE d.share_token = ?
");
$stmt->execute([$token]);
$doc = $stmt->fetch();

if (!$doc) die("Documento no encontrado o el enlace ha expirado.");

// 2. Verificar Password
$is_protected = !empty($doc['share_password']);
$access_granted = !$is_protected;

if ($is_protected && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($password_input === $doc['share_password']) { // Simple comparison for shared link pwd
        $access_granted = true;
        // Set cookie for session persistence if needed, but for now just rendering
    } else {
        $error = "Contraseña incorrecta";
    }
}

// 3. Procesar Acción (Descarga/Vista) si acceso concedido
if ($access_granted) {
    // Log View Only once per session/request to avoid spam counting? For simplicity, log every hit.
    if ($action === 'view' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $pdo->prepare("UPDATE documentos SET view_count = view_count + 1 WHERE id = ?")->execute([$doc['id']]);
        $pdo->prepare("INSERT INTO documento_logs (documento_id, tipo_evento, ip_address, user_agent) VALUES (?, 'vista', ?, ?)")
            ->execute([$doc['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
    }
    
    if ($action === 'download') {
        if (!$doc['allow_download']) die("La descarga ha sido deshabilitada para este archivo.");
        
        $pdo->prepare("UPDATE documentos SET download_count = download_count + 1 WHERE id = ?")->execute([$doc['id']]);
        $pdo->prepare("INSERT INTO documento_logs (documento_id, tipo_evento, ip_address, user_agent) VALUES (?, 'descarga', ?, ?)")
            ->execute([$doc['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

        // Redirigir al archivo real (S3 o Local)
        header("Location: " . $doc['nombre_s3']); 
        exit;
    }
}

// View Logic
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($doc['nombre_original']); ?> - Vista Previa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center p-4">

    <?php if (!$access_granted): ?>
        <!-- Password Gate -->
        <div class="bg-white p-8 rounded-2xl shadow-xl max-w-sm w-full text-center space-y-4">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto text-gray-400">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Archivo Protegido</h2>
            <p class="text-xs text-gray-500">Este documento requiere contraseña para ser visualizado.</p>
            
            <?php if(isset($error)): ?>
                <p class="text-red-500 text-xs font-bold"><?php echo $error; ?></p>
            <?php endif; ?>

            <form method="POST" class="space-y-3">
                <input type="password" name="password" placeholder="Contraseña..." class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-center tracking-widest" autofocus>
                <button type="submit" class="w-full bg-gray-900 text-white font-bold py-3 rounded-xl hover:bg-black transition-all">Acceder</button>
            </form>
        </div>
    <?php else: ?>
        
        <!-- Document Viewer -->
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col h-[85vh]">
            <!-- Header -->
            <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-white z-10">
                <div class="flex items-center gap-4">
                    <?php if($doc['empresa_logo']): ?>
                        <img src="<?php echo $doc['empresa_logo']; ?>" class="h-8 w-auto">
                    <?php else: ?>
                        <span class="font-black text-lg text-indigo-600"><?php echo htmlspecialchars($doc['empresa_nombre']); ?></span>
                    <?php endif; ?>
                    <div class="w-px h-8 bg-gray-100"></div>
                    <div>
                        <h1 class="font-bold text-gray-900 text-sm md:text-base truncate max-w-[200px] md:max-w-md"><?php echo htmlspecialchars($doc['nombre_original']); ?></h1>
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold"><?php echo strtoupper($doc['categoria']); ?> • <?php echo date('d M, Y', strtotime($doc['created_at'])); ?></p>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <?php if($doc['allow_download']): ?>
                        <a href="?t=<?php echo $token; ?>&action=download" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg hover:shadow-indigo-200 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Descargar
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-3 bg-gray-100 text-gray-400 rounded-xl font-bold text-[10px] uppercase tracking-widest flex items-center gap-2 cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            Sólo Lectura
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Content Preview -->
            <div class="flex-1 bg-gray-50 overflow-auto flex items-center justify-center p-8 relative group">
                <?php
                $ext = strtolower(pathinfo($doc['nombre_original'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                    <img src="<?php echo $doc['nombre_s3']; ?>" class="max-w-full max-h-full rounded-lg shadow-lg object-contain">
                <?php elseif ($ext === 'pdf'): ?>
                    <iframe src="<?php echo $doc['nombre_s3']; ?>" class="w-full h-full rounded-lg shadow-sm"></iframe>
                <?php else: ?>
                    <div class="text-center space-y-4">
                        <div class="w-24 h-24 bg-gray-200 rounded-3xl flex items-center justify-center mx-auto text-gray-400">
                             <span class="text-2xl font-black uppercase"><?php echo $ext; ?></span>
                        </div>
                        <p class="text-gray-500 font-medium">Vista previa no disponible para este formato.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>

</body>
</html>
