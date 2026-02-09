<?php
require_once 'db.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$empresa_id = getEmpresaId();

if (strlen($q) < 2 || !$empresa_id) {
    echo json_encode([]);
    exit;
}

$results = [];
$limit = 5;

// 1. Buscar Clientes
try {
    $stmt = $pdo->prepare("
        SELECT id, nombre, nombre_contacto, email, telefono, celular_contacto, 'cliente' as type 
        FROM clientes 
        WHERE empresa_id = ? 
        AND (nombre LIKE ? OR nombre_contacto LIKE ? OR email LIKE ? OR telefono LIKE ? OR celular_contacto LIKE ? OR identificacion LIKE ?)
        LIMIT $limit
    ");
    $term = "%$q%";
    $stmt->execute([$empresa_id, $term, $term, $term, $term, $term, $term]);
    
    while ($row = $stmt->fetch()) {
        $results[] = [
            'value' => $row['nombre'],
            'label' => $row['nombre'],
            'sub'   => $row['nombre_contacto'] ?: $row['email'],
            'type'  => 'Cliente',
            'url'   => 'perfil-cliente.php?id=' . $row['id'],
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>'
        ];
    }
} catch (Exception $e) {}

// 2. Buscar Cotizaciones
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.numero_cotizacion, c.total, cl.nombre as cliente_nombre 
        FROM cotizaciones c
        JOIN clientes cl ON c.cliente_id = cl.id
        WHERE c.empresa_id = ? 
        AND (c.numero_cotizacion LIKE ? OR c.id LIKE ?)
        LIMIT $limit
    ");
    $term = "%$q%";
    $stmt->execute([$empresa_id, $term, $term]);
    
    while ($row = $stmt->fetch()) {
        $display = $row['numero_cotizacion'] ?: ('#' . $row['id']);
        $results[] = [
            'value' => "Cotización $display",
            'label' => "Cotización $display",
            'sub'   => $row['cliente_nombre'] . " - $" . number_format($row['total'], 0),
            'type'  => 'Cotización',
            'url'   => 'disenar-cotizacion.php?id=' . $row['id'],
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>'
        ];
    }
} catch (Exception $e) {}

// 3. Buscar Órdenes (si existe tabla)
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.numero_ot, o.descripcion, cl.nombre as cliente_nombre 
        FROM ordenes_trabajo o
        JOIN clientes cl ON o.cliente_id = cl.id
        WHERE o.empresa_id = ? 
        AND (o.numero_ot LIKE ? OR o.id LIKE ?)
        LIMIT $limit
    ");
    $term = "%$q%";
    $stmt->execute([$empresa_id, $term, $term]);
    
    while ($row = $stmt->fetch()) {
        $display = $row['numero_ot'] ?: ('#' . $row['id']);
        $results[] = [
            'value' => "OT $display",
            'label' => "Orden $display",
            'sub'   => $row['cliente_nombre'],
            'type'  => 'Orden',
            'url'   => 'ordenes.php?id=' . $row['id'], // Asumiendo que hay vista de detalle
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>'
        ];
    }
} catch (Exception $e) {}

echo json_encode($results);
