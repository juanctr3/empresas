<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$empresa_id = getEmpresaId();
$action = $_GET['action'] ?? 'kpis';
$period = $_GET['period'] ?? 'month'; // day, week, month, year

try {
    // ------------------------------------------------------------------
    // 1. KPIs Generales
    // ------------------------------------------------------------------
    if ($action === 'kpis') {
        // Rango de fechas según periodo
        $dateFilter = "";
        if ($period === 'today') $dateFilter = "AND DATE(fecha) = CURDATE()";
        elseif ($period === 'week') $dateFilter = "AND YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)";
        elseif ($period === 'month') $dateFilter = "AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
        elseif ($period === 'year') $dateFilter = "AND YEAR(fecha) = YEAR(CURDATE())";

        // Cotizaciones
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total_count,
            SUM(CASE WHEN estado = 'Aprobada' THEN 1 ELSE 0 END) as approved_count,
            SUM(total) as total_amount,
            SUM(CASE WHEN estado = 'Aprobada' THEN total ELSE 0 END) as approved_amount
            FROM cotizaciones 
            WHERE empresa_id = ? $dateFilter");
        $stmt->execute([$empresa_id]);
        $quotes = $stmt->fetch();

        // Facturación
        $dateFilterFact = str_replace('fecha', 'fecha_emision', $dateFilter);
        $stmtF = $pdo->prepare("SELECT 
            COUNT(*) as total_invoices,
            SUM(total) as total_billed,
            SUM(CASE WHEN estado = 'Pagada' THEN total ELSE 0 END) as total_paid,
            SUM(CASE WHEN estado = 'Pendiente de pago' THEN total ELSE 0 END) as total_pending
            FROM facturas 
            WHERE empresa_id = ? $dateFilterFact");
        $stmtF->execute([$empresa_id]);
        $invoices = $stmtF->fetch();

        // Clientes Nuevos
        $dateFilterCli = str_replace('fecha', 'created_at', $dateFilter);
        $stmtC = $pdo->prepare("SELECT COUNT(*) as new_clients FROM clientes WHERE empresa_id = ? AND es_cliente = 1 $dateFilterCli");
        $stmtC->execute([$empresa_id]);
        $clients = $stmtC->fetch();
        
        // Tasa de Conversión
        $conversionRate = ($quotes['total_count'] > 0) ? ($quotes['approved_count'] / $quotes['total_count']) * 100 : 0;

        echo json_encode([
            'status' => 'success',
            'data' => [
                'quotes' => [
                    'count' => (int)$quotes['total_count'],
                    'amount' => (float)$quotes['total_amount'],
                    'approved_count' => (int)$quotes['approved_count'],
                    'approved_amount' => (float)$quotes['approved_amount'],
                    'conversion_rate' => round($conversionRate, 1)
                ],
                'billing' => [
                    'billed' => (float)$invoices['total_billed'],
                    'paid' => (float)$invoices['total_paid'],
                    'pending' => (float)$invoices['total_pending']
                ],
                'clients' => [
                    'new' => (int)$clients['new_clients']
                ]
            ]
        ]);
    }

    // ------------------------------------------------------------------
    // 2. Gráficos (Evolución)
    // ------------------------------------------------------------------
    elseif ($action === 'charts') {
        
        // Configuración de Agrupamiento y Filtro
        $groupBy = "DATE(fecha)";
        $dateFilter = ""; // Default: All time or specific logic
        $limit = "LIMIT 30"; 

        if ($period === 'today') {
            // Para "Hoy", mostramos por horas si fuera necesario, pero el gráfico dice "Evolución".
            // Mejor mostrar últimos 7 días si seleccionan "Hoy" para dar contexto, o solo hoy.
            $groupBy = "DATE_FORMAT(fecha, '%H:00')";
            $dateFilter = "AND DATE(fecha) = CURDATE()";
            $limit = "";
        } elseif ($period === 'week') {
            $groupBy = "DATE(fecha)";
            $dateFilter = "AND YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)";
            $limit = "";
        } elseif ($period === 'month') {
             $groupBy = "DATE(fecha)";
             $dateFilter = "AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
             $limit = "";
        } elseif ($period === 'year') {
            $groupBy = "DATE_FORMAT(fecha, '%Y-%m')";
            $dateFilter = "AND YEAR(fecha) = YEAR(CURDATE())";
            $limit = "";
        }

        // A. Cotizaciones Enviadas vs Contratadas
        // Use explicit expressions in GROUP BY for better compatibility
        $sql = "SELECT 
            $groupBy as label,
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Aprobada' THEN 1 ELSE 0 END) as won
            FROM cotizaciones 
            WHERE empresa_id = ? $dateFilter
            GROUP BY $groupBy 
            ORDER BY $groupBy ASC 
            $limit";
            
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$empresa_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // B. Top Servicios/Productos (Mismo filtro de fecha)
        $stmtProd = $pdo->prepare("SELECT 
            cd.nombre_producto as label,
            COUNT(*) as count,
            SUM(cd.subtotal) as total_value
            FROM cotizacion_detalles cd
            JOIN cotizaciones c ON cd.cotizacion_id = c.id
            WHERE c.empresa_id = ? $dateFilter
            GROUP BY cd.nombre_producto
            ORDER BY count DESC
            LIMIT 5");
        $stmtProd->execute([$empresa_id]);
        $topProducts = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

        // C. Facturación vs Recaudo
        // Usamos facturas para facturación y estado Pagada para recaudo (simplificado)
        $groupByFact = str_replace('fecha', 'fecha_emision', $groupBy);
        $dateFilterFact = str_replace('fecha', 'fecha_emision', $dateFilter);
        
        // Use explicit expressions in GROUP BY for better compatibility
        $sqlFact = "SELECT 
            $groupByFact as label,
            SUM(total) as billed,
            SUM(CASE WHEN estado = 'Pagada' THEN total ELSE 0 END) as paid
            FROM facturas 
            WHERE empresa_id = ? $dateFilterFact
            GROUP BY $groupByFact
            ORDER BY $groupByFact ASC 
            $limit";
        $stmtFact = $pdo->prepare($sqlFact);
        $stmtFact->execute([$empresa_id]);
        $finance = $stmtFact->fetchAll(PDO::FETCH_ASSOC);

        // Debug info
        $debug = [
            'sql_finance' => $sqlFact,
            'date_filter_fact' => $dateFilterFact,
            'total_invoices_check' => $pdo->query("SELECT COUNT(*) FROM facturas WHERE empresa_id = $empresa_id")->fetchColumn()
        ];

        echo json_encode([
            'status' => 'success',
            'data' => [
                'quotes_evolution' => $history,
                'top_products' => $topProducts,
                'finance_evolution' => $finance,
                'debug' => $debug
            ]
        ]);
    }

    // ------------------------------------------------------------------
    // 3. Listas (Facturas Pendientes, etc)
    // ------------------------------------------------------------------
    elseif ($action === 'lists') {
        
        // Facturas Pendientes (Más antiguas primero que no estén pagadas ni anuladas)
        $stmtPending = $pdo->prepare("SELECT 
            f.id, f.numero_factura, f.fecha_vencimiento, f.total, f.estado,
            c.nombre as cliente_nombre, c.celular_contacto, c.email as cliente_email, f.hash_publico
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            WHERE f.empresa_id = ? 
            AND f.estado NOT IN ('Pagada', 'Anulada', 'Borrador')
            ORDER BY f.fecha_vencimiento ASC
            LIMIT 10");
        $stmtPending->execute([$empresa_id]);
        $pendingInvoices = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

        // Clientes vs Prospectos
        $stmtCli = $pdo->prepare("SELECT 
            SUM(CASE WHEN es_cliente = 1 THEN 1 ELSE 0 END) as clientes,
            SUM(CASE WHEN es_cliente = 0 THEN 1 ELSE 0 END) as prospectos
            FROM clientes WHERE empresa_id = ?");
        $stmtCli->execute([$empresa_id]);
        $clientRatio = $stmtCli->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'pending_invoices' => $pendingInvoices,
                'client_ratio' => $clientRatio
            ]
        ]);
    }

} catch (Throwable $e) {
    http_response_code(200); // Return 200 to ensure JSON is parsed by frontend logic if it expects 200
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
