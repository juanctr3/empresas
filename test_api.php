<?php
// Mock session for getEmpresaId()
session_start();
$_SESSION['empresa_id'] = 1; // Assume ID 1 for testing
$_SESSION['user_id'] = 1;
$_SESSION['user_nombre'] = 'Tester';

include 'api_crm_whatsapp.php';
// The include will execute the logic based on $_GET/$_POST
// But api_crm_whatsapp has exit; in many places.
// I'll just check if it compiles.
echo "\nCompilation OK";
unlink(__FILE__);
