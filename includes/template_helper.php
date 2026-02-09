<?php
/**
 * Template Helper - Centralized Shortcode and Table Generation
 */

/**
 * Parsea shortcodes en un texto basándose en una cotización
 */
function parsearShortcodesCotizacion($pdo, $cot_id, $texto, $extra_codes = []) {
    if (!$cot_id) return $texto;

    // 1. Obtener datos de la cotización y empresa
    $stmt = $pdo->prepare("
        SELECT c.*, cl.nombre as cliente_nombre, cl.nit as cliente_nit, cl.celular_contacto as cliente_celular, 
               cl.email as cliente_email, cl.direccion as cliente_direccion, cl.telefono as cliente_tel,
               e.nombre as empresa_nombre, e.moneda as empresa_moneda, e.logo as empresa_logo
        FROM cotizaciones c 
        JOIN clientes cl ON c.cliente_id = cl.id 
        JOIN empresas e ON c.empresa_id = e.id
        WHERE c.id = ?
    ");
    $stmt->execute([$cot_id]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cot) return $texto;

    // 2. Preparar Reemplazos Básicos
    $reemplazos = [
        '{CLIENTE}' => $cot['cliente_nombre'],
        '{EMPRESA}' => $cot['empresa_nombre'],
        '{NUMERO_COT}' => $cot['numero_cotizacion'] ?: $cot['id'],
        '{NUMERO}' => $cot['numero_cotizacion'] ?: $cot['id'],
        '{FECHA}' => date('d/m/Y', strtotime($cot['fecha'])),
        '{TOTAL}' => $cot['empresa_moneda'] . ' ' . number_format($cot['total'], 2),
        '{SUB}' => $cot['empresa_moneda'] . ' ' . number_format($cot['subtotal'], 2),
        '{IVA}' => $cot['empresa_moneda'] . ' ' . number_format($cot['impuesto_total'] ?? ($cot['impuestos'] ?? 0), 2),
        '{NOTAS}' => $cot['notas'],
        '{EMAIL}' => $cot['cliente_email'],
        '{TEL}' => $cot['cliente_tel'] ?: ($cot['cliente_celular'] ?: ''),
        '{DIRECCION}' => $cot['cliente_direccion']
    ];

    // Combinar con extras
    $reemplazos = array_merge($reemplazos, $extra_codes);

    // 3. Generar Tablas si se solicitan
    if (strpos($texto, '{TABLA}') !== false || strpos($texto, '{TABLA_PRECIOS}') !== false || strpos($texto, '{ITEMS}') !== false) {
        // Para HTML (Email/Web)
        $tabla_html = generarTablaItemsHTML($pdo, $cot_id, $cot);
        // Para Texto (WhatsApp)
        $tabla_texto = generarTablaItemsTexto($pdo, $cot_id, $cot);

        // Detectar si el texto parece HTML
        if (preg_match('/<[a-z][\s\S]*>/i', $texto)) {
            $reemplazos['{TABLA}'] = $tabla_html;
            $reemplazos['{TABLA_PRECIOS}'] = $tabla_html;
            $reemplazos['{ITEMS}'] = $tabla_html;
        } else {
            $reemplazos['{TABLA}'] = $tabla_texto;
            $reemplazos['{TABLA_PRECIOS}'] = $tabla_texto;
            $reemplazos['{ITEMS}'] = $tabla_texto;
        }
    }

    return strtr($texto, $reemplazos);
}

/**
 * Genera el HTML de la tabla de items (Basado en propuesta.php)
 */
function generarTablaItemsHTML($pdo, $cot_id, $cot) {
    $stmtDet = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ? ORDER BY orden ASC");
    $stmtDet->execute([$cot_id]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $html = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-family: sans-serif;">';
    $html .= '<thead style="background-color: #f8fafc;">';
    $html .= '<tr>';
    $html .= '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #64748b; text-transform: uppercase;">Descripción</th>';
    $html .= '<th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #64748b; text-transform: uppercase;">Unit.</th>';
    $html .= '<th style="padding: 12px; text-align: center; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #64748b; text-transform: uppercase;">Cant.</th>';
    $html .= '<th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #64748b; text-transform: uppercase;">Total</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($detalles as $d) {
        $total_linea = $d['subtotal'] + ($d['subtotal'] * ($d['impuesto_porcentaje'] / 100));
        $html .= '<tr>';
        $html .= '<td style="padding: 12px; border-bottom: 1px solid #f1f5f9;">';
        $html .= '<div style="font-weight: bold; color: #1e293b;">' . htmlspecialchars($d['nombre_producto']) . '</div>';
        if (!empty($d['descripcion'])) {
            $html .= '<div style="font-size: 11px; color: #64748b; margin-top: 4px;">' . nl2br(htmlspecialchars($d['descripcion'])) . '</div>';
        }
        $html .= '</td>';
        $html .= '<td style="padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: right; color: #475569;">' . $cot['empresa_moneda'] . ' ' . number_format($d['precio_unitario'], 2) . '</td>';
        $html .= '<td style="padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #475569;">' . (float)$d['cantidad'] . '</td>';
        $html .= '<td style="padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: bold; color: #0f172a;">' . $cot['empresa_moneda'] . ' ' . number_format($total_linea, 2) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

/**
 * Genera una versión en texto plano para WhatsApp
 */
function generarTablaItemsTexto($pdo, $cot_id, $cot) {
    $stmtDet = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ? ORDER BY orden ASC");
    $stmtDet->execute([$cot_id]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $texto = "\n📦 *DETALLE DE PRODUCTOS:*\n";
    foreach ($detalles as $d) {
        $total_linea = $d['subtotal'] + ($d['subtotal'] * ($d['impuesto_porcentaje'] / 100));
        $texto .= "• *" . $d['nombre_producto'] . "*\n";
        $texto .= "  " . (float)$d['cantidad'] . " x " . $cot['empresa_moneda'] . " " . number_format($d['precio_unitario'], 2) . " = *" . $cot['empresa_moneda'] . " " . number_format($total_linea, 2) . "*\n";
    }

    $texto .= "\n*Subtotal:* " . $cot['empresa_moneda'] . " " . number_format($cot['subtotal'], 2) . "\n";
    $texto .= "*IVA:* " . $cot['empresa_moneda'] . " " . number_format($cot['impuesto_total'] ?? ($cot['impuestos'] ?? 0), 2) . "\n";
    $texto .= "💰 *TOTAL:* " . $cot['empresa_moneda'] . " " . number_format($cot['total'], 2) . "\n";

    return $texto;
}
