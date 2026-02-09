<?php
/**
 * API de Gestión de Plantillas HTML
 * Permite CRUD completo de plantillas personalizadas
 */
require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$empresa_id = getEmpresaId();

// Crear tabla si no existe (lazy migration)
$pdo->exec("CREATE TABLE IF NOT EXISTS plantillas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    empresa_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    contenido_html LONGTEXT NOT NULL,
    logo_url VARCHAR(255),
    es_activa TINYINT DEFAULT 1,
    es_default TINYINT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(empresa_id),
    INDEX(es_activa)
)");

switch ($accion) {
    case 'listar':
        $stmt = $pdo->prepare("SELECT * FROM plantillas WHERE empresa_id = ? ORDER BY es_default DESC, nombre ASC");
        $stmt->execute([$empresa_id]);
        $plantillas = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $plantillas]);
        break;

    case 'crear':
        $nombre = $_POST['nombre'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $contenido_html = $_POST['contenido_html'] ?? '';
        $logo_url = $_POST['logo_url'] ?? '';
        $es_default = isset($_POST['es_default']) ? 1 : 0;

        if (empty($nombre) || empty($contenido_html)) {
            echo json_encode(['status' => 'error', 'message' => 'El nombre y el contenido HTML son requeridos']);
            exit;
        }

        // Si se marca como default, desmarcar otras
        if ($es_default) {
            $pdo->prepare("UPDATE plantillas SET es_default = 0 WHERE empresa_id = ?")->execute([$empresa_id]);
        }

        $stmt = $pdo->prepare("INSERT INTO plantillas (empresa_id, nombre, descripcion, contenido_html, logo_url, es_default) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$empresa_id, $nombre, $descripcion, $contenido_html, $logo_url, $es_default]);

        echo json_encode(['status' => 'success', 'message' => 'Plantilla creada exitosamente', 'id' => $pdo->lastInsertId()]);
        break;

    case 'actualizar':
        $id = $_POST['id'] ?? 0;
        $nombre = $_POST['nombre'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $contenido_html = $_POST['contenido_html'] ?? '';
        $logo_url = $_POST['logo_url'] ?? '';
        $es_default = isset($_POST['es_default']) ? 1 : 0;

        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
            exit;
        }

        // Verificar que pertenece a la empresa
        $check = $pdo->prepare("SELECT id FROM plantillas WHERE id = ? AND empresa_id = ?");
        $check->execute([$id, $empresa_id]);
        if (!$check->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Plantilla no encontrada']);
            exit;
        }

        if ($es_default) {
            $pdo->prepare("UPDATE plantillas SET es_default = 0 WHERE empresa_id = ?")->execute([$empresa_id]);
        }

        $stmt = $pdo->prepare("UPDATE plantillas SET nombre = ?, descripcion = ?, contenido_html = ?, logo_url = ?, es_default = ? 
                              WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$nombre, $descripcion, $contenido_html, $logo_url, $es_default, $id, $empresa_id]);

        echo json_encode(['status' => 'success', 'message' => 'Plantilla actualizada exitosamente']);
        break;

    case 'eliminar':
        $id = $_POST['id'] ?? 0;
        
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM plantillas WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Plantilla eliminada exitosamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Plantilla no encontrada']);
        }
        break;

    case 'toggle_activa':
        $id = $_POST['id'] ?? 0;
        $es_activa = $_POST['es_activa'] ?? 1;

        $stmt = $pdo->prepare("UPDATE plantillas SET es_activa = ? WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$es_activa, $id, $empresa_id]);

        echo json_encode(['status' => 'success', 'message' => 'Estado actualizado']);
        break;

    case 'obtener':
        $id = $_GET['id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT * FROM plantillas WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        $plantilla = $stmt->fetch();

        if ($plantilla) {
            echo json_encode(['status' => 'success', 'data' => $plantilla]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Plantilla no encontrada']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
}
?>
