<?php
require_once 'db.php';
$stmt = $pdo->query("DESCRIBE cotizaciones");
echo "<pre>"; print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); echo "</pre>";
?>
