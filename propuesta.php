<?php
require_once 'db.php';

// DEBUG LOGGING
function debug_log($msg) {
    file_put_contents('debug_propuesta.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}
debug_log("Iniciando propuesta.php");

$hash = $_GET['h'] ?? '';
$stmt = $pdo->prepare("
    SELECT c.*, cl.nombre as cliente_nombre, cl.email as cliente_email, cl.telefono as cliente_tel, 
           cl.nombre_contacto, cl.cargo_contacto, cl.token_acceso, cl.identificacion as cliente_nit,
           cl.direccion as cliente_direccion, cl.celular_contacto as cliente_celular, cl.pais_codigo,
           e.nombre as empresa_nombre, e.nit as empresa_nit, e.moneda as empresa_moneda, e.color_hex, e.logo as empresa_logo, e.timezone as empresa_tz,
           e.*,
           u.telefono as creador_tel,
           p.nombre as plantilla_nombre, p.contenido_html as plantilla_html, p.logo_url as plantilla_logo
    FROM cotizaciones c
    JOIN clientes cl ON c.cliente_id = cl.id
    JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    LEFT JOIN plantillas p ON c.plantilla_id = p.id
    WHERE c.hash_publico = ?
");
$stmt->execute([$hash]);
$cot = $stmt->fetch();

if (!$cot) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h1>Propuesta no encontrada</h1><p>El enlace puede haber expirado o ser incorrecto.</p></div>");
}

// Aplicar zona horaria de la empresa
setCompanyTimezone($cot['empresa_tz'] ?? 'America/Bogota');

// Registro de Lectura (Visto 👀) - Saltamos si es vista previa
$is_preview = isset($_GET['preview']) && $_GET['preview'] == 1;

if (!$is_preview && ($cot['estado'] ?? '') !== 'Borrador') {
    try {
        // Skip tracking if the user is logged in and belongs to the same company
        $is_internal = false;
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (isset($_SESSION['empresa_id']) && $_SESSION['empresa_id'] == $cot['empresa_id']) {
            $is_internal = true;
        }

        if (!$is_internal) {
            // Auto-migrate if table doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS cotizacion_vistas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cotizacion_id INT NOT NULL,
                fecha_vista TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                user_agent TEXT,
                INDEX (cotizacion_id)
            ) ENGINE=InnoDB");

            // Insert view record
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $stmtView = $pdo->prepare("INSERT INTO cotizacion_vistas (cotizacion_id, ip_address, user_agent) VALUES (?, ?, ?)");
            $stmtView->execute([$cot['id'], $ip, $ua]);

            // Update main counters
            if ($cot['vistas_count'] == 0) {
                $stmtUpd = $pdo->prepare("UPDATE cotizaciones SET leida = 1, visto_primera_vez_el = NOW(), visto_ultima_vez_el = NOW(), vistas_count = 1 WHERE id = ?");
                $stmtUpd->execute([$cot['id']]);
            } else {
                $stmtUpd = $pdo->prepare("UPDATE cotizaciones SET visto_ultima_vez_el = NOW(), vistas_count = vistas_count + 1 WHERE id = ?");
                $stmtUpd->execute([$cot['id']]);
            }

            // --- NUEVO: RASTREO DE ENVÍO ESPECÍFICO (SID) ---
            $sid = $_GET['sid'] ?? null;
            if ($sid) {
                $stmtSid = $pdo->prepare("UPDATE cotizacion_envios SET visto = 1, fecha_visto = NOW() WHERE uid = ? AND cotizacion_id = ? AND visto = 0");
                $stmtSid->execute([$sid, $cot['id']]);
            }
            // ------------------------------------------------

            // --- NOTIFICACIÓN WHATSAPP AL CREADOR ---
            // --- NOTIFICACIÓN WHATSAPP AL CREADOR ---
            // Se fuerza el envío siempre si hay teléfono, ignorando la configuración individual (req: "Cada vez que ingrese")
            if (!empty($cot['creador_tel'])) {
                try {
                    require_once 'includes/whatsapp_helper.php';
                    
                    $num = $cot['numero_cotizacion'] ?: "#" . $cot['id'];
                    $cliente = $cot['cliente_nombre'] ?: "un cliente";
                    $link_admin = getBaseUrl() . "nueva-cotizacion.php?id=" . $cot['id'] . "&step=4";
                    
                    $msg = "👀 *Cotización Vista*\n\n";
                    $msg .= "Tu cotización *{$num}* para *{$cliente}* acaba de ser abierta.\n\n";
                    $msg .= "Ver detalles: {$link_admin}";
                    
                    enviarWhatsApp($pdo, $cot['empresa_id'], $cot['creador_tel'], $msg, null, 'image', '57');
                    
                } catch (Exception $ext) {
                    error_log("Error sending view notification: " . $ext->getMessage());
                }
            }
            // ----------------------------------------
            // ----------------------------------------
        } else {
             file_put_contents('debug_wa.log', date('Y-m-d H:i:s') . " - Skip Internal View\n", FILE_APPEND);
        }
    } catch (Exception $e) {
        error_log("Error recording view: " . $e->getMessage());
    }
}

// Verificar Vencimiento
$hoy = date('Y-m-d');
$fecha_venc = $cot['fecha_vencimiento'];
$vencida = ($fecha_venc < $hoy);

// Obtener Historial de Notas (Para impresión y panel)
$stmtH = $pdo->prepare("SELECT * FROM cotizacion_historial WHERE cotizacion_id = ? AND tipo = 'Publico' ORDER BY created_at DESC");
$stmtH->execute([$cot['id']]);
$hist = $stmtH->fetchAll();

// Detalles para la tabla
$stmtDet = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ?");
$stmtDet->execute([$cot['id']]);
$detalles = $stmtDet->fetchAll();
debug_log("Detalles encontrados: " . count($detalles));

// Configuración de Aceptación
$config_acep = json_decode($cot['config_aceptacion'] ?? '{}', true) ?: ['requeridos' => []];
$tareas_cli = json_decode($cot['tareas_cliente'] ?? '[]', true) ?: [];

// Merge legacy requirements with new tasks
if (!isset($config_acep['requeridos'])) $config_acep['requeridos'] = [];
$config_acep['requeridos'] = array_unique(array_merge($config_acep['requeridos'], $tareas_cli));

$pasos_siguientes = json_decode($cot['config_pasos'] ?? '[]', true) ?: [];

// Obtener Clientes de Confianza
$stmtTrusted = $pdo->prepare("SELECT * FROM trusted_clients WHERE empresa_id = ? ORDER BY orden ASC");
$stmtTrusted->execute([$cot['empresa_id']]);
$trusted_clients = $stmtTrusted->fetchAll();

// Obtener Formulario Vinculado
$form_vinculado = null;
if (!empty($cot['formulario_id'])) {
    $stmtF = $pdo->prepare("SELECT * FROM formularios WHERE id = ?");
    $stmtF->execute([$cot['formulario_id']]);
    $form_vinculado = $stmtF->fetch();
}
$form_config = json_decode($cot['formulario_config'] ?? '{}', true);

// --- LOGIC FIX: FORCE MODE IF FORM EXISTS ---
// Requests: "solo vamos a trabajar con el formulario como aceptacion"
$modo_actual = $form_config['modo'] ?? 'ninguno';

if ($form_vinculado) {
    // If a form is linked, we FORCE 'reemplazar_firma' mode
    // This overrides any previous specific setting to ensure the form is the PRIMARY acceptance method
    $modo_actual = 'reemplazar_firma';
    
    // Also update config array just in case it's used elsewhere
    $form_config['modo'] = 'reemplazar_firma';
}
// --------------------------------------------

// Obtener Adjuntos de la Cotización
$stmtAdj = $pdo->prepare("
    SELECT d.* 
    FROM cotizacion_adjuntos ca 
    JOIN documentos d ON ca.documento_id = d.id 
    WHERE ca.cotizacion_id = ?
");
$stmtAdj->execute([$cot['id']]);
$adjuntos = $stmtAdj->fetchAll();

// Generar Tabla de Precios HTML (Diseño Espectacular)
$mostrar_como = $cot['mostrar_cantidad_como'] ?? 'unidad';
$label_unidad = 'Unidad';
if ($mostrar_como === 'horas') $label_unidad = 'Horas';
if ($mostrar_como === 'cantidad_horas') $label_unidad = 'Cant/Horas';
if ($mostrar_como === 'personalizado') {
    if (!empty($detalles)) {
        $label_unidad = !empty($detalles[0]['unidad_nombre']) ? $detalles[0]['unidad_nombre'] : 'Unidad';
    }
}

// Generar Tabla de Precios HTML (Multi-Sección)
$html_tablas = '';

// Agrupar items por sección
$sections = [];
foreach ($detalles as $d) {
    $secName = $d['seccion'] ?? 'General';
    if (!isset($sections[$secName])) $sections[$secName] = [];
    $sections[$secName][] = $d;
}

$sumAllItemsTotal = 0;
$multiSectionMode = count($sections) > 1;

foreach ($sections as $secName => $items) {
    // Wrapper for each section table
    $html_tablas .= '<div class="mb-12 break-inside-avoid section-container">';
    
    // Header
    if ($secName !== 'General' || $multiSectionMode) {
        $html_tablas .= '<h3 class="text-xl font-black text-blue-600 mb-6 uppercase tracking-widest border-b-2 border-blue-100 pb-2 flex items-center gap-3">
            <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">
               <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
            </span>
            ' . htmlspecialchars($secName) . '
        </h3>';
    } 

    $html_tablas .= '<div class="overflow-x-auto custom-scroll mb-2"><table class="w-full text-left premium-table">
    <thead>
        <tr class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100">
            <th class="pb-4 pl-4 font-black w-24">Imagen</th>
            <th class="pb-4 font-black w-1/3">Descripción</th>
            <th class="pb-4 text-center font-black">Precio Unit.</th>
            <th class="pb-4 text-center font-black">Cant.</th>
            <th class="pb-4 text-center font-black hidden md:table-cell">Unidad</th>
            <th class="pb-4 text-right font-black pr-4">Total</th>
        </tr>
    </thead>
    <tbody class="text-sm font-medium text-slate-600">';
    
    $secSubtotal = 0;
    $secImpuestos = 0;

    foreach ($items as $d) {
        $secSubtotal += $d['subtotal']; 
        $taxVal = $d['subtotal'] * ($d['impuesto_porcentaje'] / 100);
        $secImpuestos += $taxVal;
        
        // Sum Global for Checking later
        $sumAllItemsTotal += ($d['subtotal'] + $taxVal);

        $imgHtml = '';
        if(!empty($d['imagen'])) {
             $imgHtml = '<img src="' . getSecureUrl($d['imagen'], $hash) . '" class="w-12 h-12 object-cover rounded-lg border border-slate-100 shadow-sm">';
        } else {
             $imgHtml = '<div class="w-12 h-12 bg-slate-50 rounded-lg flex items-center justify-center text-slate-300"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>';
        }

        $html_tablas .= '<tr class="border-b border-slate-50 last:border-0 group hover:bg-slate-50/50 transition-colors">
            <td class="py-6 pl-4 align-top">' . $imgHtml . '</td>
            <td class="py-6 align-top">
                <div class="space-y-1">
                    <div class="font-black text-slate-800 text-base">' . htmlspecialchars($d['nombre_producto']) . '</div>
                    ' . (!empty($d['descripcion']) ? '<div class="text-xs text-slate-500 font-medium leading-relaxed max-w-lg">' . nl2br(htmlspecialchars($d['descripcion'])) . '</div>' : '') . '
                </div>
            </td>
            <td class="py-6 text-center align-top"><span class="font-bold text-slate-800">' . $cot['empresa_moneda'] . ' ' . number_format($d['precio_unitario'], 2) . '</span></td>
            <td class="py-6 text-center align-top"><span class="bg-slate-50 text-slate-700 px-3 py-1 rounded-lg font-bold text-xs border border-slate-200">' . (float)$d['cantidad'] . '</span></td>
            <td class="py-6 text-center text-[10px] uppercase text-slate-400 tracking-wider hidden md:table-cell align-top">' . ($d['unidad_nombre'] ?? 'UND') . '</td>
            <td class="py-6 text-right pr-4 align-top">
                <div class="font-bold text-slate-900 text-lg font-mono tracking-tight">' . $cot['empresa_moneda'] . ' ' . number_format($d['subtotal'] + $taxVal, 2) . '</div>
                ' . ($d['impuesto_porcentaje'] > 0 ? '<div class="text-[9px] text-slate-400 font-bold">+ ' . (float)$d['impuesto_porcentaje'] . '% IVA</div>' : '') . '
            </td>
        </tr>';
    }
    
    // Totales de Sección (Calculados)
    $secTotal = $secSubtotal + $secImpuestos;
    
    $html_tablas .= '</tbody></table></div>';
    
    // Footer de Sección - Detallado
    $html_tablas .= '<div class="bg-slate-50/50 rounded-2xl p-6 flex flex-col items-end gap-2 mb-4 border border-slate-100">
        <div class="flex gap-12 text-right text-slate-500 text-xs font-bold uppercase tracking-widest">
            <div>Subtotal: ' . $cot['empresa_moneda'] . ' ' . number_format($secSubtotal, 2) . '</div>
            <div>Impuestos: ' . $cot['empresa_moneda'] . ' ' . number_format($secImpuestos, 2) . '</div>
        </div>
        <div class="flex items-center gap-4 border-t border-slate-200 pt-2 mt-1">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Opción</div>
            <div class="text-2xl font-black text-blue-600">' . $cot['empresa_moneda'] . ' ' . number_format($secTotal, 2) . '</div>
        </div>
    </div>';

    $html_tablas .= '</div>';
}

$tabla_html = $html_tablas;
debug_log("Tabla HTML generada. Longitud: " . strlen($tabla_html));
if (strlen($tabla_html) < 100) debug_log("ALERTA: Tabla sospechosamente vacía o corta.");

// Logic to determine if "Sum All" was active
// If there are multiple sections, we ALWAYS hide the global totals as per business rule (sections = independent options)
$hideGlobalTotals = false;
// Note: We no longer hide global totals by default in multiSectionMode 
// as many users use sections for phases rather than exclusive options.

// Renderizar Plantilla o Vista Estándar
$contenido_final = "";
$html_base = !empty($cot['contenido_html']) ? $cot['contenido_html'] : ($cot['plantilla_html'] ?? '');

if (!empty($html_base)) {
    debug_log("Usando HTML Base (Plantilla/Personalizado). Longitud: " . strlen($html_base));
    $contenido_final = $html_base;
    // Obtener Logo si existe (Prioridad: Plantilla > Empresa)
    $logo_src = !empty($cot['plantilla_logo']) ? $cot['plantilla_logo'] : (!empty($cot['empresa_logo']) ? $cot['empresa_logo'] : '');
    
    if ($logo_src) {
        $logo_html = '<img src="' . getSecureUrl($logo_src, $hash) . '" style="max-height: 60px; width: auto;">';
    } else {
        $logo_html = '<div style="font-weight: 900; font-size: 24px; color: #1e293b;">' . htmlspecialchars($cot['empresa_nombre']) . '</div>';
    }

    $contenido_final = str_replace('{LOGO}', $logo_html, $contenido_final);
    $contenido_final = str_replace('{CLIENTE_NOMBRE}', htmlspecialchars($cot['cliente_nombre']), $contenido_final);
    $contenido_final = str_replace('{EMPRESA}', htmlspecialchars($cot['empresa_nombre']), $contenido_final);
    $contenido_final = str_replace('{EMPRESA_NOMBRE}', htmlspecialchars($cot['empresa_nombre']), $contenido_final);
    $contenido_final = str_replace('{EMPRESA_NIT}', htmlspecialchars($cot['empresa_nit']), $contenido_final);
    $contenido_final = str_replace('{NUMERO_COT}', htmlspecialchars($cot['numero_cotizacion'] ?: $cot['id']), $contenido_final);
    
    // Titulo Proyecto
    $titulo_cot = $cot['titulo_cotizacion'] ?: ($cot['titulo'] ?: 'Cotización');
    $contenido_final = str_replace('{TITULO_COTIZACION}', htmlspecialchars($titulo_cot), $contenido_final);
    $contenido_final = str_replace('{TITULO_PROYECTO}', htmlspecialchars($titulo_cot), $contenido_final);
    $contenido_final = str_replace('{TITULO}', htmlspecialchars($titulo_cot), $contenido_final);
    $contenido_final = str_replace('{NUMERO}', htmlspecialchars($cot['numero_cotizacion'] ?: $cot['id']), $contenido_final);
    
    // Shortcodes de Fechas
    // Traducción rápida de meses
    $meses = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
    
    $fecha_raw = date('d F, Y', strtotime($cot['fecha']));
    $venc_raw = date('d F, Y', strtotime($cot['fecha_vencimiento']));
    
    $fecha_fmt = strtr($fecha_raw, $meses);
    $venc_fmt = strtr($venc_raw, $meses);

    $contenido_final = str_replace('{FECHA}', $fecha_fmt, $contenido_final);
    $contenido_final = str_replace('{FECHA_VENCIMIENTO}', $venc_fmt, $contenido_final);
    $contenido_final = str_replace('{NOTAS}', nl2br(htmlspecialchars($cot['notas'] ?? '')), $contenido_final);
    
    // Totales desglosados (Globales)
    // If Hidden, we replace shortcodes with empty strings or hidden divs
    if ($hideGlobalTotals) {
         $contenido_final = str_replace('{SUBTOTAL}', '', $contenido_final);
         $contenido_final = str_replace('{IMPUESTOS}', '', $contenido_final);
         $contenido_final = str_replace('{IMPUESTO_VALOR}', '', $contenido_final);
         $contenido_final = str_replace('{TOTAL}', '', $contenido_final);
         
         // Also try to hide container if user wrapped shortcodes in standard "Total Block" HTML
         // Often templates have specific markup for totals. 
         // We can't easily parse HTML here securely to remove the parent.
         // BUT, we can inject a CSS style to hide .global-total-section if we had that class.
         // Since we don't control the template HTML fully, we rely on empty string replacement.
         
         // User-Specific: If the template uses a specific block for totals, we might leave empty gaps.
         // Let's replace with a comment.
    } else {
        $subtotal_fmt = $cot['empresa_moneda'] . ' ' . number_format($cot['subtotal'], 2);
        $impuestos_fmt = $cot['empresa_moneda'] . ' ' . number_format($cot['impuesto_total'] ?? ($cot['impuestos'] ?? 0), 2); 
        $total_fmt = $cot['empresa_moneda'] . ' ' . number_format($cot['total'], 2);

        $contenido_final = str_replace('{SUBTOTAL}', $subtotal_fmt, $contenido_final);
        $contenido_final = str_replace('{SUB}', $subtotal_fmt, $contenido_final);
        $contenido_final = str_replace('{IMPUESTOS}', $impuestos_fmt, $contenido_final);
        $contenido_final = str_replace('{IMPUESTO_VALOR}', $impuestos_fmt, $contenido_final);
        $contenido_final = str_replace('{IVA}', $impuestos_fmt, $contenido_final);
        $contenido_final = str_replace('{TOTAL}', $total_fmt, $contenido_final);
    }
    
    // Shortcode Cliente Mejorado
    $cliente_info = '<div style="line-height: 1.6; color: #334155; font-size: 0.95em; font-family: sans-serif;">';
    $cliente_info .= '<div style="font-weight: 900; font-size: 1.25em; color: #0f172a; margin-bottom: 6px;">' . htmlspecialchars($cot['cliente_nombre']) . '</div>';
    
    if(!empty($cot['cliente_nit'])) {
        $cliente_info .= '<div style="margin-bottom: 2px;"><strong style="color: #64748b; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em; width: 80px; display:inline-block;">NIT:</strong> ' . htmlspecialchars($cot['cliente_nit']) . '</div>';
    }
    
    if(!empty($cot['nombre_contacto'])) {
        $cliente_info .= '<div style="margin-bottom: 2px;"><strong style="color: #64748b; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em; width: 80px; display:inline-block;">Atención:</strong> ' . htmlspecialchars($cot['nombre_contacto']) . 
                         (!empty($cot['cargo_contacto']) ? ' <span style="color:#94a3b8; font-size:0.9em; font-style:italic;">— ' . htmlspecialchars($cot['cargo_contacto']) . '</span>' : '') . '</div>';
    }
    
    if(!empty($cot['cliente_celular'])) {
         $cliente_info .= '<div style="margin-bottom: 2px;"><strong style="color: #64748b; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em; width: 80px; display:inline-block;">Móvil:</strong> ' . htmlspecialchars($cot['cliente_celular']) . '</div>';
    }

    if(!empty($cot['cliente_email'])) {
        $cliente_info .= '<div style="margin-bottom: 2px;"><strong style="color: #64748b; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em; width: 80px; display:inline-block;">Email:</strong> <a href="mailto:'.htmlspecialchars($cot['cliente_email']).'" style="color: #2563eb; text-decoration:none;">' . htmlspecialchars($cot['cliente_email']) . '</a></div>';
    }
    $cliente_info .= '</div>';
    
    $contenido_final = str_replace('{CLIENTE}', $cliente_info, $contenido_final);
    $contenido_final = str_replace('{ID_FISCAL}', htmlspecialchars($cot['cliente_nit'] ?? ''), $contenido_final);
    $contenido_final = str_replace('{NIT_CLIENTE}', htmlspecialchars($cot['cliente_nit'] ?? ''), $contenido_final);
    $contenido_final = str_replace('{DIRECCION}', htmlspecialchars($cot['cliente_direccion'] ?? ''), $contenido_final);
    $contenido_final = str_replace('{TELEFONO}', htmlspecialchars($cot['cliente_tel'] ?? ''), $contenido_final);
    $contenido_final = str_replace('{TEL}', htmlspecialchars($cot['cliente_tel'] ?: ($cot['cliente_celular'] ?: '')), $contenido_final);
    $contenido_final = str_replace('{CELULAR}', htmlspecialchars($cot['cliente_celular'] ?? ''), $contenido_final);
    $contenido_final = str_replace('{EMAIL}', htmlspecialchars($cot['cliente_email'] ?? ''), $contenido_final);
    $contenido_final = str_replace('{CONTACTO_NOMBRE}', htmlspecialchars($cot['nombre_contacto'] ?? ''), $contenido_final);
    $contenido_final = str_replace('{NOMBRE_CONTACTO}', htmlspecialchars($cot['nombre_contacto'] ?? ''), $contenido_final);
    
    // Logos Shortcode
    $logos_html = '';
    if (!empty($trusted_clients)) {
        $logos_html = '<div style="display: flex; gap: 24px; flex-wrap: wrap; opacity: 0.8; align-items: center; margin-top: 10px;">';
        foreach ($trusted_clients as $trusted) {
             $imgUrl = getSecureUrl($trusted['logo_url'], $hash);
             $logos_html .= '<img src="' . $imgUrl . '" alt="' . htmlspecialchars($trusted['nombre']) . '" style="max-height: 48px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.8;">';
        }
        $logos_html .= '</div>';
    }
    $contenido_final = str_replace('{LOGOS_CLIENTES}', $logos_html, $contenido_final);
    $contenido_final = str_replace('{CONFIAN}', $logos_html, $contenido_final);
    
    // Replace Table Shortcodes with New Section-Based Table
    $contenido_final = str_replace('{TABLA_ITEMS}', $tabla_html, $contenido_final);
    $contenido_final = str_replace('{TABLA_PRECIOS}', $tabla_html, $contenido_final);
    $contenido_final = str_replace('{ITEMS}', $tabla_html, $contenido_final);
    $contenido_final = str_replace('{TABLA}', $tabla_html, $contenido_final);

    // Logos Shortcode (Ya procesado o unificado)

    // Shortcode Redes Sociales
    $social_html = '';
    $socials = [
        'facebook' => ['url' => $cot['social_facebook'] ?? '', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/5/51/Facebook_f_logo_%282019%29.svg', 'color' => '#1877F2'],
        'instagram' => ['url' => $cot['social_instagram'] ?? '', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/e/e7/Instagram_logo_2016.svg', 'color' => '#E4405F'],
        'linkedin' => ['url' => $cot['social_linkedin'] ?? '', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/c/ca/LinkedIn_logo_initials.png', 'color' => '#0A66C2'],
        'twitter' => ['url' => $cot['social_twitter'] ?? '', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/6/6f/Logo_of_Twitter.svg', 'color' => '#1DA1F2'],
        'website' => ['url' => $cot['social_website'] ?? '', 'icon' => 'https://cdn-icons-png.flaticon.com/512/1006/1006771.png', 'color' => '#334155']
    ];

    $has_social = false;
    $social_links_html = '<div style="display: flex; justify-content: center; gap: 15px; margin-top: 20px;">';
    foreach($socials as $key => $data) {
        if(!empty($data['url'])) {
            $has_social = true;
            $social_links_html .= '<a href="' . htmlspecialchars($data['url']) . '" target="_blank" style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: transform 0.2s;">
                <img src="' . $data['icon'] . '" alt="' . $key . '" style="width: 20px; height: 20px; object-fit: contain;">
            </a>';
        }
    }
    $social_links_html .= '</div>';

    $contenido_final = str_replace('{REDES_SOCIALES}', $has_social ? $social_links_html : '', $contenido_final);
    $contenido_final = str_replace('{REDES}', $has_social ? $social_links_html : '', $contenido_final);
} else {
    debug_log("Usando Diseño Estándar Hardcoded");
    // Vista Estándar Premium Redesign
    $logo_src = !empty($cot['plantilla_logo']) ? $cot['plantilla_logo'] : (!empty($cot['empresa_logo']) ? $cot['empresa_logo'] : '');
    $logo_html = $logo_src ? '<img src="' . getSecureUrl($logo_src, $hash) . '" class="h-24 md:h-32 w-auto object-contain transition-all hover:scale-105 duration-500">' : '<div class="text-3xl font-black text-slate-900 tracking-tighter uppercase">' . htmlspecialchars($cot['empresa_nombre']) . '</div>';
    $num_cot = htmlspecialchars($cot['numero_cotizacion'] ?: $cot['id']);
    $fecha_fmt = date('d M, Y', strtotime($cot['fecha']));
    $venc_fmt = date('d M, Y', strtotime($cot['fecha_vencimiento']));

    $contenido_final = '
    <div class="space-y-16">
        <!-- Main Header Card -->
        <div class="glass-card p-10 md:p-20 rounded-[4rem] shadow-2xl shadow-blue-900/10 relative overflow-hidden print-no-shadow">
            <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-blue-500/10 rounded-full blur-[120px]"></div>
            <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-[100px]"></div>
            
            <div class="relative z-10">
                <div class="flex flex-col lg:flex-row justify-between items-start gap-16">
                    <div class="flex-1 space-y-12">
                        <div class="inline-block p-4 bg-white/50 backdrop-blur-xl rounded-[2.5rem] shadow-lg border border-white/50">
                            ' . $logo_html . '
                        </div>
                        <div class="space-y-6">
                            <div class="flex items-center gap-3">
                                <span class="px-4 py-1.5 rounded-full bg-blue-600 text-white text-[10px] font-black uppercase tracking-[0.3em]">Propuesta Comercial</span>
                                <span class="text-slate-400 font-bold text-xs">#' . $num_cot . '</span>
                            </div>
                            <h1 class="text-6xl md:text-8xl font-black text-slate-900 tracking-tightest leading-[0.85] print:text-5xl">
                                <span class="text-slate-400">Para:</span><br>
                                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-600">' . htmlspecialchars($cot['cliente_nombre']) . '</span>
                            </h1>
                            ' . (!empty($cot['nombre_contacto']) ? '
                            <div class="mt-2 text-slate-500 font-bold text-lg">
                                Atn: ' . htmlspecialchars($cot['nombre_contacto']) . '
                                ' . (!empty($cot['cargo_contacto']) ? '<span class="text-slate-400 text-sm font-medium uppercase tracking-widest ml-1">• ' . htmlspecialchars($cot['cargo_contacto']) . '</span>' : '') . '
                            </div>' : '') . '
                            <div class="flex flex-wrap gap-8 pt-4">
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Fecha de Emisión</p>
                                    <p class="text-xl font-bold text-slate-900">' . $fecha_fmt . '</p>
                                </div>
                                <div class="h-10 w-px bg-slate-100 hidden md:block"></div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Validez</p>
                                    <p class="text-xl font-bold text-slate-900">' . $venc_fmt . '</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="w-full lg:w-72 space-y-6">
                        <div class="p-8 rounded-[3rem] bg-slate-900 text-white shadow-2xl relative overflow-hidden group">
                           <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/20 rounded-full blur-2xl -mr-16 -mt-16"></div>
                           <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-4">Monto de Inversión</p>
                           <div class="space-y-1">
                               <p class="text-4xl md:text-5xl font-black tracking-tighter leading-none">$' . number_format($cot['total'], 2) . '</p>
                               <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">' . $cot['empresa_moneda'] . '</p>
                           </div>
                        </div>
                        <div class="p-8 rounded-[3rem] bg-blue-50 border border-blue-100/50">
                            <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-2 font-bold">Estado</p>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                                <p class="text-lg font-black text-blue-900 uppercase">' . $cot['estado'] . '</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details & Pricing -->
        <div class="relative z-10 px-4 md:px-0">
             <div class="flex items-center gap-4 mb-10">
                <h2 class="text-sm font-black text-slate-400 uppercase tracking-[0.3em]">Detalle de Servicios</h2>
                <div class="flex-1 h-px bg-slate-100"></div>
             </div>
             ' . $tabla_html . '
        </div>

        <!-- Trusted Clients Section -->
        ' . (!empty($trusted_clients) ? '
        <div id="trusted-clients" class="relative z-10 py-12 border-y border-slate-50">
            <p class="text-center text-[10px] font-black text-slate-300 uppercase tracking-[0.4em] mb-12">Empresas que confían en nosotros</p>
            <div class="flex flex-wrap justify-center items-center gap-12 md:gap-20 opacity-50 grayscale hover:grayscale-0 transition-all duration-700" id="trusted-grid">
                ' . implode('', array_map(function($t) {
                    return '<img src="' . htmlspecialchars($t['logo_url']) . '" alt="' . htmlspecialchars($t['nombre']) . '" class="h-10 w-auto object-contain cursor-pointer hover:scale-110 transition-transform">';
                }, $trusted_clients)) . '
            </div>
        </div>' : '') . '

        <!-- Summary & Totals -->
        ' . (!$hideGlobalTotals ? '
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative z-10">
            <div class="md:col-span-2 glass-card p-10 md:p-16 rounded-[4rem] shadow-xl shadow-slate-100 print-no-shadow">
                <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] mb-10 flex items-center gap-3">
                    <span class="w-8 h-px bg-slate-200"></span>
                    Términos y Observaciones Adicionales
                </h3>
                <div class="prose max-w-none text-slate-600 leading-relaxed text-lg italic">
                    ' . (!empty($cot['notas']) ? nl2br(htmlspecialchars($cot['notas'])) : 'No se han especificado términos adicionales para esta propuesta.') . '
                </div>
            </div>
            
            <div class="bg-white p-10 md:p-12 rounded-[4rem] shadow-2xl shadow-blue-900/5 border border-slate-50 flex flex-col justify-center space-y-8 relative overflow-hidden">
                <div class="space-y-4">
                    <div class="flex justify-between items-center text-slate-400 font-bold text-xs uppercase tracking-widest">
                        <span>Subtotal</span>
                        <span>$' . number_format($cot['subtotal'], 2) . '</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-400 font-bold text-xs uppercase tracking-widest">
                        <span>Impuestos</span>
                        <span>$' . number_format($cot['impuesto_total'] ?? ($cot['impuestos'] ?? 0), 2) . '</span>
                    </div>
                    <div class="h-px bg-slate-50 my-6"></div>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-1">Inversión Final</p>
                            <p class="text-4xl md:text-5xl font-black text-slate-900 tracking-tightest leading-none">$' . number_format($cot['total'], 2) . '</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>' : '
        <div class="glass-card p-10 md:p-16 rounded-[4rem] shadow-xl shadow-slate-100 print-no-shadow relative z-10">
            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] mb-10 flex items-center gap-3">
                <span class="w-8 h-px bg-slate-200"></span>
                Términos y Observaciones Adicionales
            </h3>
            <div class="prose max-w-none text-slate-600 leading-relaxed text-lg italic">
                ' . (!empty($cot['notas']) ? nl2br(htmlspecialchars($cot['notas'])) : 'No se han especificado términos adicionales para esta propuesta.') . '
            </div>
        </div>') . '

        <!-- Attachments Section -->
        ' . (!empty($adjuntos) ? '
        <div id="attachments-section" class="glass-card p-12 rounded-[4rem] border-dashed border-2 border-slate-100">
             <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8">Documentos Adjuntos</h3>
             <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6" id="attachments-grid">
                ' . implode('', array_map(function($a) {
                    return '<a href="' . $a['nombre_s3'] . '" target="_blank" class="p-6 bg-slate-50 rounded-3xl border border-slate-100 hover:bg-white hover:shadow-xl transition-all group">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-blue-600 shadow-sm transition-transform group-hover:scale-110">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-xs font-black text-slate-900 truncate">' . htmlspecialchars($a['nombre_original']) . '</p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Descargar</p>
                            </div>
                        </div>
                    </a>';
                }, $adjuntos)) . '
             </div>
        </div>' : '') . '
    </div>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propuesta Comercial - <?php echo htmlspecialchars($cot['empresa_nombre']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@200;400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #0f172a; overflow-x: hidden; }
        
        /* Glassmorphism Classes */
        .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); }
        .glass-dock { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 0.5); }
        
        /* Animations */
        .animate-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        
        .floating-action { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .floating-action:hover { transform: translateY(-3px); filter: brightness(1.1); }
        
        /* Table Styling */
        .premium-table { border-collapse: separate; border-spacing: 0 8px; }
        .premium-table tr { transition: all 0.3s ease; }
        .premium-table tbody tr:hover { transform: scale(1.005); filter: drop-shadow(0 10px 15px rgba(0,0,0,0.02)); }

        /* Mobile Font Sizing Fixes */
        @media (max-width: 768px) {
            .text-5xl, .text-6xl, .text-7xl, .text-8xl, .text-9xl {
                font-size: 2.5rem !important;
                line-height: 1.1 !important;
                word-break: break-word;
            }
            .text-4xl {
                font-size: 2rem !important;
            }
        }

        @media print { 
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .glass-card { 
                background: white !important; 
                border: 1px solid #f1f5f9 !important; 
                box-shadow: none !important; 
                backdrop-filter: none !important; 
                break-inside: avoid;
                border-radius: 2rem !important; /* Slightly smaller for print */
                padding: 2rem !important;
            }
            .print-no-shadow { box-shadow: none !important; border: 1px solid #f1f5f9 !important; }
            @page { size: letter; margin: 1.5cm; }
            .print-only { display: block !important; }
            
            /* Logic for avoiding cuts */
            .section-container, tr, .glass-card, #attachments-section { 
                break-inside: avoid; 
                page-break-inside: avoid; 
            }
            
            h1, h2, h3 { break-after: avoid; }
            
            /* Ensure colors and backgrounds show up */
            * { 
                -webkit-print-color-adjust: exact !important; 
                print-color-adjust: exact !important; 
            }
            
            /* Text sizing for paper */
            .text-6xl, .text-8xl { font-size: 3rem !important; line-height: 1 !important; }
            .text-4xl, .text-5xl { font-size: 2.25rem !important; }
            
            /* Header card specific fix for print */
            .rounded-\[4rem\] { border-radius: 2rem !important; }
        }
        .print-only { display: none; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="p-0 md:p-8">

    <!-- Dock Superior Móvil / Sidebar Escritorio -->
    <div class="no-print fixed bottom-6 left-1/2 -translate-x-1/2 z-[100] glass-dock px-6 py-4 rounded-[2.5rem] shadow-2xl flex items-center gap-6 md:gap-8 max-w-[95vw] md:max-w-fit overflow-x-auto scrollbar-hide border border-white/50">
        
        <a href="mi-area.php?t=<?php echo $cot['token_acceso']; ?>" class="floating-action bg-slate-900 text-white flex items-center gap-2 px-5 py-3 rounded-full shadow-lg shadow-slate-300 group hover:bg-slate-800 transition-all">
            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            <span class="font-black text-[10px] uppercase tracking-widest hidden md:inline">Mi Área</span>
        </a>
        
        <?php if($cot['estado'] != 'Aprobada' && !$vencida): ?>
        <button onclick="mostrarModalAceptacion()" class="floating-action bg-blue-600 text-white flex items-center gap-2 px-6 py-3 rounded-full shadow-lg shadow-blue-200 group">
            <svg class="w-5 h-5 transition-transform group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
            <span class="font-black text-sm uppercase tracking-widest">Aceptar</span>
        </button>
        <?php elseif($vencida && $cot['estado'] != 'Aprobada'): ?>
        <button onclick="abrirModalRecotizar()" class="floating-action bg-amber-500 text-white flex items-center gap-2 px-6 py-3 rounded-full shadow-lg shadow-amber-200 group animate-pulse">
            <svg class="w-5 h-5 transition-transform group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            <span class="font-black text-sm uppercase tracking-widest">Recotizar</span>
        </button>
        <?php endif; ?>

        <div class="h-8 w-px bg-gray-200 mx-1"></div>

        <button onclick="toggleNotes()" class="floating-action text-gray-500 hover:text-blue-600 flex flex-col items-center gap-1 group">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
            <span class="text-[8px] font-black uppercase tracking-widest">Notas</span>
        </button>

        <button onclick="mostrarNegociacion()" class="floating-action text-gray-500 hover:text-indigo-600 flex flex-col items-center gap-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            <span class="text-[8px] font-black uppercase tracking-widest">Negociar</span>
        </button>

        <button onclick="window.print()" class="floating-action text-gray-500 hover:text-gray-900 flex flex-col items-center gap-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            <span class="text-[8px] font-black uppercase tracking-widest">Imprimir</span>
        </button>

        <button onclick="compartirPropuesta()" class="floating-action text-gray-500 hover:text-blue-500 flex flex-col items-center gap-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
            <span class="text-[8px] font-black uppercase tracking-widest">Compartir</span>
        </button>

        <?php if($cot['estado'] != 'Aprobada'): ?>
        <button onclick="rechazarPropuesta()" class="floating-action text-gray-300 hover:text-rose-500 flex flex-col items-center gap-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            <span class="text-[8px] font-black uppercase tracking-widest">Rechazar</span>
        </button>
        <?php endif; ?>
    </div>

    <div class="max-w-5xl mx-auto space-y-12 mb-32 animate-up pb-12">
        
        <!-- Alerta de Vencimiento / Cuenta Regresiva Espectacular -->
        <?php if (!$vencida && ($cot['estado'] == 'Borrador' || $cot['estado'] == 'Enviada')): ?>
        <div class="bg-gradient-to-r from-blue-900 via-gray-900 to-indigo-950 text-white px-8 py-10 rounded-[3rem] flex flex-col md:flex-row items-center justify-between gap-8 shadow-2xl relative overflow-hidden group border border-white/5 no-print">
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-10"></div>
            <div class="flex items-center gap-6 relative z-10">
                <div class="w-16 h-16 bg-blue-600/30 rounded-[2rem] flex items-center justify-center text-blue-400 border border-blue-500/20">
                    <svg class="w-8 h-8 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h3 class="text-2xl font-black tracking-tighter">OFERTA ACTIVA</h3>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Expira pronto • No la dejes pasar</p>
                </div>
            </div>
            <div class="flex gap-4 relative z-10 w-full md:w-auto overflow-x-auto">
                <div class="flex-1 md:flex-none p-4 bg-white/5 rounded-3xl min-w-[75px] border border-white/10 backdrop-blur-md">
                    <span id="days" class="text-3xl font-black block leading-none mb-1">00</span>
                    <span class="text-[9px] uppercase font-black text-blue-500 tracking-widest">Días</span>
                </div>
                <div class="flex-1 md:flex-none p-4 bg-white/5 rounded-3xl min-w-[75px] border border-white/10 backdrop-blur-md">
                    <span id="hours" class="text-3xl font-black block leading-none mb-1">00</span>
                    <span class="text-[9px] uppercase font-black text-blue-500 tracking-widest">Horas</span>
                </div>
                <div class="flex-1 md:flex-none p-4 bg-white/5 rounded-3xl min-w-[75px] border border-white/10 backdrop-blur-md">
                    <span id="mins" class="text-3xl font-black block leading-none mb-1">00</span>
                    <span class="text-[9px] uppercase font-black text-blue-500 tracking-widest">Min</span>
                </div>
            </div>
        </div>
        <?php elseif ($vencida): ?>
        <div class="bg-rose-50 text-rose-900 p-8 rounded-[2.5rem] border border-rose-100 flex items-center gap-6 shadow-xl">
             <div class="w-16 h-16 bg-rose-200/50 rounded-2xl flex items-center justify-center text-rose-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
             </div>
             <div>
                <h3 class="text-xl font-black tracking-tight">VIGENCIA FINALIZADA</h3>
                <p class="text-sm font-medium opacity-70">Esta propuesta venció el <?php echo date('d/m/Y', strtotime($cot['fecha_vencimiento'])); ?>. Contáctenos para actualizarla.</p>
             </div>
        </div>
        <?php endif; ?>

        <!-- Contenido principal -->
        <div id="print-area">
            <?php echo $contenido_final; ?>
        </div>

        <!-- Sección de Notas para Impresión -->
        <div class="print-only mt-8 pb-8 border-t border-slate-200 pt-8">
            <h3 class="text-sm font-black text-slate-400 uppercase tracking-[0.3em] mb-6">Historial de Comunicación</h3>
            <?php if(empty($hist)): ?>
                <p class="text-xs text-slate-500 italic">No hay notas registradas.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach($hist as $it): ?>
                    <div class="border-b border-slate-100 pb-3">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[9px] font-black uppercase tracking-widest <?php echo (strpos($it['mensaje'], 'CLIENTE') !== false) ? 'text-blue-600' : 'text-slate-600'; ?>">
                                <?php echo (strpos($it['mensaje'], 'CLIENTE') !== false) ? 'Cliente' : 'Empresa'; ?>
                            </span>
                            <span class="text-[9px] text-slate-400 font-bold"><?php echo date('d/m/Y H:i', strtotime($it['created_at'])); ?></span>
                        </div>
                        <p class="text-[10px] text-slate-700 leading-relaxed font-medium">
                            <?php echo nl2br(htmlspecialchars(str_replace('COMENTARIO DEL CLIENTE: ', '', $it['mensaje']))); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($cot['estado'] == 'Aprobada'): ?>
        <div class="bg-green-500 text-white p-12 rounded-[3.5rem] shadow-2xl space-y-10 animate-in relative overflow-hidden">
            <div class="absolute -bottom-20 -right-20 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
            <div class="text-center relative z-10">
                <div class="w-24 h-24 bg-white text-green-600 rounded-[2rem] flex items-center justify-center mx-auto mb-8 shadow-2xl">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 class="text-5xl font-black tracking-tighter mb-4 leading-none">¡PROPUESTA <br>ACORDADA!</h2>
                <p class="text-green-50 text-lg font-medium">Gracias por confiar en <strong><?php echo htmlspecialchars($cot['empresa_nombre']); ?></strong>.</p>
                
                <?php if($form_vinculado && ($form_config['modo'] ?? 'ninguno') === 'despues_firma'): ?>
                <div class="mt-12 bg-white/20 p-8 rounded-[3rem] border border-white/30 backdrop-blur-sm animate-bounce-subtle">
                    <h4 class="text-xl font-black mb-4">Un último paso...</h4>
                    <p class="text-sm opacity-90 mb-6">Por favor complete el siguiente formulario para procesar su solicitud:</p>
                    <a href="public_form.php?hash=<?php echo $form_vinculado['hash_publico']; ?>&cotizacion_id=<?php echo $cot['id']; ?>" class="inline-block bg-white text-green-600 px-10 py-5 rounded-3xl font-black text-xl shadow-xl hover:scale-105 transition-all">
                        Completar Formulario &rarr;
                    </a>
                </div>
                <?php endif; ?>

                <?php if(!empty($cot['firma_digital'])): ?>
                <div class="mt-8 inline-block bg-white/20 p-6 rounded-3xl backdrop-blur-md border border-white/20">
                    <p class="text-[10px] font-black text-white/70 uppercase tracking-[0.2em] mb-4">Firmado Digitalmente</p>
                    <img src="<?php echo $cot['firma_digital']; ?>" class="h-24 w-auto object-contain brightness-0 invert opacity-90 mx-auto" alt="Firma">
                </div>
                <?php endif; ?>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 relative z-10">
                <?php foreach ($pasos_siguientes as $paso): ?>
                <div class="bg-white/15 p-5 rounded-3xl flex items-center gap-4 border border-white/20 transition-all hover:bg-white/25">
                    <div class="w-8 h-8 bg-white text-green-600 rounded-xl flex items-center justify-center font-black text-sm">✓</div>
                    <span class="text-sm font-black tracking-tight"><?php echo htmlspecialchars($paso); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Panel de Notas (Slide-Over) -->
    <div id="panelNotas" class="fixed inset-0 z-[200] hidden flex justify-end no-print">
        <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="toggleNotes()"></div>
        <div class="bg-white w-full max-w-md h-full relative z-10 shadow-2xl flex flex-col p-8 animate-in slide-in-from-right duration-500">
            <div class="flex justify-between items-center mb-10">
                <h3 class="text-2xl font-black tracking-tighter">Muro de Actividad</h3>
                <button onclick="toggleNotes()" class="p-2 hover:bg-gray-100 rounded-xl transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto space-y-8 pr-2 custom-scroll">
                <?php if(empty($hist)): ?>
                    <p class="text-center text-gray-400 py-10">No hay comentarios aún.</p>
                <?php else: ?>
                    <?php foreach($hist as $it): ?>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-black text-blue-500 bg-blue-50 px-2 py-1 rounded uppercase tracking-widest"><?php echo (strpos($it['mensaje'], 'CLIENTE') !== false) ? 'Tú' : 'Empresa'; ?></span>
                            <span class="text-[10px] text-gray-400 font-bold"><?php echo date('d M, H:i', strtotime($it['created_at'])); ?></span>
                        </div>
                        <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 p-4 rounded-2xl border border-gray-100">
                            <?php echo nl2br(htmlspecialchars(str_replace('COMENTARIO DEL CLIENTE: ', '', $it['mensaje']))); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-8 pt-8 border-t border-gray-100">
                <h4 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4">¿Deseas dejar un mensaje?</h4>
                <textarea id="cliente-mensaje" rows="4" class="w-full px-5 py-4 rounded-3xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all text-sm mb-4" placeholder="Escribe tu observación o duda aquí..."></textarea>
                <button onclick="enviarMensajeCliente()" id="btn-envio-comentario" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-2xl shadow-xl shadow-blue-500/20 transition-all flex items-center justify-center gap-2">
                    Enviar Comentario
                </button>
            </div>
        </div>
    </div>

    <!-- CDN Signature Pad -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

    <!-- Modal Aceptación Personalizado -->
    <div id="modalAceptacion" class="fixed inset-0 z-[200] hidden bg-gray-900/60 backdrop-blur-xl flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-[3.5rem] w-full max-w-2xl p-8 md:p-12 shadow-2xl my-8 relative">
            <button onclick="cerrarModalAceptacion()" class="absolute top-6 right-6 p-2 bg-gray-100 rounded-full text-gray-400 hover:text-gray-900 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <h3 class="text-4xl font-black text-gray-900 tracking-tighter mb-2">Confirmar Acuerdo</h3>
            <p class="text-gray-500 font-medium mb-8 leading-relaxed">Complete la información para formalizar la propuesta <strong>#<?php echo htmlspecialchars($cot['numero_cotizacion']); ?></strong>.</p>
            
            <form id="formAceptar" class="space-y-8" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="aceptar">
                <input type="hidden" name="id" value="<?php echo $cot['id']; ?>">
                <input type="hidden" name="hash" value="<?php echo $cot['hash_publico']; ?>">
                
                <!-- Sección de Documentos Requeridos Eliminada (UI Cleanup) -->

                <!-- NUEVO: Formulario Vinculado (Integración) -->
                <?php 
                $modo_actual = $form_config['modo'] ?? 'ninguno';
                if($form_vinculado && $modo_actual !== 'ninguno'): 
                ?>
                <div class="bg-blue-50 p-6 rounded-3xl border border-blue-100" id="form-integration-container">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <h4 class="text-xs font-black text-blue-600 uppercase tracking-widest">Información Adicional Requerida</h4>
                    </div>
                    
                    <div class="rounded-2xl overflow-hidden border border-blue-200 bg-white shadow-inner">
                        <iframe src="public_form.php?hash=<?php echo $form_vinculado['hash_publico']; ?>&cotizacion_id=<?php echo $cot['id']; ?>" 
                                class="w-full min-h-[400px]" 
                                id="iframe-form-integration"></iframe>
                    </div>
                    
                    <?php if($modo_actual === 'antes_firma' || $modo_actual === 'reemplazar_firma'): ?>
                    <p class="text-[10px] text-blue-500 font-bold mt-2 text-center" id="form-wait-msg">
                        * Por favor complete el formulario arriba para habilitar la confirmación.
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Sección Logística (Condicional) -->
                <?php if($cot['requiere_recoleccion']): ?>
                <div class="bg-amber-50 p-6 rounded-3xl border border-amber-100">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <h4 class="text-xs font-black text-amber-500 uppercase tracking-widest">Datos para Recolección/Despacho</h4>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                             <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 ml-1">Dirección Exacta</label>
                             <input type="text" name="logistica[direccion]" required placeholder="Calle, Número, Barrio, Ciudad" class="w-full px-4 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-amber-500 outline-none transition-all shadow-sm text-sm">
                        </div>
                        <div>
                             <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 ml-1">Fecha Preferida</label>
                             <input type="date" name="logistica[fecha]" required min="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-amber-500 outline-none transition-all shadow-sm text-sm">
                        </div>
                        <div>
                             <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 ml-1">Horario Contacto</label>
                             <select name="logistica[horario]" class="w-full px-4 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-amber-500 outline-none transition-all shadow-sm text-sm">
                                <option>08:00 AM - 12:00 PM</option>
                                <option>02:00 PM - 05:00 PM</option>
                                <option>Todo el día</option>
                             </select>
                        </div>
                        <div>
                             <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 ml-1">Persona que Entrega</label>
                             <input type="text" name="logistica[contacto_nombre]" required class="w-full px-4 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-amber-500 outline-none transition-all shadow-sm text-sm">
                        </div>
                        <div>
                             <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 ml-1">Celular (+País)</label>
                             <input type="tel" name="logistica[contacto_tel]" required placeholder="+57 300 123 4567" class="w-full px-4 py-3 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-amber-500 outline-none transition-all shadow-sm text-sm">
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Firma Digital (Visible solo si NO hay formulario o NO es reemplazar_firma) -->
                <?php 
                // Logic: 
                // If replacing signature -> Hide Signature Pad (Form handles it)
                // If before/after -> Show Signature Pad depending on step? No, simpler:
                // Show Signature Pad ONLY if mode is NOT 'reemplazar_firma'.
                $show_signature = ($modo_actual !== 'reemplazar_firma');
                ?>
                <div id="signature-section" class="<?php echo $show_signature ? '' : 'hidden'; ?>">
                     <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-3 ml-1">Su Firma Digital</label>
                     <div class="border-2 border-dashed border-gray-300 rounded-3xl bg-gray-50 relative overflow-hidden group hover:border-blue-400 transition-colors">
                        <canvas id="signature-canvas" class="w-full h-40 cursor-crosshair z-10 relative"></canvas>
                        <div class="absolute inset-0 flex items-center justify-center text-gray-300 pointer-events-none group-hover:text-blue-100 transition-colors">
                            <span class="text-xs font-black uppercase tracking-[0.3em]">Firmar Aquí</span>
                        </div>
                        <button type="button" onclick="ordenLimpiarFirma()" class="absolute bottom-2 right-2 text-[9px] bg-white border border-gray-200 px-2 py-1 rounded-lg text-gray-500 hover:text-red-500 z-20">Borrar</button>
                     </div>
                     <input type="hidden" name="firma_base64" id="input-firma">
                </div>
                
                <!-- Botón de Confirmación (SIEMPRE VISIBLE ahora) -->
                <div class="pt-4 space-y-3">
                    <button type="button" onclick="handleAcceptClick()" id="btn-confirmar-final" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-3xl shadow-xl shadow-blue-500/20 transition-all active:scale-95 text-xl tracking-tighter flex justify-center items-center gap-3">
                        <span>CONFIRMAR ACEPTACIÓN</span>
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    </button>
                    <p class="text-center text-[10px] text-gray-400 max-w-sm mx-auto leading-tight">Al hacer clic, acepta los términos y condiciones estipulados en esta cotización.</p>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL FORMULARIO (NUEVO) -->
    <?php if($form_vinculado && $modo_actual === 'reemplazar_firma'): ?>
    <div id="modal-form-acceptance" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" onclick="closeFormModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col animate-up">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <div>
                        <h3 class="text-lg font-black text-gray-900 uppercase tracking-tight">Completar Información</h3>
                        <p class="text-xs text-gray-500 font-bold">Por favor diligencie el formulario para formalizar la aceptación.</p>
                    </div>
                    <button type="button" onclick="closeFormModal()" class="p-2 text-gray-400 hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto bg-gray-50/50 p-4">
                    <iframe src="public_form.php?hash=<?php echo $form_vinculado['hash_publico']; ?>&cotizacion_id=<?php echo $cot['id']; ?>" 
                            class="w-full h-full min-h-[500px] rounded-xl border border-gray-200 shadow-sm bg-white" 
                            id="iframe-modal-form"></iframe>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Initialize Signature Pad
        let signaturePad;

        function initSignature() {
            const canvas = document.getElementById('signature-canvas');
            if(canvas && !signaturePad) {
                // ... (Keep existing resize logic) ...
                function resizeCanvas() {
                    const ratio =  Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                }
                window.addEventListener("resize", resizeCanvas);
                resizeCanvas();
                
                signaturePad = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: 'rgb(30, 58, 138)'
                });
            }
        }

        // Logic to Handle Click
        const modoActual = '<?php echo $modo_actual; ?>';
        
        function handleAcceptClick() {
            if (modoActual === 'reemplazar_firma') {
                // Open Modal
                document.getElementById('modal-form-acceptance').classList.remove('hidden');
            } else {
                // Submit traditional form
                // Validate Signature if visible
                if (!document.getElementById('signature-section').classList.contains('hidden')) {
                     if (signaturePad.isEmpty()) {
                        alert("Por favor firme en el recuadro para continuar.");
                        return;
                    }
                    document.getElementById('input-firma').value = signaturePad.toDataURL();
                }
                
                // Submit
                const form = document.getElementById('formAceptar');
                if(form) {
                    if(form.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
                    }
                } else {
                    console.error("Form formAceptar not found");
                }
            }
        }
           // Auto-open chat if hash is present
        if(window.location.hash === '#chat') {
            setTimeout(toggleNotes, 500); // Small delay to ensure smooth loading
        }

        let formSubmitted = false;
        const modoIntegracion = '<?php echo $modo_actual; ?>';

        // Consolidate Form Listener
        window.addEventListener('message', function(event) {
            // Check for 'action' (standardized) or 'type' (legacy/fallback)
            if (event.data.action === 'form_submitted' || event.data.type === 'form_submitted') {
                formSubmitted = true;
                
                // UI Feedback
                const btn = document.getElementById('btn-confirmar-final');
                const msg = document.getElementById('form-wait-msg');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Procesando...';
                }
                if (msg) msg.innerText = "✅ Formulario completado con éxito.";
                
                // If in replacement mode, we reload to show acceptance
                if (modoIntegracion === 'reemplazar_firma') {
                    // Alert and reload
                    setTimeout(() => {
                        alert("✅ ¡Propuesta aceptada correctamente!");
                        location.reload(); 
                    }, 1000);
                } else {
                    // If regular integration (before/after), just reload to update status/UI
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            }
        });

        function closeFormModal() {
            document.getElementById('modal-form-acceptance').classList.add('hidden');
        }

        function mostrarModalAceptacion() {
            // NEW LOGIC: Direct Redirect instead of Modal
            if (modoIntegracion === 'reemplazar_firma') {
                window.location.href = `public_form.php?hash=<?php echo $form_vinculado['hash_publico']; ?>&cotizacion_id=<?php echo $cot['id']; ?>&return_hash=<?php echo $hash; ?>`;
                return;
            }

            document.getElementById('modalAceptacion').classList.remove('hidden');
            
            // Handle Integration Modes
            if (modoIntegracion === 'antes_firma') {
                if (!formSubmitted) {
                    const btn = document.getElementById('btn-confirmar-final');
                    if (btn) btn.disabled = true;
                }
            }

            setTimeout(initSignature, 100);
        }

        function cerrarModalAceptacion() {
            document.getElementById('modalAceptacion').classList.add('hidden');
        }

        function mostrarNegociacion() {
            const msg = prompt("Escribe tu comentario o propuesta de negociación:");
            if(msg) {
                enviarComentarioAjax(msg, 'negociacion');
            }
        }

        function rechazarPropuesta() {
            if(confirm('¿Seguro que deseas rechazar esta propuesta?')) {
                const razon = prompt("¿Cuál es el motivo del rechazo? (Opcional)");
                procesarAccion('rechazar', razon);
            }
        }

        function compartirPropuesta() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                alert("¡Enlace copiado al portapapeles!");
            });
        }

        function enviarMensajeCliente() {
            const msg = document.getElementById('cliente-mensaje').value;
            if(!msg) return alert("Escribe un mensaje");
            enviarComentarioAjax(msg, 'comentario');
        }

        function enviarComentarioAjax(msg, tipo) {
            const btn = document.getElementById('btn-envio-comentario');
            if(btn) btn.disabled = true;

            const fd = new FormData();
            fd.append('hash', '<?php echo $hash; ?>');
            fd.append('mensaje', msg);

            fetch('api_cliente_nota.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                alert("¡Mensaje enviado correctamente!");
                location.reload();
            });
        }

        document.getElementById('formAceptar').onsubmit = async function confirmarAceptacion(e) {
            e.preventDefault();
            
            // Validación de firma según visibilidad
            const sigVisible = !document.getElementById('signature-section').classList.contains('hidden');
            if (sigVisible && signaturePad.isEmpty()) {
                alert("Por favor, firme el documento digitalmente.");
                return;
            }

            const btn = document.getElementById('btn-confirmar-final');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Procesando...';

            try {
                // Obtener signature como Base64
                const signatureData = (modoIntegracion !== 'reemplazar_firma') ? signaturePad.toDataURL() : '';
                
                // Construir FormData desde el formulario (incluye archivos)
                const form = document.getElementById('formAceptar');
                const fd = new FormData(form);
                
                // Agregar firma manualmente al FormData
                fd.append('firma_base64', signatureData);
                fd.append('hash', '<?php echo $hash; ?>'); // Ensure hash is included
                fd.append('accion', 'aceptar'); // Ensure accion is included

                const res = await fetch('api_aceptar_cotizacion.php', {
                    method: 'POST',
                    body: fd
                }).then(r => r.json());

                if (res.status === 'success') {
                    alert(res.message);
                    location.reload();
                } else {
                    alert('Error: ' + res.message);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión al procesar la aceptación.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        };

        function procesarAccion(acc, info = '') {
            const formData = new FormData();
            formData.append('hash', '<?php echo $hash; ?>');
            formData.append('accion', acc);
            formData.append('motivo', info);

            fetch('api_aceptar_cotizacion.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(() => location.reload());
        }
    </script>
    <!-- Modal Recotizar -->
    <div id="modalRecotizar" class="fixed inset-0 z-[200] hidden bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-[2rem] w-full max-w-md shadow-2xl relative animate-in zoom-in-95 duration-200">
            <div class="p-8 bg-amber-50 border-b border-amber-100 text-center rounded-t-[2rem]">
                <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                     <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </div>
                <h3 class="text-xl font-black text-slate-800 tracking-tight">Solicitar Actualización</h3>
                <p class="text-xs font-bold text-slate-500 mt-2 uppercase tracking-wide">La cotización ha vencido</p>
            </div>
            <div class="p-8 space-y-6">
                <p class="text-sm text-slate-600 leading-relaxed text-center">Envíanos un mensaje para revisar los precios y actualizar la propuesta a las condiciones actuales.</p>
                <div>
                     <textarea id="mensajeRecotizacion" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500 outline-none text-sm font-medium h-32 resize-none" placeholder="Ej: Me interesa proceder, por favor confirmar si los precios se mantienen..."></textarea>
                </div>
                <button onclick="enviarSolicitudRecotizacion()" id="btnEnviarRecotizacion" class="w-full py-4 bg-amber-500 text-white font-black rounded-xl hover:bg-amber-600 transition-all shadow-lg shadow-amber-200">
                    ENVIAR SOLICITUD
                </button>
                <button onclick="cerrarModalRecotizar()" class="w-full py-2 text-slate-400 font-bold hover:text-slate-600 text-xs uppercase tracking-widest">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    <script>
        function abrirModalRecotizar() {
            document.getElementById('modalRecotizar').classList.remove('hidden');
        }
        function cerrarModalRecotizar() {
            document.getElementById('modalRecotizar').classList.add('hidden');
        }

        async function enviarSolicitudRecotizacion() {
            const btn = document.getElementById('btnEnviarRecotizacion');
            const mensaje = document.getElementById('mensajeRecotizacion').value;
            
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-3 inline" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> ENVIANDO...';
            
            try {
                const res = await fetch('api_recotizar.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        cotizacion_id: <?php echo $cot['id']; ?>,
                        hash: '<?php echo $hash; ?>',
                        mensaje: mensaje
                    })
                });
                const data = await res.json();
                
                if (data.status === 'success') {
                    alert('✅ Solicitud enviada correctamente. Te contactaremos pronto.');
                    cerrarModalRecotizar();
                } else {
                    alert('❌ Error: ' + (data.error || 'No se pudo enviar'));
                }
            } catch (e) {
                console.error(e);
                alert('Connection Error');
            } finally {
                btn.disabled = false;
                btn.innerText = 'ENVIAR SOLICITUD';
            }
        }
    </script>
</body>
</html>
