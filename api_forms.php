<?php
require_once 'db.php';
ob_start(); // Start buffering to catch any potential noise
require_once 'includes/auth_helper.php';
require_once 'includes/mail_helper.php';
require_once 'includes/whatsapp_helper.php';
require_once 'includes/template_helper.php';

// Auto-migration for new field properties
try {
    $cols = $pdo->query("SHOW COLUMNS FROM formulario_campos")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('icon', $cols)) $pdo->exec("ALTER TABLE formulario_campos ADD COLUMN icon VARCHAR(50) NULL AFTER type");
    if (!in_array('settings', $cols)) $pdo->exec("ALTER TABLE formulario_campos ADD COLUMN settings JSON NULL AFTER visibility_rules");
} catch (Exception $e) {}

// API Response helper
function jsonResponse($data, $code = 200) {
    // Clear any previous output (warnings, notices, whitespace)
    if (ob_get_length()) ob_clean();
    
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Get Request Data
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? $_REQUEST['action'] ?? '';

// Public Access (GET Form Config)
if ($action === 'public_get') {
    $hash = $_GET['hash'] ?? '';
    if (!$hash) jsonResponse(['error' => 'Hash missing'], 400);

    $stmt = $pdo->prepare("SELECT * FROM formularios WHERE hash_publico = ? AND is_active = 1");
    $stmt->execute([$hash]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$form) jsonResponse(['error' => 'Form not found or inactive'], 404);

    // Get Fields
    $stmtF = $pdo->prepare("SELECT * FROM formulario_campos WHERE formulario_id = ? ORDER BY order_index ASC");
    $stmtF->execute([$form['id']]);
    $form['fields'] = $stmtF->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(['status' => 'success', 'data' => $form]);
}

// Public Access (Submission)
if ($action === 'public_submit') {
    $hash = $_GET['hash'] ?? '';
    if (!$hash) jsonResponse(['error' => 'Hash missing'], 400);

    // Get Form config
    $stmt = $pdo->prepare("SELECT * FROM formularios WHERE hash_publico = ?");
    $stmt->execute([$hash]);
    $form = $stmt->fetch();

    if (!$form || !$form['is_active']) jsonResponse(['error' => 'Form not found or inactive'], 404);

    // Validate Input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) jsonResponse(['error' => 'Invalid JSON'], 400);

    // Link to Quote or OT if provided
    $cot_id = $_GET['cotizacion_id'] ?? $input['cotizacion_id'] ?? null;
    if ($cot_id === '' || $cot_id === 'null') $cot_id = null;

    $ot_id = $_GET['orden_trabajo_id'] ?? $input['orden_trabajo_id'] ?? null;
    if ($ot_id === '' || $ot_id === 'null') $ot_id = null;

    try {
        // Save Entry
        $stmtIn = $pdo->prepare("INSERT INTO formulario_entradas (formulario_id, data, cotizacion_id, orden_trabajo_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtIn->execute([
            $form['id'], 
            json_encode($input),
            $cot_id,
            $ot_id,
            $_SERVER['REMOTE_ADDR'], 
            $_SERVER['HTTP_USER_AGENT']
        ]);
        $entryId = $pdo->lastInsertId();

        // --- NOTIFICATIONS FLOW ---
        require_once 'includes/smsenlinea_helper.php';
        require_once 'includes/mail_helper.php';

        // 1. Get Company Data
        $stmtEmp = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
        $stmtEmp->execute([$form['empresa_id']]);
        $empresa = $stmtEmp->fetch();

        // 2. Get All Fields for Label Mapping and Shortcodes
        $stmtAllFields = $pdo->prepare("SELECT * FROM formulario_campos WHERE formulario_id = ?");
        $stmtAllFields->execute([$form['id']]);
        $allFields = $stmtAllFields->fetchAll(PDO::FETCH_ASSOC);

        // 3. Prepare Shortcode Mappings
        $shortcodes = [
            '[FORM_NAME]' => $form['titulo'],
            '[SUBMIT_DATE]' => date('Y-m-d H:i:s'),
            '{{empresa}}' => $empresa['nombre']
        ];
        
        $tableRows = "";
        foreach ($allFields as $f) {
            $val = $input[$f['name']] ?? '';
            if (is_array($val)) $val = implode(', ', $val);
            
            // Legacy format [field_ID]
            $shortcodes['[field_'.$f['id'].']'] = $val;
            // Native format {{stable_name}}
            $shortcodes['{{'.$f['name'].'}}'] = $val;
            // Human friendly format {{Field Label}}
            $shortcodes['{{'.$f['label'].'}}'] = $val;
            
            if (!in_array($f['type'], ['step', 'section', 'title', 'image', 'html'])) {
                $tableRows .= "<b>{$f['label']}:</b> {$val}<br>";
            }
        }

        if (!function_exists('parseTemplate')) {
            function parseTemplate($text, $codes) {
                if (empty($text)) return "";
                return strtr($text, $codes);
            }
        }

        // 4. Global Admin Notifications (Custom Templates)
        // ... (existing logic remains for b-compat)
        
        // --- NEW: Quote Shortcode Integration ---
        if ($cot_id) {
            foreach ($shortcodes as $tag => $val) {
                // Ensure we don't overwrite if helper handles it better
                // Actually, let's merge them
            }
            $emailBody = parsearShortcodesCotizacion($pdo, $cot_id, !empty($form['email_template']) ? $form['email_template'] : $tableRows, $shortcodes);
        } else {
            $emailBody = !empty($form['email_template']) ? parseTemplate($form['email_template'], $shortcodes) : $tableRows;
        }

        if (!empty($form['email_recipients'])) {
            $eRecipients = parseTemplate($form['email_recipients'], $shortcodes);
            $recipients = array_map('trim', explode(',', $eRecipients));
            foreach ($recipients as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    enviarEmail($email, "Respuesta: " . $form['titulo'], $emailBody, $empresa['id']);
                }
            }
        }

        if (!empty($form['whatsapp_recipients'])) {
            $waRecipients = parseTemplate($form['whatsapp_recipients'], $shortcodes);
            $waBody = !empty($form['whatsapp_template']) ? $form['whatsapp_template'] : "";
            if ($waBody) {
                if ($cot_id) {
                    $waBody = parsearShortcodesCotizacion($pdo, $cot_id, $waBody, $shortcodes);
                } else {
                    $waBody = parseTemplate($waBody, $shortcodes);
                }
                $recipients = array_map('trim', explode(',', $waRecipients));
                foreach ($recipients as $phone) {
                    enviarWhatsApp($pdo, $empresa['id'], $phone, $waBody);
                }
            }
        }

        // 4.5 Advanced Conditional Notifications
        if (!empty($form['advanced_notifications'])) {
            $rules = json_decode($form['advanced_notifications'], true);
            if (is_array($rules)) {
                foreach ($rules as $rule) {
                    if (evaluateNotificationRule($rule, $input, $allFields)) {
                        // Send Email
                        if (!empty($rule['email']['recipients']) && !empty($rule['email']['template'])) {
                            $eRec = parseTemplate($rule['email']['recipients'], $shortcodes);
                            $eBody = parseTemplate($rule['email']['template'], $shortcodes);
                            foreach (array_map('trim', explode(',', $eRec)) as $email) {
                                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    enviarEmail($email, "Respuesta: " . $form['titulo'], $eBody, $empresa['id']);
                                }
                            }
                        }
                        // Send WA
                        if (!empty($rule['whatsapp']['recipients']) && !empty($rule['whatsapp']['template'])) {
                            $waRec = parseTemplate($rule['whatsapp']['recipients'], $shortcodes);
                            $waB = parseTemplate($rule['whatsapp']['template'], $shortcodes);
                            foreach (array_map('trim', explode(',', $waRec)) as $phone) {
                                enviarWhatsApp($pdo, $empresa['id'], $phone, $waB);
                            }
                        }
                    }
                }
            }
        }

        // 5. Per-field User Notifications (Existing logic)
        foreach ($allFields as $field) {
            if ($field['type'] !== 'phone' && $field['type'] !== 'email') continue;
            
            $fSettings = json_decode($field['settings'] ?? '{}', true);
            if (isset($fSettings['notifications']['enabled']) && $fSettings['notifications']['enabled']) {
                $recipient = $input[$field['name']] ?? '';
                if (!$recipient) continue;

                $msgTemplate = $fSettings['notifications']['message'] ?? '';
                if (!$msgTemplate) continue;

                $parsedMsg = parseTemplate($msgTemplate, $shortcodes);
                
                if ($field['type'] === 'phone') {
                    enviarWhatsApp($pdo, $empresa['id'], $recipient, $parsedMsg);
                } elseif ($field['type'] === 'email') {
                    enviarEmail($recipient, $form['titulo'], $parsedMsg, $empresa['id']);
                }
            }
        }
        
        // 6. Automatic Quotation Approval Integration
        if ($cot_id) {
            $stmtMode = $pdo->prepare("SELECT formulario_config FROM cotizaciones WHERE id = ?");
            $stmtMode->execute([$cot_id]);
            $cotConfig = json_decode($stmtMode->fetchColumn() ?: '{}', true);

            if (($cotConfig['modo'] ?? '') === 'reemplazar_firma') {
                require_once 'includes/cotizacion_helper.php';
                
                // Find first signature field in input
                $firma_base64 = '';
                foreach ($allFields as $f) {
                    if ($f['type'] === 'signature') {
                        $firma_base64 = $input[$f['name']] ?? '';
                        if ($firma_base64) break;
                    }
                }

                if ($firma_base64 || ($cotConfig['modo'] ?? '') === 'reemplazar_firma') {
                    $acceptMetadata = [
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'ua' => $_SERVER['HTTP_USER_AGENT'],
                        'fecha' => date('Y-m-d H:i:s'),
                        'form_entry_id' => $entryId,
                        'form_id' => $form['id']
                    ];
                    
                    // Si no hubo campo de firma pero es reemplazar_firma, marcamos como firmado por formulario
                    if (empty($firma_base64)) {
                        $firma_base64 = 'data:image/png;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'; // Pixel transparente o marcador
                    }

                    procesarAceptacionCotizacion($pdo, $cot_id, $firma_base64, $acceptMetadata);
                }
            }
        }
        
        // 7. Parse Success Message
        $successMsg = $form['success_message'] ?? '¡Gracias! Tu formulario ha sido enviado.';
        $successHtml = parseTemplate($successMsg, $shortcodes);
        
        jsonResponse(['status' => 'success', 'message' => 'Entrada guardada', 'id' => $entryId, 'success_html' => $successHtml]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

// Action: upload_file (Moved here for public access)
if ($action === 'upload_file') {
    // Handle Public Form File Upload
    $hash = $_POST['hash'] ?? '';
    if (!$hash) jsonResponse(['error' => 'Hash missing'], 400);

    // Validate hash
    $stmtH = $pdo->prepare("SELECT id FROM formularios WHERE hash_publico = ? AND is_active = 1");
    $stmtH->execute([$hash]);
    $formId = $stmtH->fetchColumn();
    if (!$formId) jsonResponse(['error' => 'Invalid form'], 403);

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No file uploaded or upload error'], 400);
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Target dir
    $uploadDir = 'assets/uploads/forms/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $filename = uniqid('form_file_') . '_' . $formId . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonResponse(['status' => 'success', 'file_path' => $targetPath]);
    } else {
        jsonResponse(['error' => 'Error saving file'], 500);
    }
}

