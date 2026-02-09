<?php
require_once 'db.php';
require_once 'includes/client_notifications.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Creation</h1>";

// Mock Session if needed
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
if (!isset($_SESSION['empresa_id'])) $_SESSION['empresa_id'] = 1;

try {
    // 1. Check a dummy client to notify
    $stmt = $pdo->query("SELECT id FROM clientes LIMIT 1");
    $cli = $stmt->fetch();

    if (!$cli) {
        die("No hay clientes para probar.");
    }

    echo "Testing enviarNotificacionBienvenida for ID: " . $cli['id'] . "<br>";
    $res = enviarNotificacionBienvenida($pdo, $cli['id']);
    echo "<pre>";
    print_r($res);
    echo "</pre>";

    echo "Testing double call to check for redeclaration error...<br>";
    $res2 = enviarNotificacionBienvenida($pdo, $cli['id']);
    echo "Success second call.<br>";

} catch (Throwable $e) {
    echo "<h2>FATAL ERROR CAUGHT:</h2>";
    echo "<p>Message: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . " on line " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
