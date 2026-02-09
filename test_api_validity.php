<?php
// Mock Session
session_start();
$_SESSION['empresa_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['is_super_admin'] = true;

// Mock Environment (Use values from .env directly to bypass db.php reading if it fails, 
// though db.php SHOULD read .env. The issue was test_api_entries.php included db.php 
// but didn't set environment correctly for the CLI context maybe?)
// Let's verify if db.php loads .env correctly.
// Instead of messing with env, I'll let db.php do its job but I'll add debug output to db.php via this script wrapper? No.

// I'll just rely on the fact that the app runs on the server.
// I will simulate the REQUEST.

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_GET['action'] = 'get_entries';
$_GET['id'] = 1;

// Capture Output
ob_start();
// Use the exact path to api_forms.php
require __DIR__ . '/api_forms.php';
$output = ob_get_clean();

if (empty($output)) {
    echo "No output received.";
} else {
    echo $output;
}
?>
