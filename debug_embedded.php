<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Embedded Started</h1>";

require_once 'db.php';
echo "<p>DB Loaded. Empresa ID: " . (getEmpresaId() ?? 'NULL') . "</p>";

if (session_status() === PHP_SESSION_NONE) session_start();
echo "<p>Session ID: " . session_id() . "</p>";

echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'NULL') . "</p>";

try {
    require_once 'includes/auth_helper.php';
    echo "<p>AuthHelper Loaded</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>AuthHelper Error: " . $e->getMessage() . "</p>";
}

echo "<div style='background:green; color:white; padding:10px;'>If you see this, PHP is working!</div>";
?>
