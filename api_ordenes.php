<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'actualizar_item_ot') {
        $stmt = $pdo->prepare("UPDATE ordenes_items SET estado_item = ? WHERE id = ?");
        $stmt->execute([$_POST['estado'], $_POST['item_id']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($accion === 'asignar_responsable') {
        $ot_id = $_POST['ot_id'];
        $resp_id = $_POST['responsable_id'];
        
        $pdo->prepare("UPDATE ordenes_trabajo SET responsable_id = ? WHERE id = ?")->execute([$resp_id, $ot_id]);
        
        // Notificar al Responsable
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$resp_id]);
        $user = $stmt->fetch();
        
        if ($user && $user['telefono']) {
            require_once 'api_whatsapp.php';
            $msg = "👷‍♂️ Hola " . explode(' ', $user['nombre'])[0] . ", se te ha asignado la OT #$ot_id. Revisa los detalles aquí: " . getBaseUrl() . "/ver-orden.php?id=$ot_id";
            enviarMensajeWhatsApp($user['telefono'], $msg, null);
        }
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($accion === 'finalizar_ot') {
        $firma = $_POST['firma'] ?? null;
        $sql = "UPDATE ordenes_trabajo SET estado = 'Completada' ";
        $params = [];
        
        if ($firma) {
            $sql .= ", firma_cliente = ? ";
            $params[] = $firma;
        }
        
        $sql .= "WHERE id = ?";
        $params[] = $_POST['ot_id'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // TODO: Notificar al cliente via WhatsApp que su trabajo finalizó
        
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($accion === 'subir_evidencia') {
        if (!isset($_FILES['foto'])) die(json_encode(['status'=>'error','message'=>'No file']));
        
        $dir = 'uploads/ot/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        
        $name = 'OT_'.$_POST['ot_id'].'_'.time().'.jpg';
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir.$name)) {
            // Guardar en array JSON o campo texto simple?
            // El esquema tiene evidencia_foto LONGTEXT. 
            // Vamos a appendear si ya existe o sobreescribir? 
            // Simplificado: Sobreescribimos o concatenamos ruta.
            
            // Leemos actual
            $stmt = $pdo->prepare("SELECT evidencia_foto FROM ordenes_trabajo WHERE id = ?");
            $stmt->execute([$_POST['ot_id']]);
            $curr = $stmt->fetchColumn();
            
            $evidencias = $curr ? json_decode($curr, true) : [];
            if(!is_array($evidencias)) $evidencias = []; // fallback
            
            $evidencias[] = $dir.$name;
            
            $stmtUpd = $pdo->prepare("UPDATE ordenes_trabajo SET evidencia_foto = ? WHERE id = ?");
            $stmtUpd->execute([json_encode($evidencias), $_POST['ot_id']]);
            
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
        }
        exit;
    }
}
?>
