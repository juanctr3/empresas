<?php
/**
 * exportar-cotizacion.php
 * Vista de cotización para administradores (igual a la vista del cliente con botón de impresión)
 * UNIFICADA CON propuesta.php - Versión 2.0
 */
require_once 'db.php';
require_once 'includes/auth_helper.php';

$id = $_GET['id'] ?? 0;
$es_pdf = true; // Flag para identificar que estamos en modo PDF/Admin

// Obtener Datos de la Cotización (Admin auth compatible)
$stmt = $pdo->prepare("
    SELECT c.*, cl.nombre as cliente_nombre, cl.email as cliente_email, cl.telefono as cliente_tel, 
           e.nombre as empresa_nombre, e.nit as empresa_nit, e.moneda as empresa_moneda, e.color_hex, e.logo as empresa_logo, e.timezone as empresa_tz,
           p.nombre as plantilla_nombre, p.contenido_html as plantilla_html, p.logo_url as plantilla_logo
    FROM cotizaciones c
    JOIN clientes cl ON c.cliente_id = cl.id
    JOIN empresas e ON c.empresa_id = e.id
    LEFT JOIN plantillas p ON c.plantilla_id = p.id
    WHERE c.id = ? AND c.empresa_id = ?
");
$stmt->execute([$id, getEmpresaId()]);
$cot = $stmt->fetch();

if (!$cot) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h1>Cotización no encontrada</h1><p>El ID puede ser incorrecto o no tener permisos.</p></div>");
}

// Aplicar zona horaria de la empresa
setCompanyTimezone($cot['empresa_tz'] ?? 'America/Bogota');

// NO registramos vista porque es admin
$is_preview = true; // Para que propuesta.php no registre tracking

// Generar hash simulado para getSecureUrl (funciona interno)
$hash = $cot['hash_publico'] ?? bin2hex(random_bytes(16));

// Obtener Clientes de Confianza
$stmtTrusted = $pdo->prepare("SELECT * FROM trusted_clients WHERE empresa_id = ? ORDER BY orden ASC");
$stmtTrusted->execute([$cot['empresa_id']]);
$trusted_clients = $stmtTrusted->fetchAll();

