<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Método no permitido");
}

$empresa_id = getEmpresaId();
if (!$empresa_id) {
    die("Sesión expirada");
}

// 1. Obtener datos de la empresa
$stmtEmp = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmtEmp->execute([$empresa_id]);
$empresa = $stmtEmp->fetch();

// 2. Obtener datos del cliente
$cliente_id = $_POST['cliente_id'] ?? 0;
$cliente_nombre = 'Cliente No Seleccionado';
if ($cliente_id) {
    $stmtCli = $pdo->prepare("SELECT nombre FROM clientes WHERE id = ?");
    $stmtCli->execute([$cliente_id]);
    $cliente = $stmtCli->fetch();
    if ($cliente) $cliente_nombre = $cliente['nombre'];
}

// 3. Preparar Datos de Cotización (Combinar DB y POST)
$cotizacion_id = $_POST['cotizacion_id'] ?? 0;
$plantilla_id = $_POST['plantilla_id'] ?? 0;

$datos_cot = [];
$productos = $_POST['productos'] ?? [];

if ($cotizacion_id) {
    $stmtC = $pdo->prepare("
        SELECT c.*, cl.nombre as cliente_nombre_db, e.moneda as empresa_moneda, e.logo as empresa_logo_db
        FROM cotizaciones c 
        JOIN clientes cl ON c.cliente_id = cl.id 
        JOIN empresas e ON c.empresa_id = e.id 
        WHERE c.id = ?
    ");
    $stmtC->execute([$cotizacion_id]);
    $datos_cot = $stmtC->fetch();
    
    // Si obtenemos datos reales, usémoslos como base
    if ($datos_cot) {
        $cliente_nombre = $datos_cot['cliente_nombre_db'];
        
        // Cargar productos si no vienen en POST (caso carga inicial)
        if (empty($productos)) {
            $stmtDet = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ? ORDER BY orden ASC");
            $stmtDet->execute([$cotizacion_id]);
            $detalles_db = $stmtDet->fetchAll();
            
            foreach($detalles_db as $d) {
                $productos[] = [
                    'nombre' => $d['nombre_producto'],
                    'descripcion' => $d['descripcion'],
                    'cantidad' => $d['cantidad'],
                    'precio' => $d['precio_unitario'],
                    'impuesto_p' => $d['impuesto_porcentaje'],
                    'imagen' => $d['imagen'],
                    'unidad' => $d['unidad_busqueda'] ?? $d['unidad_nombre']
                ];
            }
        }
        
        // Defaults desde DB si no están en POST
        $_POST['numero_cotizacion'] = $_POST['numero_cotizacion'] ?? $datos_cot['numero_cotizacion'];
        $_POST['fecha'] = $_POST['fecha'] ?? $datos_cot['fecha'];
        $_POST['fecha_vencimiento'] = $_POST['fecha_vencimiento'] ?? $datos_cot['fecha_vencimiento'];
        $_POST['notas'] = $_POST['notas'] ?? $datos_cot['notas'];
        
        if (!isset($_POST['total_general'])) {
            $_POST['total_subtotal'] = $datos_cot['subtotal'];
            $_POST['total_impuestos'] = $datos_cot['impuesto_total'];
            $_POST['total_general'] = $datos_cot['total'];
        }
    }
}

// 4. Generar Tabla HTML (Mejorada)
$label_unidad = 'Unidad'; 

$tabla_html = '<div class="overflow-x-auto my-8 rounded-[2rem] border border-gray-100 shadow-sm bg-white overflow-hidden">';
$tabla_html .= '<table class="w-full text-left border-collapse">';
$tabla_html .= '<thead><tr class="bg-gray-50/50">
    <th class="py-5 px-6 font-black text-gray-400 uppercase text-[10px] tracking-widest">Descripción</th>
    <th class="py-5 px-4 text-center font-black text-gray-400 uppercase text-[10px] tracking-widest">Cant.</th>
    <th class="py-5 px-4 text-center font-black text-gray-400 uppercase text-[10px] tracking-widest">' . $label_unidad . '</th>
    <th class="py-5 px-6 text-right font-black text-gray-400 uppercase text-[10px] tracking-widest">Precio Unit.</th>
    <th class="py-5 px-6 text-right font-black text-gray-400 uppercase text-[10px] tracking-widest">Total</th>
</tr></thead>';
$tabla_html .= '<tbody class="divide-y divide-gray-50">';

foreach ($productos as $p) {
    if (empty($p['nombre'])) continue;
    $cantidad = (float)($p['cantidad'] ?? 1);
    $precio = (float)($p['precio'] ?? 0);
    $impuesto_p = (float)($p['impuesto_p'] ?? 0);
    $subtotal_linea = $cantidad * $precio;
    $total_linea = $subtotal_linea * (1 + ($impuesto_p / 100));

    $tabla_html .= '<tr class="hover:bg-gray-50 transition-colors group">';
    $tabla_html .= '<td class="py-6 px-6">
        <div class="flex items-center gap-4">
            ' . (!empty($p['imagen']) ? '<img src="' . getSecureUrl($p['imagen']) . '" class="w-16 h-16 rounded-xl object-cover shadow-sm border border-gray-100">' : '') . '
            <div>
                <div class="font-bold text-gray-800 text-base">' . htmlspecialchars($p['nombre']) . '</div>';
    if (!empty($p['descripcion'])) {
        $tabla_html .= '<div class="text-xs text-gray-500 mt-1 font-medium whitespace-pre-wrap">' . htmlspecialchars($p['descripcion']) . '</div>';
    }
    $tabla_html .= '</div></div></td>';
    $tabla_html .= '<td class="py-6 px-4 text-center"><span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg font-bold text-xs">' . $cantidad . '</span></td>';
    $tabla_html .= '<td class="py-6 px-4 text-center"><span class="text-gray-400 font-bold text-[10px] uppercase tracking-wider">' . htmlspecialchars($p['unidad'] ?: '-') . '</span></td>';
    $tabla_html .= '<td class="py-6 px-6 text-right font-bold text-gray-600 text-sm">$' . number_format($precio, 2) . '</td>';
    $tabla_html .= '<td class="py-6 px-6 text-right">
        <div class="font-black text-gray-900">$' . number_format($total_linea, 2) . '</div>';
    if($impuesto_p > 0) {
        $tabla_html .= '<div class="text-[9px] text-indigo-500 font-bold bg-indigo-50 inline-block px-1.5 py-0.5 rounded mt-1">IV. ' . $impuesto_p . '%</div>';
    }
    $tabla_html .= '</td></tr>';
}
$tabla_html .= '</tbody></table></div>';

// 5. Preparar Reemplazos Shortcodes
$totales = [
    'subtotal' => (float)($_POST['total_subtotal'] ?? 0),
    'impuestos' => (float)($_POST['total_impuestos'] ?? 0),
    'total' => (float)($_POST['total_general'] ?? 0),
    'moneda' => $empresa['moneda'] ?? 'USD'
];

$fecha_fmt = !empty($_POST['fecha']) ? date('d F, Y', strtotime($_POST['fecha'])) : date('d F, Y');
$venc_fmt = !empty($_POST['fecha_vencimiento']) ? date('d/m/Y', strtotime($_POST['fecha_vencimiento'])) : '-';

// Lógica de Logo: Plantilla > Empresa > Texto
$logo_src = !empty($datos_cot['empresa_logo_db']) ? $datos_cot['empresa_logo_db'] : ($empresa['logo'] ?? '');
$logo_html = $logo_src ? '<img src="' . htmlspecialchars($logo_src) . '" style="max-height: 80px; width: auto;">' : '<div style="font-weight: 900; font-size: 24px; color: #1e293b;">' . htmlspecialchars($empresa['nombre']) . '</div>';

// Obtener Clientes de Confianza
$stmtTrusted = $pdo->prepare("SELECT * FROM trusted_clients WHERE empresa_id = ? ORDER BY orden ASC");
$stmtTrusted->execute([$empresa['id']]);
$trusted_clients = $stmtTrusted->fetchAll();

$logos_html = '';
if (!empty($trusted_clients)) {
    $logos_html = '<div style="display: flex; gap: 24px; flex-wrap: wrap; opacity: 0.8; align-items: center; margin-top: 10px;">';
    foreach ($trusted_clients as $trusted) {
            $imgUrl = getSecureUrl($trusted['logo_url']);
            $logos_html .= '<img src="' . $imgUrl . '" alt="' . htmlspecialchars($trusted['nombre']) . '" style="max-height: 48px; width: auto; object-fit: contain; filter: grayscale(100%); opacity: 0.8;">';
    }
    $logos_html .= '</div>';
}

$reemplazos = [
    '{LOGO}' => $logo_html,
    '{CLIENTE}' => htmlspecialchars($cliente_nombre),
    '{EMPRESA}' => htmlspecialchars($empresa['nombre']),
    '{NUMERO_COT}' => htmlspecialchars($_POST['numero_cotizacion'] ?? 'PREVIEW'),
    '{FECHA}' => $fecha_fmt,
    '{FECHA_VENCIMIENTO}' => $venc_fmt,
    '{SUB}' => $totales['moneda'] . ' ' . number_format($totales['subtotal'], 2),
    '{IMPUESTOS}' => $totales['moneda'] . ' ' . number_format($totales['impuesto_total'] ?? ($totales['impuestos'] ?? 0), 2),
    '{IVA}' => $totales['moneda'] . ' ' . number_format($totales['impuesto_total'] ?? ($totales['impuestos'] ?? 0), 2),
    '{TOTAL}' => $totales['moneda'] . ' ' . number_format($totales['total'], 2),
    '{NOTAS}' => nl2br(htmlspecialchars($_POST['notas'] ?? '')),
    '{TABLA_PRECIOS}' => $tabla_html,
    '{LOGOS_CLIENTES}' => $logos_html,
    '{CONFIAN}' => $logos_html
];

// 6. Determinar Contenido Base
$contenido_base = "";

if (!empty($_POST['plantilla_html'])) {
    $contenido_base = $_POST['plantilla_html'];
} else if ($plantilla_id) {
    $stmtPl = $pdo->prepare("SELECT contenido_html FROM plantillas WHERE id = ?");
    $stmtPl->execute([$plantilla_id]);
    $contenido_base = $stmtPl->fetchColumn();
} 

// Fallback si no hay plantilla (Diseño por defecto)
if (empty($contenido_base)) {
    $contenido_base = '
    <div class="bg-white p-8 md:p-20 rounded-[3.5rem] shadow-2xl border border-gray-100 relative overflow-hidden font-sans">
        <div class="flex justify-between items-center mb-16">
            <div>{LOGO}</div>
            <div class="text-right">
                <div class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Cotización #</div>
                <div class="text-3xl font-black text-gray-900">{NUMERO_COT}</div>
            </div>
        </div>
        
        <div class="flex justify-between items-end mb-12 border-b border-gray-100 pb-12">
            <div>
                <p class="text-xs font-black text-indigo-500 uppercase tracking-widest mb-2">Preparado para</p>
                <h1 class="text-4xl font-black text-gray-900 leading-tight mb-2">{CLIENTE}</h1>
                <p class="text-gray-500 font-medium">{FECHA}</p>
            </div>
        </div>

        <div class="prose max-w-none text-gray-600 mb-12">
            <p>Hola <strong>{CLIENTE}</strong>, a continuación presentamos nuestra propuesta detallada:</p>
        </div>

        {TABLA_PRECIOS}

        <div class="flex flex-col md:flex-row justify-end items-center gap-12 mt-12">
            <div class="w-full md:w-80 space-y-4">
                <div class="flex justify-between text-sm font-bold text-gray-500">
                    <span>Subtotal</span>
                    <span>{SUBTOTAL}</span>
                </div>
                <div class="flex justify-between text-sm font-bold text-gray-500">
                    <span>Impuestos</span>
                    <span>{IMPUESTOS}</span>
                </div>
                <div class="flex justify-between text-2xl font-black text-gray-900 pt-4 border-t border-gray-200">
                    <span>Total</span>
                    <span>{TOTAL}</span>
                </div>
            </div>
        </div>

        <div class="mt-16 pt-8 border-t border-gray-100">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Términos y Notas</h3>
            <div class="text-sm text-gray-500 italic bg-gray-50 p-6 rounded-2xl">
                {NOTAS}
            </div>
        </div>
    </div>';
}

// 7. Renderizar Final
echo strtr($contenido_base, $reemplazos);
?>
