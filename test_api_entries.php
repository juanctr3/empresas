<?php
// Mock Session
session_start();
$_SESSION['empresa_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['is_super_admin'] = true; // Bypass permission checks

// Mock Request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['PHP_SELF'] = '/api_forms.php';
$_GET['action'] = 'get_entries';
$_GET['id'] = 1; // Assuming form ID 1 exists. If not, we might get 404.

// Capture Output
ob_start();
require 'api_forms.php';
$output = ob_get_clean();

echo $output;
?>