// Action: search_products (Moved here for public form usage)
if ($action === 'search_products') {
    $q = $_GET['q'] ?? '';
    $hash = $_GET['hash'] ?? '';
    if (!$hash) jsonResponse(['error' => 'Hash missing'], 400);
    
    // Validate hash
    $stmtH = $pdo->prepare("SELECT empresa_id FROM formularios WHERE hash_publico = ? AND is_active = 1");
    $stmtH->execute([$hash]);
    $form = $stmtH->fetch();
    if (!$form) jsonResponse(['error' => 'Invalid form'], 403);

    $stmt = $pdo->prepare("SELECT id, nombre, precio_base, imagen FROM productos WHERE empresa_id = ? AND (nombre LIKE ? OR descripcion LIKE ?) LIMIT 10");
    $stmt->execute([$form['empresa_id'], "%$q%", "%$q%"]);
    jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// Authenticated Access Only (Admin)
$empresa_id = getEmpresaId();
if (!$empresa_id) jsonResponse(['error' => 'Unauthorized'], 401);

if ($method === 'GET') {
    if ($action === 'get') {
        $id = $_GET['id'] ?? 0;
        // Show Single Form (with fields)
        $stmt = $pdo->prepare("SELECT * FROM formularios WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$form) jsonResponse(['error' => 'Form not found'], 404);

        // Get Fields
        $stmtF = $pdo->prepare("SELECT * FROM formulario_campos WHERE formulario_id = ? ORDER BY order_index ASC");
        $stmtF->execute([$form['id']]);
        $form['fields'] = $stmtF->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['status' => 'success', 'data' => $form]);
    } elseif ($action === 'get_entries') {
        $id = $_GET['id'] ?? 0;
        // Verify ownership
        $stmtH = $pdo->prepare("SELECT id, titulo FROM formularios WHERE id = ? AND empresa_id = ?");
        $stmtH->execute([$id, $empresa_id]);
        $form = $stmtH->fetch(PDO::FETCH_ASSOC);
        if (!$form) jsonResponse(['error' => 'Form not found'], 404);

        // Get Headers (Fields)
        $stmtC = $pdo->prepare("SELECT name, label, type FROM formulario_campos WHERE formulario_id = ? ORDER BY order_index ASC");
        $stmtC->execute([$id]);
        $fields = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        // Get Entries
        $stmtE = $pdo->prepare("SELECT * FROM formulario_entradas WHERE formulario_id = ? ORDER BY created_at DESC");
        $stmtE->execute([$id]);
        $entries = $stmtE->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'status' => 'success', 
            'data' => [
                'form' => $form,
                'fields' => $fields,
                'entries' => $entries
            ]
        ]);
    } else {
        // List Forms
        $stmt = $pdo->prepare("SELECT id, titulo, hash_publico, is_active, created_at, 
            (SELECT COUNT(*) FROM formulario_entradas WHERE formulario_id = formularios.id) as entries_count 
            FROM formularios WHERE empresa_id = ? ORDER BY created_at DESC");
        $stmt->execute([$empresa_id]);
        jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}

elseif ($method === 'POST') {
    if ($action === 'upload_icon') {
        // Handle Icon Upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'No file uploaded or upload error'], 400);
        }

        $file = $_FILES['file'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check extension
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'])) {
            jsonResponse(['error' => 'Formato no permitido'], 400);
        }

        $uploadDir = 'assets/uploads/icons/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $filename = uniqid('icon_') . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            jsonResponse(['status' => 'success', 'url' => $targetPath]);
        } else {
            jsonResponse(['error' => 'Error moviendo el archivo'], 500);
        }
    } else {
        // Handle Form Save
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Create or Update
        if (isset($data['id']) && $data['id'] > 0) {
            // Update Form
            $sql = "UPDATE formularios SET titulo=?, descripcion=?, config=?, is_active=?, submit_label=?, success_message=? WHERE id=? AND empresa_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['titulo'], 
                $data['descripcion'] ?? '', 
                json_encode($data['config'] ?? []), 
                $data['is_active'] ?? 1,
                $data['submit_label'] ?? 'Enviar Formulario',
                $data['success_message'] ?? '¡Gracias! Tu formulario ha sido enviado.',
                $data['id'],
                $empresa_id
            ]);
            $formId = $data['id'];
        } else {
            // Create Form
            $hash = bin2hex(random_bytes(16)); // Unique Public Hash
            $sql = "INSERT INTO formularios (empresa_id, titulo, descripcion, config, hash_publico, is_active, submit_label, success_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $empresa_id,
                $data['titulo'],
                $data['descripcion'] ?? '',
                json_encode($data['config'] ?? []),
                $hash,
                1,
                $data['submit_label'] ?? 'Enviar Formulario',
                $data['success_message'] ?? '¡Gracias! Tu formulario ha sido enviado.'
            ]);
            $formId = $pdo->lastInsertId();
        }

        // Save Fields
        if (isset($data['fields']) && is_array($data['fields'])) {
            $pdo->prepare("DELETE FROM formulario_campos WHERE formulario_id = ?")->execute([$formId]);
            
            $sqlF = "INSERT INTO formulario_campos (formulario_id, type, icon, label, name, placeholder, options, validation, visibility_rules, settings, order_index) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtF = $pdo->prepare($sqlF);
            
            foreach ($data['fields'] as $i => $f) {
                $stmtF->execute([
                    $formId,
                    $f['type'],
                    $f['icon'] ?? null,
                    $f['label'],
                    $f['name'] ?? 'field_'.$i,
                    $f['placeholder'] ?? '',
                    json_encode($f['options'] ?? []),
                    json_encode($f['validation'] ?? (object)[]),
                    json_encode($f['visibility_rules'] ?? (object)[]),
                    json_encode($f['settings'] ?? (object)[]),
                    $i
                ]);
            }
        }

        jsonResponse(['status' => 'success', 'id' => $formId]);
    }
}


