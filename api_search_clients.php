<?php
require_once 'db.php';
header('Content-Type: application/json');

$empresa_id = getEmpresaId();
$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'cliente'; // cliente | prospecto

try {
    $is_client = ($type === 'cliente') ? 1 : 0;
    $term = "%$q%";
    
    $sql = "SELECT id, nombre, identificacion, email, telefono, celular_contacto, nombre_contacto 
            FROM clientes 
            WHERE empresa_id = ? 
            AND es_cliente = ? 
            AND (nombre LIKE ? OR identificacion LIKE ? OR nombre_contacto LIKE ? OR email LIKE ?)
            ORDER BY nombre ASC LIMIT 50";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empresa_id, $is_client, $term, $term, $term, $term]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for frontend if needed, or send raw
    echo json_encode(['status' => 'success', 'data' => $results]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
