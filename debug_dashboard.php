<?php
// Hardcode connection for debugging - TRY ROOT NO PASS
$host = '127.0.0.1';
$db = 'coticefacil-db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
// require_once 'db.php'; // Skip standard db.php to avoid env issues
require_once 'includes/auth_helper.php';

header('Content-Type: text/plain');

$empresa_id = 1; // getEmpresaId();

$period = 'month';

// 1. Setup Filters
echo "\n--- Setup Filters ---\n";
$groupBy = "DATE(fecha)";
$dateFilter = "AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
$limit = "";

echo "Group By: $groupBy\n";
echo "Date Filter: $dateFilter\n";

// 2. Query A: Quotes
echo "\n--- Query A: Quotes ---\n";
$sql = "SELECT 
    $groupBy as label,
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Aprobada' THEN 1 ELSE 0 END) as won
    FROM cotizaciones 
    WHERE empresa_id = ? $dateFilter
    GROUP BY label 
    ORDER BY label ASC 
    $limit";
echo "SQL: $sql\n";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empresa_id]);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Result: " . count($res) . " rows\n";
    print_r($res);
} catch (Exception $e) {
    echo "ERROR Query A: " . $e->getMessage() . "\n";
}

// 3. Query B: Top Products
echo "\n--- Query B: Top Products ---\n";
$stmtProdSql = "SELECT 
    nombre_producto as label,
    COUNT(*) as count,
    SUM(subtotal) as total_value
    FROM cotizacion_detalles cd
    JOIN cotizaciones c ON cd.cotizacion_id = c.id
    WHERE c.empresa_id = ? $dateFilter
    GROUP BY nombre_producto
    ORDER BY count DESC
    LIMIT 5";
echo "SQL: $stmtProdSql\n";

try {
    $stmtProd = $pdo->prepare($stmtProdSql);
    $stmtProd->execute([$empresa_id]);
    $resProd = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
    echo "Result: " . count($resProd) . " rows\n";
    print_r($resProd);
} catch (Exception $e) {
    echo "ERROR Query B: " . $e->getMessage() . "\n";
}

// 4. Query C: Finance
echo "\n--- Query C: Finance ---\n";
$groupByFact = str_replace('fecha', 'fecha_emision', $groupBy);
$dateFilterFact = str_replace('fecha', 'fecha_emision', $dateFilter);
echo "Group By Fact: $groupByFact\n";
echo "Date Filter Fact: $dateFilterFact\n";

$sqlFact = "SELECT 
    $groupByFact as label,
    SUM(total) as billed,
    SUM(CASE WHEN estado = 'Pagada' THEN total ELSE 0 END) as paid
    FROM facturas 
    WHERE empresa_id = ? $dateFilterFact
    GROUP BY label 
    ORDER BY label ASC 
    $limit";
echo "SQL: $sqlFact\n";

try {
    $stmtFact = $pdo->prepare($sqlFact);
    $stmtFact->execute([$empresa_id]);
    $resFact = $stmtFact->fetchAll(PDO::FETCH_ASSOC);
    echo "Result: " . count($resFact) . " rows\n";
    print_r($resFact);
} catch (Exception $e) {
    echo "ERROR Query C: " . $e->getMessage() . "\n";
}
?>