elseif ($method === 'DELETE') {
    if ($action === 'delete_entry') {
        $id = $_GET['id'] ?? 0;
        // Verify entry belongs to a form of this company
        $stmt = $pdo->prepare("DELETE fe FROM formulario_entradas fe 
                             JOIN formularios f ON fe.formulario_id = f.id 
                             WHERE fe.id = ? AND f.empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        jsonResponse(['status' => 'success']);
    } else {
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM formularios WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        jsonResponse(['status' => 'success']);
    }
}



function evaluateNotificationRule($rule, $input, $fields) {
    if (!$rule['enabled']) return false;
    if (empty($rule['rules'])) return true; // Default to send if no conditions

    $results = [];
    foreach ($rule['rules'] as $r) {
        $fieldName = $r['field'];
        // fieldName could be f_... or an index
        if (is_numeric($fieldName)) {
            $fieldName = $fields[$fieldName]['name'] ?? '';
        }

        $val = $input[$fieldName] ?? '';
        if (is_array($val)) $val = implode(',', $val);

        $results[] = checkConditionAPI($val, $r['op'], $r['value']);
    }

    if ($rule['match'] === 'all') {
        return !in_array(false, $results, true);
    } else {
        return in_array(true, $results, true);
    }
}

function checkConditionAPI($actual, $op, $expected) {
    $actual = (string)$actual;
    $expected = (string)$expected;

    switch ($op) {
        case 'equals': return $actual == $expected;
        case 'not_equals': return $actual != $expected;
        case 'contains': return strpos($actual, $expected) !== false;
        case 'greater': return (float)$actual > (float)$expected;
        case 'less': return (float)$actual < (float)$expected;
        default: return false;
    }
}
?>
