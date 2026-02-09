<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

if (!isset($_GET['id'])) {
    header("Location: clientes.php");
    exit;
}

$id = $_GET['id'];
$empresa_id = getEmpresaId();

// 1. Obtener Cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND empresa_id = ?");
$stmt->execute([$id, $empresa_id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    die("Cliente no encontrado.");
}

// --- LOGICA POST ADMIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Upload Admin Side
    // Upload Admin Side
    if ($action === 'upload_admin') {
        try {
            $file = $_FILES['archivo'] ?? null;
            if (!$file || $file['error'] !== 0) {
                throw new Exception("Error al subir archivo. Código: " . ($file['error'] ?? 'No file'));
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $pure_name = uniqid('admin_doc_') . '.' . $ext;
            
            // Determinar carpeta 
            $target_dir = __DIR__ . "/uploads/e{$empresa_id}/docs/";
            if (!file_exists($target_dir)) {
                if (!mkdir($target_dir, 0777, true)) {
                    throw new Exception("No se pudo crear el directorio de destino.");
                }
            }
            
            $dest_path = $target_dir . $pure_name;
            $db_path = "uploads/e{$empresa_id}/docs/" . $pure_name;
            $cat = $_POST['categoria'] ?? 'General';
            $mensaje_personalizado = $_POST['mensaje'] ?? '';
            $notificacion_tipo = $_POST['notificacion_tipo'] ?? 'email'; // email, whatsapp, both, none

            if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
                throw new Exception("Falló al mover el archivo al destino final.");
            }

            $user_id = $_SESSION['user_id'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO documentos (empresa_id, cliente_id, usuario_id, nombre_original, nombre_s3, bucket, extension, tamano, categoria, created_at) VALUES (?, ?, ?, ?, ?, 'local', ?, ?, ?, NOW())");
            $stmt->execute([$empresa_id, $id, $user_id, $file['name'], $db_path, $ext, $file['size'], $cat]);
            
            // --- NOTIFICACIÓN CONDICIONAL ---
            if ($notificacion_tipo !== 'none') {
                try {
                    // Obtener credenciales y SMTP
                    $stmtEmp = $pdo->prepare("SELECT nombre, smsenlinea_secret, smsenlinea_wa_account, smtp_host, smtp_user, smtp_pass, smtp_port, smtp_encryption, smtp_from_email FROM empresas WHERE id = ?");
                    $stmtEmp->execute([$empresa_id]);
                    $emp = $stmtEmp->fetch();
                    
                    if ($emp) {
                         // Link al portal
                         $link_portal = "https://" . $_SERVER['HTTP_HOST'] . "/mi-area.php?t=" . ($cliente['token_acceso'] ?? '');
                         
                         $doc_info = "*{$file['name']}* ({$cat})";
                         $msg_cliente = "Hola {$cliente['nombre_contacto']}! {$emp['nombre']} ha compartido un nuevo documento contigo: {$doc_info}.";
                         if($mensaje_personalizado) $msg_cliente .= "\n\nNota: " . $mensaje_personalizado;
                         $msg_cliente .= "\n\nPuedes verlo en tu portal: $link_portal";

                         // 1. WhatsApp
                         if (($notificacion_tipo === 'whatsapp' || $notificacion_tipo === 'both') && $cliente['celular_contacto']) {
                             require_once 'includes/whatsapp_helper.php';
                             enviarWhatsApp($pdo, $empresa_id, $cliente['celular_contacto'], $msg_cliente, null, 'text', $cliente['pais_codigo'] ?: '57');
                         }

                         // 2. Email
                         if (($notificacion_tipo === 'email' || $notificacion_tipo === 'both') && $cliente['email']) {
                             if (file_exists(__DIR__ . '/includes/mail_helper.php')) {
                                 require_once __DIR__ . '/includes/mail_helper.php';
                                 
                                 $infoEmail = $emp;
                                 $infoEmail['cliente_email'] = $cliente['email'];
                                 $infoEmail['empresa_nombre'] = $emp['nombre'];
                                 
                                 $asunto = "Nuevo Documento: {$file['name']} - {$emp['nombre']}";
                                 
                                 $extra_msg = "";
                                 if($mensaje_personalizado) {
                                     $extra_msg = "<div style='background:#f3f4f6; padding:15px; border-radius:8px; margin:15px 0; font-style:italic;'>\"" . nl2br(htmlspecialchars($mensaje_personalizado)) . "\"</div>";
                                 }

                                 $cuerpo = "<p>Hola <strong>" . htmlspecialchars($cliente['nombre_contacto']) . "</strong>,</p>
                                            <p>Se ha cargado un nuevo documento a tu carpeta de cliente.</p>
                                            <ul>
                                                <li><strong>Archivo:</strong> " . htmlspecialchars($file['name']) . "</li>
                                                <li><strong>Categoría:</strong> " . htmlspecialchars($cat) . "</li>
                                                <li><strong>Fecha:</strong> " . date('d/m/Y H:i') . "</li>
                                            </ul>
                                            $extra_msg
                                            <p>Puedes acceder a tu portal para visualizarlo o descargarlo.</p>
                                            <p><a href='$link_portal' style='padding:10px 20px; background:#4f46e5; color:white; text-decoration:none; border-radius:5px;'>Ir a mi Portal</a></p>";
                                 
                                 enviarEmailGenerico($infoEmail, $asunto, $cuerpo);
                             }
                         }
                    }
                } catch (Exception $eN) {
                    error_log("Error notificando documento: " . $eN->getMessage());
                    // No detenemos el flujo si falla la notificación, solo logueamos
                }
            }
            
            // Refresh to see change
            header("Location: ?id=$id&msg=subido");
            exit;

        } catch (Exception $e) {
            $error_msg = urlencode($e->getMessage());
            header("Location: ?id=$id&error=$error_msg");
            exit;
        }
    }
    
    // Stats Admin Side
    if ($action === 'get_stats_admin') {
        header('Content-Type: application/json');
        $doc_id = $_POST['doc_id'] ?? 0;
        // Verify ownership via client/company
        $stmt = $pdo->prepare("SELECT id FROM documentos WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$doc_id, $empresa_id]);
        if($stmt->fetch()) {
            $logs = $pdo->prepare("SELECT * FROM documento_logs WHERE documento_id = ? ORDER BY fecha DESC LIMIT 50");
            $logs->execute([$doc_id]);
            echo json_encode(['status' => 'success', 'data' => $logs->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }
        echo json_encode(['status' => 'error']);
        exit;
    }
    // Create OT Admin Side
    if ($action === 'crear_orden') {
        $desc = $_POST['descripcion'] ?? 'Orden Manual';
        $fecha = $_POST['fecha_programada'] ?? date('Y-m-d');
        $resp = $_POST['responsable_id'] ?? null;
        
        $sql = "INSERT INTO ordenes_trabajo (empresa_id, cliente_id, cotizacion_id, estado, fecha_programada, responsable_id, descripcion, fecha_creacion) VALUES (?, ?, 0, 'Pendiente', ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$empresa_id, $id, $fecha, $resp, $desc]);
        
        header("Location: ?id=$id&msg=ot_creada");
        exit;
    }
}


// 2. Obtener Cotizaciones
$stmtCot = $pdo->prepare("SELECT id, numero_cotizacion, created_at, estado, total, hash_publico, aceptada_data FROM cotizaciones WHERE cliente_id = ? ORDER BY created_at DESC");
$stmtCot->execute([$id]);
$cotizaciones = $stmtCot->fetchAll();

// 3. Obtener Ordenes (si tabla existe)
try {
    $stmtOrd = $pdo->prepare("SELECT * FROM ordenes_trabajo WHERE cliente_id = ? ORDER BY created_at DESC");
    $stmtOrd->execute([$id]);
    $ordenes = $stmtOrd->fetchAll();
} catch (Exception $e) { $ordenes = []; }

// 3.5 Obtener Facturas
try {
    $stmtFac = $pdo->prepare("SELECT * FROM facturas WHERE cliente_id = ? ORDER BY created_at DESC");
    $stmtFac->execute([$id]);
    $facturas = $stmtFac->fetchAll();
} catch (Exception $e) { $facturas = []; }

// 4. Documentos
try {
    $stmtDoc = $pdo->prepare("SELECT * FROM documentos WHERE cliente_id = ? ORDER BY created_at DESC");
    $stmtDoc->execute([$id]);
    $documentos = $stmtDoc->fetchAll();
} catch (Exception $e) { $documentos = []; }

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto space-y-8">
    
    <?php if(isset($_GET['error'])): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4 rounded-r-lg shadow-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700 font-bold">
                    Ocurrió un error:
                </p>
                <p class="text-xs text-red-600">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Header Perfil -->
    <div class="bg-white rounded-[2.5rem] p-8 card-shadow border border-gray-100 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
            <div class="flex items-center gap-6">
                <div class="w-24 h-24 bg-gradient-to-br from-indigo-50 to-blue-100 text-indigo-600 rounded-3xl flex items-center justify-center font-black text-4xl shadow-inner uppercase">
                    <?php echo substr($cliente['nombre'], 0, 1); ?>
                </div>
                <div>
                    <h1 class="text-3xl font-black text-gray-900 tracking-tight mb-2"><?php echo htmlspecialchars($cliente['nombre']); ?></h1>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-lg text-[10px] font-black uppercase tracking-widest">ID: <?php echo htmlspecialchars($cliente['identificacion']); ?></span>
                        <span class="px-3 py-1 bg-green-50 text-green-600 rounded-lg text-[10px] font-black uppercase tracking-widest flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span> Activo
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3">
                <?php if($cliente['celular_contacto']): ?>
                <a href="#" onclick="window.open('whatsapp.php?telefono=<?php echo $cliente['pais_codigo'] . $cliente['celular_contacto']; ?>', 'waWindow', 'width=1000,height=700'); return false;" class="px-6 py-3 bg-green-500 text-white rounded-2xl font-bold shadow-lg shadow-green-200 hover:scale-105 transition-all flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    WhatsApp
                </a>
                <?php endif; ?>
                <a href="clientes.php" class="px-6 py-3 bg-gray-100 text-gray-600 rounded-2xl font-bold hover:bg-gray-200 transition-all">
                    Volver
                </a>
            </div>
        </div>
        
        <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-6 pt-8 border-t border-gray-50">
            <div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Contacto</p>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($cliente['nombre_contacto'] ?? 'N/A'); ?></p>
                <?php if($cliente['cargo_contacto']): ?>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($cliente['cargo_contacto']); ?></p>
                <?php endif; ?>
            </div>
             <div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Email</p>
                <a href="mailto:<?php echo $cliente['email']; ?>" class="font-bold text-indigo-600 hover:underline"><?php echo htmlspecialchars($cliente['email'] ?? 'N/A'); ?></a>
            </div>
            <div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Teléfonos</p>
                <p class="text-sm font-medium text-gray-600">
                    <?php if($cliente['telefono']) echo "T: " . $cliente['telefono'] . "<br>"; ?>
                    <?php if($cliente['celular_contacto']) echo "C: +" . $cliente['pais_codigo'] . " " . $cliente['celular_contacto']; ?>
                </p>
            </div>
            <div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Portal Cliente</p>
                <?php if($cliente['token_acceso']): ?>
                    <a href="mi-area.php?t=<?php echo $cliente['token_acceso']; ?>" target="_blank" class="text-xs font-bold text-blue-500 hover:text-blue-700 flex items-center gap-1">
                        Ver como Cliente <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>
                <?php else: ?>
                    <span class="text-xs text-red-400 italic">Sin acceso generado</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabs Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Main Column -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- Cotizaciones -->
            <section class="bg-white rounded-[2.5rem] p-8 card-shadow border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight">Historial de Cotizaciones</h3>
                    <a href="nueva-cotizacion.php?cliente_id=<?php echo $id; ?>" class="px-4 py-2 bg-indigo-50 text-indigo-600 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all">
                        + Nueva
                    </a>
                </div>

                <div class="space-y-4">
                    <?php foreach($cotizaciones as $c): ?>
                        <div class="flex flex-col gap-2 p-4 rounded-2xl hover:bg-gray-50 transition-all border border-transparent hover:border-gray-100 group">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl <?php echo $c['estado']=='Enviada'?'bg-blue-50 text-blue-600':($c['estado']=='Aprobada'?'bg-green-50 text-green-600':'bg-gray-100 text-gray-500'); ?> flex items-center justify-center font-black">
                                        #<?php echo $c['id']; ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900"><?php echo $c['numero_cotizacion'] ?: ('Cot #' . $c['id']); ?></p>
                                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest"><?php echo date('d M, Y', strtotime($c['created_at'])); ?> • <?php echo $c['estado']; ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <p class="text-lg font-black text-gray-900">$<?php echo number_format($c['total'], 0); ?></p>
                                    
                                    <!-- Ver Propuesta -->
                                    <a href="propuesta.php?h=<?php echo $c['hash_publico']; ?>" target="_blank" class="p-2 text-gray-300 hover:text-blue-600 transition-colors" title="Ver Propuesta Pública">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    
                                    <!-- Editar -->
                                    <?php if($c['estado'] != 'Aprobada' && $c['estado'] != 'Facturada'): ?>
                                    <a href="nueva-cotizacion.php?id=<?php echo $c['id']; ?>" class="p-2 text-gray-300 hover:text-indigo-600 transition-colors" title="Editar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </a>
                                    <?php elseif($c['estado'] == 'Facturada'): ?>
                                    <span class="p-2 text-green-500" title="Facturada">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </span>
                                    <?php else: ?>
                                    <a href="nueva-factura.php?cotizacion_id=<?php echo $c['id']; ?>" class="p-2 text-gray-300 hover:text-green-600 transition-colors" title="Facturar Cotización">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Documentos de Aceptación -->
                            <?php 
                            $meta = json_decode($c['aceptada_data'] ?? '[]', true);
                            if (!empty($meta['documentos'])): 
                            ?>
                            <div class="mt-2 pl-16">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Adjuntos de Cliente:</p>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach($meta['documentos'] as $docPath): 
                                        $fName = basename($docPath);
                                    ?>
                                    <a href="<?php echo htmlspecialchars($docPath); ?>" target="_blank" class="flex items-center gap-2 px-3 py-1.5 bg-green-50 text-green-700 rounded-lg text-xs font-bold hover:bg-green-100 transition-colors border border-green-100">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                        <?php echo htmlspecialchars(substr($fName, 0, 20)) . '...'; ?>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if(empty($cotizaciones)): ?>
                        <div class="text-center py-10">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-300">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <p class="text-gray-400 text-sm font-medium">Sin cotizaciones registradas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            </section>

             <!-- Facturas -->
             <section class="bg-white rounded-[2.5rem] p-8 card-shadow border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight">Historial de Facturación</h3>
                    <a href="nueva-factura.php?cliente_id=<?php echo $id; ?>" class="px-4 py-2 bg-green-50 text-green-600 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-green-600 hover:text-white transition-all">
                        + Nueva Factura
                    </a>
                </div>

                <div class="space-y-4">
                    <?php if(!empty($facturas)): ?>
                        <?php foreach($facturas as $f): 
                            $bgStatusF = match($f['estado']) {
                                'Borrador' => 'bg-gray-100 text-gray-500',
                                'Enviada' => 'bg-blue-50 text-blue-600',
                                'Pagada' => 'bg-green-50 text-green-600',
                                'Anulada' => 'bg-red-50 text-red-600',
                                default => 'bg-gray-100'
                            };
                        ?>
                        <div class="flex flex-col gap-2 p-4 rounded-2xl hover:bg-gray-50 transition-all border border-transparent hover:border-gray-100 group">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-gray-50 text-gray-400 flex items-center justify-center font-black">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($f['numero_factura']); ?></p>
                                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest"><?php echo date('d M, Y', strtotime($f['fecha_emision'])); ?> • <?php echo $f['estado']; ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <p class="text-lg font-black text-gray-900">$<?php echo number_format($f['total'], 0); ?></p>
                                    
                                    <a href="ver-factura.php?h=<?php echo $f['hash_publico']; ?>" target="_blank" class="p-2 text-gray-300 hover:text-blue-600 transition-colors" title="Ver Factura">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    
                                    <a href="nueva-factura.php?id=<?php echo $f['id']; ?>" class="p-2 text-gray-300 hover:text-indigo-600 transition-colors" title="Editar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <p class="text-gray-400 text-sm font-medium">Sin facturas registradas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
             <!-- Ordenes -->
             <section class="bg-white rounded-[2.5rem] p-8 card-shadow border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight">Órdenes de Trabajo</h3>
                    <button onclick="document.getElementById('modalOrden').classList.remove('hidden')" class="px-4 py-2 bg-blue-50 text-blue-600 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all">
                        + Crear OT
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach($ordenes as $o): ?>
                        <div class="p-6 rounded-[2rem] bg-gray-50 border border-gray-100 hover:border-blue-200 transition-all">
                             <div class="flex justify-between items-start mb-4">
                                <span class="px-3 py-1 bg-blue-100 text-blue-600 rounded-lg text-[9px] font-black uppercase">OT #<?php echo $o['id']; ?></span>
                                <span class="text-xs font-bold text-gray-400"><?php echo date('d/m', strtotime($o['created_at'])); ?></span>
                            </div>
                            <h4 class="font-bold text-gray-900 truncate mb-1"><?php echo htmlspecialchars($o['descripcion'] ?? 'Orden de Trabajo'); ?></h4>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-3">Prog: <?php echo $o['fecha_programada'] ? date('d M', strtotime($o['fecha_programada'])) : 'Sin Fecha'; ?></p>
                            <a href="ver-orden.php?id=<?php echo $o['id']; ?>" class="text-xs font-bold text-indigo-600 hover:underline block">Ver Detalles &rarr;</a>
                        </div>
                    <?php endforeach; ?>
                    <?php if(empty($ordenes)): ?>
                        <p class="col-span-full text-center py-6 text-gray-400 text-xs italic">No hay órdenes activas.</p>
                    <?php endif; ?>
                </div>
             </section>

             <!-- Recordatorios -->
             <section class="bg-white rounded-[2.5rem] p-8 card-shadow border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight">Recordatorios</h3>
                    <button onclick="abrirModalRecordatoriosPropio()" class="px-4 py-2 bg-amber-50 text-amber-600 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-amber-600 hover:text-white transition-all">
                        + Crear Recordatorio
                    </button>
                </div>
                
                <div id="loading-rec" class="text-center py-4 text-gray-400 text-xs hidden">Cargando...</div>
                <div id="lista-recordatorios" class="space-y-3">
                    <!-- JS Loaded -->
                </div>
             </section>

        </div>

        <!-- Sidebar Column -->
        <div class="space-y-8">
            
            <!-- Documentos -->
            <section class="bg-white rounded-[2.5rem] p-8 card-shadow border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-black text-gray-900 uppercase tracking-tight">Documentos</h3>
                    <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white rounded-lg p-2 transition-all" title="Subir Documento">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    </button>
                </div>
                <div class="space-y-3">
                     <?php foreach($documentos as $d): ?>
                        <div class="group flex flex-col gap-2 p-3 bg-gray-50 rounded-xl border border-transparent hover:border-gray-200 transition-all">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-gray-900 truncate"><?php echo htmlspecialchars($d['nombre_original']); ?></p>
                                    <p class="text-[9px] text-gray-400 uppercase"><?php echo $d['categoria']; ?> • <?php echo $d['allow_download']?'DL':'View'; ?></p>
                                </div>
                                <a href="<?php echo htmlspecialchars($d['nombre_s3']); ?>" target="_blank" class="text-gray-400 hover:text-indigo-600" title="Descargar/Ver">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                </a>
                            </div>
                            <div class="flex gap-2 pt-2 border-t border-gray-100">
                                <button onclick="openShare(<?php echo $d['id']; ?>, '<?php echo $d['share_token']; ?>', <?php echo $d['allow_download']; ?>, '<?php echo $d['share_password']; ?>')" class="flex-1 py-1 bg-white text-indigo-600 text-[10px] font-bold uppercase rounded border border-indigo-100 hover:bg-indigo-50">Compartir</button>
                                <button onclick="openStats(<?php echo $d['id']; ?>)" class="flex-1 py-1 bg-white text-blue-600 text-[10px] font-bold uppercase rounded border border-blue-100 hover:bg-blue-50">Estadísticas</button>
                            </div>
                        </div>
                     <?php endforeach; ?>
                     <?php if(empty($documentos)): ?>
                        <p class="text-center text-xs text-gray-400 italic">Sin documentos.</p>
                     <?php endif; ?>
                </div>
            </section>

             <!-- Acciones Rápidas -->
            <section class="bg-indigo-600 rounded-[2.5rem] p-8 text-white shadow-xl shadow-indigo-200">
                <h3 class="text-lg font-black uppercase tracking-tight mb-4 text-indigo-100">Acciones</h3>
                <div class="space-y-3">
                    <a href="nueva-cotizacion.php?cliente_id=<?php echo $id; ?>" class="block w-full py-3 bg-white/10 hover:bg-white/20 rounded-xl text-center text-xs font-black uppercase tracking-widest transition-all">
                        Crear Cotización
                    </a>
                    <button onclick="abrirModalEditarCliente()" class="block w-full py-3 bg-white/10 hover:bg-white/20 rounded-xl text-center text-xs font-black uppercase tracking-widest transition-all text-white">
                        Editar Información
                    </button>
                </div>
            </section>

        </div>
    </div>

</div>

<!-- Modal Recordatorios -->
<div id="modalRecordatorios" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-8 max-w-lg w-full shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-black text-gray-900">Programar Recordatorio</h3>
        
        <form id="form-rec" onsubmit="guardarRecordatorio(event)" class="space-y-4">
            <input type="hidden" name="cliente_id" value="<?php echo $id; ?>">
            <input type="hidden" name="action" value="create">
            
            <!-- Selector de Cotización (Opcional) -->
            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Cotización (Opcional)</label>
                 <select name="cotizacion_id" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                     <option value="">-- Sin ligar a cotización --</option>
                     <?php foreach($cotizaciones as $c): ?>
                        <option value="<?php echo $c['id']; ?>">
                            #<?php echo $c['id']; ?> - $<?php echo number_format($c['total']); ?> (<?php echo $c['estado']; ?>)
                        </option>
                     <?php endforeach; ?>
                 </select>
            </div>

            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Asunto / Título</label>
                 <input type="text" name="asunto" required placeholder="Ej: Llamada de seguimiento mensual" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
            </div>

            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Mensaje / Detalle</label>
                 <textarea name="mensaje" rows="3" placeholder="Detalles adicionales..." class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Fecha y Hora</label>
                     <input type="datetime-local" name="fecha_programada" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                </div>
            </div>

            <!-- Pre-Recordatorio -->
            <div class="p-4 bg-amber-50 rounded-xl border border-amber-100">
                <label class="flex items-center gap-2 mb-3 cursor-pointer">
                    <input type="checkbox" id="rec-pre-enable" name="tiene_prerecordatorio" value="1" onchange="togglePreRec()" class="w-4 h-4 text-amber-600 rounded border-gray-300 focus:ring-amber-500">
                    <span class="text-xs font-bold text-amber-800 uppercase tracking-widest">Enviar Pre-Aviso</span>
                </label>
                <div id="rec-pre-fields" class="hidden space-y-3 pl-6 border-l-2 border-amber-200">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-600">Enviar</span>
                        <select name="dias_antes" class="p-1 bg-white border border-gray-200 rounded text-xs font-bold">
                            <option value="1">1 día antes</option>
                            <option value="2">2 días antes</option>
                            <option value="3">3 días antes</option>
                        </select>
                        <span class="text-xs text-gray-600">de la fecha programada.</span>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Mensaje del Pre-Aviso</label>
                        <textarea name="mensaje_prerecordatorio" id="rec-pre-mensaje" class="w-full p-2 bg-white border border-gray-200 rounded-lg text-xs h-16 resize-none" placeholder="Hola, recuerda que mañana..."></textarea>
                    </div>
                </div>
            </div>
            
             <div class="pt-2">
                <label class="flex items-center gap-3 p-3 border border-slate-100 rounded-xl cursor-pointer hover:bg-slate-50">
                    <input type="checkbox" name="notificar_cliente" checked class="w-4 h-4 text-amber-500 rounded border-slate-300 focus:ring-amber-400">
                    <div>
                        <p class="text-xs font-bold text-slate-700">Notificar al Cliente</p>
                        <p class="text-[10px] text-slate-400">Se enviará por Email y WhatsApp (si está configurado)</p>
                    </div>
                </label>
            </div>

            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('modalRecordatorios').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 rounded-xl font-bold text-gray-500">Cancelar</button>
                <button type="submit" class="flex-1 py-3 bg-amber-500 hover:bg-amber-600 rounded-xl font-bold text-white shadow-lg transition-all">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal OT -->
<div id="modalOrden" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl space-y-4">
        <h3 class="text-xl font-black text-gray-900">Nueva Orden de Trabajo</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="crear_orden">
            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Descripción</label>
                 <textarea name="descripcion" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors" placeholder="Detalle del trabajo..."></textarea>
            </div>
            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Fecha Programada</label>
                 <input type="date" name="fecha_programada" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
            </div>
            
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modalOrden').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 rounded-xl font-bold text-gray-500">Cancelar</button>
                <button type="submit" class="flex-1 py-3 bg-blue-600 rounded-xl font-bold text-white shadow-lg">Crear OT</button>
            </div>
        </form>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl space-y-4">
        <h3 class="text-xl font-black text-gray-900">Subir Documento</h3>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="upload_admin">
            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Archivo</label>
                 <input type="file" name="archivo" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
            </div>
            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Categoría</label>
                 <input type="text" name="categoria" list="cat_list_real" placeholder="Escribe o selecciona..." class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                 <datalist id="cat_list_real">
                     <option value="General">
                     <option value="Contrato">
                     <option value="Factura">
                     <option value="Propuesta">
                     <option value="Certificado">
                 </datalist>
            </div>
            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Mensaje (Opcional)</label>
                 <textarea name="mensaje" rows="2" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors" placeholder="Mensaje para el cliente..."></textarea>
            </div>
            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Notificación</label>
                 <select name="notificacion_tipo" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                     <option value="email">Solo Email</option>
                     <option value="whatsapp">Solo WhatsApp</option>
                     <option value="both">Ambos (Email + WhatsApp)</option>
                     <option value="none">Ninguna</option>
                 </select>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 rounded-xl font-bold text-gray-500">Cancelar</button>
                <button type="submit" class="flex-1 py-3 bg-indigo-600 rounded-xl font-bold text-white shadow-lg">Subir</button>
            </div>
        </form>
    </div>
</div>

<!-- Share Modal -->
<div id="shareModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-xl font-black text-gray-900">Compartir Documento</h3>
        
        <div id="shareLinkContainer" class="hidden bg-green-50 p-4 rounded-xl border border-green-100">
            <p class="text-xs font-bold text-green-600 mb-2">Enlace Generado:</p>
            <div class="flex gap-2">
                <input type="text" id="shareResultLink" readonly class="flex-1 bg-white text-xs p-2 rounded border border-green-200 text-green-800 font-mono select-all">
                <button onclick="copyLink()" class="p-2 bg-green-200 text-green-700 rounded hover:bg-green-300">
                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2v1m2 13h3m-3-3h3m-3 3v3m0-3v-3"></path></svg>
                </button>
            </div>
             <p class="text-[10px] text-green-600/70 mt-2">Puedes enviar este enlace a tu cliente por WhatsApp.</p>
        </div>

        <form id="shareForm" class="space-y-4">
            <input type="hidden" name="accion" value="compartir">
            <input type="hidden" name="id" id="shareDocId">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Contraseña (Opcional)</label>
                <input type="text" name="password" id="sharePass" placeholder="Protección extra..." class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white outline-none">
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" name="allow_download" id="shareAllow" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300">
                <label for="shareAllow" class="text-sm font-bold text-gray-700">Permitir Descarga</label>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('shareModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 rounded-xl font-bold text-gray-500">Cerrar</button>
                <button type="submit" class="flex-1 py-3 bg-indigo-600 rounded-xl font-bold text-white shadow-lg">Generar</button>
            </div>
        </form>
    </div>
</div>

<!-- Stats Modal -->
<div id="statsModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-8 max-w-lg w-full shadow-2xl flex flex-col max-h-[80vh]">
        <h3 class="text-xl font-black text-gray-900 mb-4">Estadísticas de Acceso</h3>
        <div id="statsList" class="flex-1 overflow-y-auto space-y-2 p-1"></div>
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
        
        try {
            const res = await fetch('api_documentos.php', { method: 'POST', body: form });
            const data = await res.json();
            if(data.status === 'success') {
                document.getElementById('shareResultLink').value = data.link;
                document.getElementById('shareLinkContainer').classList.remove('hidden');
            } else {
                alert('Error: ' + data.message);
            }
        } catch(err) { alert('Error de conexión'); }
    });
    
    function copyLink() {
        const copyText = document.getElementById("shareResultLink");
        copyText.select();
        document.execCommand("copy");
        alert("Enlace copiado al portapapeles");
    }
    
    async function openStats(id) {
        document.getElementById('statsModal').classList.remove('hidden');
        const container = document.getElementById('statsList');
        container.innerHTML = '<p class="text-center py-4">Cargando...</p>';
        
        const form = new FormData();
        form.append('action', 'get_stats_admin');
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
                         <div class="text-right">
                             <span class="text-[10px] font-mono text-gray-500 block">${log.ip_address}</span>
                             <span class="text-[9px] text-gray-300 truncate max-w-[100px] block">${log.user_agent}</span>
                         </div>
                    </div>
                `).join('');
            } else {
                 container.innerHTML = '<p class="text-center py-8 text-gray-400 text-sm">Sin actividad reciente.</p>';
            }
        } catch(e) { container.innerHTML = '<p class="text-red-400">Error al cargar.</p>'; }
    }

    // --- RECORDATORIOS ---
    const CLIENT_ID = <?php echo $id; ?>;
    
    function abrirModalRecordatoriosPropio() {
        document.getElementById('modalRecordatorios').classList.remove('hidden');
    }
    
    function togglePreRec() {
        const check = document.getElementById('rec-pre-enable').checked;
        document.getElementById('rec-pre-fields').classList.toggle('hidden', !check);
        if(check && !document.getElementById('rec-pre-mensaje').value) {
             document.getElementById('rec-pre-mensaje').value = "Hola, te recordamos que pronto tienes una actividad programada con nosotros.";
        }
    }
    
    function cargarRecordatoriosCliente() {
        const container = document.getElementById('lista-recordatorios');
        document.getElementById('loading-rec').classList.remove('hidden');
        
        fetch('api_recordatorios.php?action=list&cliente_id=' + CLIENT_ID)
            .then(res => res.json())
            .then(data => {
                document.getElementById('loading-rec').classList.add('hidden');
                if(data.status === 'success' && data.data.length > 0) {
                    container.innerHTML = data.data.map(r => {
                        let estadoClass = r.estado === 'Pendiente' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700';
                        if(r.estado === 'Fallido') estadoClass = 'bg-red-100 text-red-700';
                        
                        // Icon Type
                        let icon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                        if(r.cotizacion_id) icon = '<span class="text-[10px] font-black mr-1">#'+r.cotizacion_id+'</span>' + icon;

                        return `
                        <div class="flex flex-col gap-2 p-3 bg-gray-50 rounded-xl border border-transparent hover:border-gray-200 transition-all">
                             <div class="flex justify-between items-start">
                                <div class="flex items-center gap-2">
                                     <span class="p-1 rounded bg-white text-gray-500 shadow-sm">${icon}</span>
                                     <p class="text-xs font-bold text-gray-800">${r.asunto}</p>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase ${estadoClass}">${r.estado}</span>
                             </div>
                             <p class="text-[10px] text-gray-500 pl-8 line-clamp-2">${r.mensaje || 'Sin detalles'}</p>
                             <div class="flex justify-between items-center pl-8 pt-1">
                                 <span class="text-[10px] font-mono text-gray-400">${new Date(r.fecha_programada).toLocaleString()}</span>
                                 <button onclick="eliminarRecordatorio(${r.id})" class="text-red-300 hover:text-red-500 font-bold text-[10px] uppercase">Eliminar</button>
                             </div>
                        </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<p class="text-center text-xs text-gray-400 italic">No hay recordatorios programados.</p>';
                }
            })
            .catch(() => {
                 document.getElementById('loading-rec').classList.add('hidden');
                 container.innerHTML = '<p class="text-red-400 text-xs">Error al cargar</p>';
            });
    }

    async function guardarRecordatorio(e) {
        e.preventDefault();
        const form = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.disabled = true; submitBtn.innerText = 'Guardando...';

        try {
            const res = await fetch('api_recordatorios.php', { method: 'POST', body: form });
            const data = await res.json();
            
            if(data.status === 'success') {
                alert('Recordatorio creado exitosamente');
                document.getElementById('modalRecordatorios').classList.add('hidden');
                e.target.reset();
                document.getElementById('rec-pre-fields').classList.add('hidden');
                cargarRecordatoriosCliente();
            } else {
                alert('Error: ' + data.message);
            }
        } catch(err) {
            alert('Error de conexión');
        } finally {
            submitBtn.disabled = false; submitBtn.innerText = originalText;
        }
    }

    async function eliminarRecordatorio(id) {
        if(!confirm('¿Seguro que deseas eliminar este recordatorio?')) return;
        
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);

        try {
            await fetch('api_recordatorios.php', { method: 'POST', body: fd });
            cargarRecordatoriosCliente();
        } catch(e) { alert('Error al eliminar'); }
    }
    
    // --- EDITAR CLIENTE ---
    function abrirModalEditarCliente() {
        document.getElementById('modalEditarCliente').classList.remove('hidden');
    }

    async function guardarEdicionCliente(e) {
        e.preventDefault();
        const form = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.disabled = true; submitBtn.innerText = 'Guardando...';

        try {
            const res = await fetch('api_update_client.php', { method: 'POST', body: form });
            const data = await res.json();
            
            if(data.status === 'success') {
                alert('Información actualizada correctamente');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch(err) {
            alert('Error de conexión');
        } finally {
            submitBtn.disabled = false; submitBtn.innerText = originalText;
        }
    }
    
    // Init
    cargarRecordatoriosCliente();
</script>

<!-- Modal Editar Cliente -->
<div id="modalEditarCliente" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl p-8 max-w-2xl w-full shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-black text-gray-900">Editar Información del Cliente</h3>
        
        <form onsubmit="guardarEdicionCliente(event)" class="space-y-4">
            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Nombre / Razón Social</label>
                     <input type="text" name="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Identificación / NIT</label>
                     <input type="text" name="identificacion" value="<?php echo htmlspecialchars($cliente['identificacion']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Email Principal</label>
                     <input type="email" name="email" value="<?php echo htmlspecialchars($cliente['email']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Teléfono Fijo</label>
                     <input type="text" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Cód. País</label>
                     <input type="text" name="pais_codigo" value="<?php echo htmlspecialchars($cliente['pais_codigo']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                </div>
                <div class="md:col-span-2">
                     <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Celular / WhatsApp</label>
                     <input type="text" name="celular_contacto" value="<?php echo htmlspecialchars($cliente['celular_contacto']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                </div>
            </div>

            <div>
                 <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Dirección</label>
                 <input type="text" name="direccion" value="<?php echo htmlspecialchars($cliente['direccion']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
            </div>

            <div class="min-h-[1px] bg-gray-100 my-4"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Nombre Contacto</label>
                     <input type="text" name="nombre_contacto" value="<?php echo htmlspecialchars($cliente['nombre_contacto']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Cargo Contacto</label>
                     <input type="text" name="cargo_contacto" value="<?php echo htmlspecialchars($cliente['cargo_contacto']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-indigo-500 outline-none transition-colors">
                </div>
            </div>

            <div class="flex gap-2 pt-4">
                <button type="button" onclick="document.getElementById('modalEditarCliente').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 rounded-xl font-bold text-gray-500">Cancelar</button>
                <button type="submit" class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl font-bold text-white shadow-lg transition-all">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
