<?php
require_once 'db.php';

// Obtener Datos actuales para evitar pérdida de datos en el UPDATE
$empresa_id = getEmpresaId();
if (!$empresa_id) {
    // Si no hay empresa_id (ej: Super Admin), redirigir o mostrar error amigable
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
        header("Location: saas_dashboard.php");
        exit;
    }
    die("Error: No se encontró una empresa asociada a su usuario.");
}

$stmt_emp = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt_emp->execute([$empresa_id]);
$empresa = $stmt_emp->fetch();

if (!$empresa) {
    die("Error: La empresa no existe.");
}

// Procesar Acciones (Empresa, Impuestos y Unidades)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'actualizar_empresa':
                // Manejo de Logo
                $logo_path = $empresa['logo'] ?? null;
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                        $target_dir = getCompanyUploadPath('logos', true);
                        $nombre_archivo = 'logo_' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_dir . $nombre_archivo)) {
                            $logo_path = getCompanyUploadPath('logos') . $nombre_archivo;
                        }
                    }
                }

                $stmt = $pdo->prepare("UPDATE empresas SET nombre = ?, nit = ?, moneda = ?, color_hex = ?, timezone = ?, wa_provider = ?, smsenlinea_secret = ?, smsenlinea_wa_account = ?, evolution_api_url = ?, evolution_api_key = ?, evolution_instance_name = ?, evolution_instance_token = ?, google_maps_api_key = ?, smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_from_email = ?, smtp_pass = ?, smtp_encryption = ?, openai_api_key = ?, gemini_api_key = ?, claude_api_key = ?, storage_type = ?, s3_bucket = ?, s3_region = ?, s3_access_key = ?, s3_secret_key = ?, starting_quote_number = ?, quote_prefix = ?, quote_suffix = ?, starting_invoice_number = ?, invoice_prefix = ?, invoice_suffix = ?, logo = ?, social_facebook = ?, social_instagram = ?, social_linkedin = ?, social_twitter = ?, social_website = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['nombre'] ?? $empresa['nombre'], 
                    $_POST['nit'] ?? $empresa['nit'], 
                    $_POST['moneda'] ?? $empresa['moneda'], 
                    $_POST['color_hex'] ?? $empresa['color_hex'], 
                    $_POST['timezone'] ?? ($empresa['timezone'] ?? 'America/Bogota'),
                    $_POST['wa_provider'] ?? ($empresa['wa_provider'] ?? 'smsenlinea'),
                    $_POST['smsenlinea_secret'] ?? $empresa['smsenlinea_secret'],
                    $_POST['smsenlinea_wa_account'] ?? $empresa['smsenlinea_wa_account'],
                    $_POST['evolution_api_url'] ?? $empresa['evolution_api_url'], 
                    $_POST['evolution_api_key'] ?? $empresa['evolution_api_key'],
                    $_POST['evolution_instance_name'] ?? $empresa['evolution_instance_name'],
                    $_POST['evolution_instance_token'] ?? $empresa['evolution_instance_token'],
                    $_POST['google_maps_api_key'] ?? $empresa['google_maps_api_key'],
                    $_POST['smtp_host'] ?? $empresa['smtp_host'],
                    $_POST['smtp_port'] ?? $empresa['smtp_port'],
                    $_POST['smtp_user'] ?? $empresa['smtp_user'],
                    $_POST['smtp_from_email'] ?? $empresa['smtp_from_email'],
                    $_POST['smtp_pass'] ?? $empresa['smtp_pass'],
                    $_POST['smtp_encryption'] ?? $empresa['smtp_encryption'],
                    $_POST['openai_api_key'] ?? $empresa['openai_api_key'],
                    $_POST['gemini_api_key'] ?? $empresa['gemini_api_key'],
                    $_POST['claude_api_key'] ?? $empresa['claude_api_key'],
                    $_POST['storage_type'] ?? $empresa['storage_type'],
                    $_POST['s3_bucket'] ?? $empresa['s3_bucket'],
                    $_POST['s3_region'] ?? $empresa['s3_region'],
                    $_POST['s3_access_key'] ?? $empresa['s3_access_key'],
                    $_POST['s3_secret_key'] ?? $empresa['s3_secret_key'],
                    $_POST['starting_quote_number'] ?? ($empresa['starting_quote_number'] ?? 1),
                    $_POST['quote_prefix'] ?? ($empresa['quote_prefix'] ?? ''),
                    $_POST['quote_suffix'] ?? ($empresa['quote_suffix'] ?? ''),
                    $_POST['starting_invoice_number'] ?? ($empresa['starting_invoice_number'] ?? 1),
                    $_POST['invoice_prefix'] ?? ($empresa['invoice_prefix'] ?? ''),
                    $_POST['invoice_suffix'] ?? ($empresa['invoice_suffix'] ?? ''),
                    $logo_path,
                    $_POST['social_facebook'] ?? ($empresa['social_facebook'] ?? null),
                    $_POST['social_instagram'] ?? ($empresa['social_instagram'] ?? null),
                    $_POST['social_linkedin'] ?? ($empresa['social_linkedin'] ?? null),
                    $_POST['social_twitter'] ?? ($empresa['social_twitter'] ?? null),
                    $_POST['social_website'] ?? ($empresa['social_website'] ?? null),
                    getEmpresaId()
                ]);
                // Invalidar caché de timezone en sesión
                $_SESSION["company_timezone_cache_" . getEmpresaId()] = $_POST['timezone'] ?? 'America/Bogota';
                break;
            case 'crear_impuesto':
                $stmt = $pdo->prepare("INSERT INTO impuestos (empresa_id, nombre_impuesto, porcentaje) VALUES (?, ?, ?)");
                $stmt->execute([getEmpresaId(), $_POST['nombre'], $_POST['porcentaje']]);
                break;
            case 'eliminar_impuesto':
                $stmt = $pdo->prepare("DELETE FROM impuestos WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$_POST['id'], getEmpresaId()]);
                break;
            case 'crear_unidad':
                $stmt = $pdo->prepare("INSERT INTO unidades_medida (empresa_id, nombre_unidad) VALUES (?, ?)");
                $stmt->execute([getEmpresaId(), $_POST['nombre']]);
                break;
            case 'eliminar_unidad':
                $stmt = $pdo->prepare("DELETE FROM unidades_medida WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$_POST['id'], getEmpresaId()]);
                break;
            case 'crear_tarea':
                $stmt = $pdo->prepare("INSERT INTO tareas_empresa (empresa_id, nombre, descripcion) VALUES (?, ?, ?)");
                $stmt->execute([getEmpresaId(), $_POST['nombre'], $_POST['descripcion']]);
                break;
            case 'eliminar_tarea':
                $stmt = $pdo->prepare("DELETE FROM tareas_empresa WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$_POST['id'], getEmpresaId()]);
                break;
        }
        header("Location: configuracion.php?success=1");
        exit;
    }
}

