<?php
require_once 'db.php';

$token = $_GET['t'] ?? '';

if (empty($token)) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h1>Acceso no válido</h1><p>Por favor utiliza el enlace enviado a tu correo o WhatsApp.</p></div>");
}

// Obtener Cliente y su Empresa Principal
$stmt = $pdo->prepare("
    SELECT cl.*, e.nombre as empresa_nombre, e.logo as empresa_logo, e.color_hex, e.moneda as empresa_moneda, e.id as empresa_id
    FROM clientes cl
    JOIN empresas e ON cl.empresa_id = e.id
    WHERE cl.token_acceso = ?
");
$stmt->execute([$token]);
$cliente = $stmt->fetch();

if (!$cliente) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h1>Sesión expirada</h1><p>El enlace ya no es válido.</p></div>");
}

$msg = "";
$msg_type = "";

// --- LOGICA DE ACCIONES (UPLOAD / SHARE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_doc') {
        $file = $_FILES['archivo'] ?? null;
        if ($file && $file['error'] === 0) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $pure_name = uniqid('client_doc_') . '.' . $ext;
            
            // Determinar carpeta (Local por defecto para cliente simple)
            $target_dir = __DIR__ . "/uploads/e{$cliente['empresa_id']}/docs/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            $dest_path = $target_dir . $pure_name;
            $db_path = "uploads/e{$cliente['empresa_id']}/docs/" . $pure_name;
            
            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                $stmt = $pdo->prepare("INSERT INTO documentos (empresa_id, cliente_id, nombre_original, nombre_s3, bucket, extension, tamano, categoria, created_at) VALUES (?, ?, ?, ?, 'local', ?, ?, 'Cliente', NOW())");
                $stmt->execute([$cliente['empresa_id'], $cliente['id'], $file['name'], $db_path, $ext, $file['size']]);
                $msg = "Documento subido correctamente.";
                $msg_type = "success";
            } else {
                $msg = "Error al guardar el archivo.";
                $msg_type = "error";
            }
        }
    }
    
    if ($action === 'share_settings') {
        // AJAX Response expected
        header('Content-Type: application/json');
        $doc_id = $_POST['doc_id'] ?? 0;
        $pass = $_POST['password'] ?? '';
        $allow = isset($_POST['allow_download']) ? 1 : 0;
        
        // Verificar propiedad (doc debe ser de este cliente)
        $stmt = $pdo->prepare("SELECT id FROM documentos WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$doc_id, $cliente['id']]);
        if ($stmt->fetch()) {
            $share_token = bin2hex(random_bytes(16));
            $sql = "UPDATE documentos SET share_token = ?, allow_download = ?";
            $params = [$share_token, $allow];
            if ($pass) {
                $sql .= ", share_password = ?";
                $params[] = $pass;
            } else {
                $sql .= ", share_password = NULL";
            }
            $sql .= " WHERE id = ?";
            $params[] = $doc_id;
            
            $pdo->prepare($sql)->execute($params);
            
            echo json_encode(['status' => 'success', 'link' => getBaseUrl() . "/ver-documento.php?t=" . $share_token]);
            exit;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
    }
    
    if ($action === 'get_stats') {
        header('Content-Type: application/json');
        $doc_id = $_POST['doc_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id FROM documentos WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$doc_id, $cliente['id']]);
        if($stmt->fetch()) {
            $logs = $pdo->prepare("SELECT * FROM documento_logs WHERE documento_id = ? ORDER BY fecha DESC LIMIT 20");
            $logs->execute([$doc_id]);
            echo json_encode(['status' => 'success', 'data' => $logs->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }
        echo json_encode(['status' => 'error']);
        exit;
    }
}


// Obtener Cotizaciones
try {
    $stmtCot = $pdo->prepare("SELECT * FROM cotizaciones WHERE cliente_id = ? ORDER BY created_at DESC");
    $stmtCot->execute([$cliente['id']]);
    $cotizaciones = $stmtCot->fetchAll();
} catch (Exception $e) { $cotizaciones = []; }

// Obtener Órdenes de Trabajo (Safely)
try {
    // Try ordenes_trabajo first
    $stmtOrd = $pdo->prepare("SELECT * FROM ordenes_trabajo WHERE cliente_id = ? ORDER BY created_at DESC");
    $stmtOrd->execute([$cliente['id']]);
    $ordenes = $stmtOrd->fetchAll();
} catch (Exception $e) { 
    try {
        // Fallback to ordenes
        $stmtOrd = $pdo->prepare("SELECT * FROM ordenes WHERE cliente_id = ? ORDER BY created_at DESC");
        $stmtOrd->execute([$cliente['id']]);
        $ordenes = $stmtOrd->fetchAll();
    } catch (Exception $ex) { $ordenes = []; }
}

// Obtener Documentos
try {
    $stmtDoc = $pdo->prepare("SELECT * FROM documentos WHERE cliente_id = ? ORDER BY created_at DESC");
    $stmtDoc->execute([$cliente['id']]);
    $documentos = $stmtDoc->fetchAll();
} catch (Exception $e) { $documentos = []; }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Área de Cliente - <?php echo htmlspecialchars($cliente['empresa_nombre']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.5); }
        .tab-active { border-bottom: 3px solid <?php echo $cliente['color_hex'] ?: '#4f46e5'; ?>; color: <?php echo $cliente['color_hex'] ?: '#4f46e5'; ?>; }
    </style>
</head>
<body class="min-h-screen">

    <?php if($msg): ?>
        <script>
            Swal.fire({
                icon: '<?php echo $msg_type; ?>',
                title: '<?php echo $msg; ?>',
                showConfirmButton: false,
                timer: 2000
            });
        </script>
    <?php endif; ?>

    <!-- Header Premium -->
    <header class="bg-white border-b border-gray-100 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <?php if($cliente['empresa_logo']): ?>
                    <img src="<?php echo htmlspecialchars($cliente['empresa_logo']); ?>" class="h-10 w-auto">
                <?php else: ?>
                    <span class="text-xl font-black text-gray-900"><?php echo htmlspecialchars($cliente['empresa_nombre']); ?></span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden md:block text-right">
                    <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($cliente['nombre']); ?></p>
                    <p class="text-[10px] uppercase font-black tracking-widest text-gray-400">Área de Cliente</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 font-bold">
                    <?php echo substr($cliente['nombre'], 0, 1); ?>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-10">
        
        <div class="mb-10 flex flex-col md:flex-row justify-between items-end gap-4">
            <div>
                <h1 class="text-4xl font-black text-gray-900 tracking-tight mb-2">¡Hola, <span style="color: <?php echo $cliente['color_hex'] ?: '#4f46e5'; ?>;"><?php echo explode(' ', $cliente['nombre'])[0]; ?></span>! 👋</h1>
                <p class="text-gray-500 font-medium">Gestiona tus documentos y órdenes en un solo lugar.</p>
            </div>
            <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="px-6 py-3 bg-gray-900 text-white rounded-xl font-bold shadow-lg hover:scale-105 transition-transform flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Subir Archivo
            </button>
        </div>

        <!-- Secciones -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Columna Izquierda: Documentos y Gestión -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Bóveda de Documentos -->
                <section class="glass-card rounded-[2.5rem] p-8 shadow-xl shadow-gray-200/50">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight">Mis Documentos</h3>
                        <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-full text-[10px] font-black uppercase"><?php echo count($documentos); ?></span>
                    </div>

                    <div class="space-y-4">
                        <?php foreach($documentos as $d): ?>
                            <div class="p-4 rounded-2xl bg-white border border-gray-50 shadow-sm hover:shadow-md transition-all group flex flex-col sm:flex-row items-center gap-4">
                                <div class="flex items-center gap-3 flex-1 w-full">
                                    <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 flex-shrink-0">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-gray-900 text-sm truncate"><?php echo htmlspecialchars($d['nombre_original']); ?></p>
                                        <p class="text-[10px] uppercase font-black tracking-widest text-gray-400">
                                            <?php echo date('d M, Y', strtotime($d['created_at'])); ?> • 
                                            <?php echo $d['allow_download'] ? 'Descargable' : 'Vista Previa'; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 w-full sm:w-auto">
                                    <button onclick="openShare(<?php echo $d['id']; ?>, '<?php echo $d['share_token']; ?>', <?php echo $d['allow_download']; ?>, '<?php echo $d['share_password']; ?>')" class="flex-1 sm:flex-none py-2 px-3 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold hover:bg-indigo-100 transition-colors" title="Compartir">
                                        <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                    </button>
                                    <button onclick="openStats(<?php echo $d['id']; ?>)" class="flex-1 sm:flex-none py-2 px-3 bg-blue-50 text-blue-600 rounded-lg text-xs font-bold hover:bg-blue-100 transition-colors" title="Estadísticas">
                                        <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                    </button>
                                    <?php if($d['allow_download']): ?>
                                    <a href="<?php echo htmlspecialchars($d['nombre_s3']); ?>" target="_blank" class="flex-1 sm:flex-none py-2 px-3 bg-gray-50 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-100 transition-colors" title="Descargar">
                                        <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($documentos)): ?>
                            <div class="text-center py-12 border-2 border-dashed border-gray-100 rounded-3xl">
                                <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p class="text-gray-400 text-sm">No has subido documentos aún.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                 <!-- Cotizaciones -->
                <section class="glass-card rounded-[2.5rem] p-8 shadow-xl shadow-gray-200/50">
                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight mb-6">Cotizaciones</h3>
                     <div class="space-y-4">
                        <?php foreach($cotizaciones as $c): ?>
                            <div class="flex items-center justify-between p-4 rounded-2xl hover:bg-gray-50 transition-all border border-transparent hover:border-gray-100 group">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-black">
                                        #<?php echo $c['id']; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900"><?php echo date('d M, Y', strtotime($c['created_at'])); ?></p>
                                        <p class="text-xs text-gray-400 uppercase font-black tracking-widest">Estado: <?php echo $c['estado']; ?></p>
                                    </div>
                                </div>
                                <a href="propuesta.php?h=<?php echo $c['hash_publico']; ?>" target="_blank" class="p-3 bg-gray-900 text-white rounded-xl hover:scale-105 transition-transform">
                                    Ver
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

            </div>

            <!-- Columna Derecha: Órdenes y Contacto -->
            <div class="space-y-8">
                 <!-- Órdenes -->
                <section class="glass-card rounded-[2.5rem] p-8 shadow-xl shadow-gray-200/50">
                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight mb-6">Órdenes de Trabajo</h3>
                    <div class="space-y-4">
                        <?php foreach($ordenes as $o): ?>
                            <div class="p-5 rounded-[2rem] bg-gray-50 border border-gray-100 hover:border-blue-200 transition-all">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-600 rounded-md text-[9px] font-black uppercase">#<?php echo $o['id']; ?></span>
                                    <span class="text-[10px] font-bold text-gray-400"><?php echo date('d/m', strtotime($o['created_at'])); ?></span>
                                </div>
                                <h4 class="font-bold text-gray-900 truncate mb-1 text-sm"><?php echo htmlspecialchars($o['descripcion'] ?? 'Orden de Trabajo'); ?></h4>
                                <a href="api_orden_pdf.php?id=<?php echo $o['id']; ?>&t=<?php echo $token; ?>" class="mt-3 w-full py-2 bg-white border border-gray-200 rounded-lg text-center text-[10px] font-black uppercase tracking-widest hover:bg-gray-100 transition-all block">PDF</a>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($ordenes)): ?>
                            <p class="text-center py-4 text-gray-400 italic text-xs">Sin órdenes activas.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Contacto Rápido -->
                <section class="bg-indigo-600 rounded-[2.5rem] p-8 text-white shadow-xl shadow-indigo-200">
                    <h3 class="text-lg font-black uppercase tracking-tight mb-4 text-indigo-100">Ayuda</h3>
                    <p class="text-sm mb-6 opacity-80 leading-relaxed font-medium">¿Dudas? Contáctanos ahora.</p>
                    <a href="https://wa.me/<?php echo $cliente['empresa_telefono'] ?? ''; ?>" target="_blank" class="w-full py-4 bg-white text-indigo-600 rounded-2xl text-center font-black uppercase tracking-widest shadow-xl hover:scale-105 transition-transform block">WhatsApp</a>
                </section>
            </div>

        </div>

    </main>
    
    <!-- Upload Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl space-y-4">
            <h3 class="text-xl font-black text-gray-900">Subir Documento</h3>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload_doc">
                <input type="file" name="archivo" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                <div class="flex gap-2">
                    <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 rounded-xl font-bold text-gray-500">Cancelar</button>
                    <button type="submit" class="flex-1 py-3 bg-indigo-600 rounded-xl font-bold text-white shadow-lg">Subir</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Share Modal -->
    <div id="shareModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl space-y-4">
            <h3 class="text-xl font-black text-gray-900">Compartir Documento</h3>
            
            <div id="shareLinkContainer" class="hidden bg-green-50 p-4 rounded-xl border border-green-100">
                <p class="text-xs font-bold text-green-600 mb-2">Enlace Generado (Copia para compartir):</p>
                <input type="text" id="shareResultLink" readonly class="w-full bg-white text-xs p-2 rounded border border-green-200 text-green-800 font-mono select-all">
            </div>

            <form id="shareForm" class="space-y-4">
                <input type="hidden" name="doc_id" id="shareDocId">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Contraseña (Opcional)</label>
                    <input type="text" name="password" id="sharePass" placeholder="Dejar vacío para público" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white outline-none">
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="allow_download" id="shareAllow" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300">
                    <label for="shareAllow" class="text-sm font-bold text-gray-700">Permitir Descarga</label>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="document.getElementById('shareModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 rounded-xl font-bold text-gray-500">Cerrar</button>
                    <button type="submit" class="flex-1 py-3 bg-indigo-600 rounded-xl font-bold text-white shadow-lg">Generar Enlace</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Stats Modal -->
    <div id="statsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-3xl p-8 max-w-lg w-full shadow-2xl flex flex-col max-h-[80vh]">
            <h3 class="text-xl font-black text-gray-900 mb-4">Historial de Accesos</h3>
            <div id="statsList" class="flex-1 overflow-y-auto space-y-2 p-1">
                <!-- Injected via JS -->
            </div>
            <button onclick="document.getElementById('statsModal').classList.add('hidden')" class="mt-4 w-full py-3 bg-gray-100 rounded-xl font-bold text-gray-500">Cerrar</button>
        </div>
    </div>

    <script>
        function openShare(id, token, allow, pass) {
            document.getElementById('shareDocId').value = id;
            document.getElementById('sharePass').value = pass || '';
            document.getElementById('shareAllow').checked = (allow == 1);
            document.getElementById('shareModal').classList.remove('hidden');
            document.getElementById('shareLinkContainer').classList.add('hidden');
            
            if(token) {
                 document.getElementById('shareResultLink').value = window.location.origin + '/ver-documento.php?t=' + token;
                 document.getElementById('shareLinkContainer').classList.remove('hidden');
            }
        }
        
        document.getElementById('shareForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = new FormData(e.target);
            form.append('action', 'share_settings');
            
            try {
                const res = await fetch('', { method: 'POST', body: form });
                const data = await res.json();
                if(data.status === 'success') {
                    document.getElementById('shareResultLink').value = data.link;
                    document.getElementById('shareLinkContainer').classList.remove('hidden');
                } else {
                    alert('Error: ' + data.message);
                }
            } catch(err) { alert('Error de conexión'); }
        });
        
        async function openStats(id) {
            document.getElementById('statsModal').classList.remove('hidden');
            const container = document.getElementById('statsList');
            container.innerHTML = '<p class="text-center py-4">Cargando...</p>';
            
            const form = new FormData();
            form.append('action', 'get_stats');
            form.append('doc_id', id);
            
            try {
                const res = await fetch('', { method: 'POST', body: form });
                const data = await res.json();
                
                if(data.status === 'success' && data.data.length > 0) {
                    container.innerHTML = data.data.map(log => `
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl border border-gray-100">
                            <div>
                                <p class="font-bold text-xs text-gray-900 uppercase">${log.tipo_evento}</p>
                                <p class="text-[10px] text-gray-400">${new Date(log.fecha).toLocaleString()}</p>
                            </div>
                            <span class="text-[10px] font-mono text-gray-500">${log.ip_address}</span>
                        </div>
                    `).join('');
                } else {
                     container.innerHTML = '<p class="text-center py-8 text-gray-400 text-sm">Sin actividad reciente.</p>';
                }
            } catch(e) { container.innerHTML = '<p class="text-red-400">Error al cargar.</p>'; }
        }
    </script>

</body>
</html>
