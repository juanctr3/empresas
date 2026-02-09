<?php
/**
 * Herramienta de Diagnóstico para la API de Formularios
 * Este archivo ayudará a identificar por qué no se pueden visualizar las entradas.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
require_once 'includes/auth_helper.php';

$id = $_GET['id'] ?? 0;
$empresa_id = getEmpresaId();

echo "<html><head><title>Diagnóstico de Formularios</title>";
echo "<style>body{font-family:sans-serif; padding:20px; line-height:1.6;} .box{border:1px solid #ddd; padding:15px; margin-bottom:20px; border-radius:8px;} .success{color:green; font-weight:bold;} .error{color:red; font-weight:bold;} pre{background:#f4f4f4; padding:10px; border-radius:5px; overflow-x:auto;}</style>";
echo "</head><body>";

echo "<h1>Diagnóstico de Sistema de Formularios</h1>";

// 1. Verificar Sesión
echo "<div class='box'><h2>1. Verificación de Sesión</h2>";
if ($empresa_id) {
    echo "<p class='success'>Sesión activa. Empresa ID: $empresa_id</p>";
} else {
    echo "<p class='error'>No se detectó una sesión activa o empresa_id es nulo.</p>";
}
echo "</div>";

// 2. Verificar Formulario
echo "<div class='box'><h2>2. Verificación de Formulario (ID: $id)</h2>";
if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM formularios WHERE id = ?");
        $stmt->execute([$id]);
        $form = $stmt->fetch();
        if ($form) {
            echo "<p class='success'>Formulario encontrado: " . htmlspecialchars($form['titulo']) . "</p>";
            if ($form['empresa_id'] == $empresa_id) {
                echo "<p class='success'>Permiso verificado: El formulario pertenece a tu empresa.</p>";
            } else {
                echo "<p class='error'>Conflicto de permisos: El formulario (Empresa: {$form['empresa_id']}) no pertenece a tu sesión (Empresa: $empresa_id).</p>";
            }
        } else {
            echo "<p class='error'>No se encontró ningún formulario con ID $id.</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>Error al consultar DB: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>No se proporcionó un ID de formulario válido en la URL (?id=X).</p>";
}
echo "</div>";

// 3. Simular Llamada a API (get_entries)
if ($id > 0 && $empresa_id) {
    echo "<div class='box'><h2>3. Simulación de Respuesta API (get_entries)</h2>";
    echo "<p>Intentando obtener datos crudos de <code>api_forms.php?action=get_entries&id=$id</code>...</p>";
    
    // Capturamos el output de la API directamente
    ob_start();
    $_GET['action'] = 'get_entries';
    $_GET['id'] = $id;
    try {
        include 'api_forms.php';
    } catch (Exception $e) {
        echo "<p class='error'>Excepción durante include 'api_forms.php': " . $e->getMessage() . "</p>";
    }
    $raw_output = ob_get_clean();
    
    echo "<h3>Salida Cruda de la API:</h3>";
    echo "<pre>" . htmlspecialchars($raw_output) . "</pre>";
    
    $json = json_decode($raw_output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p class='success'>JSON decodificado correctamente.</p>";
        echo "<p>Entradas encontradas: " . (isset($json['data']['entries']) ? count($json['data']['entries']) : 'N/A') . "</p>";
    } else {
        echo "<p class='error'>Error al decodificar JSON: " . json_last_error_msg() . "</p>";
        echo "<p>Esto sucede generalmente si hay espacios en blanco, errores de PHP o HTML accidental en la salida de la API.</p>";
    }
    echo "</div>";
}

echo "</body></html>";
?>