// Re-obtener datos después de una posible actualización
$stmt_emp_refresh = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt_emp_refresh->execute([getEmpresaId()]);
$empresa = $stmt_emp_refresh->fetch();

$stmt_imp_refresh = $pdo->prepare("SELECT * FROM impuestos WHERE empresa_id = ?");
$stmt_imp_refresh->execute([getEmpresaId()]);
$impuestos = $stmt_imp_refresh->fetchAll();

$stmt_uni_refresh = $pdo->prepare("SELECT * FROM unidades_medida WHERE empresa_id = ?");
$stmt_uni_refresh->execute([getEmpresaId()]);
$unidades = $stmt_uni_refresh->fetchAll();

$stmt_tareas_refresh = $pdo->prepare("SELECT * FROM tareas_empresa WHERE empresa_id = ? ORDER BY id ASC");
$stmt_tareas_refresh->execute([getEmpresaId()]);
$tareas_empresa = $stmt_tareas_refresh->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-12">
    <!-- Sección de Cabecera -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Configuración</h1>
            <p class="text-gray-500 mt-1">Personaliza la identidad de tu SaaS y parámetros base.</p>
        </div>
        <?php if(isset($_GET['success'])): ?>
            <div class="bg-green-100 text-green-700 px-6 py-3 rounded-2xl text-sm font-bold animate-bounce">
                ¡Cambios guardados con éxito!
            </div>
        <?php endif; ?>
    </div>

    <!-- Gestión de Empresa -->
    <div class="bg-white p-8 rounded-3xl border border-gray-100 card-shadow">
        <h2 class="text-xl font-bold mb-6 text-gray-800 flex items-center gap-2">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            Perfil de la Empresa
        </h2>
        <form action="configuracion.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <input type="hidden" name="accion" value="actualizar_empresa">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Nombre Comercial</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($empresa['nombre']); ?>" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">NIT / Identificación</label>
                <input type="text" name="nit" value="<?php echo htmlspecialchars($empresa['nit']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Moneda (Símbolo)</label>
                <input type="text" name="moneda" value="<?php echo htmlspecialchars($empresa['moneda']); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Zona Horaria (Timezone)</label>
                <select name="timezone" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    <?php 
                    $tz_list = [
                        'America/Bogota' => 'Bogotá, Lima, Quito',
                        'America/Mexico_City' => 'Ciudad de México',
                        'America/Caracas' => 'Caracas',
                        'America/Santiago' => 'Santiago de Chile',
                        'America/Buenos_Aires' => 'Buenos Aires',
                        'America/La_Paz' => 'La Paz',
                        'America/Asuncion' => 'Asunción',
                        'America/Montevideo' => 'Montevideo',
                        'America/Guayaquil' => 'Guayaquil',
                        'America/Panama' => 'Panamá',
                        'America/Costa_Rica' => 'Costa Rica',
                        'America/El_Salvador' => 'El Salvador',
                        'America/Guatemala' => 'Guatemala',
                        'America/Tegucigalpa' => 'Tegucigalpa',
                        'America/Santo_Domingo' => 'Santo Domingo',
                        'America/Puerto_Rico' => 'Puerto Rico',
                        'America/New_York' => 'New York (EST)',
                        'America/Los_Angeles' => 'Los Angeles (PST)',
                        'Europe/Madrid' => 'Madrid, España'
                    ];
                    $current_tz = $empresa['timezone'] ?? 'America/Bogota';
                    foreach($tz_list as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $current_tz == $val ? 'selected' : ''; ?>><?php echo $label; ?> (<?php echo $val; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-gray-400 mt-1">Define la hora exacta para reportes y visualizaciones.</p>
            </div>
            
            <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Logo de la Empresa</label>
                    <div class="flex items-center gap-4">
                        <?php if($empresa['logo']): ?>
                            <img src="<?php echo getSecureUrl($empresa['logo']); ?>" class="w-16 h-16 object-contain rounded-xl border border-gray-200 p-1 bg-white">
                        <?php endif; ?>
                        <input type="file" name="logo" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Logo predeterminado para todas las cotizaciones.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Color de Acento (Hex)</label>
                    <div class="flex gap-4 items-center">
                        <input type="color" name="color_hex" value="<?php echo $empresa['color_hex'] ?: '#3b82f6'; ?>" class="w-full h-12 rounded-lg cursor-pointer border border-gray-200 p-1">
                    </div>
                </div>
            </div>
            
            <div class="md:col-span-3 border-t border-gray-100 pt-6 mt-4">
                <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                    Redes Sociales (Para Shortcode {REDES_SOCIALES})
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Página Web</label>
                        <input type="url" name="social_website" value="<?php echo htmlspecialchars($empresa['social_website'] ?? ''); ?>" placeholder="https://miempresa.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Facebook</label>
                        <input type="url" name="social_facebook" value="<?php echo htmlspecialchars($empresa['social_facebook'] ?? ''); ?>" placeholder="https://facebook.com/..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Instagram</label>
                        <input type="url" name="social_instagram" value="<?php echo htmlspecialchars($empresa['social_instagram'] ?? ''); ?>" placeholder="https://instagram.com/..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">LinkedIn</label>
                        <input type="url" name="social_linkedin" value="<?php echo htmlspecialchars($empresa['social_linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Twitter / X</label>
                        <input type="url" name="social_twitter" value="<?php echo htmlspecialchars($empresa['social_twitter'] ?? ''); ?>" placeholder="https://x.com/..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                </div>
            </div>
            <!-- Configuración WhatsApp -->
            <div class="md:col-span-3 border-t border-gray-100 pt-6 mt-4">
                <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                    Configuración de WhatsApp
                </h3>
                
                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Proveedor de WhatsApp</label>
                    <select name="wa_provider" id="wa_provider" onchange="toggleWAFields()" class="w-full md:w-1/3 px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <option value="smsenlinea" <?php echo ($empresa['wa_provider'] ?? 'smsenlinea') == 'smsenlinea' ? 'selected' : ''; ?>>smsenlinea.com (Predeterminado)</option>
                        <option value="evolution" <?php echo ($empresa['wa_provider'] ?? '') == 'evolution' ? 'selected' : ''; ?>>Evolution API (Propio)</option>
                    </select>
                </div>

                <!-- Campos smsenlinea -->
                <div id="fields_smsenlinea" class="space-y-6 <?php echo ($empresa['wa_provider'] ?? 'smsenlinea') == 'smsenlinea' ? '' : 'hidden'; ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">API Secret (smsenlinea)</label>
                            <input type="password" name="smsenlinea_secret" value="<?php echo htmlspecialchars($empresa['smsenlinea_secret'] ?? ''); ?>" placeholder="Secret" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Account ID (smsenlinea)</label>
                            <input type="text" name="smsenlinea_wa_account" value="<?php echo htmlspecialchars($empresa['smsenlinea_wa_account'] ?? ''); ?>" placeholder="Account" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        </div>
                    </div>
                    <div class="bg-blue-50/50 p-6 rounded-2xl border border-blue-100/50">
                        <label class="block text-[10px] font-black text-blue-600 uppercase tracking-widest mb-2">Webhook URL (Opcional - Para CRM)</label>
                        <div class="flex gap-2">
                            <input type="text" readonly value="<?php 
                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                $host = $_SERVER['HTTP_HOST'];
                                $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                                echo "$protocol://$host$path/wa_webhook_sms.php?empresa_id=$empresa_id"; 
                            ?>" class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-white text-gray-500 text-xs">
                            <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('Link copiado!');" class="bg-white hover:bg-gray-100 text-gray-700 font-bold px-4 py-2 rounded-xl border border-gray-200 transition-all text-xs">
                                Copiar
                            </button>
                        </div>
                        <p class="text-[9px] text-gray-400 mt-2">Copia este enlace y pégalo en tu panel de smsenlinea.com para recibir mensajes en el CRM.</p>
                    </div>
                </div>

                <!-- Campos Evolution -->
                <div id="fields_evolution" class="grid grid-cols-1 md:grid-cols-3 gap-6 <?php echo ($empresa['wa_provider'] ?? '') == 'evolution' ? '' : 'hidden'; ?>">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Evolution API URL</label>
                        <input type="text" name="evolution_api_url" value="<?php echo htmlspecialchars($empresa['evolution_api_url'] ?? ''); ?>" placeholder="https://api.tu-servidor.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Evolution Global API Key</label>
                        <input type="password" name="evolution_api_key" value="<?php echo htmlspecialchars($empresa['evolution_api_key'] ?? ''); ?>" placeholder="API Key" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Nombre Instancia</label>
                        <div class="flex gap-2">
                            <input type="text" name="evolution_instance_name" value="<?php echo htmlspecialchars($empresa['evolution_instance_name'] ?? ''); ?>" placeholder="Nombre" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                            <input type="hidden" name="evolution_instance_token" value="<?php echo htmlspecialchars($empresa['evolution_instance_token'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Webhook Evolution (Configurar en Panel Evolution)</label>
                        <div class="flex gap-2">
                            <input type="text" readonly value="<?php 
                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                $host = $_SERVER['HTTP_HOST'];
                                $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                                echo "$protocol://$host$path/wa_webhook.php?empresa_id=$empresa_id"; 
                            ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed text-sm">
                            <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('¡Link copiado!');" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 rounded-xl transition-all">
                                Copiar
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1">Habilita eventos: MESSAGES_UPSERT, MESSAGES_UPDATE</p>
                    </div>
                </div>
            </div>

            <!-- Numeración de Cotizaciones -->
            <div class="md:col-span-3 mt-4 border-t border-gray-100 pt-6">
                <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path></svg>
                    Numeración de Cotizaciones
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Siguiente Número (Consecutivo)</label>
                        <input type="number" name="starting_quote_number" value="<?php echo htmlspecialchars($empresa['starting_quote_number'] ?? 1); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <p class="text-[10px] text-gray-400 mt-1">El número que tendrá la próxima cotización creada.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Prefijo (Opcional)</label>
                        <input type="text" name="quote_prefix" value="<?php echo htmlspecialchars($empresa['quote_prefix'] ?? ''); ?>" placeholder="Ej: COT-" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <p class="text-[10px] text-gray-400 mt-1">Texto que aparece antes del número.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Sufijo (Opcional)</label>
                        <input type="text" name="quote_suffix" value="<?php echo htmlspecialchars($empresa['quote_suffix'] ?? ''); ?>" placeholder="Ej: -2026" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <p class="text-[10px] text-gray-400 mt-1">Texto que aparece después del número.</p>
                    </div>
                </div>
            </div>

            <!-- Numeración de Facturas -->
            <div class="md:col-span-3 mt-4 border-t border-gray-100 pt-6">
                <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Numeración de Facturas
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Siguiente Número (Consecutivo)</label>
                        <input type="number" name="starting_invoice_number" value="<?php echo htmlspecialchars($empresa['starting_invoice_number'] ?? 1); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                        <p class="text-[10px] text-gray-400 mt-1">El número de la próxima factura.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Prefijo (Opcional)</label>
                        <input type="text" name="invoice_prefix" value="<?php echo htmlspecialchars($empresa['invoice_prefix'] ?? ''); ?>" placeholder="Ej: FAC-" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Sufijo (Opcional)</label>
                        <input type="text" name="invoice_suffix" value="<?php echo htmlspecialchars($empresa['invoice_suffix'] ?? ''); ?>" placeholder="Ej: -EXP" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                    </div>
                </div>
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 mt-4">
                    <p class="text-xs text-gray-600">
                        <strong>Vista previa Facturación:</strong> 
                        <span class="text-indigo-600 font-bold ml-1">
                            <?php echo ($empresa['invoice_prefix'] ?? 'FAC-') . ($empresa['starting_invoice_number'] ?? 1) . ($empresa['invoice_suffix'] ?? ''); ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="md:col-span-3 flex justify-between items-center">
                <button type="button" onclick="testWhatsApp()" class="text-green-600 hover:text-green-700 font-bold text-sm flex items-center gap-2 px-4 py-2 rounded-xl hover:bg-green-50 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Probar Conexión WhatsApp
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-12 rounded-xl shadow-lg transition-all active:scale-95">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>

    <!-- Script de Pruebas -->
    <script>
    function toggleWAFields() {
        const provider = document.getElementById('wa_provider').value;
        document.getElementById('fields_smsenlinea').classList.toggle('hidden', provider !== 'smsenlinea');
        document.getElementById('fields_evolution').classList.toggle('hidden', provider !== 'evolution');
    }

    async function testWhatsApp() {
        const provider = document.getElementById('wa_provider').value;
        const btn = event.currentTarget;
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Probando...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'test_wa_connection'); // New testing action
        formData.append('provider', provider);
        
        if (provider === 'evolution') {
            formData.append('url', document.getElementsByName('evolution_api_url')[0].value);
            formData.append('key', document.getElementsByName('evolution_api_key')[0].value);
            formData.append('name', document.getElementsByName('evolution_instance_name')[0].value);
        } else {
            formData.append('secret', document.getElementsByName('smsenlinea_secret')[0].value);
            formData.append('account', document.getElementsByName('smsenlinea_wa_account')[0].value);
        }

        try {
            const res = await fetch('api_test_connection.php', { method: 'POST', body: formData });
            const data = await res.json();
            alert(data.message);
        } catch(e) {
            alert("Error de red");
        }
        btn.innerHTML = originalText;
        btn.disabled = false;
    }

    async function testSMTP() {
        const host = document.getElementsByName('smtp_host')[0].value;
        const port = document.getElementsByName('smtp_port')[0].value;
        const user = document.getElementsByName('smtp_user')[0].value;
        const pass = document.getElementsByName('smtp_pass')[0].value;
        const enc = document.getElementsByName('smtp_encryption')[0].value;
        const from = document.getElementsByName('smtp_from_email')[0].value;

        if(!host || !user || !pass) return alert("Completa los campos SMTP primero.");

        const btn = event.currentTarget;
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Enviando...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'test_smtp');
        formData.append('host', host);
        formData.append('port', port);
        formData.append('user', user);
        formData.append('pass', pass);
        formData.append('encryption', enc);
        formData.append('from', from);

        try {
            const res = await fetch('api_test_connection.php', { method: 'POST', body: formData });
            const data = await res.json();
            alert(data.message);
        } catch(e) {
            alert("Error de red");
        }
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
    </script>

    <!-- Configuración SMTP (Email) -->
    <div class="bg-white p-8 rounded-3xl border border-gray-100 card-shadow">
        <h2 class="text-xl font-bold mb-6 text-gray-800 flex items-center gap-2">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            Configuración de Correo (SMTP)
        </h2>
        <form action="configuracion.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <input type="hidden" name="accion" value="actualizar_empresa">
            <!-- Camposs ocultos para persistencia -->
            <input type="hidden" name="nombre" value="<?php echo htmlspecialchars($empresa['nombre']); ?>">
            <input type="hidden" name="nit" value="<?php echo htmlspecialchars($empresa['nit']); ?>">
            <input type="hidden" name="moneda" value="<?php echo htmlspecialchars($empresa['moneda']); ?>">
            <input type="hidden" name="color_hex" value="<?php echo htmlspecialchars($empresa['color_hex']); ?>">
            <input type="hidden" name="wa_provider" value="<?php echo htmlspecialchars($empresa['wa_provider'] ?? 'smsenlinea'); ?>">
            <input type="hidden" name="smsenlinea_secret" value="<?php echo htmlspecialchars($empresa['smsenlinea_secret'] ?? ''); ?>">
            <input type="hidden" name="smsenlinea_wa_account" value="<?php echo htmlspecialchars($empresa['smsenlinea_wa_account'] ?? ''); ?>">
            <input type="hidden" name="evolution_api_url" value="<?php echo htmlspecialchars($empresa['evolution_api_url'] ?? ''); ?>">
            <input type="hidden" name="evolution_api_key" value="<?php echo htmlspecialchars($empresa['evolution_api_key'] ?? ''); ?>">
            <input type="hidden" name="evolution_instance_name" value="<?php echo htmlspecialchars($empresa['evolution_instance_name'] ?? ''); ?>">
            <input type="hidden" name="evolution_instance_token" value="<?php echo htmlspecialchars($empresa['evolution_instance_token'] ?? ''); ?>">
            <input type="hidden" name="timezone" value="<?php echo htmlspecialchars($empresa['timezone'] ?? 'America/Bogota'); ?>">

            <div class="md:col-span-3 mt-8 p-6 bg-gray-50 rounded-2xl border border-gray-100">
                <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-4 border-b border-gray-100 pb-2 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.58 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.58 4 8 4s8-1.79 8-4M4 7c0-2.21 3.58-4 8-4s8 1.79 8 4m0 5c0 2.21-3.58 4-8 4s-8-1.79-8-4"></path></svg>
                    Almacenamiento de Documentos
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 items-end">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Destino de Archivos</label>
                        <select name="storage_type" id="storage_type" onchange="toggleS3Fields()" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                            <option value="local" <?php echo ($empresa['storage_type'] ?? 'local') == 'local' ? 'selected' : ''; ?>>Local (Servidor Propio)</option>
                            <option value="s3" <?php echo ($empresa['storage_type'] ?? '') == 's3' ? 'selected' : ''; ?>>Amazon S3 (Nube)</option>
                        </select>
                    </div>
                    <div>
                        <a href="wizard_s3.php" class="flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition-all transform hover:scale-105">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                            Asistente de Configuración Mágica
                        </a>
                    </div>
                </div>

                <div id="s3_fields" class="<?php echo ($empresa['storage_type'] ?? 'local') == 's3' ? '' : 'hidden'; ?> animate-fade-in space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                S3 Bucket 
                                <span class="text-gray-300 normal-case" title="Nombre del bucket donde se almacenarán los archivos (Ej: mi-empresa-docs)">ℹ️</span>
                            </label>
                            <input type="text" name="s3_bucket" value="<?php echo htmlspecialchars($empresa['s3_bucket'] ?? ''); ?>" placeholder="nombre-bucket" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                            <p class="text-[9px] text-gray-400 mt-1">Nombre del contenedor de almacenamiento en S3</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                S3 Region 
                                <span class="text-gray-300 normal-case" title="Región de AWS donde está tu bucket (Ej: us-east-1, eu-west-1)">ℹ️</span>
                            </label>
                            <input type="text" name="s3_region" value="<?php echo htmlspecialchars($empresa['s3_region'] ?? ''); ?>" placeholder="us-east-1" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                            <p class="text-[9px] text-gray-400 mt-1">Ubicación geográfica del bucket</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                Access Key ID 
                                <span class="text-gray-300 normal-case" title="Credencial pública de tu usuario IAM (empieza con AKIA...)">ℹ️</span>
                            </label>
                            <input type="text" name="s3_access_key" value="<?php echo htmlspecialchars($empresa['s3_access_key'] ?? ''); ?>" placeholder="AKIA..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                            <p class="text-[9px] text-gray-400 mt-1">ID público de tu llave de acceso AWS</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                                Secret Access Key 
                                <span class="text-gray-300 normal-case" title="Credencial privada (no la compartas)">ℹ️</span>
                            </label>
                            <input type="password" name="s3_secret_key" value="<?php echo htmlspecialchars($empresa['s3_secret_key'] ?? ''); ?>" placeholder="••••••••••••" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                            <p class="text-[9px] text-gray-400 mt-1">Llave secreta privada (se guarda cifrada)</p>
                        </div>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div class="flex-1">
                            <p class="text-xs text-blue-700 leading-relaxed">
                                <strong>Nota S3:</strong> Asegúrese de que el bucket tenga configurado el acceso público bloqueado pero permita al usuario de IAM (Access Key) realizar operaciones de <code>PutObject</code>, <code>DeleteObject</code> y <code>GetObject</code>.
                            </p>
                            <button type="button" onclick="testS3()" class="mt-3 text-blue-600 hover:text-blue-800 font-black text-[10px] uppercase tracking-widest flex items-center gap-2 bg-white px-4 py-2 rounded-lg border border-blue-200 shadow-sm transition-all hover:scale-105 active:scale-95">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Probar Conexión S3
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            async function testS3() {
                const key = document.getElementsByName('s3_access_key')[0].value;
                const secret = document.getElementsByName('s3_secret_key')[0].value;
                const region = document.getElementsByName('s3_region')[0].value;
                const bucket = document.getElementsByName('s3_bucket')[0].value;
                
                if(!key || !secret || !region || !bucket) return alert("Completa los campos de S3 primero.");

                const btn = event.currentTarget;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<svg class="animate-spin h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Probando...';
                btn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'test_s3');
                formData.append('key', key);
                formData.append('secret', secret);
                formData.append('region', region);
                formData.append('bucket', bucket);

                try {
                    const res = await fetch('api_test_connection.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    alert(data.message);
                } catch(e) {
                    alert("Error de red");
                }
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
            function toggleS3Fields() {
                const type = document.getElementById('storage_type').value;
                document.getElementById('s3_fields').classList.toggle('hidden', type !== 's3');
            }
            </script>

            <div class="md:col-span-3 mt-4">
                <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-4 border-b border-gray-100 pb-2">Claves de Inteligencia Artificial (IA)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <img src="https://openai.com/favicon.ico" class="w-4 h-4 grayscale opacity-50"> OpenAI API Key
                        </label>
                        <input type="password" name="openai_api_key" value="<?php echo htmlspecialchars($empresa['openai_api_key'] ?? ''); ?>" placeholder="sk-..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <img src="https://www.gstatic.com/lamda/images/favicon_v1_150160d13ff2865fc141.png" class="w-4 h-4 grayscale opacity-50"> Google Gemini Key
                        </label>
                        <input type="password" name="gemini_api_key" value="<?php echo htmlspecialchars($empresa['gemini_api_key'] ?? ''); ?>" placeholder="AIza..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <img src="https://www.anthropic.com/favicon.ico" class="w-4 h-4 grayscale opacity-50"> Anthropic Claude Key
                        </label>
                        <input type="password" name="claude_api_key" value="<?php echo htmlspecialchars($empresa['claude_api_key'] ?? ''); ?>" placeholder="sk-ant-..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Servidor SMTP</label>
                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($empresa['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Puerto SMTP</label>
                <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($empresa['smtp_port'] ?? '587'); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Seguridad</label>
                <select name="smtp_encryption" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                    <option value="none" <?php echo ($empresa['smtp_encryption']??'') == 'none' ? 'selected' : ''; ?>>Ninguna</option>
                    <option value="ssl" <?php echo ($empresa['smtp_encryption']??'') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="tlsv1.2" <?php echo ($empresa['smtp_encryption']??'') == 'tlsv1.2' ? 'selected' : ''; ?>>TLS v1.2</option>
                    <option value="tlsv1.3" <?php echo ($empresa['smtp_encryption']??'') == 'tlsv1.3' ? 'selected' : ''; ?>>TLS v1.3</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Usuario SMTP</label>
                <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($empresa['smtp_user'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Email Remitente (Amazon SES)</label>
                <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($empresa['smtp_from_email'] ?? ''); ?>" placeholder="verificado@empresa.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Contraseña</label>
                <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($empresa['smtp_pass'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>

            <div class="md:col-span-3 flex justify-between items-center">
                <button type="button" onclick="testSMTP()" class="text-indigo-600 hover:text-indigo-700 font-bold text-sm flex items-center gap-2 px-4 py-2 rounded-xl hover:bg-indigo-50 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Probar SMTP
                </button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-12 rounded-xl shadow-lg transition-all active:scale-95">
                    Guardar Configuración de Correo
                </button>
            </div>
        </form>
    </div>

    <!-- Automatización Cron Job -->
    <div class="bg-indigo-900 p-8 rounded-3xl border border-indigo-800 card-shadow text-white relative overflow-hidden">
        <div class="absolute top-0 right-0 p-8 opacity-10">
            <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>
        </div>
        <div class="relative z-10">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2 text-indigo-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Automatización (Cron Job)
            </h2>
            <p class="text-indigo-200 text-sm mb-6 max-w-2xl">
                Para que los recordatorios se envíen automáticamente, configura una tarea programada en un servicio externo (como cron-job.org) o en tu cPanel.
            </p>
            
            <div class="bg-indigo-950/50 p-6 rounded-2xl border border-indigo-700/50 backdrop-blur-sm">
                <label class="block text-xs font-bold text-indigo-300 uppercase tracking-widest mb-2">Enlace de Ejecución Externa</label>
                <div class="flex gap-2">
                    <input type="text" readonly value="<?php 
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];
                        $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                        echo "$protocol://$host$path/cron_recordatorios.php?token=cron_secure_123"; 
                    ?>" class="w-full px-4 py-3 rounded-xl border border-indigo-700 bg-indigo-900/50 text-indigo-100 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('¡Enlace copiado!');" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-6 rounded-xl transition-all shadow-lg shadow-indigo-900/50">
                        Copiar
                    </button>
                    <button type="button" onclick="window.open(this.parentElement.querySelector('input').value, '_blank')" class="bg-indigo-800 hover:bg-indigo-700 text-indigo-200 font-bold px-4 rounded-xl transition-all">
                        Probar
                    </button>
                </div>
                <div class="mt-4 flex gap-6 text-xs text-indigo-300">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Frecuencia recomendada: <strong>Cada minuto</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        
        <!-- Gestión de Impuestos -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-gray-100 card-shadow">
                <h2 class="text-xl font-semibold mb-4 text-blue-600 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    Impuestos
                </h2>
                <form action="configuracion.php" method="POST" class="space-y-4 mb-8">
                    <input type="hidden" name="accion" value="crear_impuesto">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-1">
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Nombre</label>
                            <input type="text" name="nombre" placeholder="Ej: IVA" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Porcentaje (%)</label>
                            <input type="number" step="0.01" name="porcentaje" placeholder="19.00" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl shadow-md transition-all active:scale-95">
                        Agregar Impuesto
                    </button>
                </form>

                <div class="space-y-3">
                    <?php foreach ($impuestos as $imp): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100 hover:border-blue-200 transition-colors">
                            <div>
                                <h3 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($imp['nombre_impuesto']); ?></h3>
                                <p class="text-xs text-gray-500"><?php echo $imp['porcentaje']; ?>%</p>
                            </div>
                            <form action="configuracion.php" method="POST" onsubmit="return confirm('¿Eliminar este impuesto?')">
                                <input type="hidden" name="accion" value="eliminar_impuesto">
                                <input type="hidden" name="id" value="<?php echo $imp['id']; ?>">
                                <button type="submit" class="text-red-400 hover:text-red-600 p-2 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Gestión de Unidades de Medida -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-gray-100 card-shadow">
                <h2 class="text-xl font-semibold mb-4 text-indigo-600 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                    Unidades de Medida
                </h2>
                <form action="configuracion.php" method="POST" class="space-y-4 mb-8">
                    <input type="hidden" name="accion" value="crear_unidad">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Nombre de la Unidad</label>
                        <input type="text" name="nombre" placeholder="Ej: Unidad, Metro, Kilo" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition-all">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-xl shadow-md transition-all active:scale-95">
                        Agregar Unidad
                    </button>
                </form>

                <div class="space-y-3">
                    <?php foreach ($unidades as $un): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100 hover:border-indigo-200 transition-colors">
                            <h3 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($un['nombre_unidad']); ?></h3>
                            <form action="configuracion.php" method="POST" onsubmit="return confirm('¿Eliminar esta unidad?')">
                                <input type="hidden" name="accion" value="eliminar_unidad">
                                <input type="hidden" name="id" value="<?php echo $un['id']; ?>">
                                <button type="submit" class="text-red-400 hover:text-red-600 p-2 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Gestión de Tareas Post-Firma -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-gray-100 card-shadow">
                <h2 class="text-xl font-semibold mb-4 text-green-600 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    Tareas Post-Firma
                </h2>
                <form action="configuracion.php" method="POST" class="space-y-4 mb-8">
                    <input type="hidden" name="accion" value="crear_tarea">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Nombre Tarea</label>
                        <input type="text" name="nombre" placeholder="Ej: Solicitar RUT" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition-all">
                    </div>
                     <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Descripción (Opcional)</label>
                        <input type="text" name="descripcion" placeholder="Ej: Para facturación electrónica" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition-all">
                    </div>
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-xl shadow-md transition-all active:scale-95">
                        Agregar Tarea
                    </button>
                </form>

                <div class="space-y-3">
                    <?php foreach ($tareas_empresa as $tarea): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100 hover:border-green-200 transition-colors">
                            <div>
                                <h3 class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($tarea['nombre']); ?></h3>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($tarea['descripcion']); ?></p>
                            </div>
                            <form action="configuracion.php" method="POST" onsubmit="return confirm('¿Eliminar esta tarea?')">
                                <input type="hidden" name="accion" value="eliminar_tarea">
                                <input type="hidden" name="id" value="<?php echo $tarea['id']; ?>">
                                <button type="submit" class="text-red-400 hover:text-red-600 p-2 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
