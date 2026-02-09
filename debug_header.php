<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mock session for testing
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_nombre'] = 'Test User';
$_SESSION['user_rol'] = 'admin';
$_SESSION['empresa_nombre'] = 'Test Corp';

echo "Start of debug script<br>";

try {
    require 'includes/header.php';
    echo "<br>Header included successfully";
} catch (Throwable $e) {
    echo "<br>Error: " . $e->getMessage();
    echo "<br>File: " . $e->getFile();
    echo "<br>Line: " . $e->getLine();
}

echo "<br>End of debug script";
?>
