<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

// Ensure admin access
AuthHelper::check();

$empresa_id = getEmpresaId();

// 1. Detect corrupted templates (Name > 100 chars or containing code-like symbols)
$stmt = $pdo->prepare("SELECT id, nombre, LENGTH(nombre) as len FROM plantillas WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$all = $stmt->fetchAll();

$deleted = 0;
$fixed = 0;

echo "<h1>Limpieza de Plantillas</h1>";
echo "<ul>";

foreach ($all as $p) {
    // Criteria for corruption: overly long name or contains function definitions
    if ($p['len'] > 100 || strpos($p['nombre'], 'function') !== false || strpos($p['nombre'], '{') !== false) {
        
        // Delete it
        $stmt_del = $pdo->prepare("DELETE FROM plantillas WHERE id = ?");
        $stmt_del->execute([$p['id']]);
        
        echo "<li style='color:red'>Eliminada plantilla corrupta ID: {$p['id']} (Longitud nombre: {$p['len']})</li>";
        $deleted++;
    }
}

echo "</ul>";

if ($deleted === 0) {
    echo "<p style='color:green'>No se encontraron plantillas corruptas.</p>";
} else {
    echo "<p style='color:blue'>Se eliminaron $deleted plantillas corruptas.</p>";
}

echo "<br><a href='plantillas.php'>Volver a Plantillas</a>";
?>