// Obtener Detalles de la cotización
$stmtDet = $pdo->prepare("
    SELECT cd.*, p.nombre as producto_nombre, cd.imagen as imagen_producto, COALESCE(u.nombre, 'Unidad') as unidad_nombre
    FROM cotizacion_detalles cd
    LEFT JOIN productos p ON cd.producto_id = p.id
    LEFT JOIN unidades_personalizadas u ON cd.unidad_id = u.id
    WHERE cd.cotizacion_id = ?
    ORDER BY cd.orden
");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll();

// Normalizar detalles para usar la misma lógica que propuesta.php
foreach ($detalles as &$det) {
    if (empty($det['nombre_producto'])) {
        $det['nombre_producto'] = $det['producto_nombre'] ?? 'Producto';
    }
}
unset($det);

// Incluir toda la lógica de propuesta.php desde la línea de generación de tabla en adelante
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización #<?php echo htmlspecialchars($cot['numero_cotizacion'] ?: $cot['id']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }
        .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); }
        .gradient-overlay { background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(99, 102, 241, 0.05) 100%); }
        .premium-table tbody tr:hover { background: rgba(241, 245, 249, 0.5); }
        .custom-scroll::-webkit-scrollbar { height: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; margin: 0 !important; padding: 0 !important; }
            .print-no-shadow { box-shadow: none !important; border: 1px solid #f1f5f9 !important; }
            @page { size: letter; margin: 1.5cm; }
            
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
            
            /* Glass card styling for print */
            .glass-card { 
                background: white !important; 
                border: 1px solid #f1f5f9 !important; 
                box-shadow: none !important; 
                backdrop-filter: none !important; 
                break-inside: avoid;
                border-radius: 2rem !important;
                padding: 2rem !important;
            }
            
            /* Text sizing for paper */
            .text-6xl, .text-8xl { font-size: 3rem !important; line-height: 1 !important; }
            .text-4xl, .text-5xl { font-size: 2.25rem !important; }
            
            /* Adjust header card for print */
            .rounded-\[4rem\] { border-radius: 2rem !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-indigo-50/20 min-h-screen py-6 md:py-12">

    <!-- Botones de Acción (Solo visible en pantalla) -->
    <div class="no-print max-w-6xl mx-auto mb-6 px-4 flex flex-wrap gap-4 justify-between items-center">
        <a href="cotizaciones.php" class="text-sm font-bold text-gray-500 hover:text-gray-700 flex items-center gap-2 bg-white px-6 py-3 rounded-2xl border border-gray-100 shadow-sm transition-all hover:shadow-md active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Volver al Panel
        </a>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-2xl shadow-lg transition-all active:scale-95 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Imprimir / Guardar PDF
        </button>
    </div>

<?php
// ========== INICIO: LÓGICA COMPARTIDA CON propuesta.php ==========

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

// Agrupar items por sección
$sections = [];
foreach ($detalles as $d) {
    $secName = $d['seccion'] ?? 'General';
    if (!isset($sections[$secName])) $sections[$secName] = [];
    $sections[$secName][] = $d;
}

$sumAllItemsTotal = 0;
$multiSectionMode = count($sections) > 1;
$html_tablas = '';

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
        
        $sumAllItemsTotal += ($d['subtotal'] + $taxVal);

        $imgHtml = '';
        if(!empty($d['imagen_producto'])) {
             $imgHtml = '<img src="' . htmlspecialchars($d['imagen_producto']) . '" class="w-12 h-12 object-cover rounded-lg border border-slate-100 shadow-sm">';
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
$hideGlobalTotals = false;
// Note: We no longer hide global totals by default in multiSectionMode 
// as many users use sections for phases rather than exclusive options.

// Renderizar Plantilla o Vista Estándar
$contenido_final = "";
$html_base = !empty($cot['contenido_html']) ? $cot['contenido_html'] : ($cot['plantilla_html'] ?? '');

if (!empty($html_base)) {
    // Usar plantilla personalizada
    $contenido_final = $html_base;
    $logo_src = !empty($cot['plantilla_logo']) ? $cot['plantilla_logo'] : (!empty($cot['empresa_logo']) ? $cot['empresa_logo'] : '');
    
    if ($logo_src) {
        $logo_html = '<img src="' . getSecureUrl($logo_src, $hash) . '" style="max-height: 60px; width: auto;">';
    } else {
        $logo_html = '<div style="font-weight: 900; font-size: 24px; color: #1e293b;">' . htmlspecialchars($cot['empresa_nombre']) . '</div>';
    }

    $contenido_final = str_replace('{LOGO}', $logo_html, $contenido_final);
    $contenido_final = str_replace('{EMPRESA}', htmlspecialchars($cot['empresa_nombre']), $contenido_final);
    $contenido_final = str_replace('{NUMERO_COT}', htmlspecialchars($cot['numero_cotizacion'] ?: $cot['id']), $contenido_final);
    
    // Titulo Proyecto
    $titulo_cot = $cot['titulo'] ?? ($cot['titulo_cotizacion'] ?? 'Cotización');
    $contenido_final = str_replace('{TITULO_COTIZACION}', htmlspecialchars($titulo_cot), $contenido_final);
    $contenido_final = str_replace('{TITULO_PROYECTO}', htmlspecialchars($titulo_cot), $contenido_final);

    // Totales desglosados (Globales)
    if ($hideGlobalTotals) {
         $contenido_final = str_replace('{SUBTOTAL}', '', $contenido_final);
         $contenido_final = str_replace('{IMPUESTOS}', '', $contenido_final);
         $contenido_final = str_replace('{IMPUESTO_VALOR}', '', $contenido_final);
         $contenido_final = str_replace('{TOTAL}', '', $contenido_final);
    } else {
        $subtotal_fmt = $cot['empresa_moneda'] . ' ' . number_format($cot['subtotal'] ?? 0, 2);
        $impuestos_fmt = $cot['empresa_moneda'] . ' ' . number_format($cot['impuesto_total'] ?? ($cot['impuestos'] ?? 0), 2); 
        $total_fmt = $cot['empresa_moneda'] . ' ' . number_format($cot['total'] ?? 0, 2);

        $contenido_final = str_replace('{SUBTOTAL}', $subtotal_fmt, $contenido_final);
        $contenido_final = str_replace('{IMPUESTOS}', $impuestos_fmt, $contenido_final);
        $contenido_final = str_replace('{IMPUESTO_VALOR}', $impuestos_fmt, $contenido_final);
        $contenido_final = str_replace('{TOTAL}', $total_fmt, $contenido_final);
    }
    
    // Shortcode Cliente Mejorado
    $cliente_info = '<div style="line-height: 1.6; color: #334155; font-size: 0.95em; font-family: sans-serif;">';
    $cliente_info .= '<div style="font-weight: 900; font-size: 1.25em; color: #0f172a; margin-bottom: 6px;">' . htmlspecialchars($cot['cliente_nombre']) . '</div>';
    
    if(!empty($cot['cliente_nit'])) $cliente_info .= '<div style="margin-bottom: 2px;"><strong style="color: #64748b; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em; width: 80px; display:inline-block;">NIT:</strong> ' . htmlspecialchars($cot['cliente_nit']) . '</div>';
    if(!empty($cot['cliente_tel'])) $cliente_info .= '<div style="margin-bottom: 2px;"><strong style="color: #64748b; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em; width: 80px; display:inline-block;">Tel:</strong> ' . htmlspecialchars($cot['cliente_tel']) . '</div>';
    if(!empty($cot['cliente_email'])) $cliente_info .= '<div style="margin-bottom: 2px;"><strong style="color: #64748b; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em; width: 80px; display:inline-block;">Email:</strong> <a href="mailto:'.htmlspecialchars($cot['cliente_email']).'" style="color: #2563eb; text-decoration:none;">' . htmlspecialchars($cot['cliente_email']) . '</a></div>';
    $cliente_info .= '</div>';
    
    $contenido_final = str_replace('{CLIENTE}', $cliente_info, $contenido_final);

    // Logos Shortcode
    $logos_html = '';
    if (!empty($trusted_clients)) {
        $logos_html = '<div style="display: flex; gap: 24px; flex-wrap: wrap; opacity: 0.8; align-items: center; margin-top: 10px;">';
        foreach ($trusted_clients as $trusted) {
             // Assuming simple secure URL or direct path
             $imgUrl = getSecureUrl($trusted['logo_url'], $hash);
             $logos_html .= '<img src="' . $imgUrl . '" alt="' . htmlspecialchars($trusted['nombre']) . '" style="max-height: 48px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.8;">';
        }
        $logos_html .= '</div>';
    }
    $contenido_final = str_replace('{LOGOS_CLIENTES}', $logos_html, $contenido_final);
    
    $contenido_final = str_replace('{FECHA_EMISION}', date('d/m/Y', strtotime($cot['fecha'])), $contenido_final);
    $contenido_final = str_replace('{FECHA_VENCIMIENTO}', date('d/m/Y', strtotime($cot['fecha_vencimiento'])), $contenido_final);
    $contenido_final = str_replace('{CONTACTO_TEL}', htmlspecialchars($cot['cliente_tel']), $contenido_final);
    $contenido_final = str_replace('{NOTAS}', nl2br(htmlspecialchars($cot['notas'] ?? '')), $contenido_final);
    $contenido_final = str_replace('{TABLA_PRECIOS}', $tabla_html, $contenido_final);
    $contenido_final = str_replace('{TABLA_ITEMS}', $tabla_html, $contenido_final);
    $contenido_final = str_replace('{ITEMS}', $tabla_html, $contenido_final);
    
    echo $contenido_final;
} else {
    // Vista Estándar Premium (IGUAL que propuesta.php)
    include __DIR__ . '/includes/vista_cotizacion_premium.php';
}

echo "</body></html>";
?>
